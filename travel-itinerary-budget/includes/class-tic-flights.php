<?php

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Flights
{

    private $database;
    private $security;
    private $itineraries;

    public function __construct(TIC_Database $database, TIC_Security $security, ?TIC_Itineraries $itineraries = null)
    {
        $this->database = $database;
        $this->security = $security;
        $this->itineraries = $itineraries;
    }

    /**
     * Muestra el formulario para capturar la información de los vuelos.
     *
     * @param int $itinerario_id Opcional. El ID del itinerario para cargar vuelos existentes.
     */
    public function mostrar_formulario_vuelos($itinerario_id = 0)
    {
        $itinerario_id = $this->security->sanitize_integer($itinerario_id);
        // $vuelos_existentes = array(); // No parece usarse en la plantilla del formulario

        // if ($itinerario_id > 0) {
        //     $vuelos_existentes = $this->obtener_vuelos_db($itinerario_id);
        // }

        // Pasar la variable a la plantilla de forma más robusta para AJAX
        //set_query_var('tic_itinerario_id_form', $itinerario_id);

        error_log("TIC_Flights::mostrar_formulario_vuelos - ID recibido para el formulario: " . print_r($itinerario_id, true));

        ob_start();
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-form.php';
        
        if (file_exists($template_path)) {
            // La variable $itinerario_id (el parámetro de esta función)
            // estará automáticamente disponible para el archivo tic-flights-form.php cuando se incluya.
            include $template_path;
        } else {
            echo '<p class="tic-error">Error: Plantilla de formulario de vuelos no encontrada.</p>';
            error_log('TIC Flights Form Template NOT FOUND: ' . $template_path);
        }
        return ob_get_clean();
    }

    /**
     * Guarda la información del vuelo en la base de datos o en la sesión.
     * Ahora también maneja la actualización de vuelos existentes para usuarios registrados.
     */
    public function guardar_vuelo()
    {
        error_log("Inicio de guardar_vuelo()");
        error_log(print_r($_POST, true)); // Los datos recibidos del formulario
        // El nonce ya fue verificado por check_ajax_referer() en travel-itinerary-budget.php

        // Verificar si se está editando un vuelo existente (y el usuario está logueado)
        if (isset($_POST['editing_flight_id']) && !empty($_POST['editing_flight_id']) && is_user_logged_in()) {
            // Si editing_flight_id está presente y es válido, es una actualización.
            // La lógica de actualización estará dentro de guardar_vuelo_db.
            $this->guardar_vuelo_db($_POST);
        } elseif (is_user_logged_in()) {
            // No hay editing_flight_id, pero el usuario está logueado: es un NUEVO vuelo para la BD.
            $this->guardar_vuelo_db($_POST);
        } else {
            // Usuario no logueado: guardar en sesión (esto solo maneja NUEVOS vuelos en sesión).
            // Si quisiéramos editar vuelos de sesión, necesitaríamos un identificador diferente a editing_flight_id
            // o una lógica más compleja aquí. Por ahora, esto sigue igual.
            $this->guardar_vuelo_session($_POST);
        }
        // wp_die() se llama implícitamente por wp_send_json_success/error dentro de los métodos guardar_*
    }

    /**
     * Guarda la información del vuelo en la base de datos (INSERT o UPDATE).
     *
     * @param array $data Los datos del formulario.
     */
    private function guardar_vuelo_db($data)
    {
        // Sanitizar y validar los datos (esto ya lo tienes, asegúrate que esté completo)
        // Es importante sanitizar CADA DATO que viene de $data antes de usarlo.
        $itinerario_id = isset($data['itinerario_id']) ? $this->security->sanitize_integer($data['itinerario_id']) : 0;
        $origen = isset($data['origen']) ? $this->security->sanitize_text($data['origen']) : '';
        $destino = isset($data['destino']) ? $this->security->sanitize_text($data['destino']) : '';
        $linea_aerea = isset($data['linea_aerea']) ? $this->security->sanitize_text($data['linea_aerea']) : '';
        $numero_vuelo = isset($data['numero_vuelo']) ? $this->security->sanitize_text($data['numero_vuelo']) : '';
        $fecha_hora_salida = isset($data['fecha_hora_salida']) ? $this->security->sanitize_datetime($data['fecha_hora_salida']) : null;
        $fecha_hora_llegada = isset($data['fecha_hora_llegada']) ? $this->security->sanitize_datetime($data['fecha_hora_llegada']) : null;
        $tiene_escalas = isset($data['tiene_escalas']) ? 1 : 0; // Si el checkbox 'tiene_escalas' está presente
        $precio_persona = isset($data['precio_persona']) ? $this->security->sanitize_decimal($data['precio_persona']) : 0;
        $moneda_precio = isset($data['moneda_precio']) ? $this->security->validate_currency($data['moneda_precio']) : 'USD';
        $numero_personas = isset($data['numero_personas']) ? $this->security->sanitize_integer($data['numero_personas']) : 1;
        $tipo_de_cambio = isset($data['tipo_de_cambio']) ? $this->security->sanitize_decimal($data['tipo_de_cambio']) : 1.00;
        $codigo_reserva = isset($data['codigo_reserva']) ? $this->security->validate_reservation_code($data['codigo_reserva']) : '';

        // Calcular precio total (siempre es bueno recalcular en el servidor)
        $precio_total_vuelos = round($precio_persona * $numero_personas * $tipo_de_cambio, 2);

        // Preparar el array de datos para la BD (común para INSERT y UPDATE)
        $vuelo_data_for_db = array(
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
            'numero_personas' => $numero_personas,
            'tipo_de_cambio' => $tipo_de_cambio,
            'precio_total_vuelos' => $precio_total_vuelos,
            'codigo_reserva' => $codigo_reserva,
        );

        // Formatos para las columnas (importante para $wpdb->insert y $wpdb->update)
        $vuelo_data_formats = array(
            '%d', // itinerario_id
            '%s', // origen
            '%s', // destino
            '%s', // linea_aerea
            '%s', // numero_vuelo
            '%s', // fecha_hora_salida (formato YYYY-MM-DD HH:MM:SS)
            '%s', // fecha_hora_llegada
            '%d', // tiene_escalas
            '%f', // precio_persona
            '%s', // moneda_precio
            '%d', // numero_personas
            '%f', // tipo_de_cambio
            '%f', // precio_total_vuelos
            '%s', // codigo_reserva
        );

        $table_name = $this->database->get_table_name('vuelos');
        $result = false;
        $editing_flight_id = 0; // Inicializar

        // --- LÓGICA PARA DECIDIR SI ES INSERT O UPDATE ---
        if (isset($data['editing_flight_id']) && !empty($data['editing_flight_id'])) {
            // Es una ACTUALIZACIÓN
            $editing_flight_id = absint($data['editing_flight_id']);
            error_log("Actualizando vuelo ID: " . $editing_flight_id);

            // $result será el número de filas afectadas, o false en error.
            $result = $this->database->wpdb->update(
                $table_name,
                $vuelo_data_for_db,    // datos a actualizar
                array('id' => $editing_flight_id), // Cláusula WHERE (ID del vuelo a editar)
                $vuelo_data_formats,   // formatos para los datos a actualizar
                array('%d')            // formato para la cláusula WHERE (ID es entero)
            );
            // El ID del vuelo no cambia al actualizar
            $flight_id_for_scales = $editing_flight_id;
            $success_message = 'Vuelo actualizado correctamente.';
            $error_message = 'Error al actualizar el vuelo.';
        } else {
            // Es una INSERCIÓN (código que ya tenías)
            error_log("Insertando nuevo vuelo para itinerario ID: " . $itinerario_id);
            // $result será el número de filas insertadas (1 si éxito), o false en error.
            $result_insert = $this->database->wpdb->insert($table_name, $vuelo_data_for_db, $vuelo_data_formats);
            if ($result_insert !== false) {
                $result = true; // Marcamos como éxito para la lógica común de abajo
                $flight_id_for_scales = $this->database->wpdb->insert_id; // ID del nuevo vuelo insertado
            } else {
                $result = false;
            }
            $success_message = 'Vuelo guardado correctamente.';
            $error_message = 'Error al guardar el vuelo.';
        }
        // --- FIN LÓGICA INSERT/UPDATE ---

        // Comprobar errores de BD
        if (!empty($this->database->wpdb->last_error)) {
            error_log('Error WPDB después de guardar/actualizar vuelo: ' . $this->database->wpdb->last_error);
            // Si hubo un error explícito de $wpdb, forzamos $result a false
            if ($result !== false) { // Solo si $result no era ya false (ej. de insert)
                $result = false; // Para asegurar que se envíe wp_send_json_error
            }
        }

        if ($result !== false) { // $result es true para insert exitoso, o número de filas para update
            // Manejo de escalas: Borrar existentes (si es update) y luego insertar las nuevas.
            // Esto es más simple que intentar diferenciar qué escalas son nuevas, cuáles se borraron, cuáles cambiaron.
            if ($editing_flight_id > 0) { // Si estamos editando, borrar escalas antiguas primero
                $table_escalas = $this->database->get_table_name('vuelos_escalas');
                $this->database->wpdb->delete($table_escalas, array('vuelo_id' => $editing_flight_id), array('%d'));
            }

            // Si "tiene_escalas" está marcado y se enviaron datos de escalas, guardarlas.
            // Usar $flight_id_for_scales que es el ID del vuelo (nuevo o editado).
            if ($tiene_escalas && isset($data['escalas']) && is_array($data['escalas']) && $flight_id_for_scales > 0) {
                $this->guardar_escalas_db($flight_id_for_scales, $data['escalas']);
            }
            wp_send_json_success(array('message' => $success_message, 'flight_id' => $flight_id_for_scales));
        } else {
            wp_send_json_error(array('message' => $error_message . ' Detalles: ' . $this->database->wpdb->last_error));
        }
    }

    /**
     * Guarda la información de las escalas en la base de datos.
     *
     * @param int $vuelo_id El ID del vuelo al que pertenecen las escalas.
     * @param array $escalas_data Array de datos de las escalas.
     */
    private function guardar_escalas_db($vuelo_id, $escalas_data)
    {
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
     * Guarda la información del vuelo en la sesión para usuarios no registrados.
     * Asegura que la estructura de datos sea consistente con la guardada en BD.
     *
     * @param array $data Los datos del formulario ($_POST).
     */
    private function guardar_vuelo_session($data)
    {
        // 1. (RECOMENDADO) Sanitizar los datos ANTES de guardarlos en sesión
        //    Esto previene guardar datos potencialmente maliciosos o malformados.
        //    Puedes crear una función helper para sanitizar y evitar duplicar código con guardar_vuelo_db.
        //    Ejemplo básico de sanitización (ajusta según tu clase TIC_Security):
        $sanitized_data = array();
        $sanitized_data['itinerario_id'] = 0; // Los no registrados no tienen ID de itinerario persistente
        $sanitized_data['origen'] = isset($data['origen']) ? $this->security->sanitize_text($data['origen']) : '';
        $sanitized_data['destino'] = isset($data['destino']) ? $this->security->sanitize_text($data['destino']) : '';
        $sanitized_data['linea_aerea'] = isset($data['linea_aerea']) ? $this->security->sanitize_text($data['linea_aerea']) : '';
        $sanitized_data['numero_vuelo'] = isset($data['numero_vuelo']) ? $this->security->sanitize_text($data['numero_vuelo']) : '';
        $sanitized_data['fecha_hora_salida'] = isset($data['fecha_hora_salida']) ? $this->security->sanitize_datetime($data['fecha_hora_salida']) : null;
        $sanitized_data['fecha_hora_llegada'] = isset($data['fecha_hora_llegada']) ? $this->security->sanitize_datetime($data['fecha_hora_llegada']) : null;
        $sanitized_data['precio_persona'] = isset($data['precio_persona']) ? $this->security->sanitize_decimal($data['precio_persona']) : 0;
        $sanitized_data['moneda_precio'] = isset($data['moneda_precio']) ? $this->security->validate_currency($data['moneda_precio']) : 'USD'; // Considera un valor por defecto
        $sanitized_data['numero_personas'] = isset($data['numero_personas']) ? $this->security->sanitize_integer($data['numero_personas']) : 1; // Considera un valor por defecto
        $sanitized_data['tipo_de_cambio'] = isset($data['tipo_de_cambio']) ? $this->security->sanitize_decimal($data['tipo_de_cambio']) : 1.00; // Considera un valor por defecto
        $sanitized_data['codigo_reserva'] = isset($data['codigo_reserva']) ? $this->security->validate_reservation_code($data['codigo_reserva']) : '';

        // 2. Asegurar que 'tiene_escalas' exista (0 si no se marcó el checkbox)
        //    Usamos $data (el $_POST original) para checar si se envió la clave.
        $sanitized_data['tiene_escalas'] = isset($data['tiene_escalas']) ? 1 : 0;

        // 3. Calcular 'precio_total_vuelos' usando los datos sanitizados
        $precio_persona = $sanitized_data['precio_persona'];
        $numero_personas = $sanitized_data['numero_personas'];
        $tipo_de_cambio = $sanitized_data['tipo_de_cambio'];
        $sanitized_data['precio_total_vuelos'] = round($precio_persona * $numero_personas * $tipo_de_cambio, 2);

        // 4. (Opcional pero consistente) Procesar y guardar escalas si existen
        $sanitized_escalas = array();
        if ($sanitized_data['tiene_escalas'] === 1 && isset($data['escalas']) && is_array($data['escalas'])) {
            $orden = 1;
            foreach ($data['escalas'] as $escala_input) {
                // Validar que la escala no esté vacía y sanitizar
                $aeropuerto = isset($escala_input['aeropuerto']) ? $this->security->sanitize_text($escala_input['aeropuerto']) : '';
                if (!empty($aeropuerto)) {
                    $sanitized_escalas[] = array(
                        // No hay vuelo_id persistente para sesión, usamos solo orden
                        'orden' => $orden++,
                        'aeropuerto' => $aeropuerto,
                        'fecha_hora_llegada' => isset($escala_input['llegada']) ? $this->security->sanitize_datetime($escala_input['llegada']) : null,
                        'fecha_hora_salida' => isset($escala_input['salida']) ? $this->security->sanitize_datetime($escala_input['salida']) : null,
                    );
                }
            }
        }
        // Añadir el array de escalas (sanitizado) a los datos del vuelo principal
        $sanitized_data['escalas'] = $sanitized_escalas;


        // 5. Inicializar el array de sesión si es la primera vez
        if (!isset($_SESSION['tic_vuelos']) || !is_array($_SESSION['tic_vuelos'])) {
            $_SESSION['tic_vuelos'] = array();
        }

        // 6. Guardar el array $sanitized_data (completo y limpio) en la sesión
        $_SESSION['tic_vuelos'][] = $sanitized_data;

        // 7. Enviar respuesta JSON de éxito (esto incluye wp_die())
        wp_send_json_success(array('message' => 'Vuelo guardado en la sesión.'));
    }

    /**
     * Muestra el reporte de vuelos para usuarios registrados (desde la base de datos).
     *
     * @param int $itinerario_id El ID del itinerario.
     */
    public function mostrar_reporte_vuelos_db($itinerario_id)
    {
        $table_name_vuelos = $this->database->get_table_name('vuelos');
        $table_name_escalas = $this->database->get_table_name('vuelos_escalas');
        $table_name_itinerarios = $this->database->get_table_name('itinerarios'); // Necesitamos el nombre de la tabla de itinerarios

        // --- INICIO MODIFICACIÓN ---
        // Obtener el nombre Y LA MONEDA del itinerario actual
        $itinerario_info = $this->database->wpdb->get_row(
            $this->database->wpdb->prepare(
                "SELECT nombre_itinerario, moneda_reporte FROM {$table_name_itinerarios} WHERE id = %d AND user_id = %d",
                $itinerario_id,
                get_current_user_id()
            ),
            ARRAY_A // Obtener como array asociativo
        );

        $itinerary_name_for_report = '';
        $active_itinerary_report_currency = 'USD'; // Moneda por defecto si no se encuentra

        if ($itinerario_info) {
            $itinerary_name_for_report = $itinerario_info['nombre_itinerario'];
            $active_itinerary_report_currency = $itinerario_info['moneda_reporte'];
        } else {
            error_log("TIC Vuelos Reporte: No se encontró info para itinerario ID: " . $itinerario_id . " para el usuario actual.");
        }
        // --- FIN MODIFICACIÓN ---

        $vuelos = $this->database->wpdb->get_results(
            $this->database->wpdb->prepare(
                "SELECT v.*,
                        GROUP_CONCAT(e.aeropuerto ORDER BY e.orden SEPARATOR ' -> ') AS escalas_aeropuertos,
                        GROUP_CONCAT(DATE_FORMAT(e.fecha_hora_llegada, '%%Y-%%m-%%d %%H:%%i') ORDER BY e.orden SEPARATOR ' -> ') AS escalas_llegadas, -- Escapar % para prepare
                        GROUP_CONCAT(DATE_FORMAT(e.fecha_hora_salida, '%%Y-%%m-%%d %%H:%%i') ORDER BY e.orden SEPARATOR ' -> ') AS escalas_salidas -- Escapar % para prepare
                    FROM {$table_name_vuelos} v
                    LEFT JOIN {$table_name_escalas} e ON v.id = e.vuelo_id
                    WHERE v.itinerario_id = %d -- Este es el único marcador real
                    GROUP BY v.id
                    ORDER BY v.fecha_hora_salida ASC", // Añadí un ORDER BY opcional
                $itinerario_id
            ),
            OBJECT // Asegurémonos que devuelve objetos, aunque sea el default
        );
        //include plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-report.php';
        // Incluir la plantilla. Las variables $vuelos y $itinerary_name_for_report estarán disponibles dentro.
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-report.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Manejo de error si la plantilla no existe
            echo '<p class="tic-error">Error: Plantilla de reporte de vuelos no encontrada.</p>';
            error_log('TIC Error: Plantilla de reporte de vuelos no encontrada en ' . $template_path);
        }
        // No necesitamos devolver nada si hacemos include aquí directamente.
        // La función que llama a esta (mostrar_reporte_vuelos) ya usa ob_start/ob_get_clean.

    }

    /**
     * Muestra el reporte de vuelos para usuarios no registrados (desde la sesión).
     * Convierte los arrays de la sesión en objetos para que coincidan con el formato esperado por la plantilla.
     */
    public function mostrar_reporte_vuelos_session()
    {
        // Obtener el array de arrays desde la sesión
        $vuelos_arrays = isset($_SESSION['tic_vuelos']) ? $_SESSION['tic_vuelos'] : array();

        // Crear un array para guardar los objetos convertidos
        $vuelos_objects = array();

        // Iterar sobre cada array de vuelo y convertirlo a objeto (stdClass)
        foreach ($vuelos_arrays as $vuelo_array) {
            $vuelos_objects[] = (object) $vuelo_array; // Casting a objeto
        }

        // **** IMPORTANTE: Asegúrate de que la plantilla espera la variable $vuelos ****
        // Si tu plantilla usa una variable con otro nombre (ej: $flights), cambia la línea siguiente:
        // Ejemplo: $flights = $vuelos_objects;

        // Pasar el array de OBJETOS a la plantilla
        $vuelos = $vuelos_objects; // <--- Asigna el array de objetos a la variable que espera la plantilla

        // Incluir la plantilla (que ahora recibirá objetos consistentemente)
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-flights-report.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>Error: No se encontró la plantilla del reporte de vuelos.</p>';
            error_log('Error: Plantilla no encontrada en ' . $template_path);
        }
    }

    /**
     * Obtiene los vuelos de un itinerario para usuarios registrados.
     *
     * @param int $itinerario_id El ID del itinerario.
     * @return array|null Array de objetos con la información de los vuelos.
     */
    public function obtener_vuelos_db($itinerario_id)
    {
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
    public function obtener_vuelos_session()
    {
        return isset($_SESSION['tic_vuelos']) ? $_SESSION['tic_vuelos'] : array();
    }

    /**
     * Muestra el reporte de vuelos (función principal llamada por el shortcode).
     * Determina si mostrar el reporte desde la base de datos o la sesión.
     *
     * @param array $atts Atributos del shortcode (puede incluir el ID del itinerario).
     */
    public function mostrar_reporte_vuelos($atts)
    {
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
    /**
     * Obtiene un vuelo específico y sus escalas por su ID.
     *
     * @param int $flight_id El ID del vuelo.
     * @return array|null Los datos del vuelo como array asociativo, o null si no se encuentra.
     */
    public function get_flight_by_id($flight_id)
    {
        $flight_id = absint($flight_id); // Sanitizar el ID

        if (!$flight_id) {
            return null;
        }

        // Obtener datos principales del vuelo
        $table_vuelos = $this->database->get_table_name('vuelos');
        $flight_data = $this->database->wpdb->get_row(
            $this->database->wpdb->prepare("SELECT * FROM {$table_vuelos} WHERE id = %d", $flight_id),
            ARRAY_A // Devolver como array asociativo
        );

        if (!$flight_data) {
            return null; // Vuelo no encontrado
        }

        // Obtener escalas si el vuelo las tiene
        if (!empty($flight_data['tiene_escalas'])) {
            $table_vuelos_escalas = $this->database->get_table_name('vuelos_escalas');
            $flight_data['escalas'] = $this->database->wpdb->get_results(
                $this->database->wpdb->prepare(
                    "SELECT aeropuerto, fecha_hora_llegada, fecha_hora_salida, orden FROM {$table_vuelos_escalas} WHERE vuelo_id = %d ORDER BY orden ASC",
                    $flight_id
                ),
                ARRAY_A // Devolver escalas como array de arrays asociativos
            );
        } else {
            $flight_data['escalas'] = array(); // Asegurar que 'escalas' exista, aunque esté vacío
        }

        // Convertir campos numéricos que vienen como string de la BD a tipos correctos si es necesario para JS.
        // Ej: precio_persona, numero_personas, tipo_de_cambio, precio_total_vuelos
        // WordPress y get_row(ARRAY_A) suelen devolverlos como strings.
        // Para la mayoría de los usos en JS no es estrictamente necesario, pero es buena práctica.
        $numeric_fields = ['precio_persona', 'numero_personas', 'tipo_de_cambio', 'precio_total_vuelos'];
        foreach ($numeric_fields as $field) {
            if (isset($flight_data[$field])) {
                $flight_data[$field] = (float) $flight_data[$field];
            }
        }
        if (isset($flight_data['tiene_escalas'])) {
            $flight_data['tiene_escalas'] = (bool) $flight_data['tiene_escalas']; // Convertir a booleano
        }


        return $flight_data;
    }

    /**
     * Elimina un vuelo específico y sus escalas asociadas de la base de datos.
     *
     * @param int $flight_id El ID del vuelo a eliminar.
     * @return bool True si la eliminación fue exitosa (o si no había nada que eliminar), False en caso de error.
     */
    public function delete_flight_and_scales($flight_id)
    {
        $flight_id = absint($flight_id); // Sanitizar el ID

        if (!$flight_id) {
            error_log('TIC Error: Intento de eliminar vuelo con ID inválido o cero.');
            return false;
        }

        $table_vuelos_escalas = $this->database->get_table_name('vuelos_escalas');
        $table_vuelos = $this->database->get_table_name('vuelos');

        // Opcional: Iniciar una transacción si tu motor de BD lo soporta (InnoDB lo hace)
        // $this->database->wpdb->query('START TRANSACTION');

        // 1. Eliminar las escalas asociadas (si las hay)
        // $wpdb->delete devuelve el número de filas afectadas o false en error.
        $escalas_deleted_result = $this->database->wpdb->delete(
            $table_vuelos_escalas,
            array('vuelo_id' => $flight_id),
            array('%d') // Formato para la cláusula WHERE
        );

        // Si $escalas_deleted_result es false, hubo un error en la consulta de eliminación de escalas.
        if ($escalas_deleted_result === false) {
            error_log("TIC Error: Falló la eliminación de escalas para el vuelo ID {$flight_id}. Error DB: " . $this->database->wpdb->last_error);
            // if ($this->database->wpdb->last_error) { $this->database->wpdb->query('ROLLBACK'); } // Si usas transacciones
            return false;
        }
        error_log("TIC Info: Escalas procesadas para eliminar para vuelo ID {$flight_id}. Filas afectadas/intentadas: " . $escalas_deleted_result);

        // 2. Eliminar el vuelo principal
        // $wpdb->delete devuelve el número de filas eliminadas o false en error.
        $vuelo_deleted_result = $this->database->wpdb->delete(
            $table_vuelos,
            array('id' => $flight_id),
            array('%d') // Formato para la cláusula WHERE
        );

        if ($vuelo_deleted_result === false) {
            error_log("TIC Error: Falló la eliminación del vuelo ID {$flight_id}. Error DB: " . $this->database->wpdb->last_error);
            // if ($this->database->wpdb->last_error) { $this->database->wpdb->query('ROLLBACK'); } // Si usas transacciones
            return false;
        } elseif ($vuelo_deleted_result === 0) {
            // Esto significa que el vuelo no fue encontrado para eliminar.
            // Puede que ya haya sido eliminado o el ID era incorrecto,
            // pero la operación de "eliminar" no falló en sí misma.
            error_log("TIC Warning: Se intentó eliminar el vuelo ID {$flight_id}, pero no se encontró o ya estaba eliminado (0 filas afectadas).");
            // Consideramos esto un "éxito" en el sentido de que el vuelo ya no está.
        } else {
            error_log("TIC Info: Vuelo ID {$flight_id} eliminado. Filas afectadas: " . $vuelo_deleted_result);
        }

        // if (!$this->database->wpdb->last_error) { $this->database->wpdb->query('COMMIT'); } // Si usas transacciones
        return true; // Éxito
    }
}
