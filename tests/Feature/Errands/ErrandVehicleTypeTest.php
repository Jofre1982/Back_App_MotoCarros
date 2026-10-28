<?php

declare(strict_types=1);

namespace Tests\Feature\Errands;

use App\Enums\ErrandStatus;
use App\Enums\VehicleType;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Mandados sin destino obligatorio y "Pedir motocarga": el mismo mandado,
 * marcado con el vehículo que necesita, que solo ven y aceptan los
 * conductores con ese vehículo — ver openapi.yaml (`/errands`).
 */
class ErrandVehicleTypeTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/errands';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_pide_un_mandado_sin_destino_y_por_defecto_es_de_motocarro(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, [
                'description' => 'Comprar dos panes y una leche',
                'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
            ])
            ->assertCreated()
            ->assertJsonPath('data.vehicle_type', 'motocarro')
            ->assertJsonPath('data.destination', null)
            ->assertJsonPath('data.destination_text', null);

        $this->assertDatabaseHas('errands', [
            'passenger_id' => $pasajero->id,
            'vehicle_type' => 'motocarro',
            'destination_site_id' => null,
        ]);
    }

    public function test_pide_una_motocarga_con_destino_en_texto(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, [
                'description' => 'Una nevera y dos cajas',
                'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
                'vehicle_type' => 'motocarga',
                'destination_text' => '  Barrio Las Palmas  ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.vehicle_type', 'motocarga')
            ->assertJsonPath('data.destination_text', 'Barrio Las Palmas');
    }

    public function test_rechaza_un_tipo_de_vehiculo_desconocido(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                'description' => 'Una nevera',
                'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
                'vehicle_type' => 'camion',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_type']);
    }

    public function test_cada_conductor_ve_solo_los_mandados_de_su_vehiculo(): void
    {
        $mandado = Errand::factory()->create(['vehicle_type' => VehicleType::Motocarro]);
        $carga = Errand::factory()->create(['vehicle_type' => VehicleType::Motocarga]);

        $deMotocarga = $this->conductorDisponible(VehicleType::Motocarga);
        $deMotocarro = $this->conductorDisponible(VehicleType::Motocarro);
        $sinVehiculo = $this->conductorDisponible(null);

        $this->assertSame([$carga->id], $this->idsVistosPor($deMotocarga));
        $this->assertSame([$mandado->id], $this->idsVistosPor($deMotocarro));
        $this->assertSame([$mandado->id], $this->idsVistosPor($sinVehiculo));
    }

    public function test_un_conductor_de_motocarro_no_acepta_una_motocarga(): void
    {
        $carga = Errand::factory()->create(['vehicle_type' => VehicleType::Motocarga]);

        $this->withToken(JWTAuth::fromUser($this->conductorDisponible(VehicleType::Motocarro)))
            ->postJson("/api/v1/errands/{$carga->id}/accept", ['agreed_price' => 20000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['errand']);

        $this->assertSame(ErrandStatus::Requested, $carga->refresh()->status);
    }

    public function test_un_conductor_de_motocarga_no_acepta_un_mandado_de_motocarro(): void
    {
        $mandado = Errand::factory()->create(['vehicle_type' => VehicleType::Motocarro]);

        $this->withToken(JWTAuth::fromUser($this->conductorDisponible(VehicleType::Motocarga)))
            ->postJson("/api/v1/errands/{$mandado->id}/accept", ['agreed_price' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['errand']);
    }

    public function test_un_conductor_de_motocarga_acepta_una_motocarga(): void
    {
        $carga = Errand::factory()->create(['vehicle_type' => VehicleType::Motocarga]);

        $this->withToken(JWTAuth::fromUser($this->conductorDisponible(VehicleType::Motocarga)))
            ->postJson("/api/v1/errands/{$carga->id}/accept", ['agreed_price' => 20000])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    private function conductorDisponible(?VehicleType $tipo): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'is_available_for_errands' => true]);
        if ($tipo !== null) {
            Vehicle::factory()->create(['user_id' => $conductor->id, 'type' => $tipo]);
        }

        return $conductor;
    }

    /**
     * @return list<int>
     */
    private function idsVistosPor(User $conductor): array
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();

        return array_column(
            $this->withToken(JWTAuth::fromUser($conductor))->getJson(self::URI)->assertOk()->json('data'),
            'id',
        );
    }
}
