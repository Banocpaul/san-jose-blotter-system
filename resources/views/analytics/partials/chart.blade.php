@php
    $series = $series ?? ['total' => 'Incidents'];
@endphp
<div class="card h-100">
    <div class="card-header"><strong>{{ $title }}</strong></div>
    <div class="card-body">
        @if(!empty($chartDescription))
            <p class="text-muted small">{{ $chartDescription }}</p>
        @endif
        @include('analytics.partials.chart-values')
    </div>
</div>
