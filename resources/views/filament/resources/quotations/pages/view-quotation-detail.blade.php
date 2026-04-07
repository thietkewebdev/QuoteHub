@php
    use App\Support\Locale\VietnamesePresentation;
    use App\Support\Quotation\QuotationLinePresentation;
    /** @var \App\Models\Quotation $q */
    /** @var array{sub: float, vat: float, total: float} $financial */
    /** @var list<array{key: string, label: string, done: bool, at: ?\Illuminate\Support\Carbon, href: ?string}> $processTimeline */
    $firstOpenTimelineIdx = null;
    foreach ($processTimeline as $i => $_timelineStep) {
        if (! $_timelineStep['done']) {
            $firstOpenTimelineIdx = $i;
            break;
        }
    }
@endphp

<div class="fi-quotation-detail-layout w-full min-w-0 max-w-none space-y-6">
    {{-- Header: 3 columns on md+ --}}
    <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.06] ring-1 ring-gray-950/5 transition-shadow duration-200 hover:shadow-lg hover:shadow-gray-900/[0.08] dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3 md:gap-8">
            <div class="min-w-0 space-y-2 sm:space-y-2.5">
                <h1 class="text-left text-xl font-bold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                    {{ __('Quotation #:id', ['id' => $q->getKey()]) }}
                </h1>
                <p class="text-left text-sm text-gray-600 sm:text-base dark:text-gray-400">
                    {{ filled($q->supplier_name) ? $q->supplier_name : '—' }}
                </p>
                <div class="flex flex-wrap items-center gap-2 pt-0.5">
                    <x-filament::badge :color="$q->approvalStatusColor()">
                        {{ $q->approvalStatusLabel() }}
                    </x-filament::badge>
                    @if (filled($q->supplier_quote_number))
                        <span class="text-left text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                            {{ __('Supplier quote #') }}: {{ $q->supplier_quote_number }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="min-w-0 space-y-3 border-t border-gray-100 pt-4 text-left md:border-t-0 md:border-l md:border-gray-100 md:pl-8 md:pt-0 dark:border-white/10">
                <dl class="space-y-2.5 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Quote date') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ $q->quote_date?->format(VietnamesePresentation::DATE_FORMAT) ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Approved at') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ $q->approved_at?->format(VietnamesePresentation::DATETIME_FORMAT) ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="flex min-w-0 flex-col items-stretch gap-3 border-t border-gray-100 pt-4 text-left md:border-t-0 md:border-l md:border-gray-100 md:pl-8 md:pt-0 dark:border-white/10 md:items-end md:text-right">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Total amount') }}
                    </p>
                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-emerald-600 sm:text-3xl dark:text-emerald-400">
                        {{ VietnamesePresentation::vnd($financial['total']) ?? '—' }}
                    </p>
                </div>
                <div class="flex w-full flex-col gap-2 md:items-end">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-start md:justify-end">
                    <x-filament::button
                        class="w-full justify-center !font-semibold shadow-sm transition-all duration-150 hover:!shadow-md active:scale-[0.98] sm:w-auto"
                        color="success"
                        size="sm"
                        wire:click="mountAction('createPurchaseOrder')"
                        :disabled="! ($q->approved_at && $q->items->isNotEmpty())"
                    >
                        {{ __('Create purchase order') }}
                    </x-filament::button>
                    @if ($q->approved_at)
                        <x-filament::button
                            class="w-full justify-center !ring-1 !ring-gray-300/90 transition-all duration-150 hover:!bg-gray-50 active:scale-[0.98] dark:!ring-white/20 dark:hover:!bg-white/5 sm:w-auto"
                            color="gray"
                            outlined
                            size="sm"
                            wire:click="mountAction('cloneToManualDraft')"
                        >
                            {{ __('Duplicate') }}
                        </x-filament::button>
                    @endif
                    <x-filament::button
                        class="w-full justify-center !ring-1 !ring-gray-300/90 transition-all duration-150 hover:!bg-gray-50 active:scale-[0.98] dark:!ring-white/20 dark:hover:!bg-white/5 sm:w-auto"
                        color="gray"
                        outlined
                        size="sm"
                        tag="a"
                        :href="$editUrl"
                    >
                        {{ __('Edit') }}
                    </x-filament::button>
                    <x-filament::button
                        class="w-full justify-center !ring-1 !ring-danger-500/25 transition-all duration-150 hover:!bg-danger-50 hover:!ring-danger-500/40 active:scale-[0.98] dark:hover:!bg-danger-500/10 sm:w-auto"
                        color="danger"
                        outlined
                        size="sm"
                        wire:click="mountAction('delete')"
                    >
                        {{ __('Delete') }}
                    </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Status timeline (ERP-style) --}}
    <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-5">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('Process') }}
        </h2>
        <div class="flex flex-col divide-y divide-gray-100 dark:divide-white/10 sm:flex-row sm:divide-x sm:divide-y-0">
            @foreach ($processTimeline as $i => $step)
                @php
                    $isCurrent = $firstOpenTimelineIdx !== null && $i === $firstOpenTimelineIdx;
                @endphp
                <div class="flex flex-1 flex-row items-center gap-4 py-4 first:pt-0 last:pb-0 sm:flex-col sm:items-center sm:px-4 sm:py-0 sm:text-center sm:first:pl-0 sm:last:pr-0">
                    <div
                        @class([
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                            'border-emerald-500 bg-emerald-500 text-white dark:border-emerald-500 dark:bg-emerald-600' => $step['done'],
                            'border-amber-400 bg-amber-50 text-amber-800 dark:border-amber-500/60 dark:bg-amber-500/15 dark:text-amber-200' => ! $step['done'] && $isCurrent,
                            'border-gray-200 bg-white text-gray-400 dark:border-white/20 dark:bg-gray-900 dark:text-gray-500' => ! $step['done'] && ! $isCurrent,
                        ])
                    >
                        @if ($step['done'])
                            <svg class="block h-5 w-5 shrink-0" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" style="max-width: 20px; max-height: 20px">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @else
                            <span class="text-xs font-bold tabular-nums">{{ $i + 1 }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 text-left sm:text-center">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $step['label'] }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            @if ($step['at'] !== null)
                                {{ $step['at']->format(VietnamesePresentation::DATETIME_FORMAT) }}
                            @else
                                {{ $step['done'] ? '—' : __('Pending') }}
                            @endif
                        </p>
                        @if (filled($step['href']))
                            <a
                                href="{{ $step['href'] }}"
                                class="mt-1 inline-flex text-xs font-medium text-primary-600 underline decoration-primary-400/40 hover:decoration-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
                            >
                                {{ __('Open purchase order') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid w-full min-w-0 grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start lg:gap-8">
        {{-- Main column --}}
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-8">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Supplier card --}}
                <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 transition-shadow duration-200 hover:shadow-lg hover:shadow-gray-900/[0.07] dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-5 sm:col-span-2 lg:col-span-1">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Supplier') }}
                    </h2>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        {{ filled($q->supplier_name) ? $q->supplier_name : '—' }}
                    </p>
                    @if ($q->supplier && trim((string) $q->supplier->name) !== trim((string) ($q->supplier_name ?? '')))
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Catalog supplier') }}: {{ $q->supplier->name }}
                        </p>
                    @endif
                    <dl class="mt-4 space-y-2 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Contact person') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ filled($q->contact_person) ? $q->contact_person : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Notes') }}</dt>
                            <dd class="whitespace-pre-wrap text-gray-800 dark:text-gray-200">{{ filled($q->notes) ? $q->notes : '—' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Financial card --}}
                <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 transition-shadow duration-200 hover:shadow-lg hover:shadow-gray-900/[0.07] dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-5">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Financial summary') }}
                    </h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600 dark:text-gray-400">{{ __('Subtotal (before tax)') }}</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                {{ VietnamesePresentation::vnd($financial['sub']) ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600 dark:text-gray-400">{{ __('VAT amount') }}</dt>
                            <dd class="text-right font-medium text-gray-900 dark:text-white tabular-nums">
                                {{ VietnamesePresentation::vnd($financial['vat']) ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-gray-100 pt-3 dark:border-white/10">
                            <dt class="font-semibold text-gray-900 dark:text-white">{{ __('Total') }}</dt>
                            <dd class="text-right text-base font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                {{ VietnamesePresentation::vnd($financial['total']) ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Approval card --}}
                <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 transition-shadow duration-200 hover:shadow-lg hover:shadow-gray-900/[0.07] dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-5">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Approval') }}
                    </h2>
                    <div class="mb-3">
                        <x-filament::badge :color="$q->approvalStatusColor()">
                            {{ $q->approvalStatusLabel() }}
                        </x-filament::badge>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Approved at') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">
                                {{ $q->approved_at?->format(VietnamesePresentation::DATETIME_FORMAT) ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Quote date') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">
                                {{ $q->quote_date?->format(VietnamesePresentation::DATE_FORMAT) ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Line items table --}}
            <div class="overflow-hidden rounded-xl border border-gray-200/90 bg-white shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5 sm:py-4">
                    <h2 class="text-sm font-semibold text-gray-900 sm:text-base dark:text-white">{{ __('Line items') }}</h2>
                </div>
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="w-full min-w-[880px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/90 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                <th scope="col" class="px-3 py-2.5 text-left sm:px-5 sm:py-3">{{ __('Product') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right sm:px-5 sm:py-3">{{ __('Quantity') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right sm:px-5 sm:py-3">{{ __('Unit price') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right sm:px-5 sm:py-3">{{ __('VAT %') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right sm:px-5 sm:py-3">{{ __('VAT amount') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right sm:px-5 sm:py-3">{{ __('Amount (incl. VAT)') }}</th>
                                <th scope="col" class="w-px whitespace-nowrap px-2 py-2.5 text-right sm:px-3 sm:py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($q->items as $item)
                                @php
                                    $incl = QuotationLinePresentation::lineTotalIncludingVat($item->line_total, $item->vat_percent);
                                    $lineVat = QuotationLinePresentation::lineVatAmount($item->line_total, $item->vat_percent);
                                @endphp
                                <tr
                                    id="quotation-line-{{ $item->getKey() }}"
                                    wire:key="quotation-line-{{ $item->getKey() }}"
                                    class="cursor-default transition-[background-color,box-shadow] duration-150 ease-out hover:bg-emerald-50/70 hover:shadow-[inset_3px_0_0_0_rgba(16,185,129,0.45)] dark:hover:bg-white/[0.07] dark:hover:shadow-[inset_3px_0_0_0_rgba(52,211,153,0.4)]"
                                >
                                    <td class="px-3 py-3 align-top sm:px-5 sm:py-4">
                                        <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                            <x-filament::badge :color="$item->mapped_product_id !== null ? 'success' : 'gray'">
                                                {{ $item->mapped_product_id !== null ? __('Mapped') : __('Not mapped') }}
                                            </x-filament::badge>
                                        </div>
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ filled($item->raw_name) ? $item->raw_name : '—' }}
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if (filled($item->raw_model) || filled($item->brand))
                                                {{ trim(implode(' · ', array_filter([(string) $item->raw_model, (string) $item->brand]))) }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                        @if ($item->mappedProduct)
                                            <div class="mt-1 text-xs text-primary-600 dark:text-primary-400">
                                                {{ __('Mapped product') }}: {{ $item->mappedProduct->name }}
                                            </div>
                                        @elseif ($item->mapped_product_id === null)
                                            <a href="{{ $editUrl }}" class="mt-2 inline-block text-xs font-medium text-primary-600 underline decoration-primary-400/40 hover:decoration-primary-600 dark:text-primary-400">
                                                {{ __('Map product') }}
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-800 sm:px-5 sm:py-4 dark:text-gray-200">
                                        {{ QuotationLinePresentation::quantity($item->quantity) ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-800 sm:px-5 sm:py-4 dark:text-gray-200">
                                        {{ VietnamesePresentation::vnd($item->unit_price) ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-800 sm:px-5 sm:py-4 dark:text-gray-200">
                                        {{ QuotationLinePresentation::percent($item->vat_percent) ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-800 sm:px-5 sm:py-4 dark:text-gray-200">
                                        {{ VietnamesePresentation::vnd($lineVat) ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right text-sm font-bold tabular-nums text-gray-950 sm:px-5 sm:py-4 sm:text-base dark:text-white">
                                        {{ VietnamesePresentation::vnd($incl) ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-3 text-right align-top sm:px-3 sm:py-4">
                                        <div class="inline-flex items-center justify-end gap-0.5 sm:gap-1">
                                            <a
                                                href="{{ $editUrl }}"
                                                class="inline-flex rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                                                title="{{ __('Edit quotation') }}"
                                                aria-label="{{ __('Edit quotation') }}"
                                            >
                                                <svg class="block h-4 w-4 shrink-0" width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="max-width: 16px; max-height: 16px">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </a>
                                            @if ($q->approved_at === null)
                                                <button
                                                    type="button"
                                                    class="inline-flex rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                                                    title="{{ __('Duplicate line') }}"
                                                    aria-label="{{ __('Duplicate line') }}"
                                                    wire:confirm="{{ __('Duplicate this line?') }}"
                                                    wire:click="duplicateQuotationLine({{ (int) $item->getKey() }})"
                                                >
                                                    <svg class="block h-4 w-4 shrink-0" width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="max-width: 16px; max-height: 16px">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.015 9.015 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.015 9.015 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.376H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5M15.75 3.75v1.5a3.375 3.375 0 0 1-3.375 3.375h-1.5" />
                                                    </svg>
                                                </button>
                                            @else
                                                <span
                                                    class="inline-flex cursor-not-allowed rounded-lg p-1.5 text-gray-300 dark:text-gray-600"
                                                    title="{{ __('Cannot duplicate lines on an approved quotation') }}"
                                                    aria-label="{{ __('Cannot duplicate lines on an approved quotation') }}"
                                                >
                                                    <svg class="block h-4 w-4 shrink-0 opacity-50" width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="max-width: 16px; max-height: 16px">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.015 9.015 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.015 9.015 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.376H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5M15.75 3.75v1.5a3.375 3.375 0 0 1-3.375 3.375h-1.5" />
                                                    </svg>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 sm:px-5 dark:text-gray-400">
                                        {{ __('No line items.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($q->ingestionBatch && $q->ingestionBatch->files->isNotEmpty())
                <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 transition-shadow duration-200 hover:shadow-lg hover:shadow-gray-900/[0.07] dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-5">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 sm:text-base dark:text-white">{{ __('Uploaded files') }}</h2>
                    <ul class="space-y-2 text-sm">
                        @foreach ($q->ingestionBatch->files as $file)
                            <li class="flex flex-wrap items-center gap-2">
                                <a class="text-primary-600 underline decoration-primary-400/50 hover:decoration-primary-600 dark:text-primary-400" href="{{ route('ingestion.files.inline', $file) }}">{{ $file->original_name }}</a>
                                <span class="text-gray-400">·</span>
                                <a class="text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200" href="{{ route('ingestion.files.download', $file) }}">{{ __('Download') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Sticky summary on large screens --}}
        <aside class="min-w-0 lg:col-span-4">
            <div class="space-y-4 rounded-xl border border-gray-200/90 bg-white p-4 shadow-md shadow-gray-900/[0.05] ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:shadow-none dark:ring-white/10 sm:p-5 lg:sticky lg:top-4 lg:transition-shadow lg:duration-200 lg:hover:shadow-lg lg:hover:shadow-gray-900/[0.07]">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('Summary') }}
                </h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3 sm:gap-4">
                        <dt class="min-w-0 shrink text-gray-600 dark:text-gray-400">{{ __('Subtotal (before tax)') }}</dt>
                        <dd class="text-right font-medium tabular-nums text-gray-900 dark:text-white">
                            {{ VietnamesePresentation::vnd($financial['sub']) ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3 sm:gap-4">
                        <dt class="min-w-0 shrink text-gray-600 dark:text-gray-400">{{ __('VAT amount') }}</dt>
                        <dd class="text-right font-medium tabular-nums text-gray-900 dark:text-white">
                            {{ VietnamesePresentation::vnd($financial['vat']) ?? '—' }}
                        </dd>
                    </div>
                    <div class="border-t border-gray-100 pt-3 dark:border-white/10">
                        <div class="flex justify-between gap-3 sm:gap-4">
                            <dt class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Total') }}</dt>
                            <dd class="text-right text-lg font-bold tabular-nums text-emerald-600 sm:text-xl dark:text-emerald-400">
                                {{ VietnamesePresentation::vnd($financial['total']) ?? '—' }}
                            </dd>
                        </div>
                    </div>
                </dl>
                <x-filament::button
                    class="w-full justify-center !font-semibold shadow-sm transition-all duration-150 hover:!shadow-md active:scale-[0.98]"
                    color="success"
                    wire:click="mountAction('createPurchaseOrder')"
                    :disabled="! ($q->approved_at && $q->items->isNotEmpty())"
                >
                    {{ __('Create purchase order') }}
                </x-filament::button>
            </div>
        </aside>
    </div>
</div>
