<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrato de POST /api/v1/auth/register/passenger — ver openapi.yaml.
 *
 * Un registro exitoso deja al pasajero ya autenticado: devuelve la cuenta y un
 * access token usable, para que la app móvil no tenga que encadenar un login
 * inmediatamente después.
 */
class RegisterPassengerTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URI = '/api/v1/auth/register/passenger';

    private const PROBE_URI = '/api/v1/_probe-protegida';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get(self::PROBE_URI, function () {
            return response()->json(['user_id' => auth('api')->id()]);
        });
    }

    public function test_registra_al_pasajero_y_devuelve_la_forma_del_contrato(): void
    {
        // `expires_in` se calcula restándole "ahora" al claim `exp` del token
        // emitido; sin congelar el reloj, cruzar un segundo entre ambas
        // lecturas daría 899 y haría el test intermitente.
        $this->freezeSecond();

        $response = $this->postJson(self::REGISTER_URI, $this->datosValidos());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role'],
                    'token' => ['access_token', 'token_type', 'expires_in'],
                ],
            ])
            ->assertJsonPath('data.user.name', 'Ana García')
            ->assertJsonPath('data.user.email', 'ana@example.com')
            ->assertJsonPath('data.user.phone', '+573001234567')
            ->assertJsonPath('data.user.role', 'passenger')
            ->assertJsonPath('data.token.token_type', 'bearer')
            // 15 minutos de TTL expresados en segundos.
            ->assertJsonPath('data.token.expires_in', 900);

        $this->assertDatabaseHas('users', [
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
            'role' => UserRole::Passenger->value,
        ]);
    }

    public function test_el_token_devuelto_autentica_al_pasajero_recien_creado(): void
    {
        // Lo que hace útil al endpoint no es que devuelva "un string": es que
        // ese token sirva de verdad contra la API, para el usuario correcto.
        $token = $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertCreated()
            ->json('data.token.access_token');

        $this->withToken($token)
            ->getJson(self::PROBE_URI)
            ->assertOk()
            ->assertJson(['user_id' => User::firstWhere('email', 'ana@example.com')->id]);
    }

    public function test_rechaza_un_email_ya_registrado(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonStructure(['message', 'errors']);

        $this->assertSame(1, User::where('email', 'ana@example.com')->count());
    }

    public function test_rechaza_un_telefono_ya_registrado(): void
    {
        // El teléfono es único en la tabla: sin validarlo, el duplicado
        // escalaría a un 500 por violación de constraint en vez de un 422.
        User::factory()->create(['phone' => '+573001234567']);

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    /**
     * @param  list<string>  $camposEsperados
     */
    #[DataProvider('camposRequeridos')]
    public function test_rechaza_la_falta_de_un_campo_requerido(string $campoFaltante, array $camposEsperados): void
    {
        $datos = $this->datosValidos();
        unset($datos[$campoFaltante]);

        $this->postJson(self::REGISTER_URI, $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($camposEsperados);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function camposRequeridos(): array
    {
        return [
            'nombre' => ['name', ['name']],
            'email' => ['email', ['email']],
            'teléfono' => ['phone', ['phone']],
            'contraseña' => ['password', ['password']],
        ];
    }

    public function test_informa_todos_los_campos_faltantes_en_una_sola_respuesta(): void
    {
        // Un 422 por campo obligaría a la app móvil a reintentar cuatro veces
        // para descubrir el formulario entero.
        $this->postJson(self::REGISTER_URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);
    }

    #[DataProvider('contrasenasQueNoCumplenLaPolitica')]
    public function test_rechaza_una_contrasena_que_no_cumple_la_politica(string $password): void
    {
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'password' => $password])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contrasenasQueNoCumplenLaPolitica(): array
    {
        return [
            'demasiado corta' => ['moto26'],
            'sin números' => ['motoyamotoya'],
            'sin letras' => ['20262026262'],
        ];
    }

    public function test_rechaza_un_email_con_formato_invalido(): void
    {
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'email' => 'ana-arroba-example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_rechaza_un_telefono_con_formato_invalido(): void
    {
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'phone' => 'tres-cero-cero'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_ignora_el_rol_que_venga_en_la_entrada(): void
    {
        // El rol lo fija el endpoint. Si se pudiera mandar, cualquiera se daría
        // de alta como conductor por el endpoint de pasajeros y se saltaría los
        // requisitos del registro de conductor (historia #7).
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'role' => UserRole::Driver->value])
            ->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Passenger->value);

        $this->assertTrue(User::firstWhere('email', 'ana@example.com')->isPassenger());
    }

    public function test_no_expone_la_contrasena_ni_la_guarda_en_claro(): void
    {
        $response = $this->postJson(self::REGISTER_URI, $this->datosValidos())->assertCreated();

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertStringNotContainsString('motoya2026', $response->getContent());

        $password = User::firstWhere('email', 'ana@example.com')->password;
        $this->assertNotSame('motoya2026', $password);
        $this->assertTrue(Hash::check('motoya2026', $password));
    }

    public function test_limita_la_cantidad_de_registros_por_minuto(): void
    {
        // El endpoint es anónimo: sin límite, sirve para crear cuentas en masa
        // y para sondear qué emails ya existen. Es el mismo limitador `auth`
        // que cubre al refresh (ver AppServiceProvider).
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::REGISTER_URI, [
                ...$this->datosValidos(),
                'email' => "ana{$i}@example.com",
                'phone' => '+57300123456'.$i,
            ])->assertCreated();
        }

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    /**
     * @return array<string, string>
     */
    private function datosValidos(): array
    {
        return [
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
            'password' => 'motoya2026',
        ];
    }
}
