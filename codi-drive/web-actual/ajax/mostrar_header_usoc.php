<?php

try {
	$exclamacio = "<i class='fas fa-exclamation-circle ml-0' style='color: #c94551; background: white; border-radius: 50%; margin-right: 0 !important;
	margin-left: 5px !important; background: radial-gradient(circle, rgba(255,255,255,1) 0%, rgba(201,69,81,1) 100%); font-size: 1.1rem;'></i>";

	$mostrar = "<div id='top-menu' class='top-menu py-1'>";
	$mostrar .= "<div class='container d-flex flex-column flex-md-row align-items-center justify-content-between'>
		<div class='formacio flex-1-0-auto col-md-9 col-lg-8 p-0'>
			<p>Formació permanent del professorat i de professionals del desenvolupament infantil</p>
		</div>
		<div class='faq text-right flex-wrap d-flex flex-row justify-content-end align-items-center col-md-3 col-lg-4 p-0'>
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
			  <img class='ml-2' height='46' role='img' src='https://web.feusoc.cat/pluginfile.php/107/block_html/content/logoEnsenyamentTransparent.png' alt='Logo USOC'>
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
