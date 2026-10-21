<?php

namespace App\Livewire;

use App\Models\RecordChange;
use App\Support\HistorySubject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class RecordHistoryPanel extends Component
{
    use WithPagination;

    #[Locked]
    public string $subjectType = '';

    #[Locked]
    public int $subjectId = 0;

    #[Locked]
    public bool $foreignTrade = false;

    public function mount(string $subjectType, int $subjectId, bool $foreignTrade = false): void
    {
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->foreignTrade = $foreignTrade;
        $this->authorizeSubject();
    }

    private function authorizeSubject(): void
    {
        abort_unless(isset(HistorySubject::models()[$this->subjectType]), 404);
        Gate::authorize(HistorySubject::gate($this->subjectType, $this->foreignTrade));
        $class = HistorySubject::models()[$this->subjectType];
        abort_unless($class::whereKey($this->subjectId)->exists(), 404);
    }

    #[On('record-saved')]
    public function render(): View
    {
        $this->authorizeSubject();
        $international = $this->subjectType === 'msc' && ! $this->foreignTrade;
        $query = RecordChange::query()->where('subject_type', $this->subjectType)->where('subject_id', $this->subjectId);
        if ($international) {
            $query->where(function ($query): void {
                foreach (HistorySubject::internationalFields() as $field) {
                    $query->orWhereNotNull('changes->'.$field);
                }
            });
        }
        $entries = $query->orderByDesc('id')->paginate(10, pageName: 'historyPage');
        if ($international) {
            $entries->through(function (RecordChange $entry): RecordChange {
                $entry->changes = array_intersect_key($entry->changes, array_flip(HistorySubject::internationalFields()));

                return $entry;
            });
        }

        return view('livewire.record-history-panel', compact('entries'));
    }
}
