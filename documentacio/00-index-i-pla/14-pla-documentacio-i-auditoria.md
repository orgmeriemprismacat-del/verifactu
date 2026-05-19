# 14 - Pla de documentacio i auditoria

> Document per ordenar quina documentacio s'ha de preparar, quina ha d'estar disponible dins del SIF i quina s'ha de conservar com a evidencia interna davant direccio, assessor, auditoria o AEAT.

## 1. Principi general

Hi ha quatre tipus de documentacio:

1. Documentacio de context: explica com funciona PrisMa.
2. Documentacio de canvis: explica que s'ha de modificar per VERI*FACTU.
3. Documentacio d'estat final: explica com queda el sistema quan estigui acabat.
4. Documentacio de compliment: declaracio responsable, versions, rols, evidencia i criteris normatius.

## 2. Que ha d'estar disponible formalment al SIF

La documentacio que ha d'estar disponible de forma clara dins del sistema es, com a minim:

- declaracio responsable del SIF per la versio concreta;
- identificacio de versio;
- informacio del sistema, productor/titular i composicio segons declaracio responsable;
- acces als registres, documents i logs que el sistema hagi de conservar.

Segons l'Orden HAC/1177/2024, article 15, la declaracio responsable ha d'estar disponible de forma llegible, individualitzada i accessible dins del propi sistema informatic.

Aixo no vol dir que tots els documents interns del projecte hagin d'estar publicats dins del SIF. El SIF ha de permetre accedir a la declaracio responsable, la identificacio de versio i els registres/documents/logs que el sistema conserva. La resta de documentacio s'ha de mantenir ordenada com a evidencia interna i aportar-la si direccio, assessor, auditoria o AEAT la demanen.

## 3. Que cal conservar com a evidencia interna

No tots els documents de projecte han d'estar publicats dins del SIF, pero si convé conservar-los:

- documentacio de fluxos;
- mapa de base de dades;
- procediments d'intranet/ecommerce;
- inventari de canvis;
- checklist de posada en produccio;
- registre de versions;
- decisions sobre permisos;
- decisions sobre rectificatives, saldos i compensacions;
- captures de pantalles finals;
- proves de Redsys, PDF, QR, AEAT i reintents;
- registre d'incidencies.

## 4. Que podria demanar l'AEAT en inspeccio

Cal estar preparats per facilitar:

- declaracio responsable del sistema i versio;
- accés o exportacio dels registres de facturacio;
- registres d'events o logs que corresponguin;
- factures, rectificatives i documents;
- explicacio de com es garanteix integritat, conservacio, accessibilitat, llegibilitat, traçabilitat i inalterabilitat;
- explicacio de com s'impedeix modificar factures emeses sense rectificativa/event;
- accessos, permisos i responsables.

## 5. Documents de canvis

Serveixen per planificar i executar el projecte:

- `../03-canvis-pendents/11-inventari-canvis-pendents.md`
- `../03-canvis-pendents/04-fluxos-facturacio.md`
- `../03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `../03-canvis-pendents/07-pantalles-intranet.md`
- `../03-canvis-pendents/08-correus-i-plantilles.md`
- `../03-canvis-pendents/09-checklist-posada-en-produccio.md`
- `../03-canvis-pendents/10-procediments-intranet-ecommerce.md`

Important: `../03-canvis-pendents/07-pantalles-intranet.md` es document de canvis. Ha d'explicar que cal modificar a les pantalles actuals, quins riscos fiscals tenen i quines accions s'han d'implementar. No es el document d'estat final.

## 6. Documents d'estat final

Serveixen per explicar com queda el sistema un cop acabat:

- `../04-estat-final/15-estat-final-sistema.md`
- `../04-estat-final/16-estat-final-pantalles.md`
- `../04-estat-final/17-estat-final-bd-relacions.md`
- `../04-estat-final/18-estat-final-operacio-incidencies.md`

Important: `../04-estat-final/16-estat-final-pantalles.md` es el document que descriu les pantalles finals d'intranet, ecommerce, intranet alumne i possibles accessos empresa/responsable. Aquest document haura d'incorporar captures finals quan cada pantalla estigui implementada.

## 7. Captures de pantalla

Caldrà afegir captures finals de:

- ecommerce, pas de dades de facturacio;
- checkout/pagament;
- confirmacio de pagament;
- consulta factura alumne;
- consulta factura empresa/responsable;
- consulta/modifica alumne;
- dades pagament;
- canvi de curs;
- baixa;
- passar pagaments;
- generar factura abans de cobrar;
- veure factura;
- rectificativa;
- notificacions fiscals;
- llistat/export de factures i registres.

Les captures finals es documentaran com a estat final, no com a tasques pendents. Si durant el projecte cal comentar una pantalla actual, aixo anira al document de canvis corresponent.

## 8. Documents addicionals recomanats

Despres de revisar el mapa documental, convé afegir aquests documents:

- `../05-governanca-operacio/19-registre-versions-i-canvis-sif.md`: versions del SIF, data d'entrada en produccio, canvis fiscals i declaracio responsable associada.
- `../05-governanca-operacio/20-pla-proves-validacio-sif.md`: proves de Redsys, idempotencia, concurrencia, PDF/QR, AEAT, reintents, rectificatives i permisos.
- `../05-governanca-operacio/21-seguretat-permisos-accessos.md`: rols, permisos d'aplicacio, permisos MySQL, accessos a BD fiscal i bloqueig d'updates/deletes sobre factures emeses.
- `../05-governanca-operacio/22-manual-operatiu-intern.md`: manual de treball per Adam, Pablo, secretaria/gestio i administracio.
- `../05-governanca-operacio/23-annex-captures-pantalla.md`: captures finals amb explicacio de cada pantalla.
- `../05-governanca-operacio/24-diccionari-camps-i-valors.md`: definicio dels camps i valors controlats, per exemple `ESTAT_FACTURA`, `ESTAT_AEAT`, `TIPUS_MOVIMENT`, `SOURCE_TYPE`, `URL_STATUS`.

## 9. Matriu documental davant AEAT

Classificacio practica:

- Disponible dins del SIF: declaracio responsable, versio, identificacio del sistema, consulta/export de registres, documents i logs conservats.
- Conservat com evidencia interna: arquitectura, fluxos, mapa BD, pantalles, procediments, proves, permisos, inventari de canvis, captures i decisions fiscals.
- Documentacio de treball: notes internes, captures preliminars i esborranys. No cal que estiguin dins del SIF, pero es poden conservar en l'expedient intern del projecte.

## 10. Llista concreta per penjar o fer accessible dins del SIF

### 10.1. Necessari dins del SIF

- Declaracio responsable signada de la versio activa del SIF.
  - Document de treball: `../01-compliment-aeat/declaracio-responsable-sif-prisma.md`
  - Format final recomanat: PDF signat.
- Identificacio de versio activa.
  - Document de treball: `../05-governanca-operacio/19-registre-versions-i-canvis-sif.md`
  - Ha d'indicar versio, data, sistema, productor/titular i declaracio responsable vinculada.
- Consulta/export de registres fiscals, documents i logs del SIF.
  - Aixo ha de ser funcionalitat del sistema, no nomes fitxers Markdown.

Ubicacio decidida:

```text
pay.prisma.cat / sif / sistema
```

La intranet principal tindra accessos cap a aquest panell i podra mostrar un indicador visual de pendents i un resum d'incidencies. Tot i aixi, la font oficial de documentacio, versio, registres, logs i incidencies sera el SIF a `pay.prisma.cat`.

Es pot crear un perfil `AUDITOR_FISCAL` o `AEAT_READONLY` de nomes lectura, activable si cal, restringit a informacio amb transcendencia tributaria.

### 10.2. Recomanat dins del SIF per administradors

- `../01-compliment-aeat/documentacio-sif-aeat.md`
- `../05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `../05-governanca-operacio/24-diccionari-camps-i-valors.md`

### 10.3. No cal penjar dins del SIF, pero cal conservar

- documents de context i estat actual;
- inventari de canvis pendents;
- documents d'estat final;
- pla de proves;
- manual operatiu intern;
- captures finals;
- decisions fiscals i tecniques del projecte.

## 11. Referencies

- BOE - Orden HAC/1177/2024, article 15: https://www.boe.es/buscar/act.php?id=BOE-A-2024-22138
- AEAT - Certificacio dels sistemes informatics: https://sede.agenciatributaria.gob.es/Sede/ca_es/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/certificacion-sistemas-informaticos-declaracion-responsable.html
- BOE - Real Decreto 1007/2023: https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840
