<?php

/**
 * Template for displaying the Accommodation report.
 *
 * Variables disponibles:
 * $itinerary_name_for_report (string) - Nombre del itinerario (si aplica y se encontró).
 * $alojamientos_data (array) - Array de objetos/arrays con los datos de los alojamientos.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}
?>

<?php
// Mostrar el nombre del itinerario si está disponible (principalmente para usuarios registrados)
if (isset($itinerary_name_for_report) && !empty($itinerary_name_for_report)) : ?>
    <h2>Itinerario: <?php echo esc_html($itinerary_name_for_report); ?></h2>
<?php endif; ?>

<?php // --- INICIO NUEVA ANOTACIÓN DE MONEDA --- 
?>
<?php if (isset($active_itinerary_report_currency) && !empty($active_itinerary_report_currency) && is_user_logged_in()) : // Mostrar solo si hay moneda y es usuario logueado 
?>
    <p class="tic-report-currency-notice">
        <em>Nota: Todos los montos y totales en este reporte de alojamiento se muestran en
            <strong><?php echo esc_html($active_itinerary_report_currency); ?></strong>
            (Moneda de Reporte del Itinerario).</em>
    </p>
<?php endif; ?>
<?php // --- FIN NUEVA ANOTACIÓN DE MONEDA --- 
?>

<h3>Reporte de Alojamientos</h3>

<?php if (!empty($alojamientos_data)) : ?>
    <table class="tic-table tic-accommodation-report-table">
        <thead>
            <tr>
                <th>País</th>
                <th>Ciudad/</br>Población</th>
                <th>Hotel/</br>Hospedaje</th>
                <th>Dirección</th>
                <th>Fecha Entrada</th>
                <th>Fecha Salida</th>
                <th>Nº Noches</th>
                <th>Precio</br>Noche</th>
                <th>Moneda (Precio/Noche)</th>
                <th>Tipo de Cambio</th>
                <th>Precio Total</th>
                <th>Fecha Pago Reserva</th>
                <th>Cód. Reserva</th>
                <th>App/Sitio/</br>Reserva</th>
                <th class="tic-acciones">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($alojamientos_data as $alojamiento) : ?>
                <?php
                // Asegurarse de que $alojamiento sea un objeto si no lo es ya
                // (Aunque el método mostrar_reporte_alojamiento intenta convertirlo)
                $alojamiento = (object) $alojamiento;
                ?>
                <tr>
                    <td><?php echo isset($alojamiento->pais) ? esc_html($alojamiento->pais) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->ciudad_poblacion) ? esc_html($alojamiento->ciudad_poblacion) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->hotel_hospedaje) ? esc_html($alojamiento->hotel_hospedaje) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->direccion_hotel) ? nl2br(esc_html($alojamiento->direccion_hotel)) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->fecha_entrada) ? esc_html(date('Y-m-d H:i', strtotime($alojamiento->fecha_entrada))) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->fecha_salida) ? esc_html(date('Y-m-d H:i', strtotime($alojamiento->fecha_salida))) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->numero_noches) ? esc_html($alojamiento->numero_noches) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->precio_noche) ? esc_html(number_format((float)$alojamiento->precio_noche, 2)) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->moneda_precio_noche) ? esc_html($alojamiento->moneda_precio_noche) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->tipo_de_cambio_alojamiento) ? esc_html(number_format((float)$alojamiento->tipo_de_cambio_alojamiento, 4)) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->precio_total_alojamiento) ? esc_html(number_format((float)$alojamiento->precio_total_alojamiento, 2)) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->fecha_pago_reserva) ? esc_html(date('Y-m-d', strtotime($alojamiento->fecha_pago_reserva))) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->codigo_reserva) ? esc_html($alojamiento->codigo_reserva) : 'N/A'; ?></td>
                    <td><?php echo isset($alojamiento->aplicacion_pago_reserva) ? esc_html($alojamiento->aplicacion_pago_reserva) : 'N/A'; ?></td>
                    <td> <?php // Celda de Acciones 
                            ?>
                        <?php
                        // Asegurarnos que $alojamiento sea un objeto y tenga un ID
                        // (para registros de la BD; la edición de datos de sesión es más compleja y la veremos si es necesaria)
                        if (is_object($alojamiento) && isset($alojamiento->id)) : ?>
                            <button type="button"
                                class="edit-link-button"
                                data-accommodation-id="<?php echo esc_attr($alojamiento->id); ?>">
                                Editar
                                <i class="material-icons">edit</i></button>
                            <?php // ***** INICIO NUEVO BOTÓN ELIMINAR ***** 
                            ?>
                            <button type="button"
                                class="delete-link-button"
                                data-accommodation-id="<?php echo esc_attr($alojamiento->id); ?>"
                                style="margin-left: 5px;">
                                Eliminar
                                <i class="material-icons">delete</i></button>
                            <?php // ***** FIN NUEVO BOTÓN ELIMINAR ***** 
                            ?>
                        <?php else: ?>
                            N/A <?php // No hay acciones si no es un registro con ID (ej. datos de sesión antiguos sin ID) 
                                ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php // ***** INICIO BOTÓN IMPRIMIR ***** 
    ?>
    <button type="button" id="tic-imprimir-reporte-alojamiento" class="button" style="margin-top: 15px;">Imprimir Reporte de Alojamiento</button>
    <?php // ***** FIN BOTÓN IMPRIMIR ***** 
    ?>

<?php else : ?>
    <p>No se han agregado registros de alojamiento a este itinerario.</p>
<?php endif; ?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Asegurarnos que el evento solo se vincule una vez, incluso si el reporte se recarga
        // Es mejor usar delegación si este contenido se recarga mucho en la misma página,
        // pero si el reporte se carga en #tic-module-content, este script se re-ejecuta.
        // Por ahora, un .off().on() es una forma simple de evitar múltiples bindings.
        $('body').off('click', '#tic-imprimir-reporte-alojamiento').on('click', '#tic-imprimir-reporte-alojamiento', function() {
            window.print();
        });
    });
</script>