<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\Concerns\Has;

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
                'password' => Hash::make('M1chele'),
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
                'password' => Hash::make('maura1965'),
                'is_admin' => 1,
                'remember_token' => NULL,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}