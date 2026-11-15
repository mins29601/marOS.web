# Se ejecuta EN EL SERVIDOR de IONOS a través de .github/workflows/maros-staging.yml (no a mano).
# Argumentos: WP_PATH acción (status|sync) [id de marOS]. Antes, el workflow antepone MIS_PHP y php-bin.sh.
set -euo pipefail
WP="$HOME/$1"; ACCION="$2"; ID="${3:-}"
wp() { "$PHPBIN" "$WPCLI" --path="$WP" "$@"; }

wp maros status

if [[ "$ACCION" == "sync" ]]; then
	[[ "$ID" =~ ^[a-z0-9-]{1,80}$ ]] || { echo "✗ Id no válido"; exit 1; }
	wp maros sync --id="$ID" --force
	echo
	echo "→ Propiedades con maros_id=$ID"
	wp post list --post_type=mis_property --post_status=any --lang= --meta_key=maros_id --meta_value="$ID" \
		--fields=ID,post_title,post_status,post_name --format=table
	POST="$(wp post list --post_type=mis_property --post_status=any --lang= --meta_key=maros_id --meta_value="$ID" --field=ID | head -n1)"
	if [[ -n "$POST" ]]; then
		echo
		echo "→ Datos de marOS guardados (post $POST)"
		# Solo campos operativos: nada de huéspedes ni de dirección llega a WordPress (principio X de marOS).
		# Sin terminal, WP-CLI separa las columnas con tabuladores.
		wp post meta list "$POST" --format=table --fields=meta_key,meta_value \
			| grep -E '^maros_' | grep -vE '^maros_(gallery|room_types|fees|pools)\s' | sort || true
		echo
		echo "→ Piscinas (maros_pools) y tasas (maros_fees)"
		wp post meta get "$POST" maros_pools || true
		wp post meta get "$POST" maros_fees || true
		echo
		echo "→ Fotos en la galería: $(wp post meta get "$POST" maros_gallery | tr ',' '\n' | grep -c . || true)"
		echo
		echo "→ Últimos 15 avisos del plugin (el más reciente primero)"
		wp option get maros_log --format=json 2>/dev/null | "$PHPBIN" -r '$l=json_decode(stream_get_contents(STDIN),true)?:[];foreach(array_slice($l,0,15) as $e){echo "  ",gmdate("Y-m-d H:i",(int)($e["time"]??0))," [",$e["level"]??"","] ",$e["message"]??"","\n";}' || true
	fi
fi
