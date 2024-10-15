<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['provider_detail_id', 'balance'];

    public function provider()
    {
        return $this->belongsTo(ProviderDetail::class);
    }
}
