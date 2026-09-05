<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\CreateRideAction;
use App\DTOs\Coordinates;
use App\DTOs\PushNotification;
use App\DTOs\RideRequest;
use App\DTOs\RouteEstimate;
use App\Enums\RideStatus;
use App\Events\Realtime\RideRequested;
use App\Exceptions\RouteEstimationFailed;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Services\Maps\RouteEstimator;
use App\Services\Realtime\PushNotificationGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 */
class CreateRideActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fares.currency', 'COP');
        Config::set('fares.base', 1500);
        Config::set('fares.per_kilometer', 800);
        Config::set('fares.per_minute', 100);
        Config::set('fares.per_waiting_minute', 60);
        Config::set('fares.minimum', 3000);
        Config::set('fares.rounding_step', 50);
    }

    public function test_crea_el_viaje_en_requested_con_el_trayecto_y_la_tarifa_estimados(): void
    {
        $pasajero = User::factory()->create();

        $viaje = $this->action(new RouteEstimate(7421, 842))->handle($pasajero, $this->solicitud());

        $this->assertTrue($viaje->exists);
        $this->assertSame(RideStatus::Requested, $viaje->status);
        $this->assertSame(7421, $viaje->estimated_distance_meters);
        $this->assertSame(842, $viaje->estimated_duration_seconds);
        $this->assertSame(8850, $viaje->estimated_fare);
        $this->assertSame('COP', $viaje->currency);
    }

    public function test_guarda_el_origen_y_el_destino_recibidos(): void
    {
        $viaje = $this->action()->handle(User::factory()->create(), $this->solicitud());

        $this->assertSame(4.710989, $viaje->origin_latitude);
        $this->assertSame(-74.072092, $viaje->origin_longitude);
        $this->assertSame(4.698, $viaje->destination_latitude);
        $this->assertSame(-74.061, $viaje->destination_longitude);
    }

    public function test_el_pasajero_sale_del_parametro_y_no_del_dto(): void
    {
        // `RideRequest` no tiene campo de pasajero a propósito, igual que el DTO
        // del alta de vehículo: quién pide el viaje lo decide quien invoca el
        // caso de uso (el guard, en el endpoint), no la entrada del cliente.
        $pasajero = User::factory()->create();

        $viaje = $this->action()->handle($pasajero, $this->solicitud());

        $this->assertSame($pasajero->id, $viaje->passenger_id);
        $this->assertNull($viaje->driver_id);
    }

    public function test_estima_el_trayecto_entre_el_origen_y_el_destino_recibidos(): void
    {
        $estimador = new class implements RouteEstimator
        {
            public ?Coordinates $origin = null;

            public ?Coordinates $destination = null;

            public function estimate(Coordinates $origin, Coordinates $destination): RouteEstimate
            {
                $this->origin = $origin;
                $this->destination = $destination;

                return new RouteEstimate(1000, 120);
            }
        };

        $this->app->instance(RouteEstimator::class, $estimador);
        $this->app->make(CreateRideAction::class)->handle(User::factory()->create(), $this->solicitud());

        $this->assertSame(4.710989, $estimador->origin?->latitude);
        $this->assertSame(-74.061, $estimador->destination?->longitude);
    }

    public function test_no_deja_ningun_viaje_si_el_proveedor_no_entrega_una_ruta(): void
    {
        $estimador = new class implements RouteEstimator
        {
            public function estimate(Coordinates $origin, Coordinates $destination): RouteEstimate
            {
                throw RouteEstimationFailed::noRouteAvailable('google');
            }
        };

        $this->app->instance(RouteEstimator::class, $estimador);

        $this->expectException(RouteEstimationFailed::class);

        try {
            $this->app->make(CreateRideAction::class)->handle(User::factory()->create(), $this->solicitud());
        } finally {
            $this->assertDatabaseCount('rides', 0);
        }
    }

    public function test_el_pasajero_con_un_viaje_activo_no_puede_abrir_otro(): void
    {
        // El Form Request ya lo valida para responder 422, pero entre esa
        // consulta y este INSERT caben dos solicitudes simultáneas: lo que
        // garantiza que no queden dos activos es el índice de la tabla.
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $this->expectException(QueryException::class);

        try {
            $this->action()->handle($pasajero, $this->solicitud());
        } finally {
            $this->assertDatabaseCount('rides', 1);
        }
    }

    /**
     * Historia #17: avisa a los conductores disponibles cercanos al origen.
     */
    public function test_dispara_ride_requested_con_los_conductores_disponibles_cercanos(): void
    {
        Event::fake([RideRequested::class]);
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available(latitude: 4.710989, longitude: -74.072092)
            ->create(['user_id' => $conductor->id]);

        $viaje = $this->action()->handle(User::factory()->create(), $this->solicitud());

        Event::assertDispatched(
            RideRequested::class,
            fn (RideRequested $evento): bool => $evento->rideId === $viaje->id
                && $evento->nearbyDriverIds === [$conductor->id],
        );
    }

    public function test_no_dispara_ride_requested_si_no_hay_conductores_disponibles_cerca(): void
    {
        Event::fake([RideRequested::class]);

        $this->action()->handle(User::factory()->create(), $this->solicitud());

        Event::assertNotDispatched(RideRequested::class);
    }

    public function test_no_dispara_ride_requested_para_un_conductor_no_disponible(): void
    {
        Event::fake([RideRequested::class]);
        $conductor = User::factory()->driver()->create();
        // No disponible: la fábrica lo deja así por defecto.
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->action()->handle(User::factory()->create(), $this->solicitud());

        Event::assertNotDispatched(RideRequested::class);
    }

    /**
     * Historia #67: además del websocket, avisa por push a cada dispositivo
     * registrado de los conductores cercanos.
     */
    public function test_envia_notificacion_push_a_cada_dispositivo_del_conductor_cercano(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available(latitude: 4.710989, longitude: -74.072092)
            ->create(['user_id' => $conductor->id]);
        DeviceToken::factory()->create(['user_id' => $conductor->id, 'token' => 'device-1']);
        DeviceToken::factory()->create(['user_id' => $conductor->id, 'token' => 'device-2']);

        $gateway = new class implements PushNotificationGateway
        {
            /** @var list<string> */
            public array $enviados = [];

            public function send(DeviceToken $token, PushNotification $notification): void
            {
                $this->enviados[] = $token->token;
            }
        };
        $this->app->instance(PushNotificationGateway::class, $gateway);

        $this->action()->handle(User::factory()->create(), $this->solicitud());

        $this->assertEqualsCanonicalizing(['device-1', 'device-2'], $gateway->enviados);
    }

    public function test_no_falla_si_el_conductor_cercano_no_tiene_ningun_dispositivo_registrado(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available(latitude: 4.710989, longitude: -74.072092)
            ->create(['user_id' => $conductor->id]);

        $gateway = new class implements PushNotificationGateway
        {
            public int $llamadas = 0;

            public function send(DeviceToken $token, PushNotification $notification): void
            {
                $this->llamadas++;
            }
        };
        $this->app->instance(PushNotificationGateway::class, $gateway);

        $viaje = $this->action()->handle(User::factory()->create(), $this->solicitud());

        $this->assertTrue($viaje->exists);
        $this->assertSame(0, $gateway->llamadas);
    }

    private function action(?RouteEstimate $trayecto = null): CreateRideAction
    {
        $estimacion = $trayecto ?? new RouteEstimate(7421, 842);

        $this->app->instance(RouteEstimator::class, new class($estimacion) implements RouteEstimator
        {
            public function __construct(private RouteEstimate $estimacion) {}

            public function estimate(Coordinates $origin, Coordinates $destination): RouteEstimate
            {
                return $this->estimacion;
            }
        });

        return $this->app->make(CreateRideAction::class);
    }

    private function solicitud(): RideRequest
    {
        return new RideRequest(
            origin: new Coordinates(4.710989, -74.072092),
            destination: new Coordinates(4.698, -74.061),
        );
    }
}
