# 22 - Manual operatiu intern

> Manual per a l'us diari del sistema per part de gestio, secretaria, Adam, Pablo i administracio.

## 1. Objectiu

Explicar que s'ha de fer en cada cas real sense entrar en codi.

## 2. Procediments pendents de completar

- curs individual pagat per Redsys;
- pagament per transferencia;
- pagament per compensacio;
- factura abans de cobrar;
- pack;
- grup;
- regal;
- USOC;
- canvi de curs;
- baixa;
- devolucio;
- saldo;
- morositat;
- rectificativa;
- factura manual;
- analitzar fitxer TPV i passar pagaments a `/alumnes/pagaments/`;
- consultar, editar accions fiscals i anul·lar factura a `/alumnes/factura/`;
- error AEAT;
- PDF no generat;
- factura electronica.
- consulta panell SIF `pay.prisma.cat/sif`;
- gestio d'incidencies SIF;
- consulta de declaracio responsable i versio activa.

## 3. Format de cada procediment

Cada procediment haura d'indicar:

- qui ho pot fer;
- pantalla;
- passos;
- validacions;
- efecte fiscal;
- correu enviat;
- notificacions;
- que no s'ha de fer mai.

## 4. Regles operatives generals

### 4.1. Abans i despres d'emetre factura

Abans d'emetre factura:

- es poden corregir dades de venda, curs, import, descompte o receptor fiscal dins els fluxos previstos;
- cal validar dades fiscals abans de crear factura real;
- si el canvi afecta preu o concepte, cal recalcular i previsualitzar abans de confirmar.

Despres d'emetre factura:

- no es modifica receptor, concepte o import directament;
- el canvi va per rectificativa, devolucio, compensacio, saldo o event controlat;
- el PDF de factura nova s'ha de consultar com a document immutable del SIF.

### 4.2. Permisos

Regla practica:

| Accio | Qui la pot fer |
| --- | --- |
| Consulta d'alumne | Meriem, Adam, Pablo, Isa si dona suport. |
| Passar pagaments | Meriem, Adam, Pablo. |
| Consulta de factures | Meriem, Adam, Pablo. |
| Marcar `E_FACT` | Meriem, Adam, Pablo. |
| Rectificativa operativa | Meriem, Adam, Pablo. |
| Configuracio SIF | Meriem. |
| Exportacions fiscals | Meriem i Adam, segons cas. |
| Suport Moodle/cursos | Isa quan calgui, sense accions fiscals critiques. |

El fet que una pantalla mostri un boto no es suficient. L'accio ha de validar permisos al servidor.

### 4.3. Consulta - Modifica alumne

Us:

- consultar estat academic, economic i fiscal;
- iniciar canvi de curs, baixa, pagament o consulta de factura;
- veure avisos quan hi ha incidencia fiscal relacionada.

No fer:

- editar imports o dades de factura ja emesa;
- canviar `FACTURA_RELACIONADA` com a solucio manual;
- considerar `INSC_CURS` com a estat fiscal.

### 4.4. Baixes

Sequencia:

1. Registrar baixa administrativa.
2. Esperar decisio economica del client: retorn, saldo o no retorn.
3. Quan hi ha decisio, tramitar impacte fiscal si correspon.

Regla:

```text
Baixa administrativa no genera rectificativa automatica.
```

### 4.5. Reclamacions i morositat

Sequencia operativa:

1. Abans de començar curs: comprovar primer pagament o justificacio.
2. Segona setmana: si no hi ha pagament ni justificacio, revisar baixa.
3. Final de curs: reclamar pendent.
4. Una setmana despres: nova reclamacio.
5. Un mes despres: nova reclamacio.
6. Despres: marcar morositat i continuar seguiment.

Regla:

```text
Morositat no es baixa i no rectifica factura per si sola.
```

Cada reclamacio ha de conservar import total, pagat, pendent, fase, data, missatge i estat.

### 4.6. Apartat VERI*FACTU de la intranet

Serveix per:

- veure indicador visual de pendents;
- veure resum d'incidencies;
- obrir el panell SIF;
- obrir documents i exportacions.

No serveix per:

- resoldre oficialment incidencies;
- editar factures;
- canviar configuracio SIF.

La resolucio oficial viu a `pay.prisma.cat/sif`.
