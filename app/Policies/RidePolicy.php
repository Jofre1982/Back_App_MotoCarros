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
     * Cancelar un viaje es del pasajero **dueño** de ese viaje (historia #16).
     *
     * Que el viaje ya no esté en `requested` **no** se decide acá: eso
     * responde 422, porque el permiso de cancelar lo tiene y lo que cambia es
     * el flujo a seguir (ver `CancelRideRequest`). Un 403 le diría que el
     * permiso le falta, que es otra cosa.
     *
     * El rol se comprueba además de la propiedad, mismo criterio que
     * `VehiclePolicy::update()`: nada impide que una fila apunte a una cuenta
     * que dejó de ser pasajero.
     */
    public function cancel(User $user, Ride $ride): bool
    {
        return $user->isPassenger() && $ride->passenger_id === $user->getKey();
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
}
