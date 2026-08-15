@props([
    'current' => 1,
])

@php
    $steps = [
        1 => 'Durasi',
        2 => 'Konfigurasi',
        3 => 'Pembayaran',
        4 => 'Selesai',
    ];
@endphp

<nav class="mb-10" aria-label="Progress">
    <ol class="grid grid-cols-4 gap-2">
        @foreach ($steps as $number => $label)
            @php
                $done = $number < $current;
                $active = $number === $current;
            @endphp
            <li class="flex flex-col items-center text-center">
                <span @class([
                    'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                    'bg-brand-900 text-white' => $done || $active,
                    'bg-white text-brand-500 ring-1 ring-brand-200' => ! $done && ! $active,
                ])>
                    @if ($done)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class([
                    'mt-2 text-[11px] font-semibold sm:text-xs',
                    'text-brand-900' => $done || $active,
                    'text-brand-500' => ! $done && ! $active,
                ])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
    <div class="mt-4 h-1 overflow-hidden rounded-full bg-brand-100">
        <div class="h-full rounded-full bg-brand-900 transition-all"
             style="width: {{ (($current - 1) / 3) * 100 }}%"></div>
    </div>
</nav>
