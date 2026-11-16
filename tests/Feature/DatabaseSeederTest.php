<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_an_empty_database_and_is_a_noop_on_redeploy(): void
    {
        $this->seed(DatabaseSeeder::class);
        $users = User::count();
        $this->assertGreaterThan(0, $users);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($users, User::count());
    }
}
