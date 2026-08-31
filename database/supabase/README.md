# Supabase migrations

Run SQL files manually in the Supabase SQL Editor after backing up the database.

## Reusable quizzes and class pages

1. Back up the Supabase project.
2. Run `2026_08_27_reusable_quizzes_and_class_pages.sql` once.
3. Deploy the application commit that contains the matching code.
4. Confirm a teacher can open **My Quizzes**, **Quiz Library**, and a classroom.

The migration copies existing `quiz_sessions` and `questions` into the new reusable
quiz tables. Existing session rows and results are retained.

To undo the database portion, first restore application code from before this
feature, then run `2026_08_27_reusable_quizzes_and_class_pages_rollback.sql`.
The rollback archives newly created quiz/customization records in tables whose
names start with `rollback_` before removing the new schema.

## Archived classes and single attempts

After the reusable-quiz migration, run
`2026_08_28_archived_classes_and_single_attempts.sql`. It adds reversible class
archiving and guarantees one stored result per student per class assignment.
Any older duplicate results are copied to
`rollback_duplicate_quiz_results_20260828` before they are removed.

To undo it, run
`2026_08_28_archived_classes_and_single_attempts_rollback.sql` before rolling
back the reusable-quiz migration. The duplicate-result backup table is retained
for verification and may be dropped manually after the rollback is confirmed.

## Scheduling, retakes, quiz governance, version restore, and scale

After the August 28 migration, run
`2026_08_29_scheduling_governance_and_scale.sql` before deploying the matching
application code. The application reads the new lifecycle, eligibility,
moderation, privacy, and suspension columns during normal requests, so the SQL
migration must be applied first.

This migration adds automatic assignment start/end dates, explicit per-student
eligibility, retained retake history, class-based quiz usage counts, verified
quiz prioritization, atomic version restoration, shared-library governance,
account suspension, audit events, and query indexes. Existing class members are
made eligible for open assignments. Existing results remain counted, while
completed assignments are frozen at their current attempt count. The earlier
draft’s per-student accommodation fields are removed when this migration is
rerun.

The migration does not require the older `rollback_*_20260827` or
`rollback_*_20260828` archive tables to still exist. If those archives were
already removed after verification, their final permission cleanup is skipped.
The file is safe to rerun when upgrading an earlier August 29 installation; the
rerun installs the current restore wrapper and reports the actual reason when a
quiz version cannot be restored.

To undo it, first restore the application code from before this feature, then
run `2026_08_29_scheduling_governance_and_scale_rollback.sql`. The rollback
keeps the currently counted result for each student and assignment, archives
additional retake results and governance records in tables whose names start
with `rollback_`, and restores the August 28 one-result behavior. Verify those
archive tables and a separate database backup before deleting either one.

## Quiz regression and browser-push hotfix

For databases where the August 29 migration was already run, execute
`2026_08_30_quiz_regression_and_push_hotfix.sql` once. It is a small,
standalone migration that reinstalls version restoration, adds shared-library
ranking indexes, and creates the protected browser-push subscription table. It
does not read any `rollback_*` archive table and is safe to rerun.

Use `2026_08_30_quiz_regression_and_push_hotfix_rollback.sql` to remove only
the push-subscription table and ranking indexes. Quiz version restoration is
retained because it belongs to the August 29 governance feature.

Then run `2026_08_30_assignment_usage_and_attempt_integrity.sql`. It refreshes
quiz usage from shared-library assignment events, repairs unapproved repeat
results so only the first is counted, ignores later unapproved inserts, and
prevents client upserts from overwriting a stored attempt. The paired
`2026_08_30_assignment_usage_and_attempt_integrity_rollback.sql` restores the
former usage and result-selection behavior without deleting result rows.

Then run `2026_08_30_shared_assignment_and_quiz_reports.sql`. It makes a
multi-class shared-quiz assignment one atomic database operation, records the
customized assignment grade on the session, and recomputes popularity from
shared-library assignment events. Assignment grades must match
the selected classes and never modify class grade levels. It also preserves
quiz and question snapshots for the dedicated Active, Reviewed, and Dismissed
report queues, including when an administrator later edits or deletes the quiz.

Use `2026_08_30_shared_assignment_and_quiz_reports_rollback.sql` only after
rolling the application back. The rollback stops if a preserved report points
to a quiz that has since been deleted, preventing accidental report loss.

If assigning a quiz or joining a class reports that
`class_member_accommodations` does not exist, run
`2026_08_30_remove_stale_accommodation_triggers.sql`. An early
August 29 draft installed assignment and class-join trigger functions that
read the former accommodations table. This standalone hotfix replaces both
function bodies, removes the obsolete eligibility column if it remains, and
backfills missing eligibility without deleting quizzes, classes, or results.

Then run
`2026_08_30_repeated_shared_class_uses_and_assignment_delete.sql`. Class Uses
will count each shared-library assignment event, so assigning the same source
quiz to the same class again after its earlier assignment ends adds another
use. Assignments made by the source quiz's own creator do not count. The file
also installs the atomic deletion used for waiting and active assignments;
deleting one shared-library assignment removes one use, while ended assignment
history remains protected. The migration corrects existing counters and is
safe to rerun.

## Notifications and account security

After all August 30 files above, run
`2026_08_31_notifications_and_account_security.sql`. It creates the in-app
notification center and event triggers for teacher verification, class roster
changes, quiz assignments and scheduling, retakes and excuses, submissions,
shared-quiz use, moderation, quiz verification, and completed Auth security
changes. It also installs idempotent 5-minute quiz-start and 30-minute quiz-due
reminders and keeps `profiles.email` synchronized after Supabase confirms an
Auth email change.

The application remains usable before this migration is applied, but the bell
will stay empty and email-change completion will not synchronize the profile
email. Use `2026_08_31_notifications_and_account_security_rollback.sql` to
remove only this notification/security layer.

Copy the hosted Auth email templates and enable the two security notification
emails by following `supabase/email-templates/README.md`.

Then run `2026_08_31_notifications_delivery_channels.sql`. It adds a protected,
retryable delivery outbox. The requested application events are sent as
designed Laravel emails: teacher application receipt and decision, account
suspension/restoration, quiz assignment/availability, retake, excuse,
submission receipts for the initial attempt and each teacher-authorized retake,
and removal from a class. Other bell events are routed to targeted Web Push.
Completed password and email-address changes stay in the bell but do not create
Web Push because Supabase Auth already sends their security emails. The original
all-admin teacher-registration and quiz-report pushes are deliberately excluded
from the outbox because their existing immediate broadcasts remain in place.

Unapproved repeat result inserts are still ignored by
`2026_08_30_assignment_usage_and_attempt_integrity.sql`. A submission-receipt
email is queued for every result row the database successfully accepts. The
initial attempt receives one receipt, and each teacher grant permits exactly one
additional immutable retake result and receipt. Use
`2026_08_31_notifications_delivery_channels_rollback.sql` to remove only the
delivery outbox and restore the prior notification function bodies.

Then run `2026_08_31_notification_delivery_policy_followup.sql`. This small
idempotent follow-up also upgrades projects that already installed the first two
August 31 migrations: it removes queued password/email security pushes,
reclassifies unsent authorized-retake receipts as email, and rearms premature
due reminders for the 30-minute window. Its paired `_rollback.sql` file restores
the former delivery policy but cannot retract an alert that was already sent.

Run `2026_08_31_quiz_starting_soon_5_minutes.sql` last. It upgrades existing
installations to the five-minute quiz-start reminder window and removes earlier
start reminders so eligible quizzes can be rearmed at the correct time. Alerts
already delivered by the browser cannot be retracted.

### Configure application email delivery

Supabase Auth continues to send sign-up, recovery, change-email, password
changed, and email-address-changed messages. The new event emails are not
Supabase Auth templates, so Laravel sends them through `MAIL_*`. Configure the
same custom SMTP provider credentials in both Supabase Auth and the deployed
Laravel environment when one sender/provider should handle all mail:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.provider.example
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=notifications@your-domain.example
MAIL_FROM_NAME="Math MetaVerse"
```

Keep `APP_URL` equal to the deployed HTTPS root URL; email buttons are built
from it. Laravel Cloud runs the schedule declared in `routes/console.php`
automatically. For a self-hosted server, invoke Laravel's scheduler every minute:

```bash
php artisan schedule:run
```

To verify the outbox manually after deployment, run:

```bash
php artisan notifications:deliver --limit=50
```

### Enable browser push alerts

The push alert appears through the browser/operating system even when the
MathVerse tab is closed. Students and teachers enable it in **Account
Security**; administrators enable it on the Mainframe. Permission is per
browser/device. Production must use HTTPS. On iPhone/iPad, install MathVerse to
the Home Screen before enabling alerts; the included web manifest supports that
browser requirement.

1. Generate one VAPID key pair from the project root:

   ```bash
   node scripts/generate-vapid-keys.mjs
   ```

2. Generate a separate server-to-server secret:

   ```bash
   node scripts/generate-admin-push-secret.mjs
   ```

3. Set `WEB_PUSH_PUBLIC_KEY` and `ADMIN_PUSH_SECRET` in the Laravel environment.
   Use the generated VAPID public key and random server secret respectively.
   `WEB_PUSH_FUNCTION_URL` is optional; when omitted, Laravel uses
   `{SUPABASE_URL}/functions/v1/send-admin-push`.
4. Set the Supabase Edge Function secrets. `ADMIN_PUSH_SECRET` must exactly
   match the Laravel value:

   ```bash
   supabase secrets set VAPID_PUBLIC_KEY="..." VAPID_PRIVATE_KEY="..." VAPID_SUBJECT="mailto:admin@example.com" ADMIN_PUSH_SECRET="..."
   ```

5. Deploy the included Edge Function. The Supabase legacy JWT check is disabled
   because this is a service-to-service call; the function validates the
   dedicated `ADMIN_PUSH_SECRET` before doing any work:

   ```bash
   supabase functions deploy send-admin-push --no-verify-jwt
   ```

The VAPID private key stays only in Supabase secrets. Both private values stay
out of browser JavaScript and Git, and `ADMIN_PUSH_SECRET` must never be shown
to users or included in screenshots.
