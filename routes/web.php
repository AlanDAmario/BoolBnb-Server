<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ApartmentsController;
use App\Http\Controllers\AutocompleteController;
use App\Http\Controllers\SearchController; // Importa il controller per la ricerca

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('auth.register');
});

Route::get('/login', function () {
    return view('auth.login');
});

// Rotta per la dashboard che richiede autenticazione e verifica dell'email
Route::get('/dashboard', function () {
    return view('auth.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Rotte per la gestione del profilo dell'utente
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('apartments', ApartmentsController::class);
});

// Rotta per l'autocompletamento di TomTom
Route::get('/autocomplete', [AutocompleteController::class, 'autocomplete']);

// Rotta per la ricerca di appartamenti in base ai criteri come servizi, zona, ecc.
Route::get('/search', [SearchController::class, 'search']);

// Includi le rotte per l'autenticazione (login, registrazione, ecc.)
require __DIR__ . '/auth.php';
