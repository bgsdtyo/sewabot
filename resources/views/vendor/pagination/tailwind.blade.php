@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-col items-center justify-between gap-4 py-2 sm:flex-row">
        <div class="text-xs text-brand-500">
            Menampilkan
            <span class="font-bold text-brand-900">{{ $paginator->firstItem() ?? 0 }}</span>
            –
            <span class="font-bold text-brand-900">{{ $paginator->lastItem() ?? 0 }}</span>
            dari
            <span class="font-bold text-brand-900">{{ $paginator->total() }}</span>
            data
        </div>

        <div class="flex items-center gap-1.5">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="@lang('pagination.previous')" class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-xl border border-brand-100 bg-brand-50 text-xs font-semibold text-brand-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200 bg-white text-xs font-semibold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span aria-disabled="true" class="inline-flex h-9 w-9 items-center justify-center text-xs font-bold text-brand-400">{{ $element }}</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brand-900 text-xs font-bold text-white shadow-sm">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200 bg-white text-xs font-semibold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200 bg-white text-xs font-semibold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="@lang('pagination.next')" class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-xl border border-brand-100 bg-brand-50 text-xs font-semibold text-brand-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
