# Estándares, prácticas de código y arquitectura — MotoYa Backend

Decisiones de arquitectura para este proyecto. Aplican a partir de ahora para todo
código nuevo; el skeleton actual (User, migraciones por defecto) no necesita
reescribirse hasta que se toque.

## Arquitectura de la lógica de negocio: Actions

Cada caso de uso de negocio es **una clase invocable, una responsabilidad**. Nada de
lógica de negocio en controllers ni en modelos.

- Ubicación: `app/Actions/{Dominio}/VerboSustantivoAction.php`
  - `app/Actions/Rides/CreateRideAction.php`
  - `app/Actions/Rides/AssignDriverAction.php`
  - `app/Actions/Rides/CompleteRideAction.php`
  - `app/Actions/Payments/ChargeRideAction.php`
  - `app/Actions/Auth/LoginAction.php`
- Método de entrada: `handle()`. Recibe parámetros tipados explícitos o un DTO —
  nunca el `Request` completo ni arrays genéricos (el mapeo Request → parámetros
  ocurre en el controller o en un Form Request).
- Las Actions **no conocen HTTP**: no reciben `Request`, no retornan `Response`.
  Deben poder invocarse igual desde un controller, un job en cola, un comando
  artisan o un test.
- Controllers quedan finos: validar (Form Request) → llamar al Action → devolver
  un API Resource.
- Evaluar el paquete `lorisleiva/laravel-actions` cuando se implemente el primer
  módulo real, si conviene que las Actions también sirvan como controller/job/listener
  sin duplicar clases. No es un requisito de entrada.

## Estructura de carpetas

```
app/
  Actions/
    Rides/
    Payments/
    Drivers/
    Auth/
  Models/
  Http/
    Controllers/Api/
    Requests/
    Resources/
    Middleware/
  Policies/
  Enums/        # RideStatus, UserRole, etc. (enums nativos de PHP)
  DTOs/         # opcional, para pasar datos entre capas sin arrays

routes/
  api.php       # crear; todas las rutas de negocio van aquí, versionadas /api/v1
```

## Autenticación de la API: JWT (tymon/jwt-auth)

- Paquete: `tymon/jwt-auth`. Guard `api` con driver `jwt` en `config/auth.php`.
- `auth:api` como middleware en toda ruta protegida.
- El JWT identifica al usuario (claims mínimos: id, y opcionalmente rol para UX del
  cliente). **No usar los claims del token como fuente de autorización de negocio.**
- Autorización fina (ej. "solo el conductor asignado puede marcar el viaje como
  completado") va siempre en **Policies** de Laravel, resueltas contra el `User`
  autenticado en cada request, no contra el contenido del token.
- Definir desde el primer endpoint el manejo de expiración/refresh (token corto +
  endpoint de refresh es el patrón recomendado con jwt-auth, ya que no trae
  refresh tokens nativos como Sanctum/Passport).

## Tiempo real: Laravel Reverb

- Broadcasting para: nueva solicitud de viaje disponible a conductores cercanos,
  cambios de estado del viaje, ubicación del conductor en curso.
- Canales privados por entidad: `ride.{id}`, `driver.{id}` — autorización en
  `routes/channels.php`.
- Eventos (`ShouldBroadcast`) se disparan al final de la Action correspondiente,
  nunca desde el controller.

## Testing: PHPUnit

- Se mantiene PHPUnit (ya está en el skeleton); no se migra a Pest.
- `tests/Feature`: un test por endpoint/flujo de API, contra rutas reales.
- `tests/Unit`: un test por Action, invocado directo (sin pasar por HTTP), más
  lógica pura de DTOs/enums.
- Factories para todo modelo de dominio (`Ride`, `Driver`, `Vehicle`, etc.).
- DB de test en sqlite (ya es el conector por defecto); usar `:memory:` en
  `phpunit.xml` si el arranque de tests se vuelve lento con archivo en disco.

## Estilo de código

- Formateo con Laravel Pint (ya instalado): `./vendor/bin/pint` antes de cada commit.
- Preset Pint por defecto (`laravel`), que implementa PSR-12.
- Type-hints en todos los métodos públicos; `declare(strict_types=1)` recomendado
  en Actions y DTOs.
- Nombres:
  - Actions en imperativo: `CreateRideAction`, no `RideCreator`.
  - Models en singular: `Ride`, `Driver`, `Vehicle`.
  - Enums en PascalCase con casos también PascalCase: `RideStatus::Requested`.
- Los Models solo tienen relaciones, scopes y accessors/casts simples — cualquier
  otra cosa va en una Action.
- **Todo atributo Eloquent cuyo cast devuelva un tipo distinto al de la columna
  (enum, Value Object, Carbon…) debe declararse con `@property` en el docblock del
  Model.** Sin esa anotación, PHPStan/Larastan lo sigue viendo como el tipo de la
  columna en BD (generalmente `string`) y marca las comparaciones con el tipo real
  como siempre-false. Ejemplo obligatorio para casts a enum:
  ```php
  /** @property UserRole $role */
  class User extends Authenticatable { … }
  ```
- **Nomenclatura de archivos y carpetas: 100% en inglés**, aunque el contenido/prosa
  esté en español (que sí se mantiene en español en este proyecto, ver el resto de
  este documento). Ej.: `known-errors.md`, no `errores-conocidos.md`; `user-story.md`,
  no `historia-usuario.md`. Aplica a cualquier archivo nuevo, no solo a los de dominio
  (docs, templates, scripts).

## Alcance: solo API, sin scaffolding web

Este backend es exclusivamente HTTP API REST, sin frontend propio. Como consecuencia:

- No hay `routes/web.php` con rutas de vista; solo `routes/api.php` (registrado en
  `bootstrap/app.php`) y `routes/console.php`.
- Se eliminó el scaffolding por defecto de Laravel que no aplica: vista `welcome`,
  Vite, Tailwind y assets en `resources/`.
- Auth es stateless (JWT), no hay `SESSION_*` relevante para rutas de negocio.
- Si en algún momento se decide construir un panel administrativo web, es una decisión
  explícita y separada (probablemente otro proyecto o al menos otro árbol de rutas
  claramente aislado) — no algo que se cuela por defecto en `app/Http/Controllers`.

## Convenciones de API

- Prefijo `/api/v1` desde el primer endpoint.
- Toda respuesta pasa por un API Resource (`App\Http\Resources\...`); nunca
  devolver un Model directo desde un controller.
- Validación de entrada siempre vía Form Request, uno por endpoint.
- Errores en formato JSON consistente a través del exception handler de Laravel
  (`message` + `errors` cuando aplica), no manejo de errores ad-hoc por controller.

## Ciclo de desarrollo de una feature: Issue → SDD → TDD → conformidad

Toda feature de la API sigue este orden, orquestado por la skill
`laravel-feature-workflow` (`.claude/skills/laravel-feature-workflow/SKILL.md`):

1. **Issue**: parte de un issue del backlog (ver sección de Backlog más abajo).
2. **SDD**: se especifica el contrato en [`openapi.yaml`](../openapi.yaml) — un único
   spec OpenAPI 3.0.3 en la raíz del repo que crece con cada feature, con ejemplos
   reales (no solo schemas) — antes de escribir tests o código.
3. **TDD**: se escriben los tests (Feature/Unit) contra ese contrato y deben fallar
   antes de que exista la implementación.
4. **Implementación**: siguiendo el resto de este documento (Actions, Form Requests,
   API Resources, Policies).
5. **Conformidad**: se valida que la implementación cumple el spec con
   `scripts/static_conformance.py` (spec válido + rutas documentadas vs. reales, sin
   servidor) y `scripts/dynamic_conformance.py` (requests reales contra un servidor
   vivo, valida el body de la respuesta contra el schema documentado) antes de dar la
   feature por terminada.

## Revisión de código: complejidad, arquitectura y tipos

Antes de dar por terminada una tarea que toca Controllers, Models o Actions, correr
la skill `laravel-api-review` (`.claude/skills/laravel-api-review/SKILL.md`), que
valida tres cosas:

- Complejidad ciclomática y anidación excesiva, y las reglas de arquitectura de este
  documento (Controllers finos, Models sin lógica de negocio, Actions sin
  conocimiento de HTTP, SQL crudo contenido, rutas versionadas), con
  `scripts/complexity_check.py` y `scripts/architecture_check.py` — corren sobre un
  AST real de PHP (`nikic/php-parser` vía `scripts/ast_dump.php`), pero las reglas de
  arquitectura en sí siguen siendo heurísticas (nombres de método/clase); tratar los
  hallazgos como señal a evaluar con criterio, no como regla mecánica.
- Tipos reales con **PHPStan/Larastan** (`composer stan`, config en `phpstan.neon`,
  nivel 5 sobre `app/` y `routes/`) — detecta bugs de tipos (métodos inexistentes,
  retornos incorrectos) que ninguna herramienta basada en heurísticas puede ver. El
  nivel puede subirse con el tiempo a medida que el código madure; no bajarlo para
  silenciar errores reales.

Para seguridad (mass assignment, campos sensibles expuestos, SQL crudo con input
dinámico, autorización faltante), ver la skill `laravel-security-review`.

## CI: todo lo anterior es obligatorio, no opcional

Además, `.github/workflows/issue-format.yml` valida el formato de los issues del
backlog al abrirse o editarse (ver la sección de Backlog más abajo).

`.github/workflows/ci.yml` corre en cada push/PR a `main`: Pint, PHPUnit,
`composer stan`, `composer audit` (CVEs conocidos en dependencias),
`laravel-api-review` (complejidad + arquitectura), `laravel-security-review`
(incluye consistencia `.env.example` vs `config/*.php`), y la conformidad OpenAPI
(estática + dinámica) de `laravel-feature-workflow`. Esto existe porque una skill
que solo corre "si alguien se acuerda de invocarla" no es una garantía — el CI es lo que convierte estas
validaciones de convención a requisito. Si un cambio necesita saltarse alguno de
estos checks, es una señal de que algo en el check o en el cambio está mal, no una
razón para deshabilitar el paso en el workflow.

## Backlog: historias de usuario e issues

El backlog se gestiona desde un GitHub Project. Todo issue (historia de usuario o
tarea técnica) sigue el formato de `.github/ISSUE_TEMPLATE/` — esa carpeta es la
fuente de verdad del formato, no un documento aparte. Para crear issues nuevos usar la
skill `github-backlog-issue` (`.claude/skills/github-backlog-issue/SKILL.md`), que
rellena el template correcto y valida el borrador con
`scripts/validate_issue.py` antes de proponer publicarlo en GitHub.

Dos reglas del formato que no son negociables, porque son lo que hace que los issues
sean comparables entre sí:

- **Ninguna sección del template es opcional.** Todos los issues llevan el mismo
  esqueleto, en el mismo orden. Si una sección no aplica a un caso concreto, se escribe
  `Ninguno.` explícitamente; nunca se borra la sección.
- **La estimación es una talla de la escala cerrada `XS|S|M|L|XL`**, nunca puntos ni
  días, para que todo el backlog se mida con la misma unidad.

Ambas se validan automáticamente: `scripts/validate_issue.py` las verifica, y el
workflow `.github/workflows/issue-format.yml` lo corre sobre cada issue `[US]`/`[TASK]`
al abrirse o editarse, comentando los errores en el propio issue. Así el formato no
depende de que quien abre el issue se acuerde de usar la skill.

## Modelo de datos para roles pasajero y conductor (decidido en #1)

**Decisión**: un único modelo `User` con campo `role` (enum `passenger`|`driver`) +
tabla `driver_profiles` para datos específicos del conductor.

- `users.role` almacena el rol de autenticación del usuario. El enum `App\Enums\UserRole`
  define los casos `Passenger` y `Driver`.
- `driver_profiles` (relación 1:1 opcional con `users`) almacena `license_number` y
  cualquier dato exclusivo del conductor. Un conductor sin perfil creado no puede
  recibir viajes.
- `User::isDriver()` / `User::isPassenger()` son los helpers canónicos para verificar
  el rol; no usar el campo `role` directamente fuera de esos métodos.
- Si en el futuro un mismo `User` necesita ambos roles, se añade el caso `Both` al enum
  o se migra a una tabla pivot `user_roles` — la capa de modelos actual no lo bloquea.

**Justificación**: una sola tabla de autenticación simplifica el JWT (el `sub` siempre
es el id de `User`), y `driver_profiles` mantiene la extensibilidad sin pre-optimizar
una estructura multi-rol que todavía no existe en el producto.

## Pendiente de decidir (no bloquea empezar)

- Estrategia de refresh token concreta para JWT.
- Cálculo de tarifas (por distancia/tiempo) y proveedor de mapas/geocoding.
