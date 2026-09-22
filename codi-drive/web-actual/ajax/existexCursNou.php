<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../Curs.php");
include("../Pack.php");
include("../LlistatCursos.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $filtres = json_decode($_GET['filtres']);
	$ed=explode('|',$filtres[0][1]);

   $existeixCursNou = '0';

   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();

   $cns="SELECT CODI_CURS FROM informacio as i WHERE i.ESTAT=1 AND TIPUS_CURS != 'R' AND TIPUS_CURS != 'C' GROUP BY CODI_CURS";
   if ( $stmt = $connexio->prepare($cns) ) {
   	$stmt->execute();
   	$stmt->bind_result($codiCurs);
   	while ($stmt->fetch() && $existeixCursNou == '0') {
   		$curs = new Curs($codiCurs, $dispositiu);
   		if ( $curs->obtenirEstat()==1 ) {
   			if ( $curs->__esCursNou() || $curs->etiquetaCursAmbDescompte() != '' ) {
   				$existeixCursNou = '1';
               echo $codiCurs;
   			}
   		}
   	}
   	$connexio->closeStmt();
   }

   // $cnsPack="SELECT i.ID_PACK FROM info_pack as i INNER JOIN packs as p ON p.ID_PACK = i.ID_PACK
   // 	WHERE i.ESTAT=1 AND p.PUBLIC=1 GROUP BY i.ID_PACK";
   // if ( $existeixCursNou == '0' && $stmt = $connexio->prepare($cnsPack) ) {
   // 	$stmt->execute();
   // 	$stmt->bind_result($idPack);
   // 	while ($stmt->fetch()) {
   // 		$pack = new Pack($idPack, $dispositiu);
   // 		if ( $pack->obtenirEstat()==1 && ( $pack->inscripcionsObertes($ed[2], $ed[1])) ) {
   // 			$existeixCursNou = '1';
   // 		}
   // 	}
   // 	$connexio->closeStmt();
   // }

   echo $existeixCursNou;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
