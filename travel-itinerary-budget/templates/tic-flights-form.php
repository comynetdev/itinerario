<?php

/**
 * Template for displaying the flights form.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

global $tic_security;

// Obtener el ID pasado por set_query_var
//$itinerario_id = get_query_var('tic_itinerario_id_form', 0);
error_log("templates/tic-flights-form.php - Valor de \$itinerario_id disponible: " . print_r(isset($itinerario_id) ? $itinerario_id : 'NO ESTABLECIDA', true));
?>

<h3>Información de Vuelo</h3>

<form id="tic-flights-form" class="tic-form-content">
    <?php wp_nonce_field('tic_guardar_vuelo_nonce', 'nonce'); ?>
    <input type="hidden" name="action" value="tic_guardar_vuelo">
    <input type="hidden" name="itinerario_id" value="<?php echo esc_attr(isset($itinerario_id) ? $itinerario_id : '0'); ?>">
                                                        
    <input type="hidden" name="editing_flight_id" id="editing_flight_id" value="">

    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="origen">Origen:</label>
            <input type="text" id="origen" name="origen" required>
        </div>

        <div class="tic-form-group">
            <label for="destino">Destino:</label>
            <input type="text" id="destino" name="destino" required>
        </div>

        <div class="tic-form-group">
            <label for="linea_aerea">Línea Aérea:</label>
            <input type="text" id="linea_aerea" name="linea_aerea">
        </div>
    </div>
    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="numero_vuelo">Número de Vuelo:</label>
            <input type="text" id="numero_vuelo" name="numero_vuelo">
        </div>

        <div class="tic-form-group">
            <label for="fecha_hora_salida">Fecha y Hora de Salida:</label>
            <input type="datetime-local" id="fecha_hora_salida" name="fecha_hora_salida">
        </div>

        <div class="tic-form-group">
            <label for="fecha_hora_llegada">Fecha y Hora de Llegada:</label>
            <input type="datetime-local" id="fecha_hora_llegada" name="fecha_hora_llegada">
        </div>
    </div>

    <div class="tic-form-group">
        <label for="tiene_escalas">¿Tiene escalas?</label>
        <input type="checkbox" id="tiene_escalas" name="tiene_escalas">
    </div>

    <div id="escalas-container" style="display: none;">
        <h4>Escalas</h4>
        <div id="escalas-wrapper">
        </div>
        <button type="button" id="add-escala-btn" class="button">Agregar Escala</button>
    </div>
    </div>
    <!-- Campos de escala hardcodeados eliminados. Se manejan dinámicamente con JS -->

    <div class="tic-form-section">
        <div class="tic-form-group">
            <label for="precio_persona">Precio por Persona:</label>
            <input type="number" id="precio_persona" name="precio_persona" step="0.01" required>
        </div>

        <div class="tic-form-group">
            <label for="moneda_precio">Moneda del Precio:</label>
            <input type="text" id="moneda_precio" name="moneda_precio" value="USD" maxlength="3" required>
        </div>

        <div class="tic-form-group">
            <label for="numero_personas">Número de Personas:</label>
            <input type="number" id="numero_personas" name="numero_personas" value="1" min="1" required>
        </div>

        <div class="tic-form-group">
            <label for="tipo_de_cambio">Tipo de Cambio:</label>
            <input type="number" id="tipo_de_cambio" name="tipo_de_cambio" step="0.01" value="1.00" required>
        </div>

        <div class="tic-form-group">
            <label for="codigo_reserva">Código de Reserva:</label>
            <input type="text" id="codigo_reserva" name="codigo_reserva">
        </div>

        <div class="tic-form-group">
            <strong>Costo Total Estimado:</br></strong>
            <span id="flight_form_calculated_total_display" style="font-weight: bold;">0.00</span>
            <span id="flight_form_report_currency_code_display" style="font-weight: bold;">[MONEDA]</span>
            <?php // El código [MONEDA] se actualizará con JS 
            ?>
        </div>

    </div>
    <div class="tic-form-group">
        <p class="tic-notice" style="font-size: 0.9em;">
            <em>Nota: El Precio Total del Vuelo se calculará y guardará en la Moneda de Reporte de su itinerario (<strong id="flight_form_report_currency_label">[MONEDA]</strong>).
                Si el 'Precio por Persona' está en una divisa diferente ('Moneda del Precio'), asegúrese de que el 'Tipo de Cambio' sea el correcto para la conversión.</em>
        </p>
    </div>
    <button type="button" id="tic-guardar-vuelo-btn" class="button button-primary">Guardar Vuelo</button>
</form>

<div id="tic-flights-report-container">
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // --- INICIO NUEVO JS PARA CÁLCULO DINÁMICO Y ANOTACIÓN DE MONEDA ---
        var $precioPersonaInput = $('#precio_persona');
        var $numeroPersonasInput = $('#numero_personas');
        var $monedaPrecioInput = $('#moneda_precio'); // <-- Asegúrate que este ID exista en tu HTML
        var $tipoCambioInput = $('#tipo_de_cambio');
        var $totalDisplay = $('#flight_form_calculated_total_display');
        var $currencyLabelReport = $('#flight_form_report_currency_label');
        var $currencyCodeDisplay = $('#flight_form_report_currency_code_display');

        // Obtener la moneda de reporte del itinerario actual (del input oculto en el dashboard principal)
        // Esta línea asume que el formulario de vuelos se carga DENTRO de la página del dashboard.
        var reportCurrency = $('#current_itinerary_currency', window.parent.document).val() || 'USD'; // Default a USD si no se encuentra

        var $parentDocCurrentItineraryCurrency = $('#current_itinerary_currency', window.parent.document);
        if ($parentDocCurrentItineraryCurrency.length) {
            reportCurrency = $parentDocCurrentItineraryCurrency.val() || 'USD';
        }
        // Actualizar las etiquetas de moneda en la anotación y en el display del total
        $currencyLabelReport.text(reportCurrency);
        $currencyCodeDisplay.text(reportCurrency);

        function calculateAndUpdateFlightTotal() {
            var precioPersona = parseFloat($precioPersonaInput.val()) || 0;
            var numeroPersonas = parseInt($numeroPersonasInput.val()) || 0;
            var tipoCambio = parseFloat($tipoCambioInput.val()) || 0;

            if (precioPersona > 0 && numeroPersonas > 0 && tipoCambio > 0) {
                var totalCalculado = (precioPersona * numeroPersonas) * tipoCambio;
                $totalDisplay.text(totalCalculado.toFixed(2));
            } else {
                $totalDisplay.text('0.00');
            }
        }

        // --- INICIO NUEVA FUNCIÓN Y LÓGICA PARA TIPO DE CAMBIO ---
        function manageExchangeRateField() {
            var monedaPrecioVal = ($monedaPrecioInput.val() || '').toUpperCase().trim();
            var reportCurrencyVal = (reportCurrency || 'USD').toUpperCase().trim();

            if (monedaPrecioVal && monedaPrecioVal === reportCurrencyVal) {
                $tipoCambioInput.val('1.00').prop('readonly', true).trigger('change'); // trigger change para recalcular total
            } else {
                $tipoCambioInput.prop('readonly', false);
                // Opcional: si quieres limpiar el tipo de cambio cuando las monedas difieren
                // if ($tipoCambioInput.val() === '1.00' || $tipoCambioInput.val() === '1') {
                //    $tipoCambioInput.val(''); // O poner un valor por defecto si tienes alguno
                // }
                // O simplemente dejar que el usuario lo edite. Si estaba en 1.00, que lo cambie.
            }
            // Volver a calcular el total ya que tipo_de_cambio pudo cambiar o su editabilidad
            // calculateAndUpdateFlightTotal(); // Se llama por el .trigger('change') en $tipoCambioInput
        }

        // Llamar a manageExchangeRateField cuando cambie la moneda del precio
        $monedaPrecioInput.on('change keyup input', function() {
            manageExchangeRateField();
            calculateAndUpdateFlightTotal(); // Asegurar que el total se recalcule también
        });

        // También cuando cambie el tipo de cambio (por si el usuario lo cambia manualmente y luego las monedas coinciden)
        // y para los otros campos que afectan el total.
        $precioPersonaInput.on('input change', calculateAndUpdateFlightTotal);
        $numeroPersonasInput.on('input change', calculateAndUpdateFlightTotal);
        $tipoCambioInput.on('input change', function() {
            // Si el usuario edita manualmente el tipo de cambio y las monedas eran iguales,
            // no queremos que se vuelva readonly inmediatamente, pero el cálculo sí debe ocurrir.
            calculateAndUpdateFlightTotal();
        });


        // Llamar a ambas funciones una vez al cargar el formulario para el estado inicial
        manageExchangeRateField(); // Esto establecerá el estado de tipo_de_cambio y llamará a calculateAndUpdateFlightTotal
        // calculateAndUpdateFlightTotal(); // Ya es llamada por manageExchangeRateField si esta hace trigger

        // --- FIN NUEVA FUNCIÓN Y LÓGICA ---
        $('#tiene_escalas').change(function() {
            if (this.checked) {
                $('#escalas-container').slideDown();
            } else {
                $('#escalas-container').slideUp();
            }
        });

        $('#tic-guardar-vuelo-btn').on('click', function(e) {
            e.preventDefault();
            // Log the value from the hidden field *inside this form* just before sending
            var formItineraryId = $('#tic-flights-form input[name="itinerario_id"]').val();
            console.log('Form itinerary_id value before serialize:', formItineraryId);

            var formData = $('#tic-flights-form').serialize();
            console.log('Serialized form data:', formData); // Log serialized data

            $.ajax({
                url: tic_ajax_object.ajaxurl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#tic-flights-form')[0].reset(); // Limpiar el formulario
                        $('#escalas-container').slideUp(); // Ocultar las escalas
                        // Recargar el reporte de vuelos
                        $.post(tic_ajax_object.ajaxurl, {
                            action: 'tic_mostrar_reporte_vuelos', // Corregido: sin prefijo wp_ajax_
                            itinerario_id: $('input[name="itinerario_id"]').val(),
                            nonce: tic_ajax_object.load_module_nonce // Reutilizamos el nonce de cargar módulo

                        }, function(reporte) {
                            $('#tic-flights-report-container').html(reporte);
                        });
                        alert(response.data.message); // Corregido: acceder a message
                    } else {
                        alert('Error: ' + (response.data.message || 'Error desconocido')); // Corregido: acceder a message
                    }
                },
                error: function(errorThrown) {
                    console.log('Error en la petición AJAX:', errorThrown);
                    alert('Hubo un error al guardar el vuelo.');
                }
            });
        });

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

                    // Aquí, más tarde, recargaremos el reporte de alojamientos
                    // Por ejemplo:
                    // var currentItineraryId = $form.find('input[name="itinerario_id"]').val();
                    // cargarReporteAlojamientos(currentItineraryId);
                    console.log('Alojamiento guardado, ID (si aplica):', response.data.accommodation_id);

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