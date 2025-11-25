<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([ 'name' => 'Test User', 'email' => 'test@example.com', ]);

        $this->call(UsersTableSeeder::class);
        $this->call(StatesTableSeeder::class);
        $this->call(RegionsTableSeeder::class);
        $this->call(ProvincesTableSeeder::class);
        $this->call(CitiesTableSeeder::class);
        $this->call(AdminTypesTableSeeder::class);
        $this->call(IstatTypesTableSeeder::class);
        $this->call(RecipientsTableSeeder::class);
    }
}
