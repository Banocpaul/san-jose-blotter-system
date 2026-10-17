# Panel recommendations: system changes and paper revisions

Prepared from the five recommendations in the panel screenshot supplied on October 17, 2026. This is a revision and demonstration guide for the San Jose Blotter Management System. The San Jose manuscript has not been supplied in this conversation; this guide does not claim that its chapters have already been edited.

## Classification and response

| Panel recommendation | Classification | System response in this branch | Paper revision and evidence |
| --- | --- | --- | --- |
| Determine admin/user account delegation, tasks, and responsibilities. | Both; system permissions and written responsibility matrix. | Retains the five existing roles and assignment restrictions. Adds a Roles & Responsibilities screen, role guidance in account forms, account-change audit entries, and enforcement of active accounts/roles on private routes. | Describe the Captain as the system administrator; distinguish Secretary, Staff, Councilor, and Lupon duties. Include an access matrix, case-delegation flow, and screenshots showing permission boundaries. |
| Include the process of a local backup system. | Both; the existing backup feature primarily needed a clear operating procedure and paper description. | Retains encrypted local `.sjbackup` downloads, validation before restore, password confirmation, and the pre-restore safety copy. Adds the local custody/recovery steps to the backup screen. New records are included in backups. | Add the backup/restore process under system design, security, deployment, and the user manual. Explain the custodian, schedule, secure local/offline copies, separate recovery-key custody, recovery verification, and exclusions. |
| Determine the different cases within barangay jurisdiction. | Both; scope/legal-basis clarification plus a system assessment step. | Adds an official decision, authority findings, venue findings, reviewer, and review date. Pending, referral, legal-review, and stale approvals cannot authorize a new mediation referral or hearing. Incident categories do not automatically decide eligibility. | Explain which complaints the project records, which matters officials may process through settlement, which require referral, and how venue is assessed. Support the explanation with primary legal sources and the barangay's validated procedure. |
| The Data Privacy Act of 2012 should govern the blotter system. | Both; technical controls and organizational/documentary obligations. | Adds a privacy notice, private-page cache restrictions, active-session enforcement, login throttling, verified optional notification contacts, encrypted notification contact/message storage, and limited advisory content. Existing authorization, password hashing, auditing, and backup encryption remain. | Describe the actual data flow, purposes, lawful processing grounds, authorized recipients, retention/disposal policy, responsible privacy contact, access/correction process, and safeguards. Code changes alone do not establish full legal compliance. |
| Real-time notifications for parties involved in a case. | Primarily a system feature; the paper must describe and evaluate it. | Adds event-triggered email advisories for verified, opted-in complainants, respondents, and witnesses, with queue processing, failure status, retry, cancellation, and notification history. Hearing schedules and case workflow updates trigger advisories. | Add the notification workflow, channel, recipients, timing dependency, delivery states, privacy limits, and evaluation procedure. Demonstrate actual delivery with an approved test mailbox before claiming operational real-time capability. |

No recommendation should be treated as a paper-only substitute for required system behavior. Existing behavior can be demonstrated and documented instead of rebuilt. Classifications above are an interpretation of the panel's short notes; the panel did not label them by chapter or deliverable.

## Suggested manuscript passages

Adapt these paragraphs to the actual chapter structure and use the appropriate tense for the project's stage. They describe the proposed/revised implementation, not findings from a completed user evaluation.

### Account roles and delegation

The system defines five personnel roles: Barangay Captain, Barangay Secretary, Staff, Barangay Councilor, and Lupon Member. The Captain administers individual accounts and assigns roles according to authorized responsibilities. The Captain or Secretary assigns cases to active Councilors for assessment, while hearing access follows the assigned presiding officer. Staff handle intake and main record encoding. Councilors work on their current assignments, and Lupon members manage their assigned proceedings. Account, assignment, and workflow actions are recorded for accountability. The access matrix and delegation process distinguish administrative authority from routine encoding and case-handling duties.

### Local backup and recovery

The system provides an encrypted application-data backup that an authorized Captain can download to an approved local computer. The operating procedure requires secure storage, a separately controlled offline copy, separate custody of the recovery encryption key, and documented recovery checks. Before restoration, the system validates the file and presents a confirmation process requiring the Captain's password. A safety backup preserves the pre-restore data. The manuscript should distinguish the record backup from application source files, deployment settings, and uploaded files, which require separate recovery arrangements. The barangay must establish the responsible custodian and backup frequency.

### Coverage and jurisdiction review

Incident categories support recording and analysis; they do not independently establish barangay settlement authority. Before a case enters mediation, an authorized Captain or Secretary records a review of legal coverage and proper venue, together with the findings and reviewer details. The system supports decisions to proceed within settlement authority, refer the matter, or seek legal review. A change to the incident category, date, location, narrative, or recorded party residence information requires renewed approval before further scheduling. This workflow supports the official's documented assessment and does not replace an authoritative legal determination.

### Privacy and confidentiality

The revised design includes individual authentication, role and case restrictions, activity records, protected backup files, and limited email advisories. Notification addresses and message content are encrypted in the application database, and recipient addresses are masked in notification history. These measures support confidentiality and accountability. The manuscript must also state the barangay's lawful grounds for processing, approved retention and disposal arrangements, responsible privacy contact, and procedures for handling requests and incidents. Optional agreement to email advisories is separate from the lawful grounds for maintaining official records. Technical safeguards and organizational procedures must be assessed together.

### Party notifications

The system records a verified email address and the party's agreement before enabling optional case advisories. Hearing scheduling and relevant workflow updates create notification records, and a background worker submits eligible messages to the configured mail service after the transaction commits. The history distinguishes queued, unconfigured, retrying, failed, canceled, and submitted messages. Submission does not establish receipt or reading. Advisories contain a reference and a short operational update, exclude allegations and other parties' names, and supplement official notices and summons. The evaluation should measure the observed time from the event to receipt at an approved test mailbox.

## Local backup demonstration

1. Sign in as the Captain and open **Backup & Restore**. Generate and download a `.sjbackup` file. Confirm that it exists on the approved local computer.
2. Record its date, custodian, and approved storage location. Keep a second copy on approved encrypted offline storage. Keep the original deployment's `APP_KEY` in a separate protected recovery store; it is not inside the backup.
3. Maintain the matching application source/version, deployment settings, and any uploaded files separately. This export contains records, not a complete server image.
4. Test restoration in an isolated installation with the matching schema and key. Upload and validate the file, inspect counts, confirm with the Captain's password, and verify case-party/proceeding relationships.
5. Download and secure the pre-restore safety backup. Coordinate any later live restoration to avoid losing users' concurrent work. Do not use a restore demonstration on live records.
6. New backups include jurisdiction assessments, verified notification contacts, and notification history. A backup from before these features restores without those optional tables' records, so new jurisdiction review/contact verification may be necessary.
7. Queue jobs are excluded from backups. Restored queued notices do not automatically reconstruct a job. An authorized official can explicitly retry an eligible notice after checking that it is still appropriate. Preserve the key because notification fields are also encrypted with it.

## Notification setup and demonstration

No new production delivery credentials are committed. No real email has been sent by the automated checks.

1. Deploy this branch's migrations with the normal reviewed release process: `php artisan migrate --force`.
2. Configure a real supported mail service using the existing Laravel mail settings. Set `CASE_PARTY_NOTIFICATIONS_ENABLED=true` and set `CASE_PARTY_NOTIFICATIONS_MAILER` to that mailer's configured name. Defaults leave delivery disabled. `log`, `array`, and composite mailers are not accepted as delivery transports by this feature.
3. Refresh cached configuration if used, and run a supervised worker: `php artisan queue:work --queue=notifications,default --tries=3 --timeout=45`. Keep the queue connection asynchronous in production; `QUEUE_CONNECTION=database` is already the example default. Ensure its retry interval exceeds the worker timeout.
4. Open a case through **Case Management → Party Notifications**. Use only an approved test party and mailbox. Verify the address and record the party's optional agreement. Saving a contact does not send a message.
5. Record an appropriate jurisdiction review and schedule a test hearing through the existing case workflow. Verify the history entry, worker processing, and receipt in the test mailbox. Record actual latency; do not infer receipt from **Submitted**.
6. Test a stopped worker (queued), a transport failure (retrying/failed), and a disabled or replaced contact (old notices canceled). A worker must run promptly for near-real-time email submission. Provider delivery remains outside the application's timing guarantee.
7. No SMS provider or resident login portal is added in this branch. SMS requires a separately selected provider, credentials, cost arrangements, and recipient-verification workflow. Digital advisories do not replace required summons service.

## Evidence to include before final submission

- Role matrix and demonstrations of allowed/denied actions, including reassignment and deactivated accounts.
- A downloaded local backup, isolated restoration record, counts/relationship verification, and the approved custody/schedule procedure.
- A jurisdiction review demonstration covering approved, referral, legal-review, and changed-facts cases. Obtain the barangay/legal officer's validation of the operational decision rules.
- Approved privacy contact, processing basis, retention/disposal schedule, privacy procedure, and screenshots of implemented controls. Avoid a blanket “fully compliant” claim based on software features alone.
- Approved mailbox notification test with event, queue/submission, and actual receipt timestamps. Record failures and retries as well as successful cases.
- The actual San Jose manuscript for direct chapter edits; do not mix the M.A.P.S. manuscript with this project's paper.

## Primary references for the paper

- [Republic Act No. 10173 — National Privacy Commission](https://privacy.gov.ph/data-privacy-act/): processing principles, lawful grounds, rights, safeguards, and accountability.
- [Republic Act No. 7160 — Local Government Code](https://lawphil.net/statutes/repacts/ra1991/ra_7160_1991.html): especially Sections 407–409 for legal advice, settlement authority, and venue. Consider applicable amendments and special laws with the responsible official.
- [Republic Act No. 9262 — Supreme Court E-Library](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/22128): protection proceedings and restrictions on compromise/conciliation. Do not assume every family-related report can proceed through ordinary mediation.

## Automated verification

The branch adds regression coverage for jurisdiction authorization and stale findings; case-party membership and contact verification; notification confidentiality, revocation, failure/retry, and transaction rollback; active-account access and login throttling; and encrypted backup round trips with previous-format compatibility. Automated checks use fake mail delivery and are not evidence of successful external email delivery or a legal-compliance certification.
