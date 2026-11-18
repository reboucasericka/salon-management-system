<?php

namespace App\Services;

use RuntimeException;

class GoogleCalendarApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message, $status);
    }
}
