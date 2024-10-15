<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $guarded =[];

    // Define the inverse relationship if needed
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
