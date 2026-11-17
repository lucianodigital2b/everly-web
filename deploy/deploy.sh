#!/usr/bin/env bash
# Deploy do everly.koffeecode.com na VPS. Roda como o user `everlyapp`, chamado pelo
# GitHub Actions por SSH. A chave do Actions só pode rodar este script (command= no
# authorized_keys):
#   command="/home/everlyapp/deploy.sh",no-port-forwarding,no-pty,... ssh-ed25519 ... github-actions@everly
# O SHA esperado chega em SSH_ORIGINAL_COMMAND. Cópia versionada em deploy/deploy.sh do
# repo; depois de mudar aqui, copiar pra /home/everlyapp/deploy.sh (e tirar o \r).
set -euo pipefail

APP=/home/everlyapp/htdocs/everly.koffeecode.com
PHP=/usr/bin/php8.4
SHA="${SSH_ORIGINAL_COMMAND:-}"

if ! [[ "$SHA" =~ ^[0-9a-f]{40}$ ]]; then
    echo "SHA inválido: '$SHA'" >&2
    exit 2
fi

cd "$APP"

exec 9>/home/everlyapp/deploy.lock
flock -n 9 || { echo "outro deploy rodando" >&2; exit 3; }

echo "==> git"
git fetch --quiet origin main
if [ "$(git rev-parse origin/main)" != "$SHA" ]; then
    echo "origin/main não é $SHA (já tem commit mais novo?)" >&2
    exit 4
fi
# Checkout de deploy: mudança local no servidor é descartada (.env, vendor,
# storage e public/build ficam fora do git e não são tocados).
git reset --hard --quiet "$SHA"

echo "==> composer"
$PHP /usr/local/bin/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet

# Sem isso o wayfinder lê o route cache antigo e o build quebra nas rotas novas.
$PHP artisan optimize:clear

echo "==> npm"
npm ci --no-audit --no-fund --loglevel=error
npm run build --silent

echo "==> artisan"
$PHP artisan migrate --force
$PHP artisan optimize
$PHP artisan queue:restart

echo "$SHA" > public/REVISION
echo "==> no ar: $SHA"
