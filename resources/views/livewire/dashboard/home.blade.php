<div @if($live->isNotEmpty()) wire:poll.10s @elseif($upcoming->isNotEmpty()) wire:poll.30s @endif>
    @if($competitionType === 'league')
        @include('livewire.dashboard.partials.home-league')
    @else
        @include('livewire.dashboard.partials.home-cup')
    @endif
</div>
