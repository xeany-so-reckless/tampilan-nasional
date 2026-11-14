<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YieldReport extends Model
{
    protected $fillable = [
        'region',
        'plant',
        'tanggal',
        'bulan',
        'tahun',

        // Livebirds
        'sppa_lb_ekor',
        'sppa_lb_kg',
        'abw',
        'ayam_mati_ekor',
        'susut_kg',
        'susut_persen',
        'berat_bersih',

        // RM Input Griller (H0) = titik nol
        'rm_h0_kg',
        'rm_h0_persen',

        // H1 Griller
        'rm_griller_kg',
        'h1_kg',
        'h1_persen',

        // H2 Parting
        'rm_parting_kg',
        'h2_kg',
        'h2_persen',

        // H3 Cut Up
        'rm_cutup_kg',
        'h3_kg',
        'h3_persen',

        // H4 Boneless
        'rm_boneless_kg',
        'h4_kg',
        'h4_persen',

        // Total
        'fg_bp_kg',
        'fg_bp_persen',
        'bp_evisc_kg',
        'bp_evisc_persen',
        'final_yield_kg',
        'final_yield_persen',
        'lost_kg',
        'lost_persen',
    ];

    protected $casts = [
        'tanggal'            => 'date:Y-m-d',
        'bulan'              => 'integer',
        'tahun'              => 'integer',

        'sppa_lb_ekor'       => 'float',
        'sppa_lb_kg'         => 'float',
        'abw'                => 'float',
        'ayam_mati_ekor'     => 'float',
        'susut_kg'           => 'float',
        'susut_persen'       => 'float',
        'berat_bersih'       => 'float',

        'rm_h0_kg'           => 'float',
        'rm_h0_persen'       => 'float',

        'rm_griller_kg'      => 'float',
        'h1_kg'              => 'float',
        'h1_persen'          => 'float',

        'rm_parting_kg'      => 'float',
        'h2_kg'              => 'float',
        'h2_persen'          => 'float',

        'rm_cutup_kg'        => 'float',
        'h3_kg'              => 'float',
        'h3_persen'          => 'float',

        'rm_boneless_kg'     => 'float',
        'h4_kg'              => 'float',
        'h4_persen'          => 'float',

        'fg_bp_kg'           => 'float',
        'fg_bp_persen'       => 'float',
        'bp_evisc_kg'        => 'float',
        'bp_evisc_persen'    => 'float',
        'final_yield_kg'     => 'float',
        'final_yield_persen' => 'float',
        'lost_kg'            => 'float',
        'lost_persen'        => 'float',
    ];
}
