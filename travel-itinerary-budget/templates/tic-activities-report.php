<?php

/**
 * Template for displaying the Activities report.
 *
 * Variables disponibles:
 * $itinerary_name_for_report (string)
 * $active_itinerary_report_currency (string)
 * $actividades_data (array) - Array de objetos con los datos de las actividades.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}
?>

<?php if (isset($itinerary_name_for_report) && !empty($itinerary_name_for_report)) : ?>
    <h2>Itinerario: <?php echo esc_html($itinerary_name_for_report); ?></h2>
<?php endif; ?>

<?php if (isset($active_itinerary_report_currency) && !empty($active_itinerary_report_currency) && is_user_logged_in()) : ?>
    <p class="tic-report-currency-notice">
        <em>Nota: Todos los montos y totales en este reporte de actividades se muestran en
            <strong><?php echo esc_html($active_itinerary_report_currency); ?></strong>
            (Moneda de Reporte del Itinerario).</em>
    </p>
<?php endif; ?>

<h3>Reporte de Actividades y Tours</h3>

<?php if (!empty($actividades_data)) : ?>
    <table class="tic-table tic-activities-report-table">
        <thead>
            <tr>
                <th>País</th>
                <th>Ciudad/</br>Población</th>
                <th>Fecha</th>
                <th>Actividad/Tour</th>
                <th>Precio/</br>Persona</th>
                <th>Personas</th>
                <th>Moneda (Precio)</th>
                <th>Tipo de Cambio</th>
                <th>Precio Total</th>
                <th>Proveedor</th>
                <th>Cód. Reserva</th>
                <th>Notas</th>
                <th class="tic-acciones">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($actividades_data as $actividad) :
                $actividad = (object) $actividad; // Asegurar que sea objeto 
            ?>
                <tr>
                    <td><?php echo isset($actividad->pais) ? esc_html($actividad->pais) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->ciudad_poblacion) ? esc_html($actividad->ciudad_poblacion) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->fecha_actividad) ? esc_html(date('Y-m-d H:i', strtotime($actividad->fecha_actividad))) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->nombre_tour_actividad) ? esc_html($actividad->nombre_tour_actividad) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->precio_persona) ? esc_html(number_format((float)$actividad->precio_persona, 2)) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->numero_personas) ? esc_html($actividad->numero_personas) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->moneda_precio_actividad) ? esc_html($actividad->moneda_precio_actividad) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->tipo_de_cambio_actividad) ? esc_html(number_format((float)$actividad->tipo_de_cambio_actividad, 4)) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->precio_total_actividad) ? esc_html(number_format((float)$actividad->precio_total_actividad, 2)) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->proveedor_reserva) ? esc_html($actividad->proveedor_reserva) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->codigo_reserva) ? esc_html($actividad->codigo_reserva) : 'N/A'; ?></td>
                    <td><?php echo isset($actividad->notas) ? nl2br(esc_html($actividad->notas)) : 'N/A'; ?></td>
                    <td>
                        <?php // Celda de Acciones 
                        ?>
                        <?php
                        // Asegurarnos que $actividad sea un objeto y tenga un ID
                        // (para registros de la BD; la edición de datos de sesión es más compleja)
                        if (is_object($actividad) && isset($actividad->id)) : ?>
                            <button type="button"
                                class="edit-link-button"
                                data-activity-id="<?php echo esc_attr($actividad->id); ?>">
                                Editar
                                <i class="material-icons">edit</i>
                            </button>
                            <button type="button"
                                class="delete-link-button"
                                data-activity-id="<?php echo esc_attr($actividad->id); ?>"
                                style="margin-left: 5px;">
                                Eliminar
                                <i class="material-icons">delete</i>
                            </button>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="button" id="tic-imprimir-reporte-actividades" class="button" style="margin-top: 15px;">Imprimir Reporte de Actividades</button>
<?php else : ?>
    <p>No se han agregado actividades o tours a este itinerario.</p>
<?php endif; ?>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('body').off('click', '#tic-imprimir-reporte-actividades').on('click', '#tic-imprimir-reporte-actividades', function() {
            window.print();
        });
    });
</script>