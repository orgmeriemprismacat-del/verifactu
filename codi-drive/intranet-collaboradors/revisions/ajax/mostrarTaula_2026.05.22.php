<?php
try {
  $hores = $_REQUEST['hores'];
  $shortname = $_REQUEST['shortname'];
  $course = $_REQUEST['course'];
  $user = $_REQUEST['usuari'];
  $num_inc = $_REQUEST['num_apartats'];

  $revisionsSave = [];
  $incidenciesSave = [];
  $cntElementSave = 0;

  include ('../../ConnexioWeb.php');
  include ('../inc/enunciats-'.$hores.'.php');

  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();

  $cnsExisteixRevisio = "SELECT guardat, finalitzat, revisat, INC_GENERAL, INC_LECTURES,
  	INC_MEDIATECA, INC_BIBLIO, INC_ALTRES FROM revisio_tutor WHERE codic = ?";

  if ( $stmt = $conWeb->prepare( $cnsExisteixRevisio ) ) {
    $stmt->bind_param('s', $shortname);
    $stmt->execute();
    $stmt->store_result();
    $rowcount = $stmt->num_rows();
    if ( $stmt->num_rows() > 0 ) {
      $stmt->bind_result($guardat, $finalitzat, $revisat, $incGeneral, $incLectures,
      $incMediateca, $incBiblio, $incAltres);
      $stmt->fetch();
      $conWeb->closeStmt();
    }
  }
  else {
    throw new Exception('', 11109);
  }

  if ( $rowcount > 0 ) {
    if ( $finalitzat != '' and $finalitzat != null ) {
      include ('../inc/missatgeRevisioEnviada.php');
    }
    else {
      $noEnviat = 1;
      $vectAux = explode( '[REV]', $revisat);
      for ( $i=1; $i<count($vectAux); $i++)
         $revisionsSave[$i]=$vectAux[$i];

      $cntElementSave = 0;

      $vectAux = explode( '[REV]', $incGeneral);
      for ( $i=1; $i< count($vectAux); $i++) {
         $dada = $vectAux[$i];
         if ( $dada == "Cap" )
            $incidenciesSave[$cntElementSave] = [];
         else {
            $vectAux2 = explode( '[INC_XX]', $dada);
            $incidenciesSave[$cntElementSave] = $vectAux2;
         }
         $cntElementSave++;
      }

      $vectAux = explode( '[REV]', $incLectures);
      for ( $i=1; $i< count($vectAux); $i++) {
         $dada = $vectAux[$i];
         if ( $dada == "Cap" )
            $incidenciesSave[$cntElementSave] = [];
         else {
            $vectAux2 = explode( '[INC_XX]', $dada);
            $incidenciesSave[$cntElementSave] = $vectAux2;
         }
         $cntElementSave++;
      }

      $vectAux = explode( '[REV]', $incBiblio);
      for ( $i=1; $i< count($vectAux); $i++) {
         $dada = $vectAux[$i];
         if ( $dada == "Cap" )
            $incidenciesSave[$cntElementSave] = [];
         else {
            $vectAux2 = explode( '[INC_XX]', $dada);
            $incidenciesSave[$cntElementSave] = $vectAux2;
         }
         $cntElementSave++;
      }

      $vectAux = explode( '[REV]', $incMediateca);
      for ( $i=1; $i< count($vectAux); $i++) {
         $dada = $vectAux[$i];
         if ( $dada == "Cap" )
            $incidenciesSave[$cntElementSave] = [];
         else {
            $vectAux2 = explode( '[INC_XX]', $dada);
            $incidenciesSave[$cntElementSave] = $vectAux2;
         }
         $cntElementSave++;
      }

      $vectAux = explode( '[REV]', $incAltres);
      for ( $i=1; $i< count($vectAux); $i++) {
         $dada = $vectAux[$i];
         if ( $dada == "Cap" )
            $incidenciesSave[$cntElementSave] = [];
         else {
            $vectAux2 = explode( '[INC_XX]', $dada);
            $incidenciesSave[$cntElementSave] = $vectAux2;
         }
         $cntElementSave++;
      }
    }
  }
  else {
    $noEnviat = 1;
  }

  if ( $noEnviat ) {
    $cnsDadesCurs = "SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf,
       DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, m.nom as nmes,
       SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits,
       SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`='1') THEN 1 ELSE 0 END) aprovats,
       SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`='1') THEN 1 ELSE 0 END) pendents,
       DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats
       FROM cursos AS c INNER JOIN personal AS p ON c.DNI_TUTOR=p.DNI INNER JOIN
        inscripcions AS i ON c.CURS = i.CURS AND c.ANY = i.ANY AND c.MES = i.MES
        AND c.AULA=i.Grup INNER JOIN mesos AS m ON m.num = i.MES
       WHERE id_Curs = ?";
    if ( $stmt = $conWeb->prepare( $cnsDadesCurs ) ) {
     $stmt->bind_param('s', $shortname);
     $stmt->execute();
       $stmt->bind_result($idCurs, $mes, $nomCurs, $aula, $dataf, $dniTutor, $tnom,
       $tcogs, $nmes, $inscrits, $aprovats, $pendents, $diesPassats);
       $stmt->fetch();

       $nomCognoms = $tnom." ".$tcogs;
       $curs = $nomCurs." - Aula ".$aula;
       $convocatoria = $nmes;

       $tableRevisio = "<table class='table table-header table-striped table-hover mb-4'>
          <thead>
             <tr colspan='4'>
                <td>DADES DEL CURS</td>
             </tr>
          </thead>
          <tbody>
             <tr>
                <td><p><strong>Tutor/a:</strong> ".$nomCognoms."</p></td>
             </tr>
             <tr>
                <td><p><strong>Curs:</strong> ".$curs."</p></td>
             </tr>
             <tr>
                <td><p><strong>Convocatòria:</strong> ".$convocatoria."</p></td>
             </tr>
          </tbody>
       </table>";

       if ( $hores == '30')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == '40')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["JA HEM ARRIBAT AL FINAL", 2]];
		else if ( $hores == '50')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 5], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == '60')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 5], ["MÒDUL 6", 5], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == '100')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == '100-2')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 3], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == 'dfd')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["MÒDUL 6", 1], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == 'htp')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 4], ["MÒDUL 2", 4], ["MÒDUL 3", 4], ["MÒDUL 4", 4], ["JA HEM ARRIBAT AL FINAL", 2]];
       else if ( $hores == 'eines')
          $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 4], ["MÒDUL 2", 4], ["MÒDUL 3", 4], ["JA HEM ARRIBAT AL FINAL", 2]];
		  else if ( $hores == 'cat')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["JA HEM ARRIBAT AL FINAL", 2]];
        else if ( $hores == 'tic')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 6], ["JA HEM ARRIBAT AL FINAL", 2]];
        else if ( $hores == 'tics')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["JA HEM ARRIBAT AL FINAL", 2]];
        else if ( $hores == 'acos')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 5], ["MÒDUL 1", 2], ["MÒDUL 2", 2], ["MÒDUL 3", 2], ["MÒDUL 4", 2], ["MÒDUL 5", 2], ["MÒDUL 6", 2], ["MÒDUL 7", 2], ["JA HEM ARRIBAT AL FINAL", 2]];
		 else if ( $hores == 'sui')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 3], ["MÒDUL 1", 3], ["MÒDUL 2", 3], ["MÒDUL 3", 3], ["MÒDUL 4", 3], ["JA HEM ARRIBAT AL FINAL", 2]];
		 else if ( $hores == 'sda')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 3], ["MÒDUL 2", 3], ["MÒDUL 3", 3], ["MÒDUL 4", 3], ["JA HEM ARRIBAT AL FINAL", 2]];
		 else if ( $hores == 'ged')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 4], ["MÒDUL 2", 3], ["MÒDUL 3", 3], ["MÒDUL 4", 3], ["JA HEM ARRIBAT AL FINAL", 2]];
		 else if ( $hores == 'ia')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 5], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 4], ["JA HEM ARRIBAT AL FINAL", 2]];
		 else if ( $hores == 'gust')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 3], ["MÒDUL 2", 3], ["MÒDUL 3", 3], ["JA HEM ARRIBAT AL FINAL", 2]];  
        else if ( $hores == 'album')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 3], ["MÒDUL 2", 3], ["JA HEM ARRIBAT AL FINAL", 2]];  
		  

       $numEnunciat = 1; $cntElements = 1;
       for ( $i = 0; $i < count($apartats); $i++ ) {
          $apartat = $apartats[$i][0];
          $numApartat = $apartats[$i][1];

          $registres = '';
          for ( $j=0; $j < $numApartat; $j++) {
             $noActiveRevisat = 'active';
             if ( count($revisionsSave) > 0) {
                $activeRevisat = $noActiveRevisat = '';
                if ( $revisionsSave[$cntElements] == "1" ) {
                   $activeRevisat = 'active';
                }
                else {
                   $noActiveRevisat = 'active';
                }
             }

             $htmlIncidenciesSave = "";
             if ( $cntElementSave > 0 ) {
                $incidenciesActuals = $incidenciesSave[$cntElements-1];
                for ( $z = 1; $z < count($incidenciesActuals); $z++ ) {
                   $vectInc = explode( '_####_', $incidenciesActuals[$z]);
                   $htmlIncidenciesSave .= "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center cnt-inputs-incidencia-".$cntElements."'>
                   <div class='form-group field-wrap position-relative mb-0 p-1 w-100 mb-1'>
                   <label class='active'>Incidència</label>
                   <textarea type='text' class='form-control incidencia element-cercat-marcat' id='incidencia-".$cntElements."-".$z."' name='incidencia-3-".$z."'>".$vectInc[0]."</textarea>
                   </div>";
                   if ( count($vectInc) > 1 ) {
                      $htmlIncidenciesSave .= "<div class='form-group field-wrap position-relative mb-0 p-1 w-100 mb-1'>
                      <label class='active'>Proposta</label>
                      <textarea type='text' class='form-control proposta element-cercat-marcat' id='proposta-".$cntElements."-".$z."' name='proposta-3-".$z."'>".$vectInc[1]."</textarea>
                      </div>";
                   }
                   $htmlIncidenciesSave .= "<i class='fa-solid fa-circle-minus removeInputs'></i>
                   </div>";
                }
             }

             $registres .= "<tr>
                <td><p class='mb-0' id='element-".$cntElements."'>".$enunciat[$numEnunciat][0]."</p><p>".$enunciat[$numEnunciat][1]."</p></td>
                <td>
                   <div class='btn-group' id='rev".$cntElements."'>
                      <button type='button' class='btn btn-default btn-switch revisat ".$activeRevisat."'>Sí</button>
                      <button type='button' class='btn btn-default btn-switch no-revisat ".$noActiveRevisat."'>No</button>
                   </div>
                </td>
                <td>
                   ".$htmlIncidenciesSave."
                   <button id='addInc-".$cntElements."' role='button' class='addInc boto-verd px-4 d-flex'>
                      <i class='fa-solid fa-plus mr-1'></i>
                      Afegeix incidència
                   </button>
                </td>
             </tr>";
             $numEnunciat++;
             $cntElements++;
          }

          $tableRevisio .= "<table class='table table-hover table-striped mb-4' style='margin-top:0px; padding-bottom:5px;'>
             <thead>
                <tr>
                   <td style='width: 45%;'>".$apartat."</td>
                   <td class='text-center' style='width: 10%;'>REVISAT</td>
                   <td class='text-center' style='width: 45%;'>INCIDÈNCIA</td>
                </tr>
             </thead>
             <tbody>".$registres."</tbody>
          </table>";
       }

       $cntRevisio = $tableRevisio."
       <div class='d-flex flex-column flex-sm-row justify-content-center align-items-center w-100 text-center mt-1'>
          <input type='hidden' name='botPress'>
          <input type='hidden' name='num_incidencies' id='num_incidencies' value='Cap'>
          <button id='desaNoEnviar' role='button' class='desar boto-verd px-4 d-flex mx-2'>
             <i class='fa-solid fa-floppy-disk mx-2'></i>
             Desa sense enviar
          </button>
          <button id='desaEnviar' role='button' class='enviar boto-verd px-4 d-flex mx-2'>
             <i class='fa-solid fa-file-export mx-2'></i>
             Envia i acaba
          </button>
       </div>";
       $conWeb->closeStmt();
    }
    else {
     throw new Exception('', 11110);
    }
  }

  $conWeb->desconectarBD();

  $page = "<div class='container d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'>";
  $page .= "<h1>Revisió</h1>";
  $page .= $cntRevisio;
  $page .= "</div>";

   echo $page;

 }
 catch (Exception $e) {
   echo missatgeError( $e->getCode() );
 }
?>
