<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingProduct extends Model
{
    use HasFactory;

    protected $guarded = [];
    public $timestamps = true; // Ensure this is true if using a custom pivot model

}
