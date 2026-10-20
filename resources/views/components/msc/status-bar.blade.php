@props(['status' => null, 'interactive' => false])
@php
    $currentStatus = $status instanceof \App\Enums\MscShipmentStatus
        ? $status : \App\Enums\MscShipmentStatus::tryFrom((string) $status);
@endphp
<x-msc.status-track :statuses="\App\Enums\MscShipmentStatus::options()" :current-status="$currentStatus"
    :interactive="$interactive" field="status" label="Estado del embarque" caption="Estado seleccionado" />