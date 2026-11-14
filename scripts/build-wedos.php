<?php

/**
 * Builds an upload package for shared hosting without SSH (WEDOS webhosting).
 *
 *   npm ci && npm run build
 *   php scripts/build-wedos.php [--out=build/wedos] [--app-dir=genetic-mastery]
 *                               [--web-to-app=../genetic-mastery] [--composer=composer]
 *
 * Result:
 *   build/wedos/genetic-mastery/  the Laravel app, upload NEXT TO the web root
 *   build/wedos/www/              upload INTO the web root (www/ or www/domains/<domain>/)
 *
 * Never contains .env, storage contents or the SQLite database, so a new
 * upload does not overwrite production data.
 */
$root = dirname(__DIR__);
$options = getopt('', ['out::', 'app-dir::', 'web-to-app::', 'composer::']);
$out = $root.'/'.trim($options['out'] ?? 'build/wedos', '/');
$appDir = $options['app-dir'] ?? 'genetic-mastery';
$webToApp = $options['web-to-app'] ?? '../'.$appDir;
$composer = $options['composer'] ?? (getenv('COMPOSER_BIN') ?: 'composer');

$fail = function (string $message): never {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
};

$run = function (array $command, string $cwd) use ($fail): string {
    // stderr goes to a file: reading two pipes one after the other deadlocks
    // as soon as the child fills the other pipe's buffer (Composer does).
    $errorFile = tempnam(sys_get_temp_dir(), 'build');
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errorFile, 'w']], $pipes, $cwd);
    if (! is_resource($process)) {
        $fail('Cannot run '.implode(' ', $command));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exit = proc_close($process);
    $stderr = (string) file_get_contents($errorFile);
    @unlink($errorFile);
    if ($exit !== 0) {
        $fail(implode(' ', $command)." failed:\n{$stderr}{$stdout}");
    }

    return $stdout;
};

if (! is_file($root.'/public/build/manifest.json')) {
    $fail('Frontend is not built. Run: npm ci && npm run build');
}

$exclude = ['tests/', 'design/', 'docker/', '.github/', 'deploy/', 'scripts/', 'resources/css/', 'resources/js/', 'public/', 'database/database.sqlite'];
$excludeFiles = ['Dockerfile', 'docker-compose.yml', '.dockerignore', 'phpunit.xml', 'package.json', 'package-lock.json', 'vite.config.js', '.editorconfig', '.gitattributes', '.npmrc'];

$removeTree = function (string $path) use (&$removeTree): void {
    if (! file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }
    foreach (scandir($path) as $item) {
        if ($item !== '.' && $item !== '..') {
            $removeTree($path.'/'.$item);
        }
    }
    rmdir($path);
};

$copy = function (string $from, string $to): void {
    if (! is_dir(dirname($to))) {
        mkdir(dirname($to), 0775, true);
    }
    copy($from, $to);
};

echo "Cleaning {$out}\n";
$removeTree($out);
$app = "{$out}/{$appDir}";
$web = "{$out}/www";
mkdir($app, 0775, true);
mkdir($web, 0775, true);

echo "Copying tracked files\n";
$files = array_filter(explode("\0", $run(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], $root)));
$count = 0;
foreach ($files as $file) {
    $file = str_replace('\\', '/', $file);
    if (in_array($file, $excludeFiles, true) || ! is_file("{$root}/{$file}")) {
        continue;
    }
    foreach ($exclude as $prefix) {
        if (str_starts_with($file, $prefix)) {
            continue 2;
        }
    }
    $copy("{$root}/{$file}", "{$app}/{$file}");
    $count++;
}
echo "  {$count} files\n";

echo "Installing production dependencies\n";
$composerCommand = str_ends_with($composer, '.phar') ? [PHP_BINARY, $composer] : [$composer];
$run([...$composerCommand, 'install', '--no-dev', '--no-interaction', '--no-progress', '--prefer-dist', '--optimize-autoloader', '--classmap-authoritative', '--no-scripts', '--ignore-platform-req=ext-*', '--working-dir='.$app], $root);

echo "Preparing web root\n";
$public = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public', FilesystemIterator::SKIP_DOTS));
foreach ($public as $item) {
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root.'/public/')));
    if (in_array($relative, ['index.php', '.htaccess', 'hot'], true)) {
        continue;
    }
    $copy($item->getPathname(), "{$web}/{$relative}");
}
foreach (['index.php', 'cron.php'] as $entry) {
    file_put_contents("{$web}/{$entry}", str_replace('{{APP_PATH}}', ltrim($webToApp, '/'), file_get_contents($root."/deploy/wedos/{$entry}.stub")));
}
copy($root.'/deploy/wedos/.htaccess', "{$web}/.htaccess");

// Empty runtime directories the app writes to.
foreach (['storage/app/private', 'storage/app/tmp', 'storage/database', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    if (! is_dir("{$app}/{$dir}")) {
        mkdir("{$app}/{$dir}", 0775, true);
    }
}
copy($root.'/deploy/wedos/env.example', "{$app}/.env.example");

echo "\nDone.\n";
echo "  Upload {$appDir}/ next to the web root (e.g. /{$appDir}), www/ into the web root.\n";
echo "  index.php looks for the app at: {$webToApp} (relative to the web root)\n";
echo "  First time: create {$appDir}/.env from {$appDir}/.env.example. See docs/NASAZENI-WEDOS.md.\n";
