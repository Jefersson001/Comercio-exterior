<?php

namespace App\Models;

use App\Concerns\HasRecordHistory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['msc_shipment_id', 'name', 'file_path', 'original_name', 'mime_type', 'file_size'])]
class MscShipmentDocument extends Model
{
    use HasRecordHistory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /** @return BelongsTo<MscShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(MscShipment::class, 'msc_shipment_id');
    }

    public function formattedSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
