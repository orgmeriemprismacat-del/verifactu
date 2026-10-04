# UC-014 — Reconciliació neta de CI sobre main

**Data:** 04/10/2026  
**Base:** `main@2bd2a751832fc3f767b1e250b955922145a5577a`  
**Cas:** UC-014 · Comprar curs normal per Redsys

## 1. Cadena de tancament

- PR #118 va fusionar el tancament funcional/documental del UC-014 a `main`.
- PR #119 va diagnosticar el CI posterior al merge i va demostrar que l'únic vermell UC-014 era un vector de test desactualitzat, a més d'un warning d'interpolació de `$fractional`.
- PR #149, test-only i amb els tres checks verds, va fusionar a `main` la correcció canònica del `payload_hash` Redsys i els cinc boundaries PACK que mantenien la suite global en vermell.
- Aquesta reconciliació recrea només el perímetre propi d'UC-014 sobre el `main` actual, sense reutilitzar la branca divergent del PR #119.

## 2. Evidència UC-014 del PR #119

El workflow específic `UC-014 SIF course checks` va acabar en **success** amb:

`UC-014 selective suite: 125 passed, 0 failed`.

La suite inclou intent/callback/worker Redsys de curs, factura/cobrament, `EXTERNAL_ALLOCATION`, status, preflight/preproducció, retorn, cutover, JASOM i primitives Redsys compartides requerides pel cas.

## 3. Correcció pròpia pendent de fusionar

`RedsysCourseLegacyFallbackBoundaryTest` contenia una interpolació accidental dins l'assert:

`"'fractional' => (int) $fractional"`

El test passava, però PHP generava `Undefined variable $fractional`. La correcció usa el literal `\$fractional` i elimina el warning sense canviar runtime ni política funcional.

## 4. CI específic estable

S'incorporen al repositori:

- `.github/workflows/uc014-sif-checks.yml`;
- `sif/tests/run-uc014-tests.php`.

Aquests fitxers separen la salut específica del UC-014 de fallades alienes en suites compartides. La suite global continua sent obligatòria per detectar regressions transversals, però no és l'únic criteri per atribuir un vermell a aquest cas.

## 5. Estat

**DOCUMENTAT:** complet per UC-014 ordinari.  
**IMPLEMENTAT:** tancat a repositori via PR #118.  
**VERIFICAT:** evidència històrica PR #79/#95/#119 i gate selectiu 125/0; la branca neta 04/10 ha de repetir el gate contra el `main` actual.  
**ACCEPTACIÓ OPERATIVA PENDENT:** Redsys real de preproducció, secrets/rotació, drain/cutover i transport UC-058.

El cas només es reobre per una regressió reproduïble del perímetre UC-014 o per una fallada del gate selectiu.
