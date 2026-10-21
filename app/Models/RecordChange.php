<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $subject_name
 * @property int|null $actor_id
 * @property string $actor_name
 * @property string $event
 * @property string $source
 * @property array<string, array{label: string, before: string, after: string}> $changes
 * @property Carbon $created_at
 */
class RecordChange extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('El historial no se puede eliminar.'));
    }
}
