<?php

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Database {

    public $wpdb;
    private $table_prefix;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $this->wpdb->prefix . 'tic_';
    }

    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->wpdb->get_charset_collate();

        $table_itinerarios = $this->get_table_name('itinerarios');
        $table_vuelos = $this->get_table_name('vuelos');
        $table_vuelos_escalas = $this->get_table_name('vuelos_escalas');

        $sql_itinerarios = "CREATE TABLE IF NOT EXISTS {$table_itinerarios} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            nombre_itinerario VARCHAR(255) NOT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE SET NULL
        ) {$charset_collate};";

        $sql_vuelos = "CREATE TABLE IF NOT EXISTS {$table_vuelos} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            itinerario_id BIGINT(20) UNSIGNED NOT NULL,
            origen VARCHAR(255) NOT NULL,
            destino VARCHAR(255) NOT NULL,
            linea_aerea VARCHAR(255),
            numero_vuelo VARCHAR(50),
            fecha_hora_salida DATETIME,
            fecha_hora_llegada DATETIME,
            tiene_escalas TINYINT(1) NOT NULL DEFAULT 0,
            precio_persona DECIMAL(10, 2) NOT NULL,
            moneda_precio VARCHAR(10) NOT NULL DEFAULT 'USD',
            moneda_usuario VARCHAR(10) NOT NULL,
            numero_personas INT(11) NOT NULL,
            tipo_de_cambio DECIMAL(10, 2) NOT NULL,
            precio_total_vuelos DECIMAL(10, 2) NOT NULL,
            codigo_reserva VARCHAR(50),
            PRIMARY KEY (id),
            FOREIGN KEY (itinerario_id) REFERENCES {$table_itinerarios}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $sql_vuelos_escalas = "CREATE TABLE IF NOT EXISTS {$table_vuelos_escalas} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vuelo_id BIGINT(20) UNSIGNED NOT NULL,
            orden INT(11) NOT NULL,
            aeropuerto VARCHAR(255),
            fecha_hora_llegada DATETIME,
            fecha_hora_salida DATETIME,
            PRIMARY KEY (id),
            FOREIGN KEY (vuelo_id) REFERENCES {$table_vuelos}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        dbDelta(array($sql_itinerarios, $sql_vuelos, $sql_vuelos_escalas));
    }

    public function get_table_name($table_base) {
        return $this->table_prefix . $table_base;
    }

    // Métodos para operaciones CRUD (Crear, Leer, Actualizar, Borrar) se agregarán aquí posteriormente
}