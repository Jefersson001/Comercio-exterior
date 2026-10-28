<?php

namespace App\Http\Controllers;

use App\Models\MscShipment;
use App\Models\MscShipmentDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MscDocumentController extends Controller
{
    public function download(MscShipment $shipment, MscShipmentDocument $document): StreamedResponse
    {
        Gate::authorize('access-foreign-trade');

        abort_unless($document->msc_shipment_id === $shipment->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }
}
