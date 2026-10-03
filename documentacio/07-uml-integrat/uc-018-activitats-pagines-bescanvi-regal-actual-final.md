# UC-018 · Activitats ACTUAL/FINAL per pàgina i apartat

## 1. Superfícies executables auditades — 2026-10-03

| Superfície | ACTUAL | FINAL |
| --- | --- | --- |
| `pagina_bescanvia.php` | carrega `mostrarBescanvia.min.js?ver=6.0` | Igual |
| `mostrarBescanvia.min.js` | controlador client; 4 peticions sensibles POST | Igual |
| `mostrar_pagina_bescanvia.php` | render formulari codi | Igual |
| `codiRegalValid.php` | POST-only; resposta neutra | Igual |
| `buscarCursRegalat.php` | POST-only + revalidació server-side | Igual |
| `bescanviaUnCurs.php` | render curs/edició | Igual |
| `inscripcioDuplicada.php` | POST en UC-018 perquè DNI no vagi a URL | Igual |
| `enviarInscripcioBescanvia.php` | POST-only; lock, FACT_REL>0, get-or-create, SIF, correus | Igual |
| `SifGiftRedemptionClient` | POST/HMAC redeem + notificacions | Igual |
| `redeem.php` | endpoint intern autoritatiu | Igual |
| `notifications.php` | claim/complete at-most-once | Igual |
| `pagina_confirmacio_bescanvia.php` + JS + AJAX | confirmació amb ID opaca | Igual |
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
C -- sí --> D[Escollir curs elegible]
C -- no --> E[Usar curs regalat]
D --> F[Escollir edició]
E --> F
F --> G[Omplir dades personals]
```

La política de canvi de curs posterior pertany a UC-26/71; UC-018 només ha de consumir una vegada el dret.

## 4. Activitat P-BES-03 · Comprovar duplicat

```mermaid
flowchart TD
A[Dades validades al navegador] --> B[POST DNI + curs + any + edició]
B --> C{Inscripció existent?}
C -- sí --> D[No crear altra matrícula]
C -- no --> E[Continuar]
```

## 5. Activitat P-BES-04 · Writer legacy

```mermaid
flowchart TD
A[POST dades + codi] --> B[Begin transaction]
B --> C[SELECT regal FOR UPDATE]
C --> D{Existeix i FACT_REL > 0?}
D -- no --> X[Error / rollback]
D -- sí --> E{USAT o candidata existent?}
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
B --> C[Claim holder + RESERVE]
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
```

`SENDING` ambigu no es reclama automàticament.

## 9. Activitat P-BES-08 · Confirmació

```mermaid
flowchart TD
A[Cap incidència de correu bloquejant resposta] --> B[Retornar ID_INSC xifrada]
B --> C[Redirect /bescanvia/confirmacio/id-opac]
C --> D[Carregar confirmació]
D --> E[Mostrar matrícula resultant]
```

## 10. Estat per apartat

| Apartat | Documentat | Implementat | Verificat |
| --- | --- | --- | --- |
| Bundle/pàgina | Sí | Sí, patch 03/10 | CI pendent |
| Transport codi/PII | Sí | POST, patch 03/10 | Boundary afegit; CI pendent |
| No enumeració | Sí | Sí, patch 03/10 | Boundary afegit; CI pendent |
| Alta/reutilització | Sí | Sí | Core CI 02/10 |
| Context autoritatiu | Sí | Sí | Core CI 02/10 |
| Bescanvi/consum | Sí | Sí | Core CI 02/10 |
| Aplicació econòmica | Sí | Sí | Core CI 02/10 |
| Reconciliació legacy | Sí | Sí | Core CI 02/10 |
| Replay/outbox recovery | Sí | Sí | Core CI 02/10 |
| Concurrència | Sí | Sí | Core CI 02/10 |
| Correus idempotents | Sí | Sí | Core CI 02/10 |
| Preproducció real | Sí | Scripts sí | [ENV] |
| Diferències de preu | Sí | Fail-closed | [POLICY] |
