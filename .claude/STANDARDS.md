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
  Services/     # clientes de proveedores externos (mapas, pagos): infraestructura,
                # no casos de uso. Se consumen por interfaz desde las Actions.

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

### Expiración y refresh (decidido en #2)

| Parámetro | Valor | Env |
|---|---|---|
| Duración del access token | **15 minutos** | `JWT_TTL` |
| Ventana de refresh | **14 días** (20160 min) desde la emisión del token original | `JWT_REFRESH_TTL` |
| Gracia de blacklist | **30 segundos** | `JWT_BLACKLIST_GRACE_PERIOD` |

- No hay refresh token separado: **el propio access token es lo que se canjea** en
  `POST /api/v1/auth/refresh`. Es lo que permite jwt-auth sin inventar una tabla de
  refresh tokens.
- Un token expirado **no sirve para consumir la API** (el guard `api` lo rechaza con
  401) pero **sí para renovarse**, mientras siga dentro de la ventana de 14 días. Pasada
  esa ventana hay que volver a autenticarse con credenciales.
- Por eso `auth:api` **no** se aplica a la ruta de refresh: rechazaría el token expirado
  antes de llegar al controller, que es justo el caso que el endpoint existe para
  atender. La validación (firma + ventana) la hace jwt-auth dentro de la Action.
- La gracia de blacklist de 30 s existe porque las apps móviles disparan requests en
  paralelo: sin ella, la primera que refresca invalidaría el token que las otras ya
  tenían en vuelo.
- Los fallos del token que manda el cliente (`TokenExpiredException`,
  `TokenInvalidException` —de la que hereda `TokenBlacklistedException`— y
  `UserNotDefinedException`) se traducen a un 401 con el formato de error estándar en
  `bootstrap/app.php`, no en cada controller. La clase base `JWTException` **no** se
  captura a propósito: jwt-auth también la lanza ante errores de configuración del
  servidor (secreto sin generar, algoritmo inexistente, blacklist deshabilitada), y
  esos tienen que escalar a 500 para que se vean en el monitoreo en vez de disfrazarse
  de "tu sesión venció".
- `JWT_SECRET` no tiene default: se genera por entorno con `php artisan jwt:secret`.
  La suite de tests usa un secreto propio fijado en `phpunit.xml`, que no es un
  secreto real ni se usa en ningún entorno desplegado. El CI lo genera en el paso de
  preparación del entorno.

### Límites de tasa

Definidos en `AppServiceProvider::configureRateLimiting()` y aplicados desde
`bootstrap/app.php` (`throttleApi()`) y `routes/api.php`:

| Limitador | Límite | Alcance |
|---|---|---|
| `api` | 60/min por usuario autenticado, o por IP si no lo hay | todo el grupo `api` |
| `auth` | 10/min por IP | endpoints de auth, que van sin `auth:api` |

Los endpoints de autenticación se consumen sin credenciales por definición, así que son
el blanco natural de la fuerza bruta; en el caso del refresh, además, cada acierto
escribe una entrada de blacklist en el cache. Login y registro heredan este mismo grupo
cuando lleguen.

### Envelope de las respuestas

Los API Resources de Laravel envuelven la respuesta en `data` y ese es el formato que
sigue la API (`{"data": {...}}` en éxito). Los errores no se envuelven: van como
`{"message": ...}` (+ `errors` en validación). Ambas formas están documentadas en
[`openapi.yaml`](../openapi.yaml).

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

## Proveedor de mapas/geocoding (decidido en #3)

**Decisión**: **Google Maps Platform** — Routes API v2 (`computeRoutes`) para
distancia/duración y Geocoding API cuando haga falta resolver direcciones.

> **Supuesto de región**: el backlog no declara en qué ciudades opera MotoYa. Esta
> comparación asume ciudades intermedias de Colombia (mercado natural del moto-taxi).
> Si la región resulta ser otra, el eje de cobertura hay que reevaluarlo; los ejes de
> costo y de modo de dos ruedas no cambian.

### Comparación

| | Google Maps Platform | Mapbox | OpenRouteService / OSRM |
|---|---|---|---|
| Datos base | Propios | OSM + propios | OSM |
| Modo moto | **Sí** (`TWO_WHEELER` en Routes API) | No: solo `driving`/`cycling`/`walking` | No como tal (perfiles `driving-car`/`cycling-*`) |
| ETA con tráfico | Sí (`TRAFFIC_AWARE`) | Sí (`driving-traffic`) | No |
| Cobertura de direcciones en ciudades intermedias de Colombia | La mejor de las tres | Buena en capitales, desigual fuera | Depende de qué tan mapeada esté la ciudad en OSM |
| Costo por request | El más alto de los tres | Intermedio | Gratis (self-hosted: costo de infraestructura) |
| Volumen gratuito mensual | Sí, acotado por API | Sí, más holgado | Plan gratuito con cuota diaria / ilimitado si se auto-hospeda |

Las cifras exactas de precio y cuota gratuita cambian con frecuencia y no se copian
acá para que no envejezcan mal: **confirmar en el tarifario del proveedor antes de
contratar o de dimensionar el costo por viaje**. Lo que sí es estable —y es lo que
sostiene la decisión— son las diferencias de la tabla que no son de precio.

### Justificación

1. **Modo de dos ruedas.** La Routes API tiene un modo de moto real. Es el único de
   los tres candidatos que lo tiene, y es exactamente el vehículo del producto:
   rutear una moto como si fuera un auto sobreestima tiempo y distancia, y eso entra
   directo en la tarifa que ve el pasajero.
2. **Calidad del geocoding donde opera el moto-taxi.** El moto-taxi es fuerte en
   ciudades intermedias, que es justo donde la cobertura OSM es más despareja. Un
   geocoding equivocado no degrada la experiencia: cancela el viaje.
3. **Tráfico.** Parte de la tarifa es tiempo; sin ETA con tráfico, la estimación en
   hora pico se separa sistemáticamente de la realidad.

El costo es el precio a pagar por lo anterior, y es un riesgo real a volumen. Se
mitiga así:

- **Field mask mínimo** (`routes.distanceMeters,routes.duration`): en la Routes API el
  SKU facturado depende de los campos pedidos, así que no se piden polylines ni tramos
  mientras no se usen.
- **La decisión es reversible por diseño**: todo el sistema depende de la interfaz
  `App\Services\Maps\RouteEstimator`, nunca de la implementación. Cambiar de proveedor
  es escribir otra implementación y cambiar `MAPS_PROVIDER`.
- **Segunda opción documentada**: si el costo por viaje deja de cerrar, el reemplazo es
  Mapbox (o un OSRM propio para el cálculo de ruta, dejando el geocoding en Google), y
  se acepta perder el modo moto a cambio.

### Integración

- Configuración en [`config/maps.php`](../config/maps.php); variables en `.env.example`
  (`MAPS_PROVIDER`, `GOOGLE_MAPS_API_KEY`, `GOOGLE_MAPS_TIMEOUT`). La API key no tiene
  default: se restringe por API y por IP en cada entorno, y nunca se commitea.
- Los clientes de proveedores externos viven en **`app/Services/{Dominio}/`**: son
  infraestructura (hablan HTTP con un tercero), no casos de uso, así que no son
  Actions. Una Action del dominio —el motor de tarifa, por ejemplo— recibe el
  `RouteEstimator` por constructor y no sabe qué proveedor hay detrás.
- Prueba de concepto: `php artisan maps:estimate "<lat,lng>" "<lat,lng>"` consulta al
  proveedor real con la key configurada. Los tests cubren el contrato con `Http::fake()`;
  el CI no llama a la API real (no tiene credenciales y gastaría cuota en cada corrida).

**Fuera de alcance de #3**: el uso productivo de estas estimaciones dentro del cálculo
de tarifa, que es el issue #4.

## Cálculo de tarifas (decidido en #4)

**Decisión**: tarifa base + distancia + tiempo, con piso mínimo y redondeo hacia arriba.
La implementa `App\Actions\Payments\CalculateFareAction`, que es la **única** fuente de
verdad del monto: la tarifa estimada que se le muestra al pasajero antes de pedir el
viaje y el cobro al terminarlo salen de la misma Action con los mismos parámetros, y lo
único que cambia entre ambos momentos es el `RouteEstimate` que recibe (el estimado del
proveedor de mapas primero, el trayecto realmente recorrido después).

### Fórmula

```
distancia = round(metros      × per_kilometer / 1000)
tiempo    = round(segundos    × per_minute    / 60)
espera    = round(seg_espera  × per_waiting_minute / 60)

subtotal  = base + distancia + tiempo + espera
total     = ceil_al_múltiplo(max(subtotal, minimum), rounding_step)
```

| Parámetro | Default | Env | Qué cubre |
|---|---|---|---|
| `base` | 1500 | `FARE_BASE` | Cargo fijo por viaje: el desplazamiento del conductor hasta el punto de recogida. |
| `per_kilometer` | 800 | `FARE_PER_KILOMETER` | Distancia recorrida. |
| `per_minute` | 100 | `FARE_PER_MINUTE` | Tiempo en ruta. |
| `per_waiting_minute` | 60 | `FARE_PER_WAITING_MINUTE` | Espera solicitada con el viaje ya aceptado. |
| `minimum` | 3000 | `FARE_MINIMUM` | Piso del cobro. |
| `rounding_step` | 50 | `FARE_ROUNDING_STEP` | Múltiplo al que se redondea el total. |
| `currency` | `COP` | `FARE_CURRENCY` | Moneda (ISO 4217). |

Los valores numéricos son un punto de partida a validar con el negocio, y por eso viven
en [`config/fares.php`](../config/fares.php) y no en el código: ajustarlos no es un
cambio de software. La **fórmula** sí es una decisión de arquitectura y cambiarla pasa
por este documento.

### Reglas que sostienen la fórmula

- **Se cobra distancia y tiempo, no uno u otro.** Sin el componente de tiempo, un
  trayecto en hora pico paga lo mismo que el mismo trayecto a medianoche ocupando al
  conductor el triple; sin el de distancia, un viaje largo y fluido queda regalado. La
  duración viene del proveedor de mapas con tráfico (ver la sección anterior).
- **Las fracciones se prorratean, no se redondean a la unidad completa**: 1500 m pagan
  kilómetro y medio, no dos. Cobrar la unidad entera produce saltos de precio que el
  pasajero percibe como arbitrarios entre dos destinos casi iguales.
- **El mínimo se aplica antes del redondeo, y el redondeo es hacia arriba.** El mínimo
  es un piso: un redondeo al múltiplo más cercano podría perforarlo.
- **El paso de redondeo existe por el efectivo.** Buena parte de estos viajes se pagan
  en efectivo y el vuelto tiene que existir físicamente. Por eso `minimum` debería ser
  siempre múltiplo de `rounding_step` (lo verifica un test).
- **La espera se cobra más barato que el minuto en ruta**: el conductor está detenido,
  sin gastar combustible.

### Dinero: enteros, nunca float

Todos los montos son **enteros** en la unidad mínima de la moneda configurada — para COP
esa unidad es el peso, que no tiene subunidad en circulación. Los float no representan
dinero de forma exacta y el error se acumula justo donde más se nota (totales, cuadres,
comisiones). Esto aplica también a las columnas de BD cuando lleguen los pagos: entero,
no `float`/`double`.

### Estructura

- `App\DTOs\FareSchedule` — los parámetros vigentes, construidos desde `config/fares.php`
  en `AppServiceProvider`. Valida en el constructor: una tarifa negativa o un paso de
  redondeo en 0 revientan al resolver el servicio, no al cobrarle a un pasajero real.
- `App\DTOs\FareBreakdown` — el resultado: total **y desglose**. El desglose no es
  decorativo; cuando el cobro final no coincide con lo estimado (trayecto distinto,
  espera) hay que poder explicar la diferencia sin recalcular a mano.
- La Action recibe `RouteEstimate` y los segundos de espera. **No conoce HTTP ni el
  modelo `Ride`**, así que sirve igual desde un controller, un job o un test.

**Fuera de alcance de #4**: tarifas dinámicas por demanda (surge pricing), descuentos y
promociones; y los endpoints que consumen este cálculo, que son las historias de tarifa
estimada (#14) y pago del viaje (#25).

## Pendiente de decidir (no bloquea empezar)

- Nada pendiente por ahora.
