<?php


if ( $pagament > 0 ) {
   $textPagat = "<li style='margin-top: 8px; line-height: 24px;' value=3>
   <strong>Sol·licitar la devolució de l’import abonat</strong><br />

   Si no vas fer el pagament amb targeta, necessitarem que ens facilitis un
   número de compte per fer la devolució. Ens el pots enviar per correu electrònic a
   <a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
   color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a> o
   b&eacute; trucant al 972 21 75 65.</li>";

   $textCanviCurs = "<li style='margin-top: 8px; line-height: 24px;'>
      <strong>Canviar-te a un altre curs amb un 25% de descompte</strong><br />
      Si tries un altre curs que comenci també [DATAI], et farem un
      <strong>descompte especial del 25%</strong> sobre el preu del nou curs.
      Pots consultar l’oferta de cursos al nostre
      <a href='https://www.prisma.cat/cursos/[MES]/[ANY]'
      style='font-weight: bold; text-decoration: none;
      color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>web</a>
      i contactar amb nosaltres per gestionar el canvi.
   </li>";
}
else {
   $textPagat = "";
   $textCanviCurs = "<li style='margin-top: 8px; line-height: 24px;'>
          <strong>Canviar-te a un altre curs que comenci també [DATAI]</strong><br />
          Pots consultar l’oferta de cursos al nostre <a href='https://www.prisma.cat/cursos/[MES]/[ANY]'
          style='font-weight: bold; text-decoration: none;
          color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>web</a>
          i contactar amb nosaltres per gestionar el canvi.
   </li>";
}

if ( count($dates) > 0 ) {
   $novesDates = "<ul style='list-style: circle; margin-left: 30px; padding: 0;'>";
   for ( $i = 0; $i < count($dates); $i++ ) {
      $novesDates .= "<li style='margin-top: 8px; line-height: 24px;'>".$dates[$i]."</li>";
   }
   $novesDates .= "</ul>";
}
else {
   $novesDates = "Properes dates: a concretar.";
}

$missatge = "<p><p>Benvolgut/da [NOM_ALUMNE],</p>
   <p align=justify>Sentim comunicar-te que, a causa del baix nombre de participants
   inscrits, el curs <strong>[TITOL]</strong>, previst per iniciar-se [DATAI], ha estat anul·lat.
   Les teves dades personals i de pagament que consten al nostre sistema són:</p>
   <div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
      <p><strong>Nom:</strong> [NOM_ALUMNE] [COG_ALUMNE]</p>
      <p><strong>NIF/NIE/passaport:</strong> [DNI_ALUMNE]</p>
      <p><strong>Correu electrònic:</strong> [CORREU_ALUMNE]</p>
      <p><strong>Telèfon de contacte:</strong> [TEL_ALUMNE]</p>
      <p><strong>Pagament:</strong> [PAY_ALUMNE] € [METHOD_PAY].</p>
   </div>
   <p>T’oferim diverses opcions perquè puguis decidir com vols procedir:</p>
   <ol style='margin-bottom: 20px; margin-left: 30px; padding: 0;'>
      ".$textCanviCurs."
      <li style='margin-top: 8px; line-height: 24px;'>
         <strong>Posposar la inscripció a una altra convocatòria</strong><br />
         ".$novesDates."
      </li>
      ".$textPagat."
   </ol>

   <p>Disculpa les molèsties que aquesta anul·lació et pugui haver causat. Restem a la teva disposició per qualsevol dubte o gestió.</p>
";

?>
