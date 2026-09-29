function gest(n,mes_num)
{
  document.gestions.gestiook.value = 'S';
  document.gestions.num.value = n;
  document.gestions.mes_num.value = mes_num;
}

function comprovar_validar()
{
  informeenviat=false;
  facturaadjuntada=false;
  facturaPDF=false;

  if (document.gestions.informe.value != "") {
    $('#modalErrors .modal-title').html("No pots gestionar el cobrament!");
    $('#modalErrors .modal-body').html("Cal enviar l'informe de curs abans d'enviar la teva gestio!");
    $('#modalErrors').modal('show');
  }
  else
    informeenviat=true;

  if (document.gestions.archivo1.value!="") {
    if (document.gestions.archivo1.value.substr(-3)!="pdf") {
        $('#modalErrors .modal-title').html("Fitxer invàlid!");
        $('#modalErrors .modal-body').html("El fitxer adjunt ha de ser un PDF!");
        $('#modalErrors').modal('show');
    }
    else
      facturaPDF = true;

    facturaadjuntada=true;
  }
  else {
    $('#modalErrors .modal-title').html("No pots gestionar el cobrament!");
    $('#modalErrors .modal-body').html("Has d'adujanr un fitxer!");
    $('#modalErrors').modal('show');
  }
  return (informeenviat && facturaadjuntada && facturaPDF)
}

function comprovar_revisar()
{
  enviarmail=false;
  document.gestions.revisio_o_gestionar.value="1";
  if (document.gestions.comentaris.value == "") {
      $('#modalErrors .modal-title').html("No pots enviar la revisió!");
      $('#modalErrors .modal-body').html("No has escrit cap text per a revisió!");
      $('#modalErrors').modal('show');
  }
  else
    enviarmail=true;

  return (enviarmail)
}

function modalError() {
	var modal = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' style='display: none;' aria-hidden='true'>";
	modal += "<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>";
	modal += "<div class='modal-content w-100 border-0 p-4 mh-100 ps2'>";
	modal += "<div class='modal-header border-0 d-flex flex-column justify-content-center align-items-center position-relative p-0'>";
	modal += "<p class='modal-title font-weight-bold text-center text-danger'></p>";
	modal += "<button type='button' class='close' data-dismiss='modal'>×</button>";
	modal += "</div>";
	modal += "<div class='modal-body ps2 pt-2 text-center' id='modalErrorsBody'></div>";
	modal += "<div class='modal-footer border-0 d-flex align-items-center justify-content-center p-0'>";
	modal += "<a type='button' class='btn btn-danger text-white' aria-label='Close' data-dismiss='modal'>Tanca</a>";
	modal += "</div>";
	modal += "</div>";
	modal += "</div></div>";
	return modal;
}
