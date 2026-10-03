# UC-005 · Activitats per pàgina/apartat ACTUAL / FINAL

## P01 — Cerca i consulta de factura

### ACTUAL
```mermaid
flowchart TD
A[Obrir alumnes/factura] --> B[Introduir criteri]
B --> C{SIF query habilitada?}
C -- sí --> D[POST sifFactures search]
D --> E{resultats?}
E -- sí --> F[Mostrar factura SIF read-only]
E -- no --> G[Fallback llegat]
C -- no --> G
```

### FINAL
```mermaid
flowchart TD
A[Consultar factura] --> B[Carregar snapshot SIF]
B --> C[Mostrar estat fiscal/econòmic/documental]
C --> D{pot corregir?}
D -- no --> E[Només lectura]
D -- sí --> F[Acció Rectificar]
```

## P02 — Editar dades de factura

### ACTUAL
```mermaid
flowchart TD
A[Llapis edició llegada] --> B[Modificar camps]
B --> C[POST guardarDadesFactura_Factures]
C --> D[Auth + guard]
D --> E[UPDATE llegat si permès]
```

### FINAL
```mermaid
flowchart TD
A[Sol·licitar canvi] --> B[Capturar motiu]
B --> C[Comparar snapshot original/nou]
C --> D[UC-74 classifica]
D --> E{efecte fiscal?}
E -- no --> F[Canvi mestre auditat]
E -- sí --> G[Preview UC-005/30/31]
```

## P03 — Rectificació per diferències

```mermaid
flowchart TD
A[Seleccionar DIFERENCIES] --> B[Recalcular base/IVA/total al servidor]
B --> C[Mostrar diferència]
C --> D[Confirmar]
D --> E[Emetre R]
E --> F[Vincular original]
```

## P04 — Rectificació per substitució

```mermaid
flowchart TD
A[Seleccionar SUBSTITUCIO] --> B[Construir dades rectificades completes]
B --> C[Validar receptor + línies + fiscalitat]
C --> D[Confirmar]
D --> E[Emetre R de substitució]
E --> F[Vincular original]
```

**Buit actual:** el builder vigent hereta el receptor de l'original i no acredita la substitució real de nom/CIF/adreça.

## P05 — “Anul·lar” factura

### ACTUAL
```mermaid
flowchart TD
A[Botó anul·lar llegat] --> B[Modal import/data/obs]
B --> C[POST anularFactura_Factures]
C --> D[Auth + guard]
D --> E[Intranet::anularFactura]
```

### FINAL
```mermaid
flowchart TD
A[Acció correcció] --> B[Classificador UC-74]
B --> C{què és?}
C -- Rectificativa --> D[UC-005]
C -- Registre improcedent --> E[UC-30]
C -- Subsanació --> F[UC-31]
C -- Baixa --> G[UC-27/72]
C -- Devolució --> H[UC-28]
```

## P06 — Resultat i consulta posterior

```mermaid
flowchart TD
A[Operació confirmada] --> B[Resultat tipificat]
B --> C[Mostrar factura R + original]
C --> D[Mostrar relació i motiu]
D --> E[Mostrar estat AEAT/document]
E --> F[Permetre traça/auditoria]
```
