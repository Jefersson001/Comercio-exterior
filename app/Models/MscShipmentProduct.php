<?php

namespace App\Models;

use App\Concerns\HasRecordHistory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'container_qty', 'units_qty'])]
class MscShipmentProduct extends Model
{
    use HasRecordHistory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['container_qty' => 'integer', 'units_qty' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<MscShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(MscShipment::class, 'msc_shipment_id');
    }

    public function totalUnits(): ?int
    {
        return $this->units_qty ?? ($this->product->units_per_container === null ? null : $this->container_qty * $this->product->units_per_container);
    }
}
