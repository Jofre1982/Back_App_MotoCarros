#!/usr/bin/env python3
"""Prueba en vivo que las respuestas reales de la API cumplen el schema
documentado en el spec OpenAPI (openapi.yaml), usando los ejemplos del spec
como datos de entrada.

No inventa payloads ni datos de prueba: si una operación no tiene un ejemplo
de request body (requestBody.content.*.example[s]) o de parámetros de path
(parameters[].example), se omite — no se puede probar de forma confiable sin
datos válidos (constraints de negocio, foreign keys, etc.). Ver
KNOWN_ERRORS.md para las limitaciones conocidas de este enfoque.

Autenticación: si los endpoints requieren JWT, pasa el token con
--auth-token o la variable de entorno OPENAPI_CONFORMANCE_TOKEN. Este script
no hace login por sí mismo.

Uso:
    python dynamic_conformance.py [--spec openapi.yaml] [--base-url http://127.0.0.1:8000]
                                   [--api-prefix /api/v1] [--start-server] [--auth-token TOKEN]

--start-server levanta `php artisan serve` para la corrida y lo apaga al
terminar. Sin esa opción, asume que ya hay un servidor corriendo en --base-url
(por ejemplo, uno que el usuario dejó corriendo con `php artisan serve`).

Sale 0 si todas las operaciones probadas cumplen el spec, 1 si alguna falla,
2 si hubo un problema para ejecutar (dependencias faltantes, spec no
encontrado, no se pudo levantar/alcanzar el servidor).
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
import time
from pathlib import Path
from typing import Any

sys.path.insert(0, str(Path(__file__).resolve().parent))
from openapi_lite import find_repo_root, iter_operations, load_spec, path_param_examples  # noqa: E402

try:
    import requests
    from jsonschema import RefResolver, ValidationError, validate
except ImportError:
    print(
        "ERROR: faltan dependencias. Instala con:\n"
        "  pip install -r .claude/skills/laravel-feature-workflow/scripts/requirements.txt",
        file=sys.stderr,
    )
    sys.exit(2)


def start_local_server(repo_root: Path, base_url: str) -> subprocess.Popen:
    host_port = base_url.split("://", 1)[-1]
    port = host_port.split(":", 1)[1] if ":" in host_port else "8000"
    proc = subprocess.Popen(
        ["php", "artisan", "serve", "--host=127.0.0.1", f"--port={port}"],
        cwd=repo_root,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    for _ in range(30):
        try:
            requests.get(base_url, timeout=1)
            return proc
        except requests.exceptions.ConnectionError:
            time.sleep(0.5)
    proc.terminate()
    raise RuntimeError(f"No se pudo levantar `php artisan serve` en {base_url} (timeout).")


def request_body_example(operation: dict[str, Any]) -> Any:
    content = ((operation.get("requestBody") or {}).get("content")) or {}
    for media in content.values():
        if not isinstance(media, dict):
            continue
        if "example" in media:
            return media["example"]
        examples = media.get("examples")
        if examples:
            first = next(iter(examples.values()))
            if isinstance(first, dict) and "value" in first:
                return first["value"]
    return None


def response_schema_for(operation: dict[str, Any], status_code: str) -> dict[str, Any] | None:
    responses = operation.get("responses") or {}
    resp = responses.get(status_code) or responses.get("default")
    if not isinstance(resp, dict):
        return None
    content = (resp.get("content") or {}).get("application/json") or {}
    return content.get("schema")


def fill_path(path: str, params: dict[str, Any]) -> str:
    filled = path
    for name, value in params.items():
        filled = filled.replace("{" + str(name) + "}", str(value))
    return filled


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--spec", help="Ruta al spec OpenAPI (default: openapi.yaml en la raíz del repo).")
    parser.add_argument("--base-url", default="http://127.0.0.1:8000")
    parser.add_argument("--api-prefix", default="/api/v1")
    parser.add_argument("--start-server", action="store_true")
    parser.add_argument("--auth-token", default=os.environ.get("OPENAPI_CONFORMANCE_TOKEN"))
    args = parser.parse_args()

    try:
        spec = load_spec(args.spec)
    except FileNotFoundError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    script_dir = Path(__file__).resolve().parent
    repo_root = find_repo_root(script_dir)

    server_proc = None
    if args.start_server:
        try:
            server_proc = start_local_server(repo_root, args.base_url)
        except RuntimeError as exc:
            print(f"ERROR: {exc}", file=sys.stderr)
            return 2

    headers = {"Accept": "application/json"}
    if args.auth_token:
        headers["Authorization"] = f"Bearer {args.auth_token}"

    tested = 0
    skipped = 0
    failures: list[str] = []
    resolver = RefResolver.from_schema(spec)

    try:
        for path, method, operation in iter_operations(spec):
            params = path_param_examples(operation)
            url_path = fill_path(path, params)
            if "{" in url_path:
                skipped += 1
                print(f"AVISO: {method.upper()} {path} — sin ejemplo para todos los parámetros de path, se omite.")
                continue

            body = request_body_example(operation) if method in ("post", "put", "patch") else None
            if method in ("post", "put", "patch") and (operation.get("requestBody") or {}).get("required") and body is None:
                skipped += 1
                print(f"AVISO: {method.upper()} {path} — requiere body pero el spec no tiene un ejemplo, se omite.")
                continue

            url = args.base_url.rstrip("/") + "/" + args.api_prefix.strip("/") + "/" + url_path.lstrip("/")

            try:
                resp = requests.request(method, url, json=body, headers=headers, timeout=10)
            except requests.exceptions.RequestException as exc:
                failures.append(f"{method.upper()} {path}: no se pudo conectar ({exc}).")
                continue

            schema = response_schema_for(operation, str(resp.status_code))
            if schema is None:
                skipped += 1
                print(
                    f"AVISO: {method.upper()} {path} — respuesta {resp.status_code} sin schema documentado "
                    "para ese código, no se valida el body."
                )
                continue

            tested += 1
            try:
                body_json = resp.json()
            except ValueError:
                failures.append(f"{method.upper()} {path}: respuesta {resp.status_code} no es JSON válido.")
                continue

            try:
                validate(instance=body_json, schema=schema, resolver=resolver)
            except ValidationError as exc:
                failures.append(
                    f"{method.upper()} {path} ({resp.status_code}): respuesta no cumple el schema — {exc.message}"
                )
    finally:
        if server_proc:
            server_proc.terminate()
            server_proc.wait(timeout=5)

    for f in failures:
        print(f"ERROR: {f}")

    if failures:
        print(f"\n{len(failures)} fallo(s), {tested} operación(es) probadas, {skipped} omitida(s).")
        return 1

    print(f"OK: {tested} operación(es) probadas contra el spec, {skipped} omitida(s), sin fallos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
