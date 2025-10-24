<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvincesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('provinces')->delete();

        DB::table('provinces')->insert(array (
            0 =>
            array (
                'id' => 1,
                'region_id' => 1,
                'name' => 'Torino',
                'code' => 'TO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'region_id' => 1,
                'name' => 'Vercelli',
                'code' => 'VC',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'region_id' => 1,
                'name' => 'Novara',
                'code' => 'NO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 =>
            array (
                'id' => 4,
                'region_id' => 1,
                'name' => 'Cuneo',
                'code' => 'CN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 =>
            array (
                'id' => 5,
                'region_id' => 1,
                'name' => 'Asti',
                'code' => 'AT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 =>
            array (
                'id' => 6,
                'region_id' => 1,
                'name' => 'Alessandria',
                'code' => 'AL',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 =>
            array (
                'id' => 7,
                'region_id' => 2,
                'name' => 'Valle d\'Aosta',
                'code' => 'AO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 =>
            array (
                'id' => 8,
                'region_id' => 7,
                'name' => 'Imperia',
                'code' => 'IM',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 =>
            array (
                'id' => 9,
                'region_id' => 7,
                'name' => 'Savona',
                'code' => 'SV',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 =>
            array (
                'id' => 10,
                'region_id' => 7,
                'name' => 'Genova',
                'code' => 'GE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 =>
            array (
                'id' => 11,
                'region_id' => 7,
                'name' => 'La Spezia',
                'code' => 'SP',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 =>
            array (
                'id' => 12,
                'region_id' => 3,
                'name' => 'Varese',
                'code' => 'VA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 =>
            array (
                'id' => 13,
                'region_id' => 3,
                'name' => 'Como',
                'code' => 'CO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            13 =>
            array (
                'id' => 14,
                'region_id' => 3,
                'name' => 'Sondrio',
                'code' => 'SO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            14 =>
            array (
                'id' => 15,
                'region_id' => 3,
                'name' => 'Milano',
                'code' => 'MI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            15 =>
            array (
                'id' => 16,
                'region_id' => 3,
                'name' => 'Bergamo',
                'code' => 'BG',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            16 =>
            array (
                'id' => 17,
                'region_id' => 3,
                'name' => 'Brescia',
                'code' => 'BS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            17 =>
            array (
                'id' => 18,
                'region_id' => 3,
                'name' => 'Pavia',
                'code' => 'PV',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            18 =>
            array (
                'id' => 19,
                'region_id' => 3,
                'name' => 'Cremona',
                'code' => 'CR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            19 =>
            array (
                'id' => 20,
                'region_id' => 3,
                'name' => 'Mantova',
                'code' => 'MN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            20 =>
            array (
                'id' => 21,
                'region_id' => 4,
                'name' => 'Bolzano',
                'code' => 'BZ',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            21 =>
            array (
                'id' => 22,
                'region_id' => 4,
                'name' => 'Trento',
                'code' => 'TN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            22 =>
            array (
                'id' => 23,
                'region_id' => 5,
                'name' => 'Verona',
                'code' => 'VR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            23 =>
            array (
                'id' => 24,
                'region_id' => 5,
                'name' => 'Vicenza',
                'code' => 'VI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            24 =>
            array (
                'id' => 25,
                'region_id' => 5,
                'name' => 'Belluno',
                'code' => 'BL',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            25 =>
            array (
                'id' => 26,
                'region_id' => 5,
                'name' => 'Treviso',
                'code' => 'TV',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            26 =>
            array (
                'id' => 27,
                'region_id' => 5,
                'name' => 'Venezia',
                'code' => 'VE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            27 =>
            array (
                'id' => 28,
                'region_id' => 5,
                'name' => 'Padova',
                'code' => 'PD',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            28 =>
            array (
                'id' => 29,
                'region_id' => 5,
                'name' => 'Rovigo',
                'code' => 'RO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            29 =>
            array (
                'id' => 30,
                'region_id' => 6,
                'name' => 'Udine',
                'code' => 'UD',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            30 =>
            array (
                'id' => 31,
                'region_id' => 6,
                'name' => 'Gorizia',
                'code' => 'GO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            31 =>
            array (
                'id' => 32,
                'region_id' => 6,
                'name' => 'Trieste',
                'code' => 'TS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            32 =>
            array (
                'id' => 33,
                'region_id' => 8,
                'name' => 'Piacenza',
                'code' => 'PC',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            33 =>
            array (
                'id' => 34,
                'region_id' => 8,
                'name' => 'Parma',
                'code' => 'PR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            34 =>
            array (
                'id' => 35,
                'region_id' => 8,
                'name' => 'Reggio nell\'Emilia',
                'code' => 'RE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            35 =>
            array (
                'id' => 36,
                'region_id' => 8,
                'name' => 'Modena',
                'code' => 'MO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            36 =>
            array (
                'id' => 37,
                'region_id' => 8,
                'name' => 'Bologna',
                'code' => 'BO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            37 =>
            array (
                'id' => 38,
                'region_id' => 8,
                'name' => 'Ferrara',
                'code' => 'FE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            38 =>
            array (
                'id' => 39,
                'region_id' => 8,
                'name' => 'Ravenna',
                'code' => 'RA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            39 =>
            array (
                'id' => 40,
                'region_id' => 8,
                'name' => 'Forlì',
                'code' => 'FC',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            40 =>
            array (
                'id' => 41,
                'region_id' => 11,
                'name' => 'Pesaro e Urbino',
                'code' => 'PU',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            41 =>
            array (
                'id' => 42,
                'region_id' => 11,
                'name' => 'Ancona',
                'code' => 'AN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            42 =>
            array (
                'id' => 43,
                'region_id' => 11,
                'name' => 'Macerata',
                'code' => 'MC',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            43 =>
            array (
                'id' => 44,
                'region_id' => 11,
                'name' => 'Ascoli Piceno',
                'code' => 'AP',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            44 =>
            array (
                'id' => 45,
                'region_id' => 9,
                'name' => 'Massa-Carrara',
                'code' => 'MS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            45 =>
            array (
                'id' => 46,
                'region_id' => 9,
                'name' => 'Lucca',
                'code' => 'LU',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            46 =>
            array (
                'id' => 47,
                'region_id' => 9,
                'name' => 'Pistoia',
                'code' => 'PT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            47 =>
            array (
                'id' => 48,
                'region_id' => 9,
                'name' => 'Firenze',
                'code' => 'FI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            48 =>
            array (
                'id' => 49,
                'region_id' => 9,
                'name' => 'Livorno',
                'code' => 'LI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            49 =>
            array (
                'id' => 50,
                'region_id' => 9,
                'name' => 'Pisa',
                'code' => 'PI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            50 =>
            array (
                'id' => 51,
                'region_id' => 9,
                'name' => 'Arezzo',
                'code' => 'AR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            51 =>
            array (
                'id' => 52,
                'region_id' => 9,
                'name' => 'Siena',
                'code' => 'SI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            52 =>
            array (
                'id' => 53,
                'region_id' => 9,
                'name' => 'Grosseto',
                'code' => 'GR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            53 =>
            array (
                'id' => 54,
                'region_id' => 10,
                'name' => 'Perugia',
                'code' => 'PG',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            54 =>
            array (
                'id' => 55,
                'region_id' => 10,
                'name' => 'Terni',
                'code' => 'TR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            55 =>
            array (
                'id' => 56,
                'region_id' => 12,
                'name' => 'Viterbo',
                'code' => 'VT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            56 =>
            array (
                'id' => 57,
                'region_id' => 12,
                'name' => 'Rieti',
                'code' => 'RI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            57 =>
            array (
                'id' => 58,
                'region_id' => 12,
                'name' => 'Roma',
                'code' => 'RM',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            58 =>
            array (
                'id' => 59,
                'region_id' => 12,
                'name' => 'Latina',
                'code' => 'LT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            59 =>
            array (
                'id' => 60,
                'region_id' => 12,
                'name' => 'Frosinone',
                'code' => 'FR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            60 =>
            array (
                'id' => 61,
                'region_id' => 15,
                'name' => 'Caserta',
                'code' => 'CE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            61 =>
            array (
                'id' => 62,
                'region_id' => 15,
                'name' => 'Benevento',
                'code' => 'BN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            62 =>
            array (
                'id' => 63,
                'region_id' => 15,
                'name' => 'Napoli',
                'code' => 'NA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            63 =>
            array (
                'id' => 64,
                'region_id' => 15,
                'name' => 'Avellino',
                'code' => 'AV',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            64 =>
            array (
                'id' => 65,
                'region_id' => 15,
                'name' => 'Salerno',
                'code' => 'SA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            65 =>
            array (
                'id' => 66,
                'region_id' => 13,
                'name' => 'L\'Aquila',
                'code' => 'AQ',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            66 =>
            array (
                'id' => 67,
                'region_id' => 13,
                'name' => 'Teramo',
                'code' => 'TE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            67 =>
            array (
                'id' => 68,
                'region_id' => 13,
                'name' => 'Pescara',
                'code' => 'PE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            68 =>
            array (
                'id' => 69,
                'region_id' => 13,
                'name' => 'Chieti',
                'code' => 'CH',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            69 =>
            array (
                'id' => 70,
                'region_id' => 14,
                'name' => 'Campobasso',
                'code' => 'CB',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            70 =>
            array (
                'id' => 71,
                'region_id' => 16,
                'name' => 'Foggia',
                'code' => 'FG',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            71 =>
            array (
                'id' => 72,
                'region_id' => 16,
                'name' => 'Bari',
                'code' => 'BA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            72 =>
            array (
                'id' => 73,
                'region_id' => 16,
                'name' => 'Taranto',
                'code' => 'TA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            73 =>
            array (
                'id' => 74,
                'region_id' => 16,
                'name' => 'Brindisi',
                'code' => 'BR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            74 =>
            array (
                'id' => 75,
                'region_id' => 16,
                'name' => 'Lecce',
                'code' => 'LE',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            75 =>
            array (
                'id' => 76,
                'region_id' => 17,
                'name' => 'Potenza',
                'code' => 'PZ',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            76 =>
            array (
                'id' => 77,
                'region_id' => 17,
                'name' => 'Matera',
                'code' => 'MT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            77 =>
            array (
                'id' => 78,
                'region_id' => 18,
                'name' => 'Cosenza',
                'code' => 'CS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            78 =>
            array (
                'id' => 79,
                'region_id' => 18,
                'name' => 'Catanzaro',
                'code' => 'CZ',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            79 =>
            array (
                'id' => 80,
                'region_id' => 18,
                'name' => 'Reggio di Calabria',
                'code' => 'RC',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            80 =>
            array (
                'id' => 81,
                'region_id' => 19,
                'name' => 'Trapani',
                'code' => 'TP',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            81 =>
            array (
                'id' => 82,
                'region_id' => 19,
                'name' => 'Palermo',
                'code' => 'PA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            82 =>
            array (
                'id' => 83,
                'region_id' => 19,
                'name' => 'Messina',
                'code' => 'ME',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            83 =>
            array (
                'id' => 84,
                'region_id' => 19,
                'name' => 'Agrigento',
                'code' => 'AG',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            84 =>
            array (
                'id' => 85,
                'region_id' => 19,
                'name' => 'Caltanissetta',
                'code' => 'CL',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            85 =>
            array (
                'id' => 86,
                'region_id' => 19,
                'name' => 'Enna',
                'code' => 'EN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            86 =>
            array (
                'id' => 87,
                'region_id' => 19,
                'name' => 'Catania',
                'code' => 'CT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            87 =>
            array (
                'id' => 88,
                'region_id' => 19,
                'name' => 'Ragusa',
                'code' => 'RG',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            88 =>
            array (
                'id' => 89,
                'region_id' => 19,
                'name' => 'Siracusa',
                'code' => 'SR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            89 =>
            array (
                'id' => 90,
                'region_id' => 20,
                'name' => 'Sassari',
                'code' => 'SS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            90 =>
            array (
                'id' => 91,
                'region_id' => 20,
                'name' => 'Nuoro',
                'code' => 'NU',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            91 =>
            array (
                'id' => 92,
                'region_id' => 20,
                'name' => 'Cagliari',
                'code' => 'CA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            92 =>
            array (
                'id' => 93,
                'region_id' => 6,
                'name' => 'Pordenone',
                'code' => 'PN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            93 =>
            array (
                'id' => 94,
                'region_id' => 14,
                'name' => 'Isernia',
                'code' => 'IS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            94 =>
            array (
                'id' => 95,
                'region_id' => 20,
                'name' => 'Oristano',
                'code' => 'OR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            95 =>
            array (
                'id' => 96,
                'region_id' => 1,
                'name' => 'Biella',
                'code' => 'BI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            96 =>
            array (
                'id' => 97,
                'region_id' => 3,
                'name' => 'Lecco',
                'code' => 'LC',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            97 =>
            array (
                'id' => 98,
                'region_id' => 3,
                'name' => 'Lodi',
                'code' => 'LO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            98 =>
            array (
                'id' => 99,
                'region_id' => 8,
                'name' => 'Rimini',
                'code' => 'RN',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            99 =>
            array (
                'id' => 100,
                'region_id' => 9,
                'name' => 'Prato',
                'code' => 'PO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            100 =>
            array (
                'id' => 101,
                'region_id' => 18,
                'name' => 'Crotone',
                'code' => 'KR',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            101 =>
            array (
                'id' => 102,
                'region_id' => 18,
                'name' => 'Vibo Valentia',
                'code' => 'VV',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            102 =>
            array (
                'id' => 103,
                'region_id' => 1,
                'name' => 'Verbano-Cusio-Ossola',
                'code' => 'VB',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            103 =>
            array (
                'id' => 104,
                'region_id' => 20,
                'name' => 'Olbia-Tempio',
                'code' => 'OT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            104 =>
            array (
                'id' => 105,
                'region_id' => 20,
                'name' => 'Ogliastra',
                'code' => 'OG',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            105 =>
            array (
                'id' => 106,
                'region_id' => 20,
                'name' => 'Medio Campidano',
                'code' => 'VS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            106 =>
            array (
                'id' => 107,
                'region_id' => 20,
                'name' => 'Carbonia-Iglesias',
                'code' => 'CI',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            107 =>
            array (
                'id' => 108,
                'region_id' => 3,
                'name' => 'Monza e della Brianza',
                'code' => 'MB',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            108 =>
            array (
                'id' => 109,
                'region_id' => 11,
                'name' => 'Fermo',
                'code' => 'FM',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            109 =>
            array (
                'id' => 110,
                'region_id' => 16,
                'name' => 'Barletta-Andria-Trani',
                'code' => 'BT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));


    }
}
