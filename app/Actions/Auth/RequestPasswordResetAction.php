<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;

/**
 * Genera y envía por SMS el código de recuperación de contraseña de la cuenta
 * con el celular dado, si existe.
 *
 * Este endpoint es anónimo por diseño (quien lo llama todavía no puede
 * iniciar sesión), así que un celular sin cuenta tiene que responder
 * exactamente igual que uno con cuenta: si no, el propio endpoint sería un
 * oráculo para saber qué números de celular están registrados en MotoYa,
 * mismo problema que `LoginAction` resuelve para el email. Por eso este
 * método nunca lanza ni informa si encontró la cuenta — `handle()` siempre
 * "tiene éxito" desde afuera, y el controller responde 204 sin importar el
 * resultado.
 *
 * Un código vigente por cuenta: `password_reset_codes.user_id` es único (ver
 * la migración), así que pedir uno nuevo reemplaza el anterior en vez de
 * acumularlo.
 */
final readonly class RequestPasswordResetAction
{
    public function __construct(private SmsGateway $sms) {}

    public function handle(string $phone): void
    {
        $user = User::firstWhere('phone', $phone);

        if ($user === null) {
            // Se gasta un hash contra nada, mismo motivo que `LoginAction`
            // con un email sin cuenta: sin esto, un celular sin cuenta
            // respondería más rápido que uno real (que además hashea el
            // código y escribe en la base), y esa diferencia de tiempo
            // reconstruye el mismo oráculo que evitar el mensaje ya evita.
            Hash::make($this->generateCode());

            return;
        }

        $code = $this->generateCode();

        $user->passwordResetCode()->updateOrCreate(
            [],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => Date::now()->addMinutes(Config::integer('password_reset.expires_in_minutes')),
            ],
        );

        $minutos = Config::integer('password_reset.expires_in_minutes');

        $this->sms->send(
            $user->phone,
            "Tu código para recuperar tu contraseña en MotoYa es {$code}. Vence en {$minutos} minutos.",
        );
    }

    private function generateCode(): string
    {
        $digitos = Config::integer('password_reset.code_length');
        $maximo = (10 ** $digitos) - 1;

        return str_pad((string) random_int(0, $maximo), $digitos, '0', STR_PAD_LEFT);
    }
}
