---
name: laravel-feature-workflow
description: Orquesta el desarrollo de una feature de la API de MotoYa de punta a punta — desde un issue de GitHub hasta código validado — siguiendo el orden Issue → SDD (spec OpenAPI) → TDD (tests que fallan primero) → implementación → validación de conformidad contra el spec (estática y dinámica). Usar esta skill siempre que el usuario pida "implementar" o "desarrollar" un issue/historia de usuario, diga "empecemos con el issue #N", pida seguir SDD o TDD para un endpoint, o pida validar que la implementación cumple el spec/swagger/OpenAPI. No reemplaza a github-backlog-issue (crear el issue) ni a laravel-api-review (complejidad/arquitectura del código) — las invoca como parte del flujo.
---

# Flujo de desarrollo de una feature — MotoYa API

## Por qué este orden

Cada feature de la API nace de un issue, se especifica antes de programarse (SDD),
se prueba antes de implementarse (TDD), y solo se considera terminada cuando la
implementación real cumple lo que el spec prometió — no solo "los tests pasan", sino
que la forma real de las respuestas coincide con lo documentado. Saltarse pasos
(programar antes de tener spec, o dar por hecho que el código cumple el contrato sin
probarlo) es exactamente el tipo de deriva que este flujo existe para evitar.

El spec OpenAPI vive en [`openapi.yaml`](../../../openapi.yaml) en la raíz del repo
— un único archivo que crece con cada feature, no un spec por issue. Es la fuente de
verdad del contrato de la API (ver `.claude/STANDARDS.md`).

## Flujo

### 1. Issue

Parte de un issue real del backlog (creado con la skill `github-backlog-issue`).
Lee su "Historia de usuario"/"Objetivo" y sus "Criterios de aceptación" — son el
insumo para el spec y los tests. Si el issue es ambiguo sobre el contrato de la API
(qué campos, qué códigos de estado, qué errores), pregunta al usuario antes de
inventar el contrato.

### 2. SDD — especifica antes de programar

Actualiza [`openapi.yaml`](../../../openapi.yaml) con el/los endpoint(s) del issue:
paths, métodos, `requestBody` con **`example` real** (no solo el schema — el chequeo
dinámico depende de esos ejemplos), `responses` por código de estado relevante (éxito
y errores esperados) con su schema, y parámetros de path con `example`. Reutiliza
schemas en `components.schemas` en vez de repetirlos inline cuando aplique a más de
un endpoint.

Valida el spec con el chequeo estático (no requiere servidor corriendo):
```bash
python .claude/skills/laravel-feature-workflow/scripts/static_conformance.py
```
En este punto es normal ver **AVISOS** de "documentado en el spec pero sin ruta
Laravel real" — el endpoint todavía no existe, eso es exactamente lo esperado antes
de implementar. Un **ERROR** sí importa: significa que el spec quedó inválido.

### 3. TDD — tests que fallan primero

Con el spec como contrato, escribe los tests **antes** que el código de producción:
- `tests/Feature`: un test por criterio de aceptación del issue, contra la ruta real
  documentada en el spec (método, path, request/response esperados).
- `tests/Unit`: un test por Action nueva, invocada directo.

Corre los tests y confirma que **fallan** (la ruta/Action todavía no existe) antes de
escribir la implementación — eso es lo que hace que sea TDD y no solo "escribir tests
después". Ver `.claude/STANDARDS.md` para las convenciones de testing del proyecto.

### 4. Implementación

Implementa siguiendo `.claude/STANDARDS.md`: Actions para la lógica de negocio,
Controllers finos, Form Requests para validación, API Resources para las respuestas,
Policies para autorización. La forma de las respuestas debe coincidir con lo que
`openapi.yaml` documentó en el paso 2 — si durante la implementación te das cuenta de
que el contrato necesita cambiar, vuelve a 2 y actualiza el spec primero, no lo dejes
desincronizado.

Corre los tests hasta que pasen en verde.

### 5. Revisión de arquitectura y complejidad

Antes de considerar la feature terminada, corre la skill `laravel-api-review` sobre
los archivos PHP que tocaste (complejidad ciclomática, anidación, reglas de
arquitectura). Corrige lo que aplique con criterio — ver esa skill para el detalle.

### 6. Validación de conformidad contra el spec

Esto es lo que confirma que "cumple la spec" no es solo una frase — se verifica con
dos scripts, en orden creciente de costo:

**Estático** (siempre, rápido, sin servidor):
```bash
python .claude/skills/laravel-feature-workflow/scripts/static_conformance.py
```
Para la feature que acabas de implementar, ya no debería haber avisos de "sin ruta
real" para sus endpoints — si los hay, algo quedó sin implementar o el path no
coincide con el spec.

**Dinámico** (antes de dar la feature por terminada / antes de cerrar el issue):
```bash
pip install -r .claude/skills/laravel-feature-workflow/scripts/requirements.txt  # una vez
python .claude/skills/laravel-feature-workflow/scripts/dynamic_conformance.py --start-server
```
Levanta un servidor real, pega requests reales usando los `example` del spec, y valida
que el body de cada respuesta cumple el schema documentado. Si un endpoint requiere
JWT, pasa `--auth-token <token>` o la variable de entorno
`OPENAPI_CONFORMANCE_TOKEN`. Los endpoints sin ejemplo en el spec se omiten (avisados,
no fallan) — si ves muchos omitidos para tu feature, probablemente falta completar
los `example` en el spec del paso 2.

Si algo fallara acá después de que los tests de PHPUnit ya pasaban, es una señal real:
los tests estaban verificando algo distinto de lo que el spec prometía — corrige el
código, el spec, o los tests, lo que esté realmente desalineado.

### 7. Cuando algo sale mal

Si alguno de los dos scripts de conformidad, o el flujo en general, te llevó por mal
camino (falso positivo, falso negativo, un paso del flujo que no tenía sentido para
un caso real), regístralo en [`KNOWN_ERRORS.md`](KNOWN_ERRORS.md) siguiendo el
formato que ya tiene el archivo, para no repetir el mismo error la próxima vez. Si es
sistemático, ajusta los scripts en `scripts/`, no solo lo documentes.

## Referencia rápida de comandos

- Validar spec + rutas (estático, sin servidor): `python .claude/skills/laravel-feature-workflow/scripts/static_conformance.py`
- Validar respuestas reales contra el spec (dinámico, levanta el servidor): `python .claude/skills/laravel-feature-workflow/scripts/dynamic_conformance.py --start-server`
- Con servidor ya corriendo en otro puerto: `python .claude/skills/laravel-feature-workflow/scripts/dynamic_conformance.py --base-url http://127.0.0.1:8000`
- Con auth JWT: agregar `--auth-token <token>` o exportar `OPENAPI_CONFORMANCE_TOKEN`
- Instalar dependencias (una vez): `pip install -r .claude/skills/laravel-feature-workflow/scripts/requirements.txt`
