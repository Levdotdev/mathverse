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
