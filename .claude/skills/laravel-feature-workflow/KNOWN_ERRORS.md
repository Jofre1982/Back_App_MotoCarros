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

### 2026-07-31 — `dynamic_conformance.py` da OK sin haber probado ningún 200 autenticado

**Qué pasó:** implementando `GET /me` (#10), correr
`dynamic_conformance.py --start-server --auth-token <jwt>` reportó
`7 operación(es) probadas, sin fallos`, pero el 200 de `/me` **nunca se ejecutó**: el
script recorre las operaciones en el orden del spec, `POST /auth/logout` va antes que
`/me` y usa el mismo token, así que lo deja en la blacklist. `/me` recibió 401, que
también está documentado y valida contra el schema `Error` — y el resultado global fue
verde igual.

**Por qué pasó:** el script comparte un único token entre todas las operaciones y no
sabe que una de ellas lo invalida. El OK es real (la respuesta cumplió *un* schema
documentado), pero no dice nada del camino de éxito, que es justo lo que la feature
necesitaba verificar. Es un falso "verde por omisión", no un falso positivo.

**Cómo evitarlo:** el `OK: N operación(es)` no alcanza para dar por validada una
feature autenticada — hay que confirmar **qué código de estado** respondió cada
operación propia, no solo que no hubo fallos. Mientras el script no lo reporte por
operación, validar el cuerpo de éxito aparte: cargar `openapi.yaml`, sacar el schema de
la respuesta 200 y correr `jsonschema.validate` sobre la forma real (una por rama del
contrato: conductor con perfil y pasajero sin él), incluyendo una contra-prueba que el
schema **rechace** un cuerpo inválido — sin ella no se distingue un schema correcto de
uno vacuamente permisivo. Alternativa de fondo, si el patrón se repite: que
`dynamic_conformance.py` pida un token nuevo por operación, o que deje `/auth/logout`
para el final.

### 2026-07-31 — El "verde por omisión" del token compartido se repitió en #12

**Qué pasó:** el mismo caso de la entrada anterior, ahora con `POST /me/vehicle` (#12):
`dynamic_conformance.py --start-server` con un token de conductor reportó
`OK: 7 operación(es) probadas, sin fallos`, y el 201 del endpoint nuevo **no se
ejecutó ni una vez** — `POST /auth/logout` va antes en el orden del spec y dejó el
token en la blacklist, así que `/me/vehicle` respondió el 401 que también está
documentado. Se detectó porque la tabla `vehicles` quedó vacía después de la corrida.

**Por qué pasó:** misma causa que en #10 — un único token compartido entre operaciones,
una de las cuales lo invalida. Con esto deja de ser un caso aislado: le pasa a **toda**
feature autenticada cuyo path ordene después de `/auth/logout`, que son casi todas.

**Cómo evitarlo:** hasta que el script se arregle, seguir validando el camino de éxito
aparte (como en #10 y acá). La corrección de fondo ya no es opcional y es contenida:
que `dynamic_conformance.py` ordene las operaciones dejando para el final las que
invalidan el token (hoy `POST /auth/logout`), o que pida un token nuevo por operación.
No se hizo dentro del PR de #12 para no mezclar un cambio de tooling que corre en CI
con una feature — merece su propio issue.
