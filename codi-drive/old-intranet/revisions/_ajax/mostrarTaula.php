<?php

   $hores = $_REQUEST['hores'];
   $shortname = $_REQUEST['shortname']; 
   $course = $_REQUEST['course'];
   $num_inc = $_REQUEST['num_apartats'];

   $revisionsSave = [];
   $incidenciesSave = [];
   $cntElementSave = 0;

   include('../../inc/dades.php');
   include ('../inc/enunciats-'.$hores.'.php'); 

   //connexió a la bd de prisma
   $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
   if (mysqli_connect_errno())
   {
   	echo "No es pot connectar: " . mysqli_connect_error();
   }
   mysqli_set_charset($connexio, "utf8");

   $cnsExisteixRevisio = "SELECT guardat, finalitzat, revisat, incidencies, INC_GENERAL, INC_LECTURES,
   	INC_MEDIATECA, INC_BIBLIO, INC_ALTRES FROM revisio_tutor WHERE codic = '".$shortname."'";
   $result = mysqli_query ($connexio, $cnsExisteixRevisio);
   $row = mysqli_fetch_array($result);

   $rowcount = mysqli_num_rows($result);

   $guardat = $row["guardat"];
   $finalitzat = $row["finalitzat"];
   $revisat = $row["revisat"];
   $incGeneral = $row["INC_GENERAL"];
   $incLectures = $row["INC_LECTURES"];
   $incBiblio = $row["INC_BIBLIO"];
   $incMediateca = $row["INC_MEDIATECA"];
   $incAltres = $row["INC_ALTRES"];
   $incidencies = $row["incidencies"];

   $enviat = 0;
   $enviatAntic = 0;

   if ( $rowcount > 0 ) {
      if ( $finalitzat == '' || $finalitzat == null ) {
         //s'ha enviat
         $cntRevisio = "<p>La revisió encara no està enviada.</p>
         <p>Per a qualsevol modificació, consulta amb <strong>secretaria@prisma.cat</strong>.</p>
         <p>Gràcies.</p>";
      }
      else {
         if ( $incidencies == '' || $incidencies == null )
            $enviat = 1;
         else
            $enviatAntic = 1;
      }
   }

   if ( $enviatAntic) {
      $vectAux = explode( '#', $revisat);
      for ( $i=1; $i<count($vectAux); $i++)
         $revisionsSave[$i]=$vectAux[$i];

      $cntElementSave = 0;

      $vectAux = explode( '#', $incidencies);
      for ( $i=1; $i< count($vectAux); $i++) {
         $dada = $vectAux[$i];
         if ( $dada == "Cap" )
            $incidenciesSave[$cntElementSave] = [];
         else {
            $incidenciesSave[$cntElementSave] = ['', $dada];
         }
         $cntElementSave++;
      }
   }

   if ( $enviat ) {
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

   if ( $enviat || $enviatAntic ) {
      $cnsDadesCurs = "SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf,
         DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, DNI_TUTOR, m.nom as nmes,
         SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits,
         SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`='1') THEN 1 ELSE 0 END) aprovats,
         SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`='1') THEN 1 ELSE 0 END) pendents,
         DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats
         FROM cursos AS c INNER JOIN personal AS p ON c.DNI_TUTOR=p.DNI INNER JOIN
          inscripcions AS i ON c.CURS = i.CURS AND c.ANY = i.ANY AND c.MES = i.MES
          AND c.AULA=i.Grup INNER JOIN mesos AS m ON m.num = i.MES
         WHERE id_Curs='".$shortname."'";

      $result_dades = mysqli_query ($connexio, $cnsDadesCurs);
      $row = mysqli_fetch_array($result_dades);

      $nomCognoms = $row['tnom']." ".$row['tcogs'];
      $curs = $row['ncurs']." - Aula ".$row['AULA'];
      $convocatoria = $row['nmes'];

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
         </tbody>
      </table>";

      if ( $hores == '30')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == '40')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == '60')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 5], ["MÒDUL 6", 5], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == '100')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == '100-2')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 3], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == 'dfd')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["MÒDUL 6", 1], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == 'htp')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["JA HEM ARRIBAT AL FINAL", 1]];
      else if ( $hores == 'eines')
         $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 4], ["MÒDUL 2", 4], ["MÒDUL 3", 4], ["JA HEM ARRIBAT AL FINAL", 1]];	  
		 else if ( $hores == 'cat')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["JA HEM ARRIBAT AL FINAL", 1]];
	 	else if ( $hores == 'tic')
	   $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 6], ["JA HEM ARRIBAT AL FINAL", 1]];
	 	else if ( $hores == 'tics')
	   $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 4], ["MÒDUL 1", 5], ["MÒDUL 2", 5], ["MÒDUL 3", 5], ["MÒDUL 4", 5], ["MÒDUL 5", 4], ["JA HEM ARRIBAT AL FINAL", 1]];
		else if ( $hores == 'acos')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 5], ["MÒDUL 1", 2], ["MÒDUL 2", 2], ["MÒDUL 3", 2], ["MÒDUL 4", 2], ["MÒDUL 5", 2], ["MÒDUL 6", 2], ["MÒDUL 7", 2], ["JA HEM ARRIBAT AL FINAL", 1]];
		else if ( $hores == 'sui')
           $apartats = [ [ "BÀNERS TUTORIES", 2 ], ["APARTAT GENERAL", 3], ["MÒDUL 1", 3], ["MÒDUL 2", 3], ["MÒDUL 3", 3], ["MÒDUL 4", 3], ["JA HEM ARRIBAT AL FINAL", 1]];

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
                  <div class='form-group field-wrap position-relative mb-0 pt-3 w-100 mb-1'>
                  <label class='active'>Incidència</label>
                  <div class='border-0 incidencia' id='incidencia-".$cntElements."-".$z."' name='incidencia-3-".$z."'>".$vectInc[0]."</div>
                  </div>";
                  if ( count($vectInc) > 1 ) {
                     $htmlIncidenciesSave .= "<div class='form-group field-wrap position-relative mb-0 pt-3 w-100 mb-1'>
                     <label class='active'>Proposta</label>
                     <div class='border-0 proposta' id='proposta-".$cntElements."-".$z."' name='proposta-3-".$z."'>".$vectInc[1]."</div>
                     </div>";
                  }
                  $htmlIncidenciesSave .= "</div>";
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

      $cntRevisio = $tableRevisio;
   }

   $page = "<div class='container d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'>";
   $page .= "<h1>Revisió</h1>";
   $page .= $cntRevisio;
   $page .= "</div>";

   echo $page;

?>
