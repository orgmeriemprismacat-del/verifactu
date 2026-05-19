# Projecte VERI*FACTU PrisMa

> Punt d'entrada de la documentacio. Els documents estan separats per funcio: index/pla, compliment AEAT, context actual, canvis pendents, estat final i governanca/operacio.

## 1. Index i pla

- `00-index-i-pla/documentacio-verifactu.md`: document mare del projecte.
- `00-index-i-pla/14-pla-documentacio-i-auditoria.md`: pla documental, auditoria i criteri AEAT.
- `00-index-i-pla/26-matriu-cobertura-casos.md`: control de cobertura de casos documentats, parcials i pendents.
- `00-index-i-pla/27-informe-auditoria-documental.md`: revisio auditora de si la documentacio actual es suficient.
- `00-index-i-pla/28-revisio-apunts-altres-ias.md`: annex intern de triatge d'apunts provinents d'altres converses d'IA. Triage tancat; no es document principal del SIF.

## 2. Compliment AEAT

- `01-compliment-aeat/documentacio-sif-aeat.md`: document tecnic/organitzatiu del SIF.
- `01-compliment-aeat/declaracio-responsable-sif-prisma.md`: esborrany declaracio responsable.
- `01-compliment-aeat/declaracio-responsable-sif-prisma-borrador.docx`: esborrany Word.

## 3. Context i estat actual

- `02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`: explicacio global del sistema actual i futur per persona externa.
- `02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`: mapa de BDs, taules existents i taules noves.

## 4. Canvis pendents

- `03-canvis-pendents/04-fluxos-facturacio.md`: fluxos de facturacio per cas.
- `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`: Redsys i migracio a `pay.prisma.cat`.
- `03-canvis-pendents/07-pantalles-intranet.md`: canvis necessaris a pantalles actuals.
- `03-canvis-pendents/08-correus-i-plantilles.md`: correus i plantilles.
- `03-canvis-pendents/09-checklist-posada-en-produccio.md`: checklist abans de produccio.
- `03-canvis-pendents/10-procediments-intranet-ecommerce.md`: procediments per apartats.
- `03-canvis-pendents/11-inventari-canvis-pendents.md`: inventari general de canvis.

## 5. Estat final

- `04-estat-final/05-model-bd-sif.md`: model detallat de BD del SIF.
- `04-estat-final/15-estat-final-sistema.md`: estat final del sistema.
- `04-estat-final/16-estat-final-pantalles.md`: pantalles finals d'intranet, ecommerce i consultes.
- `04-estat-final/17-estat-final-bd-relacions.md`: relacions finals entre bases de dades.
- `04-estat-final/18-estat-final-operacio-incidencies.md`: operacio final i incidencies.
- `04-estat-final/25-panell-sif-pay-prisma.md`: panell intern del SIF a `pay.prisma.cat`.

## 6. Governanca i operacio

- `05-governanca-operacio/19-registre-versions-i-canvis-sif.md`: versions i canvis.
- `05-governanca-operacio/20-pla-proves-validacio-sif.md`: proves i validacions.
- `05-governanca-operacio/21-seguretat-permisos-accessos.md`: rols, seguretat i permisos.
- `05-governanca-operacio/22-manual-operatiu-intern.md`: manual intern.
- `05-governanca-operacio/23-annex-captures-pantalla.md`: annex de captures.
- `05-governanca-operacio/24-diccionari-camps-i-valors.md`: camps i valors controlats.

## 7. Documents que s'han de penjar o fer accessibles dins del SIF

### 7.1. Necessaris dins del SIF

Aquests documents o pantalles equivalents han d'estar accessibles dins del SIF, preferiblement en un apartat tipus "Sistema / VERI*FACTU / Documentacio":

- Declaracio responsable signada de la versio activa del SIF.
  - Font actual: `01-compliment-aeat/declaracio-responsable-sif-prisma.md`
  - Format final recomanat: PDF signat i llegible.
- Identificacio de la versio activa del SIF.
  - Font actual: `05-governanca-operacio/19-registre-versions-i-canvis-sif.md`
  - Ha d'indicar nom del SIF, codi intern, versio, data d'entrada en produccio i responsable.
- Consulta/exportacio dels registres fiscals, documents i logs conservats pel SIF.
  - No es nomes un document: ha de ser funcionalitat del SIF.

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
