<!-- AQUEST FITXER ÉS UITLITZAT PER:
/intranet/facturacio/pagaments_efectuats.php
/intranet/facturacio/pagaments_efectuats_tutor.php 
/intranet/facturacio/pagaments_efectuats_curs.php 
/intranet/facturacio/pagaments_efectuats_any_mes.php -->

<?php 
function url_exists($url)
{
	$file_headers = @get_headers($url);
	if(strpos($file_headers[0],"200 OK")==false)
		$exists = false;
	else
		$exists = true;
	return $exists;
}
?>