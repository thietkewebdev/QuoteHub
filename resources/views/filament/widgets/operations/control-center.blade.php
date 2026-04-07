<div class="space-y-5">
    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            <div
                class="rounded-2xl border border-gray-200/90 bg-white p-5 shadow-md shadow-gray-950/[0.04] ring-1 ring-gray-950/[0.04] transition-shadow duration-200 hover:shadow-lg hover:shadow-gray-950/[0.06] dark:border-white/10 dark:bg-gray-950 dark:shadow-black/30 dark:ring-white/10 dark:hover:shadow-black/50"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $kpi['label'] }}
                </p>
                <p class="mt-2 text-2xl font-bold tabular-nums tracking-tight text-gray-950 dark:text-white">
                    {{ $kpi['value'] }}
                </p>
            </div>
        @endforeach
    </div>

    @if (count($quickActions) > 0)
        {{-- Quick actions --}}
        <div
            class="flex flex-col gap-3 rounded-2xl border border-gray-200/90 bg-gray-50/80 p-4 shadow-sm ring-1 ring-gray-950/[0.03] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('Quick actions') }}
            </p>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ $action['href'] }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200/90 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm transition-all duration-150 hover:border-primary-500/30 hover:bg-primary-50/80 hover:text-primary-700 active:scale-[0.98] dark:border-white/10 dark:bg-gray-900 dark:text-gray-100 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10 dark:hover:text-primary-300"
                    >
                        @if ($action['icon'] === 'plus')
                            <svg class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        @elseif ($action['icon'] === 'scale')
                            <svg class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.34 0-4.584.434-6.646 1.227M18.75 4.97v.75m0 15v-.75m-13.5-3h13.5m-13.5 0a3 3 0 0 1-.927-2.162c0-1.253.89-2.315 2.076-2.586m14.277 0A3.001 3.001 0 0 1 21 16.5v.75m-18 0A3.001 3.001 0 0 1 3 16.5v-.75" />
                            </svg>
                        @else
                            <svg class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        @endif
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
