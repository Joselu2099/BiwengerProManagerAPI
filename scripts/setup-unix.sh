#!/usr/bin/env bash
set -euo pipefail

echo "== BiwengerProManagerAPI — Unix setup script =="

fail() { echo "ERROR: $1" >&2; exit 1; }

# Check PHP
if ! command -v php >/dev/null 2>&1; then
  fail "php is not installed or not in PATH. Install PHP 7.4+ and try again."
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "0")
echo "PHP version: ${PHP_VERSION}"

# Basic version check (major.minor >= 7.4)
PHP_MAJOR_MINOR=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "0.0")
php_v_ok=false
if [[ "${PHP_MAJOR_MINOR}" > "7.3" ]]; then
  php_v_ok=true
fi
if [ "${php_v_ok}" = false ]; then
  echo "Warning: PHP version appears to be < 7.4 — tests and runtime may not work as expected."
fi

echo "Checking Composer..."
if ! command -v composer >/dev/null 2>&1; then
  fail "composer not found. Install Composer and run this script again."
fi

echo "Checking mongosh (MongoDB shell)..."
if ! command -v mongosh >/dev/null 2>&1; then
  echo "mongosh not found. If you use the legacy mongo shell, try installing mongosh for this script to validate DB connectivity."
else
  echo "Testing mongosh connectivity (ping)..."
  if mongosh --eval "db.adminCommand('ping')" >/dev/null 2>&1; then
    echo "mongosh ping succeeded."
  else
    echo "Warning: mongosh ping failed. Ensure MongoDB is running and accessible.";
  fi
fi

echo "Checking PHP mongodb extension..."
if php -m | grep -iq "mongodb"; then
  echo "PHP mongodb extension is installed"
else
  echo "Warning: PHP 'mongodb' extension not found. Install it (pecl install mongodb) and enable the extension in php.ini."
fi

echo "Installing PHP dependencies (composer install)..."
composer install --no-interaction

# Ensure root config/.env exists: copy from example if available
if [ -f "config/.env.example" ] && [ ! -f "config/.env" ]; then
  echo "Copying config/.env.example -> config/.env"
  cp config/.env.example config/.env || echo "Could not copy config/.env.example — create config/.env manually."
fi

# Prepare test database (drop test database) — follow docs
if command -v mongosh >/dev/null 2>&1; then
  echo "Cleaning test database 'biwenger_api_test'..."
  # Non-interactive drop (safe for CI); user should run this in dev machines where it's expected
  mongosh biwenger_api_test --eval "db.dropDatabase()" || echo "Warning: failed to drop biwenger_api_test (it may not exist or mongosh couldn't connect)."
fi

echo "Setup complete. Recommended next steps:"
echo " - Edit config file (config/.env) with MongoDB and API credentials if needed."
echo " - Start the dev server: ./scripts/run.sh  (or php -S localhost:8000 -t public)"
echo " - Run tests: vendor/bin/phpunit"

exit 0
