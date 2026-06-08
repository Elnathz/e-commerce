#!/bin/bash
# rollback.sh

echo "Starting Emergency Rollback..."

echo "[1/5] Disabling Queue Workers..."
php artisan horizon:terminate 2>/dev/null || true
# Stop supervisor queue workers
# sudo supervisorctl stop all

echo "[2/5] Restoring Previous Release..."
# Contoh jika menggunakan symlink (Envoyer / Deployer)
# ln -nfs /path/to/releases/previous /path/to/current

echo "[3/5] Restoring Environment Backup..."
if [ -f ".env.backup" ]; then
    cp .env.backup .env
fi

echo "[4/5] Verifying Payment Gateway Health..."
php artisan vendor:health

echo "[5/5] Re-Enabling Workers..."
# sudo supervisorctl start all
php artisan queue:restart

echo "Rollback Completed!"
