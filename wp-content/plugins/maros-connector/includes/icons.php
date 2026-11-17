<?php
/**
 * Iconos de comodidades (spec 006, iconos.md): Phosphor Light (MIT) más la ampliación propia
 * de marbellainstyle, en assets/icons/. Cada código de comodidad de marOS (src/amenities.js)
 * tiene su icono; las de Lodgify se reconocen por su nombre.
 *
 * @package MarbellaInStyle
 */

defined( 'ABSPATH' ) || exit;

/** Código de comodidad de marOS → archivo de icono (sin «.svg») dentro de assets/icons/. */
const MAROS_AMENITY_ICONS = array(
	'air_conditioning' => 'propios/aire-acondicionado',
	'arcade_games' => 'phosphor/joystick',
	'baby_bath' => 'phosphor/baby',
	'baby_monitor' => 'phosphor/baby',
	'baby_safety_gates' => 'phosphor/baby',
	'babysitter_recommendations' => 'phosphor/baby-carriage',
	'backyard' => 'phosphor/tree',
	'baking_sheet' => 'phosphor/cake',
	'balcony' => 'phosphor/sun',
	'bathtub' => 'phosphor/bathtub',
	'batting_cage' => 'phosphor/baseball',
	'bbq' => 'propios/barbacoa',
	'bbq_grill' => 'propios/barbacoa',
	'bbq_utensils' => 'propios/barbacoa',
	'beach_access' => 'propios/playa',
	'beach_essentials' => 'propios/playa',
	'bed_linens' => 'phosphor/bed',
	'bidet' => 'phosphor/toilet',
	'bikes' => 'phosphor/bicycle',
	'blackout_shades' => 'phosphor/moon',
	'blender' => 'phosphor/bowl-food',
	'board_games' => 'phosphor/puzzle-piece',
	'boat_slip' => 'phosphor/sailboat',
	'body_soap' => 'phosphor/hand-soap',
	'books_reading_material' => 'phosphor/books',
	'bowling_alley' => 'phosphor/bowling-ball',
	'bread_maker' => 'phosphor/bread',
	'breakfast' => 'phosphor/coffee',
	'building_staff' => 'phosphor/user-check',
	'carbon_monoxide_alarm' => 'propios/detector-humo',
	'ceiling_fan' => 'phosphor/fan',
	'changing_table' => 'phosphor/baby',
	'check' => 'phosphor/check',
	'cleaning_during_stay' => 'phosphor/broom',
	'cleaning_products' => 'phosphor/spray-bottle',
	'climbing_wall' => 'phosphor/mountains',
	'clothing_storage' => 'phosphor/dresser',
	'coffee' => 'phosphor/coffee-bean',
	'coffee_maker' => 'phosphor/coffee',
	'compost_bin' => 'phosphor/leaf',
	'conditioner' => 'phosphor/drop',
	'cooking_basics' => 'phosphor/cooking-pot',
	'crib' => 'propios/cuna',
	'dedicated_workspace' => 'phosphor/desk',
	'desk' => 'phosphor/desk',
	'dining_table' => 'phosphor/fork-knife',
	'direct_pool_access' => 'phosphor/swimming-pool',
	'dishes_silverware' => 'phosphor/fork-knife',
	'dishwasher' => 'propios/lavavajillas',
	'dryer' => 'propios/secadora',
	'drying_rack' => 'propios/tendedero',
	'ebike_charger' => 'phosphor/plug-charging',
	'elevator' => 'phosphor/elevator',
	'ensuite' => 'phosphor/bathtub',
	'essentials' => 'phosphor/towel',
	'ethernet_connection' => 'phosphor/network',
	'ev_charger' => 'phosphor/charging-station',
	'exercise_equipment' => 'phosphor/barbell',
	'extra_pillows_blankets' => 'phosphor/bed',
	'fire_extinguisher' => 'phosphor/fire-extinguisher',
	'fire_pit' => 'phosphor/campfire',
	'fireplace_guards' => 'phosphor/shield-check',
	'first_aid_kit' => 'phosphor/first-aid-kit',
	'free_parking_off_premises' => 'phosphor/car',
	'free_parking_on_premises' => 'phosphor/car',
	'free_street_parking' => 'phosphor/car',
	'freezer' => 'propios/nevera',
	'game_console' => 'phosphor/game-controller',
	'garden' => 'phosphor/park',
	'garden_view' => 'phosphor/plant',
	'gated_community' => 'phosphor/shield-check',
	'golf_view' => 'phosphor/golf',
	'gym' => 'phosphor/barbell',
	'hair_dryer' => 'phosphor/hair-dryer',
	'hammock' => 'propios/hamaca',
	'hangers' => 'phosphor/coat-hanger',
	'heated_pool' => 'propios/piscina-climatizada',
	'heating' => 'phosphor/thermometer-hot',
	'high_chair' => 'phosphor/baby',
	'hockey_rink' => 'phosphor/hockey',
	'hot_tub' => 'propios/jacuzzi',
	'hot_water' => 'phosphor/thermometer-hot',
	'independent_access' => 'phosphor/door-open',
	'indoor_fireplace' => 'phosphor/fire',
	'iron' => 'propios/plancha',
	'kayak' => 'phosphor/boat',
	'kettle' => 'phosphor/coffee',
	'key' => 'phosphor/key',
	'kids_bikes' => 'phosphor/bicycle',
	'kids_books_toys' => 'phosphor/lego',
	'kids_dinnerware' => 'phosphor/fork-knife',
	'kids_playroom' => 'phosphor/lego-smiley',
	'kitchen' => 'phosphor/cooking-pot',
	'kitchenette' => 'phosphor/cooking-pot',
	'lake_access' => 'phosphor/waves',
	'laser_tag' => 'phosphor/target',
	'laundromat_nearby' => 'phosphor/washing-machine',
	'laundry_services' => 'phosphor/t-shirt',
	'life_size_games' => 'phosphor/dice-five',
	'long_term_stays' => 'phosphor/calendar-check',
	'luggage_dropoff' => 'phosphor/suitcase-rolling',
	'microwave' => 'propios/microondas',
	'mini_fridge' => 'propios/nevera',
	'mini_golf' => 'phosphor/golf',
	'mosquito_net' => 'phosphor/bug',
	'mountain_view' => 'phosphor/mountains',
	'outdoor_dining_area' => 'phosphor/fork-knife',
	'outdoor_furniture' => 'phosphor/chair',
	'outdoor_kitchen' => 'propios/barbacoa',
	'outdoor_playground' => 'phosphor/park',
	'outdoor_shower' => 'phosphor/shower',
	'outlet_covers' => 'phosphor/plug',
	'oven' => 'phosphor/oven',
	'padel' => 'propios/padel',
	'paid_parking_off_premises' => 'phosphor/car',
	'paid_parking_on_premises' => 'phosphor/car',
	'parking' => 'phosphor/car',
	'patio_balcony' => 'phosphor/sun',
	'patio_garden_access' => 'phosphor/park',
	'piano' => 'phosphor/piano-keys',
	'ping_pong_table' => 'phosphor/ping-pong',
	'pocket_wifi' => 'phosphor/cell-signal-full',
	'pool' => 'phosphor/swimming-pool',
	'pool_table' => 'phosphor/target',
	'pool_view' => 'phosphor/swimming-pool',
	'portable_fans' => 'phosphor/fan',
	'private_entrance' => 'phosphor/door',
	'private_living_room' => 'phosphor/couch',
	'private_terrace' => 'phosphor/sun',
	'record_player' => 'phosphor/vinyl-record',
	'recycling_bins' => 'phosphor/recycle',
	'refrigerator' => 'propios/nevera',
	'resort_access' => 'phosphor/buildings',
	'rice_maker' => 'phosphor/cooking-pot',
	'safe' => 'phosphor/lock-key',
	'sauna' => 'propios/sauna',
	'sea_view' => 'phosphor/sun-horizon',
	'shampoo' => 'phosphor/drop',
	'shower' => 'phosphor/shower',
	'shower_gel' => 'phosphor/hand-soap',
	'single_level_home' => 'phosphor/house-simple',
	'sitting_area' => 'phosphor/armchair',
	'skate_ramp' => 'phosphor/sneaker-move',
	'ski_in_out' => 'phosphor/person-simple-ski',
	'smoke_alarm' => 'propios/detector-humo',
	'solar_panels' => 'phosphor/solar-panel',
	'sound_system' => 'phosphor/speaker-hifi',
	'stove' => 'phosphor/cooking-pot',
	'sun_loungers' => 'propios/tumbona',
	'table_corner_guards' => 'phosphor/shield-check',
	'terrace' => 'phosphor/sun',
	'theatre_room' => 'phosphor/film-slate',
	'themed_room' => 'phosphor/sparkle',
	'toaster' => 'phosphor/bread',
	'trash_compactor' => 'phosphor/trash',
	'travel_crib' => 'propios/cuna',
	'tv' => 'phosphor/television',
	'view' => 'phosphor/sun-horizon',
	'walk_in_closet' => 'phosphor/coat-hanger',
	'wardrobe' => 'phosphor/dresser',
	'washer' => 'phosphor/washing-machine',
	'waterfront' => 'phosphor/waves',
	'wifi' => 'phosphor/wifi-high',
	'window_guards' => 'phosphor/shield-check',
	'wine_glasses' => 'phosphor/wine',	'bed' => 'phosphor/bed',
);

/**
 * SVG en línea de un icono de assets/icons/ («phosphor/bed», «propios/secadora»…) con el
 * tamaño dado. Hereda el color del texto (currentColor). Devuelve '' si no existe.
 */
function maros_icon_svg( string $name, int $size = 24 ): string {
	static $cache = array();
	if ( ! isset( $cache[ $name ] ) ) {
		$file = dirname( __DIR__ ) . '/assets/icons/' . $name . '.svg';
		$svg  = ( preg_match( '#^[a-z]+/[a-z0-9-]+$#', $name ) && is_readable( $file ) ) ? (string) file_get_contents( $file ) : '';
		$cache[ $name ] = trim( $svg );
	}
	if ( '' === $cache[ $name ] ) {
		return '';
	}
	return preg_replace( '/^<svg /', '<svg width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false" class="mis-icon" ', $cache[ $name ], 1 );
}

/**
 * Icono para una comodidad: código de marOS («dryer», «sea_view») o término del sitio que
 * viene de Lodgify («private-pool», «tumble-dryer»…). Sin coincidencia, «check».
 */
function maros_amenity_icon( string $code ): string {
	// Los términos en español llevan «-es» al final (dryer-es); el icono es el mismo.
	$key = preg_replace( '/_es$/', '', str_replace( '-', '_', strtolower( $code ) ) );
	if ( isset( MAROS_AMENITY_ICONS[ $key ] ) ) {
		return MAROS_AMENITY_ICONS[ $key ];
	}
	// Por nombre; el orden importa: «hair dryer» antes que «dryer», «dishwasher» antes que «washer».
	$rules = array(
		'hair_dryer'       => '/hair.?dryer|secador.?de.?pelo/',
		'dryer'            => '/dryer|secadora/',
		'dishwasher'       => '/dish.?washer|lavavajillas/',
		'washer'           => '/wash|laundry|lavadora/',
		'heated_pool'      => '/heated.?pool|piscina.?climatizada/',
		'hot_tub'          => '/hot.?tub|jacuzzi|whirlpool/',
		'pool'             => '/pool|piscina/',
		'sea_view'         => '/sea_view|^sea$|_sea$|ocean|vistas_al_mar/',
		'view'             => '/view|vistas/',
		'wifi'             => '/wifi|internet/',
		'air_conditioning' => '/^air|air_cond|aire|cooling/',
		'parking'          => '/parking|garage|aparcamiento/',
		'bbq'              => '/bbq|barbecue|grill|barbacoa/',
		'gym'              => '/gym|fitness|gimnasio/',
		'sauna'            => '/sauna|spa/',
		'terrace'          => '/terrace|balcon|patio|terraza/',
		'tv'               => '/\btv\b|_tv|tv_|television|cable/',
		'microwave'        => '/microwave|microondas/',
		'refrigerator'     => '/fridge|refrigerator|nevera|frigor/',
		'kitchen'          => '/kitchen|cocina|oven|horno/',
		'coffee_maker'     => '/coffee|cafe/',
		'heating'          => '/heat|calefaccion/',
		'indoor_fireplace' => '/fireplace|chimenea/',
		'safe'             => '/safe|caja.?fuerte/',
		'garden'           => '/garden|jardin/',
		'beach_access'     => '/beach|playa/',
		'crib'             => '/crib|cot\b|cuna/',
		'iron'             => '/^iron|_iron|plancha/',
		'elevator'         => '/elevator|lift|ascensor/',
	);
	foreach ( $rules as $target => $re ) {
		if ( preg_match( $re, $key ) && isset( MAROS_AMENITY_ICONS[ $target ] ) ) {
			return MAROS_AMENITY_ICONS[ $target ];
		}
	}
	return MAROS_AMENITY_ICONS['check'];
}
