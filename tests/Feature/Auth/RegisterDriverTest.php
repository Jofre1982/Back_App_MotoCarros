<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrato de POST /api/v1/auth/register/driver — ver openapi.yaml.
 *
 * Igual que el registro de pasajero, el alta deja al conductor ya autenticado.
 * Lo que lo distingue es el perfil: la cuenta no sirve de nada sin el
 * `license_number`, así que ambos se crean en la misma operación.
 */
class RegisterDriverTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_URI = '/api/v1/auth/register/driver';

    private const PROBE_URI = '/api/v1/_probe-protegida-conductor';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get(self::PROBE_URI, function () {
            return response()->json(['user_id' => auth('api')->id()]);
        });
    }

    public function test_registra_al_conductor_y_devuelve_la_forma_del_contrato(): void
    {
        // `expires_in` sale de restarle "ahora" al claim `exp`; sin congelar el
        // reloj, cruzar un segundo entre ambas lecturas daría 899.
        $this->freezeSecond();

        $response = $this->postJson(self::REGISTER_URI, $this->datosValidos());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role'],
                    'token' => ['access_token', 'token_type', 'expires_in'],
                ],
            ])
            ->assertJsonPath('data.user.name', 'Carlos Ramírez')
            ->assertJsonPath('data.user.email', 'carlos@example.com')
            ->assertJsonPath('data.user.phone', '+573009876543')
            ->assertJsonPath('data.user.role', 'driver')
            ->assertJsonPath('data.token.token_type', 'bearer')
            // 15 minutos de TTL expresados en segundos.
            ->assertJsonPath('data.token.expires_in', 900);

        $this->assertDatabaseHas('users', [
            'email' => 'carlos@example.com',
            'phone' => '+573009876543',
            'role' => UserRole::Driver->value,
        ]);
    }

    public function test_crea_el_perfil_de_conductor_con_la_licencia(): void
    {
        // Es lo único que separa este endpoint del de pasajero: sin el perfil,
        // la cuenta tiene rol de conductor pero no la licencia que ese rol
        // exige.
        $this->postJson(self::REGISTER_URI, $this->datosValidos())->assertCreated();

        $conductor = User::firstWhere('email', 'carlos@example.com');

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $conductor->id,
            'license_number' => 'LIC-445566',
        ]);
        $this->assertSame('LIC-445566', $conductor->driverProfile->license_number);
    }

    public function test_no_expone_la_licencia_en_la_respuesta_del_registro(): void
    {
        // La respuesta reutiliza el schema `AuthenticatedUser`, que es el mismo
        // del registro de pasajero y del login: el dato del perfil se consulta
        // por el endpoint de perfil (historia #10).
        $response = $this->postJson(self::REGISTER_URI, $this->datosValidos())->assertCreated();

        $this->assertArrayNotHasKey('license_number', $response->json('data.user'));
        $this->assertSame(['user', 'token'], array_keys($response->json('data')));
    }

    public function test_el_token_devuelto_autentica_al_conductor_recien_creado(): void
    {
        $token = $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertCreated()
            ->json('data.token.access_token');

        $this->withToken($token)
            ->getJson(self::PROBE_URI)
            ->assertOk()
            ->assertJson(['user_id' => User::firstWhere('email', 'carlos@example.com')->id]);
    }

    public function test_rechaza_una_licencia_ya_registrada(): void
    {
        DriverProfile::factory()->create(['license_number' => 'LIC-445566']);

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_number')
            ->assertJsonStructure(['message', 'errors']);

        $this->assertSame(1, DriverProfile::where('license_number', 'LIC-445566')->count());
    }

    public function test_no_deja_la_cuenta_creada_cuando_la_licencia_esta_repetida(): void
    {
        // El 422 tiene que dejar el sistema como estaba: si el usuario se
        // creara igual, el conductor quedaría con cuenta y sin perfil, y el
        // reintento con otra licencia chocaría contra su propio email.
        DriverProfile::factory()->create(['license_number' => 'LIC-445566']);

        $usuariosPrevios = User::count();

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertUnprocessable();

        $this->assertSame($usuariosPrevios, User::count());
        $this->assertNull(User::firstWhere('email', 'carlos@example.com'));
    }

    public function test_rechaza_una_licencia_ya_registrada_en_otra_capitalizacion(): void
    {
        // `unique` es un `where license_number = ?` y la colación de SQLite es
        // BINARY: sin normalizar, cambiar una mayúscula alcanza para registrar
        // dos veces la misma licencia.
        DriverProfile::factory()->create(['license_number' => 'LIC-445566']);

        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'license_number' => 'lic-445566'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_number');

        $this->assertSame(1, DriverProfile::whereRaw('upper(license_number) = ?', ['LIC-445566'])->count());
    }

    public function test_guarda_la_licencia_en_su_forma_canonica(): void
    {
        // La otra mitad de la normalización: lo que se guarda tiene que ser el
        // mismo valor que se validó, o el duplicado entra por la puerta de
        // atrás en el registro siguiente.
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'license_number' => '  lic-445566 '])
            ->assertCreated();

        $this->assertDatabaseHas('driver_profiles', ['license_number' => 'LIC-445566']);
    }

    public function test_rechaza_un_email_ya_registrado(): void
    {
        // El email es único en `users` sin importar el rol: la misma persona no
        // puede tener una cuenta de pasajero y otra de conductor con el mismo
        // email.
        User::factory()->create(['email' => 'carlos@example.com']);

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::where('email', 'carlos@example.com')->count());
        $this->assertDatabaseCount('driver_profiles', 0);
    }

    public function test_rechaza_un_telefono_ya_registrado(): void
    {
        User::factory()->create(['phone' => '+573009876543']);

        $this->postJson(self::REGISTER_URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_guarda_email_y_telefono_en_su_forma_canonica(): void
    {
        $this->postJson(self::REGISTER_URI, [
            ...$this->datosValidos(),
            'email' => 'Carlos@Example.COM',
            'phone' => '573009876543',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'carlos@example.com')
            ->assertJsonPath('data.user.phone', '+573009876543');

        $this->assertDatabaseHas('users', [
            'email' => 'carlos@example.com',
            'phone' => '+573009876543',
        ]);
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
        $this->assertDatabaseCount('driver_profiles', 0);
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
            'licencia' => ['license_number', ['license_number']],
        ];
    }

    public function test_informa_todos_los_campos_faltantes_en_una_sola_respuesta(): void
    {
        // Un 422 por campo obligaría a la app móvil a reintentar cinco veces
        // para descubrir el formulario entero.
        $this->postJson(self::REGISTER_URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'password', 'license_number']);
    }

    #[DataProvider('licenciasInvalidas')]
    public function test_rechaza_una_licencia_con_formato_invalido(string $licencia): void
    {
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'license_number' => $licencia])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('license_number');

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function licenciasInvalidas(): array
    {
        return [
            'demasiado corta' => ['LIC'],
            'con símbolos' => ['LIC/445566'],
            // La normalización recorta los extremos y pasa a mayúsculas, pero
            // no junta lo que venga separado: un espacio en el medio no es una
            // licencia con formato válido, no algo que haya que "arreglar".
            'con espacios internos' => ['LIC 445 566'],
            'vacía tras normalizar' => ['   '],
        ];
    }

    #[DataProvider('contrasenasQueNoCumplenLaPolitica')]
    public function test_rechaza_una_contrasena_que_no_cumple_la_politica(string $password): void
    {
        // La política es la misma de `Password::defaults()` que usa el registro
        // de pasajero: no puede relajarse por ser otro rol.
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
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'email' => 'carlos-arroba-example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[DataProvider('telefonosInvalidos')]
    public function test_rechaza_un_telefono_con_formato_invalido(string $phone): void
    {
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'phone' => $phone])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function telefonosInvalidos(): array
    {
        return [
            'no son dígitos' => ['tres-cero-cero'],
            'prefijo duplicado' => ['++573009876543'],
            'demasiado corto' => ['+57300'],
        ];
    }

    public function test_ignora_el_rol_que_venga_en_la_entrada(): void
    {
        // El rol lo fija el endpoint, igual que en el de pasajero: mandar
        // `role` no puede dar de alta un pasajero por esta vía ni al revés.
        $this->postJson(self::REGISTER_URI, [...$this->datosValidos(), 'role' => UserRole::Passenger->value])
            ->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Driver->value);

        $this->assertTrue(User::firstWhere('email', 'carlos@example.com')->isDriver());
    }

    public function test_no_expone_la_contrasena_ni_la_guarda_en_claro(): void
    {
        $response = $this->postJson(self::REGISTER_URI, $this->datosValidos())->assertCreated();

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertStringNotContainsString('motoya2026', $response->getContent());

        $password = User::firstWhere('email', 'carlos@example.com')->password;
        $this->assertNotSame('motoya2026', $password);
        $this->assertTrue(Hash::check('motoya2026', $password));
    }

    public function test_limita_la_cantidad_de_registros_por_minuto(): void
    {
        // El endpoint es anónimo: sin límite sirve para crear cuentas en masa
        // y para sondear qué emails ya existen. Es el mismo limitador `auth`
        // que cubre al refresh y al registro de pasajero.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::REGISTER_URI, [
                ...$this->datosValidos(),
                'email' => "carlos{$i}@example.com",
                'phone' => '+57300987654'.$i,
                'license_number' => "LIC-44556{$i}",
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
            'name' => 'Carlos Ramírez',
            'email' => 'carlos@example.com',
            'phone' => '+573009876543',
            'password' => 'motoya2026',
            'license_number' => 'LIC-445566',
        ];
    }
}
