<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Models\Errand;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de GET /api/v1/errands/{errand}/photo — ver openapi.yaml.
 */
class ShowErrandPhotoRequest extends FormRequest
{
    /**
     * Ver la foto es de quienes participan del mandado, mismo criterio que
     * ver su detalle (`ErrandPolicy::view()`): el pasajero que lo pidió y el
     * conductor asignado. Se comprueba sobre el mandado de la ruta sin mirar
     * si tiene foto: quién puede ver el mandado no depende de si esta foto
     * puntual existe.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->routeErrand()) ?? false;
    }

    /**
     * El mandado de la ruta, con la foto ya confirmada. Se llama después de
     * `authorize()`, así que un mandado sin foto para alguien sin permiso
     * sigue respondiendo 403 y no 404 — no le da a un tercero ninguna pista
     * de si el mandado tiene foto o no.
     */
    public function errand(): Errand
    {
        $errand = $this->routeErrand();

        if ($errand->photo_path === null) {
            throw new NotFoundHttpException('Este mandado no tiene una foto adjunta.');
        }

        return $errand;
    }

    private function routeErrand(): Errand
    {
        /** @var Errand */
        return $this->route('errand');
    }
}
