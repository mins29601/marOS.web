# Se ejecuta EN EL SERVIDOR de IONOS a través de .github/workflows/bajar-pruebas.yml (no a mano).
# Argumentos: WP_PATH qué (prefijo|bd|fotos). Escribe en la salida estándar, comprimido:
#   bd    = la base de datos de pruebas (wp db export).
#   fotos = wp-content/uploads.
#   prefijo = el prefijo de las tablas (sin comprimir).
# Antes, el workflow antepone MIS_PHP y php-bin.sh. Solo lee: no cambia nada en pruebas.
set -euo pipefail
WP="$HOME/$1"; QUE="$2"
wp() { "$PHPBIN" "$WPCLI" --path="$WP" "$@"; }

case "$QUE" in
	prefijo) wp db prefix ;;
	bd)    wp db export - --single-transaction --quiet | gzip -6 ;;
	fotos) tar -C "$WP/wp-content" -czf - uploads ;;
	*)     echo "✗ Uso: WP_PATH prefijo|bd|fotos" >&2; exit 1 ;;
esac
