#!/usr/bin/env python3
"""Chequea reglas de arquitectura de .claude/STANDARDS.md sobre código PHP.

Reglas actuales (todas heurísticas basadas en texto, no un AST real):

- Controllers no deberían tener lógica de persistencia directa (Eloquent
  ::create/->save/->update/->delete o DB::table/insert/update/delete) ni
  validar inline con $request->validate(); eso va en Actions / Form Requests.
- Models solo deberían tener relaciones, scopes, casts y accessors simples;
  cualquier método con complejidad ciclomática > 3 se marca como sospechoso
  de tener lógica de negocio.
- Actions deberían tener un único método público de entrada (handle/invoke)
  y no conocer HTTP (no referenciar Request/Response/JsonResponse).
- SQL crudo (DB::select/statement/raw, ->whereRaw/->havingRaw) fuera de
  app/Actions/ se marca como señal a revisar (puede ser intencional).
- routes/api.php debería versionar las rutas con Route::prefix(...).

Uso:
    python architecture_check.py [ruta ...]

Por defecto analiza app/ y routes/api.php. Sale 0 si no hay violaciones,
1 si encuentra alguna, 2 si hubo un problema para ejecutar.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from php_lite import cyclomatic_complexity, iter_php_files, parse_methods, strip_comments_and_strings  # noqa: E402

DEFAULT_PATHS = ["app", "routes/api.php"]

MODEL_ALLOWED_NAME_RE = re.compile(
    r"^(scope[A-Z]\w*|get\w+Attribute|set\w+Attribute|casts|toArray)$"
)
RELATIONSHIP_CALL_RE = re.compile(
    r"\b(hasMany|belongsTo|hasOne|belongsToMany|morphMany|morphTo|morphOne|"
    r"hasManyThrough|hasOneThrough|morphToMany|morphedByMany)\s*\("
)
PERSISTENCE_CALL_RE = re.compile(
    r"::create\s*\(|::update\s*\(|::delete\s*\(|->save\s*\(|->delete\s*\(|"
    r"->update\s*\(|DB::table\s*\(|DB::insert\s*\(|DB::update\s*\(|DB::delete\s*\("
)
INLINE_VALIDATE_RE = re.compile(r"->validate\s*\(")
JSON_RESPONSE_RE = re.compile(r"\bresponse\s*\(\s*\)\s*->\s*json\s*\(")
RAW_SQL_RE = re.compile(r"DB::select\s*\(|DB::statement\s*\(|DB::raw\s*\(|->whereRaw\s*\(|->havingRaw\s*\(")
HTTP_AWARE_RE = re.compile(r"\b(Request|Response|JsonResponse)\b")


def line_of(stripped_code: str, index: int) -> int:
    return stripped_code.count("\n", 0, index) + 1


def findings_by_regex(stripped: str, pattern: re.Pattern, message: str) -> list[str]:
    return [f"línea {line_of(stripped, m.start())}: {message}" for m in pattern.finditer(stripped)]


def category_for(file: Path) -> str | None:
    parts = file.parts
    if "Controllers" in parts:
        return "controller"
    if "Models" in parts:
        return "model"
    if "Actions" in parts:
        return "action"
    return None


def check_controller(file: Path, stripped: str) -> list[str]:
    findings = []
    findings += findings_by_regex(
        stripped, PERSISTENCE_CALL_RE,
        "lógica de persistencia directa en un Controller; muévela a una Action (ver .claude/STANDARDS.md).",
    )
    findings += findings_by_regex(
        stripped, INLINE_VALIDATE_RE,
        "validación inline con $request->validate(); usa un Form Request dedicado.",
    )
    findings += findings_by_regex(
        stripped, JSON_RESPONSE_RE,
        "response()->json() directo; las respuestas deberían pasar por un API Resource.",
    )
    return [f"{file}: {f}" for f in findings]


def check_model(file: Path, code: str) -> list[str]:
    findings = []
    for method in parse_methods(code):
        if MODEL_ALLOWED_NAME_RE.match(method.name):
            continue
        if RELATIONSHIP_CALL_RE.search(method.body):
            continue
        complexity = cyclomatic_complexity(method.body)
        if complexity > 3:
            findings.append(
                f"{file}:{method.start_line}: {method.name}() tiene complejidad {complexity} en un Model; "
                "revisa si es lógica de negocio que debería vivir en una Action."
            )
    return findings


def check_action(file: Path, code: str) -> list[str]:
    findings = []
    methods = parse_methods(code)
    public_entrypoints = [m for m in methods if m.visibility == "public" and m.name != "__construct"]
    if len(public_entrypoints) > 1:
        names = ", ".join(m.name for m in public_entrypoints)
        findings.append(
            f"{file}: expone más de un método público ({names}); una Action debería tener un único "
            "punto de entrada (handle()/__invoke())."
        )
    for method in methods:
        if HTTP_AWARE_RE.search(method.signature):
            findings.append(
                f"{file}:{method.start_line}: {method.name}() referencia Request/Response/JsonResponse; "
                "las Actions no deben conocer HTTP (ver .claude/STANDARDS.md)."
            )
    return findings


def check_raw_sql_outside_actions(file: Path, stripped: str) -> list[str]:
    if "Actions" in file.parts:
        return []
    return [
        f"{file}: {f}"
        for f in findings_by_regex(
            stripped, RAW_SQL_RE,
            "SQL crudo fuera de app/Actions/; puede ser intencional, pero conviene que viva en una Action.",
        )
    ]


def check_api_routes_versioned(file: Path, code: str) -> list[str]:
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

    findings: list[str] = []
    for file in files:
        code = file.read_text(encoding="utf-8")
        stripped = strip_comments_and_strings(code)
        category = category_for(file)

        if category == "controller":
            findings += check_controller(file, stripped)
        elif category == "model":
            findings += check_model(file, code)
        elif category == "action":
            findings += check_action(file, code)

        findings += check_raw_sql_outside_actions(file, stripped)

        if file.as_posix().endswith("routes/api.php"):
            findings += check_api_routes_versioned(file, code)

    for f in findings:
        print(f"ERROR: {f}")

    if findings:
        print(f"\n{len(findings)} hallazgo(s) encontrados en {len(files)} archivo(s).")
        return 1

    print(f"OK: {len(files)} archivo(s) revisados, sin hallazgos de arquitectura.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
