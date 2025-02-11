<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PrimaryEmotion;
use App\Enums\SecondaryEmotion;

class Emotion extends Model
{
    /** @use HasFactory<\Database\Factories\EmotionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'score', 'primary', 'secondary', 'description'];

    protected $casts = [
        'primary' => PrimaryEmotion::class,
        'secondary' => SecondaryEmotion::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
