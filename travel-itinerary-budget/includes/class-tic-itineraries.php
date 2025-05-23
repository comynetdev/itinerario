<?php

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Itineraries
{

    private $database;
    private $security;

    public function __construct(TIC_Database $database, TIC_Security $security)
    {
        $this->database = $database;
        $this->security = $security;
    }

    /**
     * Crea un nuevo itinerario para un usuario registrado.
     * Ahora también guarda la moneda de reporte seleccionada.
     */
    public function crear_itinerario()
    {
        error_log("Llamado a crear_itinerario");
        // El error_log de $_POST ya lo tenías, lo cual es útil para depurar.
        // error_log(print_r($_POST, true)); 

        if (!is_user_logged_in() || !isset($_POST['itinerary_name'])) {
            wp_send_json_error(array('message' => 'Debes estar logueado y proporcionar un nombre para el itinerario.'));
            // wp_die() es llamado por wp_send_json_error
        }

        // El nonce ya se verifica en la función AJAX handler tic_ajax_create_itinerary()
        // Si quieres doble verificación o moverla aquí, asegúrate que se pase el nonce a este método.
        // Por ahora, asumimos que la verificación principal del nonce está en el handler.

        $user_id = get_current_user_id();
        $nombre_itinerario = $this->security->sanitize_text($_POST['itinerary_name']);

        // --- INICIO NUEVO CÓDIGO PARA MONEDA REPORTE ---
        $moneda_reporte_raw = isset($_POST['itinerary_report_currency']) ? sanitize_text_field($_POST['itinerary_report_currency']) : 'USD'; // Default 'USD' si no se envía
        // Sanitización básica: mayúsculas, 3 caracteres.
        $moneda_reporte = strtoupper(substr(trim($moneda_reporte_raw), 0, 3));
        // Podrías añadir una validación más estricta si tienes una lista fija de monedas permitidas.
        if (empty($moneda_reporte) || strlen($moneda_reporte) !== 3 || !ctype_alpha($moneda_reporte)) {
            $moneda_reporte = 'USD'; // Fallback a USD si la sanitización/validación falla
            error_log("TIC Itinerario: Moneda de reporte inválida recibida '{$moneda_reporte_raw}', usando USD por defecto.");
        }
        // --- FIN NUEVO CÓDIGO PARA MONEDA REPORTE ---

        if (empty($nombre_itinerario)) {
            wp_send_json_error(array('message' => 'El nombre del itinerario no puede estar vacío.'));
        }

        $table_name = $this->database->get_table_name('itinerarios');
        $data = array(
            'user_id' => $user_id,
            'nombre_itinerario' => $nombre_itinerario,
            'moneda_reporte' => $moneda_reporte, // <-- Añadir nueva columna
        );
        // Asegúrate que el orden y número de formatos coincida con el array $data
        $format = array(
            '%d', // user_id
            '%s', // nombre_itinerario
            '%s', // moneda_reporte (VARCHAR)
        );

        $result = $this->database->wpdb->insert($table_name, $data, $format);

        if ($result) {
            $itinerary_id = $this->database->wpdb->insert_id;
            // Devolver también la moneda guardada
            wp_send_json_success(array(
                'itinerary_id' => $itinerary_id,
                'itinerary_name' => $nombre_itinerario, // Usar el nombre sanitizado
                'itinerary_currency' => $moneda_reporte, // <-- Devolver la moneda
                'message' => 'Itinerario "' . esc_html($nombre_itinerario) . '" creado correctamente con moneda ' . esc_html($moneda_reporte) . '.'
            ));
        } else {
            error_log("Error WPDB al crear itinerario: " . $this->database->wpdb->last_error);
            wp_send_json_error(array('message' => 'Error al crear el itinerario en la base de datos.'));
        }
        // wp_die() es llamado por wp_send_json_success/error
    }
    /**
     * Obtiene la información de un itinerario por su ID.
     *
     * @param int $itinerary_id El ID del itinerario.
     * @return object|null El objeto con la información del itinerario o null si no se encuentra.
     */
    public function obtener_itinerario($itinerary_id)
    {
        $itinerary_id = $this->security->sanitize_integer($itinerary_id);
        $table_name = $this->database->get_table_name('itinerarios');
        return $this->database->wpdb->get_row(
            $this->database->wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $itinerary_id)
        );
    }

    // Podríamos agregar más funciones aquí para actualizar, eliminar o listar itinerarios en el futuro
}
