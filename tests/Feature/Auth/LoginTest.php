<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrato de POST /api/v1/auth/login — ver openapi.yaml.
 *
 * Es la puerta de entrada de las cuentas ya registradas, y el mismo endpoint
 * para ambos roles. Lo que más se prueba acá no es el camino feliz sino la
 * indistinguibilidad de los dos fallos posibles: si "contraseña incorrecta" y
 * "email no registrado" no responden exactamente lo mismo, el endpoint sirve
 * para averiguar qué emails tienen cuenta.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_URI = '/api/v1/auth/login';

    private const PROBE_URI = '/api/v1/_probe-protegida';

    private const PASSWORD = 'motoya2026';

    private const MENSAJE_GENERICO = 'El email o la contraseña no son correctos.';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get(self::PROBE_URI, function () {
            return response()->json(['user_id' => auth('api')->id()]);
        });
    }

    public function test_autentica_a_la_cuenta_y_devuelve_la_forma_del_contrato(): void
    {
        // `expires_in` se calcula restándole "ahora" al claim `exp` del token
        // emitido; sin congelar el reloj, cruzar un segundo entre ambas
        // lecturas daría 899 y haría el test intermitente.
        $this->freezeSecond();

        $usuario = $this->cuentaRegistrada();

        $this->postJson(self::LOGIN_URI, $this->credencialesValidas())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role'],
                    'token' => ['access_token', 'token_type', 'expires_in'],
                ],
            ])
            ->assertJsonPath('data.user.id', $usuario->id)
            ->assertJsonPath('data.user.email', 'ana@example.com')
            ->assertJsonPath('data.user.role', 'passenger')
            ->assertJsonPath('data.token.token_type', 'bearer')
            // 15 minutos de TTL expresados en segundos.
            ->assertJsonPath('data.token.expires_in', 900);
    }

    public function test_el_token_devuelto_autentica_a_la_cuenta_que_inicio_sesion(): void
    {
        // Lo que hace útil al endpoint no es que devuelva "un string": es que
        // ese token sirva de verdad contra la API, para el usuario correcto.
        $usuario = $this->cuentaRegistrada();

        $token = $this->postJson(self::LOGIN_URI, $this->credencialesValidas())
            ->assertOk()
            ->json('data.token.access_token');

        $this->withToken($token)
            ->getJson(self::PROBE_URI)
            ->assertOk()
            ->assertJson(['user_id' => $usuario->id]);
    }

    public function test_un_conductor_inicia_sesion_por_el_mismo_endpoint(): void
    {
        // Ambos roles comparten endpoint: el rol no se manda ni se elige, viaja
        // de vuelta para que el cliente sepa qué UI mostrar.
        $this->cuentaRegistrada(['email' => 'carlos@example.com'], conductor: true);

        $this->postJson(self::LOGIN_URI, [
            'email' => 'carlos@example.com',
            'password' => self::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'driver');
    }

    public function test_rechaza_una_contrasena_incorrecta_con_401(): void
    {
        $this->cuentaRegistrada();

        $this->postJson(self::LOGIN_URI, [
            ...$this->credencialesValidas(),
            'password' => 'motoya2027',
        ])
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::MENSAJE_GENERICO]);
    }

    public function test_rechaza_un_email_no_registrado_con_401(): void
    {
        // 401 y no 404: que el email no exista es un fallo de credenciales, y
        // decir "no existe" ya sería contestar la pregunta que no queremos
        // contestar.
        $this->postJson(self::LOGIN_URI, $this->credencialesValidas())
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::MENSAJE_GENERICO]);
    }

    public function test_los_dos_fallos_responden_exactamente_lo_mismo(): void
    {
        // El criterio de aceptación central de la historia. Cualquier
        // diferencia —código, mensaje, un `errors` de más— convierte al
        // endpoint en un oráculo de qué emails tienen cuenta, que es el primer
        // paso de un relleno de credenciales.
        $this->cuentaRegistrada();

        $contrasenaIncorrecta = $this->postJson(self::LOGIN_URI, [
            ...$this->credencialesValidas(),
            'password' => 'motoya2027',
        ]);

        $emailInexistente = $this->postJson(self::LOGIN_URI, [
            'email' => 'nadie@example.com',
            'password' => self::PASSWORD,
        ]);

        $this->assertSame($contrasenaIncorrecta->getStatusCode(), $emailInexistente->getStatusCode());
        $this->assertSame($contrasenaIncorrecta->json(), $emailInexistente->json());
    }

    public function test_no_devuelve_token_cuando_las_credenciales_son_incorrectas(): void
    {
        $this->cuentaRegistrada();

        $respuesta = $this->postJson(self::LOGIN_URI, [
            ...$this->credencialesValidas(),
            'password' => 'motoya2027',
        ])->assertUnauthorized();

        $this->assertNull($respuesta->json('data'));
        $this->assertStringNotContainsString('access_token', $respuesta->getContent());
    }

    public function test_acepta_el_email_en_cualquier_capitalizacion(): void
    {
        // El registro guarda el email en minúsculas (ver la normalización
        // decidida en #6). Sin normalizar acá también, quien tecleó
        // `Ana@Example.COM` en el teclado del móvil quedaría afuera de su
        // propia cuenta con un mensaje que dice que su contraseña está mal.
        $this->cuentaRegistrada();

        $this->postJson(self::LOGIN_URI, [
            'email' => 'Ana@Example.COM',
            'password' => self::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'ana@example.com');
    }

    /**
     * @param  list<string>  $camposEsperados
     */
    #[DataProvider('camposRequeridos')]
    public function test_rechaza_la_falta_de_un_campo_requerido(string $campoFaltante, array $camposEsperados): void
    {
        $this->cuentaRegistrada();

        $credenciales = $this->credencialesValidas();
        unset($credenciales[$campoFaltante]);

        $this->postJson(self::LOGIN_URI, $credenciales)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($camposEsperados);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function camposRequeridos(): array
    {
        return [
            'email' => ['email', ['email']],
            'contraseña' => ['password', ['password']],
        ];
    }

    public function test_rechaza_un_email_con_formato_invalido(): void
    {
        // Un 422 acá no delata nada: una cadena que no es un email no puede
        // corresponder a ninguna cuenta, así que la respuesta no depende de qué
        // haya en la base.
        $this->postJson(self::LOGIN_URI, [
            ...$this->credencialesValidas(),
            'email' => 'ana-arroba-example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[DataProvider('contrasenasQueNoCumplenLaPoliticaDelRegistro')]
    public function test_no_valida_la_contrasena_contra_la_politica_del_registro(string $password): void
    {
        // El login no aplica `Password::defaults()`: si lo hiciera, una
        // contraseña corta o sin números respondería 422 y una larga y bien
        // formada respondería 401, y esa diferencia delata cuál de los dos
        // campos falló. Una contraseña que no cumple la política simplemente no
        // coincide con ninguna cuenta.
        $this->cuentaRegistrada();

        $this->postJson(self::LOGIN_URI, [...$this->credencialesValidas(), 'password' => $password])
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::MENSAJE_GENERICO]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contrasenasQueNoCumplenLaPoliticaDelRegistro(): array
    {
        return [
            'demasiado corta' => ['moto26'],
            'sin números' => ['motoyamotoya'],
            'sin letras' => ['20262026262'],
        ];
    }

    public function test_no_expone_la_contrasena_en_la_respuesta(): void
    {
        $this->cuentaRegistrada();

        $respuesta = $this->postJson(self::LOGIN_URI, $this->credencialesValidas())->assertOk();

        $this->assertArrayNotHasKey('password', $respuesta->json('data.user'));
        $this->assertStringNotContainsString(self::PASSWORD, $respuesta->getContent());
    }

    public function test_limita_la_cantidad_de_intentos_por_minuto(): void
    {
        // El endpoint es anónimo y contesta "sí/no" sobre una contraseña: sin
        // límite es un banco de pruebas de fuerza bruta. Es el mismo limitador
        // `auth` que cubre al registro y al refresh (ver AppServiceProvider).
        $this->cuentaRegistrada();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::LOGIN_URI, [
                ...$this->credencialesValidas(),
                'password' => "intento{$i}",
            ])->assertUnauthorized();
        }

        $this->postJson(self::LOGIN_URI, $this->credencialesValidas())
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function cuentaRegistrada(array $atributos = [], bool $conductor = false): User
    {
        $factory = $conductor ? User::factory()->driver() : User::factory();

        return $factory->create([
            'email' => 'ana@example.com',
            'password' => self::PASSWORD,
            ...$atributos,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function credencialesValidas(): array
    {
        return [
            'email' => 'ana@example.com',
            'password' => self::PASSWORD,
        ];
    }
}
