<?php

namespace App\Providers;

use App\DTOs\FareSchedule;
use App\Services\Maps\GoogleRoutesEstimator;
use App\Services\Maps\RouteEstimator;
use App\Services\Payments\NullPaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Realtime\EloquentNearbyDriverFinder;
use App\Services\Realtime\EloquentRideParticipants;
use App\Services\Realtime\NearbyDriverFinder;
use App\Services\Realtime\NullPushNotificationGateway;
use App\Services\Realtime\PushNotificationGateway;
use App\Services\Realtime\RideParticipants;
use App\Services\Sms\NullSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRouteEstimator();
        $this->registerPaymentGateway();
        $this->registerFareSchedule();
        $this->registerRideParticipants();
        $this->registerNearbyDriverFinder();
        $this->registerPushNotificationGateway();
        $this->registerSmsGateway();
    }

    /**
     * Proveedor de SMS que envía el código de verificación de celular
     * (historia #69). Todavía no hay cuenta de Twilio u otro proveedor
     * configurada —ver "Fuera de alcance" en el issue—, así que apunta a
     * `NullSmsGateway`, que solo deja un registro en el log. Cambiar a un
     * proveedor real es reemplazar este binding, mismo criterio que
     * `registerPaymentGateway()`.
     */
    private function registerSmsGateway(): void
    {
        $this->app->singleton(SmsGateway::class, NullSmsGateway::class);
    }

    /**
     * Proveedor de notificaciones push que avisa a los conductores cercanos
     * de un viaje nuevo (historia #67). Todavía no hay cuenta de Firebase/APNs
     * configurada —ver "Fuera de alcance" en el issue—, así que apunta a
     * `NullPushNotificationGateway`, que solo deja un registro en el log.
     * Cambiar a un proveedor real es reemplazar este binding, mismo criterio
     * que `registerPaymentGateway()`.
     */
    private function registerPushNotificationGateway(): void
    {
        $this->app->singleton(PushNotificationGateway::class, NullPushNotificationGateway::class);
    }

    /**
     * Proveedor de pago que procesa el cobro de un viaje completado (historia
     * #25). Todavía no hay un proveedor real decidido —ver "Fuera de
     * alcance" en el issue—, así que apunta a `NullPaymentGateway`, que da
     * todo cobro por exitoso. Cambiar de proveedor real es reemplazar este
     * binding, mismo criterio que `registerRouteEstimator()`.
     */
    private function registerPaymentGateway(): void
    {
        $this->app->singleton(PaymentGateway::class, NullPaymentGateway::class);
    }

    /**
     * Fuente de los conductores disponibles cerca de un punto (historia
     * #17), que es lo que necesitan `CreateRideAction` y `AcceptRideAction`
     * para avisar y desavisar a los conductores cercanos.
     *
     * El radio vigente sale de `config/rides.php`, mismo criterio diferido
     * que `FareSchedule`: se lee al resolver la clase, no en cada arranque.
     */
    private function registerNearbyDriverFinder(): void
    {
        $this->app->singleton(NearbyDriverFinder::class, static fn (): NearbyDriverFinder => new EloquentNearbyDriverFinder(
            radiusMeters: Config::integer('rides.nearby_radius_meters'),
        ));
    }

    /**
     * Fuente de los participantes de un viaje, que es lo que autoriza el canal
     * privado `ride.{id}` (ver App\Broadcasting\RideChannel).
     *
     * Apunta a una implementación con Eloquent desde la historia #20: el
     * tracking en tiempo real (compartir ubicación acá, consumirla en #21)
     * es lo que necesita que el canal autorice de verdad al pasajero y al
     * conductor asignado, y ni el canal ni routes/channels.php se tocan para
     * este cambio.
     */
    private function registerRideParticipants(): void
    {
        $this->app->bind(RideParticipants::class, EloquentRideParticipants::class);
    }

    /**
     * Arma los parámetros de tarifa vigentes desde config/fares.php (ver la
     * fórmula en .claude/STANDARDS.md).
     *
     * Diferido igual que el estimador: el constructor de FareSchedule rechaza
     * una configuración imposible, y esa validación tiene que ocurrir cuando
     * alguien va a calcular una tarifa, no en cada arranque de la aplicación.
     */
    private function registerFareSchedule(): void
    {
        $this->app->singleton(FareSchedule::class, static fn (): FareSchedule => new FareSchedule(
            currency: Config::string('fares.currency'),
            base: Config::integer('fares.base'),
            perKilometer: Config::integer('fares.per_kilometer'),
            perMinute: Config::integer('fares.per_minute'),
            perWaitingMinute: Config::integer('fares.per_waiting_minute'),
            minimum: Config::integer('fares.minimum'),
            roundingStep: Config::integer('fares.rounding_step'),
        ));
    }

    /**
     * Resuelve el proveedor de mapas configurado (ver config/maps.php y la
     * decisión en .claude/STANDARDS.md).
     *
     * La resolución es diferida (`singleton` con closure): la API key solo se
     * exige cuando alguien pide de verdad una estimación, así que el resto de
     * la aplicación —y la suite de tests que no toca mapas— arranca sin
     * necesidad de credenciales del proveedor.
     */
    private function registerRouteEstimator(): void
    {
        $this->app->singleton(RouteEstimator::class, static function (): RouteEstimator {
            $provider = Config::string('maps.provider');

            return match ($provider) {
                'google' => new GoogleRoutesEstimator(
                    apiKey: Config::string('maps.google.key'),
                    endpoint: Config::string('maps.google.routes_endpoint'),
                    timeoutSeconds: Config::integer('maps.google.timeout'),
                ),
                default => throw new InvalidArgumentException(
                    "Proveedor de mapas no soportado: '{$provider}' (revisa MAPS_PROVIDER)."
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePasswordPolicy();
    }

    /**
     * Política mínima de contraseñas: 8 caracteres, con al menos una letra y al
     * menos un número (ver .claude/STANDARDS.md).
     *
     * Se declara como `Password::defaults()` y no suelta en cada Form Request
     * para que registro, login y un eventual cambio de contraseña no puedan
     * divergir: si mañana sube el mínimo, sube en un solo lugar.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }

    /**
     * Límites de tasa de la API.
     *
     * `api` es el techo general del grupo (ver bootstrap/app.php): por usuario
     * cuando el request viene autenticado, y por IP cuando no.
     *
     * `auth` es más estricto y cubre los endpoints que, por diseño, se consumen
     * sin `auth:api` — hoy solo el refresh, mañana login y registro. Son los
     * que se pueden golpear por fuerza bruta sin credenciales, y en el caso del
     * refresh cada acierto escribe además una entrada de blacklist en el cache.
     *
     * `phone-verification` es más estricto todavía y por usuario (no por IP:
     * el endpoint exige `auth:api`): cada acierto le cuesta un SMS real a
     * MotoYa apenas haya un proveedor conectado (historia #69), así que el
     * límite general de 60/min dejaría pedir 60 códigos en un minuto.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $porUsuario = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by((string) ($porUsuario ?? $request->ip()));
        });

        RateLimiter::for(
            'auth',
            fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()),
        );

        RateLimiter::for(
            'phone-verification',
            fn (Request $request) => Limit::perMinutes(10, 3)->by((string) $request->user()?->getAuthIdentifier()),
        );
    }
}
