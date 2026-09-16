# Projecte VERI*FACTU PrisMa

> Punt d'entrada de la documentacio. Els documents estan separats per funcio: index/pla, compliment AEAT, context actual, canvis pendents, estat final i governanca/operacio.

## 1. Index i pla

- `00-index-i-pla/documentacio-verifactu.md`: document mare del projecte.
- `00-index-i-pla/14-pla-documentacio-i-auditoria.md`: pla documental, auditoria i criteri AEAT.
- `00-index-i-pla/26-matriu-cobertura-casos.md`: control de cobertura de casos documentats, parcials i pendents.
- `00-index-i-pla/27-informe-auditoria-documental.md`: revisio auditora de si la documentacio actual es suficient.
- `00-index-i-pla/28-revisio-apunts-altres-ias.md`: annex intern de triatge d'apunts provinents d'altres converses d'IA. Triage tancat; no es document principal del SIF.
- `00-index-i-pla/29-pla-implementacio-tecnica-sif.md`: pla tecnic executable per implementar el nucli SIF a partir de l'arquitectura tancada.
- `00-index-i-pla/30-mapa-trello-repo.md`: mapa de traçabilitat entre Trello 1, 4, 5, 6 i documents del repo.

## 2. Compliment AEAT

- `01-compliment-aeat/documentacio-sif-aeat.md`: document tecnic/organitzatiu del SIF.
- `01-compliment-aeat/declaracio-responsable-sif-prisma.md`: esborrany declaracio responsable.
- `01-compliment-aeat/declaracio-responsable-sif-prisma-borrador.docx`: esborrany Word.
- `01-compliment-aeat/acord-intern-responsabilitats-sif-prisma.md`: acord intern de governanca, responsabilitats, certificat i activacio de versions; no substitueix la declaracio responsable.
- `01-compliment-aeat/vistiplau-tecnic-sif-prisma.md`: formulari de vistiplau tecnic pendent d'emissio per a la versio candidata `1.0.0`.

## 3. Context i estat actual

- `02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`: explicacio global del sistema actual i futur per persona externa.
- `02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`: mapa de BDs, taules existents i taules noves.

## 4. Canvis pendents

- `03-canvis-pendents/04-fluxos-facturacio.md`: fluxos de facturacio per cas.
- `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`: Redsys i migracio a `pay.prisma.cat`.
- `03-canvis-pendents/07-pantalles-intranet.md`: canvis necessaris a pantalles actuals.
- `03-canvis-pendents/08-correus-i-plantilles.md`: correus i plantilles.
- `03-canvis-pendents/09-checklist-posada-en-produccio.md`: checklist abans de produccio, criteris go/no-go, preproduccio, evidencies, backups/restauracio i activacio final.
- `03-canvis-pendents/10-procediments-intranet-ecommerce.md`: procediments per apartats.
- `03-canvis-pendents/11-inventari-canvis-pendents.md`: inventari general de canvis.

## 5. Estat final

- `04-estat-final/05-model-bd-sif.md`: model detallat de BD del SIF.
- `04-estat-final/15-estat-final-sistema.md`: estat final del sistema.
- `04-estat-final/16-estat-final-pantalles.md`: pantalles finals d'intranet, ecommerce i consultes.
- `04-estat-final/17-estat-final-bd-relacions.md`: relacions finals entre bases de dades.
- `04-estat-final/18-estat-final-operacio-incidencies.md`: operacio final i incidencies.
- `04-estat-final/25-panell-sif-pay-prisma.md`: panell intern del SIF a `pay.prisma.cat`.
- `04-estat-final/31-diagrames-classes-sif.md`: 19 diagrames d'arquitectura; classes SIF, branca asincrona, canals llegats, operació comercial prèvia i serveis pendents de gestio, registre, evidència, seguretat comuna i traça de pagaments.
- `04-estat-final/32-diagrames-sequencia-sif.md`: 47 sequencies d'emissio, cobrament, productes, reserva/inscripció, Redsys, gestions administratives, correcció fiscal, documents, incidencies, auditoria de pagaments, migracio, operacio i AEAT pendent.
- `04-estat-final/33-casos-us-sif.md`: actors, vistes, inventari UC-01 a UC-112 i cobertura agrupada de les 192 pantalles/apartats.
- `04-estat-final/34-diagrames-dades-estats-sif.md`: 16 diagrames; model base SIF, model registral ampliat, operació comercial prèvia, dades llegades, numeracio/hash i cicles d'estat.
- `04-estat-final/35-matriu-tracabilitat-diagrames.md`: prova de cobertura entre codi SIF, set còpies de `codi-drive`, proves, scripts, endpoints, taules, pantalles, casos d'us i peces pendents.
- `04-estat-final/36-mapa-components-integracions-sif.md`: context, components, 1.920 PHP en set carpetes, desplegament, seguretat, proves i fronteres completes de pagament, gestio i control a `pay.prisma.cat`.
- `04-estat-final/37-auditoria-comparativa-codi-drive.md`: comparació dels 25 candidats amb 1.895 PHP actuals/històrics, mutacions llegades, buits funcionals/registrals i criteri de transició al SIF.
- `04-estat-final/38-matriu-transformacio-funcional-verifactu.md`: matriu mestra del canvi complet, més enllà del pagament: gestions, registres, pantalles, serveis pendents, prioritats i criteri de completitud.
- `04-estat-final/39-auditoria-fitxes-funcionals.md`: reconciliació corregida de 185 `Fitxes mare` en tres taulers i límit explícit del mapatge per títol.
- `04-estat-final/40-auditoria-buits-fitxes-codi-bd.md`: auditoria de contingut entre fitxes, codi actual, Trello i BD; buits descoberts i estat real `NO-GO`.

## 6. Governanca i operacio

- `05-governanca-operacio/19-registre-versions-i-canvis-sif.md`: versions, canvis i expedient go/no-go de cada versio candidata.
- `05-governanca-operacio/20-pla-proves-validacio-sif.md`: proves i validacions, amb bateria bloquejant i fitxes d'evidencia.
- `05-governanca-operacio/21-seguretat-permisos-accessos.md`: rols, seguretat i permisos.
- `05-governanca-operacio/22-manual-operatiu-intern.md`: manual intern.
- `05-governanca-operacio/23-annex-captures-pantalla.md`: annex de captures i evidencies visuals finals.
- `05-governanca-operacio/24-diccionari-camps-i-valors.md`: camps i valors controlats.

## 7. Documents que s'han de penjar o fer accessibles dins del SIF

### 7.1. Necessaris dins del SIF

Aquests documents o pantalles equivalents han d'estar accessibles dins del SIF, preferiblement en un apartat tipus "Sistema / VERI*FACTU / Documentacio":

- Declaracio responsable signada de la versio activa del SIF.
  - Font actual: `01-compliment-aeat/declaracio-responsable-sif-prisma.md`
  - Format final recomanat: PDF signat i llegible.
  - Estat actual: borrador no signable fins que existeixi versio `1.0.0` instal·lada, verificable i preparada per produccio.
  - Control afegit: abans de signar cal conservar revisio AEAT/BOE datada, certificat/apoderament provat, mapa de camps fiscals i rol auditor nomes lectura.
- Identificacio de la versio activa del SIF.
  - Font actual: `05-governanca-operacio/19-registre-versions-i-canvis-sif.md`
  - Ha d'indicar nom del SIF, codi intern, versio, data d'entrada en produccio i responsable.
- Consulta/exportacio dels registres fiscals, documents i logs conservats pel SIF.
  - No es nomes un document: ha de ser funcionalitat del SIF.
- Estat del certificat digital de l'entitat o apoderament/configuracio equivalent usada per remetre a AEAT.
  - No ha de mostrar secrets, claus privades ni contrasenyes.
  - Ha de permetre veure estat funcional, caducitat, entorn i ultima prova.

Ubicacio decidida:

```text
pay.prisma.cat / sif
    - Declaracio responsable
    - Versio activa
    - Documentacio tecnica
    - Registres fiscals
    - Registre d'events
    - Exportacions
    - Incidencies SIF
```

La intranet principal tindra un apartat `VERI*FACTU` amb un indicador visual de pendents, resum d'incidencies i acces directe al panell de `pay.prisma.cat/sif`, pero la font oficial sera el SIF.

La intranet pot consultar o mostrar dades del SIF, pero les dades, documents, registres i incidencies han de sortir del SIF/BD fiscal, no de fitxers manuals desconnectats.

Es pot crear un perfil d'auditoria o AEAT de nomes lectura, amb accés temporal i restringit a informacio fiscal, si es requereix en una inspeccio. No ha de permetre modificar res.

La versio `1.0.0` no s'ha de considerar preparada per signar si no existeix un paquet documental minim: registre de versio, declaracio responsable completa, certificat/apoderament provat, PDF/QR/XML o equivalents verificats, proves go/no-go i decisio formal d'activacio.

### 7.2. Recomanats dins del SIF, almenys per administradors

No son estrictament el mateix que la declaracio responsable, pero es recomanable tenir-los accessibles per administracio/tecnic:

- `01-compliment-aeat/documentacio-sif-aeat.md`
- `05-governanca-operacio/24-diccionari-camps-i-valors.md`
- `05-governanca-operacio/21-seguretat-permisos-accessos.md`

### 7.3. Conservats com a evidencia interna

Aquests no cal publicar-los dins del SIF, pero s'han de conservar ordenats per si cal justificar el projecte:

- `02-context-i-estat-actual/`
- `03-canvis-pendents/`
- `04-estat-final/`
- `05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `05-governanca-operacio/22-manual-operatiu-intern.md`
- `05-governanca-operacio/23-annex-captures-pantalla.md`

## 8. Gestio d'incidencies del SIF

Les incidencies del SIF s'han de gestionar al panell intern del SIF a `pay.prisma.cat/sif`.

La intranet pot mostrar notificacions, indicador visual de pendents i accessos directes, pero no ha de ser la font de veritat de la incidencia fiscal.

Criteri:

- font de veritat: BD fiscal/SIF;
- gestio principal: `pay.prisma.cat/sif/incidencies`;
- visualitzacio secundaria: intranet, apartat `VERI*FACTU / Incidencies`;
- notificacions: intranet i SIF, per avisar administradors;
- resolucio: sempre amb log d'accions.

Tipus d'incidencia:

- error AEAT;
- retries fallits;
- PDF/QR no generat;
- hash chain o registre fiscal bloquejat;
- pagament cobrat sense factura;
- factura pendent sense cobrament;
- callback Redsys duplicat;
- dades fiscals incompletes;
- exportacio requerida;
- revisio manual.

## 9. Fitxes funcionals, auditoria de cobertura i esquema registral

- Catàleg de casos d'ús: `04-estat-final/33-casos-us-sif.md`.
- Fitxes estructurades pendents de revisió de contingut: `06-fitxes-funcionals/README.md` i 142 fitxers `uc-*.md`.
- Reconciliació de les 185 `Fitxes mare` en tres taulers:
  `04-estat-final/39-auditoria-fitxes-funcionals.md`.
- Auditoria de buits: `04-estat-final/40-auditoria-buits-fitxes-codi-bd.md`.
- Model físic additiu: migracions
  `sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql`
  i `sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql`,
  `2026_09_16_000005_add_operation_lifecycle_tables.sql` i
  `2026_09_16_000006_add_cross_system_control_tables.sql`.
- Permisos append-only: `sif/database/permissions/`.

La fitxa documental no acredita que el cas estigui programat. Cal mirar
`Estat de preparació`, `Estat d'implementació`, decisions pendents, proves i
evidència abans de marcar cap cas com a complet o productiu.
