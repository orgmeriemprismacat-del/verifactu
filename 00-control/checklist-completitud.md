# Checklist de completitud

Aquest checklist controla si la informacio del xat antic ja ha estat revisada i incorporada als documents.

## Recuperacio inicial

- [x] Copiar documentacio recuperada a `documentacio/`.
- [x] Copiar xat antic a `xat-original/`.
- [x] Crear fitxers de control a `00-control/`.
- [x] Obrir xat pont.
- [x] Fer primera revisio inicial del xat antic per temes.
- [x] Proposar ordre de revisio per blocs.

## Revisio per arees

- [x] Context actual de PrisMa revisat contra el xat antic.
- [x] Casos de facturacio revisats contra el xat antic.
- [ ] Pagaments, Redsys i `pay.prisma.cat` revisats contra el xat antic.
- [ ] Base de dades i relacions revisades contra el xat antic.
- [ ] Compliment AEAT i declaracio responsable revisats contra el xat antic.
- [ ] Pantalles, permisos i operacio interna revisats contra el xat antic.
- [ ] Correus, plantilles, PDF/QR i notificacions revisats contra el xat antic.
- [ ] Proves, produccio i governanca revisades contra el xat antic.

## Documents clau

- [ ] `documentacio/README.md` reflecteix l'estat actual.
- [ ] `documentacio/00-index-i-pla/documentacio-verifactu.md` esta actualitzat.
- [ ] `documentacio/01-compliment-aeat/documentacio-sif-aeat.md` esta complet.
- [ ] `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md` esta revisat.
- [ ] `documentacio/02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md` esta complet.
- [ ] `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md` esta complet.
- [ ] `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md` esta al dia.
- [ ] `documentacio/04-estat-final/05-model-bd-sif.md` esta complet.
- [ ] `documentacio/04-estat-final/15-estat-final-sistema.md` esta complet.
- [ ] `documentacio/04-estat-final/25-panell-sif-pay-prisma.md` esta complet.
- [ ] `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` esta complet.

## Criteri per marcar una area com a tancada

Una area es pot marcar com a revisada quan:

- el xat pont ha buscat informacio del tema al xat antic;
- s'han comparat els resultats amb els documents existents;
- les diferencies importants s'han incorporat o justificat;
- `registre-decisions.md` recull les decisions noves;
- `estat-projecte.md` diu que l'area esta revisada.
