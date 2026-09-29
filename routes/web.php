<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UniformityController;
use App\Http\Controllers\AtuController;

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

    // Cek kode otorisasi plant SEBELUM file picker dibuka (dipanggil dari modal SweetAlert)
    Route::post('/cek-kode', [UniformityController::class, 'cekKode'])->name('cek-kode');

    // Ambil data agregat untuk chart (bisa difilter ?week=...&region=...&plant=...)
    Route::get('/data', [UniformityController::class, 'data'])->name('data');

    // Perbandingan data 1 plant antara 2 bulan kalender (?plant=...&bulan[]=Y-m&bulan[]=Y-m)
    Route::get('/compare-bulan', [UniformityController::class, 'compareBulan'])->name('compare-bulan');

        // Tren 1 plant sepanjang 12 bulan dalam 1 tahun (?plant=...&tahun=YYYY)
    Route::get('/compare-tahun', [UniformityController::class, 'compareTahun'])->name('compare-tahun');

        // Perbandingan data 1 plant antara 2 minggu ISO (?plant=...&minggu[]=2026-W35&minggu[]=2026-W36)
    Route::get('/compare-minggu', [UniformityController::class, 'compareMinggu'])->name('compare-minggu');

    // Ambil daftar region & plant untuk isi dropdown filter + upload
    Route::get('/filter-options', [UniformityController::class, 'filterOptions'])->name('filter-options');

    // Status kelengkapan upload per plant per tanggal (?start=...&end=...)
    Route::get('/upload-status', [UniformityController::class, 'uploadStatus'])->name('upload-status');

    // Data mentah per rit untuk halaman Detail (?plant=...&start=...&end=...&kategori_size[]=...)
    Route::get('/detail', [UniformityController::class, 'detail'])->name('detail');
});

// // ==========================================================
// // Slaughter House - Rekap ATU Bulanan
// // ==========================================================
// Route::prefix('slaughter/atu')->name('slaughter.atu.')->group(function () {
//     // Halaman utama (blade view)
//     Route::get('/', [AtuController::class, 'index'])->name('index');
 
//     // Upload file Excel (1 file = 1 plant = 1 minggu, replace data plant+minggu itu)
//     Route::post('/upload', [AtuController::class, 'upload'])->name('upload');
 
//     // Cek kode otorisasi plant SEBELUM file picker dibuka (dipanggil dari modal SweetAlert)
//     Route::post('/cek-kode', [AtuController::class, 'cekKode'])->name('cek-kode');
 
//     // Ambil data agregat untuk chart & tabel (bisa difilter ?bulan=...&tahun=...&region=...&plant=...)
//     Route::get('/data', [AtuController::class, 'data'])->name('data');
 
//     // Ambil daftar region & plant untuk isi dropdown filter + upload, plus daftar bulan yang ada datanya
//     Route::get('/filter-options', [AtuController::class, 'filterOptions'])->name('filter-options');
// });