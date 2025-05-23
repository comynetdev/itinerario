<?php

/**
 * Template for displaying the flights report.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

// --- INICIO NUEVO CÓDIGO ---
// Mostrar el nombre del itinerario si está disponible
if (isset($itinerary_name_for_report) && !empty($itinerary_name_for_report)) : ?>
    <h2>Itinerario: <?php echo esc_html($itinerary_name_for_report); ?></h2>
<?php endif;
// --- FIN NUEVO CÓDIGO ---
// --- INICIO NUEVA ANOTACIÓN DE MONEDA ---
if (isset($active_itinerary_report_currency) && !empty($active_itinerary_report_currency)) : ?>
    <p class="tic-report-currency-notice">
        <em>Nota: Todos los montos y totales en este reporte de vuelos se muestran en
            <strong><?php echo esc_html($active_itinerary_report_currency); ?></strong>
            (Moneda de Reporte del Itinerario).</em>
    </p>
<?php endif;
// --- FIN NUEVA ANOTACIÓN DE MONEDA ---

if (!empty($vuelos)) : ?>
    <h3>Reporte de Vuelos</h3>
    <table class="tic-table">
        <thead>
            <tr>
                <th>Origen</th>
                <th>Destino</th>
                <th>Línea Aérea</th>
                <th>Número de Vuelo</th>
                <th>Salida</th>
                <th>Llegada</th>
                <th>Escala(s)</th>
                <th>Precio por Persona</th>
                <th>Moneda Cotización</th>
                <th>Personas</th>
                <th>Tipo de Cambio</th>
                <th>Precio Total</th>
                <th>Reserva</th>
                <th class="tic-acciones">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vuelos as $vuelo) : ?>
                <tr>
                    <td><?php echo esc_html($vuelo->origen); ?></td>
                    <td><?php echo esc_html($vuelo->destino); ?></td>
                    <td><?php echo esc_html($vuelo->linea_aerea); ?></td>
                    <td><?php echo esc_html($vuelo->numero_vuelo); ?></td>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($vuelo->fecha_hora_salida))); ?></td>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($vuelo->fecha_hora_llegada))); ?></td>
                    <td>
                        <?php
                        // 1. Verificar si el vuelo está marcado como que tiene escalas
                        if (!empty($vuelo->tiene_escalas)) {

                            // 2. CASO A: ¿Existen las cadenas pre-formateadas de la BD (usuarios registrados)?
                            if (isset($vuelo->escalas_aeropuertos) && !empty($vuelo->escalas_aeropuertos)) {
                                // Mostrar las cadenas concatenadas directamente
                                echo '<strong>Aeropuerto(s):</strong> ' . esc_html($vuelo->escalas_aeropuertos);
                                // Opcionalmente mostrar llegadas/salidas si también existen como cadenas
                                if (isset($vuelo->escalas_llegadas) && !empty($vuelo->escalas_llegadas)) {
                                    echo '<br><strong>Llegada:</strong> ' . esc_html($vuelo->escalas_llegadas);
                                }
                                if (isset($vuelo->escalas_salidas) && !empty($vuelo->escalas_salidas)) {
                                    echo '<br><strong>Salida:</strong> ' . esc_html($vuelo->escalas_salidas);
                                }
                            }
                            // 3. CASO B: ¿Existe el array de escalas de la sesión (usuarios no registrados)?
                            elseif (isset($vuelo->escalas) && is_array($vuelo->escalas) && !empty($vuelo->escalas)) {
                                // Recorrer el array y mostrar cada escala individualmente
                                echo '<ul>'; // Usar una lista para mejor formato
                                foreach ($vuelo->escalas as $escala) {
                                    // Asegurarse de que $escala es un array y tiene 'aeropuerto'
                                    if (is_array($escala) && isset($escala['aeropuerto']) && !empty($escala['aeropuerto'])) {
                                        echo '<li>';
                                        echo '<strong>Aeropuerto:</strong> ' . esc_html($escala['aeropuerto']);

                                        // Formatear y mostrar fechas si existen
                                        $llegada_str = (isset($escala['fecha_hora_llegada']) && !empty($escala['fecha_hora_llegada']))
                                            ? date('Y-m-d H:i', strtotime($escala['fecha_hora_llegada']))
                                            : 'N/A';
                                        $salida_str = (isset($escala['fecha_hora_salida']) && !empty($escala['fecha_hora_salida']))
                                            ? date('Y-m-d H:i', strtotime($escala['fecha_hora_salida']))
                                            : 'N/A';

                                        echo ' (Llegada: ' . esc_html($llegada_str) . ' / Salida: ' . esc_html($salida_str) . ')';
                                        echo '</li>';
                                    }
                                }
                                echo '</ul>';
                            }
                            // 4. Fallback: Marcado con escalas, pero sin detalles (raro, pero por si acaso)
                            else {
                                echo 'Sí (Detalles no disponibles)';
                            }
                        } else {
                            // 5. Si $vuelo->tiene_escalas es 0 o no existe
                            echo 'DIRECTO';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html(number_format($vuelo->precio_persona, 2)); ?></td>
                    <td><?php echo esc_html($vuelo->moneda_precio); ?></td>
                    <td><?php echo esc_html($vuelo->numero_personas); ?></td>
                    <td><?php echo esc_html(number_format($vuelo->tipo_de_cambio, 2)); ?></td>
                    <td><?php echo esc_html(number_format($vuelo->precio_total_vuelos, 2)); ?></td>
                    <td><?php echo esc_html($vuelo->codigo_reserva); ?></td>
                    <td>
                        <?php if (isset($vuelo->id)) : // Solo mostrar si tenemos un ID de vuelo (de la BD) 
                        ?>
                            <button type="button"
                                class="edit-link-button"
                                data-flight-id="<?php echo esc_attr($vuelo->id); ?>">
                                Editar
                                <i class="material-icons">edit</i></button>
                            <?php // ***** INICIO NUEVO BOTÓN ELIMINAR ***** 
                            ?>
                            <button type="button"
                                class="delete-link-button"
                                data-flight-id="<?php echo esc_attr($vuelo->id); ?>"
                                style="margin-left: 5px;">
                                Eliminar
                                <i class="material-icons">delete</i></button>
                            <?php // ***** FIN NUEVO BOTÓN ELIMINAR ***** 
                            ?>
                        <?php else: ?>
                            <?php // Para vuelos de sesión que no tienen ID de BD, editar es más complejo
                            // Por ahora, no mostramos botón si no hay ID de BD.
                            // O podrías usar un índice de sesión aquí si lo manejas.
                            ?>
                            N/A
                        <?php endif; ?>
                    </td>

                </tr>

            <?php endforeach; ?>
        </tbody>
    </table>
    <?php // ***** INICIO BOTÓN IMPRIMIR ***** 
    ?>
    <button id="tic-imprimir-reporte-vuelos" class="button">Imprimir Reporte de Vuelos</button>
    <?php // ***** FIN BOTÓN IMPRIMIR ***** 
    ?>
<?php else : ?>
    <p>No se han agregado vuelos a este itinerario.</p>
<?php endif; ?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#tic-imprimir-reporte-vuelos').on('click', function() {
            window.print();
        });
    });
</script>