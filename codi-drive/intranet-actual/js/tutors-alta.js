var urlPagina = window.location.pathname.split('?')[0];

/* Mostrem el menu lateral*/
$.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarSideBar.php",
	cache: !1,
	type: "GET",
	success: function(message) {
		/* Message conté l'estructura del menú lateral */
		$('.sidebar').html(message);
		/* Mostrem el bloc de l'usuari del menú lateral */
		$.ajax({
			url: "https://intranet.prisma.cat/ajax/mostrarSideBarUser.php",
			cache: !1,
			type: "GET",
			success: function(message) {
				$('.user').html(message)
			}
		});
		/* Mostrem els apartats del menú lateral */
		$.ajax({
			url: "https://intranet.prisma.cat/ajax/mostrarSideBarMenu.php?url="+urlPagina,
			cache: !1,
			type: "GET",
			success: function(message) {
				$('.sidebar > .sidebar-wrapper > .nav').html(message);
				if ($(".active").parent().parent().hasClass("collapse")) {
					$(".active").parent().parent().addClass('show');
				}

			}
		})

	}
})

/* Mostrem el main */
$.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php?url="+urlPagina,
	cache: !1,
	type: "GET",
	success: function(message) {
		$('.mainpanel').html(message);
	}
})
