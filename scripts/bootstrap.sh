#!/usr/bin/env bash
# Arranque de un entorno de IONOS (una vez, o para rehacerlo; es idempotente). Spec 001, paso 2.5.
#   fase «core»:  WP-CLI, núcleo de WordPress, instalación, tema Ollie y plugins de terceros
#                 (la misma lista que el entorno local, sacada de .wp-env.json).
#   fase «setup»: tras el primer despliegue, activa el tema hijo y los plugins propios y ejecuta
#                 los ajustes repetibles de scripts/setup/ (idiomas, contenido inicial).
# Requiere ~/<WP_PATH>/wp-config.php en el servidor (Julio lo sube relleno desde
# config/wp-config.<entorno>.example.php): las claves de la base nunca pasan por GitHub.
#
# Uso: scripts/bootstrap.sh core|setup
# Variables: SSH_HOST, SSH_USER, SSH_PORT, WP_PATH, MIS_PATH, SITE_URL, SITE_TITLE,
#            WP_ADMIN_USER, WP_ADMIN_EMAIL, WP_ADMIN_PASSWORD, MIS_SKIP_CONTENT (1 = sin contenido)
set -euo pipefail
PHASE="${1:?core o setup}"
: "${SSH_HOST:?}" "${SSH_USER:?}" "${WP_PATH:?}" "${MIS_PATH:?}" "${SITE_URL:?}"
: "${WP_ADMIN_USER:=julio}" "${WP_ADMIN_EMAIL:?}" "${WP_ADMIN_PASSWORD:?}"
SSH_PORT="${SSH_PORT:-22}"
SITE_TITLE="${SITE_TITLE:-Marbella in Style (pruebas)}"

PLUGINS="$(jq -r '.plugins[] | select(startswith("https://")) | capture("plugin/(?<s>[^/]+)\\.zip").s' .wp-env.json | xargs)"
THEMES="$(jq -r '.themes[] | select(startswith("https://")) | capture("theme/(?<s>[^/]+)\\.zip").s' .wp-env.json | xargs)"

{
	printf 'WP_ADMIN_PASSWORD=%q\n' "$WP_ADMIN_PASSWORD"
	cat "$(dirname "$0")/remote/bootstrap-remote.sh"
} | ssh -p "$SSH_PORT" -o BatchMode=yes "$SSH_USER@$SSH_HOST" "bash -s -- $(printf '%q ' \
	"$PHASE" "$WP_PATH" "$MIS_PATH" "$SITE_URL" "$SITE_TITLE" "$WP_ADMIN_USER" "$WP_ADMIN_EMAIL" \
	"$PLUGINS" "$THEMES" "${MIS_SKIP_CONTENT:-0}")"
