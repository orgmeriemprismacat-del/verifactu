# UC-008 · Evidència E2E del flux gestor

**Objectiu:** acreditar en preproducció que un usuari amb rol gestor real pot completar el lifecycle del panell SIF sense convertir el verificador en un script mutador.

## 1. Principi

El verificador `sif/scripts/verify-incident-manager-evidence.php` és **CLI-only i read-only**. No crea, assigna, resol, reobre ni elimina incidències.

La mutació que es vol acreditar s'ha d'haver fet **des del panell SIF** amb un usuari gestor real sobre una incidència sintètica dedicada a aquesta prova.

## 2. Incidència de prova

La incidència utilitzada com a evidència ha de tenir:

- `TIPUS_INCIDENCIA = UC008_E2E_MANAGER`;
- `SOURCE_TYPE = UC008_E2E`;
- severitat baixa o equivalent de prova;
- cap factura, pagament o registre fiscal real com a objectiu de reparació;
- una correlació estable dedicada a la prova.

No s'ha d'utilitzar una incidència productiva o funcional real per satisfer aquest control.

## 3. Flux que ha de fer el gestor al panell

Amb un usuari inclòs als `SIF_INCIDENT_MANAGE_ROLES` reals de preproducció:

1. obrir el panell mitjançant el handoff de la intranet;
2. obrir la incidència sintètica;
3. fer **Assignar / triage**;
4. afegir una **evidència** identificable de prova;
5. executar **RESOLVED** amb criteri de tancament, notes i evidència;
6. comprovar visualment que el timeline mostra les tres accions i que l'estat final és `RESOLVED`.

## 4. Verificació read-only posterior

Des del servidor SIF de preproducció:

```bash
export SIF_ENV=preproduction

php sif/scripts/verify-incident-manager-evidence.php <INCIDENT_ID> \
  | tee uc-008-manager-e2e-evidence.json
```

El JSON només és vàlid si retorna `ok=true`.

El verificador comprova:

- entorn `test` o `preproduction` i mai producció;
- rols de gestió configurats;
- incidència existent;
- marcador sintètic `UC008_E2E_MANAGER / UC008_E2E`;
- estat final `RESOLVED`;
- responsable assignat;
- `RESOLVED_AT`, notes i criteri de tancament presents;
- accions `ASSIGN`, `ADD_EVIDENCE` i `RESOLVE`;
- actor present a cadascuna;
- `ACTOR_ROLE` pertany als rols gestors configurats;
- correlació de cada acció igual a la de la incidència;
- evidència JSON present a `ADD_EVIDENCE` i `RESOLVE`;
- consulta exclusivament read-only.

## 5. Gate final

El tancament UC-008 necessita ara **tres** evidències:

1. `uc-008-preproduction-evidence.json` — preflight + E2E read-only;
2. `uc-008-menu-evidence.json` — menú intranet en estat `ALREADY_PRESENT`;
3. `uc-008-manager-e2e-evidence.json` — lifecycle real del gestor verificat read-only.

Validació:

```bash
php sif/scripts/validate-uc008-evidence.php \
  uc-008-preproduction-evidence.json \
  uc-008-menu-evidence.json \
  uc-008-manager-e2e-evidence.json \
  | tee uc-008-closure-validation.json
```

El resultat final només pot retornar `ok=true` si les tres evidències són de preproducció/contracte correcte i superen tots els checks.

## 6. Traçabilitat

`uc-008-closure-validation.json` incorpora:

- `validated_at`;
- SHA-256 de l'evidència de preproducció;
- SHA-256 de l'evidència de menú;
- SHA-256 de l'evidència E2E del gestor.

Això permet demostrar exactament quins tres fitxers van ser validats sense incorporar secrets ni paths locals.

## 7. Estat actual

- verificador read-only del gestor: **implementat**;
- proves automàtiques del verificador: **implementades**;
- gate final de tres evidències: **implementat**;
- execució amb usuari gestor real de preproducció: **pendent d'entorn**;
- `uc-008-manager-e2e-evidence.json`: **pendent de generar a preproducció**.
