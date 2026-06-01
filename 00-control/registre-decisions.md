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
