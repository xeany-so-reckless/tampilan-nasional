<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UniformityController;

Route::get('/', function () {
    return view('welcome');
});

// ==========================================================
// Slaughter House - Rekap Uniformity Mingguan
// ==========================================================
Route::prefix('slaughter/uniformity')->name('slaughter.uniformity.')->group(function () {
    // Halaman utama (blade view)
    Route::get('/', [UniformityController::class, 'index'])->name('index');

    // Halaman Detail (blade view) - dibuka dari tombol "Detail" per plant
    Route::get('/detail-page', [UniformityController::class, 'detailPage'])->name('detail-page');

    // Upload file Excel (1 file = 1 plant = 1 hari, replace data plant+tanggal itu)
    Route::post('/upload', [UniformityController::class, 'upload'])->name('upload');

    // Ambil data agregat untuk chart (bisa difilter ?week=...&region=...&plant=...)
    Route::get('/data', [UniformityController::class, 'data'])->name('data');

    // Ambil daftar region & plant untuk isi dropdown filter + upload
    Route::get('/filter-options', [UniformityController::class, 'filterOptions'])->name('filter-options');

    // Status kelengkapan upload per plant per tanggal (?start=...&end=...)
    Route::get('/upload-status', [UniformityController::class, 'uploadStatus'])->name('upload-status');

    // Data mentah per rit untuk halaman Detail (?plant=...&start=...&end=...&kategori_size[]=...)
    Route::get('/detail', [UniformityController::class, 'detail'])->name('detail');
});