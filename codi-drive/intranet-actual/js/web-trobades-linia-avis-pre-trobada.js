let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php",
	method: "GET",
	data: {
		url: urlPagina
	},
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function(message) {
	$('.mainpanel').html(message);

	/*Quan es cliqui un botó "avis", obtinc la id, la tracto per obtenir el identificador
	de la trobada i envio un correu electrònic amb el següent format:

	FROM: Atenció a l'usuari
	TO: CORREU ALUMNE

	SUBJECT DEL MISSATGE: Avís començament de la trobada en línia «ELS PROCESSOS CREATIUS EN TEMPS D'INCERTESA» | PrisMa

	COS DEL MISSATGE:

		<p>Bona tarda a tothom,</p>

		<p>Us recordem que avui dijous <strong>14 de maig a les 17:00 h</strong> comença
		la trobada en línia amb la <strong>Núria Banal</strong>:
		<strong style='color:#324569'>APLICAR ELS PROCESSOS CREATIUS
		I LES ARTS PER EDUCAR EN LA INCERTESA DE VIURE.</strong></p>

		<p>Podeu accedir-hi des de l’enllaç següent: <a target='_blank' title=\"APLICAR
		ELS PROCESSOS CREATIUS I LES ARTS PER EDUCAR EN LA INCERTESA DE VIURE\"
		style='color:#496baa;text-decoration:none'
		href='https://www.prisma.cat/formacio/trobades-en-linia-nuria-banal'>www.prisma.cat/formacio/trobades-en-linia-nuria-banal</a>.</p>

		<p>Us hi esperem! :)</p>
	*/
	$("#trobades-linia").on("click", ".avis-trobada", function(e) {
		var idButton = $(this).attr('id');
		var idTrobada = idButton.split("-")[2];

		var updValor = $.ajax({
			url: path + "web/trobades-linia/avis-trobada.php",
			method: "GET",
			data: {
				idTrobada : idTrobada
			},
			dataType: "html"
		});

		updValor.done(function( msgValor ) {
			if ( msgValor.toLowerCase().includes("error") ) {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idTrobada+"</strong>");
				mostrarModalError();
			}
			else {
				window.location.reload();
			}
		});

		updValor.fail(function( jqXHR, textStatus, errorThrown ) {
			rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al actualitzar el registre <strong>"+idCurs+"</strong>: " );
		});

	});

	//Afegeixo un botó que té la funcionalitat de mostrar totes les trobades en línia antigues
	if ( $('#mostra-trobades-anteriors')[0] ) {
		$("#trobades-linia").on("click", "#mostra-trobades-anteriors", function(e) {
			$("#trobades-linia").fadeIn();
		});
	}

});

requestMain.fail(function(jqXHR, textStatus, errorThrown) {
	errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: ");
});
