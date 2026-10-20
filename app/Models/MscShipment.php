<?php

namespace App\Models;

use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use Database\Factories\MscShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property MscShipmentStatus|null $status
 * @property MscBillOfLadingStatus|null $status_bl
 * @property Carbon|null $recognition_date
 * @property Carbon|null $receipt_date
 * @property Carbon|null $etd_vnzl
 * @property Carbon|null $eta_vnzl
 * @property Carbon|null $in_bond_date
 * @property Carbon|null $delay_date
 * @property Carbon|null $legal_abandonment_date
 * @property Carbon|null $registration_date
 * @property Carbon|null $phase_two_recognition_date
 * @property Carbon|null $validation_date
 * @property Carbon|null $plant_receipt_date
 * @property Carbon|null $return_date
 * @property Carbon|null $original_docs_receipt_date
 * @property Carbon|null $aa_instructions_sent_date
 * @property Carbon|null $bl_release_date
 * @property Carbon|null $sencamer_request_date
 * @property Carbon|null $sencamer_receipt_date
 * @property Carbon|null $comex_request_date
 * @property Carbon|null $comex_receipt_date
 */
#[Fillable(['etd', 'eta', 'bl_master', 'bl_house', 'factory_id', 'vessel_id', 'product_id', 'category_id', 'container_qty', 'units_qty', 'free_time', 'released', 'status', 'status_bl', 'exp_number', 'dua_intr', 'seabridge', 'sencamer', 'comex', 'recognition_date', 'receipt_date', 'shipment_year', 'etd_vnzl', 'eta_vnzl', 'port', 'trasegado', 'in_bond_date', 'free_days', 'delay_date', 'legal_abandonment_date', 'registration_date', 'phase_two_recognition_date', 'validation_date', 'plant_receipt_date', 'location', 'return_date', 'original_docs_receipt_date', 'aa_instructions_sent_date', 'bl_release_date', 'sencamer_request_date', 'sencamer_receipt_date', 'comex_request_date', 'comex_receipt_date', 'customs_agent_status', 'customs_agent_observation'])]
class MscShipment extends Model
{
    /** @use HasFactory<MscShipmentFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['bl_house' => ''];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['etd' => 'date', 'eta' => 'date', 'recognition_date' => 'date', 'receipt_date' => 'date', 'etd_vnzl' => 'date', 'eta_vnzl' => 'date', 'in_bond_date' => 'date', 'delay_date' => 'date', 'legal_abandonment_date' => 'date', 'registration_date' => 'date', 'phase_two_recognition_date' => 'date', 'validation_date' => 'date', 'plant_receipt_date' => 'date', 'return_date' => 'date', 'original_docs_receipt_date' => 'date', 'aa_instructions_sent_date' => 'date', 'bl_release_date' => 'date', 'sencamer_request_date' => 'date', 'sencamer_receipt_date' => 'date', 'comex_request_date' => 'date', 'comex_receipt_date' => 'date', 'container_qty' => 'integer', 'units_qty' => 'integer', 'free_time' => 'integer', 'free_days' => 'integer', 'status' => MscShipmentStatus::class, 'status_bl' => MscBillOfLadingStatus::class];
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

    /** @return HasMany<MscShipmentContainer, $this> */
    public function containers(): HasMany
    {
        return $this->hasMany(MscShipmentContainer::class)->orderBy('id');
    }

    /** @return HasMany<MscShipmentProductVariant, $this> */
    public function productVariantLines(): HasMany
    {
        return $this->hasMany(MscShipmentProductVariant::class)->orderBy('id');
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
    public function scopeFilters(Builder $query, string $status = '', string $statusBl = '', string $categoryId = ''): void
    {
        foreach (['status' => $status, 'status_bl' => $statusBl, 'category_id' => $categoryId] as $column => $value) {
            if ($value !== '') {
                $query->where($column, $value);
            }
        }
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
