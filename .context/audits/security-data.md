# Security audit: server data and privacy

Date: 2026-10-10. Baseline: `main`, `222a60ad9587c43a04fc6a1d9d8992cf148860f1`; working tree reviewed, including untracked domain files (209 Git status entries at inventory time). Review-only: no application changes, real records, real provider calls, or external publication. Root agent coordinates serial database verification. Report is not ignored by Git; contains no private payloads or credentials.

## Findings

### D1 — Private garage data remains in development diagnostics (medium by itself; high when combined with exposed unauthenticated Telescope)

`app/Http/Middleware/ProtectSensitiveData.php:21-40` excludes regular motorcycle/maintenance endpoints and `/api/v1/user` from suppression. `app/Providers/TelescopeServiceProvider.php:28-35` records all entries in local environment. Installed `vendor/laravel/telescope/src/Watchers/RequestWatcher.php:47-58` records request payload/session/response; `Watchers/QueryWatcher.php:39-44` records interpolated SQL. Header/password masking does not mask maintenance notes, titles, costs or identity response fields. These ordinary authenticated requests therefore duplicate private data into Telescope. The root review owns independent proof of local unauthenticated dashboard reachability and network exposure.

Prerequisites: local diagnostics enabled and a legitimate private request; attacker needs diagnostics access (currently potentially merely network reachability). Not a production tenant-authorization bypass: the app registers these development providers only in local environment (`app/Providers/AppServiceProvider.php:47-58`).

Proposed fix: protect all authenticated personal API routes from body/query/model diagnostics, retain safe status/timing logs, require explicit dashboard authentication in local too. Validate using a synthetic private maintenance note and identity response, asserting zero diagnostic content capture. Existing privacy tests cover secret routes only.

### D2 — Database failure messages can copy private prose into ordinary application logs (medium)

`app/Logging/RedactSensitiveData.php:14,34-39,62-76` retains exception/log message text unless credential patterns match. `vendor/laravel/framework/src/Illuminate/Database/QueryException.php:85-91` interpolates bound values into its message unless masked; `Connection.php:864` reads `mask_bindings_in_exception_messages`, absent in all configured connections (`config/database.php`). `SaveMaintenanceRecord` and `ConfirmInvoiceDraft` can fail while inserting plaintext notes/workshop contacts/cost descriptions. Laravel reports unexpected database exceptions; the existing processor does not remove this private prose. Invoice HTTP Telescope suppression does not disable normal logging.

Validation: dependency-only synthetic QueryException containing a fake prose marker passed through the actual RedactSensitiveData processor. Both sanitized message and exception context still contained the marker. Only booleans were printed; no database access or real payload. This proves the redaction gap, not that a real request has already leaked.

Prerequisites: unexpected database failure on a personal-data query plus access to logs/Pail/forwarded diagnostics. Proposed fix: enable Laravel's supported `mask_bindings_in_exception_messages` for connections and avoid retaining arbitrary private exception bodies. Preserve class/location/safe diagnostics. Test injected QueryExceptions and actual framework logging paths. AI provider failures already use safe wrappers/diagnostics and are not implicated here.

### D3 — Upload capacity is unbounded per account (medium availability hardening)

`app/Garage/Actions/Invoices/UploadInvoice.php:38-40` allows 10 MiB per document; `routes/api.php:69-70` allows 20 uploads/minute. Successful files remain stored indefinitely; only rollback cleanup calls delete (`UploadInvoice.php:89-90`). No aggregate account/storage quota, duplicate suppression, or successful-document cleanup exists. A verified account with garage write ability can keep growing persistent storage (up to roughly 200 MiB/minute under this limit) and exhaust the server volume.

Prerequisites: verified valid account or its compromised token; disk capacity finite. No load attack was performed. Suggested bounded storage/document quota with atomic accounting and recoverable deletion; maintain the useful upload rate limit. This is independent of whether indefinite maintenance-history retention is desirable.

## Remediation evidence (2026-10-10)

- **D1 / SEC02 implemented:** Telescope and Horizon authentication callbacks now require an authenticated, verified administrator in every environment, including local. Personal `/api/v1/*` traffic is suppressed before diagnostics recording and receives private/no-store responses; only the public status and auth configuration endpoints remain diagnostic-visible. Regression coverage exercises guests, ordinary users, unverified admins and verified admins against both dashboards, plus real identity/garage reads and a successful private maintenance write. Latest Telescope privacy run: **14 tests / 57 assertions passed**.
- **D2 / SEC07 implemented:** every configured SQL connection enables Laravel's supported binding masking. Application/Pail log processing omits exception message bodies and previous exceptions, retains class/code/file/line and traces without arguments, and replaces raw SQL exception message strings. A synthetic unmasked QueryException is reported through Laravel's actual exception handler into the configured log processor; private bound prose and driver messages are absent. Existing credential/object redaction remains. Logging regressions are part of the focused data/privacy run below.
- **D3 / SEC10 implemented:** configurable per-account capacity defaults to **100 documents / 100 MiB** (`garage.invoice_storage`, `INVOICE_STORAGE_MAX_DOCUMENTS`, `INVOICE_STORAGE_MAX_BYTES`). The upload Action locks the owner row, rechecks ownership/verification and reads existing evidence sizes with current locks before storing bytes. Capacity includes earlier documents across motorcycles; there is no counter migration or automatic history deletion. Failed storage/activity writes roll back document rows and delete the generated path. HTTP and direct Action callers share enforcement. Focused privacy/logging/invoice tests: **39 tests / 254 assertions passed** before the successful-maintenance-write test was expanded. A separate committed-fixture, second-MySQL-connection `NOWAIT` regression proves the Action holds the account allocation lock during file storage: **1 test / 3 assertions passed**.

Existing diagnostic rows and earlier log files are historical data; preventing new capture does not erase prior capture. Local diagnostic pruning is coordinated by the root agent. Infrastructure/retention decisions listed below remain open and are not implied solved by these changes.

## Privacy decisions before external users

- Originals are plaintext files on a private local disk (`config/filesystems.php:31-37`); keys and drafts have encrypted casts, but confirmed records/workshop data are ordinary plaintext database fields. Filesystem/backup encryption and host access remain infrastructure decisions, not proven here. Private visibility is not encryption.
- No implemented user/document erasure workflow. Foreign keys intentionally restrict deleting users with bikes/evidence (`database/migrations/2026_10_08_201637_create_motorcycles_table.php:16`, `2026_10_09_115125_create_maintenance_domain_tables.php:13`). Define explicit deletion/export, backup expiry and orphan-file cleanup before promising erasure. Do not auto-prune valuable historical invoices without a product decision.
- Activity retains selected mileage/cost/model fields for 365 days and masks maintenance prose to a changed flag; daily prune is scheduled (`Models/UserActivity.php:59-65`, `routes/console.php:15`). Actual scheduler uptime/backups are outside this proof.
- Full attachments are sent inline to the chosen provider. OpenAI transport explicitly sets `store:false`; no SDK provider file uploads/vector stores were found on this flow. That is not proof of zero retention/training under provider contracts. Anthropic/Gemini do not share an OpenAI storage switch. No live provider-policy/legal review was performed.
- File MIME/size checks do not prove malware-free PDFs or remove photograph EXIF. Downloads are attachments with generated names/no-sniff; no server PDF execution/parser is used on this path. Document disarm/scanning/metadata minimization is a future threat-model choice, not a proven RCE.

## Rejected candidates and retained controls

- Tenant IDOR: controllers require exact owner IDs, nested maintenance routes are scoped, invoice controller additionally checks import owner and motorcycle pairing. Authorized Actions validate actor/ownership; evidence attachment enforces same owner even for admin; fulfilment enforces same motorcycle. No bypass found in reviewed reachable adapters.
- Invoice path traversal/public downloads: upload uses framework-generated path, original filename is metadata only; invoice disk is private outside public storage and `serve:false`; controller checks owner and supplies safe generated download name. API Resources omit path/hash/attempt ID.
- BYOK theft/global fallback: credentials are owner-relation selected, hidden + encrypted, provider/model/key validated; URLs are server allowlisted. No caller-controlled destination URL reaches the provider. Ai state is flushed after every operation. Response shows only hint/provider/model.
- Prompt injection into tools/cross-tenant writes: extractor has no tools, only bounded schema; instructions treat documents as untrusted. Extracted output must pass validation and explicit owner review/confirmation before domain writes. Injection may still fabricate plausible facts; review is necessary and model instructions are not a security boundary.
- Queue/failed jobs AI payload leak: ExtractInvoice constructor contains only IDs; provider failures are consumed into safe state, unexpected exceptions become AiOperationException without retaining original message/chain. Safe diagnostics remove trace arguments; obsolete attempts cannot overwrite a newer result. No concrete AI credential/document failed-job leak found. Other future jobs require the same review.
- Sensitive exception-response headers: ordinary route exceptions are rendered inside Illuminate Routing Pipeline slices, so `$next` returns rendered response and protected middleware adds no-store. Recording was stopped before route failure and remains stopped. A catastrophic handler failure is outside the normal guarantee; no concrete bypass established.
- Admin cross-user access is intentional and limited by resource/policy admin checks; operations retain actor vs owner attribution. No client-supplied owner ID is accepted in ordinary HTTP save operations.

## Verification and limits

Reviewed existing behavioral tests for invoice ownership/private upload/download, scopes, MIME/size rejection, encrypted draft/key persistence, provider-safe errors, owner credential reuse isolation, stale attempts, activity minimization/retention, diagnostics secret suppression and log processors. Parent runs the serial server suite and owns its results; do not infer passes from static inspection here.

No real invoice payloads, stored credentials, log contents or queue payloads were dumped. No paid/live AI call, tenant enumeration, DoS, document exploit or real-data mutation was attempted. Review establishes bounded evidence, not absence of all vulnerabilities. Auth/social and frontend transports are other reviewers' scopes. Third-party packages were inspected only at relevant source seams; not independently audited wholesale.

## File coverage ledger

Paths below are relative to `apps/server`. Reviewed means manual complete source read for the security/privacy contracts; it does not mean exhaustive correctness proof.

- Reviewed: `app/Activity/Actions/GetUserActivity.php`
- Reviewed: `app/Activity/Actions/RecordUserActivity.php`
- Reviewed: `app/Activity/Enums/ActivityEvent.php`
- Reviewed: `app/Activity/Exceptions/ActivityRecordingException.php`
- Reviewed: `app/Activity/Filament/Resources/UserActivities/Pages/ManageUserActivities.php`
- Reviewed: `app/Activity/Filament/Resources/UserActivities/UserActivityResource.php`
- Reviewed: `app/Activity/Policies/UserActivityPolicy.php`
- Reviewed: `app/Activity/Providers/ActivityServiceProvider.php`
- Reviewed: `app/Ai/Actions/RemoveAiSettings.php`
- Reviewed: `app/Ai/Actions/SaveAiSettings.php`
- Reviewed: `app/Ai/Actions/TestAiConnection.php`
- Reviewed: `app/Ai/Agents/ConnectionCheck.php`
- Reviewed: `app/Ai/Agents/InvoiceExtractor.php`
- Reviewed: `app/Ai/Exceptions/AiInvoiceExtractionException.php`
- Reviewed: `app/Ai/Exceptions/AiOperationException.php`
- Reviewed: `app/Ai/Http/Controllers/AiSettingsController.php`
- Reviewed: `app/Garage/Actions/AttachMaintenanceEvidence.php`
- Reviewed: `app/Garage/Actions/FulfilMaintenanceTaskOccurrence.php`
- Reviewed: `app/Garage/Actions/Invoices/ConfirmInvoiceDraft.php`
- Reviewed: `app/Garage/Actions/Invoices/NormalizeExtractedInvoice.php`
- Reviewed: `app/Garage/Actions/Invoices/SaveInvoiceDraft.php`
- Reviewed: `app/Garage/Actions/Invoices/StartInvoiceExtraction.php`
- Reviewed: `app/Garage/Actions/Invoices/UploadInvoice.php`
- Reviewed: `app/Garage/Actions/Invoices/ValidateInvoiceDraft.php`
- Reviewed: `app/Garage/Actions/SaveMaintenanceRecord.php`
- Reviewed: `app/Garage/Actions/SaveMotorcycle.php`
- Reviewed: `app/Garage/Enums/EvidenceKind.php`
- Reviewed: `app/Garage/Enums/HistoryOrigin.php`
- Reviewed: `app/Garage/Enums/MaintenanceActionType.php`
- Reviewed: `app/Garage/Enums/MaintenanceOccurrenceStatus.php`
- Reviewed: `app/Garage/Enums/MaintenancePerformer.php`
- Reviewed: `app/Garage/Enums/MaintenanceRuleSource.php`
- Reviewed: `app/Garage/Enums/MaintenanceScheduleKind.php`
- Reviewed: `app/Garage/Enums/MileageUnit.php`
- Reviewed: `app/Garage/Enums/PlanStatus.php`
- Reviewed: `app/Garage/Enums/ReadingCertainty.php`
- Reviewed: `app/Garage/Exceptions/GarageInvoiceException.php`
- Reviewed: `app/Garage/Exceptions/GarageInvoiceStorageException.php`
- Reviewed: `app/Garage/Exceptions/GarageMaintenanceException.php`
- Reviewed: `app/Garage/Filament/GarageValidation.php`
- Reviewed: `app/Garage/Filament/Resources/MaintenanceRecords/MaintenanceRecordResource.php`
- Reviewed: `app/Garage/Filament/Resources/MaintenanceRecords/Pages/CreateMaintenanceRecord.php`
- Reviewed: `app/Garage/Filament/Resources/MaintenanceRecords/Pages/EditMaintenanceRecord.php`
- Reviewed: `app/Garage/Filament/Resources/MaintenanceRecords/Pages/ListMaintenanceRecords.php`
- Reviewed: `app/Garage/Filament/Resources/MaintenanceRecords/Schemas/MaintenanceRecordForm.php`
- Reviewed: `app/Garage/Filament/Resources/MaintenanceRecords/Tables/MaintenanceRecordsTable.php`
- Reviewed: `app/Garage/Filament/Resources/Motorcycles/MotorcycleResource.php`
- Reviewed: `app/Garage/Filament/Resources/Motorcycles/Pages/CreateMotorcycle.php`
- Reviewed: `app/Garage/Filament/Resources/Motorcycles/Pages/EditMotorcycle.php`
- Reviewed: `app/Garage/Filament/Resources/Motorcycles/Pages/ListMotorcycles.php`
- Reviewed: `app/Garage/Filament/Resources/Motorcycles/Schemas/MotorcycleForm.php`
- Reviewed: `app/Garage/Filament/Resources/Motorcycles/Tables/MotorcyclesTable.php`
- Reviewed: `app/Garage/Http/Controllers/InvoiceImportController.php`
- Reviewed: `app/Garage/Http/Controllers/MaintenanceRecordController.php`
- Reviewed: `app/Garage/Http/Controllers/MotorcycleController.php`
- Reviewed: `app/Garage/Http/Resources/InvoiceImportResource.php`
- Reviewed: `app/Garage/Http/Resources/MaintenanceRecordResource.php`
- Reviewed: `app/Garage/Http/Resources/MotorcycleResource.php`
- Reviewed: `app/Garage/Jobs/ExtractInvoice.php`
- Reviewed: `app/Garage/Policies/MaintenanceRecordPolicy.php`
- Reviewed: `app/Garage/Policies/MotorcyclePolicy.php`
- Reviewed: `app/Garage/Providers/GarageServiceProvider.php`
- Reviewed: `app/Http/Middleware/AddLogContext.php`
- Reviewed: `app/Http/Middleware/ProtectSensitiveData.php`
- Reviewed: `app/Logging/ConfigureLogging.php`
- Reviewed: `app/Logging/RedactSensitiveData.php`
- Reviewed: `app/Logging/RedactingPailHandler.php`
- Reviewed: `app/Models/AiCredential.php`
- Reviewed: `app/Models/EvidenceDocument.php`
- Reviewed: `app/Models/InvoiceImport.php`
- Reviewed: `app/Models/MaintenanceAction.php`
- Reviewed: `app/Models/MaintenanceCostItem.php`
- Reviewed: `app/Models/MaintenanceFulfilment.php`
- Reviewed: `app/Models/MaintenanceInvoice.php`
- Reviewed: `app/Models/MaintenanceInvoiceItem.php`
- Reviewed: `app/Models/MaintenancePlan.php`
- Reviewed: `app/Models/MaintenanceRecord.php`
- Reviewed: `app/Models/MaintenanceTask.php`
- Reviewed: `app/Models/MaintenanceTaskOccurrence.php`
- Reviewed: `app/Models/MileageReading.php`
- Reviewed: `app/Models/Motorcycle.php`
- Reviewed: `app/Models/UserActivity.php`
- Reviewed: `app/Models/Workshop.php`
- Reviewed: `app/Providers/AppServiceProvider.php`
- Reviewed: `app/Providers/TelescopeServiceProvider.php`
- Reviewed: `bootstrap/app.php`
- Reviewed: `config/ai_byok.php`
- Reviewed: `config/database.php`
- Reviewed: `config/filesystems.php`
- Reviewed: `config/logging.php`
- Reviewed: `config/queue.php`
- Reviewed: `config/telescope.php`
- Reviewed: `routes/api.php`
- Reviewed: `routes/console.php`
- Reviewed: `database/migrations/2026_10_08_201637_create_motorcycles_table.php`
- Reviewed: `database/migrations/2026_10_08_201638_create_maintenance_records_table.php`
- Reviewed: `database/migrations/2026_10_08_213243_create_ai_credentials_table.php`
- Reviewed: `database/migrations/2026_10_09_005857_create_user_activities_table.php`
- Reviewed: `database/migrations/2026_10_09_011948_create_invoice_import_tables.php`
- Reviewed: `database/migrations/2026_10_09_013652_expand_invoice_import_draft_storage.php`
- Reviewed: `database/migrations/2026_10_09_115125_create_maintenance_domain_tables.php`
- Reviewed: `database/migrations/2026_10_09_115126_backfill_maintenance_evidence_and_costs.php`
- Reviewed: `tests/Feature/InvoiceImportTest.php`
- Reviewed: `tests/Feature/InvoiceExtractionTest.php`
- Reviewed: `tests/Feature/AiSettingsTest.php`
- Reviewed: `tests/Feature/ApplicationLoggingTest.php`
- Reviewed: `tests/Feature/TelescopePrivacyTest.php`
- Reviewed: `tests/Feature/UserActivityTest.php`
- Partial: `tests/Feature/GarageTest.php`, `tests/Feature/MaintenanceDomainTest.php`: inventoried security cases; root executes, not full manual test-body review here.
- Delegated/excluded: `app/Models/User.php`, account auth providers/controllers/policies/migrations, infrastructure exposure, client transports/UI: other reviewer scopes; only referenced dependency seams.
- Third-party seam only: Laravel QueryException/Connection/Routing Pipeline, Telescope recording/watchers, Laravel AI OpenAI/Anthropic/Gemini attachment mapping and OpenAI request builder; arbitrary remote-attachment branches are unused by application.
- Excluded: factories/seeders and binary sample documents (not production adapters); dependency/generated/editor metadata; no pending application file in the explicitly inventoried domain scope.
