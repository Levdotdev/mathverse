<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentClassController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherClassController;
use App\Http\Controllers\TeacherQuizController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminQuizController;
use App\Http\Controllers\AdminPushController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LearningHubController;

Route::pattern('id', '[0-9a-fA-F-]{36}');
Route::pattern('classId', '[0-9a-fA-F-]{36}');
Route::pattern('studentId', '[0-9a-fA-F-]{36}');
Route::pattern('sessionId', '[0-9a-fA-F-]{36}');
Route::pattern('reportId', '[0-9a-fA-F-]{36}');
Route::pattern('questionId', '[0-9a-fA-F-]{36}');
Route::pattern('version', '[1-9][0-9]*');

// Auth routes
Route::get('/',       [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::get('/reset-password', function () { return view('auth.reset'); });
Route::post('/update-password', [AuthController::class, 'updatePassword']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth.supabase')->group(function () {
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/change-email', [AuthController::class, 'changeEmail']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::post('/push-subscription', [AdminPushController::class, 'store']);
    Route::delete('/push-subscription', [AdminPushController::class, 'destroy']);
});

// Student routes
Route::middleware('auth.supabase:student')->group(function () {
    Route::get('/student/dashboard', [StudentController::class, 'index']);
    Route::get('/student/learning-hub', [LearningHubController::class, 'index']);
    Route::get('/student/learning-hub/practice', [LearningHubController::class, 'practice']);
    Route::post('/student/learning-hub/questions/next', [LearningHubController::class, 'nextQuestion'])
        ->middleware('throttle:120,1');
    Route::post('/student/learning-hub/questions/{questionId}/hint', [LearningHubController::class, 'revealHint'])
        ->middleware('throttle:60,1');
    Route::post('/student/learning-hub/questions/{questionId}/answer', [LearningHubController::class, 'submitAnswer'])
        ->middleware('throttle:120,1');
    Route::post('/student/classes/join', [StudentClassController::class, 'join']);
    Route::get('/student/classes/{id}', [StudentClassController::class, 'show']);
    Route::get('/student/classes/{classId}/quizzes/{sessionId}/review', [StudentClassController::class, 'review']);
    Route::get('/student/report/progress', [StudentController::class, 'reportProgress']);
    Route::post('/student/profile', [StudentController::class, 'updateProfile']);
});

// Teacher routes
Route::middleware('auth.supabase:teacher')->group(function () {
    Route::get('/teacher/dashboard', [TeacherController::class, 'index']);

    Route::get('/teacher/quizzes', [TeacherQuizController::class, 'index']);
    Route::get('/teacher/quiz-library', [TeacherQuizController::class, 'library']);
    Route::get('/teacher/quiz-library/{id}/review', [TeacherQuizController::class, 'review']);
    Route::post('/teacher/quiz-library/{id}/assign', [TeacherQuizController::class, 'assignShared']);
    Route::post('/teacher/quiz-library/{id}/bookmark', [TeacherQuizController::class, 'toggleBookmark']);
    Route::post('/teacher/quiz-library/{id}/rating', [TeacherQuizController::class, 'rate']);
    Route::post('/teacher/quiz-library/{id}/report', [TeacherQuizController::class, 'report']);
    Route::post('/teacher/quizzes', [TeacherQuizController::class, 'store']);
    Route::get('/teacher/quizzes/{id}/versions', [TeacherQuizController::class, 'versions']);
    Route::post('/teacher/quizzes/{id}/versions/{version}/restore', [TeacherQuizController::class, 'restoreVersion']);
    Route::get('/teacher/quizzes/{id}', [TeacherQuizController::class, 'show']);
    Route::put('/teacher/quizzes/{id}', [TeacherQuizController::class, 'update']);
    Route::delete('/teacher/quizzes/{id}', [TeacherQuizController::class, 'destroy']);
    Route::post('/teacher/quizzes/{id}/assign', [TeacherQuizController::class, 'assign']);

    Route::post('/teacher/classes', [TeacherClassController::class, 'store']);
    Route::get('/teacher/classes/{id}', [TeacherClassController::class, 'show']);
    Route::get('/teacher/classes/{id}/settings', [TeacherClassController::class, 'settings']);
    Route::put('/teacher/classes/{id}/settings', [TeacherClassController::class, 'updateSettings']);
    Route::post('/teacher/classes/{id}/regenerate-code', [TeacherClassController::class, 'regenerateCode']);
    Route::post('/teacher/classes/{id}/archive', [TeacherClassController::class, 'archive']);
    Route::post('/teacher/classes/{id}/restore', [TeacherClassController::class, 'restore']);
    Route::delete('/teacher/classes/{id}', [TeacherClassController::class, 'destroy']);
    Route::delete('/teacher/classes/{classId}/students/{studentId}', [TeacherClassController::class, 'removeStudent']);
    Route::get('/teacher/classes/{classId}/quizzes/{sessionId}/lobby', [TeacherClassController::class, 'lobby']);
    Route::get('/teacher/classes/{classId}/quizzes/{sessionId}/results', [TeacherClassController::class, 'results']);
    Route::put('/teacher/classes/{classId}/quizzes/{sessionId}', [TeacherClassController::class, 'updateAssignment']);
    Route::delete('/teacher/classes/{classId}/quizzes/{sessionId}', [TeacherClassController::class, 'destroyAssignment']);
    Route::post('/teacher/classes/{classId}/quizzes/{sessionId}/start', [TeacherClassController::class, 'start']);
    Route::post('/teacher/classes/{classId}/quizzes/{sessionId}/end', [TeacherClassController::class, 'end']);
    Route::post('/teacher/classes/{classId}/quizzes/{sessionId}/students/{studentId}/retake', [TeacherClassController::class, 'grantRetake']);
    Route::post('/teacher/classes/{classId}/quizzes/{sessionId}/students/{studentId}/excuse', [TeacherClassController::class, 'excuseStudent']);

    Route::post('/teacher/profile', [TeacherController::class, 'updateProfile']);
    Route::get('/teacher/stats', [TeacherController::class, 'stats']);
});

// Admin routes
Route::middleware('auth.supabase:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
    Route::delete('/admin/user/{id}', [AdminController::class, 'deleteUser']);
    Route::post('/admin/user/{id}/suspend', [AdminController::class, 'suspendUser']);
    Route::post('/admin/user/{id}/restore', [AdminController::class, 'restoreUser']);
    Route::get('/admin/quizzes', [AdminQuizController::class, 'index']);
    Route::get('/admin/quiz-library', [AdminQuizController::class, 'library']);
    Route::get('/admin/quiz-reports', [AdminQuizController::class, 'reports']);
    Route::get('/admin/quiz-reports/{reportId}', [AdminQuizController::class, 'showReport']);
    Route::post('/admin/quiz-reports/{reportId}/resolve', [AdminQuizController::class, 'resolveReport']);
    Route::get('/admin/quiz-library/{id}/review', [AdminQuizController::class, 'review']);
    Route::put('/admin/quiz-library/{id}', [AdminQuizController::class, 'updateReviewed']);
    Route::post('/admin/quiz-library/{id}/verify', [AdminQuizController::class, 'toggleVerified']);
    Route::post('/admin/quizzes', [AdminQuizController::class, 'store']);
    Route::get('/admin/quizzes/{id}/versions', [AdminQuizController::class, 'versions']);
    Route::post('/admin/quizzes/{id}/versions/{version}/restore', [AdminQuizController::class, 'restoreVersion']);
    Route::get('/admin/quizzes/{id}', [AdminQuizController::class, 'show']);
    Route::put('/admin/quizzes/{id}', [AdminQuizController::class, 'update']);
    Route::delete('/admin/quizzes/{id}', [AdminQuizController::class, 'destroy']);
    Route::post('/admin/approve-teacher/{id}', [AdminController::class, 'approveTeacher']);
    Route::delete('/admin/deny-teacher/{id}', [AdminController::class, 'denyTeacher']);
    Route::post('/admin/profile', [AdminController::class, 'updateProfile']);
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::post('/admin/push-subscription', [AdminPushController::class, 'store']);
    Route::delete('/admin/push-subscription', [AdminPushController::class, 'destroy']);
});

// Teacher reports
Route::middleware('auth.supabase:teacher')->group(function () {
    Route::get('/teacher/report/quiz-performance', [TeacherController::class, 'reportQuizPerformance']);
    Route::get('/teacher/report/student-progress', [TeacherController::class, 'reportStudentProgress']);
    Route::get('/teacher/report/classes',          [TeacherController::class, 'reportClasses']);
    Route::get('/teacher/report/quiz/{id}',      [TeacherController::class, 'reportSingleQuiz']);
    Route::get('/teacher/report/classroom/{id}', [TeacherController::class, 'reportSingleClassroom']);
});

// Admin reports
Route::middleware('auth.supabase:admin')->group(function () {
    Route::get('/admin/report/students', [AdminController::class, 'reportStudents']);
    Route::get('/admin/report/teachers', [AdminController::class, 'reportTeachers']);
    Route::get('/admin/report/quizzes', [AdminController::class, 'reportQuizzes']);
    Route::get('/admin/report/classrooms', [AdminController::class, 'reportClassrooms']);
    Route::get('/admin/report/summary',  [AdminController::class, 'reportSummary']);
});
