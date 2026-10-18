#!/usr/bin/env bash
# Renames the Nexora database of an EXISTING install from govdoc_bd to nexora by copying it (MySQL cannot rename in place).
# The old database is kept until you drop it yourself. Usage:  bash scripts/rename-database.sh "/path/to/existing/install"
# If your MySQL root user has a password:  MYSQL_ROOT_PASSWORD=yourpassword bash scripts/rename-database.sh "/path/..."
set -euo pipefail
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
die() { printf "\033[31mERROR: %s\033[0m\n" "$*" >&2; exit 1; }

TARGET="${1:-}"
NEW_DB="nexora"; NEW_USER="nexora"; NEW_PASS="nexora_dev_pass"
ENV="$TARGET/laravel-app/.env"
[ -n "$TARGET" ] && [ -f "$ENV" ] || die "Give the install folder that contains laravel-app/, e.g.  bash scripts/rename-database.sh \"\$HOME/Desktop/your-install-folder\""

get() { grep -E "^$1=" "$ENV" | head -1 | cut -d= -f2-; }
set_env() { if grep -qE "^$1=" "$ENV"; then perl -pi -e "s|^$1=.*|$1=$2|" "$ENV"; else echo "$1=$2" >> "$ENV"; fi; }

HOST="$(get DB_HOST)"; PORT="$(get DB_PORT)"; OLD_DB="$(get DB_DATABASE)"
[ "$OLD_DB" != "$NEW_DB" ] || { echo "Already using database '$NEW_DB'. Nothing to do."; exit 0; }

ARGS=(-h "${HOST:-127.0.0.1}" -P "${PORT:-3306}" -uroot)
[ -n "${MYSQL_ROOT_PASSWORD:-}" ] && ARGS+=("-p${MYSQL_ROOT_PASSWORD}")

echo "==> Creating database '$NEW_DB' and user '$NEW_USER'"
mysql "${ARGS[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$NEW_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$NEW_USER'@'localhost' IDENTIFIED BY '$NEW_PASS'; CREATE USER IF NOT EXISTS '$NEW_USER'@'127.0.0.1' IDENTIFIED BY '$NEW_PASS'; GRANT ALL PRIVILEGES ON \`$NEW_DB\`.* TO '$NEW_USER'@'localhost'; GRANT ALL PRIVILEGES ON \`$NEW_DB\`.* TO '$NEW_USER'@'127.0.0.1'; FLUSH PRIVILEGES;" \
  || die "Could not connect as MySQL root. Start MySQL in XAMPP, or pass MYSQL_ROOT_PASSWORD=..."

TABLES="$(mysql "${ARGS[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$NEW_DB'")"
[ "$TABLES" = "0" ] || die "Database '$NEW_DB' already contains tables; refusing to overwrite it. Nothing was changed."

echo "==> Copying '$OLD_DB' into '$NEW_DB'"
mysqldump "${ARGS[@]}" --single-transaction "$OLD_DB" | mysql "${ARGS[@]}" "$NEW_DB"

count() { mysql "${ARGS[@]}" -N -e "SELECT COUNT(*) FROM \`$1\`.$2"; }
for t in users documents; do
  [ "$(count "$OLD_DB" $t)" = "$(count "$NEW_DB" $t)" ] || die "Row count for '$t' differs after the copy. .env was NOT changed; the old database is untouched."
done
echo "    copy verified (users: $(count "$NEW_DB" users), documents: $(count "$NEW_DB" documents))"

echo "==> Pointing the application at '$NEW_DB'"
set_env DB_DATABASE "$NEW_DB"; set_env DB_USERNAME "$NEW_USER"; set_env DB_PASSWORD "$NEW_PASS"
cd "$TARGET/laravel-app"
php artisan config:clear >/dev/null
php artisan migrate --force

cat <<MSG

Done. Nexora now uses the database '$NEW_DB'. Restart "php artisan serve".
The old database '$OLD_DB' was left in place as a backup. When you have checked everything works, remove it with:
  mysql ${ARGS[*]} -e "DROP DATABASE $OLD_DB"
MSG
