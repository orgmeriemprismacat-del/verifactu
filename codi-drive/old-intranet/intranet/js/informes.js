/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/informes.php  */

//busquem tots els mesos que hi han cursos d'un any
function cursos_disponibles() {
	var any = $("#any").val();
	var mes = $("#mesos").val();
	$.ajax({
		url: "https://old.prisma.cat/intranet/ajax/buscar_cursos_disponibles_informes.php?&any="+any+"&mes="+mes+"&order=id_Curs",
		cache: false,
		type: "GET",
		success: function(cursos) {
			$("#cursos").html(cursos);		   
		}
	});
}