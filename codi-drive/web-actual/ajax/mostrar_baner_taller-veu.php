<?php

$linkImg = "https://www.prisma.cat/img/banners/taller-veu.jpg";
$dscImg = "Cuida la Teva Veu: un Matí de Pràctica";

$linkImgOrig = substr($linkImg, 0, -4);
$linkImgWeb = $linkImgOrig.".webp";

function linkImg($maxwidth, $extension, $linkImgOrig) {
	$mides = ["400", "800", "1040", "1500", ""];

	if ( $maxwidth == 576) $mida = $mides[0];
	else if (  $maxwidth == 990 ) $mida = $mides[1];
	else if (  $maxwidth == 1200 ) $mida = $mides[2];
	else if (  $maxwidth == 1500 ) $mida = $mides[3];
	else if (  $maxwidth == 1501 ) $mida = $mides[4];

	if ( $mida != '' ) $mida = "-".$mida;

	$link = $linkImgOrig.$mida.".".$extension;

	return $link;

}

$mostrar="
	<div class='back-banner desc position-relative p-0 m-0'>
	<picture>
		<source media='(max-width: 576px)' type='image/webp' data-srcset='".linkImg(576,'webp',$linkImgOrig)."' srcset='".linkImg(576,'webp',$linkImgOrig)."'>
		<source media='(max-width: 576px)' type='image/jpeg' data-srcset='".linkImg(576,'jpg',$linkImgOrig)."' srcset='".linkImg(576,'jpg',$linkImgOrig)."'>
		<source media='(max-width: 990px)' type='image/webp' data-srcset='".linkImg(990,'webp',$linkImgOrig)."' srcset='".linkImg(990,'webp',$linkImgOrig)."'>
		<source media='(max-width: 990px)' type='image/jpeg' data-srcset='".linkImg(990,'jpg',$linkImgOrig)."' srcset='".linkImg(990,'jpg',$linkImgOrig)."'>
		<source media='(max-width: 1200px)' type='image/webp' data-srcset='".linkImg(1200,'webp',$linkImgOrig)."' srcset='".linkImg(1200,'webp',$linkImgOrig)."'>
		<source media='(max-width: 1200px)' type='image/jpeg' data-srcset='".linkImg(1200,'jpg',$linkImgOrig)."' srcset='".linkImg(1200,'jpg',$linkImgOrig)."'>
		<source media='(max-width: 1500px)' type='image/webp' data-srcset='".linkImg(1500,'webp',$linkImgOrig)."' srcset='".linkImg(1500,'webp',$linkImgOrig)."'>
		<source media='(max-width: 1500px)' type='image/jpeg' data-srcset='".linkImg(1500,'jpg',$linkImgOrig)."' srcset='".linkImg(1500,'jpg',$linkImgOrig)."'>
		<source media='(min-width: 1501px)' type='image/webp' data-srcset='".linkImg(1501,'webp',$linkImgOrig)."' srcset='".linkImg(1501,'webp',$linkImgOrig)."'>
		<source media='(min-width: 1501px)' type='image/jpeg' data-srcset='".linkImg(1501,'jpg',$linkImgOrig)."' srcset='".linkImg(1501,'jpg',$linkImgOrig)."'>
		<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
		<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
		<img data-src='".$linkImg."' alt=\"".$dscImg."\" src='".$linkImg."'
			class='baner position-relative position-relative descompte text-white d-flex flex-column justify-content-center align-items-center lazyload'/>
		</picture>
		<div class='escrit d-flex flex-column w-100 h-100 position-absolute justify-content-center align-items-center px-1'>
			<h4 class='text-white text-center font-weight-bold mt-0'>Cuida la Teva Veu: un Matí de Pràctica</h5>
			<p class='text-white py-3 mb-0 text-center'>Formació pràctica per evitar fatiga vocal i lesions!</p>
			<button onclick=\"location.href='https://www.prisma.cat/cuida-veu-un-mati-practica'\" title='Formació pràctica per evitar fatiga vocal i lesions! | PrisMa'>Més informació</a>
		</div>
	</div>";

echo $mostrar;
?>
