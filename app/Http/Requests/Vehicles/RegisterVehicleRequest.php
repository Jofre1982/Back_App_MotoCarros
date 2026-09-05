<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\DTOs\VehicleRegistration;
use App\Enums\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

/**
 * Entrada de POST /api/v1/me/vehicle — ver openapi.yaml.
 */
class RegisterVehicleRequest extends FormRequest
{
    /**
     * Registrar una moto es una operación del rol conductor: lo decide
     * `VehiclePolicy`, no un `isDriver()` suelto acá.
     *
     * Va en el Form Request y no en el controller para que el 403 se resuelva
     * **antes** que la validación. Al revés, una cuenta de pasajero recibiría un
     * 422 detallándole qué forma tiene que tener la entrada de un endpoint que
     * de todos modos no puede usar.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    /**
     * Lleva la placa a su forma canónica ANTES de validar, es decir antes de
     * `unique` y del DTO.
     *
     * Mismo motivo que el `license_number` del registro de conductor: `unique`
     * es un `where plate = ?` sobre una columna con índice único, así que sin
     * normalizar bastaría escribirla en minúsculas para registrar dos veces la
     * misma moto —y ahí la placa dejaría de identificar a un vehículo, que es
     * para lo único que sirve.
     *
     * Solo se recortan los extremos y se pasa a mayúsculas. Los espacios
     * interiores no se tocan: `ABC 12D` es una placa mal escrita y lo correcto
     * es que muera en el `regex` con un 422 explicable, no que el servidor
     * decida por su cuenta cómo debería haberse escrito.
     */
    protected function prepareForValidation(): void
    {
        $plate = $this->input('plate');

        if (is_string($plate)) {
            $this->merge(['plate' => Str::upper(trim($plate))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `unique` acá y no solo el índice de la tabla: sin la regla, una
            // placa repetida escalaría a un 500 por violación de constraint en
            // vez del 422 que el conductor puede entender y corregir.
            'plate' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9-]{5,10}$/',
                'unique:vehicles,plate',
            ],
            'type' => ['required', new Enum(VehicleType::class)],
            // El tope es el año que viene porque los modelos se venden
            // adelantados al calendario. Los dos límites atajan el dedazo
            // (`2205`, `19999`), no declaran una antigüedad máxima de la flota:
            // si el negocio quiere esa política, es otra decisión y su lugar es
            // la configuración, no una regla de validación.
            'year' => ['required', 'integer', 'digits:4', 'min:1970', 'max:'.((int) date('Y') + 1)],
        ];
    }

    /**
     * El "un vehículo por conductor" se comprueba acá y no con una regla sobre
     * un campo, porque no lo decide nada de lo que el cliente manda: lo decide
     * el estado de la cuenta autenticada. Por eso el error viaja bajo la clave
     * `vehicle`, que no es un campo de la entrada.
     *
     * Es 422 y no 409: para el cliente móvil es el mismo camino que cualquier
     * otro rechazo del formulario, y el endpoint ya responde 422 por la placa
     * duplicada, que es el mismo tipo de choque.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $driver = $this->user();

                if ($driver instanceof User && $driver->vehicle()->exists()) {
                    $validator->errors()->add(
                        'vehicle',
                        'Ya tienes un vehículo registrado; actualiza el existente en vez de registrar otro.',
                    );
                }
            },
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
     * Los campos se leen uno por uno en vez de con `validated()`, igual que en
     * los registros: es lo que garantiza que nada que no esté acá —un `user_id`
     * mandado por el cliente, por ejemplo— pueda colarse hasta la escritura.
     */
    public function toRegistration(): VehicleRegistration
    {
        return new VehicleRegistration(
            plate: $this->string('plate')->toString(),
            type: VehicleType::from($this->string('type')->toString()),
            year: $this->integer('year'),
        );
    }
}
