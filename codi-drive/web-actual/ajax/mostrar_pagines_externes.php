<?php

$elements = [
   [
      "https://www.prisma.cat/formacio-bonificada",
      "https://www.prisma.cat/img/bonificat-formacio.jpg",
      "Formació bonificada"
   ],
   [
      "https://www.prisma.cat/descomptes",
      "https://www.prisma.cat/img/descomptes-prisma.jpg",
      "Botiga"
   ],
   [
      "https://www.educat.cat/blog",
      "https://www.prisma.cat/img/educat-blog.jpg",
      "Educat"
   ],
   [
      "https://www.prisma.cat/formacio-colaboradors",
      "https://www.prisma.cat/img/colaboradors-prisma.jpg",
       "Els nostres col&middot;laboradors"
    ]
];

$linkImgOrig = substr($linkImg, 0, -4);
$linkImgWeb = $linkImgOrig.".webp";

$linkImg540JPG=$linkImgOrig."-540.jpg";
$linkImg345JPG=$linkImgOrig."-345.jpg";
$linkImg210JPG=$linkImgOrig."-210.jpg";
$linkImg260JPG=$linkImgOrig."-260.jpg";
$linkImg540Webp=$linkImgOrig."-540.webp";
$linkImg345Webp=$linkImgOrig."-345.webp";
$linkImg210Webp=$linkImgOrig."-210.webp";
$linkImg260Webp=$linkImgOrig."-260.webp";

$mostrar = "<div class='container'><div class='row'>";
for ($i = 0; $i < 4; $i++) {
   $urlElement = $elements[$i][0];
   $linkImg = $elements[$i][1];
   $dscImg = $elements[$i][2];

   $linkImgOrig = substr($linkImg, 0, -4);
   $linkImgWeb = $linkImgOrig.".webp";

   $linkImg540JPG=$linkImgOrig."-540.jpg";
   $linkImg345JPG=$linkImgOrig."-345.jpg";
   $linkImg210JPG=$linkImgOrig."-210.jpg";
   $linkImg260JPG=$linkImgOrig."-260.jpg";
   $linkImg540Webp=$linkImgOrig."-540.webp";
   $linkImg345Webp=$linkImgOrig."-345.webp";
   $linkImg210Webp=$linkImgOrig."-210.webp";
   $linkImg260Webp=$linkImgOrig."-260.webp";

   $picture = "<picture>
   <source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg540Webp."' srcset='".$linkImg540Webp."'>
   <source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg540JPG."' srcset='".$linkImg540JPG."'>
   <source media='(max-width: 990px)' type='image/webp' data-srcset='".$linkImg345Webp."' srcset='".$linkImg345Webp."'>
   <source media='(max-width: 990px)' type='image/jpeg' data-srcset='".$linkImg345JPG."' srcset='".$linkImg345JPG."'>
   <source media='(max-width: 1199px)' type='image/webp' data-srcset='".$linkImg210Webp."' srcset='".$linkImg210Webp."'>
   <source media='(max-width: 1199px)' type='image/jpeg' data-srcset='".$linkImg210JPG."' srcset='".$linkImg210JPG."'>
   <source media='(min-width: 1200px)' type='image/webp' data-srcset='".$linkImg260Webp."' srcset='".$linkImg260Webp."'>
   <source media='(min-width: 1200px)' type='image/jpeg' data-srcset='".$linkImg260JPG."' srcset='".$linkImg260JPG."'>

   <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
   <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
   <img data-src='".$linkImg."' alt=\"".$dscImg."\"
      class='altres-serveis w-100 lazyload' ".$onclick." />
   </picture>";

   $mostrar .= "<div class='col-12 col-sm-6 col-lg-3 mb-4 mb-lg-0'>
      <div class='element-extern'>
         <a href='".$urlElement."' rel='noopener' target='_blank'>".$picture."</a>
      </div>
   </div>";
}
$mostrar .= "</div></div>";

echo $mostrar;

?>
