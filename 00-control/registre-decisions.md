# Registre de decisions

Aquest fitxer recull decisions importants del projecte. Cada xat nou l'ha de llegir abans de proposar canvis de criteri.

## 2026-05-19 - Crear projecte pont

Decisio:
Crear un projecte pont separat del xat antic, amb documentacio, copia del registre original i fitxers de control.

Motiu:
El xat antic supera el limit de context quan Codex intenta compactar-lo. Cal conservar la informacio sense dependre d'un unic fil de conversa.

Impacte:
Els futurs xats treballaran amb fitxers compartits, no amb memoria implicita del xat antic.

## 2026-05-19 - Fer servir un xat pont abans dels xats especialitzats

Decisio:
Crear un xat pont per revisar el xat antic per blocs i completar la documentacio abans d'obrir xats especialitzats.

Motiu:
La usuaria sospita que va donar informacio important al xat antic que no esta reflectida als documents.

Impacte:
El xat pont es responsable de recuperar, classificar i documentar informacio perduda o incompleta.

## 2026-05-19 - La memoria del projecte son els fitxers

Decisio:
Els documents de `00-control/` i `documentacio/` seran la font de veritat compartida entre xats.

Motiu:
Els xats llargs poden saturar el context. Els fitxers permeten continuar amb xats nous sense perdre continuitat.

Impacte:
Cada xat ha de llegir nomes els fitxers que necessita i actualitzar els fitxers de control abans de tancar.

