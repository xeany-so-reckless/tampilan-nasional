<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtuReport extends Model
{
    protected $fillable = [
        'week_label',
        'bulan',
        'tahun',
        'tanggal_mulai',
        'tanggal_selesai',
        'region',
        'plant',
        'size',
        'jumlah_lb',
        'defect_farm_ekor',
        'defect_farm_persen',
        'defect_proses_ekor',
        'defect_proses_persen',
        'atu_ekor',
        'atu_persen',
        'total_defect_atu_ekor',
        'total_defect_atu_persen',
    ];

    protected $casts = [
        'bulan'                   => 'integer',
        'tahun'                   => 'integer',
        'tanggal_mulai'           => 'date:Y-m-d',
        'tanggal_selesai'         => 'date:Y-m-d',
        'jumlah_lb'               => 'float',
        'defect_farm_ekor'        => 'float',
        'defect_farm_persen'      => 'float',
        'defect_proses_ekor'      => 'float',
        'defect_proses_persen'    => 'float',
        'atu_ekor'                => 'float',
        'atu_persen'              => 'float',
        'total_defect_atu_ekor'   => 'float',
        'total_defect_atu_persen' => 'float',
    ];
}