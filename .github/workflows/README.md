# Workflows de CI

Workflows actuals del repositori:

- `sif-checks.yml`: controls generals del SIF.
- `sif-tests.yml`: suite PHP/MySQL del SIF.
- `uc004-sif-checks.yml`: validacions dirigides d'UC-004.
- `uc111-integration.yml`: integració dirigida d'UC-111.
- `intranet-ao-checks.yml`: controls de la integració d'intranet corresponent.

## Criteri

Cada resultat de workflow valida el **commit exacte** sobre el qual s'ha executat. Un check verd antic no s'ha de reutilitzar com a prova de canvis posteriors.

Quan un UC crític necessita una porta pròpia, el workflow ha de complementar —no duplicar de manera divergent— la suite general i ha de deixar clar què valida i què queda fora de l'abast.
