let path = "https://intranet.prisma.cat/ajax/";
let path2 = "https://intranet.prisma.catauth/";

$(".contingut").keyup(function(evObject) {
	if (evObject.keyCode == 13) $('.boto-blau').click();
});

$('.contingut').on('click', '.boto-blau', function(){
	var username = $('#username').val();
	var password = $('#password').val();

	var reqSession = $.ajax({
		url: path + "iniciaSessio.php",
		global: false,
		method: "GET",
		data: {
			username: username,
			password: password
		},
		dataType: "html"
	});
	reqSession.done(function( message ) {
		if (message.indexOf("success")>=0) {
			var authGoogle = $.ajax({
				url: path2 + "authGoogleScopes.php",
				global: false,
				data: {
					cns: 'exist'
				},
				method: "GET",
				dataType: "html"
			});
			authGoogle.done(function( msg ) {
				if (msg == "No code found") {
					window.open(path2 + "authGoogleScopes.php", '_self');
				}
				else if ( msg == "1" ){
					urlSucc = "https://intranet.prisma.catinici/";
					window.location.replace(urlSucc);
				}
				else {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut algun error a l'hora d'iniciar sessió");
					mostrarModalError();
				}
			});
			authGoogle.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora d'iniciar sessió:");
			});
		}
		else {
			var error = message.split('|')[1];
			var msgError = '';
			if ( error != '' ) {
				msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
				msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
				msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
				msgError += "<i class='material-icons'>close</i></button>";
				msgError += "<span>" + error + "</span></div>";
				$('#alerts').html(msgError);
			}
			else {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("Hi ha hagut algun error a l'hora d'iniciar sessió");
				mostrarModalError();
			}
		}
	});
	reqSession.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora d'iniciar sessió:");
	});
});
