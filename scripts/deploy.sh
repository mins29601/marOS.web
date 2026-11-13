#!/usr/bin/env bash
# Despliega el código propio (tema hijo, plugins propios, mu-plugins, mock y scripts) en un
# entorno de IONOS por SSH. Lo ejecuta GitHub Actions; también sirve a mano.
# Spec 001, US5 y plan («Despliegue atómico»).
#
# Variables (GitHub Environment del entorno):
#   SSH_HOST, SSH_USER          acceso SSH de IONOS (clave en ~/.ssh, la pone el workflow)
#   SSH_PORT                    opcional, 22 por defecto
#   WP_PATH                     carpeta de WordPress relativa al home, p. ej. «staging»
#   MIS_PATH                    carpeta de versiones relativa al home, p. ej. «mis/staging»
#   DEPLOY_MODE                 «symlink» (por defecto) o «copy» si IONOS no admite enlaces
#   DEPLOY_CF_ONLY              «1» (por defecto): el .htaccess solo admite IPs de Cloudflare
#   HTACCESS                    plantilla, p. ej. config/htaccess/staging.htaccess
#   GITHUB_SHA                  commit que se despliega
set -euo pipefail

: "${SSH_HOST:?}" "${SSH_USER:?}" "${WP_PATH:?}" "${MIS_PATH:?}" "${HTACCESS:?}"
SSH_PORT="${SSH_PORT:-22}"
DEPLOY_MODE="${DEPLOY_MODE:-symlink}"
DEPLOY_CF_ONLY="${DEPLOY_CF_ONLY:-1}"
SHA="${GITHUB_SHA:-$(git rev-parse HEAD)}"
SHORT="${SHA:0:12}"
REMOTE="${SSH_USER}@${SSH_HOST}"
SSH=(ssh -p "$SSH_PORT" -o BatchMode=yes "$REMOTE")

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

echo "→ Preparando la versión $SHORT"
mkdir -p "$BUILD/wp-content/themes" "$BUILD/wp-content/plugins"
cp -R wp-content/themes/marbellainstyle "$BUILD/wp-content/themes/"
for p in wp-content/plugins/*/; do cp -R "$p" "$BUILD/wp-content/plugins/"; done
cp -R wp-content/mu-plugins "$BUILD/wp-content/"
cp -R mock scripts "$BUILD/"
echo "$SHA" > "$BUILD/REVISION"

# Bloque de .htaccess con las IPs de Cloudflare del momento.
{
	echo "# BEGIN MIS"
	while IFS= read -r line; do
		if [[ "$line" == "#MIS_CLOUDFLARE_ONLY" ]]; then
			if [[ "$DEPLOY_CF_ONLY" == "1" ]]; then
				echo "<IfModule mod_authz_core.c>"
				echo "	<RequireAny>"
				for url in https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6; do
					curl -fsS "$url" | sed '/^$/d; s/^/		Require ip /'
					echo
				done | sed '/^$/d'
				echo "	</RequireAny>"
				echo "</IfModule>"
			fi
		else
			echo "$line"
		fi
	done < "$HTACCESS"
	echo "# END MIS"
} > "$BUILD/htaccess-mis"
if [[ "$DEPLOY_CF_ONLY" == "1" ]] && ! grep -q "Require ip" "$BUILD/htaccess-mis"; then
	echo "✗ No se pudo leer la lista de IPs de Cloudflare; no se despliega." >&2
	exit 1
fi

echo "→ Subiendo a ~/$MIS_PATH/releases/$SHORT"
# tar por SSH: no depende de que el servidor tenga rsync.
tar -C "$BUILD" -czf - . | "${SSH[@]}" \
	"rm -rf ~/$MIS_PATH/releases/$SHORT && mkdir -p ~/$MIS_PATH/releases/$SHORT && tar -xzf - -C ~/$MIS_PATH/releases/$SHORT"

echo "→ Activando ($DEPLOY_MODE)"
"${SSH[@]}" bash -s -- "$WP_PATH" "$MIS_PATH" "$SHORT" "$DEPLOY_MODE" <<'REMOTE_SCRIPT'
set -euo pipefail
WP="$HOME/$1"; MIS="$HOME/$2"; SHORT="$3"; MODE="$4"
REL="$MIS/releases/$SHORT"
test -f "$WP/wp-config.php" || { echo "✗ No hay WordPress en $WP (falta el arranque)"; exit 1; }

if [[ "$MODE" != "copy" ]]; then
	ln -sfn "$REL" "$MIS/current.new" && mv -Tf "$MIS/current.new" "$MIS/current"
	CUR="$MIS/current"
	link() { # sustituye una carpeta real (p. ej. de un despliegue en modo copia) por el enlace
		if [[ -e "$2" && ! -L "$2" ]]; then rm -rf "$2"; fi
		ln -sfn "$1" "$2"
	}
	link "$CUR/wp-content/themes/marbellainstyle" "$WP/wp-content/themes/marbellainstyle"
	for p in "$REL"/wp-content/plugins/*/; do
		n="$(basename "$p")"; link "$CUR/wp-content/plugins/$n" "$WP/wp-content/plugins/$n"
	done
	mkdir -p "$WP/wp-content/mu-plugins"
	for f in "$REL"/wp-content/mu-plugins/*.php; do
		n="$(basename "$f")"; link "$CUR/wp-content/mu-plugins/$n" "$WP/wp-content/mu-plugins/$n"
	done
else
	swap() { # copia al lado y cambia de nombre: el hueco es de milisegundos
		rm -rf "$2.new" "$2.old"; cp -a "$1" "$2.new"
		[[ -e "$2" ]] && mv "$2" "$2.old"; mv "$2.new" "$2"; rm -rf "$2.old"
	}
	swap "$REL/wp-content/themes/marbellainstyle" "$WP/wp-content/themes/marbellainstyle"
	for p in "$REL"/wp-content/plugins/*/; do
		n="$(basename "$p")"; swap "${p%/}" "$WP/wp-content/plugins/$n"
	done
	mkdir -p "$WP/wp-content/mu-plugins"
	cp -f "$REL"/wp-content/mu-plugins/*.php "$WP/wp-content/mu-plugins/"
	ln -sfn "$REL" "$MIS/current" 2>/dev/null || true
fi

# .htaccess: sustituye solo el bloque MIS y deja el de WordPress intacto.
touch "$WP/.htaccess"
awk '/^# BEGIN MIS$/{skip=1} !skip{print} /^# END MIS$/{skip=0}' "$WP/.htaccess" > "$WP/.htaccess.rest"
cat "$REL/htaccess-mis" "$WP/.htaccess.rest" > "$WP/.htaccess.new"
mv -f "$WP/.htaccess.new" "$WP/.htaccess"; rm -f "$WP/.htaccess.rest"

# Tareas tras desplegar (si hay WP-CLI).
WPCLI="$(command -v wp || echo "$HOME/bin/wp")"
if [[ -x "$WPCLI" ]] || [[ -f "$WPCLI" ]]; then
	php "$WPCLI" --path="$WP" cache flush || true
	php "$WPCLI" --path="$WP" rewrite flush || true
fi

# Se guardan las 5 últimas versiones.
ls -1dt "$MIS"/releases/*/ | tail -n +6 | xargs -r rm -rf
echo "✓ Versión $SHORT activa en $WP"
REMOTE_SCRIPT
