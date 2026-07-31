<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\UserNotDefinedException;

/**
 * El exception handler traduce a 401 los fallos atribuibles al token que mandó
 * el cliente — y solo esos (ver bootstrap/app.php).
 *
 * `JWTException` es la clase base del paquete y jwt-auth también la lanza ante
 * errores de configuración del servidor. Ese es el caso que este test protege:
 * un despliegue sin `JWT_SECRET` generado tiene que fallar con 500, visible en
 * el monitoreo, y no responderle "tu sesión venció" a todos los usuarios de
 * forma indefinida.
 *
 * Las rutas se registran acá y no en routes/api.php porque cubren el handler,
 * no un endpoint de negocio (mismo criterio que JwtGuardTest).
 */
class JwtExceptionRenderingTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<JWTException>}>
     */
    public static function excepcionesDelCliente(): iterable
    {
        yield 'token expirado' => [TokenExpiredException::class];
        yield 'token ilegible o mal firmado' => [TokenInvalidException::class];
        yield 'token en la blacklist' => [TokenBlacklistedException::class];
        yield 'token sin sujeto resoluble' => [UserNotDefinedException::class];
    }

    #[DataProvider('excepcionesDelCliente')]
    public function test_los_fallos_del_token_del_cliente_son_401(string $excepcion): void
    {
        $uri = $this->rutaQueLanza($excepcion);

        $this->getJson($uri)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Token inválido o expirado. Inicia sesión de nuevo.']);
    }

    public function test_un_error_de_configuracion_del_servidor_no_se_disfraza_de_401(): void
    {
        // Mensaje literal de jwt-auth cuando falta el secreto de firma
        // (vendor/tymon/jwt-auth/src/Providers/JWT/Lcobucci.php). Con el
        // handler capturando la clase base, esto era un 401 indistinguible de
        // un token vencido.
        $uri = $this->rutaQueLanza(JWTException::class, 'Secret is not set.');

        $this->getJson($uri)->assertStatus(500);
    }

    public function test_la_blacklist_deshabilitada_tampoco_se_disfraza_de_401(): void
    {
        // Otro `JWTException` de configuración: Manager::invalidate() lo lanza
        // si `jwt.blacklist_enabled` está en false.
        $uri = $this->rutaQueLanza(
            JWTException::class,
            'You must have the blacklist enabled to invalidate a token.',
        );

        $this->getJson($uri)->assertStatus(500);
    }

    /**
     * Registra una ruta bajo `api/*` que lanza la excepción pedida y devuelve
     * su URI. Cada caso usa una URI propia para no pisar la anterior.
     *
     * @param  class-string<JWTException>  $excepcion
     */
    private function rutaQueLanza(string $excepcion, string $mensaje = 'falla de prueba'): string
    {
        $uri = '/api/v1/_probe-excepcion-'.substr(md5($excepcion.$mensaje), 0, 8);

        Route::get($uri, function () use ($excepcion, $mensaje): never {
            throw new $excepcion($mensaje);
        });

        return $uri;
    }
}
