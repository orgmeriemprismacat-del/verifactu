# Codi copiat del Drive per referencia SIF

Aquest directori conte una copia local controlada de fitxers del Drive que cal tenir en compte per implementar el SIF VERI*FACTU.

No es el codi productiu final. Serveix per revisar fluxos existents i adaptar Fase 0/Fase 1 sense tocar directament el Drive.

## Origen

- `G:\Mi unidad\PAY.PRISMA.CAT - CANVIS VERIFACTU`
- `G:\Mi unidad\INTRANET NOVA - CANVIS VERIFACTU`

## Criteri de copia

S'han copiat fitxers de flux, callbacks, Redsys, pagaments i connexio:

- callbacks `realitzaPagament*Automatic.php`;
- classes `Pagament*Automatic.php`;
- `inc/apiRedsys.php`;
- `ajax/efectuarPagament*.php`;
- `Intranet.php` i `ajax/alumnes/efectuarPagament.php`;
- classes `Connexio*.php` sense els fitxers de parametres.

No s'han copiat `parametres-connexio*.php` ni `parametres_connexio.php`, per evitar arrossegar credencials o configuracio sensible al repo pont.

## Us previst

Les fases 0 i 1 del SIF s'han d'implementar dins del projecte actual, tenint en compte aquests fitxers com a referencia historica:

- `realitzaPagament*Automatic.php` mostra els callbacks Redsys i l'us de `Ds_Order`;
- `Intranet.php` mostra el flux antic `efectuarPagament()`, `insertFactura`, `insertFacturaAut` i `updFactGenerada`;
- les classes `Connexio*.php` indiquen la separacio actual entre BD fiscal/pay, web i intranet.

Qualsevol canvi real sobre el Drive o sobre produccio queda fora d'aquest directori i requereix una decisio explicita.
