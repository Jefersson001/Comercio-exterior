<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $units_per_container
 * @property int|null $category_id
 */
#[Fillable(['name', 'units_per_container', 'category_id'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['units_per_container' => 'integer'];
    }

    /** @return HasMany<MscShipment, $this> */
    public function mscShipments(): HasMany
    {
        return $this->hasMany(MscShipment::class);
    }

    /** @return HasMany<MscShipmentProduct, $this> */
    public function shipmentLines(): HasMany
    {
        return $this->hasMany(MscShipmentProduct::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
