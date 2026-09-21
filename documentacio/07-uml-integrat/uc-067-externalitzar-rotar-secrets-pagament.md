# UC-67 · Externalitzar i rotar els secrets de pagament

**Objectiu original:** cap clau Redsys ni credencial al codi/webroot; secret protegit i rotació provada. **Estat [PENDENT/BLOQUEJANT] del cicle complet.** La configuració existent llegeix el secret des de l'entorn, però no prova que el desplegament real el guardi amb permisos adequats ni que es pugui rotar sense rebutjar callbacks vàlids.

## Evidència revisada

`sif/config/sif.php` obté `SIF_REDSYS_MERCHANT_KEY` amb `getenv()`, així com DSN/usuaris/passwords de BD i ruta/password del P12 AEAT. `sif/public/api/redsys/callback.php` construeix `RedsysSignatureValidator` amb la clau carregada i comprova la signatura **abans** de lliurar el callback a `RedsysCallbackService`; aquest últim consulta la intenció `DS_ORDER`, valida import/divisa/terminal i crea notificació/job idempotents. `RedsysSignatureValidator` utilitza la **clau que rep al constructor**: no s'ha acreditat en aquesta classe un `key_id`, una finestra de coexistència de claus ni un circuit de rotació. Cap secret real s'ha llegit o exposat en aquesta fitxa.

## Contracte operatiu de rotació

| Fase | Regla |
| --- | --- |
| Inventariar | Identificar secret Redsys del comerç/terminal i entorn, usuaris/passwords SIF i llegat, certificat AEAT i clients/workers que depenen de cadascun. Una clau de preproducció **no** autoritza operacions de producció. |
| Custodiar | Referència opaca en magatzem/variable d'entorn protegit, fitxers fora de webroot, permisos mínims i registre de qui pot llegir-la. No guardar valor a Git, `sif_audit_event`, traça d'error, screenshot, payload de callback o PR. |
| Preparar Redsys | Coordinar versió de clau i data efectiva amb el proveïdor; establir política **aprovada** per validar notificacions d'intencions creades abans del canvi. No afegir «acceptar qualsevol de les dues claus» indefinidament ni suposar que el validador PHP actual ja ho fa. |
| Activar | Canviar referència/configuració als punts d'entrada i workers afectats, verificar handshake/signatura de prova, mantenir `DS_ORDER` i `UUID_INTENT` originals. No reemetre factura ni crear `CHARGE` perquè ha canviat la clau. |
| Tancar finestra | Revocar credencial antiga quan no queda trànsit legítim pendent, auditar el canvi per hash/fingerprint i actor **sense valor secret**, provar rollback segur davant error de signatura i actualitzar UC-38/39. |
| Error de callback | Una signatura invàlida **no es converteix en ingrés confirmat** ni s'accepta sense comprovació per «no perdre el pagament». Si hi ha cobrament bancari real però notificació rebutjada durant la rotació, obrir conciliació UC-82. |

### Flux objectiu

1. Gestió tècnica prepara inventari de secrets, entorns, clients i notificacions/ordres TPV en vol; registrar versió i fingerprint no reversible, actor i pla de tall.
2. Provisionar secret nou en magatzem protegit, assegurar que `config/sif.php` i el callback el llegeixen segons entorn i desplegament actual; verificar permisos i que cap error mostra la clau.
3. Validar notificacions de proves i definit política explícita de coexistència o tall per ordres antigues; **l'actual `RedsysSignatureValidator` no implementa per si sol selecció de claus per ordre**.
4. Coordinar activació dels productors de pagament i receptors de callbacks, monitorar errors/duplicats; en incertesa, recuperar l'estat real de Redsys/banc abans de crear cap moviment.
5. Registrar prova d'activació, revocar clau anterior i comprovar que retries de callbacks ja validats es processen amb la mateixa notificació/job; la rotació no canvia la cadena fiscal.

**Proves:** clau absent/incorrecta, secret exposat per log, callback d'ordre antiga després de rotació, dues instàncies amb claus distintes, replay, `DS_ORDER` conegut però import no coincident, timeout bancari amb resposta signada rebutjada, rollback sense doble `CHARGE`.

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Responsable seguretat" as S
actor "Operador TPV" as T
rectangle "SIF · rotació secrets" {
 usecase "UC-67\nExternalitzar i rotar secrets" as Main
 usecase "Inventariar clients, entorns i claus" as Inv
 usecase "Provisionar clau nova sense exposar-la" as Store
 usecase "Validar callbacks durant el canvi" as Check
 usecase "Revocar clau antiga i auditar resultat" as Revoke
}
S --> Main
T --> Check
Main ..> Inv : <<include>>
Main ..> Store : <<include>>
Main ..> Check : <<include>>
Main ..> Revoke : <<include>>
@enduml
```

## UML de classes

```mermaid
classDiagram
class PaymentSecretRotationService {
 <<DISSENY: no acreditat>>
 +prepare(environment,scope) plan
 +activateApproved(plan) result
 +revokePrevious(plan) result
}
class ProtectedSecretProvider {
 <<DISSENY: desplegament real no acreditat>>
 +resolve(reference,environment) secret
}
class RedsysSignatureValidator {
 <<PHP existent: una clau al constructor>>
 +decodeAndVerify(request,context) array
}
class RedsysCallbackService {
 <<PHP existent: valida intenció després de signatura>>
 +receiveCallback(db,payload,signatureValid) array
}
PaymentSecretRotationService --> ProtectedSecretProvider : referència de clau
PaymentSecretRotationService ..> RedsysSignatureValidator : prova de versió per ordre pendent
RedsysSignatureValidator ..> RedsysCallbackService : dades verificades
```

## UML de seqüència — callback antic després de rotació (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
actor S as Responsable seguretat
participant R as PaymentSecretRotationService [DISSENY]
participant K as ProtectedSecretProvider [DISSENY]
participant V as RedsysSignatureValidator [PHP]
participant C as RedsysCallbackService [PHP]
participant B as Conciliació banc/Redsys [pendent]
S->>R: Proposar clau nova per comerç/entorn
R->>K: Provisionar referència nova amb permisos
R->>R: Validar política aprovada per ordres anteriors
S->>R: Activar canvi coordinat
R->>V: Carregar clau aplicable al callback [selecció pendent]
alt La signatura de callback antic no es pot verificar
 V-->>R: Rebuig sense CHARGE
 R->>B: Cercar cobrament real i obrir incidència si escau
else Signatura vàlida i intenció coherent
 V->>C: receiveCallback(payload,signatureValid=true)
 C-->>R: Notificació/job original o reutilitzat
end
R->>K: Revocar secret antic segons finestra verificada
Note over R,V: El validador PHP actual no tria versions de clau per DS_ORDER.
```

## Traçabilitat

[UC-67 original](../06-fitxes-funcionals/uc-067.md) · [UC-38 config/certificat](uc-038-configurar-sif-certificat.md) · [UC-77 AEAT](uc-077-operar-enviament-aeat-retry-dead-letter.md) · [UC-82 conciliació](uc-082-reconciliar-sif-bd-llegada.md) · [config/sif.php](../../sif/config/sif.php) · [RedsysSignatureValidator](../../sif/src/Service/RedsysSignatureValidator.php) · [Callback web](../../sif/public/api/redsys/callback.php) · [RedsysCallbackService](../../sif/src/Service/RedsysCallbackService.php).
