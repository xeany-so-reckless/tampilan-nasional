<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformityReport extends Model
{
    protected $fillable = [
        'week_label',
        'tanggal_mulai',
        'tanggal_selesai',
        'region',
        'plant',
        'size',
        'total_lb',
        'lb_standart',
        'lb_under',
        'lb_over',
        'persen_standart',
        'persen_under',
        'persen_over',
        'target',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'total_lb' => 'float',
        'lb_standart' => 'float',
        'lb_under' => 'float',
        'lb_over' => 'float',
        'persen_standart' => 'float',
        'persen_under' => 'float',
        'persen_over' => 'float',
        'target' => 'float',
    ];
}
