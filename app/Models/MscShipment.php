<?php

namespace App\Models;

use Database\Factories\MscShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $etd
 * @property Carbon $eta
 * @property string|null $bl_master
 * @property string|null $bl_house
 * @property int|null $category_id
 * @property-read Category|null $category
 * @property int|null $factory_id
 * @property-read Factory|null $factoryRecord
 * @property int|null $vessel_id
 * @property-read Vessel|null $vessel
 * @property int|null $units_qty
 */
#[Fillable(['etd', 'eta', 'bl_master', 'bl_house', 'factory_id', 'vessel_id', 'product_id', 'category_id', 'container_qty', 'units_qty', 'free_time', 'released'])]
class MscShipment extends Model
{
    /** @use HasFactory<MscShipmentFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['bl_house' => ''];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['etd' => 'date', 'eta' => 'date', 'container_qty' => 'integer', 'units_qty' => 'integer', 'free_time' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Factory, $this> */
    public function factoryRecord(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    /** @return BelongsTo<Vessel, $this> */
    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function totalUnits(): ?int
    {
        if ($this->units_qty !== null) {
            return $this->units_qty;
        }

        $unitsPerContainer = $this->product?->units_per_container;

        return $unitsPerContainer === null ? null : $unitsPerContainer * $this->container_qty;
    }

    /** @param Builder<MscShipment> $query */
    public function scopeSearch(Builder $query, string $search, bool $includeCategory = false): void
    {
        if (trim($search) === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search, $includeCategory): void {
            $term = '%'.trim($search).'%';
            $query->where('bl_master', 'like', $term)->orWhere('bl_house', 'like', $term)
                ->orWhereHas('factoryRecord', fn (Builder $factory) => $factory->where('name', 'like', $term))
                ->orWhereHas('vessel', fn (Builder $vessel) => $vessel->where('name', 'like', $term))
                ->orWhere('released', 'like', $term)
                ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', $term));
            if ($includeCategory) {
                $query->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $term));
            }
            if (ctype_digit(trim($search))) {
                $query->orWhere('id', (int) trim($search));
            }
        });
    }
}
