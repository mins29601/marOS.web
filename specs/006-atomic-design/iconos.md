# Iconos: Phosphor Light + extensión propia

**Decisión** (Julio, 13/11/2026, T35): base **Phosphor, peso Light** (MIT, ~1.500 iconos,
`@phosphor-icons/core`); los que faltan se dibujan en la misma rejilla y trazo.

**Dónde están** (17/11/2026): en el plugin, `wp-content/plugins/maros-connector/assets/icons/`,
con `phosphor/` (solo los que usa la web, más su `LICENSE` MIT) y `propios/`. Los pinta
`includes/icons.php`: `maros_amenity_icon( $codigo )` da el icono de una comodidad y
`maros_icon_svg( $icono, $px )` lo devuelve como SVG en línea con `currentColor`. Lo usan la
lista de comodidades, las key features y las tarjetas de dormitorios (camas, baño en-suite y
cada comodidad especial con su propio icono, ya no un check).

**Reglas de la extensión propia** (`assets/icons/propios/`):
lienzo 256 × 256, trazo 12 (equivale al peso Light), extremos y uniones redondeados, sin relleno,
`currentColor`. Se ven a 24–48 px en oro (`gold`) o negro (`black`). Son **borradores**: se
revisan en pruebas y, si hace falta, los repasa un diseñador. Vista previa:
`/mnt/project-files/marca/iconos-borrador.png` (no está en el repo).

| Equipamiento / uso | Icono | Fuente |
|---|---|---|
| Piscina | `swimming-pool` | Phosphor |
| Piscina climatizada | `piscina-climatizada` | Propio |
| Jacuzzi | `jacuzzi` | Propio |
| Sauna | `sauna` | Propio |
| Gimnasio | `barbell` | Phosphor |
| Pista de tenis | `tennis-ball` | Phosphor |
| Pista de pádel | `padel` | Propio |
| Golf cerca | `golf` | Phosphor |
| Vistas al mar | `sun-horizon` / `waves` | Phosphor |
| Cerca de la playa | `playa` | Propio |
| Tumbonas / solárium | `tumbona` | Propio |
| Barbacoa | `barbacoa` | Propio |
| Aire acondicionado | `aire-acondicionado` | Propio |
| Calefacción / chimenea | `thermometer-hot` / `fire` | Phosphor |
| Wifi | `wifi-high` | Phosphor |
| Aparcamiento / garaje | `car` / `garage` | Phosphor |
| Cocina / horno | `oven` / `cooking-pot` | Phosphor |
| Lavavajillas | `lavavajillas` | Propio |
| Lavadora | `washing-machine` | Phosphor |
| **Secadora** (ropa) | `secadora` | Propio (17/11) |
| Tendedero | `tendedero` | Propio (17/11) |
| Nevera / congelador | `nevera` | Propio (17/11) |
| Microondas | `microondas` | Propio (17/11) |
| Detector de humo / CO | `detector-humo` | Propio (17/11) |
| Cuna | `cuna` | Propio (17/11) |
| Hamaca | `hamaca` | Propio (17/11) |
| Plancha | `plancha` | Propio |
| Secador de pelo | `hair-dryer` | Phosphor |
| Toallas | `towel` | Phosphor |
| Dormitorios / camas | `bed` | Phosphor |
| Baños | `bathtub` / `shower` | Phosphor |
| Cuna / niños | `baby` / `baby-carriage` | Phosphor |
| Mascotas | `dog` / `paw-print` | Phosphor |
| Televisión / cine | `television` / `film-slate` | Phosphor |
| Ascensor | `elevator` | Phosphor |
| Accesible | `wheelchair` | Phosphor |
| Seguridad / alarma | `shield-check` / `security-camera` | Phosphor |
| Jardín | `park` / `tree-palm` | Phosphor |
| Huéspedes | `users-three` | Phosphor |
| Ubicación, mapa, distancias | `map-pin` / `car` / `airplane-tilt` | Phosphor |
| Fechas, horario | `calendar-blank` / `clock` | Phosphor |
| Valoración, favoritos | `star` / `heart` | Phosphor |
| Contacto | `whatsapp-logo` / `phone` / `envelope` | Phosphor |
| Redes | `instagram-logo` / `tiktok-logo` / `youtube-logo` | Phosphor |
| Vídeo, galería | `play-circle` / `images` | Phosphor |
| Servicios: chef, traslados, barco, eventos | `chef-hat` / `van` / `sailboat` / `champagne` | Phosphor |

## Mapa completo (código de marOS → icono)

Cubre los 153 códigos del catálogo de marOS (`src/amenities.js`, spec 039) y los de Lodgify que
guarda la web. Los términos de `equipamiento` que vienen de Lodgify con otro nombre se reconocen
por palabras («ClothesDryer» → secadora, «HairDryer» → secador de pelo); sin coincidencia sale
`check`. La sincronización guarda secadora, lavadora, lavavajillas y secador de pelo con un código
fijo y su nombre en los dos idiomas («Tumble dryer» / «Secadora»), y la lista de la ficha pone
primero piscina, vistas, aire, wifi, aparcamiento, secadora y lavadora, para que entren entre las
diez que se ven sin pulsar «Ver todas».

Fuente: `MAROS_AMENITY_ICONS` en `includes/icons.php`.

| Código | Icono |
|---|---|
| `air_conditioning` | `propios/aire-acondicionado` |
| `arcade_games` | `phosphor/joystick` |
| `baby_bath` | `phosphor/baby` |
| `baby_monitor` | `phosphor/baby` |
| `baby_safety_gates` | `phosphor/baby` |
| `babysitter_recommendations` | `phosphor/baby-carriage` |
| `backyard` | `phosphor/tree` |
| `baking_sheet` | `phosphor/cake` |
| `balcony` | `phosphor/sun` |
| `bathtub` | `phosphor/bathtub` |
| `batting_cage` | `phosphor/baseball` |
| `bbq` | `propios/barbacoa` |
| `bbq_grill` | `propios/barbacoa` |
| `bbq_utensils` | `propios/barbacoa` |
| `beach_access` | `propios/playa` |
| `beach_essentials` | `propios/playa` |
| `bed_linens` | `phosphor/bed` |
| `bidet` | `phosphor/toilet` |
| `bikes` | `phosphor/bicycle` |
| `blackout_shades` | `phosphor/moon` |
| `blender` | `phosphor/bowl-food` |
| `board_games` | `phosphor/puzzle-piece` |
| `boat_slip` | `phosphor/sailboat` |
| `body_soap` | `phosphor/hand-soap` |
| `books_reading_material` | `phosphor/books` |
| `bowling_alley` | `phosphor/bowling-ball` |
| `bread_maker` | `phosphor/bread` |
| `breakfast` | `phosphor/coffee` |
| `building_staff` | `phosphor/user-check` |
| `carbon_monoxide_alarm` | `propios/detector-humo` |
| `ceiling_fan` | `phosphor/fan` |
| `changing_table` | `phosphor/baby` |
| `check` | `phosphor/check` |
| `cleaning_during_stay` | `phosphor/broom` |
| `cleaning_products` | `phosphor/spray-bottle` |
| `climbing_wall` | `phosphor/mountains` |
| `clothing_storage` | `phosphor/dresser` |
| `coffee` | `phosphor/coffee-bean` |
| `coffee_maker` | `phosphor/coffee` |
| `compost_bin` | `phosphor/leaf` |
| `conditioner` | `phosphor/drop` |
| `cooking_basics` | `phosphor/cooking-pot` |
| `crib` | `propios/cuna` |
| `dedicated_workspace` | `phosphor/desk` |
| `desk` | `phosphor/desk` |
| `dining_table` | `phosphor/fork-knife` |
| `direct_pool_access` | `phosphor/swimming-pool` |
| `dishes_silverware` | `phosphor/fork-knife` |
| `dishwasher` | `propios/lavavajillas` |
| `dryer` | `propios/secadora` |
| `drying_rack` | `propios/tendedero` |
| `ebike_charger` | `phosphor/plug-charging` |
| `elevator` | `phosphor/elevator` |
| `ensuite` | `phosphor/bathtub` |
| `essentials` | `phosphor/towel` |
| `ethernet_connection` | `phosphor/network` |
| `ev_charger` | `phosphor/charging-station` |
| `exercise_equipment` | `phosphor/barbell` |
| `extra_pillows_blankets` | `phosphor/bed` |
| `fire_extinguisher` | `phosphor/fire-extinguisher` |
| `fire_pit` | `phosphor/campfire` |
| `fireplace_guards` | `phosphor/shield-check` |
| `first_aid_kit` | `phosphor/first-aid-kit` |
| `free_parking_off_premises` | `phosphor/car` |
| `free_parking_on_premises` | `phosphor/car` |
| `free_street_parking` | `phosphor/car` |
| `freezer` | `propios/nevera` |
| `game_console` | `phosphor/game-controller` |
| `garden` | `phosphor/park` |
| `garden_view` | `phosphor/plant` |
| `gated_community` | `phosphor/shield-check` |
| `golf_view` | `phosphor/golf` |
| `gym` | `phosphor/barbell` |
| `hair_dryer` | `phosphor/hair-dryer` |
| `hammock` | `propios/hamaca` |
| `hangers` | `phosphor/coat-hanger` |
| `heated_pool` | `propios/piscina-climatizada` |
| `heating` | `phosphor/thermometer-hot` |
| `high_chair` | `phosphor/baby` |
| `hockey_rink` | `phosphor/hockey` |
| `hot_tub` | `propios/jacuzzi` |
| `hot_water` | `phosphor/thermometer-hot` |
| `independent_access` | `phosphor/door-open` |
| `indoor_fireplace` | `phosphor/fire` |
| `iron` | `propios/plancha` |
| `kayak` | `phosphor/boat` |
| `kettle` | `phosphor/coffee` |
| `key` | `phosphor/key` |
| `kids_bikes` | `phosphor/bicycle` |
| `kids_books_toys` | `phosphor/lego` |
| `kids_dinnerware` | `phosphor/fork-knife` |
| `kids_playroom` | `phosphor/lego-smiley` |
| `kitchen` | `phosphor/cooking-pot` |
| `kitchenette` | `phosphor/cooking-pot` |
| `lake_access` | `phosphor/waves` |
| `laser_tag` | `phosphor/target` |
| `laundromat_nearby` | `phosphor/washing-machine` |
| `laundry_services` | `phosphor/t-shirt` |
| `life_size_games` | `phosphor/dice-five` |
| `long_term_stays` | `phosphor/calendar-check` |
| `luggage_dropoff` | `phosphor/suitcase-rolling` |
| `microwave` | `propios/microondas` |
| `mini_fridge` | `propios/nevera` |
| `mini_golf` | `phosphor/golf` |
| `mosquito_net` | `phosphor/bug` |
| `mountain_view` | `phosphor/mountains` |
| `outdoor_dining_area` | `phosphor/fork-knife` |
| `outdoor_furniture` | `phosphor/chair` |
| `outdoor_kitchen` | `propios/barbacoa` |
| `outdoor_playground` | `phosphor/park` |
| `outdoor_shower` | `phosphor/shower` |
| `outlet_covers` | `phosphor/plug` |
| `oven` | `phosphor/oven` |
| `padel` | `propios/padel` |
| `paid_parking_off_premises` | `phosphor/car` |
| `paid_parking_on_premises` | `phosphor/car` |
| `parking` | `phosphor/car` |
| `patio_balcony` | `phosphor/sun` |
| `patio_garden_access` | `phosphor/park` |
| `piano` | `phosphor/piano-keys` |
| `ping_pong_table` | `phosphor/ping-pong` |
| `pocket_wifi` | `phosphor/cell-signal-full` |
| `pool` | `phosphor/swimming-pool` |
| `pool_table` | `phosphor/target` |
| `pool_view` | `phosphor/swimming-pool` |
| `portable_fans` | `phosphor/fan` |
| `private_entrance` | `phosphor/door` |
| `private_living_room` | `phosphor/couch` |
| `private_terrace` | `phosphor/sun` |
| `record_player` | `phosphor/vinyl-record` |
| `recycling_bins` | `phosphor/recycle` |
| `refrigerator` | `propios/nevera` |
| `resort_access` | `phosphor/buildings` |
| `rice_maker` | `phosphor/cooking-pot` |
| `safe` | `phosphor/lock-key` |
| `sauna` | `propios/sauna` |
| `sea_view` | `phosphor/sun-horizon` |
| `shampoo` | `phosphor/drop` |
| `shower` | `phosphor/shower` |
| `shower_gel` | `phosphor/hand-soap` |
| `single_level_home` | `phosphor/house-simple` |
| `sitting_area` | `phosphor/armchair` |
| `skate_ramp` | `phosphor/sneaker-move` |
| `ski_in_out` | `phosphor/person-simple-ski` |
| `smoke_alarm` | `propios/detector-humo` |
| `solar_panels` | `phosphor/solar-panel` |
| `sound_system` | `phosphor/speaker-hifi` |
| `stove` | `phosphor/cooking-pot` |
| `sun_loungers` | `propios/tumbona` |
| `table_corner_guards` | `phosphor/shield-check` |
| `terrace` | `phosphor/sun` |
| `theatre_room` | `phosphor/film-slate` |
| `themed_room` | `phosphor/sparkle` |
| `toaster` | `phosphor/bread` |
| `trash_compactor` | `phosphor/trash` |
| `travel_crib` | `propios/cuna` |
| `tv` | `phosphor/television` |
| `view` | `phosphor/sun-horizon` |
| `walk_in_closet` | `phosphor/coat-hanger` |
| `wardrobe` | `phosphor/dresser` |
| `washer` | `phosphor/washing-machine` |
| `waterfront` | `phosphor/waves` |
| `wifi` | `phosphor/wifi-high` |
| `window_guards` | `phosphor/shield-check` |
