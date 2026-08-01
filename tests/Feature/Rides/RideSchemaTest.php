<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

/**
 * Lo que garantiza la tabla `rides`, no la validación.
 *
 * El "un viaje activo por pasajero" lo sostiene un índice único sobre una
 * columna generada, y esa columna repite la lista de estados activos en SQL. Si
 * el enum gana un estado activo y la migración no, la regla se cae en silencio:
 * estos tests recorren `RideStatus::cases()` justamente para que esa divergencia
 * falle acá y no en producción.
 */
class RideSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('estadosActivos')]
    public function test_el_pasajero_no_puede_tener_dos_viajes_activos(RideStatus $estado): void
    {
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => $estado]);

        $this->expectException(QueryException::class);

        try {
            Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);
        } finally {
            $this->assertDatabaseCount('rides', 1);
        }
    }

    #[DataProvider('estadosTerminados')]
    public function test_el_viaje_terminado_no_bloquea_una_solicitud_nueva(RideStatus $estado): void
    {
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => $estado]);

        Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $this->assertDatabaseCount('rides', 2);
    }

    #[DataProvider('estadosTerminados')]
    public function test_el_pasajero_acumula_viajes_terminados_sin_limite(RideStatus $estado): void
    {
        $pasajero = User::factory()->create();

        Ride::factory()->count(3)->for($pasajero, 'passenger')->create(['status' => $estado]);

        $this->assertDatabaseCount('rides', 3);
    }

    public function test_el_viaje_activo_de_otro_pasajero_no_choca(): void
    {
        Ride::factory()->create(['status' => RideStatus::Requested]);
        Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->assertDatabaseCount('rides', 2);
    }

    public function test_liberar_el_viaje_activo_habilita_al_pasajero_de_nuevo(): void
    {
        // La columna generada se recalcula sola al cambiar el estado: no hace
        // falta que ninguna Action se acuerde de liberar nada.
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $viaje->update(['status' => RideStatus::Completed]);
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $this->assertDatabaseCount('rides', 2);
    }

    public function test_borrar_al_pasajero_se_lleva_sus_viajes(): void
    {
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create();

        $pasajero->delete();

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_el_conductor_asignado_es_opcional(): void
    {
        $viaje = Ride::factory()->create();

        $this->assertNull($viaje->driver_id);
    }

    public function test_el_viaje_conoce_a_su_pasajero(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create();

        $this->assertTrue($viaje->passenger->is($pasajero));
    }

    public function test_rechaza_un_estado_que_no_es_del_enum(): void
    {
        // La columna generada compara contra una lista fija de valores; un
        // estado escrito a mano quedaría fuera de ella y se saltaría el índice
        // sin que nadie se entere.
        $this->expectException(Throwable::class);

        Ride::factory()->create(['status' => 'esperando-al-mecanico']);
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosActivos(): array
    {
        return self::casosPorValor(array_filter(
            RideStatus::cases(),
            static fn (RideStatus $estado): bool => $estado->isActive(),
        ));
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosTerminados(): array
    {
        return self::casosPorValor(array_filter(
            RideStatus::cases(),
            static fn (RideStatus $estado): bool => ! $estado->isActive(),
        ));
    }

    /**
     * @param  array<int, RideStatus>  $estados
     * @return array<string, array{RideStatus}>
     */
    private static function casosPorValor(array $estados): array
    {
        return array_reduce(
            $estados,
            static fn (array $casos, RideStatus $estado): array => [...$casos, $estado->value => [$estado]],
            [],
        );
    }
}
