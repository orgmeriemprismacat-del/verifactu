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

## 2026-06-05 - Fase 11 iniciada: `issueInvoice(payment)` per cobrament inicial

Decisio:
Preparar el nucli `issueInvoice(payment)` per als casos on factura i cobrament neixen junts, especialment Redsys normal i transferencies ja validades. El bloc `payment` opcional de la factura es valida amb `PaymentPayloadValidator` i crea `payment_transaction` i `payment_allocation` dins la mateixa transaccio que la factura, el registre fiscal, el hash chain, la cua AEAT i `fact_rels`.

Motiu:
L'arquitectura tancada estableix que quan el fet facturable i el cobrament arriben junts no s'ha de fer primer `issueInvoice()` i despres una operacio separada amb risc de desquadrament. La factura fiscal i el moviment economic inicial han de quedar en una unica operacio idempotent, mentre que `registerPayment()` continua reservat per pagaments posteriors sobre factures ja existents.

Impacte:
Fase 11 queda iniciada a nivell de servei, endpoint intern i prova d'integracio. El reintent idempotent d'`issueInvoice(payment)` retorna tambe el `uuid_payment` existent quan ja s'havia creat el moviment inicial. Encara no s'ha activat cap canal real: l'activacio en preproduccio queda condicionada a PHP disponible, BD MySQL de test, `preflight-sif.php` amb `ok=true` i validacio criptografica Redsys real connectada abans de permetre efectes fiscals o economics des del callback.
