<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRating extends Model
{
    use HasFactory;

    protected $fillable = ['provider_service_id', 'user_id', 'rating', 'comment'];

    public function providerService()
    {
        return $this->belongsTo(ProviderService::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
