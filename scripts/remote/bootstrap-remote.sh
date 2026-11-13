# Se ejecuta EN EL SERVIDOR de IONOS a través de scripts/bootstrap.sh (no a mano).
# Argumentos: fase (core|setup) WP_PATH MIS_PATH SITE_URL TITLE ADMIN EMAIL "PLUGINS" "THEMES" SKIP_CONTENT
# Antes de este archivo, bootstrap.sh antepone la línea WP_ADMIN_PASSWORD=…
set -euo pipefail
PHASE="$1"; WP="$HOME/$2"; MIS="$HOME/$3"; URL="$4"; TITLE="$5"; ADMIN="$6"; EMAIL="$7"
PLUGINS="$8"; THEMES="$9"; SKIP_CONTENT="${10:-0}"
test -f "$WP/wp-config.php" || { echo "✗ Falta $WP/wp-config.php (súbelo desde la plantilla)"; exit 1; }

# WP-CLI: el de IONOS si existe; si no, el .phar oficial en ~/bin.
if command -v wp >/dev/null 2>&1; then WPCLI="$(command -v wp)"; else
	WPCLI="$HOME/bin/wp"
	if [[ ! -f "$WPCLI" ]]; then
		mkdir -p "$HOME/bin"
		curl -fsSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	fi
fi
wp() { php "$WPCLI" --path="$WP" "$@"; }

if [[ "$PHASE" == "core" ]]; then
	echo "→ PHP $(php -r 'echo PHP_VERSION;') · $(wp --version)"
	[[ -f "$WP/wp-load.php" ]] || wp core download --locale=en_US --skip-content
	mkdir -p "$WP/wp-content/themes" "$WP/wp-content/plugins" "$WP/wp-content/mu-plugins" "$WP/wp-content/uploads"
	if ! wp core is-installed 2>/dev/null; then
		wp core install --url="$URL" --title="$TITLE" --admin_user="$ADMIN" \
			--admin_email="$EMAIL" --admin_password="$WP_ADMIN_PASSWORD" --skip-email
	fi
	wp language core install es_ES || true
	wp option update blog_public 0
	wp option update timezone_string Europe/Madrid
	for t in $THEMES; do wp theme is-installed "$t" || wp theme install "$t"; done
	for p in $PLUGINS; do wp plugin is-installed "$p" || wp plugin install "$p"; done
	wp plugin activate $PLUGINS
	wp plugin deactivate cloudflare || true   # se conecta con APO, solo en producción
	echo "✓ Núcleo y plugins listos"
else
	wp plugin activate mis-contenido maros-connector
	wp theme activate marbellainstyle
	wp rewrite structure '/%postname%/' --hard
	SETUP="idiomas"; [[ "$SKIP_CONTENT" == "1" ]] || SETUP="idiomas contenido-inicial"
	for s in $SETUP; do
		f="$MIS/current/scripts/setup/$s.php"
		if [[ -f "$f" ]]; then wp eval-file "$f"; fi
	done
	wp rewrite flush --hard
	wp core version --extra
	echo "✓ Pruebas lista en $URL"
fi
