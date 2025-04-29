<?php

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Itineraries {

    private $database;
    private $security;

    public function __construct(TIC_Database $database, TIC_Security $security) {
        $this->database = $database;
        $this->security = $security;
    }

    /**
     * Crea un nuevo itinerario para un usuario registrado.
     */
    public function crear_itinerario() {
        error_log("Llamado a crear_itinerario");
        error_log(print_r($_POST, true));
        if (!is_user_logged_in() || !isset($_POST['itinerary_name'])) { // Coincide con el name del input
            wp_send_json_error('Debes estar logueado y proporcionar un nombre para el itinerario.');
            wp_die();
        }
    
        if (isset($_POST['nonce']) && $this->security->verify_nonce('tic_create_itinerary_nonce', $_POST['nonce'])) {
            $user_id = get_current_user_id();
            $nombre_itinerario = $this->security->sanitize_text($_POST['itinerary_name']); // Coincide con el name del input
    
            $table_name = $this->database->get_table_name('itinerarios');
            $data = array(
                'user_id' => $user_id,
                'nombre_itinerario' => $nombre_itinerario,
            );
            $format = array('%d', '%s');
    
            $result = $this->database->wpdb->insert($table_name, $data, $format);
    
            if ($result) {
                $itinerary_id = $this->database->wpdb->insert_id;
                wp_send_json_success(array('itinerary_id' => $itinerary_id, 'message' => 'Itinerario creado correctamente.'));
            } else {
                wp_send_json_error('Error al crear el itinerario.');
            }
        } else {
            wp_send_json_error('Nonce inválido.');
        }
        wp_die();
    }

    /**
     * Obtiene la información de un itinerario por su ID.
     *
     * @param int $itinerary_id El ID del itinerario.
     * @return object|null El objeto con la información del itinerario o null si no se encuentra.
     */
    public function obtener_itinerario($itinerary_id) {
        $itinerary_id = $this->security->sanitize_integer($itinerary_id);
        $table_name = $this->database->get_table_name('itinerarios');
        return $this->database->wpdb->get_row(
            $this->database->wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $itinerary_id)
        );
    }

    // Podríamos agregar más funciones aquí para actualizar, eliminar o listar itinerarios en el futuro
}