"""Cliente Python para scripts/ast_dump.php: corre el parser PHP real
(nikic/php-parser) sobre archivos .php y devuelve la info estructurada como
diccionarios Python. Reemplaza el enfoque v1 basado en regex (ver
KNOWN_ERRORS.md y git history) — esto entiende PHP de verdad.
"""

from __future__ import annotations

import json
import subprocess
from pathlib import Path
from typing import Any, Iterator


def find_repo_root(start: Path) -> Path:
    for candidate in [start, *start.parents]:
        if (candidate / ".git").exists():
            return candidate
    return start


def iter_php_files(paths: list[str]) -> Iterator[Path]:
    for p in paths:
        path = Path(p)
        if path.is_file() and path.suffix == ".php":
            yield path
        elif path.is_dir():
            yield from sorted(path.rglob("*.php"))


def run_ast_dump(files: list[Path]) -> list[dict[str, Any]]:
    """Corre ast_dump.php sobre `files` y devuelve la lista de resultados por
    archivo (en el mismo orden). Cada item tiene "file" y, o bien "methods"
    (lista de métodos analizados) o "parseError" (si el archivo no es PHP
    válido) — el caller decide cómo reportar cada caso.

    Lanza RuntimeError si `php` no está disponible, el script no se
    encuentra, o la salida no se pudo interpretar como JSON.
    """
    if not files:
        return []
    script_dir = Path(__file__).resolve().parent
    ast_dump = script_dir / "ast_dump.php"
    if not ast_dump.is_file():
        raise RuntimeError(f"No se encontró '{ast_dump}'.")
    try:
        proc = subprocess.run(
            ["php", str(ast_dump), *(str(f) for f in files)],
            capture_output=True,
            text=True,
        )
    except FileNotFoundError as exc:
        raise RuntimeError("No se encontró `php` en el PATH.") from exc
    # exit 1 de ast_dump.php significa "algún archivo tuvo error de sintaxis,
    # pero igual imprimió el JSON con el resto" — no es un fallo de ejecución.
    if proc.returncode not in (0, 1):
        raise RuntimeError(f"ast_dump.php falló (exit {proc.returncode}): {proc.stderr.strip()}")
    try:
        return json.loads(proc.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"No se pudo interpretar la salida de ast_dump.php: {exc}\n{proc.stderr}") from exc
