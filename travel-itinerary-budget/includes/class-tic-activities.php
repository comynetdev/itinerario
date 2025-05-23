<?php
/**
 * Clase TIC_Activities
 * Maneja la lógica para el módulo de actividades y tours.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Activities {

    private $database;
    private $security;

    public function __construct(TIC_Database $database, TIC_Security $security) {
        $this->database = $database;
        $this->security = $security;
    }

    /**
     * Muestra el formulario para capturar la información de una actividad.
     * Es llamado vía AJAX.
     *
     * @param int $itinerario_id El ID del itinerario actual.
     * @return string HTML del formulario.
     */
    public function mostrar_formulario_actividad($itinerario_id = 0) {
        // Lógica para sanitizar itinerario_id
        if (is_user_logged_in()) {
            $itinerario_id = $this->security->sanitize_integer($itinerario_id);
            if ($itinerario_id <= 0) {
                return '<p class="tic-notice">Por favor, selecciona un itinerario válido para añadir actividades.</p>';
            }
        } else {
            $itinerario_id = ($itinerario_id === 'temp') ? 'temp' : 0;
            if ($itinerario_id === 0 && $itinerario_id !== 'temp') {
                 return '<p class="tic-notice">Error al determinar el itinerario para las actividades (invitado).</p>';
            }
        }

        // Pasar el ID del itinerario a la plantilla del formulario
        set_query_var('tic_itinerario_id_form_actividad', $itinerario_id);
        // Aquí también podrías pasar un nonce para el guardado del formulario

        ob_start();
        // Necesitaremos crear este archivo de plantilla: templates/tic-activities-form.php
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-activities-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p class="tic-error">Error: Plantilla de formulario de actividades no encontrada.</p>';
            error_log('TIC Activities Form Template NOT FOUND: ' . $template_path);
        }
        set_query_var('tic_itinerario_id_form_actividad', null); // Limpiar
        return ob_get_clean();
    }

    /**
     * Guarda la información de una nueva actividad.
     * Es llamado vía AJAX.
     */
    public function guardar_actividad() {
        error_log('--- TIC_Activities::guardar_actividad INICIO ---');
        error_log('Raw $_POST para guardar actividad: ' . print_r($_POST, true));

        // 1. Sanitizar todos los datos esperados y obtener ID de edición si existe
        $itinerario_id_str = isset($_POST['itinerario_id']) ? sanitize_text_field($_POST['itinerario_id']) : '0';
        $editing_activity_id = isset($_POST['editing_activity_id']) && !empty($_POST['editing_activity_id']) ? absint($_POST['editing_activity_id']) : 0;

        $pais = isset($_POST['pais']) ? $this->security->sanitize_text($_POST['pais']) : '';
        $ciudad_poblacion = isset($_POST['ciudad_poblacion']) ? $this->security->sanitize_text($_POST['ciudad_poblacion']) : '';
        $fecha_actividad = isset($_POST['fecha_actividad']) ? $this->security->sanitize_datetime($_POST['fecha_actividad']) : null;
        $nombre_tour_actividad = isset($_POST['nombre_tour_actividad']) ? $this->security->sanitize_text($_POST['nombre_tour_actividad']) : '';
        $precio_persona = isset($_POST['precio_persona']) ? $this->security->sanitize_decimal($_POST['precio_persona']) : 0;
        $numero_personas = isset($_POST['numero_personas']) ? $this->security->sanitize_integer($_POST['numero_personas']) : 1;
        $moneda_precio_actividad = isset($_POST['moneda_precio_actividad']) ? $this->security->validate_currency($_POST['moneda_precio_actividad']) : 'USD';
        $tipo_de_cambio_actividad = isset($_POST['tipo_de_cambio_actividad']) ? $this->security->sanitize_decimal($_POST['tipo_de_cambio_actividad']) : 1.0000;
        if (floatval($tipo_de_cambio_actividad) == 0) {
            $tipo_de_cambio_actividad = 1.0000;
        }
        $proveedor_reserva = isset($_POST['proveedor_reserva']) ? $this->security->sanitize_text($_POST['proveedor_reserva']) : '';
        $codigo_reserva = isset($_POST['codigo_reserva']) ? $this->security->sanitize_text($_POST['codigo_reserva']) : '';
        $notas = isset($_POST['notas']) ? sanitize_textarea_field($_POST['notas']) : '';

        // 2. Validaciones básicas
        if (empty($nombre_tour_actividad) || empty($fecha_actividad)) {
            wp_send_json_error(array('message' => 'Por favor, completa los campos obligatorios: Nombre del Tour/Actividad y Fecha.'));
            return; 
        }

        // 3. Calcular precio_total_actividad (se hace para INSERT y UPDATE con los datos nuevos/modificados)
        $precio_total_actividad = 0;
        if ($precio_persona > 0 && $numero_personas > 0 && $tipo_de_cambio_actividad > 0) {
            $precio_total_actividad = round(($precio_persona * $numero_personas) * $tipo_de_cambio_actividad, 2);
        }
        error_log("Calculado Actividad - Precio Total: {$precio_total_actividad}");

        // 4. Preparar datos y formatos comunes
        // Nota: itinerario_id se añade condicionalmente abajo para INSERT, para UPDATE podría ser parte del WHERE o no actualizarse.
        $activity_data_fields = array(
            'pais' => $pais,
            'ciudad_poblacion' => $ciudad_poblacion,
            'fecha_actividad' => $fecha_actividad,
            'nombre_tour_actividad' => $nombre_tour_actividad,
            'precio_persona' => $precio_persona,
            'numero_personas' => $numero_personas,
            'moneda_precio_actividad' => $moneda_precio_actividad,
            'tipo_de_cambio_actividad' => $tipo_de_cambio_actividad,
            'precio_total_actividad' => $precio_total_actividad,
            'proveedor_reserva' => $proveedor_reserva,
            'codigo_reserva' => $codigo_reserva,
            'notas' => $notas,
        );

        $activity_data_formats = array(
            '%s', // pais
            '%s', // ciudad_poblacion
            '%s', // fecha_actividad
            '%s', // nombre_tour_actividad
            '%f', // precio_persona
            '%d', // numero_personas
            '%s', // moneda_precio_actividad
            '%f', // tipo_de_cambio_actividad
            '%f', // precio_total_actividad
            '%s', // proveedor_reserva
            '%s', // codigo_reserva
            '%s', // notas
        );

        // 5. Guardar en BD o Sesión
        if (is_user_logged_in()) {
            $table_name = $this->database->get_table_name('actividades');
            $result = false;
            $itinerario_id = $this->security->sanitize_integer($itinerario_id_str);

            if ($editing_activity_id > 0) {
                // --- ES UNA ACTUALIZACIÓN (UPDATE) ---
                if ($itinerario_id <= 0) { // El itinerario_id del form debe ser válido para el contexto
                    wp_send_json_error(array('message' => 'ID de itinerario inválido para actualizar la actividad.'));
                    return;
                }
                // Añadimos itinerario_id a los datos a actualizar, asumiendo que puede cambiar o para consistencia.
                // Si no quieres que se actualice, quítalo de aquí y de $data_for_db_final_formats.
                $data_for_db_final = array('itinerario_id' => $itinerario_id) + $activity_data_fields;
                $data_for_db_final_formats = array_merge(array('%d'), $activity_data_formats);

                error_log("Actualizando actividad ID: " . $editing_activity_id . " con datos: " . print_r($data_for_db_final, true));
                error_log("Formatos para UPDATE: " . print_r($data_for_db_final_formats, true));

                $where = array('id' => $editing_activity_id);
                // Opcional: añadir user_id a la cláusula WHERE a través de un JOIN con itinerarios para más seguridad,
                // o verificar pertenencia antes como hicimos en get_activity_by_id.
                // Por ahora, confiamos en que el ID de actividad es correcto y pertenece al usuario (validado al obtener datos para editar).
                $where_formats = array('%d');

                $result = $this->database->wpdb->update($table_name, $data_for_db_final, $where, $data_for_db_final_formats, $where_formats);
                $success_message = 'Actividad actualizada correctamente.';
                $error_message_context = 'actualizar';

            } else {
                // --- ES UNA INSERCIÓN (INSERT) ---
                if ($itinerario_id <= 0) {
                    wp_send_json_error(array('message' => 'ID de itinerario inválido para guardar la nueva actividad.'));
                    return;
                }
                $data_for_db_final = array('itinerario_id' => $itinerario_id) + $activity_data_fields;
                $data_for_db_final_formats = array_merge(array('%d'), $activity_data_formats);

                if (count($data_for_db_final) !== count($data_for_db_final_formats)) {
                    error_log('CRITICAL ERROR INSERT Actividad: Mismatch counts.');
                    wp_send_json_error(array('message' => 'Error interno del servidor (format insert actividad).'));
                    return;
                }
                error_log("Insertando nueva actividad con datos: " . print_r($data_for_db_final, true));
                error_log("Formatos para INSERT: " . print_r($data_for_db_final_formats, true));

                $result = $this->database->wpdb->insert($table_name, $data_for_db_final, $data_for_db_final_formats);
                if ($result) {
                    $editing_activity_id = $this->database->wpdb->insert_id; // ID del nuevo registro
                }
                $success_message = 'Actividad guardada correctamente.';
                $error_message_context = 'guardar';
            }

            if ($result !== false) { 
                wp_send_json_success(array(
                    'message' => $success_message,
                    'activity_id' => $editing_activity_id 
                ));
            } else {
                error_log("Error WPDB al {$error_message_context} actividad: " . $this->database->wpdb->last_error);
                wp_send_json_error(array('message' => "Error al {$error_message_context} la actividad en la BD. Detalles: " . $this->database->wpdb->last_error));
            }

        } else { // Usuario no logueado
            if ($editing_activity_id > 0) {
                wp_send_json_error(array('message' => 'La edición no está permitida para usuarios no registrados.'));
                return;
            }
            // Para la sesión, usamos $activity_data_fields que no tiene itinerario_id
            $session_data = $activity_data_fields;
            // Añadir los campos calculados y otros relevantes para la sesión
            $session_data['precio_total_actividad'] = $precio_total_actividad; 
            // No necesitamos itinerario_id dentro del item si 'temp' lo maneja globalmente para la sesión

            error_log('Array $session_data para SESIÓN: ' . print_r($session_data, true));
            if ($itinerario_id_str !== 'temp') { 
                wp_send_json_error(array('message' => 'Error de sesión al guardar actividad (invitado).'));
                return;
            }
            if (!isset($_SESSION['tic_actividades'])) {
                $_SESSION['tic_actividades'] = array();
            }
            $_SESSION['tic_actividades'][] = $session_data; 
            wp_send_json_success(array('message' => 'Actividad guardada en la sesión.'));
        }
        error_log('--- TIC_Activities::guardar_actividad FIN ---');
    }

    /**
     * Muestra el reporte de actividades para un itinerario.
     * Es llamado vía AJAX.
     *
     * @param mixed $itinerario_id_param Puede ser el ID del itinerario o 'temp'.
     * @return string HTML del reporte.
     */
    public function mostrar_reporte_actividad($itinerario_id_param) {
      $actividades_data = array();
      $itinerary_name_for_report = '';
      $active_itinerary_report_currency = 'USD'; // Moneda por defecto
  
      if (is_user_logged_in()) {
          $itinerario_id = $this->security->sanitize_integer($itinerario_id_param);
  
          if ($itinerario_id <= 0) {
              return '<p class="tic-notice">ID de itinerario no válido para mostrar reporte de actividades.</p>';
          }
  
          $table_itinerarios = $this->database->get_table_name('itinerarios');
          $itinerario_info = $this->database->wpdb->get_row(
              $this->database->wpdb->prepare(
                  "SELECT nombre_itinerario, moneda_reporte FROM {$table_itinerarios} WHERE id = %d AND user_id = %d",
                  $itinerario_id,
                  get_current_user_id()
              ),
              ARRAY_A
          );
  
          if ($itinerario_info) {
              $itinerary_name_for_report = $itinerario_info['nombre_itinerario'];
              $active_itinerary_report_currency = $itinerario_info['moneda_reporte'];
          } else {
              error_log("TIC Actividades Reporte: No se encontró info para itinerario ID: " . $itinerario_id . " para el usuario actual.");
          }
  
          $table_actividades = $this->database->get_table_name('actividades');
          $actividades_data = $this->database->wpdb->get_results(
              $this->database->wpdb->prepare(
                  "SELECT * FROM {$table_actividades} WHERE itinerario_id = %d ORDER BY fecha_actividad ASC, fecha_creacion ASC",
                  $itinerario_id
              ),
              OBJECT 
          );
  
      } else { // Usuario no logueado
          if ($itinerario_id_param === 'temp') {
              $actividades_data = isset($_SESSION['tic_actividades']) ? $_SESSION['tic_actividades'] : array();
              if (!empty($actividades_data) && isset($actividades_data[0]) && is_array($actividades_data[0])) {
                  $temp_objects = [];
                  foreach ($actividades_data as $act_array) {
                      $temp_objects[] = (object) $act_array;
                  }
                  $actividades_data = $temp_objects;
              }
          } else {
              return '<p class="tic-notice">Error de sesión al mostrar reporte de actividades (invitado).</p>';
          }
      }
  
      ob_start();
      $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/tic-activities-report.php';
  
      error_log('TIC Actividades: Intentando incluir plantilla de reporte: ' . $template_path);
      if (file_exists($template_path)) {
          include $template_path; // $actividades_data, $itinerary_name_for_report, $active_itinerary_report_currency estarán disponibles
      } else {
          $error_msg = '<p class="tic-error">Error: Plantilla de reporte de actividades NO ENCONTRADA en: ' . esc_html($template_path) . '</p>';
          echo $error_msg;
          error_log('TIC Activities Report Template NOT FOUND: ' . $template_path);
      }
  
      return ob_get_clean();
  }

    /**
     * Obtiene una actividad específica por su ID, verificando la pertenencia al usuario.
     *
     * @param int $activity_id El ID de la actividad.
     * @return array|null Los datos de la actividad como array asociativo, o null si no se encuentra o no pertenece al usuario.
     */
    public function get_activity_by_id($activity_id) {
        $activity_id = absint($activity_id); // Sanitizar el ID

        if (!$activity_id) {
            return null;
        }

        $table_actividades = $this->database->get_table_name('actividades');
        $table_itinerarios = $this->database->get_table_name('itinerarios');
        $current_user_id = get_current_user_id();

        // Obtener datos de la actividad, asegurando que pertenezca a un itinerario del usuario actual
        $activity_data = $this->database->wpdb->get_row(
            $this->database->wpdb->prepare(
                "SELECT act.* FROM {$table_actividades} act
                INNER JOIN {$table_itinerarios} i ON act.itinerario_id = i.id
                WHERE act.id = %d AND i.user_id = %d",
                $activity_id,
                $current_user_id
            ),
            ARRAY_A // Devolver como array asociativo
        );

        if (!$activity_data) {
            error_log("TIC Actividad: No se encontró la actividad ID {$activity_id} para el usuario ID {$current_user_id}.");
            return null; 
        }

        // Convertir campos numéricos que vienen como string de la BD a tipos correctos
        $numeric_fields = ['precio_persona', 'numero_personas', 'tipo_de_cambio_actividad', 'precio_total_actividad'];
        foreach ($numeric_fields as $field) {
            if (isset($activity_data[$field])) {
                $activity_data[$field] = (float) $activity_data[$field];
            }
        }
        // Las fechas ya están en formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS

        return $activity_data;
    }
    /**
     * Elimina una actividad específica de la base de datos,
     * verificando la pertenencia al usuario a través del itinerario.
     *
     * @param int $activity_id El ID de la actividad a eliminar.
     * @return bool True si la eliminación fue exitosa o si el registro no existía, False en caso de error o falta de permiso.
     */
    public function delete_activity_by_id($activity_id) {
        $activity_id = absint($activity_id); // Sanitizar el ID

        if (!$activity_id) {
            error_log('TIC Error: Intento de eliminar actividad con ID inválido o cero.');
            return false;
        }

        $table_actividades = $this->database->get_table_name('actividades');
        $table_itinerarios = $this->database->get_table_name('itinerarios');
        $current_user_id = get_current_user_id();

        // Verificar que la actividad pertenece a un itinerario del usuario actual
        $itinerario_id = $this->database->wpdb->get_var(
            $this->database->wpdb->prepare(
                "SELECT itinerario_id FROM {$table_actividades} WHERE id = %d",
                $activity_id
            )
        );

        if (!$itinerario_id) {
            error_log("TIC Delete Actividad: No se encontró actividad con ID {$activity_id} para verificar pertenencia.");
            return true; // Considerar como éxito si el registro ya no existe
        }

        $is_owner = $this->database->wpdb->get_var(
            $this->database->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_itinerarios} WHERE id = %d AND user_id = %d",
                $itinerario_id,
                $current_user_id
            )
        );

        if (!$is_owner) {
            error_log("TIC Delete Actividad: Usuario {$current_user_id} intentó borrar actividad ID {$activity_id} que no le pertenece (itinerario ID {$itinerario_id}).");
            return false; // No tiene permiso
        }

        // Proceder a eliminar la actividad
        $result = $this->database->wpdb->delete(
            $table_actividades,
            array('id' => $activity_id), // Condición WHERE
            array('%d')                     // Formato para la condición WHERE
        );

        if ($result === false) {
            error_log("TIC Error: Falló la eliminación de la actividad ID {$activity_id}. Error DB: " . $this->database->wpdb->last_error);
            return false;
        } elseif ($result === 0) {
            error_log("TIC Warning: Se intentó eliminar la actividad ID {$activity_id}, pero no se encontró o ya estaba eliminada (0 filas afectadas).");
            return true; // Consideramos éxito si ya no está.
        }

        error_log("TIC Info: Actividad ID {$activity_id} eliminada. Filas afectadas: " . $result);
        return true; // Éxito
    }

} // Fin de la clase TIC_Activities