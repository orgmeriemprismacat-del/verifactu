# Registre de decisions

Aquest fitxer recull decisions importants del projecte. Cada xat nou l'ha de llegir abans de proposar canvis de criteri.

## 2026-05-19 - Crear projecte pont

Decisio:
Crear un projecte pont separat del xat antic, amb documentacio, copia del registre original i fitxers de control.

Motiu:
El xat antic supera el limit de context quan Codex intenta compactar-lo. Cal conservar la informacio sense dependre d'un unic fil de conversa.

Impacte:
Els futurs xats treballaran amb fitxers compartits, no amb memoria implicita del xat antic.

## 2026-05-19 - Fer servir un xat pont abans dels xats especialitzats

Decisio:
Crear un xat pont per revisar el xat antic per blocs i completar la documentacio abans d'obrir xats especialitzats.

Motiu:
La usuaria sospita que va donar informacio important al xat antic que no esta reflectida als documents.

Impacte:
El xat pont es responsable de recuperar, classificar i documentar informacio perduda o incompleta.

## 2026-05-19 - La memoria del projecte son els fitxers

Decisio:
Els documents de `00-control/` i `documentacio/` seran la font de veritat compartida entre xats.

Motiu:
Els xats llargs poden saturar el context. Els fitxers permeten continuar amb xats nous sense perdre continuitat.

Impacte:
Cada xat ha de llegir nomes els fitxers que necessita i actualitzar els fitxers de control abans de tancar.

## 2026-05-19 - Ordre inicial de revisio del xat antic

Decisio:
El xat pont revisara el xat antic per blocs en aquest ordre:

1. Context actual de PrisMa i canals reals.
2. Fluxos de facturacio i casos especials.
3. Pagaments, Redsys, callbacks i conciliacio.
4. Base de dades, hash chain, concurrencia i idempotencia.
5. Pantalles, permisos i operacio interna.
6. Compliment AEAT i declaracio responsable.
7. Correus, plantilles, PDF/QR i notificacions.
8. Proves, produccio, auditoria documental i governanca.

Motiu:
La primera cerca tematica del xat antic mostra que aquests blocs concentren la informacio operativa i tecnica amb mes risc de quedar dispersa: canals reals, facturacio, pagaments, BD/concurrencia, pantalles, compliment, comunicacions i posada en produccio.

Impacte:
El xat pont no intentara completar tota la documentacio de cop. Cada bloc es buscara al xat antic amb termes concrets, es comparara amb els documents existents i nomes s'actualitzaran els fitxers afectats.

## 2026-05-19 - Bloc 1 revisat: context actual de PrisMa

Decisio:
Incorporar als documents de context que el sistema actual es PHP/JavaScript sense framework principal, amb MySQL i diverses bases de dades, i que abans del SIF els canals web/ecommerce, TPV virtuals i intranet podien intervenir en la generacio de factures.

Motiu:
El xat antic contenia matisos importants sobre el funcionament real: TPV virtuals per tipus de venda, vendes manuals des de la intranet, factures generades quan hi ha pagament, PDF no sempre generat al moment, receptors diversos i volum variable fins a 2000 factures/dia.

Impacte:
La documentacio de context ja explica millor per que el SIF ha de centralitzar la decisio fiscal final i per que cal separar dades de client/contacte, dades fiscals, pagament i factura.

## 2026-05-19 - Bloc 2 revisat: fluxos de facturacio i casos especials

Decisio:
Completar els fluxos amb matisos recuperats del xat antic: `IDPAG` pot tenir diversos intents Redsys, una transferencia pot pagar diverses factures, una compensacio pot ser saldo o descompte, les factures abans de cobrament son factures reals si s'emeten, i no s'han d'utilitzar proformes fiscals ambigues.

Motiu:
El xat antic contenia detalls operatius que afecten idempotencia, rectificatives, devolucions, canvis de curs, saldos i incidencies. Sense aquests matisos, el SIF podria documentar be el cas ideal pero perdre casos reals de PrisMa.

Impacte:
El document de fluxos diferencia millor factura, pagament, compensacio, devolucio, factura abans de cobrament i document no fiscal. Les incidencies finals tambe contemplen pagaments fraccionats, devolucions pendents i factures abans de cobrament impagades.

## 2026-06-01 - Bloc 3 revisat: pagaments, Redsys i conciliacio

Decisio:
Completar la documentacio de pagaments amb detalls recuperats del xat antic: `realitzaPagamentAutomatic.php` rep parametres `GET` de context, desa `Ds_Order` a `web.factures.NUM_COMANDA`, actualitza inscripcions i envia correu intern, pero el SIF ha de validar signatura Redsys, deduplicar per `DS_ORDER`, registrar moviments a `payment_transaction` i decidir `issueInvoice()` o `registerPayment()`.

Motiu:
El xat antic contenia detalls concrets del codi actual, dels canals TPV i de la pantalla `Passar pagaments` que afecten la migracio a `pay.prisma.cat` i la conciliacio segura. Sense aquests detalls, el disseny podia cobrir el callback ideal pero perdre casos reals com packs, regals, grups, USOC, transferencies, fitxer TPV, factura abans de cobrament o factures d'empresa/responsable.

Impacte:
`pay.prisma.cat` queda documentat com a punt funcional de pagament, callback, conciliacio i panell SIF. Les URLs antigues poden iniciar o redirigir el flux, pero no ser font fiscal. Les relacions BD incorporen `redsys_notifications`, `DS_ORDER`, `IDPAG`, `FACTURA_RELACIONADA`, `payment_transaction` i `payment_allocation`.

## 2026-06-01 - Bloc 4 revisat: BD, hash chain i idempotencia

Decisio:
Completar el model BD amb detalls recuperats del xat antic: hi havia una BD fiscal parcial amb `errors_verifactu`, `factura`, `factura_log`, `factura_registres`, `reg_pagament`, `session_log`, `fiscal_queue` i `fiscal_sequence`; el model final ha de consolidar-ho en `InnoDB`, UUID complet, imports `DECIMAL`, `IDEMPOTENCY_KEY`, `FISCAL_ORDER` i permisos MySQL que impedeixin updates/deletes manuals sobre factures emeses.

Motiu:
El xat antic contenia decisions tecniques concretes sobre concurrencia i integritat: la numeracio visible per serie/any no es la cadena fiscal; la hash chain ha de ser global del SIF i s'ha d'encadenar per `FISCAL_ORDER`. Tambe hi havia deutes de migracio de l'esquema inicial, especialment `MyISAM`, `UUID varchar(12)` i imports `double`.

Impacte:
La documentacio diferencia millor esquema parcial existent i disseny final. El SIF queda definit com a BD fiscal transaccional, amb `fiscal_sequence` per numeracio, `fiscal_chain_state` per hash chain global, `fiscal_queue` amb payload de reintent, claus uniques idempotents i permisos BD alineats amb la immutabilitat fiscal.

## 2026-06-01 - Bloc 5 revisat: pantalles, permisos i operacio

Decisio:
Completar la documentacio de pantalles i permisos amb detalls recuperats del xat antic: la intranet actual usa `apartats.ROLS_VISUALITZAR`, `ROLS_EDITAR`, `ROLS_ENVIAR_MSG`, `usuaris.ROLS`, `consultaRolsEdiicio`, `consultaRolsUsuari` i `tePermisEdicio`; aquests permisos visuals s'han de mantenir com a ajuda d'interficie, pero les accions fiscals critiques s'han de validar sempre al servidor/SIF.

Motiu:
El xat antic contenia matisos humans i operatius que no poden quedar diluits: Isa pot donar suport i consultar, pero no es rol fiscal ordinari; Meriem, Adam i Pablo concentren les accions fiscals diaries; la morositat i les reclamacions tenen pantalles i fases especifiques, pero no rectifiquen factures automaticament.

Impacte:
El manual intern passa de llista pendent a regles operatives base. Les pantalles queden millor delimitades: Consulta - Modifica alumne inicia accions, Passar pagaments registra o concilia pagaments, Consulta - Edita - Anula factura es transforma en accions controlades, i l'apartat `VERI*FACTU` de la intranet mostra indicador i accessos sense substituir el panell SIF.

## 2026-06-01 - Bloc 6 revisat: compliment AEAT i declaracio responsable

Decisio:
Completar la documentacio de compliment AEAT amb criteris recuperats del xat antic i contrastats amb fonts oficials AEAT/BOE: la declaracio responsable actual es `0.1-BORRADOR` i no s'ha de signar; la primera versio productiva signable prevista es `1.0.0`; el sistema es documenta com a desenvolupament intern per a us propi d'Associacio PrisMa, amb Meriem com a responsable tecnica, funcional, documental i de desenvolupament, sense convertir-la per defecte en productora externa persona fisica.

Motiu:
El xat antic contenia decisions importants que podien quedar massa implicites: no cal signar el borrador, no cal comunicar l'opcio `VERI*FACTU` via model 036 segons FAQ AEAT vigent, el certificat digital es de l'entitat, la declaracio final ha d'esperar a una versio instal·lada i verificable, i els punts fiscals interpretatius s'han de marcar com a criteris interns pendents de validacio externa si no hi ha assessor fiscal.

Impacte:
`documentacio-sif-aeat.md`, `declaracio-responsable-sif-prisma.md`, el registre de versions i la checklist de produccio deixen mes clar que cal abans de produccio: abast normatiu confirmat, terminis aplicables, certificat/apoderament, PDF/QR/XML, proves, declaracio accessible dins del SIF i registre de versio activa. El proper bloc passa a ser correus, plantilles, PDF/QR i notificacions.

## 2026-06-01 - Bloc 7 revisat: correus, plantilles, PDF/QR i notificacions

Decisio:
Documentar que els correus del sistema es divideixen en tres grups: correus amb `Template`, correus directes en PHP i correus nous/tecnics del SIF. No es migraran tots els correus antics a `Template` de cop, pero qualsevol correu reprogramat per VERI*FACTU o que contingui URL de pagament, factura, PDF/QR o incidencia fiscal ha de quedar tipificat i condicionat a l'estat real del SIF.

Motiu:
El xat antic contenia detalls dispersos que podien provocar errors si no quedaven documentats: el correu intern de `realitzaPagamentAutomatic.php` nomes es diagnosi i no prova fiscal; una factura d'empresa pendent pot tenir URL de pagament propia; una URL individual s'ha de desactivar o substituir quan hi ha factura d'empresa/responsable; el PDF antic no s'ha de regenerar des de dades vives; el PDF/QR pot anar en cua; i els avisos interns s'han de distingir d'incidencies SIF reals.

Impacte:
`08-correus-i-plantilles.md`, `11-inventari-canvis-pendents.md`, `16-estat-final-pantalles.md` i `25-panell-sif-pay-prisma.md` deixen clar quan es pot enviar un correu de factura, quan cal adjuntar PDF, quan cal enllac segur, com tractar el PDF/QR pendent o fallit, i com usar `incidencia SIF`, `notificacio`, `avis` i `indicador`. El proper bloc passa a ser proves, produccio, auditoria documental i governanca.

## 2026-06-01 - Bloc 8 revisat: proves, produccio, auditoria documental i governanca

Decisio:
Documentar que el pas a produccio del SIF requereix un entorn de proves/preproduccio separat, paquet go/no-go, regressions critiques i expedient d'evidencies. Cap prova pot consumir numeracio productiva ni crear factura real abans de la decisio de produccio. Un cop emesa una factura productiva, no es fa rollback fiscal: es conserva i es corregeix amb registres posteriors si cal.

Motiu:
El xat antic contenia criteris importants que no podien quedar nomes com a conversa: el projecte necessita demostrar idempotencia, concurrencia, bloqueig d'accions destructives antigues, PDF/QR, AEAT retry, permisos, backups, restauracio i activacio de versio. Tambe deixava clar que VERI*FACTU PrisMa es un projecte de mesos, amb Meriem com a capacitat principal de desenvolupament fiscal/SIF, i que aquesta planificacio es governanca interna, no document AEAT.

Impacte:
`09-checklist-posada-en-produccio.md`, `20-pla-proves-validacio-sif.md`, `19-registre-versions-i-canvis-sif.md`, `14-pla-documentacio-i-auditoria.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` incorporen criteris de preproduccio, backups, go/no-go, versio `0.3-BORRADOR`, activacio `1.0.0`, evidencies i ordre de xats especialitzats. La revisio transversal del xat antic queda completada i el treball ha de continuar per xats especialitzats.

## 2026-06-01 - Xat especialitzat intranet: Consulta - Modifica alumne

Decisio:
Tractar `Consulta - Modifica alumne` com a pantalla central de consulta i inici d'accions sobre inscripcions, no com a pantalla per editar factures emeses. Les cinc icones queden consolidades com: informacio, canvi de curs, baixa, veure factura i certificat. Veure factura es nomes lectura; canvi de curs i baixa poden derivar a fluxos fiscals, pero no modifiquen factures silenciosament.

Motiu:
El xat antic contenia una explicacio molt concreta de la pantalla, de les captures aportades, de la baixa opacitat de les icones, de la URL de pagament, de `A_PAGAR`, de descomptes i despeses de gestio en canvi de curs, i del fet que Adam fa el retorn manual quan hi ha curs mes barat amb part pagada. Aquesta informacio ja estava parcialment documentada, pero calia convertir-la en criteri executable i proves.

Impacte:
`07-pantalles-intranet.md`, `10-procediments-intranet-ecommerce.md`, `16-estat-final-pantalles.md`, `21-seguretat-permisos-accessos.md`, `22-manual-operatiu-intern.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el subbloc preparat per implementacio, captures finals i proves. El seguent subbloc recomanat es `Passar pagaments / Analitzar fitxer TPV`.

## 2026-06-01 - Xat especialitzat intranet: Passar pagaments i TPV

Decisio:
Tractar `Passar pagaments / Analitzar fitxer TPV` com el subbloc critic de conciliacio de cobraments. La pantalla pot buscar deutes per un sol criteri i pot analitzar TPV, pero el SIF final ha de separar cerca, proposta de conciliacio, emissio de factura quan calgui i registre de pagament. Una factura existent o emesa abans de cobrament no s'actualitza: rep `registerPayment()`.

Motiu:
El xat antic contenia detalls concrets dels endpoints, del JSON de TPV, del modal de confirmacio, de `efectuarPagament()` i de la confusio potencial entre `efact` i `E_FACT`. Sense aquesta capa, el sistema podia mantenir l'habitud antiga d'actualitzar factures o marcar `PAGAT` sense auditoria fiscal suficient.

Impacte:
`07-pantalles-intranet.md`, `10-procediments-intranet-ecommerce.md`, `16-estat-final-pantalles.md`, `20-pla-proves-validacio-sif.md`, `21-seguretat-permisos-accessos.md`, `22-manual-operatiu-intern.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el subbloc preparat per implementacio, proves i captures. El seguent subbloc recomanat es `Generar factura abans de pagar`.

## 2026-06-01 - Xat especialitzat intranet: Generar factura abans de pagar

Decisio:
Tractar `Generar factura abans de pagar` com a emissio fiscal real abans del cobrament. El flux final ha de cridar `issueInvoice()` amb `EMESA_ABANS_COBRAMENT = 1`, conservar relacions amb les inscripcions, snapshot fiscal del receptor i idempotencia. No marca `E_FACT` automaticament, i el pagament posterior ha d'anar per `registerPayment()`.

Motiu:
El xat antic contenia detalls de pantalla i JS que fan aquest punt delicat: `idsInsc` pot acumular-se, l'entitat es text visible, el preu i conceptes surten del client, `concepte2` depen d'una crida asincrona, i el PDF es regenera/destrueix amb fitxers temporals. Sense criteri SIF, es podria crear factura duplicada quan posteriorment es passa el pagament.

Impacte:
`07-pantalles-intranet.md`, `10-procediments-intranet-ecommerce.md`, `16-estat-final-pantalles.md`, `20-pla-proves-validacio-sif.md`, `21-seguretat-permisos-accessos.md`, `22-manual-operatiu-intern.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el subbloc preparat per implementacio, proves i captures. El seguent subbloc recomanat es `Consulta - Edita - Anula factura`.

## 2026-06-02 - Xat especialitzat intranet: Consulta - Edita - Anula factura

Decisio:
Tractar `Consulta - Edita - Anula factura` com a centre de control de factures ja emeses, no com a pantalla d'edicio directa. El flux final ha de bloquejar `updDadesFact` per a factures SIF, substituir `anularFactura()` historica per rectificatives SIF, i vincular qualsevol retorn a devolucio, saldo o compensacio amb pagament i factura original.

Motiu:
El xat antic ha recuperat codi concret: `guardarDadesFactura_Factures()` modifica `web.factures` directament i retorna `OK`; `anularFactura()` crea una factura `R` negativa amb numeracio local i actualitza resums d'inscripcio; el modal antic ja avisava del risc de multiples inscripcions relacionades. Sense aquesta decisio, el sistema podria continuar corregint factures emeses amb updates manuals i sense rastre fiscal suficient.

Impacte:
`07-pantalles-intranet.md`, `10-procediments-intranet-ecommerce.md`, `16-estat-final-pantalles.md`, `20-pla-proves-validacio-sif.md`, `21-seguretat-permisos-accessos.md`, `22-manual-operatiu-intern.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el subbloc preparat per implementacio, cataleg de motius, proves i captures. El seguent subbloc recomanat es `Intranet alumne, empresa/responsable i acces VERI*FACTU`.

## 2026-06-02 - Xat especialitzat intranet: Intranet alumne, empresa/responsable i acces VERI*FACTU

Decisio:
Tractar la intranet de l'alumne, els accessos d'empresa/responsable i l'apartat `VERI*FACTU` com a capa de consulta i visibilitat, no com a espai de gestio fiscal. L'alumne pot veure factures individuals propies; les factures d'empresa o grup nomes son visibles per l'empresa/responsable autoritzat. L'apartat `VERI*FACTU` de la intranet resumeix i enllaça, pero la resolucio oficial viu a `pay.prisma.cat/sif`.

Motiu:
El xat antic va deixar una decisio de privacitat molt concreta: l'alumne no ha de veure factures pagades per una empresa, ni una factura completa de grup amb altres participants; nomes l'empresa/responsable pot veure-la. Tambe va fixar que els PDFs s'han de guardar en espai no public de `pay.prisma.cat` i servir-se amb permisos, sense exposar ruta directa.

Impacte:
`07-pantalles-intranet.md`, `10-procediments-intranet-ecommerce.md`, `16-estat-final-pantalles.md`, `20-pla-proves-validacio-sif.md`, `21-seguretat-permisos-accessos.md`, `22-manual-operatiu-intern.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen definit el criteri de visibilitat, enllac segur, PDF/QR, token/permis i resum `VERI*FACTU`. El seguent bloc recomanat es `Redsys curs normal`.

## 2026-06-02 - Bloc especialitzat: Redsys curs normal

Decisio:
Tractar `Redsys curs normal` com el patro base de migracio dels callbacks de pagament. `realitzaPagamentAutomatic.php` no ha de calcular numero fiscal ni inserir a `web.factures`; ha de validar signatura i import Redsys, registrar/deduplicar `DS_ORDER`, carregar la inscripcio per `IDPAG` i decidir entre `issueInvoice()` o `registerPayment()`.

Motiu:
El xat antic recupera el flux real: el callback actual llegeix `Ds_MerchantParameters`, calcula signatura, busca inscripcio per `IDPAG`, genera `A{any}/{ordre}`, insereix a `factures` i actualitza `PAGAMENT`, `FACTURA_RELACIONADA`, `DATA PAG` i `FRACCIO`. Aquest patró pot duplicar factura o pagament si Redsys repeteix notificacio, si hi ha fraccionaments o si ja existeix factura abans de cobrament.

Impacte:
`06-integracio-redsys-pay-prisma.md`, `10-procediments-intranet-ecommerce.md`, `20-pla-proves-validacio-sif.md`, `21-seguretat-permisos-accessos.md`, `22-manual-operatiu-intern.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el flux preparat per implementacio i proves. El seguent bloc recomanat es `Packs`.

## 2026-06-02 - Bloc especialitzat: Packs

Decisio:
Tractar `Packs` com una variant Redsys amb multiples linies: una factura per pagament real, una linia per curs/inscripcio, agrupacio per `IDPAG` i descompte `PACK` del 25% aplicat al segon curs. Si intranet registra un fraccionament excepcional, cada pagament real ha de tenir factura/idempotencia propia; ecommerce no ha de permetre dividir el pack en diverses factures.

Motiu:
El xat antic confirma que el pack normal inclou 2 cursos, crea una inscripcio per curs, comparteix `IDPAG`, obtene el preu de taules pack/preu i aplica el descompte sempre al segon curs. Les consultes actuals `buscarPagamentsPack`, `buscarInfoPack`, `cnsInscsPack` i `cnsDadesCursPack` identifiquen el punt operatiu que cal convertir en snapshot fiscal abans de cridar el SIF.

Impacte:
`04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `20-pla-proves-validacio-sif.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el cas pack preparat per implementacio i proves. El seguent bloc recomanat es `Grups`.

## 2026-06-02 - Bloc especialitzat: Grups

Decisio:
Tractar `Grups` com una variant amb multiples inscripcions i receptor fiscal no necessariament igual al participant: una factura per pagament real, una linia per participant, receptor empresa/escola o responsable particular, i DNI del participant nomes visible si hi ha justificacio documentada.

Motiu:
El xat antic confirma que una empresa o persona paga per N participants, que cada participant te una fila a `inscripcions`, que el preu per participant surt de `descomptes_grup` i que la factura de grup necessita linies per participant per justificacions tipus FUNDAE/Tripartita. Les consultes `buscarPersRespGrup2`, `buscarPersGrup`, `buscarPagamentsGrup`, `searchMembresGrup` i `searchMembresGrup2` mostren que l'operativa historica gira al voltant de `TIPUS_INSC = G`, `IDPAG` i `respGrups`.

Impacte:
`04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `10-procediments-intranet-ecommerce.md`, `20-pla-proves-validacio-sif.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el cas grup preparat per implementacio i proves. El seguent bloc recomanat es `Regals`.

## 2026-06-02 - Bloc especialitzat: Regals

Decisio:
Tractar `Regals` com una venda facturada al comprador: una factura per pagament real, `SOURCE_TYPE = REGAL`, `SOURCE_ID = regal.ID`, codi regal i bescanvi posterior del destinatari sense generar una segona factura.

Motiu:
El xat antic confirma que paga qui regala el curs, que la factura va al comprador, que el destinatari encara no omple dades d'inscripcio en el moment de compra i que rep un codi per bescanviar. Les consultes `buscarRegNoPayByCodi`, `buscarRegNoPayByDni`, `buscarRegalById` i `updFactRegal` mostren que l'operativa historica gira al voltant de la taula `regal`, `CODI`, `FACT_REL`, `ORIGEN` i `DESTI`.

Impacte:
`04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `10-procediments-intranet-ecommerce.md`, `20-pla-proves-validacio-sif.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el cas regal preparat per implementacio i proves. El seguent bloc recomanat es `USOC`.

## 2026-06-02 - Bloc especialitzat: USOC

Decisio:
Tractar `USOC` com un cas de validacio manual i doble factura ordinaria: l'alumne rep factura per la part que paga i USOC rep factura per la diferencia que assumeix. `TIPUS_DESC = 4` identifica `Afiliat USOC` i `VALID_DESC` ha d'estar validat abans d'aplicar el descompte i emetre factura amb import reduit.

Motiu:
El xat antic confirma que `curs afiliat d'USOC` era un canal TPV propi, que el descompte USOC es valida a l'apartat `validar descomptes` de la intranet, que el cas habitual tenia un primer pagament de l'alumne de 10 euros i un segon pagament de la diferencia per USOC, i que el concepte podia indicar que el pagament de la diferencia el realitza l'entitat USOC. Tambe s'han identificat `cnsAlumnDescNoValidat`, `updValidDescByInsc`, `updValidDescByInscPreu` i el cas especial `Altres: Curs gratüit USOC` amb `anticipi-preu-usoc`.

Impacte:
`04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `10-procediments-intranet-ecommerce.md`, `20-pla-proves-validacio-sif.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el cas USOC preparat per implementacio i proves. El seguent bloc recomanat es `Codis promocionals`.

## 2026-06-02 - Bloc especialitzat: Codis promocionals

Decisio:
Tractar els codis promocionals i promocions temporals com a logica operativa pre-factura. Ecommerce/intranet valida codi, DNI, us i vigencia; el SIF no revalida el codi, nomes congela el resultat fiscal dins `factura_linia` amb import/percentatge, text visible, codi o referencia interna i total final.

Motiu:
El xat antic confirma que els clients poden introduir un codi al camp `Codi promocional`, que `descomptes.TIPUS` 11-99 identifica promocions temporals, i que `promocions` conserva `CODI_DESCOMPTE`, `DNI`, `MES`, `CURS`, `PERCENTATGE`, `USED`, `DATAI` i `DATAF`. Tambe s'han recuperat `cnsSiTePromocioDispo`, `updDataFPromocio`, l'exemple `MACABODETITULAR#...` i la decisio que si el codi caduca o queda usat despres d'emetre, la factura no canvia.

Impacte:
`documentacio-verifactu.md`, `05-model-bd-sif.md`, `04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `10-procediments-intranet-ecommerce.md`, `20-pla-proves-validacio-sif.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el cas preparat per implementacio i proves. El seguent bloc recomanat es `Transferencia validada a intranet`.

## 2026-06-02 - Bloc especialitzat: Transferencia validada a intranet

Decisio:
Tractar la transferencia validada des de `Passar pagaments` com a registre de cobrament, no com a edicio de factura. Si la factura SIF ja existeix, el flux final ha de fer `registerPayment()` i `payment_allocation`; si no existeix factura i el cobrament crea obligacio fiscal, ha de fer `issueInvoice()` + `registerPayment()` dins una operacio idempotent.

Motiu:
El xat antic confirma que la transferencia es valida a la intranet i que `efectuarPagament.php` envia `id`, `tipus`, `pagament`, `dataPag`, `banc`, `obs`, `numFact` i `efact`. S'ha recuperat el cami `efact != 0`: `efectuarPagamentFacturaGenerada()` usa `buscarPagamentsByFact`, `updFactGenerada`, `searchMembresFactRel`, `updPayInscr`, `updDateInscr` i `updFraccBDByFact`. Aquest comportament historic queda substituit per `payment_transaction` i `payment_allocation`, perque una factura VERI*FACTU emesa no pot modificar import, data de pagament o forma de pagament com a part de la factura.

Impacte:
`04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `10-procediments-intranet-ecommerce.md`, `07-pantalles-intranet.md`, `05-model-bd-sif.md`, `20-pla-proves-validacio-sif.md`, `26-matriu-cobertura-casos.md` i `27-informe-auditoria-documental.md` deixen el cas preparat per implementacio i proves. El seguent bloc recomanat es `Compensacio/saldo`.

## 2026-06-02 - Arquitectura tecnica SIF tancada

Decisio:
Tancar el contracte tecnic del SIF: `issueInvoice()` es l'unic flux que assigna numero fiscal, crea `factura`, `factura_linia`, `factura_registres`, actualitza `fiscal_chain_state`, crea `fiscal_queue` i registra `fact_rels`. `registerPayment()` queda limitat a registrar moviments economics sobre factures existents mitjancant `payment_transaction` i `payment_allocation`, recalculant `ESTAT_COBRAMENT` sense crear numero fiscal, registre fiscal ni hash chain. Quan factura i cobrament neixen en el mateix event, el flux final es `issueInvoice()` amb bloc `payment` dins una unica operacio idempotent, no dues crides publiques separades.

Motiu:
La documentacio ja contenia les peces principals, pero estaven disperses i amb algunes ambiguitats: `registerPayment()` no tenia flux transaccional equivalent a `issueInvoice()`, `fact_rels` tenia dues formes SQL, `PROVIDER_REF` i `DS_ORDER` no estaven alineats, faltaven valors controlats de cobrament/assignacio/origen i alguns documents deien `issueInvoice()` + `registerPayment()` sense aclarir que havia de ser una operacio unica.

Impacte:
`05-model-bd-sif.md`, `17-estat-final-bd-relacions.md`, `18-estat-final-operacio-incidencies.md`, `24-diccionari-camps-i-valors.md`, `13-mapa-bases-dades-i-taules.md`, `documentacio-verifactu.md`, `04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md`, `07-pantalles-intranet.md`, `10-procediments-intranet-ecommerce.md`, `16-estat-final-pantalles.md`, `20-pla-proves-validacio-sif.md` i `27-informe-auditoria-documental.md` queden alineats amb el criteri final. La relacio amb BD antiga queda tancada com a relacio logica auditada via `fact_rels`, sense foreign keys entre BD fiscal i BD web/intranet.

## 2026-06-02 - Entrada de pagaments al SIF

Decisio:
Definir un contracte unic d'entrada de pagaments al SIF. Redsys entra per callback a `pay.prisma.cat` i es registra primer a `redsys_notifications` per `DS_ORDER`; les transferencies i pagaments manuals entren per `Passar pagaments`; els fitxers TPV entren com a analisi/conciliacio auditada. El cobrament real acceptat pel SIF queda a `payment_transaction` i la seva aplicacio a factures queda a `payment_allocation`.

Motiu:
La documentacio ja separava `issueInvoice()` i `registerPayment()`, pero encara podia quedar ambigua la diferencia entre notificacio Redsys, linia de fitxer TPV, cobrament real i assignacio a factura. Sense aquest contracte, un callback duplicat, una transferencia repetida o una linia TPV no conciliada podria tornar a crear factura, duplicar pagament o actualitzar camps antics com si fossin font fiscal.

Impacte:
`06-integracio-redsys-pay-prisma.md`, `04-fluxos-facturacio.md`, `10-procediments-intranet-ecommerce.md`, `25-panell-sif-pay-prisma.md` i `20-pla-proves-validacio-sif.md` deixen definit que `redsys_notifications` no substitueix `payment_transaction`, que el fitxer TPV no factura automaticament si hi ha dubtes, que `payment_allocation` suporta pagaments parcials o multiples factures, i que la sincronitzacio amb `web.inscripcions`/`web.factures` nomes pot passar despres d'una resposta correcta del SIF.

## 2026-06-02 - Paquet executable de proves i posada en produccio

Decisio:
Convertir el bloc de proves i produccio en criteris executables: decisio formal `GO`, `GO AMB LIMITACIONS` o `NO-GO`; bateria bloquejant amb IDs de prova; fitxa d'evidencia; criteri de captures; prova minima de backup/restauracio; classificacio d'incidencies; i checklist final abans, durant i despres de l'activacio productiva.

Motiu:
La documentacio ja deia que calia provar preproduccio, idempotencia, Redsys duplicat, concurrencia, PDF/QR, AEAT, permisos, backups i restauracio, pero encara podia quedar com a intencio. Per poder activar `1.0.0` cal que cada criteri tingui resultat esperat, evidencia conservable i efecte clar sobre la decisio go/no-go.

Impacte:
`09-checklist-posada-en-produccio.md`, `20-pla-proves-validacio-sif.md`, `19-registre-versions-i-canvis-sif.md`, `26-matriu-cobertura-casos.md`, `27-informe-auditoria-documental.md` i `README.md` deixen el paquet preparat a nivell documental. Encara queda pendent executar-lo en un entorn de preproduccio o produccio controlada, guardar captures/logs/exports reals i associar-lo a la versio candidata.

## 2026-09-16 - Plantilles operatives de proves i go/no-go

Decisio:
Afegir plantilles concretes per executar una prova, resumir una campanya go/no-go, documentar una restauracio, registrar l'acta go/no-go i descriure una versio candidata del SIF.

Motiu:
El paquet de proves ja definia criteris i IDs, pero faltava un format reutilitzable per omplir el dia que s'executin proves reals. Sense plantilla, les evidencies podrien quedar disperses, incompletes o no comparables entre versions.

Impacte:
`20-pla-proves-validacio-sif.md` incorpora fitxa d'execucio i resum de campanya; `09-checklist-posada-en-produccio.md` incorpora acta de restauracio i acta go/no-go; `19-registre-versions-i-canvis-sif.md` incorpora fitxa de versio candidata. Les plantilles encara no son evidencies reals: nomes preparen l'execucio futura.

## 2026-06-02 - Fluxos fiscals especials tancats

Decisio:
Tancar el criteri fiscal de compensacio/saldo, pagaments fraccionats, rectificatives, devolucions, baixes, canvis de curs, factura manual i migracio de factures historiques. Aquests casos queden separats com a fluxos propis: no es resolen modificant directament imports, dates, pagaments o factures emeses.

Motiu:
El xat antic contenia matisos importants que podien quedar barrejats: saldo per baixa, pagaments parcials, devolucions parcials, canvis de curs amb diferencia, descomptes excepcionals, rectificatives per substitucio, anul·lacions historiques i factures manuals. Amb VERI*FACTU, aquests casos han de passar per moviments economics, rectificatives, relacions i logs, no per updates silenciosos.

Impacte:
`04-fluxos-facturacio.md`, `11-inventari-canvis-pendents.md`, `15-estat-final-sistema.md`, `18-estat-final-operacio-incidencies.md` i `26-matriu-cobertura-casos.md` deixen els fluxos com `DISSENY COBERT`. Queda pendent implementar pantalles, SQL final, serveis SIF, correus i proves executables, pero el criteri funcional/fiscal ja no queda pendent de decidir.

## 2026-06-02 - Preparacio normativa i documental del SIF

Decisio:
Preparar el paquet normatiu/documental del SIF sense convertir encara cap document en signable. La declaracio responsable continua sent `0.1-BORRADOR` no signable; la primera versio signable prevista continua sent `1.0.0`, condicionada a versio instal·lada i verificable, certificat digital de l'entitat o apoderament configurat/provat, proves conservades, PDF/QR/XML, declaracio accessible dins del SIF i decisio formal d'activacio.

Motiu:
La documentacio ja recollia criteris sobre AEAT, declaracio responsable, productor/titular, certificat i rols, pero calia fer-los mes executables i alineats amb fonts oficials consultades el 2026-06-02. Tambe calia evitar ambiguitats entre Associacio PrisMa com a productora/titular interna i obligada tributaria, Meriem com a responsable tecnica/documental i Adam/direccio com a signant o representacio legal quan correspongui.

Impacte:
`documentacio-sif-aeat.md`, `declaracio-responsable-sif-prisma.md`, `19-registre-versions-i-canvis-sif.md`, `21-seguretat-permisos-accessos.md`, `24-diccionari-camps-i-valors.md` i `README.md` queden reforcats amb criteris de fonts oficials, paquet `1.0.0`, certificat/apoderament, rol auditor nomes lectura, secrets tecnics i camps fiscals minims. Queden pendents les dades reals de signatura, certificat/apoderament, proves executades i decisio final sobre dades personals del contacte tecnic a incloure a la declaracio signada o conservar en expedient intern.

## 2026-06-02 - Pla d'implementacio tecnica del SIF

Decisio:
Crear un pla d'implementacio tecnica executable per al nucli SIF a `documentacio/00-index-i-pla/29-pla-implementacio-tecnica-sif.md`. El pla tradueix l'arquitectura tancada en fases de treball: base PHP amb autoload propi i runner de proves sense Composer, migracio SQL, infraestructura transaccional, validacions, hash chain, `issueInvoice()`, idempotencia/concurrencia, `registerPayment()`, `fact_rels`, endpoints interns, Redsys, documents/incidencies i preflight.

Motiu:
L'arquitectura de `issueInvoice()`, `registerPayment()`, idempotencia, hash chain, taules fiscals, pagaments i relacio amb BD antiga ja esta tancada. El risc principal ja no es decidir criteris, sino executar-los de manera ordenada, provable i sense barrejar el projecte documental pont amb el repo real d'implementacio del SIF.

Impacte:
`documentacio/README.md`, `documentacio-verifactu.md`, `estat-projecte.md` i `checklist-completitud.md` apunten al nou pla. El proper pas tecnic queda definit com executar Fase 0 i Fase 1 al repo real de `pay.prisma.cat`/SIF, sense commit/push fins que es demani explicitament.

## 2026-06-02 - Fase 0/1 preparada amb copia local del Drive

Decisio:
No implementar directament sobre el Drive. Copiar al repo de treball els fitxers del Drive necessaris com a referencia (`codi-drive/`) i preparar Fase 0/1 del SIF en aquest repo. La copia local conserva callbacks Redsys, pagaments automatics, `apiRedsys.php`, connexions sense fitxers de parametres i el flux antic `Intranet.php`/`efectuarPagament.php`.

Motiu:
Els fitxers programats reals existeixen al Drive i s'han de tenir en compte, pero no s'han de tocar directament durant la preparacio. Tambe cal evitar copiar credencials (`parametres-connexio*`) i separar el codi historic de referencia del nucli SIF nou.

Impacte:
Fase 0 queda preparada amb `sif/src/autoload.php`, `sif/tests/run-tests.php`, `sif/tests/Support/Assert.php`, `sif/config/sif.php`, bootstrap i helper de tests. Fase 1 queda preparada amb migracio SQL `CREATE TABLE IF NOT EXISTS`, seed inicial, runner de migracions protegit contra `SIF_ENV=production` i test estructural d'esquema. La verificacio real queda pendent perque PHP no esta disponible al PATH d'aquest entorn.

## 2026-06-03 - Fase 2 preparada: infraestructura comuna SIF

Decisio:
Preparar la infraestructura comuna del SIF amb classes petites i provables: `ConnectionFactory`, `TransactionRunner`, `UuidGenerator` i `SifException`. Les proves unitàries s'han escrit abans del codi per validar format UUID v4, codis d'excepcio i commit/rollback transaccional.

Motiu:
Abans de validar payloads, hash chain o serveis fiscals, cal una base comuna estable per connexio BD, transaccions, identificadors i errors. Aquesta capa es manté separada del codi historic copiat del Drive i no modifica cap callback ni flux antic.

Impacte:
Fase 2 queda preparada a nivell de codi i proves dins `sif/src` i `sif/tests/Unit`. La verificacio real queda pendent fins que `php` estigui disponible al PATH. El seguent pas tecnic es Fase 3: `InvoicePayloadValidator` i `PaymentPayloadValidator`.

## 2026-06-03 - Execucio sense Composer

Decisio:
Eliminar la dependencia de Composer i PHPUnit del nucli SIF. El SIF carregara classes amb `sif/src/autoload.php` i les proves locals s'executaran amb `php sif/tests/run-tests.php`.

Motiu:
El servidor no pot tenir `composer install`. Per tant, qualsevol dependencia d'autoload Composer, `vendor/` o PHPUnit instal·lat per Composer faria que el desplegament no fos realista.

Impacte:
S'han eliminat `composer.json` i `phpunit.xml` del repo de treball, s'ha creat un autoloader propi, un runner de proves PHP pur i asserts propis. El pla tecnic 29 i els controls queden alineats amb un SIF executable amb PHP pur.

## 2026-06-03 - Fase 3 preparada: validacio de payloads

Decisio:
Preparar els validators de payload del SIF: `InvoicePayloadValidator` i `PaymentPayloadValidator`. Les proves unitàries s'han escrit abans del codi i cobreixen payloads vàlids, camps obligatoris, serie de factura, linies obligatories, tipus de moviment i assignacions de pagament.

Motiu:
Abans d'implementar hash chain, repositoris o serveis `issueInvoice()`/`registerPayment()`, el SIF necessita rebutjar entrades incompletes o fora de cataleg. Això evita que els canals antics o callbacks Redsys passin dades insuficients al nucli fiscal.

Impacte:
Fase 3 queda preparada a nivell de codi i proves dins `sif/src/Service` i `sif/tests/Unit`. La verificacio real queda pendent fins que `php` estigui disponible al PATH. El seguent pas tecnic es Fase 4: `HashCalculator`.

## 2026-06-05 - Task 4 de Fase 4 preparada: hash fiscal intern

Decisio:
Preparar `HashCalculator` com a calculador deterministic del hash fiscal intern del SIF. El hash es calcula sobre un payload canonic que inclou `previous_hash`, ordena recursivament els arrays associatius per clau i conserva l'ordre de les llistes, especialment les linies de factura.

Motiu:
La hash chain necessita que el mateix registre fiscal produeixi sempre el mateix hash independentment de l'ordre accidental de les claus del payload. Alhora, l'ordre real de `factura_linia` no es pot reordenar perque forma part de la representacio fiscal i documental de la factura.

Impacte:
El bloc de hash fiscal intern de Fase 4 queda preparat a nivell de codi i proves dins `sif/src/Domain` i `sif/tests/Unit`. La verificacio real queda pendent fins que `php` estigui disponible al PATH. El seguent pas tecnic es continuar la Fase 4 amb Task 5: repositoris de numeracio i factura per preparar `issueInvoice()`.

## 2026-06-05 - Task 5 de Fase 4 preparada: repositoris i primer `issueInvoice()`

Decisio:
Preparar els repositoris fiscals base i el primer servei `InvoiceService::issueInvoice()`. `FiscalSequenceRepository` bloqueja i incrementa la numeracio per serie/any dins la transaccio; `InvoiceRepository` crea factura, linies, registre fiscal, actualitza `fiscal_chain_state`, crea `fiscal_queue` i registra `fact_rels`; `InvoiceService` valida payload, reutilitza factura existent per `IDEMPOTENCY_KEY` i encapsula l'operacio amb `TransactionRunner`.

Motiu:
La fase anterior nomes calculava el hash; per començar a convertir l'arquitectura en codi executable calia unir numeracio, hash chain, taules fiscals i relacio logica amb BD antiga dins una operacio transaccional. La implementacio s'ha ajustat a la migracio real (`IMPORT_BASE`, `BASE_IMPOSABLE`, `TOTAL`) i no als noms provisionals del pla.

Impacte:
Task 5 queda preparat a nivell de codi i proves dins `sif/src/Repository`, `sif/src/Service`, `sif/tests/Integration` i `sif/tests/Support`. La verificacio real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles. El seguent pas tecnic es continuar la Fase 4 amb Task 6: idempotencia i concurrencia seqüencial d'`issueInvoice()`.

## 2026-06-05 - Task 6 de Fase 4 preparada: idempotencia i ordre fiscal seqüencial

Decisio:
Preparar les proves d'idempotencia i concurrencia seqüencial d'`issueInvoice()`. El test d'idempotencia comprova que una segona crida amb el mateix `IDEMPOTENCY_KEY` reutilitza la factura existent i no incrementa `fiscal_sequence`. El smoke test seqüencial comprova 10 emissions amb numeracio lineal, `LAST_FISCAL_ORDER` lineal, ordres fiscals diferents i hashes fiscals únics.

Motiu:
Abans de passar a `registerPayment()`, el nucli d'emissio ha de demostrar que la numeracio visible i l'ordre fiscal intern avancen de manera coherent i que la idempotencia evita duplicar factures o avançar numeracio davant callbacks o reintents repetits.

Impacte:
Task 6 queda preparat a nivell de proves dins `sif/tests/Integration`. No ha requerit canvis de codi de produccio respecte al Task 5. La verificacio real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles. El seguent pas tecnic es Fase 5: `registerPayment()` i estat de cobrament.

## 2026-06-05 - Fase 5 preparada: `registerPayment()` i estat de cobrament

Decisio:
Preparar `registerPayment()` com a flux economic separat de l'emissio fiscal. `PaymentService` valida el payload, aplica idempotencia per `IDEMPOTENCY_KEY` i encapsula l'operacio amb `TransactionRunner`; `PaymentRepository` crea `payment_transaction` i `payment_allocation`, recalcula `ESTAT_COBRAMENT` i reutilitza pagaments existents; `PaymentStatusCalculator` calcula `PENDING`, `PARTIAL`, `PAID`, `OVERPAID`, `PARTIALLY_REFUNDED` i `REFUNDED` en centims.

Motiu:
El contracte tancat del SIF diu que `registerPayment()` no pot crear numero fiscal, hash chain ni registre fiscal. Serveix per registrar moviments economics sobre factures ja emeses i per actualitzar l'estat de cobrament auditablement, sense modificar la factura fiscal emesa.

Impacte:
Fase 5 queda preparada a nivell de codi i proves dins `sif/src/Domain`, `sif/src/Repository`, `sif/src/Service`, `sif/tests/Unit` i `sif/tests/Integration`. La verificacio real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles. El seguent pas tecnic es Fase 6: relacio amb BD antiga i sincronitzacio controlada.

## 2026-06-05 - Fase 6 preparada: `fact_rels` i sincronitzacio legacy controlada

Decisio:
Preparar la relacio amb BD antiga com a pont logic i resum operatiu, no com a font fiscal. `LegacyRelationsTest` comprova que `fact_rels` conserva `FACTURA_RELACIONADA`, `IDPAG`, `DS_ORDER`, `SOURCE_TYPE` i `SOURCE_ID`. `LegacySyncService::syncAfterSifSuccess()` queda com a crida explicita posterior a l'exit del SIF, i `LegacySyncRepository` actualitza nomes el resum d'`inscripcions` amb `FACTURA_RELACIONADA` si estava buida i una nota `SIF`.

Motiu:
El criteri tancat del projecte diu que no hi ha foreign keys entre BD fiscal i BD web/intranet, i que els camps antics nomes poden servir de compatibilitat operativa. La sincronitzacio no ha de correr dins `issueInvoice()` ni `registerPayment()` abans del commit fiscal, per evitar que la BD antiga sembli confirmada si el SIF falla.

Impacte:
Fase 6 queda preparada a nivell de codi i proves dins `sif/src/Repository`, `sif/src/Service` i `sif/tests/Integration`. La verificacio real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles. El seguent pas tecnic es Fase 7: endpoints HTTP interns.

## 2026-06-05 - Fase 7 preparada: endpoints HTTP interns

Decisio:
Preparar dos endpoints interns PHP pur: `sif/public/api/factures/issue.php` per `issueInvoice()` i `sif/public/api/payments/register.php` per `registerPayment()`. Els endpoints llegeixen JSON amb `JsonResponse::fromInput()`, construeixen els serveis SIF amb `ConnectionFactory`, `TransactionRunner` i repositoris corresponents, i retornen JSON amb `JsonResponse`.

Motiu:
El nucli SIF necessita una entrada HTTP simple per ser cridat des de callbacks, intranet o adaptadors interns sense exposar els detalls de repositoris i transaccions. Aquesta fase prepara la superfície tècnica, pero no incorpora encara autenticacio definitiva, permisos, Redsys ni sincronitzacio legacy automatica.

Impacte:
Fase 7 queda preparada a nivell de codi i proves estàtiques dins `sif/src/Http`, `sif/public/api` i `sif/tests/Integration`. La verificacio real queda pendent fins que `php`, una BD MySQL de test i un servidor local PHP estiguin disponibles. El seguent pas tecnic es Fase 8: Redsys i entrada de pagaments.

## 2026-06-05 - Fase 8 preparada: Redsys i deduplicacio de callback

Decisio:
Preparar l'entrada Redsys com a registre previ i idempotent a `redsys_notifications`. `RedsysNotificationRepository` grava el callback i deduplica per `DS_ORDER`; `RedsysCallbackService` exigeix que la signatura ja hagi estat validada abans de cridar `recordReceived()`; l'endpoint `sif/public/api/redsys/callback.php` queda cablejat pero segur per defecte amb `$signatureValid = false`.

Motiu:
Redsys pot reenviar callbacks i el mateix `IDPAG` pot tenir diversos intents o `DS_ORDER`. Per evitar factures o pagaments duplicats, el primer pas auditable ha de ser registrar/deduplicar la notificacio i nomes despres, en fases d'activacio, decidir si cal cridar `issueInvoice()` o `registerPayment()`. La signatura no es pot confiar com a camp enviat pel client.

Impacte:
Fase 8 queda preparada a nivell de codi i proves dins `sif/src/Repository`, `sif/src/Service`, `sif/public/api/redsys` i `sif/tests/Integration`. La validacio criptografica final queda pendent de connectar amb la llibreria/funcio Redsys actual de PrisMa (`apiRedsys.php`, `decodeMerchantParameters()`, `createMerchantSignatureNotif()` o equivalent), sense hardcodejar secrets i sempre abans de gravar notificacions o generar efectes fiscals/economics. La verificacio real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles.

## 2026-06-05 - Fase 9 preparada: documents, cua AEAT i incidencies

Decisio:
Preparar la capa de conservacio documental i incidencies del SIF. `IssueInvoiceTest` comprova que el `PAYLOAD_JSON` fiscal queda congelat igual a `factura_registres` i `fiscal_queue`, amb cua `PENDING`. `DocumentRepository` registra documents fiscals a `factura_documents` amb `HASH_FITXER` SHA-256 i estat `CREATED`. `IncidentRepository` obre incidencies SIF a `errors_verifactu` amb estat `OPEN`.

Motiu:
La factura fiscal no es nomes una fila de `factura`: necessita registre fiscal, cua AEAT, documents immutables o verificables per hash i incidencies visibles quan falla PDF/QR/AEAT o qualsevol proces fiscal. Aquesta capa separa conservar metadades i hash del document de la generacio real del PDF/XML/QR, que vindra despres.

Impacte:
Fase 9 queda preparada a nivell de codi i proves dins `sif/src/Repository` i `sif/tests/Integration`. No s'ha modificat la migracio perque `fiscal_queue`, `factura_documents` i `errors_verifactu` ja estaven creades a l'SQL inicial. La verificacio real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles. El seguent pas tecnic es Fase 10: proves go/no-go, preflight i activacio controlada.

## 2026-06-05 - Fase 10 preparada: preflight tecnic SIF

Decisio:
Preparar `sif/scripts/preflight-sif.php` com a comprovacio tecnica de nomes lectura abans de qualsevol pilot o activacio. El script carrega l'autoload propi, usa `ConnectionFactory`, comprova connexio, taules SIF clau, Redsys, documents, incidencies i seed de `fiscal_chain_state`, i retorna JSON amb `ok`, `environment`, `checks`, `failed` i `errors` quan correspongui.

Motiu:
Abans d'activar canals o fer proves go/no-go reals, cal una comprovacio rapida i repetible que detecti errors basics d'entorn, migracio o seed sense crear factures ni tocar dades fiscals. Aquesta comprovacio ha de poder executar-se al servidor sense Composer i ha de tenir sortida clara per operacio tecnica.

Impacte:
Fase 10 queda preparada a nivell de codi i prova estàtica dins `sif/scripts` i `sif/tests/Integration`. La verificacio executable real queda pendent fins que `php` i una BD MySQL de test estiguin disponibles. El seguent pas tecnic passa a ser Fase 11: integracio progressiva de canals en preproduccio, començant per validar entorn i Redsys en mode test.

## 2026-06-10 - Fase 10 ampliada: bateria go/no-go de preproduccio

Decisio:
Afegir `sif/scripts/go-no-go-preproduction.php` i `GoNoGoPreproductionScriptTest`. La bateria comprova entorn no productiu, runner de proves, migracions, preflight base, connexio SIF, connexio legacy, clau Redsys, taules fiscals minimes i presencia dels circuits de preproduccio ja preparats.

Motiu:
Abans de provar canals reals cal una porta unica que digui `GO` o `NO-GO` amb checks bloquejants. Executar scripts individuals ajuda a diagnosticar, pero la decisio de pilot necessita una sortida agregada i conservable com a evidencia.

Impacte:
El script es de nomes lectura i retorna JSON amb `go_no_go_decision`, `checks`, `failed` i `errors`. No crea factures, no registra pagaments i no sincronitza legacy. La seva execucio real queda pendent fins que hi hagi PHP, BD SIF/legacy de test i clau Redsys de test configurada.

## 2026-06-05 - Fase 11 iniciada: `issueInvoice(payment)` per cobrament inicial

Decisio:
Preparar el nucli `issueInvoice(payment)` per als casos on factura i cobrament neixen junts, especialment Redsys normal i transferencies ja validades. El bloc `payment` opcional de la factura es valida amb `PaymentPayloadValidator` i crea `payment_transaction` i `payment_allocation` dins la mateixa transaccio que la factura, el registre fiscal, el hash chain, la cua AEAT i `fact_rels`.

Motiu:
L'arquitectura tancada estableix que quan el fet facturable i el cobrament arriben junts no s'ha de fer primer `issueInvoice()` i despres una operacio separada amb risc de desquadrament. La factura fiscal i el moviment economic inicial han de quedar en una unica operacio idempotent, mentre que `registerPayment()` continua reservat per pagaments posteriors sobre factures ja existents.

Impacte:
Fase 11 queda iniciada a nivell de servei, endpoint intern i prova d'integracio. El reintent idempotent d'`issueInvoice(payment)` retorna tambe el `uuid_payment` existent quan ja s'havia creat el moviment inicial. Encara no s'ha activat cap canal real: l'activacio en preproduccio queda condicionada a PHP disponible, BD MySQL de test, `preflight-sif.php` amb `ok=true` i validacio criptografica Redsys real connectada abans de permetre efectes fiscals o economics des del callback.

## 2026-06-06 - Fase 11 Redsys: validacio de signatura preparada sense secret hardcoded

Decisio:
Preparar un adaptador propi `RedsysSignatureValidator` per validar notificacions Redsys amb `Ds_MerchantParameters` i `Ds_Signature`, llegint la clau des de `SIF_REDSYS_MERCHANT_KEY` i sense copiar el secret hardcodejat detectat al codi antic del Drive.

Motiu:
El callback Redsys no pot acceptar una bandera manual ni confiar en cap camp del payload abans de registrar notificacions o generar efectes fiscals/economics. La signatura ha de validar-se abans d'entrar a `redsys_notifications`, pero la clau no pot quedar al repositori ni dependre de Composer.

Impacte:
`sif/public/api/redsys/callback.php` queda cablejat per POST real de Redsys i normalitza `ds_order`, `idpag`, `amount` i `response_code` abans de cridar `RedsysCallbackService`. La prova unitària inclou una notificacio signada de test per comprovar el cami valid i la conversio d'import en centims. El servei classifica `Ds_Response`: `0..99` queda `VALIDATED` i qualsevol resposta no autoritzada queda `ERROR`, sempre sense crear factura ni `payment_transaction`. Aquesta subfase encara no activa `issueInvoice()` ni `registerPayment()` des del callback; nomes prepara el pas segur anterior. La verificacio executable queda pendent fins que PHP estigui disponible i hi hagi `SIF_REDSYS_MERCHANT_KEY` de test configurada.

## 2026-06-06 - Fase 11 Redsys: payload `issueInvoice(payment)` preparat per notificacio validada

Decisio:
Preparar `RedsysInvoicePayloadBuilder` com a frontera entre una notificacio Redsys `VALIDATED` i el payload fiscal `issueInvoice(payment)` de curs normal. El builder consulta `redsys_notifications` per `DS_ORDER`, exigeix `STATUS = VALIDATED`, construeix la clau idempotent `REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`, afegeix `payment` Redsys i injecta `IDPAG`/`DS_ORDER` a `relations`.

Motiu:
Abans d'unir el callback amb emissio fiscal real, cal separar dos passos: validar/registrar Redsys i construir el payload fiscal. Això evita que una notificacio `ERROR`, `RECEIVED` o `DUPLICATE` pugui crear factura o pagament, i deixa clar que la font d'idempotencia del cas Redsys es el `DS_ORDER` signat i registrat.

Impacte:
El flux tecnic queda preparat per proves de curs normal en mode test amb un payload base construit per un adaptador futur de BD antiga. Encara no s'ha implementat la lectura real d'inscripcions/cursos del sistema antic ni s'ha cablejat el callback perquè cridi automaticament `issueInvoice()`.

## 2026-06-06 - Fase 11 curs normal: snapshot legacy a payload fiscal base

Decisio:
Preparar `LegacyCourseInvoicePayloadBuilder` com a adaptador PHP pur entre les dades antigues de `inscripcions` + `curs` i el payload base de `issueInvoice()`. El builder no consulta la BD antiga: rep un snapshot, conserva receptor, NIF, adreca, correu, concepte/detall del curs, import actual del pagament, IVA exempt i relacio `INSCRIPCIO`. No usa `inscripcions.PAGAMENT` com a import de factura perquè aquest camp es pagat acumulat historic.

Motiu:
El callback Redsys real no ha de inventar dades fiscals ni dependre directament del codi antic. Cal una peça intermedia testable que separi les dades fiscals del curs de la notificacio Redsys signada. La idempotencia fiscal definitiva i el bloc `payment` continuen sortint de `RedsysInvoicePayloadBuilder`, perquè depenen de `DS_ORDER`/`IDPAG` validats.

Impacte:
El cas curs normal ja te un payload base compost amb `RedsysInvoicePayloadBuilder` i `issueInvoice(payment)` en prova d'integracio. Queden pendents la lectura real de `inscripcions`/`curs` des de la BD antiga en preproduccio, l'execucio amb PHP/MySQL de test i el cablejat final del callback només quan `preflight-sif.php` sigui `ok=true`.

## 2026-06-06 - Fase 11 curs normal: lectura legacy de snapshot preparada

Decisio:
Preparar `LegacyCourseSnapshotRepository` com a adaptador de nomes lectura per recuperar la inscripcio antiga per `IDPAG` i `INSC CURS` `0`, `1` o `M`, carregar el curs per `ANY`/`MES`/`CURS` i retornar el snapshot amb l'import actual del pagament ja validat pel flux Redsys.

Motiu:
El callback antic barrejava lectura legacy, emissio fiscal, actualitzacio d'inscripcio i correus. El SIF necessita separar la lectura de dades operatives de l'emissio fiscal: primer es valida i registra Redsys, despres es carrega el snapshot antic, despres es construeix el payload, i només al final es crida `issueInvoice(payment)` o `registerPayment()`.

Impacte:
La consulta antiga de curs normal queda encapsulada i testada amb PDO espia, sense escriure a la BD antiga ni activar cap endpoint real. Encara falta composar aquesta lectura amb `redsys_notifications`, `RedsysInvoicePayloadBuilder` i `InvoiceService` en un orquestrador de preproduccio.

## 2026-06-06 - Fase 11 curs normal: orquestrador de servei preparat

Decisio:
Preparar `RedsysCourseInvoiceService` per composar el flux de curs normal des d'una notificacio Redsys `VALIDATED`: valida l'estat de `redsys_notifications`, carrega snapshot legacy per `IDPAG`, construeix payload fiscal base, injecta idempotencia/pagament Redsys i delega l'emissio a `issueInvoice(payment)`.

Motiu:
Abans de cablejar cap endpoint real cal tenir una peça de servei testable que uneixi les fronteres ja creades sense tornar al patró antic de callback monolitic. Això manté separats validacio Redsys, lectura legacy, emissio fiscal i sincronitzacio posterior.

Impacte:
El curs normal Redsys queda preparat a nivell de servei amb reintent idempotent i bloqueig de notificacions `ERROR` abans de consultar legacy. El callback real continua sense efectes fiscals automatics fins que hi hagi PHP, BD MySQL de test, `preflight-sif.php` amb `ok=true` i prova Redsys real.

## 2026-06-06 - Fase 11 curs normal: script manual de preproduccio preparat

Decisio:
Afegir configuracio `legacy_db` amb variables `SIF_LEGACY_DB_DSN`, `SIF_LEGACY_DB_USER` i `SIF_LEGACY_DB_PASSWORD`, ampliar `ConnectionFactory` amb `makeLegacy()` i crear `sif/scripts/process-redsys-course.php` per processar manualment un `DS_ORDER` ja registrat com a `VALIDATED`.

Motiu:
Abans d'activar el callback real cal una eina controlada per provar el flux complet amb BD SIF i BD legacy de test. La prova manual permet validar l'orquestrador sense barrejar-se amb el POST Redsys ni amb la recepcio automatica de callbacks.

Impacte:
El script nomes funciona en CLI, rebutja `SIF_ENV=production`, requereix `SIF_LEGACY_DB_*` i no parseja ni valida notificacions Redsys. L'execucio real continua pendent de PHP disponible, BD SIF/legacy de test i evidencies de preproduccio.

## 2026-06-06 - Fase 11 curs normal: preflight especific Redsys curs preparat

Decisio:
Crear `sif/scripts/preflight-redsys-course.php` com a comprovacio de nomes lectura abans de processar un curs normal Redsys manualment. El script valida que l'entorn no sigui produccio, que la clau Redsys estigui configurada, que la BD SIF i la BD legacy connectin, i que existeixin les taules minimes `redsys_notifications`, `payment_transaction`, `inscripcions` i `curs`.

Motiu:
El processador manual ja pot emetre factura i pagament sobre una notificacio `VALIDATED`, per tant necessita una porta prèvia que comprovi readiness sense generar cap efecte fiscal ni economic.

Impacte:
La prova de preproduccio queda dividida en dos passos segurs: primer `preflight-sif.php` i `preflight-redsys-course.php`, despres `process-redsys-course.php DS_ORDER`. Encara no s'ha executat cap dels dos perquè aquest entorn no te PHP disponible.

## 2026-06-06 - Fase 11 curs normal: preview de payload preparada

Decisio:
Crear `sif/scripts/preview-redsys-course.php` com a dry-run per construir el payload fiscal complet d'un `DS_ORDER` `VALIDATED` sense emetre factura. El script llegeix SIF i legacy, construeix snapshot, payload base i payload Redsys amb bloc `payment`, i retorna JSON amb `dry_run=true`.

Motiu:
Abans d'executar el processador manual convé revisar visualment receptor, NIF, linies, import, relacions i idempotencia. Aquesta preview redueix el risc d'emetre una factura de test amb dades mal mapejades.

Impacte:
El flux de preproduccio queda ordenat: preflight general, preflight Redsys curs, preview de payload i nomes despres processament manual. La preview no crea `factura`, `payment_transaction`, hash chain ni `fiscal_queue`.

## 2026-06-06 - Fase 11 curs normal: sincronitzacio legacy opcional post-SIF

Decisio:
Fer que `RedsysCourseInvoiceService` retorni metadades de relacions per a sincronitzacio posterior i afegir l'opcio `--sync-legacy` a `sif/scripts/process-redsys-course.php`. Amb aquest flag, el script crida `LegacySyncService::syncAfterSifSuccess()` nomes despres que el SIF retorni `ok=true`.

Motiu:
La BD antiga necessita resum operatiu, pero aquesta sincronitzacio no pot formar part de `issueInvoice()` ni de la transaccio fiscal. Fer-la explicita evita que una emissio fiscal correcta quedi barrejada amb una actualitzacio legacy opcional o fallida.

Impacte:
Per defecte el processador manual nomes emet al SIF. Si es vol actualitzar el resum legacy en preproduccio, cal executar `process-redsys-course.php DS_ORDER --sync-legacy`. La sincronitzacio continua limitada al resum ja definit per `LegacySyncRepository`.

## 2026-06-06 - Fase 11 factura abans de cobrament: flux preparat a nivell de prova

Decisio:
Afegir `InvoiceBeforePaymentFlowTest` per cobrir el flux `issueInvoice(emesa_abans_cobrament=1)` seguit de `registerPayment()`. La factura queda emesa i pendent de cobrament fins que arriba el pagament posterior.

Motiu:
Factura abans de cobrament ha de tenir registre fiscal, hash chain i cua AEAT en el moment d'emissio, pero el pagament posterior no ha de generar un segon registre fiscal ni renumerar res. Aquesta separacio evita confondre fet fiscal amb moviment economic.

Impacte:
El test comprova que el pagament posterior crea nomes `payment_transaction` i `payment_allocation`, actualitza `ESTAT_COBRAMENT` a `PAID` i manté estable `factura_registres`, `fiscal_queue` i `fiscal_chain_state`.

## 2026-06-10 - Fase 11 factura abans de cobrament: circuit CLI de preproduccio

Decisio:
Afegir `InvoiceBeforePaymentPayloadBuilder`, `InvoiceBeforePaymentService` i els scripts `preflight-invoice-before-payment.php`, `preview-invoice-before-payment.php` i `process-invoice-before-payment.php`. El circuit llegeix un payload JSON, força `source_channel = INTRANET`, `EMESA_ABANS_COBRAMENT = 1`, idempotencia estable i rebutja qualsevol bloc `payment` inicial.

Motiu:
La intranet necessita provar `Generar factura abans de pagar` com a factura fiscal real pendent de cobrament, no com a proforma ni com a pagament implicit. Separar aquest processador del cobrament garanteix que el moviment economic posterior entra nomes per `registerPayment()`.

Impacte:
El processador pot emetre la factura pendent de cobrament en preproduccio, pero no crea `payment_transaction` ni `payment_allocation`, no toca legacy, no depen de Redsys i no activa cap endpoint real. L'execucio executable queda pendent de PHP/MySQL de test.

## 2026-06-06 - Fase 11 transferencies manuals: payload `registerPayment()` preparat

Decisio:
Afegir `ManualPaymentPayloadBuilder` per transformar una transferencia validada manualment a `Passar pagaments` en un payload de `registerPayment()` contra una factura SIF existent. El builder normalitza import, data, canal `INTRANET`, metode `TRANSFERENCIA`, referencia bancaria, banc, notes i assignacio unica `INVOICE_PAYMENT`.

Motiu:
La pantalla antiga no ha de modificar imports, data, numero ni receptor d'una factura ja emesa. Quan administracio confirma una transferencia, el SIF ha de registrar nomes el moviment economic a `payment_transaction` i la seva assignacio a `payment_allocation`, mantenint intacte el registre fiscal i el hash chain.

Impacte:
La idempotencia queda centralitzada abans de tocar la intranet real: si hi ha referencia bancaria s'usa `TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}`; si no, s'usa `TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}`. L'activacio operativa queda pendent de PHP/MySQL de test, pantalla real de `Passar pagaments` i prova `SIF-PAY-001`.

## 2026-06-10 - Fase 11 transferencies manuals: circuit CLI per factura SIF existent

Decisio:
Afegir `ManualPaymentInvoiceRepository`, `ManualPaymentService` i els scripts `sif/scripts/preflight-manual-payment.php`, `sif/scripts/preview-manual-payment.php` i `sif/scripts/process-manual-payment.php`. El circuit localitza una factura SIF existent per `UUID_FACTURA` o `NUM_VISIBLE`, construeix el payload manual amb `ManualPaymentPayloadBuilder` i executa `PaymentService::registerPayment()`.

Motiu:
El cas `Passar pagaments -> factura existent` ja tenia el contracte de payload, però faltava una eina segura de preproduccio per provar-lo sense tocar la pantalla real. Acceptar `NUM_VISIBLE` evita obligar administracio a treballar amb UUIDs, i mantenir-ho en `registerPayment()` garanteix que no es crea cap registre fiscal nou.

Impacte:
El flux queda preparat amb preflight nomes SIF, preview dry-run i processador CLI no productiu. No depen de Redsys ni legacy, no construeix `InvoiceService`, no crida `issueInvoice()` i no fa sync legacy. L'activacio operativa continua pendent de PHP/MySQL de test i prova `SIF-PAY-001`.

## 2026-06-06 - Fase 11 transferencies manuals: `issueInvoice(payment)` preparat per curs sense factura prèvia

Decisio:
Afegir `ManualCourseInvoicePayloadBuilder` per transformar el subcas antic `efact == 0` de curs/inscripcio normal en un payload `issueInvoice(payment)`. El builder rep snapshot legacy d'inscripcio + curs, import/data/referencia/banc validats per administracio i genera factura SIF amb `source_channel = INTRANET` i cobrament inicial dins el bloc `payment`.

Motiu:
Quan una transferencia crea l'obligacio fiscal i encara no hi ha factura SIF, no s'ha de crear una factura historica a `web.factures` ni assignar numeracio fora del SIF. La factura, linia, registre fiscal, hash chain, cua AEAT i moviment economic inicial han de quedar dins una sola operacio idempotent `issueInvoice(payment)`.

Impacte:
La idempotencia fiscal del curs manual queda separada de Redsys: amb referencia s'usa `TRANSFERENCIA|CURS|IDPAG:{IDPAG}|REF:{REFERENCIA_BANCARIA}`; sense referencia, `TRANSFERENCIA|CURS|IDPAG:{IDPAG}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}`. La prova d'integracio prepara la verificacio de `payment_transaction.PROVIDER_REF`, `REFERENCIA_BANCARIA` i `IDPAG`, però l'execucio real continua pendent de PHP/MySQL de test i pantalla real `Passar pagaments`.

## 2026-06-06 - Fase 11 transferencies manuals: orquestrador de curs manual preparat

Decisio:
Afegir `ManualCourseInvoiceService` per orquestrar el subcas de curs normal validat manualment a `Passar pagaments`: rep `IDPAG` i dades del cobrament, carrega snapshot legacy de `inscripcions`/`curs`, construeix payload manual amb `ManualCourseInvoicePayloadBuilder` i crida `InvoiceService::issueInvoice()`.

Motiu:
El builder de payload resol la forma fiscal, però cal una frontera de servei que uneixi lectura legacy i emissio SIF sense recuperar el patró antic d'`efectuarPagament()`, que calculava numeracio i escrivia a `web.factures`. Aquesta peça permet provar el flux de curs manual en preproduccio abans de tocar l'endpoint/pantalla real.

Impacte:
El servei retorna `legacy_sync` com a metadades per a una sincronitzacio posterior i explicita, però no escriu a legacy. El test cobreix reintent idempotent, preservacio de `IDPAG`, `PROVIDER_REF` i referencia bancaria, i rebuig d'`IDPAG` invalid abans de consultar la BD antiga. L'execucio real continua pendent de PHP/MySQL de test.

## 2026-06-06 - Fase 11 transferencies manuals: preview de curs manual preparada

Decisio:
Crear `sif/scripts/preview-manual-course.php` com a dry-run per construir el payload `issueInvoice(payment)` d'un curs manual validat a `Passar pagaments` sense emetre factura. El script rep `IDPAG`, import, data de moviment i opcionalment referencia, banc, notes i usuari.

Motiu:
Abans de processar una transferencia manual real cal poder revisar receptor, NIF, linia, import, idempotencia, relacions i bloc `payment` sense generar registre fiscal, hash chain, cua AEAT ni moviment economic.

Impacte:
El flux manual de curs queda preparat amb una comprovacio prèvia equivalent a la preview Redsys: CLI-only, rebutja `SIF_ENV=production`, connecta nomes a legacy, construeix payload i retorna JSON amb `dry_run=true`. L'execucio real continua pendent de PHP/MySQL de test i dades de preproduccio.

## 2026-06-06 - Fase 11 transferencies manuals: processador manual de curs preparat

Decisio:
Crear `sif/scripts/process-manual-course.php` com a eina CLI de preproduccio per executar el flux de curs manual: rep `IDPAG`, import, data de moviment, referencia/banc/notes/usuari opcionals i crida `ManualCourseInvoiceService`.

Motiu:
Despres de la preview cal una eina controlada per fer la prova real `SIF-PAY-001` sense tocar encara la pantalla de `Passar pagaments`. Aquesta eina permet emetre al SIF amb `issueInvoice(payment)` i revisar idempotencia abans de qualsevol integracio d'intranet.

Impacte:
El script rebutja `SIF_ENV=production`, construeix serveis SIF sense Composer, no depen de Redsys i exposa `legacy_sync_executed`. La sincronitzacio legacy nomes s'executa si es passa `--sync-legacy` i el resultat SIF es `ok=true`; per defecte no escriu a la BD antiga.

## 2026-06-06 - Fase 11 transferencies manuals: preflight manual de curs preparat

Decisio:
Crear `sif/scripts/preflight-manual-course.php` com a comprovacio de nomes lectura abans d'executar la preview o el processador manual de curs.

Motiu:
El flux manual de curs no necessita clau Redsys ni taula de notificacions Redsys, pero si necessita BD SIF preparada, BD legacy configurada, taules fiscals/economiques minimes, taules `inscripcions` i `curs`, i seed de `fiscal_chain_state`.

Impacte:
El preflight manual separa readiness de Redsys i readiness de `Passar pagaments`. Rebutja produccio, retorna JSON amb `checks`, `failed` i `errors`, no construeix cap servei d'emissio i no crea factures ni pagaments. L'execucio real continua pendent de PHP/MySQL de test.

## 2026-06-07 - Fase 11 packs: snapshot i payload fiscal preparats

Decisio:
Afegir `LegacyPackSnapshotRepository` i `LegacyPackInvoicePayloadBuilder` com a primer tall de packs, sense scripts ni endpoints encara. El snapshot carrega inscripcions `TIPUS_INSC = 'P'` per `IDPAG`, detecta `PACK|{ID_PACK}` a `OBSERVACIONS`, consulta `info_pack` i emparella cada inscripcio amb el seu curs. El payload declara `source_type = PACK`, crea una linia per curs i conserva relacions `PACK` i `INSCRIPCIO`.

Motiu:
Els packs son el primer canal especial perquè afegeixen diverses linies sense canviar encara receptor ni visibilitat. Separar snapshot i builder permet validar fiscalment imports, descompte i relacions abans d'afegir preview/processador o tocar callbacks.

Impacte:
La idempotencia Redsys queda preparada per `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` sense trencar el cas de curs normal, que continua usant `CURS` per defecte. El descompte del pack normal queda congelat a la segona linia amb `DESC_ORIGEN = PACK`, `DESC_MODE = PERCENT`, `DESC_PCT = 25.00` i `DESC_IMPORT`; si el snapshot aporta imports fiscals explicits, es respecten. L'execucio real continua pendent de PHP/MySQL de test i dades legacy de preproduccio.

## 2026-06-07 - Fase 11 packs: circuit Redsys manual de preproduccio

Decisio:
Afegir `RedsysPackInvoiceService` i els scripts `sif/scripts/preflight-redsys-pack.php`, `sif/scripts/preview-redsys-pack.php` i `sif/scripts/process-redsys-pack.php`. El processador rep un `DS_ORDER` ja registrat com a `VALIDATED`, carrega el pack legacy, construeix el payload fiscal `PACK`, crida `issueInvoice(payment)` i permet `--sync-legacy` nomes despres d'un resultat SIF `ok=true`.

Motiu:
Despres del snapshot/payload de pack cal una prova controlada equivalent al curs normal abans de tocar el callback automatic. El pack necessita revisar especialment les dues linies, el descompte `PACK`, `fact_rels`, `payment_transaction` i la sincronitzacio de les dues inscripcions.

Impacte:
El flux de pack queda preparat per preproduccio en quatre passos: `preflight-sif.php`, `preflight-redsys-pack.php`, `preview-redsys-pack.php DS_ORDER` i `process-redsys-pack.php DS_ORDER [--sync-legacy]`. El callback real continua sense cridar aquest servei i no s'ha activat cap endpoint automatic de pack. L'execucio real continua pendent de PHP/MySQL de test, BD legacy de test i notificacio Redsys validada.

## 2026-06-07 - Fase 11 packs: circuit manual de transferencia

Decisio:
Afegir `ManualPackInvoicePayloadBuilder`, `ManualPackInvoiceService` i els scripts `sif/scripts/preflight-manual-pack.php`, `sif/scripts/preview-manual-pack.php` i `sif/scripts/process-manual-pack.php`. El flux rep `IDPAG`, import, data de moviment i referencia/banc opcionals, carrega el pack legacy, construeix `issueInvoice(payment)` amb `source_channel = INTRANET` i permet `--sync-legacy` nomes despres d'un resultat SIF `ok=true`.

Motiu:
Els packs poden entrar per `Passar pagaments` igual que un curs normal. Cal una eina equivalent al curs manual abans d'integrar la pantalla real, pero preservant la factura multi-linia, el descompte `PACK`, les relacions `PACK`/`INSCRIPCIO` i la idempotencia separada de Redsys.

Impacte:
La idempotencia queda fixada com `TRANSFERENCIA|PACK|IDPAG:{IDPAG}|REF:{REFERENCIA_BANCARIA}` quan hi ha referencia i amb fallback per data/import/banc quan no n'hi ha. El builder exigeix que l'import manual coincideixi amb el total fiscal del pack abans de crear el cobrament inicial, evitant marcar com a cobrat un pack parcial. L'execucio real continua pendent de PHP/MySQL de test, BD legacy de test i validacio operativa de `Passar pagaments`.

## 2026-06-08 - Fase 11 grups: snapshot i payload fiscal preparats

Decisio:
Afegir `LegacyGroupSnapshotRepository` i `LegacyGroupInvoicePayloadBuilder` com a primer tall de grups. El snapshot carrega inscripcions `TIPUS_INSC = 'G'` per `IDPAG`, responsable fiscal des de `respGrups` i dades de curs per participant. El payload declara `source_type = GRUP`, crea una linia per participant, relacio `GRUP`, relacions `INSCRIPCIO` i fixa `visible_alumne = 0`.

Motiu:
Els grups son el pas posterior als packs perquè afegeixen receptor fiscal diferent i risc de privacitat. El document tancat exigeix factura única per pagament real, linia per participant i que els participants no vegin la factura completa si conte altres persones.

Impacte:
La idempotencia Redsys queda preparada per `REDSYS|GRUP|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` gracies al `source_type = GRUP`. El builder no imprimeix el DNI del participant a concepte o detall i respecta imports/descomptes explicits si el snapshot els porta. El SQL final de `descomptes_grup` continua pendent de validacio abans d'activar circuits reals Redsys/manuals de grup.

## 2026-06-08 - Fase 11 regals: snapshot i payload fiscal preparats

Decisio:
Afegir `LegacyGiftSnapshotRepository` i `LegacyGiftInvoicePayloadBuilder` com a primer tall de regals. El snapshot carrega `regal` per `ID` o `CODI`, incloent comprador, curs, import, codi regal, `FACT_REL`, origen, desti i observacions. El payload declara `source_type = REGAL`, crea factura al comprador, una linia fiscal `REGAL`, relacio `REGAL` i `visible_alumne = 0`.

Motiu:
El cas de regal separa comprador fiscal i destinatari futur. La compra genera factura al comprador; el bescanvi posterior del codi crea o vincula una inscripcio sense factura nova. Cal evitar que la targeta regal comercial o l'enllac del destinatari substitueixin el PDF fiscal immutable.

Impacte:
La idempotencia base queda `LEGACY|REGAL|ID:{ID}` i la composicio Redsys queda preparada com `REDSYS|REGAL|IDPAG:NULL|ORDER:{DS_ORDER}`. El builder no crea inscripcio del destinatari ni escriu `FACT_REL`; qualsevol actualitzacio legacy queda pendent d'un servei de sincronitzacio post-SIF i de validar el SQL real de `regal`, `FACT_REL`, `ORIGEN`, `DESTI` i `CODI`.

## 2026-06-08 - Fase 11 USOC: snapshot i payload fiscal preparats

Decisio:
Afegir `LegacyUsocSnapshotRepository` i `LegacyUsocInvoicePayloadBuilder` com a primer tall d'USOC. El snapshot carrega una inscripcio per `IDPAG`, exigeix `TIPUS_DESC = 4` i `VALID_DESC = 1`, carrega el curs i congela l'import pagat per l'alumne. El builder genera dos payloads separats: `USOC_ALUMNE` per la factura de l'alumne i `USOC_ENTITAT` per la factura de l'entitat.

Motiu:
USOC no es un descompte intern simple quan hi ha dos pagadors reals. L'alumne rep factura per la part pagada i l'entitat USOC rep factura ordinaria per la diferencia assumida, vinculada internament a la inscripcio i a la factura de l'alumne. No s'han d'inventar dades fiscals de l'entitat si no estan confirmades.

Impacte:
La idempotencia de l'alumne queda preparada per `REDSYS|USOC_ALUMNE|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`. La factura de l'entitat queda `INTRANET|USOC_ENTITAT|ID_INSC:{ID_INSC}|FACT_ALUMNE:{UUID_FACTURA_ALUMNE}`, requereix receptor fiscal explicit i queda pendent de cobrament fins que s'hi registri un pagament. L'activacio real continua pendent de PHP/MySQL de test, dades fiscals completes d'USOC i prova de privacitat.

## 2026-06-08 - Fase 11 codis promocionals: snapshot de descompte congelat a `factura_linia`

Decisio:
Ampliar `LegacyCourseInvoicePayloadBuilder` perquè el curs normal pugui rebre un bloc `discount` ja validat pel canal i congelar-lo dins el payload fiscal. El builder trasllada `import_base`, `discount`, `total` i camps `DESC_*` de linia: origen, mode, id de descompte temporal, codi promocional, percentatge, import, text visible i motiu intern.

Motiu:
Els codis promocionals no son un canal fiscal propi. Ecommerce/intranet validen vigencia, DNI, us i import abans del pagament; el SIF no ha de revalidar `promocions` ni recalcular el codi, pero si ha de conservar la foto fiscal que justifica el preu final.

Impacte:
La factura de curs normal pot conservar `CODI_PROMO` o `PROMOCIO_TEMPORAL` a `factura_linia` i queda immutable encara que el codi caduqui o quedi usat despres. Queden pendents el SQL final de `promocions`/`descomptes.TIPUS` 11-99, la decisio de visibilitat del codi concret al PDF i l'execucio real amb PHP/MySQL de test.

## 2026-06-08 - Fase 11 regals: circuit Redsys manual de preproduccio

Decisio:
Afegir `RedsysGiftInvoiceService` i els scripts `sif/scripts/preflight-redsys-gift.php`, `sif/scripts/preview-redsys-gift.php` i `sif/scripts/process-redsys-gift.php`. El processador rep un `DS_ORDER` ja `VALIDATED` i un regal identificat explicitament per `--gift-id=ID` o `--gift-code=CODI`.

Motiu:
Les notificacions Redsys de regal no tenen `IDPAG`, i no s'ha d'inferir el regal a partir de l'ordre TPV. Per evitar enllaços fràgils, la prova de preproduccio exigeix indicar quin registre `regal` es factura i comprova que l'import validat per Redsys coincideixi amb `regal.IMPORT`.

Impacte:
El flux de regal queda preparat per preproduccio en quatre passos: `preflight-sif.php`, `preflight-redsys-gift.php`, `preview-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)` i `process-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)`. En aquest tall no hi ha `--sync-legacy`, no s'actualitza `regal.FACT_REL` i no es crea cap inscripcio del destinatari; el bescanvi posterior queda pendent de validacio separada.

## 2026-06-10 - Fase 11 regals: circuit manual de `Passar pagaments`

Decisio:
Afegir `ManualGiftInvoicePayloadBuilder`, `ManualGiftInvoiceService` i els scripts `sif/scripts/preflight-manual-gift.php`, `sif/scripts/preview-manual-gift.php` i `sif/scripts/process-manual-gift.php`. El processador rep un regal identificat per `--gift-id=ID` o `--gift-code=CODI`, import, data de moviment i referencia/banc opcionals, i construeix `issueInvoice(payment)` amb `source_channel = INTRANET`.

Motiu:
La pantalla `Passar pagaments` pot localitzar regals per codi i registrar cobraments manuals. Aquest flux no ha d'editar factures antigues ni actualitzar `regal.FACT_REL` en el mateix tall: el SIF ha d'emetre la factura al comprador i registrar el cobrament de manera idempotent abans de qualsevol sincronitzacio legacy posterior.

Impacte:
El regal manual queda preparat per preproduccio amb preflight sense Redsys, preview dry-run i processador CLI no productiu. L'import manual ha de coincidir amb `regal.IMPORT`; no hi ha `--sync-legacy`, no s'actualitza `regal.FACT_REL` i no es crea cap inscripcio del destinatari.

## 2026-06-10 - Fase 11 USOC: circuit Redsys d'alumne de preproduccio

Decisio:
Afegir `RedsysUsocInvoiceService` i els scripts `sif/scripts/preflight-redsys-usoc.php`, `sif/scripts/preview-redsys-usoc.php` i `sif/scripts/process-redsys-usoc.php`. El processador rep un `DS_ORDER` ja `VALIDATED` i l'import assumit per USOC com a `--usoc-amount=AMOUNT`, carrega la inscripcio USOC validada (`TIPUS_DESC = 4`, `VALID_DESC = 1`) i emet nomes la factura `USOC_ALUMNE` amb cobrament Redsys inicial.

Motiu:
USOC necessita doble factura, pero la factura de l'entitat no s'ha d'emetre sense dades fiscals completes i confirmades. Separar el tall d'alumne permet provar idempotencia, relacio visible de l'alumne, descompte USOC congelat i cobrament Redsys sense inventar receptor fiscal de l'entitat.

Impacte:
El circuit queda preparat per preproduccio amb `preflight-redsys-usoc.php`, `preview-redsys-usoc.php DS_ORDER --usoc-amount=AMOUNT` i `process-redsys-usoc.php DS_ORDER --usoc-amount=AMOUNT`. El resultat retorna `entity_invoice_pending` amb `student_invoice_uuid`, import d'entitat i requisit de billing explicit; no hi ha `--sync-legacy`, no es crea `USOC_ENTITAT` en aquest tall i l'execucio real continua pendent de PHP/MySQL de test i dades fiscals completes d'USOC.

## 2026-06-12 - Fase 11 USOC: circuit d'entitat amb billing explicit

Decisio:
Afegir `UsocEntityInvoiceService` i els scripts `sif/scripts/preflight-usoc-entity.php`, `sif/scripts/preview-usoc-entity.php` i `sif/scripts/process-usoc-entity.php`. El circuit rep un JSON explicit amb `idpag`, `student_amount`, `amount`, `student_invoice_uuid` i `billing`, carrega la inscripcio USOC validada i genera el payload/factura `USOC_ENTITAT` amb `source_channel = INTRANET`.

Motiu:
La factura d'entitat USOC no pot reutilitzar dades implicites ni inventar receptor fiscal. Separar-la en un circuit propi permet validar la factura pendent de cobrament, vinculada a la factura de l'alumne, sense barrejar-la amb el cobrament Redsys ni amb cap sincronitzacio legacy automatica.

Impacte:
El processador `process-usoc-entity.php --payload-file=payload.json` rebutja produccio, no registra pagaments, no crea `payment_transaction`/`payment_allocation` i no sincronitza legacy. La factura queda `PENDING` fins que es registri un cobrament posterior per `registerPayment()`. L'execucio real continua pendent de PHP/MySQL de test, dades fiscals completes d'USOC i revisio del payload en preproduccio.

## 2026-06-12 - Fase 11 codis promocionals: snapshot explicit en Redsys curs

Decisio:
Afegir a `RedsysCourseInvoiceService` un paràmetre opcional `discountSnapshot` i permetre que `preview-redsys-course.php` i `process-redsys-course.php` rebin `--discount-file=discount.json`. El fitxer JSON representa el resultat fiscal del codi promocional o promocio temporal ja validat pel canal abans de Redsys.

Motiu:
El SIF ja sap congelar `DESC_*` a `factura_linia`, pero encara no esta tancat el SQL final de `promocions` i `descomptes.TIPUS` 11-99. Acceptar un snapshot explicit permet provar el comportament fiscal immutable sense inventar consultes ni recalcular codis dins el SIF.

Impacte:
El circuit de preproduccio pot revisar i processar un curs Redsys amb descompte promocional mitjancant `preview-redsys-course.php DS_ORDER --discount-file=discount.json` i `process-redsys-course.php DS_ORDER --discount-file=discount.json`. L'activacio real continua pendent de PHP/MySQL de test, creacio del snapshot des d'ecommerce/intranet i validacio SQL final.

## 2026-06-12 - Fase 11 codis promocionals: snapshot explicit en curs manual

Decisio:
Afegir a `ManualCourseInvoiceService` un parametre opcional `discountSnapshot` i permetre que `preview-manual-course.php` i `process-manual-course.php` rebin `--discount-file=discount.json`. El mateix JSON de snapshot fiscal es pot usar quan el descompte ja ha estat validat abans d'un cobrament manual o transferencia des de la intranet.

Motiu:
Els codis promocionals no depenen del canal de cobrament. Si el pagament entra per `Passar pagaments`, el SIF ha de congelar la mateixa foto fiscal que en Redsys: base, import descomptat, percentatge/id/codi, total final i text visible, sense recalcular ni revalidar `promocions`.

Impacte:
El circuit manual de curs pot revisar i processar un descompte promocional amb `preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE --discount-file=discount.json` i `process-manual-course.php IDPAG AMOUNT MOVEMENT_DATE --discount-file=discount.json`. L'activacio real continua pendent de PHP/MySQL de test, integracio amb el punt real de creacio del snapshot i validacio SQL final.

## 2026-06-12 - Fase 11 codis promocionals: lector compartit de `discount.json`

Decisio:
Afegir `DiscountSnapshotFileReader` com a lector compartit del fitxer `discount.json` i substituir les funcions locals duplicades dels scripts de curs Redsys/manual.

Motiu:
El contracte del snapshot de descompte ha de tenir una sola frontera tècnica abans d'arribar al builder fiscal. Això evita divergències entre preview i processador, i entre Redsys i `Passar pagaments`, sense canviar el criteri fiscal ni afegir consultes a `promocions`.

Impacte:
Els scripts de curs mantenen `--discount-file=discount.json`, pero la lectura i la validacio basica de fitxer/JSON passen per una classe compartida amb prova unitària. L'execucio real de la prova continua pendent de PHP al PATH.

## 2026-06-12 - Fase 11 devolucions manuals: `REFUND` contra factura existent

Decisio:
Afegir `ManualRefundPayloadBuilder`, `ManualRefundService` i els scripts `sif/scripts/preview-manual-refund.php` i `sif/scripts/process-manual-refund.php`. El circuit localitza una factura SIF existent per `UUID_FACTURA` o `NUM_VISIBLE` i registra una devolucio economica amb `PaymentService::registerPayment()`.

Motiu:
Les devolucions no han de modificar la factura emesa ni crear una factura nova per si mateixes. El moviment economic de retorn ha de quedar a `payment_transaction` amb `TIPUS_MOVIMENT = REFUND` i a `payment_allocation`, mantenint intactes numero fiscal, hash chain i registre fiscal. Si fiscalment cal rectificativa, aquesta es un flux separat.

Impacte:
El circuit de preproduccio pot revisar i processar devolucions amb `preview-manual-refund.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE` i `process-manual-refund.php ...`. La idempotencia queda `REFUND|REF:{REFERENCIA}` o fallback per factura/data/import/banc, i `ESTAT_COBRAMENT` passa a `PARTIALLY_REFUNDED` o `REFUNDED` segons l'import retornat. L'execucio real continua pendent de PHP/MySQL de test i validacio operativa de `Passar pagaments`.

## 2026-06-12 - Fase 11 compensacio/saldo: `credit_balance` i `COMPENSATION`

Decisio:
Afegir `CreditBalancePayloadBuilder`, `CreditBalanceRepository`, `CreditBalanceService` i els scripts `sif/scripts/preview-credit-balance.php`, `process-credit-balance.php`, `preview-credit-compensation.php` i `process-credit-compensation.php`. El circuit separa crear saldo a `credit_balance` d'aplicar-lo posteriorment a una factura SIF existent.

Motiu:
El saldo no es una rebaixa silenciosa ni una edicio d'una factura emesa. Quan neix un saldo, queda com a dret economic del titular; quan s'utilitza, s'ha de registrar com a moviment economic `COMPENSATION` amb metode `COMPENSACIO`, assignat a factura i sense crear un nou registre fiscal.

Impacte:
La compensacio bloqueja saldo i factura dins la mateixa transaccio, rebutja imports superiors al saldo disponible o al pendent de factura, crea `payment_transaction`/`payment_allocation` amb `CREDIT_COMPENSATION` i resta `IMPORT_DISPONIBLE` nomes en la primera execucio idempotent. Si el saldo queda a zero passa a `USED`; els reintents reutilitzen el mateix `uuid_payment`. L'execucio real continua pendent de PHP/MySQL de test.

## 2026-06-12 - Fase 11 pagaments fraccionats manuals: fraccions com `registerPayment()`

Decisio:
Afegir `ManualInstallmentPaymentPayloadBuilder`, `ManualInstallmentPaymentService` i els scripts `sif/scripts/preview-manual-installment.php` i `process-manual-installment.php`. El circuit registra cada fraccio manual contra una factura SIF existent com un moviment economic propi.

Motiu:
Una factura fraccionada no s'ha de duplicar per cada cobrament. El total fiscal queda a la factura emesa i cada fraccio posterior ha de quedar a `payment_transaction` i `payment_allocation`, amb recalcul de l'estat de cobrament i sense crear nous registres fiscals.

Impacte:
La idempotencia queda `MANUAL|FRACCIO|ID_INSC:{ID_INSC}|DATA:{DATA}|IMPORT:{IMPORT}|USUARI:{USUARI}`. El moviment usa `method = MANUAL`, `source_channel = INTRANET` i assignacio `INSTALLMENT_PAYMENT`; dues fraccions successives poden portar una factura de `PARTIAL` a `PAID`, i el reintent d'una mateixa fraccio reutilitza el `uuid_payment`. L'execucio real continua pendent de PHP/MySQL de test i integracio amb pantalla/URL final.

## 2026-06-12 - Fase 11 rectificatives manuals: factura serie `R`

Decisio:
Afegir `ManualRectificationPayloadBuilder`, `RectificationRepository`, `ManualRectificationService` i els scripts `sif/scripts/preview-manual-rectification.php` i `process-manual-rectification.php`. El circuit crea una factura rectificativa SIF nova contra una factura SIF existent.

Motiu:
Una factura emesa no es corregeix modificant receptor, concepte o import. La rectificativa ha d'entrar a la hash chain com una factura nova serie `R`, conservar un vincle directe amb la factura rectificada i deixar motiu/mode estructurats per auditoria i revisio fiscal.

Impacte:
El payload de rectificativa usa `series = R`, `type = R1`, receptor copiat de l'original, linia/totals amb import positiu o negatiu, relacio `RECTIFIES` i idempotencia per factura/mode/motiu/import o referencia explicita. El processador insereix `factura_rectificacio`, marca l'original com `RECTIFIED`, no registra pagament i no sincronitza legacy en aquest tall. L'execucio real continua pendent de PHP/MySQL de test i validacio fiscal puntual abans de produccio.

## 2026-06-12 - Fase 11 factura manual: circuit CLI `issueInvoice()`

Decisio:
Afegir `ManualInvoicePayloadBuilder`, `ManualInvoiceService` i els scripts `sif/scripts/preview-manual-invoice.php` i `process-manual-invoice.php`. El circuit normalitza factures manuals iniciades per intranet i les envia a `InvoiceService::issueInvoice()`.

Motiu:
La factura manual no pot inserir-se directament a `web.factures` ni saltar-se numeracio, hash chain, cua AEAT o registre fiscal. Ha de tenir el mateix contracte que qualsevol factura SIF: snapshot fiscal, linies estructurades, usuari intern, idempotencia i, si neix cobrada, bloc `payment` dins la mateixa operacio fiscal.

Impacte:
El payload queda amb `source_channel = INTRANET`, `source_type = MANUAL`, `series = A`, `type = F1`, usuari intern obligatori i idempotencia per referencia o fallback `INTRANET|MANUAL|USUARI:{USUARI}|DATA:{DATA}|HASH:{HASH}`. El processador rebutja produccio, no sincronitza legacy i no crida `registerPayment()` directament; el pagament inicial, quan existeix, es registra via `issueInvoice(payment)`. L'execucio real continua pendent de PHP/MySQL de test, pantalla final, permisos, correus/enllac segur i evidencies de preproduccio.

## 2026-06-12 - Fase 11 migracio historica: `NO_VERIFACTU`

Decisio:
Afegir `HistoricalInvoicePayloadBuilder`, `HistoricalInvoiceMigrationRepository`, `HistoricalInvoiceMigrationService` i els scripts `sif/scripts/preview-historical-invoice-migration.php` i `process-historical-invoice-migration.php`. El circuit importa factures historiques a les taules de consulta SIF amb marca `NO_VERIFACTU`.

Motiu:
Les factures historiques han de poder consultar-se des del SIF i relacionar-se amb inscripcions, pagaments o rectificatives futures, pero no es poden convertir retroactivament en registres VERI*FACTU. Per tant, la migracio conserva numero visible, import, receptor, linies i relacions, pero no crea hash chain, registre fiscal ni cua AEAT.

Impacte:
La importacio usa `ESTAT_FACTURA = HISTORICAL`, `ESTAT_AEAT = NO_VERIFACTU`, `SOURCE_CHANNEL = MIGRACIO`, relacio `HISTORIC_LINK` i document antic opcional amb hash i estat `ARCHIVED`. El repositori no toca `factura_registres`, `fiscal_queue`, `fiscal_sequence` ni `fiscal_chain_state`. L'execucio real continua pendent de PHP/MySQL de test, informe agregat de control, validacio de totals per any/serie i criteri final de cutover de numeracio productiva.

## 2026-06-12 - Verificacio final dels fluxos fiscals especials

Decisio:
Mantenir l'estat dels fluxos fiscals especials com a `IMPLEMENTACIO TECNICA PREPARADA` fins que es puguin executar el runner PHP i les proves amb BD SIF/legacy de test.

Motiu:
La passada final ha pogut verificar estructura, fitxers, absencia de whitespace final i absencia de crides indegudes en scripts critics, pero no pot demostrar execucio de tests perque `php` no esta instal.lat o no esta disponible al PATH de l'entorn.

Impacte:
Els fluxos de compensacio/saldo, pagaments fraccionats, rectificatives, devolucions, baixes/canvis de curs, factura manual i migracio historica queden preparats per preproduccio, no per activacio productiva directa. El desbloqueig operatiu requereix executar `php sif/tests/run-tests.php`, preflights i previews/processadors amb dades de test.

## 2026-06-14 - Inventari documental dels fluxos fiscals especials

Decisio:
Actualitzar `04-fluxos-facturacio.md` i `11-inventari-canvis-pendents.md` perquè deixin clar que els fluxos fiscals especials ja estan tancats a nivell de criteri i preparats a nivell de circuit tecnic, tot i que encara no estan executats en preproduccio.

Motiu:
El codi i el pla tecnic ja diferenciaven compensacio/saldo, pagaments fraccionats, rectificatives, devolucions, baixes, canvis de curs, factura manual i migracio historica, pero l'inventari encara podia llegir-se com si el criteri estigues obert. Cal separar "pendent de criteri" de "pendent d'execucio amb PHP/MySQL de test".

Impacte:
El checklist marca `11-inventari-canvis-pendents.md` com al dia per aquests fluxos. La posada en marxa continua bloquejada fins que es puguin executar proves, preflights, previews/processadors i evidencies reals de preproduccio.

## 2026-06-14 - Fase 11 grups: circuits Redsys i manual de preproduccio

Decisio:
Afegir els orquestradors i scripts de grup: `RedsysGroupInvoiceService`, `ManualGroupInvoicePayloadBuilder`, `ManualGroupInvoiceService`, `preflight-redsys-group.php`, `preview-redsys-group.php`, `process-redsys-group.php`, `preflight-manual-group.php`, `preview-manual-group.php` i `process-manual-group.php`.

Motiu:
El snapshot i payload de grup ja estaven preparats, pero faltava el circuit complet que consumeix una notificacio Redsys `VALIDATED` o una transferencia validada a `Passar pagaments`. El cas de grup necessita garantir receptor `respGrups`, una linia per participant, relacions no visibles a alumne i sync legacy nomes posterior a l'exit SIF.

Impacte:
El grup queda en estat `IMPLEMENTACIO TECNICA PREPARADA`. Encara no queda activat per produccio: cal executar PHP/MySQL de test, revisar `VISIBLE_ALUMNE = 0`, provar reintents idempotents, validar `--sync-legacy` i incorporar el SQL final de `descomptes_grup` abans d'activacio real.

## 2026-06-14 - Fase 11 reclamacio/morositat: cobrament sense factura nova

Decisio:
Afegir `ClaimPaymentPayloadBuilder`, `ClaimPaymentService`, `preflight-claim-payment.php`, `preview-claim-payment.php` i `process-claim-payment.php` per registrar cobraments derivats de reclamacio/morositat sobre una factura SIF existent.

Motiu:
Una reclamacio no rectifica la factura ni genera factura nova. Si el client paga despres de la reclamacio, l'efecte correcte es economic: `registerPayment()` amb `payment_allocation` `CLAIM_PAYMENT`, conservant la factura original intacta.

Impacte:
El flux de morositat/reclamacio passa de `PARCIAL` a `IMPLEMENTACIO TECNICA PREPARADA` a nivell SIF. Queden pendents pantalla, URL controlada de pagament, correus/plantilles de reclamacio, permisos i execucio amb PHP/MySQL de test.

## 2026-06-14 - Go/no-go ampliat per circuits fiscals preparats

Decisio:
Ampliar `sif/scripts/go-no-go-preproduction.php` perquè comprovi tambe els circuits de `credit_balance`, devolucio manual, Redsys USOC, USOC entitat, grup Redsys, grup manual i reclamacio/morositat, a mes de la taula legacy `respGrups`.

Motiu:
Alguns fluxos ja estaven implementats o preparats pero no quedaven bloquejats pel paquet go/no-go. La decisio de preproduccio ha de detectar si falta una peça de circuit abans de provar casos fiscals reals.

Impacte:
La bateria go/no-go continua sent de nomes lectura i no crea factures ni pagaments. El resultat `GO` exigira que aquests circuits estiguin presents i que l'entorn/BD compleixi els prerequisits minims abans d'un pilot controlat.

## 2026-06-18 - Xat 3: rectificativa, anul·lacio AEAT i subsanacio son fluxos diferents

Decisio:
Substituir el concepte generic antic d'`anul·lar factura` per un decisor fiscal. Segons si existeix factura SIF i segons la causa, el sistema ha de triar cancel·lacio operativa, factura rectificativa, `RegistroAnulacion`, subsanacio, nova alta correcta o incidencia bloquejant.

Motiu:
La documentacio AEAT diferencia la factura rectificativa de l'anul·lacio i la subsanacio de registres. La subsanacio nomes es valida quan la causa no exigeix factura rectificativa. Una baixa, una devolucio o una pantalla amb nom historic d'anul·lacio tampoc impliquen per si soles `RegistroAnulacion`.

Impacte:
`ManualRectificationService` continua cobrint el circuit intern preparat de rectificatives, pero no es considera implementacio de `RegistroAnulacion` ni de subsanacio. Cal crear registres immutables d'anul·lacio/subsanacio, encadenament i huella AEAT, camps `Subsanacion`, `RechazoPrevio` i `SinRegistroPrevio`, cua/resposta AEAT i proves XML/XSD abans de donar el bloc per tancat tecnicament.

## 2026-06-19 - `DS_ORDER` es resol amb una intencio de pagament creada al servidor

Decisio:
Crear `redsys_payment_intent` abans de redirigir a Redsys i usar `DS_ORDER` com a clau unica per recuperar `IDPAG`, tipus/origen, import, divisa, terminal i snapshot fiscal. El callback no pot confiar en `IDPAG` ni en imports rebuts per query string. `redsys_payment_intent`, `redsys_notifications` i `payment_transaction` representen respectivament el context previ al TPV, la notificacio rebuda i el moviment economic confirmat.

Motiu:
La signatura Redsys valida les dades del TPV, pero el vincle amb l'origen funcional ha de quedar creat al servidor abans del pagament. Sense aquest mapa, el callback actual depen d'un `IDPAG` extern i no pot seleccionar de manera segura l'orquestrador de curs, pack, grup, regal o USOC.

Impacte:
La migracio SQL i el contracte documental queden preparats. Un `DS_ORDER` repetit nomes es idempotent si coincideixen import, resposta, signatura i intencio; qualsevol discrepancia es una incidencia bloquejant. Encara cal implementar repositori/servei, integrar els punts de creacio de Redsys, connectar el callback als orquestradors i executar les proves amb PHP/MySQL.

## 2026-09-22 - Fixar `main` com a inventari provisional i no com a base validada

Decisió:
Prendre el commit `e71958b` només com a fotografia provisional del material
recopilat. Abans de desenvolupar més cal resoldre els conflictes versionats,
reconciliar la branca UML encara divergent, regenerar el catàleg i els hashes,
reexecutar lint/proves/migracions sobre el `HEAD` actual i decidir el tractament
de les còpies `codi-drive` amb possibles dades personals.

Motiu:
El volum incorporat és substancial, però les evidències verdes són anteriors a
81 canvis de runtime/proves/SQL. UC-77 trenca el contracte de 21 apartats, les
142 fitxes acumulen 1.874 hashes desactualitzats, `ConnectionFactoryTest.php`
conté marcadors de conflicte i el commit d'arxius actuals ha incorporat 589 PDF
entre 3.356 fitxers de snapshots.

Impacte:
No s'ha de confondre presència de codi, UML o snapshots amb validació del
producte. Queden bloquejats nous commits amplis, publicació de
`intranet-collaboradors` i qualsevol `GO` fins a netejar la base, revisar dades,
obtenir una suite actual reproduïble i tornar a establir traçabilitat coherent.

## 2026-09-22 - Retirar artefactes PDF i rutes de certificat del `HEAD`

Decisió:
Eliminar els 589 PDF versionats i els 476 fitxers amb `certificat` a la ruta,
amb un total de 1.065 fitxers sense solapament. No incloure en aquesta retirada
la còpia local no versionada `intranet-collaboradors`.

Motiu:
Els artefactes no són necessaris per compilar el SIF i poden contenir dades o
evidències que requereixen una política de custòdia diferent del repositori de
codi.

Impacte:
Les eliminacions queden preparades a l'índex mitjançant `git rm`, sense commit
ni push. Els objectes continuen a l'historial Git; qualsevol purga de
l'historial remot requereix una decisió separada perquè reescriu commits i
afecta totes les còpies del repositori.

## 2026-06-20 - Contracte executable i operacio del circuit asincron Redsys
## 2026-06-19 - Els callbacks Redsys es processen amb una cua asincrona propia

Decisio:
El callback bloqueja `redsys_payment_intent`, compara import/divisa/terminal, persisteix `redsys_notifications` i crea un unic job dins una transaccio curta. El worker reclama amb `FOR UPDATE`, processa exclusivament el snapshot congelat i persisteix `PROCESSED`, `RETRY` o `INCIDENT`.

Motiu:
La resposta a Redsys no pot dependre de facturacio ni de consultes legacy. Un duplicat nomes es coherent si import, resposta, divisa, terminal, versio i hash coincideixen; una contradiccio retorna `409` i obre incidencia.

Impacte:
Queden implementats `CURS`, `PACK`, `GRUP`, `REGAL` i `USOC_ALUMNE`, worker CLI finit i preflight de nomes lectura. REGAL usa ID numeric congelat, USOC conserva `entity_amount` i la sincronitzacio legacy automatica continua exclosa. L'activacio productiva segueix sotmesa al go/no-go de preproduccio.

## 2026-09-23 - Separar DTO de pantalla i payload fiscal SIF

Decisio:
Els endpoints interns no acceptaran directament el payload fiscal complet des del navegador. La UI envia un DTO limitat; el servidor autentica, autoritza, valida i deriva actor, canal, serie, tipus de moviment, assignacions i relacions abans de cridar el servei SIF.

Motiu:
Exposar camps interns permetria manipular identitat, accio fiscal, receptor copiat o relacions. A mes, reenviar totes les dades durant `confirm` permetria canviar l'operacio despres del preview.

Impacte:
`15-contractes-api-pantalles-internes.md` fixa DTO, mapatges, errors i proves. `confirm` nomes rep el token de preview i la confirmacio; el servidor recupera la instantania congelada, revalida estat i aplica idempotencia. Els endpoints de baix nivell continuen fora de l'abast directe del navegador.


## 2026-09-23 - Correlació AEAT i idempotència de correccions

Decisió:
Una referència fiscal reutilitzada ha de coincidir amb la factura i el
contingut original de la petició. Les respostes AEAT es correlacionen per
emissor, número/data, tipus d'operació i marques de correcció. Un duplicat
requereix revisió i no es converteix automàticament en acceptació.

Motiu:
La regressió ha reproduït la reutilització incorrecta d'una anul·lació
d'una altra factura per una comprovació situada al mètode equivocat.
La mateixa identitat fiscal també pot tenir alta, subsanacions i anul·lació;
comprovar només número/data és insuficient.

Impacte:
Corregits FiscalRecordRepository i ResponseParser, amb proves executables.
La huella oficial queda al snapshot AEAT; el digest HASH_FACT continua
essent intern. El transport candidat segueix limitat a proves externes i
la declaració no es fa signable amb evidències sintètiques.
El preflight usa les dependències reals de cURL/mTLS i valida el certificat
localment sense afirmar confiança AEAT, revocació o representació.


Validació d'aquesta decisió:
363 proves de regressió correctes, cap fallada, en una BD de revisió separada.
El manifest local 2026-09-23-aeat-review-source-manifest.json identifica els
fitxers verificats; cap hash ha canviat durant l'execució final.
El preflight bloquejat per dependències/configuració reals és un resultat
esperat i no es reetiqueta com a preparació per producció.


## 2026-09-23 - Preview fiscal consultiu i confirmació revalidada

Decisió:
Preview i confirmació CLI comparteixen parser i validació de transicions.
El preview oficial ha de validar XML/XSD sense cap INSERT/UPDATE. Hora,
ordre i huella proposats són provisionals i la confirmació els regenera
sota el bloqueig transaccional, després de comprovar idempotència i estat.
No es tracta del token de confirmació de la futura UI.

Motiu:
Els CLI antics no admetien corrected_fields ni totes les variants d'anul·lació
i el preview podia acceptar operacions que el servei rebutjava. L'àlies de
subsanació SIN_REGISTRO_PREVIO ha d'exigir rebuig previ com RECHAZO_PREVIO.

Impacte:
Afegits parser i validador compartits i proves que llancen els processos CLI
reals. El contingut JSON, no la ruta del fitxer, determina la petició idempotent.
Les columnes de resum AEAT de la cua es poblen des de la resposta; el registre
conserva el detall complet. cURL local activat, certificat real encara pendent.

Validació final:
371 proves de regressió correctes i cap fallada. No hi ha canvis de hashes
de codi durant la prova. El preflight confirma cURL actiu i manté el bloqueig
per certificat usable i directori privat no configurats; no hi ha enviament AEAT.

## 2026-09-24 — Verificació executable de la integritat AEAT

Decisió: bloquejar XmlCodec si falta un recurs del manifest o no coincideix
el seu SHA-256; normalitzar els recursos locals a UTF-8 sense BOM i LF.
Afegir un verificador de només lectura dels intents desats, amb estats
RESPONSE_RECORDED, INCOMPLETE, FAILED_ATTEMPT i INVALID. Només el primer
amb integritat correcta retorna codi de sortida zero.
Motiu: un manifest merament informatiu no detectava alteracions abans de
validar XML; la presència de fitxers tampoc acreditava un intent complet.
Límit: els hashes no autentiquen una substitució conjunta de fitxers i manifest,
ni constitueixen WORM o evidència d'acceptació AEAT. No hi ha enviaments externs.

Verificació final 2026-09-24: **374 passed, 0 failed** a la BD aïllada
sif_test_aeat_review_20260923; cap canvi dels hashes de codi durant la regressió.
Logs locals a sif/var/evidence/2026-09-24-aeat-integrity-regression.log,
2026-09-24-aeat-integrity-preflight.json i
2026-09-24-aeat-integrity-source-manifest.json. Les 26 proves específiques
consten a 2026-09-23-aeat-integrity-tests.log. Cap enviament AEAT ni commit/push.

## 2026-09-24 — Espera persistent després d'un intent fallit

Decisió: renovar NEXT_SEND_AT a 60 segons des del final quan el transport
llança una excepció o retorna un temps d'espera invàlid, abans de propagar
l'error al processador de retries. Mantenir també l'espera preventiva inicial.
Motiu: comptar únicament des de l'inici consumia part de l'espera durant la
petició fallida. El límit global ha de continuar actiu després d'un reinici.
Validació: 27 proves específiques correctes; timeout i espera invàlida
coberts amb transport sintètic i MySQL aïllat. No s'ha enviat res a AEAT.
Límit: una terminació abrupta no executa el catch; conserva el termini
preventiu i necessita la recuperació explícita del lock antic.

## 2026-09-24 — Respectar la marca de revisió de la resposta

Decisió: propagar requires_review des de la resposta al resultat del processador
i consultar-la al worker, inclosa duplicate=true, encara que l'estat sigui ACCEPTED.
Motiu: el parser podia marcar revisió, però el worker només examinava l'estat fiscal.
La incidència AEAT_REVIEW conserva la necessitat d'actuació sense canviar l'estat
retornat ni reenviar una resposta ja processada. No resol la conciliació del duplicat.
Validació: 28 proves específiques correctes amb transport sintètic i BD aïllada;
cap enviament extern. Lint dels tres fitxers PHP correcte.

## 2026-09-24 — Verificació de concurrència i límit després de recuperació

Decisió: conservar el comportament actual del worker i cobrir-lo amb proves
sobre dues connexions MySQL independents. El lock global precedeix qualsevol
recuperació; els intents consumits no es reinicien en recuperar un lock antic.
La recuperació amb pressupost esgotat bloqueja la cadena i obre una incidència,
sense reenviar ni passar al registre següent. Repetir el cicle no duplica la incidència.
Validació: 30 proves específiques correctes amb transport sintètic, BD aïllada
i cap enviament AEAT. No acredita recuperació del servidor davant una caiguda real.

## 2026-09-24 — Evidència vigent de la infraestructura local

Decisió:
Donar per validat l'entorn local de proves amb PHP 8.4.25/MySQL 8.4.10 i el conjunt actual de deu migracions, basant-se en instal·lació buida, reexecució, lint de 328 PHP i suite completa de 378 proves sense fallades. Conservar els logs i el manifest de fonts a sif/var/evidence/2026-09-24-infra-*.

Motiu:
Les execucions anteriors no cobrien tots els canvis incorporats posteriorment. La comprovació del 24 de setembre cobreix el codi actual, inclosos els controls d'infraestructura i les darreres proves AEAT; no hi ha hagut canvis de fonts durant la validació.

Impacte:
El preflight local és satisfactori. El go/no-go continua NO-GO per manca de clau Redsys i de les quatre taules legacy requerides. Un GO del script només té abast technical_preflight_only i production_authorized=false; no substitueix qualificació externa, restauració, permisos ni portes G1..G7. No cal canviar l'estat global ni activar cap canal real.
