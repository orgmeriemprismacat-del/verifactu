<?php

$nomMesos = [
	"01" 	=> "gener",
	"02" 	=> "febrer",
	"03" 	=> "març",
	"04" 	=> "abril",
	"05" 	=> "maig",
	"06" 	=> "juny",
	"07" 	=> "juliol",
	"08" 	=> "agost",
	"09" 	=> "setembre",
	"10" 	=> "octubre",
	"11" 	=> "novembre",
	"12" 	=> "desembre",
];

$dateRevisio = new DateTime( $finalitzat );
$any = date_format($dateRevisio, 'Y');
$mes = date_format($dateRevisio, 'm');
$dia = date_format($dateRevisio, 'd');

$nomMes = "de ";
if ( ($mes == '04') or ($mes == '08') or ($mes == '10') )
   $nomMes = "d'";

$nomMes .= $nomMesos[$mes];

$dataRevisio = intval( $dia )." ".$nomMes." del ".$any;

$cntRevisio = "<p>
La revisió està enviada el dia <strong>".$dataRevisio."</strong>.
</p>";

$cntRevisio .= "<p>
Per a qualsevol modificació, consulta amb <strong>secretaria@prisma.cat</strong>.
</p>";

$cntRevisio .= "Gràcies.";

?>
