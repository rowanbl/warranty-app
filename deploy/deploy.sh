#!/usr/bin/env bash
#
# One command to deploy. Runs ON THE SERVER, from the app's git checkout — it's
# the whole "git pull + build + composer + migrate" dance you used to type by
# hand, in one place. GitHub Actions runs exactly this over SSH on every merge to
# main, but you can also run it yourself:
#
#   cd ~/domains/warrantyapp.online/public_html && bash deploy/deploy.sh
#
set -euo pipefail

# This host can't put php/composer/node on the PATH, so spell them out here.
PHP="/opt/alt/php85/usr/bin/php"
COMPOSER="$PHP /usr/local/bin/composer2.phar"
NODE_VERSION="20"

# Work from the repo root, however the script was invoked.
cd "$(dirname "$0")/.."

echo "→ Pulling latest code"
git pull --ff-only

echo "→ Building the frontend (Node $NODE_VERSION via nvm)"
export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1091
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
nvm use "$NODE_VERSION" >/dev/null 2>&1 || nvm install "$NODE_VERSION"
npm install
npm run build

echo "→ Installing PHP dependencies"
$COMPOSER install --no-interaction

echo "→ Running migrations"
$PHP artisan migrate --force

echo "✓ Deploy finished"
