@php
    $p = $this->getOcrPayload();
@endphp
<x-filament-panels::page>
    @if ($p === null)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('No quotation review draft or payload yet for this batch.') }}
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Extraction status') }}
            </x-slot>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('OCR') }}</dt>
                <dd>{{ data_get($p, 'extraction_status.ocr', '—') }}</dd>
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('Normalization') }}</dt>
                <dd>{{ data_get($p, 'extraction_status.normalization', '—') }}</dd>
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('Provider') }}</dt>
                <dd>{{ $p['ocr_provider'] ?? '—' }}</dd>
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('Processor type') }}</dt>
                <dd>{{ $p['ocr_processor_type'] ?? '—' }}</dd>
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('Captured at') }}</dt>
                <dd>{{ $p['ocr_captured_at'] ?? '—' }}</dd>
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('Source path (primary)') }}</dt>
                <dd class="break-all">{{ $p['source_file_path'] ?? '—' }}</dd>
            </dl>
            @if (filled($p['ocr_error'] ?? null))
                <p class="mt-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-800 dark:bg-danger-500/10 dark:text-danger-200">
                    {{ $p['ocr_error'] }}
                </p>
            @endif
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('Source files') }}
            </x-slot>
            <pre class="max-h-48 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($p['ocr_source_files'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('raw_full_text') }}
            </x-slot>
            <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ $p['raw_full_text'] ?? '' }}</pre>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('raw_pages (JSON)') }}
            </x-slot>
            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($p['raw_pages'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('raw_blocks (JSON)') }}
            </x-slot>
            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($p['raw_blocks'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                {{ __('raw_tables (JSON)') }}
            </x-slot>
            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($p['raw_tables'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
