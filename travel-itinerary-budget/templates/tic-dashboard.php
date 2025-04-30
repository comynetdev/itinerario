<?php
/**
 * Template for displaying the travel itinerary dashboard.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

global $tic_flights; // Aseguramos que la instancia de la clase TIC_Flights esté disponible

?>

<div class="tic-dashboard">
    <h2>Panel de Itinerario de Viaje</h2>

    <?php if (is_user_logged_in()) : ?>
        <div class="tic-itineraries-management">
            <h3>Mis Itinerarios</h3>
            <?php
            $current_user_id = get_current_user_id();
            global $wpdb;
            $table_name = $wpdb->prefix . 'tic_itinerarios';
            $itinerarios = $wpdb->get_results(
                $wpdb->prepare("SELECT id, nombre_itinerario FROM {$table_name} WHERE user_id = %d ORDER BY fecha_creacion DESC", $current_user_id)
            );
            ?>

            <?php if (!empty($itinerarios)) : ?>
                <form id="tic-select-itinerary-form">
                    <label for="selected_itinerary">Seleccionar Itinerario:</label>
                    <select id="selected_itinerary" name="selected_itinerary">
                        <option value="0">Seleccionar Itinerario</option>
                        <?php foreach ($itinerarios as $itinerario) : ?>
                            <option value="<?php echo esc_attr($itinerario->id); ?>"><?php echo esc_html($itinerario->nombre_itinerario); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="tic-load-itinerary-btn" class="tic-button">Cargar Itinerario</button>
                </form>
                <div id="tic-current-itinerary-name"></div>
            <?php else : ?>
                <p>No has creado ningún itinerario aún. Introduce un nombre para crear uno nuevo.</p>
            <?php endif; ?>

            <form id="tic-create-new-itinerary-form">
                <label for="new_itinerary_name">Nombre del Nuevo Itinerario:</label>
                <input type="text" id="new_itinerary_name" name="itinerary_name">
                <?php wp_nonce_field('tic_create_itinerary_nonce', 'nonce'); ?>
                <button type="button" id="tic-create-itinerary-btn" class="tic-button">Crear Itinerario</button>
                <input type="hidden" id="current_itinerary_id" name="current_itinerary_id" value="0">
            </form>
        </div>
    <?php else : ?>
        <p>Crea tu itinerario de viaje. Los datos se guardarán temporalmente en tu navegador.</p>
        <input type="hidden" id="current_itinerary_id" name="current_itinerary_id" value="temp">
    <?php endif; ?>

    <div class="tic-module-navigation">
        <h3>Módulos del Itinerario</h3>
        <ul>
            <li><a href="#" data-module="vuelos" class="tic-load-module active">Vuelos</a></li>
            <li><a href="#" data-module="alojamiento" class="tic-load-module">Alojamiento</a></li>
            <li><a href="#" data-module="actividades" class="tic-load-module">Actividades</a></li>
            <li><a href="#" data-module="traslados" class="tic-load-module">Traslados Locales</a></li>
            <li><a href="#" data-module="alimentacion" class="tic-load-module">Alimentación</a></li>
            <li><a href="#" data-module="presupuesto" class="tic-load-module">Presupuesto Total</a></li>
        </ul>
    </div>

    <div id="tic-module-content">
        <?php
        // Cargar el formulario de vuelos por defecto
        $tic_database = new TIC_Database();
        $tic_security = new TIC_Security();
        $tic_flights = new TIC_Flights($tic_database, $tic_security); // Instanciar aquí, pasando las dependencias
        $tic_flights->mostrar_formulario_vuelos();
        ?>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.tic-load-module').on('click', function(e) {
            console.log($('#current_itinerary_id').val());
            e.preventDefault();
            var module = $(this).data('module');
            $('.tic-load-module').removeClass('active');
            $(this).addClass('active');
            $('#tic-module-content').empty();

            var itineraryId = $('#current_itinerary_id').val();

            if (module === 'vuelos') {
                console.log('Valor de itineraryId antes de cargar el formulario de vuelos:', itineraryId);
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_formulario_vuelos',
                    itinerary_id: itineraryId
                }, function(response) {
                    $('#tic-module-content').html(response);
                });
            } else if (module === 'alojamiento') {
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_formulario_alojamiento', // Esta acción la crearemos después
                    itinerary_id: itineraryId
                }, function(response) {
                    $('#tic-module-content').html(response);
                });
            }
            // Agrega más 'else if' para los otros módulos
        });

        $('#tic-create-itinerary-btn').on('click', function(e) {
            console.log($('#current_itinerary_id').val());
            e.preventDefault();
            var newItineraryName = $('#new_itinerary_name').val();
            if (newItineraryName) {
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_create_itinerary',
                    itinerary_name: newItineraryName, // Coincide con el name del input
                    nonce: $('#tic-create-new-itinerary-form input[name="nonce"]').val()
                }, function(response) {
                    if (response.success) {
                        var newItineraryId = response.data.itinerary_id;
                        var newItineraryName = response.data.itinerary_name; // Asumiendo que la respuesta devuelve el nombre también

                        // Actualizar el ID oculto
                        $('#current_itinerary_id').val(newItineraryId);
                        console.log('Nuevo ID de itinerario:', newItineraryId);

                        // Actualizar el nombre mostrado
                        $('#tic-current-itinerary-name').text('Itinerario actual: ' + newItineraryName);

                        // Añadir a la lista desplegable y seleccionarlo
                        var newOption = $('<option>', {
                            value: newItineraryId,
                            text: newItineraryName,
                            selected: true // Seleccionar automáticamente
                        });
                        $('#selected_itinerary').append(newOption);

                        // Limpiar el campo de nuevo nombre
                        $('#new_itinerary_name').val('');

                        // Recargar el módulo de vuelos actual para usar el nuevo ID
                        // (Simulamos un clic en el enlace activo, que debería ser 'Vuelos' por defecto)
                        $('.tic-load-module.active').trigger('click');

                        alert('Itinerario "' + newItineraryName + '" creado correctamente.');

                    } else {
                        alert('Error al crear el itinerario: ' + (response.data.message || 'Error desconocido'));
                    }
                }, 'json');
            } else {
                alert('Por favor, introduce un nombre para el itinerario.');
            }
        });

        // Manejador para el botón "Cargar Itinerario"
        $('#tic-load-itinerary-btn').on('click', function(e) {
            e.preventDefault();
            var selectedId = $('#selected_itinerary').val();
            var selectedName = $('#selected_itinerary option:selected').text();

            if (selectedId > 0) {
                // Actualizar el ID oculto
                $('#current_itinerary_id').val(selectedId);
                console.log('ID de itinerario cargado:', selectedId);

                // Actualizar el nombre mostrado
                $('#tic-current-itinerary-name').text('Itinerario actual: ' + selectedName);

                // Recargar el módulo activo (ej. Vuelos) con el nuevo ID
                $('.tic-load-module.active').trigger('click');
            } else {
                // Si seleccionan "Crear Nuevo Itinerario", limpiar el ID y el nombre
                $('#current_itinerary_id').val(0);
                $('#tic-current-itinerary-name').text('');
                // Opcional: Limpiar el contenido del módulo
                $('#tic-module-content').html('<p>Selecciona un itinerario existente o introduce un nombre para crear uno nuevo.</p>');
            }
        });

    });

    function tic_define_ajaxurl() {
    if (typeof ajaxurl == 'undefined') {
        ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
}
tic_define_ajaxurl();
</script>