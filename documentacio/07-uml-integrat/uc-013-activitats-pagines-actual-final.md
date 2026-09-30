# UC-013 · Diagrames d'activitat ACTUAL / FINAL per pàgina i apartat

**Data d'auditoria:** 29/09/2026  
**Abast:** RM-037 — pàgina per pàgina i apartat per apartat.  
**Regla:** ACTUAL = comportament contrastat al codi. FINAL = comportament objectiu del SIF. No confondre cap diagrama FINAL amb implementació ja desplegada.

## 1. Pàgina pública · Descomptes USOC

### ACTUAL

```mermaid
flowchart TD
    A[Usuari obre pàgina USOC] --> B[PaginaDescomptesUsoc.php]
    B --> C[Mostra condicions i descompte públic]
    C --> D[Mostra taula de cursos/preus]
    D --> E[Usuari va a inscripció]
```

**Codi:** `pagina_descomptes_usoc.php`, `PaginaDescomptesUsoc.php`, `mostrarDescomptesUsoc.min.js`.

**Observació:** el text públic actual indica 20 %. Existeix documentació històrica amb 25 %; no prendre cap percentatge com a constant SIF.

### FINAL

```mermaid
flowchart TD
    A[Usuari consulta condicions USOC] --> B[Mostra regla comercial vigent]
    B --> C[Mostra que la sol·licitud requereix validació]
    C --> D[No promet cobrament/factura abans de validació]
    D --> E[Accés a formulari d'inscripció]
```

## 2. Pàgina d'inscripció USOC · Apartat dades personals

### ACTUAL

```mermaid
flowchart TD
    A[Carregar formulari] --> B[Introduir dades personals]
    B --> C[JS valida camps]
    C --> D{Errors?}
    D -- Sí --> B
    D -- No --> E[Continuar]
```

### FINAL

```mermaid
flowchart TD
    A[Carregar formulari] --> B[Introduir dades mínimes]
    B --> C[Validació client UX]
    C --> D[Validació servidor autoritativa]
    D --> E{Dades vàlides?}
    E -- No --> B
    E -- Sí --> F[Continuar sense crear fet fiscal]
```

## 3. Pàgina d'inscripció USOC · Apartat dades curriculars

### ACTUAL

```mermaid
flowchart TD
    A[Dades curriculars] --> B[Validacions JS]
    B --> C[Continuar]
```

### FINAL

```mermaid
flowchart TD
    A[Dades curriculars] --> B[Validar servidor]
    B --> C[Relacionar amb ID_INSC provisional/operació]
    C --> D[Continuar sense concedir USOC]
```

## 4. Pàgina d'inscripció USOC · Apartat curs i afiliació

### ACTUAL

```mermaid
flowchart TD
    A[Mostrar curs/preus] --> B[Checkbox afiliació USOC]
    B --> C{Marcat?}
    C -- No --> D[validarUSOC retorna error]
    C -- Sí --> E[checkUSOC=1]
    E --> F[Continuar]
```

**Precisió:** marcar el checkbox no valida l'afiliació real.

### FINAL

```mermaid
flowchart TD
    A[Seleccionar curs] --> B[Sol·licitar tractament USOC]
    B --> C[Registrar només estat PENDING]
    C --> D[Congelar curs/edició i identificadors]
    D --> E[Esperar decisió de gestió/USOC]
    E --> F{Validat?}
    F -- No --> G[Oferta ordinària o alternativa]
    F -- Sí --> H[Preparar snapshot comercial USOC]
```

## 5. Endpoint d'enviament de la inscripció

### ACTUAL

```mermaid
flowchart TD
    A[JS enviarInscripcioAfiliat] --> B[Reservar IDPAG amb allocator compartit + named lock]
    B --> C[INSERT inscripció]
    C --> D[TIPUS_DESC=4]
    D --> E[VALID_DESC pendent]
    E --> F[Preparar correu alumne]
    F --> G[Preparar/enviar correu FEUSOC]
    G --> H[Final]
```

### FINAL

```mermaid
flowchart TD
    A[POST sol·licitud USOC] --> B[Autenticar/validar request]
    B --> C[Assignar identificador concurrent-safe]
    C --> D[Persistir sol·licitud PENDING]
    D --> E[Registrar actor/correlació]
    E --> F[Outbox comunicació FEUSOC]
    F --> G[Commit]
    G --> H[Resposta PENDING]
```

## 6. Intranet · Validar descomptes · Llistat

### ACTUAL

```mermaid
flowchart TD
    A[Obrir validar descomptes] --> B[SELECT VALID_DESC=0]
    B --> C[Mostrar inscripcions pendents]
    C --> D[Gestió selecciona Sí/No]
```

### FINAL

```mermaid
flowchart TD
    A[Obrir panell] --> B[Carregar casos PENDING autoritzats]
    B --> C[Mostrar evidència mínima i import]
    C --> D[Gestió decideix]
    D --> E[POST decisió amb request id]
```

## 7. Intranet · Validar descomptes · Decisió positiva

### ACTUAL

```mermaid
flowchart TD
    A[Gestió marca Sí] --> B[POST sendMsgValidatCurosDescomptes.php + CSRF]
    B --> C[Intranet::sendMsgValidatCurosDescomptes]
    C --> D[VALID_DESC=1]
    D --> E[Preparar instruccions de pagament]
    E --> F[Enviar comunicació]
```

### FINAL

```mermaid
flowchart TD
    A[Gestió confirma afiliació] --> B[POST autenticat]
    B --> C[Validar rol, CSRF, versió i idempotència]
    C --> D[Congelar imports alumne/entitat]
    D --> E[Registrar VALIDATED + actor + motiu]
    E --> F[Crear/invalidar intenció de pagament coherent]
    F --> G[Outbox comunicació]
    G --> H[Commit]
```

## 8. Intranet · Validar descomptes · Decisió negativa

### ACTUAL

```mermaid
flowchart TD
    A[Gestió marca No] --> B[POST endpoint validació + CSRF]
    B --> C[VALID_DESC=2]
    C --> D[Buscar preu alternatiu]
    D --> E[Pot actualitzar TIPUS_DESC i A_PAGAR]
    E --> F[Enviar comunicació]
```

### FINAL

```mermaid
flowchart TD
    A[Gestió denega] --> B[POST autenticat]
    B --> C[Registrar decisió INVALID]
    C --> D{Hi ha factura emesa?}
    D -- No --> E[Recalcular oferta i invalidar intenció incompatible]
    D -- Sí --> F[No UPDATE fiscal; derivar incidència/rectificació]
    E --> G[Outbox comunicació]
    F --> G
```

## 9. Pagament alumne · Redsys

### ACTUAL / SIF EXISTENT

```mermaid
flowchart TD
    A[Notificació Redsys validada] --> B[RedsysUsocInvoiceService]
    B --> C[Carregar snapshot USOC]
    C --> D{TIPUS_DESC=4 i VALID_DESC=1?}
    D -- No --> E[CONFLICT]
    D -- Sí --> F[Construir factura alumne]
    F --> G[InvoiceService]
    G --> H[Factura + payment alumne]
    H --> I[Persistir usoc_financing_case]
    I --> J[Retornar entity_invoice_pending + id_insc]
```

### FINAL

```mermaid
flowchart TD
    A[Callback validat] --> B[Verificar snapshot congelat]
    B --> C[Emetre/reutilitzar factura alumne]
    C --> D[Registrar CHARGE alumne]
    D --> E[Persistir checkpoint USOC durable]
    E --> F[Estat PENDENT_ENTITAT]
```

## 10. Factura entitat USOC

### ACTUAL IMPLEMENTAT EN SERVEI, ADAPTADOR NO ACREDITAT

```mermaid
flowchart TD
    A[Input explícit entitat] --> B[UsocEntityInvoiceService]
    B --> C[Exigir billing + amount + student_invoice_uuid]
    C --> D[Carregar snapshot per IDPAG + ID_INSC]
    D --> E[Exigir checkpoint persistent i imports congelats]
    E --> F[Validar UUID factura alumne + ID_INSC + IDPAG + import]
    F --> G[Construir payload entitat]
    G --> H[InvoiceService]
    H --> I[Factura entitat PENDING]
    I --> J[Persistir checkpoint ENTITY_INVOICED]
    J --> K[payment_registered=false]
```

### FINAL

```mermaid
flowchart TD
    A[Gestió obre expedient USOC] --> B[Carregar factura alumne i checkpoint]
    B --> C[Validar que UUID alumne pertany a ID_INSC/IDPAG]
    C --> D[Confirmar receptor/import entitat]
    D --> E[Emetre o reutilitzar factura entitat]
    E --> F[Persistir UUID entitat al checkpoint]
    F --> G[Estat ENTITAT_FACTURADA]
```

## 11. Cobrament entitat

### ACTUAL / IMPLEMENTAT EN REPOSITORI
`alumnes-usoc-financament.php` ofereix una UI autenticada per consultar l'expedient, emetre factura entitat i registrar cobraments. Addicionalment, `alumnes-mostrar-alumne-usoc.js` injecta un panell contextual sota `#dades-pagament` del modal de Consulta/Modifica alumne quan existeix un expedient USOC; respecta `capabilities.manage` i reutilitza el mateix `SifInternalUsocClient`/API signada. El navegador parla només amb `ajax/alumnes/usocFinancament.php`; aquest controlador valida CSRF i delega al `SifInternalUsocClient`, que signa la petició HMAC cap a `/api/usoc/manage.php`. `UsocEntityPaymentService` registra el cobrament al ledger general i reconcilia l'expedient. L'accés de menú està implementat directament a `mostrarSideBarMenu.php`, fail-closed per `SIF_USOC_MENU_ROLES`, sense assumir ni modificar la taula `apartats`.

### FINAL

```mermaid
flowchart TD
    A[Ingrés real USOC] --> B[Registrar PaymentService]
    B --> C[Assignar a UUID_FACTURA_ENTITAT]
    C --> D{Import complet?}
    D -- No --> E[PARTIAL]
    D -- Sí --> F[PAID]
    E --> G[Actualitzar expedient USOC]
    F --> G
```

## 12. Conciliació final USOC

### ACTUAL / IMPLEMENTAT EN REPOSITORI
Existeixen `usoc_financing_case`, `UsocFinancingCaseRepository` i `UsocCaseReconciler`. La conciliació manual/controlada es pot executar amb `sif/scripts/reconcile-usoc-case.php`. Encara no està connectada automàticament després de cada cobrament de l'entitat.

### FINAL

```mermaid
flowchart TD
    A[Reconciliar ID_INSC] --> B[Carregar factura alumne]
    B --> C[Carregar payments alumne]
    C --> D[Carregar factura entitat]
    D --> E[Carregar payments entitat]
    E --> F[Aplicar refunds/compensacions]
    F --> G{Imports i orígens quadren?}
    G -- No --> H[PENDING/CONFLICT]
    G -- Sí --> I[FINANÇAMENT_CONCILIAT]
```

## 13. Canvi de curs / baixa / rectificació

### FINAL obligatori

```mermaid
flowchart TD
    A[Canvi o baixa] --> B[Recuperar ambdues factures]
    B --> C[Separar pagador alumne i USOC]
    C --> D[Classificar efecte de cada factura]
    D --> E[Rectificatives/trasllats segons cas]
    E --> F[No retornar diners no cobrats]
    F --> G[No compensar un pagador amb l'altre]
    G --> H[Actualitzar conciliació]
```

## 14. Cobertura RM-037

| Superfície | ACTUAL | FINAL | Codi contrastat |
| --- | --- | --- | --- |
| Pàgina informativa USOC | Sí | Sí | Sí |
| Inscripció · personals | Sí | Sí | Sí |
| Inscripció · curriculars | Sí | Sí | Sí |
| Inscripció · curs/USOC | Sí | Sí | Sí |
| Endpoint alta | Sí | Sí | Sí |
| Validar descomptes · llistat | Sí | Sí | Sí |
| Validar positiu | Sí | Sí | Sí |
| Validar negatiu | Sí | Sí | Sí |
| Pagament/factura alumne | Sí | Sí | Sí |
| Factura entitat | Sí | Sí | Servei + pantalla autònoma + panell contextual implementats; desplegament/configuració pendent |
| Cobrament entitat | Sí, dues UI + servei + script | Sí | UI autònoma + panell contextual implementats; menú fail-closed implementat; desplegament/configuració pendent |
| Conciliació | Sí, servei/script | Sí | Implementada parcialment; trigger automàtic pendent |
| Canvi/baixa | Parcial | Sí | Compartit amb altres UC |

## 15. Pendents de codi derivats dels diagrames

1. Afegir traça persistent SIF/correlació de la decisió de validació legacy; POST + CSRF + permisos ja implementats.
2. Mantenir el test de regressió de l'allocator IDPAG compartit; implementació actual protegida amb named lock.
3. Configurar `SIF_USOC_MENU_ROLES` i validar l'accés de menú al desplegament de preproducció.
4. Validar en preproducció la configuració HMAC, rols i DB legacy amb `preflight-usoc-intranet.php`.
5. Evidència CI conservada a `documentacio/09-proves-qa/uc-013-evidencia-ci-2026-09-30.md`; run `36660979100` **SUCCESS, 646 passed / 0 failed**, incloent E2E de servei fins a `FINANCING_RECONCILED`. Resta validació navegador/desplegament/preproducció.
6. Tractament definit per alumne=0/curs gratuït.
