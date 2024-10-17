<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stripe\Service\Climate\ProductService;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Define the relationship with products (assuming many-to-many)
    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function providerService(){
        return $this->belongsTo(ProviderService::class)->with('user');
    }
}
