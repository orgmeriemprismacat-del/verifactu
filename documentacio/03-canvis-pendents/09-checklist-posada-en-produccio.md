# 09 - Checklist de posada en produccio

> Document viu. Recull comprovacions previes a l'arrencada del SIF, criteris de bloqueig i evidencies que s'han de conservar.

## 0. Regla go/no-go

El SIF no s'ha de marcar com a productiu nomes per tenir codi desplegat.

Abans d'activar produccio cal tenir, com a minim:

- versio registrada;
- BD fiscal productiva preparada;
- entorn de proves/preproduccio separat o mode de proves verificat;
- proves principals executades amb evidencia;
- certificat digital o apoderament provat;
- PDF/QR/XML o documents fiscals equivalents generats i conservats;
- cua AEAT i retries provats;
- backups i restauracio provats;
- permisos d'app i MySQL revisats;
- declaracio responsable preparada per a la versio productiva;
- decisio go/no-go signada o validada per la responsable tecnica i direccio quan correspongui.

Regla:

```text
Cap prova pot consumir numeracio fiscal productiva ni crear factures reals si encara no s'ha decidit activar produccio.
```

### 0.1. Criteri formal de decisio

La decisio de posada en produccio ha de quedar registrada com una de les tres opcions seguents:

| Decisio | Quan es pot usar | Condicions |
| --- | --- | --- |
| `GO` | El SIF pot activar-se en produccio. | Totes les proves bloquejants passen, no hi ha incidencies critiques obertes, backups/restauracio estan provats i la versio te expedient complet. |
| `GO AMB LIMITACIONS` | Es pot activar amb abast limitat i controls manuals temporals. | Les incidencies obertes no afecten numeracio, hash chain, AEAT, PDF/QR, permisos, cobrament o immutabilitat fiscal. Les limitacions han de tenir responsable i data de revisio. |
| `NO-GO` | No es pot activar. | Qualsevol prova bloquejant falla o no te evidencia, falta preproduccio separada, no s'ha provat restauracio, hi ha dubtes de certificat/apoderament, o no es pot demostrar que les factures emeses no es modifiquen. |

Incidencies sempre bloquejants abans de produccio:

- duplicat de numero fiscal;
- ruptura o incoherencia de hash chain;
- `issueInvoice()` no idempotent;
- callback Redsys duplicat que duplica factura o pagament;
- factura emesa editable des d'app, endpoint antic o BD amb usuari no autoritzat;
- PDF/QR/XML no generat ni registrat com a incidencia controlada;
- error AEAT sense cua, retry o incidencia;
- backup o restauracio no verificats;
- falta de registre de versio o declaracio responsable preparada per a la versio productiva.

## Blocs de checklist

- Certificat digital de l'entitat: disponibilitat, ubicacio segura, permisos d'us i proves de connexio AEAT.
- Entorn de proves AEAT.
- BD fiscal.
- Permisos MySQL.
- Backups.
- Endpoints SIF.
- Redsys.
- PDFs/QR.
- Cua AEAT.
- Notificacions.
- Panell SIF `pay.prisma.cat/sif`.
- Dashboard SIF.
- Factures SIF.
- Registres AEAT.
- Incidencies SIF.
- Documents SIF.
- Versions SIF.
- Exportacions SIF.
- Configuracio SIF.
- Apartat `VERI*FACTU` a la intranet.
- Indicador visual de pendents a la intranet.
- Resum d'incidencies pendents a la intranet.
- Intranet alumne.
- Rectificatives.
- Formacio interna.
- Declaracio responsable.

## 1. Entorn de proves i preproduccio

Abans del primer desplegament productiu:

- [ ] BD fiscal de proves separada o mode `TEST` que no barregi dades amb produccio.
- [ ] Numeracio de proves separada o clarament marcada com no productiva.
- [ ] Documents PDF/QR de prova marcats com a prova.
- [ ] Redsys test o simulador intern preparat per callbacks.
- [ ] AEAT proves/preproduccio configurada si correspon.
- [ ] Logs i incidencies de prova separats de produccio.
- [ ] Dades reals anonimitzades o duplicades en entorn controlat quan calgui.
- [ ] Procediment per netejar proves sense tocar historics productius.
- [ ] Regla escrita per impedir que `issueInvoice()` en mode proves crei factura productiva.
- [ ] Paquet de dades de preproduccio definit: curs normal, pack, grup, regal, USOC, transferencia, factura abans de cobrament i rectificativa.
- [ ] Usuari provador, usuari sense permisos i usuari auditor/lectura preparats.
- [ ] Fitxers de captures i logs separats per versio i entorn.
- [ ] Criteri per promocionar configuracio de preproduccio a produccio revisat, sense copiar dades de prova.

Condicio minima:

```text
La preproduccio ha de demostrar el comportament fiscal; no serveix nomes com a servidor on "obre la pantalla".
```

## 2. Checklist tecnic abans de produccio

### 2.1. BD fiscal i integritat

- [ ] Totes les taules fiscals critiques estan en `InnoDB`.
- [ ] Imports fiscals en `DECIMAL`, no `double`.
- [ ] `UUID_FACTURA` amb longitud adequada i unicitat.
- [ ] `IDEMPOTENCY_KEY` amb clau unica.
- [ ] `fiscal_sequence` amb bloqueig transaccional per serie/any.
- [ ] `fiscal_chain_state` o mecanisme equivalent de hash chain global.
- [ ] `FISCAL_ORDER` lineal i no reutilitzable.
- [ ] `fact_rels` relaciona factures, inscripcions, pagaments i historics.
- [ ] `factura_documents` conserva PDF/XML/QR amb hash.
- [ ] `errors_verifactu` o taula d'incidencies ampliada i tipificada.

### 2.2. Permisos i bloquejos

- [ ] L'app bloqueja edicio directa de factura emesa.
- [ ] Usuaris MySQL no SIF sense `UPDATE`/`DELETE` sobre factures emeses.
- [ ] Accions critiques validades al servidor, no nomes al JS.
- [ ] Rol auditor/AEAT nomes lectura preparat, si cal.
- [ ] Logs d'accessos i accions sensibles activats.
- [ ] Procés automatic SIF identificat i amb permisos minims.

### 2.3. Serveis i endpoints

- [ ] `issueInvoice()` provat en cas ordinari.
- [ ] `registerPayment()` provat contra factura existent.
- [ ] Rectificativa provada.
- [ ] Callback Redsys validat amb signatura/import/order.
- [ ] Callback duplicat no duplica factura ni pagament.
- [ ] Transferencia/manual no duplica factura existent.
- [ ] Error AEAT genera retry i incidencia.
- [ ] Error PDF/QR genera incidencia i no desfà factura.
- [ ] Panell `pay.prisma.cat/sif` accessible amb SSL.
- [ ] Intranet principal mostra nomes resum/indicador i no substitueix el SIF.

## 3. Backups, restauracio i rollback

Abans de produccio:

- [ ] Backup automatic de BD fiscal configurat.
- [ ] Backup de documents fiscals (`factura_documents`) configurat.
- [ ] Backup de configuracio SIF i secrets documentat sense exposar claus.
- [ ] Prova de restauracio executada en entorn separat.
- [ ] Retencio de backups definida.
- [ ] Pla de resposta si falla deploy: aturar noves emissions, mantenir consulta, conservar logs i no manipular factures emeses.
- [ ] Pla de resposta si falla AEAT: continuar segons mode previst, deixar registres en cua i enviar quan es resolgui.
- [ ] Pla de resposta si falla Redsys: no facturar sense pagament validat o sense decisio manual registrada.

Rollback:

```text
No es fa rollback de factures emeses.
Es pot revertir codi, pero els registres fiscals creats es conserven i es corregeixen amb registres posteriors si cal.
```

### 3.1. Prova minima de restauracio

La prova de restauracio ha de fer-se en entorn separat i ha de demostrar:

- restauracio de BD fiscal fins a un punt temporal conegut;
- restauracio de documents fiscals associats (`PDF`, `QR`, `XML` o equivalent);
- coherencia entre `factura`, `factura_linia`, `factura_registres`, `fiscal_queue`, `factura_documents`, `payment_transaction`, `payment_allocation` i `fact_rels`;
- consulta d'una factura restaurada amb el mateix numero, UUID, hash i document;
- verificacio que la restauracio no reactiva enviaments AEAT o callbacks Redsys com si fossin nous;
- registre de data, responsable, origen del backup, entorn de restauracio i resultat.

#### 3.1.1. Plantilla d'acta de restauracio

```markdown
## Acta de restauracio

| Camp | Valor |
| --- | --- |
| ID acta |  |
| Versio SIF |  |
| Data/hora inici |  |
| Data/hora final |  |
| Responsable |  |
| Entorn restaurat |  |
| Origen backup BD |  |
| Origen backup documents |  |
| Punt temporal restaurat |  |
| Factura de mostra verificada |  |
| UUID / numero / hash verificats |  |
| Documents fiscals verificats | PDF / QR / XML / altres |
| Cues o processos reactivats per error | No / Si, detall |
| Resultat | PASS / FAIL / BLOCKED |
| Evidencies |  |
| Incidencia associada |  |
```

L'acta no substitueix el backup automatic. Serveix per demostrar que PrisMa sap restaurar i consultar el SIF sense alterar factures ni reprocessar events.

## 4. Evidencies minimes a conservar

Per a la versio productiva:

- [ ] hash/commit o paquet de codi desplegat;
- [ ] SQL o migracions aplicades;
- [ ] captures finals principals;
- [ ] resultats de proves executades;
- [ ] logs de prova de callback Redsys duplicat;
- [ ] logs de prova de concurrencia/numeracio;
- [ ] factura de prova amb PDF/QR/hash;
- [ ] prova d'error AEAT i retry;
- [ ] prova d'error PDF/QR i incidencia;
- [ ] prova de permisos: usuari sense permis no edita factura emesa;
- [ ] export fiscal de prova;
- [ ] declaracio responsable vinculada a la versio.

Cada evidencia ha d'indicar:

- data;
- versio;
- entorn;
- responsable;
- resultat;
- fitxer/log/captura associada.

## Detall del bloc AEAT i declaracio responsable

Abans de posar el SIF en produccio cal comprovar:

- [ ] Abast normatiu aplicable a PrisMa confirmat: Impost sobre Societats o altre regim, no SII, no normativa foral basca/navarresa i sense exempcio especifica que alteri l'obligacio.
- [ ] Termini aplicable confirmat amb criteri intern o gestoria: 1 de gener de 2027 si aplica Impost sobre Societats, o 1 de juliol de 2027 per la resta d'obligats afectats.
- [ ] Modalitat `VERI*FACTU` confirmada com a modalitat de produccio.
- [ ] Confirmat que no cal comunicar l'opcio `VERI*FACTU` via model 036, segons FAQ AEAT vigent, llevat de canvi normatiu posterior.
- [ ] Certificat digital de l'entitat o apoderament disponible.
- [ ] Certificat o apoderament provat contra l'entorn AEAT corresponent.
- [ ] Ubicacio segura del certificat definida, amb permisos minims i registre d'us.
- [ ] Endpoints/WSDL/serveis AEAT configurats.
- [ ] Cua d'enviament AEAT provada amb error, retry i resolucio d'incidencia.
- [ ] Text `VERI*FACTU` o "Factura verificable en la sede electronica de la AEAT" incorporat al PDF quan correspongui.
- [ ] QR generat amb dades definitives segons especificacio AEAT vigent.
- [ ] XML/registres fiscals generats i conservats.
- [ ] CSV/resposta AEAT conservada quan correspongui.
- [ ] Declaracio responsable `1.0.0` revisada, amb versio exacta, components finals, data, lloc i signants.
- [ ] Declaracio responsable disponible dins del SIF de forma llegible, individualitzada i accessible.
- [ ] Registre de versions actualitzat amb versio activa i declaracio associada.
- [ ] Proves principals executades i evidencies guardades.
- [ ] Punts fiscals sensibles revisats internament i marcats com a pendents de validacio externa si no hi ha assessor fiscal.

Punts fiscals sensibles a revisar abans de signar o activar produccio:

- rectificativa per canvi de NIF, rao social o dades fiscals;
- factura abans de cobrament no pagada;
- compensacions i saldos a favor;
- mencio exacta d'exempcio IVA per cursos;
- text visible de descomptes per evitar dades sensibles.

## 5. Expedient de captures i evidencies

Cada versio candidata a produccio ha de tenir un expedient propi, per exemple:

```text
evidencies/
  SIF-PRISMA-0.3-BORRADOR/
  SIF-PRISMA-1.0.0/
```

Cada captura o log ha d'incloure al nom o a la fitxa:

- ID de prova;
- versio;
- entorn (`TEST`, `PREPROD` o `PROD`);
- data;
- pantalla, endpoint o proces;
- usuari o rol;
- resultat (`PASS`, `FAIL`, `INCIDENCIA`);
- referencia a factura, pagament, cua o incidencia si existeix.

Captures minimes abans de `1.0.0`:

- panell `pay.prisma.cat/sif` amb versio activa o candidata;
- dashboard amb incidencies visibles;
- emissio de factura ordinaria amb PDF/QR;
- callback Redsys acceptat i callback duplicat;
- factura abans de cobrament i cobrament posterior;
- rectificativa vinculada a factura original;
- incidencia AEAT amb retry;
- incidencia PDF/QR;
- bloqueig d'usuari sense permisos;
- intranet alumne sense visibilitat de factura de grup/empresa;
- export fiscal de prova;
- registre de backups/restauracio.

### 5.1. Manifest mestre de l'expedient

Cada expedient ha de tenir un manifest unic que permeti comprovar-ne la completitud sense recórrer manualment totes les carpetes.

| Bloc | Referencia obligatoria | Estat admes |
| --- | --- | --- |
| Versio | Fitxa de versio candidata i paquet/commit exacte. | `COMPLET` / `PENDENT` |
| Entorn | Configuracio no secreta de PREPROD i prova de separacio de PROD. | `COMPLET` / `PENDENT` |
| Migracions | Llista, ordre, hash i resultat d'aplicacio. | `PASS` / `FAIL` / `BLOCKED` |
| Proves | Resum de campanya i fitxes d'execucio. | `PASS` / `FAIL` / `BLOCKED` / `N/A JUSTIFICAT` |
| Evidencies | Index amb ubicacio, tipus, prova associada i hash quan correspongui. | `COMPLET` / `INCOMPLET` |
| Incidencies | Relacio d'obertes i tancades, severitat i criteri de tancament. | `SENSE BLOQUEJANTS` / `BLOQUEJAT` |
| Backup/restauracio | Backup previ, acta i verificacio de restauracio. | `PASS` / `FAIL` / `BLOCKED` |
| Seguretat | Permisos, secrets, certificat/apoderament i rol auditor. | `PASS` / `FAIL` / `N/A JUSTIFICAT` |
| Documents | PDF/QR/XML, declaracio responsable i documents de versio. | `COMPLET` / `INCOMPLET` |
| Decisio | Acta go/no-go i aprovacions aplicables. | `GO` / `GO AMB LIMITACIONS` / `NO-GO` |

El manifest ha d'indicar data de congelacio, responsable, ubicacio de l'expedient i hash del mateix manifest. Despres de la decisio no se sobreescriu: qualsevol correccio genera una nova revisio i conserva l'anterior.

## 6. Incidencies i criteri de bloqueig

Durant preproduccio i posada en produccio, tota incidencia ha de quedar classificada:

| Severitat | Exemple | Efecte en go/no-go |
| --- | --- | --- |
| `CRITICA` | Duplicat fiscal, hash chain incorrecte, factura editable, callback duplicat que factura dues vegades. | Sempre `NO-GO`. |
| `ALTA` | PDF/QR fallit sense recuperacio, AEAT sense retry, permisos incomplets, backup no restaurable. | `NO-GO` fins a resolucio o prova compensatoria forta. |
| `MITJANA` | Captura pendent, text de correu millorable, export no critic incomplet. | Pot permetre `GO AMB LIMITACIONS` si no afecta compliment fiscal. |
| `BAIXA` | Millora visual o aclariment documental sense impacte fiscal. | No bloqueja, pero queda registrada. |

Cada incidencia ha de tenir:

- identificador;
- data d'obertura;
- versio i entorn;
- origen: prova, usuari, AEAT, Redsys, PDF/QR, backup, permisos o operacio;
- descripcio;
- severitat;
- responsable;
- estat;
- evidencia associada;
- criteri de tancament.

## 7. Checklist final d'activacio productiva

La decisio final s'ha de revisar en aquest ordre:

### 7.1. Abans del dia d'activacio

- [ ] Versio candidata registrada.
- [ ] Migracions SQL aplicades i verificades en preproduccio.
- [ ] Paquet de proves bloquejants executat amb resultat `PASS`.
- [ ] Incidencies `CRITICA` i `ALTA` tancades.
- [ ] Backups configurats i prova de restauracio documentada.
- [ ] Certificat/apoderament revisat i provat quan correspongui.
- [ ] Declaracio responsable de `1.0.0` preparada, no signada fins que la versio sigui verificable.
- [ ] Captures finals principals guardades.
- [ ] Pla de comunicacio interna preparat: qui pot emetre, qui resol incidencies i qui decideix aturada.

### 7.2. Dia d'activacio

- [ ] Backup immediat abans del desplegament.
- [ ] Codi/paquet desplegat coincideix amb la versio registrada.
- [ ] Configuracio productiva revisada: BD, numeracio, AEAT, Redsys, PDF/QR, secrets i permisos.
- [ ] Primera prova controlada en produccio validada sense consumir dades ficticies.
- [ ] Panell SIF accessible i amb versio activa visible.
- [ ] Logs d'emissio, pagament, documents, AEAT i incidencies visibles.
- [ ] Decisio `GO`, `GO AMB LIMITACIONS` o `NO-GO` registrada.

### 7.3. Despres de l'activacio

- [ ] Revisio de primeres factures productives.
- [ ] Revisio de callbacks Redsys reals.
- [ ] Revisio de cua AEAT i respostes.
- [ ] Revisio de generacio PDF/QR.
- [ ] Revisio d'indicadors i incidencies de la intranet.
- [ ] Evidencia final de versio activa i declaracio responsable associada.

### 7.4. Plantilla d'acta go/no-go

```markdown
## Acta go/no-go SIF

| Camp | Valor |
| --- | --- |
| ID acta |  |
| Versio candidata |  |
| Data decisio |  |
| Entorn validat |  |
| Paquet/commit desplegat |  |
| Migracions aplicades |  |
| Campanya de proves |  |
| Proves PASS |  |
| Proves FAIL |  |
| Proves BLOCKED |  |
| Incidencies CRITICA obertes |  |
| Incidencies ALTA obertes |  |
| Backup previ verificat | Si / No |
| Restauracio provada | Si / No |
| Certificat/apoderament provat | Si / No / No aplica justificat |
| Declaracio responsable preparada | Si / No |
| Decisio | GO / GO AMB LIMITACIONS / NO-GO |
| Limitacions acceptades |  |
| Responsable tecnica |  |
| Direccio/responsable legal |  |
| Propera revisio |  |
```

Si la decisio es `GO AMB LIMITACIONS`, l'acta ha d'explicar exactament quin abast queda activat i quin queda bloquejat o sota control manual temporal.

### 7.5. Matriu final de portes

| Porta | Condicio de pas | Responsable de validar | Evidencia |
| --- | --- | --- | --- |
| `G1` Versio | Paquet, migracions i configuracio coincideixen amb la candidata. | Responsable tecnica | Fitxa de versio, hashes i resultat de migracions. |
| `G2` Integritat fiscal | Numeracio, hash chain, immutabilitat i idempotencia passen. | Responsable tecnica | Proves `SIF-INV-001`, `SIF-IDEM-001` i `SIF-CON-001`. |
| `G3` Canals i documents | Fluxos activats, PDF/QR/XML i AEAT passen. | Responsable tecnica | Campanya, documents i logs. |
| `G4` Seguretat | Permisos, secrets, certificat i auditoria estan validats. | Responsable tecnica / responsable legal quan pertoqui | Proves de permisos i certificat no secretes. |
| `G5` Continuïtat | Backup recuperable i restauracio demostrada. | Operacio tecnica | Backup i acta `SIF-BCK-001`. |
| `G6` Incidencies | Cap incidencia critica o alta oberta. | Responsable tecnica | Registre d'incidencies i reexecucions. |
| `G7` Governanca | Manifest complet, acta emesa i aprovacions registrades. | Responsable tecnica i direccio/responsable legal quan correspongui | Manifest, acta i vistiplau. |

Regles de decisio:

- `GO` exigeix `G1` a `G7` superades.
- `GO AMB LIMITACIONS` exigeix igualment `G1`, `G2`, `G4`, `G5`, `G6` i `G7`; nomes pot limitar funcionalitats o canals no activats de `G3`.
- `GO AMB LIMITACIONS` no pot substituir una prova bloquejant, una restauracio pendent, una incidencia critica/alta ni una manca de certificat, permisos, immutabilitat, numeracio o hash.
- qualsevol porta bloquejada o sense evidencia suficient implica `NO-GO`.

## 8. Fonts oficials revisades

Revisio feta el 2026-06-01:

- BOE, Orden HAC/1177/2024: `https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138`
- AEAT, Sistemes informatics de facturacio i VERI*FACTU: `https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html`
- AEAT, FAQ sistemes VERI*FACTU: `https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html`
