#!/usr/bin/env python3
"""Revisión de seguridad heurística para el backend Laravel de MotoYa:
mass assignment, campos sensibles expuestos en serialización, acciones
mutantes sin autorización visible, SQL crudo con input dinámico (posible
inyección), secretos hardcodeados, y consistencia entre .env.example y las
variables que config/*.php realmente usa.

Reusa el AST real de PHP (nikic/php-parser) de la skill `laravel-api-review`
vía su ast_client.py/ast_dump.php — requiere que esa skill esté presente en
.claude/skills/laravel-api-review/.

Uso:
    python security_check.py [ruta ...] [--secrets-paths ruta ...] [--no-env-check]

Por defecto analiza app/ para mass assignment/campos expuestos/autorización/
SQL dinámico, y app/ + config/ + routes/ para secretos hardcodeados
(deliberadamente se excluyen tests/ y database/ del scan de secretos por
defecto — ahí es común tener valores de prueba literales que no son un
secreto real, ver KNOWN_ERRORS.md).

El chequeo de .env.example vs config/*.php tiene dos niveles de confianza
deliberadamente distintos (ver check_env_consistency): una clave en
.env.example que ya nadie lee es un ERROR (señal confiable, sin ruido); una
env() sin default que no está documentada es solo un AVISO, porque Laravel
declara así, por diseño, decenas de variables de drivers opcionales (AWS,
Postmark, Memcached, etc.) donde no tener valor es válido — tratarlo como
error produciría ruido masivo en cualquier proyecto Laravel estándar.

Sale 0 si no hay errores (los avisos no cuentan), 1 si hay errores, 2 si
hubo un problema para ejecutar (dependencia de laravel-api-review faltante,
`php` no encontrado, etc.).
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path
from typing import Any

_SCRIPT_DIR = Path(__file__).resolve().parent
_API_REVIEW_SCRIPTS = _SCRIPT_DIR.parents[1] / "laravel-api-review" / "scripts"
sys.path.insert(0, str(_API_REVIEW_SCRIPTS))

try:
    from ast_client import iter_php_files, run_ast_dump  # noqa: E402
except ImportError:
    print(
        "ERROR: no se pudo importar ast_client de la skill laravel-api-review "
        f"(se esperaba en '{_API_REVIEW_SCRIPTS}'). Esta skill reusa esos scripts "
        "para el análisis AST — confirma que .claude/skills/laravel-api-review/ existe.",
        file=sys.stderr,
    )
    sys.exit(2)

DEFAULT_PATHS = ["app"]
DEFAULT_SECRETS_PATHS = ["app", "config", "routes"]
DEFAULT_CONFIG_PATH = "config"
DEFAULT_ENV_EXAMPLE_PATH = ".env.example"

ENV_CALL_RE = re.compile(r"\benv\(\s*['\"]([A-Z][A-Z0-9_]*)['\"]\s*(,|\))")
ENV_EXAMPLE_KEY_RE = re.compile(r"^([A-Z][A-Z0-9_]*)=")

# Familias de variables de servicios/drivers opcionales que Laravel declara
# por defecto en config/*.php sin fallback — no tener valor es válido (el
# driver simplemente no se usa). Ver docstring del módulo y KNOWN_ERRORS.md:
# medido empíricamente contra el skeleton de Laravel, sin este filtro el
# chequeo "sin default y no documentada" produce ~25 falsos positivos en un
# proyecto Laravel completamente estándar.
OPTIONAL_SERVICE_PREFIXES = (
    "AWS_", "MEMCACHED_", "POSTMARK_", "SLACK_", "PAPERTRAIL_", "DYNAMODB_",
    "SQS_", "MYSQL_ATTR_", "DB_CACHE_", "DB_QUEUE_", "SESSION_", "MAIL_",
    "SES_", "SPARKPOST_", "LOG_", "RESEND_",
)

SENSITIVE_FIELD_RE = re.compile(
    r"(?i)(password|token|secret|api[_-]?key|jwt|ssn|credit[_-]?card|card[_-]?number|cvv|pin|private[_-]?key)"
)

AWS_KEY_RE = re.compile(r"\bAKIA[0-9A-Z]{16}\b")

SENSITIVE_LITERAL_RE = re.compile(
    r"(?i)\b(secret|password|api[_-]?key|token|private[_-]?key|access[_-]?key)\b"
    r"['\"]?\s*(?:=>|=)\s*['\"]([A-Za-z0-9+/_\-]{12,})['\"]"
)


def category_for(file: Path) -> str | None:
    parts = file.parts
    if "Controllers" in parts:
        return "controller"
    if "Models" in parts:
        return "model"
    return None


def check_mass_assignment(file: Path, item: dict[str, Any]) -> list[str]:
    if item.get("guarded") == []:
        return [
            f"{file}: $guarded = [] (o #[Guarded([])]) deshabilita la protección de mass assignment "
            "por completo — define $fillable en su lugar, o un $guarded específico."
        ]
    return []


def check_exposed_fields(file: Path, item: dict[str, Any]) -> list[str]:
    fillable = item.get("fillable") or []
    hidden = set(item.get("hidden") or [])
    findings = []
    for field in fillable:
        if SENSITIVE_FIELD_RE.search(field) and field not in hidden:
            findings.append(
                f"{file}: el campo '{field}' parece sensible y es fillable pero no está en "
                "$hidden/#[Hidden] — probablemente se esté serializando en las respuestas de la API."
            )
    return findings


def check_authorization(file: Path, methods: list[dict[str, Any]]) -> list[str]:
    findings = []
    for m in methods:
        if m["persistenceCalls"] and not m["authorizationCall"]:
            calls = ", ".join(m["persistenceCalls"])
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() muta datos ({calls}) sin una llamada de "
                "autorización visible (->authorize()/Gate::.../->can()) en el método — confirma que está "
                "cubierta por una Policy, un Form Request, o middleware a nivel de ruta."
            )
    return findings


def check_dynamic_raw_sql(file: Path, methods: list[dict[str, Any]]) -> list[str]:
    findings = []
    for m in methods:
        for call in m["dynamicRawSqlCalls"]:
            findings.append(
                f"{file}:{m['startLine']}: {m['name']}() usa {call} con un argumento que no es un string "
                "literal estático — riesgo de inyección SQL si ese valor viene de input externo sin "
                "parametrizar (usa bindings, ej. DB::select('... WHERE x = ?', [$valor]))."
            )
    return findings


def check_hardcoded_secrets(files: list[Path]) -> list[str]:
    findings = []
    for file in files:
        try:
            text = file.read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError):
            continue
        for lineno, line in enumerate(text.splitlines(), start=1):
            if AWS_KEY_RE.search(line):
                findings.append(f"{file}:{lineno}: posible AWS access key hardcodeada.")
            m = SENSITIVE_LITERAL_RE.search(line)
            if m:
                findings.append(
                    f"{file}:{lineno}: posible secreto hardcodeado (palabra clave '{m.group(1)}' "
                    "seguida de un valor literal)."
                )
    return findings


def _collect_env_calls(config_dir: Path) -> dict[str, bool]:
    """Escanea *.php en config_dir y devuelve {nombre_env: tiene_default}."""
    used: dict[str, bool] = {}
    if not config_dir.is_dir():
        return used
    for php_file in sorted(config_dir.glob("*.php")):
        text = php_file.read_text(encoding="utf-8")
        for m in ENV_CALL_RE.finditer(text):
            name, closer = m.group(1), m.group(2)
            has_default = closer == ","
            used[name] = used.get(name, False) or has_default
    return used


def check_env_consistency(config_path: Path, env_example_path: Path) -> tuple[list[str], list[str]]:
    """Compara las env() usadas en config/*.php contra las claves declaradas
    en .env.example. Devuelve (errores, avisos) — ver docstring del módulo
    para por qué las dos direcciones tienen confianza distinta.

    Laravel 11+ ya no publica config/*.php para cada feature por defecto —
    el framework trae sus propios defaults internos en
    vendor/laravel/framework/config/*.php (ej. BCRYPT_ROUNDS vía
    hashing.php, BROADCAST_CONNECTION vía broadcasting.php) que se usan
    igual aunque el archivo no esté publicado en config/. Por eso el chequeo
    de "obsoleta" considera también esos defaults del framework — si solo
    mirara config/ del proyecto, marcaría como muertas variables que
    Laravel sigue leyendo internamente (falso positivo real, encontrado
    probando esto contra el repo — ver KNOWN_ERRORS.md).
    """
    if not config_path.is_dir() or not env_example_path.is_file():
        return [], []

    used = _collect_env_calls(config_path)
    repo_root = config_path.resolve().parent
    framework_defaults = _collect_env_calls(repo_root / "vendor" / "laravel" / "framework" / "config")
    known_anywhere = set(used) | set(framework_defaults)

    documented: set[str] = set()
    for line in env_example_path.read_text(encoding="utf-8").splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue
        m = ENV_EXAMPLE_KEY_RE.match(stripped)
        if m:
            documented.add(m.group(1))

    errors = []
    stale = sorted(documented - known_anywhere)
    for name in stale:
        errors.append(
            f"{env_example_path}: '{name}' está documentada pero nada la usa — ni {config_path}/*.php ni "
            "los defaults internos de Laravel — probablemente quedó obsoleta (ver KNOWN_ERRORS.md, ya pasó "
            "con VITE_APP_NAME al quitar Vite)."
        )

    warnings = []
    for name, has_default in sorted(used.items()):
        if has_default or name in documented:
            continue
        if name.startswith(OPTIONAL_SERVICE_PREFIXES):
            continue
        warnings.append(
            f"env('{name}') en {config_path}/*.php no tiene default y no está en {env_example_path} — "
            "revisa si es realmente requerida; si sí, documéntala."
        )

    return errors, warnings


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument(
        "paths", nargs="*", default=DEFAULT_PATHS,
        help="Archivos o carpetas para mass assignment/campos expuestos/autorización/SQL (default: app/).",
    )
    parser.add_argument(
        "--secrets-paths", nargs="*", default=DEFAULT_SECRETS_PATHS,
        help="Rutas a escanear en busca de secretos hardcodeados (default: app config routes).",
    )
    parser.add_argument("--config-path", default=DEFAULT_CONFIG_PATH, help="Carpeta de config (default: config).")
    parser.add_argument("--env-example-path", default=DEFAULT_ENV_EXAMPLE_PATH, help="Ruta a .env.example.")
    parser.add_argument("--no-env-check", action="store_true", help="Omite el chequeo de consistencia .env.example vs config/.")
    args = parser.parse_args()

    valid_paths = [p for p in args.paths if Path(p).exists()]
    for p in args.paths:
        if p not in valid_paths:
            print(f"AVISO: la ruta '{p}' no existe, se omite.", file=sys.stderr)

    errors: list[str] = []
    warnings: list[str] = []

    files = list(iter_php_files(valid_paths))
    if files:
        try:
            results = run_ast_dump(files)
        except RuntimeError as exc:
            print(f"ERROR: {exc}", file=sys.stderr)
            return 2

        for item in results:
            file = Path(item["file"])
            if "parseError" in item:
                errors.append(f"{file}: error de sintaxis — {item['parseError']}")
                continue

            methods = item.get("methods", [])
            category = category_for(file)

            if category == "model":
                errors += check_mass_assignment(file, item)
                errors += check_exposed_fields(file, item)
            if category == "controller":
                warnings += check_authorization(file, methods)

            errors += check_dynamic_raw_sql(file, methods)

    secrets_valid_paths = [p for p in args.secrets_paths if Path(p).exists()]
    secret_files = list(iter_php_files(secrets_valid_paths))
    errors += check_hardcoded_secrets(secret_files)

    if not args.no_env_check:
        env_errors, env_warnings = check_env_consistency(Path(args.config_path), Path(args.env_example_path))
        errors += env_errors
        warnings += env_warnings

    for w in warnings:
        print(f"AVISO: {w}")
    for e in errors:
        print(f"ERROR: {e}")

    if errors:
        print(f"\n{len(errors)} error(es), {len(warnings)} aviso(s).")
        return 1

    print(
        f"OK: {len(files)} archivo(s) revisados (mass assignment/autorización/SQL), "
        f"{len(secret_files)} archivo(s) revisados (secretos) — {len(warnings)} aviso(s)."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
