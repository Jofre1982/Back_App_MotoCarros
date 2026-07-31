<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de PATCH /api/v1/me — ver openapi.yaml.
 *
 * Es un PATCH parcial (historia #11): solo los campos de contacto presentes
 * en el body cambian, el rol nunca se acepta como entrada, y no hay forma de
 * tocar la cuenta de otra persona.
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me';

    public function test_actualiza_el_nombre(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana García']);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['name' => 'Ana García Pérez'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana García Pérez');

        $this->assertSame('Ana García Pérez', $usuario->fresh()->name);
    }

    public function test_actualiza_el_email(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@example.com']);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['email' => 'ana.garcia@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'ana.garcia@example.com');

        $this->assertSame('ana.garcia@example.com', $usuario->fresh()->email);
    }

    public function test_normaliza_el_email_a_minusculas(): void
    {
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['email' => 'Ana.Garcia@Example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'ana.garcia@example.com');
    }

    public function test_actualiza_el_telefono_y_lo_normaliza_con_el_signo_mas(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['phone' => '573007654321'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+573007654321');

        $this->assertSame('+573007654321', $usuario->fresh()->phone);
    }

    public function test_actualiza_varios_campos_a_la_vez(): void
    {
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, [
                'name' => 'Ana García Pérez',
                'email' => 'ana.nueva@example.com',
                'phone' => '+573007654321',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana García Pérez')
            ->assertJsonPath('data.email', 'ana.nueva@example.com')
            ->assertJsonPath('data.phone', '+573007654321');
    }

    public function test_los_campos_ausentes_del_body_no_cambian(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
        ]);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['name' => 'Ana García Pérez'])
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.phone', '+573001234567');
    }

    public function test_reenviar_el_propio_email_sin_cambiarlo_no_es_un_conflicto(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@example.com']);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['email' => 'ana@example.com', 'name' => 'Ana García Pérez'])
            ->assertOk();
    }

    public function test_rechaza_cambiar_el_email_a_uno_ya_usado_por_otra_cuenta(): void
    {
        User::factory()->create(['email' => 'otra@example.com']);
        $usuario = User::factory()->create(['email' => 'ana@example.com']);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['email' => 'otra@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame('ana@example.com', $usuario->fresh()->email);
    }

    public function test_rechaza_cambiar_el_telefono_a_uno_ya_usado_por_otra_cuenta(): void
    {
        User::factory()->create(['phone' => '+573009999999']);
        $usuario = User::factory()->create(['phone' => '+573001234567']);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['phone' => '+573009999999'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->assertSame('+573001234567', $usuario->fresh()->phone);
    }

    public function test_rechaza_un_email_con_formato_invalido(): void
    {
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['email' => 'no-es-un-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_rechaza_un_telefono_con_formato_invalido(): void
    {
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['phone' => 'no-es-un-telefono'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Un campo presente pero vacío es 422, no un borrado: `users.email` es NOT
     * NULL UNIQUE y el login es por email, así que aceptarlo dejaría la cuenta
     * sin forma de entrar —y a la segunda que lo hiciera, con un 500 por el
     * índice único en vez del 422 que el resto del repo garantiza.
     *
     * @param  string  $campo  el que va vacío en el body
     * @param  string  $vacio  la forma de "vacío" que se prueba
     */
    #[DataProvider('camposVacios')]
    public function test_rechaza_un_campo_presente_pero_vacio(string $campo, string $vacio): void
    {
        $usuario = User::factory()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
        ]);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, [$campo => $vacio])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campo]);

        $recargado = $usuario->fresh();

        $this->assertSame('Ana García', $recargado->name);
        $this->assertSame('ana@example.com', $recargado->email);
        $this->assertSame('+573001234567', $recargado->phone);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function camposVacios(): array
    {
        return [
            'name vacío' => ['name', ''],
            'name solo espacios' => ['name', '   '],
            'email vacío' => ['email', ''],
            'email solo espacios' => ['email', '   '],
            'phone vacío' => ['phone', ''],
            'phone solo espacios' => ['phone', '   '],
        ];
    }

    public function test_ignora_un_rol_enviado_en_el_body(): void
    {
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['role' => 'driver', 'name' => 'Ana García Pérez'])
            ->assertOk()
            ->assertJsonPath('data.role', 'passenger');

        $this->assertTrue($usuario->fresh()->isPassenger());
    }

    public function test_ignora_un_id_enviado_en_el_body(): void
    {
        $otra = User::factory()->create();
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['id' => $otra->id, 'name' => 'Ana García Pérez'])
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id);

        $this->assertSame('Ana García Pérez', $usuario->fresh()->name);
        $this->assertNotSame('Ana García Pérez', $otra->fresh()->name);
    }

    public function test_no_permite_cambiar_la_contrasena_por_esta_via(): void
    {
        $usuario = User::factory()->create(['password' => 'motoya2026']);
        $hashOriginal = $usuario->password;

        $this->withToken(JWTAuth::fromUser($usuario))
            ->patchJson(self::URI, ['password' => 'otra-contrasena-999'])
            ->assertOk();

        $this->assertSame($hashOriginal, $usuario->fresh()->password);
        $this->assertTrue(Hash::check('motoya2026', $usuario->fresh()->password));
    }

    public function test_el_perfil_del_conductor_conserva_su_licencia_tras_actualizar_el_nombre(): void
    {
        $conductor = User::factory()->driver()->create(['name' => 'Carlos Ramírez']);
        DriverProfile::factory()->create([
            'user_id' => $conductor->id,
            'license_number' => 'LIC-445566',
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['name' => 'Carlos Ramírez Ruiz'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Carlos Ramírez Ruiz')
            ->assertJsonPath('data.driver_profile.license_number', 'LIC-445566');
    }

    public function test_rechaza_la_actualizacion_sin_token(): void
    {
        $this->patchJson(self::URI, ['name' => 'Ana García Pérez'])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_no_afecta_a_otra_cuenta(): void
    {
        $otra = User::factory()->create(['name' => 'Otra Persona']);
        $propia = User::factory()->create(['name' => 'Ana García']);

        $this->withToken(JWTAuth::fromUser($propia))
            ->patchJson(self::URI, ['name' => 'Ana García Pérez'])
            ->assertOk();

        $this->assertSame('Otra Persona', $otra->fresh()->name);
    }
}
