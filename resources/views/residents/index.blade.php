@extends('layouts.app')

@section('title', 'People Directory')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">People Directory</h3>
        <div class="text-muted">
            Master directory for residents and non-residents linked to barangay cases.
        </div>
    </div>

    @if($status !== 'archived')
        <a href="{{ route('residents.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Add Person
        </a>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="alert alert-primary border-0 mb-4">
    <div class="d-flex gap-3 align-items-start">
        <i class="bi bi-info-circle mt-1"></i>
        <div>
            <strong>Add each person once.</strong>
            <div class="small mt-1">
                Blotter records will reuse people from this directory instead of encoding the same name repeatedly.
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('residents.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-6">
                <label class="form-label">Search</label>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Person ID, name, contact, street, or sitio"
                >
            </div>

            <div class="col-md-3 col-lg-2">
                <label class="form-label">Classification</label>
                <select name="classification" class="form-select">
                    <option value="">All</option>
                    <option value="Resident" @selected($classification === 'Resident')>Resident</option>
                    <option value="Non-Resident" @selected($classification === 'Non-Resident')>Non-Resident</option>
                </select>
            </div>

            <div class="col-md-3 col-lg-2">
                <label class="form-label">Record</label>
                <select name="status" class="form-select">
                    <option value="active" @selected($status === 'active')>Current</option>
                    <option value="archived" @selected($status === 'archived')>Archived</option>
                </select>
            </div>

            <div class="col-lg-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>{{ $status === 'archived' ? 'Archived People' : 'People' }}</strong>
        <span class="badge text-bg-secondary">{{ $residents->total() }}</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Person ID</th>
                        <th>Name</th>
                        <th>Classification</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($residents as $resident)
                        <tr>
                            <td><strong>{{ $resident->resident_code }}</strong></td>
                            <td>{{ $resident->full_name }}</td>
                            <td>
                                <span class="badge {{ $resident->classification === 'Resident' ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                    {{ $resident->classification ?? 'Unclassified' }}
                                </span>
                            </td>
                            <td>{{ $resident->contact_number ?? '—' }}</td>
                            <td>
                                {{
                                    collect([
                                        $resident->house_number ? 'House No. ' . $resident->house_number : null,
                                        $resident->street,
                                        $resident->purok,
                                        $resident->address_details,
                                    ])->filter()->join(', ') ?: '—'
                                }}
                            </td>
                            <td>
                                @if($status === 'archived')
                                    <span class="badge text-bg-secondary">Archived</span>
                                @elseif($resident->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-warning">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    @if($status === 'active')
                                        <a href="{{ route('residents.show', $resident) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                        <a href="{{ route('residents.edit', $resident) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form
                                            method="POST"
                                            action="{{ route('residents.destroy', $resident) }}"
                                            onsubmit="return confirm('Archive this person record?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Archive</button>
                                        </form>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('residents.restore', $resident->id) }}"
                                            onsubmit="return confirm('Restore this person record?');"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                No people found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($residents->hasPages())
        <div class="card-footer bg-white">
            {{ $residents->links() }}
        </div>
    @endif
</div>

@endsection
