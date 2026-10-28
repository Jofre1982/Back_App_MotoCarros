<?php

declare(strict_types=1);

namespace App\Services\Codes;

use App\Mail\AccountCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class MailAccountCodeSender implements AccountCodeSender
{
    /**
     * Si el servidor de correo falla, se registra y no se propaga: un 500
     * en "olvidé mi contraseña" delataría que el correo sí tiene cuenta
     * (el endpoint responde 204 igual con o sin cuenta, ver openapi.yaml).
     */
    public function send(User $user, string $code, string $purpose, int $expiresInMinutes): void
    {
        try {
            Mail::to($user->email)->send(new AccountCodeMail($user->name, $code, $purpose, $expiresInMinutes));
        } catch (Throwable $error) {
            Log::error('No se pudo enviar el código por correo', [
                'user_id' => $user->getKey(),
                'error' => $error->getMessage(),
            ]);
        }
    }
}
