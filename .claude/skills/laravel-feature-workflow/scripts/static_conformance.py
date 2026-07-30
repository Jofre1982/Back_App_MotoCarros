#!/usr/bin/env python3
"""Valida el spec OpenAPI (openapi.yaml) y lo cruza contra las rutas reales
de Laravel (`php artisan route:list`). No hace requests HTTP — para eso está
dynamic_conformance.py. No requiere un servidor corriendo.

Comprueba:
  - El spec es válido según la especificación OpenAPI (openapi-spec-validator).
  - Cada ruta real bajo el prefijo de la API está documentada en el spec
    (si no, ERROR: se implementó algo sin documentarlo primero).
  - Cada path documentado en el spec tiene una ruta Laravel real (si no,
    AVISO: es normal durante SDD/TDD antes de implementar, pero debe
    resolverse antes de cerrar el issue).

Uso:
    python static_conformance.py [--spec openapi.yaml] [--route-prefix api/v1]

Sale 0 si no hay errores (los avisos no cuentan para el exit code), 1 si hay
errores, 2 si hubo un problema para ejecutar (spec no encontrado, dependencias
faltantes, `php artisan` falló).
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from openapi_lite import find_repo_root, iter_operations, load_spec, normalize_path  # noqa: E402


def validate_spec_structure(spec: dict) -> list[str]:
    try:
        from openapi_spec_validator import validate
        from openapi_spec_validator.exceptions import OpenAPISpecValidatorError
    except ImportError as exc:
        raise RuntimeError(
            "Falta 'openapi-spec-validator'. Instala las dependencias con:\n"
            "  pip install -r .claude/skills/laravel-feature-workflow/scripts/requirements.txt"
        ) from exc
    try:
        validate(spec)
    except OpenAPISpecValidatorError as exc:
        return [f"spec OpenAPI inválido: {exc}"]
    return []


def laravel_routes(repo_root: Path, route_prefix: str) -> set[tuple[str, str]]:
    try:
        proc = subprocess.run(
            ["php", "artisan", "route:list", "--json"],
            cwd=repo_root,
            capture_output=True,
            text=True,
            check=True,
        )
    except FileNotFoundError as exc:
        raise RuntimeError("No se encontró `php` en el PATH.") from exc
    except subprocess.CalledProcessError as exc:
        raise RuntimeError(f"`php artisan route:list` falló: {exc.stderr.strip()}") from exc

    try:
        routes = json.loads(proc.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"No se pudo interpretar la salida de `php artisan route:list --json`: {exc}") from exc

    prefix = route_prefix.strip("/")
    result: set[tuple[str, str]] = set()
    for route in routes:
        uri = (route.get("uri") or "").strip("/")
        if not prefix or not (uri == prefix or uri.startswith(prefix + "/")):
            continue
        remainder = uri[len(prefix):].strip("/")
        for method in (route.get("method") or "").split("|"):
            method = method.strip().lower()
            if method in {"head", "options"}:
                continue
            result.add((method, normalize_path(remainder)))
    return result


def spec_operations(spec: dict) -> set[tuple[str, str]]:
    return {(method, normalize_path(path)) for path, method, _ in iter_operations(spec)}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--spec", help="Ruta al spec OpenAPI (default: openapi.yaml en la raíz del repo).")
    parser.add_argument(
        "--route-prefix",
        default="api/v1",
        help="Prefijo de rutas Laravel a comparar contra el spec (default: api/v1).",
    )
    args = parser.parse_args()

    try:
        spec = load_spec(args.spec)
    except FileNotFoundError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    try:
        errors = validate_spec_structure(spec)
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    script_dir = Path(__file__).resolve().parent
    repo_root = find_repo_root(script_dir)
    try:
        routes = laravel_routes(repo_root, args.route_prefix)
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    documented = spec_operations(spec)
    warnings: list[str] = []

    undocumented = routes - documented
    for method, path in sorted(undocumented):
        errors.append(f"ruta implementada sin documentar en el spec: {method.upper()} {path}")

    not_implemented = documented - routes
    for method, path in sorted(not_implemented):
        warnings.append(
            f"documentado en el spec pero sin ruta Laravel real: {method.upper()} {path} "
            "(esperado durante SDD/TDD antes de implementar; debe resolverse antes de cerrar el issue)"
        )

    for w in warnings:
        print(f"AVISO: {w}")
    for e in errors:
        print(f"ERROR: {e}")

    if errors:
        print(f"\n{len(errors)} error(es), {len(warnings)} aviso(s).")
        return 1

    print(f"OK: spec válido, {len(routes)} ruta(s) de API revisadas ({len(warnings)} aviso(s)).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
