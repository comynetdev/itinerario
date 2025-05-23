// Hacer estas variables y funciones globales para que sean accesibles desde otros scripts
var escalaCount = 0;

function agregarEscala() {
    console.log('Función agregarEscala() llamada. escalaCount actual ANTES de incrementar:', escalaCount);
    escalaCount++; // Incrementar el contador global
    var escalaIndex = escalaCount - 1; // Para los arrays basados en 0
    var escalaHtml = `
        <div class="escala-item tic-form-flexFields" data-escala-index="${escalaIndex}">
            <h4>Escala ${escalaCount}</h4>
            <div class="tic-form-group">
                <label for="aeropuerto_escala_${escalaIndex}">Aeropuerto:</label>
                <input type="text" id="aeropuerto_escala_${escalaIndex}" name="escalas[${escalaIndex}][aeropuerto]">
            </div>
            <div class="tic-form-group">
                <label for="fecha_hora_llegada_escala_${escalaIndex}">Fecha y Hora de Llegada:</label>
                <input type="datetime-local" id="fecha_hora_llegada_escala_${escalaIndex}" name="escalas[${escalaIndex}][llegada]">
            </div>
            <div class="tic-form-group">
                <label for="fecha_hora_salida_escala_${escalaIndex}">Fecha y Hora de Salida:</label>
                <input type="datetime-local" id="fecha_hora_salida_escala_${escalaIndex}" name="escalas[${escalaIndex}][salida]">
            </div>
            <button type="button" class="remove-escala-btn button">Eliminar Escala</button>
        </div>
    `;
    console.log('HTML de escala creado. Nuevo escalaCount:', escalaCount);
    jQuery('#escalas-wrapper').append(escalaHtml); // Usar jQuery consistentemente
}

function actualizarNumeracionEscalas() {
    console.log('actualizarNumeracionEscalas llamada');
    var contadorVisual = 1;
    jQuery('#escalas-wrapper .escala-item').each(function() {
        jQuery(this).find('h4').text('Escala ' + contadorVisual);
        // Aquí podrías re-indexar los IDs y names si es estrictamente necesario,
        // pero usualmente el backend maneja bien los índices discontinuos si es un array.
        // Por ahora, solo actualizamos el texto del encabezado.
        contadorVisual++;
    });
    // Actualizar el contador global al número real de elementos
    escalaCount = jQuery('#escalas-wrapper .escala-item').length;
    console.log('Numeración de escalas actualizada. Nuevo escalaCount:', escalaCount);
}

jQuery(document).ready(function($) {

    // Usar delegación de eventos para elementos dentro de #tic-module-content
    // ya que este contenedor se carga/actualiza vía AJAX.

    // Manejador para el checkbox "Tiene escalas"
    $('#tic-module-content').on('change', '#tiene_escalas', function() {
        console.log('#tiene_escalas cambiado. Checked:', this.checked, 'escalaCount actual:', escalaCount);
        if (this.checked) {
            $('#escalas-container').slideDown();
            // Solo añadir la primera escala si no hay ninguna y el contenedor está vacío
            if ($('#escalas-wrapper .escala-item').length === 0) {
                 escalaCount = 0; // Resetear por si acaso antes de añadir la primera
                 agregarEscala();
            }
        } else {
            $('#escalas-container').slideUp();
            $('#escalas-wrapper').empty(); // Limpiar los campos de escalas
            escalaCount = 0; // Resetear el contador
            console.log('Contenedor de escalas limpiado y ocultado.');
        }
    });

    // Manejador para el botón "Agregar Escala"
    $('#tic-module-content').on('click', '#add-escala-btn', function(e) {
        e.preventDefault();
        console.log('Botón Agregar Escala clickeado');
        agregarEscala();
    });

    // Manejador para el botón "Eliminar Escala"
    $('#tic-module-content').on('click', '.remove-escala-btn', function(e) {
        e.preventDefault();
        console.log('Botón Eliminar Escala clickeado');
        $(this).closest('.escala-item').remove();
        // Volver a numerar y actualizar el contador después de eliminar
        actualizarNumeracionEscalas(); 
    });

    // El código de carga inicial del reporte que estaba aquí fue eliminado correctamente.
});