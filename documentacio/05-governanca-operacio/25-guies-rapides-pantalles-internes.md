# Guies rapides de pantalles internes

Data de tall: 2026-09-17

Estat: procediment operatiu preparat; nomes aplicable quan les pantalles i permisos estiguin implementats i validats.

## 1. Com usar aquestes guies

Aquest document resumeix el treball diari. Les regles completes continuen a `22-manual-operatiu-intern.md`, `21-seguretat-permisos-accessos.md` i `12-contracte-tecnic-pantalles-internes.md`.

Regla comuna:

```text
Consultar -> verificar receptor i estat -> previsualitzar -> llegir avisos -> confirmar -> comprovar resultat
```

Aturar sempre si:

- el receptor fiscal no es clar;
- l'import o el pendent no coincideix;
- hi ha referencia duplicada o estat contradictori;
- la pantalla no mostra l'accio SIF prevista;
- el SIF no respon o l'ultima sincronitzacio no es vigent;
- el rol no permet l'accio;
- la factura te diverses inscripcions i no es veu l'assignacio;
- el preview ha caducat o les dades han canviat.

En aquests casos, no corregir SQL, `PAGAMENT`, `FACTURA_RELACIONADA`, imports o factura a ma. Conservar el `request_id` i escalar.

## 2. Rols i circuit d'escalat

| Rol | Pot operar | Ha d'escalar |
|---|---|---|
| Pablo / gestio-secretaria | Consultes, pagaments, factures i rectificatives permeses | Errors tecnics, dubte fiscal, duplicats o divergencia SIF-llegat |
| Adam / operador facturacio | Consultes, moviments, factures i exportacions permeses | Configuracio, incidencies critiques o decisio fiscal no tipificada |
| Meriem / responsable tecnica SIF | Configuracio, proves, incidencies, versions i suport tecnic | Decisio juridica/fiscal no documentada o aprovacio formal de produccio |
| Isa / suport | Consulta de suport autoritzada | Qualsevol emissio, pagament, rectificacio o dada fiscal sensible |
| Auditor/AEAT | Consulta temporal i exportacio autoritzada | Qualsevol peticio de canvi |
| Alumne | Consulta de documents propis | Error de visibilitat, cobrament o document |
| Empresa/responsable | Consulta de documents coberts i pagament per URL valida | Token, cobertura, receptor o document incorrecte |

Escalat intern:

1. copiar `request_id`, pantalla, hora i accio intentada;
2. anotar factura/UUID o referencia parcial, sense copiar secrets;
3. fer captura anonimitzada de l'avis;
4. obrir o enllacar incidencia SIF si la pantalla ho permet;
5. no repetir la confirmacio fins conèixer el resultat de l'intent anterior.

## 3. Guia rapida: Passar pagaments

Qui: Meriem, Adam o Pablo.

### Abans

- disposar de referencia bancaria/TPV, data, import i metode;
- analitzar primer el fitxer TPV si el cobrament hi apareix;
- cercar amb un sol criteri;
- comprovar receptor i si hi ha factura SIF, factura previa o cobertura d'empresa.

### Passos

1. Seleccionar la factura o operacio correcta.
2. Revisar total, cobrat, pendent, estat fiscal i conciliacio.
3. Informar data, import, metode, referencia i observacio.
4. Previsualitzar.
5. Llegir l'accio prevista:
   - `Registrar pagament`: ja existeix factura;
   - `Emetre factura i registrar pagament`: no existeix i el cas ho permet;
   - `Revisio/incidencia`: no confirmar.
6. Confirmar una sola vegada.
7. Comprovar UUID de pagament, factura associada i nou estat de cobrament.

### Aturar

- import zero, negatiu o superior al pendent sense classificacio explicita;
- referencia ja utilitzada sense resultat idempotent visible;
- factura anul·lada, substituida o d'un altre receptor;
- URL individual activa quan paga empresa/responsable;
- estat final desconegut despres d'un timeout.

### Resultat correcte

- factura existent: un pagament nou, cap factura nova;
- cas sense factura: factura i pagament dins l'operacio prevista;
- reintent: mateix resultat idempotent;
- dubte: incidencia, no actualitzacio silenciosa.

## 4. Guia rapida: Generar factura abans de pagar

Qui: Meriem, Adam o Pablo.

### Abans

- confirmar que es necessita factura real, no proforma;
- tenir receptor fiscal complet;
- verificar que les inscripcions es poden agrupar;
- comprovar que no existeix factura incompatible.

### Passos

1. Seleccionar operacio o inscripcions candidates.
2. Revisar curs, edicio, imports i cobertura.
3. Seleccionar empresa/responsable o receptor correcte.
4. Revisar NIF/CIF, rao, adreca, codi postal, municipi i pais.
5. Revisar concepte, linies, impostos i total.
6. Previsualitzar sense pagament inicial.
7. Confirmar l'avis de factura fiscal real pendent de cobrament.
8. Comprovar numero, UUID, estat pendent i PDF/QR o incidencia documental.
9. Utilitzar `Passar pagaments` quan arribi el cobrament.

### Aturar

- receptor ambigu o incomplet;
- inscripcions de cursos/edicions incompatibles;
- factura previa o referencia duplicada;
- el preview inclou un cobrament;
- el resultat no mostra factura SIF identificable.

### Resultat correcte

- `EMESA_ABANS_COBRAMENT = 1`;
- cobrament pendent;
- `E_FACT` no activat automaticament;
- URL individual eliminada o substituida quan paga empresa/responsable.

## 5. Guia rapida: Consulta - Edita - Anula factura

Qui: Meriem, Adam o Pablo; consulta limitada segons rol.

### Consulta

1. Cercar per numero, UUID, receptor o referencia permesa.
2. Distingir factura SIF de factura historica no VERI*FACTU.
3. Revisar per separat estat fiscal, cobrament i AEAT.
4. Consultar pagaments, assignacions, documents i rectificatives.

### Correccio

1. Confirmar si la dada es administrativa o fiscal.
2. Editar directament nomes dades administratives autoritzades.
3. Per dada fiscal emesa, iniciar rectificacio.
4. Seleccionar motiu i mode aplicable.
5. Revisar original i proposta al preview.
6. Verificar efecte sobre pagaments, devolucio o saldo.
7. Confirmar `Emetre rectificativa` una sola vegada.

### Aturar

- la pantalla ofereix editar import, receptor, numero o concepte fiscal en lloc;
- no es veu la factura original;
- falta motiu o assignacio entre inscripcions;
- el cas podria ser anul·lacio registral o subsanacio i no esta classificat;
- es proposa esborrar PDF o factura.

### Resultat correcte

- original immutable;
- rectificativa relacionada i identificada;
- motiu, actor i resultat auditats;
- devolucio/saldo separats de la rectificacio quan correspongui.

## 6. Guia rapida: consulta d'alumne

Qui: alumne autenticat; gestio pot donar suport sense assumir-ne la identitat.

L'alumne pot:

- veure factures on es receptor i la visibilitat ho permet;
- consultar estat simplificat de cobrament;
- descarregar el document autoritzat;
- veure que una empresa cobreix la inscripcio sense veure la seva factura completa.

Si informa d'un problema:

1. demanar numero o referencia visible, no captures amb dades de tercers;
2. comprovar la factura amb un rol intern autoritzat;
3. no enviar manualment un PDF d'empresa/grup;
4. escalar si el document propi no apareix o apareix un document aliè.

Qualsevol filtracio de dades d'una altra persona o empresa es incidencia de seguretat i obliga a retirar temporalment l'acces afectat fins revisar-lo.

## 7. Guia rapida: empresa o responsable

Qui: receptor/contacte autoritzat mitjancant canal extern segur.

Pot:

- veure les factures incloses en el seu abast;
- veure inscripcions cobertes amb dades minimes;
- descarregar PDF autoritzat;
- pagar amb URL vigent;
- contactar amb gestio.

No pot entrar a la intranet principal ni modificar factura o pagament.

Si el token es invalid, caducat, utilitzat o revocat:

1. no mostrar si el document existeix;
2. no enviar el token complet per correu intern o captura;
3. verificar el contacte per un canal conegut;
4. revocar l'enllac anterior i generar-ne un de nou si el procediment ho permet;
5. conservar auditoria de l'accés denegat i del nou enllac.

## 8. Guia rapida: avisos VERI*FACTU

| Estat | Que significa | Que fer |
|---|---|---|
| `INFO` | Context de l'operacio | Llegir i continuar si les dades son correctes |
| `WARNING` | Risc o excepcio controlada | Revisar i confirmar nomes si es compren l'efecte |
| `BLOCKING` | L'operacio no es valida | Corregir la causa o escalar; no forçar |
| `INCIDENT` | Problema oficial registrat | Obrir el panell SIF i seguir la incidencia |
| Estat desconegut | No hi ha resposta fiable | No assumir `OK`; revisar ultima sincronitzacio i escalar |

Un indicador de pendents serveix per obrir el resum. No resol ni tanca la incidencia.

## 9. Guia rapida: SIF no disponible o timeout

1. No repetir immediatament una confirmacio.
2. Conservar `request_id`, hora, factura/referencia i captura de l'avis.
3. Consultar la factura o pagament en una nova lectura.
4. Si existeix el resultat, tractar-lo com a exit/reintent idempotent.
5. Si no hi ha resultat concloent, obrir incidencia i deixar l'operacio en revisio.
6. Meriem revisa logs, auditoria i cues abans d'autoritzar reintent.
7. No executar fallback local ni actualitzar la BD llegada per fer desapareixer el pendent.

## 10. Guia rapida: final de jornada

Gestio revisa:

- pagaments confirmats sense factura associada;
- previews iniciats sense resultat terminal;
- incidencies `BLOCKING` o `INCIDENT` obertes;
- factures previes pendents amb cobrament rebut;
- documents PDF/QR pendents o fallits;
- avisos de visibilitat o tokens denegats;
- divergencies SIF-llegat.

No cal resoldre tecnicament cada incidencia el mateix dia, pero si deixar-la registrada, assignada i sense una operacio economica amb resultat desconegut.

## 11. Registre minim d'una incidencia de pantalla

```text
Data/hora:
Entorn i versio:
Usuari i rol:
Pantalla:
Accio intentada:
Factura/UUID o referencia parcial:
Request ID:
Codi i severitat de l'avis:
Estat abans del preview:
Resultat observat:
S'ha repetit la confirmacio?: NO/SI
Captura anonimitzada:
Responsable assignat:
```

No adjuntar tokens complets, claus, certificat, dades bancaries completes ni documents amb dades personals innecessaries.
