# DECLARACION RESPONSABLE DEL SISTEMA INFORMATICO DE FACTURACION

> Borrador de futura declaracio signable. No es signable encara: cal completar les dades identificatives, versio exacta, components definitius i data/lloc de signatura abans de signar. Aquest document s'ha preparat seguint l'estructura de l'article 15 de l'Orden HAC/1177/2024.

## Nota previa - BORRADOR NO FIRMABLE TODAVIA

Este documento es una plantilla viva de trabajo. No debe firmarse como declaracion definitiva hasta que exista una version concreta, instalada, verificable y preparada para produccion del SIF PrisMa.

Criterio interno acordado:

```text
0.1-BORRADOR = documento de trabajo
1.0.0 = primera version productiva firmable
```

No es necesario firmar cada cambio durante el desarrollo. Si despues de una version firmada hay cambios relevantes que afecten al cumplimiento, a los componentes de facturacion, a la modalidad de funcionamiento o a los registros conservados, se preparara una nueva version de declaracion o un anexo versionado.

## 1.a) Nombre del sistema informatico a que se refiere esta declaracion responsable

```text
SIF PrisMa
```

## 1.b) Codigo identificador del sistema informatico

```text
SIF-PRISMA
```

## 1.c) Identificador completo de la version concreta del sistema informatico

```text
Version: 0.1-BORRADOR
Fecha de version: [INDICAR FECHA]
Entorno: Produccion
Dominio/subdominio previsto: pay.prisma.cat
```

Nota: esta declaracion no debera firmarse como version definitiva hasta que exista una version concreta, instalada y verificable del SIF. La primera version firmable se preve como `1.0.0`, correspondiente a la primera version productiva del SIF centralizado.

## 1.d) Componentes, hardware y software, de que consta el sistema informatico, breve descripcion y principales funcionalidades

El sistema informatico de facturacion SIF PrisMa esta compuesto, de forma resumida, por los siguientes componentes:

```text
1. Aplicacion backend de facturacion desarrollada en PHP.
2. API interna de facturacion para ecommerce, Redsys/TPV e intranet.
3. Base de datos MySQL/InnoDB de datos fiscales.
4. Modulo de generacion de facturas, lineas, numeracion fiscal y registros fiscales.
5. Modulo de cadena hash global de registros de facturacion.
6. Modulo de idempotencia para evitar duplicidades.
7. Modulo de cola de envio AEAT / VERI*FACTU.
8. Modulo de gestion de documentos PDF/XML/QR.
9. Modulo de pagos, asignacion de pagos, devoluciones y compensaciones.
10. Modulo de rectificativas.
11. Modulo de logs, incidencias y notificaciones internas.
12. Integracion con Redsys y procesos internos de transferencia/compensacion.
```

Descripcion funcional:

```text
El SIF PrisMa centraliza la emision de facturas de PrisMa. Los canales de venta y gestion no asignan numero fiscal ni crean facturas finales, sino que remiten una solicitud al SIF. El SIF valida la peticion, aplica idempotencia, asigna numero fiscal, genera la factura y sus lineas, crea el registro fiscal, encadena la huella/hash, conserva la informacion, genera o prepara el documento PDF/QR y registra el envio VERI*FACTU a la AEAT.
```

Principales funcionalidades:

```text
- Emision de facturas ordinarias.
- Emision de facturas rectificativas.
- Gestion de facturas antes del cobro.
- Gestion de cobros, pagos parciales, devoluciones y compensaciones.
- Gestion de packs, grupos, cursos, regalos y facturas manuales.
- Conservacion de snapshot fiscal del receptor.
- Generacion de registros fiscales.
- Encadenamiento hash de registros.
- Cola de envio a AEAT.
- Gestion de errores, reintentos e incidencias.
- Generacion y conservacion de PDF/XML/QR.
- Consulta de facturas desde intranet con control de permisos.
```

## 1.e) Indicacion de si el sistema se ha producido para funcionar exclusivamente como VERI*FACTU

```text
El sistema SIF PrisMa se produce y configura para operar en modalidad VERI*FACTU para las facturas emitidas a partir de su puesta en produccion, con remision de los registros de facturacion generados conforme a las especificaciones aplicables.
```

## 1.f) Indicacion de si permite ser usado por varios obligados tributarios

```text
El sistema SIF PrisMa esta previsto para dar soporte a la facturacion de un unico obligado tributario:

Razon social: Associacio PrisMa
NIF/CIF: G17881988

No esta previsto como producto multiempresa para terceros ni para varios obligados tributarios, salvo adaptacion futura documentada y certificada.
```

## 1.g) Tipos de firma utilizados para firmar registros de facturacion y de evento si el sistema no es VERI*FACTU

```text
No aplica en la configuracion prevista, al tratarse de un sistema producido para operar en modalidad VERI*FACTU.
```

## 1.h) Nombre y apellidos de la persona o razon social de la entidad productora del sistema informatico

```text
Entidad productora/titular interna del sistema informatico:
Associacio PrisMa

Desarrollo interno y responsable tecnica del proyecto:
Meriem Abjil Bajja
```

Nota: el sistema se documenta como desarrollo interno para uso propio de Associacio PrisMa. La mencion de Meriem Abjil Bajja identifica la direccion tecnica, funcional, documental y de desarrollo del proyecto, pero no implica por defecto que actue como productora externa persona fisica, salvo decision formal futura.

## 1.i) Numero de identificacion fiscal de la persona o entidad productora

```text
NIF/CIF de la entidad productora/titular: G17881988

Dato interno de responsable tecnica, si se conserva como contacto tecnico:
Meriem Abjil Bajja - NIF 77922662L
```

## 1.j) Direccion postal completa de contacto de la persona o entidad productora

```text
Direccion de la entidad:
c. Sant Hipolit, 16, bxs. 2a
17003 Girona
Girona
Espana

Direccion de contacto tecnico de la responsable del proyecto, si se decide mantenerla en el expediente:
C/ Pont, 29
17486 Castello d'Empuries
Girona
Espana
```

## 1.k) Manifestacion de cumplimiento

La persona o entidad productora del sistema informatico identificado en esta declaracion responsable manifiesta que el sistema informatico SIF PrisMa, en la version indicada, cumple con lo dispuesto en:

```text
- el articulo 29.2.j) de la Ley 58/2003, de 17 de diciembre, General Tributaria;
- el Reglamento que establece los requisitos que deben adoptar los sistemas y programas informaticos o electronicos que soporten los procesos de facturacion de empresarios y profesionales, aprobado por el Real Decreto 1007/2023, de 5 de diciembre;
- la Orden HAC/1177/2024, de 17 de octubre;
- las especificaciones tecnicas, funcionales y de contenido publicadas en la sede electronica de la Agencia Estatal de Administracion Tributaria que completen o desarrollen las anteriores.
```

## 1.l) Fecha y lugar de firma de la declaracion responsable

```text
Lugar: [INDICAR LUGAR]
Fecha: [INDICAR FECHA]
```

## 1.m) Datos identificativos y firma de la persona que suscribe la declaracion responsable

Responsable tecnica / gestora del proyecto:

```text
Nombre y apellidos: Meriem Abjil Bajja
NIF: 77922662L
Cargo/funcion: Responsable tecnica y funcional del proyecto SIF PrisMa
Firma:


____________________________________
```

Responsable legal / direccion:

```text
Nombre y apellidos: Adam Carmona
NIF: [INDICAR]
Cargo/funcion: Director / responsable legal
Firma:


____________________________________
```

## 1.n) Otra informacion adicional considerada de interes

```text
El SIF PrisMa ha sido concebido como un sistema centralizado de facturacion para evitar la generacion descentralizada de facturas desde distintos canales. El sistema incorpora idempotencia, separacion entre factura y pago, gestion de rectificativas, registro de pagos, cola de envio AEAT, gestion de incidencias, conservacion documental y control de acceso a facturas.

El sistema se desarrolla internamente para uso propio de Associacio PrisMa. La responsable tecnica, funcional, documental y de desarrollo del proyecto es Meriem Abjil Bajja, que define la arquitectura, los flujos funcionales, el modelo de datos, las integraciones, los criterios de activacion y la puesta en operativa del SIF. La titularidad, uso y responsabilidad organizativa del sistema corresponden a Associacio PrisMa, representada por su direccion.

La documentacion tecnica y funcional del sistema se conserva en documento separado bajo el titulo "Documentacio 2 - Funcionament del SIF PrisMa per compliment AEAT / VERI*FACTU".
```

## Anexo - Pendientes antes de preparar la version firmable

Antes de convertir este borrador en declaracion responsable definitiva de la version `1.0.0`, falta completar o confirmar:

```text
- Identificador exacto de version y fecha de version.
- Fecha y lugar de firma.
- NIF y cargo exacto de Adam Carmona o de la persona que firme por direccion.
- Componentes definitivos del SIF en produccion.
- Dominio/subdominio y SSL definitivamente configurados.
- Certificado digital de la entidad o apoderamiento usado para AEAT.
- Endpoints, WSDL/servicios AEAT y configuracion tecnica final.
- Generacion y conservacion de PDF/XML/QR.
- Pruebas principales ejecutadas y evidencias conservadas.
- Declaracion responsable accesible dentro del propio SIF.
- Revision de puntos fiscales sensibles si se dispone de asesoria externa.
```

## Anexo - Referencias normativas usadas para preparar este borrador

- AEAT - Certificacion de los sistemas informaticos: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/certificacion-sistemas-informaticos_.html
- BOE - Orden HAC/1177/2024, articulo 15: https://www.boe.es/buscar/act.php?id=BOE-A-2024-22138
- AEAT - Ejemplos de declaraciones responsables: https://sede.agenciatributaria.gob.es/static_files/Sede/Tema/IVA/Verifactu/EjemplosDeclaracionResponsable%28V0.5.1%29.pdf
- AEAT - Nota informativa de plazos de adaptacion SIF: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/nota-informativa-ampliacion-plazo-adaptacion-facturacion.html
- AEAT - FAQ sistemas VERI*FACTU y modelo 036: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html
