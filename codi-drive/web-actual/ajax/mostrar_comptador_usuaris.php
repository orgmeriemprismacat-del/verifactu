<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Mail.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$usuaris = 30138;
	$confianca = 75;
	$edicions = 29045;

	/* Fem la connexió a la BD */
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cns = "SELECT ID FROM informacio WHERE ESTAT=1";
	$stmt=$connexio->prepare($cns);
	$stmt->execute();
	$stmt->bind_result($id);
	$stmt->store_result();
	$numCursos = $stmt->num_rows();
	$connexio->closeStmt();

	//Array que guarda en cada posició el nombre de persones que han fet x cursos on x és la posicio de l'array
	$numUserCurs=[];
	for ($i=0; $i<$numCursos; $i++) $numUserCurs[$i]=0;

	$cns = "SELECT DISTINCT DNI, SUM(`INSC CURS`) FROM inscripcions WHERE (`INSC CURS`='1' OR `INSC CURS` = 'M') GROUP BY DNI";
	$stmt=$connexio->prepare($cns);
	$stmt->execute();
	$stmt->bind_result($dni, $nCursRealitzat);
	while ($stmt->fetch()) {
		$numUserCurs[$nCursRealitzat]+=1;
		// echo $nCursRealitzat." ".$numUserCurs[$nCursRealitzat]."<br />";
	}
	$connexio->closeStmt();
	$usuaris=0; $inscTot=0;
	for ($i=0; $i<$numCursos; $i++) {
		$usuaris = $usuaris + $numUserCurs[$i];
		$inscTot += $numUserCurs[$i]*$i;
	}

	$confianca = round(floatval( (($inscTot - $numUserCurs[1])*100)/$inscTot ));

	$mes_actual = date(m);
	$any_actual = date(Y);

	$cns = "SELECT DATAI FROM curs WHERE PUBLIC='1' and estat!='0' AND MES='".$mes_actual."' AND ANY=".$any_actual." LIMIT 1";
	$stmt=$connexio->prepare($cns);
	$stmt->execute();
	$stmt->bind_result($data_inici);
	$stmt->fetch();
	$connexio->closeStmt();

	$cns = "SELECT COUNT(ID_CURS) FROM curs WHERE PUBLIC='1' and estat!='0' AND DATAI<='".$data_inici."'";
	$stmt=$connexio->prepare($cns);
	$stmt->execute();
	$stmt->bind_result($edicions);
	$stmt->fetch();
	$connexio->closeStmt();

	$connexio->desconectarBD();

	$mostrar = "<div class='container'  id='counter'><div class='row justify-content-center'>";

	$mostrar.= "<div class='col-4 d-flex flex-column alig-items-center justify-content-center text-center counter'>";
	$mostrar.= "<i class='fa fa-user'></i>";
	$mostrar.= "<span class='number'><span id='contador-usuaris' class='contador' data-count='".$usuaris."'>0</span></span>";
	$mostrar.= "<p class='titol'>Usuaris</p>";
	$mostrar.= "<p>Persones que han fet un curs a l'Associació PrisMa des del 2007.</p>";
	$mostrar.= "</div>";

	$mostrar.= "<div class='col-4 d-flex flex-column alig-items-center justify-content-center text-center counter'>";
	$mostrar.= "<i class='fas fa-hand-holding-heart'></i>";
	$mostrar.= "<span class='number'><span id='contador-fidelitat' class='contador' data-count='".$confianca."'>0</span>%</span>";
	$mostrar.= "<p class='titol'>Fidelitat</p>";
	$mostrar.= "<p>Percentatge d'inscripcions d'alumnes que repeteixen amb nosaltres.</p>";
	$mostrar.= "</div>";

	$mostrar.= "<div class='col-4 d-flex flex-column alig-items-center justify-content-center text-center counter'>";
	$mostrar.= "<i class='fat fa-shield'></i>";
	$mostrar.= "<span class='number'><span id='contador-edicions' class='contador' data-count='".$edicions."'>0</span></span>";
	$mostrar.= "<p class='titol'>Edicions</p>";
	$mostrar.= "<p>Nombre de convocatòries de tots els cursos que hem realitzat.</p>";
	$mostrar.= "</div>";

	$mostrar.= "</div></div>";

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
