<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\DTOs\PushNotification;
use App\Models\DeviceToken;
use App\Services\Realtime\PushNotificationGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Firebase Cloud Messaging, API HTTP v1.
 *
 * Se autentica con la cuenta de servicio del proyecto (un JWT firmado con su
 * llave privada se canjea por un access token de Google, que se guarda en
 * cache casi una hora). Nunca lanza: una notificación que no sale no puede
 * romper el viaje o el mandado que la disparó, solo queda en el log. Si
 * Firebase dice que el token ya no existe (la app se desinstaló), se borra.
 */
final class FcmPushNotificationGateway implements PushNotificationGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Canal de Android que crea la app (MainActivity) con prioridad alta. */
    public const ANDROID_CHANNEL = 'motoya_servicios';

    public function __construct(
        private readonly string $projectId,
        private readonly string $clientEmail,
        private readonly string $privateKey,
        private readonly string $tokenUri,
        private readonly string $sendEndpoint,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials  el .json de la cuenta de servicio
     */
    public static function fromCredentials(array $credentials, string $sendEndpoint, int $timeoutSeconds): self
    {
        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (! is_string($credentials[$key] ?? null) || $credentials[$key] === '') {
                throw new RuntimeException("La cuenta de servicio de Firebase no trae '{$key}'.");
            }
        }

        return new self(
            projectId: $credentials['project_id'],
            clientEmail: $credentials['client_email'],
            privateKey: $credentials['private_key'],
            tokenUri: is_string($credentials['token_uri'] ?? null) ? $credentials['token_uri'] : 'https://oauth2.googleapis.com/token',
            sendEndpoint: $sendEndpoint,
            timeoutSeconds: $timeoutSeconds,
        );
    }

    public function send(DeviceToken $token, PushNotification $notification): void
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->timeout($this->timeoutSeconds)
                ->post(sprintf($this->sendEndpoint, $this->projectId), $this->message($token->token, $notification));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar la notificación push', [
                'device_token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->successful()) {
            return;
        }

        if ($this->isUnregistered($response)) {
            $token->delete();

            return;
        }

        Log::warning('Firebase rechazó la notificación push', [
            'device_token_id' => $token->id,
            'status' => $response->status(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $deviceToken, PushNotification $notification): array
    {
        return [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => self::ANDROID_CHANNEL,
                    ],
                ],
            ],
        ];
    }

    private function isUnregistered(Response $response): bool
    {
        $details = $response->json('error.details');
        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }

    private function accessToken(): string
    {
        // Google lo da por 1 hora; se guarda 50 minutos.
        return Cache::remember(
            'fcm.access_token.'.md5($this->clientEmail),
            3000,
            fn (): string => $this->requestAccessToken(),
        );
    }

    private function requestAccessToken(): string
    {
        $now = time();
        $response = Http::asForm()
            ->timeout($this->timeoutSeconds)
            ->post($this->tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedJwt([
                    'iss' => $this->clientEmail,
                    'scope' => self::SCOPE,
                    'aud' => $this->tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]),
            ])
            ->throw();

        $accessToken = $response->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google no devolvió un access token para Firebase.');
        }

        return $accessToken;
    }

    /**
     * @param  array<string, int|string>  $claims
     */
    private function signedJwt(array $claims): string
    {
        $input = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.self::base64Url((string) json_encode($claims));

        $signature = '';
        if (! openssl_sign($input, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar con la llave de la cuenta de servicio de Firebase.');
        }

        return $input.'.'.self::base64Url($signature);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
