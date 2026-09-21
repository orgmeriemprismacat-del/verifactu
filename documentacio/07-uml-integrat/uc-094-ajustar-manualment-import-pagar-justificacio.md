# UC-94 · Ajustar manualment l'import a pagar amb justificació

**Objectiu original:** registrar import anterior/nou, motiu i aprovador a `operational_event`; si existeix una factura emesa, **impedir-ne la mutació directa**. **Estat [DISSENY/BLOQUEJANT]** del flux complet de gestió: hi ha un writer genèric d'events i serveis de facturació, però no un controlador acreditat que autoritzi ajustos, resolgui el snapshot TPV i decideixi el document corrector.

## Evidència i riscos del PHP real

`OperationalEventRepository::append()` escriu `OPERATION_TYPE, FISCAL_IMPACT, ECONOMIC_IMPACT, STATUS, REASON_CODE`, actor, correlació i hashes dels snapshots abans/després. **No fa aprovació de negoci, bloqueig de concurrència ni deduplicació idempotent per ajust**. `LegacyCourseInvoicePayloadBuilder::lineAmounts()` comprova que base–descompte sigui coherent amb total del snapshot per a un curs, però no valida qui ha autoritzat un preu excepcional. `InvoiceRepository::insertInvoice()` desa `TOTAL` i `DESC_IMPORT` de la factura, i el registre encadenat reflecteix el payload emès. `PaymentService::registerPayment()` reusa una clau idempotent sense comparar el payload nou, de manera que canviar l'import sense coordinar clau i referència bancària pot ocultar una divergència.

| Instant de l'ajust | Contracte |
| --- | --- |
| Oferta sense factura ni intent Redsys | Gestió identifica `ID_INSC`, producte, base, descompte ja aplicat, preu nou i causa **diferent de la categoria genèrica «manual»**. Validar cèntims, límits/autorització i impacte per línia; versionar oferta abans de confirmar. |
| Intenció `DS_ORDER` pendent | No reusar `DS_ORDER` si s'ha alterat l'import o el snapshot. Decidir caducitat de la intenció i crear-ne una de nova amb quantia confirmada. No generar `REFUND` perquè no hi ha ingrés. |
| Factura emesa sense cobrar | El deute pendent deriva de factura/assignacions reals; no canviar `factura.TOTAL` o `A_PAGAR` llegat com a substitut de correcció fiscal. UC-74 classifica el document nou, si escau. |
| Factura ja pagada parcialment o totalment | Conservar `UUID_PAYMENT` i les transferències reals. Si l'ajust crea excés: UC-104/28/29/105 decideixen excés no assignat, refund extern, saldo o atribució **per inscrit**; una reducció de preu no demostra sortida bancària. |
| Grup/pack | Documentar el canvi sobre `ID_INSC` i línia concreta, mantenint suma per factura i pagador legítim; no dividir un `CHARGE` conjunt entre persones sense traça d'atribució quantitativa. |

### Flux objectiu

1. El formulari recull **abans/després** amb import original congelat, nou import, motivació i prova, actor proponent i aprovador autoritzat; comprova versió del snapshot i existència de factura/pagament real.
2. El servei de decisió **pendent** calcula diferència en cèntims per línia i verifica que no és una edició del document emès. Rebutja mateixa `REQUEST_ID` amb quanties contradictòries i intents paral·lels de canviar la mateixa oferta.
3. Registra decisió amb `OperationalEventRepository` i `FISCAL_IMPACT/ECONOMIC_IMPACT` classificats. L'ús del writer existeix, però **la seva crida des de la intranet d'ajustos no està acreditada**.
4. Sense factura, publicar oferta/intenció noves; amb factura, derivar a UC-74 per corrector justificat; amb diner extern, executar una única via de saldo/retorn/traspàs i conservar el `CHARGE` original.
5. Comparar SIF amb llegat/estat acadèmic i informar de resultats parcials sense repetir emissió/cobrament per un error de sincronització.

**Proves:** ajust de 80 € a 65 € abans de TPV, intenció antiga de 80 € amb nova oferta de 65 €, factura de 80 € amb 30 € pagats, transferència real de 80 € i descompte tardà de 15 €, empresa de grup amb dos participants, dues aprovacions concurrents, event repetit amb payload diferent.

**Pendents:** política d'import excepcional, rols, bloqueig/idempotència de negoci, writer d'ofertes versionades, classificador fiscal i traça monetària individual `enrollment_fund_movement` (**proposta, no implementada**).

### Modal llegat de pagament: separació entre ajustar un deute i registrar un ingrés

**Punt d'escriptura concret que s'ha de substituir o limitar.** La fitxa d'alumne `/alumnes/mostrar-alumne/` obre `guardarDadesPagament_modalsresultatCerca()`, capaç de modificar directament `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `IDPAG`, `FRACCIONAT`, `FRACCIO` i `FACTURA_RELACIONADA`, juntament amb camps de reclamació. El procediment del projecte **decideix expressament** que aquest bloc no pot continuar sent un editor silenciós de dades econòmiques/fiscals. L'ajust `A_PAGAR` ha d'obrir **una comanda d'ajust amb motiu i impacte fiscal**, mentre que l'ingrés real es tramita per UC-02/22/24 i la relació fiscal per UC-44/74. No copiar un `PAGAMENT` editat com si fos justificació de banc.

**Comparar quatre imports, no un camp únic.** En la previsualització mostrar (1) import de **prestació original congelat** per línia, (2) descomptes ja aprovats, (3) cobrament real extern i atribucions `payment_transaction/payment_allocation`, i (4) saldo exigible i proposta d'ajust. El valor `A_PAGAR` llegat pot ser dada operativa afectada per fracció o canvi de curs i no ha d'omplir per defecte `factura.TOTAL`. En pack o grup, expressar `ID_INSC` i import afectat: un sol `IDPAG` no identifica quin participant rep el benefici ni a qui correspon una eventual devolució.

**Segons l'estat de la factura.** Si només hi ha oferta i cap `DS_ORDER`, aprovar nova base/import i congelar UC-112. Si ja existeix una intenció, no manipular-ne `EXPECTED_AMOUNT/SNAPSHOT_JSON`; obrir-ne una de nova quan correspongui i preservar qualsevol callback anterior. Amb factura real pendent, disminuir `A_PAGAR` **no** esborra l'obligació fiscal: UC-74 classifica la correcció i només llavors es recalcula el saldo. Amb factura cobrada, el menor preu aprovat **no prova una sortida bancària**; UC-104/28/29/105 decideixen excessos, retorn o saldo amb el titular i l'ingrés acreditats.

**Traça i recuperació.** El writer genèric `OperationalEventRepository::append()` desa snapshots i motiu, però la documentació no acredita cap connexió de la pantalla antiga al writer ni un aprovador en línia. La comanda futura porta `ID_INSC/UUID_OPERATION`, versió d'oferta, import antic/nou, causa, actor, decisió autoritzada i identificador de petició. Si l'UPDATE de compatibilitat falla després de confirmar SIF, recuperar el mateix event i tornar a executar només el resum llegat, **no** una segona rectificativa, un segon `CHARGE` o una segona devolució.

### Proves d'ajust separat del moviment monetari (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| AJ-94-01 | Operador modifica `A_PAGAR` sense ingrés extern | Ajust justificat i auditat segons estat; cap `CHARGE` fictici. |
| AJ-94-02 | Canvi del pendent llegat quan existeix factura real emesa | Conservar `factura.TOTAL` i derivar impacte a UC-74; no editar document fiscal. |
| AJ-94-03 | Pack/grup amb `IDPAG` compartit i ajust d'un participant | Línia i `ID_INSC` identificats, no distribució de l'ajust al total del grup. |
| AJ-94-04 | Intenció TPV antiga amb import original i nova oferta de menor import | Ordre antiga immutable i callback tardà conciliat si arriba; no atribuir-lo automàticament a l'oferta nova. |
| AJ-94-05 | Factura pagada i reducció aprovada de preu | Conservar `UUID_PAYMENT`; `REFUND` només quan hi hagi sortida bancària real. |
| AJ-94-06 | SIF confirma ajust/document, sincronització `A_PAGAR` falla | Reintentar només sincronització de compatibilitat, amb el mateix event i UUIDs. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió" as G
actor "Aprovador autoritzat" as A
rectangle "SIF · ajust manual d'import" {
 usecase "UC-94\nAjustar import amb causa" as Main
 usecase "Comprovar estat d'oferta/factura/ingrés" as Check
 usecase "Comparar imports per línia i inscrit" as Delta
 usecase "Registrar abans/després i aprovador" as Audit
 usecase "UC-74\nDecidir document corrector" as Fiscal
 usecase "UC-28/29/105\nResoldre fons reals" as Money
}
G --> Main
A --> Audit
Main ..> Check : <<include>>
Main ..> Delta : <<include>>
Main ..> Audit : <<include>>
Fiscal ..> Main : <<extend>> (factura emesa)
Money ..> Main : <<extend>> (ingrés amb diferència)
@enduml
```

## UML de classes

```mermaid
classDiagram
class ManualPriceAdjustmentService {
 <<DISSENY: no acreditat>>
 +preview(operation,proposedAmount) impact
 +approve(requestId,actor) decision
}
class OperationalEventRepository {
 <<PHP existent: writer genèric>>
 +append(db,event) string
}
class LegacyCourseInvoicePayloadBuilder {
 <<PHP existent: aritmètica curs al primer snapshot>>
 +build(snapshot) array
}
class ManualRectificationService {
 <<PHP existent: document R després de decisió>>
 +issueByUuid(sifDb,uuidFactura,input) array
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: import atribuït a ID_INSC>>
 +append(db,movement) result
}
ManualPriceAdjustmentService --> OperationalEventRepository : event amb motiu [integració pendent]
ManualPriceAdjustmentService ..> LegacyCourseInvoicePayloadBuilder : només nova emissió
ManualPriceAdjustmentService ..> ManualRectificationService : si corrector aprovat
ManualPriceAdjustmentService ..> EnrollmentFundMovementRepository : atribució pendent
```

## UML de seqüència — factura cobrada i preu ajustat

```mermaid
sequenceDiagram
actor G as Gestió
participant S as ManualPriceAdjustmentService [DISSENY]
participant F as factura i factura_linia [SQL]
participant P as payment_transaction/allocation [SQL]
participant E as OperationalEventRepository [PHP]
participant C as Classificació UC-74 [DISSENY]
participant M as Resolució d'excés UC-104/28/29/105
G->>S: Proposar import nou amb ID_INSC i justificació
S->>F: Llegir import original i estat emès
S->>P: Llegir CHARGE real i pagador
S-->>G: Diferència per línia, fiscal i diner
G->>S: Aprovar amb REQUEST_ID i rol verificats
S->>E: append(abans,després,motiu,actor) [integració pendent]
S->>C: Classificar impacte sobre factura original
C-->>S: Via fiscal autoritzada o cap efecte
opt Excés econòmic acreditat
 S->>M: Decidir saldo, refund real o atribució interna
 M-->>S: Resultat o incidència pendent
end
S-->>G: Estat diferenciat; sense UPDATE del TOTAL original
```

## Traçabilitat

[UC-94 original](../06-fitxes-funcionals/uc-094.md) · [UC-90 descompte tardà](uc-090-descompte-validat-despres-compra.md) · [UC-74 classificador](uc-074-classificar-correccio-fiscal.md) · [UC-104 excés](uc-104-gestionar-exces-cobrament.md) · [UC-105 reassignació](uc-105-reassignar-repartir-pagament.md) · [OperationalEventRepository](../../sif/src/Repository/OperationalEventRepository.php) · [LegacyCourseInvoicePayloadBuilder](../../sif/src/Service/LegacyCourseInvoicePayloadBuilder.php) · [PaymentService](../../sif/src/Service/PaymentService.php) · [Moviments d'inscripció](00-revisio-moviments-inscripcions.md).
