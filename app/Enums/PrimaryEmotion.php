<?php

namespace App\Enums;

enum PrimaryEmotion: string {
    case Happy = 'happy';
    case Sad = 'sad';
    case Angry = 'angry';
    case Overwhelmed = 'overwhelmed';
    case Fearful = 'fearful';
    case Disgusted = 'disgusted';
    case Neutral = 'neutral';
}