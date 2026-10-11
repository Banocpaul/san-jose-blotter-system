@if(count($searchTerms))
    <div class="col-12 d-flex flex-wrap align-items-center gap-2 mb-2" aria-label="Active search terms">
        <span class="small text-muted">Searching within:</span>
        @foreach($searchTerms as $term)
            <input type="hidden" name="search_terms[]" value="{{ $term }}">
            <span class="badge text-bg-primary px-3 py-2">{{ $term }}</span>
        @endforeach
        <span class="small text-muted">Add another term to narrow these results. Clear resets the search.</span>
    </div>
@endif
