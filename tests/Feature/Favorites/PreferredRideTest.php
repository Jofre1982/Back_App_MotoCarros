<?php

declare(strict_types=1);

namespace Tests\Feature\Favorites;

use App\DTOs\PushNotification;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Realtime\PushNotificationGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Pedirle un viaje primero a un conductor favorito (`preferred_driver_id` en
 * POST /rides): 2 minutos solo para él, después para todos.
 */
class PreferredRideTest extends TestCase
{
    use RefreshDatabase;

    /** @var object{enviados: list<string>} */
    private object $gateway;

    private User $pasajero;

    private User $favorito;

    private User $otro;

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

        $this->pasajero = User::factory()->create();
        $this->favorito = $this->conductor('favorito');
        $this->otro = $this->conductor('otro');
        $this->pasajero->favoriteDrivers()->attach($this->favorito->id);
    }

    public function test_solo_el_favorito_ve_y_recibe_el_viaje_durante_la_espera(): void
    {
        $viaje = $this->pedirAlFavorito()
            ->assertCreated()
            ->assertJsonPath('data.preferred_driver.id', $this->favorito->id)
            ->json('data');

        $this->assertNotNull($viaje['preferred_until']);
        $this->assertSame(['favorito: ⭐ ¡Un pasajero te eligió!'], $this->gateway->enviados);
        $this->assertSame([$viaje['id']], $this->disponiblesPara($this->favorito));
        $this->assertSame([], $this->disponiblesPara($this->otro));

        $this->comoConductor($this->otro)
            ->postJson("/api/v1/rides/{$viaje['id']}/accept", ['agreed_price' => 5000])
            ->assertConflict();
    }

    public function test_si_el_favorito_no_responde_queda_para_todos_y_se_avisa_una_vez(): void
    {
        $id = $this->pedirAlFavorito()->json('data.id');

        $this->travel(3)->minutes();

        // El pasajero que espera consulta su viaje: eso dispara el aviso.
        $this->comoConductor($this->pasajero)->getJson("/api/v1/rides/{$id}")->assertOk();
        $this->comoConductor($this->pasajero)->getJson("/api/v1/rides/{$id}")->assertOk();

        $this->assertSame([$id], $this->disponiblesPara($this->otro));
        $this->assertSame([
            'favorito: ⭐ ¡Un pasajero te eligió!',
            'otro: 🛺 Nuevo viaje',
        ], $this->gateway->enviados);

        $this->comoConductor($this->otro)
            ->postJson("/api/v1/rides/{$id}/accept", ['agreed_price' => 5000])
            ->assertOk();
    }

    public function test_si_rechazan_el_precio_del_favorito_queda_para_todos_ya(): void
    {
        $id = $this->pedirAlFavorito()->json('data.id');
        $this->comoConductor($this->favorito)
            ->postJson("/api/v1/rides/{$id}/accept", ['agreed_price' => 9000])
            ->assertOk();

        $this->comoConductor($this->pasajero)
            ->postJson("/api/v1/rides/{$id}/price-response", ['decision' => 'reject'])
            ->assertOk();

        $this->assertSame([$id], $this->disponiblesPara($this->otro));
    }

    public function test_rechaza_un_favorito_que_no_esta_en_la_lista_o_no_recibe_viajes(): void
    {
        $this->pedir($this->otro->id)->assertUnprocessable()->assertJsonValidationErrors('preferred_driver_id');

        $this->favorito->driverProfile()->update(['is_available' => false]);
        $this->pedir($this->favorito->id)->assertUnprocessable()->assertJsonValidationErrors('preferred_driver_id');

        $this->assertSame(0, Ride::query()->count());
    }

    public function test_sin_favorito_se_pide_a_todos_como_siempre(): void
    {
        $this->pedir(null)->assertCreated()->assertJsonPath('data.preferred_driver', null);

        $this->assertEqualsCanonicalizing(['favorito: 🛺 Nuevo viaje', 'otro: 🛺 Nuevo viaje'], $this->gateway->enviados);
    }

    private function pedirAlFavorito(): TestResponse
    {
        return $this->pedir($this->favorito->id);
    }

    private function pedir(?int $favorito): TestResponse
    {
        return $this->comoConductor($this->pasajero)->postJson('/api/v1/rides', array_filter([
            'mode' => 'pickup_only',
            'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
            'passenger_count' => 1,
            'preferred_driver_id' => $favorito,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return list<int>
     */
    private function disponiblesPara(User $conductor): array
    {
        return array_column($this->comoConductor($conductor)->getJson('/api/v1/rides/available')->assertOk()->json('data'), 'id');
    }

    private function comoConductor(User $user): static
    {
        $this->flushHeaders();
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    private function conductor(string $celular): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'is_available' => true]);
        Vehicle::factory()->create(['user_id' => $conductor->id]);
        DeviceToken::factory()->create(['user_id' => $conductor->id, 'token' => $celular]);

        return $conductor;
    }
}
