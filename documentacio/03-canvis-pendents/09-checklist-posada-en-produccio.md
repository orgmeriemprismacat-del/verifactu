# 09 - Checklist de posada en produccio

> Document especific pendent de desenvolupar. Recollira comprovacions previes a l'arrencada del SIF.

## Blocs de checklist

- Certificat digital de l'entitat: disponibilitat, ubicacio segura, permisos d'us i proves de connexio AEAT.
- Entorn de proves AEAT.
- BD fiscal.
- Permisos MySQL.
- Backups.
- Endpoints SIF.
- Redsys.
- PDFs/QR.
- Cua AEAT.
- Notificacions.
- Panell SIF `pay.prisma.cat/sif`.
- Dashboard SIF.
- Factures SIF.
- Registres AEAT.
- Incidencies SIF.
- Documents SIF.
- Versions SIF.
- Exportacions SIF.
- Configuracio SIF.
- Apartat `VERI*FACTU` a la intranet.
- Indicador visual de pendents a la intranet.
- Resum d'incidencies pendents a la intranet.
- Intranet alumne.
- Rectificatives.
- Formacio interna.
- Declaracio responsable.

## Detall del bloc AEAT i declaracio responsable

Abans de posar el SIF en produccio cal comprovar:

- [ ] Abast normatiu aplicable a PrisMa confirmat: Impost sobre Societats o altre regim, no SII, no normativa foral basca/navarresa i sense exempcio especifica que alteri l'obligacio.
- [ ] Termini aplicable confirmat amb criteri intern o gestoria: 1 de gener de 2027 si aplica Impost sobre Societats, o 1 de juliol de 2027 per la resta d'obligats afectats.
- [ ] Modalitat `VERI*FACTU` confirmada com a modalitat de produccio.
- [ ] Confirmat que no cal comunicar l'opcio `VERI*FACTU` via model 036, segons FAQ AEAT vigent, llevat de canvi normatiu posterior.
- [ ] Certificat digital de l'entitat o apoderament disponible.
- [ ] Certificat o apoderament provat contra l'entorn AEAT corresponent.
- [ ] Ubicacio segura del certificat definida, amb permisos minims i registre d'us.
- [ ] Endpoints/WSDL/serveis AEAT configurats.
- [ ] Cua d'enviament AEAT provada amb error, retry i resolucio d'incidencia.
- [ ] Text `VERI*FACTU` o "Factura verificable en la sede electronica de la AEAT" incorporat al PDF quan correspongui.
- [ ] QR generat amb dades definitives segons especificacio AEAT vigent.
- [ ] XML/registres fiscals generats i conservats.
- [ ] CSV/resposta AEAT conservada quan correspongui.
- [ ] Declaracio responsable `1.0.0` revisada, amb versio exacta, components finals, data, lloc i signants.
- [ ] Declaracio responsable disponible dins del SIF de forma llegible, individualitzada i accessible.
- [ ] Registre de versions actualitzat amb versio activa i declaracio associada.
- [ ] Proves principals executades i evidencies guardades.
- [ ] Punts fiscals sensibles revisats internament i marcats com a pendents de validacio externa si no hi ha assessor fiscal.

Punts fiscals sensibles a revisar abans de signar o activar produccio:

- rectificativa per canvi de NIF, rao social o dades fiscals;
- factura abans de cobrament no pagada;
- compensacions i saldos a favor;
- mencio exacta d'exempcio IVA per cursos;
- text visible de descomptes per evitar dades sensibles.
