<?php
try {
  $user_course = 0;
  $dniUser = $USER->username;
  $accessActivCampus = 0;

  $shortname = $_REQUEST['shortname'];
  $course = substr( $curs, 4, -3);

  $conMoodle = new ConnexioMoodle();
  $conMoodle->connectarBD();
  $conMoodle2 = new ConnexioMoodle();
  $conMoodle2->connectarBD();
  $conMoodleAntic = new ConnexioMoodleAntic();
  $conMoodleAntic->connectarBD();
  $conIntra = new ConnexioIntranet();
  $conIntra->connectarBD();

  /* buscar parametre */
  $cnsExParam = "SELECT ID FROM params WHERE PARAM = ? AND VALOR = ?
        AND DATAI <= CURRENT_TIMESTAMP AND
        (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)";

  if ( $stmt = $conIntra->prepare( $cnsExParam ) ) {
    $stmt->bind_param('ss', $param, $dniUser);
    $param = 'access-activitats-campus-nou';
    $stmt->execute();
    $stmt->store_result();
    if ( $stmt->num_rows() > 0 ) {
      $accessActivCampus = 1;
    }
  }
  else {
    throw new Exception('', 11104);
  }

  if ( !$accessActivCampus ) {
    $cnsIdUserMdl = "SELECT id FROM mdl_user where username like ?";
    $cnsCourseLabMdl = "SELECT userid FROM mdl_user_enrolments WHERE enrolid =
      ( SELECT e.id FROM mdl_enrol AS e WHERE courseid =
          (SELECT c.id FROM mdl_course as c where shortname like ?)
          and e.enrol = 'manual'
      )";

    if ( $stmt=$conMoodle->prepare( $cnsIdUserMdl ) ) {
      $stmt->bind_param("d", $username);
      $username = $dniUser."%";
      $stmt->execute();
      $stmt->bind_result($idUserMdl);
      $stmt->fetch();
      $conMoodle->closeStmt();
    }
    else {
      throw new Exception('', 11105);
    }

    $is_user = 0;
    /* Si és algun tutor que està al Laboratori, pot accedir a l'activitat */
    //Obtenim les persones que es troben en el curs $course del laboratori
    if ( $stmt=$conMoodle->prepare( $cnsCourseLabMdl ) ) {
      $stmt->bind_param("s", $shortnameLab);
     // $shortnameLab = "%".$course." | LAB%";
	  $shortnameLab = $course." | LAB%";
      $stmt->execute();
      $stmt->store_result();
      //Si existeix el lab, es mira en en el campus nou, sino mirem a l'antic
      if ( $stmt->num_rows() > 0 ) {
        $stmt->bind_result($idUserMdlLab);
        while ( $stmt->fetch() ) {
          if ( $idUserMdlLab == $idUserMdl )
            $is_user = true;
          }
      }
      else {
        if ( $stmtMdlAntic=$conMoodleAntic->prepare( $cnsIdUserMdl ) ) {
          $stmtMdlAntic->bind_param("d", $username);
          $username = $dniUser."%";
          $stmtMdlAntic->execute();
          $stmtMdlAntic->bind_result($idUserMdlAntic);
          $stmtMdlAntic->fetch();
          $conMoodleAntic->closeStmt();
        }
        else {
          throw new Exception('', 11105);
        }
        if ( $stmtMdlAntic=$conMoodleAntic->prepare( $cnsCourseLabMdl ) ) {
          $stmtMdlAntic->bind_param("s", $shortnameLab);
          //$shortnameLab = "%".$course." | LAB%";
		  $shortnameLab = $course." | LAB%";
          $stmtMdlAntic->execute();
          $stmtMdlAntic->bind_result($idUserMdlAnticLab);
          while ( $stmtMdlAntic->fetch() ) {
            if ( $idUserMdlAnticLab == $idUserMdlAntic )
              $is_user = true;
          }
          $conMoodleAntic->closeStmt();
        }
      }
      $conMoodle->closeStmt();
    }
    else {
      throw new Exception('', 11106);
    }

    if ( $is_user )
      $user_course = 1;
    else {
      //Busques tots els cursos oberts amb codi $curs
      $cnsCourseVisibleMdl = "SELECT shortname FROM mdl_course where shortname like ? and visible=1";
      $cnsUserInCourseMdl = "SELECT mdl_user_enrolments.id FROM mdl_user_enrolments WHERE userid = ? and enrolid =
        (SELECT e.id FROM mdl_enrol AS e WHERE courseid =
            (SELECT c.id FROM mdl_course as c where shortname like ?)
            and e.enrol = 'manual'
        )";

      //Per cada curs obert i mentre no estigui inscrit, comproves si esta inscrit.
      //Si està inscrit, $user_course = true;
      if ( $stmt=$conMoodle->prepare( $cnsCourseVisibleMdl ) ) {
        $stmt->bind_param("s", $shortnameCourse); 
        //$shortnameCourse = "%".$course."%";
		$shortnameCourse = $course."%";
        $stmt->execute();
        $stmt->bind_result($shortnameMdlCourse);
        while ( $stmt->fetch() ) {
          if ( $stmt2=$conMoodle2->prepare( $cnsUserInCourseMdl ) ) {
            $stmt2->bind_param("ds", $idUserMdl, $shortnameMdlCourse);
            $stmt2->execute(); echo "<br>curs: ".$course;
            $stmt2->store_result(); echo "<br>iduser: ".$idUserMdl."-".$shortnameMdlCourse."-".$stmt2->num_rows();
            if ( $stmt2->num_rows() > 0 ) {
              $is_user = true; echo "<br>is user: ".$is_user;
            }
            $conMoodle2->closeStmt();
          }
          else {
            throw new Exception('', 11108);
          }
        }
        $conMoodle->closeStmt();
      }
      else {
        throw new Exception('', 11107);
      }

    }

  }
  else {
    $user_course = 1;
  }
  $conIntra->desconectarBD();
  $conMoodle2->desconectarBD();
  $conMoodle->desconectarBD();
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
