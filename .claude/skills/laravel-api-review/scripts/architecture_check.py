#!/usr/bin/env python3
"""Chequea reglas de arquitectura de .claude/STANDARDS.md sobre código PHP,
usando un AST real de PHP (nikic/php-parser, vía scripts/ast_dump.php) — no
un análisis basado en regex. Requiere `php` en el PATH y `composer install`
ya corrido.

Reglas actuales:

- Controllers no deberían tener lógica de persistencia directa (Eloquent
  ::create/->save/->update/->delete o DB::table/insert/update/delete), ni
  validar inline con ->validate(), ni usar response()->json() directo; eso
  va en Actions / Form Requests / API Resources.
- Models solo deberían tener relaciones, scopes, casts y accessors simples;
  cualquier otro método con complejidad ciclomática > 3 se marca como
  sospechoso de tener lógica de negocio.
- Actions deberían tener un único método público de entrada (handle/invoke)
  y no conocer HTTP (no tipar Request/Response/JsonResponse).
- SQL crudo (DB::select/statement/raw, ->whereRaw/->havingRaw/->orWhereRaw)
  fuera de app/Actions/ se marca como señal a revisar (puede ser
  intencional).
- routes/api.php debería versionar las rutas con Route::prefix(...).

Uso:
    python architecture_check.py [ruta ...]

Por defecto analiza app/ y routes/api.php. Sale 0 si no hay violaciones,
1 si encuentra alguna o algún archivo con error de sintaxis, 2 si hubo un
problema para ejecutar.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path
from typing import Any

sys.path.insert(0, str(Path(__file__).resolve().parent))
from ast_client import iter_php_files, run_ast_dump  # noqa: E402

DEFAULT_PATHS = ["app", "routes/api.php"]

MODEL_ALLOWED_NAME_RE = re.compile(r"^(scope[A-Z]\w*|get\w+Attribute|set\w+Attribute|casts|toArray)$")
HTTP_TYPE_RE = re.compile(r"\b(Request|Response|JsonResponse)\b")


def category_for(file: Path) -> str | None:
    parts = file.parts
    if "Controllers" in parts:
        return "controller"
    if "Models" in parts:
        return "model"
    if "Actions" in parts:
        return "action"
    return None


def check_controller(file: Path, methods: list[dict[str, Any]]) -> list[str]:
    findings = []
    for m in methods:
        for call in m["persistenceCalls"]:
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() usa {call} — lógica de persistencia directa "
                "en un Controller; muévela a una Action (ver .claude/STANDARDS.md)."
            )
        if m["inlineValidate"]:
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() valida inline con ->validate(); "
                "usa un Form Request dedicado."
            )
        for _call in m["jsonResponseCalls"]:
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() usa response()->json() directo; "
                "las respuestas deberían pasar por un API Resource."
            )
    return findings


def check_model(file: Path, methods: list[dict[str, Any]]) -> list[str]:
    findings = []
    for m in methods:
        if MODEL_ALLOWED_NAME_RE.match(m["name"]) or m["relationshipCall"]:
            continue
        if m["complexity"] > 3:
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() tiene complejidad {m['complexity']} en un Model; "
                "revisa si es lógica de negocio que debería vivir en una Action."
            )
    return findings


def check_action(file: Path, methods: list[dict[str, Any]]) -> list[str]:
    findings = []
    public_entrypoints = [m for m in methods if m["visibility"] == "public" and m["name"] != "__construct"]
    if len(public_entrypoints) > 1:
        names = ", ".join(m["name"] for m in public_entrypoints)
        findings.append(
            f"{file}: expone más de un método público ({names}); una Action debería tener un único "
            "punto de entrada (handle()/__invoke())."
        )
    for m in methods:
        types_text = " ".join([*m["paramTypes"], m["returnType"] or ""])
        if HTTP_TYPE_RE.search(types_text):
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() referencia Request/Response/JsonResponse; "
                "las Actions no deben conocer HTTP (ver .claude/STANDARDS.md)."
            )
    return findings


def check_raw_sql_outside_actions(file: Path, methods: list[dict[str, Any]]) -> list[str]:
    if "Actions" in file.parts:
        return []
    findings = []
    for m in methods:
        for call in m["rawSqlCalls"]:
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() usa {call} — SQL crudo fuera de app/Actions/; "
                "puede ser intencional, pero conviene que viva en una Action."
            )
    return findings


def check_api_routes_versioned(file: Path) -> list[str]:
    code = file.read_text(encoding="utf-8")
    if "Route::prefix(" not in code:
        return [f"{file}: no versiona las rutas con Route::prefix(...) (ver .claude/STANDARDS.md: /api/v1)."]
    return []


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("paths", nargs="*", default=DEFAULT_PATHS, help="Archivos o carpetas a analizar.")
    args = parser.parse_args()

    existing_paths = [p for p in args.paths if Path(p).exists()]
    if not existing_paths:
        print(f"ERROR: ninguna de las rutas existe: {', '.join(args.paths)}", file=sys.stderr)
        return 2

    files = list(iter_php_files(existing_paths))
    if not files:
        print(f"No se encontraron archivos .php en: {', '.join(existing_paths)}")
        return 0

    try:
        results = run_ast_dump(files)
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    findings: list[str] = []
    for item in results:
        file = Path(item["file"])
        if "parseError" in item:
            findings.append(f"{file}: error de sintaxis — {item['parseError']}")
            continue

        methods = item.get("methods", [])
        category = category_for(file)

        if category == "controller":
            findings += check_controller(file, methods)
        elif category == "model":
            findings += check_model(file, methods)
        elif category == "action":
            findings += check_action(file, methods)

        findings += check_raw_sql_outside_actions(file, methods)

        if file.as_posix().endswith("routes/api.php"):
            findings += check_api_routes_versioned(file)

    for f in findings:
        print(f"ERROR: {f}")

    if findings:
        print(f"\n{len(findings)} hallazgo(s) encontrados en {len(files)} archivo(s).")
        return 1

    print(f"OK: {len(files)} archivo(s) revisados, sin hallazgos de arquitectura.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
