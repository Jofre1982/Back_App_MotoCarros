<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Resources\AvatarUrl;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;

/**
 * Autorización sobre las cuentas de otros usuarios (historia #97).
 *
 * Solo el administrador puede listarlas, mismo criterio que `SitePolicy` y
 * `DriverDocumentPolicy`: es staff operando sobre cuentas de terceros, no el
 * dueño de ninguna de ellas.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Ver la foto de perfil: la propia cuenta, un admin, o quien comparte un
     * viaje o mandado con esa cuenta (pasajero y conductor asignado). Así el
     * pasajero reconoce a quien lo recoge sin que la foto quede abierta a
     * cualquiera.
     */
    public function viewAvatar(User $viewer, User $owner): bool
    {
        if ($viewer->is($owner) || $viewer->isAdmin()) {
            return true;
        }

        if ($owner->avatar_status !== AvatarUrl::APPROVED) {
            return false;
        }

        return $this->sharesAService($viewer, $owner);
    }

    /**
     * Foto del vehículo: el propio conductor, un admin, o quien comparte un
     * viaje o mandado con él. Que esté aprobada lo decide el controller (404).
     */
    public function viewVehiclePhoto(User $viewer, User $owner): bool
    {
        return $viewer->is($owner) || $viewer->isAdmin() || $this->sharesAService($viewer, $owner);
    }

    private function sharesAService(User $viewer, User $owner): bool
    {
        $pair = fn ($query) => $query
            ->where(fn ($q) => $q->where('passenger_id', $viewer->getKey())->where('driver_id', $owner->getKey()))
            ->orWhere(fn ($q) => $q->where('passenger_id', $owner->getKey())->where('driver_id', $viewer->getKey()));

        return Ride::query()->where($pair)->exists() || Errand::query()->where($pair)->exists();
    }

    /**
     * Aprobar o rechazar fotos de perfil es del admin, mismo criterio que
     * revisar documentos.
     */
    public function reviewAvatar(User $user): bool
    {
        return $user->isAdmin();
    }
}
