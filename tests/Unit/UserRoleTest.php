<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_enum_has_passenger_case(): void
    {
        $this->assertSame('passenger', UserRole::Passenger->value);
    }

    public function test_enum_has_driver_case(): void
    {
        $this->assertSame('driver', UserRole::Driver->value);
    }

    public function test_enum_has_admin_case(): void
    {
        $this->assertSame('admin', UserRole::Admin->value);
    }

    public function test_can_be_created_from_value(): void
    {
        $this->assertSame(UserRole::Passenger, UserRole::from('passenger'));
        $this->assertSame(UserRole::Driver, UserRole::from('driver'));
        $this->assertSame(UserRole::Admin, UserRole::from('admin'));
    }
}
