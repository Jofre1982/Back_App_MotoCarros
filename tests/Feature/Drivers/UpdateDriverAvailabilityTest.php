<?php

declare(strict_types=1);

namespace Tests\Feature\Drivers;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de PATCH /api/v1/me/availability — ver openapi.yaml.
 *
 * Historia #17: es lo que decide si un conductor entra en la búsqueda de
 * `NearbyDriverFinder` cuando se crea un viaje nuevo. Lo que fija esta suite
 * es qué perfil se toca (siempre el de la cuenta del token), que la ubicación
 * sea obligatoria al marcarse disponible pero no al marcarse no disponible, y
 * el orden de los rechazos: 403 el rol, 404 el recurso que no existe, 422 la
 * entrada — mismo criterio que `UpdateVehicleTest`.
 */
class UpdateDriverAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/availability';

    public function test_marca_disponible_y_guarda_la_ubicacion(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'license_number' => 'LIC-445566']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, [
                'is_available' => true,
                'latitude' => 4.710989,
                'longitude' => -74.072092,
            ])
            ->assertOk()
            ->assertJsonPath('data.license_number', 'LIC-445566')
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.latitude', 4.710989)
            ->assertJsonPath('data.longitude', -74.072092);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $conductor->id,
            'is_available' => true,
            'latitude' => 4.710989,
            'longitude' => -74.072092,
        ]);
    }

    public function test_marca_no_disponible_sin_mandar_ubicacion(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $conductor->id,
            'is_available' => false,
        ]);
    }

    public function test_exige_ubicacion_al_marcarse_disponible(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertDatabaseHas('driver_profiles', ['user_id' => $conductor->id, 'is_available' => false]);
    }

    public function test_rechaza_is_available_ausente(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['latitude' => 4.7, 'longitude' => -74.0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_available');
    }

    public function test_rechaza_coordenadas_fuera_de_rango_al_marcarse_disponible(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available' => true, 'latitude' => 95.0, 'longitude' => -74.0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');
    }

    public function test_no_publica_el_id_de_la_fila_ni_el_dueno(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available' => false])
            ->assertOk();

        $respuesta->assertJsonMissingPath('data.id');
        $respuesta->assertJsonMissingPath('data.user_id');
    }

    public function test_responde_404_si_el_conductor_todavia_no_tiene_perfil(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available' => false])
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_el_404_pesa_mas_que_la_entrada_invalida(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->patchJson(self::URI, ['is_available' => 'tal-vez'])
            ->assertNotFound();
    }

    public function test_la_cuenta_de_pasajero_no_puede_marcar_disponibilidad(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(self::URI, ['is_available' => true, 'latitude' => 4.7, 'longitude' => -74.0])
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_al_pasajero_le_responde_403_aunque_los_datos_sean_invalidos(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(self::URI, [])
            ->assertForbidden();
    }

    public function test_no_toca_el_perfil_de_otro_conductor_aunque_venga_su_id_en_el_body(): void
    {
        $otro = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $otro->id]);

        $conductor = User::factory()->driver()->create();
        $propio = DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, [
                'id' => $propio->id + 1000,
                'user_id' => $otro->id,
                'is_available' => true,
                'latitude' => 4.7,
                'longitude' => -74.0,
            ])
            ->assertOk();

        $this->assertDatabaseHas('driver_profiles', ['user_id' => $conductor->id, 'is_available' => true]);
        $this->assertDatabaseHas('driver_profiles', ['user_id' => $otro->id, 'is_available' => false]);
    }

    public function test_rechaza_la_actualizacion_sin_token(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->patchJson(self::URI, ['is_available' => false])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_actualizacion_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->patchJson(self::URI, ['is_available' => false])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }
}
