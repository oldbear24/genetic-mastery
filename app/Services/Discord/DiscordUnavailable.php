<?php

namespace App\Services\Discord;

use RuntimeException;

/**
 * Discord could not be reached or answered with an unexpected error. Callers
 * decide whether to fail (login) or carry on with the last known state.
 */
class DiscordUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?float $retryAfter = null)
    {
        parent::__construct($message);
    }
}
