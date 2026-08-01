<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ride;
use App\Models\User;

/**
 * Quién puede operar sobre un viaje.
 *
 * Como en `VehiclePolicy`, la autorización de negocio vive acá y no en los
 * claims del token: el `role` del JWT quedó congelado al emitirse, y una cuenta
 * que pasó a ser conductor seguiría solicitando viajes como pasajero hasta que
 * su token venciera.
 */
class RidePolicy
{
    /**
     * Solicitar un viaje es una operación del rol pasajero.
     *
     * El conductor no queda afuera del producto por esto: consigue viajes
     * aceptando los que ya existen (historia #18), que es otra operación con su
     * propia regla.
     *
     * Que el pasajero ya tenga un viaje activo **no** se decide acá: eso
     * responde 422, porque el permiso lo tiene y lo que le falta es terminar o
     * cancelar el viaje que ya pidió. Mismo criterio que el segundo vehículo de
     * un conductor en `VehiclePolicy`.
     */
    public function create(User $user): bool
    {
        return $user->isPassenger();
    }

    /**
     * Ver un viaje es de quienes participan de él: el pasajero que lo pidió y
     * el conductor asignado (historia #21). Son exactamente los mismos dos que
     * entran al canal privado `ride.{id}` — el endpoint es la consulta puntual
     * que acompaña a ese canal, así que un desacuerdo entre ambas reglas sería
     * un agujero: lo que no se puede leer por HTTP tampoco debería llegar por
     * el socket, y al revés.
     *
     * A diferencia de `cancel()` y `start()`, acá **no** se comprueba además
     * el rol. Esas deciden si la cuenta puede *operar* el viaje, y una cuenta
     * que cambió de rol no debería poder; esta solo lee lo que esa misma
     * persona ya vivió, y quitarle el acceso a su propio viaje porque después
     * se registró como conductor sería un efecto secundario que nadie pidió.
     */
    public function view(User $user, Ride $ride): bool
    {
        return $ride->passenger_id === $user->getKey()
            || ($ride->driver_id !== null && $ride->driver_id === $user->getKey());
    }

    /**
     * Ver el recibo de un viaje es del pasajero **dueño** de ese viaje
     * (historia #26): es él quien pagó y quien necesita el desglose para
     * verificar el cobro, no el conductor que lo llevó.
     *
     * A diferencia de `view()`, acá el conductor asignado queda afuera a
     * propósito: el recibo es un documento de lo que se le cobró al
     * pasajero, no del viaje en sí, y el conductor ya conoce lo que le
     * corresponde por otra vía. Mismo criterio que `view()` en cuanto a no
     * comprobar además el rol: esto solo lee lo que esa misma persona ya
     * pagó, y quitarle el acceso porque después se registró como conductor
     * sería un efecto secundario que nadie pidió.
     */
    public function viewReceipt(User $user, Ride $ride): bool
    {
        return $ride->passenger_id === $user->getKey();
    }

    /**
     * Cancelar un viaje es del pasajero **dueño** de ese viaje (historia #16),
     * o del conductor **asignado** a ese viaje (historia #23) — mismo
     * endpoint, dos operaciones distintas: `CancelRideAction` decide si
     * cancela el viaje o lo devuelve al pool según cuál de los dos sea quien
     * llama.
     *
     * Que el viaje ya no esté en un estado cancelable para ese actor **no**
     * se decide acá: eso responde 422, porque el permiso de cancelar lo tiene
     * y lo que cambia es el flujo a seguir (ver `CancelRideRequest`). Un 403
     * le diría que el permiso le falta, que es otra cosa.
     *
     * El rol se comprueba además de la propiedad en ambas ramas, mismo
     * criterio que `VehiclePolicy::update()`: nada impide que una fila apunte
     * a una cuenta que dejó de tener ese rol.
     */
    public function cancel(User $user, Ride $ride): bool
    {
        return ($user->isPassenger() && $ride->passenger_id === $user->getKey())
            || ($user->isDriver() && $ride->driver_id === $user->getKey());
    }

    /**
     * Aceptar un viaje es del rol conductor (historia #18): el pasajero
     * consigue el suyo solicitándolo, no aceptando el de otro.
     *
     * No depende de un viaje concreto, mismo criterio que `create()`: cualquier
     * conductor puede intentar aceptar cualquier viaje `requested`, así que el
     * permiso no se resuelve contra una fila. Que el viaje ya no esté
     * disponible, o que el conductor ya tenga uno propio activo, no se decide
     * acá: son 409 y 422 respectivamente, porque el permiso lo tiene y lo que
     * falla es el estado del viaje o el de su propia cuenta.
     */
    public function accept(User $user): bool
    {
        return $user->isDriver();
    }

    /**
     * Iniciar un viaje es del conductor **asignado a ese viaje** (historia
     * #19), y por eso sí depende de la fila, al revés que `accept()`: acá el
     * viaje ya tiene dueño del lado del conductor, y que otro cualquiera
     * pudiera marcarlo en curso sería marcar el viaje de otra persona.
     *
     * Es la contraparte de `cancel()` en el lado del conductor: mismo criterio
     * de comprobar además el rol, porque nada impide que una fila apunte a una
     * cuenta que dejó de ser conductor.
     *
     * Un viaje sin conductor asignado (`requested`) no lo puede iniciar nadie,
     * y sale de acá como 403: no es que falte un paso del ciclo de vida que el
     * conductor pueda dar, es que ese viaje no es suyo. Que el viaje sí sea
     * suyo pero esté en otro estado (`in_progress`, `completed`, `cancelled`)
     * es en cambio 422, y lo decide `StartRideRequest`.
     */
    public function start(User $user, Ride $ride): bool
    {
        return $user->isDriver()
            && $ride->driver_id !== null
            && $ride->driver_id === $user->getKey();
    }

    /**
     * Completar un viaje es del conductor **asignado a ese viaje** (historia
     * #24), mismo criterio que `start()`: depende de la fila porque el viaje
     * ya tiene dueño del lado del conductor.
     *
     * Un viaje sin conductor asignado (`requested`) no lo puede completar
     * nadie, y sale de acá como 403: no es que falte un paso del ciclo de
     * vida que el conductor pueda dar, es que ese viaje no es suyo. Que el
     * viaje sí sea suyo pero esté en otro estado (`accepted`, `completed`,
     * `cancelled`) es en cambio 422, y lo decide `CompleteRideRequest`.
     */
    public function complete(User $user, Ride $ride): bool
    {
        return $user->isDriver()
            && $ride->driver_id !== null
            && $ride->driver_id === $user->getKey();
    }

    /**
     * Publicar la ubicación es del conductor **asignado a ese viaje**
     * (historia #20), mismo criterio que `start()`: depende de la fila
     * porque el viaje ya tiene dueño del lado del conductor.
     *
     * Que el viaje no esté `in_progress` no se decide acá: eso es 422 y lo
     * resuelve `ShareRideLocationRequest`, porque el permiso de publicar lo
     * tiene y lo que falla es en qué punto del ciclo de vida está.
     */
    public function shareLocation(User $user, Ride $ride): bool
    {
        return $user->isDriver()
            && $ride->driver_id !== null
            && $ride->driver_id === $user->getKey();
    }

    /**
     * Calificar al conductor es del pasajero **dueño** de ese viaje (historia
     * #27), mismo criterio que `cancel()`: se comprueba el rol además de la
     * propiedad, porque nada impide que una fila apunte a una cuenta que dejó
     * de ser pasajero.
     *
     * Que el viaje no esté `completed`, o que el pasajero ya lo haya
     * calificado, no se decide acá: eso es 422 y lo resuelve
     * `RateDriverRequest`, porque el permiso de calificar lo tiene y lo que
     * falla es el momento del ciclo de vida o que ya no queda nada por
     * registrar.
     */
    public function rateDriver(User $user, Ride $ride): bool
    {
        return $user->isPassenger() && $ride->passenger_id === $user->getKey();
    }

    /**
     * Ver el propio historial de viajes es de cualquier cuenta autenticada:
     * el pasajero ve los que pidió (historia #29), el conductor ve los que le
     * asignaron (historia #30). Mismo criterio que `create()` para no
     * depender de una fila concreta: la consulta ya viene acotada a la cuenta
     * autenticada (`User::rides()` o `User::driverRides()` según el rol, ver
     * `ShowRideHistoryController`), así que no existe la pregunta "¿de quién
     * es este viaje?" que sí resuelven `view()` o `cancel()`.
     */
    public function viewHistory(User $user): bool
    {
        return $user->isPassenger() || $user->isDriver();
    }

    /**
     * Ver el resumen de ganancias por rango de fechas es del rol conductor
     * (historia #30): es a él a quien le corresponde el monto de cada viaje,
     * el pasajero ya vio lo que pagó en su propio historial y en el recibo
     * (historia #26). No depende de una fila concreta, mismo criterio que
     * `viewHistory()`: la consulta ya viene acotada a la cuenta autenticada.
     */
    public function viewEarnings(User $user): bool
    {
        return $user->isDriver();
    }
}
