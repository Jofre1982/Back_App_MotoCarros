<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Http\Resources\AvatarUrl;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda la foto de perfil en el disco privado y borra la anterior, para no
 * acumular archivos que nadie va a volver a pedir. La de un conductor queda
 * pendiente de revisión.
 */
final readonly class UploadAvatarAction
{
    public function handle(User $user, UploadedFile $photo): User
    {
        $previous = $user->avatar_path;
        $path = $photo->store('avatars', 'local');

        // La foto de un conductor la ve gente que se sube a su vehículo: la
        // revisa un admin antes. La de un pasajero se usa de una.
        $user->update([
            'avatar_path' => $path,
            'avatar_status' => $user->isDriver() ? AvatarUrl::PENDING : AvatarUrl::APPROVED,
            'avatar_rejection_reason' => null,
        ]);

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return $user->refresh();
    }
}
