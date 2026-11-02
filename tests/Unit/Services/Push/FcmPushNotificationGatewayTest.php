<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Push;

use App\DTOs\PushNotification;
use App\Models\DeviceToken;
use App\Services\Push\FcmPushNotificationGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Firebase Cloud Messaging (HTTP v1) con una cuenta de servicio de prueba:
 * llave RSA generada acá, Google y Firebase simulados con `Http::fake`.
 */
class FcmPushNotificationGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URI = 'https://oauth.test/token';

    private const SEND_URL = 'https://fcm.test/v1/projects/motoya-test/messages:send';

    public function test_firma_con_la_cuenta_de_servicio_y_manda_la_notificacion(): void
    {
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'google-token', 'expires_in' => 3599]),
            self::SEND_URL => Http::response(['name' => 'projects/motoya-test/messages/1']),
        ]);
        $token = DeviceToken::factory()->create(['token' => 'celular-1']);

        $this->gateway()->send($token, new PushNotification('🛺 Nuevo viaje', 'Un pasajero está esperando.'));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::TOKEN_URI) {
                return false;
            }
            [$header, $claims] = array_map(
                fn (string $part) => json_decode((string) base64_decode(strtr($part, '-_', '+/')), true),
                array_slice(explode('.', (string) $request['assertion']), 0, 2),
            );

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $header['alg'] === 'RS256'
                && $claims['iss'] === 'motoya@motoya-test.iam.gserviceaccount.com'
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
        });
        Http::assertSent(fn (Request $request): bool => $request->url() === self::SEND_URL
            && $request->hasHeader('Authorization', 'Bearer google-token')
            && $request['message']['token'] === 'celular-1'
            && $request['message']['notification']['title'] === '🛺 Nuevo viaje'
            && $request['message']['android']['notification']['channel_id'] === FcmPushNotificationGateway::ANDROID_CHANNEL);
    }

    public function test_reutiliza_el_access_token(): void
    {
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'google-token']),
            self::SEND_URL => Http::response([]),
        ]);
        $token = DeviceToken::factory()->create();
        $gateway = $this->gateway();

        $gateway->send($token, new PushNotification('a', 'b'));
        $gateway->send($token, new PushNotification('c', 'd'));

        Http::assertSentCount(3);
    }

    public function test_borra_el_token_de_un_celular_que_ya_no_tiene_la_app(): void
    {
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'google-token']),
            self::SEND_URL => Http::response(['error' => [
                'status' => 'NOT_FOUND',
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
            ]], 404),
        ]);
        $token = DeviceToken::factory()->create();

        $this->gateway()->send($token, new PushNotification('a', 'b'));

        $this->assertModelMissing($token);
    }

    public function test_un_fallo_de_firebase_no_lanza_ni_borra_el_token(): void
    {
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'google-token']),
            self::SEND_URL => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);
        $token = DeviceToken::factory()->create();

        $this->gateway()->send($token, new PushNotification('a', 'b'));

        $this->assertModelExists($token);
    }

    public function test_si_google_no_da_access_token_no_lanza(): void
    {
        Http::fake([self::TOKEN_URI => Http::response(['error' => 'invalid_grant'], 400)]);
        $token = DeviceToken::factory()->create();

        $this->gateway()->send($token, new PushNotification('a', 'b'));

        $this->assertModelExists($token);
        Http::assertSentCount(1);
    }

    public function test_rechaza_una_cuenta_de_servicio_incompleta(): void
    {
        $this->expectException(RuntimeException::class);

        FcmPushNotificationGateway::fromCredentials(['project_id' => 'x'], self::SEND_URL, 5);
    }

    private function gateway(): FcmPushNotificationGateway
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);

        return FcmPushNotificationGateway::fromCredentials([
            'project_id' => 'motoya-test',
            'client_email' => 'motoya@motoya-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => self::TOKEN_URI,
        ], 'https://fcm.test/v1/projects/%s/messages:send', 5);
    }
}
