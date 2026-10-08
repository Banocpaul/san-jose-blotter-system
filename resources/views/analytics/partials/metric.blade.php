<div class="col-6 col-md-4 col-xl-2">
    <div class="card h-100 analytics-metric {{ $tone ?? '' }}"><div class="card-body">
        <span class="analytics-metric-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
        <div class="analytics-metric-label">{{ $label }}</div>
        <div class="analytics-metric-value">{{ $value }}</div>
        <div class="small analytics-metric-description">{{ $description }}</div>
    </div></div>
</div>
