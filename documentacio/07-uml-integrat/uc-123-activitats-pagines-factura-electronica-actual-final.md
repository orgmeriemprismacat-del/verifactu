# UC-123 — activitats per pantalla, modal, document i lliurament ACTUAL/FINAL

**Tall 25/09/2026.** **ACTUAL** = pantalles/codi de factura i PDF o repositori de metadades inspeccionats a `main`; no significa que siguin un procés integral de factura electrònica. **FINAL** = disseny a implementar. **P07 (transport format acordat)** no té pantalla/worker real acreditat, per tant només FINAL. [Fitxa](../06-fitxes-funcionals/uc-123.md) · [auditoria lot 12](00-auditoria-casos-pendents-lot-12-uc-123-2026-09-25.md) · [UML](uc-123-lliurar-factura-electronica.md).

## P01 — «Generar factura abans de pagar»: cerca i selecció d'inscripcions

**Font:** [`alumnes-genera-factura-abans-pagar.js` L90–236](../../codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js#L90-L236).

### ACTUAL
```plantuml
@startuml
title UC123 P01 ACTUAL - Cercar i seleccionar inscripcions
start
:Operador obre Generar factura abans de pagar;
:Cercar persona/inscripcions per document;
:Seleccionar o treure inscripcions del llistat;
if (Continuar?) then (Si)
 :JS prepara idsInsc, cursos i edicions seleccionats;
 :Suma valors A_PAGAR de la vista per preuTotal;
 :Mostra pas 2 de conceptes/empresa;
else (No)
 :Mantenir la seleccio local;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC123 P01 FINAL - Separar emissio de lliurament
start
:Consultar factura existent del receptor autoritzat;
if (Encara no hi ha factura emesa?) then (Si)
 :Derivar a UC emissio sobre imports validats al backend;
else (No)
 :Carregar UUID_FACTURA emesa i identificacio fiscal immutable;
endif
:No convertir seleccio d'inscripcions en preferencia de lliurament;
stop
@enduml
```

## P02 — «Generar factura abans de pagar»: empresa, concepte i «Factura creada!»

**Font:** [JS L304–390](../../codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js#L304-L390) i [`generaFacturaElectronica_Factures.php`](../../codi-drive/intranet-actual/ajax/alumnes/generaFacturaElectronica_Factures.php).

### ACTUAL
```plantuml
@startuml
title UC123 P02 ACTUAL - Endpoint anomenat factura electronica
start
:Operador selecciona empresa, concepte i observacions;
:JS comprova camps requerits i pren preuTotal de la vista;
:POST empresa, conceptes, preu, cursos, edicions, inscripcions;
:Wrapper crida Intranet.generarFacturaElectronica_Alumnes;
if (Resposta textual no conte error?) then (Si)
 :Mostrar Factura creada i obrir pas 3;
else (No)
 :Mostrar incidencia de creacio;
endif
note right
 El nom del metode no demostra
 generacio de format electronic
 ni transport/lliurament al receptor.
 Cos Intranet.php no revalidat aqui.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC123 P02 FINAL - Factura emesa com a precondicio
start
:Comprovar autoritzacio i receptor fiscal;
:Validar dades/total al servidor;
:Crear o recuperar UUID_FACTURA per UC emissio;
if (Factura ISSUED real?) then (Si)
 :Mostrar numero/UUID amb estat documental independent;
 :Consultar preferencia E_FACT i requisits del receptor;
else (No)
 :No programar lliurament de factura inexistent;
endif
stop
@enduml
```

## P03 — Pas 3: dades, previsualització, descàrrega i fitxer temporal

**Font:** [JS L371–452](../../codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js#L371-L452) i [wrappers AJAX de dades i inscripcions](../../codi-drive/intranet-actual/ajax/alumnes/mostraDadesFacturaElectronica_Factures.php).

### ACTUAL
```plantuml
@startuml
title UC123 P03 ACTUAL - Consultar i descarregar document intern
start
:Despres del missatge Factura creada;
:POST consultar dades de factura i inscripcions;
:Mostrar pas 3 i modal de previsualitzacio;
if (Operador clica descarregar?) then (Si)
 :GET descarregaFactura.php per identificador;
 :JS crea enllac de descarrega del fitxer retornat;
 :GET eliminarArxiu.php per fitxer temporal;
endif
note right
 No s'ha comprovat el cos PHP
 de generacio, la custodia fiscal
 ni prova de lliurament.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC123 P03 FINAL - Visualitzacio i accés auditat
start
:Mostrar estats independents emesa, E_FACT, artefacte i lliurament;
:Comprovar permis de l'actor sobre la factura;
if (Demanar visualitzacio/PDF?) then (Si)
 :Servir copia autoritzada via endpoint auditat;
 :No exposar storageRef intern ni document d'altre receptor;
endif
:No marcar DELIVERED per descarrega de gestio interna;
stop
@enduml
```

## P04 — «Consulta / Anul·la factura»: modal de dades i «Desar»

**Font:** [`alumnes-factura.js` L321–434](../../codi-drive/intranet-actual/js/alumnes-factura.js#L321-L434), [`guardarDadesFactura_Factures.php`](../../codi-drive/intranet-actual/ajax/alumnes/guardarDadesFactura_Factures.php).

### ACTUAL
```plantuml
@startuml
title UC123 P04 ACTUAL - Modal editable llegat
start
:Operador consulta factura i obre modal de dades;
if (JS tePermisEdicio?) then (Si)
 :Activar editar/cancelar/desar;
 if (Desar?) then (Si)
  :GET dades fiscals, conceptes, observacions i id factura;
  :Wrapper delega en Intranet.guardarDadesFactura_Factures;
  :JS interpreta resposta textual i repinta la vista;
 else (Cancel.lar)
  :Tancar edicio sense enviar l'ordre;
 endif
else (No)
 :Mostrar avis de manca de permís al client;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC123 P04 FINAL - Preferencia documental separada de factura
start
:Consultar UUID_FACTURA immutable i actor autoritzat;
if (Canviar preferencia E_FACT?) then (Si)
 :Derivar a UC032 amb historial, actor i motiu;
endif
if (Corregir rao, NIF o concepte d'una factura emesa?) then (Si)
 :Derivar a UC corrector, no UPDATE de factura original;
endif
:Consultar format/canal/lliurament a UC123 per separat;
stop
@enduml
```

## P05 — Modal «Previsualitza» i descàrrega de factura

**Font:** [`alumnes-factura.js` L635–707](../../codi-drive/intranet-actual/js/alumnes-factura.js#L635-L707), [`descarregaFactura.php`](../../codi-drive/intranet-actual/ajax/alumnes/descarregaFactura.php).

### ACTUAL
```plantuml
@startuml
title UC123 P05 ACTUAL - Generacio o descarrega PDF llegat
start
:Obrir previsualitzacio d'una factura de la intranet;
if (Clicar descarrega?) then (Si)
 :GET descarregaFactura.php amb id;
 :Wrapper inclou Dompdf i crida Intranet.generaFactura(id,true);
 :Retornar resposta per a la descarrega;
endif
note right
 El PDF de gestio no acredita
 entrega del format electronic
 acordat amb el receptor.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC123 P05 FINAL - Artefactes diferents
start
:Comprovar permis de veure la factura;
:Consultar PDF de visualitzacio i fitxer electronic pactat;
if (Format requerit ja custodiat?) then (Si)
 :Servir el fitxer autentic i comparar integritat;
else (No)
 :Programar generacio validada sense reemetre factura;
endif
:Registrar accés, no inferir lliurament a receptor;
stop
@enduml
```

## P06 — SIF: registrar artefacte i indicador E_FACT

**Font:** [`DocumentRepository.php` L9–39](../../sif/src/Repository/DocumentRepository.php#L9-L39), [`InvoiceRepository.php` L81–124](../../sif/src/Repository/InvoiceRepository.php#L81-L124), [SQL 000005 L242–269](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L242-L269).

### ACTUAL
```plantuml
@startuml
title UC123 P06 ACTUAL - Metadades de document
start
:UC emissio desa factura amb E_FACT = 0 inicial;
:DocumentRepository rep UUID_FACTURA, tipus, path i bytes;
if (Tipus PDF/XML/QR i path valid?) then (Si)
 :Calcula SHA256 de contents rebut;
 :INSERT factura_documents amb estat CREATED;
else (No)
 :Llença error de validacio;
endif
note right
 Aquest metode NO escriu el fitxer
 ni el lliura. Tampoc valida
 un esquema electronic del receptor.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC123 P06 FINAL - Generar i custodiar artefacte validat
start
:Carregar factura emesa i format/versio acordats;
:Validar receptor i actor;
:Generar bytes amb adaptador del format acordat;
:Validar esquema/contingut i coherencia amb factura;
:Escriure fitxer a storage protegit;
:Rellegir bytes i verificar hash;
if (Custodia i integritat correctes?) then (Si)
 :Registrar factura_documents i versio del document;
else (No)
 :Obrir incidencia sense declarar artefacte disponible;
endif
stop
@enduml
```

## P07 — canal de lliurament de factura electrònica, NOMÉS FINAL

**No s'ha identificat un writer/worker/transport real de `electronic_invoice_delivery` en les superfícies revisades.** El DDL defineix el contracte, però no es representa com a servei actual acreditat.

```plantuml
@startuml
title UC123 P07 FINAL - Lliurament auditat i reintent sense reemissio
start
:Carregar UUID_FACTURA, document custodiat i preferencia UC032;
:Validar persona receptora/representant, format, versio, canal i adreça;
if (Falten autorizacions o format/canal?) then (Si)
 :Guardar sol.licitud pendent de validacio;
 stop
endif
:Crear o reutilitzar comanda idempotent electronic_invoice_delivery;
:Enviar el mateix document pel canal acordat;
:Registrar resposta, nombre d'intents i evidencia;
if (Hi ha prova suficient de lliurament segons canal?) then (Si)
 :Desar DELIVERED_AT i prova custodiada;
else (No)
 if (Error temporal?) then (Si)
  :Programar retry del document existent;
 else (No)
  :Registrar rebuig/error i incidencia per revisio;
 endif
endif
:Mostrar lliurament independent de cobrament i AEAT;
stop
@enduml
```

## Matriu de cobertura

| Superfície | ACTUAL acreditat | FINAL |
| --- | --- | --- |
| P01–P02 | Selecció/creació de factura abans de cobrar, endpoint amb nom «Electrònica» | Creació fiscal separada de preferència/lliurament |
| P03 | Consulta, vista i descàrrega temporal | Consulta autoritzada i estats diferents |
| P04 | Edició de dades de factura llegada | Preferència UC032 / correcció fiscal si escau |
| P05 | PDF de previsualització / descàrrega | PDF vs format electrònic estructurat |
| P06 | `E_FACT=0` inicial i metadada/hash `factura_documents` | Adaptador, validació i storage comprovats |
| P07 | **No hi ha servei de transport acreditat** | Comanda/intent/prova/retry per canal |

**DOC:** 13 activitats PlantUML (P01–P06 ACTUAL/FINAL, P07 només FINAL). **IMP:** UI llegada i metadades existents, procés integral UC123 no acreditat. **TEST:** proves de lliurament no executades. **PRODUCCIÓ:** no verificada.
