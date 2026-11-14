<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Tap for every log channel: replaces configured secrets (bot token, OAuth
 * client secret, app key …) with [redacted] in messages and context, so a
 * secret that ends up in an exception message never reaches the log (SEC-4).
 */
class RedactSecrets implements ProcessorInterface
{
    /** @var list<string> */
    private array $secrets;

    public function __construct()
    {
        $this->secrets = array_values(array_filter([
            config('services.discord.bot_token'),
            config('services.discord.client_secret'),
            config('app.key'),
            config('database.connections.pgsql.password'),
        ], fn ($value) => is_string($value) && strlen($value) >= 8));
    }

    public function __invoke(Logger|LogRecord $target): Logger|LogRecord
    {
        if ($target instanceof Logger) {
            $target->getLogger()->pushProcessor($this);

            return $target;
        }

        if ($this->secrets === []) {
            return $target;
        }

        return $target->with(
            message: $this->redact($target->message),
            context: $this->redactArray($target->context),
            extra: $this->redactArray($target->extra),
        );
    }

    private function redact(string $text): string
    {
        return str_replace($this->secrets, '[redacted]', $text);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $values[$key] = $this->redactArray($value);
            } elseif ($value instanceof \Throwable) {
                $values[$key] = $value::class.': '.$this->redact($value->getMessage());
            }
        }

        return $values;
    }
}
