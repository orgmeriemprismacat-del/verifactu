<?php


$intro = "<p>Us informem que ja hem obert les «portes» de les aules virtuals del curs
<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[TITOL]</strong>
corresponent a l’edició [EDICIO_MES].
Tot i que les classes començaran oficialment [DATAI],
ja podeu accedir-hi per començar a familiaritzar-vos amb els continguts.</p>";

if ( $cas1 == 1 ) {
   $intro = "<p>Us informem que ja hem obert les «portes» de les aules virtuals del curs
   <strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[TITOL]</strong>
   corresponent a l’edició [EDICIO_MES].
   Tot i que la primera sessió del curs serà [DATA_SESSIO1] a les [HORA_SESSIO1],
   ja podeu accedir-hi per començar a familiaritzar-vos amb els continguts.</p>";
}

$materialCurs = "<p>A partir d’ara ja podeu entrar al vostre curs per
<strong>presentar-vos</strong>, i a més a més podeu:
   <ul>
      <li>Llegir les <strong>Lectures</strong>.</li>
      <li>Consultar la <strong>Bibliografia</strong>.</li>
      <li>Visitar els enllaços de la <strong>Mediateca</strong>.</li>
   </ul>
</p>";
if ( $cas2 == 1 ) {
   $materialCurs = "<p>A partir d’ara ja podeu entrar al vostre curs per
   <strong>presentar-vos</strong>, i a més a més podeu:
      <ul>
      <li>Llegir la <strong>Guia general</strong>.</li>
      <li>Visitar els enllaços de la <strong>Mediateca</strong>.</li>
      </ul>
   </p>";
}

$missatge = "<p>Bon dia,</p>
   ".$intro."
   <p>Em dic <strong>Pablo Martori</strong> i, juntament amb els meus companys de secretaria,
   m’encarrego de les tasques administratives dels cursos: gestionar inscripcions,
   respondre correus, emetre certificats, validar les dades, etc.</p>
   ".$materialCurs."
   <p>Per accedir-hi, entreu a <a href='https://www.prisma.cat' target='_blank' style='font-weight: bold; text-decoration: none;
      color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>www.prisma.cat</a>
      amb el vostre <strong>usuari i contrasenya</strong>
      (els heu rebut per correu electrònic o són els mateixos d’edicions anteriors).
   </p>
   <p>Si teniu problemes per accedir al campus, cliqueu a
      «<a href='https://campus.prisma.cat/login/forgot_password.php' target='_blank'
      style='font-weight: bold; text-decoration: none;
      color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>Heu oblidat la contrasenya?</a>»
      i introduïu el vostre <strong>DNI sense la lletra</strong>.
      Rebreu una nova contrasenya provisional per correu electrònic.
   </p>
   <p>
      <strong>🔔 Important:</strong> si ja no teniu accés al <strong>correu electrònic
      associat al vostre usuari del campus</strong>,
      poseu-vos en contacte amb nosaltres i us ajudarem a actualitzar les dades.
   </p>
   <p>Un cop dins del campus, trobareu els cursos actius a l’apartat
   «<strong>Tots els meus cursos</strong>». Només cal clicar-hi per començar.</p>
   <p>Si teniu qualsevol dubte o incidència sobre el funcionament del curs, estem a la vostra disposició:
      <ul>
         <li>
            📧 <a href='mailto:secretaria@prisma.cat' style='font-weight: bold; text-decoration: none;
            color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>secretaria@prisma.cat</a>
         </li>
         <li>
            📞 <strong>972 21 75 65</strong> / <strong>678 123 687</strong>
            (de dilluns a divendres, de 8 h a 15 h)
         </li>
      </ul>
   </p>
   <p>Rebeu una salutació ben cordial,</p>
   <p>Pablo Martori</p>
   <p>Secretari Pedagògic</p>";
?>
