<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateUniformityReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Revisi: uniformity_reports sekarang menyimpan data AGREGAT per hari
     * (bukan per minggu). Kolom tanggal_mulai/tanggal_selesai diganti jadi
     * satu kolom `tanggal` (wajib). week_label tetap dipertahankan sebagai
     * label turunan yang dihitung & disimpan ulang tiap insert (supaya
     * query filter per minggu masih murah tanpa hitung ulang tiap request).
     */
    public function up(): void
    {
        // 1) Tambah kolom tanggal (nullable dulu, supaya bisa backfill data lama kalau ada)
        Schema::table('uniformity_reports', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('week_label');
        });

        // 2) Backfill: kalau ada data lama, pakai tanggal_mulai sebagai tanggal
        //    (data lama berbasis minggu tidak 100% akurat direpresentasikan
        //    sebagai harian, tapi ini best-effort supaya tidak hilang begitu saja).
        DB::table('uniformity_reports')
            ->whereNotNull('tanggal_mulai')
            ->update(['tanggal' => DB::raw('tanggal_mulai')]);

        // 3) Hapus baris yang tidak punya tanggal_mulai sama sekali (tidak bisa di-backfill)
        DB::table('uniformity_reports')->whereNull('tanggal')->delete();

        // 4) Set tanggal jadi NOT NULL, hapus kolom lama, dan tambah unique constraint
        Schema::table('uniformity_reports', function (Blueprint $table) {
            $table->date('tanggal')->nullable(false)->change();
            $table->dropColumn(['tanggal_mulai', 'tanggal_selesai']);

            // Cegah duplikat data untuk kombinasi tanggal + plant + size
            $table->unique(['tanggal', 'plant', 'size'], 'uniformity_reports_tanggal_plant_size_unique');

            // Index tambahan untuk query per plant per tanggal (fitur "belum upload")
            $table->index(['plant', 'tanggal'], 'uniformity_reports_plant_tanggal_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uniformity_reports', function (Blueprint $table) {
            $table->dropUnique('uniformity_reports_tanggal_plant_size_unique');
            $table->dropIndex('uniformity_reports_plant_tanggal_index');
            $table->date('tanggal_mulai')->nullable()->after('week_label');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
        });

        DB::table('uniformity_reports')->update([
            'tanggal_mulai' => DB::raw('tanggal'),
            'tanggal_selesai' => DB::raw('tanggal'),
        ]);

        Schema::table('uniformity_reports', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }
}
