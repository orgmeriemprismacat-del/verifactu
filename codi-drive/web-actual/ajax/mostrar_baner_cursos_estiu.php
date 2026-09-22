<?php

$linkImg = "https://www.prisma.cat/img/banners/cursos-estiu.jpg";
$dscImg = "Cursos d'estiu";

$linkImgOrig = substr($linkImg, 0, -4);
$linkImgWeb = $linkImgOrig.".webp";

$linkImg575JPG=$linkImgOrig."-575.jpg";
$linkImg990JPG=$linkImgOrig."-990.jpg";
$linkImg1200JPG=$linkImgOrig."-1200.jpg";
$linkImg1500JPG=$linkImgOrig."-1500.jpg";
$linkImg575Webp=$linkImgOrig."-575.webp";
$linkImg990Webp=$linkImgOrig."-990.webp";
$linkImg1200Webp=$linkImgOrig."-1200.webp";
$linkImg1500Webp=$linkImgOrig."-1500.webp";

$mostrar="
	<div class='back-banner regala position-relative p-0 m-0'>
	<picture>
		<source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg575Webp."' srcset='".$linkImg575Webp."'>
		<source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg575JPG."' srcset='".$linkImg575JPG."'>
		<source media='(max-width: 990px)' type='image/webp' data-srcset='".$linkImg990Webp."' srcset='".$linkImg990Webp."'>
		<source media='(max-width: 990px)' type='image/jpeg' data-srcset='".$linkImg990JPG."' srcset='".$linkImg990JPG."'>
		<source media='(max-width: 1200px)' type='image/webp' data-srcset='".$linkImg1200Webp."' srcset='".$linkImg1200Webp."'>
		<source media='(max-width: 1200px)' type='image/jpeg' data-srcset='".$linkImg1200JPG."' srcset='".$linkImg1200JPG."'>
		<source media='(max-width: 1600px)' type='image/webp' data-srcset='".$linkImg1500Webp."' srcset='".$linkImg1500Webp."'>
		<source media='(max-width: 1600px)' type='image/jpeg' data-srcset='".$linkImg1500JPG."' srcset='".$linkImg1500JPG."'>
		<source media='(min-width: 1601px)' type='image/webp' data-srcset='".$linkImgWeb."' srcset='".$linkImgWeb."'>
		<source media='(min-width: 1601px)' type='image/jpeg' data-srcset='".$linkImg."' srcset='".$linkImg."'>
		<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
		<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
		<img data-src='".$linkImg."' alt=\"".$dscImg."\" src='".$linkImg."'
			class='baner regala text-white d-flex flex-column justify-content-center align-items-center lazyload'/>
	</picture>
	<div class='escrit d-flex flex-column w-100 h-100 position-absolute justify-content-center align-items-center px-1'>
		<h4 class='text-white text-center font-weight-normal mt-0'>Cursos d'estiu</h4>
		<p class='text-white py-3 mb-0 text-center'>Cursos en línia i mixtos per als mesos de <strong>juliol i agost</strong></p>
		<button onclick=\"location.href='https://www.prisma.cat/cursos-estiu'\" title='Cursos d'estiu | PrisMa'>Més informació</a>
	</div>
</div>";

echo $mostrar;
?>
