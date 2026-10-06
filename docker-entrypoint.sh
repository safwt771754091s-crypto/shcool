#!/bin/sh
set -e

cd /var/www/html

# Render supplies the real MySQL credentials as environment variables.
# Never store production credentials in GitHub.
if [ "${DB_CONNECTION:-}" = "mysql" ]; then
  echo "Waiting for MySQL..."
  php -r '
    $host=getenv("DB_HOST"); $port=getenv("DB_PORT") ?: "3306";
    $db=getenv("DB_DATABASE"); $user=getenv("DB_USERNAME"); $pass=getenv("DB_PASSWORD");
    for ($i=1; $i<=30; $i++) {
      try {
        new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_TIMEOUT=>3]);
        exit(0);
      } catch (Throwable $e) { fwrite(STDERR, "MySQL not ready ($i/30)\n"); sleep(2); }
    }
    fwrite(STDERR, "MySQL connection failed after 60 seconds.\n"); exit(1);
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
