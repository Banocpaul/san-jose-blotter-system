@extends('layouts.app')

@section('title', 'Add Person')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Add Person</h3>
        <div class="text-muted">
            Add the person once, then reuse the record in blotter cases.
        </div>
    </div>

    <a href="{{ route('residents.index') }}" class="btn btn-outline-secondary">
        Back to People Directory
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

<form method="POST" action="{{ route('residents.store') }}">
    @csrf

    @include('residents._form')

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Save Person
        </button>

        <a href="{{ route('residents.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>

@endsection
