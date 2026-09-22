<?php

include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

function mostrar_requadre($dispositiu){
	$titol = "<h2>Sortejos vigents</h2>";
	$mostrar = ""; // Quan no hi ha cap concurs vigent

	// Posem aquí el concurs o concursos oberts i si no n'hi ha, l'amaguem
	$mostrar = "<div class='container py-3 mb-4'>".$titol;

   $mostrar .= "<div class='sortejos-container d-flex flex-column flex-md-row border-radius-2 mb-4'>
    <div class='col-12 col-md-8 sortejos pt-2 pr-2 pb-1 pl-3'>
       <div class='mb-2 mt-1'>
	 			<div class='event-schedule d-flex flex-wrap mb-2'>
	 	      <p>🌹📚 <strong>Aquest Sant Jordi, el drac ha canviat de plans… i t’ofereix un curs gratis!</strong> 🐉✨</p>
              <p>Celebrem la diada amb un <strong>sorteig d’un curs en línia de 30 o 40 hores TOTALMENT GRATUÏT</strong>! Podràs triar el que vulguis del nostre catàleg. 🧠✨</p>
	 			<p><strong>Com hi pots participar?</strong> Més fàcil que trobar una rosa el 23 d’abril:</p>
	 			<ul style='list-style:none'>
	 			<li>✅ Fes «M'agrada» a la <a class='font-weight-bold' target=_blank' href='https://www.instagram.com/p/DW50f9bDzfa/'>publicació</a>.</li>
	 			<li>✅ Segueix el compte <a class='font-weight-bold' target=_blank' href='https://www.instagram.com/prisma.educacio/'>@prisma.educacio</a>.</li>
	 			<li>✅ Etiqueta una o més persones en comentaris (com més comentaris, més opcions! Sí, pots jugar a ser estratega... 🧩)</li>
	 			</ul>
	 			<p>📅 <strong>Tens temps fins al 22 d’abril (inclòs)</strong> per participar-hi.</p>
                <p>📣 <strong>El 23 d’abril</strong> &mdash;dia de roses, llibres i bona sort&mdash; farem públic qui s’endú el curs!</p>
	 			<p>🍀 Sort, saviesa i Sant Jordi per a tothom!</p>
	 		</div>
	 	 </div>
        </div>
	 	<div class='col-12 col-md-4 prisma-sub-overlay p-0'>
	 		<picture>
	 			<source media='(max-width: 768px)' type='image/webp' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-714.webp?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-714.webp?ver=1.0'>
	 			<source media='(max-width: 768px)' type='image/jpeg' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-714.jpg?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-714.jpg?ver=1.0'>
	 			<source media='(max-width: 990px)' type='image/webp' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-239.webp?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-239.webp?ver=1.0'>
	 			<source media='(max-width: 990px)' type='image/jpeg' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-239.jpg?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-239.jpg?ver=1.0'>
	 			<source media='(max-width: 991px)' type='image/webp' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-379.webp?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-379.webp?ver=1.0'>
	 			<source media='(max-width: 991px)' type='image/jpeg' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-379.jpg?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026-379.jpg?ver=1.0'>
	 			<source media='(min-width: 1200px)' type='image/webp' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.webp?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.webp?ver=1.0'>
	 			<source media='(min-width: 1200px)' type='image/jpeg' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.jpg?ver=1.0' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.jpg?ver=1.0'>
	 			<source type='image/webp' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.webp?ver=1.0' alt='Sorteig Dia Sant Jordi - Instagram' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.webp?ver=1.0'>
	 			<source type='image/jpeg' data-srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.jpg?ver=1.0' alt='Sorteig Dia Sant Jordi - Instagram' srcset='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.jpg?ver=1.0'>
	 			<img data-src='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.jpg?ver=1.0' alt='Sorteig Dia Sant Jordi' class='h-100 w-100 imatge-gran lazyloaded' src='https://www.prisma.cat/img/sortejos/sorteig-sant-jordi-2026.jpg?ver=1.0'>
	 		</picture>
	 	</div>
	 </div>";

	/*$mostrar .= "<div class='back-banner sortejos position-relative p-0 mb-3'>
	    <picture>
	    <img data-src='https://www.prisma.cat/img/banners/sortejos.jpg' alt='Sortejos!' src='https://www.prisma.cat/img/banners/sortejos.jpg?ver=1.6' class='baner trobades text-white d-flex flex-column justify-content-center align-items-center lazyloaded'>
	    </picture>
	    <div class='escrit d-flex flex-column w-100 h-100 position-absolute justify-content-center align-items-center px-1'>
	       <p class='text-white font-weight-bold py-3 mb-0 text-center'>Actualment no tenim cap sorteig actiu!</p>
	    </div>
	 </div>";*/

	$mostrar .= "</div>";

	if ( $mostrar != '' ) {
		$mostrar = $mostrar;
	}
	else
	{
		$mostrar = "<div class='container py-3 mb-4'>".$titol."En aquests moments no hi ha cap sorteig actiu.</div>";
	}

	return $mostrar;
}

try {
	$dispositiu = $_GET['dispositiu'];

	$mostrar .= mostrar_requadre($dispositiu);

	echo $mostrar;

}
catch(Exception $e) {
	if ($e->getCode()==404)
	  echo mostrarPagina404();
  else
	  echo missatgeError($e->getCode());
}

?>
