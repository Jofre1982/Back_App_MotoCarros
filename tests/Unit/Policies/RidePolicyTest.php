<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Policy invocada directo, sin pasar por HTTP.
 *
 * Solicitar un viaje es del rol pasajero: el conductor consigue viajes
 * aceptando los que ya existen (historia #18), no creándolos.
 */
class RidePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_puede_solicitar_un_viaje(): void
    {
        $this->assertTrue(User::factory()->create()->can('create', Ride::class));
    }

    public function test_el_conductor_no_puede_solicitar_un_viaje(): void
    {
        $this->assertFalse(User::factory()->driver()->create()->can('create', Ride::class));
    }
}
