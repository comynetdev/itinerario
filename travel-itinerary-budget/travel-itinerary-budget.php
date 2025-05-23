<?php

/**
 * Plugin Name: Travel Itinerary & Budget
 * Plugin URI: Tu URL del plugin (opcional)
 * Description: Plugin para crear y gestionar itinerarios de viaje con presupuesto.
 * Version: 1.0.0
 * Author: Alfonso Moreno
 * Author URI: Tu URL (opcional)
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

// Incluir archivos necesarios
require_once plugin_dir_path(__FILE__) . 'includes/class-tic-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tic-security.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tic-itineraries.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tic-flights.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tic-accommodation.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tic-activities.php';

// Función para crear las tablas de la base de datos
function tic_create_tables()
{
    $tic_database = new TIC_Database();
    $tic_database->create_tables();
}

// Función para verificar y crear las tablas (llamada solo en la activación)
function tic_install()
{
    tic_create_tables(); // Llamar a la función de creación de tablas

    // Actualizar la versión del plugin
    update_option('tic_plugin_version', '1.0.0');
}
register_activation_hook(__FILE__, 'tic_install');

// Funciones para manejar usuarios no registrados (sesiones)
function tic_init_session()
{
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'tic_init_session', 1);

function tic_enqueue_assets()
{
    wp_enqueue_style('tic-styles', plugin_dir_url(__FILE__) . 'assets/css/tic-styles.css');

    // wp_enqueue_script('tic-main-js', plugin_dir_url(__FILE__) . 'assets/js/tic-main.js', array('jquery'), '1.0', true);
    // wp_localize_script('tic-main-js', 'tic_ajax_object', array('ajaxurl' => admin_url('admin-ajax.php'))); // No modificar si no existe

    wp_enqueue_script('tic-flights-js', plugin_dir_url(__FILE__) . 'assets/js/tic-flights.js', array('jquery'), '1.0', true); // Corregir el handle si es necesario
    // Cargar nonce:
    wp_localize_script('tic-flights-js', 'tic_ajax_object', array(
        'ajaxurl'             => admin_url('admin-ajax.php'),
        'load_module_nonce'   => wp_create_nonce('tic_load_module_action_nonce'),
        'get_flight_details_nonce' => wp_create_nonce('tic_get_flight_details_action'),
        'delete_flight_nonce'       => wp_create_nonce('tic_delete_flight_action'),
        'get_accommodation_details_nonce'   => wp_create_nonce('tic_get_accommodation_details_action'),
        'delete_accommodation_nonce'      => wp_create_nonce('tic_delete_accommodation_action'),
        'get_activity_details_nonce'      => wp_create_nonce('tic_get_activity_details_action'),
        'delete_activity_nonce'           => wp_create_nonce('tic_delete_activity_action'),
        'is_user_logged_in'               => is_user_logged_in()
        // Mantén cualquier otra variable que ya tuvieras aquí
    ));

    // wp_enqueue_script('tic-accommodation-js', plugin_dir_url(__FILE__) . 'assets/js/tic-main.js', array('jquery', 'tic-main-js'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'tic_enqueue_assets');

// Función para mostrar el dashboard (Shortcode)
function tic_mostrar_dashboard()
{
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);
    ob_start();
    include_once plugin_dir_path(__FILE__) . 'templates/tic-dashboard.php';
    return ob_get_clean();
}
add_shortcode('travel_itinerary_dashboard', 'tic_mostrar_dashboard');

// Registrar la acción AJAX para crear itinerarios
add_action('wp_ajax_tic_create_itinerary', 'tic_ajax_create_itinerary');

function tic_ajax_create_itinerary()
{
    // Verificar el nonce primero
    check_ajax_referer('tic_create_itinerary_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_itineraries = new TIC_Itineraries($tic_database, $tic_security);
    $tic_itineraries->crear_itinerario();
}

// Funciones para manejar el formulario de vuelos
add_action('wp_ajax_tic_guardar_vuelo', function (...$args) {
    // Verificar nonce primero
    check_ajax_referer('tic_guardar_vuelo_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);
    $tic_flights->guardar_vuelo();
});
add_action('wp_ajax_nopriv_tic_guardar_vuelo', function (...$args) {
    // Verificar nonce primero
    check_ajax_referer('tic_guardar_vuelo_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);
    $tic_flights->guardar_vuelo();
});
add_action('wp_ajax_tic_mostrar_formulario_vuelos', function () { // No necesitamos ...$args aquí
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);

    // Obtener y sanitizar el ID del itinerario desde la solicitud AJAX
    $itinerario_id = 0;
    if (isset($_POST['itinerary_id'])) {
        // Usar la instancia de $tic_security que ya tenemos
        $itinerario_id = $tic_security->sanitize_integer($_POST['itinerary_id']);
    }

    // Obtener el HTML del formulario pasando el ID
    $form_html = $tic_flights->mostrar_formulario_vuelos($itinerario_id);

    // Devolver el HTML como respuesta AJAX
    echo $form_html;

    wp_die(); // Mover wp_die() aquí para el handler de usuarios registrados
}); // Mover el cierre aquí para el handler de usuarios registrados

// Handler para usuarios NO registrados (nopriv)
add_action('wp_ajax_nopriv_tic_mostrar_formulario_vuelos', function () {
    // Log de depuración eliminado
    // Restaurar lógica original
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);

    // Para usuarios no registrados, el ID siempre será 0 (o manejado por sesión)
    $itinerario_id = 0;

    // Obtener el HTML del formulario pasando 0
    $form_html = $tic_flights->mostrar_formulario_vuelos($itinerario_id);

    // Devolver el HTML como respuesta AJAX
    echo $form_html;

    wp_die(); // wp_die() para el handler nopriv
});

// Agregar estas líneas:
add_action('wp_ajax_tic_mostrar_reporte_vuelos', 'tic_mostrar_reporte_vuelos_ajax');
add_action('wp_ajax_nopriv_tic_mostrar_reporte_vuelos', 'tic_mostrar_reporte_vuelos_ajax');

// Ya tienes esto para registrar las acciones AJAX:
// add_action('wp_ajax_tic_mostrar_formulario_vuelos', 'tic_ajax_mostrar_formulario_vuelos_handler');
// add_action('wp_ajax_nopriv_tic_mostrar_formulario_vuelos', 'tic_ajax_mostrar_formulario_vuelos_handler'); // Para usuarios no registrados

/**
 * Función manejadora para la acción AJAX 'tic_mostrar_formulario_vuelos'.
 * Muestra el formulario de vuelos o un mensaje si no hay itinerario seleccionado.
 */
function tic_ajax_mostrar_formulario_vuelos_handler()
{

    check_ajax_referer('tic_load_module_action_nonce', 'nonce');

    // Obtener el ID del itinerario desde la solicitud AJAX
    $itinerario_id_input = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';

    // --- INICIO LÓGICA DE VERIFICACIÓN ---
    if (is_user_logged_in()) {

        $itinerario_id_valido = intval($itinerario_id_input);
        if ($itinerario_id_valido <= 0) {
            echo '<p class="tic-notice">Por favor, selecciona un itinerario de la lista o crea uno nuevo para gestionar los vuelos.</p>';
            wp_die(); // Terminar ejecución
        }
        // Usar el ID validado para usuarios logueados
        $id_a_pasar = $itinerario_id_valido;
    } else {
        // Para usuarios no logueados, el ID es 'temp' o lo que sea que estés usando
        // Asegúrate que tu JS envíe 'temp' o lo que corresponda si no hay ID numérico
        $id_a_pasar = ($itinerario_id_input === 'temp') ? 'temp' : 0; // O la lógica que tengas para 'temp'
    }
    // --- FIN LÓGICA DE VERIFICACIÓN ---

    // Instanciar Clases (como ya lo haces)
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights_instance = new TIC_Flights($tic_database, $tic_security); // Renombré la variable para claridad

    // Llamar al método de la clase que genera el HTML y hacer echo
    // La función mostrar_formulario_vuelos ya retorna el HTML con ob_start/ob_get_clean
    echo $tic_flights_instance->mostrar_formulario_vuelos($id_a_pasar);

    wp_die(); // Es importante terminar la ejecución AJAX correctamente
}

function tic_mostrar_reporte_vuelos_ajax()
{
    check_ajax_referer('tic_load_module_action_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);

    $atts = array(
        'itinerario_id' => isset($_POST['itinerario_id']) ? intval($_POST['itinerario_id']) : 0,
    );

    $reporte = $tic_flights->mostrar_reporte_vuelos($atts);
    echo $reporte; // Enviar el HTML del reporte
    wp_die(); // Terminar la ejecución de AJAX
}

// En tu archivo principal del plugin (travel-itinerary-budget.php)
add_action('delete_user', 'tic_handle_user_deletion');

function tic_handle_user_deletion($user_id)
{
    global $wpdb;
    $tic_database = new TIC_Database(); // Asumiendo que tienes acceso a tu clase
    $table_itinerarios = $tic_database->get_table_name('itinerarios');

    // Sanitizar ID (aunque viene de un hook de WP, es buena práctica)
    $user_id_sanitized = absint($user_id);

    if ($user_id_sanitized > 0) {
        $wpdb->update(
            $table_itinerarios,
            array('user_id' => null), // Poner user_id a NULL
            array('user_id' => $user_id_sanitized), // Donde el user_id coincida
            array('%s'), // Formato para el valor a establecer (NULL se maneja como string a veces, o dejarlo como %d y pasar NULL) - Revisar documentación $wpdb->update
            array('%d')  // Formato para el WHERE
        );
        // O usar $wpdb->query con prepare:
        // $wpdb->query($wpdb->prepare("UPDATE {$table_itinerarios} SET user_id = NULL WHERE user_id = %d", $user_id_sanitized));
    }
}

// Registrar la nueva acción AJAX (solo para usuarios logueados)
add_action('wp_ajax_tic_get_flight_details', 'tic_ajax_get_flight_details_handler');

/**
 * Manejador AJAX para obtener los detalles de un vuelo específico.
 */
function tic_ajax_get_flight_details_handler()
{
    // 1. Verificar Nonce (lo añadiremos al JS en el siguiente paso)
    check_ajax_referer('tic_get_flight_details_action', 'nonce');

    // 2. Validar y obtener el ID del vuelo
    if (!isset($_POST['flight_id']) || empty($_POST['flight_id'])) {
        wp_send_json_error(array('message' => 'No se proporcionó ID de vuelo.'));
        return; // o wp_die();
    }
    $flight_id = absint($_POST['flight_id']);

    // 3. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    // Asumiendo que TIC_Flights no necesita TIC_Itineraries para get_flight_by_id
    $tic_flights_instance = new TIC_Flights($tic_database, $tic_security);

    // 4. Obtener los datos del vuelo
    $flight_data = $tic_flights_instance->get_flight_by_id($flight_id);

    // 5. Enviar respuesta
    if ($flight_data) {
        // Podríamos querer verificar aquí si el vuelo pertenece al itinerario_id actual 
        // o al usuario actual por seguridad, antes de enviarlo.
        // Por ahora, lo enviamos directamente.
        wp_send_json_success($flight_data);
    } else {
        wp_send_json_error(array('message' => 'Vuelo no encontrado o no tienes permiso para verlo.'));
    }
    // wp_die() es llamado por wp_send_json_success/error
}

// Registrar la nueva acción AJAX para eliminar vuelos (solo para usuarios logueados)
add_action('wp_ajax_tic_delete_flight', 'tic_ajax_delete_flight_handler');

/**
 * Manejador AJAX para eliminar un vuelo específico.
 */
function tic_ajax_delete_flight_handler()
{
    // 1. Verificar Nonce (lo crearemos y enviaremos desde JS)
    check_ajax_referer('tic_delete_flight_action', 'nonce');

    // 2. Validar y obtener el ID del vuelo
    if (!isset($_POST['flight_id']) || empty($_POST['flight_id'])) {
        wp_send_json_error(array('message' => 'No se proporcionó ID de vuelo para eliminar.'));
        // wp_die() es llamado por wp_send_json_error
    }
    $flight_id = absint($_POST['flight_id']);

    // 3. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights_instance = new TIC_Flights($tic_database, $tic_security);

    // 4. Eliminar el vuelo y sus escalas
    $deleted = $tic_flights_instance->delete_flight_and_scales($flight_id);

    // 5. Enviar respuesta
    if ($deleted) {
        wp_send_json_success(array('message' => 'Vuelo eliminado correctamente.'));
    } else {
        wp_send_json_error(array('message' => 'Error al eliminar el vuelo. Revisa los logs del servidor para más detalles.'));
    }
    // wp_die() es llamado por wp_send_json_success/error
}

// Acción AJAX para MOSTRAR el formulario de alojamiento
add_action('wp_ajax_tic_mostrar_formulario_alojamiento', 'tic_ajax_mostrar_formulario_alojamiento_handler');
add_action('wp_ajax_nopriv_tic_mostrar_formulario_alojamiento', 'tic_ajax_mostrar_formulario_alojamiento_handler');
// (Asegúrate que tic_ajax_mostrar_formulario_alojamiento_handler existe y llama a $instancia->mostrar_formulario_alojamiento())

// Acción AJAX para mostrar el REPORTE de alojamiento
add_action('wp_ajax_tic_mostrar_reporte_alojamiento', 'tic_ajax_mostrar_reporte_alojamiento_handler');
add_action('wp_ajax_nopriv_tic_mostrar_reporte_alojamiento', 'tic_ajax_mostrar_reporte_alojamiento_handler');

// Acción AJAX para mostrar el formulario de actividades
add_action('wp_ajax_tic_mostrar_formulario_actividad', 'tic_ajax_mostrar_formulario_actividad_handler');
add_action('wp_ajax_nopriv_tic_mostrar_formulario_actividad', 'tic_ajax_mostrar_formulario_actividad_handler');

// Acción AJAX para guardar una nueva actividad
add_action('wp_ajax_tic_guardar_actividad', 'tic_ajax_guardar_actividad_handler');
add_action('wp_ajax_nopriv_tic_guardar_actividad', 'tic_ajax_guardar_actividad_handler');

// Acción AJAX para mostrar el REPORTE de actividades
add_action('wp_ajax_tic_mostrar_reporte_actividad', 'tic_ajax_mostrar_reporte_actividad_handler');
add_action('wp_ajax_nopriv_tic_mostrar_reporte_actividad', 'tic_ajax_mostrar_reporte_actividad_handler');

// Registrar la nueva acción AJAX para obtener detalles de una actividad
add_action('wp_ajax_tic_get_activity_details', 'tic_ajax_get_activity_details_handler');

// Registrar la nueva acción AJAX para eliminar actividades (solo para usuarios logueados)
add_action('wp_ajax_tic_delete_activity', 'tic_ajax_delete_activity_handler');

/**
 * Manejador AJAX para mostrar el formulario de alojamiento.
 */
function tic_ajax_mostrar_formulario_alojamiento_handler()
{
    // 1. Verificar el nonce enviado desde el formulario tic-accommodation-form.php
    // El primer argumento debe coincidir con el action de wp_nonce_field()
    // El segundo argumento es el 'name' del campo nonce en el formulario
    check_ajax_referer('tic_load_module_action_nonce', 'nonce');


    $itinerario_id_input = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';

    // Lógica para validar $itinerario_id_input (similar a la de vuelos)
    $id_a_pasar = '0'; // Valor por defecto
    if (is_user_logged_in()) {
        $itinerario_id_valido = intval($itinerario_id_input);
        if ($itinerario_id_valido <= 0) {
            echo '<p class="tic-notice">Por favor, selecciona un itinerario de la lista o crea uno nuevo para gestionar el alojamiento.</p>';
            wp_die();
        }
        $id_a_pasar = $itinerario_id_valido;
    } else {
        // Para usuarios no logueados, el ID debe ser 'temp' o el identificador que uses
        $id_a_pasar = ($itinerario_id_input === 'temp') ? 'temp' : 0;
        if ($id_a_pasar === 0) { // Si no es 'temp' y no es un ID válido
            echo '<p class="tic-notice">Error al determinar el itinerario para el alojamiento (invitado).</p>';
            wp_die();
        }
    }

    // 2. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_accommodation_instance = new TIC_Accommodation($tic_database, $tic_security);

    // 3. Llamar al método de la clase para guardar.
    // Este método se encargará de sanitizar $_POST y enviar la respuesta JSON.
    //$tic_accommodation_instance->mostrar_formulario_alojamiento();
    echo $tic_accommodation_instance->mostrar_formulario_alojamiento($id_a_pasar);

    // Nota: guardar_alojamiento() llama a wp_send_json_success/error, que incluye wp_die().
}

/**
 * Manejador AJAX para mostrar el REPORTE de alojamientos.
 */
function tic_ajax_mostrar_reporte_alojamiento_handler()
{
    // 1. Verificar el Nonce
    // Reutilizamos el nonce que creamos para cargar módulos/formularios,
    // ya que la acción es similar (cargar contenido principal de un módulo).
    check_ajax_referer('tic_load_module_action_nonce', 'nonce');

    // 2. Obtener y sanitizar el ID del itinerario desde la solicitud AJAX
    // El JavaScript enviará 'itinerario_id' (o el nombre que elijas, debe coincidir).
    $itinerario_id_input = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';

    // 3. Lógica para determinar el ID a pasar a la clase
    // (Esta lógica es similar a la que usas para mostrar el formulario)
    $id_a_pasar = '0'; // Valor por defecto
    if (is_user_logged_in()) {
        $itinerario_id_valido = intval($itinerario_id_input);
        // Para mostrar un reporte, usualmente necesitamos un ID de itinerario válido
        if ($itinerario_id_valido <= 0) {
            echo '<p class="tic-notice">Por favor, selecciona un itinerario de la lista o crea uno nuevo para ver el reporte de alojamiento.</p>';
            wp_die();
        }
        $id_a_pasar = $itinerario_id_valido;
    } else { // Usuario no logueado
        // Asumimos que 'temp' es el identificador para datos de sesión de no logueados
        $id_a_pasar = ($itinerario_id_input === 'temp') ? 'temp' : '0';
        if ($id_a_pasar === '0') {
            echo '<p class="tic-notice">Error al determinar el itinerario para el reporte de alojamiento (invitado).</p>';
            wp_die();
        }
    }

    // 4. Instanciar Clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_accommodation_instance = new TIC_Accommodation($tic_database, $tic_security);

    // 5. Llamar al método de la clase que genera el HTML del REPORTE y hacer echo
    // El método mostrar_reporte_alojamiento() ya usa output buffering y devuelve el HTML.
    echo $tic_accommodation_instance->mostrar_reporte_alojamiento($id_a_pasar);

    wp_die(); // Terminar la ejecución AJAX correctamente
}

// Acción AJAX para GUARDAR un nuevo alojamiento
add_action('wp_ajax_tic_guardar_alojamiento', 'tic_ajax_guardar_alojamiento_handler');
add_action('wp_ajax_nopriv_tic_guardar_alojamiento', 'tic_ajax_guardar_alojamiento_handler');

/**
 * Manejador AJAX para guardar los datos del formulario de alojamiento.
 */
function tic_ajax_guardar_alojamiento_handler()
{
    // 1. Verificar el nonce enviado desde el formulario tic-accommodation-form.php
    // El primer argumento debe coincidir con el action de wp_nonce_field()
    // El segundo argumento es el 'name' del campo nonce en el formulario
    check_ajax_referer('tic_guardar_alojamiento_action', 'tic_accommodation_nonce');

    // 2. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_accommodation_instance = new TIC_Accommodation($tic_database, $tic_security);

    // 3. Llamar al método de la clase para guardar.
    // Este método se encargará de sanitizar $_POST y enviar la respuesta JSON.
    $tic_accommodation_instance->guardar_alojamiento();

    // Nota: guardar_alojamiento() llama a wp_send_json_success/error, que incluye wp_die().
}

// Registrar la nueva acción AJAX para obtener detalles de alojamiento
add_action('wp_ajax_tic_get_accommodation_details', 'tic_ajax_get_accommodation_details_handler');

/**
 * Manejador AJAX para obtener los detalles de un alojamiento específico.
 */
function tic_ajax_get_accommodation_details_handler()
{
    // 1. Verificar Nonce (lo añadiremos al JS en el siguiente paso)
    check_ajax_referer('tic_get_accommodation_details_action', 'nonce');

    // 2. Validar y obtener el ID del alojamiento
    if (!isset($_POST['accommodation_id']) || empty($_POST['accommodation_id'])) {
        wp_send_json_error(array('message' => 'No se proporcionó ID de alojamiento.'));
        return;
    }
    $accommodation_id = absint($_POST['accommodation_id']);

    // 3. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_accommodation_instance = new TIC_Accommodation($tic_database, $tic_security);

    // 4. Obtener los datos del alojamiento
    $accommodation_data = $tic_accommodation_instance->get_accommodation_by_id($accommodation_id);

    // 5. Enviar respuesta
    if ($accommodation_data) {
        wp_send_json_success($accommodation_data);
    } else {
        wp_send_json_error(array('message' => 'Alojamiento no encontrado o no tienes permiso para verlo.'));
    }
}
// En travel-itinerary-budget.php

// Registrar la nueva acción AJAX para eliminar alojamiento (solo para usuarios logueados)
add_action('wp_ajax_tic_delete_accommodation', 'tic_ajax_delete_accommodation_handler');

/**
 * Manejador AJAX para eliminar un alojamiento específico.
 */
function tic_ajax_delete_accommodation_handler()
{
    // 1. Verificar Nonce
    check_ajax_referer('tic_delete_accommodation_action', 'nonce');

    // 2. Validar y obtener el ID del alojamiento
    if (!isset($_POST['accommodation_id']) || empty($_POST['accommodation_id'])) {
        wp_send_json_error(array('message' => 'No se proporcionó ID de alojamiento para eliminar.'));
    }
    $accommodation_id = absint($_POST['accommodation_id']);

    // 3. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_accommodation_instance = new TIC_Accommodation($tic_database, $tic_security);

    // 4. Eliminar el alojamiento
    $deleted = $tic_accommodation_instance->delete_accommodation_by_id($accommodation_id);

    // 5. Enviar respuesta
    if ($deleted) {
        wp_send_json_success(array('message' => 'Alojamiento eliminado correctamente.'));
    } else {
        wp_send_json_error(array('message' => 'Error al eliminar el alojamiento. Verifique que el registro exista y le pertenezca.'));
    }
}
/**
 * Manejador AJAX para mostrar el formulario de actividades.
 */
function tic_ajax_mostrar_formulario_actividad_handler()
{
    error_log('--- tic_ajax_mostrar_formulario_actividad_handler INVOCADO ---'); // Log de entrada
    error_log('PHP Handler - Raw POST data: ' . print_r($_POST, true)); // Loguear todo el $_POST
    check_ajax_referer('tic_load_module_action_nonce', 'nonce'); // Reutilizamos nonce

    //$itinerario_id_input = isset($_POST['itinerary_id']) ? sanitize_text_field($_POST['itinerary_id']) : '0';
    $itinerario_id_input = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';
    error_log('PHP Handler - itinerary_id_input after sanitize: ' . $itinerario_id_input); // Loguear ID específico
    $id_a_pasar = '0';

    if (is_user_logged_in()) {
        $itinerario_id_valido = intval($itinerario_id_input);
        if ($itinerario_id_valido <= 0) {
            echo '<p class="tic-notice">Por favor, selecciona un itinerario para gestionar las actividades.</p>';
            wp_die();
        }
        $id_a_pasar = $itinerario_id_valido;
    } else {
        $id_a_pasar = ($itinerario_id_input === 'temp') ? 'temp' : '0';
        if ($id_a_pasar === '0') {
            echo '<p class="tic-notice">Error al determinar el itinerario para las actividades (invitado).</p>';
            wp_die();
        }
    }

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_activities_instance = new TIC_Activities($tic_database, $tic_security); // Usar la nueva clase

    echo $tic_activities_instance->mostrar_formulario_actividad($id_a_pasar);
    wp_die();
}
/**
 * Manejador AJAX para guardar los datos del formulario de actividades.
 */
function tic_ajax_guardar_actividad_handler()
{
    // 1. Verificar el nonce enviado desde el formulario tic-activities-form.php
    check_ajax_referer('tic_guardar_actividad_action', 'tic_activity_nonce');

    // 2. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_activities_instance = new TIC_Activities($tic_database, $tic_security);

    // 3. Llamar al método de la clase para guardar.
    // Este método se encargará de sanitizar $_POST y enviar la respuesta JSON.
    $tic_activities_instance->guardar_actividad();
}
/**
 * Manejador AJAX para mostrar el REPORTE de actividades.
 */
function tic_ajax_mostrar_reporte_actividad_handler()
{
    check_ajax_referer('tic_load_module_action_nonce', 'nonce'); // Reutilizamos nonce

    $itinerario_id_input = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';
    $id_a_pasar = '0';

    if (is_user_logged_in()) {
        $itinerario_id_valido = intval($itinerario_id_input);
        if ($itinerario_id_valido <= 0) {
            echo '<p class="tic-notice">Por favor, selecciona un itinerario para ver el reporte de actividades.</p>';
            wp_die();
        }
        $id_a_pasar = $itinerario_id_valido;
    } else {
        $id_a_pasar = ($itinerario_id_input === 'temp') ? 'temp' : '0';
        if ($id_a_pasar === '0') {
            echo '<p class="tic-notice">Error al determinar el itinerario para el reporte de actividades (invitado).</p>';
            wp_die();
        }
    }

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_activities_instance = new TIC_Activities($tic_database, $tic_security);

    echo $tic_activities_instance->mostrar_reporte_actividad($id_a_pasar);
    wp_die();
}
/**
 * Manejador AJAX para obtener los detalles de una actividad específica.
 */
function tic_ajax_get_activity_details_handler()
{
    // 1. Verificar Nonce (lo añadiremos al JS en el siguiente paso)
    check_ajax_referer('tic_get_activity_details_action', 'nonce');

    // 2. Validar y obtener el ID de la actividad
    if (!isset($_POST['activity_id']) || empty($_POST['activity_id'])) {
        wp_send_json_error(array('message' => 'No se proporcionó ID de actividad.'));
        return;
    }
    $activity_id = absint($_POST['activity_id']);

    // 3. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_activities_instance = new TIC_Activities($tic_database, $tic_security);

    // 4. Obtener los datos de la actividad
    $activity_data = $tic_activities_instance->get_activity_by_id($activity_id);

    // 5. Enviar respuesta
    if ($activity_data) {
        wp_send_json_success($activity_data);
    } else {
        wp_send_json_error(array('message' => 'Actividad no encontrada o no tienes permiso para verla.'));
    }
}
/**
 * Manejador AJAX para eliminar una actividad específica.
 */
function tic_ajax_delete_activity_handler()
{
    // 1. Verificar Nonce
    check_ajax_referer('tic_delete_activity_action', 'nonce');

    // 2. Validar y obtener el ID de la actividad
    if (!isset($_POST['activity_id']) || empty($_POST['activity_id'])) {
        wp_send_json_error(array('message' => 'No se proporcionó ID de actividad para eliminar.'));
    }
    $activity_id = absint($_POST['activity_id']);

    // 3. Instanciar clases
    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_activities_instance = new TIC_Activities($tic_database, $tic_security);

    // 4. Eliminar la actividad
    $deleted = $tic_activities_instance->delete_activity_by_id($activity_id);

    // 5. Enviar respuesta
    if ($deleted) {
        wp_send_json_success(array('message' => 'Actividad eliminada correctamente.'));
    } else {
        wp_send_json_error(array('message' => 'Error al eliminar la actividad. Verifique que el registro exista y le pertenezca.'));
    }
}
