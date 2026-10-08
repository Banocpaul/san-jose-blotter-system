@if(collect($rows)->sum('total') > 0)
    <div class="analytics-chart">
        <canvas id="{{ $id }}" role="img" aria-label="{{ $title }}"
            data-analytics-chart data-rows="{{ $rows->toJson() }}"
            data-series="{{ json_encode($series) }}"
            data-type="{{ $chartType ?? 'bar' }}"
            data-horizontal="{{ !empty($horizontal) ? 'true' : 'false' }}"
            data-stacked="{{ !empty($stacked) ? 'true' : 'false' }}"
            data-by-type="{{ !empty($byType) ? 'true' : 'false' }}"></canvas>
    </div>
@else
    <div class="analytics-empty text-muted">No matching data to display.</div>
@endif
<details class="mt-3">
    <summary class="small text-muted">View chart values</summary>
    <div class="table-responsive mt-2">
        <table class="table table-sm mb-0">
            <thead><tr><th scope="col">{{ $categoryLabel ?? 'Category' }}</th>
                @foreach($series as $label)<th scope="col" class="text-end">{{ $label }}</th>@endforeach
                @if(count($series) > 1)<th scope="col" class="text-end">Total</th>@endif
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr><td>{{ data_get($row, 'label') }}</td>
                    @foreach($series as $key => $label)<td class="text-end">{{ number_format(data_get($row, $key, 0)) }}</td>@endforeach
                    @if(count($series) > 1)<td class="text-end">{{ number_format(data_get($row, 'total')) }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ count($series) + 1 }}" class="text-muted">No matching data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</details>
