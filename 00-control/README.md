# Control del projecte VERI*FACTU / SIF

Aquesta carpeta concentra la **memòria operativa i de governança** del projecte: estat, decisions, plans, auditories, estimacions i criteris de seguiment.

No és el lloc on es demostra que una funcionalitat està implementada: aquesta evidència correspon al codi, proves, CI i evidències versionades.

## Documents vius

- `estat-projecte.md`: resum viu de situació, avenços, riscos i pendents.
- `registre-decisions.md`: decisions preses, alternatives i motiu.
- `checklist-completitud.md`: control transversal de completitud.
- `inventari-fonts-pla-2026-09-15.md`: inventari de fonts que continua sent útil per rastrejar l'origen documental.
- `trello/`: regles de taxonomia, propietat i reconciliació amb els taulers en directe.

## Planificació

Els fitxers `pla-*.md`, estimacions i matrius de planificació són eines de treball. Quan hi hagi conflicte amb l'estat real del codi, CI o una decisió posterior, preval la font més recent i verificable.

## Auditories

Les carpetes i documents d'auditoria conserven:

- què es va revisar;
- sobre quin tall o data;
- quines troballes es van fer;
- quines accions van quedar pendents;
- i quina evidència sustentava la conclusió.

Una auditoria datada és una **fotografia històrica**, no un indicador automàtic de l'estat actual.

## Snapshots i històric

Fitxers amb sufix de data, com `*_2026-09-20.md`, s'han de llegir com a snapshots. Es conserven per traçabilitat, però no s'han d'editar per fingir que són l'estat actual.

Els documents `guia-xat-pont.md`, `mapa-xats.md` i `informacio-a-recuperar-del-xat-antic.md` expliquen una fase anterior de recuperació de coneixement. Són útils com a antecedent, però el projecte actual es governa pel repositori, els commits, les proves, la documentació viva i Trello.

## Trello

Trello és la font de treball en directe. Vegeu [`trello/README.md`](trello/README.md).

Regla de traçabilitat recomanada:

```text
UC / troballa
  -> targeta propietària
  -> commit / PR / fitxer
  -> prova
  -> evidència
  -> documentació actualitzada
```

No s'han de mantenir en aquest repositori còpies massives de targetes privades ni dades sensibles.

## Què hauria d'existir

Perquè el control sigui sostenible:

- cada auditoria important ha de deixar resultat i accions;
- cada canvi funcional rellevant ha de poder vincular-se a UC o paquet;
- les decisions que canvien arquitectura o criteris fiscals han de quedar al registre de decisions;
- els plans antics no s'han d'usar com a font de veritat sense revalidació;
- els README han d'indicar explícitament si una peça és **vigent, històrica, snapshot o evidència**.
