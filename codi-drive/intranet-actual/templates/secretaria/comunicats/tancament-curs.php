<?php

$materialLecturaCurs = "<li>Les <strong>lectures actualitzades</strong>.</li>";
if ( $cas1 == 1 ) {
   $materialLecturaCurs = "<li>Les <strong>presentacions que es van fer servir a les sessions síncrones</strong>.</li>";
}

$missatge = "<p>Bon dia,</p>
<p>Us informem que a partir del <strong>[DATAF_INCR_3MONTHS]</strong> es tancarà el curs
<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[TITOL]</strong>
corresponent a l’edició [EDICIO_MES].</p>

<p>A partir d'aquesta data, <strong>només podreu accedir</strong> (si ho heu sol·licitat i us hi heu inscrit) a
l’<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[NOM_CURS_AULA_OBERTA]</strong>, on trobareu:</p>

<ul>
   <li>Els <strong>exalumnes</strong> que han fet el curs des de la primera edició.</li>
   ".$materialLecturaCurs."
   <li>La <strong>mediateca actualitzada</strong>.</li>
   <li><strong>Fòrums</strong> per intercanviar experiències i recursos.</li>
</ul>

<p>Podeu accedir a l'<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[NOM_CURS_AULA_OBERTA]</strong> des d'aquesta imatge:</p>
<p><a href='https://campus.prisma.cat/course/view.php?id=[ID_MDL_AULA_OBERTA]' target='_blank'>
   <img src='https://intranet.prisma.cat/img/comunicats/cursos/[CODI_CURS].jpg'
   style='border: 1px solid #ccc; border-radius: 2px; max-width: 1120px' />
</a></p>

<p>Si no hi esteu inscrits, podeu visitar la vostra
<a href='https://campus.prisma.cat/alumnes/' style='font-weight: bold; text-decoration: none;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>Intranet</strong></a> per demanar-ne l’accés:</p>

<a href='https://campus.prisma.cat/alumnes/contacte' target='_blank'
   class='d-flex justify-content-center align-items-center mx-auto px-3 py-2 my-2'
   style='cursor: pointer;border-radius: 5px;
   background-color: #496baa;color: #fff !important;font-weight: bold; margin-bottom: 20px !important; max-width: 220px'' >
   <img title='La meva Intranet' alt='La meva Intranet'
   src='https://campus.prisma.cat/documents/imatges/icones/my-intranet-alumnes.svg'
   style='margin-right: 5px; width: 20px; vertical-align: middle;'>La meva intranet
</a>

<p style='font-size:16px; font-weight:bold;'>📧 Subscriviu-vos al nostre butlletí electrònic!</p>
<p>Si encara no us heu subscrit al nostre butlletí electrònic i us interessa rebre novetats sobre els nostres cursos, podeu fer-ho aquí:</p>
<a title='Subscripció Butlletí electrònic' name='Subscripció Butlletí electrònic'
   href='https://www.prisma.cat/mailing/nou.php' target='_blank' class='d-flex justify-content-center align-items-center mx-auto px-3 py-2 my-2'
   style='cursor: pointer; border-radius: 5px; background-color: #496baa; color: #fff !important; font-weight: bold; margin-bottom: 20px !important; max-width: 220px'>
      <img title='Subscripció Butlletí electrònic' alt='Subscripció Butlletí electrònic'
      src='https://campus.prisma.cat/documents/imatges/icones/envelope-solid.svg'
      style='margin-right: 5px; width: 20px; vertical-align: middle;'>Butlletí electrònic
</a>
<p>Ben cordialment,</p>
<p>Pablo Martori</p>
<p>Secretari Pedagògic</p>

";

?>
