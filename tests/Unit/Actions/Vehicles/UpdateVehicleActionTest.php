<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vehicles;

use App\Actions\Vehicles\UpdateVehicleAction;
use App\DTOs\VehicleUpdate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 */
class UpdateVehicleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualiza_solo_los_campos_presentes_en_el_dto(): void
    {
        $vehiculo = $this->vehiculo();

        $resultado = $this->action()->handle($vehiculo, new VehicleUpdate(year: 2023));

        $this->assertSame('ABC12D', $resultado->plate);
        $this->assertSame('Bajaj Boxer CT 100', $resultado->model);
        $this->assertSame(2023, $resultado->year);
    }

    public function test_no_escribe_en_la_base_si_el_dto_no_trae_ningun_campo(): void
    {
        $vehiculo = $this->vehiculo();
        $actualizadoEn = $vehiculo->updated_at;

        $this->travel(1)->minute();
        $resultado = $this->action()->handle($vehiculo, new VehicleUpdate);

        $this->assertSame('ABC12D', $resultado->plate);
        $this->assertEquals($actualizadoEn, $vehiculo->fresh()->updated_at);
    }

    /**
     * La Action no depende de que la haya llamado el Form Request: un job o un
     * comando pueden armar el DTO a mano, y las tres columnas son NOT NULL.
     */
    public function test_ignora_los_campos_de_texto_que_vienen_vacios_en_el_dto(): void
    {
        $vehiculo = $this->vehiculo();

        $resultado = $this->action()->handle(
            $vehiculo,
            new VehicleUpdate(plate: '   ', model: ''),
        );

        $this->assertSame('ABC12D', $resultado->plate);
        $this->assertSame('Bajaj Boxer CT 100', $resultado->model);

        $recargado = $vehiculo->fresh();

        $this->assertSame('ABC12D', $recargado->plate);
        $this->assertSame('Bajaj Boxer CT 100', $recargado->model);
    }

    public function test_el_dueno_no_cambia_porque_el_dto_no_lo_tiene(): void
    {
        // `VehicleUpdate` no tiene campo de dueño a propósito, igual que
        // `VehicleRegistration`: a quién pertenece la moto no es algo que la
        // entrada pueda elegir.
        $vehiculo = $this->vehiculo();
        $dueno = $vehiculo->user_id;

        $resultado = $this->action()->handle($vehiculo, new VehicleUpdate(model: 'Yamaha YBR 125'));

        $this->assertSame($dueno, $resultado->user_id);
        $this->assertDatabaseHas('vehicles', ['user_id' => $dueno, 'model' => 'Yamaha YBR 125']);
    }

    public function test_devuelve_la_instancia_con_los_datos_ya_actualizados(): void
    {
        $vehiculo = $this->vehiculo();

        $resultado = $this->action()->handle($vehiculo, new VehicleUpdate(plate: 'XYZ98W'));

        $this->assertSame($vehiculo->id, $resultado->id);
        $this->assertSame('XYZ98W', $resultado->plate);
        $this->assertSame('XYZ98W', $vehiculo->fresh()->plate);
    }

    public function test_no_toca_ningun_otro_vehiculo(): void
    {
        $ajeno = Vehicle::factory()->create(['plate' => 'JJJ11J', 'model' => 'Honda CB 110']);

        $this->action()->handle($this->vehiculo(), new VehicleUpdate(model: 'Yamaha YBR 125'));

        $this->assertDatabaseHas('vehicles', ['id' => $ajeno->id, 'model' => 'Honda CB 110']);
    }

    private function action(): UpdateVehicleAction
    {
        return $this->app->make(UpdateVehicleAction::class);
    }

    private function vehiculo(): Vehicle
    {
        return Vehicle::factory()->create([
            'user_id' => User::factory()->driver(),
            'plate' => 'ABC12D',
            'model' => 'Bajaj Boxer CT 100',
            'year' => 2022,
        ]);
    }
}
