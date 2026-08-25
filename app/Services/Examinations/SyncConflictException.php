<?php

namespace App\Services\Examinations;

use InvalidArgumentException;

class SyncConflictException extends InvalidArgumentException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public array $details = [],
    ) {
        parent::__construct($message);
    }
}
