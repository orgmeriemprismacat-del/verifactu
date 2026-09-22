let path = "https://old.prisma.cat/intranet/revisions/";
let revisat,
	codeRev = '',
	codeErrorsGen = '',
	codeErrorsLectures = '',
	codeErrorsMediateca = '',
	codeErrorsBiblio = '',
	codeErrorsAltres = '';
let numApartatsLectures = 3; //indico el numero de l'apartat de lectures;
let numApartatBiblio = 4; //indico el numero de l'apartat de biblio;
let numApartatMediateca = 5; //indico el numero de l'apartat de mediateca;
let num_apartats = "", user, shortname;

function mostrarRevisio() {
   num_apartats = $('#num_apartats').val().trim();
   var hores = $('#hores').val().trim();
   shortname = $('#shortname').val().trim();
   var course = $('#course').val().trim();

   var showTable = $.ajax({
		url: path + "ajax/mostrarTaula.php",
		method: "GET",
		data: {
			hores: hores,
			shortname: shortname,
			course: course,
			num_apartats: num_apartats
		},
		dataType: "html"
	});
	showTable.done(function(msg) {
		var revisio = msg;
		$('#revisio').html(revisio);
	});

	showTable.fail(function(XMLHttpRequest, textStatus, errorThrown) {
		mostrarModalError(errorThrown);
	});
}
