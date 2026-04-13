<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'vat_number',
        'tax_number',
        'state_id',
        'address',
        'city_code',
        'city_id',
        'place',
        'phone',
        'email',
        'pec',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
