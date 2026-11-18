# Se ejecuta EN EL SERVIDOR de IONOS a través de .github/workflows/maros-staging.yml (no a mano).
# Argumentos: WP_PATH acción (status|sync|seo|zonas|opiniones|servicios) [id de marOS] [MIS_PATH]. Antes, el workflow antepone MIS_PHP y php-bin.sh.
set -euo pipefail
WP="$HOME/$1"; ACCION="$2"; ID="${3:-}"; MIS="$HOME/${4:-}"
wp() { "$PHPBIN" "$WPCLI" --path="$WP" "$@"; }

wp maros status || [[ "$ACCION" == "seo" || "$ACCION" == "zonas" || "$ACCION" == "opiniones" || "$ACCION" == "servicios" ]]  # SEO, zonas, opiniones y servicios no necesitan marOS

if [[ "$ACCION" == "zonas" ]]; then
	# Reescribe las 13 guías con el texto del código desplegado: pisa lo editado a mano en WordPress.
	f="$MIS/current/scripts/setup/zonas.php"
	[[ -n "${4:-}" && -f "$f" ]] || { echo "✗ No encuentro $f (¿MIS_PATH?)"; exit 1; }
	echo "→ Guías de zona (zonas.php forzar)"
	wp eval-file "$f" forzar
fi

if [[ "$ACCION" == "opiniones" ]]; then
	# Opiniones de las fichas de la web actual. Idempotente: no duplica las que ya estén.
	f="$MIS/current/scripts/setup/opiniones-web-actual.php"
	[[ -n "${4:-}" && -f "$f" ]] || { echo "✗ No encuentro $f (¿MIS_PATH?)"; exit 1; }
	echo "→ Opiniones de la web actual"
	wp eval-file "$f"
fi

if [[ "$ACCION" == "servicios" ]]; then
	# Servicios y experiencias con los textos de marOS.content. No pisa lo editado en WordPress.
	f="$MIS/current/scripts/setup/servicios.php"
	[[ -n "${4:-}" && -f "$f" ]] || { echo "✗ No encuentro $f (¿MIS_PATH?)"; exit 1; }
	echo "→ Servicios y experiencias (servicios.php)"
	wp eval-file "$f"
	wp rewrite flush  # el .htaccess lo escribe deploy.sh
fi

if [[ "$ACCION" == "seo" ]]; then
	echo "→ Yoast SEO en todas las fichas (respeta lo editado a mano)"
	wp maros seo
fi

if [[ "$ACCION" == "sync" ]]; then
	[[ "$ID" =~ ^[a-z0-9-]{1,80}$ ]] || { echo "✗ Id no válido"; exit 1; }
	echo "→ JSON que devuelve marOS: ficha (las fotos se resumen en su número)"
	MAROS_ID="$ID" wp eval '$d = Maros_Client::get_property( getenv( "MAROS_ID" ) ); if ( is_wp_error( $d ) ) { echo "✗ ", $d->get_error_message(), "\n"; return; } if ( isset( $d["photos"] ) && is_array( $d["photos"] ) ) { $d["photos"] = count( $d["photos"] ) . " fotos" . ( $d["photos"] ? ", la primera: " . ( $d["photos"][0]["url"] ?? "" ) : "" ); } echo wp_json_encode( $d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";' || true
	echo
	echo "→ JSON que devuelve marOS: calendario (resumen y los dos primeros días)"
	MAROS_ID="$ID" wp eval '$c = Maros_Client::get_calendar( getenv( "MAROS_ID" ) ); if ( is_wp_error( $c ) ) { echo "✗ ", $c->get_error_message(), "\n"; return; } $days = is_array( $c["days"] ?? null ) ? $c["days"] : array(); $c["days"] = array_slice( $days, 0, 2 ); $c["_dias"] = count( $days ); $c["_libres"] = count( array_filter( $days, static fn( $x ) => ! empty( $x["available"] ) ) ); echo wp_json_encode( $c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";' || true
	echo
	echo "→ Importación"
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
