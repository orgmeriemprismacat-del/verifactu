<?php

/*
 * Aquest include necessita:
 *
 * $filtres
 * $dispositiu
 * $cdd
 * $nousCursos
 *
 * I genera:
 *
 * $llistatCursosPacks
 * $objOrd
 * $ordre
 */

/* =========================================================
 * 1. INICIALITZACIÓ
 * ========================================================= */

$edicions = '';
$perfilsFiltres = [];
$temesFiltres = [];
$nivellsFiltres = [];
$altresFiltres = [];

$packsCandidats = [];
$nivellsPerPack = [];
$temesPerPack = [];
$perfilsPerPack = [];
$packsAmbInscripcioOberta = [];

$llistatCursosPacks = [];

$qualsevolEdicio = false;
$ed = null;


/* =========================================================
 * 2. FILTRE D’EDICIONS
 * ========================================================= */

$i = 0;

while (
    $i < count($filtres[0]) &&
    !$qualsevolEdicio
) {
    $edicio = $filtres[0][$i];
    $edicioParts = explode('|', $edicio);

    $mesEdicio = $edicioParts[1] ?? '00';
    $anyEdicio = (int) ($edicioParts[2] ?? 0);

    /*
     * Conservem l’última edició processada per mantenir
     * compatibilitat amb el comportament anterior.
     */
    $ed = [
        1 => $mesEdicio,
        2 => $anyEdicio
    ];

    if ($mesEdicio === '00') {
        $qualsevolEdicio = true;
        break;
    }

    if ($edicions !== '') {
        $edicions .= ' OR ';
    }

    $edicions .= sprintf(
        "(c.ANY = %d AND c.MES = '%s')",
        $anyEdicio,
        addslashes($mesEdicio)
    );

    if (
        $mesEdicio === '08' &&
        $anyEdicio === 2025
    ) {
        $edicions .= " AND c.CODI_CURS != 'SDA'";
    }

    if (
        $mesEdicio === '07' &&
        $anyEdicio === 2025
    ) {
        $edicions .= "
            OR (
                c.ANY = 2025
                AND c.MES = '08'
                AND c.HORES = 15
            )
        ";
    }

    $i++;
}

/*
 * Quan se selecciona “qualsevol edició”, obtenim la data
 * mínima indicada pel filtre.
 */
if ($qualsevolEdicio) {
    $edicioReferencia =
        $filtres[0][1] ??
        $filtres[0][0] ??
        null;

    if ($edicioReferencia !== null) {
        $partsReferencia = explode('|', $edicioReferencia);

        $mesReferencia = $partsReferencia[1] ?? '01';
        $anyReferencia = (int) ($partsReferencia[2] ?? date('Y'));

        $edicions = sprintf(
            "(
                c.ANY > %d
                OR (
                    c.ANY = %d
                    AND c.MES >= '%s'
                )
            )",
            $anyReferencia,
            $anyReferencia,
            addslashes($mesReferencia)
        );
    } else {
        $edicions = '1 = 1';
    }
}

if ($edicions === '') {
    $edicions = '1 = 1';
}


/* =========================================================
 * 3. ORDRE
 * ========================================================= */

$objOrd = explode('|', $filtres[4]);

$campOrdre = $objOrd[1] ?? 'ANY,MES';
$direccioOrdre = strtoupper($objOrd[2] ?? 'ASC');

/*
 * No introduïm directament qualsevol text rebut per GET
 * dins de l’ORDER BY.
 */
$ordresPermesos = [
    'ANY,MES' => 'c.ANY, c.MES',
    'TITOL'   => 'i.TITOL'
];

if (!isset($ordresPermesos[$campOrdre])) {
    $campOrdre = 'ANY,MES';
}

if (!in_array($direccioOrdre, ['ASC', 'DESC'], true)) {
    $direccioOrdre = 'ASC';
}

$ordre = $campOrdre.' '.$direccioOrdre;
$ordreSQL = $ordresPermesos[$campOrdre].' '.$direccioOrdre;


/* =========================================================
 * 4. CONVERSIÓ DELS FILTRES A ARRAYS
 * ========================================================= */

foreach ($filtres[1] as $perfil) {
    $parts = explode('|', $perfil);

    if (
        isset($parts[0]) &&
        $parts[0] !== '' &&
        $parts[0] !== '0'
    ) {
        $perfilsFiltres[] = (string) $parts[0];
    }
}

foreach ($filtres[2] as $tema) {
    $parts = explode('|', $tema);

    if (
        isset($parts[0]) &&
        $parts[0] !== '' &&
        $parts[0] !== '0'
    ) {
        $temesFiltres[] = (string) $parts[0];
    }
}

foreach ($filtres[3] as $nivell) {
    $parts = explode('|', $nivell);

    if (
        isset($parts[0]) &&
        $parts[0] !== '' &&
        $parts[0] !== '0'
    ) {
        $nivellsFiltres[] = (string) $parts[0];
    }
}

foreach ($filtres[5] as $altre) {
    $parts = explode('|', $altre);

    if (isset($parts[0]) && $parts[0] !== '') {
        $altresFiltres[] = (string) $parts[0];
    }
}

$inclouCursosEstiu =
    isset($altresFiltres[0]) &&
    $altresFiltres[0] !== '0';


/* =========================================================
 * 5. CONNEXIÓ
 * ========================================================= */

$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();


/* =========================================================
 * 6. PACKS CANDIDATS
 *
 * Recuperem també:
 * - DATA_CREATE per saber si és nou.
 * - ETIQ_CDD per saber si té CDD.
 * - ETIQ_PERFIL per filtrar perfils sense crear Curs.
 * ========================================================= */

$cnsPack = "
    SELECT
        i.ID_PACK,
        i.DATA_CREATE,
        i.ETIQ_CDD,
        i.ETIQ_PERFIL
    FROM info_pack AS i

    INNER JOIN packs AS p
        ON p.ID_PACK = i.ID_PACK

    INNER JOIN curs AS c
        ON c.ID_CURS = p.ID_CURS

    INNER JOIN filtres AS f
        ON f.ID_INFO = i.ID_PACK

    WHERE f.DATAI <= NOW()
      AND (
            f.DATAF IS NULL
            OR f.DATAF >= NOW()
      )
      AND (".$edicions.")
      AND i.ESTAT = 1
      AND c.PUBLIC = 1
      AND p.PUBLIC = 1
      AND c.ESTAT NOT IN ('0', 'T')

    GROUP BY
        i.ID_PACK,
        i.DATA_CREATE,
        i.ETIQ_CDD,
        i.ETIQ_PERFIL

    ORDER BY ".$ordreSQL;

if (!$stmt = $connexio->prepare($cnsPack)) {
    throw new Exception('', 2702);
}

$stmt->execute();

$stmt->bind_result(
    $idPack,
    $dataCreate,
    $cddPack,
    $etiquetaPerfilPack
);

while ($stmt->fetch()) {
    $idPack = (string) $idPack;

    $packsCandidats[$idPack] = [
        'dataCreate'    => $dataCreate,
        'cdd'           => $cddPack,
        'etiquetaPerfil'=> $etiquetaPerfilPack
    ];
}

$connexio->closeStmt();


/* =========================================================
 * 7. NIVELLS DELS PACKS
 * ========================================================= */

if (
    !empty($packsCandidats) &&
    !empty($nivellsFiltres)
) {
    $cnsNivells = "
        SELECT
            f.ID_INFO,
            f.ID_NIVELL
        FROM filtres AS f

        INNER JOIN info_pack AS i
            ON i.ID_PACK = f.ID_INFO

        WHERE f.DATAI <= NOW()
          AND (
                f.DATAF IS NULL
                OR f.DATAF >= NOW()
          )
          AND i.ESTAT = 1
          AND f.ID_NIVELL IS NOT NULL
          AND f.ID_NIVELL != ''
    ";

    if (!$stmtNivells = $connexio->prepare($cnsNivells)) {
        throw new Exception('', 2607);
    }

    $stmtNivells->execute();

    $stmtNivells->bind_result(
        $idPackNivell,
        $idsNivell
    );

    while ($stmtNivells->fetch()) {
        $idPackNivell = (string) $idPackNivell;

        if (!isset($packsCandidats[$idPackNivell])) {
            continue;
        }

        $nivells = array_filter(
            explode('|', (string) $idsNivell),
            static function ($id) {
                return $id !== '';
            }
        );

        if (!isset($nivellsPerPack[$idPackNivell])) {
            $nivellsPerPack[$idPackNivell] = [];
        }

        $nivellsPerPack[$idPackNivell] = array_values(
            array_unique(
                array_merge(
                    $nivellsPerPack[$idPackNivell],
                    $nivells
                )
            )
        );
    }

    $connexio->closeStmt();
}


/* =========================================================
 * 8. TEMES DELS CURSOS INCLOSOS ALS PACKS
 * ========================================================= */

if (
    !empty($packsCandidats) &&
    !empty($temesFiltres)
) {
    $cnsTemes = "
        SELECT DISTINCT
            p.ID_PACK,
            f.ID_TEMA

        FROM packs AS p

        INNER JOIN curs AS c
            ON c.ID_CURS = p.ID_CURS

        INNER JOIN informacio AS i
            ON i.CODI_CURS = c.CURS

        INNER JOIN filtres AS f
            ON f.ID_INFO = i.ID

        WHERE p.PUBLIC = 1
          AND c.PUBLIC = 1
          AND c.ESTAT NOT IN ('0', 'T')
          AND i.ESTAT = 1
          AND f.DATAI <= NOW()
          AND (
                f.DATAF IS NULL
                OR f.DATAF >= NOW()
          )
          AND f.ID_TEMA IS NOT NULL
          AND f.ID_TEMA != ''
    ";

    if (!$stmtTemes = $connexio->prepare($cnsTemes)) {
        throw new Exception('', 2612);
    }

    $stmtTemes->execute();

    $stmtTemes->bind_result(
        $idPackTema,
        $idTema
    );

    while ($stmtTemes->fetch()) {
        $idPackTema = (string) $idPackTema;

        if (!isset($packsCandidats[$idPackTema])) {
            continue;
        }

        if (!isset($temesPerPack[$idPackTema])) {
            $temesPerPack[$idPackTema] = [];
        }

        $idsTema = array_filter(
            explode('|', (string) $idTema),
            static function ($id) {
                return $id !== '';
            }
        );

        $temesPerPack[$idPackTema] = array_values(
            array_unique(
                array_merge(
                    $temesPerPack[$idPackTema],
                    $idsTema
                )
            )
        );
    }

    $connexio->closeStmt();
}


/* =========================================================
 * 9. PERFILS
 *
 * ETIQ_PERFIL té el format:
 *
 * hores_idPerfil|hores_idPerfil
 *
 * Exemple:
 *
 * 30_2|40_5
 *
 * Per tant, recuperem la part posterior al guió baix.
 * ========================================================= */

if (!empty($perfilsFiltres)) {
    foreach ($packsCandidats as $idPack => $dadesPack) {
        $perfilsPerPack[$idPack] = [];

        $etiquetes = $dadesPack['etiquetaPerfil'];

        if ($etiquetes === null || $etiquetes === '') {
            continue;
        }

        foreach (explode('|', $etiquetes) as $etiqueta) {
            $parts = explode('_', $etiqueta);

            if (
                isset($parts[1]) &&
                $parts[1] !== ''
            ) {
                $perfilsPerPack[$idPack][] = (string) $parts[1];
            }
        }

        $perfilsPerPack[$idPack] = array_values(
            array_unique($perfilsPerPack[$idPack])
        );
    }
}


/* =========================================================
 * 10. CONFIGURACIÓ DE PACK NOU
 * ========================================================= */

$valorPackNou = null;

if ((int) $nousCursos === 1) {
    $cnsPackNou = "
        SELECT VALOR
        FROM params
        WHERE TIPUS = ?
          AND DATAI <= NOW()
          AND (
                DATAF IS NULL
                OR DATAF >= NOW()
          )
        ORDER BY DATAI DESC
        LIMIT 1
    ";

    if (!$stmtPackNou = $connexio->prepare($cnsPackNou)) {
        throw new Exception('', 2611);
    }

    $tipusPackNou = 'etiqueta-curs-nou';

    $stmtPackNou->bind_param(
        's',
        $tipusPackNou
    );

    $stmtPackNou->execute();
    $stmtPackNou->bind_result($valorPackNou);
    $stmtPackNou->fetch();

    $connexio->closeStmt();
}


/* =========================================================
 * 11. DIES DURANT ELS QUALS ES POT INSCRIURE
 * ========================================================= */

$diesInscripcioPerHores = [];

if (!$qualsevolEdicio) {
    $cnsDiesInscripcio = "
        SELECT VALOR
        FROM params
        WHERE TIPUS = ?
          AND DATAI <= NOW()
          AND (
                DATAF IS NULL
                OR DATAF >= NOW()
          )
        ORDER BY VALOR
    ";

    if (!$stmtDies = $connexio->prepare($cnsDiesInscripcio)) {
        throw new Exception('', 2611);
    }

    $tipusDies = 'dies-inscriu-cursos';

    $stmtDies->bind_param(
        's',
        $tipusDies
    );

    $stmtDies->execute();
    $stmtDies->bind_result($valorDies);

    while ($stmtDies->fetch()) {
        $parts = explode('|', $valorDies);

        if (count($parts) !== 2) {
            continue;
        }

        $horesConfigurades = (int) $parts[0];
        $diesOberts = (int) $parts[1];

        if (!isset(
            $diesInscripcioPerHores[$horesConfigurades]
        )) {
            $diesInscripcioPerHores[
                $horesConfigurades
            ] = [];
        }

        if (!in_array(
            $diesOberts,
            $diesInscripcioPerHores[$horesConfigurades],
            true
        )) {
            $diesInscripcioPerHores[
                $horesConfigurades
            ][] = $diesOberts;
        }
    }

    $connexio->closeStmt();
}


/* =========================================================
 * 12. PACKS AMB INSCRIPCIÓ OBERTA
 * ========================================================= */

if (
    !$qualsevolEdicio &&
    !empty($packsCandidats) &&
    $ed !== null
) {
    $anySeleccionat = (int) $ed[2];
    $mesSeleccionat = (string) $ed[1];

    $cnsInscripcions = "
        SELECT DISTINCT
            p.ID_PACK,
            c.HORES,
            c.DATAI

        FROM packs AS p

        INNER JOIN curs AS c
            ON c.ID_CURS = p.ID_CURS

        INNER JOIN aula AS a
            ON a.ID_AULA = c.ID_AULA

        INNER JOIN rel_cuho AS r
            ON r.ID_CUHO = a.ID_CUHO

        INNER JOIN honoraris AS h
            ON h.ID = r.ID_HONO

        WHERE c.ANY = ?
          AND c.MES = ?
          AND c.PUBLIC = 1
          AND p.PUBLIC = 1
          AND c.CURS != 'PROVA'
          AND c.CURS NOT LIKE '%0%'
          AND c.CURS NOT LIKE '%JOR%'
          AND c.ESTAT != '0'
          AND r.ACTIU = 1
          AND (
                a.ID_CUHO IN (17, 13)

                OR (
                    a.ID_CUHO != 17
                    AND h.DNI_TUTOR = 'GENERIC'
                )

                OR (
                    a.ID_CUHO != 17
                    AND h.DNI_TUTOR != 'GENERIC'
                    AND a.AULA = 'A'
                    AND h.PERFIL = 'tutor'
                )
          )
    ";

    if (
        !$stmtInscripcions =
            $connexio->prepare($cnsInscripcions)
    ) {
        throw new Exception('', 2702);
    }

    $stmtInscripcions->bind_param(
        'is',
        $anySeleccionat,
        $mesSeleccionat
    );

    $stmtInscripcions->execute();

    $stmtInscripcions->bind_result(
        $idPackInscripcio,
        $horesEdicio,
        $dataIniciEdicio
    );

    $avui = new DateTime('today');

    while ($stmtInscripcions->fetch()) {
        $idPackInscripcio = (string) $idPackInscripcio;
        $horesEdicio = (int) $horesEdicio;

        if (!isset($packsCandidats[$idPackInscripcio])) {
            continue;
        }

        if (!isset(
            $diesInscripcioPerHores[$horesEdicio]
        )) {
            continue;
        }

        foreach (
            $diesInscripcioPerHores[$horesEdicio]
            as $diesOberts
        ) {
            $dataLimit = new DateTime($dataIniciEdicio);

            $dataLimit->modify(
                ($diesOberts >= 0 ? '+' : '').
                $diesOberts.
                ' days'
            );

            if ($dataLimit > $avui) {
                $packsAmbInscripcioOberta[
                    $idPackInscripcio
                ] = true;

                break;
            }
        }
    }

    $connexio->closeStmt();
}


/* =========================================================
 * 13. APLICACIÓ DELS FILTRES
 * ========================================================= */

foreach ($packsCandidats as $idPack => $dadesPack) {
    /*
     * Edició i inscripcions obertes
     */
    $compleixEdicio =
        $qualsevolEdicio ||
        isset($packsAmbInscripcioOberta[$idPack]);

    /*
     * Nivells
     */
    $compleixNivell =
        empty($nivellsFiltres) ||
        !empty(
            array_intersect(
                $nivellsPerPack[$idPack] ?? [],
                $nivellsFiltres
            )
        );

    /*
     * Temes
     */
    $compleixTema =
        empty($temesFiltres) ||
        !empty(
            array_intersect(
                $temesPerPack[$idPack] ?? [],
                $temesFiltres
            )
        );

    /*
     * Perfils
     */
    $compleixPerfil =
        empty($perfilsFiltres) ||
        !empty(
            array_intersect(
                $perfilsPerPack[$idPack] ?? [],
                $perfilsFiltres
            )
        );

    /*
     * CDD
     */
    $teCDD =
        $dadesPack['cdd'] !== null &&
        $dadesPack['cdd'] !== '' &&
        $dadesPack['cdd'] != 0;

    $compleixCDD =
        (int) $cdd !== 1 ||
        $teCDD;

    /*
     * Pack nou
     */
    $compleixNou = true;

    if ((int) $nousCursos === 1) {
        $compleixNou = false;

        if (
            $valorPackNou !== null &&
            $dadesPack['dataCreate'] !== null &&
            $dadesPack['dataCreate'] !== ''
        ) {
            $dataDeixaDeSerNou = strtotime(
                $valorPackNou,
                strtotime($dadesPack['dataCreate'])
            );

            $compleixNou =
                $dataDeixaDeSerNou !== false &&
                $dataDeixaDeSerNou > strtotime(date('Y-m-d'));
        }
    }

    /*
     * Si compleix tots els filtres, creem únicament
     * el Pack lleuger necessari per a LlistatCursos.
     */
    if (
        $compleixEdicio &&
        $compleixNivell &&
        $compleixTema &&
        $compleixPerfil &&
        $compleixCDD &&
        $compleixNou
    ) {
        $llistatCursosPacks[] = new Pack(
            $idPack,
            $dispositiu, true
        );
    }
}


/* =========================================================
 * 14. TANCAMENT
 * ========================================================= */

$connexio->desconectarBD();

?>
