let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat";

/* Mostrem el main */
$.ajax({
	url: path+"ajax/mostrarMain.php?url="+urlPagina,
	cache: !1,
	type: "GET",
	success: function(message) {
		$('.mainpanel').html(message);


	}
})
