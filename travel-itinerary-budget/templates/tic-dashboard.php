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
            $table_name_itinerarios = $wpdb->prefix . 'tic_itinerarios'; // Corregido para claridad
            $itinerarios = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, nombre_itinerario, moneda_reporte FROM {$table_name_itinerarios} WHERE user_id = %d ORDER BY fecha_creacion DESC", // <-- Añadir moneda_reporte
                    $current_user_id
                )
            );
            $has_itineraries = !empty($itinerarios);
            $initial_itinerary_id = 0;
            $initial_itinerary_name = '';
            $initial_itinerary_currency = 'USD'; // Moneda por defecto si no hay itinerarios o no se carga uno

            if ($has_itineraries) {
                // No seleccionamos el primero automáticamente, pero podemos tener sus datos si es necesario
                // $first_itinerary_id = $itinerarios[0]->id;
                // $first_itinerary_name = $itinerarios[0]->nombre_itinerario;
                $initial_itinerary_currency = $itinerarios[0]->moneda_reporte; // Moneda del primer itinerario
            }
            ?>

            <div id="tic-has-itinerary-view" <?php if (!$has_itineraries) echo 'style="display: none;"'; ?>>
                <form id="tic-select-itinerary-form" style="margin-bottom: 10px;">
                    <label for="selected_itinerary">Seleccionar Itinerario:</label>
                    <select id="selected_itinerary" name="selected_itinerary">
                        <option value="0" data-currency="<?php echo esc_attr($initial_itinerary_currency); ?>">Seleccionar Itinerario</option>
                        <?php
                        if ($has_itineraries) {
                            foreach ($itinerarios as $itinerario) {
                                // Añadir data-currency a cada opción
                                echo '<option value="' . esc_attr($itinerario->id) . '" data-currency="' . esc_attr($itinerario->moneda_reporte) . '">' . esc_html($itinerario->nombre_itinerario) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    
                    <button type="button" id="tic-load-itinerary-btn" class="tic-button" <?php if (empty($itinerarios)) echo 'style="display: none;"'; ?>>Cargar Itinerario</button>
                    <button type="button" id="tic-view-report-btn" class="tic-button" style="display: none; margin-left: 10px;">Ver Reporte Vuelos</button>
                    <button type="button" id="tic-view-accommodation-report-btn" class="tic-button" style="display: none; margin-left: 5px;">Ver Reporte Alojamiento</button>
                    <button type="button" id="tic-view-activity-report-btn" class="tic-button tic-button-secondary" style="display: none; margin-left: 5px;">Ver Reporte Actividades</button>
                </form>
                <div id="tic-current-itinerary-info" class="tic-itinerario-activo">
                    <span id="tic-current-itinerary-name">
                        <?php // Se mostrará vacío inicialmente 
                        ?>
                    </span>
                    <?php // ***** NUEVO SPAN PARA MOSTRAR LA MONEDA ***** 
                    ?>
                    <span id="tic-current-itinerary-currency-display" style="font-weight: bold; margin-left: 10px;"></span>
                </div>
            </div>

            <div id="tic-no-itinerary-view" <?php if ($has_itineraries) echo 'style="display: none;"'; ?>>
                <p>No has creado ningún itinerario aún. Introduce un nombre para crear uno nuevo.</p>
            </div>

						<form id="tic-create-new-itinerary-form" style="margin-top: 20px;">
							<div class="tic-form-section">
                <div class="tic-form-group">
                    <label for="new_itinerary_name">Nombre del Nuevo Itinerario:</label>
                    <input type="text" id="new_itinerary_name" name="itinerary_name" required>
                </div>

                <div class="tic-form-group" style="margin-top: 10px; margin-bottom: 10px;">
                    <label for="new_itinerary_currency">Moneda de Reporte para este Itinerario:</label>
                    <select id="new_itinerary_currency" name="itinerary_report_currency">
                        <option value="USD" selected>USD - Dólar Estadounidense</option>
                        <option value="EUR">EUR - Euro</option>
                        <option value="MXN">MXN - Peso Mexicano</option>
                        <option value="GBP">GBP - Libra Esterlina</option>
                        <option value="JPY">JPY - Yen Japonés</option>
                        <option value="CAD">CAD - Dólar Canadiense</option>
                        <option value="AUD">AUD - Dólar Australiano</option>
                        <?php // Puedes añadir más monedas comunes ?>
                    </select>
								</div>
              </div>
              <?php wp_nonce_field('tic_create_itinerary_nonce', 'nonce'); ?>
              <button type="button" id="tic-create-itinerary-btn" class="tic-button">Crear Itinerario</button>
                
              <input type="hidden" id="current_itinerary_id" name="current_itinerary_id" value="<?php echo esc_attr($initial_itinerary_id); ?>">
              <input type="hidden" id="current_itinerary_currency" value="<?php echo esc_attr($initial_itinerary_currency); ?>">
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
        <?php if (is_user_logged_in()) : ?>
            <p class="tic-notice">Por favor, selecciona un itinerario de la lista o crea uno nuevo para comenzar.</p>
        <?php else: ?>
            <?php
            // Para usuarios no logueados, sí cargamos el formulario de vuelos directamente
            // porque operan con 'temp' y no hay selección previa.
            $tic_database = new TIC_Database();
            $tic_security = new TIC_Security();
            $tic_flights = new TIC_Flights($tic_database, $tic_security);
            $tic_flights->mostrar_formulario_vuelos('temp'); // Pasar 'temp' explícitamente
            ?>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.tic-load-module').on('click', function(e) {
            console.log('LOAD MODULE Start: Reading #current_itinerary_id value:', itineraryId); // <<< AÑADIR/VERIFICAR ESTE LOG
            e.preventDefault();
            var module = $(this).data('module');
            var $thisLink = $(this);
            $('.tic-load-module').removeClass('active');
            $(this).addClass('active');
            $('#tic-module-content').empty().html('<p>Cargando ' + module + '...</p>');

            // 1. OBTENER EL ID DEL ITINERARIO ACTUAL
            console.log('ID del itinerario actual ANTES de cargar módulo:', $('#current_itinerary_id').val(), 'Moneda actual:', $('#current_itinerary_currency').val());
            var itineraryId = $('#current_itinerary_id').val();
            // Asegurarse de que itineraryId tenga un valor para la lógica siguiente
            if (typeof itineraryId === 'undefined' || itineraryId === null || itineraryId === "") {
                itineraryId = '0'; // Default a '0' si está vacío/undefined para que parseInt funcione
                console.warn('LOAD MODULE: #current_itinerary_id estaba undefined/vacío, usando "0" por defecto para la lógica JS.');
            }
            console.log('LOAD MODULE Start: itineraryId obtenido es:', itineraryId);

            // 2. DEFINIR isValidItineraryForReport AQUÍ, usando el itineraryId obtenido arriba
            var isValidItineraryForReport = (tic_ajax_object.is_user_logged_in && parseInt(itineraryId) > 0) || (!tic_ajax_object.is_user_logged_in && itineraryId === 'temp');
            console.log('LOAD MODULE: isValidItineraryForReport evaluado como:', isValidItineraryForReport);

            // Nueva lógica para visibilidad de botones de reporte
            $('#tic-view-report-btn').hide(); // Ocultar botón de reporte de vuelos por defecto
            $('#tic-view-accommodation-report-btn').hide(); // Ocultar botón de reporte de alojamiento por defecto
            $('#tic-view-activity-report-btn').hide(); // Ocultar botón de reporte de actividades por defecto
            // Añadir aquí .hide() para futuros botones de reporte de otros módulos

            // 4. Lógica para cargar el módulo y mostrar el botón de reporte correspondiente
            if (module === 'vuelos') {
                console.log('LOAD MODULE Vuelos: Requesting form for itinerary_id:', itineraryId);
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_formulario_vuelos',
                    itinerario_id: itineraryId,
                    nonce: tic_ajax_object.load_module_nonce
                }, function(response) {
                    $('#tic-module-content').html(response);
                    console.log('LOAD MODULE Vuelos: Flight form loaded.');
                    if (isValidItineraryForReport) { // Usar la variable definida arriba
                        $('#tic-view-report-btn').show();
                    }
                }).fail(function(xhr, status, error) {
                    console.error("Error al cargar formulario de vuelos:", status, error);
                    $('#tic-module-content').html('<p class="tic-error">Error al cargar el formulario de vuelos.</p>');
                });

            } else if (module === 'alojamiento') {
                console.log('LOAD MODULE Alojamiento: Requesting form for itinerary_id:', itineraryId);
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_formulario_alojamiento',
                    itinerario_id: itineraryId,
                    nonce: tic_ajax_object.load_module_nonce
                }, function(response) {
                    $('#tic-module-content').html(response);
                    console.log('LOAD MODULE Alojamiento: Formulario de alojamiento cargado.');
                    if (isValidItineraryForReport) { // Usar la variable definida arriba
                        $('#tic-view-accommodation-report-btn').show();
                    }
                }).fail(function(xhr, status, error) {
                    console.error("Error al cargar formulario de alojamiento:", status, error);
                    $('#tic-module-content').html('<p class="tic-error">Error al cargar el formulario de alojamiento.</p>');
                });

            } else if (module === 'actividades') {
                // var itineraryId = $('#current_itinerary_id').val(); // Ya lo tienes definido arriba en el handler

                var data_for_activities_form = {
                    action: 'tic_mostrar_formulario_actividad',
                    itinerario_id: itineraryId, // Usar la variable itineraryId definida al inicio del handler
                    nonce: tic_ajax_object.load_module_nonce
                };
                // Loguear exactamente lo que se va a enviar
                console.log('LOAD MODULE Actividades: Data to be sent for form request:', data_for_activities_form);

                $.post(tic_ajax_object.ajaxurl, data_for_activities_form, function(response) {
                    $('#tic-module-content').html(response);
                    console.log('LOAD MODULE Actividades: Response received for form. Content length:', response.length);
                    if (isValidItineraryForReport) {
                        $('#tic-view-activity-report-btn').show();
                    }
                }).fail(function(xhr, status, error) {
                    console.error("Error al cargar formulario de actividades:", status, error);
                    $('#tic-module-content').html('<p class="tic-error">Error al cargar el formulario de actividades.</p>');
                });
            }
            // ... (más else if para otros módulos) ...
        });
        $('#tic-create-itinerary-btn').on('click', function(e) {
            console.log($('#current_itinerary_id').val());
            e.preventDefault();
            var newItineraryName = $('#new_itinerary_name').val();
            var selectedReportCurrency = $('#new_itinerary_currency').val();
            if (newItineraryName) {
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_create_itinerary',
                    itinerary_name: newItineraryName,
                    itinerary_report_currency: selectedReportCurrency, // <-- AÑADIR ESTA LÍNEA
                    nonce: $('#tic-create-new-itinerary-form input[name="nonce"]').val()
                }, function(response) {
                    // <<<< INICIO CÓDIGO SUCCESS REVISADO >>>>
                    if (response.success) {
                        var newItineraryId = response.data.itinerary_id;
                        var newItineraryName = response.data.itinerary_name;
                        var newItineraryCurrency = response.data.itinerary_currency; // Ya lo recibimos de PHP

                        $('#current_itinerary_id').val(newItineraryId);
                        $('#tic-current-itinerary-name').text('Itinerario actual: ' + newItineraryName);

                        // --- INICIO NUEVO CÓDIGO ---
                        $('#current_itinerary_currency').val(newItineraryCurrency); // Guardar moneda activa
                        $('#tic-current-itinerary-currency-display').text('(Moneda: ' + newItineraryCurrency + ')'); // Mostrar moneda
                        // --- FIN NUEVO CÓDIGO ---

                        // 3. Añadir opción al dropdown
                        var $dropdown = $('#selected_itinerary');
                        
                        // Añadir data-currency a la nueva opción
                        var newOption = $('<option>', {
                            value: newItineraryId,
                            text: newItineraryName,
                            'data-currency': newItineraryCurrency // <-- Guardar moneda en la opción
                        });
                        $dropdown.append(newOption);
                        $dropdown.val(newItineraryId);

                        // 4. Seleccionar la nueva opción en el dropdown
                        $('#new_itinerary_name').val('');

                        // 5. Gestionar visibilidad de vistas
                        $('#tic-no-itinerary-view').hide();
                        $('#tic-has-itinerary-view').show();
                        $('#tic-current-itinerary-name').show();
                        $('#tic-current-itinerary-currency-display').show();

                        // 6. Gestionar visibilidad del botón "Cargar Itinerario"
                        // Mostrar/ocultar botones de reporte según corresponda
                        var $loadButton = $('#tic-load-itinerary-btn'); // Botón Cargar Itinerario
                        var $viewFlightReportBtn = $('#tic-view-report-btn'); // Botón Ver Reporte Vuelos
                        var $viewAccReportBtn = $('#tic-view-accommodation-report-btn'); // Botón Ver Reporte Alojamiento
                        if ($dropdown.find('option[value!="0"]').length > 0) { // Si hay al menos UN itinerario real
                            $loadButton.show();
                        } else {
                            $loadButton.hide();
                        }

                        // 8. Recargar el módulo activo (Vuelos por defecto) para el nuevo ID
                        console.log('Intentando recargar módulo para ID:', newItineraryId); // Log para debug
                        // Asegúrate que el selector '.tic-load-module.active' es correcto
                        // y que su manejador de click usa $('#current_itinerary_id').val()
                        console.log('CREATE SUCCESS: Triggering module load...'); // <<< AÑADIR/VERIFICAR ESTE LOG
                        $('.tic-load-module.active').trigger('click');
                        // Mostrar/ocultar botones de reporte según el módulo activo (asumimos Vuelos es el activo)
                        // Esta lógica se refinará en el handler de .tic-load-module
                        $('#tic-view-report-btn').show(); // Para vuelos
                        $('#tic-view-accommodation-report-btn').hide();
                        $('#tic-view-activity-report-btn').hide(); // <<< AÑADIR ESTA LÍNEA

                        // Ocultar otros botones de reporte de módulos futuros

                        // 9. Alerta de éxito (opcional, puedes quitarla si la UI es clara)
                        // alert('Itinerario "' + newItineraryName + '" creado correctamente.');

                    } else {
                        // Esta parte maneja los casos donde el nombre o la moneda no se proporcionaron
                        if (!newItineraryName) {
                            alert('Por favor, introduce un nombre para el itinerario.');
                        } else if (!selectedReportCurrency || selectedReportCurrency === "0") {
                            // Asumimos que tu <select> podría tener una opción default como <option value="0">Seleccionar...</option>
                            // O simplemente !selectedReportCurrency si todos los values son códigos válidos.
                            alert('Por favor, selecciona una moneda de reporte para el itinerario.');
                        }
                    }
                    // <<<< FIN CÓDIGO SUCCESS REVISADO >>>>
                }, 'json');
            } else {
                alert('Por favor, introduce un nombre para el itinerario.');
            }
        }); // Fin de #tic-create-itinerary-btn click handler

        // Manejador para el botón "Cargar Itinerario"
        $('#tic-load-itinerary-btn').on('click', function(e) {
            e.preventDefault();
            var selectedId = $('#selected_itinerary').val();
            var $selectedOption = $('#selected_itinerary option:selected'); // Obtener la opción seleccionada
            var selectedName = $selectedOption.text();
            // --- INICIO NUEVO CÓDIGO ---
            var selectedCurrency = $selectedOption.data('currency'); // Leer moneda del data-attribute
            // --- FIN NUEVO CÓDIGO ---

            if (selectedId > 0) {
                // Actualizar el ID oculto
                $('#current_itinerary_id').val(selectedId);
                $('#tic-current-itinerary-name').text('Itinerario actual: ' + selectedName);

                console.log('LOAD SUCCESS: #current_itinerary_id value AFTER set:', $('#current_itinerary_id').val()); // <<< AÑADIR/VERIFICAR ESTE LOG
                // --- INICIO NUEVO CÓDIGO ---
                $('#current_itinerary_currency').val(selectedCurrency); // Guardar moneda activa
                $('#tic-current-itinerary-currency-display').text('(Moneda: ' + selectedCurrency + ')').show(); // Mostrar moneda
                // --- FIN NUEVO CÓDIGO ---
                // Actualizar el nombre mostrado
                //$('#tic-current-itinerary-name').text('Itinerario Actual: ' + selectedName);

                // Recargar el módulo activo (ej. Vuelos) con el nuevo ID
                $('.tic-load-module.active').trigger('click');
            } else {
                $('#current_itinerary_id').val(0);
                $('#current_itinerary_currency').val('<?php echo esc_js($initial_itinerary_currency); ?>'); // Reset a moneda por defecto
                $('#tic-current-itinerary-name').text('');
                $('#tic-current-itinerary-currency-display').text('').hide(); // Ocultar display de moneda

                $('#tic-view-report-btn').hide(); // Ocultar todos los botones de reporte
                $('#tic-view-accommodation-report-btn').hide();

                $('#tic-module-content').html('<p>Selecciona un itinerario existente o introduce un nombre para crear uno nuevo.</p>');
            }
        });

        // Añade este nuevo bloque dentro de jQuery(document).ready(function($) { ... });

        $('#tic-view-report-btn').on('click', function(e) {
            e.preventDefault();
            console.log('Botón Ver Reporte clickeado');

            var currentItineraryId = $('#current_itinerary_id').val();
            var isLoggedIn = <?php echo json_encode(is_user_logged_in()); ?>; // Pasar estado de login a JS

            // Validar ID (ID > 0 para logueados, o 'temp' para no logueados)
            if ((isLoggedIn && parseInt(currentItineraryId) > 0) || (!isLoggedIn && currentItineraryId === 'temp')) {
                console.log('ID válido para reporte:', currentItineraryId);

                // Mostrar un indicador de carga (opcional)
                $('#tic-module-content').html('<p>Cargando reporte...</p>');

                // Hacer la llamada AJAX para obtener el reporte
                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_reporte_vuelos', // La acción AJAX que ya usábamos
                    itinerario_id: currentItineraryId,
                    nonce: tic_ajax_object.load_module_nonce // Reutilizamos el nonce de cargar módulo
                }, function(response) {
                    // Cargar la respuesta HTML en el contenedor del módulo
                    $('#tic-module-content').html(response);
                    console.log('Reporte cargado.');
                    // Aquí podrías añadir lógica para inicializar el botón de imprimir DENTRO del reporte si es necesario
                }).fail(function(xhr, status, error) {
                    // Manejo básico de errores AJAX
                    console.error("Error al cargar reporte:", status, error);
                    $('#tic-module-content').html('<p class="tic-error">Error al cargar el reporte. Por favor, intenta de nuevo.</p>');
                });

            } else {
                console.log('Intento de ver reporte con ID inválido:', currentItineraryId);
                // Opcional: mostrar mensaje si no hay ID válido (aunque el botón debería estar oculto)
                alert('Por favor, carga o crea un itinerario primero.');
            }
        });

        // Manejador para los botones "Editar Vuelo"
        // Usamos delegación de eventos en un contenedor estático que siempre exista,
        // como #tic-module-content o incluso .tic-dashboard

        $('.tic-dashboard').on('click', '.tic-edit-flight-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var flightId = $button.data('flight-id');

            if (!flightId) {
                alert('No se pudo obtener el ID del vuelo.');
                return;
            }

            console.log('Editar vuelo ID:', flightId);
            $button.text('Cargando...');
            $button.prop('disabled', true);

            // 1. Obtener los detalles del vuelo
            $.post(tic_ajax_object.ajaxurl, {
                action: 'tic_get_flight_details',
                flight_id: flightId,
                nonce: tic_ajax_object.get_flight_details_nonce
            }, function(response) {
                if (response.success) {
                    var flightData = response.data; // Datos del vuelo, ej: flightData.origen, flightData.escalas
                    console.log('Datos del vuelo para editar:', flightData);

                    // 2. Cargar el HTML del FORMULARIO de vuelos en el área de contenido.
                    //    Necesitamos el itinerary_id al que pertenece este vuelo para cargar el contexto correcto del formulario.
                    //    Asumimos que flightData.itinerario_id contiene el ID del itinerario del vuelo.
                    var itineraryIdForForm = flightData.itinerario_id || $('#current_itinerary_id').val(); // Usar el del vuelo o el global

                    $('#tic-module-content').html('<p>Cargando formulario para edición...</p>'); // Feedback visual

                    $.post(tic_ajax_object.ajaxurl, {
                        action: 'tic_mostrar_formulario_vuelos', // La acción que muestra el form
                        itinerary_id: itineraryIdForForm, // ID del itinerario
                        nonce: tic_ajax_object.load_module_nonce // Reutilizamos nonce de cargar módulo
                    }, function(formHtml) {
                        $('#tic-module-content').html(formHtml);
                        console.log('Formulario de vuelos cargado para edición.');

                        // 3. AHORA, poblar el formulario con flightData
                        // Asegúrate que los IDs de los campos coincidan con los de tu tic-flights-form.php

                        // Añadir el ID del vuelo que se está editando como un campo oculto en el formulario
                        // (Primero asegúrate de que este input exista en tu tic-flights-form.php)
                        //$('#tic-flights-form').append('<input type="hidden" name="editing_flight_id" id="editing_flight_id" value="' + flightId + '">');
                        $('#editing_flight_id').val(flightId);

                        $('#origen').val(flightData.origen);
                        $('#destino').val(flightData.destino);
                        $('#linea_aerea').val(flightData.linea_aerea);
                        $('#numero_vuelo').val(flightData.numero_vuelo);

                        // Para datetime-local, el formato debe ser YYYY-MM-DDTHH:MM
                        if (flightData.fecha_hora_salida) {
                            $('#fecha_hora_salida').val(flightData.fecha_hora_salida.replace(' ', 'T').substring(0, 16));
                        }
                        if (flightData.fecha_hora_llegada) {
                            $('#fecha_hora_llegada').val(flightData.fecha_hora_llegada.replace(' ', 'T').substring(0, 16));
                        }

                        $('#precio_persona').val(parseFloat(flightData.precio_persona).toFixed(2));
                        $('#moneda_precio').val(flightData.moneda_precio);
                        $('#moneda_usuario').val(flightData.moneda_usuario);
                        $('#numero_personas').val(parseInt(flightData.numero_personas));
                        $('#tipo_de_cambio').val(parseFloat(flightData.tipo_de_cambio).toFixed(2));
                        $('#codigo_reserva').val(flightData.codigo_reserva);

                        // Manejar escalas
                        $('#escalas-wrapper').empty(); // Limpiar cualquier escala existente en el formulario
                        escalaCount = 0; // MUY IMPORTANTE: Resetear el contador global ANTES de añadir las escalas existentes
                        // La variable global 'escalaCount' se usa en tu función agregarEscala()
                        // Si no está definida globalmente en tic-dashboard.js, defínela: var escalaCount = 0;
                        // Aquí asumiremos que está accesible o la reseteamos dentro de tic-flights-form.js si es necesario.
                        // Por simplicidad, si tu 'escalaCount' está en tic-flights-form.js, esta parte
                        // de poblar escalas funcionará bien después de que el script del form se ejecute.

                        if (flightData.tiene_escalas && flightData.escalas && flightData.escalas.length > 0) {
                            $('#tiene_escalas').prop('checked', true); // Marcar el checkbox
                            $('#escalas-container').show(); // Asegurar que el contenedor de escalas sea visible (no animado aquí para simpleza)

                            flightData.escalas.forEach(function(escalaData) {
                                // Ahora agregarEscala() es global y debería funcionar
                                agregarEscala(); // Esto incrementará escalaCount y añadirá una fila vacía
                                var $lastEscalaItem = $('#escalas-wrapper .escala-item:last-child');
                                $lastEscalaItem.find('input[name$="[aeropuerto]"]').val(escalaData.aeropuerto);
                                if (escalaData.fecha_hora_llegada) {
                                    $lastEscalaItem.find('input[name$="[llegada]"]').val(escalaData.fecha_hora_llegada.replace(' ', 'T').substring(0, 16));
                                }
                                if (escalaData.fecha_hora_salida) {
                                    $lastEscalaItem.find('input[name$="[salida]"]').val(escalaData.fecha_hora_salida.replace(' ', 'T').substring(0, 16));
                                }
                            });
                        } else {
                            $('#tiene_escalas').prop('checked', false);
                            $('#escalas-container').hide();
                        }

                        // Cambiar texto del botón de guardar (asegúrate que el ID es correcto)
                        $('#tic-guardar-vuelo-btn').text('Actualizar Vuelo');

                        alert('Formulario listo para editar.'); // Alerta temporal

                    }).fail(function() {
                        $('#tic-module-content').html('<p class="tic-error">Error al cargar el formulario de edición.</p>');
                    });

                } else {
                    console.error('Error al obtener detalles del vuelo para editar:', response.data.message);
                    alert('Error: ' + (response.data.message || 'No se pudieron cargar los datos del vuelo.'));
                }
            }, 'json').fail(function() {
                alert('Error de comunicación al intentar cargar los datos del vuelo.');
            }).always(function() {
                $button.text('Editar'); // Restaurar texto original del botón "Editar"
                $button.prop('disabled', false);
            });
        });

        // Manejador para los botones "Eliminar Vuelo"
        $('.tic-dashboard').on('click', '.tic-delete-flight-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var flightId = $button.data('flight-id');

            if (!flightId) {
                alert('No se pudo obtener el ID del vuelo para eliminar.');
                return;
            }

            // Pedir confirmación al usuario
            if (confirm('¿Estás seguro de que quieres eliminar este vuelo? Esta acción no se puede deshacer.')) {
                console.log('Confirmado eliminar vuelo ID:', flightId);

                // Feedback visual para el usuario
                $button.text('Eliminando...');
                $button.prop('disabled', true);
                // Opcional: deshabilitar también el botón de editar mientras se procesa
                $button.siblings('.tic-edit-flight-btn').prop('disabled', true);

                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_delete_flight',
                    flight_id: flightId,
                    nonce: tic_ajax_object.delete_flight_nonce // Usar el nuevo nonce para eliminar
                }, function(response) {
                    if (response.success) {
                        // Eliminar la fila de la tabla visualmente con una animación
                        $button.closest('tr').fadeOut(500, function() {
                            $(this).remove();
                            // Opcional: Notificación más sutil que una alerta
                            // $('#tic-module-content').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        });
                        alert(response.data.message); // O usar una notificación más integrada
                    } else {
                        console.error('Error al eliminar el vuelo:', response.data.message);
                        alert('Error: ' + (response.data.message || 'No se pudo eliminar el vuelo.'));
                        // Reactivar botones en caso de error si la fila no se eliminó
                        $button.text('Eliminar');
                        $button.prop('disabled', false);
                        $button.siblings('.tic-edit-flight-btn').prop('disabled', false);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Error AJAX al eliminar vuelo:', status, error);
                    alert('Error de comunicación al intentar eliminar el vuelo.');
                    // Reactivar botones
                    $button.text('Eliminar');
                    $button.prop('disabled', false);
                    $button.siblings('.tic-edit-flight-btn').prop('disabled', false);
                });
            } // Fin del if (confirm)
        });
        $('#tic-view-accommodation-report-btn').on('click', function(e) {
            e.preventDefault();
            console.log('Botón Ver Reporte Alojamiento clickeado');

            var currentItineraryId = $('#current_itinerary_id').val();
            // Usaremos la misma variable global o localizada para el estado de login
            // var isLoggedIn = tic_ajax_object.is_logged_in; // Si lo añadiste a tic_ajax_object
            var isLoggedIn = <?php echo json_encode(is_user_logged_in()); ?>; // O directamente si este script se parsea con PHP

            if ((isLoggedIn && parseInt(currentItineraryId) > 0) || (!isLoggedIn && currentItineraryId === 'temp')) {
                console.log('ID válido para reporte de alojamiento:', currentItineraryId);
                $('#tic-module-content').html('<p>Cargando reporte de alojamiento...</p>');

                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_reporte_alojamiento', // Acción PHP que creamos
                    itinerario_id: currentItineraryId,
                    nonce: tic_ajax_object.load_module_nonce // Reutilizamos nonce
                }, function(response) {
                    $('#tic-module-content').html(response);
                    console.log('Reporte de alojamiento cargado en #tic-module-content.');
                }).fail(function(xhr, status, error) {
                    console.error("Error al cargar reporte de alojamiento:", status, error);
                    $('#tic-module-content').html('<p class="tic-error">Error al cargar el reporte de alojamiento.</p>');
                });
            } else {
                alert('Por favor, carga o crea un itinerario primero.');
            }
        });

        $('#tic-view-activity-report-btn').on('click', function(e) {
            e.preventDefault();
            console.log('Botón Ver Reporte Actividades clickeado');

            var currentItineraryId = $('#current_itinerary_id').val();
            var isLoggedIn = tic_ajax_object.is_user_logged_in; // Usar la variable global/localizada

            if ((isLoggedIn && parseInt(currentItineraryId) > 0) || (!isLoggedIn && currentItineraryId === 'temp')) {
                console.log('ID válido para reporte de actividades:', currentItineraryId);
                $('#tic-module-content').html('<p>Cargando reporte de actividades...</p>'); // Feedback visual

                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_mostrar_reporte_actividad', // Acción PHP que creamos para el reporte de actividades
                    itinerario_id: currentItineraryId,
                    nonce: tic_ajax_object.load_module_nonce // Reutilizamos el nonce de cargar módulo
                }, function(response) {
                    $('#tic-module-content').html(response); // Cargar en el contenedor principal
                    console.log('Reporte de actividades cargado en #tic-module-content.');
                }).fail(function(xhr, status, error) {
                    console.error("Error al cargar reporte de actividades:", status, error);
                    $('#tic-module-content').html('<p class="tic-error">Error al cargar el reporte de actividades.</p>');
                });
            } else {
                alert('Por favor, carga o crea un itinerario primero.');
            }
        });

        // Manejador para los botones "Editar Alojamiento"
        // Usamos delegación desde un contenedor estático como '.tic-dashboard' o '#tic-module-content'

        $('.tic-dashboard').on('click', '.tic-edit-accommodation-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var accommodationId = $button.data('accommodation-id');

            if (!accommodationId) {
                alert('No se pudo obtener el ID del alojamiento.');
                return;
            }

            console.log('Editar alojamiento ID:', accommodationId);
            $button.text('Cargando...');
            $button.prop('disabled', true);

            // 1. Obtener los detalles del alojamiento que se va a editar
            $.post(tic_ajax_object.ajaxurl, {
                action: 'tic_get_accommodation_details',
                accommodation_id: accommodationId,
                nonce: tic_ajax_object.get_accommodation_details_nonce
            }, function(response) {
                if (response.success) {
                    var accommodationDataToEdit = response.data; // Datos del alojamiento a editar
                    console.log('Datos del alojamiento para rellenar formulario:', accommodationDataToEdit);

                    // 2. Cargar el HTML del FORMULARIO de alojamiento en el área de contenido.
                    //    El ID del itinerario está en accommodationDataToEdit.itinerario_id
                    var itineraryIdForForm = accommodationDataToEdit.itinerario_id || $('#current_itinerary_id').val();

                    $('#tic-module-content').html('<p>Cargando formulario para edición de alojamiento...</p>');

                    $.post(tic_ajax_object.ajaxurl, {
                        action: 'tic_mostrar_formulario_alojamiento', // Acción que muestra el form
                        itinerary_id: itineraryIdForForm,
                        nonce: tic_ajax_object.load_module_nonce
                    }, function(formHtml) {
                        $('#tic-module-content').html(formHtml);
                        console.log('Formulario de alojamiento cargado para edición.');

                        // 3. AHORA, poblar el formulario con accommodationDataToEdit
                        // Los IDs de los campos deben coincidir con los de tic-accommodation-form.php

                        // Establecer el ID del alojamiento que se está editando en el campo oculto
                        $('#editing_accommodation_id').val(accommodationDataToEdit.id);

                        $('#tic_acc_pais').val(accommodationDataToEdit.pais);
                        $('#tic_acc_ciudad_poblacion').val(accommodationDataToEdit.ciudad_poblacion);
                        $('#tic_acc_hotel_hospedaje').val(accommodationDataToEdit.hotel_hospedaje);
                        $('#tic_acc_direccion_hotel').val(accommodationDataToEdit.direccion_hotel);

                        if (accommodationDataToEdit.fecha_entrada) {
                            $('#tic_acc_fecha_entrada').val(accommodationDataToEdit.fecha_entrada.replace(' ', 'T').substring(0, 16));
                        }
                        if (accommodationDataToEdit.fecha_salida) {
                            $('#tic_acc_fecha_salida').val(accommodationDataToEdit.fecha_salida.replace(' ', 'T').substring(0, 16));
                        }

                        // Los campos calculados numero_noches y precio_total_alojamiento no se rellenan en el form,
                        // ya que se calculan al guardar. Solo rellenamos los que el usuario introduce.
                        $('#tic_acc_precio_noche').val(parseFloat(accommodationDataToEdit.precio_noche).toFixed(2));
                        $('#tic_acc_moneda').val(accommodationDataToEdit.moneda);

                        if (accommodationDataToEdit.fecha_pago_reserva) {
                            // El input type="date" espera 'YYYY-MM-DD'
                            $('#tic_acc_fecha_pago_reserva').val(accommodationDataToEdit.fecha_pago_reserva.substring(0, 10));
                        }
                        $('#tic_acc_codigo_reserva').val(accommodationDataToEdit.codigo_reserva);
                        $('#tic_acc_aplicacion_pago_reserva').val(accommodationDataToEdit.aplicacion_pago_reserva);

                        // Cambiar texto del botón de guardar
                        // Asegúrate de que el ID del botón en tic-accommodation-form.php sea #tic-guardar-alojamiento-btn
                        $('#tic-guardar-alojamiento-btn').text('Actualizar Alojamiento');

                        console.log('Formulario de alojamiento rellenado para editar.');

                    }).fail(function() {
                        $('#tic-module-content').html('<p class="tic-error">Error al cargar el formulario de edición de alojamiento.</p>');
                        // Restaurar botón "Editar" si falla la carga del formulario
                        $button.text('Editar');
                        $button.prop('disabled', false);
                    });

                } else { // Falló tic_get_accommodation_details
                    console.error('Error al obtener detalles del alojamiento para editar:', response.data.message);
                    alert('Error: ' + (response.data.message || 'No se pudieron cargar los datos del alojamiento.'));
                    $button.text('Editar'); // Restaurar botón "Editar"
                    $button.prop('disabled', false);
                }
            }, 'json').fail(function() {
                alert('Error de comunicación al intentar cargar los datos del alojamiento.');
                $button.text('Editar'); // Restaurar botón "Editar"
                $button.prop('disabled', false);
            });
        });

        //Manejador para los botones "Eliminar Alojamiento"
        $('.tic-dashboard').on('click', '.tic-delete-accommodation-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var accommodationId = $button.data('accommodation-id');

            if (!accommodationId) {
                alert('No se pudo obtener el ID del alojamiento para eliminar.');
                return;
            }

            // Pedir confirmación al usuario
            if (confirm('¿Estás seguro de que quieres eliminar este registro de alojamiento? Esta acción no se puede deshacer.')) {
                console.log('Confirmado eliminar alojamiento ID:', accommodationId);

                $button.text('Eliminando...');
                $button.prop('disabled', true);
                $button.siblings('.tic-edit-accommodation-btn').prop('disabled', true);

                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_delete_accommodation',
                    accommodation_id: accommodationId,
                    nonce: tic_ajax_object.delete_accommodation_nonce // Usar el nuevo nonce
                }, function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(500, function() {
                            $(this).remove();
                        });
                        alert(response.data.message || 'Alojamiento eliminado.');
                    } else {
                        console.error('Error al eliminar alojamiento:', response.data.message);
                        alert('Error: ' + (response.data.message || 'No se pudo eliminar el alojamiento.'));
                        $button.text('Eliminar');
                        $button.prop('disabled', false);
                        $button.siblings('.tic-edit-accommodation-btn').prop('disabled', false);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Error AJAX al eliminar alojamiento:', status, error);
                    alert('Error de comunicación al intentar eliminar el alojamiento.');
                    $button.text('Eliminar');
                    $button.prop('disabled', false);
                    $button.siblings('.tic-edit-accommodation-btn').prop('disabled', false);
                });
            } // Fin del if (confirm)
        });

        // Manejador para los botones "Editar Actividad"
        $('.tic-dashboard').on('click', '.tic-edit-activity-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var activityId = $button.data('activity-id'); // activity-id para actividades

            if (!activityId) {
                alert('No se pudo obtener el ID de la actividad.');
                return;
            }

            console.log('Editar actividad ID:', activityId);
            $button.text('Cargando...');
            $button.prop('disabled', true);

            // 1. Obtener los detalles de la actividad que se va a editar
            $.post(tic_ajax_object.ajaxurl, {
                action: 'tic_get_activity_details',
                activity_id: activityId, // Enviar activity_id
                nonce: tic_ajax_object.get_activity_details_nonce
            }, function(response) {
                if (response.success) {
                    var activityDataToEdit = response.data; // Datos de la actividad a editar
                    console.log('Datos de la actividad para rellenar formulario:', activityDataToEdit);

                    // 2. Cargar el HTML del FORMULARIO de actividades en el área de contenido.
                    //    El ID del itinerario está en activityDataToEdit.itinerario_id
                    var itineraryIdForForm = activityDataToEdit.itinerario_id || $('#current_itinerary_id').val();

                    $('#tic-module-content').html('<p>Cargando formulario para edición de actividad...</p>');

                    $.post(tic_ajax_object.ajaxurl, {
                        action: 'tic_mostrar_formulario_actividad', // Acción que muestra el form de actividad
                        itinerario_id: itineraryIdForForm,
                        nonce: tic_ajax_object.load_module_nonce
                    }, function(formHtml) {
                        $('#tic-module-content').html(formHtml);
                        console.log('Formulario de actividades cargado para edición.');

                        // 3. AHORA, poblar el formulario con activityDataToEdit
                        // Los IDs de los campos deben coincidir con los de tic-activities-form.php

                        $('#editing_activity_id').val(activityDataToEdit.id); // ID de la actividad que se edita

                        $('#tic_act_pais').val(activityDataToEdit.pais);
                        $('#tic_act_ciudad_poblacion').val(activityDataToEdit.ciudad_poblacion);
                        if (activityDataToEdit.fecha_actividad) {
                            $('#tic_act_fecha_actividad').val(activityDataToEdit.fecha_actividad.replace(' ', 'T').substring(0, 16));
                        }
                        $('#tic_act_nombre_tour_actividad').val(activityDataToEdit.nombre_tour_actividad);
                        $('#tic_act_precio_persona').val(parseFloat(activityDataToEdit.precio_persona || 0).toFixed(2));
                        $('#tic_act_numero_personas').val(parseInt(activityDataToEdit.numero_personas || 1));
                        $('#tic_act_moneda_precio').val(activityDataToEdit.moneda_precio_actividad);
                        $('#tic_act_tipo_de_cambio').val(parseFloat(activityDataToEdit.tipo_de_cambio_actividad || 1.0000).toFixed(4));
                        $('#tic_act_proveedor_reserva').val(activityDataToEdit.proveedor_reserva);
                        $('#tic_act_codigo_reserva').val(activityDataToEdit.codigo_reserva);
                        $('#tic_act_notas').val(activityDataToEdit.notas);

                        // Cambiar texto del botón de guardar
                        // Asegúrate que el ID del botón en tic-activities-form.php sea #tic-guardar-actividad-btn
                        $('#tic-guardar-actividad-btn').text('Actualizar Actividad');

                        // Llamar a las funciones de cálculo y gestión del tipo de cambio
                        // Asumimos que estas funciones (manageActivityExchangeRateField, calculateAndUpdateActivityTotal)
                        // están definidas en el script cargado con tic-activities-form.php
                        if (typeof manageActivityExchangeRateField === "function") {
                            manageActivityExchangeRateField();
                        }
                        if (typeof calculateAndUpdateActivityTotal === "function") {
                            calculateAndUpdateActivityTotal();
                        }

                        console.log('Formulario de actividades rellenado para editar.');

                    }).fail(function() {
                        $('#tic-module-content').html('<p class="tic-error">Error al cargar el formulario de edición de actividad.</p>');
                        $button.text('Editar');
                        $button.prop('disabled', false);
                    });

                } else { // Falló tic_get_activity_details
                    console.error('Error al obtener detalles de la actividad para editar:', response.data.message);
                    alert('Error: ' + (response.data.message || 'No se pudieron cargar los datos de la actividad.'));
                    $button.text('Editar');
                    $button.prop('disabled', false);
                }
            }, 'json').fail(function() {
                alert('Error de comunicación al intentar cargar los datos de la actividad.');
                $button.text('Editar');
                $button.prop('disabled', false);
            });
        });

        // Manejador para los botones "Eliminar Actividad"
        $('.tic-dashboard').on('click', '.tic-delete-activity-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var activityId = $button.data('activity-id'); // data-activity-id

            if (!activityId) {
                alert('No se pudo obtener el ID de la actividad para eliminar.');
                return;
            }

            if (confirm('¿Estás seguro de que quieres eliminar esta actividad? Esta acción no se puede deshacer.')) {
                console.log('Confirmado eliminar actividad ID:', activityId);

                $button.text('Eliminando...');
                $button.prop('disabled', true);
                $button.siblings('.tic-edit-activity-btn').prop('disabled', true);

                $.post(tic_ajax_object.ajaxurl, {
                    action: 'tic_delete_activity', // Nueva acción AJAX
                    activity_id: activityId, // activity_id
                    nonce: tic_ajax_object.delete_activity_nonce // Usar el nuevo nonce
                }, function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(500, function() {
                            $(this).remove();
                        });
                        alert(response.data.message || 'Actividad eliminada.');
                    } else {
                        console.error('Error al eliminar actividad:', response.data.message);
                        alert('Error: ' + (response.data.message || 'No se pudo eliminar la actividad.'));
                        $button.text('Eliminar');
                        $button.prop('disabled', false);
                        $button.siblings('.tic-edit-activity-btn').prop('disabled', false);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Error AJAX al eliminar actividad:', status, error);
                    alert('Error de comunicación al intentar eliminar la actividad.');
                    $button.text('Eliminar');
                    $button.prop('disabled', false);
                    $button.siblings('.tic-edit-activity-btn').prop('disabled', false);
                });
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