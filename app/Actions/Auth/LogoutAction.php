<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\JWT;

/**
 * Cierra la sesión invalidando el access token que la sostiene.
 *
 * Invalida **solo el token recibido**, no la cuenta: cerrar sesión en el
 * teléfono no puede echar a la tableta. El cierre remoto en todos los
 * dispositivos es otra historia y necesita llevar registro de los tokens
 * activos por usuario.
 */
final class LogoutAction
{
    /**
     * Se inyecta `JWT` y no su subclase `JWTAuth` a propósito: son dos
     * singletons distintos (`tymon.jwt` y `tymon.jwt.auth`), y el guard `api`
     * se construye con el primero. El `unsetToken()` del final tiene que caer
     * sobre esa instancia y no sobre la otra para que sirva de algo.
     */
    public function __construct(
        private readonly JWT $jwt,
        private readonly Blacklist $blacklist,
    ) {}

    /**
     * @throws JWTException si la blacklist está deshabilitada: sin ella no
     *                      hay forma de cerrar la sesión. Escala a 500 a
     *                      propósito (ver más abajo).
     * @throws TokenExpiredException si el token ya venció.
     * @throws TokenInvalidException si es ilegible, si la firma no cierra, o si
     *                               la sesión ya se había cerrado con él.
     */
    public function handle(string $token): void
    {
        // Sin blacklist no hay cierre de sesión posible: la entrada se
        // escribiría igual, pero nadie la consultaría al validar, así que el
        // token seguiría sirviendo hasta vencer solo mientras el endpoint
        // responde 204. Es la peor respuesta posible — el usuario cree que
        // cerró la sesión y no la cerró.
        //
        // Esta es la verificación que trae de regalo `Manager::invalidate()`
        // (el único punto donde jwt-auth la hace) y que se pierde al escribir
        // en el `Blacklist` directo, como hace la línea de más abajo. Se
        // comprueba antes que el token porque es un error de configuración del
        // servidor, no del cliente: se lanza `JWTException`, que a propósito
        // **no** está mapeada a 401 en bootstrap/app.php y escala a 500 para
        // que el monitoreo la vea (ver .claude/STANDARDS.md).
        if (! config('jwt.blacklist_enabled')) {
            throw new JWTException('You must have the blacklist enabled to invalidate a token.');
        }

        // `getPayload()` es lo que valida el token —firma, vencimiento y
        // blacklist— antes de tocar nada. El endpoint va detrás de `auth:api`,
        // que ya lo rechazaría, pero la Action tiene que sostener sus `@throws`
        // por su cuenta para quien la invoque desde un comando o un job.
        $payload = $this->jwt->setToken($token)->getPayload();

        // El cierre de sesión ignora la gracia de blacklist a propósito. Esos
        // 30 s existen para que las requests que ya iban en vuelo sobrevivan a
        // una *renovación* (ver .claude/STANDARDS.md); acá dejarían vivo medio
        // minuto más justo el token que el usuario está pidiendo matar porque
        // perdió el dispositivo.
        //
        // Se hace bajando la gracia a 0 en vez de con `invalidate(forever)`
        // —que también sería inmediato— porque `add()` guarda la entrada solo
        // hasta que el token deje de ser renovable (14 días), y el cache la
        // reclama sola; `addForever()` la escribiría de forma permanente y la
        // blacklist crecería sin techo, un cierre de sesión a la vez.
        //
        // El Blacklist es el singleton que comparte todo jwt-auth, así que la
        // gracia se restaura pase lo que pase: dejarla en 0 le quitaría al
        // refresh el margen que sí necesita.
        $graciaConfigurada = $this->blacklist->getGracePeriod();

        try {
            $this->blacklist->setGracePeriod(0)->add($payload);
        } finally {
            $this->blacklist->setGracePeriod($graciaConfigurada);
        }

        // `JWT` es un singleton y su `getToken()` devuelve el token que tenga
        // cacheado *antes* de intentar leerlo de la cabecera, así que dejarlo
        // puesto es dejar uno ya invalidado como el token "actual" de la
        // aplicación. Con una request por proceso da igual —el contenedor
        // nace limpio—, pero no cuando sobrevive a la request: en un worker de
        // colas, en la suite de tests o con Octane, la siguiente autenticación
        // reusaría este token muerto en vez del que venga en la cabecera, y
        // rechazaría a un usuario que mandó credenciales perfectamente
        // válidas. Es lo mismo que hace `JWTGuard::logout()` tras invalidar.
        $this->jwt->unsetToken();
    }
}
