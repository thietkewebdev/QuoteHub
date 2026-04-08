@php
    use App\Filament\Resources\Products\ProductResource;
    use App\Support\Locale\VietnamesePresentation;
    use App\Support\Quotation\QuotationLinePresentation;
    use Illuminate\Support\Js;
    /** @var \App\Models\Quotation $q */
    /** @var array{sub: float, vat: float, total: float} $financial total = grand total incl. VAT */
    /** @var bool $canEditQuotationLines */
    /** @var string|null $quotationFileDownloadUrl */
@endphp

<div class="fi-quotation-detail-layout w-full min-w-0 max-w-none space-y-6">
    {{-- Compact header: purchasing focus --}}
    <div
        class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-950 dark:ring-white/10 sm:p-5"
    >
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
            <div class="min-w-0 flex-1 space-y-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
                        {{ __('Quotation #:id', ['id' => $q->getKey()]) }}
                    </h1>
                    <p
                        class="mt-2 text-base font-semibold leading-snug text-gray-900 sm:text-lg dark:text-gray-100"
                    >
                        {{ filled($q->supplier_name) ? $q->supplier_name : '—' }}
                    </p>
                    @php
                        $supplierPhone = filled($q->supplier?->phone) ? trim((string) $q->supplier->phone) : null;
                        $supplierEmail = filled($q->supplier?->email) ? trim((string) $q->supplier->email) : null;
                        $hasContactBlock = filled($q->contact_person) || $supplierPhone !== null || $supplierEmail !== null;
                    @endphp
                    @if ($hasContactBlock)
                        <div class="mt-2 space-y-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            @if (filled($q->contact_person))
                                <p class="text-base">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Contact person') }}:</span>
                                    <span class="ml-1 font-medium text-gray-950 dark:text-white">{{ $q->contact_person }}</span>
                                </p>
                            @endif
                            @if ($supplierPhone !== null)
                                <p class="tabular-nums">
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">{{ __('Phone') }}</span>
                                    <a
                                        href="tel:{{ preg_replace('/\s+/', '', $supplierPhone) }}"
                                        class="mt-1 inline-flex max-w-full items-center rounded-lg bg-primary-600/10 px-3 py-2 text-base font-bold tracking-tight text-primary-700 ring-1 ring-primary-600/20 transition hover:bg-primary-600/15 hover:ring-primary-600/35 dark:bg-primary-400/15 dark:text-primary-300 dark:ring-primary-400/25 dark:hover:bg-primary-400/25"
                                    >{{ $supplierPhone }}</a>
                                </p>
                            @endif
                            @if ($supplierEmail !== null)
                                <p class="break-all">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}:</span>
                                    <a
                                        href="mailto:{{ $supplierEmail }}"
                                        class="ml-0.5 text-primary-600 underline decoration-primary-400/40 underline-offset-2 hover:decoration-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
                                    >{{ $supplierEmail }}</a>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <x-filament::badge :color="$q->approvalStatusColor()">
                        {{ $q->approvalStatusLabel() }}
                    </x-filament::badge>
                    <span class="text-gray-700 dark:text-gray-300">
                        <span class="text-gray-500 dark:text-gray-500">{{ __('Quote date') }}:</span>
                        {{ $q->quote_date?->format(VietnamesePresentation::DATE_FORMAT) ?? '—' }}
                    </span>
                    <span class="text-gray-700 dark:text-gray-300">
                        <span class="text-gray-500 dark:text-gray-500">{{ __('Approved at') }}:</span>
                        {{ $q->approved_at?->format(VietnamesePresentation::DATETIME_FORMAT) ?? '—' }}
                    </span>
                </div>
            </div>
            <div
                class="flex w-full flex-col gap-4 border-t border-gray-100 pt-4 dark:border-white/10 sm:w-auto sm:border-t-0 sm:pt-0 lg:min-w-[16rem] lg:items-end lg:border-t-0 lg:pt-0"
            >
                <div class="text-left sm:text-right">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('Total amount (incl. VAT)') }}
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold tabular-nums tracking-tight text-emerald-600 dark:text-emerald-400 sm:text-3xl"
                    >
                        {{ VietnamesePresentation::vnd($financial['total']) ?? '—' }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                    <x-filament::button
                        class="w-full justify-center sm:w-auto"
                        color="gray"
                        outlined
                        size="sm"
                        wire:click="mountAction('createPurchaseOrder')"
                        :disabled="! ($q->approved_at && $q->items->isNotEmpty())"
                    >
                        {{ __('Create purchase order') }}
                    </x-filament::button>
                    @if ($q->approved_at)
                        <x-filament::button
                            class="w-full justify-center sm:w-auto"
                            color="gray"
                            outlined
                            size="sm"
                            wire:click="mountAction('cloneToManualDraft')"
                        >
                            {{ __('Duplicate') }}
                        </x-filament::button>
                    @endif
                    @if (filled($quotationFileDownloadUrl ?? null))
                        <x-filament::button
                            class="w-full justify-center gap-1.5 !font-semibold shadow-sm sm:w-auto [&>svg]:shrink-0"
                            color="success"
                            size="sm"
                            tag="a"
                            :href="$quotationFileDownloadUrl"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="h-4 w-4"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                                />
                            </svg>
                            {{ __('Download quotation file') }}
                        </x-filament::button>
                    @else
                        <x-filament::button
                            class="w-full justify-center sm:w-auto"
                            color="gray"
                            outlined
                            size="sm"
                            disabled
                        >
                            {{ __('No file') }}
                        </x-filament::button>
                    @endif
                    <x-filament::button
                        class="w-full justify-center sm:w-auto"
                        color="gray"
                        outlined
                        size="sm"
                        tag="a"
                        :href="$editUrl"
                    >
                        {{ __('Edit') }}
                    </x-filament::button>
                    <x-filament::button
                        class="w-full justify-center sm:w-auto"
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

    {{-- Line items (main content) --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-950 dark:ring-white/10"
    >
        <div class="border-b border-gray-100 px-4 py-3.5 dark:border-white/10 sm:px-5">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Line items') }}</h2>
        </div>
        <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
            <table class="w-full min-w-[44rem] text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-gray-100 bg-gray-50/95 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/[0.04] dark:text-gray-400"
                    >
                        <th scope="col" class="px-3 py-2.5 text-left sm:px-4 sm:py-3">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-left sm:px-4 sm:py-3">{{ __('Technical specifications') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right sm:px-4 sm:py-3">{{ __('Unit price') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right sm:px-4 sm:py-3">{{ __('Quantity') }}</th>
                        <th scope="col" class="w-px whitespace-nowrap px-2 py-2.5 text-right sm:px-3 sm:py-3">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($q->items as $item)
                        <tr
                            id="quotation-line-{{ $item->getKey() }}"
                            wire:key="quotation-line-{{ $item->getKey() }}"
                            class="transition-colors duration-150 hover:bg-gray-50/80 dark:hover:bg-white/[0.04]"
                        >
                            <td class="max-w-xs px-3 py-2.5 align-top sm:max-w-sm sm:px-4 sm:py-3">
                                @if ($item->mappedProduct)
                                    <a
                                        href="{{ ProductResource::getUrl('view', ['record' => $item->mappedProduct]) }}"
                                        class="group inline-flex max-w-full items-center gap-1.5 font-semibold text-gray-900 decoration-primary-500/0 decoration-2 underline-offset-2 transition-colors hover:text-primary-600 hover:underline hover:decoration-primary-500/80 dark:text-white dark:hover:text-primary-400 dark:hover:decoration-primary-400/80"
                                    >
                                        <span class="min-w-0 break-words">{{ filled($item->raw_name) ? $item->raw_name : '—' }}</span>
                                        <svg
                                            class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-colors group-hover:text-primary-500 dark:text-gray-500 dark:group-hover:text-primary-400"
                                            width="14"
                                            height="14"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                            style="max-width: 14px; max-height: 14px"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"
                                            />
                                        </svg>
                                    </a>
                                @else
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        {{ filled($item->raw_name) ? $item->raw_name : '—' }}
                                    </div>
                                    <div class="mt-1.5">
                                        <x-filament::badge color="gray">
                                            {{ __('Not mapped') }}
                                        </x-filament::badge>
                                    </div>
                                @endif
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if (filled($item->raw_model) || filled($item->brand))
                                        {{ trim(implode(' · ', array_filter([(string) $item->raw_model, (string) $item->brand]))) }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </td>
                            <td class="max-w-[13rem] px-3 py-2.5 align-top sm:max-w-[14rem] sm:px-4 sm:py-3">
                                @php
                                    $specLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($item->specs_text ?? '')))));
                                @endphp
                                @if (count($specLines) > 0)
                                    <ul
                                        class="list-inside list-disc space-y-0.5 text-xs leading-snug text-gray-600 marker:text-gray-400 dark:text-gray-400 dark:marker:text-gray-500"
                                    >
                                        @foreach ($specLines as $specLine)
                                            <li class="break-words pl-0.5">{{ $specLine }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right align-top sm:px-4 sm:py-3">
                                <div class="text-base font-semibold tabular-nums text-gray-900 dark:text-white">
                                    {{ VietnamesePresentation::vnd($item->unit_price) ?? '—' }}
                                </div>
                                <div class="mt-0.5 text-[11px] font-normal text-gray-500 dark:text-gray-500">
                                    {{ __('Excl. VAT') }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-800 dark:text-gray-200 sm:px-4 sm:py-3">
                                {{ QuotationLinePresentation::quantity($item->quantity) ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-2 py-2.5 text-right align-top sm:px-3 sm:py-3">
                                <div class="flex flex-col items-end gap-1.5">
                                <div class="inline-flex items-center justify-end gap-0.5">
                                    <a
                                        href="{{ $editUrl }}"
                                        class="inline-flex rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                                        title="{{ __('Edit quotation') }}"
                                        aria-label="{{ __('Edit quotation') }}"
                                    >
                                        <svg
                                            class="block h-4 w-4 shrink-0"
                                            width="16"
                                            height="16"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                            style="max-width: 16px; max-height: 16px"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                            />
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
                                            <svg
                                                class="block h-4 w-4 shrink-0"
                                                width="16"
                                                height="16"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                                style="max-width: 16px; max-height: 16px"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.015 9.015 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.015 9.015 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.376H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5M15.75 3.75v1.5a3.375 3.375 0 0 1-3.375 3.375h-1.5"
                                                />
                                            </svg>
                                        </button>
                                    @else
                                        <span
                                            class="inline-flex cursor-not-allowed rounded-lg p-1.5 text-gray-300 dark:text-gray-600"
                                            title="{{ __('Cannot duplicate lines on an approved quotation') }}"
                                            aria-label="{{ __('Cannot duplicate lines on an approved quotation') }}"
                                        >
                                            <svg
                                                class="block h-4 w-4 shrink-0 opacity-50"
                                                width="16"
                                                height="16"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                aria-hidden="true"
                                                style="max-width: 16px; max-height: 16px"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.015 9.015 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.015 9.015 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.376H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5M15.75 3.75v1.5a3.375 3.375 0 0 1-3.375 3.375h-1.5"
                                                />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                @if ($canEditQuotationLines ?? false)
                                    <div class="flex flex-col items-end gap-0.5 border-t border-gray-100/90 pt-1.5 dark:border-white/10">
                                        @if ($item->mappedProduct)
                                            <x-filament::button
                                                class="!px-2 !py-1"
                                                size="xs"
                                                color="gray"
                                                outlined
                                                type="button"
                                                wire:click="mountAction('mapQuotationLineItem', {{ Js::from(['quotationItemId' => (int) $item->getKey()]) }})"
                                            >
                                                {{ __('Remap') }}
                                            </x-filament::button>
                                            <x-filament::button
                                                class="!px-2 !py-1"
                                                size="xs"
                                                color="danger"
                                                outlined
                                                type="button"
                                                wire:click="mountAction('unlinkQuotationLineItem', {{ Js::from(['quotationItemId' => (int) $item->getKey()]) }})"
                                            >
                                                {{ __('Unlink') }}
                                            </x-filament::button>
                                        @else
                                            <x-filament::button
                                                class="!px-2 !py-1"
                                                size="xs"
                                                color="primary"
                                                outlined
                                                type="button"
                                                wire:click="mountAction('mapQuotationLineItem', {{ Js::from(['quotationItemId' => (int) $item->getKey()]) }})"
                                            >
                                                {{ __('Map') }}
                                            </x-filament::button>
                                        @endif
                                    </div>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-5"
                            >
                                {{ __('No line items.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Totals: quantity + money (VAT breakdown only here) --}}
    @php
        $totalQuantitySum = $q->items->sum(function (\App\Models\QuotationItem $line): float {
            $qty = $line->quantity;
            if ($qty === null || $qty === '' || ! is_numeric($qty)) {
                return 0.0;
            }

            return (float) $qty;
        });
    @endphp
    <div
        class="ml-auto max-w-md rounded-xl border border-gray-200/90 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-950 dark:ring-white/10 sm:p-5"
    >
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('Totals') }}
        </p>
        <dl class="space-y-2.5 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-gray-600 dark:text-gray-400">{{ __('Total quantity') }}</dt>
                <dd class="text-right font-medium tabular-nums text-gray-900 dark:text-white">
                    {{ QuotationLinePresentation::quantity($totalQuantitySum) ?? '—' }}
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-600 dark:text-gray-400">{{ __('Subtotal (before tax)') }}</dt>
                <dd class="text-right font-medium tabular-nums text-gray-900 dark:text-white">
                    {{ VietnamesePresentation::vnd($financial['sub']) ?? '—' }}
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-600 dark:text-gray-400">{{ __('VAT amount') }}</dt>
                <dd class="text-right font-medium tabular-nums text-gray-900 dark:text-white">
                    {{ VietnamesePresentation::vnd($financial['vat']) ?? '—' }}
                </dd>
            </div>
            <div class="flex justify-between gap-4 border-t border-gray-100 pt-3 dark:border-white/10">
                <dt class="font-semibold text-gray-900 dark:text-white">{{ __('Grand total (incl. VAT)') }}</dt>
                <dd class="text-right text-base font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                    {{ VietnamesePresentation::vnd($financial['total']) ?? '—' }}
                </dd>
            </div>
        </dl>
    </div>
</div>
