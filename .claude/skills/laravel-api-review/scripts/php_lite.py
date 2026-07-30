"""Utilidades mínimas para inspeccionar código PHP sin un parser completo.

Es un análisis heurístico basado en texto: se "vacían" comentarios y strings
(se reemplazan por espacios, preservando saltos de línea) y después se cuentan
llaves y palabras clave. No es un AST real — ver las limitaciones documentadas
en SKILL.md y KNOWN_ERRORS.md antes de confiar ciegamente en los resultados.
"""

from __future__ import annotations

import re
from dataclasses import dataclass


def strip_comments_and_strings(code: str) -> str:
    """Reemplaza comentarios y contenido de strings por espacios, preservando
    saltos de línea (para que los números de línea calculados después sigan
    siendo correctos). No maneja heredoc/nowdoc (<<<EOT ... EOT) — ver
    KNOWN_ERRORS.md.
    """
    out: list[str] = []
    i = 0
    n = len(code)
    in_string: str | None = None
    while i < n:
        c = code[i]
        if in_string:
            if c == "\\" and i + 1 < n:
                out.append(" " if c != "\n" else "\n")
                i += 1
                out.append(" " if code[i] != "\n" else "\n")
                i += 1
                continue
            if c == in_string:
                in_string = None
            out.append(" " if c != "\n" else "\n")
            i += 1
            continue
        if c == "/" and i + 1 < n and code[i + 1] == "/":
            while i < n and code[i] != "\n":
                out.append(" ")
                i += 1
            continue
        if c == "#" and not (i + 1 < n and code[i + 1] == "["):
            while i < n and code[i] != "\n":
                out.append(" ")
                i += 1
            continue
        if c == "/" and i + 1 < n and code[i + 1] == "*":
            out.append(" ")
            out.append(" ")
            i += 2
            while i < n and not (code[i] == "*" and i + 1 < n and code[i + 1] == "/"):
                out.append(" " if code[i] != "\n" else "\n")
                i += 1
            if i < n:
                out.append(" ")
                out.append(" ")
                i += 2
            continue
        if c in ("'", '"'):
            in_string = c
            out.append(" ")
            i += 1
            continue
        out.append(c)
        i += 1
    return "".join(out)


@dataclass
class MethodInfo:
    name: str
    visibility: str  # "public" | "protected" | "private" | "" (no declarada)
    is_static: bool
    signature: str  # modificadores + parámetros + tipo de retorno
    body: str  # cuerpo, ya "vaciado" de comentarios/strings
    start_line: int
    end_line: int


DECISION_RE = re.compile(r"\b(if|elseif|for|foreach|while|case|catch)\b")
LOGICAL_OP_RE = re.compile(r"&&|\|\|")

_METHOD_SIG_RE = re.compile(
    r"(?P<mods>(?:(?:public|protected|private|static|final|abstract)\s+)*)"
    r"function\s+(?P<name>\w+)\s*\("
)


def cyclomatic_complexity(body: str) -> int:
    """Aproximación de complejidad ciclomática de McCabe: 1 + puntos de
    decisión (if/elseif/for/foreach/while/case/catch/&&/||). No cuenta
    ternarios (?:) ni `match` de PHP 8.1+ — ver KNOWN_ERRORS.md.
    """
    return 1 + len(DECISION_RE.findall(body)) + len(LOGICAL_OP_RE.findall(body))


def max_nesting_depth(body: str) -> int:
    """Profundidad máxima de llaves `{}` dentro del cuerpo. Es una aproximación
    de anidación de estructuras de control: no distingue un `if` anidado de un
    closure o una clase anónima que también abren `{}` — ver KNOWN_ERRORS.md.
    """
    depth = 0
    max_depth = 0
    for ch in body:
        if ch == "{":
            depth += 1
            max_depth = max(max_depth, depth)
        elif ch == "}":
            depth = max(depth - 1, 0)
    return max_depth


def _find_matching(text: str, open_index: int, open_ch: str, close_ch: str) -> int:
    depth = 0
    i = open_index
    n = len(text)
    while i < n:
        if text[i] == open_ch:
            depth += 1
        elif text[i] == close_ch:
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return -1


def parse_methods(code: str) -> list[MethodInfo]:
    """Extrae métodos/funciones con nombre y cuerpo con llaves `{ }`.

    No extrae closures anónimos (`function () {...}` sin nombre, ni arrow
    functions `fn() => ...`) — ver KNOWN_ERRORS.md. Para el código de dominio
    de este proyecto (Actions/Controllers/Models) eso cubre lo relevante,
    porque la lógica de negocio vive en métodos con nombre, no en closures.
    """
    stripped = strip_comments_and_strings(code)
    methods: list[MethodInfo] = []
    for m in _METHOD_SIG_RE.finditer(stripped):
        paren_open = m.end() - 1
        paren_close = _find_matching(stripped, paren_open, "(", ")")
        if paren_close == -1:
            continue
        j = paren_close + 1
        while j < len(stripped) and stripped[j] not in "{;":
            j += 1
        if j >= len(stripped) or stripped[j] == ";":
            continue  # método abstracto / de interfaz, sin cuerpo
        body_open = j
        body_close = _find_matching(stripped, body_open, "{", "}")
        if body_close == -1:
            continue
        mods = m.group("mods")
        visibility = next((v for v in ("public", "protected", "private") if v in mods), "")
        signature = stripped[m.start():body_open]
        body = stripped[body_open + 1:body_close]
        start_line = stripped.count("\n", 0, m.start()) + 1
        end_line = stripped.count("\n", 0, body_close) + 1
        methods.append(
            MethodInfo(
                name=m.group("name"),
                visibility=visibility,
                is_static="static" in mods,
                signature=signature,
                body=body,
                start_line=start_line,
                end_line=end_line,
            )
        )
    return methods


def find_class_name(code: str) -> str | None:
    m = re.search(r"\bclass\s+(\w+)", strip_comments_and_strings(code))
    return m.group(1) if m else None


def iter_php_files(paths: list[str]):
    from pathlib import Path

    for p in paths:
        path = Path(p)
        if path.is_file() and path.suffix == ".php":
            yield path
        elif path.is_dir():
            yield from sorted(path.rglob("*.php"))
