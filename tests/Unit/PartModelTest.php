<?php

namespace Tests\Unit;

use App\Models\Part;
use PHPUnit\Framework\TestCase;

class PartModelTest extends TestCase
{
    public function test_has_stock_returns_true_when_sufficient(): void
    {
        $part = new Part(['stock_quantity' => 10]);
        $this->assertTrue($part->hasStock(5));
        $this->assertTrue($part->hasStock(10));
    }

    public function test_has_stock_returns_false_when_insufficient(): void
    {
        $part = new Part(['stock_quantity' => 3]);
        $this->assertFalse($part->hasStock(4));
    }

    public function test_has_stock_with_zero(): void
    {
        $part = new Part(['stock_quantity' => 0]);
        $this->assertFalse($part->hasStock(1));
        $this->assertFalse($part->hasStock());
    }
}
