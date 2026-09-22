<?php
require('../config.php');
session_start();
// print_r($_SESSION);
if ( !isloggedin() ) {
	?>
	<script>window.location.href = "https://campus.prisma.cat/login"</script>
	<?php
}
else {
	include 'inc/iniciaSessio.php';
	if ( $iniciaSessio ) {
		?>
			<script>
			urlSucc = "https://campus.prisma.cat/alumnes/inici/";
			window.location.replace(urlSucc);
			</script>
		<?php
	}
	else {
		?>
			<script>
			urlSucc = "https://campus.prisma.cat/alumnes/errorLogin/";
			window.location.replace(urlSucc);
			</script>
		<?php
	}
}

?>
