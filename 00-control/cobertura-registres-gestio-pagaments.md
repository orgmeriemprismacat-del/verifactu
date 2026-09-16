> **Pla vigent — 16/09/2026:** vegeu [Pla reconciliat R2](pla-reconciliat-r2.md): 38 paquets, 944 h de mínim proposat i 1.332 h probables per a l'abast ampli. Les estimacions i correccions pendents d'aquest document es conserven com a antecedent.

# Correcció de cobertura — registres de gestió i pagaments

Data: 15/09/2026. Aquesta revisió respon a la indicació de l'usuari que el pla ha de contemplar el registre de qualsevol gestió que afecti el pagament i tota la persistència acordada.

## 1. Resultat de la comprovació

La planificació anterior incloïa BD, moviments econòmics i auditoria genèrica, però **no acreditava ni pressupostava explícitament el ledger universal `payment_action_event` i el `PaymentActionGateway`**. No es pot afirmar que els 36 paquets i les 720 h cobreixin tot el contracte documental vigent.

Fonts comprovades:

- `documentacio/04-estat-final/38-matriu-transformacio-funcional-verifactu.md`, apartats 4, 5, 6 i 14.
- `documentacio/04-estat-final/33-casos-us-sif.md`, UC-69…UC-86, especialment UC-86.
- `documentacio/04-estat-final/34-diagrames-dades-estats-sif.md`, apartats 13.1 i 13.2.
- `documentacio/04-estat-final/35-matriu-tracabilitat-diagrames.md`, apartats 12 y 13.
- `documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md`, apartats 8 i 9.
- `00-control/registre-decisions.md`, decisions sobre transformació funcional i traça immutable del 15/09.

La cerca acotada de `payment_action_event` i `PaymentActionGateway` no retorna implementació a les fonts PHP/SQL revisades del checkpoint i del worktree Redsys. La documentació també els identifica com a disseny pendent/bloquejant. Això no és una inspecció de la BD productiva ni una prova executada.

## 2. Les tres responsabilitats que no es poden confondre

| Responsabilitat | Què conserva | Què no substitueix |
| --- | --- | --- |
| Registre de gestió/auditoria | Petició, decisió, actor/procés, motiu, abans/després, origen, resultat i correlació. | No substitueix el moviment econòmic ni emet automàticament una factura. |
| Moviment econòmic | Cobrament, devolució, saldo/compensació i assignacions a factures. | No explica per si sol totes les consultes, intents, rebuigs ni gestions anteriors. |
| Registre fiscal | Alta, rectificació mitjançant factura corresponent, anul·lació/subsanació de registre, cadena i remissió. | No es crea necessàriament per cada consulta, canvi operatiu o cobrament posterior. |

La seqüència del projecte és gestió registrada → classificació d'impacte → moviment econòmic si correspon → acció fiscal si correspon → evidències. Cada gestió conserva la seva traça, encara que no produeixi factura nova ni moviment nou.

## 3. Nou bloc VT-37 — traça universal de pagaments

**Prioritat: obligatòria abans de validar qualsevol canal productiu, també en un llançament amb funcionalitat limitada.** No és una millora del dashboard ni es pot ajornar per fer servir un procediment assistit: aquest també ha de generar traça.

### VT-37.1 — Esquema i permisos

- [ ] Crear migració de `payment_action_event`, claus, relacions, índexs i permisos d'inserció/consulta sense edició ni esborrat funcional.
- [ ] Conservar `UUID_EVENT`, pagament quan existeixi, clau idempotent, `REQUEST_ID`, `CORRELATION_ID`, `CAUSATION_ID`, acció/resultat tipificats, actor/rol, canal i entorn, motiu, hashes abans/després, diferències mínimes, error i dates.
- [ ] No copiar secrets, tokens reutilitzables ni dades completes de targeta.

### VT-37.2 — Gateway i garanties de persistència

- [ ] Implementar `PaymentActionGateway`, `PaymentActionAuditService` i `PaymentActionEventRepository` amb identitat resolta al servidor.
- [ ] Registrar l'intent abans de l'acció; bloquejar-la si no es pot conservar.
- [ ] Confirmar la mutació i el resultat terminal en la mateixa transacció.
- [ ] Conservar el rebuig/error quan el domini es reverteix; definir explícitament el camí de persistència fora de la transacció fallida.
- [ ] No retornar consulta/exportació sense traça persistent.
- [ ] Definir el tractament d'estats asíncrons `QUEUED`/`PARTIAL` i la correlació fins al resultat esperat.

### VT-37.3 — Integració de totes les entrades actives

- [ ] Web, intranet, portal d'alumne, panell i API.
- [ ] Callback/worker Redsys, CLI i tasques programades.
- [ ] Conciliació, importació/migració, sincronització llegada i eines administratives autoritzades.
- [ ] Creació, reutilització, cerca/consulta/exportació, assignació/reassignació/repartiment, conciliació, devolució/compensació/reclamació, cancel·lació, retry i bloqueig d'edició directa.
- [ ] Integrar `PaymentQueryService`, `PaymentReconciliationService` i `PaymentCorrectionService` on pertoqui, reutilitzant motor i regles existents.

### VT-37.4 — Cronologia i monitoratge

- [ ] Cronologia per pagament i correlació amb permisos i exportació també auditada.
- [ ] `AuditMonitor`: detectar peticions sense resultat esperat i obrir incidència.
- [ ] Garantir correccions amb nous events i contramoviments/versions, no escriptures sobre el passat.

### VT-37.5 — Proves bloquejants

- [ ] Èxit, reutilització, consulta, denegació, validació fallida, error tècnic, callback duplicat, retry, assignació i correcció, exportació i intent d'edició bloquejat.
- [ ] Caiguda del ledger abans d'actuar: cap mutació ni retorn de dades.
- [ ] Fallada en la transacció: cap canvi de negoci confirmat sense resultat terminal; intent conservat i error o incidència correlacionats.
- [ ] Procés interromput després de l'intent: detecció i recuperació de correlació incompleta.
- [ ] Reconstrucció cronològica de cada operació des de tots els canals activats.

La unitat és la petició/comanda/decisió funcional, no cada SELECT intern. Aquesta distinció evita soroll i doble instrumentació sense reduir el contracte.

## 4. Matriu de BD i registres acordats → treball del pla

Els noms de diversos registres són conceptuals i el document 38 permet ajustar-los; la responsabilitat de persistència no es pot suprimir. La columna de paquets indica on s'ha de pressupostar i verificar, no que ja estigui acabat o que les hores anteriors siguin suficients.

| Persistència acordada | Paquets responsables | Comprovació de finalització |
| --- | --- | --- |
| `payment_action_event` | **VT-37**, integrat amb VT-07/12…25/32/33 | Tota petició i resultat correlacionats, immutable i amb bloqueig si falla la traça. |
| `operational_event` | VT-02/05/23/24/25 | Decisió administrativa, motiu, abans/després i impacte classificat. |
| `billing_profile_history` | VT-05/15/17/19 | Canvi de dada mestra conservat sense reescriure snapshot de factura. |
| `course_change_event` | VT-23 | Inscripció origen/destí, imports, diferències, despeses i resultat fiscal relacionats. |
| `enrollment_cancellation_event` | VT-24 | Baixa registrada i decisió de retorn/saldo/no retorn separada. |
| Ampliacions `factura`, `factura_registres` | VT-05/07/08/10 | Camps necessaris, registre anterior, tipus, indicadors, XML i estat per registre. |
| `aeat_submission_attempt` i ampliació de `fiscal_queue` | VT-09 | Cada intent/resposta, locks, reintent i esgotament d'intents persistits. |
| `document_job` i ampliació de `factura_documents` | VT-11 | Job, estat, versió generador, storage, hash, resultat i recuperació. |
| `notification_outbox`, `notification_delivery_attempt` | VT-20 | Missatge posterior al commit i cada intent d'entrega auditables. |
| `sif_incident_action` i ampliació d'`errors_verifactu` | VT-21 | Responsable, severitat, transicions, accions i resolució conservades. |
| `sif_audit_event` | VT-12/32 | Auditoria comuna d'accions sensibles; definir connexió amb VT-37 sense duplicar implementació. |
| `fiscal_document_access` | VT-11/19/32 | Consulta, descàrrega i denegació traçades segons actor i document. |
| `sif_version`, `sif_declaration` | VT-34/35 | Versió/artefacte i declaració vinculats a l'activació real. |
| `fiscal_export` | VT-21/32/35 | Criteris, sol·licitant, motiu, fitxer/hash i accessos persistits. |
| `reconciliation_run/item` | VT-18/22 | Execució, discrepàncies, decisió, actor i resolució, també si el procés és assistit. |
| `backup_restore_evidence` | VT-34 | Abast, còpia, restauració, resultat i incidències amb evidència. |
| Ampliació de `fact_rels` | VT-05/06/18/19 | Relació amb origen/event i política de visibilitat justificable. |
| `payment_transaction`, `payment_allocation` | VT-07/16/24/37 | Moviments/assignacions coherents i correccions controlades, sense edició lliure. |

## 5. Conseqüència per al pressupost i calendari

1. No mantenir 720 h com a estimació completa demostrada: hi faltava aquesta traçabilitat explícita.
2. VT-37 entra abans de donar per acabats API, pagaments, consultes, callbacks i processos; cada integració inclou la seva prova de traça.
3. Reestimar VT-37 i les ampliacions de registres de la matriu; contrastar els imports ja pressupostats de VT-05/12/21/32/33 per evitar sumar dues vegades autenticació, auditoria, consulta o proves.
4. La capacitat de **855–877,5 h netes amb 75 h brutes/setmana** continua sent el càlcul vigent. El marge de 135–157,5 h del pla anterior no es pot considerar lliure fins a incorporar aquest treball.
5. No s'assigna un nou total fingint una reconciliació d'hores que encara no s'ha fet. Les fites de desembre queden condicionades a aquesta actualització.

## 6. Regla de verificació de cobertura

Per poder afirmar que un acord està inclòs en el pla cal una fila: requisit documental → registre/taula → servei/comanda → canal/pantalla → tasca/hores → prova/evidència.

Aquesta revisió afegeix la correspondència documental i de paquets dels registres indicats. No és una afirmació que tot el contingut acordat del projecte hagi estat verificat línia a línia ni que els registres estiguin implementats. Els 81 casos/variants de la fotografia inicial no descriuen per si sols el catàleg actual, ampliat fins a UC-86.
