<?php
/**
 * Plantilla de wp-config.php para PRUEBAS (staging) en IONOS. Sin secretos.
 *
 * Se copia una sola vez al servidor como ~/marbellainstyle/staging/wp-config.php (fuera de la carpeta
 * pública si IONOS lo permite) y se rellenan los valores entre <>. Nunca se sube al repo
 * con valores reales. Spec 001, plan («Cómo se gestionan producción y pruebas»).
 *
 * @package MarbellaInStyle
 */

// ── Base de datos (panel de IONOS → Bases de datos → la de pruebas) ─────────────────────
define( 'DB_NAME', '<dbs…>' );
define( 'DB_USER', '<dbu…>' );
define( 'DB_PASSWORD', '<contraseña de la base de pruebas>' );
define( 'DB_HOST', '<db…hosting-data.io>' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
$table_prefix = 'mis_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

// ── Claves: generar en https://api.wordpress.org/secret-key/1.1/salt/ ───────────────────
// (pegar aquí las 8 líneas AUTH_KEY … NONCE_SALT)

// ── Entorno ─────────────────────────────────────────────────────────────────────────────
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
define( 'WP_HOME', 'https://staging.privatevillacollection.com' );
define( 'WP_SITEURL', 'https://staging.privatevillacollection.com' );
define( 'FORCE_SSL_ADMIN', true );
define( 'DISABLE_WP_CRON', true );          // cron real de IONOS cada 5 minutos
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/kunden/homepages/29/d668976456/htdocs/mis/logs/debug.log' ); // ~/mis no se sirve por web
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );       // el código solo llega por GitHub
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_DEVELOPMENT_MODE', 'theme' );   // theme.json sin caché mientras se diseña

// Cloudflare delante: la IP real del visitante y HTTPS.
if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP']; // phpcs:ignore
}
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

// ── marOS ───────────────────────────────────────────────────────────────────────────────
// Mientras la API pública de marOS no esté lista, pruebas usa el mock del repo (los mismos
// JSON que en local). Después: MAROS_ENV=staging y la URL de marOS pruebas.
define( 'MAROS_ENV', 'mock' );
define( 'MAROS_API_BASE', '' );                 // con el mock no se usa
define( 'MAROS_API_KEY', '<clave de marOS pruebas, o cualquier texto con el mock>' );
define( 'MAROS_WEBHOOK_SECRET', '<secreto del webhook de marOS pruebas>' );
define( 'MAROS_MOCK_DIR', '/kunden/homepages/29/d668976456/htdocs/mis/staging/current/mock/maros-api' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
