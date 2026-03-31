#!/usr/bin/env bash
# =============================================================
# SKYmanager WiFi — Production Deployment Script
# Usage: bash deploy.sh [--branch main]
# =============================================================
set -euo pipefail

BRANCH="${1:-main}"
APP_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "====================================================="
echo " SKYmanager Deployment — branch: ${BRANCH}"
echo " Directory: ${APP_DIR}"
echo "====================================================="

# ---- 1. Pull latest code ----
echo "[1/8] Pulling latest code..."
git -C "${APP_DIR}" fetch --all --prune
git -C "${APP_DIR}" checkout "${BRANCH}"
git -C "${APP_DIR}" pull origin "${BRANCH}"

# ---- 2. PHP dependencies ----
echo "[2/8] Installing PHP dependencies (production)..."
composer install \
    --working-dir="${APP_DIR}" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet

# ---- 3. Node / frontend ----
echo "[3/8] Installing Node dependencies & building assets..."
npm --prefix "${APP_DIR}" ci --silent
npm --prefix "${APP_DIR}" run build

# ---- 4. Environment checks ----
echo "[4/8] Checking required .env keys..."
required_keys=("APP_KEY" "DB_PASSWORD" "CLICKPESA_CLIENT_ID" "CLICKPESA_API_KEY" "ZTP_VPS_IP" "ZTP_SSTP_SECRET")
missing=()
for key in "${required_keys[@]}"; do
    value=$(grep -E "^${key}=" "${APP_DIR}/.env" 2>/dev/null | cut -d= -f2-)
    if [[ -z "${value}" ]]; then
        missing+=("${key}")
    fi
done
if [[ ${#missing[@]} -gt 0 ]]; then
    echo "  ERROR: Missing .env keys: ${missing[*]}"
    exit 1
fi
echo "  All required .env keys present."

# ---- 5. Cache configuration ----
echo "[5/8] Caching config, routes, views..."
php "${APP_DIR}/artisan" config:cache --no-interaction
php "${APP_DIR}/artisan" route:cache --no-interaction
php "${APP_DIR}/artisan" view:cache --no-interaction
php "${APP_DIR}/artisan" event:cache --no-interaction

# ---- 6. Database migrations ----
echo "[6/8] Running migrations (--force for production)..."
php "${APP_DIR}/artisan" migrate --force --no-interaction

# ---- 7. Queue / scheduler ----
echo "[7/8] Restarting queue workers..."
php "${APP_DIR}/artisan" queue:restart --no-interaction

# ---- 8. Final health check ----
echo "[8/8] Checking app is up..."
php "${APP_DIR}/artisan" about --only=environment --no-interaction 2>/dev/null | grep -E "Environment|Debug" || true

echo ""
echo "====================================================="
echo " Deployment complete."
echo "====================================================="
