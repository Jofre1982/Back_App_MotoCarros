<?php

declare(strict_types=1);

namespace Tests\Feature\Vehicles;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de PATCH /api/v1/me/vehicle — ver openapi.yaml.
 *
 * Lo que fija esta suite es qué vehículo se toca (siempre el de la cuenta del
 * token, nunca el de otra), que sea un PATCH de verdad —lo que no se manda no
 * se cambia, y lo que se manda no puede venir vacío—, y el orden en que se
 * responden los rechazos: 403 el rol, 404 el recurso que no existe, 422 la
 * entrada. Ese orden importa: al revés, un 422 le detallaría la forma de la
 * entrada a quien ni siquiera tiene el endpoint disponible.
 */
class UpdateVehicleTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/vehicle';

    public function test_actualiza_el_vehiculo_del_conductor_y_responde_los_nuevos_valores(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create([
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, [
                'plate' => 'XYZ98W',
                'model' => 'Bajaj Boxer CT 100 ES',
                'year' => 2023,
            ])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'plate' => 'XYZ98W',
                    'model' => 'Bajaj Boxer CT 100 ES',
                    'year' => 2023,
                ],
            ]);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $conductor->id,
            'plate' => 'XYZ98W',
            'model' => 'Bajaj Boxer CT 100 ES',
            'year' => 2023,
        ]);
    }

    public function test_solo_cambia_los_campos_presentes_en_el_body(): void
    {
        // Es un PATCH parcial: la forma de no tocar un dato es no mandarlo.
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create([
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['year' => 2023])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'plate' => 'ABC12D',
                    'model' => 'Bajaj Boxer CT 100',
                    'year' => 2023,
                ],
            ]);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2023,
        ]);
    }

    public function test_un_body_vacio_no_cambia_nada_y_devuelve_el_vehiculo(): void
    {
        $conductor = User::factory()->driver()->create();
        $vehiculo = Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);
        $actualizadoEn = $vehiculo->updated_at;

        $this->travel(1)->minute();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, [])
            ->assertOk()
            ->assertJsonPath('data.plate', 'ABC12D');

        $this->assertEquals($actualizadoEn, $vehiculo->fresh()->updated_at);
    }

    public function test_la_respuesta_no_publica_el_id_de_la_fila_ni_el_dueno(): void
    {
        // Mismo criterio que el alta: al vehículo se llega por la cuenta que
        // manda el token, nunca por un id propio.
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['year' => 2023])
            ->assertOk();

        $respuesta->assertJsonMissingPath('data.id');
        $respuesta->assertJsonMissingPath('data.user_id');
    }

    public function test_responde_404_si_el_conductor_todavia_no_registro_su_vehiculo(): void
    {
        // No hay recurso que actualizar: lo que le corresponde es el alta.
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['year' => 2023])
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_el_404_pesa_mas_que_la_entrada_invalida(): void
    {
        // Que el recurso no exista se responde antes que la forma del body: un
        // 422 haría creer que corrigiendo los datos la request funcionaría.
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->patchJson(self::URI, ['year' => 'dos mil veintitrés'])
            ->assertNotFound();
    }

    public function test_la_cuenta_de_pasajero_no_puede_actualizar_un_vehiculo(): void
    {
        // 403 y no 404: para el pasajero no es que le falte registrar la moto,
        // es que operar vehículos no es de su rol. Mismo trato que en el alta.
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(self::URI, ['year' => 2023])
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_al_pasajero_le_responde_403_aunque_los_datos_sean_invalidos(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(self::URI, ['year' => 'dos mil veintitrés'])
            ->assertForbidden();
    }

    public function test_no_toca_el_vehiculo_de_otro_conductor_aunque_venga_su_id_en_el_body(): void
    {
        // La ruta no lleva id, así que no hay forma de direccionar el vehículo
        // ajeno; esto verifica que tampoco se cuele por el body. Que la Policy
        // rechace al no-dueño se prueba directo en VehiclePolicyTest: por esta
        // ruta el caso es estructuralmente inalcanzable.
        $otro = User::factory()->driver()->create();
        $ajeno = Vehicle::factory()->create([
            'user_id' => $otro->id,
            'plate' => 'JJJ11J',
            'model' => 'Honda CB 110',
            'year' => 2020,
        ]);

        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, [
                'id' => $ajeno->id,
                'user_id' => $otro->id,
                'model' => 'Yamaha YBR 125',
            ])
            ->assertOk()
            ->assertJsonPath('data.plate', 'ABC12D');

        $this->assertDatabaseHas('vehicles', [
            'id' => $ajeno->id,
            'user_id' => $otro->id,
            'model' => 'Honda CB 110',
        ]);
        $this->assertDatabaseHas('vehicles', [
            'user_id' => $conductor->id,
            'model' => 'Yamaha YBR 125',
        ]);
    }

    public function test_rechaza_una_placa_ya_registrada_por_otro_conductor(): void
    {
        Vehicle::factory()->create(['plate' => 'XYZ98W']);

        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['plate' => 'XYZ98W'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'plate' => 'ABC12D']);
    }

    public function test_reenviar_la_placa_propia_sin_cambiarla_no_es_un_conflicto(): void
    {
        // La unicidad se comprueba ignorando la propia fila: un cliente que
        // manda el formulario completo sin haber tocado la placa no puede
        // chocar consigo mismo.
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['plate' => 'ABC12D', 'year' => 2023])
            ->assertOk()
            ->assertJsonPath('data.plate', 'ABC12D')
            ->assertJsonPath('data.year', 2023);
    }

    public function test_guarda_la_placa_en_su_forma_canonica(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['plate' => ' xyz98w '])
            ->assertOk()
            ->assertJsonPath('data.plate', 'XYZ98W');

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'plate' => 'XYZ98W']);
    }

    public function test_la_placa_duplicada_se_detecta_sin_importar_como_se_escriba(): void
    {
        Vehicle::factory()->create(['plate' => 'XYZ98W']);

        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['plate' => '  xyz98w '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'plate' => 'ABC12D']);
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_la_entrada_invalida(array $body, string $campoConError): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create([
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campoConError);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);
    }

    /**
     * Un campo ausente es "no lo toques", pero uno presente no puede venir
     * vacío ni en `null`: las tres columnas son NOT NULL y un vehículo sin
     * placa ni modelo no le sirve al pasajero que tiene que reconocerlo.
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function entradasInvalidas(): array
    {
        return [
            'placa vacía' => [['plate' => ''], 'plate'],
            'placa en null' => [['plate' => null], 'plate'],
            'placa solo con espacios' => [['plate' => '   '], 'plate'],
            'placa demasiado corta' => [['plate' => 'AB1'], 'plate'],
            'placa demasiado larga' => [['plate' => 'ABCDE12345F'], 'plate'],
            'placa con espacios interiores' => [['plate' => 'ABC 12D'], 'plate'],
            'placa con símbolos' => [['plate' => 'ABC*12D'], 'plate'],
            'modelo vacío' => [['model' => ''], 'model'],
            'modelo en null' => [['model' => null], 'model'],
            'modelo demasiado largo' => [['model' => str_repeat('a', 101)], 'model'],
            'año en null' => [['year' => null], 'year'],
            'año no numérico' => [['year' => 'dos mil veintidós'], 'year'],
            'año de dos dígitos' => [['year' => 22], 'year'],
            'año anterior al mínimo' => [['year' => 1969], 'year'],
            'año con un dedazo hacia el futuro' => [['year' => 2205], 'year'],
        ];
    }

    public function test_acepta_el_ano_siguiente_al_en_curso(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['year' => (int) date('Y') + 1])
            ->assertOk();
    }

    public function test_ignora_el_dueno_que_venga_en_la_entrada(): void
    {
        // `user_id` es fillable en el modelo: si el Form Request pasara
        // `validated()` completo o el DTO tuviera el campo, un cliente podría
        // regalarle su moto a otra cuenta.
        $victima = User::factory()->driver()->create();
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['user_id' => $victima->id, 'year' => 2023])
            ->assertOk();

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'year' => 2023]);
        $this->assertDatabaseMissing('vehicles', ['user_id' => $victima->id]);
    }

    public function test_rechaza_la_actualizacion_sin_token(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);

        $this->patchJson(self::URI, ['year' => 2023])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'plate' => 'ABC12D']);
    }

    public function test_rechaza_la_actualizacion_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->patchJson(self::URI, ['year' => 2023])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_actualizacion_con_un_token_expirado(): void
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'year' => 2022]);
        $token = JWTAuth::fromUser($conductor);

        $this->travel(30)->minutes();

        $this->withToken($token)
            ->patchJson(self::URI, ['year' => 2023])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'year' => 2022]);
    }
}
