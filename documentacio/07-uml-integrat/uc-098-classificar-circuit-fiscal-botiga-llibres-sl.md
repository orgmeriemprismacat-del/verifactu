# UC-98 · Classificar el circuit fiscal de la botiga de llibres / SL

**Objectiu original:** decidir **abans d'implementar** titular del SIF, emissor, sèries i dades separades respecte de PrisMa. **Estat [PENDENT/BLOQUEJANT].** La fitxa no atribueix a una entitat un règim tributari concret sense dades d'operació i validació fiscal; constata que **el codi PHP actual no és un SIF multiemissor complet**.

## Evidència i límits reals

`sif/config/sif.php` defineix un sol bloc `issuer.nif/name` per entorn i les sèries `A/R` com a valors de configuració. `InvoicePayloadValidator::validate()` només admet sèries `A/R` i exigeix receptor, totals i línies, **sense un identificador d'emissor per línia ni una decisió de titular jurídic de la botiga**. `InvoiceRepository::insertInvoice()` escriu `BILLING_*` del receptor, `IVA_REGIM/PCT/IMPORT` del payload i `SOURCE_CHANNEL`; no crea una separació d'emissor per venda ni selecciona una cadena fiscal independent per SL. `LegacyCourseInvoicePayloadBuilder` fixa `iva_regim=EXEMPT` en el cas de cursos que construeix; aquest valor **no autoritza aplicar el mateix règim als llibres**.

`HistoricalInvoicePayloadBuilder` pot importar documents `NO_VERIFACTU`, però no transforma una factura antiga de SL en factura nova del SIF ni demostra que totes les entitats puguin compartir la mateixa seqüència/certificat.

| Qüestió específica | Decisió pendent, sense inventar resposta |
| --- | --- |
| Qui fa la venda de llibres? | Identificar empresa/associació que formalitza cada venda, titular del TPV, receptor dels diners, responsable del lliurament i emissor jurídic del document. Un logo o domini comercial comú no acredita que l'emissor sigui l'Associació. |
| És el mateix SIF o un altre? | Aprovar partició per emissor/instal·lació, sèries, numeració, cadena, certificat, credencials, BD, declaració i custòdia. L'esquema actual centralitza `fiscal_chain_state` amb fila ID=1 i `FiscalSequenceRepository` per sèrie/any, **no** per emissor. |
| Línies amb curs i llibre | UC-88 exigeix classificar emissor i tributació per servei abans de decidir agrupació; una sola cistella o cobrament bancari **no implica** factura única de dues entitats. |
| Impost/règim del llibre | Fixar per producte/operació, amb responsable fiscal, la base, tipus, quota i supòsit aplicable; **no** copiar `EXEMPT/0.00` del builder de formació ni inventar un percentatge legal sense verificar-lo. |
| TPV i devolucions | Un banc/DS_ORDER pot tenir relacions amb diversos serveis, però els ingressos reals i assignacions han d'estar vinculats a l'emissor titular acreditat. **No duplicar `CHARGE`** per comptabilitzar dues factures. |
| Històric | UC-97 conserva l'emissor real de factures anteriors i `NO_VERIFACTU`; no barrejar números iguals d'entitats distintes. |

### Flux de decisió abans del desenvolupament

1. Inventariar botiga web, TPV, contractes, entitat venedora, emissor de cada producte, factures històriques i codi de checkout/callback **actiu en producció**. Si no es pot verificar un punt d'entrada, mantenir-lo com a no acreditat.
2. L'àrea fiscal aprova una **matriu per producte i entitat** (titular, tributació, receptor, numeració i responsable de declaració) i el tractament de cistelles mixtes. Documentar explícitament els casos amb devolucions o pagament únic.
3. Arquitectura defineix a partir d'aquesta decisió si són necessàries instàncies/configuracions/seqüències/cadenes diferenciades o un multiemissor segur; **no** reutilitzar sense més `A/R`, `fiscal_chain_state.ID=1` i l'emissor per defecte.
4. Preparar proves de numeració, callbacks duplicats, factures mixtes, exportació/auditoria per entitat i reconciliació de banc/SL/llegat; gate UC-39/46 per entorn i artefacte real.
5. Fins a completar classificació i implementació, **no dirigir vendes de SL al builder de curs de l'Associació** ni certificar que la botiga està incorporada al SIF.

**Proves pendents:** curs Associació + llibre SL en cistella única, dues factures A2026/000001 amb emissors diferents, pagament conjunt, retorn parcial llibre, certificat d'una entitat en l'altra, llibre amb impostos no exempts, històric SL i prova d'accés d'un alumne a document de tercers.

### Matriu d'emissor abans de la cistella mixta i tall de numeració

**Límit verificat en les tres peces de codi.** `sif/config/sif.php` exposa **un únic** `issuer.nif/name` per entorn; `FiscalSequenceRepository::next()` bloqueja `fiscal_sequence` per **`TIPUS_SERIE + ANY_FACT`**, sense paràmetre d'emissor; `InvoiceRepository::lockChainState()` consulta **`fiscal_chain_state.ID=1`**, compartit per totes les factures d'aquella instal·lació. `InvoiceRepository::createInvoiceGraph()` genera el número a partir de sèrie/any/seq i insereix un registre d'alta per factura, però el payload general no selecciona un emissor jurídic diferent **per venda**. Una columna de receptor `BILLING_NIF_CIF` no substitueix la identitat del venedor: canviar el CIF del destinatari o la sèrie `A/R` no implementa un circuit de SL.

**Fitxa de decisió per cada línia de la cistella.** Abans de dirigir un article al SIF, confirmar **entitat que ven**, producte/prestació, emissor del document, titular del compte receptor, evidència de cobrament, règim/impost aplicable aprovat per l'àrea fiscal, destinatari i codi de producte real. Si hi ha un curs de PrisMa i un llibre de botiga en la mateixa cistella, identificar **per línia** si els serveis provenen o no del mateix emissor; UC-88 decideix aleshores documents i assignacions del **moviment extern real**. `LegacyCourseInvoicePayloadBuilder` fixa `iva_regim=EXEMPT` als cursos del seu circuit; **aquest codi no classifica** la venda de llibres, ni els seus percentatges, ni la identitat fiscal de la SL. La fitxa tampoc determina aquests valors sense una decisió documentada sobre cada operació.

**Dues sortides possibles que no es poden confondre amb un canvi de paràmetre.** Si es decideixen **instal·lacions independents**, cal acreditar a cada una configuració/BD, numeració, cadena i registre, cua/certificat, documents, backups i permisos, així com el contracte de conciliació d'un possible pagament conjunt; el codi actual del SIF de l'Associació no ha d'escriure una factura atribuïda a la SL. Si es decideix una **arquitectura multiemissor**, són canvis de model i repositoris encara pendents: `issuer_id` coherent a factura, seqüència, cadena, transport, evidències, documents i control d'accés; un `WHERE ID=1` no separa cadenes per emissor. La classificació aprovada ha de precedir qualsevol adaptació; no donar cap opció per desplegada només perquè el PHP pot rebre un CIF a `billing`.

**Històric i prova de tall.** Les factures antigues que provinguen de l'Associació o de la SL conserven emissor/origen en l'inventari UC-97 i estat `NO_VERIFACTU` si s'importen com a històric. Dos documents amb el mateix `NUM_VISIBLE` però emissor diferent no són la mateixa factura; cap importació ni exportació pot atribuir-los l'emissor SIF actiu per defecte. Abans de producció, contrastar la ruta web i callback de la botiga **que s'executen realment**, els pagaments del TPV, l'emissor de cada document i la numeració separada amb proves de cistella mixta i devolució parcial.

### Proves de separació d'emissor (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| EM-98-01 | Configuració actual només amb un `issuer` i cistella Associació + SL | No facturar tots dos sota el mateix titular per omissió; decisió d'emissor per línia. |
| EM-98-02 | Dues entitats intenten fer servir `A2026/000001` a la mateixa instància | Dues identitats fiscals/documentals diferenciades segons arquitectura aprovada; no sobreescriptura o fusió. |
| EM-98-03 | Llibre passa pel builder de curs amb `EXEMPT` | Bloquejar reús de classificació sense decisió fiscal específica del producte. |
| EM-98-04 | Un pagament TPV conjunt dona lloc a dues factures legítimes | Ingrés extern comptat una sola vegada i atribució real a documents/emissors corresponents. |
| EM-98-05 | Factura antiga SL importada al SIF de PrisMa | Inventari històric amb emissor acreditat; cap alta AEAT retroactiva ni emissor per defecte. |
| EM-98-06 | Codi de botiga al GitHub sense accés confirmat al runtime | Tall i integració productius marcats com a no verificats, no «botiga incorporada». |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Responsable fiscal" as F
actor "Responsable tècnica" as T
rectangle "Botiga/SL · classificació prèvia" {
 usecase "UC-98\nClassificar circuit fiscal de llibres" as Main
 usecase "Identificar venedor, TPV i entitat emissora" as Entity
 usecase "Aprovar tributació per servei/entitat" as Tax
 usecase "Separar cadenes/sèries i dades si escau" as Separate
 usecase "UC-88\nDecidir cistella mixta" as Basket
}
F --> Main
T --> Separate
Main ..> Entity : <<include>>
Main ..> Tax : <<include>>
Main ..> Separate : <<include>>
Basket ..> Main : <<extend>> (curs i llibre)
@enduml
```

## UML de classes

```mermaid
classDiagram
class BookstoreFiscalCircuitClassifier {
 <<DISSENY: decisió i servei no acreditats>>
 +classify(product,merchant,contract) route
 +checkMixedBasket(items) decision
}
class IssuerRoutingRegistry {
 <<DISSENY: separació emissor/instal·lació pendent>>
 +route(legalEntity,product) instance
}
class InvoicePayloadValidator {
 <<PHP existent: sèries A/R i receptor>>
 +validate(payload) array
}
class InvoiceRepository {
 <<PHP existent: una cadena fiscal central>>
 +createInvoiceGraph(db,payload,seq,chainState) array
}
class HistoricalInvoicePayloadBuilder {
 <<PHP existent: històric NO_VERIFACTU>>
 +build(input) array
}
BookstoreFiscalCircuitClassifier --> IssuerRoutingRegistry : titular jurídic
BookstoreFiscalCircuitClassifier ..> InvoicePayloadValidator : no decideix emissor
IssuerRoutingRegistry ..> InvoiceRepository : instància segura pendent
HistoricalInvoicePayloadBuilder ..> BookstoreFiscalCircuitClassifier : històric no es reemet
```

## UML de seqüència — cistella conjunta amb dos possibles emissors

```mermaid
sequenceDiagram
actor F as Responsable fiscal
participant S as BookstoreFiscalCircuitClassifier [DISSENY]
participant C as Cistella curs + llibre
participant R as IssuerRoutingRegistry [DISSENY]
participant I as InvoiceService existent [PHP]
participant P as Moviment bancari/assignació [pendent]
F->>S: Classificar cistella de dos productes
S->>C: Consultar contractant, TPV, titular i règim per línia
S->>R: Resoldre emissor de curs i emissor de llibre
alt Emissor o tributació del llibre no acreditats
 R-->>S: UNKNOWN
 S-->>F: Bloquejar integració fiscal de botiga
else Dos emissors verificats
 R-->>S: Rutes fiscals separades, numeracions diferenciades
 S-->>F: Dues factures a circuits aprovats
 F->>P: Atribuir ingrés real únic sense duplicar CHARGE [pendent]
else Emissor únic i agrupació aprovada
 S-->>F: Una factura multiconcepte amb règim per línia validat
 F->>I: issueInvoice(payload aprovat en entorn habilitat)
end
Note over S,I: El SIF actual no acredita multiemissor ni tributació automàtica de llibres.
```

## Traçabilitat

[UC-98 original](../06-fitxes-funcionals/uc-098.md) · [UC-97 històric](uc-097-consultar-historic-associacio-sl.md) · [UC-88 agrupació](uc-088-decidir-agrupacio-linies-factura-multiconcepte.md) · [UC-39 gate](uc-039-proves-gate-go-no-go.md) · [Configuració d'emissor](../../sif/config/sif.php) · [InvoicePayloadValidator](../../sif/src/Service/InvoicePayloadValidator.php) · [InvoiceRepository](../../sif/src/Repository/InvoiceRepository.php) · [HistoricalInvoicePayloadBuilder](../../sif/src/Service/HistoricalInvoicePayloadBuilder.php).
