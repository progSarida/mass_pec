<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'code',
        'province_id',
        'zip_code'
    ];


    public function province(){
        return $this->belongsTo(Province::class, 'province_id');
    }
}
