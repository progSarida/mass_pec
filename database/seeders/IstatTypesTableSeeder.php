<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class IstatTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('istat_types')->delete();
        
        \DB::table('istat_types')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Agenzie ed Enti per il Turismo',
                'position' => 1,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Agenzie ed Enti Regionali del Lavoro',
                'position' => 2,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Agenzie ed Enti Regionali di Sviluppo Agricolo',
                'position' => 3,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Agenzie ed Enti Regionali per la Formazione, la Ricerca e l\'Ambiente',
                'position' => 4,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Agenzie Fiscali',
                'position' => 5,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Agenzie Regionali e Provinciale per la Rappresentanza Negoziale',
                'position' => 6,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Agenzie Regionali per le Erogazioni in Agricoltura',
                'position' => 7,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'Agenzie Regionali Sanitarie',
                'position' => 8,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Agenzie, Enti e Consorzi Pubblici per il Diritto allo Studio Universitario',
                'position' => 9,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'Altri Enti Locali',
                'position' => 10,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'Automobile Club Federati ACI',
                'position' => 11,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'Autorita\' Amministrative Indipendenti',
                'position' => 12,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'Autorita\' di Ambito Territoriale Ottimale',
                'position' => 13,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'Autorita\' di Bacino',
                'position' => 14,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'Autorita\' Portuali',
                'position' => 15,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            15 => 
            array (
                'id' => 16,
                'name' => 'Aziende e Consorzi Pubblici Territoriali per l\'Edilizia Residenziale',
                'position' => 16,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            16 => 
            array (
                'id' => 17,
                'name' => 'Aziende ed Amministrazioni dello Stato ad Ordinamento Autonomo',
                'position' => 17,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            17 => 
            array (
                'id' => 18,
                'name' => 'Aziende Ospedaliere, Aziende Ospedaliere Universitarie, Policlinici e Istituti di Ricovero e Cura a Carattere Scientifico Pubblici',
                'position' => 18,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            18 => 
            array (
                'id' => 19,
                'name' => 'Aziende Pubbliche di Servizi alla Persona',
                'position' => 19,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            19 => 
            array (
                'id' => 20,
                'name' => 'Aziende Sanitarie Locali',
                'position' => 20,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            20 => 
            array (
                'id' => 21,
                'name' => 'Camere di Commercio, Industria, Artigianato e Agricoltura e loro Unioni Regionali',
                'position' => 21,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            21 => 
            array (
                'id' => 22,
                'name' => 'Citta\' Metropolitane',
                'position' => 22,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            22 => 
            array (
                'id' => 23,
                'name' => 'Comuni e loro Consorzi e Associazioni',
                'position' => 23,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            23 => 
            array (
                'id' => 24,
                'name' => 'Comunita\' Montane e loro Consorzi e Associazioni',
                'position' => 24,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            24 => 
            array (
                'id' => 25,
                'name' => 'Consorzi di Bacino Imbrifero Montano',
                'position' => 25,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            25 => 
            array (
                'id' => 26,
                'name' => 'Consorzi Interuniversitari di Ricerca',
                'position' => 26,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            26 => 
            array (
                'id' => 27,
                'name' => 'Consorzi per l\'Area di Sviluppo Industriale',
                'position' => 27,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            27 => 
            array (
                'id' => 28,
                'name' => 'Consorzi tra Amministrazioni Locali',
                'position' => 28,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            28 => 
            array (
                'id' => 29,
                'name' => 'Enti di Regolazione dei Servizi Idrici e o dei Rifiuti',
                'position' => 29,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            29 => 
            array (
                'id' => 30,
                'name' => 'Enti e Istituzioni di Ricerca Pubblici',
                'position' => 30,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            30 => 
            array (
                'id' => 31,
                'name' => 'Enti Nazionali di Previdenza ed Assistenza Sociale in Conto Economico Consolidato',
                'position' => 31,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            31 => 
            array (
                'id' => 32,
                'name' => 'Enti Pubblici Non Economici',
                'position' => 32,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            32 => 
            array (
                'id' => 33,
                'name' => 'Enti Pubblici Produttori di Servizi Assistenziali, Ricreativi e Culturali ',
                'position' => 33,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            33 => 
            array (
                'id' => 34,
                'name' => 'Federazioni Nazionali, Ordini, Collegi e Consigli Professionali',
                'position' => 34,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            34 => 
            array (
                'id' => 35,
                'name' => 'Fondazioni Lirico, Sinfoniche',
                'position' => 35,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            35 => 
            array (
                'id' => 36,
                'name' => 'Forze di Polizia ad Ordinamento Civile e Militare per la Tutela dell\'Ordine e della Sicurezza Pubblica',
                'position' => 36,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            36 => 
            array (
                'id' => 37,
                'name' => 'Gestori di Pubblici Servizi',
                'position' => 37,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            37 => 
            array (
                'id' => 38,
                'name' => 'Istituti di Istruzione Statale di Ogni Ordine e Grado',
                'position' => 38,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            38 => 
            array (
                'id' => 39,
                'name' => 'Istituti Zooprofilattici Sperimentali',
                'position' => 39,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            39 => 
            array (
                'id' => 40,
                'name' => 'Istituzioni per l\'Alta Formazione Artistica, Musicale e Coreutica - AFAM',
                'position' => 40,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            40 => 
            array (
                'id' => 41,
                'name' => 'Organi Costituzionali e di Rilievo Costituzionale',
                'position' => 41,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            41 => 
            array (
                'id' => 42,
                'name' => 'Parchi Nazionali, Consorzi e Enti Gestori di Parchi e Aree Naturali Protette',
                'position' => 42,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            42 => 
            array (
                'id' => 43,
                'name' => 'Presidenza del Consiglio dei Ministri, Ministeri e Avvocatura dello Stato',
                'position' => 43,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            43 => 
            array (
                'id' => 44,
                'name' => 'Province e loro Consorzi e Associazioni',
                'position' => 44,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            44 => 
            array (
                'id' => 45,
                'name' => 'Regioni, Province Autonome e loro Consorzi e Associazioni',
                'position' => 45,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            45 => 
            array (
                'id' => 46,
                'name' => 'Societa\' in Conto Economico Consolidato',
                'position' => 46,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            46 => 
            array (
                'id' => 47,
                'name' => 'Teatri Stabili ad Iniziativa Pubblica',
                'position' => 47,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            47 => 
            array (
                'id' => 48,
                'name' => 'Unioni di Comuni e loro Consorzi e Associazioni',
                'position' => 48,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            48 => 
            array (
                'id' => 49,
                'name' => 'Universita\' e Istituti di Istruzione Universitaria Pubblici',
                'position' => 49,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}