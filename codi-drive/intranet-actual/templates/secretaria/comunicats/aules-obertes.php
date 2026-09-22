<?php

$materialLecturaCurs = "<li>📚 Les <strong>lectures actualitzades</strong> i la bibliografia revisada.</li>";
if ( $cas1 == 1 ) {
   $materialLecturaCurs = "<li>📚 El <strong>material de suport actualitzat</strong>.</li>";
}

$missatge = "<p>Bon dia,</p>
<p>A partir d’avui teniu l’opció de <strong>sol·licitar l’accés</strong> a
l’<a href='https://campus.prisma.cat/course/view.php?id=[ID_MDL_AULA_BERTA]' target='_blank'><strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[NOM_CURS_AULA_OBERTA]</strong></a>,
un espai <strong>gratuït</strong> i <strong>autoregulat</strong> que oferim als alumnes de totes les edicions d’aquest curs.</p>

<p style='font-size:18px; font-weight:bold;'>Per a què serveixen les Aules Obertes?</p>
<p>A les Aules Obertes podreu:
<ul>
   <li>✅ <strong>Continuar aprenent</strong> amb els materials i recursos més recents.</li>
   <li>🤝 <strong>Compartir experiències</strong> i recursos amb altres participants que han completat el mateix curs.</li>
</ul>
</p>

<p style='font-size:18px; font-weight:bold;'>Què hi trobareu?</p>
<ul>
   ".$materialLecturaCurs."
   <li>🎥 Els <strong>audiovisuals</strong> dels mòduls.</li>
   <li>️🗄️ La <strong>mediateca actualitzada</strong>.</li>
   <li>💬 <strong>Fòrums</strong> per intercanviar idees, preguntes i recursos.</li>
</ul>


<p style='font-size:18px; font-weight:bold;'>Com sol·licitar-hi l’accés?</p>
<p>Només cal que entreu a l’apartat «<a target='_blank' href='https://campus.prisma.cat/alumnes/meus-cursos/' style='font-weight: bold; text-decoration: none;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>Tots els meus cursos</a>» de la vostra intranet i cliqueu al botó de <strong>demanar accés</strong> a l’Aula Oberta del curs que us interessi.</p>

<a href='https://campus.prisma.cat/alumnes/contacte' target='_blank'
   class='d-flex justify-content-center align-items-center mx-auto px-3 py-2 my-2'
   style='cursor: pointer;border: 1px solid #ccc;border-radius: 5px;
   background-color: #496baa;color: #fff !important;font-weight: bold; margin-bottom: 20px !important; max-width: 220px'' >
   <img title='La meva Intranet' alt='La meva Intranet'
   src='https://campus.prisma.cat/documents/imatges/icones/my-intranet-alumnes.svg'
   style='margin-right: 5px; width: 20px; vertical-align: middle;'>La meva intranet
</a>

<p>📅 N’obtindreu l’accés en un màxim de 48 hores laborables.</p>
<p>🔔 Tingueu en compte que sempre podreu donar-vos-en de baixa si ho desitgeu.</p>

<p style='font-size:18px; font-weight:bold;'>Termini i accés al curs tancat</p>
<p>Disposeu de <strong>tres mesos</strong> més, fins al <strong>[DATAF_INCR_3MONTHS]</strong>,
per continuar entrant al curs que heu realitzat per consultar-ne els fòrums, els qüestionaris, les devolucions, etc.</p>
<p>A partir d’aquesta data, l’accés al curs tancat quedarà deshabilitat i
<strong>només</strong> tindreu disponible l’<strong>Aula Oberta</strong> (si us hi heu inscrit).</p>

<p style='font-size:18px; font-weight:bold;'>Mantingueu-vos al dia!</p>

<p>🔔 <strong>Subscriviu-vos</strong> al nostre <a href='https://www.prisma.cat/mailing/nou.php'
style='font-weight: bold; text-decoration: none;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>butlletí electrònic</a>
per rebre novetats i promocions.</p>
<p>📲 <strong>Seguiu-nos</strong> a les nostres xarxes socials:
<a href='https://www.instagram.com/prisma.educacio/'
style='font-weight: bold; font-style: italic; text-decoration: none;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>Instagram</a>,
<a href='https://www.facebook.com/PrisMaFormacio' style='font-weight: bold; text-decoration: none;font-style: italic;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>Facebook</a>,
<a href='https://www.tiktok.com/@prisma.educacio' style='font-weight: bold; text-decoration: none;font-style: italic;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>TikTok</a> i
<a href='https://www.x.com/PrisMaFormacio' style='font-weight: bold; text-decoration: none;font-style: italic;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>X</a>.</p>
<p>⭐ I si teniu un minut…, ens encantaria llegir la vostra
<a href='https://g.page/r/CczqwM5-nqOCEB0/review'
style='font-weight: bold; text-decoration: none;
color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>ressenya a <em>Google</em></a>!
La vostra opinió ens ajuda a millorar.</p>
<p>Salutacions ben cordials,</p>
<p>Pablo Martori</p>
<p>Secretari Pedagògic</p>
";

?>
