# Vistiplau tècnic de la versió SIF PrisMa

**Estat documental:** PENDENT D'EMISSIÓ. NO SIGNAR COM A FAVORABLE FINS COMPLETAR LES EVIDÈNCIES.

Aquest document recull la revisió tècnica prèvia a la subscripció de la declaració responsable i a l'entrada en producció. No substitueix la declaració responsable de l'entitat productora ni la subscripció formal per una persona amb representació suficient.

## Identificació de la revisió

- Sistema: SIF PrisMa
- Codi del sistema: SIF-PRISMA
- Versió candidata: 1.0.0
- Entorn verificat: pendent
- Referència del codi o desplegament: pendent
- Data de la revisió: pendent
- Responsable tècnica: Meriem Abjil Bajja
- Data de revisió de fonts AEAT/BOE: pendent
- Termini legal aplicable a l'entitat: pendent de confirmació

## Abast del vistiplau

La revisió tècnica ha de confirmar que la versió identificada coincideix amb el codi desplegat, que els components descrits a la declaració responsable existeixen, que el certificat o representació s'ha provat des de l'entorn real del SIF i que les proves conservades permeten sostenir el resultat emès.

## Evidències mínimes

| ID | Comprovació | Estat | Referència de l'evidència |
|---|---|---|---|
| VT-01 | Versió 1.0.0 identificada i desplegada a l'entorn verificat | PENDENT | |
| VT-02 | Migracions de base de dades aplicades i esquema fiscal revisat | PENDENT | |
| VT-03 | Numeració fiscal, registres d'alta i rectificatives verificats | PENDENT | |
| VT-04 | Encadenament hash i integritat dels registres verificats | PENDENT | |
| VT-05 | Idempotència i tractament de duplicats verificats | PENDENT | |
| VT-06 | XML i validacions XSD o del servei AEAT superats | PENDENT | |
| VT-07 | Certificat o representació configurat i autenticació AEAT provada des del worker o entorn real del SIF | PENDENT | |
| VT-08 | Cua AEAT, reintents, respostes i incidències verificats | PENDENT | |
| VT-09 | PDF, QR i accés als documents fiscals verificats | PENDENT | |
| VT-10 | Permisos, secrets, logs i absència de claus al repositori revisats | PENDENT | |
| VT-11 | Backup i restauració executats amb evidència | PENDENT | |
| VT-12 | Declaració responsable, versió activa i documentació accessibles dins del SIF | PENDENT | |
| VT-13 | Proves dels canals i casos especials exigits pel projecte superades | PENDENT | |
| VT-14 | Incidències bloquejants tancades o inexistents | PENDENT | |
| VT-15 | Fonts oficials AEAT/BOE revisades i termini legal aplicable confirmat | PENDENT | |
| VT-16 | Mapa camp normatiu -> taula interna -> XML/PDF/QR -> prova completat | PENDENT | |
| VT-17 | Rol auditor/AEAT només lectura verificat sense escriptura ni accés a secrets | PENDENT | |

Estats admesos: `OK`, `NO OK`, `NO APLICA` amb justificació o `PENDENT`.

## Resultat tècnic

S'ha de marcar una única opció quan totes les comprovacions necessàries tinguin evidència:

- [ ] GO. Versió tècnicament preparada per a l'activació dins de les facultats delegades i perquè Associació PrisMa subscrigui la declaració responsable.
- [ ] GO AMB LIMITACIONS. Preparada únicament dins de les limitacions descrites i documentades amb l'equip intern quan tinguin efectes no tècnics.
- [ ] NO-GO. No preparada per entrar en producció ni per subscriure la declaració responsable com a versió conforme.

**Resultat actual:** PENDENT D'EMISSIÓ.

## Limitacions i incidències obertes

________________________________________________________________________________

________________________________________________________________________________

________________________________________________________________________________

## Manifestació de la responsable tècnica

Quan s'emeti el resultat, la responsable tècnica declara que ha revisat la versió i les evidències identificades en aquest document, que el resultat reflecteix les comprovacions efectivament realitzades i que ha comunicat les limitacions o incidències conegudes.

El vistiplau tècnic no atribueix a la responsable tècnica la condició de productora externa, representant legal o garant personal de les obligacions tributàries d'Associació PrisMa.

## Signatura tècnica

Meriem Abjil Bajja
Responsable tècnica, funcional i documental del projecte SIF PrisMa

Data: ____ de ____________________ de ______

Signatura:

____________________________________

## Recepció i constància de direcció

La direcció confirma que ha rebut el resultat tècnic i deixa constància de la situació comunicada. Aquesta recepció no substitueix la conformitat de la responsable tècnica ni habilita la direcció o qualsevol altra persona a activar una versió o ordenar actuacions sobre pagaments al marge de l'acord intern:

- [ ] Ha rebut el resultat `GO` i la versió ha quedat tècnicament habilitada per a l'activació.
- [ ] Ha rebut el resultat `GO AMB LIMITACIONS` i deixa constància de les limitacions compartides.
- [ ] Ha rebut el resultat `NO-GO` i deixa constància que la versió no s'ha d'activar.

Adam Carmona
Director d'Associació PrisMa

Data: ____ de ____________________ de ______

Signatura:

____________________________________

## Documents vinculats

- Declaració responsable del sistema informàtic de facturació SIF PrisMa, versió candidata 1.0.0.
- Acord intern de designació i responsabilitats del projecte SIF PrisMa.
- Expedient de proves i decisió GO o NO-GO de la versió.
- Documentació SIF AEAT i registre de versions vigents en la data de revisió.

## Referències normatives i oficials

- Reial decret 1007/2023, article 13, declaració responsable dels sistemes informàtics de facturació.
- Ordre HAC/1177/2024, article 15, contingut i ubicació de la declaració responsable.
- Reial decret llei 15/2025, modificació de terminis d'adaptació SIF.
- Fonts AEAT sobre certificació, modalitats `VERI*FACTU`, registre d'alta, FAQ i exemples de declaració responsable revisades per a la versió candidata.
