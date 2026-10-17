<?php

namespace App\Http\Controllers;

use App\Models\BlotterCase;
use App\Models\CaseNotificationContact;
use App\Models\CaseNotificationDelivery;
use App\Services\AuditLogService;
use App\Services\CaseNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CaseNotificationController extends Controller
{
    public function index(BlotterCase $blotter, CaseNotificationService $notifications): View
    {
        $this->authorize('manageWorkflow', $blotter);
        $parties = collect();
        foreach (['complainant' => 'complainants', 'respondent' => 'respondents', 'witness' => 'witnesses'] as $type => $relation) {
            foreach ($blotter->{$relation} as $party) {
                $parties->push(['key' => $type.':'.$party->id, 'label' => ucfirst($type).' — '.trim($party->first_name.' '.$party->last_name)]);
            }
        }
        $contacts = $blotter->notificationContacts()->get();
        $deliveries = CaseNotificationDelivery::with('contact')->whereHas('contact',
            fn ($query) => $query->where('blotter_case_id', $blotter->id))->latest()->paginate(15);

        return view('cases.notifications', ['case' => $blotter, 'parties' => $parties,
            'contacts' => $contacts, 'deliveries' => $deliveries, 'configured' => $notifications->isConfigured()]);
    }

    public function store(Request $request, BlotterCase $blotter): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        $data = $request->validate([
            'party' => ['required', 'string', 'regex:/^(complainant|respondent|witness):[0-9]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'contact_verified' => ['accepted'],
            'notification_consent' => ['accepted'],
        ]);
        [$type, $id] = explode(':', $data['party'], 2);
        $relation = ['complainant' => 'complainants', 'respondent' => 'respondents', 'witness' => 'witnesses'][$type];
        $blotter->{$relation}()->findOrFail($id);
        DB::transaction(function () use ($blotter, $data, $type, $id): void {
            $contact = $blotter->notificationContacts()->updateOrCreate(['party_type' => $type, 'party_id' => $id], [
                'email' => trim($data['email']), 'revision' => (string) Str::uuid(), 'enabled' => true,
                'verified_by' => auth()->id(), 'consent_recorded_at' => now(),
            ]);
            $contact->deliveries()->whereIn('status', ['queued', 'retrying', 'failed', 'not_configured'])
                ->update(['status' => 'canceled']);
            AuditLogService::log(action: 'notification_contact_verified', module: 'Case Notifications',
                description: 'Optional email updates enabled after recipient verification and agreement.', auditable: $blotter,
                newValues: ['contact_id' => $contact->id, 'party_type' => $type, 'party_id' => (int) $id]);
        });

        return back()->with('success', 'Verified contact saved for future updates. No email was sent by this action.');
    }

    public function disable(BlotterCase $blotter, CaseNotificationContact $contact): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        abort_unless($contact->blotter_case_id === $blotter->id, 404);
        DB::transaction(function () use ($contact, $blotter): void {
            $contact->update(['enabled' => false, 'revision' => (string) Str::uuid()]);
            $contact->deliveries()->whereIn('status', ['queued', 'retrying', 'failed', 'not_configured'])
                ->update(['status' => 'canceled']);
            AuditLogService::log(action: 'notification_contact_disabled', module: 'Case Notifications',
                description: 'Optional email updates disabled.', auditable: $blotter, newValues: ['contact_id' => $contact->id]);
        });

        return back()->with('success', 'Optional email updates disabled and pending notices canceled.');
    }

    public function retry(BlotterCase $blotter, CaseNotificationDelivery $delivery, CaseNotificationService $notifications): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        abort_unless($delivery->contact?->blotter_case_id === $blotter->id, 404);
        abort_unless($notifications->isConfigured(), 422, 'Configure the mail service before retrying.');
        abort_unless($delivery->contact->enabled && $delivery->contact_revision === $delivery->contact->revision, 422);
        $eligible = in_array($delivery->status, ['failed', 'not_configured'], true)
            || ($delivery->status === 'queued' && $delivery->updated_at->lt(now()->subMinutes(5)));
        abort_unless($eligible, 422, 'This notification is already processing or cannot be retried.');
        $delivery->update(['status' => 'queued']);
        $notifications->dispatch($delivery);
        AuditLogService::log(action: 'notification_retried', module: 'Case Notifications',
            description: 'A case notification was queued for retry.', auditable: $blotter,
            newValues: ['delivery_id' => $delivery->id]);

        return back()->with('success', 'Notification queued for retry. Submission does not confirm receipt.');
    }
}
