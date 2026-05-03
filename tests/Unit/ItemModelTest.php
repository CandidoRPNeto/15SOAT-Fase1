<?php

namespace Tests\Unit;

use App\Models\Item;
use PHPUnit\Framework\TestCase;

class ItemModelTest extends TestCase
{
    public function test_has_stock_returns_true_when_sufficient(): void
    {
        $item = new Item(['stock_quantity' => 10]);
        $this->assertTrue($item->hasStock(5));
        $this->assertTrue($item->hasStock(10));
    }

    public function test_has_stock_returns_false_when_insufficient(): void
    {
        $item = new Item(['stock_quantity' => 3]);
        $this->assertFalse($item->hasStock(4));
    }

    public function test_has_stock_with_zero(): void
    {
        $item = new Item(['stock_quantity' => 0]);
        $this->assertFalse($item->hasStock(1));
        $this->assertFalse($item->hasStock());
    }
}
