<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterSizeAyamTypeUniformityBatches extends Migration
{
    /**
     * Kolom size_ayam awalnya double, tapi isi aslinya teks rentang
     * (contoh "1.5-1.7"), jadi diubah jadi string.
     */
    public function up(): void
    {
        Schema::table('uniformity_batches', function (Blueprint $table) {
            $table->string('size_ayam')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('uniformity_batches', function (Blueprint $table) {
            $table->double('size_ayam')->nullable()->change();
        });
    }
}
