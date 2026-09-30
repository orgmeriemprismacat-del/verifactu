# Lot 12 — UC-123 · generar i lliurar factura electrònica en format i canal acordats

**Tall de fonts:** `main` revisat el 25/09/2026, fitxa/UML a la branca `docs/registre-mestre-auditoria-2026-09-22`. **Abast contrastat:** pantalla de factura abans de pagar, consulta/edició i PDF intern de factura, wrappers PHP que invoquen `Intranet`, metadades de document SIF i taula SQL de lliurament. **No s'ha verificat:** el cos del gran `Intranet.php` amb aquesta extracció (el connector no retorna els seus fragments), altres pantalles de portal alumne, missatgeria real, arxius de factures personals, cap executor de generació d'un estàndard electrònic ni producció. No interpretar absència en les superfícies inspeccionades com a inexistència absoluta.

[Fitxa v2](../06-fitxes-funcionals/uc-123.md) · [UML ACTUAL/FINAL](uc-123-lliurar-factura-electronica.md) · [activitats P01–P07](uc-123-activitats-pagines-factura-electronica-actual-final.md).

## 1. Matriu de superfícies, apartats i controls

| ID | Superfície real i codi | ACTUAL acreditat | Límits de la UC |
| --- | --- | --- | --- |
| P01 | [`alumnes-genera-factura-abans-pagar.php`](../../codi-drive/intranet-actual/alumnes-genera-factura-abans-pagar.php), [JS L90–236](../../codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js#L90-L236) | Pas 1: cercar inscripcions per DNI, afegir o eliminar inscripcions de la selecció, preparar `idsInsc`, `cursos`, `edicions`; sumar `#apagar-{ID}` per calcular `preuTotal` al navegador. | Selecció d'inscripcions i import a facturar **no són decisió de format/canal ni generació del document electrònic**. Import de client ha de contrastar-se al backend en l'emissió. |
| P02 | [JS L304–389](../../codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js#L304-L389), [`generaFacturaElectronica_Factures.php`](../../codi-drive/intranet-actual/ajax/alumnes/generaFacturaElectronica_Factures.php) | Pas 2: triar `empresa`, concepte, veure preu i fer POST de `empresa,concepte1,concepte2,preu,cursos,edicions,inscripcions,observacions`; wrapper invoca `Intranet::generarFacturaElectronica_Alumnes()`. El JS considera èxit si la resposta textual no conté «error» i mostra «Factura creada!». | El nom del mètode **no demostra** format, XML validat, hash de fitxer, autorització del destinatari o lliurament. No s'ha rellegit el cos d'`Intranet::generarFacturaElectronica_Alumnes` en aquest lot. |
| P03 | [JS L371–452](../../codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js#L371-L452), [`mostraDadesFacturaElectronica_Factures.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostraDadesFacturaElectronica_Factures.php), [`mostraInscripcionsFacturaElectronica_Factures.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostraInscripcionsFacturaElectronica_Factures.php) | Pas 3: consultar dades de la factura i inscripcions, obrir modal de previsualització, fer GET a `descarregaFactura.php` i després a `eliminarArxiu.php` per un fitxer temporal. | Previsualització/descàrrega de factura llegat **no equivalen** a format electrònic estructurat acreditat ni a enviament a canal contractat. L'eliminació de fitxer temporal del navegador no prova esborrat de document fiscal immutable. |
| P04 | [`alumnes-factura.php`](../../codi-drive/intranet-actual/alumnes-factura.php), [JS L321–434](../../codi-drive/intranet-actual/js/alumnes-factura.js#L321-L434), [`guardarDadesFactura_Factures.php`](../../codi-drive/intranet-actual/ajax/alumnes/guardarDadesFactura_Factures.php) | Consulta/edició de dades i concepte de factura: JS `tePermisEdicio`, GET amb `id,factura,rao,cif,cp,poblacio,adreca,concepte1,concepte2,obs` cap a `Intranet::guardarDadesFactura_Factures()`. | **Risc de migració:** la UI llegada ofereix edició d'una factura; el SIF no pot propagar un GET d'edició a la factura fiscal emesa. El grau de validació/permisos/restriccions al cos PHP gran no s'ha revisat aquí. |
| P05 | [JS L635–707](../../codi-drive/intranet-actual/js/alumnes-factura.js#L635-L707), [`descarregaFactura.php`](../../codi-drive/intranet-actual/ajax/alumnes/descarregaFactura.php) | Modal de previsualitzar i petició GET `id` per cridar `Intranet::generaFactura($id,true)` (amb `Dompdf` inclòs al wrapper); el JS inicia descàrrega. | Generar/obtenir PDF d'ús intern no és una evidència de format/versionat/canal/lliurament de factura electrònica per UC-123. L'autorització backend de l'ID requereix verificació. |
| P06 | [`DocumentRepository.php` L9–39](../../sif/src/Repository/DocumentRepository.php#L9-L39), [`InvoiceRepository.php` L81–124](../../sif/src/Repository/InvoiceRepository.php#L81-L124) | `DocumentRepository::registerDocument(db,UUID_FACTURA,type,path,contents)` accepta només `PDF/XML/QR`, calcula SHA-256 dels bytes lliurats i insereix `factura_documents(...ESTAT='CREATED')`. `InvoiceRepository::insertInvoice()` inicia `factura.E_FACT=0`. | No hi ha `file_put_contents`, escriptura de storage ni transport en aquest repositori de **metadades**. `E_FACT=0/1` no prova que existeixi XML, PDF lliurat o consentiment; UC-032 és la preferència separada. |
| P07 | [SQL 000005 L242–269](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L242-L269) i [`DocumentsAndIncidentsTest.php` L14–37](../../sif/tests/Integration/DocumentsAndIncidentsTest.php#L14-L37) | DDL `electronic_invoice_delivery` amb `FORMAT_CODE/VERSION`, destinatari hash, `CHANNEL`, estat, idempotència, intents/retry i prova. El test existent verifica que **un PDF fictici** crea metadada/hash, no el fitxer o la seva entrega. | **Només disseny del lliurament:** cap writer/worker/transport end-to-end provat en aquesta revisió. No inferir format concret (p. ex. Facturae) sense contracte de destinataris i adaptador real. |

## 2. Set dimensions diferents que no s'han de col·lapsar

(1) Factura emesa/UUID i línies fiscals immutables; (2) si s'ha emès abans de cobrar; (3) `E_FACT` com a indicador de preferència sol·licitada, gestionat a UC-032; (4) artefacte generat i validat en **format/versionat acordat**, bytes físicament custodiats/hash comprovat; (5) petició/intents de transmissió amb canal/destinatari autoritzats; (6) confirmació de lliurament amb prova suficient segons canal; (7) registre i resultat de remissió a AEAT. La pàgina llegada «Factura creada!» és resultat d'una crida de creació de factura, no confirmació dels punts 4–7.

## 3. Riscos i mancances, no incidents productius confirmats

| ID | Evidència | Acció FINAL |
| --- | --- | --- |
| UC123-P0-01 · inferir factura electrònica d'un nom de mètode | `generaFacturaElectronica_Factures.php` delega `generarFacturaElectronica_Alumnes` amb conceptes/import però no envia format, versió, adreça i canal. | Separar creació factura/UC emissió de generació i lliurament UC-123. No mostrar «factura electrònica lliurada» a partir d'«Factura creada!». |
| UC123-P0-02 · document només metadades | `DocumentRepository` desa `PATH_FITXER` i hash dels bytes aportats sense escriure fitxer. | Custòdia física verificable, relectura i comparació de SHA-256, registre només després d'emmagatzematge efectiu i permisos. |
| UC123-P0-03 · destinatari/permisos | `empresa` seleccionada al pas de creació; el receptor fiscal no és automàticament qualsevol contacte o inscrit d'un grup. | Verificar representació, correu/adreça/endpoint, dret a obtenir document i canal; no enviar factura d'empresa a tot `IDPAG`. |
| UC123-P1-04 · pantalla llegada editable | `guardarDadesFactura_Factures.php` accepta raó, CIF, adreça i conceptes per GET. | No editar factura fiscal emesa en el nou SIF. Classificar errors fiscals per UC corrector; preferència `E_FACT` separada. |
| UC123-P1-05 · prova de transmissió | `electronic_invoice_delivery` existeix com DDL, però writer, transport, comprovant i migració aplicada no verificats. | Job/outbox amb idempotència, estats i resultat per intent, prova immutable de lliurament o rebuig, retry sense reemissió. |
| UC123-P1-06 · confusió XML | `DocumentRepository` accepta `XML`; XML de remissió AEAT i XML de factura per receptor tenen objectes/contrats diferents. | Adaptador i validador específics del format pactat; no reusar XML AEAT com a factura electrònica del destinatari. |
| UC123-P1-07 · client i resultats | JS d'emissió calcula `preuTotal` sumant elements DOM i mostra èxit per absència de «error». | Emissió amb càlcul servidor, resposta tipificada i després estat propi de generació/lliurament. |
| UC123-P1-08 · fitxer temporal i accés | `descarregaFactura.php` retorna ruta/nom via `generaFactura` i el JS sol·licita `eliminarArxiu.php`. | Custòdia immutable distinta de PDF temporal, endpoint d'accés auditat amb controls backend. No s'ha verificat el cos de generació/esborrat llegat. |

## 4. Proves T01–T16 proposades, cap executada

| ID | Escenari exigible |
| --- | --- |
| UC123-T01 | Factura emesa amb `E_FACT=0`: existeix factura real, no es marca document lliurat. |
| UC123-T02 | Marcar preferència després d'emetre: mateix UUID/número, encara cal format/canal i destinatari. |
| UC123-T03 | Generació en format i versió acordats, validació d'esquema, bytes re-llegits igual al hash. |
| UC123-T04 | Registrar metadada amb PATH sense fitxer real: no declarar document físicament generat. |
| UC123-T05 | Receptor fiscal entitat, contacte no autoritzat: bloquejar transmissió/descàrrega. |
| UC123-T06 | Alumne d'un grup intenta accedir a factura completa d'empresa per IDPAG: denegar. |
| UC123-T07 | Format no negociat, versió no admesa o adaptador inexistent: estat pendent, no enviar XML arbitrari. |
| UC123-T08 | Error temporal del canal: mateix UUID_FACTURA/document, retry sense segona emissió. |
| UC123-T09 | Reintent equivalent/doble clic: una comanda, intents documentats, cap lliurament duplicat no controlat. |
| UC123-T10 | Canal informa només «enviat»: no declarar «lliurat» sense prova definida per aquell canal. |
| UC123-T11 | Destinatari rebutja document: resultat rebutjat separat de factura emesa i remissió AEAT. |
| UC123-T12 | PDF de previsualització/descàrrega interna no genera per si sol fila `electronic_invoice_delivery`. |
| UC123-T13 | XML AEAT disponible però XML acordat amb receptor absent: no declarar factura electrònica preparada. |
| UC123-T14 | Canvi d'adreça quan un job és a la cua: revalidar adreça/permís i preservar intents previs. |
| UC123-T15 | `E_FACT` desactivat després de lliurament: conservar document/prova històrics; aplicar decisió futura segons política. |
| UC123-T16 | Edició de raó/CIF/concepte a UI llegada després d'emissió SIF: no modificar línies/numeració/document immutable. |

**Tests de codi ja definits:** `DocumentsAndIncidentsTest::testRegisterDocumentStoresImmutableHashOnly()` comprova hash/metadata de `PDF` fictici, no genera PDF físic, XML electrònic ni lliurament. No s'ha executat en aquesta auditoria.

## 5. Decisions de negoci i estat

`DEC123-01` format/versionat per tipus de receptor; `DEC123-02` canal/adreça, consentiment quan pertoqui i qui l'aprova; `DEC123-03` què acredita `DELIVERED` per canal (enviat ≠ rebut/acceptat); `DEC123-04` SLA, intents/retry, canal alternatiu i retenció de prova; `DEC123-05` gestió de documents previs i preferència `E_FACT` activada/desactivada; `DEC123-06` distinció exacta PDF de visualització/XML acordat/registre XML AEAT. No deduir aquestes decisions del tipus `XML` o de `E_FACT`.

**DOC:** fluxos web interns i metadades/DDL SIF contrastats amb límits; **IMP:** UI llegada de factura/PDF i inserció de metadades reals, servei integral de factura electrònica i lliurament no acreditat; **TEST:** T01–T16 proposats, no executats; **PRODUCCIÓ:** no verificada.
