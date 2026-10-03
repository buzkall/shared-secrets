<?php

namespace Workbench\Database\Seeders;

use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create(['name' => 'Ada Admin', 'email' => 'admin@example.test']);
        User::factory()->admin()->create(['name' => 'Grace Admin', 'email' => 'grace@example.test']);
        User::factory()->client()->create(['name' => 'Carl Client', 'email' => 'client@example.test']);
    }
}
