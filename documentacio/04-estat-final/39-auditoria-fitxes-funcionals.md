# 39 - Auditoria i reconciliació de fitxes funcionals

Data de tall: 16/09/2026.

Fonts locals revisades:

- tauler 2, `cIht4ZYN - 2-verifactu-sif-casos-dus-analisi-funcional (1).json`, SHA-256 `37264af8ea7f8dec9109a4c7c386294d6f027d2e907f2275e614652b6b4b3492`;
- tauler 3, `Hi8lzHW2 - 3-verifactu-sif-desenvolupament-sif-bd-i-api.json`, SHA-256 `9200fd5d63ac2a1938735b0036be4f1b74b465a173b036d7038d904a80e730c6`;
- tauler 6, `MQ8dR2NZ - 6-verifactu-sif-sif-payprismacat (3).json`, SHA-256 `1c628670e7f75421d1ae13c38bc041124e6fedb281c25ad178b0dfe24004d4e5`.

## 1. Resultat

- targetes obertes `Fitxes mare` al tauler 2: **145**;
- targetes obertes `Fitxes mare` al tauler 3: **10**;
- targetes obertes `Fitxes mare` al tauler 6: **30**;
- total real revisat entre els tres taulers: **185**;
- identificadors canònics actuals: **112** més **13 variants**;
- fitxes canòniques generades: **125**;
- casos nous descoberts directament al codi i absents del catàleg anterior: **7** (UC-106..UC-112).

La taula individual de l'apartat 2 conserva la reconciliació funcional del
tauler 2. Les 40 targetes addicionals dels taulers 3 i 6 són principalment
fitxes tècniques o de programació i es detallen a l'apartat 3. El recompte
anterior de 145/145 era correcte només per a una llista d'un tauler i no podia
usar-se per afirmar cobertura global.

La revisió anterior també era només un **mapatge de títols**. No incorporava de
forma individual el contingut de les 145 descripcions ni els 36 elements de
checklist detectats, i no contrastava tota la superfície funcional del codi.
Per tant, “0 sense correspondència” no equivalia a “0 funcionalitat absent”.

## 2. Reconciliació individual

| # | Fitxa mare | Cas canònic o classificació |
| ---: | --- | --- |
| 1 | [Cas d’ús: Registres AEAT](https://trello.com/c/rf8JD5f4) | UC-35, UC-54, UC-75, UC-76, UC-77 |
| 2 | [Cas d’ús: Proves i validació](https://trello.com/c/ddZVH9mD) | UC-39 |
| 3 | [Cas d’ús mare: Empresa/responsable substitueix pagament d’alumne](https://trello.com/c/diLTrKn9) | UC-21, UC-33, UC-50, UC-103 |
| 4 | [Cas d’ús: Factures SIF](https://trello.com/c/qAmb4Gtg) | UC-07, UC-34, UC-35 |
| 5 | [Cas d’ús: Documents SIF](https://trello.com/c/32asp2mz) | UC-36, UC-55, UC-78, UC-80 |
| 6 | [Cas d’ús: Apartat VERI*FACTU intranet](https://trello.com/c/Kx1Ow5bn) | UC-34, UC-60 |
| 7 | [Cas d’ús confirmat: Grup amb participants](https://trello.com/c/lU3bwP2J) | UC-16 |
| 8 | [Cas d’ús confirmat: Reclamació de pagament](https://trello.com/c/1PDpKmsB) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 9 | [Cas d’ús: 1a Reclamació Pagament](https://trello.com/c/4G8pLYUN) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 10 | [Cas d’ús: Validar descomptes](https://trello.com/c/dOukrU5T) | UC-19, UC-20, UC-90, UC-91 |
| 11 | [Cas d’ús: Baixes 2a setmana](https://trello.com/c/EkAXWKOM) | UC-27, UC-72, UC-96 |
| 12 | [Cas d’ús: Reclamació Final i Baixa](https://trello.com/c/EWOudvgi) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 13 | [Cas d’ús: Recordatori Pagament Final](https://trello.com/c/K3DvHcil) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 14 | [Cas d’ús: Control morosos](https://trello.com/c/BMe6cfNf) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 15 | [Cas d’ús: Promoció temporal](https://trello.com/c/dJYrzd0v) | UC-20c |
| 16 | [Cas d’ús mare: Canvi de curs amb major import](https://trello.com/c/z4aGIiNA) | UC-26, UC-28, UC-71 |
| 17 | [Cas d’ús: Passar pagament de factura ja generada](https://trello.com/c/usN3pntN) | UC-02, UC-22, UC-56, UC-86 |
| 18 | [Cas d’ús mare: Canvi de curs amb menor import](https://trello.com/c/NHLH46qh) | UC-26, UC-28, UC-29, UC-71 |
| 19 | [Cas d’ús: Canvi de curs](https://trello.com/c/dkHnz8Mt) | UC-26, UC-71 |
| 20 | [Cas d’ús: Transferència validada a intranet](https://trello.com/c/5fJbmrhy) | UC-22, UC-56, UC-86 |
| 21 | [Cas d’ús: Curs normal Redsys](https://trello.com/c/G1uMdZPl) | UC-03, UC-14, UC-63 |
| 22 | [Cas d’ús: Baixa d’inscripció](https://trello.com/c/8uthYv85) | UC-27, UC-72 |
| 23 | [Cas d’ús: Despeses de gestió](https://trello.com/c/353mfFJy) | UC-73, UC-94 |
| 24 | [Cas d’ús mare: Canvi de curs amb mateix import](https://trello.com/c/ybVK14Ax) | UC-26, UC-71 |
| 25 | [Cas d’ús: Aplicar saldo futur](https://trello.com/c/lWAOHjln) | UC-29, UC-29a |
| 26 | [Cas d’ús mare: Despeses de gestió en grup](https://trello.com/c/PoRBd7LO) | UC-73, UC-91, UC-94 |
| 27 | [Cas d’ús: Devolució transferència/manual](https://trello.com/c/N6lCvQ2p) | UC-28 |
| 28 | [Cas d’ús mare: Despeses de gestió per canvi de curs](https://trello.com/c/SJocKLoE) | UC-71, UC-73, UC-94 |
| 29 | [Cas d’ús: PDF/QR pendent](https://trello.com/c/NCcu5h1n) | UC-55, UC-78 |
| 30 | [Cas d’ús mare: Despeses de gestió com a cobrament independent](https://trello.com/c/OcIJysaF) | UC-22, UC-73, UC-86, UC-94 |
| 31 | [Cas d’ús: Històric Associació/SL barrejat](https://trello.com/c/23PHpsnr) | UC-97 |
| 32 | [Cas d’ús: Botiga de llibres / SL pendent de decisió](https://trello.com/c/QGoIxrEO) | UC-98 |
| 33 | [Cas d’ús: Redsys denegat i després acceptat](https://trello.com/c/KBupC3Ru) | UC-03, UC-51, UC-52 |
| 34 | [Cas d’ús: Diversos intents amb mateix `IDPAG`](https://trello.com/c/XaTsw4Tc) | UC-51, UC-52, UC-86 |
| 35 | [Cas d’ús: Client canvia dades fiscals després](https://trello.com/c/qALbjGJj) | UC-70, UC-93 |
| 36 | [Cas d’ús: Operació no facturable](https://trello.com/c/Dvvt8vQn) | UC-100 |
| 37 | [Cas d’ús: Cercar pagaments sense criteri únic actual](https://trello.com/c/8cp3Fz7t) | UC-56, UC-86 |
| 38 | [Cas d’ús: Previsualitzar canvi de curs](https://trello.com/c/Fc7jo7VC) | UC-26, UC-71 |
| 39 | [Cas d’ús: Una transferència paga diverses factures](https://trello.com/c/ji9uBtzr) | UC-02, UC-22, UC-105 |
| 40 | [Cas d’ús: URL de pagament d’alumne substituïda per empresa](https://trello.com/c/h3J6eU9m) | UC-21, UC-33, UC-50, UC-103 |
| 41 | [Cas d’ús: Anul·lar pagament des de la web i moure el flux a pay.prisma.cat](https://trello.com/c/iPOdxEt6) | UC-68, UC-86, UC-103 |
| 42 | [Cas d’ús: Generar factura abans de pagar](https://trello.com/c/4C1BVVQx) | UC-04, UC-21 |
| 43 | [Cas d’ús: Analitzar fitxer TPV](https://trello.com/c/vWDpnMD9) | UC-25, UC-82 |
| 44 | [Cas d’ús: Descarregar factures](https://trello.com/c/HGelqGqt) | UC-07, UC-36, UC-80 |
| 45 | [Cas d’ús: Exportació de factures VeriFactu](https://trello.com/c/PFF3cBsr) | UC-37, UC-84 |
| 46 | [Cas d’ús: Informació de l’alumne](https://trello.com/c/j5aFGQ1J) | UC-42, UC-61, UC-86 |
| 47 | [Cas d’ús: Consultar pagaments](https://trello.com/c/68WIGD64) | UC-56, UC-86 |
| 48 | [Cas d’ús: Passar pagaments](https://trello.com/c/RcnTpn64) | UC-56, UC-86 |
| 49 | [Cas d’ús: Consulta / edita / anul·la factura](https://trello.com/c/4l7wnDlS) | UC-05, UC-07, UC-30, UC-31, UC-32 |
| 50 | [Cas d’ús: Pack](https://trello.com/c/B3l5rWQ5) | UC-15 |
| 51 | [Cas d’ús: Grup de persones](https://trello.com/c/GHyh2f4B) | UC-16, UC-16a, UC-16b |
| 52 | [Cas d’ús: Regal](https://trello.com/c/X2EvUbZi) | UC-17, UC-18, UC-18a |
| 53 | [Cas d’ús: USOC](https://trello.com/c/HD6U81Kn) | UC-13, UC-19, UC-19a, UC-19b |
| 54 | [Cas d’ús: Compensació / saldo](https://trello.com/c/G9vDvngq) | UC-29, UC-29a |
| 55 | [Cas d’ús: Factura rectificativa](https://trello.com/c/OZBcpDEl) | UC-05, UC-74 |
| 56 | [Cas d’ús: Factura manual](https://trello.com/c/EqKp6wif) | UC-01, UC-92 |
| 57 | [Cas d’ús: Factura abans de cobrament + pagament posterior](https://trello.com/c/ajqunquD) | UC-04, UC-21 |
| 58 | [Cas d’ús: Devolució total](https://trello.com/c/VLlCNoFE) | UC-28 |
| 59 | [Cas d’ús: Devolució parcial](https://trello.com/c/mugsCSmL) | UC-28 |
| 60 | [Cas d’ús: Pagament fraccionat](https://trello.com/c/1OCNDf89) | UC-23, UC-86 |
| 61 | [Cas d’ús: Consulta intranet alumne](https://trello.com/c/xcczJ5qq) | UC-61, UC-102 |
| 62 | [Cas d’ús: Factura amb PDF/QR pendent](https://trello.com/c/JLu4nHzf) | UC-55, UC-78 |
| 63 | [Cas d’ús: Error SIF amb cua i reintent](https://trello.com/c/9lKLrSrP) | UC-08, UC-54, UC-55, UC-81 |
| 64 | [Cas d’ús: Callback Redsys duplicat](https://trello.com/c/EiTiynSf) | UC-03, UC-51, UC-52 |
| 65 | [Cas d’ús: Pagament cobrat sense factura](https://trello.com/c/FaO2pvWt) | UC-01, UC-25, UC-53, UC-86 |
| 66 | [Cas d’ús: Factura emesa pendent de cobrament](https://trello.com/c/RTV2ZZn1) | UC-04, UC-61 |
| 67 | [Cas d’ús: Error PDF/QR](https://trello.com/c/0TwOp7Bv) | UC-08, UC-55, UC-78, UC-81 |
| 68 | [Cas d’ús: comprador estranger o entitat amb dades fiscals incompletes](https://trello.com/c/aQPZRdFE) | UC-87 |
| 69 | [Cas d’ús: factura d’empresa visible en fitxa d’alumne](https://trello.com/c/4VC0kEG6) | UC-07, UC-80 |
| 70 | [Cas d’ús mare: Desactivar URL individual d’alumne cobert per empresa/responsable](https://trello.com/c/NT1ytqVo) | UC-21, UC-33, UC-50, UC-103 |
| 71 | [Cas d’ús: Consulta auditor/AEAT només lectura](https://trello.com/c/VCORSw4m) | UC-45, UC-59, UC-84 |
| 72 | [Cas d’ús confirmat: Error PDF](https://trello.com/c/xGGUROT4) | UC-08, UC-55, UC-78, UC-81 |
| 73 | [Cas d’ús: Jornada](https://trello.com/c/7PL4ycth) | UC-14b |
| 74 | [Cas d’ús: Taller](https://trello.com/c/DGj9EdQG) | UC-14a |
| 75 | [Cas d’ús: Codi promocional](https://trello.com/c/r5XizI7y) | UC-20d |
| 76 | [Cas d’ús: Factura electrònica E_FACT](https://trello.com/c/gil8bq8w) | UC-32 |
| 77 | [Cas d’ús: Factura ordinària A](https://trello.com/c/dyPaCDSa) | UC-01 |
| 78 | [Cas d’ús: Veure factura](https://trello.com/c/NnRF8w6u) | UC-07, UC-36, UC-80 |
| 79 | [Cas d’ús: canvi de concepte després de pagar](https://trello.com/c/Yrl8QHK4) | UC-89 |
| 80 | [Cas d’ús: falsa proforma que en realitat és factura](https://trello.com/c/CDtWWSkv) | UC-48 |
| 81 | [Cas d’ús: factura agrupada de diverses coses](https://trello.com/c/3hJdyQr3) | UC-88 |
| 82 | [Cas d’ús: una factura per pagament vs una factura amb diverses línies](https://trello.com/c/zcrgxak6) | UC-88 |
| 83 | [Cas d’ús: Notificació fiscal pendent](https://trello.com/c/SquvMUYa) | UC-58, UC-79 |
| 84 | [Cas d’ús: Exportació de registres fiscals](https://trello.com/c/E8CEWlIJ) | UC-37, UC-84 |
| 85 | [Cas d'ús: Registres AEAT al panell SIF](https://trello.com/c/FhJdGKoS) | UC-35, UC-54, UC-75, UC-76, UC-77 |
| 86 | [Cas d'ús: Factures al panell SIF](https://trello.com/c/v8f42LGi) | UC-07, UC-34, UC-35 |
| 87 | [Cas d’ús: Dades del curs / dades pagament](https://trello.com/c/lNj0QkXT) | UC-42, UC-61, UC-86 |
| 88 | [Cas d’ús: migració de factures antigues com històric no VeriFactu](https://trello.com/c/cZqdNfUk) | UC-11, UC-47, UC-97 |
| 89 | [Validar cas d’ús complet: Intranet tutor](https://trello.com/c/8RdafU8J) | UC-99 |
| 90 | [Fitxa de cas d’ús: factura i cobrament neixen junts](https://trello.com/c/9Q2tBx8E) | UC-01, UC-02 |
| 91 | [Cas d’ús: Incidències SIF](https://trello.com/c/56FeKycz) | UC-08, UC-54, UC-55, UC-81 |
| 92 | [Cas d’ús mare: Privacitat de participants en factura de grup/empresa](https://trello.com/c/HqCCC4jn) | UC-07, UC-16, UC-80 |
| 93 | [Cas d’ús mare: Accés segur de l’empresa/responsable a factura](https://trello.com/c/IcdFXjRy) | UC-50, UC-80 |
| 94 | [Cas d’ús: alumne sense rol intranet però amb intranet personalitzada](https://trello.com/c/ndb2pE5k) | UC-102 |
| 95 | [Cas d’ús: curs no superat amb pagament pendent](https://trello.com/c/OtuBymJ4) | UC-12, UC-95 |
| 96 | [Cas d’ús: curs superat amb pagament pendent](https://trello.com/c/OiloeFuF) | UC-12, UC-95 |
| 97 | [Cas d’ús: Carnet Jove](https://trello.com/c/NoCzLrjE) | UC-20a |
| 98 | [Cas d’ús: Descompte Alumne PrisMa](https://trello.com/c/15iKkmmv) | UC-20 |
| 99 | [Cas d’ús: Descompte grup](https://trello.com/c/qj2XwbhR) | UC-91 |
| 100 | [Cas d’ús: Diccionari camps/valors](https://trello.com/c/1bYIe2j0) | META-DICCIONARI → UC-01…UC-105 |
| 101 | [Cas d’ús: Discapacitat/família nombrosa/monoparental/violència de gènere](https://trello.com/c/3MsEw8FU) | UC-20b |
| 102 | [Cas d’ús: Factures manuals](https://trello.com/c/FmvCfU7T) | UC-01, UC-92 |
| 103 | [Cas d’ús: Migració factures històriques](https://trello.com/c/oNMkfBGw) | UC-11, UC-47, UC-97 |
| 104 | [Cas d’ús: Pagament morositat/reclamació](https://trello.com/c/Yh2glOm0) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 105 | [Cas d’ús: QR fiscal](https://trello.com/c/FFHZV2pg) | UC-36, UC-78 |
| 106 | [Cas d’ús: Rol auditor/AEAT](https://trello.com/c/WvmymzeG) | UC-45, UC-59, UC-84 |
| 107 | [Cas d’ús: Subdomini/SSL pay.prisma.cat](https://trello.com/c/gsoDAKj6) | UC-38, UC-101 |
| 108 | [Cas d’ús: XML / registre AEAT](https://trello.com/c/bz1S7BsW) | UC-35, UC-54, UC-75, UC-76, UC-77 |
| 109 | [Identificació de casos d’ús fiscals](https://trello.com/c/5rwMq7RC) | META-CATÀLEG → UC-01…UC-105 |
| 110 | [Separació entre cas d’ús i programació](https://trello.com/c/o33UvJsx) | META-CATÀLEG → UC-01…UC-105 |
| 111 | [Cas d’ús: adopció de modalitat VeriFactu a empresa amb intranet pròpia](https://trello.com/c/ZTgh9274) | META-ARQ → UC-62, UC-68, UC-103 |
| 112 | [Separació entre pagament i factura](https://trello.com/c/WvEB96zN) | META-REGLE → UC-01, UC-02, UC-88 |
| 113 | [Detecció de casos de descomptes i promocions](https://trello.com/c/izTGo2X9) | META-ANÀLISI → UC-20, UC-20a…UC-20d, UC-90, UC-91 |
| 114 | [Detecció de casos de morositat](https://trello.com/c/Ama1p6dK) | META-ANÀLISI → UC-12, UC-24, UC-95, UC-96 |
| 115 | [Detecció de casos de document fiscal pendent](https://trello.com/c/xcqbVPwQ) | META-ANÀLISI → UC-55, UC-78 |
| 116 | [Cas d’ús: Configuració SIF](https://trello.com/c/01NVrrJW) | UC-38, UC-83, UC-101 |
| 117 | [Cas d’ús: Dashboard SIF](https://trello.com/c/3lUSun81) | UC-34, UC-60 |
| 118 | [Cas d’ús: Línies de factura](https://trello.com/c/gzpxIZlb) | UC-01, UC-88 |
| 119 | [Cas d’ús: PDF immutable](https://trello.com/c/t0AcPKIl) | UC-36, UC-55, UC-78 |
| 120 | [Cas d’ús: Permisos i rols](https://trello.com/c/eps3W08i) | UC-45, UC-59, UC-102 |
| 121 | [Cas d’ús: Preproducció / entorn de proves](https://trello.com/c/mfhX1QdA) | UC-39 |
| 122 | [Cas d’ús: descompte de grup per trams](https://trello.com/c/jNTsXGJe) | UC-91 |
| 123 | [Analitzar descomptes reals del sistema](https://trello.com/c/IRQWKMwF) | META-ANÀLISI → UC-20, UC-20a…UC-20d, UC-90, UC-91 |
| 124 | [Analitzar codis promocionals](https://trello.com/c/BzyFkPxo) | META-ANÀLISI → UC-20, UC-20a…UC-20d, UC-90, UC-91 |
| 125 | [Cas d’ús: client compra com a particular i després demana factura](https://trello.com/c/EOLpNlDy) | UC-93 |
| 126 | [Cas d’ús: canvi de dades personals només en inscripcions pendents](https://trello.com/c/RX0Co1xp) | UC-42, UC-70 |
| 127 | [Cas d’ús: canvi de concepte després de pagar](https://trello.com/c/VCmLYf4V) | UC-89 |
| 128 | [Cas d’ús: calendari de reclamacions de pagament](https://trello.com/c/2nfN4miB) | UC-12, UC-24, UC-43, UC-95, UC-96 |
| 129 | [Cas d’ús: comprador estranger o entitat amb dades fiscals incompletes](https://trello.com/c/wPb0qB4x) | UC-87 |
| 130 | [Cas d’ús: una factura per pagament vs una factura amb diverses línies](https://trello.com/c/OGRGKAAL) | UC-88 |
| 131 | [Cas d’ús: alumne sense rol intranet però amb intranet personalitzada](https://trello.com/c/vQagRVTp) | UC-102 |
| 132 | [Cas d’ús: venda manual des d’intranet per empresa o trucada](https://trello.com/c/9FWiw0O4) | UC-92 |
| 133 | [Cas d’ús: falsa proforma que en realitat és factura](https://trello.com/c/PAz7zvpK) | UC-48 |
| 134 | [Cas d’ús: factura d’empresa visible en fitxa d’alumne](https://trello.com/c/ynPOo9Ck) | UC-07, UC-80 |
| 135 | [Cas d’ús: canvi manual d’A_PAGAR justificat en modal](https://trello.com/c/EKFGRXYF) | UC-94 |
| 136 | [Cas d’ús: no paga a l’inici però justifica i segueix fins segona setmana](https://trello.com/c/xiDP6N6y) | UC-96 |
| 137 | [Verificar i tancar cobertura: Empresa que substitueix pagament d’alumne](https://trello.com/c/VHhyVQKA) | UC-21, UC-33, UC-50, UC-103 |
| 138 | [Verificar i tancar cobertura: Descompte validat després](https://trello.com/c/hDyBLhCo) | UC-90 |
| 139 | [Cas d’ús: SIF centralitzat per intranet, ecommerce i TPVs](https://trello.com/c/G6lFbyoR) | META-ARQ → UC-62, UC-68, UC-103 |
| 140 | [Cas d’ús: pas intermedi de dades fiscals abans d’emetre factura](https://trello.com/c/knbCycA1) | UC-69, UC-87 |
| 141 | [Cas d’ús: compra de curs online amb Redsys](https://trello.com/c/5rVG5AnX) | UC-03, UC-14, UC-63 |
| 142 | [Cas d’ús: factura agrupada de diverses coses](https://trello.com/c/tfhxNJ5g) | UC-88 |
| 143 | [Cas d’ús mare: Callback Redsys autoritzat amb processament asíncron](https://trello.com/c/yNNL42W3) | UC-03, UC-51, UC-52 |
| 144 | [Cas d’ús: Descompte validat després de la compra](https://trello.com/c/8yOET6Zv) | UC-90 |
| 145 | [Cas d’ús: Operació informativa](https://trello.com/c/EY67fglp) | UC-100 |

## 3. Fitxes mare tècniques dels taulers 3 i 6

### 3.1. Tauler 3 — desenvolupament SIF, BD i API (10)

| Fitxa mare | Correspondència |
| --- | --- |
| [Baixa com event administratiu](https://trello.com/c/H1tVGBPe) | UC-27, UC-72; `enrollment_cancellation_event` |
| [Taula `notificacions` a BD intranet](https://trello.com/c/pWjicTnn) | UC-43, UC-58; decisió de frontera intranet/outbox |
| [Despeses de gestió com a línia de factura](https://trello.com/c/2K8nObSY) | UC-73, UC-94 |
| [Despeses de gestió com a penalització](https://trello.com/c/qrzrjVlZ) | UC-73, UC-74, UC-94 |
| [Despeses de gestió com a possible cobrament independent](https://trello.com/c/34iFUn5Z) | UC-22, UC-73, UC-86, UC-94 |
| [`credit_balance` per saldo futur](https://trello.com/c/AxP1dTyG) | UC-29, UC-29a |
| [Promocions temporals separades de codis promocionals](https://trello.com/c/BfcOXngT) | UC-20c, UC-90, UC-112 |
| [Diversos intents amb mateix `IDPAG`](https://trello.com/c/l3ZlYRbS) | UC-51, UC-52, UC-86, UC-112 |
| [Canvi de dades fiscals posterior com a rectificativa](https://trello.com/c/i0HrnK46) | UC-70, UC-74, UC-93 |
| [Operació no facturable com a criteri preventiu](https://trello.com/c/WII15RX5) | UC-100, UC-108, UC-109 |

### 3.2. Tauler 6 — SIF `pay.prisma.cat` (30)

| Fitxa mare | Correspondència |
| --- | --- |
| [Generador UUID complet](https://trello.com/c/dVS5xX7R) | META-INFRA → UC transversals |
| [Idempotència genèrica del SIF](https://trello.com/c/Qq84AvSc) | META-INFRA → UC-01, UC-02, UC-86, UC-106..UC-112 |
| [Repositori de seqüència fiscal](https://trello.com/c/CyhShT1D) | UC-01 |
| [Validador de payload `issueInvoice()`](https://trello.com/c/A6KLh6B1) | UC-01, UC-112 |
| [Validador de payload `registerPayment()`](https://trello.com/c/MlQDx6P5) | UC-02, UC-86 |
| [Migració callback Redsys taller](https://trello.com/c/n9ipw48G) | UC-03, UC-14a, UC-51, UC-68 |
| [Migració callback Redsys jornada](https://trello.com/c/ZEoGiWMj) | UC-03, UC-14b, UC-51, UC-68 |
| [Migració callback Redsys pack](https://trello.com/c/ctz5vu12) | UC-03, UC-15, UC-51, UC-68 |
| [Migració callback Redsys grup](https://trello.com/c/JWG7rR2a) | UC-03, UC-16, UC-51, UC-68 |
| [Migració callback Redsys regal](https://trello.com/c/isSnHUa4) | UC-03, UC-17, UC-51, UC-68 |
| [Resposta idempotent a Redsys](https://trello.com/c/XyoP4nZH) | UC-03, UC-51, UC-52 |
| [Passar pagaments com a conciliació SIF](https://trello.com/c/iFtML7sE) | UC-25, UC-53, UC-82, UC-86 |
| [QR fiscal](https://trello.com/c/ygL5s43f) | UC-36, UC-55, UC-78 |
| [`fiscal_queue` i retries](https://trello.com/c/GhD3vIOa) | UC-09, UC-54, UC-77 |
| [Incidències SIF](https://trello.com/c/nmoHV53O) | UC-08, UC-81 |
| [Sincronització resum cap a BD antiga](https://trello.com/c/EwfpP8xX) | UC-47, UC-68 |
| [`motiu_canvi`](https://trello.com/c/WZNWrgyf) | UC-26, UC-71, UC-94 |
| [Baixa com event administratiu](https://trello.com/c/731mzMzs) | UC-27, UC-72 |
| [Script `preflight-sif.php`](https://trello.com/c/Twy2yFkM) | META-OPERACIÓ → UC-39, UC-60, UC-83 |
| [Permisos MySQL restrictius](https://trello.com/c/WPwwXevy) | META-SEGURETAT → UC-45, UC-59, UC-102 |
| [Taula `notificacions` a BD intranet](https://trello.com/c/KWjHTbuk) | UC-43, UC-58 |
| [Despeses de gestió com a línia](https://trello.com/c/wPOQHkXo) | UC-73, UC-94 |
| [Despeses de gestió com a penalització](https://trello.com/c/eSmwh9LP) | UC-73, UC-74, UC-94 |
| [Despeses de gestió com a cobrament independent](https://trello.com/c/fnQOxroQ) | UC-22, UC-73, UC-86, UC-94 |
| [`credit_balance` per saldo futur](https://trello.com/c/AqcQ6rzc) | UC-29, UC-29a |
| [Promocions temporals separades](https://trello.com/c/7Gc53wq1) | UC-20c, UC-90, UC-112 |
| [Redsys denegat i després acceptat](https://trello.com/c/g1lyI28p) | UC-03, UC-51, UC-52 |
| [Diversos intents amb mateix `IDPAG`](https://trello.com/c/wet2FfcT) | UC-51, UC-52, UC-86, UC-112 |
| [Canvi de dades fiscals posterior](https://trello.com/c/QthLs8Ns) | UC-70, UC-74, UC-93 |
| [Operació no facturable](https://trello.com/c/ZfbW8ooW) | UC-100, UC-108, UC-109 |

## 4. Criteri correcte per afirmar que no falta una fitxa

1. cada identificador del catàleg té un fitxer amb 21 apartats;
2. cada `Fitxa mare` dels tres taulers es mapeja o queda marcada com a metadada;
3. la descripció i les checklists de cada targeta aporten claims verificables a la fitxa corresponent;
4. cada flux funcional descobert al codi té cas, variants, dades i persistència pròpies;
5. les 192 pantalles/estats es tracten com a interfícies o variants i no es confonen automàticament amb casos nous;
6. cada fitxa declara dades concretes, regles, persistència, impacte fiscal/econòmic, auditoria, errors, notificacions, proves, buits i tasques;
7. una fitxa només es pot declarar completa quan no depèn de textos genèrics i les decisions bloquejants estan resoltes.

Amb aquest criteri, les 125 fitxes actuals són **esborranys estructurats**. No
es declara encara que totes estiguin completes a nivell de contingut.

## 5. Límit de l’auditoria

La reconciliació usa les tres fotografies locals indicades i la documentació del
repositori. No consulta Trello en directe ni el JSONL del xat antic. No acredita
que les decisions pendents estiguin resoltes, que totes les descripcions hagin
estat incorporades claim a claim ni que el codi desplegat coincideixi amb les
còpies locals.

## 6. Validació reproduïble del recompte i mapatge

`sif/tools/functional-card/reconcile-trello-fitxes.ps1` rep els tres exports,
comprova els recomptes 145/10/30, el total 185, els SHA-256 i que cap títol
quedi `REVISAR`. L'script no sobreescriu aquest informe: valida l'inventari, no
pot substituir la revisió manual de descripcions, checklists i codi.
