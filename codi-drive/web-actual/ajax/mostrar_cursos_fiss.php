<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	
	$any_actual = date("Y");

	$cnsCursosFiss="SELECT CODI_CURS, TITOL FROM (curs AS c INNER JOIN informacio AS i
		ON c.CURS = i.CODI_CURS AND (FISS IS NOT NULL AND FISS!='') AND i.ESTAT=1 AND ANY>=".$any_actual.")
		GROUP BY CODI_CURS ORDER BY TITOL";
	$stmt = $connexio->prepare($cnsCursosFiss);
	$stmt->execute();
	$stmt->store_result();
	$mostrar='';
	if ( $stmt->num_rows() > 0) {
		$stmt->bind_result($codiCurs, $titol);
		$mostrar.="<p>Les edicions dels cursos reconeguts com a formació d'interès en serveis socials són les següents:</p>";
		$mostrar.="<ul class='llistes'>";
		/* guardem en una llista les dates de d'inici de cada primera edició dels cursos reconeguts com a FISS */
		/* Assignem a l'array $llistat_cursos com a clau el codi curs i com a valor el nom del curs*/
		while ($stmt->fetch()) {
			$listCurs[$codiCurs]=$titol;
		}
		$connexio->closeStmt();
		foreach ($listCurs as $clau => $valor) {
			/* busquem la primera edició del curs */
			$cnsFirstEdCurs="SELECT DATAI FROM (curs AS c INNER JOIN informacio AS i
				ON c.CURS = i.CODI_CURS AND FISS IS NOT NULL AND i.ESTAT=1 AND CODI_CURS=?)
				ORDER BY DATAI ASC LIMIT 1";
			$stmt = $connexio->prepare($cnsFirstEdCurs);
	      $stmt->bind_param("s", $clau);
	      $stmt->execute();
	      $stmt->bind_result($datai);
	      $stmt->fetch();
	      $connexio->closeStmt();

			/* busquem la darrera edició del curs i les dades a mostrar */
			$cnsLastEdCurs="SELECT TITOL, HORES, DATAI, ID_AMIGABLE, URL FROM
				(curs AS c INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS AND
				FISS IS NOT NULL AND i.ESTAT=1 AND CODI_CURS=?)
				INNER JOIN amigable AS a ON i.ID_AMIGABLE = a.ID
				ORDER BY DATAI DESC LIMIT 1";
			$stmt = $connexio->prepare($cnsLastEdCurs);
	      $stmt->bind_param("s", $clau);
	      $stmt->execute();
	      $stmt->bind_result($titol, $hores, $dataf, $idAmig, $link);
	      $stmt->fetch();
	      $connexio->closeStmt();

			$mostrar .= "<li><a role=\"link\" class='font-weight-bold' href='".$link."' target='_self' ";
			$mostrar .= "title='Curs ".$titol."'>".$titol."</a> | ".$hores." h";

			$data1 = explode('-',$datai);
			$textMes1 = new Text($data1[1]);
			$textAny1 = new Text($data1[0]);

			$data2 = explode('-',$dataf);
			$textMes2 = new Text($data2[1]);
			$textAny2 = new Text($data2[0]);

			$textMes1De = new Text($textMes1->obtenirDeMesLlarg());
			$textMes1DeUnic = new Text($textMes1->obtenirMesLlarg());
			$majMes1De = $textMes1De->convertirMajPrimLletra();
			$majMes1DeUnic = $textMes1DeUnic->convertirMajPrimLletra();
			$any1 = $textAny1->obtenirText();
			$any2 = $textAny2->obtenirText();
			$mes1 = $textMes1->obtenirText();
			$mes2 = $textMes2->obtenirText();
			$mes2Llarg = $textMes2->obtenirMesLlarg();

			if ($any1 != $any2)
				$mostrar.="<p>".$majMes1De." de ".$any1." a ".$mes2Llarg." de ".$any2.".</p></li>";
			else if ($any1 == $any2 && $mes1 != $mes2)
				$mostrar.="<p>".$majMes1De." a ".$mes2Llarg." de ".$any2.".</p></li>";
			else
				$mostrar.="<p>".$majMes1DeUnic." de ".$any1.".</p></li>";
		}
		$mostrar .= "</ul></div>";
	}
	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
