#!/usr/bin/env bash
# Comprobaciones HTTP tras desplegar (spec 001, «Testing»).
# Uso: SITE_URL=https://staging.<temporal> [CF_ACCESS_CLIENT_ID=… CF_ACCESS_CLIENT_SECRET=…] \
#      [EXPECT_NOINDEX=1] scripts/smoke.sh
# Con Access delante, el runner entra con un token de servicio de Cloudflare Access.
set -uo pipefail
: "${SITE_URL:?}"
EXPECT_NOINDEX="${EXPECT_NOINDEX:-1}"
H=()
if [[ -n "${CF_ACCESS_CLIENT_ID:-}" ]]; then
	H=(-H "CF-Access-Client-Id: $CF_ACCESS_CLIENT_ID" -H "CF-Access-Client-Secret: $CF_ACCESS_CLIENT_SECRET")
fi
fail=0
check() { if eval "$2"; then echo "✓ $1"; else echo "✗ $1"; fail=1; fi; }

for path in / /es/ /wp-json/; do
	code="$(curl -s -o /dev/null -w '%{http_code}' -L "${H[@]}" "$SITE_URL$path")"
	check "$path responde 200 (dio $code)" '[[ "$code" == 200 ]]'
done

if [[ "$EXPECT_NOINDEX" == "1" ]]; then
	headers="$(curl -s -D - -o /dev/null "${H[@]}" "$SITE_URL/")"
	check "cabecera X-Robots-Tag noindex" 'grep -qi "^x-robots-tag:.*noindex" <<<"$headers"'
	body="$(curl -s "${H[@]}" "$SITE_URL/")"
	check "meta robots noindex en el HTML" 'grep -qi "<meta name=.robots.[^>]*noindex" <<<"$body"'
	robots="$(curl -s "${H[@]}" "$SITE_URL/robots.txt")"
	check "robots.txt lo prohíbe todo" 'grep -q "^Disallow: /$" <<<"$robots"'
	# Sin token, Access debe impedir ver la web.
	anon="$(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/")"
	check "sin identificarse no se ve la web (dio $anon)" '[[ "$anon" != 200 ]]'
fi

exit "$fail"
