/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/revisions.php
/intranet/informes.php   */

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
	var any = document.revisions.any.value;
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_mesos_disponibles.php?&any="+any,
		cache: false,
		type: "GET",
		success: function(mesos) {
			$("#mesos").html(mesos);		   
		}
	});
	
}