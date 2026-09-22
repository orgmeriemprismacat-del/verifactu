<?php

$mostrar = "<div class='container text-centrat d-flex flex-column
align-items-center justify-content-center'>
	<h2>Butlletí electrònic</h2>
	<p>Estigues al dia dels nostres cursos i serveis!</p>
	<div class='col-6 form-butlleti' role='search'>
		<label class='font-size-small posicio-absoluta' data-error='wrong'
		data-success='right' for='butlleti-adreca-electronica'>
		Escriu la teva adreça electrònica...</label>
		<input id='butlleti-adreca-electronica' name='butlleti-adreca-electronica'
		type='text' class='form-control' title='Escriu la teva adreça electrònica...'>
		<button aria-label='Newsletter' class='news' id='butlleti-news'>
		<i class='fa fa-envelope'></i></button>
		<label for='comprovaSpam' class='comprovaSpam'>Si veus això, no omplis el camp!</label>
		<input id='comprovaSpam' name='comprovaSpam' class='comprovaSpam' value=''>
	</div>
</div>";
echo $mostrar;

?>
