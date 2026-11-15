# Se incluye en los scripts que corren EN EL SERVIDOR. En IONOS el «php» de la terminal es
# PHP 4.4; el PHP 8 se llama php8.x-cli. Define PHPBIN y la función wp() con WP-CLI propio.
# MIS_PHP fuerza un binario concreto.
mis_find_php() {
	local c
	for c in ${MIS_PHP:-} php8.4-cli php8.4 php84 php8.5-cli php8.5 php85 php8.3-cli php8.3 php83 php; do
		command -v "$c" >/dev/null 2>&1 || continue
		if "$c" -r 'exit(version_compare(PHP_VERSION, "8.1", ">=") ? 0 : 1);' >/dev/null 2>&1; then
			command -v "$c"; return 0
		fi
	done
	return 1
}
PHPBIN="$(mis_find_php)" || { echo "✗ No encuentro PHP ≥ 8.1 en el servidor (prueba MIS_PHP=…)"; exit 1; }
WPCLI="$HOME/bin/wp-cli.phar"
if [[ ! -f "$WPCLI" ]]; then
	mkdir -p "$HOME/bin"
	curl -fsSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
