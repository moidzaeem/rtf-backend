<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function bookings()
    {
        return $this->belongsToMany(Booking::class)->withPivot('quantity');
    }
}
