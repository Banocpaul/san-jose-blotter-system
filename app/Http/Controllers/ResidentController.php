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
        $classification =
            $request
                ->string('classification')
                ->toString();

        /*
        |--------------------------------------------------------------------------
        | Current People Directory
        |--------------------------------------------------------------------------
        |
        | Archived / soft-deleted records are intentionally not exposed in the
        | client-facing People Directory. The client requested View, Add and
        | Edit only, with no Archive or Restore workflow.
        |
        */

        $query = Resident::query();

        if ($request->filled('search')) {
            $query->search(
                (string) $request->input('search')
            );
        }

        if (
            in_array(
                $classification,
                ['Resident', 'Non-Resident'],
                true
            )
        ) {
            $query->where(
                'classification',
                $classification
            );
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
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view(
            'residents.index',
            compact(
                'residents',
                'classification'
            )
        );
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
                'contact_number',
                'classification',
            ]);

        if ($id > 0) {
            $query->whereKey($id);
        } elseif ($term !== '') {
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

        /*
         * Blank search = full active People Directory.
         * This lets the picker open like a searchable dropdown on focus/click.
         */
        $people = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->when(
                $id > 0,
                fn ($peopleQuery) => $peopleQuery->limit(1)
            )
            ->get()
            ->map(fn (Resident $person) => [
                'id' => $person->id,
                'code' => $person->resident_code,
                'name' => $person->full_name,
                'contact' => $person->contact_number,
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

    /*
    |--------------------------------------------------------------------------
    | Audit Snapshot Helper
    |--------------------------------------------------------------------------
    */

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
