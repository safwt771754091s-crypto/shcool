#!/bin/sh
set -eu

cd /var/www/html

# Production database credentials belong in Render Environment, never in Git.
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
  if [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ] || [ -z "${DB_USERNAME:-}" ] || [ -z "${DB_PASSWORD:-}" ]; then
    echo "Database configuration incomplete: set DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in Render Environment." >&2
    exit 1
  fi

  echo "Checking MySQL connectivity (host and credentials are not logged)..."
  php -r '
    $host = getenv("DB_HOST");
    $port = getenv("DB_PORT") ?: "3306";
    $db = getenv("DB_DATABASE");
    $user = getenv("DB_USERNAME");
    $pass = getenv("DB_PASSWORD");
    $options = [PDO::ATTR_TIMEOUT => 5];
    $ca = getenv("MYSQL_ATTR_SSL_CA");
    if ($ca !== false && $ca !== "" && is_readable($ca)) {
      $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    }
    $lastError = "unknown connection error";
    for ($i = 1; $i <= 30; $i++) {
      try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, $options);
        $pdo->query("SELECT 1");
        echo "MySQL connectivity verified.\n";
        exit(0);
      } catch (Throwable $e) {
        $lastError = $e->getMessage();
        if ($i === 1 || $i % 10 === 0) {
          fwrite(STDERR, "MySQL connection attempt $i/30 failed: " . $lastError . "\n");
        }
        sleep(2);
      }
    }
    fwrite(STDERR, "MySQL connection failed after 60 seconds. Last error: " . $lastError . "\n");
    exit(1);
  '
fi

php artisan migrate --force

if [ "${RUN_DB_SEED:-false}" = "true" ]; then
  php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache || true
php artisan view:cache

exec apache2-foreground
