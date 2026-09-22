<?php

try {
	$exclamacio = "<i class='fas fa-exclamation-circle ml-0' style='color: #c94551; background: white; border-radius: 50%; margin-right: 0 !important;
	margin-left: 5px !important; background: radial-gradient(circle, rgba(255,255,255,1) 0%, rgba(201,69,81,1) 100%); font-size: 1.1rem; border: 1px solid #c94551	'></i>";

	$mostrar = "<div id='top-menu' class='top-menu py-1'>";
	$mostrar .= "<div class='container d-flex flex-column flex-md-row align-items-center justify-content-between'>
		<div class='formacio flex-1-0-auto col-md-9 col-lg-8 p-0'>
			<p>Formació permanent del professorat i de professionals del desenvolupament infantil</p>
		</div>
		<div class='faq text-right flex-wrap d-flex flex-row justify-content-end align-items-center col-md-3 col-lg-4 p-0'>
			<a role='link' class='m-0' rel='noopener' title='Preguntes freqüents' href='https://www.prisma.cat/preguntes-frequents' target='_blank'>FAQ</a>
			<span class='mx-2'>|</span>
			<span class='m-0 telefon'><i class='fa fa-phone'></i>972 21 75 65</span>
			<span class='mx-2'>|</span>
			<a href='https://www.instagram.com/prisma.educacio/' class='d-flex align-items-center justify-content-center ml-0 mr-2' target='_blank' aria-label='Instagram' role='link' rel='noopener nofollow' title='Instagram de PrisMa'>
				<i class='fab fa-instagram'></i>
				<!--<span id='insta-nou' class='position-relative text-center text-white ml-1'>NOU</span>-->
			</a>
			<a href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' class='d-flex align-items-center justify-content-center ml-0 mr-2' target='_blank' aria-label='Youtube' role='link' rel='noopener nofollow' title='Youtube de PrisMa'><i class='fab fa-youtube'></i></a>
			<a href='https://twitter.com/PrisMaFormacio' class='d-flex align-items-center justify-content-center ml-0 mr-2' target='_blank' aria-label='Twitter' role='link' rel='noopener nofollow' title='Twitter de PrisMa'><i class='fab fa-twitter'></i></a>
			<a href='https://www.facebook.com/PrisMaFormacio' class='d-flex align-items-center justify-content-center ml-0 mr-2' target='_blank' aria-label='Facebook' role='link' rel='noopener nofollow' title='Facebook de PrisMa'><i class='fab fa-facebook-f'></i></a>
            <a href='https://www.tiktok.com/@prisma.educacio' class='d-flex align-items-center justify-content-center ml-0 mr-2' target='_blank' aria-label='Tik Tok' role='link' rel='noopener nofollow' title='Tik Tok de PrisMa'><i class='fab fa-tiktok'></i></a>
		</div>
	</div>";
    $mostrar .= "</div><div class='prisma-header'>";
	$mostrar .= "<div class='modal fade' id='modalLoginForm' tabindex='-1' role='dialog' aria-labelledby='modalLoginForm'
	  aria-hidden='true'>
	  <div class='modal-dialog modal-dialog-centered' role='document'>
		<div class='modal-content w-100'>
		  <div class='modal-header w-100 text-center border-0 color-prisma'>
			<p class='modal-title w-100 font-weight-bold text-center'>ACC&Eacute;S AL CAMPUS <i class='fa fa-user'></i></p>
			<button type='button' role='button' class='close position-absolute' data-dismiss='modal' aria-label='Close'>
			  <span aria-hidden='true'>&times;</span>
			</button>
		  </div>
		  <form method='post' target='_self' action='https://www.prisma.cat/campus/login/index.php'>
		  <div class='modal-body'>
			<div class='md-form'>
			  <input type='text' name='username' id='username' class='form-control' title='Introdueix el DNI sense la lletra'aria-required='true' required>
			  <i class='fa fa-user position-absolute'></i>
			  <label class='font-size-standard position-absolute' data-error='wrong' data-success='right' for='username'>DNI sense lletra</label>
			</div>

			<div class='md-form'>
			  <input type='password' name='password' id='password' class='form-control' title='Introdueix la contrasenya' aria-required='true' required >
			  <i class='fas fa-key position-absolute'></i>
			  <label class='font-size-standard position-absolute' data-error='wrong' data-success='right' for='password'>Contrasenya</label>
			</div>

		  </div>
		  <div class='modal-footer text-center border-0'>
			<!--<input type='submit' role='button' class='boto-blau border-0 border-radius-2 text-center position-relative font-weight-bold text-white background-prisma' value='INICIA LA SESSI&Oacute;' />-->
			<button role='button' class='boto-blau border-0 border-radius-2 text-center position-relative font-weight-bold text-white background-prisma' data-dismiss='modal' onclick='this.form.submit();'>INICIA LA SESSI&Oacute;</button>
			<a role='link' class='mes-informacio' href='https://www.prisma.cat/campus/login/forgot_password.php'>Heu oblidat la contrasenya?</a>
		  </div>
		  </form>
		</div>
	  </div>
	</div>

	<nav id='nav-header' class='navbar prisma-nav w-100 background-prisma'>
		<div class='container'>
			<div class='navbar-header'>
			  <button type='button' onclick='openNav()' class='navbar-toggle collapsed float-left text-white background-prisma' data-toggle='collapse' data-target='#bs-example-navbar-collapse-1' aria-expanded='false' aria-label='Obre/tanca el menú'>
				<i class='fas fa-bars'></i>
			  </button>
			  <a role='link' class='navbar-brand prisma-brand mr-0' href='https://www.prisma.cat/' target='_self' title='Veure la p&agrave;gina principal de PrisMa'><img width='139.39' height='46' role='img' src='https://www.prisma.cat/img/logo-prisma-light.png' alt='Logo PrisMa'></a>
			</div>

			<div id='sideNavPrisma' class='navbarPrisma'>
			  <a id='boto-tancar' role='button' class='closebtn position-absolute' onclick='closeNav()'>&times;</a>
			  <ul id='navBarPrisma' class='nav navbar-nav navbar-right'>
				<li class='dropdown li-white'>
					<a href='https://www.prisma.cat/' class='px-1 px-lg-2 py-3' role='button' target='_self' title='P&agrave;gina principal de PrisMa'>Inici</a>
				</li>
				<li class='dropdown li-white'>
					<a href='https://www.prisma.cat/cursos' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Cursos en l&iacute;nea que ofereix PrisMa'>Cursos</a>
				</li>
				<li class='dropdown li-white'>
				  <a href='https://www.prisma.cat/perfils-professionals' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Cursos de PrisMa amb perfils professionals'>Perfils</a>
				</li>
				<!--<li class='dropdown li-white juliol'>
				  <a href='https://www.prisma.cat/cursos-subvencionats' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Cursos subvencionats'>
					Subvencionats</a>
				</li>-->
				<li class='dropdown li-white juliol'>
				  <a href='https://www.prisma.cat/cursos/07/2025' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Cursos de juliol'>
					Juliol</a> <div class='float-right ml-1 mr-2' style='margin-top: 5px;'><div class='d-flex justify-content-center align-items-center mr-0' style='background-color: #fff; border-radius: 50%; height: 12px; width: 9px;'><i class='fas fa-exclamation-circle' style='color:#C94551;'></i></div></div>
				</li>
				<li class='dropdown li-white'>
				  <a href='https://www.prisma.cat/packs' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Packs de cursos'><em>Packs</em> <!--<div class='float-right ml-1 mr-2' style='margin-top: 5px;'><div class='d-flex justify-content-center align-items-center mr-0' style='background-color: #fff; border-radius: 50%; height: 12px; width: 9px;'><i class='fas fa-exclamation-circle' style='color:#C94551;'></i></div></div>--></a>
				</li>
				<li class='dropdown li-white'>
				  <a href='https://www.prisma.cat/tastets' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Tastets gratuïts d&#39;alguns cursos'>Tastets</a>
				</li>
				<li class='dropdown li-white juliol'>
				  <a href='https://www.prisma.cat/tallers/cuida-veu-mati-practica' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Taller presencial'>
					Taller</a> <div class='float-right ml-1 mr-2' style='margin-top: 5px;'><div class='d-flex justify-content-center align-items-center mr-0' style='background-color: #fff; border-radius: 50%; height: 12px; width: 9px;'><i class='fas fa-exclamation-circle' style='color:#C94551;'></i></div></div>
				</li>
				<li class='dropdown li-white'>
				  <a href='https://www.prisma.cat/qui-som' class='px-1 px-lg-2 py-3' role='button' target='_self' title=\"Sobre l'equip, la tasca i els valors de PrisMa\">Qui som</a>
				</li>
				<li class='dropdown li-white'>
				  <a href='https://www.prisma.cat/contacte' class='px-1 px-lg-2 py-3' role='button' target='_self' title='Contacta amb PrisMa'>Contacte</a>
				</li>
				<li class='dropdown li-campus'>
				  <a href='https://campus.prisma.cat/login/' class='px-1 px-lg-2 py-3' role='button' target='_blank' title='Accés al Campus'>Campus &nbsp;<i class='fa fa-user'></i></a>
				</li>
				<li class='dropdown li-blue'>
				  <a  class='px-1 px-lg-2 py-3'href='https://www.prisma.cat/docents' role='button' target='_self' title='Tutors dels cursos on-line de PrisMa'>Equip docent</a>
				</li>
				<li class='dropdown li-blue'>
				  <a  class='px-1 px-lg-2 py-3'href='https://www.prisma.cat/reconeixements-certificacio' role='button' target='_self' title='Informació sobre els reconeixements i certificació dels cursos on-line de PrisMa'>Reconeixements</a>
				</li>
				<li class='dropdown li-blue'>
				  <a  class='px-1 px-lg-2 py-3'href='https://www.prisma.cat/metodologia' role='button' target='_self' title='Funcionament dels cursos on-line de PrisMa'>Metodologia</a>
				</li>
				<li class='dropdown li-blue'>
				  <a  class='px-1 px-lg-2 py-3'href='https://www.prisma.cat/preguntes-frequents' role='button' target='_self' title='Preguntes freq&uuml;ents'>Preguntes freq&uuml;ents</a>
				</li>
			  </ul>
			</div>
		</div>
	</nav>";
    $mostrar .= "</div>";

	echo $mostrar;
}
catch(Exception $e) {
    echo $e->getMessage();
}
?>
