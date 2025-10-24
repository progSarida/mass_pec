<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = [
        'name',
        'alpha2',
        'alpha3',
        'country_code',
        'iso_3166_2',
        'region',
        'sub_region',
        'intermediate_region',
        'region_code',
        'sub_region_code',
        'intermediate_region_code'
    ];

    public function abroad(){
        return $this->name !== 'Italia';
    }
}
