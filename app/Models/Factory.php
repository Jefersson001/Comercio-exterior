<?php

namespace App\Models;

use Database\Factories\FactoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Factory extends Model
{
    /** @use HasFactory<FactoryFactory> */
    use HasFactory;

    /** @return HasMany<MscShipment, $this> */
    public function mscShipments(): HasMany
    {
        return $this->hasMany(MscShipment::class);
    }
}
