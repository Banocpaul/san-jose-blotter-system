@extends('layouts.app')

@section('title', 'Edit Person')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Edit Person</h3>
        <div class="text-muted">
            {{ $resident->resident_code }} — {{ $resident->full_name }}
        </div>
    </div>

    <a href="{{ route('residents.show', $resident) }}" class="btn btn-outline-secondary">
        Back
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('residents.update', $resident) }}">
    @csrf
    @method('PUT')

    @include('residents._form', ['resident' => $resident])

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary">
            Save Changes
        </button>

        <a href="{{ route('residents.show', $resident) }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>

@endsection
