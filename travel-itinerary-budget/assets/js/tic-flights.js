jQuery(document).ready(function($) {
  var escalaCount = 0;

  $('#tiene_escalas').change(function() {
      if (this.checked) {
          $('#escalas-container').slideDown();
          if (escalaCount === 0) {
              agregarEscala(); // Agregar la primera escala automáticamente
          }
      } else {
          $('#escalas-container').slideUp();
          $('#escalas-wrapper').empty(); // Limpiar los campos de escalas
          escalaCount = 0;
      }
  });

  $(document).on('click', '#add-escala-btn', function(e) {
    e.preventDefault();
    console.log('Botón Agregar Escala clickeado'); // Vuelve a agregar este log
    agregarEscala();
});

  function agregarEscala() {
    console.log('Función agregarEscala() llamada'); // Agregar este log
      escalaCount++;
      var escalaIndex = escalaCount - 1;
      var escalaHtml = `
          <div class="escala-item">
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
      console.log('HTML de escala creado:', escalaHtml); // Agregar este log
      $('#escalas-wrapper').append(escalaHtml);

      $('.remove-escala-btn').off('click').on('click', function(e) {
          e.preventDefault();
          console.log('Botón Eliminar Escala clickeado'); // Agregar este log
          $(this).closest('.escala-item').remove();
          escalaCount--;
          actualizarNumeracionEscalas();
      });
  }

  function actualizarNumeracionEscalas() {
      $('#escalas-wrapper .escala-item').each(function(index) {
          $(this).find('h4').text('Escala ' + (index + 1));
      });
  }
    
    // Cargar el reporte inicial si hay un itinerario ID
    if ($('input[name="itinerario_id"]').val() > 0) {
        $.post(tic_ajax_object.ajaxurl, {
            action: 'tic_mostrar_reporte_vuelos', // Acción correcta
            itinerario_id: $('input[name="itinerario_id"]').val()
        }, function(reporte) {
            $('#tic-flights-report-container').html(reporte);
        });
    } else {
        // Cargar el reporte desde la sesión si no hay itinerario ID
        $.post(tic_ajax_object.ajaxurl, {
            action: 'tic_mostrar_reporte_vuelos' // Acción correcta
        }, function(reporte) {
            $('#tic-flights-report-container').html(reporte);
        });
    }
});