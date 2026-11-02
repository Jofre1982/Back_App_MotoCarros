<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Actions\Errands\AcceptErrandAction;
use App\Actions\Errands\CancelErrandAction;
use App\Actions\Errands\CompleteErrandAction;
use App\Actions\Errands\RespondToErrandPriceAction;
use App\Actions\Rides\AcceptRideAction;
use App\Actions\Rides\CancelRideAction;
use App\Actions\Rides\CompleteRideAction;
use App\Actions\Rides\RespondToRidePriceAction;
use App\Actions\Rides\StartRideAction;
use App\DTOs\PushNotification;
use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
use App\Enums\VehicleType;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Realtime\PushNotificationGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Cuándo llega una notificación push y a quién (pedido del dueño: que
 * avise aunque la app esté cerrada). El gateway es un doble que anota cada
 * envío como "token: título"; el envío diferido se ejecuta en el acto.
 */
class ServicePushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /** @var object{enviados: list<string>} */
    private object $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->gateway = new class implements PushNotificationGateway
        {
            /** @var list<string> */
            public array $enviados = [];

            public function send(DeviceToken $token, PushNotification $notification): void
            {
                $this->enviados[] = "{$token->token}: {$notification->title}";
            }
        };
        $this->app->instance(PushNotificationGateway::class, $this->gateway);
    }

    public function test_un_viaje_nuevo_avisa_a_los_conductores_de_motocarro_que_reciben_viajes(): void
    {
        $this->conductor('disponible', viajes: true);
        $this->conductor('apagado', viajes: false);
        $this->conductor('motocarga', viajes: true, tipo: VehicleType::Motocarga);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', [
                'mode' => 'pickup_only',
                'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
                'destination_text' => 'Al hospital',
                'passenger_count' => 1,
            ])
            ->assertCreated();

        $this->assertSame(['disponible: 🛺 Nuevo viaje'], $this->gateway->enviados);
    }

    public function test_una_motocarga_nueva_avisa_solo_a_los_de_motocarga_que_reciben_mandados(): void
    {
        $this->conductor('motocarro', mandados: true);
        $this->conductor('motocarga', mandados: true, tipo: VehicleType::Motocarga);
        $this->conductor('motocarga-apagado', mandados: false, tipo: VehicleType::Motocarga);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/errands', [
                'description' => 'Llevar una nevera al barrio Berlín.',
                'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
                'vehicle_type' => 'motocarga',
                'destination_text' => 'Barrio Berlín',
            ])
            ->assertCreated();

        $this->assertSame(['motocarga: 📦 Nueva motocarga'], $this->gateway->enviados);
    }

    public function test_el_pasajero_sabe_que_le_ofrecieron_precio_y_cuando_empieza_y_termina(): void
    {
        $pasajero = $this->conCelular(User::factory()->create(), 'pasajero');
        $conductor = User::factory()->driver()->create(['name' => 'Luis']);
        $viaje = Ride::factory()->pickupOnly()->create(['passenger_id' => $pasajero->id]);

        $viaje = app(AcceptRideAction::class)->handle($viaje, $conductor, 7000);
        app(RespondToRidePriceAction::class)->handle($viaje, true);
        $viaje = app(StartRideAction::class)->handle($viaje->refresh());
        app(CompleteRideAction::class)->handle($viaje);

        $this->assertSame([
            'pasajero: 💬 Te ofrecen un precio',
            'pasajero: 🚀 ¡Tu viaje comenzó!',
            'pasajero: 🎉 ¡Llegaste!',
        ], $this->gateway->enviados);
    }

    public function test_el_conductor_sabe_si_aceptaron_o_rechazaron_su_precio(): void
    {
        $conductor = $this->conCelular(User::factory()->driver()->create(), 'conductor');
        $acepta = Ride::factory()->pickupOnly()->create([
            'status' => RideStatus::Accepted, 'driver_id' => $conductor->id, 'agreed_price' => 5000,
        ]);
        $rechaza = Errand::factory()->create([
            'status' => ErrandStatus::Accepted, 'driver_id' => $conductor->id, 'agreed_price' => 9000,
        ]);

        app(RespondToRidePriceAction::class)->handle($acepta, true);
        app(RespondToErrandPriceAction::class)->handle($rechaza, false);

        $this->assertSame([
            'conductor: ✅ ¡Aceptaron tu precio!',
            'conductor: 😕 No aceptaron tu precio',
        ], $this->gateway->enviados);
    }

    public function test_mandado_aceptado_completado_y_cancelado(): void
    {
        $pasajero = $this->conCelular(User::factory()->create(), 'pasajero');
        $conductor = $this->conCelular(User::factory()->driver()->create(['name' => 'Ana']), 'conductor');
        $mandado = Errand::factory()->create(['passenger_id' => $pasajero->id]);
        $otro = Errand::factory()->create([
            'passenger_id' => $pasajero->id, 'status' => ErrandStatus::Accepted, 'driver_id' => $conductor->id,
        ]);

        $mandado = app(AcceptErrandAction::class)->handle($mandado, $conductor, 12000);
        app(CompleteErrandAction::class)->handle($mandado);
        app(CancelErrandAction::class)->handle($otro, $pasajero);

        $this->assertSame([
            'pasajero: 💬 Te ofrecen un precio',
            'pasajero: 🎉 ¡Tu pedido está listo!',
            'conductor: ❌ El pasajero canceló',
        ], $this->gateway->enviados);
    }

    public function test_el_conductor_se_entera_si_el_pasajero_cancela_el_viaje(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conCelular(User::factory()->driver()->create(), 'conductor');
        $viaje = Ride::factory()->create([
            'passenger_id' => $pasajero->id, 'status' => RideStatus::Accepted, 'driver_id' => $conductor->id,
        ]);

        app(CancelRideAction::class)->handle($viaje, $pasajero);

        $this->assertSame(['conductor: ❌ El pasajero canceló'], $this->gateway->enviados);
    }

    private function conductor(string $celular, bool $viajes = false, bool $mandados = false, VehicleType $tipo = VehicleType::Motocarro): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create([
            'user_id' => $conductor->id,
            'is_available' => $viajes,
            'is_available_for_errands' => $mandados,
        ]);
        Vehicle::factory()->create(['user_id' => $conductor->id, 'type' => $tipo]);

        return $this->conCelular($conductor, $celular);
    }

    private function conCelular(User $user, string $token): User
    {
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => $token]);

        return $user;
    }
}
