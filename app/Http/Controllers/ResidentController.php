<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentRequest;
use App\Models\Resident;
use App\Services\AuditLogService;
use App\Services\ResidentReferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResidentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'active';
        $classification = $request->string('classification')->toString();

        $query = $status === 'archived'
            ? Resident::onlyTrashed()
            : Resident::query();

        if ($request->filled('search')) {
            $query->search((string) $request->input('search'));
        }

        if (in_array($classification, ['Resident', 'Non-Resident'], true)) {
            $query->where('classification', $classification);
        }

        $residents = $query
            ->select([
                'id',
                'resident_code',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'sex',
                'contact_number',
                'classification',
                'house_number',
                'street',
                'purok',
                'address_details',
                'is_active',
                'deleted_at',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('residents.index', compact(
            'residents',
            'status',
            'classification'
        ));
    }


    public function search(Request $request)
    {
        $id = $request->integer('id');
        $term = trim((string) $request->input('q', ''));

        $query = Resident::active()
            ->select([
                'id',
                'resident_code',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'classification',
            ]);

        if ($id > 0) {
            $query->whereKey($id);
        } else {
            if (strlen($term) < 2) {
                return response()->json(['data' => []]);
            }

            $tokens = array_slice(
                preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY),
                0,
                4
            );

            $query->where(function ($search) use ($term, $tokens) {
                $prefix = $term . '%';

                $search
                    ->where('resident_code', 'like', $prefix)
                    ->orWhere('contact_number', 'like', $prefix)
                    ->orWhere(function ($nameSearch) use ($tokens) {
                        foreach ($tokens as $token) {
                            $nameSearch->where(function ($part) use ($token) {
                                $prefix = $token . '%';

                                $part
                                    ->where('first_name', 'like', $prefix)
                                    ->orWhere('middle_name', 'like', $prefix)
                                    ->orWhere('last_name', 'like', $prefix)
                                    ->orWhere('suffix', 'like', $prefix);
                            });
                        }
                    });
            });
        }

        $people = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($id > 0 ? 1 : 12)
            ->get()
            ->map(fn (Resident $person) => [
                'id' => $person->id,
                'code' => $person->resident_code,
                'name' => $person->full_name,
                'classification' => $person->classification ?: 'Resident',
            ])
            ->values();

        return response()->json(['data' => $people]);
    }

    public function create()
    {
        return view('residents.create');
    }

    public function store(
        ResidentRequest $request,
        ResidentReferenceService $referenceService
    ) {
        $resident = DB::transaction(function () use ($request, $referenceService) {
            $data = $request->validated();
            $data['resident_code'] = $referenceService->generate();
            $data['created_by'] = auth()->id();

            $resident = Resident::create($data);

            AuditLogService::log(
                action: 'created',
                module: 'People Directory',
                description: "Person record {$resident->resident_code} was created.",
                auditable: $resident,
                newValues: $this->residentAuditSnapshot($resident)
            );

            return $resident;
        });

        return redirect()
            ->route('residents.show', $resident)
            ->with('success', 'Person added to the People Directory successfully.');
    }

    public function show(Resident $resident)
    {
        /*
         * The page only needs participation totals, not every related case row.
         * Using aggregate counts avoids loading three full relationship trees.
         */
        $resident->loadMissing('creator:id,name');
        $resident->loadCount([
            'complaints',
            'responses',
            'witnessRecords',
        ]);

        return view('residents.show', compact('resident'));
    }

    public function edit(Resident $resident)
    {
        return view('residents.edit', compact('resident'));
    }

    public function update(
        ResidentRequest $request,
        Resident $resident
    ) {
        DB::transaction(function () use ($request, $resident) {
            $oldValues = $this->residentAuditSnapshot($resident);

            $resident->update($request->validated());
            $resident->refresh();

            AuditLogService::log(
                action: 'updated',
                module: 'People Directory',
                description: "Person record {$resident->resident_code} was updated.",
                auditable: $resident,
                oldValues: $oldValues,
                newValues: $this->residentAuditSnapshot($resident)
            );
        });

        return redirect()
            ->route('residents.show', $resident)
            ->with('success', 'Person record updated successfully.');
    }

    public function destroy(Resident $resident)
    {
        DB::transaction(function () use ($resident) {
            $oldValues = $this->residentAuditSnapshot($resident);
            $residentCode = $resident->resident_code;

            $resident->delete();

            AuditLogService::log(
                action: 'archived',
                module: 'People Directory',
                description: "Person record {$residentCode} was archived.",
                auditable: $resident,
                oldValues: $oldValues,
                newValues: [
                    'archived' => true,
                    'archived_at' => now()->toDateTimeString(),
                ]
            );
        });

        return redirect()
            ->route('residents.index')
            ->with('success', 'Person record archived successfully.');
    }

    public function restore(int $id)
    {
        $resident = Resident::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($resident) {
            $oldValues = $this->residentAuditSnapshot($resident);

            $resident->restore();
            $resident->refresh();

            AuditLogService::log(
                action: 'restored',
                module: 'People Directory',
                description: "Person record {$resident->resident_code} was restored.",
                auditable: $resident,
                oldValues: $oldValues,
                newValues: $this->residentAuditSnapshot($resident)
            );
        });

        return redirect()
            ->route('residents.index')
            ->with('success', 'Person record restored successfully.');
    }

    private function residentAuditSnapshot(Resident $resident): array
    {
        $attributes = $resident->getAttributes();

        unset(
            $attributes['created_at'],
            $attributes['updated_at']
        );

        return $attributes;
    }
}
