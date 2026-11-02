#!/usr/bin/env bash
# Local assessment setup for macOS/Linux. Run from any directory with ./setup.sh.
set -euo pipefail
trap 'printf "\nSetup stopped at line %s. Resolve the error above and rerun ./setup.sh.\n" "$LINENO" >&2' ERR

cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

fail() {
    printf 'Error: %s\n' "$1" >&2
    exit 1
}

check_port() {
    php -r '
        $socket = @stream_socket_server("tcp://127.0.0.1:8000", $code, $message);
        if ($socket === false) {
            fwrite(STDERR, "Cannot bind 127.0.0.1:8000. Stop the existing server or check permissions.\n");
            exit(1);
        }
        fclose($socket);
    '
}

printf '\nChecking prerequisites...\n'
for command in php composer node npm; do
    command -v "$command" >/dev/null 2>&1 || fail "Install $command and ensure it is on your PATH."
done
for file in composer.json composer.lock package.json package-lock.json .env.example; do
    [[ -f "$file" ]] || fail "Missing $file. Include both dependency lockfiles in the project package."
done
node -e 'const [major, minor] = process.versions.node.split(".").map(Number); process.exit(major > 22 || (major === 22 && minor >= 12) ? 0 : 1)' \
    || fail 'Node.js 22.12 or newer is required by the locked frontend dependencies. Node.js 24 LTS is recommended.'
php -r '
    foreach (["pdo_sqlite", "fileinfo"] as $extension) {
        if (!extension_loaded($extension)) {
            fwrite(STDERR, "Enable the PHP ".$extension." extension.\n");
            exit(1);
        }
    }
'
composer check-platform-reqs --lock --no-interaction
check_port

mkdir -p bootstrap/cache database storage/app/private storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs

fresh_env=0
if [[ ! -f .env ]]; then
    (umask 077; cp .env.example .env)
    fresh_env=1
fi
[[ -w .env ]] || fail '.env must be writable by your current user.'
chmod 600 .env

# Keep service credentials and existing keys
INSTAREPORTS_FRESH_ENV="$fresh_env" php <<'PHP'
<?php
$path = getcwd().'/.env';
$content = file_get_contents($path);
$updates = [
    'APP_URL' => 'http://127.0.0.1:8000',
    'SESSION_DOMAIN' => 'null',
    'SESSION_SECURE_COOKIE' => 'false',
];
if (getenv('INSTAREPORTS_FRESH_ENV') === '1') {
    $updates += [
        'APP_NAME' => 'InstaReports',
        'APP_ENV' => 'local',
        'APP_KEY' => '',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => '"'.addcslashes(getcwd().'/database/database.sqlite', "\\\"$").'"',
        'DB_URL' => 'null',
        'SESSION_DRIVER' => 'database',
        'CACHE_STORE' => 'database',
        'FILESYSTEM_DISK' => 'local',
    ];
}
foreach ($updates as $key => $value) {
    $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
    $line = $key.'='.$value;
    $content = preg_match($pattern, $content)
        ? preg_replace_callback($pattern, fn () => $line, $content)
        : rtrim($content).PHP_EOL.$line.PHP_EOL;
}
if (file_put_contents($path, $content) === false) {
    fwrite(STDERR, "Could not configure .env.\n");
    exit(1);
}
PHP

printf '\nInstalling locked dependencies...\n'
composer install --no-interaction --prefer-dist --no-progress
npm ci --include=dev --include=optional --no-audit --no-fund

printf '\nConfiguring Laravel and SQLite...\n'
php artisan config:clear --no-interaction
if ! php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(config("app.key") ? 0 : 1);'; then
    php artisan key:generate --force --no-interaction
fi

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.url')) {
    fwrite(STDERR, "This local setup requires DB_CONNECTION=sqlite and an empty DB_URL.\n");
    exit(1);
}
if (!in_array(config('queue.default'), ['sync', 'database'], true)) {
    fwrite(STDERR, "Use QUEUE_CONNECTION=sync or database for this self-contained setup.\n");
    exit(1);
}
$database = config('database.connections.sqlite.database');
if (!$database || $database === ':memory:' || str_starts_with($database, 'file:')) {
    fwrite(STDERR, "DB_DATABASE must name a persistent SQLite file.\n");
    exit(1);
}
if (!is_dir(dirname($database)) && !mkdir(dirname($database), 0755, true)) {
    fwrite(STDERR, "Could not create the SQLite directory.\n");
    exit(1);
}
if ((!is_file($database) && !touch($database)) || !is_writable($database)) {
    fwrite(STDERR, "The SQLite file must be writable.\n");
    exit(1);
}
if (!config('services.geoapify.key')) {
    fwrite(STDERR, "Note: Set GEOAPIFY_API_KEY in .env to enable address lookup. Manual locations still work.\n");
}
PHP

# These commands preserve existing reports
php artisan migrate --force --no-interaction
php artisan db:seed --class=UserSeeder --force --no-interaction
php artisan view:clear --no-interaction
php artisan route:clear --no-interaction

printf '\nBuilding the Vue frontend...\n'
npm run build
# A stale Vite dev-server marker would make Blade ignore the built assets.
if [[ -f public/hot ]]; then
    php -r 'if (!unlink("public/hot")) { exit(1); }'
fi

queue_connection="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("queue.default");')"
check_port

# Use Laravel's own router from public/, applying upload limits only to this server.
processes=("cd public && php -d upload_max_filesize=10M -d post_max_size=64M -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php")
names="app"
if [[ "$queue_connection" == database ]]; then
    processes+=("php artisan queue:work --timeout=30 --sleep=1 --no-interaction")
    names+=",queue"
fi
processes+=("php artisan schedule:work --no-interaction")
names+=",scheduler"

printf '\nStarting InstaReports — http://127.0.0.1:8000\nPress Ctrl+C to stop all application processes.\n\n'
exec ./node_modules/.bin/concurrently --kill-others --names "$names" "${processes[@]}"