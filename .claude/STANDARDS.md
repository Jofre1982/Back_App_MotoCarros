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

## Pendiente de decidir (no bloquea empezar)

- Si `User` es un solo modelo con rol (`passenger`/`driver`) o tablas separadas.
- Estrategia de refresh token concreta para JWT.
- Cálculo de tarifas (por distancia/tiempo) y proveedor de mapas/geocoding.
