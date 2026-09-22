/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/revisions.php  */

//busquem tots els anys que hi han cu
function anys_disponibles() {
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_anys_disponibles_any_actual.php?",
		cache: false,
		type: "GET",
		success: function(anys) {
			$("#any").html(anys);
			mesos_disponibles();
		}
	});
}

function mesos_disponibles() {
	var any = document.revisions.any.value;
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_mesos_disponibles_any_actual.php?&any="+any,
		cache: false,
		type: "GET",
		success: function(mesos) {
			$("#mesos").html(mesos);
			cursos_disponibles();
		}
	});

}

//busquem tots els mesos que hi han cursos d'un any
function cursos_disponibles() {
	var any = $("#any").val();
	var mes = $("#mesos").val();
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_cursos_disponibles_revisions.php?&any="+any+"&mes="+mes+"&order=id_Curs",
		cache: false,
		type: "GET",
		success: function(cursos) {
			$("#cursos").html(cursos);
		}
	});

}

function ordenar_per_nom_curs() {
	var any = $("#any").val();
	var mes = $("#mesos").val();
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_cursos_disponibles_revisions.php?&any="+any+"&mes="+mes+"&order=NOM_CURS",
		cache: false,
		type: "GET",
		success: function(cursos) {
			$("#cursos").html(cursos);
		}
	});
}

function ordenar_per_tutor() {
	var any = $("#any").val();
	var mes = $("#mesos").val();
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_cursos_disponibles_revisions.php?&any="+any+"&mes="+mes+"&order=NOM, COGNOMS",
		cache: false,
		type: "GET",
		success: function(cursos) {
			$("#cursos").html(cursos);
		}
	});
}
