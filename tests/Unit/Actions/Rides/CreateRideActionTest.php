<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\CreateRideAction;
use App\DTOs\Coordinates;
use App\DTOs\PushNotification;
use App\DTOs\RideRequest;
use App\Enums\PricingUnit;
use App\Enums\RideStatus;
use App\Enums\VehicleType;
use App\Events\Realtime\RideRequested;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use App\Services\Realtime\PushNotificationGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 */
class CreateRideActionTest extends TestCase
{
    use RefreshDatabase;

    private Site $sitio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sitio = $this->siteWithFare(8850);
    }

    public function test_crea_el_viaje_en_requested_con_la_tarifa_del_sitio(): void
    {
        $pasajero = User::factory()->create();

        $viaje = $this->action()->handle($pasajero, $this->solicitud());

        $this->assertTrue($viaje->exists);
        $this->assertSame(RideStatus::Requested, $viaje->status);
        $this->assertSame($this->sitio->id, $viaje->destination_site_id);
        $this->assertSame(8850, $viaje->estimated_fare);
        $this->assertSame('COP', $viaje->currency);
    }

    public function test_multiplica_el_precio_por_pasajero_cuando_el_sitio_cobra_por_persona(): void
    {
        $sitio = $this->siteWithFare(4000, PricingUnit::PerPerson);

        $viaje = $this->action()->handle(
            User::factory()->create(),
            $this->solicitud(destinationSiteId: $sitio->id, passengerCount: 3),
        );

        $this->assertSame(12000, $viaje->estimated_fare);
    }

    public function test_no_multiplica_el_precio_cuando_el_sitio_cobra_por_viaje(): void
    {
        $sitio = $this->siteWithFare(20000, PricingUnit::PerTrip);

        $viaje = $this->action()->handle(
            User::factory()->create(),
            $this->solicitud(destinationSiteId: $sitio->id, passengerCount: 3),
        );

        $this->assertSame(20000, $viaje->estimated_fare);
    }

    public function test_guarda_el_origen_y_el_sitio_de_destino_recibidos(): void
    {
        $viaje = $this->action()->handle(User::factory()->create(), $this->solicitud());

        $this->assertSame(4.710989, $viaje->origin_latitude);
        $this->assertSame(-74.072092, $viaje->origin_longitude);
        $this->assertSame($this->sitio->id, $viaje->destination_site_id);
    }

    public function test_guarda_la_cantidad_de_pasajeros(): void
    {
        $viaje = $this->action()->handle(User::factory()->create(), $this->solicitud(passengerCount: 2));

        $this->assertSame(2, $viaje->passenger_count);
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
                && $evento->destinationSiteId === $this->sitio->id
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

    private function action(): CreateRideAction
    {
        return $this->app->make(CreateRideAction::class);
    }

    private function solicitud(?int $destinationSiteId = null, int $passengerCount = 1): RideRequest
    {
        return new RideRequest(
            origin: new Coordinates(4.710989, -74.072092),
            destinationSiteId: $destinationSiteId ?? $this->sitio->id,
            passengerCount: $passengerCount,
        );
    }

    private function siteWithFare(
        int $dayPrice,
        PricingUnit $pricingUnit = PricingUnit::PerTrip,
        ?int $nightPrice = null,
    ): Site {
        $site = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $site->id,
            'vehicle_type' => VehicleType::Motocarro,
            'pricing_unit' => $pricingUnit,
            'day_price' => $dayPrice,
            'night_price' => $nightPrice,
        ]);

        return $site;
    }
}
