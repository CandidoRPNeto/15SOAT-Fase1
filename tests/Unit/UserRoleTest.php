<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_label_returns_portuguese(): void
    {
        $this->assertSame('Recepcionista', UserRole::RECEPTIONIST->label());
        $this->assertSame('Mecânico', UserRole::MECHANIC->label());
        $this->assertSame('Cliente', UserRole::CLIENT->label());
    }

    public function test_values_are_strings(): void
    {
        $this->assertSame('receptionist', UserRole::RECEPTIONIST->value);
        $this->assertSame('mechanic', UserRole::MECHANIC->value);
        $this->assertSame('client', UserRole::CLIENT->value);
    }

    public function test_from_string(): void
    {
        $this->assertSame(UserRole::CLIENT, UserRole::from('client'));
    }
}
