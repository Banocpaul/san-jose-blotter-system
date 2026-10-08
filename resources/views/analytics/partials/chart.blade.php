<div class="card h-100">
    <div class="card-header"><strong>{{ $title }}</strong></div>
    <div class="card-body">
        @if(!empty($chartDescription))
            <p class="text-muted small">{{ $chartDescription }}</p>
        @endif
        @if(collect($rows)->sum('total') > 0)
            <div class="analytics-chart">
                <canvas id="{{ $id }}" role="img" aria-label="{{ $title }}"
                    data-analytics-chart data-rows="{{ $rows->toJson() }}"
                    data-type="{{ $chartType ?? 'bar' }}" data-horizontal="{{ !empty($horizontal) ? 'true' : 'false' }}"></canvas>
            </div>
        @else
            <div class="analytics-empty text-muted">No matching data to display.</div>
        @endif
        <details class="mt-3">
            <summary class="small text-muted">View chart values</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm mb-0">
                    <thead><tr><th scope="col">Category</th><th scope="col" class="text-end">Cases</th></tr></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr><td>{{ data_get($row, 'label') }}</td><td class="text-end">{{ number_format(data_get($row, 'total')) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">No matching data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</div>
