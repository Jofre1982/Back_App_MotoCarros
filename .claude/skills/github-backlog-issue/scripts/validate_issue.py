#!/usr/bin/env python3
"""Valida que un borrador de issue cumpla el formato definido en
.github/ISSUE_TEMPLATE/ (fuente de verdad del formato, no está duplicado aquí).

Uso:
    python validate_issue.py --type user-story borrador.md
    cat borrador.md | python validate_issue.py --type technical-task -
    python validate_issue.py --issue 42
    python validate_issue.py --type user-story --title "[US] Solicitar viaje" borrador.md

Sale con código 0 si el borrador es válido, 1 si hay errores de formato,
2 si hubo un problema para ejecutar la validación (archivo no encontrado,
templates no encontrados, `gh` no disponible, etc.).
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from dataclasses import dataclass, field
from pathlib import Path

TEMPLATE_FILES = {
    "user-story": "user-story.md",
    "technical-task": "technical-task.md",
}

TITLE_PREFIXES = {
    "user-story": "[US]",
    "technical-task": "[TASK]",
}

CHECKLIST_SECTIONS = {"Criterios de aceptación", "Definition of Done"}

# Estas secciones tienen su propio chequeo detallado en EXTRA_CHECKS (presencia +
# contenido), así que el loop genérico de "falta la sección" las salta para no
# duplicar el mismo error.
SECTIONS_WITH_DEDICATED_CHECK = {"Historia de usuario", "Objetivo"}

# Placeholder inline tipo "[campo]" que no es un checkbox ("[ ]"/"[x]") ni un
# link markdown ("[texto](url)").
_PLACEHOLDER_RE = re.compile(r"\[([^\[\]\n]{1,80})\]")
_CHECKBOX_LINE_RE = re.compile(r"^\s*-\s*\[( |x|X)\]\s*(.*)$")
_HEADER_RE = re.compile(r"^##\s+(.+?)\s*$")


@dataclass
class Section:
    header: str
    optional: bool


@dataclass
class ValidationResult:
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    @property
    def ok(self) -> bool:
        return not self.errors


def find_repo_root(start: Path) -> Path:
    """Sube desde `start` buscando la raíz del repo (carpeta con .git)."""
    for candidate in [start, *start.parents]:
        if (candidate / ".git").exists():
            return candidate
    return start


def load_templates_dir() -> Path:
    script_dir = Path(__file__).resolve().parent
    for root in (Path.cwd(), find_repo_root(script_dir)):
        templates_dir = root / ".github" / "ISSUE_TEMPLATE"
        if templates_dir.is_dir():
            return templates_dir
    raise FileNotFoundError(
        "No se encontró .github/ISSUE_TEMPLATE/. Corre este script desde la raíz "
        "del repo, o revisa que los templates existan."
    )


def strip_frontmatter(text: str) -> str:
    lines = text.splitlines()
    if lines and lines[0].strip() == "---":
        for i in range(1, len(lines)):
            if lines[i].strip() == "---":
                return "\n".join(lines[i + 1 :])
    return text


def parse_sections(template_body: str) -> list[Section]:
    lines = template_body.splitlines()
    sections: list[Section] = []
    for i, line in enumerate(lines):
        m = _HEADER_RE.match(line)
        if not m:
            continue
        header = m.group(1).strip()
        optional = i + 1 < len(lines) and lines[i + 1].strip() == "<!-- opcional -->"
        sections.append(Section(header=header, optional=optional))
    return sections


def get_section_content(body: str, header: str) -> str | None:
    lines = body.splitlines()
    start = None
    for i, line in enumerate(lines):
        m = _HEADER_RE.match(line)
        if m and m.group(1).strip().lower() == header.lower():
            start = i + 1
            break
    if start is None:
        return None
    end = len(lines)
    for i in range(start, len(lines)):
        if _HEADER_RE.match(lines[i]):
            end = i
            break
    return "\n".join(lines[start:end]).strip()


def has_required_header(body: str, header: str) -> bool:
    return any(
        _HEADER_RE.match(line) and _HEADER_RE.match(line).group(1).strip().lower() == header.lower()
        for line in body.splitlines()
    )


def find_placeholders(text: str) -> list[str]:
    found = []
    for line in text.splitlines():
        checkbox = _CHECKBOX_LINE_RE.match(line)
        # En líneas de checkbox, sólo miramos el contenido después de "- [ ] ".
        scan_text = checkbox.group(2) if checkbox else line
        for m in _PLACEHOLDER_RE.finditer(scan_text):
            inner = m.group(1)
            after = scan_text[m.end() : m.end() + 1]
            if after == "(":
                continue  # es un link markdown [texto](url)
            found.append(f"[{inner}]")
    return found


def check_checklist_has_content(body: str, header: str) -> str | None:
    content = get_section_content(body, header)
    if content is None:
        return f'Falta la sección requerida "## {header}".'
    items = [
        m.group(2).strip()
        for line in content.splitlines()
        if (m := _CHECKBOX_LINE_RE.match(line))
    ]
    if not items:
        return f'La sección "## {header}" no tiene ningún ítem tipo "- [ ] ...".'
    if not any(item and not _PLACEHOLDER_RE.fullmatch(item) for item in items):
        return f'La sección "## {header}" solo tiene placeholders sin completar.'
    return None


_HISTORIA_RE = re.compile(
    r"como\s+.+?,\s*quiero\s+.+?,\s*para\s+.+?\.", re.IGNORECASE | re.DOTALL
)


def check_user_story(body: str, result: ValidationResult) -> None:
    content = get_section_content(body, "Historia de usuario")
    if content is None:
        result.errors.append('Falta la sección requerida "## Historia de usuario".')
        return
    if not _HISTORIA_RE.search(content):
        result.errors.append(
            'La sección "## Historia de usuario" no sigue el formato '
            '"Como <rol>, quiero <capacidad>, para <beneficio>."'
        )
    if find_placeholders(content):
        result.errors.append(
            'La sección "## Historia de usuario" tiene placeholders sin completar '
            f"({', '.join(find_placeholders(content))})."
        )


def check_objective(body: str, result: ValidationResult) -> None:
    content = get_section_content(body, "Objetivo")
    if content is None:
        result.errors.append('Falta la sección requerida "## Objetivo".')
        return
    if not content or find_placeholders(content):
        result.errors.append(
            'La sección "## Objetivo" está vacía o tiene placeholders sin completar.'
        )


EXTRA_CHECKS = {
    "user-story": [check_user_story],
    "technical-task": [check_objective],
}


def validate(issue_type: str, body: str, templates_dir: Path, title: str | None) -> ValidationResult:
    result = ValidationResult()

    template_path = templates_dir / TEMPLATE_FILES[issue_type]
    template_text = strip_frontmatter(template_path.read_text(encoding="utf-8"))
    sections = parse_sections(template_text)

    for section in sections:
        if section.optional or section.header in SECTIONS_WITH_DEDICATED_CHECK:
            continue
        if section.header in CHECKLIST_SECTIONS:
            error = check_checklist_has_content(body, section.header)
            if error:
                result.errors.append(error)
        elif not has_required_header(body, section.header):
            result.errors.append(f'Falta la sección requerida "## {section.header}".')

    for check in EXTRA_CHECKS.get(issue_type, []):
        check(body, result)

    # Placeholders sueltos fuera de las secciones ya revisadas arriba.
    stray = find_placeholders(body)
    if stray:
        result.warnings.append(
            f"Posibles placeholders sin completar en el borrador: {', '.join(sorted(set(stray)))}"
        )

    if title is not None:
        prefix = TITLE_PREFIXES[issue_type]
        if not title.strip().startswith(prefix):
            result.errors.append(f'El título debería empezar con "{prefix}" (tipo {issue_type}).')
        if title.strip() == prefix:
            result.errors.append("El título tiene el prefijo pero le falta el resumen.")

    return result


def detect_type(body: str, templates_dir: Path) -> str | None:
    best_type, best_score = None, -1
    for issue_type, filename in TEMPLATE_FILES.items():
        template_text = strip_frontmatter((templates_dir / filename).read_text(encoding="utf-8"))
        required = [s.header for s in parse_sections(template_text) if not s.optional]
        score = sum(1 for h in required if has_required_header(body, h))
        if score > best_score:
            best_type, best_score = issue_type, score
    return best_type


def fetch_issue(issue_number: str) -> tuple[str, str]:
    try:
        proc = subprocess.run(
            ["gh", "issue", "view", issue_number, "--json", "title,body"],
            capture_output=True,
            text=True,
            check=True,
        )
    except FileNotFoundError as exc:
        raise RuntimeError("No se encontró `gh` (GitHub CLI) en el PATH.") from exc
    except subprocess.CalledProcessError as exc:
        raise RuntimeError(f"`gh issue view {issue_number}` falló: {exc.stderr.strip()}") from exc
    data = json.loads(proc.stdout)
    return data["title"], data["body"] or ""


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument(
        "path",
        nargs="?",
        help='Archivo con el borrador del issue, o "-" para leer de stdin.',
    )
    parser.add_argument("--issue", metavar="N", help="En vez de un archivo, valida el issue #N ya creado en GitHub.")
    parser.add_argument(
        "--type",
        choices=list(TEMPLATE_FILES),
        help="Tipo de issue. Si se omite, se intenta detectar automáticamente.",
    )
    parser.add_argument("--title", help="Título propuesto para el issue (opcional, valida el prefijo).")
    args = parser.parse_args()

    try:
        templates_dir = load_templates_dir()
    except FileNotFoundError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    try:
        if args.issue:
            title, body = fetch_issue(args.issue)
            if args.title is None:
                args.title = title
        elif args.path == "-":
            body = sys.stdin.read()
        elif args.path:
            body = Path(args.path).read_text(encoding="utf-8")
        else:
            print("ERROR: especifica un archivo, '-' para stdin, o --issue N.", file=sys.stderr)
            return 2
    except (OSError, RuntimeError, json.JSONDecodeError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    issue_type = args.type
    if issue_type is None:
        issue_type = detect_type(body, templates_dir)
        if issue_type is None:
            print(
                "ERROR: no se pudo detectar el tipo de issue automáticamente, "
                "especifica --type user-story|technical-task.",
                file=sys.stderr,
            )
            return 2
        print(f"(tipo detectado automáticamente: {issue_type})")

    result = validate(issue_type, body, templates_dir, args.title)

    for warning in result.warnings:
        print(f"AVISO: {warning}")
    for error in result.errors:
        print(f"ERROR: {error}")

    if result.ok:
        print(f"OK: el borrador cumple el formato de '{issue_type}'.")
        return 0

    print(f"\n{len(result.errors)} error(es) encontrados.")
    return 1


if __name__ == "__main__":
    sys.exit(main())
