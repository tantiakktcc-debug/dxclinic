<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case BOOKED = 'BOOKED';
    case ARRIVED = 'ARRIVED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
