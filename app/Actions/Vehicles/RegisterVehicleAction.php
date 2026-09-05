<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\DTOs\VehicleRegistration;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Registra la moto de un conductor y se la asocia.
 *
 * El conductor llega como parámetro y no dentro del DTO: es lo que garantiza
 * que el dueño salga siempre de quien invoca el caso de uso (el usuario que
 * resolvió el guard) y nunca de la entrada.
 *
 * No hay transacción, a diferencia del registro de conductor: acá se escribe una
 * sola fila, así que no existe el estado a medias que allá había que evitar. Lo
 * que sostiene la unicidad —la placa y el "un vehículo por conductor"— son los
 * índices de la tabla; el Form Request los adelanta para poder responder 422 en
 * vez de 500, pero entre su consulta y este INSERT caben dos altas simultáneas y
 * ahí la última pierde con una violación de constraint. Es el mismo trato que le
 * da el alta de conductor a una licencia duplicada.
 */
final class RegisterVehicleAction
{
    public function handle(User $driver, VehicleRegistration $registration): Vehicle
    {
        return $driver->vehicle()->create([
            'plate' => $registration->plate,
            'type' => $registration->type,
            'year' => $registration->year,
        ]);
    }
}
