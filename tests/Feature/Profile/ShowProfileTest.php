<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/me — ver openapi.yaml.
 *
 * Es la primera request que hacen las apps móviles al arrancar, así que lo que
 * fija esta suite es de quién son los datos que devuelve (los del token, y no
 * los de otra cuenta ni los que el token traía cacheados en sus claims) y qué
 * datos nunca salen (la contraseña).
 */
class ShowProfileTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me';

    public function test_devuelve_los_datos_de_la_cuenta_autenticada(): void
    {
        $pasajera = User::factory()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
        ]);

        $this->withToken(JWTAuth::fromUser($pasajera))
            ->getJson(self::URI)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $pasajera->id,
                    'name' => 'Ana García',
                    'email' => 'ana@example.com',
                    'phone' => '+573001234567',
                    'role' => 'passenger',
                ],
            ]);
    }

    public function test_no_expone_la_contrasena_ni_su_hash(): void
    {
        // `assertExactJson` de arriba ya lo cubre para el pasajero, pero esto es
        // el criterio de aceptación explícito de la historia y no depende de que
        // nadie afloje esa aserción: se comprueba contra el cuerpo crudo, así
        // que también atrapa un hash escondido dentro de `driver_profile`.
        $conductor = User::factory()->driver()->create(['password' => 'motoya2026']);
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk();

        $respuesta->assertJsonMissingPath('data.password');
        $this->assertStringNotContainsString('motoya2026', $respuesta->getContent());
        $this->assertStringNotContainsString($conductor->getAuthPassword(), $respuesta->getContent());
    }

    public function test_el_perfil_del_conductor_incluye_su_licencia(): void
    {
        // El registro de conductor (#7) responde el schema `User`, que no lleva
        // la licencia a propósito; este endpoint es el único lugar donde el dato
        // vuelve al cliente (ver .claude/STANDARDS.md).
        $conductor = User::factory()->driver()->create([
            'name' => 'Carlos Ramírez',
            'email' => 'carlos@example.com',
            'phone' => '+573009876543',
        ]);
        DriverProfile::factory()->create([
            'user_id' => $conductor->id,
            'license_number' => 'LIC-445566',
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $conductor->id,
                    'name' => 'Carlos Ramírez',
                    'email' => 'carlos@example.com',
                    'phone' => '+573009876543',
                    'role' => 'driver',
                    'driver_profile' => [
                        'license_number' => 'LIC-445566',
                        // Historia #17: `false`/`null` por defecto, mismo
                        // criterio que `started_at` en `RideResource` — el
                        // campo viaja siempre presente, aunque no aplique
                        // todavía.
                        'is_available' => false,
                        'latitude' => null,
                        'longitude' => null,
                        'location_updated_at' => null,
                    ],
                ],
            ]);
    }

    public function test_la_cuenta_de_pasajero_omite_la_clave_del_perfil_de_conductor(): void
    {
        // Omitida entera, no en `null`: no es un dato que falte, es un dato que
        // no aplica, y un `null` empujaría al cliente a preguntarse si el
        // conductor todavía no cargó su licencia.
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonMissingPath('data.driver_profile');
    }

    public function test_el_conductor_sin_perfil_creado_tambien_omite_la_clave(): void
    {
        // El registro crea cuenta y perfil en una transacción, así que este
        // estado no debería existir; si aparece igual (una carga de datos, un
        // rol cambiado a mano) el endpoint tiene que responder el perfil que sí
        // hay, no reventar buscando el que falta.
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonPath('data.role', 'driver')
            ->assertJsonMissingPath('data.driver_profile');
    }

    public function test_devuelve_el_perfil_de_quien_manda_el_token_y_no_el_de_otra_cuenta(): void
    {
        User::factory()->create(['name' => 'Otra Persona']);
        $propia = User::factory()->create(['name' => 'Ana García']);

        $this->withToken(JWTAuth::fromUser($propia))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonPath('data.id', $propia->id)
            ->assertJsonPath('data.name', 'Ana García');
    }

    public function test_los_datos_salen_de_la_base_y_no_de_los_claims_del_token(): void
    {
        // El `role` viaja en el JWT solo para que el cliente sepa qué UI
        // mostrar, y quedó congelado al emitirse. Si este endpoint lo leyera de
        // ahí, respondería el estado de la cuenta en el pasado — que es justo lo
        // contrario de para lo que la app móvil lo consulta al arrancar.
        $usuario = User::factory()->create(['name' => 'Ana García']);

        $token = JWTAuth::fromUser($usuario);

        $usuario->forceFill([
            'name' => 'Ana García Ruiz',
            'role' => UserRole::Driver,
        ])->save();

        $this->withToken($token)
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana García Ruiz')
            ->assertJsonPath('data.role', 'driver');
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
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->travel(30)->minutes();

        $this->withToken($token)
            ->getJson(self::URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_consulta_con_un_token_ya_cerrado(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        // En producción cada request estrena aplicación; acá el guard `api`
        // conserva cacheado el usuario de la request anterior y pasaría sin
        // volver a validar el token contra la blacklist.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson(self::URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }
}
