let mdlUsername = $('#mdl-user-username').html().trim();

// function gest(n,mes_num)
// {
//   document.gestions.gestiook.value = 'S';
//   document.gestions.num.value = n;
//   document.gestions.mes_num.value = mes_num;
// }
//
// function comprovar_validar()
// {
//   informeenviat=false;
//   facturaadjuntada=false;
//   facturaPDF=false;
//
//   if (document.gestions.informe.value != "") {
//     $('#modalErrors .modal-title').html("No pots gestionar el cobrament!");
//     $('#modalErrors .modal-body').html("Cal enviar l'informe de curs abans d'enviar la teva gestio!");
//     $('#modalErrors').modal('show');
//   }
//   else
//     informeenviat=true;
//
//   if (document.gestions.archivo1.value!="") {
//     if (document.gestions.archivo1.value.substr(-3)!="pdf") {
//         $('#modalErrors .modal-title').html("Fitxer invàlid!");
//         $('#modalErrors .modal-body').html("El fitxer adjunt ha de ser un PDF!");
//         $('#modalErrors').modal('show');
//     }
//     else
//       facturaPDF = true;
//
//     facturaadjuntada=true;
//   }
//   else {
//     $('#modalErrors .modal-title').html("No pots gestionar el cobrament!");
//     $('#modalErrors .modal-body').html("Has d'adjuntar un fitxer!");
//     $('#modalErrors').modal('show');
//   }
//   return (informeenviat && facturaadjuntada && facturaPDF)
// }
//
// function comprovar_revisar()
// {
//   enviarmail=false;
//   document.gestions.revisio_o_gestionar.value="1";
//   if (document.gestions.comentaris.value == "") {
//       $('#modalErrors .modal-title').html("No pots enviar la revisió!");
//       $('#modalErrors .modal-body').html("No has escrit cap text per a revisió!");
//       $('#modalErrors').modal('show');
//   }
//   else
//     enviarmail=true;
//
//   return (enviarmail)
// }

function posar() {
  $('#archivo1').prop("required", true);
  document.getElementById('adj').style.display=''
}
function treure() {
  $('#archivo1').removeAttr("required");
  document.getElementById('adj').style.display='none';
}

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-collaboradors/cobraments/ajax/mostrarMain.php",
	method: "GET",
	data: {
		url : urlPagina,
		mdlUsername : mdlUsername
	},
	dataType: "html"
});


/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel > #head-title').html(message);

		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

$(window).resize( resizeHeightBreadrcump );

/* Ajusta estil. Quan l'amplada de la pantala >= 991, el sidebar es
manté visible. Quan l'amplada de la pantalla < 991, el sidebar s'oculta */
function resizeHeightBreadrcump() {
		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');
}
