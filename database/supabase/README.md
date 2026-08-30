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
quiz usage as the number of distinct assigned classes, repairs unapproved
repeat results so only the first is counted, ignores later unapproved inserts,
and prevents client upserts from overwriting a stored attempt. The paired
`2026_08_30_assignment_usage_and_attempt_integrity_rollback.sql` restores the
former usage and result-selection behavior without deleting result rows.

### Enable administrator browser push alerts

The push alert appears through the browser/operating system even when the
MathVerse tab is closed. Each administrator must click **Enable Browser
Alerts** once on the Mainframe and allow notification permission.

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
