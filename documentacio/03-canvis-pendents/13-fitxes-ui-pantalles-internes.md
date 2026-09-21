# Fitxes UI de pantalles internes

Data de tall: 2026-09-16

Estat: definicio visual i d'interaccio preparada; implementacio i captures reals pendents.

## 1. Criteris comuns

Aquestes fitxes concreten la disposicio i els estats de les pantalles definides a `12-contracte-tecnic-pantalles-internes.md`. No substitueixen les validacions del servidor.

- cap accio fiscal critica s'executa des del llistat sense detall o confirmacio;
- la capcalera mostra entorn, usuari i rol actiu;
- estat fiscal, cobrament i estat AEAT es mostren separats;
- els botons depenen de `available_actions` retornat pel servidor;
- ocultar o deshabilitar un boto no substitueix el control de permisos;
- els avisos apareixen abans de l'accio i mantenen codi i severitat;
- tota operacio usa `Previsualitzar` abans de `Confirmar`;
- despres de confirmar, la vista es refresca i el preview deixa de ser reutilitzable;
- en mobil, les taules passen a files apilades i cap accio queda fora de pantalla.

| Estat comu | Representacio |
|---|---|
| Carregant | Indicador dins del bloc afectat, sense desplacar la pagina |
| Buit | Missatge concret i seguent accio possible |
| Error recuperable | Avis amb `Tornar-ho a provar` i `request_id` |
| Error bloquejant | Causa, accio requerida i incidencia si existeix |
| Sense permis | Acces denegat sense filtrar dades de l'objecte |
| Confirmat | Resum immutable i enllacos al resultat |

## 2. Navegacio interna

```text
Alumnes
  Consulta / Modifica alumne
  Passar pagaments
  Generar factura abans de pagar
  Consulta / Edita / Anula factura

VERI*FACTU
  Resum
  Factures
  Incidencies
  Panell SIF
```

L'apartat VERI*FACTU de la intranet es un resum i punt d'entrada. La gestio fiscal completa continua a `pay.prisma.cat/sif`.

## 3. Fitxa UI: Passar pagaments

Ruta funcional: `/alumnes/pagaments/`.

```text
+------------------------------------------------------------------+
| Passar pagaments                         [Entorn] [Usuari / rol]  |
+------------------------------------------------------------------+
| Cerca: [Tipus v] [Valor________________] [Cercar]                 |
| Filtres: cobrament | origen | data | conciliacio                  |
+------------------------------------------------------------------+
| Factura | Receptor | Total | Cobrat | Pendent | Estat | Accions  |
+------------------------------------------------------------------+
| Factura i receptor | Estat fiscal | Cobrament | Relacions         |
| [Avisos estructurats]                                            |
| Data [____] Import [____] Metode [v] Referencia [____________]   |
| Observacions [_______________________________________________]   |
|                         [Previsualitzar] [Confirmar pagament]     |
+------------------------------------------------------------------+
| Resultat / auditoria                                             |
+------------------------------------------------------------------+
```

La cerca admet un sol criteri actiu: UUID SIF, numero visible, NIF/NIE, IDPAG, codi regal o referencia bancaria. Columnes minimes: numero, receptor, origen, total, cobrat, pendent, cobrament, conciliacio i accions.

| Estat | Accio principal | Missatge |
|---|---|---|
| Factura existent i operable | `Previsualitzar pagament` | El cobrament s'associara a la factura existent |
| Sense factura, cas facturable | `Previsualitzar emissio i pagament` | Es creara factura i s'hi registrara el pagament |
| Factura abans del cobrament | `Previsualitzar pagament` | La factura ja existeix; no se'n creara una altra |
| Referencia duplicada idempotent | `Obrir resultat existent` | Aquest pagament ja es va processar |
| Incoherencia o manca de permisos | Cap confirmacio | Operacio bloquejada i causa visible |

`Confirmar pagament` nomes s'activa amb preview vigent. Canviar import, data, metode, referencia o factura invalida el preview. El resultat mostra UUID del pagament, factura, import aplicat, estat de cobrament, `audit_event_id` i acces a factura o incidencia.

## 4. Fitxa UI: Generar factura abans de pagar

Ruta funcional: `/alumnes/genera-factura-abans-pagar/`.

```text
1. Seleccio              2. Receptor i linies          3. Confirmacio
[inscripcions]           [dades fiscals]               [resum immutable]
[imports/origen]         [conceptes i impostos]        [avisos]
[continuar]              [previsualitzar]              [emetre factura]
```

Pas 1:

- cercador d'alumne, empresa/responsable o operacio;
- inscripcions candidates amb import i estat;
- avis de factura previa o cobertura utilitzada;
- seleccio multiple nomes quan la regla fiscal permet agrupar.

Pas 2:

- receptor, NIF/CIF, nom o rao social, adreca, codi postal, municipi i pais;
- linies amb concepte, base, tipus, quota i total;
- origen i referencia idempotent per a usuaris tecnics autoritzats;
- bloqueig si falten dades o s'inclou un pagament inicial.

Pas 3 mostra l'avis `Es generara una factura fiscal real abans del cobrament`, receptor, linies, total i estat futur `PENDENT DE COBRAMENT`. El resultat mostra numero, UUID, estat fiscal, AEAT, cobrament i PDF/QR. `Registrar pagament` obre `Passar pagaments` amb la factura preseleccionada.

## 5. Fitxa UI: Consulta - Edita - Anula factura

Ruta funcional: `/alumnes/factura/`.

Llistat:

```text
Filtres: numero | UUID | receptor | data | tipus | fiscal | cobrament | AEAT
Numero | Data | Receptor | Tipus | Total | Fiscal | Cobrament | AEAT | Accions
```

Accions rapides: `Veure` i, si esta autoritzat, `Descarregar PDF`. Rectificar o anul·lar exigeix entrar al detall.

Ordre del detall:

1. numero, tipus i estats separats;
2. avisos i incidencies;
3. emissor i receptor fiscal;
4. linies, impostos i totals;
5. pagaments i assignacions;
6. documents segons permisos;
7. relacions i rectificatives;
8. historial auditable;
9. accions disponibles.

`Dades administratives` pot contenir camps no fiscals editables. `Dades fiscals emeses` es sempre de nomes lectura. Davant un canvi fiscal, mostrar `Aquesta dada forma part d'una factura emesa. Cal iniciar una rectificacio.`

El flux de rectificacio/anulacio inclou motiu controlat, mode quan pertoqui, justificacio, dades corregides, preview comparatiu, efecte sobre cobrament i boto explicit `Emetre rectificativa`. El resultat enllaca original i rectificativa; mai fa un `UPDATE` destructiu.

## 6. Fitxa UI: Intranet alumne

```text
Factures i pagaments
Numero | Concepte | Data | Total | Estat de pagament | Document
```

La vista mostra numero, data, concepte, total, cobrament simplificat, PDF disponible i avis accionable. No mostra UUID, hash, cua AEAT, logs ni errors tecnics, excepte una referencia de suport.

Si una empresa o responsable es el receptor, l'alumne veu: `Aquesta inscripcio esta coberta per una factura emesa a una altra entitat.` No veu dades fiscals ni altres participants.

Una URL manipulada retorna una vista generica de recurs no disponible, registra la denegacio i no confirma si el document existeix.

## 7. Fitxa UI: Empresa o responsable

L'acces es fa amb autenticacio especifica o enllac segur. Si el token ha caducat, s'ha utilitzat o s'ha revocat, la resposta es neutra i permet sol·licitar un nou acces.

Contingut:

- identitat de l'entitat o responsable;
- factures autoritzades;
- inscripcions cobertes amb dades minimes;
- estat de cobrament i documents;
- enllac de pagament vigent, si correspon;
- contacte de gestio.

Accions: `Veure factura`, `Descarregar PDF`, `Pagar` i `Contactar`. No permet emetre, rectificar, anul·lar ni registrar pagaments manualment. No mostra navegacio interna, altres empreses, logs o participants fora de cobertura.

## 8. Fitxa UI: indicador i avisos VERI*FACTU

| Estat | Etiqueta | Accio |
|---|---|---|
| `OK` | `VERI*FACTU: sense pendents` | Obrir resum |
| `WARNING` | `VERI*FACTU: revisio pendent (N)` | Obrir pendents |
| `INCIDENT` | `VERI*FACTU: incidencia (N)` | Obrir incidencies |
| Desconegut | `VERI*FACTU: estat no disponible` | Reintentar o obrir panell |

Una fallada de connexio mai es representa com a `OK`.

```text
Estat general | Ultima actualitzacio
Pendents de cua | Errors | Incidencies obertes | Documents pendents
[Obrir panell SIF] [Veure incidencies]
```

Els comptadors enllacen a vistes filtrades quan el rol ho permet. `INFO` dona context; `WARNING` pot requerir confirmacio; `BLOCKING` deshabilita confirmar; `INCIDENT` mostra referencia i acces autoritzat. Els avisos no desapareixen mentre afectin l'operacio.

## 9. Accessibilitat i usabilitat

- operacio completa amb teclat i ordre de focus coherent;
- etiqueta visible per camp i errors associats;
- resum d'errors al principi del formulari;
- estats no diferenciats nomes per color;
- confirmacions amb verb i objecte concrets;
- imports alineats i moneda visible;
- dates visibles `dd/mm/aaaa` i valor API ISO;
- taules amb capcaleres accessibles i alternativa apilada en mobil.

## 10. Captures obligatories

Per cada pantalla implementada:

1. estat inicial o llistat;
2. formulari abans del preview;
3. preview amb avisos i accio SIF prevista;
4. bloqueig representatiu;
5. resultat confirmat;
6. estat sense permisos o visibilitat limitada;
7. vista mobil sense solapaments.

Les captures segueixen `23-annex-captures-pantalla.md`, anonimitzen dades personals i indiquen prova, entorn, versio i resultat.
