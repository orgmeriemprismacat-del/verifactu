# Assistent generador de fitxes funcionals SIF

## Finalitat

Generar una previsualització auditable d'una fitxa funcional a partir d'un cas d'ús i d'un manifest congelat de fonts del repositori. L'assistent no decideix qüestions funcionals o fiscals, no actualitza documentació i no converteix el comportament històric en disseny objectiu.

## Entrades obligatòries

1. JSON validat amb `input.schema.json`.
2. Manifest generat per `prepare-functional-card.php`.
3. Plantilla `output-template.md`.

No s'ha de llegir el JSONL complet de `xat-original`. Les fonts addicionals només es poden usar quan apareixen al manifest.

## Seqüència de treball

1. Llegir el cas, el context i totes les entrades `IN-*`.
2. Revisar el manifest i descartar com a suport de `CONFIRMAT` qualsevol font que no tingui `validation=AUTORITZADA`.
3. Extreure afirmacions atòmiques. Per a cada afirmació, conservar context (`OBJECTIU`, `ACTUAL`, `HISTORIC` o `IMPLEMENTACIO`), condició i modalitat (`obligatori`, `recomanat` o `possible`).
4. Comparar només afirmacions del mateix context i condició. Una diferència entre llegat i sistema objectiu és una migració, no un conflicte.
5. Classificar cada afirmació com `CONFIRMAT`, `PROPOSTA`, `PENDENT`, `CONFLICTE` o `NO APLICABLE`.
6. Emplenar les 21 seccions exactes de la plantilla. Cada secció de la 3 a la 21 ha de contenir almenys una afirmació classificada.
7. Executar `validate-functional-card.php`. No presentar la fitxa com a vàlida si el validador falla.

## Regles d'estat

- `CONFIRMAT`: necessita almenys una font del repositori `AUTORITZADA` que sustenti exactament el contingut.
- `PROPOSTA`: deducció o recomanació de l'assistent. Pot no tenir font, però mai es presenta com a decisió presa.
- `PENDENT`: ha d'incloure `Pregunta:` i `Responsable:`. Marcar `Bloqueja: SÍ` quan impedeix definir impacte fiscal, permisos, idempotència o implementació.
- `CONFLICTE`: necessita almenys dues fonts i ha d'explicitar `A:` i `B:`. No s'escull guanyador automàticament.
- `NO APLICABLE`: necessita una exclusió expressa i ha d'incloure `Exclusió:`.

## Format obligatori de cada afirmació

```text
- [UC26-FLOW-001] [CONFIRMAT] Contingut. | Fonts: SRC-004 | Bloqueja: NO
```

Per a més d'una font, separar els IDs amb coma. Si una proposta no té font, usar `Fonts: —`.

Els IDs han de començar amb l'identificador del cas sense signes. Per exemple, `UC-26` es converteix en `UC26-`.

## Preparació per programar

La fitxa només pot usar `READY_FOR_PROGRAMMING` quan no existeixi cap `CONFLICTE` ni cap `PENDENT` bloquejant. L'estat d'implementació és independent de l'estat epistemològic de cada afirmació.

## Persistència

La sortida de l'MVP és `PREVIEW`. Si el cas ja existeix a `documentacio/04-estat-final/33-casos-us-sif.md`, la fitxa és una proposta d'ampliació d'aquell cas. No s'ha de crear un document canònic paral·lel ni modificar automàticament el repositori o Trello.
