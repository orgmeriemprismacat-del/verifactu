# Importador Trello

Script CLI per pujar targetes noves als Trellos VERI*FACTU.

## Credencials

No guardis tokens dins el repo. Fes servir variables d'entorn:

```powershell
$env:TRELLO_KEY="..."
$env:TRELLO_TOKEN="..."
```

## Dry-run

Per defecte no crea res:

```powershell
php .\00-control\trello-import\trello-import-cards.php --input=.\00-control\trello-import\targetes-noves.sample.json
```

Per revisar un informe Markdown sense trucar a Trello:

```powershell
php .\00-control\trello-import\trello-import-cards.php --input=.\00-control\trello-auditoria-interficies-proves-faltants-2026-06-08.md --offline
```

## Crear targetes

Quan el dry-run sigui correcte:

```powershell
php .\00-control\trello-import\trello-import-cards.php --input=.\00-control\trello-import\targetes-noves.sample.json --execute --create-lists --create-labels
```

## Entrades admeses

JSON:

```json
{
  "defaults": {
    "board": "Intranet/interficies",
    "list": "Pantalla - Passar pagaments / conciliacio SIF",
    "labels": ["INTRANET", "PAGAMENTS"]
  },
  "cards": [
    {
      "name": "Nom de la targeta",
      "desc": "Descripcio",
      "labels": ["INTRANET", "UI"],
      "checklists": [
        {"name": "Checklist", "items": ["Punt 1", "Punt 2"]}
      ]
    }
  ]
}
```

Markdown:

El script tambe pot llegir taules amb aquest format:

```markdown
| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Redissenyar cerca | INTRANET, PAGAMENTS |
```

## Alias de Trellos

El script enten aquests alias:

- `Intranet/interficies`
- `Proves/entorns/produccio`
- `Fitxes funcionals i documentacio de casos`
- `SIF pay.prisma.cat`
- `Control del projecte`
- `Casos d'us / analisi funcional`

També pots afegir un alias temporal:

```powershell
php .\00-control\trello-import\trello-import-cards.php --input=targetes.json --alias=MEU_ALIAS="Nom complet del Trello"
```

## Seguretat

- `--execute` es obligatori per crear targetes.
- Sense `--allow-duplicates`, si una targeta amb el mateix nom ja existeix a la mateixa llista, se salta.
- Sense `--create-lists`, una llista inexistent provoca error.
- Sense `--create-labels` ni `--ignore-missing-labels`, una etiqueta inexistent provoca error.
