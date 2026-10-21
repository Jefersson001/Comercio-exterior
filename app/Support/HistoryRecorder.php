<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\MscShipmentContainer;
use App\Models\MscShipmentProduct;
use App\Models\MscShipmentProductVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecordChange;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type HistoryField array{value: mixed, display: string}
 * @phpstan-type Snapshot array{name: string, fields: array<string, HistoryField>}
 */
class HistoryRecorder
{
    private bool $recording = false;

    /** @var array<string, array{type: string, id: int, before: Snapshot|null}> */
    private array $pending = [];

    /**
     * Group an aggregate or import into a single meaningful change per record.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function batch(callable $callback, ?string $source = null): mixed
    {
        if ($this->recording) {
            return $callback();
        }
        $this->recording = true;
        $this->pending = [];
        $actor = auth()->user();
        $actorName = $actor->full_name ?? 'Sistema';
        $source ??= $actor ? 'web' : 'system';
        try {
            return DB::transaction(function () use ($callback, $actor, $actorName, $source) {
                $result = $callback();
                $actorId = $actor && DB::table('users')->where('id', $actor->getKey())->exists() ? $actor->getKey() : null;
                foreach ($this->pending as $item) {
                    $after = $this->snapshot($item['type'], $item['id']);
                    $before = $item['before'];
                    $changes = [];
                    foreach (HistorySubject::fields($item['type']) as $field => $label) {
                        $old = $before['fields'][$field] ?? ['value' => null, 'display' => '—'];
                        $new = $after['fields'][$field] ?? ['value' => null, 'display' => '—'];
                        if ($old['value'] === $new['value'] || ($before === null && in_array($new['value'], [null, '', []], true))) {
                            continue;
                        }
                        $changes[$field] = ['label' => $label, 'before' => $old['display'], 'after' => $new['display']];
                    }
                    if (($before === null && $after === null) || ($before !== null && $after !== null && $changes === [])) {
                        continue;
                    }
                    (new RecordChange)->forceFill([
                        'subject_type' => $item['type'], 'subject_id' => $item['id'],
                        'subject_name' => $after['name'] ?? $before['name'],
                        'actor_id' => $actorId, 'actor_name' => $actorName,
                        'event' => $before === null ? 'created' : ($after === null ? 'deleted' : 'updated'),
                        'source' => $source, 'changes' => $changes,
                    ])->save();
                }

                return $result;
            });
        } finally {
            $this->recording = false;
            $this->pending = [];
        }
    }

    public function capture(Model $model, bool $deleting = false): void
    {
        $type = HistorySubject::type($model);
        if ($type !== null) {
            if ($model->exists) {
                $this->remember($type, (int) $model->getKey());
                if ($deleting) {
                    $dependents = match ($type) {
                        'category' => [['product', 'category_id'], ['msc', 'category_id']],
                        'factory' => [['msc', 'factory_id']],
                        'vessel' => [['msc', 'vessel_id']],
                        default => [],
                    };
                    foreach ($dependents as [$dependent, $foreignKey]) {
                        $class = HistorySubject::models()[$dependent];
                        foreach ($class::where($foreignKey, $model->getKey())->pluck('id') as $id) {
                            $this->remember($dependent, (int) $id);
                        }
                    }
                }
            }

            return;
        }
        $parent = match ($model::class) {
            ProductVariant::class => ['product', 'product_id'],
            MscShipmentProduct::class, MscShipmentContainer::class, MscShipmentProductVariant::class => ['msc', 'msc_shipment_id'],
            default => null,
        };
        if ($parent !== null) {
            foreach (array_unique([$model->getRawOriginal($parent[1]), $model->getAttribute($parent[1])]) as $id) {
                if ($id !== null) {
                    $this->remember($parent[0], (int) $id);
                }
            }
        }
    }

    public function created(Model $model): void
    {
        if (($type = HistorySubject::type($model)) !== null) {
            $key = $type.':'.$model->getKey();
            $this->pending[$key] ??= ['type' => $type, 'id' => (int) $model->getKey(), 'before' => null];
        }
    }

    private function remember(string $type, int $id): void
    {
        $key = $type.':'.$id;
        if (! isset($this->pending[$key])) {
            $model = HistorySubject::models()[$type]::query()->lockForUpdate()->find($id);
            if ($model !== null) {
                $this->pending[$key] = ['type' => $type, 'id' => $id, 'before' => $this->snapshot($type, $id)];
            }
        }
    }

    /** @return Snapshot|null */
    private function snapshot(string $type, int $id): ?array
    {
        $model = HistorySubject::models()[$type]::find($id);
        if ($model === null) {
            return null;
        }
        $fields = [];
        $references = ['product_id' => Product::class, 'category_id' => Category::class, 'factory_id' => Factory::class, 'vessel_id' => Vessel::class];
        foreach (HistorySubject::fields($type) as $field => $label) {
            if (! array_key_exists($field, $model->getAttributes())) {
                continue;
            }
            $value = $model->getRawOriginal($field);
            if (in_array($field, ['is_active', 'allows_multiple_products', 'access_international_trade', 'access_foreign_trade', 'access_configuration'], true)) {
                $value = (bool) $value;
                $display = $value ? 'Sí' : 'No';
            } elseif (isset($references[$field])) {
                $value = $value === null ? null : (int) $value;
                $display = $value === null ? '—' : '#'.$value.' · '.($references[$field]::find($value)->name ?? 'Registro eliminado');
            } else {
                $value = $value === null || $value === '' ? null : (string) $value;
                $display = $value === null || $value === '' ? '—' : $value;
            }
            $fields[$field] = ['value' => $value, 'display' => $display];
        }
        if ($model instanceof Product) {
            $fields['colors'] = $this->lines($model->variants()->pluck('color')->map(fn ($color): string => (string) $color)->all());
        }
        if ($model instanceof MscShipment) {
            $fields['products'] = $this->lines($model->productLines()->with('product')->get()->map(fn ($line): array => [
                'value' => [$line->product_id, $line->container_qty, $line->units_qty],
                'display' => ($line->product->name ?? '#'.$line->product_id).' · '.$line->container_qty.' contenedores · '.($line->units_qty ?? 'Automático').' unidades',
            ])->all());
            $fields['containers'] = $this->lines($model->containers()->pluck('name')->map(fn ($name): string => (string) $name)->all());
            $fields['colors'] = $this->lines($model->productVariantLines()->with('productVariant.product')->get()->map(fn ($line): array => [
                'value' => [$line->product_variant_id, $line->quantity],
                'display' => ($line->productVariant->product->name ?? '').' / '.($line->productVariant->color ?? '#'.$line->product_variant_id).' · '.$line->quantity.' unidades',
            ])->all());
        }

        $name = $model instanceof User ? $model->full_name : (string) ($model->getAttribute('name') ?? '#'.$id);

        return ['name' => $type === 'msc' ? 'MSC No. '.$id : $name, 'fields' => $fields];
    }

    /**
     * @param  array<array-key, string|array{value: mixed, display: string}>  $rows
     * @return HistoryField
     */
    private function lines(array $rows): array
    {
        $rows = array_map(fn ($row): array => is_array($row) ? $row : ['value' => $row, 'display' => $row], $rows);
        usort($rows, fn ($a, $b): int => strcmp(json_encode($a['value'], JSON_THROW_ON_ERROR), json_encode($b['value'], JSON_THROW_ON_ERROR)));

        return ['value' => array_column($rows, 'value'), 'display' => $rows === [] ? '—' : implode("\n", array_column($rows, 'display'))];
    }
}
