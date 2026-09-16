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

### 4.1. Condicions de preproduccio `0.3-BORRADOR`

La versio `0.3-BORRADOR` no es signable, pero ha de servir com a punt de validacio abans de preparar `1.0.0`.

Per arribar a `0.3-BORRADOR` caldria tenir:

- BD fiscal de proves separada o mode test verificat;
- numeracio de proves separada de la productiva;
- Redsys en mode test o simulador;
- entorn AEAT de prova si correspon i esta disponible;
- PDF/QR/XML o documents fiscals equivalents generats en proves;
- panell `pay.prisma.cat/sif` accessible en mode intern;
- registre de versions visible;
- declaracio responsable en esborrany associada a la versio;
- paquet inicial d'evidencies de proves;
- incidencies classificades per bloquejants i no bloquejants.

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

Per a una versio productiva, la decisio d'activacio ha d'indicar:

| Camp | Contingut |
| --- | --- |
| Versio | Codi exacte activat. |
| Commit/paquet | Referencia tecnica desplegada. |
| Migracio BD | Identificador o resum de migracions aplicades. |
| Checklist | Enllac o referencia al checklist de produccio. |
| Proves | Paquet de proves go/no-go executat. |
| Backups | Data de backup i prova de restauracio o control equivalent. |
| Certificat/apoderament | Estat i prova d'us quan correspongui. |
| Declaracio responsable | Document associat a la versio. |
| Decisio | `GO`, `GO AMB LIMITACIONS` o `NO-GO`. |
| Responsables | Validacio tecnica i revisio legal/direccio. |

### 5.1. Expedient go/no-go de versio

Cada versio candidata a produccio ha de tenir un expedient de validacio associat.

Contingut minim:

- referencia exacta de codi o paquet desplegat;
- resum de migracions SQL aplicades;
- entorn on s'han executat les proves;
- paquet de proves go/no-go amb IDs i resultats;
- evidencies principals: captures, logs, exports, PDF/QR/XML, hashes i consultes;
- acta o fitxa de backup i restauracio;
- incidencies obertes, tancades i severitat;
- decisio final `GO`, `GO AMB LIMITACIONS` o `NO-GO`;
- limitacions acceptades, si n'hi ha, amb responsable i data de revisio;
- declaracio responsable o esborrany associat a la versio.

Regla:

```text
Una versio pot estar desplegada tecnicament i continuar sense estar activa fiscalment.
La versio nomes es activa quan te registre, proves, evidencies, backups/restauracio i decisio go/no-go.
```

### 5.2. Paquet documental de la versio `1.0.0`

La versio `1.0.0` sera la primera versio productiva signable. No es pot activar nomes amb el codi desplegat: ha de tenir un paquet documental minim.

Paquet requerit:

- registre de versio `1.0.0` complet;
- referencia tecnica del paquet desplegat o commit equivalent;
- migracions BD aplicades i estat de BD fiscal productiva;
- domini/subdomini final i SSL verificat;
- mode actiu `VERI*FACTU`;
- certificat digital de l'entitat o apoderament configurat/provat;
- declaracio responsable completa, sense camps pendents, associada a `1.0.0`;
- decisio sobre qui signa formalment per l'entitat i si hi ha vistiplau tecnic separat;
- declaracio responsable accessible dins de `pay.prisma.cat/sif`;
- proves go/no-go executades i evidencia conservada;
- estat de PDF/QR/XML i remissio AEAT;
- incidencies bloquejants tancades o justificades.

Regla:

```text
No hi ha versio productiva signable sense paquet documental.
No hi ha paquet documental complet sense certificat/apoderament i declaracio accessible dins del SIF.
```

### 5.3. Plantilla de versio candidata

```markdown
## Versio candidata SIF

| Camp | Valor |
| --- | --- |
| Versio |  |
| Tipus | BORRADOR / PREPRODUCCIO / PRODUCCIO |
| Data proposta |  |
| Responsable tecnica |  |
| Responsable legal/direccio |  |
| Paquet/commit |  |
| Entorn desplegat |  |
| Migracions BD |  |
| Canvis funcionals |  |
| Afectacio fiscal | Cap / Menor / Substancial |
| Afecta AEAT | Si / No |
| Afecta PDF/QR/XML | Si / No |
| Afecta permisos | Si / No |
| Afecta hash chain/numeracio | Si / No |
| Proves requerides |  |
| Campanya go/no-go associada |  |
| Declaracio responsable | No aplica / Esborrany / Signable / Signada |
| Estat final | Candidata / Activa / Rebutjada / Substituida |
```

Regla:

```text
Si un canvi afecta numeracio, hash chain, AEAT, PDF/QR/XML, permisos fiscals o fluxos d'emissio/rectificacio, la versio candidata ha de passar per campanya go/no-go abans de ser activa.
```

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
- criteri final sobre punts fiscals sensibles pendents de validacio externa;
- decisio final sobre productor/titular intern, contacte tecnic i signant formal de la declaracio;
- referencia interna o hash del document de declaracio responsable signat, si es conserva al SIF.

## 8. Evidencia de canvis

Cada canvi de versio ha de conservar, com a minim:

- resum funcional del canvi;
- motiu del canvi;
- afectacio sobre facturacio, AEAT, PDF/QR/XML, permisos o dades;
- migracions SQL aplicades;
- proves executades;
- incidencies obertes o tancades;
- captures o exports si el canvi afecta pantalles o documents;
- declaracio responsable nova, annex o justificacio de per que no cal.

Els canvis purament interns o visuals poden quedar registrats sense nova declaracio responsable, sempre que no alterin el comportament fiscal, la conservacio, la traçabilitat, la inalterabilitat, l'enviament AEAT ni els permisos.
