<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;

/**
 * Genera y envía el código de verificación de celular de una cuenta (historia
 * #69).
 *
 * Un código vigente por cuenta: `phone_verification_codes.user_id` es único
 * (ver la migración), así que pedir uno nuevo reemplaza el anterior en vez de
 * acumularlo —`updateOrCreate()` a través de la relación resuelve esto solo.
 *
 * Se guarda el hash del código, nunca el valor en claro: nadie con acceso a
 * la base debería poder leerlo, mismo criterio que la contraseña.
 */
final readonly class RequestPhoneVerificationAction
{
    public function __construct(private SmsGateway $sms) {}

    public function handle(User $user): void
    {
        $code = $this->generateCode();

        $user->phoneVerificationCode()->updateOrCreate(
            [],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => Date::now()->addMinutes(Config::integer('phone_verification.expires_in_minutes')),
            ],
        );

        $minutos = Config::integer('phone_verification.expires_in_minutes');

        $this->sms->send(
            $user->phone,
            "Tu código de verificación MotoYa es {$code}. Vence en {$minutos} minutos.",
        );
    }

    private function generateCode(): string
    {
        $digitos = Config::integer('phone_verification.code_length');
        $maximo = (10 ** $digitos) - 1;

        return str_pad((string) random_int(0, $maximo), $digitos, '0', STR_PAD_LEFT);
    }
}
