<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Models\User;
use App\Services\Codes\AccountCodeSender;

/**
 * Anota lo que se habría enviado en vez de mandar un correo de verdad.
 */
final class FakeAccountCodeSender implements AccountCodeSender
{
    /** @var list<array{user: User, code: string, purpose: string, minutes: int}> */
    public array $sent = [];

    public function send(User $user, string $code, string $purpose, int $expiresInMinutes): void
    {
        $this->sent[] = ['user' => $user, 'code' => $code, 'purpose' => $purpose, 'minutes' => $expiresInMinutes];
    }

    public function lastEmail(): ?string
    {
        $last = end($this->sent);

        return $last === false ? null : $last['user']->email;
    }
}
