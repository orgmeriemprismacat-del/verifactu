# Estat del projecte VERI*FACTU

Ultima actualitzacio: 2026-06-01

## Objectiu

Adaptar el sistema de facturacio de PrisMa a VERI*FACTU mitjancant un SIF centralitzat, amb documentacio tecnica, fiscal, operativa i de posada en produccio suficient per continuar el projecte sense dependre del xat antic.

## Fonts disponibles

- Documentacio principal: `documentacio/`
- Index documental: `documentacio/README.md`
- Document mare: `documentacio/00-index-i-pla/documentacio-verifactu.md`
- Xat antic complet: `xat-original/rollout-2026-05-13T22-15-39-019e22fb-0e44-71f3-81ef-e0b9d0dcd2a7.jsonl`

## Estat actual

- La documentacio principal existeix i esta separada per apartats.
- El xat antic esta copiat com a arxiu de consulta.
- Encara hi pot haver informacio donada al xat antic que no estigui reflectida als documents.
- El xat pont ja ha fet un inventari inicial del xat antic per cerques tematiques, sense carregar el JSONL complet.
- Bloc 1 revisat: context actual de PrisMa i canals reals. S'han incorporat matisos de stack tecnic, TPV virtuals, receptors, estat actual de factura/PDF, camps operatius i volum/concurrencia.
- Bloc 2 revisat: fluxos de facturacio i casos especials. S'han incorporat matisos sobre `IDPAG`, intents Redsys, transferencies, compensacions, factura abans de cobrament, proformes, canvis de curs, baixes, devolucions i rectificatives.
- Bloc 3 revisat: pagaments, Redsys, callbacks i conciliacio. S'han incorporat matisos sobre `realitzaPagamentAutomatic.php`, parametres reals del callback, `Ds_Order`/`NUM_COMANDA`, canals TPV, `Passar pagaments`, migracio a `pay.prisma.cat`, conciliacio i visibilitat de factura/PDF.
- Bloc 4 revisat: base de dades, hash chain, concurrencia i idempotencia. S'han incorporat matisos sobre BD fiscal parcial, `errors_verifactu`, `reg_pagament`, `factura_log`, `session_log`, migracio de `UUID varchar(12)` i `double`, `InnoDB`, `IDEMPOTENCY_KEY`, `FISCAL_ORDER`, hash chain global, `fiscal_queue.PAYLOAD_JSON` i permisos MySQL contra updates de factures emeses.
- Bloc 5 revisat: pantalles, permisos i operacio interna. S'han incorporat matisos sobre rutes d'intranet, `ROLS_VISUALITZAR`, `ROLS_EDITAR`, `ROLS_ENVIAR_MSG`, `consultaRolsEdiicio`, `consultaRolsUsuari`, `tePermisEdicio`, permisos d'Isa, accions critiques, reclamacions, morositat i apartat `VERI*FACTU` de la intranet.
- Bloc 6 revisat: compliment AEAT i declaracio responsable. S'han incorporat criteris sobre abast normatiu, terminis AEAT verificats, model 036, versio `0.1-BORRADOR` no signable, versio `1.0.0` signable, certificat digital de l'entitat, productor/titular, rol de Meriem, punts fiscals sensibles i cicle de versions.
- Bloc 7 revisat: correus, plantilles, PDF/QR i notificacions. S'han incorporat criteris sobre grups reals de correus, `Template`, correus directes en PHP, correu tecnic de `realitzaPagamentAutomatic.php`, URLs de pagament a `pay.prisma.cat`, enllac segur, PDF/QR en cua, `factura_documents` i nomenclatura `incidencia SIF`/`notificacio`/`avis`/`indicador`.
- Bloc 8 revisat: proves, produccio, auditoria documental i governanca. S'han incorporat criteris sobre entorn de preproduccio separat, go/no-go, regressions critiques, evidencies de prova, backups/restauracio, no rollback de factures emeses, versio `0.3-BORRADOR`, activacio de `1.0.0` i planificacio interna realista.
- La revisio transversal del xat antic per blocs queda completada. La prioritat immediata passa a ser obrir xats especialitzats per convertir la documentacio parcial en procediments executables, SQL, pantalles, proves i evidencies.

## Decisions base ja assumides

- El SIF sera centralitzat.
- Els canals no han de crear factures fiscals finals pel seu compte.
- El SIF ha de decidir numero fiscal, hash, registre, estat AEAT, PDF i QR.
- `pay.prisma.cat/sif` es la ubicacio funcional prevista per al panell intern del SIF.
- La intranet pot mostrar alertes i accessos, pero no ha de ser la font fiscal principal.

## Inventari inicial del xat antic

Revisio inicial feta el 2026-05-19.

Metode:

- no s'ha carregat el JSONL complet al context;
- s'han filtrat missatges reals d'usuari/agent;
- s'han fet cerques tematiques per detectar blocs amb mes risc de buit documental.

Temes amb mes risc de contenir detalls pendents de contrast:

1. Context real de canals, productes, TPV, ecommerce, intranet, cursos, packs, regals, empreses i vendes manuals.
2. Fluxos de facturacio i excepcions: factura emesa, dades fiscals, rectificatives, devolucions, pagaments parcials, duplicats, canvis de curs i baixes.
3. Pagaments, Redsys, transferencia, callbacks, conciliacio TPV i migracio cap a `pay.prisma.cat`.
4. Base de dades, hash chain, concurrencia, idempotencia, MyISAM/InnoDB, cues, retries i logs fiscals.
5. Compliment AEAT, certificat digital, declaracio responsable, QR/XML/CSV i criteris a validar amb assessor fiscal.
6. Pantalles, permisos, operacio interna, incidencies, exportacions, auditories i rols.
7. Proves, produccio, migracio, checklist, evidencia i governanca.
8. Correus, plantilles, PDF/QR, enllacos segurs i notificacions.

## Ordre de revisio proposat

1. Context actual de PrisMa i canals reals.
2. Fluxos de facturacio i casos especials.
3. Pagaments, Redsys, callbacks i conciliacio.
4. Base de dades, hash chain, concurrencia i idempotencia.
5. Pantalles, permisos i operacio interna.
6. Compliment AEAT i declaracio responsable.
7. Correus, plantilles, PDF/QR i notificacions.
8. Proves, produccio, auditoria documental i governanca.

## Proper pas recomanat

Obrir el primer xat especialitzat segons el mapa de xats i la matriu de cobertura:

```text
Pantalles i procediments reals d'intranet/ecommerce.
```

Alternativament, si la prioritat tecnica es reduir risc abans de pantalles, obrir un xat especialitzat de BD/SQL i migracio fiscal.

## Com s'ha de tancar cada sessio

Abans d'acabar qualsevol xat, demanar:

```text
Actualitza els fitxers de control del projecte: estat-projecte.md, registre-decisions.md i checklist-completitud.md amb el que hem decidit o completat en aquesta sessio.
```
