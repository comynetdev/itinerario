<?php
/**
 * Template for displaying the flights form.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

global $tic_security;

// Obtener el ID pasado por set_query_var
$itinerario_id = get_query_var('tic_itinerario_id_form', 0);

?>

<h3>Información de Vuelo</h3>

<form id="tic-flights-form">
    <?php wp_nonce_field('tic_guardar_vuelo_nonce', 'nonce'); ?>
    <input type="hidden" name="action" value="tic_guardar_vuelo">
    <input type="hidden" name="itinerario_id" value="<?php echo esc_attr($itinerario_id); // Ahora usa el valor de get_query_var ?>">

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

    <div class="tic-form-group">
        <label for="precio_persona">Precio por Persona:</label>
        <input type="number" id="precio_persona" name="precio_persona" step="0.01" required>
    </div>

    <div class="tic-form-group">
        <label for="moneda_precio">Moneda del Precio:</label>
        <input type="text" id="moneda_precio" name="moneda_precio" value="USD" maxlength="3" required>
    </div>

    <div class="tic-form-group">
        <label for="moneda_usuario">Moneda de Preferencia:</label>
        <input type="text" id="moneda_usuario" name="moneda_usuario" value="USD" maxlength="3" required>
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

    <button type="button" id="tic-guardar-vuelo-btn" class="button button-primary">Guardar Vuelo</button>
</form>

<div id="tic-flights-report-container">
    </div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
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
                            itinerario_id: $('input[name="itinerario_id"]').val()
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


    });
</script>