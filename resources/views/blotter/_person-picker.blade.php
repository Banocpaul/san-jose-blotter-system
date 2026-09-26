@php
    $fieldName = $fieldName ?? ($prefix . '_resident_id');
    $selectedId = old($fieldName);
    $required = $required ?? true;
    $helpText = $helpText ?? null;
@endphp

<div
    class="person-picker"
    data-person-picker
    data-search-url="{{ route('residents.search') }}"
>
    <label class="form-label" for="{{ $prefix }}_person_search">
        {{ $label }}@if($required) * @endif
    </label>

    <input
        type="hidden"
        name="{{ $fieldName }}"
        value="{{ $selectedId }}"
        data-person-id
    >

    <div class="position-relative">
        <input
            type="search"
            id="{{ $prefix }}_person_search"
            class="form-control @error($fieldName) is-invalid @enderror"
            placeholder="Search by name, person ID, or contact number..."
            autocomplete="off"
            data-person-search
        >

        <div
            class="list-group position-absolute start-0 end-0 mt-1 shadow-sm d-none"
            style="z-index: 1050; max-height: 280px; overflow-y: auto;"
            data-person-results
        ></div>

        @error($fieldName)
            <div class="invalid-feedback d-block">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div
        class="border rounded p-3 mt-2 bg-light d-none"
        data-person-selected
    >
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="fw-semibold" data-person-name></div>
                <div class="small text-muted" data-person-meta></div>
            </div>

            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                data-person-clear
            >
                Change
            </button>
        </div>
    </div>

    <div class="form-text mt-2">
        @if($helpText)
            {{ $helpText }}
        @else
            Select an existing person from the People Directory.
            If the person is not listed,
            <a
                href="{{ route('residents.create') }}"
                target="_blank"
                rel="noopener"
            >add them to the People Directory first</a>.
        @endif
    </div>
</div>
