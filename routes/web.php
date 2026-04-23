<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\AdminController;

// Public routes
Route::get('/',       [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Student routes
Route::middleware('auth.supabase:student')->group(function () {
    Route::get('/student/dashboard', [StudentController::class, 'index']);
    Route::post('/student/join-class', [StudentController::class, 'joinClass']);
    Route::post('/student/leave-class', [StudentController::class, 'leaveClass']);
    Route::get('/student/class-roster/{classId}', [StudentController::class, 'classRoster']);
    Route::post('/student/profile', [StudentController::class, 'updateProfile']);
});

// Teacher routes
Route::middleware('auth.supabase:teacher')->group(function () {
    Route::get('/teacher/dashboard', [TeacherController::class, 'index']);
    Route::post('/teacher/quiz', [TeacherController::class, 'storeQuiz']);
    Route::put('/teacher/quiz/{id}', [TeacherController::class, 'updateQuiz']);
    Route::delete('/teacher/quiz/{id}', [TeacherController::class, 'deleteQuiz']);
    Route::get('/teacher/quiz/{id}/results', [TeacherController::class, 'quizResults']);
    Route::post('/teacher/class', [TeacherController::class, 'createClass']);
    Route::delete('/teacher/class/{id}', [TeacherController::class, 'deleteClass']);
    Route::get('/teacher/class/{id}/roster', [TeacherController::class, 'classRoster']);
    Route::delete('/teacher/class/{classId}/student/{studentId}', [TeacherController::class, 'removeStudent']);
    Route::get('/teacher/lobby/{sessionId}', [TeacherController::class, 'lobbyParticipants']);
    Route::post('/teacher/quiz/{id}/start', [TeacherController::class, 'startQuiz']);
    Route::post('/teacher/quiz/{id}/end', [TeacherController::class, 'endQuiz']);
    Route::post('/teacher/profile', [TeacherController::class, 'updateProfile']);
});

// Admin routes
Route::middleware('auth.supabase:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
    Route::put('/admin/user/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/user/{id}', [AdminController::class, 'deleteUser']);
    Route::post('/admin/approve-teacher/{id}', [AdminController::class, 'approveTeacher']);
    Route::delete('/admin/deny-teacher/{id}', [AdminController::class, 'denyTeacher']);
    Route::post('/admin/profile', [AdminController::class, 'updateProfile']);
});