#!/usr/bin/env python3
"""Reporta complejidad ciclomática y anidación excesiva en métodos PHP.

Es un análisis heurístico basado en texto (ver php_lite.py), no un AST real.
Útil como señal rápida, no como verdad absoluta — un método flageado puede
tener una razón legítima para su forma; usa criterio antes de forzar un
refactor solo para bajar el número.

Uso:
    python complexity_check.py [ruta ...] [--max-complexity 10] [--max-nesting 3]

Por defecto analiza app/. Sale 0 si no hay violaciones, 1 si encuentra alguna,
2 si hubo un problema para ejecutar (ruta inexistente, etc.).
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from php_lite import cyclomatic_complexity, iter_php_files, max_nesting_depth, parse_methods  # noqa: E402

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

    violations: list[str] = []
    for file in files:
        code = file.read_text(encoding="utf-8")
        for method in parse_methods(code):
            complexity = cyclomatic_complexity(method.body)
            nesting = max_nesting_depth(method.body)
            if complexity > args.max_complexity:
                violations.append(
                    f"{file}:{method.start_line}: {method.name}() complejidad ciclomática "
                    f"{complexity} > {args.max_complexity}"
                )
            if nesting > args.max_nesting:
                violations.append(
                    f"{file}:{method.start_line}: {method.name}() anidación {nesting} > {args.max_nesting}"
                )

    for v in violations:
        print(f"ERROR: {v}")

    if violations:
        print(f"\n{len(violations)} violacion(es) encontradas en {len(files)} archivo(s).")
        return 1

    print(
        f"OK: {len(files)} archivo(s) revisados, sin violaciones "
        f"(umbrales: complejidad<={args.max_complexity}, anidación<={args.max_nesting})."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
