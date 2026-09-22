var user;

$(document).ready(function(){
	mostrar_cursos();
});

function mostrar_cursos() {
	escollirOpcio();
}

function escollirOpcio() {
	var opcio = $("#opcio").val();
	if (opcio == "generarCertificat")
		generarCertificatsDisponibles();
	else if (opcio == "enviarCertificat")
		enviarCertificatsDisponibles();
}

function generarCertificatsDisponibles() {
	$("#loading").html("<div class=\"loader\" style=\"float: left\"></div><span class=\"verificant_dades\">Carregant dades...</span></p>");

	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/generarCertificatsDisponibles.php",
		cache: false,
		type: "GET",
		success: function(data) {
			$("#cursos").html(data);
			$("#cursos table").css('display','none');

			$("#loading").html("");
		}
	});
}

function enviarCertificatsDisponibles() {
	$("#loading").html("<div class=\"loader\" style=\"float: left\"></div><div style=\"float: left; padding-left: 5px; font-size: 12px;\">Cercant cursos disponibles...</div>");
	user =  $("#session").html();

	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/enviarCertificatsDisponibles.php?user="+user,
		cache: false,
		type: "GET",
		success: function(data) {
			$("#cursos").html(data);
			$("#loading").html("");
		}
	});
}
