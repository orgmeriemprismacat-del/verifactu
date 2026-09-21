# Fitxes funcionals del SIF

Catàleg navegable generat des de `33-casos-us-sif.md`. Cada fitxa té 21 apartats, però és un **esborrany estructurat**, no una anàlisi funcional completa. Cal revisar descripcions/checklists Trello, evidència de codi, dades reals, decisions fiscals, permisos i proves específiques abans de marcar cap fitxa com a preparada per programar o completa.

| ID | Cas | Domini | Estat documental | Fitxa |
| --- | --- | --- | --- | --- |
| UC-01 | Emetre o reutilitzar factura | facturació i registre fiscal | `[BASE]` | [uc-001.md](./uc-001.md) |
| UC-02 | Registrar pagament sobre factura | pagaments i conciliació | `[BASE]` | [uc-002.md](./uc-002.md) |
| UC-03 | Processar cobrament Redsys asíncron | pagaments i conciliació | `[ASYNC]` | [uc-003.md](./uc-003.md) |
| UC-04 | Emetre factura abans de cobrar | facturació i registre fiscal | `[PARCIAL]` | [uc-004.md](./uc-004.md) |
| UC-05 | Crear rectificativa | facturació i registre fiscal | `[PARCIAL]` | [uc-005.md](./uc-005.md) |
| UC-06 | Registrar devolució, saldo o compensació | pagaments i conciliació | `[PARCIAL]` | [uc-006.md](./uc-006.md) |
| UC-07 | Consultar factura, estat i document | documents, accés i comunicacions | `[DISSENY]` | [uc-007.md](./uc-007.md) |
| UC-08 | Gestionar incidència | governança i operació | `[PARCIAL]` | [uc-008.md](./uc-008.md) |
| UC-09 | Remetre registre a AEAT | governança i operació | `[DISSENY]` | [uc-009.md](./uc-009.md) |
| UC-10 | Gestionar configuració i versió | governança i operació | `[DISSENY]` | [uc-010.md](./uc-010.md) |
| UC-11 | Importar factura històrica | facturació i registre fiscal | `[BASE]` | [uc-011.md](./uc-011.md) |
| UC-12 | Gestionar el cicle de morositat i reclamació | integració SIF | `[PARCIAL]` | [uc-012.md](./uc-012.md) |
| UC-13 | Orquestrar la doble facturació USOC | facturació i registre fiscal | `[PARCIAL]` | [uc-013.md](./uc-013.md) |
| UC-14 | Comprar curs normal per Redsys | facturació i registre fiscal | `[ASYNC/PARCIAL]` | [uc-014.md](./uc-014.md) |
| UC-14a | Comprar taller | facturació i registre fiscal | `[PARCIAL]` | [uc-014a.md](./uc-014a.md) |
| UC-14b | Comprar jornada | facturació i registre fiscal | `[PARCIAL]` | [uc-014b.md](./uc-014b.md) |
| UC-15 | Comprar pack | facturació i registre fiscal | `[BASE/ASYNC/PARCIAL]` | [uc-015.md](./uc-015.md) |
| UC-16 | Facturar grup | facturació i registre fiscal | `[BASE/ASYNC/PARCIAL]` | [uc-016.md](./uc-016.md) |
| UC-16a | Afegir participant després d'emetre | facturació i registre fiscal | `[DISSENY]` | [uc-016a.md](./uc-016a.md) |
| UC-16b | Treure participant després d'emetre | facturació i registre fiscal | `[DISSENY]` | [uc-016b.md](./uc-016b.md) |
| UC-17 | Comprar regal | facturació i registre fiscal | `[BASE/ASYNC/PARCIAL]` | [uc-017.md](./uc-017.md) |
| UC-18 | Bescanviar regal | venda i descomptes | `[DISSENY]` | [uc-018.md](./uc-018.md) |
| UC-18a | Gestionar regal caducat o duplicat | venda i descomptes | `[DISSENY]` | [uc-018a.md](./uc-018a.md) |
| UC-19 | Validar afiliació USOC | facturació i registre fiscal | `[DISSENY]` | [uc-019.md](./uc-019.md) |
| UC-19a | Facturar part de l'alumne USOC | facturació i registre fiscal | `[BASE/ASYNC/PARCIAL]` | [uc-019a.md](./uc-019a.md) |
| UC-19b | Facturar diferència a USOC | facturació i registre fiscal | `[BASE/PARCIAL]` | [uc-019b.md](./uc-019b.md) |
| UC-20 | Aplicar Alumne PrisMa | venda i descomptes | `[PARCIAL]` | [uc-020.md](./uc-020.md) |
| UC-20a | Validar Carnet Jove | venda i descomptes | `[DISSENY]` | [uc-020a.md](./uc-020a.md) |
| UC-20b | Aplicar descompte sensible | venda i descomptes | `[DISSENY]` | [uc-020b.md](./uc-020b.md) |
| UC-20c | Aplicar promoció temporal | venda i descomptes | `[PARCIAL]` | [uc-020c.md](./uc-020c.md) |
| UC-20d | Aplicar codi promocional | venda i descomptes | `[PARCIAL]` | [uc-020d.md](./uc-020d.md) |
| UC-21 | Empresa/responsable paga inscripcions | facturació i registre fiscal | `[PARCIAL]` | [uc-021.md](./uc-021.md) |
| UC-22 | Registrar transferència | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-022.md](./uc-022.md) |
| UC-23 | Registrar fracció | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-023.md](./uc-023.md) |
| UC-24 | Registrar cobrament de reclamació | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-024.md](./uc-024.md) |
| UC-25 | Analitzar fitxer TPV | pagaments i conciliació | `[DISSENY]` | [uc-025.md](./uc-025.md) |
| UC-25a | Comprovar IDPAG duplicats | pagaments i conciliació | `[DISSENY]` | [uc-025a.md](./uc-025a.md) |
| UC-26 | Canviar de curs | canvis posteriors | `[DISSENY/PARCIAL]` | [uc-026.md](./uc-026.md) |
| UC-27 | Donar de baixa | canvis posteriors | `[DISSENY/PARCIAL]` | [uc-027.md](./uc-027.md) |
| UC-28 | Registrar devolució | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-028.md](./uc-028.md) |
| UC-29 | Crear saldo | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-029.md](./uc-029.md) |
| UC-29a | Aplicar compensació | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-029a.md](./uc-029a.md) |
| UC-30 | Anul·lar registre improcedent | facturació i registre fiscal | `[DISSENY]` | [uc-030.md](./uc-030.md) |
| UC-31 | Subsanar registre | facturació i registre fiscal | `[DISSENY]` | [uc-031.md](./uc-031.md) |
| UC-32 | Marcar o desmarcar factura electrònica | facturació i registre fiscal | `[DISSENY]` | [uc-032.md](./uc-032.md) |
| UC-33 | Desactivar URL de pagament | pagaments i conciliació | `[DISSENY]` | [uc-033.md](./uc-033.md) |
| UC-34 | Consultar dashboard | governança i operació | `[DISSENY]` | [uc-034.md](./uc-034.md) |
| UC-35 | Consultar registre, cadena i estat AEAT | governança i operació | `[DISSENY]` | [uc-035.md](./uc-035.md) |
| UC-36 | Generar/consultar PDF, QR o XML | documents, accés i comunicacions | `[PARCIAL]` | [uc-036.md](./uc-036.md) |
| UC-37 | Exportar període fiscal | governança i operació | `[DISSENY]` | [uc-037.md](./uc-037.md) |
| UC-38 | Configurar SIF i certificat | governança i operació | `[DISSENY]` | [uc-038.md](./uc-038.md) |
| UC-39 | Executar proves i go/no-go | governança i operació | `[BASE/PARCIAL]` | [uc-039.md](./uc-039.md) |
| UC-40 | Fer backup i restauració | governança i operació | `[DISSENY]` | [uc-040.md](./uc-040.md) |
| UC-41 | Crear o editar entitat/responsable | facturació i registre fiscal | `[DISSENY]` | [uc-041.md](./uc-041.md) |
| UC-42 | Consultar/modificar alumne | gestió operativa | `[DISSENY]` | [uc-042.md](./uc-042.md) |
| UC-43 | Gestionar notificacions i recordatoris | gestió operativa | `[DISSENY]` | [uc-043.md](./uc-043.md) |
| UC-44 | Consultar i mantenir `fact_rels` i origen legacy | governança i operació | `[BASE/PARCIAL]` | [uc-044.md](./uc-044.md) |
| UC-45 | Activar auditor temporal | governança i operació | `[DISSENY]` | [uc-045.md](./uc-045.md) |
| UC-46 | Activar versió i declaració responsable | governança i operació | `[DISSENY]` | [uc-046.md](./uc-046.md) |
| UC-47 | Sincronitzar l'estat mínim cap al llegat després del commit SIF | pagaments i conciliació | `[BASE/PARCIAL]` | [uc-047.md](./uc-047.md) |
| UC-48 | Crear o consultar una proforma no fiscal | documents, accés i comunicacions | `[LEGACY/DISSENY]` | [uc-048.md](./uc-048.md) |
| UC-49 | Enviar factura, document o avis per correu | documents, accés i comunicacions | `[LEGACY/DISSENY]` | [uc-049.md](./uc-049.md) |
| UC-50 | Crear, consultar, desactivar o caducar un enllaç de pagament | pagaments i conciliació | `[DISSENY]` | [uc-050.md](./uc-050.md) |
| UC-51 | Tractar callback Redsys denegat, tardà, duplicat o contradictori | pagaments i conciliació | `[ASYNC]` | [uc-051.md](./uc-051.md) |
| UC-52 | Operar la cua Redsys | pagaments i conciliació | `[ASYNC/PARCIAL]` | [uc-052.md](./uc-052.md) |
| UC-53 | Detectar i resoldre divergències SIF-llegat | governança i operació | `[DISSENY]` | [uc-053.md](./uc-053.md) |
| UC-54 | Operar la cua fiscal i tractar la resposta AEAT | governança i operació | `[DISSENY]` | [uc-054.md](./uc-054.md) |
| UC-55 | Generar, reintentar i custodiar documents fiscals | documents, accés i comunicacions | `[PARCIAL/DISSENY]` | [uc-055.md](./uc-055.md) |
| UC-56 | Cercar i assignar un cobrament | pagaments i conciliació | `[PARCIAL]` | [uc-056.md](./uc-056.md) |
| UC-57 | Mantenir i optimitzar la BD SIF | governança i operació | `[DISSENY]` | [uc-057.md](./uc-057.md) |
| UC-58 | Gestionar l'outbox de notificacions | governança i operació | `[DISSENY]` | [uc-058.md](./uc-058.md) |
| UC-59 | Concedir, caducar i revocar accés auditor | governança i operació | `[DISSENY]` | [uc-059.md](./uc-059.md) |
| UC-60 | Monitorar salut, cues, documents, backups i versió activa | governança i operació | `[DISSENY]` | [uc-060.md](./uc-060.md) |
| UC-61 | Consultar un import pendent i obtenir un enllaç de pagament | documents, accés i comunicacions | `[LEGACY/OBJECTIU]` | [uc-061.md](./uc-061.md) |
| UC-62 | Iniciar factura o cobrament des de la intranet | pagaments i conciliació | `[LEGACY/DISSENY]` | [uc-062.md](./uc-062.md) |
| UC-63 | Crear la intenció Redsys des de l'ecommerce | pagaments i conciliació | `[ASYNC/PARCIAL]` | [uc-063.md](./uc-063.md) |
| UC-64 | Reconciliar la candidata amb el codi actual | governança i operació | `[CONTROL/PENDENT]` | [uc-064.md](./uc-064.md) |
| UC-65 | Presentar una factura o rebut de col·laborador | circuit adjacent de col·laboradors | `[LEGACY/ADJACENT]` | [uc-065.md](./uc-065.md) |
| UC-66 | Consultar i gestionar cobraments d'un col·laborador | circuit adjacent de col·laboradors | `[LEGACY/ADJACENT]` | [uc-066.md](./uc-066.md) |
| UC-67 | Externalitzar i rotar secrets de pagament | governança i operació | `[PENDENT/BLOQUEJANT]` | [uc-067.md](./uc-067.md) |
| UC-68 | Retirar callbacks i escriptures fiscals llegades | pagaments i conciliació | `[DISSENY]` | [uc-068.md](./uc-068.md) |
| UC-69 | Confirmar i congelar dades fiscals | facturació i registre fiscal | `[DISSENY/PARCIAL]` | [uc-069.md](./uc-069.md) |
| UC-70 | Modificar dades mestres després d'emetre | facturació i registre fiscal | `[DISSENY]` | [uc-070.md](./uc-070.md) |
| UC-71 | Registrar un canvi de curs complet | facturació i registre fiscal | `[DISSENY/PARCIAL]` | [uc-071.md](./uc-071.md) |
| UC-72 | Registrar baixa i decisió econòmica | pagaments i conciliació | `[DISSENY/PARCIAL]` | [uc-072.md](./uc-072.md) |
| UC-73 | Documentar un ajust, descompte o despesa | facturació i registre fiscal | `[DISSENY]` | [uc-073.md](./uc-073.md) |
| UC-74 | Classificar una correcció fiscal | facturació i registre fiscal | `[DISSENY/BLOQUEJANT]` | [uc-074.md](./uc-074.md) |
| UC-75 | Crear un registre d'anul·lació | facturació i registre fiscal | `[DISSENY/BLOQUEJANT]` | [uc-075.md](./uc-075.md) |
| UC-76 | Crear un registre de subsanació | facturació i registre fiscal | `[DISSENY/BLOQUEJANT]` | [uc-076.md](./uc-076.md) |
| UC-77 | Operar enviament AEAT, retry i dead-letter | governança i operació | `[DISSENY/BLOQUEJANT]` | [uc-077.md](./uc-077.md) |
| UC-78 | Generar i custodiar PDF, QR i XML | documents, accés i comunicacions | `[PARCIAL/DISSENY]` | [uc-078.md](./uc-078.md) |
| UC-79 | Enviar una comunicació fiscal auditable | documents, accés i comunicacions | `[DISSENY]` | [uc-079.md](./uc-079.md) |
| UC-80 | Servir i registrar accés a document fiscal | documents, accés i comunicacions | `[DISSENY]` | [uc-080.md](./uc-080.md) |
| UC-81 | Gestionar el cicle complet d'una incidència | governança i operació | `[PARCIAL/DISSENY]` | [uc-081.md](./uc-081.md) |
| UC-82 | Reconciliar SIF amb la BD llegada | governança i operació | `[DISSENY]` | [uc-082.md](./uc-082.md) |
| UC-83 | Registrar i activar versió i declaració | governança i operació | `[DISSENY/BLOQUEJANT]` | [uc-083.md](./uc-083.md) |
| UC-84 | Crear un paquet fiscal d'auditoria | governança i operació | `[DISSENY]` | [uc-084.md](./uc-084.md) |
| UC-85 | Executar backup, restauració i reconciliació | governança i operació | `[DISSENY/BLOQUEJANT]` | [uc-085.md](./uc-085.md) |
| UC-86 | Registrar qualsevol acció sobre un pagament | pagaments i conciliació | [DISSENY/BLOQUEJANT] | [uc-086.md](./uc-086.md) |
| UC-87 | Validar receptor estranger o amb dades fiscals incompletes | facturació i registre fiscal | `[DISSENY/BLOQUEJANT]` | [uc-087.md](./uc-087.md) |
| UC-88 | Decidir agrupació i línies d'una factura multiconcepte | facturació i registre fiscal | `[DISSENY/PARCIAL]` | [uc-088.md](./uc-088.md) |
| UC-89 | Canviar concepte després del cobrament o emissió | facturació i registre fiscal | `[DISSENY/BLOQUEJANT]` | [uc-089.md](./uc-089.md) |
| UC-90 | Resoldre un descompte validat després de la compra | pagaments i conciliació | `[DISSENY/BLOQUEJANT]` | [uc-090.md](./uc-090.md) |
| UC-91 | Aplicar descompte de grup per trams | facturació i registre fiscal | `[DISSENY/PARCIAL]` | [uc-091.md](./uc-091.md) |
| UC-92 | Registrar venda manual des d'intranet o telèfon | pagaments i conciliació | `[DISSENY/PARCIAL]` | [uc-092.md](./uc-092.md) |
| UC-93 | Canviar el receptor fiscal sol·licitat després d'una compra particular | facturació i registre fiscal | `[DISSENY/BLOQUEJANT]` | [uc-093.md](./uc-093.md) |
| UC-94 | Ajustar manualment l'import a pagar amb justificació | pagaments i conciliació | `[DISSENY/BLOQUEJANT]` | [uc-094.md](./uc-094.md) |
| UC-95 | Gestionar estat acadèmic amb deute pendent | pagaments i conciliació | `[DISSENY]` | [uc-095.md](./uc-095.md) |
| UC-96 | Concedir una pròrroga de pagament fins a la segona setmana | pagaments i conciliació | `[DISSENY]` | [uc-096.md](./uc-096.md) |
| UC-97 | Consultar històric barrejat Associació/SL | governança i operació | `[DISSENY/BLOQUEJANT]` | [uc-097.md](./uc-097.md) |
| UC-98 | Classificar el circuit fiscal de botiga de llibres/SL | governança i operació | `[PENDENT/BLOQUEJANT]` | [uc-098.md](./uc-098.md) |
| UC-99 | Limitar la intranet de tutors a un circuit no fiscal | documents, accés i comunicacions | `[DISSENY/PENDENT]` | [uc-099.md](./uc-099.md) |
| UC-100 | Registrar una operació informativa o no facturable | casuística recuperada | `[DISSENY]` | [uc-100.md](./uc-100.md) |
| UC-101 | Operar domini, TLS i separació d'entorns de `pay.prisma.cat` | governança i operació | `[PARCIAL/BLOQUEJANT]` | [uc-101.md](./uc-101.md) |
| UC-102 | Autoritzar l'accés de l'alumne sense rol d'intranet | documents, accés i comunicacions | `[DISSENY/BLOQUEJANT]` | [uc-102.md](./uc-102.md) |
| UC-103 | Delegar anul·lació o canvi de pagament web al SIF | pagaments i conciliació | `[DISSENY/BLOQUEJANT]` | [uc-103.md](./uc-103.md) |
| UC-104 | Gestionar un excés de cobrament | pagaments i conciliació | `[DISSENY/BLOQUEJANT]` | [uc-104.md](./uc-104.md) |
| UC-105 | Reassignar o repartir un pagament | pagaments i conciliació | `[DISSENY/BLOQUEJANT]` | [uc-105.md](./uc-105.md) |
| UC-106 | Crear una reserva o inscripció abans del pagament | operació comercial i inscripció | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-106.md](./uc-106.md) |
| UC-107 | Detectar una inscripció duplicada i evitar efectes econòmics dobles | operació comercial i inscripció | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-107.md](./uc-107.md) |
| UC-108 | Registrar un tastet o repte gratuït com a operació no facturable | operació comercial i inscripció | `[LEGACY/DISSENY]` | [uc-108.md](./uc-108.md) |
| UC-109 | Registrar una inscripció a curs subvencionat sense cobrament individual | operació comercial i inscripció | `[LEGACY/PENDENT/BLOQUEJANT]` | [uc-109.md](./uc-109.md) |
| UC-110 | Gestionar el descompte d'amics amb dues inscripcions i un pagador | operació comercial i inscripció | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-110.md](./uc-110.md) |
| UC-111 | Validar docent novell i generar un dret de descompte futur | operació comercial i inscripció | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-111.md](./uc-111.md) |
| UC-112 | Congelar preu, descompte, places i classificació fiscal abans del TPV | operació comercial i inscripció | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-112.md](./uc-112.md) |
| UC-113 | Importar o crear inscripcions manualment o en lot sense inventar cobrament | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-113.md](./uc-113.md) |
| UC-114 | Versionar canvis de producte o edició amb operacions obertes | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-114.md](./uc-114.md) |
| UC-115 | Reservar i alliberar places amb aforament, caducitat i concurrència | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-115.md](./uc-115.md) |
| UC-116 | Custodiar i revisar evidències sensibles de descompte | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-116.md](./uc-116.md) |
| UC-117 | Gestionar el cicle de vida d'un codi promocional o dret futur | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-117.md](./uc-117.md) |
| UC-118 | Gestionar un grup abans d'emetre o cobrar | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-118.md](./uc-118.md) |
| UC-119 | Gestionar el cicle complet d'un regal o codi de bescanvi | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-119.md](./uc-119.md) |
| UC-120 | Tramitar una sol·licitud de canvi de dades personals i la seva propagació | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-120.md](./uc-120.md) |
| UC-121 | Repreuar o renovar una reserva caducada abans del pagament | cicle de vida comercial, acadèmic i documental | `[DISSENY/BLOQUEJANT]` | [uc-121.md](./uc-121.md) |
| UC-122 | Gestionar la composició d'un pack i la indisponibilitat d'un component | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-122.md](./uc-122.md) |
| UC-123 | Generar i lliurar una factura electrònica en format i canal acordats | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-123.md](./uc-123.md) |
| UC-124 | Reconciliar accés acadèmic i certificat amb baixa, deute i pagador de grup | cicle de vida comercial, acadèmic i documental | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-124.md](./uc-124.md) |
| UC-125 | Gestionar consentiment de comunicacions separat de la inscripció | consentiment, identitat i coherència entre sistemes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-125.md](./uc-125.md) |
| UC-126 | Resoldre identitat i dades de contacte en conflicte entre sistemes | consentiment, identitat i coherència entre sistemes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-126.md](./uc-126.md) |
| UC-127 | Canviar l'estat d'una edició i resoldre totes les operacions afectades | consentiment, identitat i coherència entre sistemes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-127.md](./uc-127.md) |
| UC-128 | Validar i normalitzar adreça, codi postal i població abans de congelar dades fiscals | consentiment, identitat i coherència entre sistemes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-128.md](./uc-128.md) |
| UC-129 | Reconciliar inscripcions, usuaris, cursos i matrícules entre Prisma i Moodle | consentiment, identitat i coherència entre sistemes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-129.md](./uc-129.md) |

## Validació

```powershell
pwsh -File sif/tools/functional-card/generate-catalog.ps1 -ValidateOnly
```
