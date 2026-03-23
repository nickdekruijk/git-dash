#!/usr/bin/env bash
# db-pull.sh — Download the production MySQL database to your local dev environment.
#
# Configuration is read from .env.production (must exist at project root).
# Required keys in .env.production:
#   DB_PULL_SSH_HOST      e.g. 123.456.78.90 or server.example.com
#   DB_PULL_SSH_USER      e.g. forge
#   DB_PULL_SSH_PORT      e.g. 22  (optional, defaults to 22)
#   DB_PULL_REMOTE_PATH   absolute path to the Laravel app on the server
#
# DB credentials are read automatically from the remote server's .env file.
# Local credentials are read from .env.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Flags ─────────────────────────────────────────────────────────────────────

NO_INTERACTION=false
for arg in "$@"; do
    if [[ "$arg" == "--yes" || "$arg" == "-y" ]]; then
        NO_INTERACTION=true
    fi
done

# ── Helper ────────────────────────────────────────────────────────────────────

# Parses KEY=value from an env file, stripping inline comments and quotes.
parse_env() {
    local file="$1"
    local key="$2"
    local default="${3:-}"
    value=$(grep -E "^${key}=" "$file" 2>/dev/null | head -n1 | cut -d'=' -f2- | sed 's/[[:space:]]*#.*$//' | sed "s/['\"]//g" | xargs)
    echo "${value:-$default}"
}

# Same as parse_env but operates on a string (the remote .env contents).
parse_env_str() {
    local content="$1"
    local key="$2"
    local default="${3:-}"
    value=$(echo "$content" | grep -E "^${key}=" | head -n1 | cut -d'=' -f2- | sed 's/[[:space:]]*#.*$//' | sed "s/['\"]//g" | xargs)
    echo "${value:-$default}"
}

# ── Load local .env.production (SSH details only) ────────────────────────────

PROD_ENV="$SCRIPT_DIR/.env.production"
if [[ ! -f "$PROD_ENV" ]]; then
    echo "Error: .env.production not found at $PROD_ENV"
    echo "Create it with DB_PULL_SSH_HOST, DB_PULL_SSH_USER, and DB_PULL_REMOTE_PATH."
    exit 1
fi

PROD_SSH_HOST=$(parse_env "$PROD_ENV" DB_PULL_SSH_HOST)
PROD_SSH_USER=$(parse_env "$PROD_ENV" DB_PULL_SSH_USER "forge")
PROD_SSH_PORT=$(parse_env "$PROD_ENV" DB_PULL_SSH_PORT "22")
PROD_REMOTE_PATH=$(parse_env "$PROD_ENV" DB_PULL_REMOTE_PATH)

if [[ -z "$PROD_SSH_HOST" || -z "$PROD_REMOTE_PATH" ]]; then
    echo "Error: DB_PULL_SSH_HOST and DB_PULL_REMOTE_PATH must be set in .env.production"
    exit 1
fi

# ── Read DB credentials from the remote .env ──────────────────────────────────

echo "▶ Reading remote .env from ${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_REMOTE_PATH}/.env..."

REMOTE_ENV=$(ssh -p "$PROD_SSH_PORT" "${PROD_SSH_USER}@${PROD_SSH_HOST}" "cat '${PROD_REMOTE_PATH}/.env'")

PROD_DB_HOST=$(parse_env_str "$REMOTE_ENV" DB_HOST "127.0.0.1")
PROD_DB_PORT=$(parse_env_str "$REMOTE_ENV" DB_PORT "3306")
PROD_DB_NAME=$(parse_env_str "$REMOTE_ENV" DB_DATABASE)
PROD_DB_USER=$(parse_env_str "$REMOTE_ENV" DB_USERNAME)
PROD_DB_PASS=$(parse_env_str "$REMOTE_ENV" DB_PASSWORD "")

if [[ -z "$PROD_DB_NAME" || -z "$PROD_DB_USER" ]]; then
    echo "Error: could not read DB_DATABASE or DB_USERNAME from remote .env"
    exit 1
fi

# ── Load local config ─────────────────────────────────────────────────────────

LOCAL_ENV="$SCRIPT_DIR/.env"
LOCAL_DB_HOST=$(parse_env "$LOCAL_ENV" DB_HOST "127.0.0.1")
LOCAL_DB_PORT=$(parse_env "$LOCAL_ENV" DB_PORT "3306")
LOCAL_DB_NAME=$(parse_env "$LOCAL_ENV" DB_DATABASE)
LOCAL_DB_USER=$(parse_env "$LOCAL_ENV" DB_USERNAME "root")
LOCAL_DB_PASS=$(parse_env "$LOCAL_ENV" DB_PASSWORD "")

# ── Confirm before wiping local DB ───────────────────────────────────────────

echo ""
echo "  Production  →  ${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_SSH_PORT}  /  ${PROD_DB_NAME}"
echo "  Local       →  ${LOCAL_DB_HOST}:${LOCAL_DB_PORT}  /  ${LOCAL_DB_NAME}"
echo ""
if [[ "$NO_INTERACTION" == true ]]; then
    echo "Running non-interactively (--yes flag set)."
else
    read -r -p "This will REPLACE your local database '${LOCAL_DB_NAME}'. Continue? [y/N] " confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 0
    fi
fi

# ── Dump from production over SSH ────────────────────────────────────────────

DUMP_FILE="$SCRIPT_DIR/storage/app/private/.db-pull-$(date +%Y%m%d%H%M%S).sql.gz"

echo ""
echo "▶ Dumping production database..."

MYSQL_PWD_ARG=""
if [[ -n "$PROD_DB_PASS" ]]; then
    MYSQL_PWD_ARG="MYSQL_PWD='${PROD_DB_PASS}'"
fi

ssh -p "$PROD_SSH_PORT" "${PROD_SSH_USER}@${PROD_SSH_HOST}" \
    "${MYSQL_PWD_ARG} mysqldump \
        --host='${PROD_DB_HOST}' \
        --port='${PROD_DB_PORT}' \
        --user='${PROD_DB_USER}' \
        --single-transaction \
        --quick \
        --skip-lock-tables \
        --no-tablespaces \
        '${PROD_DB_NAME}'" \
    | gzip > "$DUMP_FILE"

echo "   Dump saved to $(basename "$DUMP_FILE")"

# ── Import into local database ────────────────────────────────────────────────

echo "▶ Importing into local database '${LOCAL_DB_NAME}'..."

LOCAL_MYSQL_OPTS=(--host="$LOCAL_DB_HOST" --port="$LOCAL_DB_PORT" --user="$LOCAL_DB_USER")
if [[ -n "$LOCAL_DB_PASS" ]]; then
    LOCAL_MYSQL_OPTS+=(--password="$LOCAL_DB_PASS")
fi

mysql "${LOCAL_MYSQL_OPTS[@]}" -e "DROP DATABASE IF EXISTS \`${LOCAL_DB_NAME}\`; CREATE DATABASE \`${LOCAL_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip < "$DUMP_FILE" | mysql "${LOCAL_MYSQL_OPTS[@]}" "$LOCAL_DB_NAME"

# ── Clean up dump file ────────────────────────────────────────────────────────

rm "$DUMP_FILE"

# ── Run local migrations (to apply any pending local-only migrations) ─────────

echo "▶ Running migrations..."
php "$SCRIPT_DIR/artisan" migrate --no-interaction --force

echo ""
echo "✓ Done — local database '${LOCAL_DB_NAME}' is now a copy of production."
