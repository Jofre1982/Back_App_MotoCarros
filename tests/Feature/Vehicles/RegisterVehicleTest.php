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
 * Contrato de POST /api/v1/me/vehicle — ver openapi.yaml.
 *
 * Un conductor no puede recibir solicitudes de viaje hasta tener una moto
 * registrada, así que lo que fija esta suite es de quién queda el vehículo (de
 * la cuenta del token, nunca de otra), que la placa no se pueda duplicar, y que
 * un segundo registro no pise en silencio el vehículo con el que el conductor
 * ya está trabajando.
 */
class RegisterVehicleTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/vehicle';

    public function test_registra_el_vehiculo_y_lo_asocia_a_la_cuenta_del_conductor(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'plate' => 'ABC12D',
                    'model' => 'Bajaj Boxer CT 100',
                    'year' => 2022,
                ],
            ]);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);
    }

    public function test_la_respuesta_no_publica_el_id_de_la_fila_ni_el_dueno(): void
    {
        // Al vehículo se llega por la cuenta que manda el token, nunca por un
        // id propio: publicarlos solo invitaría a intentar direccionarlo por
        // ahí. Mismo criterio que `driver_profile` en GET /me.
        $conductor = User::factory()->driver()->create();

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $respuesta->assertJsonMissingPath('data.id');
        $respuesta->assertJsonMissingPath('data.user_id');
    }

    public function test_el_vehiculo_queda_del_dueno_del_token_y_no_de_otra_cuenta(): void
    {
        $otroConductor = User::factory()->driver()->create();
        $propio = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($propio))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertDatabaseHas('vehicles', ['user_id' => $propio->id]);
        $this->assertDatabaseMissing('vehicles', ['user_id' => $otroConductor->id]);
    }

    public function test_rechaza_una_placa_ya_registrada_por_otro_conductor(): void
    {
        Vehicle::factory()->create(['plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_la_placa_duplicada_se_detecta_sin_importar_como_se_escriba(): void
    {
        // `unique` es un `where plate = ?` sobre una columna con índice único,
        // así que sin normalizar bastaría escribirla en minúsculas para
        // registrar dos veces la misma moto.
        Vehicle::factory()->create(['plate' => 'ABC12D']);

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, [...$this->datosValidos(), 'plate' => '  abc12d '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_guarda_la_placa_en_su_forma_canonica(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [...$this->datosValidos(), 'plate' => ' abc12d '])
            ->assertCreated()
            ->assertJsonPath('data.plate', 'ABC12D');

        $this->assertDatabaseHas('vehicles', ['plate' => 'ABC12D']);
    }

    public function test_rechaza_un_segundo_vehiculo_para_la_misma_cuenta(): void
    {
        // El endpoint es de alta, no de alta-o-reemplazo: un cliente que
        // reintenta una request que ya había llegado no debe pisar en silencio
        // los datos de la moto con la que el conductor está trabajando.
        // Actualizarla es la historia #13.
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'XYZ98W']);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle');

        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'plate' => 'XYZ98W']);
    }

    public function test_la_cuenta_de_pasajero_no_puede_registrar_un_vehiculo(): void
    {
        // 403 y no 422: no es una entrada que se pueda corregir mandando otros
        // datos, es una operación que el rol no tiene.
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_al_pasajero_le_responde_403_aunque_los_datos_sean_invalidos(): void
    {
        // La autorización corre antes que la validación: si no, el 422 le
        // diría a una cuenta sin permiso qué forma tiene que tener la entrada
        // para seguir intentando.
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [])
            ->assertForbidden();
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_la_entrada_invalida(array $sobrescritura, string $campoConError): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, [...$this->datosValidos(), ...$sobrescritura])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campoConError);

        $this->assertDatabaseCount('vehicles', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function entradasInvalidas(): array
    {
        return [
            'sin placa' => [['plate' => null], 'plate'],
            'placa vacía' => [['plate' => ''], 'plate'],
            'placa demasiado corta' => [['plate' => 'AB1'], 'plate'],
            'placa demasiado larga' => [['plate' => 'ABCDE12345F'], 'plate'],
            // Los espacios interiores no se limpian a propósito: `ABC 12D` es
            // una placa mal escrita y corresponde un 422 explicable, no que el
            // servidor adivine cómo debería haberse escrito.
            'placa con espacios interiores' => [['plate' => 'ABC 12D'], 'plate'],
            'placa con símbolos' => [['plate' => 'ABC*12D'], 'plate'],
            'sin modelo' => [['model' => null], 'model'],
            'modelo vacío' => [['model' => ''], 'model'],
            'modelo demasiado largo' => [['model' => str_repeat('a', 101)], 'model'],
            'sin año' => [['year' => null], 'year'],
            'año no numérico' => [['year' => 'dos mil veintidós'], 'year'],
            'año de dos dígitos' => [['year' => 22], 'year'],
            'año anterior al mínimo' => [['year' => 1969], 'year'],
            'año con un dedazo hacia el futuro' => [['year' => 2205], 'year'],
        ];
    }

    public function test_acepta_el_ano_siguiente_al_en_curso(): void
    {
        // Los modelos se venden adelantados al calendario, así que el tope es
        // el año que viene y no el actual.
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, [...$this->datosValidos(), 'year' => (int) date('Y') + 1])
            ->assertCreated();
    }

    public function test_ignora_el_dueno_que_venga_en_la_entrada(): void
    {
        // El dueño es siempre la cuenta del token. `user_id` es fillable en el
        // modelo, así que si el Form Request pasara `validated()` completo o el
        // DTO tuviera el campo, un cliente podría registrarle una moto a otra
        // persona.
        $victima = User::factory()->driver()->create();
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [...$this->datosValidos(), 'user_id' => $victima->id])
            ->assertCreated();

        $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id]);
        $this->assertDatabaseMissing('vehicles', ['user_id' => $victima->id]);
    }

    public function test_rechaza_el_registro_sin_token(): void
    {
        $this->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_rechaza_el_registro_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_el_registro_con_un_token_expirado(): void
    {
        $token = JWTAuth::fromUser(User::factory()->driver()->create());

        $this->travel(30)->minutes();

        $this->withToken($token)
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(): array
    {
        return [
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ];
    }
}
