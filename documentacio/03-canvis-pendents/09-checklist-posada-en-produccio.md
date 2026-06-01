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

## 5. Fonts oficials revisades

Revisio feta el 2026-06-01:

- BOE, Orden HAC/1177/2024: `https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138`
- AEAT, Sistemes informatics de facturacio i VERI*FACTU: `https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html`
- AEAT, FAQ sistemes VERI*FACTU: `https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html`
