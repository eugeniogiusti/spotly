<?php

use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\PoisController;
use App\Http\Controllers\Api\PoiTagsController;
use App\Http\Controllers\Api\SavedPoisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\SavedPlacesController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
| Map, saved places, and API endpoints for POI data and community tags.
| All internal API routes live here (not api.php) to share the session auth.
*/
Route::middleware(['auth'])->group(function () {
    // Pages
    Route::get('map', [MapController::class, 'index'])->name('map');
    Route::get('/my-places', [SavedPlacesController::class, 'index'])->name('my-places');

    // Saved POIs — save, update notes, remove
    Route::post('/saved-pois', [SavedPoisController::class, 'store'])->name('saved-pois.store');
    Route::patch('/saved-pois/{externalId}/notes', [SavedPoisController::class, 'updateNotes'])->name('saved-pois.notes');
    Route::delete('/saved-pois/{externalId}', [SavedPoisController::class, 'destroy'])->name('saved-pois.destroy');

    // POI data + community tags — 60 req/min
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/api/pois', [PoisController::class, 'index']);
        Route::get('/api/pois/{externalId}/tags', [PoiTagsController::class, 'index']);
        Route::post('/api/pois/{externalId}/tags', [PoiTagsController::class, 'toggle']);
    });

    // Geocoding proxy (Nominatim) — 20 req/min
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('/api/geocode', [GeocodingController::class, 'search']);
    });
});

/*
|--------------------------------------------------------------------------
| Verified routes
|--------------------------------------------------------------------------
| Requires email verification in addition to authentication.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
