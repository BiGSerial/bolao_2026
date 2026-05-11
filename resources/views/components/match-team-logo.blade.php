{{--
    Reusable team logo / crest component.
    Props:
      team  – App\Models\Team|null
      size  – 'xs' | 'sm' | 'md' | 'lg' | 'xl'  (default 'md')
--}}
@props(['team' => null, 'size' => 'md'])

@php
    $dims = match ($size) {
        'xs'    => 'h-6 w-6',
        'sm'    => 'h-8 w-8',
        'md'    => 'h-12 w-12',
        'lg'    => 'h-14 w-14',
        'xl'    => 'h-16 w-16',
        default => 'h-12 w-12',
    };
    $text = match ($size) {
        'xs'    => 'text-[10px]',
        'sm'    => 'text-xs',
        'md'    => 'text-sm',
        'lg'    => 'text-base',
        'xl'    => 'text-lg',
        default => 'text-sm',
    };
@endphp

@if($team?->crest)
    <img src="{{ $team->crest }}"
         alt="{{ $team->tla ?? $team->short_name ?? '' }}"
         class="{{ $dims }} object-contain drop-shadow"
         loading="lazy">
@else
    <div class="{{ $dims }} rounded-full bg-bolao-bg4 border border-white/[0.07] flex items-center justify-center {{ $text }} font-bold font-bc text-bolao-muted select-none">
        {{ $team?->tla ?? '?' }}
    </div>
@endif
