<?php

declare(strict_types=1);

namespace Tests\Feature\Vehicles;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/me/vehicle — ver openapi.yaml.
 *
 * Lo que fija esta suite es que el vehículo devuelto es siempre el de la
 * cuenta del token, que el shape es el mismo que el de `POST`/`PATCH` sobre
 * el mismo recurso, y el orden de los rechazos: 403 el rol, 404 la cuenta de
 * conductor sin vehículo todavía.
 */
class ShowVehicleTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/vehicle';

    public function test_devuelve_el_vehiculo_del_conductor_autenticado(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create([
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'plate' => 'ABC12D',
                    'model' => 'Bajaj Boxer CT 100',
                    'year' => 2022,
                ],
            ]);
    }

    public function test_la_respuesta_no_publica_el_id_de_la_fila_ni_el_dueno(): void
    {
        // Mismo criterio que el alta y la actualización: al vehículo se llega
        // por la cuenta que manda el token, nunca por un id propio.
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk();

        $respuesta->assertJsonMissingPath('data.id');
        $respuesta->assertJsonMissingPath('data.user_id');
    }

    public function test_no_devuelve_el_vehiculo_de_otro_conductor(): void
    {
        $otro = User::factory()->driver()->create();
        Vehicle::factory()->create([
            'user_id' => $otro->id,
            'plate' => 'JJJ11J',
            'model' => 'Honda CB 110',
            'year' => 2020,
        ]);

        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonPath('data.plate', 'ABC12D');
    }

    public function test_responde_404_si_el_conductor_todavia_no_registro_su_vehiculo(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_la_cuenta_de_pasajero_no_puede_consultar_un_vehiculo(): void
    {
        // 403 y no 404: para el pasajero no es que le falte registrar la
        // moto, es que operar vehículos no es de su rol. Mismo trato que en
        // el alta y la actualización.
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI)
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_consulta_sin_token(): void
    {
        $this->getJson(self::URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_consulta_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->getJson(self::URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_consulta_con_un_token_expirado(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id]);
        $token = JWTAuth::fromUser($conductor);

        $this->travel(30)->minutes();

        $this->withToken($token)
            ->getJson(self::URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }
}
