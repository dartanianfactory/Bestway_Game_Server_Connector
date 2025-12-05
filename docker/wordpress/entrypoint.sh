#!/bin/sh
set -e

echo "Starting WordPress container..."
echo "Checking PHP modules..."

php -m | grep -i pdo || true
php -m | grep -i mysql || true

echo "Waiting for WordPress database..."
for i in {1..30}; do
    if mysql -h db -u wordpress -pwordpress -e "SELECT 1" 2>/dev/null; then
        echo "WordPress database is ready!"
        break
    fi
    echo "Waiting for WordPress database... ($i/30)"
    sleep 2
done

echo "Testing connection to game_db..."
if mysql -h db_game -u game_user -pgame_password -e "SELECT 1" 2>/dev/null; then
    echo "✅ Connected to game_db successfully!"
else
    echo "⚠️ Cannot connect to game_db yet, continuing..."
fi

exec docker-entrypoint.sh php-fpm