<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\UpdateProfileAction;
use App\DTOs\ProfileUpdate;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class UpdateProfileActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualiza_solo_los_campos_presentes_en_el_dto(): void
    {
        $usuario = User::factory()->create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
        ]);

        $resultado = $this->action()->handle(
            $usuario,
            new ProfileUpdate(name: 'Ana García Pérez'),
        );

        $this->assertSame('Ana García Pérez', $resultado->name);
        $this->assertSame('ana@example.com', $resultado->email);
        $this->assertSame('+573001234567', $resultado->phone);
    }

    public function test_no_escribe_en_la_base_si_el_dto_no_trae_ningun_campo(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana García']);
        $actualizadoEn = $usuario->updated_at;

        $this->travel(1)->minute();
        $resultado = $this->action()->handle($usuario, new ProfileUpdate);

        $this->assertSame('Ana García', $resultado->name);
        $this->assertEquals($actualizadoEn, $usuario->fresh()->updated_at);
    }

    public function test_el_rol_no_cambia_porque_el_dto_no_lo_tiene(): void
    {
        $usuario = User::factory()->create();

        $resultado = $this->action()->handle(
            $usuario,
            new ProfileUpdate(name: 'Ana García Pérez'),
        );

        $this->assertSame(UserRole::Passenger, $resultado->role);
    }

    public function test_devuelve_la_instancia_con_los_datos_ya_actualizados(): void
    {
        $usuario = User::factory()->create();

        $resultado = $this->action()->handle(
            $usuario,
            new ProfileUpdate(email: 'nueva@example.com'),
        );

        $this->assertSame($usuario->id, $resultado->id);
        $this->assertSame('nueva@example.com', $resultado->email);
        $this->assertSame('nueva@example.com', $usuario->fresh()->email);
    }

    private function action(): UpdateProfileAction
    {
        return $this->app->make(UpdateProfileAction::class);
    }
}
