<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Digestion extends Model
{
    /** @use HasFactory<\Database\Factories\DigestionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'score', 'description'];

}
