<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformityBatch extends Model
{
    protected $fillable = [
        'region',
        'plant',
        'tanggal',
        'no_urut',
        'nama_farm',
        'jumlah_sample',
        'ekspedisi',
        'rata_sppa',
        'rata_rpa',
        'size_ayam',
        'berat_min',
        'berat_max',
        'kategori_size',
        'ekor_under',
        'ekor_standart',
        'ekor_over',
        'total_ekor',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'no_urut' => 'integer',
        'jumlah_sample' => 'integer',
        'rata_sppa' => 'float',
        'rata_rpa' => 'float',
        'berat_min' => 'float',
        'berat_max' => 'float',
        'ekor_under' => 'integer',
        'ekor_standart' => 'integer',
        'ekor_over' => 'integer',
        'total_ekor' => 'integer',
    ];
}
