<?php
	$username = $_GET['username'];
	$firstname = $_GET['firstname'];
	$lastname = $_GET['lastname'];
	$correuAlumne =  $_GET['emailAlumne'];

	$nomAlumne = $firstname." ".$lastname;

	//Cal obtenir tots els camps
	$mostrar.="<p id='noms' class='nomAlumne'>".$nomAlumne."</p>";
	$mostrar.="<input type='text' id='email' name='email' maxlength='100' ";
	$mostrar.="placeholder='El meu correu electrònic' value='".$correuAlumne."'>";
	$mostrar.="<textarea id='consulta' name='consulta' placeholder='La meva consulta' rows='7'></textarea>";
	$mostrar.="<div class='boto'><input id='enviar-dades' type='submit' ";
	$mostrar.="name='submit' value='Envia les dades'></div>";

	echo $mostrar;
?>
