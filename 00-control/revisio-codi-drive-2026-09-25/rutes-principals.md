# Rutes literals dels controladors principals

Rutes extretes dels condicionals `url->get()` i crides `__mostrarPage_*`. No substitueixen el contingut dinàmic de `apartats`, permisos de BD ni configuració de servidor. Duplicats/candidats es mantenen identificats.

| Aplicació/font | Ruta | Mètode | Línia del mètode |
| --- | --- | --- | --- |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1380) | `/home/` | `__mostrarPage_Inici` | 1602 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1383) | `/perfil/mostra-perfil/` | `__mostrarPage_Perfil_MostrarPerfil` | 30652 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1386) | `/alumnes/mostrar-alumne/` | `__mostrarPage_Alumnes_MostrarAlumne` | 4212 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1389) | `/alumnes/pagaments/` | `__mostrarPage_Alumnes_Pagaments` | 10330 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1392) | `/alumnes/genera-factura-abans-pagar/` | `__mostrarPage_Alumnes_GeneraFacturaAbansPagar` | 13039 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1395) | `/alumnes/genera-entitat/` | `__mostrarPage_Alumnes_GeneraEntitat` | 13774 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1398) | `/alumnes/factura/` | `__mostrarPage_Alumnes_ConsultaFactura` | 14090 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1401) | `/alumnes/validar-descomptes/` | `__mostrarPage_Alumnes_ValidarProfessorNovell` | 16096 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1401) | `/alumnes/validar-descomptes/` | `__mostrarPage_Alumnes_ValidarDescomptes` | 15272 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1405) | `/curs/mostrar-curs/` | `__mostrarPage_Cursos_MostrarCurs` | 16708 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1408) | `/cursos/cursos-oberts/` | `__mostrarPage_Cursos_CursosOberts` | 18394 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1411) | `/cursos/inici-cursos/generar-fitxer-pujada-alumnes/` | `__mostrarPage_Cursos_Pujada_Inscripcions` | 3619 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1414) | `/cursos/inici-cursos/comprovar-correus-diferents/` | `__mostrarPage_Cursos_ComprovarCorreusDiferentsBDiCampus` | 20611 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1417) | `/cursos/inici-cursos/foto-perfil/` | `__mostrarPage_Cursos_FotoPerfil` | 20960 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1420) | `/cursos/previ-inici-cursos/estat-cursos/` | `__mostrarPage_Cursos_PreviInici_MostrarEstatCursos` | 18948 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1423) | `/cursos/previ-inici-cursos/avisar-inici-cursos-pendents/` | `__mostrarPage_Cursos_PreviInici_MostrarAvisCursosPendents` | 20138 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1426) | `/cursos/previ-inici-cursos/avisar-inici-cursos-pendents-tots/` | `__mostrarPage_Cursos_PreviInici_MostrarAvisCursosPendentsTots` | 21316 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1429) | `/cursos/previ-inici-cursos/proposta-tutoritzacio/` | `__mostrarPage_Cursos_PreviInici_MostrarPropostaTutoritzacio` | 21871 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1432) | `/cursos/previ-inici-cursos/duplicats/` | `__mostrarPage_Cursos_PreviInici_Duplicats` | 22803 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1435) | `/cursos/fi-cursos/pujar-aules-obertes/` | `__mostrarPage_Cursos_Pujada_AO` | 3307 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1438) | `/cursos/fi-cursos/pujar-gtaf/` | `__mostrarPage_Cursos_Pujar_GTAF` | 3071 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1441) | `/cursos/fi-cursos/correu-comunicat-certificat/` | `__mostrarPage_Cursos_Certificat` | 22933 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1444) | `/cursos/fi-cursos/canviar-forums-debat-simple/` | `__mostrarPage_Cursos_Fi_Cursos_Canviar_Forums_Debat_Simple` | 23383 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1447) | `/cursos/fi-cursos/curs-superat-no-superat/` | `__mostrarPage_Cursos_Fi_Cursos_Curs_Superat` | 23628 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1450) | `/cursos/fi-cursos/curs-superat-no-superat-curs-aula/` | `__mostrarPage_Cursos_Fi_Cursos_Curs_Superat` | 23628 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1453) | `/cursos/ultimes-tasques/bloquejar-cursos/` | `__mostrarPage_Cursos_Bloqueig_Cursos` | 1855 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1456) | `/cursos/ultimes-tasques/curs-superat/` | `__mostrarPage_Cursos_Curs_Superat` | 2198 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1459) | `/cursos/ultimes-tasques/fer-informes-educacio/` | `__mostrarPage_Cursos_Informes_Ensenyament` | 2392 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1462) | `/cursos/ultimes-tasques/fer-informes-fiss/` | `__mostrarPage_Cursos_Informes_FISS` | 2634 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1465) | `/cursos/cursos-nous/` | `__mostrarPage_Cursos_CursosNous` | 18699 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1468) | `/cursos/afegir-edicions/` | `__mostrarPage_Cursos_AfegirEdicions` | 18814 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1471) | `/tutors/proposta-tutoritzacio/` | `__mostrarPage_Cursos_PreviInici_MostrarPropostaTutoritzacio` | 21871 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1474) | `/tutors/confirmacio-tutoritzacio/` | `__mostrarPage_Tutors_MostrarConfirmacioTutoritzacio` | 30328 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1477) | `/web/trobades-linia/avis-pre-trobada/` | `__mostrarPage_WEB_Trobades_TaulaAvis` | 30927 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1480) | `/web/trobades-linia/resum-dades/` | `__mostrarPage_WEB_Trobades_Resum` | 31215 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1483) | `/web/cursos-creacio/imatges/` | `__mostrarPage_WEB_CursNou_Imatges` | 31304 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1486) | `/dades/nombre-alumnes/` | `__mostrarPage_Dades_Comprovar_Nombre_Alumnes` | 31547 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1489) | `/facturacio/comprovar-idpags/` | `__mostrarPage_Facturacio_Comprovar_Duplicats_IdPags` | 32181 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1492) | `/facturacio/primera-reclamacio/` | `__mostrarPage_Facturacio_Primera_Reclamacio` | 32352 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1495) | `/facturacio/baixes/` | `__mostrarPage_Facturacio_Baixes_Segona_Setmana` | 32870 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1498) | `/facturacio/recordatori-pagament/` | `__mostrarPage_Facturacio_Recordatori_Pagament` | 33806 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1501) | `/facturacio/reclamacio-final/` | `__mostrarPage_Facturacio_BaixaMorosos` | 34535 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1504) | `/facturacio/morosos/` | `__mostrarPage_Facturacio_Morosos` | 35863 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1507) | `/calendar_meriem/` | `__mostrarPage_Calendari_Meriem` | 37486 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1510) | `/marqueting/calendar/` | `__mostrarPage_Marqueting_Calendari` | 37627 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1513) | `/xarxes/calendar/` | `__mostrarPage_Xarxes_Calendari` | 37778 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1516) | `/mailing/calendar/` | `__mostrarPage_Mailing_Calendari` | 37923 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1519) | `/devolper/calendar/` | `__mostrarPage_Develop_Calendari` | 38068 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1522) | `/comunicats/obertura-aules/` | `__mostrarPage_Comunicats_OberturaAules` | 25699 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1525) | `/comunicats/a-punt-comencar/` | `__mostrarPage_Comunicats_aPuntComencar` | 26400 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1528) | `/comunicats/servei-atencio/` | `__mostrarPage_Comunicats_ServeiAtencio` | 27120 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1531) | `/comunicats/aules-obertes/` | `__mostrarPage_Comunicats_AulesObertes` | 27715 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1534) | `/comunicats/certificat/` | `__mostrarPage_Comunicats_Certificat` | 28377 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1537) | `/comunicats/ia/` | `__mostrarPage_Comunicats_IA` | 29726 |
| [intranet-actual/Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L1540) | `/comunicats/tancament-curs/` | `__mostrarPage_Comunicats_Tancament` | 29052 |
| [intranet-alumne-actual/IntranetAlumne.php](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L196) | `/alumnes/` | `__mostrarPage_Inici` | 234 |
| [intranet-alumne-actual/IntranetAlumne.php](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L199) | `/alumnes/dades/` | `__mostrarPage_Alumnes_Dades_Personals` | 324 |
| [intranet-alumne-actual/IntranetAlumne.php](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L202) | `/alumnes/meus-cursos/` | `__mostrarPage_Alumnes_Meus_Cursos` | 847 |
| [intranet-alumne-actual/IntranetAlumne.php](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L205) | `/alumnes/cursos-no-he-realitzat/` | `__mostrarPage_Alumnes_Cursos_No_He_Realitzat` | 2030 |
| [intranet-alumne-actual/IntranetAlumne.php](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L208) | `/alumnes/contacte/` | `__mostrarPage_Alumnes_Contacte` | 2046 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L251) | `/collaboradors/` | `__mostrarPage_Inici` | 295 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L254) | `/collaboradors/gestio-cobraments/` | `__mostrarPage_Tutors_Gestio_Cobraments` | 344 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L257) | `/collaboradors/consulta-cobraments/` | `__mostrarPage_Tutors_Consulta_Cobraments` | 348 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L260) | `/collaboradors/consulta-revisions/` | `__mostrarPage_Tutors_Consulta_Revisions` | 741 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L263) | `/collaboradors/consulta-informes/` | `__mostrarPage_Tutors_Consulta_Informes` | 745 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L266) | `/collaboradors/consulta-alumnes/` | `__mostrarPage_Tutors_Consulta_Alumnes` | 749 |
| [intranet-collaboradors/IntranetTutor.php](../../codi-drive/intranet-collaboradors/IntranetTutor.php#L269) | `/collaboradors/contacte/` | `__mostrarPage_Tutors_Contacte` | 1033 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1312) | `/home/` | `__mostrarPage_Inici` | 1528 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1315) | `/perfil/mostra-perfil/` | `__mostrarPage_Perfil_MostrarPerfil` | 29026 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1318) | `/alumnes/mostrar-alumne/` | `__mostrarPage_Alumnes_MostrarAlumne` | 4031 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1321) | `/alumnes/pagaments/` | `__mostrarPage_Alumnes_Pagaments` | 10134 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1324) | `/alumnes/genera-factura-abans-pagar/` | `__mostrarPage_Alumnes_GeneraFacturaAbansPagar` | 12843 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1327) | `/alumnes/genera-entitat/` | `__mostrarPage_Alumnes_GeneraEntitat` | 13578 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1330) | `/alumnes/factura/` | `__mostrarPage_Alumnes_ConsultaFactura` | 13894 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1333) | `/alumnes/validar-descomptes/` | `__mostrarPage_Alumnes_ValidarProfessorNovell` | 15870 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1333) | `/alumnes/validar-descomptes/` | `__mostrarPage_Alumnes_ValidarDescomptes` | 15076 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1337) | `/curs/mostrar-curs/` | `__mostrarPage_Cursos_MostrarCurs` | 16482 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1340) | `/cursos/cursos-oberts/` | `__mostrarPage_Cursos_CursosOberts` | 18168 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1343) | `/cursos/inici-cursos/generar-fitxer-pujada-alumnes/` | `__mostrarPage_Cursos_Pujada_Inscripcions` | 3438 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1346) | `/cursos/inici-cursos/comprovar-correus-diferents/` | `__mostrarPage_Cursos_ComprovarCorreusDiferentsBDiCampus` | 20364 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1349) | `/cursos/inici-cursos/foto-perfil/` | `__mostrarPage_Cursos_FotoPerfil` | 20713 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1352) | `/cursos/previ-inici-cursos/estat-cursos/` | `__mostrarPage_Cursos_PreviInici_MostrarEstatCursos` | 18723 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1355) | `/cursos/previ-inici-cursos/avisar-inici-cursos-pendents/` | `__mostrarPage_Cursos_PreviInici_MostrarAvisCursosPendents` | 19891 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1358) | `/cursos/previ-inici-cursos/avisar-inici-cursos-pendents-tots/` | `__mostrarPage_Cursos_PreviInici_MostrarAvisCursosPendentsTots` | 21069 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1361) | `/cursos/previ-inici-cursos/proposta-tutoritzacio/` | `__mostrarPage_Cursos_PreviInici_MostrarPropostaTutoritzacio` | 21465 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1364) | `/cursos/previ-inici-cursos/duplicats/` | `__mostrarPage_Cursos_PreviInici_Duplicats` | 22404 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1367) | `/cursos/fi-cursos/pujar-aules-obertes/` | `__mostrarPage_Cursos_Pujada_AO` | 3126 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1370) | `/cursos/fi-cursos/pujar-gtaf/` | `__mostrarPage_Cursos_Pujar_GTAF` | 2892 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1373) | `/cursos/fi-cursos/correu-comunicat-certificat/` | `__mostrarPage_Cursos_Certificat` | 22534 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1376) | `/cursos/fi-cursos/canviar-forums-debat-simple/` | `__mostrarPage_Cursos_Fi_Cursos_Canviar_Forums_Debat_Simple` | 22872 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1379) | `/cursos/fi-cursos/curs-superat-no-superat/` | `__mostrarPage_Cursos_Fi_Cursos_Curs_Superat` | 23117 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1382) | `/cursos/ultimes-tasques/bloquejar-cursos/` | `__mostrarPage_Cursos_Bloqueig_Cursos` | 1781 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1385) | `/cursos/ultimes-tasques/curs-superat/` | `__mostrarPage_Cursos_Curs_Superat` | 2041 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1388) | `/cursos/ultimes-tasques/fer-informes-educacio/` | `__mostrarPage_Cursos_Informes_Ensenyament` | 2235 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1391) | `/cursos/ultimes-tasques/fer-informes-fiss/` | `__mostrarPage_Cursos_Informes_FISS` | 2455 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1394) | `/cursos/cursos-nous/` | `__mostrarPage_Cursos_CursosNous` | 18474 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1397) | `/cursos/afegir-edicions/` | `__mostrarPage_Cursos_AfegirEdicions` | 18589 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1400) | `/tutors/proposta-tutoritzacio/` | `__mostrarPage_Cursos_PreviInici_MostrarPropostaTutoritzacio` | 21465 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1403) | `/tutors/confirmacio-tutoritzacio/` | `__mostrarPage_Tutors_MostrarConfirmacioTutoritzacio` | 28702 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1406) | `/web/trobades-linia/avis-pre-trobada/` | `__mostrarPage_WEB_Trobades_TaulaAvis` | 29301 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1409) | `/web/trobades-linia/resum-dades/` | `__mostrarPage_WEB_Trobades_Resum` | 29589 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1412) | `/web/cursos-creacio/imatges/` | `__mostrarPage_WEB_CursNou_Imatges` | 29678 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1415) | `/dades/nombre-alumnes/` | `__mostrarPage_Dades_Comprovar_Nombre_Alumnes` | 29921 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1418) | `/facturacio/comprovar-idpags/` | `__mostrarPage_Facturacio_Comprovar_Duplicats_IdPags` | 30555 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1421) | `/facturacio/primera-reclamacio/` | `__mostrarPage_Facturacio_Primera_Reclamacio` | 30726 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1424) | `/facturacio/baixes/` | `__mostrarPage_Facturacio_Baixes_Segona_Setmana` | 31244 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1427) | `/facturacio/recordatori-pagament/` | `__mostrarPage_Facturacio_Recordatori_Pagament` | 32180 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1430) | `/facturacio/reclamacio-final/` | `__mostrarPage_Facturacio_BaixaMorosos` | 32909 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1433) | `/facturacio/morosos/` | `__mostrarPage_Facturacio_Morosos` | 34237 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1436) | `/calendar_meriem/` | `__mostrarPage_Calendari_Meriem` | 35860 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1439) | `/marqueting/calendar/` | `__mostrarPage_Marqueting_Calendari` | 36001 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1442) | `/xarxes/calendar/` | `__mostrarPage_Xarxes_Calendari` | 36152 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1445) | `/mailing/calendar/` | `__mostrarPage_Mailing_Calendari` | 36297 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1448) | `/devolper/calendar/` | `__mostrarPage_Develop_Calendari` | 36442 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1451) | `/comunicats/obertura-aules/` | `__mostrarPage_Comunicats_OberturaAules` | 24743 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1454) | `/comunicats/a-punt-comencar/` | `__mostrarPage_Comunicats_aPuntComencar` | 25443 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1457) | `/comunicats/servei-atencio/` | `__mostrarPage_Comunicats_ServeiAtencio` | 26098 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1460) | `/comunicats/aules-obertes/` | `__mostrarPage_Comunicats_AulesObertes` | 26693 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1463) | `/comunicats/certificat/` | `__mostrarPage_Comunicats_Certificat` | 27355 |
| [intranet-nova-canvis-verifactu/Intranet.php](../../codi-drive/intranet-nova-canvis-verifactu/Intranet.php#L1466) | `/comunicats/tancament-curs/` | `__mostrarPage_Comunicats_Tancament` | 28030 |

## Diferències de declaracions intranet actual / candidata

Actual: 444 noms únics; candidata: 432. Són declaracions textuals, incloses eventuals còpies comentades.

Noms no trobats a la candidata: `sendMsgTutorAvisCursosPendentsTots`, `mostrarTable_Alumnes_EnviarCertficat_Curs`, `mostrarTable_Alumnes_EnviarMsgCursSuperat_Curs_aula`, `mostrarTable_Alumnes_Curs_EnviarMsgCursSuperat_Curs_aula`, `__mostrarPage_Comunicats_IA`, `viewTemplateComunicate_IA`, `viewContainerComunicate__IA`, `viewCourses_Comunicate_IA`, `__getLabelSendComunicate_IA`, `viewCourses_Comunicate_IAOrderBy`, `viewComunicate_IA`, `insertComunicate_IA`.

Noms només a la candidata: Cap.
