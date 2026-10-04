# UC-022 · Política d'identitat del moviment bancari

## Decisió

`external_bank_event_id` és l'identificador únic de l'operació proporcionat per l'entitat bancària en l'extracte, detall del moviment o export bancari.

Mentre no existeixi una importació bancària automàtica, l'operador el copia **tal qual** des de la font bancària.

## No són identificadors vàlids

No es pot fabricar l'identificador a partir de:

- data + import;
- número de factura;
- DNI/CIF;
- nom del pagador;
- concepte o referència escrita pel pagador;
- un comptador intern inventat per l'operador.

Aquests camps poden ajudar a conciliar, però no substitueixen la identitat del moviment.

## Namespace

La identitat efectiva al SIF és:

`SHA-256(UPPER(TRIM(banc)) + "\n" + external_bank_event_id)`

Això permet que dues entitats bancàries diferents puguin emetre el mateix text local sense col·lidir.

## Persistència

- `payment_transaction.PROVIDER_REF`: conserva l'identificador original del banc.
- `payment_transaction.IDEMPOTENCY_KEY`: conserva el hash namespaced.
- auditoria: no necessita publicar l'identificador en clar; es pot conservar el hash.

## Límits

- longitud màxima: 80 caràcters, coherent amb `PROVIDER_REF`;
- TPV/Redsys no usa aquest canal;
- un retry ha d'utilitzar exactament el mateix banc i el mateix identificador.

## Evolució futura

Quan s'implementi importació d'extractes, el camp manual s'ha de substituir per una selecció d'un moviment bancari importat. El contracte SIF no necessita canviar: continuarà rebent `bank + external_bank_event_id`.
