#!/usr/bin/env bash
# Trae a la web local (wp-env, http://localhost:8888) la base de datos y las fotos de PRUEBAS.
# Julio 24-11-2026. Runbook: docs/runbooks/entorno-local.md, «Traer contenido de pruebas».
#
# Uso, desde la raíz del repo y con wp-env arrancado:
#   scripts/bajar-pruebas.sh            # lanza «Bajar pruebas a local» en GitHub, espera y descarga
#   scripts/bajar-pruebas.sh <carpeta>  # usa una descarga que ya tienes (bd.sql.gz, fotos.tar.gz, info.env)
#
# Qué hace: guarda una copia de la base local en scripts/.pruebas/local-<fecha>.sql.gz, borra la base
# local, importa la de pruebas, cambia el prefijo de tablas al de wp-env (wp_), cambia las URLs de
# pruebas por http://localhost:8888, copia las fotos y deja el usuario admin / password (solo local).
# También pausa la sincronización con marOS: la web local habla con el mock y pisaría las fichas.
# Para volver a la base anterior: npm run wp -- db import wp-content/mis-scripts/.pruebas/local-<fecha>.sql
set -euo pipefail
cd "$(dirname "$0")/.."
DIR="scripts/.pruebas"          # dentro del contenedor: wp-content/mis-scripts/.pruebas
CDIR="wp-content/mis-scripts/.pruebas"
LOCAL_URL="http://localhost:8888"
wp() { npx wp-env run cli wp "$@"; }
mkdir -p "$DIR"

# 1. La descarga de GitHub.
if [[ $# -ge 1 ]]; then
	SRC="$1"
else
	command -v gh >/dev/null || { echo "✗ Hace falta gh (brew install gh && gh auth login), o pasa la carpeta descargada"; exit 1; }
	echo "→ Lanzo «Bajar pruebas a local» en GitHub"
	gh workflow run bajar-pruebas.yml --ref main
	sleep 8
	RUN="$(gh run list --workflow bajar-pruebas.yml --limit 1 --json databaseId -q '.[0].databaseId')"
	gh run watch "$RUN" --exit-status
	SRC="$DIR/descarga"
	rm -rf "$SRC"
	gh run download "$RUN" -n pruebas -D "$SRC"
fi
[[ -f "$SRC/bd.sql.gz" && -f "$SRC/info.env" ]] || { echo "✗ No encuentro bd.sql.gz e info.env en $SRC"; exit 1; }
# shellcheck disable=SC1091
source "$SRC/info.env"
[[ -n "${SITE_URL:-}" && -n "${PREFIJO:-}" ]] || { echo "✗ info.env sin SITE_URL o PREFIJO"; exit 1; }
echo "→ Copia de pruebas del $FECHA ($SITE_URL, prefijo $PREFIJO)"

# 2. Copia de seguridad de la base local.
STAMP="$(date +%Y%m%d-%H%M%S)"
wp db export "$CDIR/local-$STAMP.sql" --quiet
gzip "$DIR/local-$STAMP.sql"
echo "✓ Base local guardada en $DIR/local-$STAMP.sql.gz"

# 3. Importar.
gunzip -c "$SRC/bd.sql.gz" > "$DIR/pruebas.sql"
wp db reset --yes --quiet
wp db import "$CDIR/pruebas.sql" --quiet
rm -f "$DIR/pruebas.sql"

# 4. Prefijo de tablas: el de pruebas (p. ej. mis_) pasa al de wp-env (wp_).
if [[ "$PREFIJO" != "wp_" ]]; then
	echo "→ Prefijo $PREFIJO → wp_"
	# Los nombres salen del propio volcado (no de la salida de wp-env, que añade sus avisos).
	tablas="$(gunzip -c "$SRC/bd.sql.gz" | grep -oE "^CREATE TABLE \`${PREFIJO}[A-Za-z0-9_]+\`" | sed -E 's/^CREATE TABLE `(.*)`$/\1/' | sort -u)"
	{
		for t in $tablas; do printf 'RENAME TABLE `%s` TO `wp_%s`;\n' "$t" "${t#"$PREFIJO"}"; done
		printf "UPDATE wp_options SET option_name = 'wp_user_roles' WHERE option_name = '%suser_roles';\n" "$PREFIJO"
		printf "UPDATE wp_usermeta SET meta_key = CONCAT('wp_', SUBSTRING(meta_key, %d)) WHERE LEFT(meta_key, %d) = '%s';\n" $(( ${#PREFIJO} + 1 )) ${#PREFIJO} "$PREFIJO"
	} > "$DIR/prefijo.sql"
	wp db import "$CDIR/prefijo.sql" --quiet
	rm -f "$DIR/prefijo.sql"
fi

# 5. URLs (también las escapadas en JSON y sin protocolo).
HOST="${SITE_URL#https://}"
wp search-replace "$SITE_URL" "$LOCAL_URL" --all-tables --precise --quiet
wp search-replace "https:\\/\\/$HOST" "http:\\/\\/localhost:8888" --all-tables --precise --quiet
wp search-replace "//$HOST" "//localhost:8888" --all-tables --precise --quiet

# 6. Fotos.
if [[ -f "$SRC/fotos.tar.gz" ]]; then
	echo "→ Fotos"
	cp "$SRC/fotos.tar.gz" "$DIR/fotos.tar.gz"
	npx wp-env run cli bash -c "tar -xzf $CDIR/fotos.tar.gz -C wp-content"
	rm -f "$DIR/fotos.tar.gz"
fi

# 7. Lo propio del entorno local.
wp option update maros_sync_paused 1 --quiet
wp theme activate marbellainstyle --quiet || true
wp plugin deactivate cloudflare --quiet || true
if wp user get admin --field=ID >/dev/null 2>&1; then
	wp user update admin --user_pass=password --role=administrator --skip-email --quiet
else
	wp user create admin admin@localhost.test --user_pass=password --role=administrator --quiet
fi
wp rewrite flush --quiet
wp cache flush --quiet || true
echo "✓ Listo: $LOCAL_URL (admin / password). La sincronización con marOS está pausada:"
echo "  para reactivarla, npm run wp -- option delete maros_sync_paused"
