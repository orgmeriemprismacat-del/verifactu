<?php

if ( $vista == 2 ) {
   $missatge = "<p>
      Si vols pagar més endavant o mitjançant <span class='font-weight-bold'>
      transferència</span> o <span class='font-weight-bold'>ingrés bancari</span>,
      podràs fer-ho visitant l’enllaç que has rebut en el correu de confirmació.
   </p>";
}
else {
   if ( $type == 'R' ) $conecpte = "<span class='codiRegal'>[CODI]</span>";
   else  if ( $type == 'G' || $type == 'N' ) $conecpte = "<span class='codiCurs'>[CODI]</span>-[MES]";
   else if ( $type == 'P' ) $conecpte = "<span class='codiCurs'>[CODI]</span>";

   $missatge = "<div class='form-dades'>
   <h3>Pagament per transferència o ingrés bancari</h3>
   <p>
      Si ho prefereixes, pots fer una TRANSFERÈNCIA o INGRÉS BANCARI,
      indicant clarament el concepte <span class='font-weight-bold'>
      «".$conecpte."»</span>  en qualsevol dels comptes següents:
   </p>
   <ul>
      <li>La Caixa: ES30 2100 4279 21 2200080678<br />
      Beneficiari: <strong>ASSOC. PEL DESENVOLUPAMENT INFANTIL FAMILIAR PRISMA</strong></li>
      <li>BBVA: ES04 0182 5117 00 0201534816<br />
      Beneficiari: <strong>ASSOCIACIO PER AL DESENVOLOPAMENT INFANTIL I FAMILIAR PRISMA</strong>
      </li>
      <p>
         Si en fer la transferència el teu banc mostra un avís
         indicant que el nom del beneficiari no coincideix exactament, pot ser degut a abreviacions,
         majúscules, accents o al nom registrat internament per l'entitat bancària.
         <strong>Abans de confirmar l'operació, revisa que l'IBAN sigui un dels indicats anteriorment.</strong>
      </p>
   </ul>
   </div>";
}

$missatge .= "<p>
   Un cop hagis realitzat el pagament, et demanem que conservis
   el justificant bancari fins que t'arribi un correu electrònic
   que et confirmi que l'hem rebut correctament.
</p>";

/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */

?>
