# Codi del SIF

Aquesta carpeta conté el codi PHP del nucli SIF. L'organització separa domini, contractes, persistència, serveis, HTTP, AEAT i infraestructura.

## Estructura

- `Domain/`: objectes i regles de domini.
- `Contract/`: interfícies i contractes entre capes.
- `Repository/`: accés a dades i persistència.
- `Service/`: casos d'ús i orquestració de negoci.
- `Http/`: peces de frontera HTTP i validació.
- `Aeat/`: construcció i transport relacionat amb AEAT.
- `Database/`: infraestructura de connexió/migracions compartida.
- `Cli/`: suport de processos de línia d'ordres.
- `FunctionalCard/`: suport del generador/validador documental.
- `Exception/`: excepcions de domini i infraestructura.
- `autoload.php`: càrrega local de classes.

## Criteri d'arquitectura

La UI, els endpoints i els scripts no haurien de duplicar regles de negoci. Quan una operació és fiscal o econòmica, la validació autoritativa ha de viure en serveis/repositoris reutilitzables.

## Què hauria d'existir per cada flux crític

- contracte d'entrada clar;
- validació d'identitat, rol i estat;
- idempotència;
- transacció i persistència coherents;
- auditoria;
- resultat/estat explícit;
- proves unitàries i d'integració;
- traçabilitat amb el UC i la documentació.

La mera existència d'una classe no acredita que el flux estigui complet, connectat a una interfície real o desplegat.
