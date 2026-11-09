<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Actions\Auth\LogoutAction;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra la cuenta y sus datos (requisito de Google Play para apps donde la
 * gente crea cuenta).
 *
 * La base de datos se encarga del resto por sus llaves foráneas: con el
 * usuario se van su perfil de conductor y documentos, su vehículo, sus
 * celulares registrados para push, los viajes y mandados que pidió como
 * pasajero (con sus pagos y calificaciones) y sus códigos pendientes. En los
 * servicios que hizo como conductor queda `driver_id = null`: el pasajero
 * conserva su historial, sin datos del conductor.
 *
 * Los archivos (foto de perfil, documentos, fotos de mandados) no están en
 * la base de datos, así que se borran aparte, después de confirmar el borrado.
 */
final readonly class DeleteAccountAction
{
    public function __construct(private LogoutAction $logout) {}

    public function handle(User $user, string $token): void
    {
        $files = $this->filesOf($user);

        DB::transaction(fn () => $user->delete());

        Storage::disk('local')->delete($files);
        $this->logout->handle($token);
    }

    /**
     * @return list<string>
     */
    private function filesOf(User $user): array
    {
        $documents = $user->driverProfile?->documents()->pluck('path')->all() ?? [];
        $errandPhotos = Errand::query()
            ->where('passenger_id', $user->getKey())
            ->whereNotNull('photo_path')
            ->pluck('photo_path')
            ->all();

        return array_values(array_filter(
            [$user->avatar_path, ...$documents, ...$errandPhotos],
            fn ($path): bool => is_string($path) && $path !== '',
        ));
    }
}
