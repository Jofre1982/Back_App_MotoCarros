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

### 2026-07-31 — El chequeo dinámico nunca prueba el 401 sin `Accept: application/json`

**Qué pasó:** implementando `PATCH /me/vehicle` (#13), el chequeo dinámico dio
`OK: 9 operación(es) probadas` y el 401 documentado del endpoint validó sin problema.
Pero pegándole al servidor real **sin** cabecera `Accept`, ese mismo 401 no existe:
la API responde **500** (`Route [login] not defined.`) porque
`AuthenticationException` cae en la rama de redirección al login web en vez de la de
JSON. No es del endpoint nuevo — `GET /me` (#10) y `POST /me/vehicle` (#12) hacen
exactamente lo mismo, así que el spec viene prometiendo un 401 que solo se cumple si
el cliente manda `Accept`.

**Por qué pasó:** `dynamic_conformance.py` fija `headers = {"Accept": "application/json"}`
para todas las requests, así que el único camino que ejercita es justo el que funciona.
Los tests de PHPUnit tampoco lo ven: `patchJson()`/`getJson()` ponen esa misma cabecera.
Entre las dos herramientas, el caso quedó sin cubrir por ninguna.

**Cómo evitarlo:** no dar por verificado un 401 documentado solo porque el chequeo
dinámico lo valide — probarlo también sin `Accept` (`curl -X PATCH -H 'Content-Type:
application/json' ...`) antes de cerrar una feature autenticada. La corrección de fondo
es del backend, no del script (que `shouldRenderJsonWhen` aplique también a la rama de
`unauthenticated()`, o `redirectGuestsTo(null)`), y toca a todos los endpoints
protegidos a la vez: merece su propio issue en vez de colarse en el PR de una historia.
Si se arregla, agregar al script una request de control sin `Accept` para que no
vuelva a pasar inadvertido.

### 2026-07-31 — Un segundo camino de "verde por omisión": el proveedor de mapas sin configurar

**Qué pasó:** implementando `POST /rides` (#15), el chequeo dinámico habría dado verde
sin ejecutar nunca el 201, por dos causas independientes que se suman. La conocida (el
token compartido que `POST /auth/logout` invalida antes) y una nueva: los endpoints que
llaman al proveedor de mapas responden **422 documentado** cuando no hay
`GOOGLE_MAPS_API_KEY` en el entorno, porque `RouteEstimationFailed` está mapeada a 422.
Ese 422 valida contra el schema `Error` y el resultado global es verde — aunque el
endpoint no haya creado ni un solo viaje. Le pasa igual a `POST /rides/estimate` (#14).

**Por qué pasó:** el spec documenta a propósito el 422 de "zona sin cobertura", así que
cualquier fallo del proveedor —incluida su ausencia de configuración— cae en una
respuesta que el contrato permite. No es un bug del script: es que "no hubo fallos" y
"se probó el camino de éxito" son cosas distintas, y el script solo reporta la primera.

**Cómo evitarlo:** para una feature que dependa del proveedor de mapas, apuntar
`GOOGLE_MAPS_ROUTES_ENDPOINT` a un stub local (config/maps.php lo permite
explícitamente para esto) y fijar cualquier `GOOGLE_MAPS_API_KEY` antes de correr el
servidor; así el 201 se ejecuta de verdad. Después validar ese cuerpo real contra el
schema del spec con `jsonschema`, **incluyendo la contra-prueba** de que el schema
rechaza cuerpos inválidos, como ya indican las dos entradas anteriores. Conviene además
usar una `DB_DATABASE` aparte: el chequeo dinámico escribe filas reales, y contra la
base de desarrollo dejaría un viaje activo que después bloquea al mismo pasajero.
La corrección de fondo del script sigue siendo la ya anotada (no compartir un token que
una operación invalida); esta entrada agrega que, además, el reporte debería decir **qué
código de estado** respondió cada operación.

### 2026-07-31 — Cuarta repetición del token compartido (#19), y por qué el conteo engaña

**Qué pasó:** implementando `POST /rides/{id}/start` (#19), el chequeo dinámico reportó
`OK: 15 operación(es) probadas contra el spec, 0 omitida(s), sin fallos` con la base
sembrada a propósito (un viaje `accepted` con id 1 asignado al conductor del token).
El 200 del endpoint nuevo **no se ejecutó**: al terminar la corrida el viaje seguía en
`accepted` con `started_at` en NULL. Misma causa de siempre — `POST /auth/logout` va
antes en el orden del spec y deja el token en la blacklist, así que `/rides/1/start`
respondió el 401 que el contrato también documenta.

**Por qué pasó:** idéntica a las tres entradas anteriores. Lo que agrega este caso es
que el **conteo de operaciones tampoco sirve como señal**: entre dos corridas seguidas
pasó de `14 probadas, 1 omitida` a `15 probadas, 0 omitidas` sin que ninguna hubiera
ejecutado su camino de éxito. Lo único que delató la omisión fue mirar el estado de la
fila en la base después de correr el script.

**Cómo evitarlo:** lo ya anotado —validar el camino de éxito aparte— y, como control
barato para cualquier feature que **escriba** algo, comprobar el estado de la fila
después de la corrida: si el recurso no cambió, el 200/201 no se ejecutó por más verde
que diga el resumen. Acá se verificó con `curl` contra un servidor propio y una
`DB_DATABASE` de scratch: 200 con el cuerpo del schema `Ride` (`status: in_progress`,
`started_at` poblado) y 422 documentado al repetir la llamada. La corrección de fondo
del script (dejar `/auth/logout` para el final, o un token por operación) lleva cuatro
historias pendiente y sigue mereciendo su propio issue en vez de colarse en el PR de
una feature.

### 2026-08-01 — `dynamic_conformance.py` daba por incumplido cualquier campo `nullable`

**Qué pasó:** implementando `GET /rides/{id}` (#21), validar a mano el 200 real contra
el schema `Ride` del spec falló con `None is not of type 'string'` en `started_at`, y
lo mismo habría pasado con el `driver: null` de un viaje sin aceptar. La respuesta era
correcta: los dos campos están documentados `nullable: true` a propósito, justamente
para que el cliente los reciba siempre presentes.

**Por qué pasó:** OpenAPI 3.0 **no es** JSON Schema. Expresa "puede venir en null" con
su palabra clave propia `nullable: true`, que `jsonschema` no conoce y descarta en
silencio, quedándose con `type: string` a secas. El script pasaba el schema del spec
directo a `validate()` sin traducirlo. No se había notado antes porque —por el problema
del token compartido de las cuatro entradas anteriores— ninguna corrida había llegado
nunca a validar un cuerpo de éxito con un campo nullable poblado en null.

**Cómo evitarlo:** corregido en el script, que era el arreglo de fondo: `as_json_schema()`
traduce `nullable: true` a `type: [..., 'null']` (o a un `anyOf` con `{"type": "null"}`
cuando acompaña a un `allOf`, que es la única forma de anotar un `$ref` en 3.0), se
aplica al schema de la respuesta y también al spec con el que se construye el
`RefResolver` —si no, lo que entra por un `$ref` volvería a llegar sin traducir—. Al
corregirlo se comprobó que el validador sigue detectando incumplimientos reales (un
`driver` ausente y un `started_at` numérico se reportan igual), que es lo que
distingue arreglar el validador de apagarlo.

### 2026-08-01 — Sin intérprete Python real en el entorno del agente

**Qué pasó:** implementando `POST /rides/{id}/cancel` para el conductor (#23), tanto
`static_conformance.py` como `dynamic_conformance.py` fallaron antes de correr una
sola línea: `python`/`python3`/`py` resuelven al stub de Microsoft Store de Windows
("Python was not found; run without arguments to install from the Microsoft Store"),
no a un intérprete real. No es un fallo del spec ni del código de la feature.

**Por qué pasó:** el entorno de esa corrida del agente no tenía Python instalado, solo
el alias de ejecución de Windows que apunta a la Store.

**Cómo evitarlo:** si `python --version` (o `python3`/`py`) no devuelve una versión
real, no asumir que los scripts corrieron — verificarlo primero. Sin Python, la
alternativa que se usó acá: revisar el YAML del spec a mano (releerlo completo tras
editar) y validar la conformidad dinámica manualmente con un servidor real
(`php artisan serve`) contra una `DB_DATABASE` de scratch, sembrando con `tinker` los
estados necesarios para cada rama del contrato (pasajero cancela, conductor devuelve
al pool, 403 de un tercero, 422 de un viaje `in_progress`) y comparando el cuerpo real
con el schema del spec a ojo. Esto no reemplaza los scripts —no hay contraprueba
automática de que el schema rechace un cuerpo inválido—, así que en un entorno con
Python disponible, seguir corriendo `static_conformance.py`/`dynamic_conformance.py`
como primera opción.
