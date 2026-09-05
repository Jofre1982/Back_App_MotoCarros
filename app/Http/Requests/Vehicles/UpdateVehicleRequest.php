<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\DTOs\VehicleUpdate;
use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de PATCH /api/v1/me/vehicle — ver openapi.yaml.
 */
class UpdateVehicleRequest extends FormRequest
{
    private ?Vehicle $vehicle = null;

    /**
     * Resuelve, en este orden, los tres rechazos posibles antes de la
     * validación: el rol (403), el recurso inexistente (404) y la propiedad
     * (403). El orden **es** el cuerpo de este método, y no es cosmético:
     *
     * - Al pasajero le corresponde 403 y no 404. Que no tenga moto no es un
     *   dato que le falte cargar: operar vehículos no es de su rol, igual que
     *   en el alta. Un 404 le sugeriría que registrando una podría seguir.
     * - Al conductor sin moto le corresponde 404 antes que cualquier 422: que
     *   el recurso no exista pesa más que la forma del body, y un 422 le haría
     *   creer que corrigiendo los datos la request funcionaría.
     *
     * El 404 vive acá y no en `prepareForValidation()` porque ese hook corre
     * **antes** que la autorización (`ValidatesWhenResolvedTrait::validateResolved()`),
     * y ahí el pasajero se llevaría el 404 en vez del 403.
     */
    public function authorize(): bool
    {
        $driver = $this->user();

        // "¿Podría esta cuenta tener una moto?" es exactamente la pregunta que
        // responde `create`, así que se reusa en vez de inventar un tercer
        // método de Policy que diría lo mismo. Va antes de buscar el vehículo
        // porque es lo único que se puede contestar sin recurso.
        if (! $driver instanceof User || ! $driver->can('create', Vehicle::class)) {
            return false;
        }

        return $driver->can('update', $this->vehicle());
    }

    /**
     * La moto de la cuenta autenticada, o 404 si todavía no registró ninguna.
     *
     * Se resuelve por la relación y nunca por un id de la entrada: es lo que
     * hace que no exista forma de apuntar este endpoint al vehículo de otro
     * conductor. La `VehiclePolicy` comprueba igual la propiedad —ver
     * `VehiclePolicyTest`—: la garantía tiene que estar en la regla, no en que
     * la ruta de hoy no lleve id.
     *
     * Memoizada porque la usan la autorización, las reglas y el controller, y
     * las tres tienen que ver la misma fila.
     */
    public function vehicle(): Vehicle
    {
        if ($this->vehicle instanceof Vehicle) {
            return $this->vehicle;
        }

        $driver = $this->user();
        $vehicle = $driver instanceof User ? $driver->vehicle : null;

        if (! $vehicle instanceof Vehicle) {
            throw new NotFoundHttpException(
                'No tienes un vehículo registrado; regístralo antes de actualizarlo.',
            );
        }

        return $this->vehicle = $vehicle;
    }

    /**
     * Lleva la placa a su forma canónica ANTES de validar, es decir antes de
     * `unique` y del DTO — mismo motivo que en el alta: `unique` es un
     * `where plate = ?` sobre una columna con índice único, así que sin
     * normalizar bastaría escribirla en minúsculas para terminar con dos filas
     * que son la misma moto.
     *
     * Solo se toca si vino: en un PATCH parcial, un campo ausente significa "no
     * lo cambies", y mergear una placa vacía lo convertiría en un campo
     * presente que además fallaría la validación.
     */
    protected function prepareForValidation(): void
    {
        $plate = $this->input('plate');

        if (is_string($plate)) {
            $this->merge(['plate' => Str::upper(trim($plate))]);
        }
    }

    /**
     * Las reglas son las del alta con `sometimes|required` delante: un dato
     * corregido no puede quedar en un estado que el registro habría rechazado.
     *
     * `sometimes` a secas no alcanza —al contrario, sería un agujero—: ante una
     * cadena vacía Laravel se salta TODAS las reglas no implícitas
     * (`Validator::presentOrRuleIsImplicit()`), incluidas `regex` y `unique`,
     * así que `{"plate": ""}` pasaría la validación y dejaría la moto sin placa
     * contra una columna NOT NULL. `required` es implícita, corre igual sobre
     * la cadena vacía y también cubre la que trae solo espacios.
     *
     * La unicidad de la placa se ignora contra la propia fila: si no, un
     * cliente que manda el formulario completo sin haber tocado la placa
     * recibiría un 422 por chocar consigo mismo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plate' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9-]{5,10}$/',
                Rule::unique('vehicles', 'plate')->ignore($this->vehicle()->getKey()),
            ],
            'type' => ['sometimes', 'required', new Enum(VehicleType::class)],
            // El tope es el año que viene porque los modelos se venden
            // adelantados al calendario, igual que en el alta.
            'year' => ['sometimes', 'required', 'integer', 'digits:4', 'min:1970', 'max:'.((int) date('Y') + 1)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate.regex' => 'El campo plate debe tener entre 5 y 10 caracteres, solo letras, dígitos y guiones; se guarda siempre en mayúsculas.',
        ];
    }

    /**
     * Traduce la entrada validada al DTO que consume la Action, que no conoce
     * HTTP (ver .claude/STANDARDS.md).
     *
     * Los campos se leen uno por uno y no con `validated()`: es lo que
     * garantiza que nada que no esté acá —un `user_id` o un `id` mandado por el
     * cliente, por ejemplo— pueda colarse hasta la escritura. Se lee con `has()`
     * y no con `filled()` porque lo que importa es si el campo vino en la
     * request: para cuando esto corre, un valor vacío ya murió en el `required`
     * de `rules()`, así que lo que llega es un valor real.
     */
    public function toUpdate(): VehicleUpdate
    {
        return new VehicleUpdate(
            plate: $this->has('plate') ? $this->string('plate')->toString() : null,
            type: $this->has('type') ? VehicleType::from($this->string('type')->toString()) : null,
            year: $this->has('year') ? $this->integer('year') : null,
        );
    }
}
