<?php

/**
 * Template for displaying the Activities form.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

$itinerario_id = get_query_var('tic_itinerario_id_form_actividad', 0);
?>

<h3>Información de Actividad/Tour</h3>

<form id="tic-activities-form" class="tic-form-content">
    <?php wp_nonce_field('tic_guardar_actividad_action', 'tic_activity_nonce'); ?>
    <input type="hidden" name="action" value="tic_guardar_actividad"> <input type="hidden" name="itinerario_id" value="<?php echo esc_attr($itinerario_id); ?>">
    <input type="hidden" name="editing_activity_id" id="editing_activity_id" value="">
    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_act_pais">País:</label>
            <input type="text" id="tic_act_pais" name="pais">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_ciudad_poblacion">Ciudad/Población:</label>
            <input type="text" id="tic_act_ciudad_poblacion" name="ciudad_poblacion">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_fecha_actividad">Fecha de la Actividad:</label>
            <input type="datetime-local" id="tic_act_fecha_actividad" name="fecha_actividad">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_nombre_tour_actividad">Nombre del Tour/Actividad:</label>
            <input type="text" id="tic_act_nombre_tour_actividad" name="nombre_tour_actividad" required>
        </div>
    </div>

    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_act_precio_persona">Precio por Persona:</label>
            <input type="number" id="tic_act_precio_persona" name="precio_persona" step="0.01" min="0">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_numero_personas">Número de Personas:</label>
            <input type="number" id="tic_act_numero_personas" name="numero_personas" value="1" min="1">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_moneda_precio">Moneda del Precio:</label>
            <input type="text" id="tic_act_moneda_precio" name="moneda_precio_actividad" maxlength="3" placeholder="Ej: USD">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_tipo_de_cambio">Tipo de Cambio:</label>
            <input type="number" id="tic_act_tipo_de_cambio" name="tipo_de_cambio_actividad" step="0.0001" value="1.0000" min="0">
        </div>
    </div>
    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_act_proveedor_reserva">Proveedor/Sitio de Reserva:</label>
            <input type="text" id="tic_act_proveedor_reserva" name="proveedor_reserva" placeholder="Ej: GetYourGuide, Viator">
        </div>
        <div class="tic-form-group">
            <label for="tic_act_codigo_reserva">Código de Reserva:</label>
            <input type="text" id="tic_act_codigo_reserva" name="codigo_reserva">
        </div>
        <div class="tic-form-group">
            <strong>Costo Total Estimado:</br></strong>
            <span id="activity_form_calculated_total_display" style="font-weight: bold;">0.00</span>
            <span id="activity_form_report_currency_code_display" style="font-weight: bold;">[MONEDA]</span>
        </div>
    </div>
    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="tic_act_notas">Notas Adicionales:</label>
            <textarea id="tic_act_notas" name="notas" rows="4"></textarea>
        </div>
    </div>

    <div class="tic-form-section" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
        <p class="tic-notice" style="font-size: 0.9em;">
            <em>Nota: El Costo Total de la Actividad se calculará y guardará en la Moneda de Reporte de su itinerario (<strong id="activity_form_report_currency_label">[MONEDA]</strong>).
                Si el 'Precio por Persona' está en una divisa diferente, asegúrese de que el 'Tipo de Cambio' sea el correcto.</em>
        </p>
    </div>

    <div class="tic-form-group" style="margin-top:20px;">
        <button type="button" id="tic-guardar-actividad-btn" class="button button-primary">Guardar Actividad</button>
    </div>
</form>

<div id="tic-activities-report-container" style="margin-top: 20px;">
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Selectores para los campos del formulario de Actividades
        var $precioPersonaActInput = $('#tic_act_precio_persona');
        var $numeroPersonasActInput = $('#tic_act_numero_personas');
        var $monedaPrecioActInput = $('#tic_act_moneda_precio');
        var $tipoCambioActInput = $('#tic_act_tipo_de_cambio');

        var $totalActDisplay = $('#activity_form_calculated_total_display');
        var $currencyLabelActReport = $('#activity_form_report_currency_label');
        var $currencyCodeActDisplay = $('#activity_form_report_currency_code_display');

        var reportCurrencyAct = 'USD'; // Default

        // Obtener la Moneda de Reporte del itinerario actual desde el dashboard
        var $parentDocCurrentItineraryCurrencyAct = $('#current_itinerary_currency', window.parent.document);
        if ($parentDocCurrentItineraryCurrencyAct.length) {
            reportCurrencyAct = $parentDocCurrentItineraryCurrencyAct.val() || 'USD';
        }
        $currencyLabelActReport.text(reportCurrencyAct);
        $currencyCodeActDisplay.text(reportCurrencyAct);

        // Función para calcular y actualizar el total de la actividad
        function calculateAndUpdateActivityTotal() {
            var precioPersona = parseFloat($precioPersonaActInput.val()) || 0;
            var numeroPersonas = parseInt($numeroPersonasActInput.val()) || 0;
            var tipoCambio = parseFloat($tipoCambioActInput.val()) || 0;

            if (precioPersona > 0 && numeroPersonas > 0 && tipoCambio > 0) {
                var totalCalculado = (precioPersona * numeroPersonas) * tipoCambio;
                $totalActDisplay.text(totalCalculado.toFixed(2));
            } else {
                $totalActDisplay.text('0.00');
            }
        }

        // Función para gestionar el campo de tipo de cambio
        function manageActivityExchangeRateField() {
            var monedaPrecioVal = ($monedaPrecioActInput.val() || '').toUpperCase().trim();
            var reportCurrencyVal = (reportCurrencyAct || 'USD').toUpperCase().trim();

            if (monedaPrecioVal && monedaPrecioVal === reportCurrencyVal) {
                $tipoCambioActInput.val('1.0000').prop('readonly', true).trigger('input');
            } else {
                $tipoCambioActInput.prop('readonly', false);
            }
            // No es necesario llamar a calculateAndUpdateActivityTotal() aquí si .trigger('input') lo hace.
        }

        // Event listeners para los campos relevantes
        $precioPersonaActInput.on('input change', calculateAndUpdateActivityTotal);
        $numeroPersonasActInput.on('input change', calculateAndUpdateActivityTotal);
        $monedaPrecioActInput.on('change keyup input', function() {
            manageActivityExchangeRateField();
            calculateAndUpdateActivityTotal(); // Asegurar recálculo
        });
        $tipoCambioActInput.on('input change', calculateAndUpdateActivityTotal);

        // Llamadas iniciales al cargar el formulario (o cuando se inyecta este script)
        manageActivityExchangeRateField();
        calculateAndUpdateActivityTotal();

        $('#tic-guardar-actividad-btn').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $form = $('#tic-activities-form');
            var formData = $form.serialize();

            console.log('Datos del formulario de actividad a enviar:', formData);
            $button.prop('disabled', true).text('Guardando...');

            $.post(tic_ajax_object.ajaxurl, formData, function(response) {
                if (response.success) {
                    alert(response.data.message || '¡Operación de actividad exitosa!');
                    $form[0].reset();

                    // --- Resetear estado de edición ---
                    $('#editing_activity_id').val('');
                    $button.text('Guardar Actividad'); // Asumiendo que $button es referencia al botón de guardar
                    // --- Fin Resetear ---

                    // Resetear también el display del total y el campo de tipo de cambio
                    if (typeof calculateAndUpdateActivityTotal === "function") { // Verificar si la función existe
                        calculateAndUpdateActivityTotal();
                    }
                    if (typeof manageActivityExchangeRateField === "function") { // Verificar si la función existe
                        manageActivityExchangeRateField();
                    }

                    console.log('Actividad guardada, ID (si aplica):', response.data.activity_id);

                    // --- INICIO CARGAR REPORTE DESPUÉS DE GUARDAR ---
                    var currentItineraryId = $form.find('input[name="itinerario_id"]').val();
                    var $reportContainer = $('#tic-activities-report-container'); // Contenedor en este mismo archivo
                    $reportContainer.html('<p>Actualizando reporte de actividades...</p>').show();

                    var isLoggedIn = tic_ajax_object.is_user_logged_in;

                    if ((isLoggedIn && parseInt(currentItineraryId) > 0) || (!isLoggedIn && currentItineraryId === 'temp')) {
                        $.post(tic_ajax_object.ajaxurl, {
                            action: 'tic_mostrar_reporte_actividad', // Nueva acción
                            itinerario_id: currentItineraryId,
                            nonce: tic_ajax_object.load_module_nonce
                        }, function(reporteHtml) {
                            $reportContainer.html(reporteHtml);
                            console.log('Reporte de actividades recargado después de guardar.');
                        }).fail(function() {
                            $reportContainer.html('<p class="tic-error">Error al recargar el reporte de actividades.</p>');
                        });
                    } else {
                        $reportContainer.html(''); // Limpiar si no hay ID válido
                    }
                    // --- FIN CARGAR REPORTE DESPUÉS DE GUARDAR ---

                } else {
                    alert('Error: ' + (response.data.message || 'No se pudo guardar la actividad.'));
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error("Error AJAX al guardar actividad:", status, error, xhr.responseText);
                alert('Error de comunicación al guardar la actividad.');
            }).always(function() {
                $button.prop('disabled', false).text('Guardar Actividad');
            });
        });
    });
</script>