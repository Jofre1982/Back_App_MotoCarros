<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vehicles;

use App\Actions\Vehicles\RegisterVehicleAction;
use App\DTOs\VehicleRegistration;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 */
class RegisterVehicleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_vehiculo_asociado_al_conductor_recibido(): void
    {
        $conductor = User::factory()->driver()->create();

        $vehiculo = $this->action()->handle($conductor, $this->registracion());

        $this->assertTrue($vehiculo->exists);
        $this->assertSame('ABC12D', $vehiculo->plate);
        $this->assertSame('Bajaj Boxer CT 100', $vehiculo->model);
        $this->assertSame(2022, $vehiculo->year);
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseHas('vehicles', [
            'user_id' => $conductor->id,
            'plate' => 'ABC12D',
        ]);
    }

    public function test_el_dueno_sale_del_conductor_recibido_y_no_del_dto(): void
    {
        // `VehicleRegistration` no tiene campo de dueño a propósito: quién
        // registra la moto lo decide quien invoca la Action (el guard, en el
        // caso del endpoint), no los datos que llegan del cliente.
        $conductor = User::factory()->driver()->create();

        $vehiculo = $this->action()->handle($conductor, $this->registracion());

        $this->assertSame($conductor->id, $vehiculo->user_id);
        $this->assertTrue($conductor->vehicle()->is($vehiculo));
    }

    public function test_la_placa_duplicada_no_deja_un_segundo_vehiculo(): void
    {
        // El Form Request ya valida que la placa esté libre, pero entre esa
        // consulta y este INSERT cabe otra alta con la misma placa: el índice
        // único de la tabla es lo que garantiza que no queden dos.
        Vehicle::factory()->create(['plate' => 'ABC12D']);

        $this->expectException(QueryException::class);

        try {
            $this->action()->handle(User::factory()->driver()->create(), $this->registracion());
        } finally {
            $this->assertDatabaseCount('vehicles', 1);
        }
    }

    public function test_el_conductor_que_ya_tiene_vehiculo_no_puede_registrar_otro(): void
    {
        // Misma carrera que la placa, pero sobre el dueño: la relación es uno a
        // uno y el índice único de `user_id` la sostiene aunque dos requests
        // pasen la validación a la vez.
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'XYZ98W']);

        $this->expectException(QueryException::class);

        try {
            $this->action()->handle($conductor, $this->registracion());
        } finally {
            $this->assertDatabaseCount('vehicles', 1);
            $this->assertDatabaseHas('vehicles', ['user_id' => $conductor->id, 'plate' => 'XYZ98W']);
        }
    }

    private function action(): RegisterVehicleAction
    {
        return $this->app->make(RegisterVehicleAction::class);
    }

    private function registracion(): VehicleRegistration
    {
        return new VehicleRegistration(
            plate: 'ABC12D',
            model: 'Bajaj Boxer CT 100',
            year: 2022,
        );
    }
}
