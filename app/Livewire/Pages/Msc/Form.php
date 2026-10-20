<?php

namespace App\Livewire\Pages\Msc;

use App\Actions\Msc\PrepareShipmentProducts;
use App\Concerns\InteractsWithMscContext;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\MscShipmentContainer;
use App\Models\MscShipmentProductVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vessel;
use App\Support\MscFields;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithMscContext;

    #[Locked]
    public ?int $shipmentId = null;

    public string $etd = '';

    public string $eta = '';

    public string $bl_master = '';

    public string $bl_house = '';

    public ?int $factory_id = null;

    public ?int $product_id = null;

    public ?int $category_id = null;

    public string $container_qty = '';

    public string $units_qty = '';

    #[Locked]
    public bool $unitsQtyOverridden = false;

    public string $free_time = '';

    public ?int $vessel_id = null;

    public string $released = '';

    public string $status = '';

    public string $status_bl = '';

    public string $activeForeignTradeTab = 'phase-1';

    public string $exp_number = '';

    public string $dua_intr = '';

    public string $seabridge = '';

    public string $sencamer = '';

    public string $comex = '';

    public string $recognition_date = '';

    public string $receipt_date = '';

    public string $shipment_year = '';

    public string $etd_vnzl = '';

    public string $eta_vnzl = '';

    public string $port = '';

    public string $trasegado = '';

    public string $in_bond_date = '';

    public string $free_days = '';

    public string $delay_date = '';

    public string $legal_abandonment_date = '';

    public string $registration_date = '';

    public string $phase_two_recognition_date = '';

    public string $validation_date = '';

    public string $plant_receipt_date = '';

    public string $location = '';

    public string $return_date = '';

    public string $original_docs_receipt_date = '';

    public string $aa_instructions_sent_date = '';

    public string $bl_release_date = '';

    public string $sencamer_request_date = '';

    public string $sencamer_receipt_date = '';

    public string $comex_request_date = '';

    public string $comex_receipt_date = '';

    public string $customs_agent_status = '';

    public string $customs_agent_observation = '';

    /** @var array<int, array{name: string}> */
    public array $containerLines = [];

    /** @var array<int, array{product_variant_id: int|string|null, quantity: int|string|null}> */
    public array $productVariantLines = [];

    /** @var array<int, array{product_id: int|string|null, container_qty: int|string|null, units_qty: int|string|null}> */
    public array $productLines = [];

    /** @var array<int, bool> */
    #[Locked]
    public array $lineUnitsOverridden = [];

    public ?int $pendingCategoryId = null;

    #[Locked]
    public bool $confirmingCategoryChange = false;

    #[Locked]
    public bool $hasMultipleProducts = false;

    public function boot(): void
    {
        Gate::authorize('manage-msc');
    }

    public function mount(?MscShipment $shipment = null, bool $foreignTrade = false): void
    {
        $this->initializeMscContext($foreignTrade);
        if ($shipment?->exists) {
            $this->shipmentId = $shipment->id;
            $this->etd = $shipment->etd->format('Y-m-d');
            $this->eta = $shipment->eta->format('Y-m-d');
            $this->bl_master = $shipment->bl_master ?? '';
            $this->bl_house = $shipment->bl_house ?? '';
            $this->factory_id = $shipment->factory_id;
            $this->product_id = $shipment->product_id;
            $this->hasMultipleProducts = $shipment->productLines()->count() > 1;
            $this->productLines = $shipment->productLines->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'container_qty' => (string) $line->container_qty,
                'units_qty' => $line->units_qty === null ? '' : (string) $line->units_qty,
            ])->all();
            if ($this->productLines === []) {
                $this->productLines = [['product_id' => $shipment->product_id, 'container_qty' => (string) $shipment->container_qty, 'units_qty' => $shipment->units_qty === null ? '' : (string) $shipment->units_qty]];
            }
            if ($this->foreignTrade) {
                $this->category_id = $shipment->category_id;
                $this->pendingCategoryId = $this->category_id;
                $this->status_bl = $shipment->status_bl?->value ?? '';
                $this->exp_number = $shipment->exp_number ?? '';
                $this->dua_intr = $shipment->dua_intr ?? '';
                $this->seabridge = $shipment->seabridge ?? '';
                $this->sencamer = $shipment->sencamer ?? '';
                $this->comex = $shipment->comex ?? '';
                $this->recognition_date = $shipment->recognition_date?->format('Y-m-d') ?? '';
                $this->receipt_date = $shipment->receipt_date?->format('Y-m-d') ?? '';
                $this->shipment_year = $shipment->shipment_year ?? '';
                $this->etd_vnzl = $shipment->etd_vnzl?->format('Y-m-d') ?? '';
                $this->eta_vnzl = $shipment->eta_vnzl?->format('Y-m-d') ?? '';
                $this->port = $shipment->port ?? '';
                $this->trasegado = $shipment->trasegado ?? '';
                $this->in_bond_date = $shipment->in_bond_date?->format('Y-m-d') ?? '';
                $this->free_days = $shipment->free_days === null ? '' : (string) $shipment->free_days;
                $this->delay_date = $shipment->delay_date?->format('Y-m-d') ?? '';
                $this->legal_abandonment_date = $shipment->legal_abandonment_date?->format('Y-m-d') ?? '';
                $this->registration_date = $shipment->registration_date?->format('Y-m-d') ?? '';
                $this->phase_two_recognition_date = $shipment->phase_two_recognition_date?->format('Y-m-d') ?? '';
                $this->validation_date = $shipment->validation_date?->format('Y-m-d') ?? '';
                $this->plant_receipt_date = $shipment->plant_receipt_date?->format('Y-m-d') ?? '';
                $this->location = $shipment->location ?? '';
                $this->return_date = $shipment->return_date?->format('Y-m-d') ?? '';
                $this->original_docs_receipt_date = $shipment->original_docs_receipt_date?->format('Y-m-d') ?? '';
                $this->aa_instructions_sent_date = $shipment->aa_instructions_sent_date?->format('Y-m-d') ?? '';
                $this->bl_release_date = $shipment->bl_release_date?->format('Y-m-d') ?? '';
                $this->sencamer_request_date = $shipment->sencamer_request_date?->format('Y-m-d') ?? '';
                $this->sencamer_receipt_date = $shipment->sencamer_receipt_date?->format('Y-m-d') ?? '';
                $this->comex_request_date = $shipment->comex_request_date?->format('Y-m-d') ?? '';
                $this->comex_receipt_date = $shipment->comex_receipt_date?->format('Y-m-d') ?? '';
                $this->customs_agent_status = $shipment->customs_agent_status ?? '';
                $this->customs_agent_observation = $shipment->customs_agent_observation ?? '';
                $this->containerLines = $shipment->containers
                    ->map(fn (MscShipmentContainer $container): array => ['name' => $container->name])
                    ->values()
                    ->all();
                $this->productVariantLines = $shipment->productVariantLines
                    ->map(fn (MscShipmentProductVariant $line): array => [
                        'product_variant_id' => $line->product_variant_id,
                        'quantity' => (string) $line->quantity,
                    ])
                    ->values()
                    ->all();
            }
            $this->container_qty = (string) $shipment->container_qty;
            $this->unitsQtyOverridden = $shipment->units_qty !== null;
            $this->units_qty = $shipment->units_qty === null ? '' : (string) $shipment->units_qty;
            if (! $this->foreignTrade && $shipment->productLines->count() === 1) {
                $lineUnits = $shipment->productLines->first()->units_qty;
                $this->unitsQtyOverridden = $lineUnits !== null;
                $this->units_qty = $lineUnits === null ? '' : (string) $lineUnits;
            }
            $this->free_time = (string) $shipment->free_time;
            $this->vessel_id = $shipment->vessel_id;
            $this->released = $shipment->released ?? '';
            $this->status = $shipment->status?->value ?? '';
        }
        if (! $this->unitsQtyOverridden) {
            $this->recalculateUnitsQty();
        }
        $this->initializeProductLineQuantities();
    }

    public function updated(string $property, mixed $value = null): void
    {
        if ($this->foreignTrade && $property === 'pendingCategoryId') {
            $this->confirmingCategoryChange = $this->pendingCategoryId !== $this->category_id && $this->productLines !== [];
            if (! $this->confirmingCategoryChange) {
                $this->applyCategoryChange();
            }

            return;
        }
        if ($this->foreignTrade && $property === 'productLines') {
            $this->initializeProductLineQuantities();

            return;
        }
        if ($this->foreignTrade && preg_match('/^productLines\.(\d+)\.(product_id|container_qty|units_qty)$/', $property, $matches)) {
            $index = (int) $matches[1];
            if (! isset($this->productLines[$index])) {
                return;
            }
            if ($matches[2] === 'product_id') {
                $this->pruneProductVariants();
            }
            if ($matches[2] === 'units_qty') {
                $this->lineUnitsOverridden[$index] = trim((string) $value) !== '';
                if ($this->lineUnitsOverridden[$index]) {
                    $this->recalculateProductLineContainers($index);
                } else {
                    $this->recalculateProductLineUnits($index);
                }
            } else {
                $this->lineUnitsOverridden[$index] = false;
                $this->recalculateProductLineUnits($index);
            }

            return;
        }
        if (in_array($property, ['product_id', 'container_qty'], true)) {
            if ($property === 'product_id' && $this->foreignTrade) {
                $this->productVariantLines = [];
            }

            $this->unitsQtyOverridden = false;
            $this->recalculateUnitsQty();

            return;
        }

        if ($property === 'units_qty') {
            $this->unitsQtyOverridden = trim((string) $value) !== '';
            if ($this->unitsQtyOverridden) {
                $this->recalculateContainerQtyFromUnits();
            } else {
                $this->recalculateUnitsQty();
            }
        }
    }

    public function applyCategoryChange(): void
    {
        abort_unless($this->foreignTrade, 404);
        $this->validate(['pendingCategoryId' => ['nullable', 'integer', 'exists:categories,id']]);
        if ($this->pendingCategoryId !== $this->category_id) {
            $ids = Product::where('category_id', $this->pendingCategoryId)->pluck('id')->all();
            $retainedLines = [];
            $retainedOverrides = [];
            foreach ($this->productLines as $index => $line) {
                if (in_array((int) ($line['product_id'] ?? 0), $ids, true)) {
                    $retainedLines[] = $line;
                    $retainedOverrides[] = $this->lineUnitsOverridden[$index] ?? true;
                }
            }
            $this->productLines = $retainedLines;
            $this->lineUnitsOverridden = $retainedOverrides;
            $this->category_id = $this->pendingCategoryId;
            $this->productVariantLines = [];
        }
        $this->confirmingCategoryChange = false;
        $this->resetValidation();
    }

    public function cancelCategoryChange(): void
    {
        $this->pendingCategoryId = $this->category_id;
        $this->confirmingCategoryChange = false;
    }

    public function addProductLine(): void
    {
        abort_unless($this->foreignTrade, 404);
        $category = Category::find($this->category_id);
        if ($category === null || (! $category->allows_multiple_products && $this->productLines !== []) || count($this->productLines) >= 100) {
            return;
        }
        $this->productLines[] = ['product_id' => null, 'container_qty' => '1', 'units_qty' => ''];
        $this->lineUnitsOverridden[] = false;
    }

    public function removeProductLine(int $index): void
    {
        abort_unless($this->foreignTrade, 404);
        unset($this->productLines[$index]);
        unset($this->lineUnitsOverridden[$index]);
        $this->productLines = array_values($this->productLines);
        $this->lineUnitsOverridden = array_values($this->lineUnitsOverridden);
        $this->pruneProductVariants();
    }

    private function pruneProductVariants(): void
    {
        $variantIds = ProductVariant::whereIn('product_id', array_column($this->productLines, 'product_id'))->pluck('id')->all();
        $this->productVariantLines = array_values(array_filter($this->productVariantLines, fn (array $line): bool => in_array((int) ($line['product_variant_id'] ?? 0), $variantIds, true)));
    }

    public function selectForeignTradeTab(string $tab): void
    {
        abort_unless(in_array($tab, ['phase-1', 'phase-2', 'phase-3', 'notes'], true), 404);

        $this->activeForeignTradeTab = $tab;
    }

    public function addContainerLine(): void
    {
        abort_unless($this->foreignTrade, 404);

        $this->containerLines[] = ['name' => ''];
    }

    public function removeContainerLine(int $index): void
    {
        abort_unless($this->foreignTrade, 404);

        unset($this->containerLines[$index]);
        $this->containerLines = array_values($this->containerLines);
    }

    public function addProductVariantLine(): void
    {
        abort_unless($this->foreignTrade, 404);
        if (! $this->variantsEnabled()) {
            return;
        }

        $this->productVariantLines[] = ['product_variant_id' => null, 'quantity' => '0'];
    }

    public function removeProductVariantLine(int $index): void
    {
        abort_unless($this->foreignTrade, 404);
        if (! $this->variantsEnabled()) {
            return;
        }

        unset($this->productVariantLines[$index]);
        $this->productVariantLines = array_values($this->productVariantLines);
    }

    public function save(): void
    {
        $this->authorizeMscContext();
        foreach (['bl_master', 'bl_house', 'released', 'units_qty', 'status'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
        if ($this->foreignTrade) {
            $this->status_bl = trim($this->status_bl);
            foreach (['exp_number', 'dua_intr', 'seabridge', 'sencamer', 'comex'] as $field) {
                $this->{$field} = trim($this->{$field});
            }
            foreach (['recognition_date', 'receipt_date'] as $field) {
                $this->{$field} = trim($this->{$field});
            }
            foreach (['shipment_year', 'etd_vnzl', 'eta_vnzl', 'port', 'trasegado', 'in_bond_date', 'free_days', 'delay_date', 'legal_abandonment_date', 'registration_date', 'phase_two_recognition_date', 'validation_date', 'plant_receipt_date', 'location', 'return_date', 'original_docs_receipt_date', 'aa_instructions_sent_date', 'bl_release_date', 'sencamer_request_date', 'sencamer_receipt_date', 'comex_request_date', 'comex_receipt_date', 'customs_agent_status', 'customs_agent_observation'] as $field) {
                $this->{$field} = trim($this->{$field});
            }
            foreach ($this->containerLines as $index => $line) {
                if (isset($line['name']) && is_string($line['name'])) {
                    $this->containerLines[$index]['name'] = trim($line['name']);
                }
            }
            foreach ($this->productVariantLines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }
                if (isset($line['product_variant_id']) && is_string($line['product_variant_id'])) {
                    $this->productVariantLines[$index]['product_variant_id'] = trim($line['product_variant_id']);
                }
                if (isset($line['quantity']) && is_string($line['quantity'])) {
                    $this->productVariantLines[$index]['quantity'] = trim($line['quantity']);
                }
            }
            $this->productVariantLines = array_values(array_filter(
                $this->productVariantLines,
                fn (mixed $line): bool => ! is_array($line)
                    || ($line['product_variant_id'] ?? '') !== ''
                    || ! in_array($line['quantity'] ?? '', ['', '0', 0, null], true),
            ));
        }
        $rules = MscFields::rules($this->shipmentId, $this->foreignTrade, $this->bl_house);
        $variantsEnabled = $this->variantsEnabled();
        $prepared = null;
        if ($this->foreignTrade) {
            if ($this->confirmingCategoryChange) {
                $this->addError('category_id', 'Confirma o cancela el cambio de categoría antes de guardar.');

                return;
            }
            $linesToSave = $this->productLines;
            foreach ($linesToSave as $index => $line) {
                if (($this->lineUnitsOverridden[$index] ?? true) === false) {
                    $linesToSave[$index]['units_qty'] = '';
                }
            }
            $prepared = app(PrepareShipmentProducts::class)->handle($this->category_id, $linesToSave);
            unset($rules['product_id'], $rules['container_qty'], $rules['units_qty']);
        } elseif ($this->hasMultipleProducts) {
            unset($rules['product_id'], $rules['container_qty'], $rules['units_qty']);
        }
        if ($this->foreignTrade) {
            $rules += [
                'containerLines' => ['array'],
                'containerLines.*' => ['array:name'],
                'containerLines.*.name' => ['nullable', 'string', 'max:255'],
                'productVariantLines' => ['array'],
                'productVariantLines.*' => ['array:product_variant_id,quantity'],
                'productVariantLines.*.product_variant_id' => [
                    'required',
                    'integer',
                    Rule::exists(ProductVariant::class, 'id')->whereIn('product_id', array_column($prepared['lines'], 'product_id')),
                    'distinct',
                ],
                'productVariantLines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            ];
        }
        if (! $variantsEnabled) {
            foreach (array_keys($rules) as $field) {
                if ($field === 'productVariantLines' || str_starts_with($field, 'productVariantLines.')) {
                    unset($rules[$field]);
                }
            }
        }
        $data = $this->validate($rules, attributes: MscFields::labels());
        $data['status'] = $this->status === '' ? null : $this->status;
        if ($this->foreignTrade) {
            $data['status_bl'] = $this->status_bl === '' ? null : $this->status_bl;
        }
        if ($this->foreignTrade) {
            $data['product_id'] = $prepared['lines'][0]['product_id'];
            $data['container_qty'] = $prepared['container_qty'];
            $data['units_qty'] = $prepared['units_qty'];
        } elseif (! $this->hasMultipleProducts) {
            $data['units_qty'] = $this->unitsQtyOverridden ? (int) $this->units_qty : null;
        }
        $unitsPerContainer = $this->foreignTrade || $this->hasMultipleProducts ? null : Product::query()->whereKey($data['product_id'])->value('units_per_container');
        $capacity = $unitsPerContainer === null ? null : (int) $unitsPerContainer * (int) $data['container_qty'];
        if ($capacity !== null && $capacity > 2147483647) {
            $this->addError('container_qty', 'La capacidad calculada supera el máximo permitido. Ajusta las unidades por contenedor o la cantidad.');

            return;
        }
        if (($data['units_qty'] ?? null) !== null && $capacity !== null && $data['units_qty'] > $capacity) {
            $this->addError('units_qty', 'La cantidad real no puede superar la capacidad de los contenedores.');

            return;
        }
        $data['bl_house'] = $this->bl_house;
        $containerLines = [];
        $productVariantLines = [];
        if ($this->foreignTrade) {
            $containerLines = collect($data['containerLines'] ?? [])
                ->map(fn (array $line): array => ['name' => trim((string) ($line['name'] ?? ''))])
                ->filter(fn (array $line): bool => $line['name'] !== '')
                ->values()
                ->all();
            unset($data['containerLines']);
            $productVariantLines = collect($data['productVariantLines'] ?? [])
                ->map(fn (array $line): array => [
                    'product_variant_id' => (int) $line['product_variant_id'],
                    'quantity' => ($line['quantity'] ?? '') === '' ? 0 : (int) $line['quantity'],
                ])
                ->values()
                ->all();
            unset($data['productVariantLines']);
        }
        $shipment = DB::transaction(function () use ($data, $containerLines, $productVariantLines, $prepared, $variantsEnabled): MscShipment {
            $shipment = $this->shipmentId ? MscShipment::query()->lockForUpdate()->findOrFail($this->shipmentId) : new MscShipment;
            $multipleProducts = $shipment->exists && $shipment->productLines()->count() > 1;
            if (! $this->foreignTrade && $multipleProducts) {
                unset($data['product_id'], $data['container_qty'], $data['units_qty']);
            }
            $shipment->fill($data);
            $shipment->save();
            if ($this->foreignTrade) {
                $shipment->productLines()->delete();
                $shipment->productLines()->createMany($prepared['lines']);
            } elseif (! $this->hasMultipleProducts && ! $multipleProducts) {
                $shipment->syncSingleProductLine();
            }
            if ($this->foreignTrade) {
                $shipment->containers()->delete();
                if ($containerLines !== []) {
                    $shipment->containers()->createMany($containerLines);
                }
                if ($variantsEnabled) {
                    $shipment->productVariantLines()->delete();
                    if ($productVariantLines !== []) {
                        $shipment->productVariantLines()->createMany($productVariantLines);
                    }
                }
            }

            return $shipment;
        });
        session()->flash('status', 'Registro MSC No. '.$shipment->id.' guardado correctamente.');
        $this->redirect($this->mscRoute('index'));
    }

    public function delete(): void
    {
        $this->authorizeMscContext();
        abort_if($this->shipmentId === null, 404);
        MscShipment::findOrFail($this->shipmentId)->delete();
        session()->flash('status', 'Registro MSC eliminado correctamente.');
        $this->redirect($this->mscRoute('index'));
    }

    public function render(): View
    {
        $title = $this->shipmentId ? 'MSC No. '.$this->shipmentId : 'Crear MSC';

        $products = Product::query()->when($this->foreignTrade, fn ($query) => $this->category_id === null ? $query->whereRaw('1 = 0') : $query->where('category_id', $this->category_id))->orderBy('name')->get();
        $selectedProduct = $products->firstWhere('id', $this->product_id);
        $selectedIds = array_column($this->productLines, 'product_id');
        $lineProducts = Product::whereIn('id', $selectedIds)->get()->keyBy('id');
        $totalContainers = 0;
        $totalUnits = 0;
        $unknownUnits = false;
        foreach ($this->productLines as $line) {
            $count = is_numeric($line['container_qty'] ?? null) ? max(0, (int) $line['container_qty']) : 0;
            $perContainer = $lineProducts->get($line['product_id'] ?? null)?->units_per_container;
            $actual = $line['units_qty'] ?? '';
            $totalContainers += $count;
            $unknownUnits = $unknownUnits || ($actual === '' && $perContainer === null);
            $totalUnits += is_numeric($actual) ? (int) $actual : $count * ($perContainer ?? 0);
        }

        return view('pages.msc.form', [
            'factories' => Factory::orderBy('name')->get(),
            'products' => $products,
            'lineProducts' => $lineProducts,
            'totalContainers' => $totalContainers,
            'totalLineUnits' => $unknownUnits ? null : $totalUnits,
            'selectedCategory' => $this->foreignTrade ? Category::find($this->category_id) : null,
            'variantsEnabled' => $this->variantsEnabled(),
            'selectedProduct' => $selectedProduct,
            'unitsPerContainer' => $selectedProduct?->units_per_container,
            'vessels' => Vessel::orderBy('name')->get(),
            'categories' => $this->foreignTrade ? Category::orderBy('name')->get() : collect(),
            'productVariants' => $this->foreignTrade && $this->variantsEnabled() ? ProductVariant::with('product')->whereIn('product_id', $selectedIds)->get() : collect(),
        ])
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
    }

    private function variantsEnabled(): bool
    {
        return ! (Category::find($this->category_id)?->allows_multiple_products ?? false);
    }

    private function initializeProductLineQuantities(): void
    {
        $this->lineUnitsOverridden = [];
        foreach ($this->productLines as $index => $line) {
            $this->lineUnitsOverridden[$index] = ! in_array($line['units_qty'] ?? null, ['', null], true);
            if (! $this->lineUnitsOverridden[$index]) {
                $this->recalculateProductLineUnits($index);
            }
        }
    }

    private function recalculateProductLineUnits(int $index): void
    {
        $line = $this->productLines[$index];
        $unitsPerContainer = empty($line['product_id']) ? null : Product::whereKey($line['product_id'])->value('units_per_container');
        $count = (string) ($line['container_qty'] ?? '');
        if ($unitsPerContainer === null || ! ctype_digit($count) || (float) $count > 2147483647) {
            $this->productLines[$index]['units_qty'] = '';

            return;
        }
        $units = (int) $unitsPerContainer * (int) $count;
        $this->productLines[$index]['units_qty'] = $units > 2147483647 ? '' : (string) $units;
    }

    private function recalculateProductLineContainers(int $index): void
    {
        $line = $this->productLines[$index];
        $units = trim((string) ($line['units_qty'] ?? ''));
        if (! ctype_digit($units) || (float) $units < 1 || (float) $units > 2147483647 || empty($line['product_id'])) {
            return;
        }
        $unitsPerContainer = Product::whereKey($line['product_id'])->value('units_per_container');
        if ($unitsPerContainer !== null && (int) $unitsPerContainer > 0) {
            $this->productLines[$index]['container_qty'] = (string) intdiv((int) $units + (int) $unitsPerContainer - 1, (int) $unitsPerContainer);
        }
    }

    private function recalculateUnitsQty(): void
    {
        $unitsPerContainer = $this->product_id === null
            ? null
            : Product::query()->whereKey($this->product_id)->value('units_per_container');

        if ($unitsPerContainer === null || ! ctype_digit($this->container_qty)) {
            $this->units_qty = '';

            return;
        }

        $calculatedUnits = (int) $unitsPerContainer * (int) $this->container_qty;
        $this->units_qty = $calculatedUnits > 2147483647 ? '' : (string) $calculatedUnits;
    }

    private function recalculateContainerQtyFromUnits(): void
    {
        if (! ctype_digit($this->units_qty) || (int) $this->units_qty < 1 || $this->product_id === null) {
            return;
        }

        $unitsPerContainer = Product::query()->whereKey($this->product_id)->value('units_per_container');
        if ($unitsPerContainer === null) {
            return;
        }

        $this->container_qty = (string) intdiv((int) $this->units_qty + (int) $unitsPerContainer - 1, (int) $unitsPerContainer);
    }
}
