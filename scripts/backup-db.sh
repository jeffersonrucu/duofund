#!/usr/bin/env bash
#
# Dump do banco do DuoFund, para rodar no cron do cPanel.
#
# Roda direto no shell, então não depende de proc_open/exec do PHP — que
# hospedagem compartilhada costuma bloquear via disable_functions. Foi por
# isso que não usamos spatie/laravel-backup aqui.
#
# Uso:  ./scripts/backup-db.sh
# Vars: BACKUP_DIR (padrão ~/duofund-backups), BACKUP_KEEP_DAYS (padrão 14)
#
# Cron (diário às 03h20, logando falhas):
#   20 3 * * * /caminho/do/projeto/scripts/backup-db.sh >> ~/backup.log 2>&1

set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
DEST="${BACKUP_DIR:-$HOME/duofund-backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"

if [ ! -f "$APP_DIR/.env" ]; then
    echo "não achei $APP_DIR/.env" >&2
    exit 1
fi

# Lê uma chave do .env, tirando aspas e comentário de fim de linha
env_get() {
    sed -n "s/^$1=//p" "$APP_DIR/.env" \
        | head -1 \
        | sed -e 's/[[:space:]]*#.*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
}

DB_HOST="$(env_get DB_HOST)"
DB_PORT="$(env_get DB_PORT)"
DB_NAME="$(env_get DB_DATABASE)"
DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"

if [ -z "$DB_NAME" ]; then
    echo "DB_DATABASE vazio em $APP_DIR/.env" >&2
    exit 1
fi

mkdir -p "$DEST"
chmod 700 "$DEST"

# Credenciais em arquivo temporário: em --password= elas apareceriam no `ps`
CNF="$(mktemp)"
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
cat > "$CNF" <<EOF
[client]
host=${DB_HOST:-localhost}
port=${DB_PORT:-3306}
user=$DB_USER
password=$DB_PASS
EOF

FILE="$DEST/duofund-$(date +%Y-%m-%d_%H%M).sql.gz"

# --no-tablespaces: sem ele o mysqldump 8 pede o privilégio PROCESS, que
# usuário de hospedagem compartilhada não tem.
mysqldump --defaults-extra-file="$CNF" \
    --single-transaction --quick --routines --events \
    --no-tablespaces --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$FILE"

chmod 600 "$FILE"

# Dump vazio comprime para ~20 bytes: melhor falhar alto do que guardar lixo
if [ "$(stat -c%s "$FILE")" -lt 1024 ]; then
    echo "dump suspeito, menor que 1KB — descartando: $FILE" >&2
    rm -f "$FILE"
    exit 1
fi

find "$DEST" -name 'duofund-*.sql.gz' -mtime "+$KEEP_DAYS" -delete

echo "ok: $FILE ($(du -h "$FILE" | cut -f1))"
