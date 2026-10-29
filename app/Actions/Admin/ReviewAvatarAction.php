<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Http\Resources\AvatarUrl;
use App\Models\User;

/**
 * El admin aprueba la foto de perfil de un conductor (desde ahí la ven los
 * pasajeros) o la rechaza con un motivo que le llega al conductor.
 */
final readonly class ReviewAvatarAction
{
    public function handle(User $user, ?string $rejectionReason): User
    {
        $user->update($rejectionReason === null
            ? ['avatar_status' => AvatarUrl::APPROVED, 'avatar_rejection_reason' => null]
            : ['avatar_status' => AvatarUrl::REJECTED, 'avatar_rejection_reason' => $rejectionReason]);

        return $user->refresh();
    }
}
