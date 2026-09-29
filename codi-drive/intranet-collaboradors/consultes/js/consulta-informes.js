/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-collaboradors/cobraments/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
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
