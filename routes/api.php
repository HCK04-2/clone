<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailCheckController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteStatsController;
use App\Http\Controllers\MedecinController;
use App\Http\Controllers\OrganisationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/check-email', [EmailCheckController::class, 'check']);
Route::get('/annonces', [AnnonceController::class, 'publicIndex']);
// NEW: make site stats public
Route::get('/site-stats', [SiteStatsController::class, 'getStats']);
Route::post('/site-stats/bump', [SiteStatsController::class, 'bump']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/profile/update', [UserController::class, 'updateProfile']); // <-- ADD THIS LINE
    Route::post('/user/profile/update-avatar', [UserController::class, 'updateProfileAvatar']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Doctor routes
    Route::prefix('doctor')->group(function () {
        Route::get('/stats', [DashboardController::class, 'doctorStats']);
        Route::get('/appointments', [DashboardController::class, 'doctorAppointments']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

        Route::get('/annonces', [AnnonceController::class, 'index']);
        Route::post('/annonces', [AnnonceController::class, 'store']);
        Route::get('/annonces/{id}', [AnnonceController::class, 'show']);
        Route::post('/annonces/{id}', [AnnonceController::class, 'update']);
        Route::put('/annonces/{id}/toggle-status', [AnnonceController::class, 'toggleStatus']);
        Route::delete('/annonces/{id}', [AnnonceController::class, 'destroy']);
    });

    // Patient routes
    Route::prefix('patient')->group(function () {
        // Patient specific routes here
    });

    // Medecins
    Route::get('/medecins', [MedecinController::class, 'index']);

    // Organisations
    Route::get('/organisations', [OrganisationController::class, 'index']);

    // Appointments
    Route::get('/appointments', [AppointmentController::class, 'index']);
});
