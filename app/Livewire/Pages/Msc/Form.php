<?php

namespace App\Livewire\Pages\Msc;

use App\Concerns\InteractsWithMscContext;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\MscShipmentContainer;
use App\Models\Product;
use App\Models\Vessel;
use App\Support\MscFields;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    /** @var array<int, array{name: string}> */
    public array $containerLines = [];

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
            if ($this->foreignTrade) {
                $this->category_id = $shipment->category_id;
                $this->status_bl = $shipment->status_bl?->value ?? '';
                $this->exp_number = $shipment->exp_number ?? '';
                $this->dua_intr = $shipment->dua_intr ?? '';
                $this->seabridge = $shipment->seabridge ?? '';
                $this->sencamer = $shipment->sencamer ?? '';
                $this->comex = $shipment->comex ?? '';
                $this->recognition_date = $shipment->recognition_date?->format('Y-m-d') ?? '';
                $this->receipt_date = $shipment->receipt_date?->format('Y-m-d') ?? '';
                $this->containerLines = $shipment->containers
                    ->map(fn (MscShipmentContainer $container): array => ['name' => $container->name])
                    ->values()
                    ->all();
            }
            $this->container_qty = (string) $shipment->container_qty;
            $this->unitsQtyOverridden = $shipment->units_qty !== null;
            $this->units_qty = $shipment->units_qty === null ? '' : (string) $shipment->units_qty;
            $this->free_time = (string) $shipment->free_time;
            $this->vessel_id = $shipment->vessel_id;
            $this->released = $shipment->released ?? '';
            $this->status = $shipment->status?->value ?? '';
        }
        if (! $this->unitsQtyOverridden) {
            $this->recalculateUnitsQty();
        }
    }

    public function updated(string $property, mixed $value = null): void
    {
        if (in_array($property, ['product_id', 'container_qty'], true)) {
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

    public function selectForeignTradeTab(string $tab): void
    {
        abort_unless(in_array($tab, ['phase-1', 'phase-2', 'phase-3', 'phase-4', 'notes'], true), 404);

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

    public function save(): void
    {
        Gate::authorize('manage-msc');
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
            foreach ($this->containerLines as $index => $line) {
                if (isset($line['name']) && is_string($line['name'])) {
                    $this->containerLines[$index]['name'] = trim($line['name']);
                }
            }
        }
        $rules = MscFields::rules($this->shipmentId, $this->foreignTrade, $this->bl_house);
        if ($this->foreignTrade) {
            $rules += [
                'containerLines' => ['array'],
                'containerLines.*' => ['array:name'],
                'containerLines.*.name' => ['nullable', 'string', 'max:255'],
            ];
        }
        $data = $this->validate($rules, attributes: MscFields::labels());
        $data['status'] = $this->status === '' ? null : $this->status;
        if ($this->foreignTrade) {
            $data['status_bl'] = $this->status_bl === '' ? null : $this->status_bl;
        }
        $data['units_qty'] = $this->unitsQtyOverridden ? (int) $this->units_qty : null;
        $unitsPerContainer = Product::query()->whereKey($data['product_id'])->value('units_per_container');
        $capacity = $unitsPerContainer === null ? null : (int) $unitsPerContainer * (int) $data['container_qty'];
        if ($capacity !== null && $capacity > 2147483647) {
            $this->addError('container_qty', 'La capacidad calculada supera el máximo permitido. Ajusta las unidades por contenedor o la cantidad.');

            return;
        }
        if ($data['units_qty'] !== null && $capacity !== null && $data['units_qty'] > $capacity) {
            $this->addError('units_qty', 'La cantidad real no puede superar la capacidad de los contenedores.');

            return;
        }
        $data['bl_house'] = $this->bl_house;
        $containerLines = [];
        if ($this->foreignTrade) {
            $containerLines = collect($data['containerLines'] ?? [])
                ->map(fn (array $line): array => ['name' => trim((string) ($line['name'] ?? ''))])
                ->filter(fn (array $line): bool => $line['name'] !== '')
                ->values()
                ->all();
            unset($data['containerLines']);
        }
        $shipment = DB::transaction(function () use ($data, $containerLines): MscShipment {
            $shipment = $this->shipmentId ? MscShipment::findOrFail($this->shipmentId) : new MscShipment;
            $shipment->fill($data);
            $shipment->save();
            if ($this->foreignTrade) {
                $shipment->containers()->delete();
                if ($containerLines !== []) {
                    $shipment->containers()->createMany($containerLines);
                }
            }

            return $shipment;
        });
        session()->flash('status', 'Registro MSC No. '.$shipment->id.' guardado correctamente.');
        $this->redirect($this->mscRoute('index'));
    }

    public function delete(): void
    {
        Gate::authorize('manage-msc');
        abort_if($this->shipmentId === null, 404);
        MscShipment::findOrFail($this->shipmentId)->delete();
        session()->flash('status', 'Registro MSC eliminado correctamente.');
        $this->redirect($this->mscRoute('index'));
    }

    public function render(): View
    {
        $title = $this->shipmentId ? 'MSC No. '.$this->shipmentId : 'Crear MSC';

        $products = Product::orderBy('name')->get();
        $selectedProduct = $products->firstWhere('id', $this->product_id);

        return view('pages.msc.form', [
            'factories' => Factory::orderBy('name')->get(),
            'products' => $products,
            'unitsPerContainer' => $selectedProduct?->units_per_container,
            'vessels' => Vessel::orderBy('name')->get(),
            'categories' => $this->foreignTrade ? Category::orderBy('name')->get() : collect(),
        ])
            ->layout('layouts.dashboard', ['title' => $title, 'heading' => $title]);
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
