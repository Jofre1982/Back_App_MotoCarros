<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\User;

/**
 * Recuperar la contraseña acepta la cuenta por `email` (lo normal desde que
 * los códigos llegan por correo) o por `phone` (como antes). Uno de los dos
 * es obligatorio; si llegan ambos, manda el correo.
 *
 * Requiere `NormalizesAccountInput` en la misma Request, para comparar el
 * correo en minúsculas y el celular con su `+`.
 */
trait ResolvesAccountByEmailOrPhone
{
    private ?User $resolvedAccount = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function accountLookupRules(): array
    {
        return [
            'email' => ['required_without:phone', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20', 'regex:/^\+[0-9]{7,15}$/'],
        ];
    }

    public function account(): ?User
    {
        if ($this->resolvedAccount instanceof User) {
            return $this->resolvedAccount;
        }

        if ($this->filled('email')) {
            return $this->resolvedAccount = User::firstWhere('email', $this->string('email')->toString());
        }

        if ($this->filled('phone')) {
            return $this->resolvedAccount = User::firstWhere('phone', $this->string('phone')->toString());
        }

        return null;
    }
}
