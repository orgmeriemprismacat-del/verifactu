# UC-022 · Configuració requerida a pay-test.prisma.cat

Entorn objectiu: `pay-test.prisma.cat` amb BD SIF de proves `sif_test`.

No s'han d'escriure secrets reals en aquest fitxer. Els valors sensibles han d'existir només a la configuració del servidor.

## Variables SIF

- `SIF_ENV=test`
- `SIF_DB_DSN`: DSN MySQL que apunti a `sif_test`
- `SIF_DB_USER`: usuari tècnic de `sif_test`
- `SIF_DB_PASSWORD`: secret del servidor
- `SIF_INTERNAL_API_KEY_ID`: mateix identificador configurat a `intranet-pre`
- `SIF_INTERNAL_API_SECRET`: mateix secret HMAC configurat a `intranet-pre`
- `SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH=/api/payments/manual-transfer.php`
- `SIF_MANUAL_TRANSFER_ROLES`: mateix contracte de rols autoritzats que a `intranet-pre`

## Connexions de projecció

- `SIF_LEGACY_DB_DSN`: BD web de preproducció amb `factures` i `inscripcions`
- `SIF_LEGACY_DB_USER`
- `SIF_LEGACY_DB_PASSWORD`
- `SIF_LEGACY_INTRANET_DB_DSN`: BD intranet de preproducció amb `entitats` i `entitats_resp`
- `SIF_LEGACY_INTRANET_DB_USER`
- `SIF_LEGACY_INTRANET_DB_PASSWORD`

## Restriccions

- No apuntar mai a BD de producció.
- No reutilitzar secrets de producció.
- El secret HMAC no pot aparèixer al navegador, logs o evidències.
- El path signat ha de coincidir exactament als dos hosts.
- Els rols autoritzats han de coincidir als dos hosts.
