<?php

declare(strict_types=1);

namespace App\Services\Codes;

use App\Models\User;

/**
 * Entrega un código de un solo uso (verificar la cuenta, recuperar la
 * contraseña) a la persona dueña de la cuenta.
 *
 * Hoy va por correo (`MailAccountCodeSender`): no hay proveedor de SMS
 * contratado y el correo del hosting es gratis. Si algún día se contrata
 * SMS, se cambia el binding en `AppServiceProvider` sin tocar las Actions.
 */
interface AccountCodeSender
{
    /**
     * @param  string  $purpose  Para qué es el código, en una frase corta
     *                           ("verificar tu cuenta", "recuperar tu contraseña").
     */
    public function send(User $user, string $code, string $purpose, int $expiresInMinutes): void;
}
