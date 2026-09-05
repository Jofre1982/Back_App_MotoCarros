<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\DTOs\VehicleUpdate;
use App\Enums\VehicleType;
use App\Models\Vehicle;

/**
 * Corrige los datos de la moto ya registrada de un conductor.
 *
 * Recibe el vehículo y no el conductor, al revés que `RegisterVehicleAction`:
 * allá había que crear la fila y el dueño tenía que salir de quien invoca; acá
 * la fila ya existe y quién puede editarla es una pregunta de autorización que
 * responde `VehiclePolicy`, no esta Action. Lo que sí se mantiene es que el
 * dueño no viaja en el DTO: esta Action no puede cambiar de manos una moto
 * porque no tiene con qué.
 *
 * La placa no se normaliza acá, igual que en el alta: llevarla a su forma
 * canónica es trabajo de la entrada (`prepareForValidation`), y tiene que pasar
 * antes de validar la unicidad, no después.
 */
final class UpdateVehicleAction
{
    public function handle(Vehicle $vehicle, VehicleUpdate $update): Vehicle
    {
        // Descarta también la cadena vacía, y no solo `null`: las tres columnas
        // son NOT NULL y una moto sin placa ni tipo no le sirve al pasajero
        // que tiene que reconocerla. `UpdateVehicleRequest` ya lo ataja con
        // `sometimes|required`, pero esta Action es invocable desde un job o un
        // comando que no pasa por el Form Request, y su invariante propia es
        // "no escribo un dato vacío".
        $datos = array_filter(
            [
                'plate' => $update->plate,
                'type' => $update->type,
                'year' => $update->year,
            ],
            static fn (string|int|VehicleType|null $valor): bool => is_string($valor)
                ? trim($valor) !== ''
                : $valor !== null,
        );

        if ($datos !== []) {
            $vehicle->update($datos);
        }

        return $vehicle;
    }
}
