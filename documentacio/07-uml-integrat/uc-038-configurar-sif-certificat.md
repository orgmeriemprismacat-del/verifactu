# UC-38 · Configurar el SIF i el certificat sense exposar secrets

**Objectiu original:** secrets i certificat fora del repositori; implementació pendent. **Estat [DISSENY/PARCIAL].** `sif/config/sif.php` carrega paràmetres des de variables d'entorn, però un fitxer de configuració i comprovacions locals no constitueixen un circuit complet de provisió, rotació, revocació i activació d'entorn.

## 1. Codi i contracte de configuració

`sif/config/sif.php` llegeix `SIF_ENV`, DSN/usuari/contrasenya de BD SIF i llegada, dades d'emissor i sèries, `SIF_REDSYS_MERCHANT_KEY`, i paràmetres AEAT: WSDL, endpoint, XSD, ruta/contrasenya del certificat, NIF emissor, ID/versió SIF i ID instal·lació, així com política de retry. Els valors de desenvolupament (inclòs NIF d'exemple) **no són una configuració productiva validada**.

`ClientCertificate::inspect()` comprova que el fitxer PKCS#12 és llegible **fora del repo/webroot**, que certificat/clau privada coincideixen i que la validesa temporal és correcta. El codi mateix adverteix que aquestes comprovacions locals **no acrediten confiança de l'AEAT, revocació ni representació**. `AeatPreflight::check()` comprova extensions, URLs HTTPS, XSD/certificat llegibles i camps no buits; tampoc comprova qualitat del contingut, identitat fiscal o connexió remota amb acceptació real. `SoapTransport` revisat **només admet l'endpoint AEAT de proves** i refusa un endpoint diferent; no deduir disponibilitat productiva.

## 2. Fitxa funcional

| Element | Contracte |
| --- | --- |
| Actor | Administrador SIF autoritzat, responsable de seguretat/certificat i responsable fiscal per a emissor i representació. |
| Entrada | Entorn `local/test/preproduction/production` segons política final, host/DB segregats, emissor real, sèries, referències opaques de secrets, certificat i fingerprint, dates de validesa, endpoint/WSDL/XSD, versió/instal·lació i `REQUEST_ID`. |
| Secret | Variables d'entorn o magatzem protegit segons entorn i permisos mínims; **no** copiar clau Redsys, password de BD, password del P12 o clau privada a Git, `PAYLOAD_JSON`, exportacions, logs o captures d'errors. |
| Certificat | Verificar fitxer, fingerprint, titular, habilitació/representació fiscal aplicable, cadena de confiança, venciment i revocació **amb un circuit aprovat**; el PHP existent només comprova una part d'aquests punts localment. |
| Canvi de configuració | Versionar artefacte/configuració (`sif_version.CONFIG_HASH` definida a SQL), actor, data, resultat de proves, pla de rollback i finestres de rotació; no activar secrets nous en worker i callback de manera inconsistent. |
| Integració | UC-39 preflights, UC-46/83 activació versionada; UC-77 transmet únicament quan la versió/entorn compleixen les condicions reals. `ready=true` local no substitueix una validació de l'AEAT. |
| Economia | Canviar certificat/endpoint no modifica factures ni genera pagaments; els registres pendents conserven identitat i hash, i el tractament de retries incerts requereix UC-77. |

### Flux i riscos

1. Preparar configuració **separada per entorn**, amb issuer/NIF/sèrie coherent amb empresa emissora i secrets fora de Git. No assumir que el valor local d'exemple és l'emissor autoritzat.
2. Comprovar permisos de fitxer, cicle de vida P12, cadena de confiança/representació segons procés extern aprovat i disponibilitat d'endpoint, WSDL i XSD. Registrar només fingerprint, caducitat i codi de resultat, mai password ni bytes de certificat.
3. Executar `AeatPreflight`, comprovacions de BD SIF/llegada i proves de l'entorn; diferenciar **preflight de presència** i prova de funcionalitat. Si falla, bloquejar activació, no editar registres fiscals per adaptar-los a una configuració incompleta.
4. Preparar canvi de versió/configuració i finestra de rotació amb worker drenat o estratègia segura per les transaccions en vol; conservar evidència de rollback.
5. Aprovar i activar només després del go/no-go UC-39 i la validació del transport **de l'entorn objectiu**. El `SoapTransport` actual restringit a test és un bloquejant de qualsevol afirmació d'enviament productiu amb aquesta classe.
6. Provar: P12 inaccessible/dins webroot, password incorrecta, clau/certificat discordants, caducitat/revocació, NIF emissor incorrecte, endpoint productiu rebutjat per `SoapTransport`, workers concurrents amb dos fingerprints, secrets en logs i rollback després de callback en curs.

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Administrador SIF" as A
actor "Responsable fiscal/seguretat" as F
rectangle "SIF · configuració segura" {
 usecase "UC-38\nConfigurar entorn i certificat" as Main
 usecase "Externalitzar i validar secrets" as Secrets
 usecase "Comprovar P12 i representació" as Cert
 usecase "Executar preflights per entorn" as Check
 usecase "UC-39/46\nAprovar canvi i activació" as Activate
}
A --> Main
F --> Cert
Main ..> Secrets : <<include>>
Main ..> Cert : <<include>>
Main ..> Check : <<include>>
Activate ..> Main : <<extend>> (proves i aprovació)
@enduml
```

## 4. UML de classes — configuració carregada, governança pendent

```mermaid
classDiagram
class SifEnvironmentConfiguration {
 <<PHP existent: config/sif.php>>
 +loadEnvironment() array
}
class ClientCertificate {
 <<PHP existent>>
 +inspect(now) array
 +curlOptions() array
}
class AeatPreflight {
 <<PHP existent>>
 +check(config) array
}
class SoapTransport {
 <<PHP existent: només TEST_ENDPOINT>>
 +send(fiscalPayload) array
}
class SecureConfigurationChangeService {
 <<DISSENY: no acreditat>>
 +propose(configuration,actor) version
 +approveAndActivate(version) result
}
SecureConfigurationChangeService --> AeatPreflight : prerequisits
SecureConfigurationChangeService --> ClientCertificate : P12/fingerprint
SoapTransport --> ClientCertificate : mTLS
```

## 5. UML de seqüència — certificat no apte (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
actor A as Administrador
participant C as SecureConfigurationChangeService [DISSENY]
participant Env as config/sif.php [PHP]
participant P as AeatPreflight [PHP]
participant Cert as ClientCertificate [PHP]
participant G as Gate UC-39/46 [pendent]
A->>C: Proposar entorn, issuer, fingerprint i endpoint
C->>Env: Carregar referències de secrets i paràmetres
C->>P: check(config AEAT)
C->>Cert: inspect()
alt P12 invàlid, secrets absents o entorn no qualificat
 Cert-->>C: Fallada / validació incompleta
 C-->>A: Bloqueig d'activació, cap secret al log
else Preflight local correcte
 Cert-->>C: Fingerprint i caducitat
 P-->>C: ready=true local
 C->>G: Sol·licitar proves reals i aprovació formal
 G-->>A: Decisió d'activació o bloqueig
end
Note over C,G: El transport PHP revisat només admet TEST_ENDPOINT; el flux productiu no està acreditat.
```

## 6. Traçabilitat

[UC-38 original](../06-fitxes-funcionals/uc-038.md) · [UC-39 proves](../06-fitxes-funcionals/uc-039.md) · [UC-46 activació](../06-fitxes-funcionals/uc-046.md) · [UC-67 secrets original](../06-fitxes-funcionals/uc-067.md) · [config/sif.php](../../sif/config/sif.php) · [ClientCertificate](../../sif/src/Aeat/ClientCertificate.php) · [AeatPreflight](../../sif/src/Service/AeatPreflight.php) · [SoapTransport](../../sif/src/Aeat/SoapTransport.php).
