# DECLARACIÓN RESPONSABLE DEL SISTEMA INFORMÁTICO DE FACTURACIÓN

**Estado documental:** BORRADOR DE LA VERSIÓN CANDIDATA 1.0.0. NO SUSCRIBIR TODAVÍA.

Esta declaración se ha preparado conforme al artículo 13 del Real Decreto 1007/2023 y al artículo 15 de la Orden HAC/1177/2024. Solo podrá suscribirse cuando la versión indicada esté instalada, cerrada y respaldada por las evidencias técnicas y operativas correspondientes.

La entidad productora suscribirá la declaración indicando fecha y lugar. La normativa no exige firma electrónica para este documento. Para el expediente interno de Associació PrisMa se prevé, además, la firma visible de una persona con representación suficiente.

Las fuentes AEAT/BOE se han revisado de nuevo el 16/09/2026 para esta versión candidata. Esta declaración sigue siendo un borrador: no sustituye la validación fiscal externa ni acredita por sí sola que el SIF esté implantado.

## Control previo a la suscripción

Antes de retirar la indicación de borrador deben quedar completados estos puntos:

- versión 1.0.0 cerrada e identificada de forma inequívoca;
- plazo legal aplicable documentado y confirmado para Associació PrisMa;
- componentes realmente instalados contrastados con el apartado 1.d;
- pruebas funcionales, fiscales, de seguridad y de recuperación ejecutadas;
- certificado o representación para la remisión a la AEAT configurado y probado desde el entorno real del proceso SIF;
- XML, hash, idempotencia, cola AEAT, PDF y QR verificados;
- correspondencia de campos mínimos del registro de alta/anulación con tablas internas, XML y evidencias de prueba;
- rol de auditoría/AEAT solo lectura preparado, sin acceso a secretos ni acciones de escritura;
- incidencias bloqueantes cerradas;
- visto bueno técnico emitido;
- persona con representación suficiente confirmada por la entidad;
- declaración accesible dentro del propio SIF.

## 1.a Nombre del sistema informático

SIF PrisMa

## 1.b Código identificador del sistema informático

SIF-PRISMA

## 1.c Identificador completo de la versión concreta

1.0.0 - versión candidata pendiente de cierre y verificación final.

## 1.d Componentes y funcionalidades del sistema

La descripción siguiente corresponde a la configuración candidata. Antes de la suscripción deberá comprobarse que coincide con la instalación efectiva de la versión 1.0.0.

El SIF PrisMa es una aplicación web centralizada para la facturación propia de Associació PrisMa. Su configuración candidata comprende:

1. aplicación backend de facturación desarrollada en PHP;
2. API interna para los canales ecommerce, Redsys TPV e intranet;
3. base de datos fiscal MySQL con tablas transaccionales InnoDB;
4. módulos de facturas, líneas, numeración fiscal y registros de facturación;
5. encadenamiento mediante huella o hash e idempotencia;
6. gestión de pagos, asignaciones, devoluciones, compensaciones y rectificativas;
7. generación y conservación de documentos PDF, XML y código QR;
8. cola de remisión VERI*FACTU, reintentos, respuestas e incidencias;
9. permisos, registros operativos y trazabilidad documental;
10. integración con los flujos internos y con las notificaciones de Redsys.

Los canales de venta y gestión solicitan la operación al SIF. El SIF valida la petición, impide duplicidades, asigna la numeración fiscal, genera la factura y el registro de facturación, calcula la huella, conserva la información y gestiona la remisión a la AEAT.

## 1.e Funcionamiento exclusivo como VERI FACTU

S - Sí.

La versión 1.0.0 se configura para funcionar exclusivamente como sistema VERI*FACTU desde su puesta en producción, con remisión de los registros de facturación conforme a las especificaciones aplicables.

## 1.f Uso por varios obligados tributarios

N - No.

El sistema está destinado a la facturación de un único obligado tributario:

- Razón social: Associació PrisMa
- NIF: G17881988

## 1.g Tipos de firma de los registros cuando no se utiliza como VERI FACTU

No procede, porque la versión declarada se configura para funcionar exclusivamente como VERI*FACTU. La autenticación de la remisión se realizará mediante un certificado electrónico cualificado admitido por la AEAT, ya sea de la entidad o de un tercero con representación, apoderamiento o habilitación suficiente.

## 1.h Razón social de la entidad productora

Associació PrisMa

El sistema se desarrolla internamente para uso propio de la entidad. Meriem Abjil Bajja ejerce la responsabilidad técnica, funcional y documental del proyecto, sin adquirir por ello la condición de productora externa a título personal.

## 1.i Número de identificación fiscal de la entidad productora

G17881988

## 1.j Dirección postal completa de contacto de la entidad productora

c. Sant Hipòlit, 16, bajos 2.ª
17003 Girona
Girona
España

## 1.k Manifestación de cumplimiento

La entidad productora del sistema informático identificado en esta declaración responsable hace constar que el sistema SIF PrisMa, en la versión indicada, cumple con lo dispuesto en:

- el artículo 29.2.j) de la Ley 58/2003, de 17 de diciembre, General Tributaria;
- el Reglamento aprobado por el Real Decreto 1007/2023, de 5 de diciembre;
- la Orden HAC/1177/2024, de 17 de octubre;
- las especificaciones técnicas, funcionales y de contenido publicadas en la sede electrónica de la Agencia Estatal de Administración Tributaria que completen las anteriores.

**Esta manifestación no debe suscribirse mientras el documento conserve el estado de borrador o falte alguna comprobación bloqueante.**

## 1.l Fecha y lugar de suscripción

Fecha: ____ de ____________________ de ______

Lugar: Girona, España

## Formalización interna de la suscripción

Este bloque se incorpora al expediente de Associació PrisMa para identificar a la persona que actúa en nombre de la entidad. No sustituye los datos obligatorios de los apartados 1.a a 1.l.

Matriz interna previa:

| Elemento | Criterio de esta versión candidata | Pendiente antes de firmar |
| --- | --- | --- |
| Entidad productora/titular interna | Associació PrisMa | Confirmar que se mantiene desarrollo interno para uso propio. |
| Obligado tributario usuario | Associació PrisMa | Confirmar alcance fiscal aplicable, SII/no SII y territorio común. |
| Responsable técnica/documental | Meriem Abjil Bajja | Emitir visto bueno técnico de la versión instalada. |
| Firmante formal | Adam Carmona, o representante formal que confirme la entidad | Confirmar NIF, cargo y facultades suficientes. |
| Certificado/apoderamiento AEAT | Certificado de entidad o representación admitida por AEAT | Probar desde el servidor o worker real y archivar evidencia no secreta. |

Por Associació PrisMa

Nombre y apellidos: Adam Carmona
Cargo: Director
Facultades de representación suficientes: pendiente de confirmación por la entidad

Firma:

____________________________________

## Anexo técnico previsto

La versión definitiva podrá incorporar como anexo el visto bueno técnico de la versión 1.0.0 y una referencia al expediente de pruebas. El anexo técnico no sustituye la declaración de la entidad productora.

## Referencias normativas

- Real Decreto 1007/2023, artículo 13.
- Orden HAC/1177/2024, artículo 15.
- Preguntas frecuentes de la AEAT sobre certificación de los sistemas informáticos y sistemas VERI*FACTU, actualizadas a 21 de julio de 2026.
- Nota informativa de la AEAT sobre ampliación de plazos SIF y Real Decreto-ley 15/2025.
- Ejemplos de declaraciones responsables de SIF publicados por la AEAT, versión 0.5.1.
