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
