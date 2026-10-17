<?php

namespace App\Exceptions;

use Exception;

class TokenAllocationException extends Exception
{
    public static function dailyLimitExceeded(): self
    {
        return new self("Daily token limit exceeded for this session.", 422);
    }

    public static function sequenceError(): self
    {
        return new self("Could not allocate sequence number due to concurrency error. Please try again.", 409);
    }
}
