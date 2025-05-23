<?php
/**
 * Clase TIC_Accommodation
 * Maneja la lógica para el módulo de alojamiento.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Accommodation {

    private $database;
    private $security;
    // Podrías necesitar $itineraries si interactúas con la lógica de itinerarios directamente

    public function __construct(TIC_Database $database, TIC_Security $security) {
        $this->database = $database;
        $this->security = $security;
    }

    /**
     * Muestra el formulario para capturar la información de alojamiento.
     * Es llamado vía AJAX.
     *
     * @param int $itinerario_id El ID del itinerario actual.
     * @return string HTML del formulario.
     */
    public function mostrar_formulario_alojamiento($itinerario_id = 0) {
        // Lógica para sanitizar itinerario_id
        if (is_user_logged_in()) {
            $itinerario_id = $this->security->sanitize_integer($itinerario_id);
            if ($itinerario_id <= 0) {
                return '<p class="tic-notice">Por favor, selecciona un itinerario válido.</p>';
            }
        } else {
            // Para no logueados, el $itinerario_id podría ser 'temp' o un identificador de sesión
            $itinerario_id = ($itinerario_id === 'temp') ? 'temp' : 0; // Ajustar según tu lógica
        }

        // Pasar el ID del itinerario a la plantilla del formulario
        set_query_var('tic_itinerario_id_form_alojamiento', $itinerario_id);
        // Considera también pasar un nonce para el guardado del formulario

        ob_start();
        // Necesitaremos crear este archivo de plantilla: templates/tic-accommodation-form.php
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-accommodation-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p class="tic-error">Error: Plantilla de formulario de alojamiento no encontrada.</p>';
        }
        set_query_var('tic_itinerario_id_form_alojamiento', null); // Limpiar
        return ob_get_clean();
    }

    public function guardar_alojamiento() {
        error_log('--- TIC_Accommodation::guardar_alojamiento INICIO ---');
        error_log('Raw $_POST para guardar alojamiento: ' . print_r($_POST, true));

        // 1. Sanitizar todos los datos esperados del formulario
        $itinerario_id_str = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';
        $editing_accommodation_id = isset($_POST['editing_accommodation_id']) && !empty($_POST['editing_accommodation_id']) ? absint($_POST['editing_accommodation_id']) : 0;

        $pais = isset($_POST['pais']) ? $this->security->sanitize_text($_POST['pais']) : '';
        $ciudad_poblacion = isset($_POST['ciudad_poblacion']) ? $this->security->sanitize_text($_POST['ciudad_poblacion']) : '';
        $hotel_hospedaje = isset($_POST['hotel_hospedaje']) ? $this->security->sanitize_text($_POST['hotel_hospedaje']) : '';
        $direccion_hotel = isset($_POST['direccion_hotel']) ? sanitize_textarea_field($_POST['direccion_hotel']) : '';
        $fecha_entrada = isset($_POST['fecha_entrada']) ? $this->security->sanitize_datetime($_POST['fecha_entrada']) : null;
        $fecha_salida = isset($_POST['fecha_salida']) ? $this->security->sanitize_datetime($_POST['fecha_salida']) : null;
        $precio_noche = isset($_POST['precio_noche']) ? $this->security->sanitize_decimal($_POST['precio_noche']) : 0;

        // --- INICIO CAMBIOS PARA NUEVOS CAMPOS DE MONEDA Y TIPO DE CAMBIO ---
        // El campo 'moneda' del formulario ahora será 'moneda_precio_noche'
        $moneda_precio_noche = isset($_POST['moneda_precio_noche']) ? $this->security->validate_currency($_POST['moneda_precio_noche']) : 'USD';
        $tipo_de_cambio_alojamiento = isset($_POST['tipo_de_cambio_alojamiento']) ? $this->security->sanitize_decimal($_POST['tipo_de_cambio_alojamiento']) : 1.0000;
        // Asegurarse de que el tipo de cambio no sea cero si se va a usar para multiplicar
        if (floatval($tipo_de_cambio_alojamiento) == 0) {
            $tipo_de_cambio_alojamiento = 1.0000; // Evitar división por cero o multiplicación por cero no deseada
        }
        // --- FIN CAMBIOS PARA NUEVOS CAMPOS DE MONEDA Y TIPO DE CAMBIO ---

        $fecha_pago_reserva_raw = isset($_POST['fecha_pago_reserva']) ? trim($_POST['fecha_pago_reserva']) : '';
        $fecha_pago_reserva = null;
        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_pago_reserva_raw)) {
            $d = DateTime::createFromFormat('Y-m-d', $fecha_pago_reserva_raw);
            if ($d && $d->format('Y-m-d') === $fecha_pago_reserva_raw) {
                $fecha_pago_reserva = $fecha_pago_reserva_raw;
            }
        }
        $codigo_reserva = isset($_POST['codigo_reserva']) ? $this->security->sanitize_text($_POST['codigo_reserva']) : '';
        $aplicacion_pago_reserva = isset($_POST['aplicacion_pago_reserva']) ? $this->security->sanitize_text($_POST['aplicacion_pago_reserva']) : '';

        // 2. Validaciones básicas
        if (empty($hotel_hospedaje) || empty($fecha_entrada) || empty($fecha_salida) || floatval($precio_noche) <= 0) {
            wp_send_json_error(array('message' => 'Por favor, completa los campos obligatorios (Hotel, Fechas, Precio por Noche mayor a 0) correctamente.'));
            return; 
        }

        // 3. Calcular campos derivados (número de noches y precio total)
        $num_noches = 0;
        $precio_total_alojamiento = 0;
        if ($fecha_entrada && $fecha_salida) {
            try {
                $entrada_dt_date_only = new DateTime(substr($fecha_entrada, 0, 10));
                $salida_dt_date_only = new DateTime(substr($fecha_salida, 0, 10));
                if ($salida_dt_date_only > $entrada_dt_date_only) {
                    $diferencia = $entrada_dt_date_only->diff($salida_dt_date_only);
                    $num_noches = $diferencia->days;
                    if ($num_noches > 0 && $precio_noche > 0) {
                        // --- ACTUALIZAR CÁLCULO DE PRECIO TOTAL ---
                        $precio_total_alojamiento = round(($num_noches * $precio_noche) * $tipo_de_cambio_alojamiento, 2);
                    }
                }
            } catch (Exception $e) { /* error_log... */ }
        }
        error_log("Calculado Alojamiento - Noches: {$num_noches}, Precio Noche: {$precio_noche}, Tipo Cambio: {$tipo_de_cambio_alojamiento}, Precio Total: {$precio_total_alojamiento}");

        // 4. Preparar datos y formatos
        $accommodation_data = array(); 
        $data_formats = array();     

        if (is_user_logged_in()) {
            $itinerario_id = $this->security->sanitize_integer($itinerario_id_str);
            if ($itinerario_id <= 0 && $editing_accommodation_id == 0) { // Solo requerir itinerario_id para nuevos registros
                wp_send_json_error(array('message' => 'ID de itinerario inválido para guardar el nuevo alojamiento.'));
                return;
            }
            if ($itinerario_id > 0) { // Solo añadir si es válido, para updates puede no necesitar cambiar itinerario_id
                $accommodation_data['itinerario_id'] = $itinerario_id;
                $data_formats[] = '%d'; 
            }
        }

        $accommodation_data['pais'] = $pais; $data_formats[] = '%s';
        $accommodation_data['ciudad_poblacion'] = $ciudad_poblacion; $data_formats[] = '%s';
        $accommodation_data['hotel_hospedaje'] = $hotel_hospedaje; $data_formats[] = '%s';
        $accommodation_data['direccion_hotel'] = $direccion_hotel; $data_formats[] = '%s';
        $accommodation_data['fecha_entrada'] = $fecha_entrada;   $data_formats[] = '%s';
        $accommodation_data['fecha_salida'] = $fecha_salida;    $data_formats[] = '%s';
        $accommodation_data['precio_noche'] = $precio_noche;      $data_formats[] = '%f';

        // --- USAR NUEVOS NOMBRES DE CAMPO ---
        $accommodation_data['moneda_precio_noche'] = $moneda_precio_noche; $data_formats[] = '%s';
        $accommodation_data['tipo_de_cambio_alojamiento'] = $tipo_de_cambio_alojamiento; $data_formats[] = '%f';
        // --- FIN USAR NUEVOS NOMBRES DE CAMPO ---

        $accommodation_data['numero_noches'] = $num_noches;         $data_formats[] = '%d';
        $accommodation_data['precio_total_alojamiento'] = $precio_total_alojamiento; $data_formats[] = '%f';
        $accommodation_data['fecha_pago_reserva'] = $fecha_pago_reserva; $data_formats[] = '%s';
        $accommodation_data['codigo_reserva'] = $codigo_reserva;   $data_formats[] = '%s';
        $accommodation_data['aplicacion_pago_reserva'] = $aplicacion_pago_reserva; $data_formats[] = '%s';

        error_log('Array $accommodation_data para DB/Sesión: ' . print_r($accommodation_data, true));
        if (is_user_logged_in()) {
            error_log('Array $data_formats para $wpdb->insert/update: ' . print_r($data_formats, true));
        }

        // 5. Guardar en BD o Sesión
        if (is_user_logged_in()) {
            $table_name = $this->database->get_table_name('alojamientos');
            $result = false;

            // Asegurarse de que el número de elementos en data y formats coincida
            // Esta comprobación debe hacerse con cuidado si itinerario_id es opcional en $accommodation_data para updates
            if ($editing_accommodation_id > 0) {
                // Para UPDATE, $accommodation_data puede no incluir itinerario_id si no se permite cambiarlo.
                // Reconstruir $data_formats para UPDATE si la estructura de $accommodation_data es diferente.
                // Por ahora, asumimos que $accommodation_data tiene todos los campos que $data_formats espera,
                // O que $data_formats se ajusta dinámicamente.
                // La forma más simple es que $accommodation_data y $data_formats siempre incluyan todos los campos
                // y que itinerario_id no se actualice si no queremos (no incluyéndolo en el array de datos para update).

                // Si no queremos actualizar itinerario_id en el UPDATE:
                $update_data = $accommodation_data;
                $update_formats = $data_formats;
                if (isset($update_data['itinerario_id'])) {
                    // Si no permitimos cambiar el itinerario_id de un alojamiento existente, lo quitamos del update
                    // unset($update_data['itinerario_id']);
                    // array_shift($update_formats); // Quitar el primer formato (%d para itinerario_id)
                    // Pero es más simple mantenerlo si el valor es el mismo.
                }
                if (count($update_data) !== count($update_formats)) {
                    error_log('CRITICAL ERROR UPDATE: Mismatch $update_data (' . count($update_data) . ') vs $update_formats (' . count($update_formats) . ')');
                    wp_send_json_error(array('message' => 'Error interno del servidor (data format mismatch update alojamiento).'));
                    return;
                }

                error_log("Actualizando alojamiento ID: " . $editing_accommodation_id);
                $result = $this->database->wpdb->update($table_name, $update_data, array('id' => $editing_accommodation_id), $update_formats, array('%d'));
                $success_message = 'Alojamiento actualizado correctamente.';
                $error_message_context = 'actualizar';
            } else { // Es una INSERCIÓN
                if (count($accommodation_data) !== count($data_formats)) { // Asegurar que itinerario_id esté para nuevos
                    error_log('CRITICAL ERROR INSERT: Mismatch $accommodation_data (' . count($accommodation_data) . ') vs $data_formats (' . count($data_formats) . ')');
                    wp_send_json_error(array('message' => 'Error interno del servidor (data format mismatch insert alojamiento).'));
                    return;
                }
                error_log("Insertando nuevo alojamiento");
                $result = $this->database->wpdb->insert($table_name, $accommodation_data, $data_formats);
                if ($result) {
                    $editing_accommodation_id = $this->database->wpdb->insert_id;
                }
                $success_message = 'Alojamiento guardado correctamente.';
                $error_message_context = 'guardar';
            }

            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => $success_message,
                    'accommodation_id' => $editing_accommodation_id
                ));
            } else {
                error_log("Error WPDB al {$error_message_context} alojamiento: " . $this->database->wpdb->last_error);
                wp_send_json_error(array('message' => "Error al {$error_message_context} el alojamiento en la BD. Detalles: " . $this->database->wpdb->last_error));
            }

        } else { // Usuario no logueado
            if ($editing_accommodation_id > 0) {
                wp_send_json_error(array('message' => 'La edición no está permitida para usuarios no registrados.'));
                return;
            }
            // El $accommodation_data para sesión ya está completo (sin itinerario_id)
            // Añadimos tipo_de_cambio_alojamiento y moneda_precio_noche si no estaban ya
            $accommodation_data['moneda_precio_noche'] = $moneda_precio_noche;
            $accommodation_data['tipo_de_cambio_alojamiento'] = $tipo_de_cambio_alojamiento;

            error_log('Array $accommodation_data para SESIÓN: ' . print_r($accommodation_data, true));
            // ... (resto de tu lógica de sesión) ...
            if ($itinerario_id_str !== 'temp') { 
                wp_send_json_error(array('message' => 'Error de sesión al guardar alojamiento (invitado).'));
                return;
            }
            if (!isset($_SESSION['tic_alojamientos'])) {
                $_SESSION['tic_alojamientos'] = array();
            }
            $_SESSION['tic_alojamientos'][] = $accommodation_data; 
            wp_send_json_success(array('message' => 'Alojamiento guardado en la sesión.'));
        }
        error_log('--- TIC_Accommodation::guardar_alojamiento FIN ---');
    }

    /**
     * Muestra el reporte de alojamientos para un itinerario.
     * Es llamado vía AJAX o directamente.
     *
     * @param mixed $itinerario_id_param Puede ser el ID del itinerario (int) o 'temp' (string).
     * @return string HTML del reporte.
     */
    public function mostrar_reporte_alojamiento($itinerario_id_param) {
        $alojamientos_data = array();
        $itinerary_name_for_report = ''; // Nombre del itinerario para el encabezado del reporte
        $active_itinerary_report_currency = 'USD'; // Moneda por defecto

        if (is_user_logged_in()) {
            $itinerario_id = $this->security->sanitize_integer($itinerario_id_param);

            if ($itinerario_id <= 0) {
                return '<p class="tic-notice">ID de itinerario no válido para mostrar reporte de alojamiento.</p>';
            }

            // Obtener el nombre del itinerario
            $table_itinerarios = $this->database->get_table_name('itinerarios');
            $itinerario_info = $this->database->wpdb->get_row( // Usar get_row para obtener un solo itinerario
                $this->database->wpdb->prepare(
                    "SELECT nombre_itinerario, moneda_reporte FROM {$table_itinerarios} WHERE id = %d AND user_id = %d",
                    $itinerario_id,
                    get_current_user_id() 
                ),
                ARRAY_A // Obtener como array asociativo
            );

            if ($itinerario_info) {
                $itinerary_name_for_report = $itinerario_info['nombre_itinerario'];
                $active_itinerary_report_currency = $itinerario_info['moneda_reporte']; // <-- Obtener moneda
            } else {
                error_log("TIC Alojamiento Reporte: No se encontró info para itinerario ID: " . $itinerario_id . " para el usuario actual.");
                // Continuar para mostrar "No hay alojamientos..." si es el caso,
                // $active_itinerary_report_currency mantendrá el default 'USD'
            }

            // Obtener los datos de alojamiento de la BD
            $table_alojamientos = $this->database->get_table_name('alojamientos');
            $alojamientos_data = $this->database->wpdb->get_results(
                $this->database->wpdb->prepare(
                    "SELECT * FROM {$table_alojamientos} WHERE itinerario_id = %d ORDER BY fecha_entrada ASC",
                    $itinerario_id
                ),
                OBJECT 
            );

        } else { // Usuario no logueado
            if ($itinerario_id_param === 'temp') {
                $alojamientos_data = isset($_SESSION['tic_alojamientos']) ? $_SESSION['tic_alojamientos'] : array();
                // Para usuarios no logueados, no hay un "nombre de itinerario" persistente que mostrar.
                // Los datos en sesión son objetos si los guardamos así, o arrays si los guardamos como arrays.
                // Asegurémonos que sean objetos para la plantilla, si es necesario.
                if (!empty($alojamientos_data) && is_array($alojamientos_data[0])) { // Si son arrays, convertirlos
                    $temp_objects = [];
                    foreach ($alojamientos_data as $acc_array) {
                        $temp_objects[] = (object) $acc_array;
                    }
                    $alojamientos_data = $temp_objects;
                }
            } else {
                return '<p class="tic-notice">Error de sesión al mostrar reporte de alojamiento (invitado).</p>';
            }
        }

        // Usar output buffering para capturar el HTML de la plantilla
        ob_start();

        // La variable $itinerary_name_for_report y $alojamientos_data estarán disponibles
        // dentro del scope de la plantilla incluida.
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-accommodation-report.php';

        error_log('TIC Alojamiento: Intentando incluir plantilla de reporte: ' . $template_path);
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $error_msg = '<p class="tic-error">Error: Plantilla de reporte de alojamiento NO ENCONTRADA en: ' . esc_html($template_path) . '</p>';
            echo $error_msg;
            error_log('TIC Accommodation Report Template NOT FOUND: ' . $template_path);
        }

        return ob_get_clean();
    }
    /**
     * Obtiene un registro de alojamiento específico por su ID.
     *
     * @param int $accommodation_id El ID del alojamiento.
     * @return array|null Los datos del alojamiento como array asociativo, o null si no se encuentra o no pertenece al usuario.
     */
    public function get_accommodation_by_id($accommodation_id) {
        $accommodation_id = absint($accommodation_id); // Sanitizar el ID

        if (!$accommodation_id) {
            return null;
        }

        $table_alojamientos = $this->database->get_table_name('alojamientos');
        $table_itinerarios = $this->database->get_table_name('itinerarios');
        $current_user_id = get_current_user_id();

        // Obtener datos principales del alojamiento, asegurando que pertenezca a un itinerario del usuario actual
        // Esta consulta es un poco más compleja para la seguridad, uniendo con itinerarios.
        $accommodation_data = $this->database->wpdb->get_row(
            $this->database->wpdb->prepare(
                "SELECT a.* FROM {$table_alojamientos} a
                INNER JOIN {$table_itinerarios} i ON a.itinerario_id = i.id
                WHERE a.id = %d AND i.user_id = %d",
                $accommodation_id,
                $current_user_id
            ),
            ARRAY_A // Devolver como array asociativo
        );

        if (!$accommodation_data) {
            error_log("TIC Alojamiento: No se encontró el alojamiento ID {$accommodation_id} para el usuario ID {$current_user_id}.");
            return null; // Alojamiento no encontrado o no pertenece al usuario
        }

        // Convertir campos numéricos y fechas que vienen como string de la BD a tipos más usables en JS si es necesario.
        $numeric_fields = ['precio_noche', 'numero_noches', 'precio_total_alojamiento'];
        foreach ($numeric_fields as $field) {
            if (isset($accommodation_data[$field])) {
                $accommodation_data[$field] = (float) $accommodation_data[$field];
            }
        }
        // Las fechas ya están en formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS, que JS puede parsear.

        return $accommodation_data;
    }

    /**
     * Elimina un registro de alojamiento específico de la base de datos.
     *
     * @param int $accommodation_id El ID del alojamiento a eliminar.
     * @return bool True si la eliminación fue exitosa, False en caso de error o si no se eliminaron filas.
     */
    public function delete_accommodation_by_id($accommodation_id) {
        $accommodation_id = absint($accommodation_id); // Sanitizar el ID

        if (!$accommodation_id) {
            error_log('TIC Error: Intento de eliminar alojamiento con ID inválido o cero.');
            return false;
        }

        $table_alojamientos = $this->database->get_table_name('alojamientos');
        $current_user_id = get_current_user_id();

        // Para mayor seguridad, podríamos verificar que el alojamiento pertenece a un itinerario del usuario actual.
        // Primero, obtenemos el itinerario_id del alojamiento.
        $itinerario_id = $this->database->wpdb->get_var(
            $this->database->wpdb->prepare(
                "SELECT itinerario_id FROM {$table_alojamientos} WHERE id = %d",
                $accommodation_id
            )
        );

        if (!$itinerario_id) {
            error_log("TIC Delete Alojamiento: No se encontró alojamiento con ID {$accommodation_id} para verificar pertenencia.");
            return false; // Alojamiento no existe
        }

        // Ahora verificamos que el itinerario_id pertenezca al usuario actual.
        $table_itinerarios = $this->database->get_table_name('itinerarios');
        $is_owner = $this->database->wpdb->get_var(
            $this->database->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_itinerarios} WHERE id = %d AND user_id = %d",
                $itinerario_id,
                $current_user_id
            )
        );

        if (!$is_owner) {
            error_log("TIC Delete Alojamiento: Usuario {$current_user_id} intentó borrar alojamiento ID {$accommodation_id} que no le pertenece (itinerario ID {$itinerario_id}).");
            return false; // No tiene permiso
        }

        // Si todo está bien, proceder a eliminar el alojamiento
        $result = $this->database->wpdb->delete(
            $table_alojamientos,
            array('id' => $accommodation_id), // Condición WHERE
            array('%d')                     // Formato para la condición WHERE
        );

        if ($result === false) {
            error_log("TIC Error: Falló la eliminación del alojamiento ID {$accommodation_id}. Error DB: " . $this->database->wpdb->last_error);
            return false;
        } elseif ($result === 0) {
            error_log("TIC Warning: Se intentó eliminar el alojamiento ID {$accommodation_id}, pero no se encontró o ya estaba eliminado (0 filas afectadas).");
            return true; // Consideramos éxito si ya no está.
        }

        error_log("TIC Info: Alojamiento ID {$accommodation_id} eliminado. Filas afectadas: " . $result);
        return true; // Éxito
    }

}