<?php

namespace App\Concerns;

use App\Support\HistoryRecorder;

trait HasRecordHistory
{
    public static function bootHasRecordHistory(): void
    {
        static::saving(fn ($model) => app(HistoryRecorder::class)->capture($model));
        static::deleting(fn ($model) => app(HistoryRecorder::class)->capture($model, deleting: true));
        static::created(fn ($model) => app(HistoryRecorder::class)->created($model));
    }

    public function save(array $options = []): bool
    {
        return app(HistoryRecorder::class)->batch(fn () => parent::save($options));
    }

    public function delete(): ?bool
    {
        return app(HistoryRecorder::class)->batch(fn () => parent::delete());
    }
}
