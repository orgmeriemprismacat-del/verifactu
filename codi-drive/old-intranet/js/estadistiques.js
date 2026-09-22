/* AQUEST FITXER S'UTILITZA A:
/intranet/estadistiques.php   */

function mostrar_estadistiques()
{
	var curs = $("#cursos").val();
	$.ajax({
		url: "https://old.prisma.cat/ajax/calcular_estadistiques.php?&curs="+curs,
		cache: false,
		type: "GET",
		success: function(cursos) {
			$("#estadistiques").html(cursos);
		}
	});
}
