<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

/**
 * Los parámetros vigentes de la tarifa: cuánto vale arrancar, cada kilómetro,
 * cada minuto en ruta y cada minuto de espera.
 *
 * Existe como objeto y no como lecturas sueltas de `config('fares.*')` dentro
 * del motor de tarifa por dos motivos: el motor queda siendo una función pura
 * de sus parámetros (testeable sin contenedor ni entorno), y una configuración
 * imposible —un paso de redondeo de 0, una tarifa negativa— revienta al
 * resolver la aplicación y no al calcular la primera tarifa de un pasajero
 * real.
 *
 * Todos los montos son enteros en la unidad mínima de `$currency`; ver la nota
 * sobre dinero en config/fares.php.
 */
final readonly class FareSchedule
{
    public function __construct(
        public string $currency,
        public int $base,
        public int $perKilometer,
        public int $perMinute,
        public int $perWaitingMinute,
        public int $minimum,
        public int $roundingStep,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Moneda inválida: '{$currency}'. Se espera un código ISO 4217 de tres letras mayúsculas.");
        }

        foreach ([
            'base' => $base,
            'per_kilometer' => $perKilometer,
            'per_minute' => $perMinute,
            'per_waiting_minute' => $perWaitingMinute,
            'minimum' => $minimum,
        ] as $name => $amount) {
            if ($amount < 0) {
                throw new InvalidArgumentException("La tarifa '{$name}' no puede ser negativa: {$amount}.");
            }
        }

        if ($roundingStep < 1) {
            throw new InvalidArgumentException("El paso de redondeo debe ser al menos 1: {$roundingStep}.");
        }
    }
}
