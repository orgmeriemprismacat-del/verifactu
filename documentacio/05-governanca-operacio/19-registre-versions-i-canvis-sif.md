# 19 - Registre de versions i canvis del SIF

> Document d'estat final i manteniment. Relaciona cada versio del SIF amb els canvis aplicats, la data d'entrada en produccio i la declaracio responsable corresponent.

## 1. Objectiu

Mantenir traçabilitat de les versions del SIF PrisMa.

Cada versio ha d'indicar:

- codi de versio;
- data de desplegament;
- responsable tecnic;
- responsable legal/direccio;
- resum de canvis;
- afectacio fiscal;
- declaracio responsable associada;
- proves realitzades;
- incidencies conegudes.

## 2. Versio inicial prevista

```text
Sistema: SIF PrisMa
Codi intern: SIF-PRISMA
Primera versio signable prevista: 1.0.0
Estat documental actual: 0.1-BORRADOR
```

## 3. Registre inicial

| Versio | Data | Estat | Canvis principals | Declaracio responsable | Notes |
| --- | --- | --- | --- | --- | --- |
| 0.1-BORRADOR | pendent | Documentacio | Definicio inicial del projecte | No signable | Treball intern |
| 1.0.0 | pendent | Prevista | Primera versio productiva VERI*FACTU | Pendent | Signable quan estigui implantada |

## 4. Cicle documental previst

La revisio del xat antic deixa decidit que la declaracio responsable no s'ha de signar durant el desenvolupament inicial. El registre de versions ha de separar clarament versions de treball i versions productives.

| Versio | Tipus | Quan es faria servir | Declaracio responsable |
| --- | --- | --- | --- |
| 0.1-BORRADOR | Documentacio | Estructura inicial del SIF, documents i criteris generals. | No signable |
| 0.2-BORRADOR | Documentacio / desenvolupament | Quan s'incorpori Redsys, `pay.prisma.cat` i primers fluxos reals. | No signable |
| 0.3-BORRADOR | Preproduccio | Quan hi hagi PDF/QR/XML, proves principals i panell SIF preparat. | No signable, revisable |
| 1.0.0 | Produccio | Primera versio productiva del SIF centralitzat. | Signable |
| 1.1.x | Evolutiva | Canvis rellevants en facturacio, AEAT, documents, rectificatives, permisos o components. | Nova declaracio o annex si afecta compliment |

Regla:

```text
No hi ha versio activa sense registre de versio.
No hi ha declaracio signada sense versio concreta.
No cal signar canvis menors mentre el SIF esta en desenvolupament.
```

## 5. Condicions per marcar una versio com activa

Una versio nomes es pot marcar com activa quan:

- el codi i la BD desplegats corresponen a la versio registrada;
- els endpoints fiscals principals estan operatius;
- PDF/QR/XML estan configurats segons la versio;
- la cua AEAT i els reintents estan configurats;
- el certificat digital de l'entitat o apoderament esta disponible i provat;
- s'han executat proves minimes i se'n conserva evidencia;
- la declaracio responsable de la versio esta disponible dins del SIF si la versio es productiva;
- la responsable tecnica valida que el SIF esta preparat;
- direccio/responsable legal revisa o signa quan correspongui.

## 6. Canvis que poden requerir nova declaracio o annex

Poden requerir nova declaracio responsable, annex o actualitzacio signada:

- canvi de modalitat `VERI*FACTU` / no `VERI*FACTU`;
- canvi substancial de component de facturacio;
- canvi de model de BD fiscal o cadena hash;
- canvi de generacio PDF/QR/XML;
- canvi de mecanisme d'enviament AEAT;
- canvi de productor/titular o responsable legal;
- canvi en l'abast multiobligat o en l'us del SIF per tercers;
- canvi en els fluxos que creen, rectifiquen o anul·len factures.

No haurien de requerir una nova declaracio per si sols:

- correccions de text sense impacte fiscal;
- millores visuals del panell;
- canvis interns sense impacte en registre fiscal, documents, hash, AEAT ni control d'accessos.

## 7. Dades pendents per a la versio 1.0.0

Abans de tancar la versio `1.0.0`, cal completar:

- data d'entrada en produccio;
- data del primer enviament `VERI*FACTU`, quan existeixi;
- domini final i SSL;
- certificat digital de l'entitat o apoderament;
- declaracio responsable signada o preparada per signar;
- NIF i carrec complet de la persona que signa per direccio;
- proves executades;
- incidencies conegudes;
- criteri final sobre punts fiscals sensibles pendents de validacio externa.
