<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    use HasFactory;

    protected $table='opening_hours';

    protected $fillable = [
        'provider_service_id', // or 'service_id' if linking to a service
        'day',
        'start_time',
        'close_time',
        'is_open'
    ];

    public function provider()
    {
        return $this->belongsTo(ProviderDetail::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
