# Se ejecuta EN EL SERVIDOR de IONOS a través de scripts/bootstrap.sh (no a mano).
# Argumentos: fase (core|setup) WP_PATH MIS_PATH SITE_URL TITLE ADMIN EMAIL "PLUGINS" "THEMES" SKIP_CONTENT
# Antes de este archivo, bootstrap.sh antepone la línea WP_ADMIN_PASSWORD=… y php-bin.sh
set -euo pipefail
PHASE="$1"; WP="$HOME/$2"; MIS="$HOME/$3"; URL="$4"; TITLE="$5"; ADMIN="$6"; EMAIL="$7"
PLUGINS="$8"; THEMES="$9"; SKIP_CONTENT="${10:-0}"
test -f "$WP/wp-config.php" || { echo "✗ Falta $WP/wp-config.php (súbelo desde la plantilla)"; exit 1; }

wp() { "$PHPBIN" "$WPCLI" --path="$WP" "$@"; }

if [[ "$PHASE" == "core" ]]; then
	echo "→ $PHPBIN $("$PHPBIN" -r 'echo PHP_VERSION;') · $(wp --version)"
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
	# Propiedades del mock (o de marOS pruebas): ficha, zona, equipamiento y fotos.
	[[ "$SKIP_CONTENT" == "1" ]] || wp maros sync --publish || echo "  (aviso: wp maros sync falló)"
	for s in $SETUP; do
		f="$MIS/current/scripts/setup/$s.php"
		if [[ -f "$f" ]]; then wp eval-file "$f"; fi
	done
	wp rewrite flush --hard
	wp core version --extra
	echo "✓ Pruebas lista en $URL"
fi
