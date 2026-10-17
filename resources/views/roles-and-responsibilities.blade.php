@extends('layouts.app')
@section('title', 'Roles & Responsibilities')
@section('page-title', 'Roles & Responsibilities')
@section('content')
<h3 class="mb-2">Account Roles &amp; Delegation</h3>
<p class="text-muted mb-4">Each official uses an individual account. The Barangay Captain assigns account roles; the Captain or Secretary delegates case assessment to an active Councilor and schedules proceedings with an assigned presiding officer.</p>
<div class="card shadow-sm mb-4"><div class="card-body table-responsive">
    <table class="table align-middle mb-0"><thead><tr><th>Role</th><th>Responsibilities</th><th>Access boundary</th></tr></thead><tbody>
        <tr><th>Barangay Captain<br><span class="small fw-normal text-muted">System administrator</span></th><td>Manage accounts and roles, delegate cases, review jurisdiction, oversee proceedings and reports, inspect audit logs, and create or restore backups.</td><td>System oversight; restoration requires password confirmation.</td></tr>
        <tr><th>Barangay Secretary</th><td>Manage people and case records, assign Councilors, record jurisdiction reviews, schedule hearings, manage verified notification contacts, maintain proceedings and reports.</td><td>Operational records; cannot administer accounts or use backup and restore.</td></tr>
        <tr><th>Staff / Encoder</th><td>Encode people and incidents, update main case information, and view the case analytics available to staff.</td><td>Cannot delegate cases, approve jurisdiction, schedule hearings, manage notifications, administer accounts, or restore backups.</td></tr>
        <tr><th>Barangay Councilor</th><td>Assess currently assigned cases, record investigation notes, manage witnesses, and submit an assessment for mediation scheduling after jurisdiction approval.</td><td>Current assignments only; cannot assign themselves or schedule hearings.</td></tr>
        <tr><th>Lupon Member</th><td>View cases linked to their assigned hearings and manage those hearings' notices, attendance, and outcomes.</td><td>Assigned hearings only; cannot browse the People Directory or administer accounts.</td></tr>
    </tbody></table>
</div></div>
<div class="card shadow-sm"><div class="card-body">
    <h5>Delegating a Case</h5>
    <ol class="mb-3">
        <li>The Staff, Secretary, or Captain records the complaint and the parties.</li>
        <li>The Captain or Secretary starts assessment and records the legal-coverage and venue review.</li>
        <li>The Captain or Secretary assigns an active Councilor, including task instructions when needed.</li>
        <li>The assigned Councilor records findings and submits the case for mediation scheduling when appropriate.</li>
        <li>The Secretary or Captain records the hearing date, time, venue, and presiding officer. Assigned hearing access follows that officer.</li>
    </ol>
    <p class="mb-0 small text-muted">Reassignment ends the earlier Councilor's current access. Account deactivation blocks existing sessions. Assignment and review history remain available to authorized officials.</p>
</div></div>
@endsection
