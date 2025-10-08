<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', // 'purchase' | 'project'
        'year', // int
        'seq',  // int
    ];

    public $timestamps = false;
}
