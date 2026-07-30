#!/usr/bin/env python3
"""Reporta complejidad ciclomática y anidación excesiva en métodos PHP,
usando un AST real de PHP (nikic/php-parser, vía scripts/ast_dump.php) — no
un análisis basado en regex. Requiere `php` en el PATH y `composer install`
ya corrido (nikic/php-parser es dependencia transitiva del proyecto).

Un método flageado puede tener una razón legítima para su forma; usa criterio
antes de forzar un refactor solo para bajar el número.

Uso:
    python complexity_check.py [ruta ...] [--max-complexity 10] [--max-nesting 3]

Por defecto analiza app/. Sale 0 si no hay violaciones, 1 si encuentra alguna
violación o algún archivo con error de sintaxis, 2 si hubo un problema para
ejecutar (ruta inexistente, `php` no encontrado, etc.).
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from ast_client import iter_php_files, run_ast_dump  # noqa: E402

DEFAULT_PATHS = ["app"]


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("paths", nargs="*", default=DEFAULT_PATHS, help="Archivos o carpetas a analizar (default: app/).")
    parser.add_argument("--max-complexity", type=int, default=10, help="Complejidad ciclomática máxima permitida por método.")
    parser.add_argument("--max-nesting", type=int, default=3, help="Profundidad de anidación máxima permitida por método.")
    args = parser.parse_args()

    for p in args.paths:
        if not Path(p).exists():
            print(f"ERROR: no existe la ruta '{p}'.", file=sys.stderr)
            return 2

    files = list(iter_php_files(args.paths))
    if not files:
        print(f"No se encontraron archivos .php en: {', '.join(args.paths)}")
        return 0

    try:
        results = run_ast_dump(files)
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    problems: list[str] = []
    for item in results:
        if "parseError" in item:
            problems.append(f"{item['file']}: error de sintaxis — {item['parseError']}")
            continue
        for method in item.get("methods", []):
            complexity = method["complexity"]
            nesting = method["maxNesting"]
            if complexity > args.max_complexity:
                problems.append(
                    f"{item['file']}:{method['startLine']}: {method['name']}() complejidad ciclomática "
                    f"{complexity} > {args.max_complexity}"
                )
            if nesting > args.max_nesting:
                problems.append(
                    f"{item['file']}:{method['startLine']}: {method['name']}() anidación "
                    f"{nesting} > {args.max_nesting}"
                )

    for p in problems:
        print(f"ERROR: {p}")

    if problems:
        print(f"\n{len(problems)} problema(s) encontrados en {len(files)} archivo(s).")
        return 1

    print(
        f"OK: {len(files)} archivo(s) revisados, sin violaciones "
        f"(umbrales: complejidad<={args.max_complexity}, anidación<={args.max_nesting})."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
