<?php

namespace App\Enums;

enum VisitStatus: string
{
    case REGISTERED = 'REGISTERED';
    case WAITING = 'WAITING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
