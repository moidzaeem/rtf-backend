<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function specialities()
    {
        return $this->hasMany(Speciality::class);
    }
    protected $hidden = ['created_at', 'updated_at'];

}
