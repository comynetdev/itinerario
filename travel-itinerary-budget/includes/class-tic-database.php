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
        // Asegurarse de que el prefijo base de tic_ sea consistente
        $this->table_prefix = $this->wpdb->prefix . 'tic_';
    }

    /**
     * Crea o actualiza las tablas de la base de datos de forma segura.
     */
    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->wpdb->get_charset_collate();

        $table_itinerarios = $this->get_table_name('itinerarios');
        $table_vuelos = $this->get_table_name('vuelos');
        $table_vuelos_escalas = $this->get_table_name('vuelos_escalas');
        $table_alojamientos = $this->get_table_name('alojamientos');
        $table_actividades = $this->get_table_name('actividades'); // <<< NUEVA LÍNEA
        $table_users = $this->wpdb->prefix . 'users'; // Tabla de usuarios de WP

        error_log("TIC DB: Iniciando creación/actualización de tablas...");

        // --- Paso 1: Definir y crear/actualizar estructura básica de tablas SIN FKs ---

        // SQL Definition for Itinerarios (sin FK a users inicialmente)
        $sql_itinerarios = "CREATE TABLE {$table_itinerarios} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL, /* Referencia a users.ID */
            nombre_itinerario VARCHAR(255) NOT NULL,
            moneda_reporte VARCHAR(3) NOT NULL DEFAULT 'USD',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id_idx (user_id) /* Índice para la FK */
        ) {$charset_collate};";

        // SQL Definition for Vuelos (sin FK a itinerarios inicialmente)
        $sql_vuelos = "CREATE TABLE {$table_vuelos} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            itinerario_id BIGINT(20) UNSIGNED NULL, /* Permitir NULL temporalmente si FK se añade después */
            origen VARCHAR(255) NULL, /* Permitir NULL temporalmente */
            destino VARCHAR(255) NULL, /* Permitir NULL temporalmente */
            linea_aerea VARCHAR(255) NULL,
            numero_vuelo VARCHAR(50) NULL,
            fecha_hora_salida DATETIME NULL,
            fecha_hora_llegada DATETIME NULL,
            tiene_escalas TINYINT(1) NOT NULL DEFAULT 0,
            precio_persona DECIMAL(10, 2) NULL,
            moneda_precio VARCHAR(10) NULL DEFAULT 'USD',
            numero_personas INT(11) NULL,
            tipo_de_cambio DECIMAL(10, 2) NULL,
            precio_total_vuelos DECIMAL(10, 2) NULL,
            codigo_reserva VARCHAR(50) NULL,
            PRIMARY KEY (id),
            KEY itinerario_id_idx (itinerario_id) /* Índice para la FK */
        ) {$charset_collate};";

        // SQL Definition for Vuelos Escalas (sin FK a vuelos inicialmente)
        $sql_vuelos_escalas = "CREATE TABLE {$table_vuelos_escalas} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vuelo_id BIGINT(20) UNSIGNED NULL, /* Permitir NULL temporalmente si FK se añade después */
            orden INT(11) NULL, /* Permitir NULL temporalmente */
            aeropuerto VARCHAR(255) NULL,
            fecha_hora_llegada DATETIME NULL,
            fecha_hora_salida DATETIME NULL,
            PRIMARY KEY (id),
            KEY vuelo_id_idx (vuelo_id) /* Índice para la FK */
        ) {$charset_collate};";

        // --- SQL Definition for Alojamientos (sin FK inicialmente para dbDelta) ---
        $sql_alojamientos = "CREATE TABLE {$table_alojamientos} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            itinerario_id BIGINT(20) UNSIGNED NULL, /* Permitir NULL temporalmente si FK se añade después */
            pais VARCHAR(100) NULL,
            ciudad_poblacion VARCHAR(100) NULL,
            hotel_hospedaje VARCHAR(255) NULL,
            direccion_hotel TEXT NULL,
            fecha_entrada DATETIME NULL,
            fecha_salida DATETIME NULL,
            precio_noche DECIMAL(10, 2) NULL,
            moneda_precio_noche VARCHAR(10) NULL DEFAULT 'USD',
            numero_noches INT NULL,
            tipo_de_cambio_alojamiento DECIMAL(10, 4) NULL DEFAULT 1.0000,
            precio_total_alojamiento DECIMAL(10, 2) NULL,
            fecha_pago_reserva DATETIME NULL,
            codigo_reserva VARCHAR(100) NULL,
            aplicacion_pago_reserva VARCHAR(100) NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY itinerario_id_idx (itinerario_id) /* Índice para la FK */
        ) {$charset_collate};";

        // --- SQL Definition for Actividades (sin FK inicialmente para dbDelta) ---
        $sql_actividades = "CREATE TABLE {$table_actividades} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            itinerario_id BIGINT(20) UNSIGNED NULL, /* Se cambiará a NOT NULL con ALTER TABLE */
            pais VARCHAR(100) NULL,
            ciudad_poblacion VARCHAR(100) NULL,
            fecha_actividad DATETIME NULL, /* Usamos DATETIME por si la hora es relevante */
            nombre_tour_actividad TEXT NULL, /* TEXT para descripciones potencialmente largas */
            precio_persona DECIMAL(10, 2) NULL,
            numero_personas INT(11) NULL DEFAULT 1,
            moneda_precio_actividad VARCHAR(3) NULL DEFAULT 'USD',
            tipo_de_cambio_actividad DECIMAL(10, 4) NULL DEFAULT 1.0000,
            precio_total_actividad DECIMAL(10, 2) NULL, /* Calculado */
            proveedor_reserva VARCHAR(255) NULL,
            codigo_reserva VARCHAR(100) NULL,
            notas TEXT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY itinerario_id_idx (itinerario_id) /* Índice para la FK */
        ) {$charset_collate};";

        // Ejecutar dbDelta para cada tabla por separado
        error_log("TIC DB: Creando/Actualizando tabla {$table_itinerarios}...");
        dbDelta($sql_itinerarios);
        $this->check_dbdelta_errors($table_itinerarios);

        error_log("TIC DB: Creando/Actualizando tabla {$table_vuelos}...");
        dbDelta($sql_vuelos);
        $this->check_dbdelta_errors($table_vuelos);

        error_log("TIC DB: Creando/Actualizando tabla {$table_vuelos_escalas}...");
        dbDelta($sql_vuelos_escalas);
        $this->check_dbdelta_errors($table_vuelos_escalas);

        error_log("TIC DB: Creando/Actualizando tabla {$table_alojamientos}...");
        dbDelta($sql_alojamientos);
        $this->check_dbdelta_errors($table_alojamientos);

        // --- Paso 2: Intentar añadir las claves foráneas explícitamente ---
        error_log("TIC DB: Intentando añadir claves foráneas par alojamientos si no existen...");

        error_log("TIC DB: Creando/Actualizando tabla {$table_actividades}...");
        dbDelta($sql_actividades);
        $this->check_dbdelta_errors($table_actividades);

        // FK: vuelos -> itinerarios
        if (!$this->foreign_key_exists($table_vuelos, 'fk_vuelo_itinerario')) {
             error_log("TIC DB: Añadiendo FK vuelos -> itinerarios");
             // Antes de añadir, asegurarse que la columna referenciada NO ES NULL si la FK lo requiere
             $this->wpdb->query("ALTER TABLE {$table_vuelos} MODIFY COLUMN itinerario_id BIGINT(20) UNSIGNED NOT NULL");
             $this->wpdb->query("ALTER TABLE {$table_vuelos} ADD CONSTRAINT fk_vuelo_itinerario FOREIGN KEY (itinerario_id) REFERENCES {$table_itinerarios}(id) ON DELETE CASCADE");
             $this->check_dbdelta_errors($table_vuelos . '_fk_itinerario');
        } else {
             error_log("TIC DB: FK vuelos -> itinerarios ya existe.");
        }

        // FK: vuelos_escalas -> vuelos
         if (!$this->foreign_key_exists($table_vuelos_escalas, 'fk_escala_vuelo')) {
            error_log("TIC DB: Añadiendo FK vuelos_escalas -> vuelos");
            // Antes de añadir, asegurarse que la columna referenciada NO ES NULL si la FK lo requiere
            $this->wpdb->query("ALTER TABLE {$table_vuelos_escalas} MODIFY COLUMN vuelo_id BIGINT(20) UNSIGNED NOT NULL");
            $this->wpdb->query("ALTER TABLE {$table_vuelos_escalas} ADD CONSTRAINT fk_escala_vuelo FOREIGN KEY (vuelo_id) REFERENCES {$table_vuelos}(id) ON DELETE CASCADE");
            $this->check_dbdelta_errors($table_vuelos_escalas . '_fk_vuelo');
         } else {
             error_log("TIC DB: FK vuelos_escalas -> vuelos ya existe.");
         }

        // FK: alojamientos -> itinerarios
            if (!$this->foreign_key_exists($table_alojamientos, 'fk_alojamiento_itinerario')) {
                error_log("TIC DB: Añadiendo FK alojamientos -> itinerarios");
                // Asegurarse que la columna referenciada NO ES NULL si la FK lo requiere
                $this->wpdb->query("ALTER TABLE {$table_alojamientos} MODIFY COLUMN itinerario_id BIGINT(20) UNSIGNED NOT NULL");
                $this->wpdb->query("ALTER TABLE {$table_alojamientos} ADD CONSTRAINT fk_alojamiento_itinerario FOREIGN KEY (itinerario_id) REFERENCES {$table_itinerarios}(id) ON DELETE CASCADE");
                $this->check_dbdelta_errors($table_alojamientos . '_fk_itinerario');
            } else {
                error_log("TIC DB: FK alojamientos -> itinerarios ya existe.");
            }
        // FK: actividades -> itinerarios
            if (!$this->foreign_key_exists($table_actividades, 'fk_actividad_itinerario')) {
                error_log("TIC DB: Añadiendo FK actividades -> itinerarios");
                $this->wpdb->query("ALTER TABLE {$table_actividades} MODIFY COLUMN itinerario_id BIGINT(20) UNSIGNED NOT NULL");
                $this->wpdb->query("ALTER TABLE {$table_actividades} ADD CONSTRAINT fk_actividad_itinerario FOREIGN KEY (itinerario_id) REFERENCES {$table_itinerarios}(id) ON DELETE CASCADE");
                $this->check_dbdelta_errors($table_actividades . '_fk_itinerario');
            } else {
                error_log("TIC DB: FK actividades -> itinerarios ya existe.");
            }

        error_log("TIC DB: Proceso de creación/actualización de tablas completado.");
    }


    /**
     * Obtiene el nombre completo de la tabla con el prefijo.
     * @param string $table_base Nombre base de la tabla (ej: 'itinerarios')
     * @return string Nombre completo de la tabla (ej: 'wp_tic_itinerarios')
     */
    public function get_table_name($table_base) {
        // Sanitización básica del nombre base
        $table_base = preg_replace('/[^a-zA-Z0-9_]/', '', $table_base);
        return $this->table_prefix . $table_base;
    }

    /**
     * Registra errores de $wpdb si existen después de una operación.
     * @param string $context Contexto para el mensaje de error.
     */
    private function check_dbdelta_errors($context = '') {
        if (!empty($this->wpdb->last_error)) {
            // Usar error_log para que vaya al debug.log de PHP/WordPress
            error_log("TIC DB Error ($context): " . $this->wpdb->last_error);
            // Opcional: Mostrar el último query para depuración profunda
            // error_log("Last Query ($context): " . $this->wpdb->last_query);
        }
         // Nota: dbDelta también puede devolver mensajes útiles en su array de retorno.
         // Se podrían capturar y loggear si fuera necesario: $results = dbDelta(...); error_log(print_r($results, true));
    }

    /**
     * Verifica si ya existe una clave foránea con un nombre específico.
     * NOTA: Requiere que las FKs tengan nombres predecibles/consistentes.
     *
     * @param string $table_name Nombre de la tabla que tiene la FK.
     * @param string $constraint_name Nombre de la restricción FK (ej: 'fk_vuelo_itinerario').
     * @return bool True si existe, False si no.
     */
    private function foreign_key_exists($table_name, $constraint_name) {
         $check_sql = $this->wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            DB_NAME, $table_name, $constraint_name
        );
        $result = $this->wpdb->get_var($check_sql);
        // error_log("Check FK وجود {$constraint_name} on {$table_name}: " . ($result ? 'Exists' : 'Not Found')); // Debug Log
        return !empty($result);
    }

    // Resto de tus métodos CRUD irían aquí...
}