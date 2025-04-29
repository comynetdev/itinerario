<?php
/**
 * Template for displaying the flights report.
 */

if (!defined('ABSPATH')) {
    die('Acceso directo no permitido.');
}

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
                <th>Escalas</th>
                <th>Precio por Persona</th>
                <th>Moneda</th>
                <th>Personas</th>
                <th>Tipo de Cambio</th>
                <th>Precio Total</th>
                <th>Reserva</th>
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
                        <?php if ($vuelo->tiene_escalas && isset($vuelo->escalas_aeropuertos)) : ?>
                            <?php echo esc_html($vuelo->escalas_aeropuertos); ?>
                            <br>
                            Llegada: <?php echo esc_html($vuelo->escalas_llegadas); ?>
                            <br>
                            Salida: <?php echo esc_html($vuelo->escalas_salidas); ?>
                        <?php else : ?>
                            No
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(number_format($vuelo->precio_persona, 2)); ?></td>
                    <td><?php echo esc_html($vuelo->moneda_usuario); ?></td>
                    <td><?php echo esc_html($vuelo->numero_personas); ?></td>
                    <td><?php echo esc_html(number_format($vuelo->tipo_de_cambio, 2)); ?></td>
                    <td><?php echo esc_html(number_format($vuelo->precio_total_vuelos, 2)); ?></td>
                    <td><?php echo esc_html($vuelo->codigo_reserva); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button id="tic-imprimir-reporte-vuelos" class="button">Imprimir Reporte</button>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#tic-imprimir-reporte-vuelos').on('click', function() {
                window.print();
            });
        });
    </script>
<?php else : ?>
    <p>No se han agregado vuelos a este itinerario.</p>
<?php endif; ?>