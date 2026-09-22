let pageFooter = '', bannersRegalaFooter = '', bannerDescFooter = '', inici1 = 1;
var marginTop;
function mostrarHeaderFooter() {
	$.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/mostrar_header_2.php",
		cache: !0,
		type: "GET",
		success: function(pagina) {
			$("header").html(pagina);
			$('.closebtn').css('display', 'none');
			if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
				$('.navbar-nav > li').addClass('mobile');
			} else {
				$('.navbar-nav > li').addClass('computer');
			}
			$('.prisma-header').on('focus', '.form-control', function(){
				$(this).next().next().addClass('active');
			});
			$('.prisma-header').on('blur', '.form-control', function(){
				if ($(this).val()=='')
					$(this).next().next().removeClass('active');
			});
		}
	});
	$.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/mostrar_footer_2.php",
		cache: !0,
		type: "GET",
		success: function(pagina) {
			pageFooter = pagina;
		}
	})
}
mostrarHeaderFooter();
var urlPagina = window.location.pathname.split('?')[0];
if (urlPagina.substr(-1) == "/") urlPagina = urlPagina.substr(0, urlPagina.length - 1);
var dispositiu;
if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
	dispositiu = "mobil";
	$('#boto-tancar').css('display', 'none');
	$('#top-menu').remove()
} else {
	dispositiu = "ordinador"
}

var filtres = [
	[0],
	[],
	[],
	[],
	'ordre|TITOL|ASC|1',
	[0]
] //edicions, hores, temes, nivells, ordre
var nousCursos = 0;
var packs = 0;
var subvencio = 0;
var cdd = 0;
var mixtos = 0;
var limitPantallaTablet = 992;
var limitPantallaMovil = 660;
var vistaOrd = false;
var vistaTablet = false;
var vistaMov = false;
var inici = true;
var iniciPromoFiltre = true;
var iniciFiltreTematica = true;
var screenWitdh = parseInt($(this).width());

iniciPack = 0;

vectUrlPagina = urlPagina.split('/');
urlPagina = "/" + vectUrlPagina[1];
if (typeof vectUrlPagina[2] !== "undefined" && typeof vectUrlPagina[3] !== "undefined")  {
	filtres[0][0] = "ed|" + vectUrlPagina[2] + "|" + vectUrlPagina[3];
}
if ( vectUrlPagina[1] == 'packs' ) {
	packs = 1;
	iniciPack = 1;
}
if ( vectUrlPagina[2] == 'novetats' ) {
	nousCursos = 1;
}
if ( vectUrlPagina[2] == 'cdd' ) {
	cdd = 1;
}
if ( vectUrlPagina[2] == 'mixtos' ) {
	mixtos = 1;
}
if ( vectUrlPagina[2] == 'subvencionats' ) {
	subvencio = 1;
}
if ( vectUrlPagina[2] == 'promo20anys' ) {
	filtres[1][0] = '60';
	filtres[1][1] = '100';
	$("#hores_60").prop("checked", true);
	$("#hores_100").prop("checked", true);
}
if ( vectUrlPagina[2] == 'salut-mental' ) {
	filtres[2][0] = '13|salut_mental';
	$("#tema_salut_mental").prop("checked", true);
}
if ( vectUrlPagina[2] == 'autoconeixement' ) {
	filtres[2][0] = '8|vida_autoconeixement';
	$("#tema_vida_autoconeixement").prop("checked", true);
}
if ( vectUrlPagina[2] == 'educacio-emocional' ) {
	filtres[2][0] = '3|educacio_emocional';
	$("#tema_educacio_emocional").prop("checked", true);
}
if ( vectUrlPagina[2] == 'totes-edicions' ) {
	filtres[5][0] = '1';
	$("#edicions_totes").prop("checked", true);
}

var screenWidthCurs = parseInt($(this).width());
if (screenWidthCurs >= limitPantallaTablet) vistaOrd = true;
else if (screenWidthCurs < limitPantallaTablet && screenWidthCurs >= limitPantallaMovil) vistaTablet = true;
else if (screenWidthCurs < limitPantallaMovil) vistaMov = true;

var reqEdicions = $.ajax({
	url: "https://www.prisma.cat/ajax/consultaEdicionsEstiu.php",
	method: "GET",
	dataType: "html"
});

reqEdicions.done(function( variables ) {
	var edicions = variables.split("#");
	/* Afegim les edicions disponibles en els filtres */
	var i = 0;
	if (!inici || !(typeof vectUrlPagina[2] !== "undefined" && typeof vectUrlPagina[3] !== "undefined")) {
		for (i=0; i<variables.split("#").length; i++) {
			filtres[0][i] = edicions[i];
		}
	}
	else if (typeof vectUrlPagina[2] !== "undefined" && typeof vectUrlPagina[3] !== "undefined") {
		filtres[0][0] = "ed|" + vectUrlPagina[2] + "|" + vectUrlPagina[3];
	}
	inici = false;
	/* Mostrar el bloc dels cursos */
	mostrarBlocCursos();
});

var reqTitolFiltres = $.ajax({
	async: !0,
	url: "https://www.prisma.cat/ajax/mostrar_titol_filtres_cursos_estiu.php",
	method: "GET",
	dataType: "html"
});
var reqBlocFiltres = $.ajax({
	async: !0,
	url: "https://www.prisma.cat/ajax/mostrar_bloc_filtres.php",
	method: "GET",
	dataType: "html"
});

reqTitolFiltres.done(function( titol ) {
	$(".cnt-titol-filtres").html(titol);

	/* Quan es clica un element del desplegable, es canvia l'orientació de les fletxes*/
	$(".cnt-cursos .filtres").click(function(e) {
		toogleLlistat(e, $(this).attr('id'))
	});
});

reqTitolFiltres.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request del títol: " );
});

reqBlocFiltres.done(function( bloc ) {
	$(".cnt-bloc-filtres").html(bloc);

	if (typeof vectUrlPagina[2] !== "undefined" && typeof vectUrlPagina[3] !== "undefined") {
		$('#edicions .element-selected').html( $(".ed_"+vectUrlPagina[2]+"_"+vectUrlPagina[3]+" a").html() );
	}

	/* Quan es clica fora del select, s'amaga automaticament */
	$(window).click(function() {
		$('.select-list').hide();
	});

	if ( vectUrlPagina[2] == 'promo20anys' && iniciPromoFiltre ) {
		$("#filtres #hores_60").prop("checked", true);
		$("#filtres-mobil #hores_60").prop("checked", true);
		$("#filtres #hores_100").prop("checked", true);
		$("#filtres-mobil #hores_100").prop("checked", true);
		iniciPromoFiltre = 0;
	}
	if ( vectUrlPagina[2] == 'salut-mental' && iniciFiltreTematica ) {
		$("#filtres #tema_salut_mental").prop("checked", true);
		$("#filtres-mobil #tema_salut_mental").prop("checked", true);
		iniciFiltreTematica = 0;
	}
	if ( vectUrlPagina[2] == 'autoconeixement' && iniciFiltreTematica ) {
		$("#filtres #tema_vida_autoconeixement").prop("checked", true);
		$("#filtres-mobil #tema_vida_autoconeixement").prop("checked", true);
		iniciFiltreTematica = 0;
	}
	if ( vectUrlPagina[2] == 'educacio-emocional' && iniciFiltreTematica ) {
		$("#filtres #tema_educacio_emocional").prop("checked", true);
		$("#filtres-mobil #tema_educacio_emocional").prop("checked", true);
		iniciFiltreTematica = 0;
	}
	if ( vectUrlPagina[2] == 'totes-edicions' && iniciFiltreTematica ) {
		$("#filtres #edicions_totes").prop("checked", true);
		$("#filtres-mobil #edicions_totes").prop("checked", true);
		iniciFiltreTematica = 0;
	}

	/* Quan es clica un element del desplegable, es canvia l'orientació de les fletxes*/
	// $(".cnt-cursos .filtres").click(function(e) {
	// 	console.log('3');
	//
	// 	toogleLlistat(e, $(this).attr('id'))
	// });
	/* Quan cliquen una opció del desplegable d'ordenacions o d'edicions,
		si es el filtre d'edicions, s'afegeix en el filtre el id de la llista
		si es el filtre d'ordenacions, s'afageix en el filtre el id de la llista*/
	$(".cnt-cursos .filtres").on("click", "li", function(e) {
		aplicarFiltreLlistat(e, $(this).attr('id'), $(this).find('a').html(), $(this).hasClass('edicio'), $(this).hasClass('ordre'));
	});

	/* Quan cliquen el botó de novetats, mostrar els cursos nous */
	$(".novetats").click(function(){
		aplicarFiltreNovetats();
	});
	/* Quan cliquen el botó de packs, mostrar els cursos packs */
	$(".packs").click(function() {
		aplicarFiltrePacks()
	});
	/* Quan cliquen el botó de packs, mostrar els cursos packs */
	$(".cdd.boto-header").click(function() {
		aplicarFiltreCDD()
	});
	$(".mixtos.boto-header").click(function() {
		aplicarFiltreMixtos()
	});
	/* Quan cliquen el botó de packs, mostrar els cursos packs */
	$(".subvencio.boto-header").click(function() {
		aplicarFiltreSubvencio()
	});

	/* Sticky*/
	var minHeight = $('#filtres').css('height');
	$(".sticky-sidebar").css({
		minHeight,
		minHeight
	});

	/* Versió ordinador i movil. Quan es clica un checkbox, aplica filtres */
	$('.cnt-bloc-filtres').on('click', ':checkbox', function() {
		aplicarFiltreCheckbox(this.checked, $(this).attr('id'));
	});

	/* Versió ordinador i movil. Quan es clica el botó d'eliminar filtres, elimina filters*/
	$('.cnt-bloc-filtres').on('click', '.eliminar-filtres', function() {
		eliminarFiltres();
	});
	/* Versió movil. Quan es clica el botó de mostrar filtres, mostrar filters*/
	$('.cnt-bloc-filtres').on('click', '.show-filters', function() {
		//es mostra la pantalla dels filtres (100vh de la pàgina)
		$('#filtres-mobil').show();
	});
	/* Versió movil. Quan es clica el botó d'aplicar filtres, aplicar filters*/
	$('.cnt-bloc-filtres').on('click', '.aplicar-filtres', function() {
		$('#filtres-mobil').hide();
		mostrarModalLoading();
		mostrarBlocCursos();
	});


});

reqBlocFiltres.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

/* Quan es clica un element del desplegable, es canvia l'orientació de les fletxes*/
function toogleLlistat(e, idThis) {
	e.stopPropagation();
	var lista = $('#'+idThis).find("ul"),
		triangle = $('#'+idThis).find("i");
	e.preventDefault();
	$('#'+idThis).find("ul").toggle();
	if (lista.is(":hidden")) {
		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	} else {
		triangle.removeClass("fa-angle-down").addClass("fa-angle-up");
	}
};

function mostrarTextCapcalera( idfrase ) {
	var req = $.ajax({
		url: "https://www.prisma.cat/ajax/mostrar_text_head_cursos.php",
		method: "GET",
		data: {
			filtres : JSON.stringify(filtres),
			idfrase : idfrase
		},
		dataType: "html"
	});
	req.done(function( frase ) {
		$("#frase").html(frase);
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request de la frase: " );
	});
}

function mostrarBlocCursos() {
	if ( iniciPack ) {
		packs = 1; iniciPack = 0;
	}
	if ( filtres[0][0].split('|')[1] == '07' || filtres[0][0].split('|')[1] == '08') {
		filtres[5][0] = '1';
		$("#edicions_totes").prop("checked", true);
	}
	else if ( filtres[0][0].split('|')[1] != '00' ) {
		filtres[5][0] = '0';
		$("#edicions_totes").prop("checked", false);
	}
	var reqBlocCursos = $.ajax({
		url: "https://www.prisma.cat/ajax/mostrar_cursos_only_edicions_estiu.php",
		method: "GET",
		data: {
			filtres : JSON.stringify(filtres),
			dispositiu : dispositiu,
			nousCursos : nousCursos,
			packs : packs,
			cdd : cdd,
			mixtos : mixtos,
			subvencio : subvencio
		},
		dataType: "html"
	});
	reqBlocCursos.done(function( cursos ) {
		$(".cnt-bloc-cursos").html(cursos);
		mostrarTextCapcalera('frase-nomes-estiu');
		amagarLoadingModal();

		sticky();

		if (inici1 == 1) {
			$(".cnt-baner.regala").html(bannersRegalaFooter);
			$(".cnt-baner.descomptes").html(bannerDescFooter);
			$('.cnt-baner.d-none').remove();

			$("footer").html(pageFooter);

			adjustStyle();

			$(window).resize(function() {
				adjustStyle();
			});

			$('.form-footer').on('focus', '.form-control', function(){
				$('.form-footer label').hide();
			});
			$('.form-footer').on('blur', '.form-control', function(){
				if ($('#adreca-electronica').val()=='')
					$('.form-footer label').show();
			});

			$('.form-footer').on('click', '#news', function() {
				news();
			});

			function checkAcceptCookies() {
				if(localStorage.getItem("acceptCookies") == 'true'){
					$('#cntCookies').hide();
				}
			}
			checkAcceptCookies();

			inici1 = 0;
		}
	});

	reqBlocCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
	});
}

function adjustStyle() {
	screenWidth = parseInt($(this).width());

	if (screenWidth < 575) {
		$('.prisma-footer .panel').css('display', 'none');
		$('.accordion-footer').removeClass('active');
		$('.accordion-footer').click(function() {
			this.classList.toggle("active");
			var panel = this.nextElementSibling;
			if (panel.style.display === "block") {
				panel.style.display = "none";
				panel.style.maxHeight = null
			} else {
				panel.style.display = "block";
				panel.style.maxHeight = panel.scrollHeight + "px"
			}
		})
	}
	else {
		$('.accordion-footer').removeClass('active');
		$('.prisma-footer .panel').css('display', 'block')
	}
}

window.onscroll = function() {
	noPerdreHeader()
};

function noPerdreHeader() {
	if (document.body.scrollTop > 0 || document.documentElement.scrollTop > 0) {
		$('.top-menu').slideUp( "400", function() {
			$('#top-menu').css('display', 'none');
			$('#nav-header').css('position', 'fixed');
			$('.prisma-container').css('margin-top', '71px');
	  });
		$('#nav-header').css('top', '0px');
	} else {
		if (!(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))) {
			console.log("scroll"+document.documentElement.scrollTop);
			$('.prisma-container').css('margin-top', '0px');
			$('.top-menu').slideDown( "400", function() {
		  });
			$('#nav-header').css('position', 'inherit')

		}
	}
}
