# Cua executable de preparació — R2
Data: 16/09/2026. Complement del [pla vigent](pla-reconciliat-r2.md). Aquesta és la descomposició de les **148 h de fase A**, no feina addicional. Hores inicials de gestió, revisables amb evidència. Estat de totes les tasques: pendent de verificació/execució; no es descompta feina només perquè existeix un fitxer.

## 1. Tasques i dependències
Responsable d'execució previst: titular del projecte. Negoci/facturació valida decisions de negoci; no s'ha assignat ni contactat cap altra persona.

| ID | Paquet | Hores netes | Depèn de | Acció | Evidència de finalització |
|---|---|---:|---|---|---|
| A-01.1 | VT-01 | 4 | Cap | Inventariar branques, diferències i components reutilitzables | Commit/base i divergències identificats |
| A-01.2 | VT-01 | 4 | 01.1 | Preparar execució reproduïble dels tests existents | Comanda, entorn i requisits documentats |
| A-01.3 | VT-01 | 4 | 01.2 | Executar baseline en test i separar fallades de codi i entorn | Resultats amb evidència; no assumir que tot el codi existent funciona |
| A-02.1 | VT-02 | 8 | 01.1 | Revisar bloquejos de fitxes i agrupar decisions repetides | Registre de decisions concretes, sense reobrir regles confirmades |
| A-02.2 | VT-02 | 8 | 02.1 | Relacionar venda activa, obligacions prèvies i variants amb criteris de tall | Cap venda pendent sense circuit; proposta d'abast visible |
| A-02.3 | VT-02 | 8 | 02.2 | Fixar exemples d'entrada/resultat i acceptació per compra i gestió | Escenaris nominals, duplicats, errors i traça verificables |
| A-03.1 | VT-03 | 8 | 01.1 | Inventariar PHP, extensions, MySQL, dominis i requisits d'accés | Diferències de l'entorn i impediments identificats |
| A-03.2 | VT-03 | 8 | 03.1 | Preparar preproducció, configuració segregada i serveis | Aplicació i BD de prova accessibles; cap secret a evidències |
| A-03.3 | VT-03 | 8 | 03.2 | Validar workers, correu, TLS i connectivitat necessària | Proves de salut i llista de dependències externes |
| A-04.1 | VT-04 | 8 | 01.1 | Localitzar rutes i escriptures del llegat afectades | Mapa de punts d'entrada i dependències |
| A-04.2 | VT-04 | 8 | 04.1,03.2 | Corregir incompatibilitats de les rutes seleccionades | Rutes executables amb sessions i AJAX coherents |
| A-04.3 | VT-04 | 4 | 04.2 | Comprovar les rutes afectades i registrar regressions | Evidència local; regressió transversal reservada a VT-31 |
| A-05.1 | VT-05 | 8 | 01.1,03.2 | Contrastar esquema real, migracions existents i matriu de registres | Diferències per taula, tipus i índex, sense donar SQL per aplicat |
| A-05.2 | VT-05 | 12 | 05.1,02.3 | Completar migracions comunes i relacions factura/fact_rels | SQL revisat i dades de prova; taules de domini als seus paquets |
| A-05.3 | VT-05 | 12 | 05.2 | Assajar migració i recuperació en còpia de prova | Recomptes, imports i relacions conciliats; recuperació comprovada |
| A-06.1 | VT-06 | 8 | 05.1,02.2 | Classificar històric, pendents i separació d'emissors | Regles de coexistència i excepcions amb responsable |
| A-06.2 | VT-06 | 8 | 06.1,05.2 | Preparar tractament dels pendents i referències antigues | Consultes i regles de transició sense reemissió indiscriminada |
| A-06.3 | VT-06 | 4 | 06.2 | Provar mostres i quadrar imports i relacions | Excepcions visibles i criteri de tall documentat |
| A-12.1 | VT-12 | 4 | 01.1 | Definir contracte d'identitat, rol, canal i errors | Identitat rebuda del servidor; contracte reutilitzable per VT-37 |
| A-12.2 | VT-12 | 8 | 12.1,03.2 | Completar autorització d'API i adaptadors | Validació d'abast i permisos als endpoints |
| A-12.3 | VT-12 | 4 | 12.2 | Provar permisos denegats i identitat manipulada | Cap accés per ocultació de botons o rol aportat pel client |

Les dependències abreujades corresponen a IDs amb prefix A-. Es pot començar la inspecció de tasques dependents, però no declarar-les acabades sense la seva entrada validada.

## 2. Primera setmana completa: 56,25 h netes
Per no inventar quins dies treballes, D1/D2/D3 són els teus tres dies laborals de VERI*FACTU. Cada jornada de 15 h brutes té pressupost d'11,25 h netes; les altres 3,75 h són reserva, no tasques noves.

| Jornada | Assignació neta |
|---|---|
| D1 | A-01.1 4 h + A-03.1 7,25 h |
| D2 | A-03.1 0,75 h + A-03.2 8 h + A-01.2 2,5 h |
| D3 | A-01.2 1,5 h + A-01.3 4 h + A-02.1 5,75 h |
| Dissabte | A-02.1 2,25 h + A-05.1 8 h + A-12.1 1 h |
| Diumenge | A-12.1 3 h + A-04.1 8 h + A-02.2 0,25 h |

És un repartiment inicial, no una obligació de completar una tasca en aquell temps. Si preproducció està bloquejada, avançar A-02.1, A-04.1 i A-12.1; no donar per executats els tests. Al tancament de setmana registrar estimació restant i bloqueig.

## 3. Segona i tercera setmana
- Segona: completar A-02.2/02.3, A-03.3, A-12.2/12.3 i avançar A-05.2. Utilitzar la capacitat restant en A-04.2.
- Tercera: acabar A-04.2/04.3, A-05.2/05.3 i A-06.1/06.2/06.3 respectant les dependències.
- Aquestes tres setmanes tenen fins a 168,75 h netes, però la fase només consumeix 148 h. Les 20,75 h restants poden iniciar fonaments de VT-37/38 quan dades i identitat estiguin validades; no afegir-les al pressupost de fase A.
- La primera setmana parcial del calendari mestre es pot dedicar a aquests mateixos IDs: qualsevol hora feta es resta de la cua, no es compta de nou.

## 4. Bloquejos que han d'entrar a l'ordre de treball
Vegeu [inventari de punts bloquejants](bloquejos-fitxes-r2.md), extret de les fitxes actuals. Hi ha 22 fitxes amb 66 files marcades com a bloquejants. No són 66 funcionalitats noves: moltes repeteixen proposta de classificació, estat de disseny i pregunta genèrica de validació.

A-02.1 ha de separar:
1. Regles ja confirmades: mantenir-les; no demanar que es decideixin de nou.
2. Decisió de negoci real: concretar alternatives i exemple que canvia el resultat.
3. Disseny/implementació pendent: convertir-lo en tasca del paquet existent.
4. Dependència externa: identificar accés, entorn o dada necessària i data límit per disposar-ne.

Cada decisió tindrà cas afectat, pregunta concreta, proposta justificada, responsable de validar, estat i conseqüència si no es resol. Una frase genèrica del tipus «validar variants i permisos» no és una decisió preparada per aprovar.

## 5. Estat tècnic actualitzat del registre
Ara existeixen:
- migració `sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql`;
- repositori `sif/src/Repository/PaymentActionEventRepository.php`;
- proves unitàries `sif/tests/Unit/PaymentActionEventRepositoryTest.php`.

La lectura mostra inserció d'events i validació de resultats; els tests inspeccionats comproven entrades rebutjades abans d'escriure. Això no acredita persistència real, atomicitat amb pagaments, captura dels errors després de rollback ni cobertura de tots els canals. No s'han localitzat classes PaymentActionGateway, OperationalChangeController o FiscalImpactClassifier a la cerca actual.

Per tant, corregim l'afirmació anterior d'absència total de base. VT-37 conserva provisionalment les 120 h per revisar, completar, integrar i provar; les 12 h de migració són una provisió a reestimar, no una instrucció de reescriure el que ja existeix. A-01.1 i A-05.1 han de produir el descompte justificat si aquesta base supera les comprovacions.

## 6. Registre de seguiment
Per cada ID: estat (pendent/en curs/bloquejat/validat), hores reals, hores restants, evidència i impediment. No convertir percentatge de fitxers creats en percentatge de projecte acabat.

**Pròxim lliurable:** baseline reproduïble, diferències de BD i bloquejos de negoci concrets. Fins a tenir-lo, les 944 h són previsió inicial i no compromís tancat.
