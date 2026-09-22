# UC-116 — diagrames d'activitat per pàgina i apartat (RM-037)

**Versió del codi observat:** main @ e71958b3026549bde09fb4b25f2ec3ba370937ec (22/09/2026). **Estat:** diagrames delimitats per les fonts visibles; la pàgina/formulari que crida l'upload no s'ha identificat, ni el cos de `Intranet::mostrarPage` / `sendMsgValidatCurosDescomptes`; per tant, aquests punts es marquen **PENDENT DE CONTRAST**. No és una representació completa de totes les pàgines de la web o de la intranet. [Auditoria i matriu d'accions](00-auditoria-casos-pendents-lot-05-uc-116-2026-09-22.md).

## 1. Pàgina pública «Descomptes» — cinc apartats visibles

**Font:** [Descomptes.php](../../codi-drive/web-actual/Descomptes.php#L185-L257) mostra les seccions «Alumnes PrisMa», «Carnet Jove», «Socials», «USOC» i «Grups i centres» i consulta preus per la taula. Només l'apartat «Socials» indica explícitament aportar prova i esperar validació. La classe és un renderitzador de contingut: NO acredita la URL de formulari, una descàrrega ni l'execució del procés de validació.

### ACTUAL — pàgina informativa

```plantuml
@startuml
title Descomptes | Pàgina pública ACTUAL (només render i enllaços acreditats)
start
:Renderitzar títol, bàner i cinc apartats;
fork
  :Alumnes PrisMa;
  :Mostrar requisit d'haver cursat i descompte automàtic per DNI;
fork again
  :Carnet Jove;
  :Mostrar indicació de marcar carnet i recàlcul del preu;
fork again
  :Socials;
  :Indicar que cal aportar document al formulari i esperar validació;
fork again
  :USOC;
  :Indicar comprovació de l'afiliació per USOC;
fork again
  :Grups i centres;
  :Mostrar descomptes per nombre d'integrants i enllaç a formulari grupal;
end fork
:Mostrar taules de preus i informació;
note right
  Aquesta classe no acredita
  quin formulari/JS real fa la càrrega
  ni el permís amb què llegeix el fitxer.
end note
stop
@enduml
```

### FINAL — informació i vies segons evidència realment necessària (DISSENY)

```plantuml
@startuml
title Descomptes | Pàgina pública FINAL (contracte proposat)
start
:Mostrar apartats i regles vigents per producte;
if (La modalitat necessita justificant?) then (sí, segons regla)
  :Mostrar només la informació mínima del document requerit;
  :Enllaçar al formulari protegit del producte/inscripció;
else (no)
  :No sol·licitar document addicional;
endif
:Separar verificació de dret i preu del lliurament fiscal;
:No incrustar URLs de justificants ni dades personals a la pàgina;
stop
@enduml
```

**Pendents:** URL/pàgina real de cadascun dels formularis, variants i tipus de preu vigents, condicions de grup i si la comprovació USOC comporta o no càrrega documental. Aquests apartats corresponen també als UC comercials respectius; no reinterpretar-los tots com a UC-116.

## 2. Apartat de formulari web «Aportar document» — ruta AJAX contrastada

**Font:** [enviarImatgeCarnetInscripcio.php](../../codi-drive/web-actual/ajax/enviarImatgeCarnetInscripcio.php). **Origen de pàgina i controls del navegador no identificats**: només se'n dibuixa el subflux servidor i se'n marca l'entrada desconeguda.

### ACTUAL — endpoint de pujada i preparació d'avís

```plantuml
@startuml
title UC-116 | Apartat upload web ACTUAL (formulari desconegut)
start
:Rebre POST codiCurs, edicio, any, documentacio i FILE;
:Normalitzar text documentacio i formar nom amb extensió original;
:Intentar move_uploaded_file a ajax/carnets/;
if (move_uploaded_file dona cert?) then (sí)
  :Retornar camí local carnets/nom;
else (no)
  :Retornar 0;
endif
:Construir HTML amb URL directa /ajax/carnets/nom;
:Consultar paràmetres d'autenticació de correu;
:Instanciar objecte MailSMTPFile;
note right
  Aquest fitxer NO acredita
  el resultat del transport de correu,
  rol del remitent, ID_INSC, hash
  ni emmagatzematge privat.
end note
stop
@enduml
```

### FINAL — endpoint de recepció i custòdia (DISSENY)

```plantuml
@startuml
title UC-116 | Apartat upload web FINAL (contracte a implementar)
start
:Rebre sol·licitud amb sessió o identitat autoritzada;
:Validar actor, inscripció, producte, TIPUS_DESC i necessitat real de prova;
if (Dret i necessitat acreditats?) then (sí)
  :Validar mida, tipus real, integritat i format admès;
  if (Document vàlid?) then (sí)
    :Derivar identificador opac; calcular hash de bytes;
    :Desar bytes en custòdia privada fora del webroot i Git;
    if (Bytes persistits i hash verificat?) then (sí)
      :Enllaçar a UUID_VALIDATION i registrar metadata/evidència;
      if (Persistència metadata correcta?) then (sí)
        :Retornar estat REBUDA/CUSTODIADA segons contracte aprovat;
        :Notificar recepció sense URL pública ni prova personal;
      else (no)
        :Marcar incidència i reconciliar fitxer sense metadata;
      endif
    else (no)
      :Marcar error; no afirmar custòdia;
    endif
  else (no)
    :Denegar tipus/contingut/mida invalids;
  endif
else (no)
  :Denegar sense guardar cap document;
endif
:No crear factura, cobrament ni aprovació comercial automàtica;
stop
@enduml
```

**Pendents:** identificar la pàgina/URL d'origen i els controls del navegador, verificació al servidor productiu, política per tipus de descompte, protocol d'accés, retenció i gestió de fitxer prèviament compartit amb una URL directa.

## 3. Pàgina intranet «Validar descomptes» — dos apartats amb accions diferents

**Fonts:** [alumnes-validar-descomptes.php](../../codi-drive/intranet-actual/alumnes-validar-descomptes.php), [JS](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js), [mostrarMain.php](../../codi-drive/intranet-actual/ajax/mostrarMain.php) i [wrapper de decisió](../../codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatCurosDescomptes.php). La pàgina inclou els selectors `#inscripcions` (descompte) i `#inscripcions_recent_titulat` (resguard); el segon apartat té funcionalitat pròpia, però **no es revisa el seu UC** en aquest lot.

### ACTUAL — càrrega de pàgina i acció de descompte

```plantuml
@startuml
title UC-116 | Intranet Validar descomptes ACTUAL (PHP/JS recuperat)
start
:Comprovar sessió a shell de pàgina;
if (configOk?) then (sí)
  :Carregar mainpanel i JS de descomptes;
  :GET ajax/mostrarMain.php amb URL de pàgina;
  :Consultar apartat i rol de visualització;
  if (tePermisVisualitzacio?) then (sí)
    :Intranet::mostrarPage genera contingut (cos NO revisat);
    :El JS associa clics a #inscripcions i #inscripcions_recent_titulat;
    if (Clic a opció del descompte?) then (sí)
      :Canviar classes i text visual SÍ/NO;
      :Quan es clica validat, GET idInsc i verificat;
      :Wrapper delega a Intranet::sendMsgValidatCurosDescomptes;
      note right
        Cos del mètode NO inspeccionat.
        No es pot afirmar l'UPDATE real,
        el permís per acció ni l'enviament.
      end note
      if (Resposta conté error segons JS?) then (sí)
        :Mostrar modal d'error;
      else (no)
        :Mostrar modal de missatge;
      endif
    else (no)
      :Altres accions de pàgina: pendent de mapatge;
    endif
  else (no)
    :Mostrar missatge de pàgina sense permís;
  endif
else (no)
  :Redirigir a intranet;
endif
stop
@enduml
```

### FINAL — revisió i decisió amb permisos/traça (DISSENY)

```plantuml
@startuml
title UC-116 | Intranet Validar descomptes FINAL (contracte proposat)
start
:Identificar actor i rol al servidor per cada acció;
:Consultar sol·licituds i estats de validació autoritzats;
if (Actor té permís per aquesta inscripció i finalitat?) then (sí)
  :Mostrar metadades mínimes i opció de consultar prova;
  if (Sol·licita lectura de prova?) then (sí)
    :Autoritzar lectura al backend, auditar accés i verificar hash dels bytes;
    if (Fitxer íntegre i existent?) then (sí)
      :Servir prova en canal autenticat i amb política de descàrrega;
    else (no)
      :Registrar incidència, no validar amb prova absent;
    endif
  else (no)
    :Cap accés al contingut documental;
  endif
  if (Revisor decideix acceptar o denegar?) then (sí)
    :Revalidar permís, regla, versió, evidència i estat actual;
    :Guardar decisió i actor en event auditat idempotent;
    :Separar aplicació econòmica i rectificació fiscal si correspon;
    :Notificar resultat autoritzat sense adjuntar prova sensible;
  else (no)
    :Conservar expedient pendent;
  endif
else (no)
  :Denegar i auditar intent d'accés/acció;
endif
:Conservar termini de retenció aprovat i estat del justificant separats de factura;
stop
@enduml
```

**Pendents per completar RM-037:** estructura exacta d'apartats generada per `Intranet::mostrarPage`, accions de la taula i el mètode de persistència; ampliar diagrames de la pàgina completa quan estiguin acreditades les fonts. Els subfluxos del resguard novell **no s'auditen ni es donen per completats** en UC-116.
