@php
    $batchPollInterval = $this->getBatchStatusPollInterval();
@endphp
<div
    @if (filled($batchPollInterval))
        wire:poll.{{ $batchPollInterval }}="refreshIngestionBatchStatus"
    @endif
>
    <x-filament-panels::page>
        {{ $this->content }}
    </x-filament-panels::page>
</div>
