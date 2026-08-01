<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterDriverController;
use App\Http\Controllers\Api\V1\Auth\RegisterPassengerController;
use App\Http\Controllers\Api\V1\Drivers\UpdateDriverAvailabilityController;
use App\Http\Controllers\Api\V1\Profile\ShowProfileController;
use App\Http\Controllers\Api\V1\Profile\UpdateProfileController;
use App\Http\Controllers\Api\V1\Rides\AcceptRideController;
use App\Http\Controllers\Api\V1\Rides\CancelRideController;
use App\Http\Controllers\Api\V1\Rides\CreateRideController;
use App\Http\Controllers\Api\V1\Rides\EstimateRideController;
use App\Http\Controllers\Api\V1\Rides\ShareRideLocationController;
use App\Http\Controllers\Api\V1\Rides\ShowRideController;
use App\Http\Controllers\Api\V1\Rides\StartRideController;
use App\Http\Controllers\Api\V1\Vehicles\RegisterVehicleController;
use App\Http\Controllers\Api\V1\Vehicles\UpdateVehicleController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autorización de los canales privados de Reverb. La llama el SDK del
    // cliente (Echo/pusher-js) por su cuenta al suscribirse, no la app móvil a
    // mano. Se declara acá, y no con `withBroadcasting()` en bootstrap/app.php,
    // para que quede versionada bajo /api/v1 y solo con POST: ese helper la
    // registra con GET y POST fuera del prefijo de la API (ver
    // App\Providers\BroadcastServiceProvider).
    //
    // Lleva `auth:api` como cualquier ruta protegida: acá el token expirado no
    // sirve —a diferencia del refresh— y quién es el usuario es exactamente lo
    // que las reglas de routes/channels.php necesitan resolver.
    Route::middleware('auth:api')
        ->post('broadcasting/auth', [BroadcastController::class, 'authenticate'])
        ->name('broadcasting.auth');

    Route::prefix('auth')->name('auth.')->group(function () {
        // Estos van sin `auth:api` a propósito, así que cualquiera puede
        // golpearlos sin credenciales. Por eso llevan un límite de tasa más
        // estricto que el general de la API (ver AppServiceProvider); para el
        // login es, además, la contención principal contra la fuerza bruta
        // sobre contraseñas.
        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', LoginController::class)->name('login');
            Route::post('refresh', RefreshTokenController::class)->name('refresh');
            Route::post('register/driver', RegisterDriverController::class)->name('register.driver');
            Route::post('register/passenger', RegisterPassengerController::class)->name('register.passenger');
        });

        // El cierre de sesión, en cambio, exige un token vigente, así que se
        // queda con el limitador general de la API (60/min por usuario). Bajo
        // `throttle:auth` compartiría la cuota por IP con los endpoints
        // anónimos, y una IP con muchos usuarios detrás —el NAT de una oficina,
        // una red móvil— se quedaría sin poder cerrar sesión porque otros
        // estuvieron intentando entrar.
        Route::middleware('auth:api')
            ->post('logout', LogoutController::class)
            ->name('logout');
    });

    // El perfil propio no cuelga de `auth/`: ahí viven las operaciones sobre la
    // sesión (entrar, salir, renovar), y esto es el recurso de la cuenta. De
    // `me` cuelgan además sus sub-recursos, como el vehículo del conductor
    // (historia #12, `POST /me/vehicle`).
    //
    // Se queda con el limitador general de la API (60/min por usuario) y no con
    // `throttle:auth`: exige token vigente, así que no es un endpoint anónimo, y
    // es la primera request que hace la app móvil al arrancar.
    Route::middleware('auth:api')
        ->get('me', ShowProfileController::class)
        ->name('profile.show');

    // PATCH y no PUT: solo se aceptan los campos de contacto presentes en el
    // body, el resto de la cuenta no se toca (historia #11).
    Route::middleware('auth:api')
        ->patch('me', UpdateProfileController::class)
        ->name('profile.update');

    // El vehículo del conductor cuelga de `me` porque es un sub-recurso de la
    // cuenta: se llega a él por el token, nunca por un id propio. Que solo los
    // conductores puedan registrarlo no se declara acá con un middleware de
    // rol, sino con `VehiclePolicy` (ver .claude/STANDARDS.md: la autorización
    // de negocio va en Policies, no en el token ni en la ruta).
    Route::middleware('auth:api')
        ->post('me/vehicle', RegisterVehicleController::class)
        ->name('vehicles.store');

    // PATCH y no PUT, igual que el perfil: solo se aceptan los campos
    // presentes en el body, el resto del vehículo no se toca (historia #13).
    // Tampoco lleva id acá: es el mismo sub-recurso de `me`, así que la moto
    // que se edita es siempre la de la cuenta del token.
    Route::middleware('auth:api')
        ->patch('me/vehicle', UpdateVehicleController::class)
        ->name('vehicles.update');

    // Disponibilidad y ubicación del conductor mientras no está en un viaje
    // (historia #17). Cuelga de `me`, mismo criterio que el vehículo: se
    // llega por el token, nunca por un id propio, así que estructuralmente
    // no existe forma de tocar la disponibilidad de otro conductor. Que solo
    // los conductores puedan usarlo no lo decide un middleware de rol acá,
    // sino `DriverProfilePolicy` desde `UpdateDriverAvailabilityRequest`.
    Route::middleware('auth:api')
        ->patch('me/availability', UpdateDriverAvailabilityController::class)
        ->name('drivers.availability');

    // La solicitud de viaje (historia #15). Es un recurso propio y no un
    // sub-recurso de `me`: se direcciona por su id —es el `{rideId}` del canal
    // privado `ride.{id}`— y del otro lado lo van a operar tanto el pasajero
    // como el conductor asignado. Que solo los pasajeros puedan crearlo lo
    // decide `RidePolicy`, no un middleware de rol acá.
    Route::middleware('auth:api')
        ->post('rides', CreateRideController::class)
        ->name('rides.store');

    // No cuelga de `me` como el vehículo: no es un sub-recurso de la cuenta,
    // es una consulta puntual que no persiste nada (historia #14). Exige
    // `auth:api` igual que el resto de la API y no solo `throttle:auth`
    // porque cada request golpea al proveedor de mapas, que se paga por
    // consulta — dejarlo anónimo abriría la puerta a agotar la cuota del
    // proveedor sin que haya una cuenta a la que atribuírselo.
    Route::middleware('auth:api')
        ->post('rides/estimate', EstimateRideController::class)
        ->name('rides.estimate');

    // Consultar el estado del viaje (historia #21). Es el fallback no
    // realtime del canal privado `ride.{id}`: lo que llega por el socket
    // mientras la app está viva, este endpoint lo responde cuando arranca o
    // se reconecta. Por eso lo autoriza la misma regla que el canal —el
    // pasajero dueño y el conductor asignado—, resuelta en `RidePolicy::view()`.
    Route::middleware('auth:api')
        ->get('rides/{ride}', ShowRideController::class)
        ->name('rides.show');

    // Cancelar antes de que un conductor acepte (historia #16). Es el primer
    // endpoint del proyecto que direcciona por id de ruta: `{ride}` se
    // resuelve por binding implícito de Eloquent, así que un id inexistente
    // responde 404 antes de que `CancelRideRequest` se evalúe. Que el viaje
    // ya no esté en `requested` no es este endpoint: esa cancelación tiene
    // sus propias reglas (penalización, liberar al conductor) y es la
    // historia #22/#23.
    Route::middleware('auth:api')
        ->post('rides/{ride}/cancel', CancelRideController::class)
        ->name('rides.cancel');

    // Aceptar una solicitud disponible (historia #18). Mismo binding implícito
    // que cancelar: `{ride}` resuelve el modelo antes de que se evalúe
    // `AcceptRideRequest`, así que un id inexistente responde 404 antes de la
    // autorización. Que dos conductores compitan por el mismo viaje no lo
    // decide la ruta ni el Form Request: lo resuelve `AcceptRideAction` bajo
    // lock.
    Route::middleware('auth:api')
        ->post('rides/{ride}/accept', AcceptRideController::class)
        ->name('rides.accept');

    // Iniciar el viaje ya aceptado (historia #19). A diferencia de aceptar,
    // acá el permiso depende del viaje concreto y no solo del rol: lo inicia
    // el conductor asignado a *ese* viaje, y eso lo decide `RidePolicy`. Que
    // el viaje no esté en `accepted` es 422 y lo resuelve `StartRideRequest`;
    // no hace falta lock porque el único que puede competir por esta fila es
    // el mismo conductor (ver `StartRideAction`).
    Route::middleware('auth:api')
        ->post('rides/{ride}/start', StartRideController::class)
        ->name('rides.start');

    // Publicar la ubicación del conductor durante el viaje (historia #20).
    // Mismo criterio de autorización que iniciar: `{ride}` resuelve el
    // binding implícito antes de `ShareRideLocationRequest`, y quién puede
    // publicar depende de la fila (RidePolicy::shareLocation()), no del rol
    // solo. No persiste nada: solo dispara el evento que consume el pasajero
    // suscrito al canal `ride.{id}` (historia #21).
    Route::middleware('auth:api')
        ->post('rides/{ride}/location', ShareRideLocationController::class)
        ->name('rides.location');
});
