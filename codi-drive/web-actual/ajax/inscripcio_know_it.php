<?php

$val = $_REQUEST['val'];

?>
<font class="normal" style="float:left;">Com has conegut aquest curs?</font>&nbsp;&nbsp;&nbsp;
<select id="com_conegut" name="com_conegut" class="normal" onChange="conegut_altres()">
<option value="Cap" selected>-- Tria l'opció per indicar-nos com has conegut aquest curs --</option>
<option value="Recomanacio">Me l'han recomanat</option>
<option value="Cercador">L'he trobat en un cercador (Google, Bing...)</option>
<option value="Xarxes">L'he vist a les xarxes socials (Facebook, Instagram, Twitter...)</option>
<option value="Web PrisMa">L'he vist al web de PrisMa</option>

<?php
if ($val=="0") //Afegir opcio «He rebut el butlletí electrònic»
{
	?>
 <option value="Mailing">He rebut el butlletí electrònic (Newsletter)</option>
	<?php
}
?>

<option value="altres">Altres (indica'ns com)</option>
</select>
<br>
<input  type="text" name="c_altres" id="c_altres" style="display: none; margin: 10px 0px; width: 99%;" />
<div id="error_conegut" class="error" style="float:left;"></div>
