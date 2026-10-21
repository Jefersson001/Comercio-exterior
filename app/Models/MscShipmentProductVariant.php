<?php

namespace App\Models;

use App\Concerns\HasRecordHistory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['msc_shipment_id', 'product_variant_id', 'quantity'])]
class MscShipmentProductVariant extends Model
{
    use HasRecordHistory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<MscShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(MscShipment::class, 'msc_shipment_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
