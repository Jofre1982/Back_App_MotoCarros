<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property UserRole $role
 * @property Carbon|null $phone_verified_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'role'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'phone_verified_at' => 'datetime',
        ];
    }

    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * La moto del conductor. Uno a uno: `vehicles.user_id` es único, y un
     * conductor que quiere cambiar de moto actualiza la que tiene en vez de
     * registrar otra.
     *
     * El tipo de la relación se declara con sus genéricos porque
     * `RegisterVehicleAction` escribe a través de ella: sin esto, `create()`
     * devuelve un `Model` genérico y PHPStan no puede verificar que la Action
     * cumple su propio contrato de retorno.
     *
     * @return HasOne<Vehicle, $this>
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    /**
     * Los viajes pedidos por esta cuenta como pasajero (historia #29). No
     * se llama `passengerRides` a propósito: del lado del conductor asignado
     * ningún endpoint necesita hoy la relación inversa por `driver_id`, así
     * que un solo nombre corto alcanza sin volverse ambiguo.
     *
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class, 'passenger_id');
    }

    /**
     * Los viajes que esta cuenta aceptó como conductor (historia #30). Sirve
     * el mismo historial que `rides()` del lado del pasajero, y además la
     * base de la que se agregan las ganancias en `SummarizeDriverEarningsAction`.
     *
     * @return HasMany<Ride, $this>
     */
    public function driverRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::Driver;
    }

    public function isPassenger(): bool
    {
        return $this->role === UserRole::Passenger;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * El código de verificación de celular vigente de esta cuenta (historia
     * #69), si hay uno. Uno a uno: `phone_verification_codes.user_id` es
     * único, y pedir un código nuevo reemplaza el que hubiera.
     *
     * @return HasOne<PhoneVerificationCode, $this>
     */
    public function phoneVerificationCode(): HasOne
    {
        return $this->hasOne(PhoneVerificationCode::class);
    }

    /**
     * El código de recuperación de contraseña vigente de esta cuenta, si hay
     * uno. Uno a uno: `password_reset_codes.user_id` es único, y pedir un
     * código nuevo reemplaza el que hubiera — mismo criterio que
     * `phoneVerificationCode()`.
     *
     * @return HasOne<PasswordResetCode, $this>
     */
    public function passwordResetCode(): HasOne
    {
        return $this->hasOne(PasswordResetCode::class);
    }

    /**
     * Identificador que viaja en el claim `sub` del JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Claims extra del JWT.
     *
     * `role` va solo para que el cliente móvil sepa qué UI mostrar sin una
     * request extra. La autorización de negocio NO se resuelve con este claim:
     * se resuelve con Policies contra el `User` autenticado (ver
     * .claude/STANDARDS.md).
     *
     * @return array<string, string>
     */
    public function getJWTCustomClaims(): array
    {
        return ['role' => $this->role->value];
    }
}
