<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['msc_shipment_id', 'name'])]
class MscShipmentContainer extends Model
{
    /** @return BelongsTo<MscShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(MscShipment::class, 'msc_shipment_id');
    }
}
