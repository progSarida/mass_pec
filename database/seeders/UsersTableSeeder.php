<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        DB::table('users')->delete();
        
        DB::table('users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'michele',
                'email' => 'michele.gavazzi@sarida.it',
                'email_verified_at' => NULL,
                'password' => '$2y$12$SQjHi6Dp6UNW/PCbN3OW8eRnw2YLmCIrgxyOMvIyVw2UhBpdl.kZq',
                'is_admin' => 1,
                'remember_token' => NULL,
                'created_at' => '2025-10-07 16:27:34',
                'updated_at' => '2025-10-13 15:17:24',
            ),
            1 => 
            array (
                'id' => 20,
                'name' => 'riccardo',
                'email' => 'riccardo.sambuceti@sarida.it',
                'email_verified_at' => NULL,
                'password' => '$2y$10$p1SzgrAwhbYkO/DcPAl2Oe8oDM0gJ8wWcog1ckdwfBLxvOi8nbF6G',
                'is_admin' => 1,
                'remember_token' => NULL,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}