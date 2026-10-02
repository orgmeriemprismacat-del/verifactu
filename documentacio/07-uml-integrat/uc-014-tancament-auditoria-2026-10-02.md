# UC-014 — Tancament d'auditoria i implementació

**Data:** 02/10/2026  
**Cas:** UC-014 · Comprar curs normal per Redsys  
**Estat:** **AUDITORIA TANCADA · IMPLEMENTACIÓ TANCADA · ACCEPTACIÓ OPERATIVA PENDENT**

## 1. Abast tancat

Queden tancats dins el repositori: fitxa funcional reconciliada; inventari PHP/JS ACTUAL, pont candidat i SIF; classes, seqüències i activitats ACTUAL/FINAL; intenció Redsys server-authoritative; validació criptogràfica i de merchant/order/import/divisa/terminal/resposta; cua/worker; factura i cobrament SIF; `EXTERNAL_ALLOCATION` per inscripció; sincronització llegada; retorn navegador read-only; productor d'outbox; hardening del fallback; cutover en dues fases; proves unitàries, integration/boundary i E2E simulat.

## 2. Evidència

El paquet de l'auditoria anterior va quedar verd als heads documentats al PR #105. L'atribució per inscripció va quedar integrada al PR #95 amb **841 proves SIF passades i 0 fallades** i els workflows documentats. Aquest tancament reconcilia el paquet amb el `main` vigent del 02/10/2026 i preserva els canvis posteriors d'altres UC en fitxers compartits.

## 3. Acceptació operativa separada

No són gaps de programació del UC-014: executar una compra real Redsys de preproducció; conservar evidència del `DS_ORDER` i UUIDs; rotar/configurar secrets a l'entorn objectiu; executar drenatge/cutover productiu; acreditar transport UC-058 si el rollout exigeix correu efectiu. Si una prova real falla per una regressió reproduïble del codi, el cas es reobre amb aquella incidència concreta.

## 4. Resultat

No queda cap fitxa, diagrama, inventari, traçabilitat, boundary o implementació coneguda pròpia del UC-014 pendent de crear al repositori. El cas surt del backlog de desenvolupament i resta únicament al checklist de preproducció/go-live.
