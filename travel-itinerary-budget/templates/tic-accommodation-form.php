<?php

/**
 * Template for displaying the Accommodation form.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

// Obtener el ID del itinerario pasado desde la clase TIC_Accommodation
// (a través de set_query_var('tic_itinerario_id_form_alojamiento', $itinerario_id))
$itinerario_id = get_query_var('tic_itinerario_id_form_alojamiento', 0);
// Obtener el nonce si se pasó para el guardado del formulario (lo haremos más adelante)
// $nonce_guardar = get_query_var('tic_nonce_guardar_alojamiento', '');
?>
<h3>Información de Alojamiento</h3>

<form id="tic-accommodation-form" class="tic-form-content">
    <?php
    // Nonce para la acción de guardar alojamiento. Lo crearemos en la clase al mostrar el form.
    // Por ahora, un placeholder o puedes generarlo aquí si prefieres.
    // Lo ideal es generarlo en el PHP que llama a esta plantilla y pasarlo.
    // Para este ejemplo, lo pongo directamente.
    wp_nonce_field('tic_guardar_alojamiento_action', 'tic_accommodation_nonce');
    ?>
    <input type="hidden" name="action" value="tic_guardar_alojamiento">
    <input type="hidden" name="itinerario_id" value="<?php echo esc_attr($itinerario_id); ?>">
    <input type="hidden" name="editing_accommodation_id" id="editing_accommodation_id" value="">
    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_acc_pais">País:</label>
            <input type="text" id="tic_acc_pais" name="pais" required>
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_ciudad_poblacion">Ciudad/Población:</label>
            <input type="text" id="tic_acc_ciudad_poblacion" name="ciudad_poblacion" required>
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_hotel_hospedaje">Hotel/Hospedaje:</label>
            <input type="text" id="tic_acc_hotel_hospedaje" name="hotel_hospedaje" required>
        </div>
    </div>

    <div class="tic-form-group">
        <label for="tic_acc_direccion_hotel">Dirección del Hotel:</label>
        <textarea id="tic_acc_direccion_hotel" name="direccion_hotel" rows="3"></textarea>
    </div>

    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_acc_fecha_entrada">Fecha de Entrada:</label>
            <input type="datetime-local" id="tic_acc_fecha_entrada" name="fecha_entrada">
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_fecha_salida">Fecha de Salida:</label>
            <input type="datetime-local" id="tic_acc_fecha_salida" name="fecha_salida">
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_precio_noche">Precio por Noche:</label>
            <input type="number" id="tic_acc_precio_noche" name="precio_noche" step="0.01" min="0">
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_moneda_precio_noche">Moneda Precio/Noche:</label>
            <input type="text" id="tic_acc_moneda_precio_noche" name="moneda_precio_noche" maxlength="3" placeholder="Ej: USD">
        </div>
        <div class="tic-form-group">
            <label for="tic_acc_tipo_de_cambio">Tipo de Cambio:</label>
            <input type="number" id="tic_acc_tipo_de_cambio" name="tipo_de_cambio_alojamiento" step="0.0001" value="1.0000" min="0">
        </div>
    </div>

    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_acc_fecha_pago_reserva">Fecha de Pago de Reserva:</label>
            <input type="date" id="tic_acc_fecha_pago_reserva" name="fecha_pago_reserva">
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_codigo_reserva">Código de Reserva:</label>
            <input type="text" id="tic_acc_codigo_reserva" name="codigo_reserva">
        </div>

        <div class="tic-form-group">
            <label for="tic_acc_aplicacion_pago_reserva">Aplicación/Sitio de Reserva:</label>
            <input type="text" id="tic_acc_aplicacion_pago_reserva" name="aplicacion_pago_reserva" placeholder="Ej: Expedia, Booking.com">
        </div>
        <div class="tic-form-group">
            <strong>Costo Total Estimado:</br></strong>
            <span id="accommodation_form_calculated_total_display" style="font-weight: bold;">0.00</span>
            <span id="accommodation_form_report_currency_code_display" style="font-weight: bold;">[MONEDA]</span>
        </div>
    </div>
    <p class="tic-notice" style="font-size: 0.9em;">
        <em>Nota: El Costo Total del Alojamiento se calculará y guardará en la Moneda de Reporte de su itinerario (<strong id="accommodation_form_report_currency_label">[MONEDA]</strong>).
            Si el 'Precio por Noche' está en una divisa diferente ('Moneda Precio/Noche'), asegúrese de que el 'Tipo de Cambio' sea el correcto.</em>
    </p>

    <div class="tic-form-group">
        <button type="button" id="tic-guardar-alojamiento-btn" class="button button-primary">Guardar Alojamiento</button>
    </div>
</form>

<div id="tic-accommodation-report-container" style="margin-top: 20px;">
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {

        // --- INICIO NUEVO JS PARA CÁLCULO DINÁMICO Y TIPO DE CAMBIO ---
        var $fechaEntradaInput = $('#tic_acc_fecha_entrada');
        var $fechaSalidaInput = $('#tic_acc_fecha_salida');
        var $precioNocheInput = $('#tic_acc_precio_noche');
        var $monedaPrecioNocheInput = $('#tic_acc_moneda_precio_noche');
        var $tipoCambioAlojamientoInput = $('#tic_acc_tipo_de_cambio'); // ID del input de tipo de cambio

        var $totalDisplay = $('#accommodation_form_calculated_total_display');
        var $currencyLabelReport = $('#accommodation_form_report_currency_label');
        var $currencyCodeDisplay = $('#accommodation_form_report_currency_code_display');

        var reportCurrency = 'USD'; // Default
        var $parentDocCurrentItineraryCurrency = $('#current_itinerary_currency', window.parent.document);
        if ($parentDocCurrentItineraryCurrency.length) {
            reportCurrency = $parentDocCurrentItineraryCurrency.val() || 'USD';
        }
        $currencyLabelReport.text(reportCurrency);
        $currencyCodeDisplay.text(reportCurrency);

        function calculateNights() {
            var fechaEntradaStr = $fechaEntradaInput.val();
            var fechaSalidaStr = $fechaSalidaInput.val();
            var numNoches = 0;
            if (fechaEntradaStr && fechaSalidaStr) {
                try {
                    // Tomar solo la parte de la fecha para el cálculo de noches
                    var entrada_dt = new Date(fechaEntradaStr.substring(0, 10));
                    var salida_dt = new Date(fechaSalidaStr.substring(0, 10));

                    if (salida_dt > entrada_dt) {
                        var diffTime = Math.abs(salida_dt - entrada_dt);
                        numNoches = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    }
                } catch (e) {
                    console.error("Error calculando noches:", e);
                }
            }
            return numNoches;
        }

        function calculateAndUpdateAccommodationTotal() {
            var numNoches = calculateNights();
            var precioNoche = parseFloat($precioNocheInput.val()) || 0;
            var tipoCambio = parseFloat($tipoCambioAlojamientoInput.val()) || 0;

            if (numNoches > 0 && precioNoche > 0 && tipoCambio > 0) {
                var totalCalculado = (precioNoche * numNoches) * tipoCambio;
                $totalDisplay.text(totalCalculado.toFixed(2));
            } else {
                $totalDisplay.text('0.00');
            }
        }

        function manageAccommodationExchangeRateField() {
            var monedaPrecioVal = ($monedaPrecioNocheInput.val() || '').toUpperCase().trim();
            var reportCurrencyVal = (reportCurrency || 'USD').toUpperCase().trim();

            if (monedaPrecioVal && monedaPrecioVal === reportCurrencyVal) {
                $tipoCambioAlojamientoInput.val('1.0000').prop('readonly', true).trigger('input'); // Usar 'input' para que otros listeners reaccionen
            } else {
                $tipoCambioAlojamientoInput.prop('readonly', false);
            }
            // Siempre recalcular total cuando la moneda o el tipo de cambio puedan cambiar
            // calculateAndUpdateAccommodationTotal(); // Se llama por el .trigger('input') en $tipoCambioAlojamientoInput si se modifica
        }

        // Event listeners
        $fechaEntradaInput.on('change input', calculateAndUpdateAccommodationTotal);
        $fechaSalidaInput.on('change input', calculateAndUpdateAccommodationTotal);
        $precioNocheInput.on('input change', calculateAndUpdateAccommodationTotal);
        $monedaPrecioNocheInput.on('change keyup input', function() {
            manageAccommodationExchangeRateField(); // Esto puede cambiar el tipo de cambio y disparar su propio 'input'
            calculateAndUpdateAccommodationTotal(); // Asegurar recálculo
        });
        $tipoCambioAlojamientoInput.on('input change', calculateAndUpdateAccommodationTotal);


        // Llamadas iniciales al cargar el formulario/script
        manageAccommodationExchangeRateField(); // Establece estado inicial de tipo de cambio
        calculateAndUpdateAccommodationTotal(); // Calcula total inicial

        // --- FIN NUEVO JS PARA CÁLCULO DINÁMICO ---

        $('#tic-guardar-alojamiento-btn').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $form = $('#tic-accommodation-form');
            var formData = $form.serialize(); // Obtiene todos los datos del formulario

            console.log('Datos del formulario de alojamiento a enviar:', formData);

            // Deshabilitar botón para prevenir múltiples envíos
            $button.prop('disabled', true).text('Guardando...');

            $.post(tic_ajax_object.ajaxurl, formData, function(response) {
                if (response.success) {
                    alert(response.data.message || '¡Alojamiento guardado!');
                    $form[0].reset(); // Limpiar el formulario

                    // --- NUEVO: Resetear estado de edición ---
                    $('#editing_accommodation_id').val(''); // Limpiar el ID de edición
                    console.log('Alojamiento guardado, ID (si aplica):', response.data.accommodation_id);
                    // --- FIN NUEVO ---
                    // --- INICIO NUEVO CÓDIGO PARA CARGAR REPORTE ---
                    var currentItineraryId = $form.find('input[name="itinerario_id"]').val();
                    // Asegurarse de que el contenedor del reporte esté visible o limpiarlo
                    var $reportContainer = $('#tic-accommodation-report-container');
                    $reportContainer.html('<p>Actualizando reporte...</p>').show();

                    // ***** USA LA VARIABLE CORRECTA AQUÍ *****
                    var isLoggedIn = tic_ajax_object.is_user_logged_in; // Utilizar la variable localizada

                    if ((isLoggedIn && parseInt(currentItineraryId) > 0) || (!isLoggedIn && currentItineraryId === 'temp')) {
                        $.post(tic_ajax_object.ajaxurl, {
                            action: 'tic_mostrar_reporte_alojamiento',
                            itinerario_id: currentItineraryId,
                            nonce: tic_ajax_object.load_module_nonce
                        }, function(reporteHtml) {
                            $reportContainer.html(reporteHtml);
                            console.log('Reporte de alojamiento recargado después de guardar/actualizar.');
                        }).fail(function() {
                            $reportContainer.html('<p class="tic-error">Error al recargar el reporte de alojamiento.</p>');
                        });
                    } else {
                        console.warn('No se recargará el reporte. CurrentItineraryId:', currentItineraryId, 'Logged in:', isLoggedIn);
                        $reportContainer.html('<p class="tic-notice">No se puede cargar el reporte (ID de itinerario o estado de sesión no válido).</p>');
                    }
                    // --- FIN NUEVO CÓDIGO PARA CARGAR REPORTE ---


                } else {
                    alert('Error: ' + (response.data.message || 'No se pudo guardar el alojamiento.'));
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error("Error AJAX al guardar alojamiento:", status, error, xhr.responseText);
                alert('Error de comunicación al guardar el alojamiento.');
            }).always(function() {
                // Rehabilitar botón
                $button.prop('disabled', false).text('Guardar Alojamiento');
            });
        });
    });
</script>