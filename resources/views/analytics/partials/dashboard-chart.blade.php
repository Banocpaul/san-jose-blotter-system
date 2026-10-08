@php
    $chartRows = collect($rows);
    $series = $series ?? ['total' => ($valueLabel ?? 'Cases')];
    $hasValues = $chartRows->contains(fn ($row) => collect(array_keys($series))->contains(fn ($key) => data_get($row, $key) !== null));
@endphp
<div class="card h-100">
    <div class="card-header"><strong>{{ $title }}</strong></div>
    <div class="card-body">
        <p class="text-muted small mb-3">{{ $description }}</p>
        @if($chartRows->isNotEmpty() && $hasValues)
            <div class="analytics-chart analytics-dashboard-chart">
                <canvas id="{{ $id }}" role="img" aria-label="{{ $title }}"
                    data-dashboard-chart data-rows="{{ $chartRows->toJson() }}"
                    data-series="{{ json_encode($series) }}" data-type="{{ $chartType ?? 'bar' }}"
                    data-colors="{{ json_encode($colors ?? ['#2563eb', '#16a675', '#fbbf24', '#ef6464', '#94a3b8']) }}"
                    data-horizontal="{{ !empty($horizontal) ? 'true' : 'false' }}"
                    data-by-row="{{ !empty($byRow) ? 'true' : 'false' }}"
                    data-stacked="{{ !empty($stacked) ? 'true' : 'false' }}"
                    data-unit="{{ $unit ?? 'cases' }}"></canvas>
            </div>
        @else
            <div class="analytics-empty text-muted">No recorded measurements available.</div>
        @endif
        <details class="mt-3">
            <summary class="small text-muted">View chart values</summary>
            <div class="table-responsive mt-2"><table class="table table-sm mb-0">
                <thead><tr><th scope="col">Category</th>@foreach($series as $label)<th scope="col" class="text-end">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@forelse($chartRows as $row)
                    <tr><td>{{ data_get($row, 'label') }}</td>@foreach($series as $key => $label)<td class="text-end">{{ data_get($row, $key) === null ? 'Unavailable' : number_format(data_get($row, $key), ($unit ?? 'cases') === 'days' ? 1 : 0) }}</td>@endforeach</tr>
                @empty<tr><td colspan="{{ count($series) + 1 }}" class="text-muted">No matching data.</td></tr>@endforelse</tbody>
            </table></div>
        </details>
    </div>
</div>
