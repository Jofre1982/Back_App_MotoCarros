# Known errors — skill `laravel-feature-workflow`

Este archivo registra fallos reales de `static_conformance.py`,
`dynamic_conformance.py`, o del flujo Issue → SDD → TDD → implementación →
conformidad en general: falsos positivos, falsos negativos, o pasos del flujo que no
tenían sentido para un caso real. No es un changelog de features: solo entran acá
casos concretos encontrados en uso real — no anticipaciones de errores hipotéticos.

## Cómo agregar una entrada nueva

Cuando se detecte un fallo, agrega una entrada al final con este formato:

```
### <fecha ISO> — <resumen corto del fallo>

**Qué pasó:** descripción concreta (comando corrido, spec/endpoint involucrado).
**Por qué pasó:** causa raíz, si se conoce.
**Cómo evitarlo:** instrucción concreta para la próxima vez; si el patrón es
sistemático, ajusta directamente `scripts/static_conformance.py`,
`scripts/dynamic_conformance.py`, `scripts/openapi_lite.py`, o `SKILL.md` en vez de
solo documentarlo acá.
```

---

### 2026-07-30 — `openapi-spec-validator` no expone `OpenAPIValidationError`

**Qué pasó:** la primera versión de `static_conformance.py` importaba
`from openapi_spec_validator.exceptions import OpenAPIValidationError`, que no existe
en `openapi-spec-validator` 0.9.0 instalado (`requirements.txt` solo fija
`>=0.7`). El script fallaba con `ImportError` en vez de validar el spec.

**Por qué pasó:** el nombre de la excepción real en esa versión es
`OpenAPISpecValidatorError`, no `OpenAPIValidationError` — se escribió de memoria sin
verificar contra la librería instalada.

**Cómo evitarlo:** corregido en el código (`scripts/static_conformance.py` ya usa
`OpenAPISpecValidatorError`). Si `openapi-spec-validator` cambia de nuevo su API en
una versión futura (el rango en `requirements.txt` es abierto, `>=0.7`), este mismo
síntoma (ImportError en vez de un resultado de validación) puede repetirse — si pasa,
revisar primero `python -c "import openapi_spec_validator.exceptions as e; print(dir(e))"`
en el entorno real antes de asumir el nombre de la excepción.

### 2026-07-30 — `jsonschema.RefResolver` está deprecado

**Qué pasó:** `dynamic_conformance.py` usa `jsonschema.RefResolver` para resolver
`$ref` del spec al validar respuestas. Con `jsonschema` 4.18+ esto emite un
`DeprecationWarning` en cada corrida (funciona, pero avisa que será removido en una
versión futura de la librería).

**Por qué pasó:** es la forma más simple de resolver `$ref` de OpenAPI con
`jsonschema` sin sumar la dependencia adicional `referencing` y su API. Se aceptó el
warning para mantener el script más simple en esta v1.

**Cómo evitarlo:** no es un error funcional hoy — no hace falta actuar mientras
`RefResolver` siga disponible. Si una actualización de `jsonschema` llega a removerlo
(el script empezaría a fallar con `ImportError`/`AttributeError` en vez de solo
avisar), migrar `dynamic_conformance.py` a `referencing.Registry` +
`jsonschema.validators.validator_for(schema)(schema, registry=registry)` en vez de
`RefResolver.from_schema`.
