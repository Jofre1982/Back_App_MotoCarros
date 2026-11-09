<?php

declare(strict_types=1);

namespace Tests\Feature\Favorites;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET/POST /api/v1/me/favorite-drivers y DELETE
 * /api/v1/me/favorite-drivers/{id} — ver openapi.yaml.
 */
class FavoriteDriversTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/favorite-drivers';

    public function test_guarda_un_conductor_que_ya_lo_llevo_y_lo_lista(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conductor('Jofre', disponible: true);
        Ride::factory()->create([
            'passenger_id' => $pasajero->id, 'driver_id' => $conductor->id, 'status' => RideStatus::Completed,
        ]);
        $token = JWTAuth::fromUser($pasajero);

        $this->withToken($token)
            ->postJson(self::URI, ['driver_id' => $conductor->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $conductor->id)
            ->assertJsonPath('data.receiving_rides', true)
            ->assertJsonPath('data.vehicle.plate', 'ABC12D');

        // Repetirlo no duplica.
        $this->withToken($token)->postJson(self::URI, ['driver_id' => $conductor->id])->assertCreated();

        $this->withToken($token)
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jofre');
    }

    public function test_tambien_vale_un_conductor_de_un_mandado(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conductor('Ana');
        Errand::factory()->create(['passenger_id' => $pasajero->id, 'driver_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, ['driver_id' => $conductor->id])
            ->assertCreated()
            ->assertJsonPath('data.receiving_rides', false);
    }

    public function test_no_guarda_a_un_conductor_que_nunca_lo_llevo(): void
    {
        $pasajero = User::factory()->create();
        $extrano = $this->conductor('Pedro');

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, ['driver_id' => $extrano->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver_id');

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver_id');
    }

    public function test_quita_un_favorito_solo_de_la_propia_lista(): void
    {
        $pasajero = User::factory()->create();
        $otro = User::factory()->create();
        $conductor = $this->conductor('Jofre');
        $pasajero->favoriteDrivers()->attach($conductor->id);
        $otro->favoriteDrivers()->attach($conductor->id);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->deleteJson(self::URI."/{$conductor->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('favorite_drivers', ['passenger_id' => $pasajero->id]);
        $this->assertDatabaseHas('favorite_drivers', ['passenger_id' => $otro->id, 'driver_id' => $conductor->id]);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    private function conductor(string $nombre, bool $disponible = false): User
    {
        $conductor = User::factory()->driver()->create(['name' => $nombre]);
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'is_available' => $disponible]);
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        return $conductor;
    }
}
