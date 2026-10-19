<?php

namespace App\Models;

use Database\Factories\VesselFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Vessel extends Model
{
    /** @use HasFactory<VesselFactory> */
    use HasFactory;

    /** @return HasMany<MscShipment, $this> */
    public function mscShipments(): HasMany
    {
        return $this->hasMany(MscShipment::class);
    }
}
