let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let cntImatges = 0;

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel').html(message);

		//quan es clica a qualsevol lloc fora del select, amago el desplegable
		// $(window).click(function() {
		// 	//amago el desplegable
		// 	$('.select .select-list').hide();
		// 	//retorno el triangle com esta per defecte
		// 	var triangle = $('.select').find("i");
		// 	triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
		// });
		//
		// $(".select").click(function(e) {
		// 	e.stopPropagation();
		// 	var lista = $(this).find("ul"),
		// 		triangle = $(this).find("i");
		// 	e.preventDefault();
		// 	$(this).find("ul").toggle();
		// 	if (lista.is(":hidden")) {
		// 		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
		// 	} else {
		// 		triangle.removeClass("fa-angle-down").addClass("fa-angle-up");
		// 	}
		// });
		//
		// $("#cursos-superat").on("click", "#xxxx", function(e) {
		// 	//xxxx
		// });
		$("#imatges").on("click", ".img-curs", function(e) {
			var modal = "<div class='modal' id='modalImgCurs"+cntImatges+"' tabindex='-1' role='dialog' ";
			modal += "aria-labelledby='modalLoading' style='display: none;' aria-hidden='true'>";
			modal += "<div class='modal-dialog modal-dialog-centered' role='document'>";
			modal += "<div class='modal-content w-100 border-0'>";
			modal += "<div class='modal-body'>";
			modal += "<img src='" + $(this).attr('src') + "' class='w-100' />";
			modal += "</div></div></div>";

			$('#imatges').append( modal );
			$("#modalImgCurs"+cntImatges).modal('show');

			cntImatges++;
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
