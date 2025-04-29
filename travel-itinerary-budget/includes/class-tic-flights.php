<?php

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Flights {

    private $database;
    private $security;
    private $itineraries;

    public function __construct(TIC_Database $database, TIC_Security $security, ?TIC_Itineraries $itineraries = null) {
        $this->database = $database;
        $this->security = $security;
        $this->itineraries = $itineraries;
    }

    /**
     * Muestra el formulario para capturar la información de los vuelos.
     *
     * @param int $itinerario_id Opcional. El ID del itinerario para cargar vuelos existentes.
     */
    public function mostrar_formulario_vuelos($itinerario_id = 0) {
        $itinerario_id = $this->security->sanitize_integer($itinerario_id);
        // $vuelos_existentes = array(); // No parece usarse en la plantilla del formulario

        // if ($itinerario_id > 0) {
        //     $vuelos_existentes = $this->obtener_vuelos_db($itinerario_id);
        // }

        // Pasar la variable a la plantilla de forma más robusta para AJAX
        set_query_var('tic_itinerario_id_form', $itinerario_id);

        ob_start();
        // Usar include en lugar de include_once puede ser más seguro si se llama múltiples veces
        include plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-form.php';
        // Limpiar la variable para evitar conflictos
        set_query_var('tic_itinerario_id_form', null);
        return ob_get_clean(); // Devolver el HTML capturado
    }

    /**
     * Guarda la información del vuelo en la base de datos o en la sesión.
     */
    public function guardar_vuelo() {
        error_log("Inicio de guardar_vuelo()"); // Imprime un mensaje para saber que se llamó la función
        error_log(print_r($_POST, true)); // Imprime los datos recibidos
        // El nonce ya fue verificado por check_ajax_referer() en travel-itinerary-budget.php

        if (is_user_logged_in()) {
            // Guardar en la base de datos para usuarios registrados
            $this->guardar_vuelo_db($_POST);
        } else {
            // Guardar en la sesión para usuarios no registrados
            $this->guardar_vuelo_session($_POST);
        }
        // wp_die() se llama implícitamente por wp_send_json_success/error dentro de los métodos guardar_*
    }

    /**
     * Guarda la información del vuelo en la base de datos.
     *
     * @param array $data Los datos del formulario.
     */
    private function guardar_vuelo_db($data) {
        // Sanitizar y validar los datos antes de guardar
        $itinerario_id = $this->security->sanitize_integer($data['itinerario_id']);
        $origen = $this->security->sanitize_text($data['origen']);
        $destino = $this->security->sanitize_text($data['destino']);
        $linea_aerea = $this->security->sanitize_text($data['linea_aerea']);
        $numero_vuelo = $this->security->sanitize_text($data['numero_vuelo']);
        $fecha_hora_salida = $this->security->sanitize_datetime($data['fecha_hora_salida']);
        $fecha_hora_llegada = $this->security->sanitize_datetime($data['fecha_hora_llegada']);
        $tiene_escalas = isset($data['tiene_escalas']) ? 1 : 0;
        $precio_persona = $this->security->sanitize_decimal($data['precio_persona']);
        $moneda_precio = $this->security->validate_currency($data['moneda_precio']);
        $moneda_usuario = $this->security->validate_currency($data['moneda_usuario']);
        $numero_personas = $this->security->sanitize_integer($data['numero_personas']);
        $tipo_de_cambio = $this->security->sanitize_decimal($data['tipo_de_cambio']);
        $codigo_reserva = $this->security->validate_reservation_code($data['codigo_reserva']);

        $precio_total_vuelos = $precio_persona * $numero_personas * $tipo_de_cambio;
        $precio_total_vuelos = round($precio_total_vuelos, 2); // Redondear a 2 decimales

        $vuelo_data = array(
            'itinerario_id' => $itinerario_id,
            'origen' => $origen,
            'destino' => $destino,
            'linea_aerea' => $linea_aerea,
            'numero_vuelo' => $numero_vuelo,
            'fecha_hora_salida' => $fecha_hora_salida,
            'fecha_hora_llegada' => $fecha_hora_llegada,
            'tiene_escalas' => $tiene_escalas,
            'precio_persona' => $precio_persona,
            'moneda_precio' => $moneda_precio,
            'moneda_usuario' => $moneda_usuario,
            'numero_personas' => $numero_personas,
            'tipo_de_cambio' => $tipo_de_cambio,
            'precio_total_vuelos' => $precio_total_vuelos,
            'codigo_reserva' => $codigo_reserva,
        );

        $table_name = $this->database->get_table_name('vuelos');
        $result = $this->database->wpdb->insert($table_name, $vuelo_data);

        // Comprobar errores de BD inmediatamente después de insertar
        if (!empty($this->database->wpdb->last_error)) {
            error_log('Error WPDB después de insertar vuelo: ' . $this->database->wpdb->last_error);
        }

        if ($result !== false) { // Comprobar contra false, ya que insert devuelve num filas o false en error
            $vuelo_id = $this->database->wpdb->insert_id;
            if ($tiene_escalas && isset($data['escalas']) && is_array($data['escalas'])) {
                 // error_log("Llamando a guardar_escalas_db para vuelo ID: " . $vuelo_id); // Log opcional
                 $this->guardar_escalas_db($vuelo_id, $data['escalas']);
            }
            // No mostrar el reporte aquí, la actualización se maneja en JS
            wp_send_json_success(array('message' => 'Vuelo guardado correctamente.'));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar el vuelo.'));
        }
    }

    /**
     * Guarda la información de las escalas en la base de datos.
     *
     * @param int $vuelo_id El ID del vuelo al que pertenecen las escalas.
     * @param array $escalas_data Array de datos de las escalas.
     */
    private function guardar_escalas_db($vuelo_id, $escalas_data) {
        $table_name = $this->database->get_table_name('vuelos_escalas');
        $orden = 1;
        foreach ($escalas_data as $escala) {
            $aeropuerto = $this->security->sanitize_text($escala['aeropuerto']);
            $fecha_hora_llegada = $this->security->sanitize_datetime($escala['llegada']);
            $fecha_hora_salida = $this->security->sanitize_datetime($escala['salida']);

            if (!empty($aeropuerto)) {
                $escala_data = array(
                    'vuelo_id' => $vuelo_id,
                    'orden' => $orden++,
                    'aeropuerto' => $aeropuerto,
                    'fecha_hora_llegada' => $fecha_hora_llegada,
                    'fecha_hora_salida' => $fecha_hora_salida,
                );
                $this->database->wpdb->insert($table_name, $escala_data);
            }
        }
    }

    /**
     * Guarda la información del vuelo en la sesión.
     *
     * @param array $data Los datos del formulario.
     */
    private function guardar_vuelo_session($data) {
        // Implementar la lógica para guardar en la sesión
        $_SESSION['tic_vuelos'][] = $data; // Esto es un ejemplo básico, necesitarás una estructura más robusta
        // No mostrar el reporte aquí, la actualización se maneja en JS
        wp_send_json_success(array('message' => 'Vuelo guardado en la sesión.'));
    }

    /**
     * Muestra el reporte de vuelos para usuarios registrados (desde la base de datos).
     *
     * @param int $itinerario_id El ID del itinerario.
     */
    public function mostrar_reporte_vuelos_db($itinerario_id) {
        $table_name_vuelos = $this->database->get_table_name('vuelos');
        $table_name_escalas = $this->database->get_table_name('vuelos_escalas');
        // Los error_log de depuración ya no son necesarios aquí
    
        $vuelos = $this->database->wpdb->get_results(
            $this->database->wpdb->prepare(
                "SELECT v.*,
                        GROUP_CONCAT(e.aeropuerto ORDER BY e.orden SEPARATOR ' -> ') AS escalas_aeropuertos,
                        GROUP_CONCAT(DATE_FORMAT(e.fecha_hora_llegada, '%%Y-%%m-%%d %%H:%%i') ORDER BY e.orden SEPARATOR ' -> ') AS escalas_llegadas, -- Escapar % para prepare
                        GROUP_CONCAT(DATE_FORMAT(e.fecha_hora_salida, '%%Y-%%m-%%d %%H:%%i') ORDER BY e.orden SEPARATOR ' -> ') AS escalas_salidas -- Escapar % para prepare
                 FROM {$table_name_vuelos} v
                 LEFT JOIN {$table_name_escalas} e ON v.id = e.vuelo_id
                 WHERE v.itinerario_id = %d -- Este es el único marcador real
                 GROUP BY v.id",
                $itinerario_id
            )
        );
        include plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-report.php';
    }

    /**
     * Muestra el reporte de vuelos para usuarios no registrados (desde la sesión).
     */
    public function mostrar_reporte_vuelos_session() {
        $vuelos = isset($_SESSION['tic_vuelos']) ? $_SESSION['tic_vuelos'] : array();
        include plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-report.php';
    }

    /**
     * Obtiene los vuelos de un itinerario para usuarios registrados.
     *
     * @param int $itinerario_id El ID del itinerario.
     * @return array|null Array de objetos con la información de los vuelos.
     */
    public function obtener_vuelos_db($itinerario_id) {
        $table_name = $this->database->get_table_name('vuelos');
        error_log('Llamando a prepare con la siguiente pila de llamadas:');
error_log(print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true));
        return $this->database->wpdb->get_results(
            $this->database->wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE itinerario_id = %d",
                $itinerario_id
            )
        );
    }

    /**
     * Obtiene los vuelos de la sesión para usuarios no registrados.
     *
     * @return array Array con la información de los vuelos en la sesión.
     */
    public function obtener_vuelos_session() {
        return isset($_SESSION['tic_vuelos']) ? $_SESSION['tic_vuelos'] : array();
    }

    /**
     * Muestra el reporte de vuelos (función principal llamada por el shortcode).
     * Determina si mostrar el reporte desde la base de datos o la sesión.
     *
     * @param array $atts Atributos del shortcode (puede incluir el ID del itinerario).
     */
    public function mostrar_reporte_vuelos($atts) {
        $atts = shortcode_atts(array(
            'itinerario_id' => 0,
        ), $atts);
        $itinerario_id = $this->security->sanitize_integer($atts['itinerario_id']);
    
        ob_start(); // Iniciar el buffer de salida
        if (is_user_logged_in() && $itinerario_id > 0) {
            $this->mostrar_reporte_vuelos_db($itinerario_id);
        } elseif (!is_user_logged_in()) {
            $this->mostrar_reporte_vuelos_session();
        } else {
            echo '<p>No se ha seleccionado un itinerario.</p>';
        }
        return ob_get_clean(); // Devolver el contenido del buffer
    } 

}