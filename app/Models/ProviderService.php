<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderService extends Model
{
    use HasFactory;

    protected $table = 'provider_services';

    protected $guarded = [];

    public function media()
    {
        return $this->hasMany(ServiceMedia::class);
    }

    public function user(){
        return $this->belongsTo(User::class); 
    }

    public function providerDetails(){
        return $this->belongsTo(ProviderDetail::class, 'user_id','user_id');
    }

    public function service(){
        return $this->belongsTo(Service::class);
    }

    public function openHours(){
        return $this->hasMany(OpeningHour::class);
    }

    public function ratings()
    {
        return $this->hasMany(ServiceRating::class);
    }

    public function products(){
        return $this->hasMany(Product::class);
    }
}
