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
  tenían en vuelo. **Aplica solo a la renovación**: el cierre de sesión la ignora a
  propósito (ver "Cierre de sesión").
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

### Registro de cuentas (decidido en #6)

**Un endpoint de registro por rol**, no uno solo con el rol como campo de entrada:
`POST /api/v1/auth/register/passenger` (historia #6) y el de conductor con la #7.

- El rol **nunca se acepta como entrada**. Lo fija la Action correspondiente. El DTO
  de entrada (`App\DTOs\PassengerRegistration`) directamente no tiene campo de rol, y
  el Form Request arma ese DTO campo por campo en vez de pasar `validated()` completo:
  así no hay ningún camino por el que un `role` del cliente llegue hasta `users`.
  Importa porque `role` es fillable en el modelo, y porque registrarse como conductor
  exige requisitos (perfil, documentos) que el endpoint de pasajeros no pide.
- El registro **devuelve un access token**, no solo la cuenta creada: obligar a un
  login inmediatamente después haría que la app móvil mande la contraseña dos veces
  por la red para completar un alta. Responde **201** con el schema
  `AuthenticatedUser` (`{user, token}`), que el login reutiliza.
- Los endpoints de registro van sin `auth:api` y, por lo tanto, bajo el limitador
  `auth` (10/min por IP): anónimos, sirven para crear cuentas en masa y para sondear
  qué emails ya existen.
- `email` y `phone` son únicos en `users`; ambos se validan con `unique` en el Form
  Request, no solo con el índice de la tabla — sin la regla, un duplicado escala a un
  500 por violación de constraint en vez del 422 que el cliente puede mostrar.
- **Forma canónica de `email` y `phone`** (email en minúsculas; teléfono siempre con
  `+`, aunque el cliente pueda omitirlo). Se normaliza en `prepareForValidation()` del
  Form Request, es decir **antes** de `unique` y del DTO, así que lo que se valida y lo
  que se guarda son el mismo valor. Sin esto la unicidad de la cuenta depende de la
  colación del motor —SQLite (dev y tests) compara BINARY, MySQL con colación `_ci` no—
  y basta una mayúscula, o pegar el número desde la agenda en vez de teclearlo, para
  terminar con dos cuentas de la misma persona. Importa más allá del alta: sobre esa
  unicidad se apoyan el login (#8), la recuperación de cuenta y el teléfono como canal
  de contacto durante el viaje. Los endpoints de registro que vengan (conductor, #7)
  normalizan igual.
  Queda **fuera** normalizar a E.164 de verdad (deducir el código de país de un número
  local con libphonenumber): eso es una decisión de negocio con migración de los datos
  ya guardados, y el lugar para tomarla es cuando llegue el OTP por SMS.

### Registro de conductor (decidido en #7)

`POST /api/v1/auth/register/driver` sigue punto por punto lo anterior (rol fijado por
el endpoint, 201 con `AuthenticatedUser`, limitador `auth`, email y teléfono
canónicos) y agrega lo propio del rol:

- **Cuenta y perfil se crean en una transacción.** Una cuenta con rol `driver` y sin
  fila en `driver_profiles` no es un estado válido del dominio. El Form Request ya
  valida que la licencia esté libre, pero entre esa consulta y el `INSERT` cabe otra
  alta con la misma licencia, y ahí el índice único rechaza la escritura: sin la
  transacción quedaría la cuenta a medias, y el propio conductor no podría reintentar
  porque su email y su teléfono ya estarían tomados por ella.
- **`license_number` también tiene forma canónica**: se recorta y se pasa a mayúsculas
  en `prepareForValidation()`, por el mismo motivo que el email — `unique` es un
  `where license_number = ?` sobre una columna con índice único, así que sin
  normalizar bastaría escribirla en minúsculas para tener dos conductores con la misma
  habilitación. No se tocan los espacios interiores: `LIC 445 566` es una licencia mal
  escrita y corresponde un 422 explicable, no que el servidor adivine cómo debería
  haberse escrito.
- **La respuesta no incluye la licencia**: reutiliza el schema `AuthenticatedUser` del
  registro de pasajero y del login, y el dato del perfil se consulta por el endpoint de
  perfil (#10). Así el schema `User` no gana un campo que en las cuentas de pasajero
  estaría siempre vacío.
- La API **no verifica** que la licencia exista ni que esté vigente contra ninguna
  entidad externa: registra lo que el conductor declara (explícitamente fuera de
  alcance en #7). El vehículo se registra aparte (#12), porque un conductor puede dar
  de alta su cuenta antes de tener la moto cargada.
- Lo común a ambos registros (normalización de email/teléfono y reglas de los campos de
  cuenta) vive en el trait `App\Http\Requests\Concerns\NormalizesAccountInput`, no
  duplicado en cada Form Request: si las dos altas divergieran, la misma persona podría
  terminar con una cuenta por rol usando el mismo email escrito distinto.

### Inicio de sesión (decidido en #8)

`POST /api/v1/auth/login` es **un solo endpoint para ambos roles**: el rol no se manda
ni se elige, sale de la cuenta encontrada y viaja de vuelta en `user.role` solo para
que el cliente sepa qué UI mostrar. Responde **200** con el mismo schema
`AuthenticatedUser` (`{user, token}`) que los dos registros, así que la app móvil
procesa igual el alta y el login.

- **Los dos fallos posibles son indistinguibles, y eso es el requisito, no un
  detalle**: contraseña que no coincide y email sin cuenta responden 401 con el mismo
  cuerpo palabra por palabra. Cualquier diferencia —código, mensaje, un `errors` de
  más— convierte al endpoint en un oráculo para averiguar qué emails tienen cuenta,
  que es el primer paso de un relleno de credenciales. Lo sostienen tres decisiones
  concretas:
  - Una sola excepción de dominio (`App\Exceptions\Auth\InvalidCredentialsException`)
    para ambos motivos, **sin ningún dato que permita distinguirlos**. Si el motivo
    llegara hasta el controller, tarde o temprano alguien lo renderizaría.
  - El mensaje se define en un único lugar y la traducción a 401 vive en
    `bootstrap/app.php`, junto al resto de fallos de autenticación — no en el
    controller, que no captura nada.
  - **La Action gasta un hash contra nada cuando el email no existe.** Sin eso, el
    email sin cuenta responde sin haber ejecutado bcrypt y el email real responde
    después de ejecutarlo: la diferencia de tiempo es medible y reconstruye el mismo
    oráculo que el mensaje genérico evita. Laravel no lo hace por su cuenta
    (`EloquentUserProvider::retrieveByCredentials()` devuelve `null` y nadie llega a
    comparar nada), así que corresponde a la Action.
- **El login NO valida la contraseña contra `Password::defaults()`.** Es la única
  excepción a la política de la sección siguiente, y es deliberada: aplicarla haría
  que una contraseña corta respondiera 422 y una bien formada 401, y esa diferencia de
  código de estado delata cuál de los dos campos falló. Una contraseña que no cumple
  la política simplemente no coincide con ninguna cuenta. Tampoco hay
  `exists:users,email`, por el mismo motivo. Del `email` se valida la forma y nada
  más: una cadena que no es un email no puede corresponder a ninguna cuenta, así que
  su 422 no depende de qué haya en la base.
- **El `email` se normaliza con el mismo trait `NormalizesAccountInput` que los
  registros.** No es una comodidad: el alta guardó el email en minúsculas, así que un
  login que normalizara distinto —o que no normalizara— dejaría a quien tecleó
  `Ana@Example.COM` afuera de su propia cuenta, y encima con un mensaje que le dice
  que su contraseña está mal.
- Va sin `auth:api` y por lo tanto bajo el limitador `auth` (10/min por IP), que acá
  es además la contención principal contra la fuerza bruta sobre contraseñas.
- La contraseña en claro viaja en `App\DTOs\LoginCredentials` marcada con
  `#[\SensitiveParameter]`, igual que hace Laravel en
  `EloquentUserProvider::validateCredentials()`: sin eso queda como argumento en el
  stack trace de cualquier excepción lanzada más abajo, y los reporters que vuelcan
  los argumentos de cada frame la escribirían en el log.

Queda **fuera** de #8: recuperación de contraseña, login con proveedores externos
(Google/Apple), bloqueo de cuenta tras N intentos fallidos y rehash de la contraseña
al iniciar sesión. El bloqueo por cuenta, en particular, es una decisión de producto
con su propio costo (permite que un tercero deje sin servicio a un usuario conocido
solo con fallar sus intentos) y merece su propia historia.

### Cierre de sesión (decidido en #9)

`POST /api/v1/auth/logout` invalida el access token con el que se autenticó la request.
Responde
**204 sin cuerpo**: no hay ningún recurso que devolver y lo único que el cliente hace
con la respuesta es descartar el token que ya tenía, así que envolver un mensaje en
`data` sería inventar un recurso que no existe. Es la excepción declarada al envelope
de la sección "Envelope de las respuestas", no una omisión.

- **Sí lleva `auth:api`**, al revés que el refresh. Un token vencido ya no sirve para
  consumir la API, que es exactamente el estado al que el logout quiere llevarlo: no
  queda nada que cerrar y el 401 del guard es la respuesta correcta.
- **No hay periodo de gracia, y es la decisión central de la historia.** Los 30 s de
  `JWT_BLACKLIST_GRACE_PERIOD` existen para que las requests en vuelo sobrevivan a una
  *renovación*; aplicarlos acá dejaría vivo medio minuto más justo el token que el
  usuario pide matar porque perdió el dispositivo. `LogoutAction` baja la gracia a 0
  solo para esa escritura y la restaura en un `finally`: el `Blacklist` es un singleton
  compartido con el refresh y dejarlo en 0 le quitaría el margen que sí necesita.
- **Se invalida con `Blacklist::add()`, no con `invalidate(forceForever: true)`**,
  aunque las dos formas son inmediatas. `add()` guarda la entrada solo hasta que el
  token deja de ser renovable (14 días) y el cache la reclama sola; `addForever()` la
  escribiría de forma permanente y la blacklist crecería sin techo, un cierre de sesión
  a la vez.
- **Con `JWT_BLACKLIST_ENABLED=false` el logout falla con 500, no responde 204.**
  Escribir en el `Blacklist` directo se salta la única comprobación que jwt-auth hace
  de esa opción (`Manager::invalidate()`), así que `LogoutAction` la repite a mano y
  corta antes de tocar el token. Sin ella la entrada se escribe pero nadie la consulta
  al validar: el token sigue vivo hasta vencer solo mientras el endpoint responde 204,
  y el usuario cree que cerró una sesión que sigue abierta. Es un error de
  configuración del servidor, no del token del cliente, y por eso escala a 500 como el
  resto de ellos (ver "Autenticación" más arriba) en vez de disfrazarse de éxito.
- **Se cierra el token que el guard aceptó, no el que traiga la cabecera.** El
  controller lo lee de la instancia `tymon.jwt` —la misma que recibe el `JWTGuard`— y
  no con `Request::bearerToken()`. jwt-auth no busca el token solo en `Authorization`:
  su cadena de parsers es `AuthHeaders`, `QueryString`, `InputSource`, `RouteParams` y
  `Cookies`, y este repo no la restringe, así que un `POST /auth/logout?token=<jwt>`
  autentica igual. Con `bearerToken()` esos casos respondían 401 **con la sesión sin
  cerrar**, que es el peor fallo posible acá: desde el cliente es indistinguible del
  401 de "ya estaba cerrada", así que quien perdió el teléfono se queda con el token
  vivo creyendo lo contrario. La convención `(string) $request->bearerToken()` del
  refresh no sirve de precedente porque ese endpoint va **sin** `auth:api` y la
  cabecera es su única fuente por definición; el logout es el primero que invalida un
  token después de que el guard ya lo parseó. El contrato publicado en `openapi.yaml`
  sigue siendo `bearerAuth`: esto es consistencia con el guard, no una nueva forma de
  autenticarse.
- **El token cerrado tampoco se puede renovar.** Sale gratis —el refresh valida contra
  la blacklist— pero es el requisito de fondo: si se pudiera canjear por uno nuevo,
  cerrar sesión no serviría de nada frente al caso que la historia describe.
- **Se invalida el token enviado, no la cuenta**: cerrar sesión en el teléfono no echa
  a la tableta. El cierre remoto en todos los dispositivos queda fuera de #9 y necesita
  llevar registro de los tokens activos por usuario.
- **La Action recibe `Tymon\JWTAuth\JWT`, no su subclase `JWTAuth`.** Son dos singletons
  distintos (`tymon.jwt` y `tymon.jwt.auth`) y el guard `api` se construye con el
  primero. Como `JWT::getToken()` devuelve lo que tenga cacheado antes de leer la
  cabecera, la Action suelta el token con `unsetToken()` al terminar —igual que
  `JWTGuard::logout()`—: si no, un contenedor que sobrevive a la request (worker de
  colas, suite de tests, Octane) reusaría el token ya invalidado en la siguiente
  autenticación y rechazaría credenciales válidas.
- **Queda bajo el limitador general `api`, no bajo `throttle:auth`.** Exige token
  vigente, así que no es un endpoint anónimo; bajo `throttle:auth` compartiría la cuota
  por IP con el login y una IP con muchos usuarios detrás —el NAT de una oficina, una
  red móvil— se quedaría sin poder cerrar sesión porque otros estuvieron intentando
  entrar.

### Perfil propio (decidido en #10)

`GET /api/v1/me` devuelve la cuenta que envió el token. Responde **200** con el schema
`Profile`, que es `User` más lo que depende del rol.

- **Los datos salen de la base, no de los claims del token.** El `role` del JWT quedó
  congelado al emitirse y viaja solo para que el cliente elija qué UI mostrar (ver
  "Autenticación"); este endpoint existe justo para responder el estado *actual* de la
  cuenta, así que leerlo del token respondería el del pasado. Un test lo fija:
  cambiando el rol en la base, la respuesta refleja el nuevo y no el del token.
- **Es el único endpoint que devuelve el `license_number`**, dentro de un objeto
  `driver_profile`. Lo prometió la #7 al dejarlo fuera de `AuthenticatedUser`, y sin
  esto el dato no volvería al cliente desde ningún lado. Por eso `Profile` es un schema
  aparte y no un campo nuevo en `User`: `User` es lo que responden el login y los dos
  registros, donde un `driver_profile` no aplica.
- **La clave se omite entera cuando no aplica, en vez de viajar en `null`.** En una
  cuenta de pasajero no es un dato que falte; un `null` no dejaría distinguirla de un
  conductor que todavía no tiene perfil creado.
- **No hay Action detrás, y es deliberado.** Leer la cuenta que el guard ya resolvió no
  decide ni cambia nada: una Action acá sería un pasamanos. La regla de este documento
  existe para que la lógica no se esconda en los controllers, no para envolver lo que no
  la tiene — la #11 (actualizar el perfil) sí la va a necesitar, porque ahí sí se escribe.
- **Tampoco hay Policy**: el recurso *es* el usuario autenticado, así que no existe la
  pregunta "¿puede este usuario ver este perfil?". Ver el perfil ajeno (el del conductor
  asignado a un viaje) es otro endpoint y ahí la autorización sí tendrá qué decidir.
- **La ruta es `/me`, no `/auth/me`.** Bajo `auth/` viven las operaciones sobre la sesión
  (entrar, salir, renovar); esto es el recurso de la cuenta, y de él cuelgan sus
  sub-recursos — el vehículo del conductor es `POST /me/vehicle` (#12). Queda con el
  limitador general `api`, no con `throttle:auth`, por lo mismo que el logout: exige token
  vigente, así que no es un endpoint anónimo.
- **`DriverProfileResource` no publica el `id` de la fila ni el `user_id`**: son claves
  internas de una relación 1:1 a la que se llega por la cuenta, nunca por su id.

Queda **fuera** de #10: consultar el perfil de otra persona (se resuelve dentro de la
historia del viaje correspondiente, con lo que ahí corresponda mostrar) y actualizar el
propio (#11).

### Política de contraseñas (decidida en #6)

Mínimo **8 caracteres, con al menos una letra y al menos un número**, declarada una
sola vez como `Password::defaults()` en `AppServiceProvider` y referenciada desde los
Form Requests. Centralizarla es lo que evita que registro, login y un eventual cambio
de contraseña diverjan.

No se exigen símbolos ni mayúsculas y minúsculas: para una app de transporte en móvil
esas reglas empujan a contraseñas anotadas o a variaciones triviales (`Motoya1!`), y
las guías actuales (NIST SP 800-63B) favorecen longitud sobre composición. Si más
adelante hace falta endurecerla, el lugar es subir el mínimo de longitud.

Queda **fuera** `uncompromised()` (contraste contra la lista de Pwned Passwords): hace
una llamada HTTP a un servicio externo dentro del ciclo de validación, lo que ataría
el registro a la disponibilidad de un tercero y metería red en la suite de tests. Es
una mejora razonable a futuro si se resuelve con timeout y degradación explícita.

### Envelope de las respuestas

Los API Resources de Laravel envuelven la respuesta en `data` y ese es el formato que
sigue la API (`{"data": {...}}` en éxito). Los errores no se envuelven: van como
`{"message": ...}` (+ `errors` en validación). Ambas formas están documentadas en
[`openapi.yaml`](../openapi.yaml).

Una operación que no devuelve ningún recurso responde **204 sin cuerpo** en vez de
envolver un mensaje en `data` (hoy: el cierre de sesión). El envelope es para
recursos; inventar uno para decir "listo" no le da nada al cliente.

## Tiempo real: Laravel Reverb

- Broadcasting para: nueva solicitud de viaje disponible a conductores cercanos,
  cambios de estado del viaje, ubicación del conductor en curso.
- Canales privados por entidad: `ride.{id}`, `driver.{id}` — autorización en
  `routes/channels.php`.
- Eventos (`ShouldBroadcast`) se disparan al final de la Action correspondiente,
  nunca desde el controller.

### Canales y autorización (decidido en #5)

**Todos los canales son privados.** No hay nada en este dominio que se pueda
escuchar de forma anónima: por estos canales viaja la ubicación en vivo de
personas concretas.

| Canal | Quién entra | Qué lleva |
|---|---|---|
| `ride.{rideId}` | El pasajero del viaje y el conductor asignado | Cambios de estado y ubicación durante el viaje |
| `driver.{driverId}` | Ese conductor, si tiene perfil creado | Solicitudes de viaje cercanas y avisos que son para él |

- `{driverId}` es el id del `User`, **no** el de `driver_profiles`: la API ya
  identifica a todo el mundo por el id de `User` (es el `sub` del JWT) y tener dos
  numeraciones para la misma persona es una fuente de errores de autorización.
- `routes/channels.php` solo declara qué canales existen; la regla de cada uno vive
  en una clase de `app/Broadcasting/` (canales class-based de Laravel), que se prueba
  sin levantar HTTP. Los canales se registran en `App\Providers\BroadcastServiceProvider`.
- Todos los canales declaran `['guards' => ['api']]` de forma explícita: la API es
  stateless con JWT y no existe el guard `web`.
- El canal `ride.{id}` ya tiene su regla definitiva, pero el modelo `Ride` llega con
  la historia #15. Hasta entonces resuelve los participantes contra
  `App\Services\Realtime\RideParticipants`, cuya implementación registrada
  (`PendingRideParticipants`) no conoce ningún viaje y por lo tanto **deniega todo**.
  Fallar cerrado es la única opción aceptable acá: un canal abierto "provisionalmente"
  expone posiciones en vivo a cualquier usuario autenticado que adivine un id. Cuando
  exista la tabla, se cambia el binding en `AppServiceProvider` y nada más.

### La ruta de autorización es una ruta de la API

`POST /api/v1/broadcasting/auth` (con `auth:api`), declarada en `routes/api.php` y
documentada en [`openapi.yaml`](../openapi.yaml) como cualquier otro endpoint. No se
usa el helper `withBroadcasting()` de `bootstrap/app.php` porque registra la ruta con
`GET` y `POST` y fuera del prefijo `/api/v1`, lo que rompe tanto la convención de
versionado como la correspondencia entre spec y rutas reales que valida
`static_conformance.py`.

Acá el token expirado **no** sirve (a diferencia de `POST /api/v1/auth/refresh`): el
cliente renueva y vuelve a suscribirse.

### Configuración

- `config/broadcasting.php` solo declara las conexiones que se usan (`reverb`, `log`,
  `null`); las de Pusher y Ably del skeleton se quitaron, mismo criterio que el resto
  del scaffolding sin usar.
- Variables en `.env.example` (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`,
  `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_HOST`,
  `REVERB_SERVER_PORT`). Las credenciales de la aplicación Reverb no tienen default:
  son un secreto por entorno igual que `JWT_SECRET`, y se generan con
  `php artisan reverb:install`.
- La suite de tests corre con `BROADCAST_CONNECTION=null` (`phpunit.xml`): ningún test
  abre conexiones. Un test que necesite el broadcaster real tiene que fijar la conexión
  en el entorno **antes** de que arranque la aplicación — los canales se registran
  contra el broadcaster configurado al bootear, así que cambiarla después con
  `Config::set` deja al nuevo broadcaster sin canales.
- Toda variable de entorno que un test necesite se declara en `phpunit.xml`, **no** en
  un `setUp()`. El repositorio de phpdotenv es inmutable y se llena en el primer boot
  del proceso: una variable que no esté definida antes de ese boot queda registrada como
  cargada desde `.env`, y en cada boot posterior el writer la repisa con el valor del
  archivo, pisando lo que haya puesto el `setUp()`. El síntoma es un test que pasa
  aislado (primer boot) y falla dentro de la suite, con un resultado que además depende
  del `.env` de cada máquina — en CI, `.env` sale de `.env.example`. Fijar una variable
  desde `setUp()` solo funciona si ya está declarada en `phpunit.xml` (ese es el caso de
  `BROADCAST_CONNECTION`): al no entrar nunca en el conjunto *loaded*, nadie la repisa.
  Las credenciales `REVERB_APP_*` de prueba están en `phpunit.xml` por esta razón.
- Prueba de concepto: con `php artisan reverb:start` corriendo y un cliente suscrito a
  `private-driver.{id}`, `php artisan realtime:ping <id> "<mensaje>"` recorre la cadena
  completa (autorización del canal, publicación y entrega). El evento
  `App\Events\Realtime\RealtimePingSent` es `ShouldBroadcastNow` por ser una prueba;
  los eventos de negocio usan `ShouldBroadcast` y salen por la cola.

**Fuera de alcance de #5**: los eventos de negocio concretos (solicitudes cercanas,
tracking del viaje), que llegan con sus propias historias.

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

## Vehículo del conductor (decidido en #12)

`POST /api/v1/me/vehicle` da de alta la moto del conductor autenticado. Responde
**201** con el schema `Vehicle` (`plate`, `model`, `year`).

- **Cuelga de `/me` y no de `/vehicles`**, por lo mismo que el perfil: es un
  sub-recurso de la cuenta y se llega a él por el token, nunca por un id propio. Por
  eso `VehicleResource` tampoco publica el `id` de la fila ni el `user_id`, igual que
  `DriverProfileResource`.
- **La relación es 1:1 y la sostiene el índice único de `vehicles.user_id`**, no solo
  la validación. Un segundo registro responde **422**, no reemplaza al vehículo
  existente: el endpoint es de alta, y un cliente móvil que reintenta una request que
  ya había llegado no debe pisar en silencio los datos de la moto con la que el
  conductor está trabajando. Actualizarla es la #13.
- **Ese 422 viaja bajo la clave `vehicle`, que no es un campo de la entrada**, porque
  no lo decide nada de lo que el cliente manda sino el estado de la cuenta. Se agrega
  desde `after()` en el Form Request, no como regla de un campo.
- **El rol se autoriza con `VehiclePolicy`, y una cuenta de pasajero recibe 403, no
  422**: no es una entrada que se pueda corregir mandando otros datos, es una operación
  que su rol no tiene. La Policy se invoca desde `authorize()` del Form Request para
  que el 403 se resuelva **antes** que la validación — al revés, el 422 le detallaría a
  una cuenta sin permiso qué forma tiene que tener la entrada. El primer uso de
  Policies del proyecto; se declara con `#[UsePolicy]` en el modelo en vez de dejarla a
  la convención de nombres.
- **Que la cuenta ya tenga vehículo no se decide en la Policy**, aunque podría: el
  conductor sí tiene el permiso, lo que le falta es actualizar lo que ya registró. Un
  403 ahí le diría que el problema es de permisos, que es otra cosa.
- **La `plate` tiene forma canónica** (recorte y mayúsculas en `prepareForValidation()`),
  por el mismo motivo que `license_number`: `unique` es un `where plate = ?` sobre una
  columna con índice único, así que sin normalizar bastaría escribirla en minúsculas
  para registrar dos veces la misma moto — y ahí la placa dejaría de identificar a un
  vehículo, que es para lo único que sirve. Los espacios interiores no se limpian:
  `ABC 12D` es una placa mal escrita y corresponde un 422 explicable.
- **El `regex` de la placa no codifica el formato de ningún país** (`^[A-Z0-9-]{5,10}$`).
  El supuesto de región de la #3 es Colombia, pero atarlo al formato `AAA00A` haría que
  cambiar de país fuera un cambio de código; la API tampoco verifica contra ninguna
  entidad externa que la placa exista o esté vigente, igual que con la licencia.
- **Los límites de `year` (1970 … año en curso + 1) atajan el dedazo, no declaran una
  antigüedad máxima de la flota.** El tope es el año siguiente porque los modelos se
  venden adelantados al calendario. Si el negocio quiere una política de antigüedad, es
  otra decisión y su lugar es la configuración, no una regla de validación.
- **La Action no usa transacción**, a diferencia del alta de conductor: escribe una sola
  fila, así que no existe el estado a medias que allá había que evitar. Dos altas
  simultáneas que pasen la validación a la vez terminan con la última perdiendo contra
  el índice único (500), que es el mismo trato que la #7 le da a una licencia duplicada.

Queda **fuera** de #12: los documentos del vehículo (la historia los menciona, pero no
tiene criterio de aceptación para ellos y almacenarlos exige decidir antes dónde viven
los archivos y quién los verifica — la verificación administrativa está declarada fuera
de alcance en el propio issue); actualizar el vehículo (#13); y que un conductor tenga
más de una moto.

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
