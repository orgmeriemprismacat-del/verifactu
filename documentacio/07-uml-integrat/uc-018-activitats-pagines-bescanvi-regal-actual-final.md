# UC-018 · Activitats ACTUAL/FINAL per pàgina i apartat

## 1. Superfícies executables auditades — revalidació 2026-10-04

| Superfície | ACTUAL | FINAL |
| --- | --- | --- |
| `.htaccess` | `/bescanvia-regal` → `pagina_bescanvia.php`; elimina dependència de fitxer prova absent | Igual |
| `pagina_bescanvia.php` | carrega `mostrarBescanvia.min.js?ver=6.0` | Igual |
| `mostrarBescanvia.min.js` | controlador client; totes les peticions amb codi/DNI/correu van per POST | Igual |
| `mostrar_pagina_bescanvia.php` | render formulari codi | Igual |
| `codiRegalValid.php` | POST-only; resposta neutra | Igual |
| `buscarCursRegalat.php` | POST-only + revalidació server-side | Igual |
| `bescanviaUnCurs.php` | render curs/edició | Igual |
| `inscripcioDuplicada.php` | compartit; POST en UC-018 perquè DNI no vagi a URL | Igual |
| `buscarSiHaRealitzatElCurs.php` | compartit; POST en UC-018 perquè DNI/curs no vagin a URL | Igual |
| `enviamentPubli.php` | compartit; POST en UC-018 perquè correu no vagi a URL | Igual |
| `enviarInscripcioBescanvia.php` | POST-only; lock, FACT_REL>0, guard de curs/hores abans del commit, get-or-create, SIF, correus | Igual |
| `SifGiftRedemptionClient` | POST/HMAC redeem + notificacions | Igual |
| `redeem.php` | endpoint intern autoritatiu | Igual |
| `notifications.php` | claim/complete at-most-once | Igual |
| `GiftRedemptionConfirmationToken.php` + pàgina/JS/AJAX | token AES-256-GCM al fragment; POST-only; no-store/no-referrer; correu emmascarat | Igual |
| preflight/verificador | codi implementat | execució [ENV] |

## 2. Activitat P-BES-01 · Introduir i validar codi

```mermaid
flowchart TD
A[Obrir /bescanvia] --> B[Carregar bundle rastrejable]
B --> C[Introduir codi]
C --> D[POST codiRegalValid]
D --> E{Bescanviable?}
E -- no --> F[Missatge neutre]
E -- sí --> G[POST buscarCursRegalat]
G --> H[Revalidar server-side]
H --> I[Curs/modalitat]
```

## 3. Activitat P-BES-02 · Seleccionar curs i edició

```mermaid
flowchart TD
A[Curs/modalitat] --> B[Render bescanviaUnCurs]
B --> C{Regal genèric?}
C -- sí --> D[Escollir curs de la mateixa categoria d'hores]
C -- no --> E[Mostrar curs regalat i permetre canvi]
E --> H{Canvia de curs?}
H -- no --> F[Escollir edició del curs regalat]
H -- sí --> I[Escollir altre curs de les mateixes hores]
D --> F
I --> F
F --> G[Omplir dades personals]
```

El canvi **abans del bescanvi** forma part de l'UC-018 legacy: un regal concret pot bescanviar-se pel mateix curs o per un altre amb les mateixes hores. Un canvi **després** d'haver consumit el dret continua pertanyent a UC-26/71.

## 4. Activitat P-BES-03 · Precomprovacions personals i duplicat

```mermaid
flowchart TD
A[Dades validades al navegador] --> M[POST correu a enviamentPubli]
M --> H[POST DNI + curs a buscarSiHaRealitzatElCurs]
H --> X{Curs derivat?}
X -- sí --> H2[POST DNI + curs derivat]
X -- no --> B[POST DNI + curs + any + edició]
H2 --> B
B --> C{Inscripció existent?}
C -- sí --> D[No crear altra matrícula]
C -- no --> E[Continuar]
```

DNI i correu no apareixen en query string en cap d'aquestes comprovacions UC-018.

## 5. Activitat P-BES-04 · Writer legacy

```mermaid
flowchart TD
A[POST dades + codi] --> B[Begin transaction]
B --> C[SELECT regal FOR UPDATE]
C --> D{Existeix i FACT_REL > 0?}
D -- no --> X[Error / rollback]
D -- sí --> V[Resoldre hores de l'edició seleccionada]
V --> W{CCURS compatible?}
W -- no --> X
W -- sí --> E{USAT o candidata existent?}
E -- sí --> F[Validar curs DNI import factura codi]
E -- no --> G[Crear una única ID_INSC]
F --> H[Reutilitzar ID_INSC]
G --> I[Commit legacy]
H --> I
I --> J[POST/HMAC redeem al SIF]
```

## 6. Activitat P-BES-05 · Saga SIF

```mermaid
flowchart TD
A[redeem] --> B[Context autoritatiu]
B --> X{regal.CCURS numèric?}
X -- sí --> Z[Resoldre hores de l'edició seleccionada]
X -- no --> Y{CURS seleccionat = CCURS?}
Y -- sí --> C[Claim holder + RESERVE]
Y -- no --> Y2[Resoldre hores històriques unívoques del curs regalat]
Y2 --> Q{Mateixes hores?}
Z --> Q
Q -- no --> R[CONFLICT 409]
Q -- sí --> C
C --> D[COMPENSATION_ALLOCATION]
D --> E[CONSUME]
E --> F[Complete operation]
F --> G[Reconciliar regal.USAT]
G --> H[Crear/reutilitzar 6 outbox]
```

## 7. Activitat P-BES-06 · Replay/recovery

```mermaid
flowchart TD
A[Reintent] --> B[Lock regal]
B --> C{USAT mateixa ID?}
C -- sí --> D[Validar coherència]
C -- no --> E[Get-or-create normal]
D --> F[Reentrar al SIF]
E --> F
F --> G[Reutilitzar saga/moviment/consum]
G --> H[Recuperar o reutilitzar outbox]
H --> I[Claim correus pendents]
```

**No** hi ha retorn prematur abans del SIF.

## 8. Activitat P-BES-07 · Notificacions

```mermaid
flowchart TD
A[Bundle de 6] --> B[Claim individual]
B --> C{should_send?}
C -- no SENT --> D[No reenviar]
C -- sí --> E[SMTP]
E --> F{Resultat}
F -- ok --> G[Complete SENT]
F -- error --> H[Complete FAILED]
H --> I[Registrar reconciliació operativa pendent]
I --> J[Continuar a confirmació de matrícula]
```

`SENDING` ambigu no es reclama automàticament. Un error de comunicació posterior al consum no converteix la inscripció en fallida; queda com a incidència d'outbox/OPS.

## 9. Activitat P-BES-08 · Confirmació

```mermaid
flowchart TD
A[Cap incidència de correu bloquejant resposta] --> B[Emetre token v2 AES-256-GCM]
B --> C[Redirect /bescanvia/confirmacio/v2#token]
C --> D[Fragment no viatja al servidor/Referer]
D --> E[Pàgina no-store + no-referrer]
E --> F[JS llegeix fragment]
F --> G[POST token a endpoint]
G --> H{AEAD + ID_INSC + regal.USAT vàlids?}
H -- no --> X[Error neutre]
H -- sí --> I[Mostrar confirmació sense codi i correu emmascarat]
```

El format CBC anterior no es reutilitza: l'IV no estava autenticat i, per tant, els tokens antics fallen tancat amb el parser v2.

## 10. Estat per apartat

| Apartat | Documentat | Implementat | Verificat |
| --- | --- | --- | --- |
| Routing + bundle/pàgina | Sí | `.htaccess` apunta a la pàgina existent + bundle rastrejable | Boundary; CI pendent |
| Transport codi/PII | Sí | POST també a mailing i històric de curs | Boundary ampliat; CI pendent |
| Elegibilitat per hores | Sí | Genèric `CCURS=N` i swap de regal concret per curs de mateixes hores; guard writer + resolver + stager | Tests match/mismatch + concrete swap afegits; CI pendent |
| No enumeració | Sí | Sí, patch 03/10 | Boundary afegit; CI pendent |
| Alta/reutilització | Sí | Sí | Core CI 02/10 |
| Context autoritatiu | Sí | Sí | Core CI 02/10 |
| Bescanvi/consum | Sí | Sí | Core CI 02/10 |
| Aplicació econòmica | Sí | Sí | Core CI 02/10 |
| Reconciliació legacy | Sí | Sí | Core CI 02/10 |
| Replay/outbox recovery | Sí | Sí | Core CI 02/10 |
| Concurrència | Sí | Sí | Core CI 02/10 |
| Correus idempotents | Sí | Sí; `FAILED/SENDING` no bloqueja confirmació | Core CI 02/10 + boundary 04/10 pendent |
| Confirmació | Sí | AEAD v2 + fragment + POST + no-store/no-referrer | Boundary nou; CI pendent |
| Preproducció real | Sí | Scripts sí | [ENV] |
| Preu catàleg curs vs FACE_VALUE | Sí, gap | No es compara autoritativament; només s'aplica FACE_VALUE | [POLICY/DATA] |
