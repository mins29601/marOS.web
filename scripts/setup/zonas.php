<?php
/**
 * Las 13 zonas de la web nueva con su guía (spec 005; zonas de Julio del 14-11-2026 en
 * marOS.content, preguntas-ola-1 Z1). contenido-inicial.php crea cuatro de ellas sin texto;
 * aquí se escriben todas las guías. Idempotente.
 *
 * Uso: wp eval-file scripts/setup/zonas.php
 * (en local: npm run wp -- eval-file wp-content/mis-scripts/setup/zonas.php)
 *
 * Por cada zona:
 * - crea el término de la taxonomía `zona` en inglés y español, enlazados con Polylang
 *   (/areas/<slug>/ y /es/zonas/<slug>-es/), con el mismo slug que usa marOS Connector;
 * - escribe la guía (la descripción del término, que la plantilla taxonomy-zona muestra
 *   bajo el título) solo si la zona aún no tiene texto: lo que el equipo edite en
 *   WordPress no se pisa. Para reescribirla: `wp eval-file zonas.php forzar`;
 * - la añade a los menús «primary» y «footer-stay» de cada idioma, bajo Zonas/Alojamiento,
 *   si aún no está.
 *
 * Para añadir otra zona basta con otra entrada en $mis_zonas.
 *
 * Los textos siguen la estructura y los criterios de PC-17 en marOS.content
 * (contenidos/ideas/PC-17-revision-zonas.md y docs/guia-editorial.md): sin distancias ni
 * tiempos que no se hayan medido y sin locales que no recomiende Marbella in Style. Once zonas
 * parten del texto de la web actual (marOS.content, contenidos/ideas/zonas-web-actual/ y
 * PC-17-textos-web-actual.md); New Golden Mile se escribió desde cero y Benahavís pueblo sigue
 * provisional. La versión definitiva sale del flujo de marOS.content y se pega en WordPress o
 * aquí con «forzar».
 *
 * @package MarbellaInStyle
 */

if ( ! function_exists( 'pll_set_term_language' ) || ! class_exists( 'Maros_Sync' ) ) {
	WP_CLI::error( 'Faltan Polylang o marOS Connector.' );
}

// WP-CLI corre sin usuario: sin esto, kses quita los <h2> y <ul> de la guía.
kses_remove_filters();

$mis_forzar = in_array( 'forzar', $args ?? array(), true );

$mis_zonas = array(
	'new-golden-mile' => array(
		'nombre' => array( 'New Golden Mile', 'New Golden Mile' ),
		'en'     => <<<'HTML'
<p>The New Golden Mile is the stretch of coast in Estepona between San Pedro de Alcántara and Estepona town, with the sea on one side and the Sierra Bermeja foothills on the other. It suits families and groups who want a large villa with a garden and pool, close to the beach and the golf courses, and are happy to drive to restaurants and shops.</p>

<h2>What it is like</h2>
<p>A residential area of villas and gated communities around Cancelada, El Paraíso and Atalaya, with the N-340 coast road (A-7) and the AP-7 toll road running through it. It is quieter than Marbella and Puerto Banús, especially outside the summer months.</p>

<h2>Beaches</h2>
<p>Long, open beaches such as El Saladillo and Guadalmansa, with beach clubs and chiringuitos along the shore.</p>

<h2>Golf and sport</h2>
<p>Atalaya Golf &amp; Country Club and El Paraíso Golf Club are within the area, and the courses of Benahavís and Nueva Andalucía are a short drive away.</p>

<h2>Getting around</h2>
<p>You will need a car or private transport for almost everything: shops, restaurants and the beach are spread along the coast road. Our team can arrange a driver for airport transfers and evenings out.</p>

<h2>Good to know</h2>
<p>Choose the New Golden Mile for space and quiet, not for walking to the old town or the port. If nightlife on your doorstep matters, Puerto Banús or the Golden Mile will suit you better.</p>
HTML,
		'es'     => <<<'HTML'
<p>La New Golden Mile es el tramo de costa de Estepona entre San Pedro de Alcántara y el pueblo de Estepona, con el mar a un lado y las faldas de Sierra Bermeja al otro. Encaja con familias y grupos que buscan una villa grande con jardín y piscina, cerca de la playa y del golf, y no les importa coger el coche para ir a cenar o a comprar.</p>

<h2>Cómo es</h2>
<p>Una zona residencial de villas y urbanizaciones cerradas en torno a Cancelada, El Paraíso y Atalaya, que cruzan la N-340 (A-7) y la autopista de peaje AP-7. Es más tranquila que Marbella y Puerto Banús, sobre todo fuera del verano.</p>

<h2>Playas</h2>
<p>Playas largas y abiertas como El Saladillo y Guadalmansa, con beach clubs y chiringuitos a lo largo de la orilla.</p>

<h2>Golf y deporte</h2>
<p>Atalaya Golf &amp; Country Club y El Paraíso Golf Club están en la propia zona, y los campos de Benahavís y Nueva Andalucía quedan a un rato en coche.</p>

<h2>Cómo moverse</h2>
<p>Necesitarás coche o transporte privado para casi todo: tiendas, restaurantes y playa están repartidos a lo largo de la carretera de la costa. Nuestro equipo te organiza un chófer para el aeropuerto y para salir por la noche.</p>

<h2>Lo que hay que saber</h2>
<p>Elige la New Golden Mile por espacio y tranquilidad, no para ir andando al casco antiguo o al puerto. Si quieres la vida nocturna a la puerta de casa, te encajarán mejor Puerto Banús o la Milla de Oro.</p>
HTML,
	),
	// Guías adaptadas del texto de marbellainstyle.com (descargado el 18-11-2026; original sin
	// corregir en marOS.content, contenidos/ideas/zonas-web-actual/). Se quitan las distancias y
	// tiempos sin medir, teléfonos, superlativos y datos con fecha. Los locales son los que
	// recomendaba esa web (2022-2026): hay que comprobar que siguen abiertos antes de publicar.
	'golden-mile' => array(
		'nombre' => array( 'Golden Mile', 'Milla de Oro' ),
		'en'     => <<<'HTML'
<p>The Golden Mile is the stretch of coast between the west of Marbella town and Puerto Banús, one of the most sought-after areas for villas in Marbella, with tree-lined streets and views of the sea and the mountains. It suits guests who want the beach, the seafront promenade and Marbella's restaurants close by, in a quiet and private setting.</p>

<h2>What it is like</h2>
<p>The N-340 coast road divides it in two: from the road down to the beach, and from the road up towards the mountains, at the foot of La Concha. On the beach side you will find private villas, apartment complexes such as Las Cañas and Playa Esmeralda, and the Marbella Club and Puente Romano hotels. The Marbella Club, built by Prince Alfonso de Hohenlohe in the 1950s, is where Marbella's story as an international destination began. Along the main road there are art galleries, fashion boutiques and furniture and interior design studios.</p>

<h2>Beaches</h2>
<p>On the beach side, the sea is a few steps away. The seafront promenade runs along the Golden Mile between Marbella and Puerto Banús, with beach clubs and restaurants along the way, and is popular for running, walking, cycling and even rollerblading.</p>

<h2>Eating out</h2>
<p>There are restaurants of many international cuisines, cafés and beach clubs. By the sea, our guide has recommended Victor's Beach, El Chiringuito at Puente Romano, El Trocadero Playa and Tamanaco. Chef Dani García also has several restaurants on the Golden Mile, such as Lobito de Mar.</p>

<h2>Golf and sport</h2>
<p>The golf courses of Nueva Andalucía (Los Naranjos, Las Brisas and Aloha) are a short drive away, as are the marinas of Puerto Banús and Marbella. Exercise on the beach and yoga are part of everyday life here.</p>

<h2>Services</h2>
<p>Supermarkets such as Al Andalus, Lidl, Aldi and Carrefour Market, pharmacies and banks are a short drive away, without the bustle of the town centre or the crowds of Puerto Banús.</p>

<h2>Getting around</h2>
<p>From the beach side, the promenade takes you on foot or by bike towards Puerto Banús and Marbella town. For everything else you will want a car, a taxi or a driver; our team can arrange one for airport transfers and evenings out.</p>

<h2>Good to know</h2>
<p>Beachfront villas are the most in demand and the fewest, so book early for summer. Tell our local team your dates, how many you are and whether you prefer to walk or drive, and we will help you check the location of the homes you are considering.</p>
HTML,
		'es'     => <<<'HTML'
<p>La Milla de Oro es la franja de costa entre el oeste de Marbella y Puerto Banús, una de las zonas más solicitadas para alojarse en villa en Marbella, con calles arboladas y vistas al mar y a la montaña. Encaja contigo si quieres tener cerca la playa, el paseo marítimo y los restaurantes de Marbella, en un entorno tranquilo y privado.</p>

<h2>Cómo es</h2>
<p>La carretera de la costa N-340 la divide en dos: de la carretera hacia la playa, y de la carretera hacia la montaña, a los pies de La Concha. Del lado de la playa hay villas privadas, complejos de apartamentos como Las Cañas y Playa Esmeralda, y los hoteles Marbella Club y Puente Romano. El Marbella Club, que construyó el príncipe Alfonso de Hohenlohe en los años cincuenta, es donde empezó la historia de Marbella como destino internacional. A lo largo de la carretera encontrarás galerías de arte, tiendas de moda y estudios de mobiliario e interiorismo.</p>

<h2>Playas</h2>
<p>Del lado de la playa, el mar está a pocos pasos. El paseo marítimo recorre la Milla de Oro entre Marbella y Puerto Banús, con beach clubs y restaurantes por el camino, y es muy popular para correr, caminar, ir en bici e incluso patinar.</p>

<h2>Comer y salir</h2>
<p>Hay restaurantes de muchas cocinas internacionales, cafeterías y beach clubs. Junto al mar, nuestra guía ha recomendado Victor's Beach, El Chiringuito de Puente Romano, El Trocadero Playa y Tamanaco. El chef Dani García tiene además varios restaurantes en la Milla de Oro, como Lobito de Mar.</p>

<h2>Golf y deporte</h2>
<p>Los campos de golf de Nueva Andalucía (Los Naranjos, Las Brisas y Aloha) quedan a un rato en coche, igual que los puertos deportivos de Puerto Banús y Marbella. El deporte en la playa y el yoga forman parte del día a día.</p>

<h2>Servicios</h2>
<p>Supermercados como Al Andalus, Lidl, Aldi y Carrefour Market, farmacias y bancos quedan a poca distancia en coche, sin el bullicio del centro ni la afluencia de Puerto Banús.</p>

<h2>Cómo moverse</h2>
<p>Desde el lado de la playa, el paseo marítimo te lleva andando o en bici hacia Puerto Banús y el centro de Marbella. Para todo lo demás necesitarás coche, taxi o chófer; nuestro equipo te lo organiza para el aeropuerto y para salir por la noche.</p>

<h2>Lo que hay que saber</h2>
<p>Las villas en primera línea son las más solicitadas y las que menos hay, así que reserva pronto para el verano. Cuéntale a nuestro equipo tus fechas, cuántos sois y si prefieres moverte andando o en coche, y te ayudamos a comprobar la ubicación de las casas que estés mirando.</p>
HTML,
	),
	'nueva-andalucia' => array(
		'nombre' => array( 'Nueva Andalucía', 'Nueva Andalucía' ),
		'en'     => <<<'HTML'
<p>Nueva Andalucía borders Puerto Banús and stretches inland, below La Concha mountain and around its golf courses, which is why it is known as the Golf Valley. It suits golfers and families who want a spacious villa with a pool and garden in a quiet setting, a short drive or taxi ride from Puerto Banús.</p>

<h2>What it is like</h2>
<p>Tall palm trees, villas with pools surrounded by gardens, and views of the Mediterranean and La Concha. Hotels, restaurants and nightclubs are concentrated on the side closest to Puerto Banús; the rest of Nueva Andalucía is residential and quiet, with local cafés and artisan markets.</p>

<h2>Beaches</h2>
<p>The nearest beach is Playa del Duque, west of Puerto Banús. It is one of the most popular in Marbella, with small coves that shelter swimmers from the waves, beach bars, sun loungers, umbrellas and water sports. A promenade lined with palm trees runs from there to San Pedro de Alcántara, and Ocean Club Marbella offers food, live music and a pool by the beach.</p>

<h2>Eating out</h2>
<p>Between Nueva Andalucía and Puerto Banús there are restaurants for every taste and occasion, from quiet places to fashionable ones where you need to book a few days ahead. Our guide has recommended La Bocana, a family restaurant with a terrace overlooking Puerto Banús; Amore e Fantasia, for pasta, pizza and cocktails by the port; Tango, an Argentine grill; and Carpaccio, for Italian cooking.</p>

<h2>Golf and sport</h2>
<p>Three courses lie a few minutes from each other: Los Naranjos, designed by Robert Trent Jones Sr; Las Brisas; and Aloha, a parkland course with lakes and views of La Concha and the sea.</p>

<h2>Services</h2>
<p>Supermarkets (SuperCor and Mercadona), pharmacies and banks are a few minutes away by car. In Puerto Banús you will find the El Corte Inglés and Marina Banús shopping centres, and San Pedro de Alcántara and Marbella are close by.</p>

<h2>Getting around</h2>
<p>You will need a car, a taxi or a driver for most plans. In high season, parking in Puerto Banús at peak times can be difficult, but there are paid car parks.</p>

<h2>Good to know</h2>
<p>The exact location makes the difference: before you choose, check how far the villa is from your plans, whether you can walk and whether you will need a car or transfers. Our local team can help you weigh up the options for your dates and group.</p>
HTML,
		'es'     => <<<'HTML'
<p>Nueva Andalucía limita con Puerto Banús y se extiende hacia el interior, bajo la montaña de La Concha y entre sus campos de golf, por lo que se la conoce como el Valle del Golf. Encaja con golfistas y familias que buscan una villa amplia con piscina y jardín en un entorno tranquilo, a poca distancia en coche o en taxi de Puerto Banús.</p>

<h2>Cómo es</h2>
<p>Palmeras altas, villas con piscina rodeadas de jardines y vistas al Mediterráneo y a La Concha. Hoteles, restaurantes y discotecas se concentran en el lado más cercano a Puerto Banús; el resto de Nueva Andalucía es residencial y tranquilo, con cafeterías de barrio y mercadillos de artesanía.</p>

<h2>Playas</h2>
<p>La playa más cercana es la del Duque, al oeste de Puerto Banús. Es una de las más concurridas de Marbella, con pequeñas calas que protegen a los bañistas de las olas, chiringuitos, tumbonas, sombrillas y deportes acuáticos. Desde allí, un paseo marítimo con palmeras llega hasta San Pedro de Alcántara, y Ocean Club Marbella ofrece comida, música en directo y piscina a pie de playa.</p>

<h2>Comer y salir</h2>
<p>Entre Nueva Andalucía y Puerto Banús hay restaurantes para todos los gustos y momentos, desde sitios tranquilos hasta locales de moda donde hay que reservar con unos días de antelación. Nuestra guía ha recomendado La Bocana, restaurante familiar con terraza sobre Puerto Banús; Amore e Fantasia, pasta, pizza y cócteles frente al puerto; Tango, asador argentino; y Carpaccio, de cocina italiana.</p>

<h2>Golf y deporte</h2>
<p>Tres campos a pocos minutos uno de otro: Los Naranjos, diseñado por Robert Trent Jones Sr.; Las Brisas; y Aloha, un campo de estilo parkland con lagos y vistas a La Concha y al mar.</p>

<h2>Servicios</h2>
<p>Supermercados (SuperCor y Mercadona), farmacias y bancos a pocos minutos en coche. En Puerto Banús están los centros comerciales de El Corte Inglés y Marina Banús, y San Pedro de Alcántara y Marbella quedan cerca.</p>

<h2>Cómo moverse</h2>
<p>Necesitarás coche, taxi o chófer para casi todo. En temporada alta, aparcar en Puerto Banús en horas punta puede costar, pero hay aparcamientos de pago.</p>

<h2>Lo que hay que saber</h2>
<p>La ubicación concreta marca la diferencia: antes de elegir, comprueba la distancia de la villa a tus planes, si puedes ir andando y si necesitarás coche o traslados. Nuestro equipo local te ayuda a valorar las opciones según tus fechas y tu grupo.</p>
HTML,
	),
	'sierra-blanca' => array(
		'nombre' => array( 'Sierra Blanca', 'Sierra Blanca' ),
		'en'     => <<<'HTML'
<p>Sierra Blanca stretches from the foothills of La Concha down to the edge of the Golden Mile. It is a gated residential area with few empty plots, high-quality villas with lush subtropical gardens, controlled access and almost no traffic. Being high up, it has views over Marbella, Puerto Banús and the coast as far as Gibraltar and North Africa. It suits families who want privacy and views close to Marbella.</p>

<h2>What it is like</h2>
<p>A purely residential area on the edge of a protected natural area at the foot of the mountains it is named after, with hiking trails close by. There are hardly any shops or restaurants in Sierra Blanca itself: everything Marbella offers is a few minutes away by car.</p>

<h2>Beaches</h2>
<p>The nearest beaches are Casablanca, La Fontanilla, Cortijo Blanco, Río Verde and Nagüeles, a short drive away, with soft sand and clear water. The promenade from Marbella to Puerto Banús has beach clubs, bars, restaurants and chiringuitos, and is lovely for a run, a ride or a walk at sunset.</p>

<h2>Eating out</h2>
<p>Our guide has recommended Lobito de Mar, Dani García's bar on the Golden Mile; Da Bruno, for Italian and Andalusian food; and El Trocadero Playa, by the sea.</p>

<h2>Golf and sport</h2>
<p>Los Naranjos, Las Brisas and Aloha, in Nueva Andalucía, are a short drive away. The hiking trails of the sierra start nearby.</p>

<h2>Services</h2>
<p>Supermarkets (Carrefour Market, Lidl and Mercadona), pharmacies and banks are a few minutes away by car in Marbella.</p>

<h2>Getting around</h2>
<p>Sierra Blanca sits next to the end of the AP-7 toll road, which leads to Málaga airport, and connects with the free A-7 after the Nagüeles tunnel. The N-340 is reached by driving down through Nagüeles towards the beach. You will need a car or a driver for the beach, shops and restaurants.</p>

<h2>Good to know</h2>
<p>Choose Sierra Blanca for privacy, security and views, knowing that you will drive to the beach and to dinner.</p>
HTML,
		'es'     => <<<'HTML'
<p>Sierra Blanca se extiende desde la falda de La Concha hasta el límite de la Milla de Oro. Es una urbanización cerrada con pocas parcelas sin construir, villas de gran calidad con frondosos jardines subtropicales, acceso controlado y casi sin tráfico. Al estar en alto, tiene vistas a Marbella, Puerto Banús y el litoral hasta Gibraltar y el norte de África. Encaja con familias que buscan privacidad y vistas cerca de Marbella.</p>

<h2>Cómo es</h2>
<p>Una zona puramente residencial, en el límite de un área natural protegida a los pies de la sierra que le da nombre, con rutas de senderismo al lado. En la propia Sierra Blanca casi no hay tiendas ni restaurantes: todo lo que ofrece Marbella está a pocos minutos en coche.</p>

<h2>Playas</h2>
<p>Las playas más cercanas son Casablanca, La Fontanilla, Cortijo Blanco, Río Verde y Nagüeles, a un rato en coche, con arena fina y agua limpia. El paseo marítimo de Marbella a Puerto Banús tiene beach clubs, bares, restaurantes y chiringuitos, y es ideal para correr, ir en bici o pasear al atardecer.</p>

<h2>Comer y salir</h2>
<p>Nuestra guía ha recomendado Lobito de Mar, el bar de Dani García en la Milla de Oro; Da Bruno, de cocina italiana y andaluza; y El Trocadero Playa, junto al mar.</p>

<h2>Golf y deporte</h2>
<p>Los Naranjos, Las Brisas y Aloha, en Nueva Andalucía, quedan a un rato en coche. Las rutas de senderismo de la sierra empiezan muy cerca.</p>

<h2>Servicios</h2>
<p>Supermercados (Carrefour Market, Lidl y Mercadona), farmacias y bancos a pocos minutos en coche, en Marbella.</p>

<h2>Cómo moverse</h2>
<p>Sierra Blanca está junto al final de la autopista de peaje AP-7, que lleva al aeropuerto de Málaga, y enlaza con la A-7, gratuita, pasado el túnel de Nagüeles. A la N-340 se llega bajando por Nagüeles hacia la playa. Necesitarás coche o chófer para la playa, las compras y los restaurantes.</p>

<h2>Lo que hay que saber</h2>
<p>Elige Sierra Blanca por privacidad, seguridad y vistas, sabiendo que irás en coche a la playa y a cenar.</p>
HTML,
	),
	'puerto-banus' => array(
		'nombre' => array( 'Puerto Banús', 'Puerto Banús' ),
		'en'     => <<<'HTML'
<p>Puerto Banús is the marina of Marbella, full of luxury yachts, between the Golden Mile and Nueva Andalucía. It suits guests who want nightlife, shops, beach clubs and restaurants on their doorstep.</p>

<h2>What it is like</h2>
<p>Around the marina you will find designer boutiques and speciality shops, a cinema and boat trips along the Costa del Sol. It is a great place to people-watch. At night the bars, music venues and nightclubs stay open until the early hours.</p>

<h2>Beaches</h2>
<p>The beaches are clean and well looked after, with beach bars and beach clubs. To the west, Playa del Duque has small coves sheltered from the waves and a palm-lined promenade towards San Pedro de Alcántara.</p>

<h2>Eating out</h2>
<p>Our guide has recommended La Bocana, a family restaurant with a terrace overlooking the port; Amore e Fantasia, for pasta, pizza and cocktails by the marina; Carpaccio, for Italian cooking; and Tango, an Argentine grill.</p>

<h2>Golf and sport</h2>
<p>The courses of Nueva Andalucía (Los Naranjos, Las Brisas and Aloha) are just behind Puerto Banús.</p>

<h2>Services</h2>
<p>The El Corte Inglés and Marina Banús shopping centres, supermarkets, pharmacies and banks are all close by.</p>

<h2>Getting around</h2>
<p>Most things are within walking distance. In high season parking is difficult and taxis are in high demand, so a private driver saves you the search; our team can arrange one.</p>

<h2>Good to know</h2>
<p>In summer Puerto Banús is busy day and night. If you want quiet nights, Nueva Andalucía or the Golden Mile keep it close by.</p>
HTML,
		'es'     => <<<'HTML'
<p>Puerto Banús es el puerto deportivo de Marbella, lleno de yates de lujo, entre la Milla de Oro y Nueva Andalucía. Encaja contigo si quieres tener la vida nocturna, las tiendas, los beach clubs y los restaurantes a la puerta de casa.</p>

<h2>Cómo es</h2>
<p>Alrededor del puerto encontrarás tiendas de grandes marcas y comercios especializados, un cine y paseos en barco por la Costa del Sol. Es un buen sitio para sentarse a ver pasar a la gente. Por la noche, bares, locales de música y discotecas abren hasta la madrugada.</p>

<h2>Playas</h2>
<p>Las playas están limpias y bien cuidadas, con chiringuitos y beach clubs. Hacia el oeste, la playa del Duque tiene pequeñas calas resguardadas de las olas y un paseo marítimo con palmeras hacia San Pedro de Alcántara.</p>

<h2>Comer y salir</h2>
<p>Nuestra guía ha recomendado La Bocana, restaurante familiar con terraza sobre el puerto; Amore e Fantasia, pasta, pizza y cócteles frente al puerto; Carpaccio, de cocina italiana; y Tango, asador argentino.</p>

<h2>Golf y deporte</h2>
<p>Los campos de Nueva Andalucía (Los Naranjos, Las Brisas y Aloha) están justo detrás de Puerto Banús.</p>

<h2>Servicios</h2>
<p>Los centros comerciales de El Corte Inglés y Marina Banús, supermercados, farmacias y bancos, todo cerca.</p>

<h2>Cómo moverse</h2>
<p>Casi todo se hace andando. En temporada alta cuesta aparcar y hay mucha demanda de taxis, así que un chófer te ahorra la búsqueda; nuestro equipo te lo organiza.</p>

<h2>Lo que hay que saber</h2>
<p>En verano Puerto Banús está lleno de día y de noche. Si quieres noches tranquilas, Nueva Andalucía o la Milla de Oro te lo dejan cerca.</p>
HTML,
	),
	'guadalmina' => array(
		'nombre' => array( 'Guadalmina', 'Guadalmina' ),
		'en'     => <<<'HTML'
<p>Guadalmina is at the western end of the municipality of Marbella, bordering Estepona and next to San Pedro de Alcántara. The A-7 divides it in two: Guadalmina Baja, between the road and the sea, and Guadalmina Alta, inland. It suits families and golfers who want quiet close to the beach.</p>

<h2>What it is like</h2>
<p>Guadalmina Baja is made up of beautiful, spacious villas with gardens, and it is extremely quiet: only a few residents' cars drive through, plus guests of the Guadalmina Spa &amp; Golf Resort hotel, which has its own golf course and beach club. Guadalmina Alta is more apartments and houses, with the area's shopping centre.</p>

<h2>Beaches</h2>
<p>The beach runs from the San Pedro de Alcántara promenade to the start of Estepona. The large villas extend their gardens right to the sand and there is no promenade, just a few beach bars and the hotel's beach club, with one of the few golf courses that ends where the sand begins. It is a natural, uncrowded beach that has been awarded the Blue Flag on numerous occasions. The Roman baths of Las Bóvedas and a 16th-century watchtower can still be visited.</p>

<h2>Eating out</h2>
<p>Guadalmina Baja has its places by the sea, with Mediterranean dishes, while the shopping centre in Guadalmina Alta is a food corner of its own, from Italian pasta to a Basque grill and Thai food. Our guide has recommended El Ancla and La Pesquera de San Pedro, for seafood; El Pescador de Guadalmina, for fish and shellfish; and El Asador de Guadalmina, a Basque steakhouse with grilled meat and fish.</p>

<h2>Golf and sport</h2>
<p>The Real Club de Golf Guadalmina has two 18-hole courses and welcomes visitors. Atalaya Golf &amp; Country Club and El Higueral, a nine-hole course in the hills of Benahavís, are close by.</p>

<h2>Services</h2>
<p>The Guadalmina shopping centre has a Carrefour Market, pharmacies, banks, bars and other services. San Pedro de Alcántara, next door, has everything else.</p>

<h2>Getting around</h2>
<p>Both parts of Guadalmina are well connected with the A-7: east to Puerto Banús, Nueva Andalucía, the Golden Mile, Marbella and Málaga airport, and west to Estepona, Manilva and Sotogrande. You will need a car or a driver.</p>

<h2>Good to know</h2>
<p>Guadalmina Baja is for quiet days by the sea, not for nightlife: for that, Puerto Banús is a short drive east.</p>
HTML,
		'es'     => <<<'HTML'
<p>Guadalmina está en el extremo oeste del municipio de Marbella, lindando con Estepona y junto a San Pedro de Alcántara. La A-7 la divide en dos: Guadalmina Baja, entre la carretera y el mar, y Guadalmina Alta, hacia el interior. Encaja con familias y golfistas que buscan tranquilidad cerca de la playa.</p>

<h2>Cómo es</h2>
<p>Guadalmina Baja se compone de bonitas y amplias villas con jardines, y su tranquilidad es extrema: solo circulan unos pocos coches de residentes y los huéspedes del hotel Guadalmina Spa &amp; Golf Resort, con su propio campo de golf y beach club. Guadalmina Alta tiene más apartamentos y casas, y el centro comercial de la zona.</p>

<h2>Playas</h2>
<p>La playa va del paseo marítimo de San Pedro de Alcántara al inicio de Estepona. Las grandes villas llevan sus jardines hasta la arena y no hay paseo marítimo, solo unos chiringuitos y el beach club del hotel, con uno de los pocos campos de golf que acaban donde empieza la arena. Es una playa natural y poco concurrida que ha recibido la bandera azul en numerosas ocasiones. Aún se pueden visitar las termas romanas de Las Bóvedas y una torre vigía del siglo XVI.</p>

<h2>Comer y salir</h2>
<p>Guadalmina Baja tiene sus rincones junto al mar, con cocina mediterránea, y el centro comercial de Guadalmina Alta es un rincón gastronómico en sí mismo, de la pasta italiana al asador vasco o la comida tailandesa. Nuestra guía ha recomendado El Ancla y La Pesquera de San Pedro, de pescado y marisco; El Pescador de Guadalmina; y El Asador de Guadalmina, asador vasco de carnes y pescados a la brasa.</p>

<h2>Golf y deporte</h2>
<p>El Real Club de Golf Guadalmina tiene dos campos de 18 hoyos y recibe visitantes. Atalaya Golf &amp; Country Club y El Higueral, un campo de nueve hoyos en las colinas de Benahavís, quedan cerca.</p>

<h2>Servicios</h2>
<p>El centro comercial de Guadalmina tiene Carrefour Market, farmacias, bancos, bares y otros servicios. San Pedro de Alcántara, al lado, tiene todo lo demás.</p>

<h2>Cómo moverse</h2>
<p>Las dos partes de Guadalmina están bien comunicadas con la A-7: hacia el este, Puerto Banús, Nueva Andalucía, la Milla de Oro, Marbella y el aeropuerto de Málaga; hacia el oeste, Estepona, Manilva y Sotogrande. Necesitarás coche o chófer.</p>

<h2>Lo que hay que saber</h2>
<p>Guadalmina Baja es para días tranquilos junto al mar, no para la vida nocturna: para eso, Puerto Banús queda a un rato en coche hacia el este.</p>
HTML,
	),
	'los-flamingos' => array(
		'nombre' => array( 'Los Flamingos', 'Los Flamingos' ),
		'en'     => <<<'HTML'
<p>Los Flamingos is a gated residential complex with 24-hour security in Benahavís, around the Anantara Villa Padierna Palace hotel and its golf courses, with views of the sea and the mountains. It sits above the New Golden Mile, between San Pedro de Alcántara and Estepona, near the Cancelada exit. It suits golfers and families who want a quiet villa with views.</p>

<h2>What it is like</h2>
<p>An entirely residential area with no shops, a few minutes from the village of Benahavís, known for its food and its Andalusian streets.</p>

<h2>Beaches</h2>
<p>The nearest beaches are Guadalmansa and El Saladillo, on the New Golden Mile. Guadalmansa is a natural beach without crowds, even in the busiest months, and popular for water sports such as kitesurfing. El Saladillo has beach bars and is a beach to relax and enjoy the quiet.</p>

<h2>Eating out</h2>
<p>The Villa Padierna hotel has several restaurants, such as La Loggia, for Mediterranean and Italian cooking, and 99 Sushi Bar, plus a beach club, By the Sea. Near the coast, our guide has recommended Tikitano, in Guadalmansa, and Salduna Beach. In Benahavís village, the restaurants serve mountain dishes such as rabbit, suckling pig, veal and venison, as well as fish and shellfish.</p>

<h2>Golf and sport</h2>
<p>Villa Padierna has three 18-hole courses, including Alferini. Atalaya Golf &amp; Country Club, El Paraíso, designed by Gary Player, the Real Club de Golf Guadalmina and the Marbella Club Golf Resort are close by.</p>

<h2>Services</h2>
<p>There is no supermarket within walking distance. Supermarkets (Lidl, Mercadona and Supeco), pharmacies and cash machines are on the coast road, a short drive away.</p>

<h2>Getting around</h2>
<p>You will need a car or a driver for everything. The AP-7 toll road and the A-7 are close by, and our team can arrange airport transfers.</p>

<h2>Good to know</h2>
<p>Do your shopping on the way back from the beach or from a day out, because there are no shops in Los Flamingos.</p>
HTML,
		'es'     => <<<'HTML'
<p>Los Flamingos es un complejo residencial cerrado, con seguridad las 24 horas, en Benahavís, en torno al hotel Anantara Villa Padierna Palace y sus campos de golf, con vistas al mar y a la montaña. Está sobre la New Golden Mile, entre San Pedro de Alcántara y Estepona, cerca de la salida de Cancelada. Encaja con golfistas y familias que buscan una villa tranquila con vistas.</p>

<h2>Cómo es</h2>
<p>Una zona totalmente residencial, sin comercios, a pocos minutos del pueblo de Benahavís, conocido por su gastronomía y sus calles andaluzas.</p>

<h2>Playas</h2>
<p>Las playas más cercanas son Guadalmansa y El Saladillo, en la New Golden Mile. Guadalmansa es una playa natural y sin aglomeraciones, incluso en los meses de más gente, y muy buscada para deportes acuáticos como el kitesurf. El Saladillo tiene chiringuitos y es una playa para relajarse y disfrutar del silencio.</p>

<h2>Comer y salir</h2>
<p>El hotel Villa Padierna tiene varios restaurantes, como La Loggia, de cocina mediterránea e italiana, y 99 Sushi Bar, además de un beach club, By the Sea. Cerca de la costa, nuestra guía ha recomendado Tikitano, en Guadalmansa, y Salduna Beach. En el pueblo de Benahavís, los restaurantes sirven platos de sierra como conejo, cochinillo, ternera o venado, y también pescado y marisco.</p>

<h2>Golf y deporte</h2>
<p>Villa Padierna tiene tres campos de 18 hoyos, entre ellos Alferini. Atalaya Golf &amp; Country Club, El Paraíso, diseñado por Gary Player, el Real Club de Golf Guadalmina y el Marbella Club Golf Resort quedan cerca.</p>

<h2>Servicios</h2>
<p>No hay supermercado al que ir andando. Supermercados (Lidl, Mercadona y Supeco), farmacias y cajeros están en la carretera de la costa, a poca distancia en coche.</p>

<h2>Cómo moverse</h2>
<p>Necesitarás coche o chófer para todo. La autopista de peaje AP-7 y la A-7 quedan cerca, y nuestro equipo te organiza los traslados al aeropuerto.</p>

<h2>Lo que hay que saber</h2>
<p>Aprovecha la vuelta de la playa o de una excursión para hacer la compra, porque en Los Flamingos no hay tiendas.</p>
HTML,
	),
	'la-alqueria' => array(
		'nombre' => array( 'La Alquería', 'La Alquería' ),
		'en'     => <<<'HTML'
<p>La Alquería is in the gentle hills of Benahavís, at the foot of Atalaya Golf &amp; Country Club, between the village of Benahavís and the coast. Its large villas, with open-plan modern design, big gardens and private pools, have views, privacy and 24-hour private security. It suits families who want space, quiet and nature, a short drive from the beach, Marbella and Estepona.</p>

<h2>What it is like</h2>
<p>A residential area surrounded by nature, with hiking and mountain-bike routes nearby. A couple of kilometres away, the Acequia del Guadalmina path is an easy circular walk along the old irrigation channel, with mountain views. For something more adventurous, you can go down the Guadalmina river: take water shoes and a waterproof backpack, find out about the route before you go, and expect to swim some stretches. It is best in summer.</p>

<h2>Beaches</h2>
<p>La Alquería is on the hillside. The nearest beaches are Guadalmina, a natural, uncrowded beach that has been awarded the Blue Flag on numerous occasions, and those of the New Golden Mile, where you can also do water sports such as kitesurfing.</p>

<h2>Eating out</h2>
<p>Benahavís village, known for its restaurants, is a few minutes up the road, and the coast road has plenty more. Our guide has recommended La Alcaría de Ramos, for traditional food, and Yiu &amp; Gabo, in El Paraíso; El Carnicero 1993, for premium beef, in Cancelada; and The Garden by La Rosa, for Argentine grills, in Atalaya.</p>

<h2>Golf and sport</h2>
<p>Atalaya Golf &amp; Country Club is next door. El Paraíso, the Real Club de Golf Guadalmina, the Marbella Club Golf Resort and the three courses of Villa Padierna are close by.</p>

<h2>Services</h2>
<p>The only shop within walking distance is a Mercadona supermarket. Carrefour Market, Aldi, pharmacies and banks are a short drive away, on the coast road or in Benahavís.</p>

<h2>Getting around</h2>
<p>You will need a car or a driver. The road up to Benahavís, along the Guadalmina river, is a beautiful drive.</p>

<h2>Good to know</h2>
<p>Choose La Alquería for nature, space and quiet; for restaurants and the beach you will drive.</p>
HTML,
		'es'     => <<<'HTML'
<p>La Alquería está en las suaves colinas de Benahavís, a los pies de Atalaya Golf &amp; Country Club, entre el pueblo de Benahavís y la costa. Sus grandes villas, de diseño moderno y planta abierta, con amplios jardines y piscina privada, tienen vistas, privacidad y seguridad privada las 24 horas. Encaja con familias que buscan espacio, tranquilidad y naturaleza, a poca distancia en coche de la playa, Marbella y Estepona.</p>

<h2>Cómo es</h2>
<p>Una zona residencial rodeada de naturaleza, con rutas de senderismo y de bici de montaña cerca. A un par de kilómetros, el sendero de la Acequia del Guadalmina es un recorrido circular sencillo junto a la acequia, con vistas a la sierra. Si buscas algo más aventurero, puedes hacer el descenso del río Guadalmina: lleva zapatillas de agua y una mochila estanca, infórmate de la ruta antes de ir y cuenta con nadar algunos tramos. Lo mejor es hacerlo en verano.</p>

<h2>Playas</h2>
<p>La Alquería está en la ladera. Las playas más cercanas son la de Guadalmina, natural y poco concurrida, con bandera azul en numerosas ocasiones, y las de la New Golden Mile, donde también se practican deportes acuáticos como el kitesurf.</p>

<h2>Comer y salir</h2>
<p>El pueblo de Benahavís, conocido por sus restaurantes, está a pocos minutos carretera arriba, y en la carretera de la costa hay muchos más. Nuestra guía ha recomendado La Alcaría de Ramos, de comida tradicional, y Yiu &amp; Gabo, en El Paraíso; El Carnicero 1993, de carnes de calidad, en Cancelada; y The Garden by La Rosa, de carnes a la brasa y recetas argentinas, en Atalaya.</p>

<h2>Golf y deporte</h2>
<p>Atalaya Golf &amp; Country Club está al lado. El Paraíso, el Real Club de Golf Guadalmina, el Marbella Club Golf Resort y los tres campos de Villa Padierna quedan cerca.</p>

<h2>Servicios</h2>
<p>Lo único a lo que se llega andando es un supermercado Mercadona. Carrefour Market, Aldi, farmacias y bancos están a poca distancia en coche, en la carretera de la costa o en Benahavís.</p>

<h2>Cómo moverse</h2>
<p>Necesitarás coche o chófer. La carretera que sube a Benahavís junto al río Guadalmina es un paseo precioso.</p>

<h2>Lo que hay que saber</h2>
<p>Elige La Alquería por naturaleza, espacio y tranquilidad; a los restaurantes y a la playa irás en coche.</p>
HTML,
	),
	'rio-real' => array(
		'nombre' => array( 'Río Real', 'Río Real' ),
		'en'     => <<<'HTML'
<p>Río Real is a residential area just east of Marbella town, as you leave the centre towards Málaga. It offers a quiet life in a green, discreet setting, typical of a suburb, with Marbella's shops, restaurants and beaches a few minutes away. It suits guests who want peace and quiet without giving up the town.</p>

<h2>What it is like</h2>
<p>The Río Real golf course runs through the area from north to south, among large groves of trees, with some holes by the beach. Around it are villas and residential streets with very little through traffic.</p>

<h2>Beaches</h2>
<p>Depending on where you stay, the sea is a walk or a short drive away. Being further from the centre of Marbella, these beaches are less busy and more natural: fine sand, flat and firm underfoot, ideal for long walks at sunrise or sunset. A wooden boardwalk along the coast is used all year round for walking, cycling or taking the dog out.</p>

<h2>Eating out</h2>
<p>By the sea there are beach clubs where you can watch the sunset with a cocktail, such as Trocadero Arena, and the hotel Los Monteros has its own restaurants. Our guide has recommended The Point and Opium Beach Club, on the coast road, and Bounty and La Dolce Vita, on Playa del Cable.</p>

<h2>Golf and sport</h2>
<p>Río Real Golf, designed by Javier Arana, is in the area itself, and it has a golf academy. Santa Clara Golf and Marbella Golf Country Club, designed by Robert Trent Jones Sr, are a few minutes away.</p>

<h2>Services</h2>
<p>There are few services in Río Real itself. Supermarkets (Aldi and Mercadona), pharmacies and banks are a few minutes away by car, in Marbella.</p>

<h2>Getting around</h2>
<p>Marbella town centre is a short drive away, so you can do your shopping, run errands and go out, and be back in a few minutes. You will need a car, a taxi or a driver.</p>

<h2>Good to know</h2>
<p>Río Real is for quiet stays close to town and the beach, not for nightlife on your doorstep.</p>
HTML,
		'es'     => <<<'HTML'
<p>Río Real es una zona residencial justo al este de Marbella, a la salida del centro en dirección a Málaga. Ofrece una vida tranquila en un entorno verde y discreto, propio de un barrio a las afueras, con las tiendas, los restaurantes y las playas de Marbella a pocos minutos. Encaja con quien busca tranquilidad sin renunciar a la ciudad.</p>

<h2>Cómo es</h2>
<p>El campo de golf de Río Real recorre la zona de norte a sur, entre grandes arboledas, con algunos hoyos junto a la playa. A su alrededor hay villas y calles residenciales con muy poco tráfico de paso.</p>

<h2>Playas</h2>
<p>Según dónde te alojes, el mar está a un paseo o a un rato en coche. Al estar más lejos del centro de Marbella, estas playas tienen menos gente y más naturaleza: arena fina, llana y firme bajo los pies, ideal para largos paseos al amanecer o al atardecer. Una pasarela de madera junto a la costa se usa todo el año para caminar, ir en bici o sacar al perro.</p>

<h2>Comer y salir</h2>
<p>Junto al mar hay beach clubs para ver la puesta de sol con un cóctel, como Trocadero Arena, y el hotel Los Monteros tiene sus propios restaurantes. Nuestra guía ha recomendado The Point y Opium Beach Club, en la carretera de la costa, y Bounty y La Dolce Vita, en la playa del Cable.</p>

<h2>Golf y deporte</h2>
<p>Río Real Golf, diseñado por Javier Arana, está en la propia zona y tiene escuela de golf. Santa Clara Golf y Marbella Golf Country Club, diseñado por Robert Trent Jones Sr., quedan a pocos minutos.</p>

<h2>Servicios</h2>
<p>En el propio Río Real hay pocos servicios. Supermercados (Aldi y Mercadona), farmacias y bancos a pocos minutos en coche, en Marbella.</p>

<h2>Cómo moverse</h2>
<p>El centro de Marbella queda a un rato en coche: puedes hacer tus compras, tus gestiones o salir, y estar de vuelta en unos minutos. Necesitarás coche, taxi o chófer.</p>

<h2>Lo que hay que saber</h2>
<p>Río Real es para estancias tranquilas cerca de la ciudad y de la playa, no para tener la vida nocturna a la puerta.</p>
HTML,
	),
	'estepona' => array(
		'nombre' => array( 'Estepona', 'Estepona' ),
		'en'     => <<<'HTML'
<p>Estepona lies west of Marbella on the Costa del Sol, between Marbella and Manilva. Its seafront, marina, old town and beaches make it a practical base for guests who want a slower pace while staying within driving distance of Puerto Banús, Marbella and Gibraltar.</p>

<h2>What it is like</h2>
<p>The historic centre is a cluster of narrow streets full of flowers and orange trees, linking squares such as Plaza Blas Infante, the square of the Clock tower and Plaza de las Flores, with whitewashed houses decorated with pots of geraniums. Look out for the church of Santa María de los Remedios and the ruins of the Castillo de San Luis. Between La Rada and El Cristo beaches is the fishing quarter, with the Punta Doncella lighthouse and the marina, where cafés sit among the yachts and evenings are lively. On Sundays there is a craft market. Behind the town, the mountains have several hiking trails.</p>

<h2>Beaches</h2>
<p>A seafront walkway runs along the whole coastline of Estepona, with bars, beach bars, benches and giant chess boards: a pleasure early in the day or at sunset. La Rada, the town beach, is well equipped, with showers, toilets, children's play areas, volleyball nets, sun loungers and umbrellas, lifeguards and plenty of palm trees. El Cristo is another favourite.</p>

<h2>Eating out</h2>
<p>The local dish is sardine skewers (espetos) grilled in boats on the sand. Our guide has recommended Pimienta and El Cortijo, for Andalusian food, both on Calle Caridad in the old town, and El Trocadero Playa, at the marina.</p>

<h2>Golf and sport</h2>
<p>Estepona Golf and Azata Golf, both with views of the sea and Sierra Bermeja, and El Paraíso Golf Club, designed by Gary Player, are close to town. You can also go cycling, snorkelling or horse riding on the beach, or take a shopping day trip to Gibraltar.</p>

<h2>Services</h2>
<p>Estepona has grown from a fishing village into a small town with everything you need: supermarkets, banks, pharmacies and all kinds of shops.</p>

<h2>Getting around</h2>
<p>The old town, the seafront and the marina are easy to explore on foot. For Puerto Banús, Marbella or Gibraltar you will need a car or a driver.</p>

<h2>Good to know</h2>
<p>Estepona is a town with its own life all year round, quieter than Marbella and Puerto Banús.</p>
HTML,
		'es'     => <<<'HTML'
<p>Estepona está al oeste de Marbella, en la Costa del Sol, entre Marbella y Manilva. Su paseo marítimo, su puerto, su casco antiguo y sus playas la hacen una base práctica si buscas un ritmo más tranquilo sin dejar de tener a mano, en coche, Puerto Banús, Marbella y Gibraltar.</p>

<h2>Cómo es</h2>
<p>El casco histórico es un entramado de calles estrechas llenas de flores y naranjos que enlazan plazas como la de Blas Infante, la del Reloj y la plaza de las Flores, con casas encaladas adornadas con macetas de geranios. No te pierdas la iglesia de Santa María de los Remedios ni las ruinas del castillo de San Luis. Entre las playas de La Rada y El Cristo está el barrio de pescadores, con el faro de Punta Doncella y el puerto deportivo, con cafeterías entre los yates y mucho ambiente por la noche. Los domingos hay mercadillo de artesanía. Detrás del pueblo, la sierra tiene varias rutas de senderismo.</p>

<h2>Playas</h2>
<p>Una pasarela junto al mar recorre toda la costa de Estepona, con bares, chiringuitos, bancos y tableros de ajedrez gigantes: un gusto a primera hora o al atardecer. La Rada, la playa urbana, está muy bien equipada, con duchas, aseos, juegos infantiles, redes de vóley, tumbonas y sombrillas, socorristas y muchas palmeras. El Cristo es otra de las favoritas.</p>

<h2>Comer y salir</h2>
<p>El plato típico son los espetos de sardinas hechos a la brasa en barcas sobre la arena. Nuestra guía ha recomendado Pimienta y El Cortijo, de cocina andaluza, los dos en la calle Caridad del casco antiguo, y El Trocadero Playa, en el puerto deportivo.</p>

<h2>Golf y deporte</h2>
<p>Estepona Golf y Azata Golf, los dos con vistas al mar y a Sierra Bermeja, y El Paraíso Golf Club, diseñado por Gary Player, quedan cerca del pueblo. También puedes montar en bici, hacer esnórquel o montar a caballo por la playa, o hacer una excursión de compras a Gibraltar.</p>

<h2>Servicios</h2>
<p>Estepona ha pasado de pueblo de pescadores a pequeña ciudad con todo lo necesario: supermercados, bancos, farmacias y comercios de todo tipo.</p>

<h2>Cómo moverse</h2>
<p>El casco antiguo, el paseo marítimo y el puerto se recorren a pie. Para Puerto Banús, Marbella o Gibraltar necesitarás coche o chófer.</p>

<h2>Lo que hay que saber</h2>
<p>Estepona es un pueblo con vida propia todo el año, más tranquilo que Marbella y Puerto Banús.</p>
HTML,
	),
	'san-pedro' => array(
		'nombre' => array( 'San Pedro de Alcántara', 'San Pedro de Alcántara' ),
		'en'     => <<<'HTML'
<p>San Pedro de Alcántara is part of Marbella, just west of Puerto Banús, on a fertile plain between dramatic mountains and the sea. It has its own boulevard, shops and seafront promenade, and it is quieter than the busiest parts of Marbella. It suits families, especially with young children, who want everyday life within walking distance.</p>

<h2>What it is like</h2>
<p>A town with its own life all year round, with boutique shops and good places to try the local cooking. Children will enjoy its play parks and gardens, and there is a theatre. The sunsets over the sea and the mountains are a highlight.</p>

<h2>Beaches</h2>
<p>The promenade runs along the beaches of San Pedro, from Guadalmina to the palm-lined walk towards Puerto Banús.</p>

<h2>Eating out</h2>
<p>Our guide has recommended La Pesquera de San Pedro, on the seafront, and El Ancla, for seafood all year round.</p>

<h2>Golf and sport</h2>
<p>The Real Club de Golf Guadalmina is next door, and the courses of Nueva Andalucía and Benahavís are a short drive away.</p>

<h2>Services</h2>
<p>Supermarkets, pharmacies, banks and shops are in the town itself.</p>

<h2>Getting around</h2>
<p>Day-to-day errands, the beach and restaurants can be done on foot from the centre. For Puerto Banús, Marbella or the airport you will want a car or a driver.</p>

<h2>Good to know</h2>
<p>San Pedro is a real town, not a resort: choose it for a family stay with everything close by, rather than for nightlife.</p>
HTML,
		'es'     => <<<'HTML'
<p>San Pedro de Alcántara es un núcleo de Marbella, justo al oeste de Puerto Banús, en una vega fértil entre la sierra y el mar. Tiene su bulevar, sus comercios y su paseo marítimo, y es más tranquilo que las zonas más animadas de Marbella. Encaja con familias, sobre todo con niños pequeños, que quieren tener el día a día a pie.</p>

<h2>Cómo es</h2>
<p>Un pueblo con vida propia todo el año, con tiendas pequeñas y buenos sitios para probar la cocina local. Los niños disfrutarán de sus parques infantiles y jardines, y hay teatro. Las puestas de sol con el mar y la sierra de fondo son de lo mejor.</p>

<h2>Playas</h2>
<p>El paseo marítimo recorre las playas de San Pedro, de Guadalmina al paseo con palmeras que va hacia Puerto Banús.</p>

<h2>Comer y salir</h2>
<p>Nuestra guía ha recomendado La Pesquera de San Pedro, en el paseo marítimo, y El Ancla, de pescado y marisco todo el año.</p>

<h2>Golf y deporte</h2>
<p>El Real Club de Golf Guadalmina está al lado, y los campos de Nueva Andalucía y Benahavís quedan a un rato en coche.</p>

<h2>Servicios</h2>
<p>Supermercados, farmacias, bancos y comercios en el propio pueblo.</p>

<h2>Cómo moverse</h2>
<p>Desde el centro puedes hacer a pie las compras, ir a la playa y a cenar. Para Puerto Banús, Marbella o el aeropuerto te convendrá coche o chófer.</p>

<h2>Lo que hay que saber</h2>
<p>San Pedro es un pueblo de verdad, no un complejo turístico: elígelo para una estancia en familia con todo cerca, no para la vida nocturna.</p>
HTML,
	),
	'marbella-centro' => array(
		'nombre' => array( 'Marbella town centre', 'Marbella centro' ),
		'en'     => <<<'HTML'
<p>In the heart of Marbella is the historic Old Town, around Plaza de los Naranjos, with the Alameda park and the seafront promenade a short walk away. It offers a glimpse of the town's multicultural past, with influences from North Africa and southern Europe. It suits guests who want to walk to restaurants, tapas bars, shops and the beach.</p>

<h2>What it is like</h2>
<p>Historic buildings with flower-filled balconies, small squares with fountains and terraces shaded by large umbrellas. Places to see include El Hospitalillo chapel, the remains of the Arab castle and its walls, and squares such as Plaza de los Naranjos and Plaza Puente Ronda. There are open-air concerts, markets, museums and parks: children will enjoy the Vigil de Quiñones park and the Arroyo de la Represa park, which has a bonsai museum.</p>

<h2>Beaches</h2>
<p>The town beaches and the seafront promenade are a short walk from the Old Town.</p>

<h2>Eating out</h2>
<p>The Old Town is a great place to find and enjoy tapas, with terraces on its squares and side streets.</p>

<h2>Golf and sport</h2>
<p>Río Real, Santa Clara and the courses of Nueva Andalucía are a short drive away.</p>

<h2>Services</h2>
<p>Everything is here: supermarkets, pharmacies, banks and shops.</p>

<h2>Getting around</h2>
<p>The centre is best explored on foot. Parking in the busiest spots can be a challenge, so look for car parks, and in summer taxis are in high demand.</p>

<h2>Good to know</h2>
<p>Staying in the centre puts culture, dining and nightlife on your doorstep; if you prefer a villa with garden and pool, you can stay in another area and come into town when you feel like it.</p>
HTML,
		'es'     => <<<'HTML'
<p>En el corazón de Marbella está el casco antiguo, en torno a la plaza de los Naranjos, con la Alameda y el paseo marítimo a un paseo. Muestra el pasado multicultural de la ciudad, con influencias del norte de África y del sur de Europa. Encaja contigo si quieres ir andando a restaurantes, bares de tapas, tiendas y playa.</p>

<h2>Cómo es</h2>
<p>Edificios históricos con balcones llenos de flores, plazuelas con fuentes y terrazas a la sombra de grandes sombrillas. Merece la pena ver la capilla de El Hospitalillo, los restos del castillo árabe y sus murallas, y plazas como la de los Naranjos y la del Puente de Ronda. Hay conciertos al aire libre, mercadillos, museos y parques: los niños disfrutarán del parque Vigil de Quiñones y del parque Arroyo de la Represa, que tiene un museo del bonsái.</p>

<h2>Playas</h2>
<p>Las playas del centro y el paseo marítimo están a un paseo del casco antiguo.</p>

<h2>Comer y salir</h2>
<p>El casco antiguo es un gran sitio para ir de tapas, con terrazas en sus plazas y callejuelas.</p>

<h2>Golf y deporte</h2>
<p>Río Real, Santa Clara y los campos de Nueva Andalucía quedan a un rato en coche.</p>

<h2>Servicios</h2>
<p>Está todo: supermercados, farmacias, bancos y tiendas.</p>

<h2>Cómo moverse</h2>
<p>El centro se recorre mejor a pie. Aparcar en los sitios más concurridos puede costar, así que busca un aparcamiento, y en verano hay mucha demanda de taxis.</p>

<h2>Lo que hay que saber</h2>
<p>Alojarte en el centro te deja la cultura, los restaurantes y la vida nocturna a la puerta; si prefieres una villa con jardín y piscina, puedes quedarte en otra zona y bajar al centro cuando te apetezca.</p>
HTML,
	),
	// Sin guía en la web actual: texto provisional (Julio, 18-11-2026) hasta que PC-17 la escriba.
	'benahavis' => array(
		'nombre' => array( 'Benahavís village', 'Benahavís pueblo' ),
		'en'     => '<p>A white village in the mountains above San Pedro de Alcántara, known for its restaurants. It suits guests who want countryside and views, a short drive from the coast.</p>',
		'es'     => '<p>Un pueblo blanco en la sierra, sobre San Pedro de Alcántara, conocido por sus restaurantes. Encaja con quien busca campo y vistas, a poca distancia en coche de la costa.</p>',
	),
);

// ---------------------------------------------------------------- zonas y guías

WP_CLI::log( 'Zonas nuevas…' );
$mis_nuevas = array(); // lang => [term_id]
foreach ( $mis_zonas as $mis_slug => $mis_z ) {
	foreach ( array( 'en', 'es' ) as $mis_lang ) {
		$mis_id = Maros_Sync::term( 'zona', $mis_slug, $mis_z['nombre'], $mis_lang );
		if ( ! $mis_id ) {
			WP_CLI::warning( "No se pudo crear la zona $mis_slug ($mis_lang)" );
			continue;
		}
		$mis_nuevas[ $mis_lang ][] = $mis_id;

		$mis_term = get_term( $mis_id, 'zona' );
		if ( '' === trim( (string) $mis_term->description ) || $mis_forzar ) {
			wp_update_term( $mis_id, 'zona', array( 'description' => trim( $mis_z[ $mis_lang ] ) ) );
			WP_CLI::log( "  + guía $mis_slug ($mis_lang)" );
		} else {
			WP_CLI::log( "  = $mis_slug ($mis_lang) ya tiene guía; no se toca" );
		}
	}
}

// ---------------------------------------------------------------- menús

WP_CLI::log( 'Menús…' );

/** Elemento de primer nivel del que cuelgan las zonas: el que ya tiene zonas debajo o, si no, el primero. */
function mis_zonas_padre( array $items ): int {
	$top = array();
	foreach ( $items as $it ) {
		if ( 0 === (int) $it->menu_item_parent ) {
			$top[] = (int) $it->ID;
		}
	}
	foreach ( $items as $it ) {
		if ( 'zona' === $it->object && in_array( (int) $it->menu_item_parent, $top, true ) ) {
			return (int) $it->menu_item_parent;
		}
	}
	return 0;
}

foreach ( array( 'en', 'es' ) as $mis_lang ) {
	foreach ( array( 'primary', 'footer-stay' ) as $mis_loc ) {
		$mis_menu = wp_get_nav_menu_object( "$mis_loc ($mis_lang)" );
		if ( ! $mis_menu ) {
			continue; // Sin contenido inicial no hay menús: no se crean aquí.
		}
		$mis_items = wp_get_nav_menu_items( $mis_menu->term_id ) ?: array();
		$mis_padre = mis_zonas_padre( $mis_items );
		if ( ! $mis_padre && 'primary' === $mis_loc ) {
			continue; // En el menú principal solo se añade bajo «Zonas»; sin él no se adivina.
		}
		if ( ! $mis_padre ) {
			foreach ( $mis_items as $mis_it ) {
				if ( 0 === (int) $mis_it->menu_item_parent ) {
					$mis_padre = (int) $mis_it->ID;
					break;
				}
			}
		}
		$mis_ya = array_map( static fn( $it ) => 'zona' === $it->object ? (int) $it->object_id : 0, $mis_items );
		foreach ( $mis_nuevas[ $mis_lang ] ?? array() as $mis_id ) {
			if ( in_array( $mis_id, $mis_ya, true ) ) {
				continue;
			}
			wp_update_nav_menu_item(
				$mis_menu->term_id,
				0,
				array(
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $mis_padre,
					'menu-item-object'    => 'zona',
					'menu-item-object-id' => $mis_id,
					'menu-item-type'      => 'taxonomy',
				)
			);
			WP_CLI::log( "  + " . get_term( $mis_id )->name . " en $mis_loc ($mis_lang)" );
		}
	}
}

flush_rewrite_rules( true );
WP_CLI::success( 'Zonas listas.' );
