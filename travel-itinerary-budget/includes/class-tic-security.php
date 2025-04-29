<?php

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

class TIC_Security {

    public function __construct() {
        // No es necesario inicializar nada por ahora
    }

    /**
     * Sanitiza una cadena de texto.
     *
     * @param string $text La cadena de texto a sanitizar.
     * @return string La cadena de texto sanitizada.
     */
    public function sanitize_text($text) {
        return sanitize_text_field($text);
    }

    /**
     * Sanitiza un número entero.
     *
     * @param int|string $number El número a sanitizar.
     * @return int El número sanitizado o 0 si no es válido.
     */
    public function sanitize_integer($number) {
        return absint(intval($number));
    }

    /**
     * Sanitiza un número decimal.
     *
     * @param float|string $decimal El número decimal a sanitizar.
     * @return float El número decimal sanitizado o 0.0 si no es válido.
     */
    public function sanitize_decimal($decimal) {
        return filter_var($decimal, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitiza una fecha y hora.
     *
     * @param string $datetime La fecha y hora a sanitizar (formato esperado YYYY-MM-DDTHH:MM).
     * @return string La fecha y hora formateada como YYYY-MM-DD HH:MM:SS o una cadena vacía si no es válida.
     */
    public function sanitize_datetime($datetime) {
        // Intentar parsear el formato de datetime-local (YYYY-MM-DDTHH:MM)
        $parsed_datetime = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);

        if ($parsed_datetime) {
            // Si se parseó correctamente, formatear para MySQL (YYYY-MM-DD HH:MM:SS)
            return $parsed_datetime->format('Y-m-d H:i:s');
        }
        // Si no se pudo parsear, devolver cadena vacía
        return '';
    }

    /**
     * Valida si un valor es una moneda válida (código de 3 letras).
     *
     * @param string $currency El código de moneda a validar.
     * @return string El código de moneda validado en mayúsculas o una cadena vacía si no es válido.
     */
    public function validate_currency($currency) {
        $currency = strtoupper($this->sanitize_text($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency)) {
            return $currency;
        }
        return '';
    }

    /**
     * Valida si un valor es un código de reserva válido (alfanumérico).
     *
     * @param string $reservation_code El código de reserva a validar.
     * @return string El código de reserva validado o una cadena vacía si no es válido.
     */
    public function validate_reservation_code($reservation_code) {
        return $this->sanitize_text($reservation_code); // Permitimos alfanumérico y otros caracteres comunes
    }

    /**
     * Verifica un nonce para protección contra CSRF.
     *
     * @param string $action El nombre de la acción del nonce.
     * @param string $nonce El valor del nonce enviado.
     * @return bool True si el nonce es válido, false en caso contrario.
     */
    public function verify_nonce($action, $nonce) {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Crea un nonce para protección contra CSRF.
     *
     * @param string $action El nombre de la acción del nonce.
     * @return string El nonce generado.
     */
    public function create_nonce($action) {
        return wp_create_nonce($action);
    }
}