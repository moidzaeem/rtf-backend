<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceMedia extends Model
{
    use HasFactory;

    protected $table='media';

    protected $guarded = [];

    public function providerService()
    {
        return $this->belongsTo(ProviderService::class);
    }
}
