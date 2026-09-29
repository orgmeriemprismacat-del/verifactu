# UC-108 — Implementació de hardening del codi versionat

**Data:** 29/09/2026  
**Branca:** `feat/uc-108-hardening-2026-09-29`  
**Estat:** canvis de codi versionat preparats per revisió. **No acrediten desplegament, BD real, SMTP, Moodle ni proves runtime.**

## 1. Canvis implementats

### P-TAS-01 / P-TAS-02 · Tastet

- `Tastet` inicia en estat segur `0` i només passa a `1` quan el SELECT retorna un tastet actiu.
- Evita tractar com a actiu un slug sense registre `reptes.ESTAT=1`.
- El detall i el card no depenen obligatòriament que el curs comercial original continuï actiu.
- S'inicialitza `$nivells` i s'elimina la referència a `$this->nivells` inexistent.
- Es corregeix `$versioImg` al card/carrusel i la construcció de l'URL d'imatge.
- S'inicialitza `$footerInfoCourse`.

### P-TAS-03 · Formulari i enviament

- `InscripcioTastet::mostrar()` retorna 404 si el tastet no és actiu.
- `obtenirCodiTastet.php` usa prepared statements i exigeix un tastet actiu.
- El botó d'enviament queda deshabilitat fins que `codiCurs` s'ha resolt.
- El submit torna a validar que `codiCurs` correspon al slug/ruta del formulari.
- Requests amb DNI/dades personals passen a POST.
- Es manté fallback GET temporal al PHP per compatibilitat amb clients antics.
- S'elimina el doble `closeStmt()` del detector de duplicats.
- `$comentaris` queda inicialitzat.
- Es retiren blocs residuals de CP/promoció sense origen al formulari actual.

### P-TAS-04 · Confirmació

- El servidor llegeix `keyEncr` directament de `$_GET` i deixa de retallar 16 caràcters.
- El JS codifica el token amb `encodeURIComponent`.
- Els tokens nous calculen HMAC sobre `IV + ciphertext`.
- El verificador manté compatibilitat temporal amb tokens antics.
- Es valida base64, longitud mínima i ID desxifrat numèric.
- La confirmació deixa de carregar DNI, imatge i curs comercial original.
- La confirmació històrica ja no exigeix `reptes.ESTAT=1`.

### SMTP

- `MailSMTPComvive` conserva el resultat de `PHPMailer::send()`.
- S'afegeixen `enviat()` i `obtenirError()` sense canviar els callers existents.

## 2. Troballes que passen a “implementació preparada”

- UC108-P02-02 · estat de `Tastet`.
- UC108-P02-03 · recurs inexistent/inactiu.
- UC108-P03-01 · render d'`InscripcioTastet` inactiu.
- UC108-P03-02 · resolució auxiliar insegura/no tipificada.
- UC108-P03-03 · codi de tastet desvinculat de la ruta.
- UC108-RACE-01 · cursa asíncrona de `codiCurs`.
- UC108-CONF-01 · retall de token.
- UC108-P04-01 · dependències innecessàries de confirmació.
- UC108-DATA-02 · comentaris sense inicialitzar.
- UC108-LEG-01/02 · CP/promoció residual.
- UC108-SEC-04 · SQL concatenat a `obtenirCodiTastet.php`.
- UC108-HTTP-01 · PII via GET en el flux web actual nou.
- UC108-SEC-03 · integritat del token nou sobre IV+ciphertext.

## 3. Pendent expressament

No s'implementa encara perquè requereix esquema, semàntica d'estats o proves:

1. **Idempotència atòmica / concurrència:** request id, unique key o lock. No inventar la semàntica de `INSC_CURS`.
2. **Estat pendent/actiu/caducat/baixa/denegació:** cal confirmar BD/intranet/Moodle.
3. **Outbox/reintents SMTP:** encara hi ha correus PRE/POST-INSERT; només s'ha exposat el resultat de `send()`.
4. **Resposta JSON tipificada:** el handler continua retornant token textual per compatibilitat.
5. **Caducitat del token:** DEC-108-01 continua oberta.
6. **Retirada del fallback GET:** fer-ho després de verificar que no hi ha clients antics.
7. **Retirada del HMAC legacy:** fer-ho quan els tokens antics ja no s'hagin de consultar.
8. **Mailing A → baixa → tastet B:** decisió oberta.
9. **DEC-108-06:** SIF `NON_BILLABLE/FREE_SAMPLE` vs fora SIF.
10. **Plantilla manual d'accés i 7 dies Moodle:** no acreditats al repositori/runtime.

## 4. Proves a executar abans de merge/desplegament

- TG-108-24/25/26: card, Home i imatges.
- TG-108-45/46/47/48/49/52: confirmació/token.
- TG-108-53…60: SMTP i errors parcials.
- TG-108-61…68: rutes, estat actiu i curs original.
- TG-108-78…84: cursa de codi, manipulació de tastet, prepared statement i desactivació entre render/submit.
- Smoke PHP/JS de P-TAS-01…04.
- Validació manual de retrocompatibilitat d'enllaços de confirmació antics.

## 5. Criteri

Aquests canvis passen de **pendent de codi** a **implementats en branca / pendents de prova**. No marcar-los com a verificats ni desplegats fins executar les proves corresponents.
