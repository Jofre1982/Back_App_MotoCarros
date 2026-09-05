<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_user_can_be_created(): void
    {
        $user = User::create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
            'password' => 'secret',
            'role' => UserRole::Passenger,
        ]);

        $this->assertTrue($user->isPassenger());
        $this->assertFalse($user->isDriver());
        $this->assertSame(UserRole::Passenger, $user->role);
    }

    public function test_driver_user_can_be_created_with_profile(): void
    {
        $user = User::create([
            'name' => 'Carlos López',
            'email' => 'carlos@example.com',
            'phone' => '+573009876543',
            'password' => 'secret',
            'role' => UserRole::Driver,
        ]);

        $user->driverProfile()->create(['license_number' => 'LIC-001']);

        $this->assertTrue($user->isDriver());
        $this->assertFalse($user->isPassenger());
        $this->assertNotNull($user->driverProfile);
        $this->assertSame('LIC-001', $user->driverProfile->license_number);
    }

    public function test_admin_user_can_be_created(): void
    {
        $user = User::create([
            'name' => 'Staff MotoYa',
            'email' => 'admin@example.com',
            'phone' => '+573001112233',
            'password' => 'secret',
            'role' => UserRole::Admin,
        ]);

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isDriver());
        $this->assertFalse($user->isPassenger());
    }

    public function test_license_number_is_unique_across_drivers(): void
    {
        $this->expectException(QueryException::class);

        $driver1 = User::create([
            'name' => 'Driver One', 'email' => 'd1@example.com',
            'phone' => '+1111', 'password' => 'secret', 'role' => UserRole::Driver,
        ]);
        $driver2 = User::create([
            'name' => 'Driver Two', 'email' => 'd2@example.com',
            'phone' => '+2222', 'password' => 'secret', 'role' => UserRole::Driver,
        ]);

        $driver1->driverProfile()->create(['license_number' => 'DUP-001']);
        $driver2->driverProfile()->create(['license_number' => 'DUP-001']);
    }

    public function test_phone_is_unique(): void
    {
        $this->expectException(QueryException::class);

        User::create([
            'name' => 'User A', 'email' => 'a@example.com',
            'phone' => '+573000000000', 'password' => 'secret', 'role' => UserRole::Passenger,
        ]);
        User::create([
            'name' => 'User B', 'email' => 'b@example.com',
            'phone' => '+573000000000', 'password' => 'secret', 'role' => UserRole::Passenger,
        ]);
    }

    public function test_driver_profile_belongs_to_user(): void
    {
        $user = User::create([
            'name' => 'Pedro', 'email' => 'pedro@example.com',
            'phone' => '+999', 'password' => 'secret', 'role' => UserRole::Driver,
        ]);
        $profile = $user->driverProfile()->create(['license_number' => 'LIC-XYZ']);

        $this->assertTrue($profile->user->is($user));
    }
}
