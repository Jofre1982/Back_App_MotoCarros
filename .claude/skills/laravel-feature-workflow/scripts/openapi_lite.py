"""Utilidades compartidas para cargar y recorrer el spec OpenAPI de este
proyecto (openapi.yaml en la raíz del repo, ver .claude/STANDARDS.md).
"""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any, Iterator

import yaml

DEFAULT_SPEC_FILENAME = "openapi.yaml"

HTTP_METHODS = {"get", "post", "put", "patch", "delete", "options", "head", "trace"}

_PARAM_RE = re.compile(r"\{[^{}]+\}")


def find_repo_root(start: Path) -> Path:
    for candidate in [start, *start.parents]:
        if (candidate / ".git").exists():
            return candidate
    return start


def load_spec(path: str | None = None) -> dict[str, Any]:
    if path:
        spec_path = Path(path)
    else:
        script_dir = Path(__file__).resolve().parent
        spec_path = find_repo_root(script_dir) / DEFAULT_SPEC_FILENAME
    if not spec_path.is_file():
        raise FileNotFoundError(f"No se encontró el spec OpenAPI en '{spec_path}'.")
    with spec_path.open(encoding="utf-8") as f:
        return yaml.safe_load(f) or {}


def iter_operations(spec: dict[str, Any]) -> Iterator[tuple[str, str, dict[str, Any]]]:
    """Itera (path, method, operation) para cada operación documentada en el spec."""
    for path, item in (spec.get("paths") or {}).items():
        if not isinstance(item, dict):
            continue
        for method, operation in item.items():
            if method.lower() not in HTTP_METHODS or not isinstance(operation, dict):
                continue
            yield path, method.lower(), operation


def normalize_path(path: str) -> str:
    """Reemplaza params {algo} por un placeholder genérico para poder comparar
    paths de OpenAPI (`/rides/{rideId}`) contra URIs de Laravel (`/rides/{ride}`)
    sin que el nombre del parámetro importe."""
    normalized = path if path.startswith("/") else "/" + path
    normalized = normalized.rstrip("/") or "/"
    return _PARAM_RE.sub("{}", normalized)


def path_param_examples(operation: dict[str, Any]) -> dict[str, Any]:
    """Extrae valores de ejemplo para parámetros `in: path` desde el spec
    estándar de OpenAPI (parameters[].example o parameters[].schema.example)."""
    values: dict[str, Any] = {}
    for param in operation.get("parameters") or []:
        if not isinstance(param, dict) or param.get("in") != "path":
            continue
        name = param.get("name")
        if name is None:
            continue
        if "example" in param:
            values[name] = param["example"]
        elif isinstance(param.get("schema"), dict) and "example" in param["schema"]:
            values[name] = param["schema"]["example"]
    return values
