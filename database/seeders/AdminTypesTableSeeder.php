<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('admin_types')->delete();
        
        \DB::table('admin_types')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Enti Nazionali di Previdenza ed Assistenza Sociale in Conto Economico Consolidato',
                'position' => 1,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Gestori di Pubblici Servizi',
                'position' => 2,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Pubbliche Amministrazioni',
                'position' => 3,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Societa\' in Conto Economico Consolidato',
                'position' => 4,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}