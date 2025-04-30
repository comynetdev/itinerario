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
//require_once plugin_dir_path(__FILE__) . 'includes/class-tic-accommodation.php';

// Función para crear las tablas de la base de datos
function tic_create_tables() {
    $tic_database = new TIC_Database();
    $tic_database->create_tables();
}

// Función para verificar y crear las tablas (llamada solo en la activación)
function tic_install() {
    tic_create_tables(); // Llamar a la función de creación de tablas

    // Actualizar la versión del plugin
    update_option('tic_plugin_version', '1.0.0');
}
register_activation_hook(__FILE__, 'tic_install');

// Funciones para manejar usuarios no registrados (sesiones)
function tic_init_session() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'tic_init_session', 1);

function tic_enqueue_assets() {
    wp_enqueue_style('tic-styles', plugin_dir_url(__FILE__) . 'assets/css/tic-styles.css');

    // wp_enqueue_script('tic-main-js', plugin_dir_url(__FILE__) . 'assets/js/tic-main.js', array('jquery'), '1.0', true);
    // wp_localize_script('tic-main-js', 'tic_ajax_object', array('ajaxurl' => admin_url('admin-ajax.php'))); // No modificar si no existe

    wp_enqueue_script('tic-flights-js', plugin_dir_url(__FILE__) . 'assets/js/tic-flights.js', array('jquery'), '1.0', true); // Corregir el handle si es necesario
    wp_localize_script('tic-flights-js', 'tic_ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));

    // wp_enqueue_script('tic-accommodation-js', plugin_dir_url(__FILE__) . 'assets/js/tic-main.js', array('jquery', 'tic-main-js'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'tic_enqueue_assets');

// Función para mostrar el dashboard (Shortcode)
function tic_mostrar_dashboard() {
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

function tic_ajax_create_itinerary() {
    // Verificar el nonce primero
    check_ajax_referer('tic_create_itinerary_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_itineraries = new TIC_Itineraries($tic_database, $tic_security);
    $tic_itineraries->crear_itinerario();
}

// Funciones para manejar el formulario de vuelos
add_action('wp_ajax_tic_guardar_vuelo', function(...$args) {
    // Verificar nonce primero
    check_ajax_referer('tic_guardar_vuelo_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);
    $tic_flights->guardar_vuelo();
});
add_action('wp_ajax_nopriv_tic_guardar_vuelo', function(...$args) {
    // Verificar nonce primero
    check_ajax_referer('tic_guardar_vuelo_nonce', 'nonce');

    $tic_database = new TIC_Database();
    $tic_security = new TIC_Security();
    $tic_flights = new TIC_Flights($tic_database, $tic_security);
    $tic_flights->guardar_vuelo();
});
add_action('wp_ajax_tic_mostrar_formulario_vuelos', function() { // No necesitamos ...$args aquí
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
add_action('wp_ajax_nopriv_tic_mostrar_formulario_vuelos', function() {
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

function tic_mostrar_reporte_vuelos_ajax() {
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