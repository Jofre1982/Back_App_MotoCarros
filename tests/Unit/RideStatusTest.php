<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\RideStatus;
use PHPUnit\Framework\TestCase;

/**
 * Los valores del enum son parte del contrato publicado (`status` del schema
 * `Ride` en openapi.yaml) y además viajan tal cual a la columna `rides.status`,
 * así que renombrar uno rompe los dos lados a la vez.
 */
class RideStatusTest extends TestCase
{
    public function test_los_valores_son_los_publicados_en_el_contrato(): void
    {
        $this->assertSame(
            ['requested', 'accepted', 'in_progress', 'completed', 'cancelled'],
            array_column(RideStatus::cases(), 'value'),
        );
    }

    public function test_activo_es_el_viaje_que_todavia_ocupa_al_pasajero(): void
    {
        // Es lo que decide si puede pedir otro: mientras el viaje siga en
        // alguno de estos estados, hay uno en curso.
        $this->assertSame(
            [RideStatus::Requested, RideStatus::Accepted, RideStatus::InProgress],
            RideStatus::active(),
        );
    }

    public function test_el_viaje_terminado_ya_no_esta_activo(): void
    {
        $this->assertTrue(RideStatus::Requested->isActive());
        $this->assertTrue(RideStatus::Accepted->isActive());
        $this->assertTrue(RideStatus::InProgress->isActive());
        $this->assertFalse(RideStatus::Completed->isActive());
        $this->assertFalse(RideStatus::Cancelled->isActive());
    }
}
