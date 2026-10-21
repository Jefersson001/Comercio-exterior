<?php

namespace App\Livewire\Pages\Home;

use App\Enums\MscBillOfLadingStatus;
use App\Enums\MscShipmentStatus;
use App\Models\Category;
use App\Models\Factory;
use App\Models\MscShipment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active, 403);
    }

    public function render(): View
    {
        $trade = Gate::allows('manage-msc');
        $exterior = Gate::allows('access-foreign-trade');
        $configuration = Gate::allows('manage-configuration');
        $users = Gate::allows('manage-users');
        $mscRoute = $exterior ? 'foreign-trade.msc.index' : 'msc.index';

        $shipmentCounts = $trade ? MscShipment::query()->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all() : [];
        $blCounts = $exterior ? MscShipment::query()->toBase()->selectRaw('status_bl, COUNT(*) AS total')->groupBy('status_bl')->pluck('total', 'status_bl')->all() : [];
        $shipmentCategories = $exterior ? MscShipment::query()->toBase()->selectRaw('category_id, COUNT(*) AS total')->groupBy('category_id')->pluck('total', 'category_id')->all() : [];
        $productCounts = $configuration ? Product::query()->toBase()->selectRaw('category_id, COUNT(*) AS total')->groupBy('category_id')->pluck('total', 'category_id')->all() : [];
        $categories = ($exterior || $configuration) ? Category::query()->orderBy('name')->get(['id', 'name']) : collect();
        $vessels = $configuration ? Vessel::query()->when($trade, fn ($query) => $query->withCount('mscShipments'))->orderBy('name')->get() : collect();
        $factories = $configuration ? Factory::query()->when($trade, fn ($query) => $query->withCount('mscShipments'))->orderBy('name')->get() : collect();
        $userCounts = $users ? User::query()->toBase()->selectRaw('is_active, COUNT(*) AS total')->groupBy('is_active')->pluck('total', 'is_active')->all() : [];

        $shipmentTotal = (int) array_sum($shipmentCounts);
        $productTotal = (int) array_sum($productCounts);
        $shipmentCategoryRows = $categories->map(fn ($category): array => [
            'label' => $category->name,
            'count' => (int) ($shipmentCategories[$category->id] ?? 0),
            'href' => route('foreign-trade.msc.index', ['category_id' => $category->id]),
        ])->all();
        $productCategoryRows = $categories->map(fn ($category): array => [
            'label' => $category->name,
            'count' => (int) ($productCounts[$category->id] ?? 0),
        ])->all();
        // Include unassigned records so each breakdown reconciles with its total.
        $shipmentCategoryRows[] = ['label' => 'Sin categoría', 'count' => (int) ($shipmentCategories[''] ?? 0)];
        $productCategoryRows[] = ['label' => 'Sin categoría', 'count' => (int) ($productCounts[''] ?? 0)];

        return view('pages.home.index', [
            'hasOverview' => $trade || $configuration || $users,
            'showTrade' => $trade,
            'showExterior' => $exterior,
            'showConfiguration' => $configuration,
            'showUsers' => $users,
            'mscRoute' => $mscRoute,
            'shipmentTotal' => $shipmentTotal,
            'shipmentStatuses' => $this->statusRows($shipmentCounts, MscShipmentStatus::options(), $mscRoute, 'status'),
            'blStatuses' => $this->statusRows($blCounts, MscBillOfLadingStatus::cases(), $mscRoute, 'status_bl'),
            'shipmentCategories' => $exterior ? $shipmentCategoryRows : [],
            'productTotal' => $productTotal,
            'productCategories' => $configuration ? $productCategoryRows : [],
            'categoryTotal' => $categories->count(),
            'vessels' => $vessels,
            'factories' => $factories,
            'shipmentsWithoutFactory' => $configuration && $trade ? MscShipment::whereNull('factory_id')->count() : 0,
            'shipmentsWithoutVessel' => $configuration && $trade ? MscShipment::whereNull('vessel_id')->count() : 0,
            'userTotal' => (int) array_sum($userCounts),
            'activeUsers' => (int) ($userCounts[1] ?? 0),
            'inactiveUsers' => (int) ($userCounts[0] ?? 0),
        ])->layout('layouts.dashboard', ['title' => 'Inicio', 'heading' => 'Inicio']);
    }

    /**
     * @param  array<string, int|string>  $counts
     * @param  list<MscShipmentStatus|MscBillOfLadingStatus>  $options
     * @return list<array{label: string, count: int, href?: string}>
     */
    private function statusRows(array $counts, array $options, string $route, string $field): array
    {
        $counts['Borrador'] = (int) ($counts['Borrador'] ?? 0) + (int) ($counts[''] ?? 0);
        unset($counts['']);
        $rows = [];
        foreach ($options as $option) {
            $rows[] = ['label' => $option->value, 'count' => (int) ($counts[$option->value] ?? 0), 'href' => route($route, [$field => $option->value])];
            unset($counts[$option->value]);
        }
        foreach ($counts as $label => $count) {
            $rows[] = ['label' => (string) $label, 'count' => (int) $count];
        }

        return $rows;
    }
}
