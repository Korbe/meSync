<?php

namespace App\Enums;

enum PrimaryEmotion: string
{
    case Glücklich = 'happy';
    case Traurig = 'sad';
    case Wütend = 'angry';
    case Überfordert = 'Overwhelmed';
    case Ängstlich = 'fearful';
    case Angewidert = 'disgusted';
    case Neutral = 'neutral';
}