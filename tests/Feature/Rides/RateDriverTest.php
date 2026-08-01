<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RatedRole;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\RideRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/{id}/rate-driver — ver openapi.yaml.
 *
 * Historia #27: el pasajero dueño de un viaje completado califica al
 * conductor asignado con una puntuación de 1 a 5 y un comentario opcional.
 */
class RateDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_dueno_califica_al_conductor_de_su_viaje_completado(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), [
                'score' => 5,
                'comment' => 'Muy puntual y buen trato.',
            ])
            ->assertCreated();

        $respuesta->assertJsonPath('data.ride_id', $viaje->id);
        $respuesta->assertJsonPath('data.score', 5);
        $respuesta->assertJsonPath('data.comment', 'Muy puntual y buen trato.');
        $this->assertNotNull($respuesta->json('data.rated_at'));

        $this->assertDatabaseHas('ride_ratings', [
            'ride_id' => $viaje->id,
            'rated_role' => RatedRole::Driver->value,
            'score' => 5,
            'comment' => 'Muy puntual y buen trato.',
        ]);
    }

    public function test_el_comentario_es_opcional(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), ['score' => 4])
            ->assertCreated();

        $respuesta->assertJsonPath('data.comment', null);
        $this->assertDatabaseHas('ride_ratings', [
            'ride_id' => $viaje->id,
            'score' => 4,
            'comment' => null,
        ]);
    }

    public function test_rechaza_una_puntuacion_fuera_de_rango(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), ['score' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('score');

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), ['score' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('score');

        $this->assertDatabaseMissing('ride_ratings', ['ride_id' => $viaje->id]);
    }

    public function test_rechaza_una_segunda_calificacion_del_mismo_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);
        RideRating::factory()->for($viaje)->create(['rated_role' => RatedRole::Driver]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), ['score' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertSame(1, RideRating::query()->where('ride_id', $viaje->id)->count());
    }

    #[DataProvider('estadosSinCalificacion')]
    public function test_rechaza_un_viaje_que_no_esta_completado(RideStatus $estado): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => $estado,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), ['score' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertDatabaseMissing('ride_ratings', ['ride_id' => $viaje->id]);
    }

    public function test_rechaza_a_un_pasajero_que_no_es_dueno_del_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $dueno = User::factory()->create();
        $otroPasajero = User::factory()->create();
        $viaje = Ride::factory()->for($dueno, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($otroPasajero))
            ->postJson($this->uri($viaje), ['score' => 5])
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('ride_ratings', ['ride_id' => $viaje->id]);
    }

    public function test_rechaza_al_conductor_asignado(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje), ['score' => 5])
            ->assertForbidden();

        $this->assertDatabaseMissing('ride_ratings', ['ride_id' => $viaje->id]);
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides/999999/rate-driver', ['score' => 5])
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'completed_at' => now(),
        ]);

        $this->postJson($this->uri($viaje), ['score' => 5])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('ride_ratings', ['ride_id' => $viaje->id]);
    }

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/rate-driver";
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosSinCalificacion(): array
    {
        return [
            RideStatus::Requested->value => [RideStatus::Requested],
            RideStatus::Accepted->value => [RideStatus::Accepted],
            RideStatus::InProgress->value => [RideStatus::InProgress],
            RideStatus::Cancelled->value => [RideStatus::Cancelled],
        ];
    }
}
