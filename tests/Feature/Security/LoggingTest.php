<?php

use App\Logging\RedactSecrets;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;

it('never writes secrets to the log (SEC-4)', function () {
    config([
        'services.discord.bot_token' => 'Bot.Super.Secret.Token.123',
        'services.discord.client_secret' => 'oauth-client-secret-456',
    ]);
    $file = tempnam(sys_get_temp_dir(), 'log');
    config(['logging.channels.redaction_test' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'handler_with' => ['stream' => $file],
        'formatter' => JsonFormatter::class,
        'tap' => [RedactSecrets::class],
    ]]);

    Log::channel('redaction_test')->error('Discord said: invalid token Bot.Super.Secret.Token.123', [
        'request' => ['authorization' => 'Bot Bot.Super.Secret.Token.123'],
        'secret' => 'oauth-client-secret-456',
        'exception' => new RuntimeException('failed with Bot.Super.Secret.Token.123'),
    ]);

    $written = (string) file_get_contents($file);
    @unlink($file);

    expect($written)->not->toContain('Bot.Super.Secret.Token.123')
        ->and($written)->not->toContain('oauth-client-secret-456')
        ->and($written)->toContain('[redacted]')
        ->and(json_decode(trim($written), true))->toBeArray();
});

it('logs as JSON on stderr in Docker and redacts on every channel', function () {
    expect(config('logging.channels.json.formatter'))->toBe(JsonFormatter::class)
        ->and(config('logging.channels.json.handler_with.stream'))->toBe('php://stderr');

    foreach (['json', 'json_file', 'single', 'daily', 'stderr'] as $channel) {
        expect(config("logging.channels.{$channel}.tap"))->toContain(RedactSecrets::class);
    }
});
