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
export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
[ -s "$NVM_DIR/nvm.sh" ] || { echo "✗ nvm not found at $NVM_DIR/nvm.sh" >&2; exit 1; }

# nvm isn't safe under `set -e`/`set -u` (it reads unset vars and some commands
# return non-zero), so relax strict mode just while we load and select Node.
set +eu
# shellcheck disable=SC1091
. "$NVM_DIR/nvm.sh"
nvm install "$NODE_VERSION"   # installs if missing, otherwise just selects it
set -eu

command -v node >/dev/null || { echo "✗ Node not available after nvm" >&2; exit 1; }
echo "  using $(node --version)"

npm install
npm run build

echo "→ Installing PHP dependencies"
$COMPOSER install --no-interaction

echo "→ Running migrations"
$PHP artisan migrate --force

echo "✓ Deploy finished"
