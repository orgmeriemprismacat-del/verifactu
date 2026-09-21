# Permisos de persistència funcional i auditoria

`functional-audit-roles.sql` és una plantilla administrativa per a MySQL 8. No
s'executa des de `run-migrations.php`: l'ha d'aplicar una persona administradora
després de seleccionar la base SIF i abans d'assignar els rols als comptes reals.

Regles:

- els comptes i secrets no es guarden al repositori;
- les taules append-only no concedeixen `UPDATE` ni `DELETE` als rols d'aplicació;
- `sif_auditor_readonly` només rep `SELECT`;
- la base ha de revocar qualsevol privilegi heretat més ampli abans d'assignar els
  rols;
- la prova de permisos ha d'intentar `INSERT`, `UPDATE` i `DELETE` amb cada rol i
  conservar el resultat com a evidència de go/no-go.
