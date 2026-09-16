# Auditoria ▶ fonts AEAT VERI*FACTU

Generat: 2026-06-15

Objectiu: revisar els documents AEAT aportats sobre VERI*FACTU i dimensionar correctament els buits de Trello/documentacio. Aquesta auditoria corregeix el criteri anterior: **32 targetes no resolen el problema**; nomes cobrien el primer xoc entre el flux antic de pagament i el flux SIF actual.

## Fonts revisades

| Fitxer | Estat | Observacions |
|---|---|---|
| `Validaciones_Errores_Veri-Factu.pdf` | Revisat | 24 pagines. Versio extreta 1.1.2. Validacions, estats i errors. |
| `Veri-Factu_Descripcion_SWeb.pdf` | Revisat | 100 pagines. Versio extreta 1.0.3. Serveis web, WSDL/SOAP, alta/anulacio, resposta i consulta. |
| `Veri-Factu_especificaciones_huella_hash_registros.pdf` | Revisat | 13 pagines. Algoritme SHA-256 i camps exactes per huella/hash. |
| `Contingut_del_Registre_de_facturació_d_alta..pdf` | Revisat | 3 pagines. Resum de contingut minim del registre d'alta. |
| `DetalleEspecificacTecnCodigoQRfactura.pdf` | Revisat | 34 pagines. QR, URL de coteig/remissio, entorns, parametres i errors. |
| `EspecTecGenerFirmaElectRfact.pdf` | Revisat | 15 pagines. Firma XAdES per registres de facturacio i d'event. |
| `AnexosEjemplosFirmaRegFact.zip` | Revisat | 2 XML: registre sense firmar i registre firmat XAdES-EPES. |
| `errores.properties` | Revisat | 239 codis AEAT en codificacio `cp1252`. |
| `Preguntes_freqüents__PMF_.pdf` | Revisat | 128 pagines. PMF locals del 12/09/2025. |
| `Procediments_de_facturació.pdf` | Revisat | 15 pagines. Tipus de factura, rectificatives, anul.lacions i substitucions. |
| `Información periódica Orden ETD_699_2020...pdf` | Fora d'abast | Document bancari/personal. No es considera font AEAT VERI*FACTU. |

## Comprovacio de vigencia

La seu electronica de l'AEAT mostra les pagines tecniques de VERI*FACTU actualitzades a **26/03/2026**. Les PMF oficials indiquen actualitzacio a **05/12/2025**. Per tant:

- les copies locals son valides com a base de treball;
- abans de programar o signar versio productiva cal descarregar/validar els ultims PDF, XSD, WSDL, dissenys de registre i PMF de la seu AEAT;
- el Trello ha de tenir targetes especifiques de control de versio normativa, no nomes targetes de desenvolupament.

## Blocs normatius detectats

### 1. Governanca de fonts AEAT

Cal controlar:

- versio de cada document tecnic;
- data de revisio contra seu AEAT;
- WSDL vigent;
- XSD/esquemes vigents;
- dissenys de registre vigents;
- codis d'error vigents;
- PMF vigents;
- declaracio responsable vinculada a versio del SIF.

### 2. Modalitat VERI*FACTU vs no VERI*FACTU

El projecte esta orientat a modalitat `VERI*FACTU`, pero cal que les targetes separin:

- remissio voluntaria en linia;
- resposta sota requeriment, si algun dia aplica;
- obligacions que baixen en modalitat VERI*FACTU;
- obligacions que nomes serien completes en no VERI*FACTU, com firma obligatoria de registres i registre d'events reforcat;
- permanencia minima de l'opcio VERI*FACTU fins final d'any natural un cop iniciada.

### 3. Separacio per obligat tributari

La revisio confirma que no es pot tractar el sistema com un sol flux indiferenciat:

- un lot AEAT no ha de barrejar obligats tributaris;
- si el mateix SIF dona servei a diverses empreses o entitats, cal separar enviaments/configuracio;
- el punt de botiga/SL/Associacio no es un detall operatiu: afecta l'arquitectura fiscal.

### 4. Registre de facturacio d'alta

El registre d'alta exigeix dades fiscals completes i congelades:

- emissor;
- destinatari quan correspongui;
- expedidor material si factura tercer o destinatari;
- numero i serie;
- data d'expedicio;
- data d'operacio o pagament anticipat si difereix;
- tipus de factura;
- rectificativa i factures rectificades si aplica;
- substitucio de simplificades si aplica;
- descripcio general;
- import total;
- regim fiscal aplicat;
- inversio del subjecte passiu si aplica;
- base, tipus, quota IVA i recarrec equivalencia quan apliqui;
- no-subjeccio i causa quan apliqui;
- referencia al registre anterior i part de la huella/hash anterior;
- identificacio del sistema i productor;
- data/hora/minut/segon de generacio del registre.

### 5. Anulacio, subsanacio i rectificacio

Cal separar tres coses que en targetes antigues apareixen barrejades:

- anulacio de registre quan una factura no procedeix o hi ha error d'identificacio/operacio inexistent;
- factura rectificativa quan la factura original existeix i s'ha de corregir economicament o juridicament;
- registre substitutiu/subsanacio quan cal corregir un registre amb error sense alterar el registre anterior.

Impacte: Trello necessita targetes diferents per `RegistroAnulacion`, `FacturaRectificativa`, `Subsanacion`, `RechazoPrevio` i `TipoRectificativa`.

### 6. Hash AEAT

El hash AEAT no es el hash intern actual de JSON.

Camps detectats per alta:

- `IDEmisorFactura`;
- `NumSerieFactura`;
- `FechaExpedicionFactura`;
- `TipoFactura`;
- `CuotaTotal`;
- `ImporteTotal`;
- `Huella` del registre anterior;
- `FechaHoraHusoGenRegistro`.

Camps detectats per anulacio:

- `IDEmisorFacturaAnulada`;
- `NumSerieFacturaAnulada`;
- `FechaExpedicionFacturaAnulada`;
- `Huella`;
- `FechaHoraHusoGenRegistro`.

Risc actual: `sif/src/Domain/HashCalculator.php` calcula SHA-256 sobre JSON canonic intern. Aixo pot ser valid com a hash intern, pero no equival necessariament a la huella AEAT.

### 7. XML, XSD i exemples AEAT

Els XML del ZIP confirmen camps que han de tenir correspondencia clara en el model intern:

- `IDVersion`;
- `IDFactura`;
- `IDEmisorFactura`;
- `NumSerieFactura`;
- `FechaExpedicionFactura`;
- `NombreRazonEmisor`;
- `Subsanacion`;
- `RechazoPrevio`;
- `TipoFactura`;
- `TipoRectificativa`;
- `FacturasRectificadas`;
- `Destinatarios`;
- `Desglose`;
- `DetalleDesglose`;
- `ClaveRegimen`;
- `CalificacionOperacion`;
- `TipoImpositivo`;
- `BaseImponibleOimporteNoSujeto`;
- `CuotaRepercutida`;
- `CuotaTotal`;
- `ImporteTotal`;
- `Encadenamiento`;
- `SistemaInformatico`;
- `FechaHoraHusoGenRegistro`;
- `TipoHuella`.

La versio firmada afegeix estructura XAdES-EPES.

### 8. Serveis web AEAT

Calen targetes separades per:

- client SOAP;
- WSDL de proves;
- WSDL de produccio;
- endpoints de remissio voluntaria;
- alta de registres;
- anulacio de registres;
- consulta de registres presentats en modalitat VERI*FACTU;
- cabecera/obligat emissio/representant;
- remissio voluntaria vs requeriment;
- control de flux;
- tractament de cadenes XML;
- valors numerics;
- escapament XML;
- persistencia de request/response.

Risc actual: hi ha `fiscal_queue`, `PAYLOAD_JSON` i `AEAT_RESPONSE_JSON`, pero no es veu encara un client AEAT complet ni una capa XML/XSD/SOAP final.

### 9. Estats i errors AEAT

La resposta AEAT s'ha de modelar en dos nivells:

- estat global de la peticio: acceptacio completa, acceptacio parcial o rebuig complet;
- estat per registre: acceptat, acceptat amb errors o rebutjat.

Cal tractar de manera diferent:

- rebuig estructural de tot el XML;
- error sintactic de cabecera;
- error de registre individual;
- acceptat amb errors;
- errors admissibles/no admissibles segons modalitat;
- ausencia de resposta i reintent.

`errores.properties` conte 239 codis, de 1100 a 4140, incloent errors de XML, cabecera, NIF, certificat, GET no permes, `RegistroAlta`, `RegistroAnulacion`, huella anterior, dates, imports i validacions fiscals.

### 10. QR i URL de coteig/remissio

El QR no es nomes una imatge:

- ha de generar URL valida;
- ha de diferenciar entorn de proves i produccio;
- ha de diferenciar factura verificable i no verificable;
- ha de fer URL encoding;
- parametres principals detectats: `nif`, `numserie`, `fecha`, `importe`;
- ha de controlar resposta HTML/JSON quan hi ha parametre opcional;
- ha de tractar errors de falta de parametres, format, NIF/NIE i altres codis;
- ha de quedar integrat en PDF i accesible pel receptor.

### 11. Firma electronica

En modalitat VERI*FACTU ordinaria no sembla obligatoria la firma electronica XAdES dels registres remesos, pero:

- el document tecnic de firma existeix i s'ha de controlar;
- pot aplicar en no VERI*FACTU o requeriments/altres escenaris;
- els exemples mostren XAdES enveloped i classe EPES;
- cal decidir si el projecte ho deixa fora de la versio 1.0 o com a bloc futur condicional.

### 12. Declaracio responsable i producte SIF

Calen targetes per:

- dades del productor;
- identificacio del sistema;
- nom i versio;
- components;
- modalitat;
- disponibilitat dins del sistema;
- evidencia de compliment;
- relacio amb releases;
- estat no signable fins proves completes.

### 13. Procediments de facturacio

El document de procediments reforça que cal targetitzar:

- F1/F2/F3;
- R1/R2/R3/R4/R5;
- rectificativa per substitucio;
- rectificativa per diferencies;
- anulacio per factura improcedent;
- nova alta quan cal emetre factura correcta;
- data d'operacio original en rectificatives;
- factures simplificades i substitucio de simplificades;
- descomptes, devolucions, canvis d'import i resolucio d'operacions.

## Buits principals respecte Trello actual

No falta una unica targeta gran; falten capes senceres de targetes petites:

| Bloc | Estat actual | Risc |
|---|---|---|
| Versions AEAT/WSDL/XSD | Parcial | Implementar contra una versio no vigent. |
| Hash AEAT exacte | Parcial | Hash intern correcte tecnicament pero no equivalent a huella AEAT. |
| XML `RegistroAlta` | Parcial | No poder generar payload validable per XSD/AEAT. |
| XML `RegistroAnulacion` | Pendent/parcial | Barrejar anulacio amb rectificativa. |
| Subsanacio/rechazo previo | Pendent/parcial | No gestionar acceptat amb errors o registre substitutiu. |
| Estats AEAT globals/per registre | Parcial | Tractar acceptacio parcial o acceptat amb errors com exit/fallada simple. |
| Cataleg errors AEAT | Parcial | No classificar 239 codis ni accions operatives. |
| SOAP/WSDL/endpoints | Parcial | Tenir cua fiscal sense remissio real. |
| Consulta registres presentats | Pendent/parcial | No poder reconciliar amb AEAT en mode VERI*FACTU. |
| QR URL i errors | Parcial | Generar QR visual sense URL valida o sense encoding correcte. |
| Firma XAdES condicional | Parcial | No deixar clar que queda fora de VF ordinari o com a futur condicional. |
| Separacio obligat tributari | Parcial | Barrejar Associacio, SL i botiga en lots o configuracio fiscal. |
| Declaracio responsable | Parcial | Signar o publicar abans de proves i versions tancades. |
| Procediments de rectificativa/anulacio | Parcial | Fer servir un flux normal per casos que exigeixen registre diferent. |

## Estimacio realista de targetes que falten

Per aquesta capa AEAT, una descomposicio petita i util probablement no baixa de:

- 20-30 targetes de governanca i versions AEAT;
- 35-50 targetes de XML/RegistroAlta/RegistroAnulacion/subsanacio;
- 20-30 targetes de hash i encadenament AEAT exacte;
- 25-40 targetes de serveis web, WSDL, endpoints, certificats i cua;
- 25-35 targetes d'estats, errors i reintents;
- 15-25 targetes de QR/URL/coteig/PDF;
- 10-20 targetes de firma condicional i declaracio responsable;
- 20-30 targetes de proves i evidencies AEAT.

Total orientatiu: **170-260 targetes petites**, no 32.

## Ordre recomanat de treball

1. Control de versions AEAT i descarrega oficial vigent.
2. Matriu de camps interns vs `RegistroAlta`.
3. Matriu de camps interns vs `RegistroAnulacion`.
4. Hash AEAT exacte i proves amb exemples.
5. XML builder + validacio XSD.
6. Cua AEAT + client SOAP/WSDL.
7. Gestio de resposta global/per registre.
8. Cataleg `errores.properties` importat i classificat.
9. QR URL + PDF + resposta coteig.
10. Rectificatives/anulacions/subsanacions.
11. Declaracio responsable i evidencia go/no-go.
12. JSON Trello final.

## Conclusions

- La critica de Meriem es correcta: amb 32 targetes no es podia cobrir el problema.
- Les 32 targetes anteriors nomes marcaven el canvi conceptual del flux de pagament.
- La capa AEAT exigeix una passada propia, mes tecnica i normativa.
- No s'ha de generar el JSON final de Trello fins que aquesta capa estigui convertida en targetes petites.
