<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentClassController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherClassController;
use App\Http\Controllers\TeacherQuizController;
use App\Http\Controllers\AdminController;

Route::pattern('id', '[0-9a-fA-F-]{36}');
Route::pattern('classId', '[0-9a-fA-F-]{36}');
Route::pattern('studentId', '[0-9a-fA-F-]{36}');
Route::pattern('sessionId', '[0-9a-fA-F-]{36}');

// Auth routes
Route::get('/',       [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('/reset-password', function () { return view('auth.reset'); });
Route::post('/update-password', [AuthController::class, 'updatePassword']);
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Student routes
Route::middleware('auth.supabase:student')->group(function () {
    Route::get('/student/dashboard', [StudentController::class, 'index']);
    Route::post('/student/classes/join', [StudentClassController::class, 'join']);
    Route::get('/student/classes/{id}', [StudentClassController::class, 'show']);
    Route::post('/student/profile', [StudentController::class, 'updateProfile']);
});

// Teacher routes
Route::middleware('auth.supabase:teacher')->group(function () {
    Route::get('/teacher/dashboard', [TeacherController::class, 'index']);

    Route::get('/teacher/quizzes', [TeacherQuizController::class, 'index']);
    Route::get('/teacher/quiz-library', [TeacherQuizController::class, 'library']);
    Route::post('/teacher/quizzes', [TeacherQuizController::class, 'store']);
    Route::get('/teacher/quizzes/{id}', [TeacherQuizController::class, 'show']);
    Route::put('/teacher/quizzes/{id}', [TeacherQuizController::class, 'update']);
    Route::delete('/teacher/quizzes/{id}', [TeacherQuizController::class, 'destroy']);
    Route::post('/teacher/quizzes/{id}/assign', [TeacherQuizController::class, 'assign']);

    Route::post('/teacher/classes', [TeacherClassController::class, 'store']);
    Route::get('/teacher/classes/{id}', [TeacherClassController::class, 'show']);
    Route::get('/teacher/classes/{id}/settings', [TeacherClassController::class, 'settings']);
    Route::put('/teacher/classes/{id}/settings', [TeacherClassController::class, 'updateSettings']);
    Route::post('/teacher/classes/{id}/regenerate-code', [TeacherClassController::class, 'regenerateCode']);
    Route::delete('/teacher/classes/{id}', [TeacherClassController::class, 'destroy']);
    Route::delete('/teacher/classes/{classId}/students/{studentId}', [TeacherClassController::class, 'removeStudent']);
    Route::get('/teacher/classes/{classId}/quizzes/{sessionId}/lobby', [TeacherClassController::class, 'lobby']);
    Route::get('/teacher/classes/{classId}/quizzes/{sessionId}/results', [TeacherClassController::class, 'results']);
    Route::post('/teacher/classes/{classId}/quizzes/{sessionId}/start', [TeacherClassController::class, 'start']);
    Route::post('/teacher/classes/{classId}/quizzes/{sessionId}/end', [TeacherClassController::class, 'end']);

    Route::post('/teacher/profile', [TeacherController::class, 'updateProfile']);
    Route::get('/teacher/stats', [TeacherController::class, 'stats']);
});

// Admin routes
Route::middleware('auth.supabase:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
    Route::put('/admin/user/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/user/{id}', [AdminController::class, 'deleteUser']);
    Route::post('/admin/approve-teacher/{id}', [AdminController::class, 'approveTeacher']);
    Route::delete('/admin/deny-teacher/{id}', [AdminController::class, 'denyTeacher']);
    Route::post('/admin/profile', [AdminController::class, 'updateProfile']);
    Route::get('/admin/stats', [AdminController::class, 'stats']);
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
    Route::get('/admin/report/summary',  [AdminController::class, 'reportSummary']);
});
