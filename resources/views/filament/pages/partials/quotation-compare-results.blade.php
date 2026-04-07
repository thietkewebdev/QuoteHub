@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Quotation> $quotations */
    use App\Filament\Resources\Quotations\QuotationResource;
    use App\Support\Locale\VietnamesePresentation;

    $badgeTone = static function (string $filamentColor): string {
        return match ($filamentColor) {
            'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
            'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
            'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
            'info' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30',
            'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-300 dark:ring-gray-400/25',
        };
    };
@endphp

@if ($quotations->isEmpty())
    <div class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('Run a comparison to see quotations here.') }}
    </div>
@else
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach ($quotations as $quotation)
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-3 flex flex-col gap-1 border-b border-gray-100 pb-3 dark:border-white/10">
                    <a
                        href="{{ QuotationResource::getUrl('view', ['record' => $quotation]) }}"
                        class="text-base font-semibold text-primary-600 hover:underline dark:text-primary-400"
                    >
                        {{ __('Quotation #:id', ['id' => $quotation->id]) }}
                    </a>
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $quotation->supplier_name ?: '—' }}
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span @class(['inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', $badgeTone($quotation->approvalStatusColor())])>
                            {{ $quotation->approvalStatusLabel() }}
                        </span>
                        <span @class(['inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', $badgeTone($quotation->pricingPolicyBadgeColor())])>
                            {{ $quotation->pricingPolicyLabel() }}
                        </span>
                    </div>
                    <dl class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                        <dt>{{ __('Quote date') }}</dt>
                        <dd class="text-end font-medium text-gray-900 dark:text-gray-100">
                            {{ $quotation->quote_date?->format(VietnamesePresentation::DATE_FORMAT) ?? '—' }}
                        </dd>
                        <dt>{{ __('Total') }}</dt>
                        <dd class="text-end font-medium text-gray-900 dark:text-gray-100">
                            {{ VietnamesePresentation::vnd($quotation->total_amount) ?? '—' }}
                        </dd>
                    </dl>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[240px] border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-start text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="py-1 pe-2 font-medium">{{ __('Line') }}</th>
                                <th class="py-1 pe-2 font-medium">{{ __('Product') }}</th>
                                <th class="py-1 text-end font-medium">{{ __('Unit price') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($quotation->items as $item)
                                <tr class="border-b border-gray-50 dark:border-white/5">
                                    <td class="py-1 pe-2 align-top text-gray-500">{{ $item->line_no }}</td>
                                    <td class="py-1 pe-2 align-top">
                                        @if ($item->mappedProduct)
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $item->mappedProduct->name }}</span>
                                            @if (filled($item->raw_model))
                                                <span class="block text-gray-500 dark:text-gray-400">{{ $item->raw_model }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-900 dark:text-gray-100">{{ $item->raw_name ?: '—' }}</span>
                                            @if (filled($item->raw_model))
                                                <span class="block text-gray-500 dark:text-gray-400">{{ $item->raw_model }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="py-1 align-top text-end tabular-nums text-gray-900 dark:text-gray-100">
                                        {{ VietnamesePresentation::vnd($item->unit_price) ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-2 text-gray-500">{{ __('No line items.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endif
