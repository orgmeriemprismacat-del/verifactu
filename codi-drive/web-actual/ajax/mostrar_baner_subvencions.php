<?php

$linkImg = "https://www.prisma.cat/img/banners/cursos-subvencionats.jpg";
$dscImg = "Cursos subvencionats!";

$linkImgOrig = substr($linkImg, 0, -4);
$linkImgWeb = $linkImgOrig.".webp";

$linkImg400JPG=$linkImgOrig."-400.jpg";
$linkImg800JPG=$linkImgOrig."-800.jpg";
$linkImg1040JPG=$linkImgOrig."-1040.jpg";
$linkImg1500JPG=$linkImgOrig."-1500.jpg";
$linkImg400Webp=$linkImgOrig."-400.webp";
$linkImg800Webp=$linkImgOrig."-800.webp";
$linkImg1040Webp=$linkImgOrig."-1040.webp";
$linkImg1500Webp=$linkImgOrig."-1500.webp";

$mostrar="
	<div class='back-banner trobades position-relative p-0 m-0'>
	<picture>
		<source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg400Webp."' srcset='".$linkImg400Webp."'>
		<source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg400JPG."' srcset='".$linkImg400JPG."'>
		<source media='(max-width: 990px)' type='image/webp' data-srcset='".$linkImg800Webp."' srcset='".$linkImg800Webp."'>
		<source media='(max-width: 990px)' type='image/jpeg' data-srcset='".$linkImg800JPG."' srcset='".$linkImg800JPG."'>
		<source media='(max-width: 1200px)' type='image/webp' data-srcset='".$linkImg1040Webp."' srcset='".$linkImg1040Webp."'>
		<source media='(max-width: 1200px)' type='image/jpeg' data-srcset='".$linkImg1040JPG."' srcset='".$linkImg1040JPG."'>
		<source media='(max-width: 1600px)' type='image/webp' data-srcset='".$linkImg1500Webp."' srcset='".$linkImg1500Webp."'>
		<source media='(max-width: 1600px)' type='image/jpeg' data-srcset='".$linkImg1500JPG."' srcset='".$linkImg1500JPG."'>
		<source media='(min-width: 1601px)' type='image/webp' data-srcset='".$linkImgWeb."' srcset='".$linkImgWeb."'>
		<source media='(min-width: 1601px)' type='image/jpeg' data-srcset='".$linkImg."' srcset='".$linkImg."'>
		<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
		<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
		<img data-src='".$linkImg."' alt=\"".$dscImg."\" src='".$linkImg."'
			class='baner trobades text-white d-flex flex-column justify-content-center align-items-center lazyload'/>
		</picture>
		<div class='escrit d-flex flex-column w-100 h-100 position-absolute justify-content-center align-items-center px-1'>
			<h4 class='text-white text-center font-weight-normal mt-0'>".$dscImg."</h4>
			<p class='text-white py-3 mb-0 text-center'>Cursos subvencionats amb el fons Next Generation EU</p>
			<button onclick=\"location.href='https://www.prisma.cat/cursos-subvencionats'\"
			title='".$dscImg." | PrisMa'>Més informació</a>
		</div>
	</div>";

echo $mostrar;
?>
