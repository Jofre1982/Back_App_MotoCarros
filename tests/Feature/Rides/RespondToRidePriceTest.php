<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RecordsBroadcasts;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/{id}/price-response — ver openapi.yaml.
 *
 * El conductor propone el precio al aceptar un viaje solo recogida; el
 * pasajero lo acepta (y recién ahí se puede iniciar) o lo rechaza (y el
 * viaje vuelve a quedar libre para otro conductor).
 */
class RespondToRidePriceTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    private function viajeConOferta(?User $pasajero = null, ?User $conductor = null): Ride
    {
        $conductor ??= $this->conductor();

        return Ride::factory()->pickupOnly()->create([
            'passenger_id' => ($pasajero ?? User::factory()->create())->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Accepted,
            'agreed_price' => 7000,
        ]);
    }

    private function conductor(): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        return $conductor;
    }

    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }

    public function test_al_aceptar_el_conductor_recien_lo_ve_confirmado(): void
    {
        $pasajero = User::factory()->create();
        $viaje = $this->viajeConOferta($pasajero);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.price_confirmed', false);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.agreed_price', 7000)
            ->assertJsonPath('data.price_confirmed', true);

        $this->assertNotNull($viaje->refresh()->price_confirmed_at);
    }

    public function test_al_rechazar_el_viaje_vuelve_a_quedar_libre(): void
    {
        $pasajero = User::factory()->create();
        $viaje = $this->viajeConOferta($pasajero);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.agreed_price', null)
            ->assertJsonPath('data.price_confirmed', false);

        $viaje->refresh();
        $this->assertSame(RideStatus::Requested, $viaje->status);
        $this->assertNull($viaje->driver_id);
    }

    public function test_el_conductor_no_inicia_sin_precio_confirmado(): void
    {
        $conductor = $this->conductor();
        $viaje = $this->viajeConOferta(conductor: $conductor);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);
    }

    public function test_con_precio_confirmado_el_conductor_inicia(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conductor();
        $viaje = $this->viajeConOferta($pasajero, $conductor);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])
            ->assertOk();

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_un_viaje_de_catalogo_viene_confirmado_y_no_espera_respuesta(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $this->conductor()->id,
            'status' => RideStatus::Accepted,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.price_confirmed', true);
    }

    public function test_no_responde_dos_veces_ni_sin_oferta(): void
    {
        $pasajero = User::factory()->create();
        $viaje = $this->viajeConOferta($pasajero);
        $token = JWTAuth::fromUser($pasajero);

        $this->withToken($token)->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])->assertOk();
        $this->withToken($token)->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'reject'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);

        // Un pasajero solo tiene un viaje activo: el caso "sin oferta" va con otro.
        $otro = User::factory()->create();
        $sinConductor = Ride::factory()->pickupOnly()->create(['passenger_id' => $otro->id, 'status' => RideStatus::Requested]);
        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($otro))
            ->postJson("/api/v1/rides/{$sinConductor->id}/price-response", ['decision' => 'accept'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);
    }

    public function test_rechaza_una_decision_desconocida(): void
    {
        $pasajero = User::factory()->create();
        $viaje = $this->viajeConOferta($pasajero);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'tal vez'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['decision']);
    }

    public function test_solo_el_pasajero_duenio_responde(): void
    {
        $conductor = $this->conductor();
        $viaje = $this->viajeConOferta(conductor: $conductor);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])
            ->assertForbidden();

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])
            ->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $viaje = $this->viajeConOferta();

        $this->postJson("/api/v1/rides/{$viaje->id}/price-response", ['decision' => 'accept'])->assertUnauthorized();
    }
}
