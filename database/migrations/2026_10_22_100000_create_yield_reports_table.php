<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1 file Excel = 1 plant = 1 hari = 1 baris di tabel ini.
     * Semua angka diambil dari kolom ALL (Qty Kg di kolom C, % di kolom D) template
     * "FORM PERHITUNGAN INDEX GRILLER (MONITORING YIELD)".
     * Persentase disimpan sebagai desimal (0.93 = 93%), semua dihitung terhadap Berat Bersih.
     */
    public function up(): void
    {
        Schema::create('yield_reports', function (Blueprint $table) {
            $table->id();

            // Info lokasi
            $table->string('region');   // contoh: Banten, Jabar, Jateng, Jatim, Luar Jawa
            $table->string('plant');    // contoh: Jombang, Salatiga 1, Medan

            // Info tanggal. "bulan"/"tahun" diturunkan dari "tanggal" saat upload,
            // dipakai untuk filter tampilan per bulan kalender.
            $table->date('tanggal');                // sel C3
            $table->unsignedTinyInteger('bulan');   // 1-12
            $table->unsignedSmallInteger('tahun');  // contoh: 2026

            // ===== LIVEBIRDS =====
            $table->double('sppa_lb_ekor')->default(0);       // C7
            $table->double('sppa_lb_kg')->default(0);         // C8
            $table->double('abw')->default(0);                // C9  (berat rata-rata ayam hidup, kg/ekor)
            $table->double('ayam_mati_ekor')->default(0);     // C10
            $table->double('susut_kg')->default(0);           // C11
            $table->double('susut_persen')->default(0);       // C12
            $table->double('berat_bersih')->default(0);       // C13 (basis perhitungan persen)

            // ===== RM Input Griller (H0) = titik nol =====
            $table->double('rm_h0_kg')->default(0);           // C15
            $table->double('rm_h0_persen')->default(0);       // D15

            // ===== H1 Griller =====
            $table->double('rm_griller_kg')->default(0);      // C18 (RM Griller titik nol, untuk donut)
            $table->double('h1_kg')->default(0);              // C19
            $table->double('h1_persen')->default(0);          // D19

            // ===== H2 Parting =====
            $table->double('rm_parting_kg')->default(0);      // C27 (RM Parting titik nol, untuk donut)
            $table->double('h2_kg')->default(0);              // C33
            $table->double('h2_persen')->default(0);          // D33

            // ===== H3 Cut Up =====
            $table->double('rm_cutup_kg')->default(0);        // C36 (RM Cut Up titik nol, untuk donut)
            $table->double('h3_kg')->default(0);              // C44
            $table->double('h3_persen')->default(0);          // D44

            // ===== H4 Boneless =====
            $table->double('rm_boneless_kg')->default(0);     // C47 (RM Boneless titik nol, untuk donut)
            $table->double('h4_kg')->default(0);              // C54
            $table->double('h4_persen')->default(0);          // D54

            // ===== TOTAL =====
            $table->double('fg_bp_kg')->default(0);           // C57 Grand Total Qty FG+BP
            $table->double('fg_bp_persen')->default(0);       // D57
            $table->double('bp_evisc_kg')->default(0);        // C58 BP EVISC (kecuali SHUV)
            $table->double('bp_evisc_persen')->default(0);    // D58
            $table->double('final_yield_kg')->default(0);     // C59 YIELD FG TOTAL
            $table->double('final_yield_persen')->default(0); // D59
            $table->double('lost_kg')->default(0);            // C60 (bisa negatif)
            $table->double('lost_persen')->default(0);        // D60 (bisa negatif)

            $table->timestamps();

            // Supaya query filter bulan/tahun/region/plant lebih cepat
            $table->index(['tahun', 'bulan', 'region', 'plant']);

            // 1 plant hanya boleh punya 1 baris per tanggal (dipakai untuk replace saat re-upload)
            $table->unique(['plant', 'tanggal'], 'yield_reports_plant_tanggal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yield_reports');
    }
};
