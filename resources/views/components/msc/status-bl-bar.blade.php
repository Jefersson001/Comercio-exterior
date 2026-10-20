@props(['status' => null, 'interactive' => false])
@php
    $currentStatus = $status instanceof \App\Enums\MscBillOfLadingStatus
        ? $status : \App\Enums\MscBillOfLadingStatus::tryFrom((string) $status);
@endphp
<x-msc.status-track :statuses="\App\Enums\MscBillOfLadingStatus::cases()" :current-status="$currentStatus"
    :interactive="$interactive" field="status_bl" label="Status BL" caption="Status BL seleccionado" minimum="180px" />