/* AQUEST FITXER S'UTILITZA A:
/intranet/estadistiques.php   */

//busquem tots els cursos que hi ha o hi ha hagut a PrisMa
function cursos_disponibles()
{

	$.ajax({
		url: "https://old.prisma.cat/ajax/stats_cursos_disponibles.php",
		cache: false,
		type: "GET",
		success: function(cursos) {
			$("#cursos").html(cursos);
		}
	});
}
