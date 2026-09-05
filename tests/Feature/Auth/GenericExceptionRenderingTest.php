<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Traducción al español de las excepciones que genera el propio framework
 * (historia técnica #73) — a diferencia de JwtExceptionRenderingTest, que
 * cubre las de jwt-auth, y del resto de los render() en bootstrap/app.php,
 * que traducen excepciones de dominio del proyecto.
 */
class GenericExceptionRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El bug real que motivó esta suite: `ApplicationBuilder::withMiddleware()`
     * deja por defecto `redirectGuestsTo(fn () => route('login'))`, pensado
     * para una app web con sesión. Esta API no tiene ninguna ruta `login`, así
     * que sin el override en bootstrap/app.php, cualquier request sin token
     * que además no mande `Accept: application/json` explícito hacía que
     * `Authenticate::redirectTo()` intentara construir esa URL y explotara con
     * un 500 en vez de responder 401.
     *
     * Por eso el test usa `$this->get()` (sin el helper `getJson()`, que sí
     * manda ese encabezado) — es la única forma de reproducir el caso real
     * que rompía.
     */
    public function test_una_cuenta_sin_token_recibe_401_en_espanol_sin_encabezado_accept(): void
    {
        $this->get('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No has iniciado sesión.']);
    }

    public function test_authorization_exception_es_403_en_espanol(): void
    {
        $uri = $this->rutaQueLanza(fn () => throw new AuthorizationException);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson($uri)
            ->assertForbidden()
            ->assertExactJson(['message' => 'No tienes permiso para hacer esto.']);
    }

    public function test_throttle_requests_exception_es_429_en_espanol(): void
    {
        $uri = $this->rutaQueLanza(fn () => throw new ThrottleRequestsException('Too Many Attempts.'));

        $this->getJson($uri)
            ->assertStatus(429)
            ->assertExactJson(['message' => 'Se superó el límite de solicitudes. Intenta de nuevo en unos minutos.']);
    }

    public function test_model_not_found_es_404_generico_en_espanol_sin_exponer_la_clase_php(): void
    {
        $uri = $this->rutaQueLanza(
            fn () => throw (new ModelNotFoundException)->setModel(User::class, [999]),
        );

        $this->getJson($uri)
            ->assertNotFound()
            ->assertExactJson(['message' => 'No se encontró el recurso solicitado.']);
    }

    /**
     * Un `NotFoundHttpException` lanzado a mano por el propio proyecto, con un
     * mensaje ya en español, no debe pisarse por el render genérico de arriba
     * — solo el que Laravel arma automáticamente al convertir
     * `ModelNotFoundException` lleva ese `getPrevious()` (ver bootstrap/app.php).
     * Se prueba contra un endpoint real (`GET /me/vehicle` sin vehículo
     * registrado, historia #12) en vez de una ruta de prueba, para que el
     * caso protegido sea el real y no una reconstrucción sintética.
     */
    public function test_un_404_manual_del_proyecto_conserva_su_propio_mensaje(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/me/vehicle')
            ->assertNotFound()
            ->assertExactJson(['message' => 'No tienes un vehículo registrado.']);
    }

    /**
     * Registra una ruta bajo `api/*` que ejecuta el callback dado y devuelve
     * su URI, mismo criterio que `JwtExceptionRenderingTest::rutaQueLanza()`
     * (acá el nombre solo tiene que ser único dentro de la corrida, no
     * reproducible, así que alcanza con `uniqid()`).
     */
    private function rutaQueLanza(callable $callback): string
    {
        $uri = '/api/v1/_probe-excepcion-'.uniqid();

        Route::get($uri, $callback);

        return $uri;
    }
}
