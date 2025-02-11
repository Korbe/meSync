<?php

namespace App\Enums;

enum PrimaryEmotion: string
{
    case HAPPY = 'happy';
    case SAD = 'sad';
    case ANGRY = 'angry';
    case SURPRISED = 'surprised';
    case FEARFUL = 'fearful';
    case DISGUSTED = 'disgusted';
}