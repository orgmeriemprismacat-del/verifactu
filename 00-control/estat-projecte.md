# Estat del projecte VERI*FACTU

Ultima actualitzacio: 2026-05-19

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
- La prioritat immediata es continuar amb el bloc 3: pagaments, Redsys, callbacks i conciliacio.

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

Continuar el xat pont pel tercer bloc de revisio:

```text
Pagaments, Redsys, callbacks i conciliacio.
```

## Com s'ha de tancar cada sessio

Abans d'acabar qualsevol xat, demanar:

```text
Actualitza els fitxers de control del projecte: estat-projecte.md, registre-decisions.md i checklist-completitud.md amb el que hem decidit o completat en aquesta sessio.
```
