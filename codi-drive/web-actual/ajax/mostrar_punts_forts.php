<?php

$icona1 = "<div class='d-flex flex-column align-items-center col-12 col-sm-6 col-lg-3 mb-4 mb-lg-0 icones-rodones'>";
$icona1 .= "<div class='d-flex flex-column justify-content-center align-items-center icones-img' ";
$icona1 .= "title='Els nostres cursos' onclick=\"location.href='https://www.prisma.cat/cursos'\">";
$icona1 .= "<i class='fas fa-mouse'></i></div><a class='color-text' ";
$icona1 .= "href='https://www.prisma.cat/cursos' target='_self' ";
$icona1 .= "title='Cursos en línea que ofereix PrisMa'><h2>Els nostres cursos</h2></a>";
$icona1 .= "<p class='text-center mb-0'>Formació en línia per a docents i ";
$icona1 .= "altres professionals de l’educació que volen ampliar ";
$icona1 .= "coneixements,  visions i propostes educatives.</p></div>";

$icona2 = "<div class='d-flex flex-column align-items-center col-12 col-sm-6 col-lg-3 mb-4 mb-lg-0 icones-rodones icona2'>";
$icona2 .= "<div class='d-flex flex-column justify-content-center align-items-center icones-img' ";
$icona2 .= "title='Metodologia' onclick=\"location.href='https://www.prisma.cat/metodologia'\">";
$icona2 .= "<i class='fas fa-cogs'></i></div><a class='color-text' ";
$icona2 .= "href='https://www.prisma.cat/metodologia' target='_self'";
$icona2 .= "title='Mostra més informació de la metodologia'><h2>Metodologia</h2></a>";
$icona2 .= "<p class='text-center mb-0'>Cursos en línia asíncrons, flexibles, centrats en ";
$icona2 .= "el participant, i amb una tutoria activa i personalitzada ";
$icona2 .= "que afavoreix la interacció contínua.</p></div>";

$icona3 = "<div class='d-flex flex-column align-items-center col-12 col-sm-6 col-lg-3 mb-4 mb-lg-0 icones-rodones'>";
$icona3 .= "<div class='d-flex flex-column justify-content-center align-items-center icones-img' ";
$icona3 .= "title='Reconeixement' onclick=\"location.href='https://www.prisma.cat/reconeixements-certificacio'\">";
$icona3 .= "<i class='fas fa-award'></i></div><a class='color-text' ";
$icona3 .= "href='https://www.prisma.cat/reconeixements-certificacio' target='_self'";
$icona3 .= "title='Mostra més informació del reconeixement'><h2>Reconeixement</h2></a>";
$icona3 .= "<p class='text-center mb-0'>Cursos reconeguts i certificats com a ";
$icona3 .= "formació permanent del professorat pel Departament d’Educació ";
$icona3 .= "de la Generalitat de Catalunya.</p></div>";

$icona4 = "<div class='d-flex flex-column align-items-center col-12 col-sm-6 col-lg-3 mb-4 mb-lg-0 icones-rodones'>";
$icona4 .= "<div class='d-flex flex-column justify-content-center align-items-center icones-img' ";
$icona4 .= "title='Equip docent' onclick=\"location.href='https://www.prisma.cat/docents'\">";
$icona4 .= "<i class='fas fa-users'></i></div><a class='color-text' ";
$icona4 .= "href='https://www.prisma.cat/docents' target='_self'";
$icona4 .= "title='Tutors dels cursos on-line de PrisMa'><h2>Equip docent</h2></a>";
$icona4 .= "<p class='text-center mb-0'>Professionals que col·laboren a PrisMa i ";
$icona4 .= "que acompanyen el procés d’aprenentatge dels participants ";
$icona4 .= "dels cursos en línia.</p></div>";

$mostrar = "<div class='container px-md-0'><div class='row'>";
$mostrar .= $icona1.$icona2.$icona3.$icona4;
$mostrar .= "</div></div>";

echo $mostrar;
?>
