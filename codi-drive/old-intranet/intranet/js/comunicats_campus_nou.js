/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/comunicats.php  */

//busquem tots els anys que hi han cu
function anys_disponibles() {
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_anys_disponibles.php?",
		cache: false,
		type: "GET",
		success: function(anys) {
			$("#any").html(anys);
		}
	});
}

//busquem tots els mesos que hi han cursos d'un any
function mesos_disponibles() {
	var any = $("#any").val();
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_mesos_disponibles.php?&any="+any,
		cache: false,
		type: "GET",
		success: function(mesos) {
			$("#mesos").html(mesos);
		}
	});

}

function mostrar_cursos() {
	var mes = $("#mesos").val();
	if (mes!="Cap")
		cursos_disponibles($("#hores").val());
}

function cursos_disponibles(hores) {
	$("#loading").html("<div class=\"loader\" style=\"float: left\"></div><div style=\"float: left; padding-left: 5px; font-size: 12px;\">Cercant cursos disponibles...</div>");
	var any = $("#any").val();
	var mes = $("#mesos").val();
	var comunicat = $("#comunicat").val();

	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/getcursos_campusnou_"+comunicat+".php?mes="+mes+"&any="+any+"&hores="+hores,
		cache: false,
		type: "GET",
		success: function(data) {
			$("#cursos").html(data);
			$("#loading").html("");
		}
	});
}
