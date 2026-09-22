<?php
/**
  * @class Jornada
  * @brief Conté tota la informació relacionada amb una Jornada.
*/
class JornadaStmt {
  private $codi; /** Text Codi d'una jornada ex: ACRE */
  private $titol; /** Text El titol de la jorna. ex. Alumnat amb Altes Capacitats */
  private $subtitol; /** Text El subtitol de la jornada. ex. Alumnat amb Altes Capacitats */
  private $carrec; /** Text El nom de la persona a càrrec de la jornada. ex. FB */
  private $ciutat; /** Text La ciutat on es fa la jornada*/
  private $presentacio; /** string Presentació dla jornada */
  private $destinataris; /** string Destinataris dla jornada */
  private $objectius; /** string Objectius dla jornada */
  // private $programa_presencial; /** string Programa dla jornada */
  // private $programa_virtual; /** string Programa dla jornada */
  private $ponents; /** Text Els docents que porten la jornada */
  private $lloc; /** Text El lloc on es fa la jornada */
  private $com_arribar; /** Text Com arribar a la jornada presencial */
  private $hores; /** Numero Les hores de la jornada ex: 40 */
  private $data_inici; /** Text Data d'inici de la jornada */
  private $desc_temps; /* Text El descompte abans d'una data determinada ex: 2020-04-14 */
  private $frac; /* Numero 1 Si existeix la posibilitat de fraccionar el pagament, 0 en cas contrari*/
  private $id_preu; /** Numero El ID preu que correspon a la jornada. Per poder obtenir el preu d'una jornada cal anar a buscar a la taula preus, tots els registres amb NUM = ID_PREU  */
  private $video; /** Video El video de la jornada. Si no n'hi ha, valdrà null  */
  private $img_large; /** Imatge La imatge allargada de la jornada.  */
  private $img_small; /** Imatge La imatge rectangular de la jornada. */
  private $url; /** URL L'enllaç de la jornada. Si no n'hi ha, valdrà null  */
  private $dispositiu; /** string Mobil si el dispositiu és mobil i altrament, ordinador */
  private $estat; /** string Mobil si el dispositiu és mobil i ordindador si el dispositiu és mobil  */

  /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

  public function __construct($url, $dispositiu) {
    $this->url = $url;
    $this->dispositiu = $dispositiu;

    /* Obtenim la ID amigable */
    $id_amigable = $url->obtenirID();

    /* Fem la connexió a la BD */
    require_once 'ConnexioBBDD_PreparedStatment.php';
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

    /* Prepares la consulta*/
    $consultaInfoJornades = "SELECT CODI_CURS, TITOL, SUBTITOL, TEXT_DATA, CIUTAT,
      PRESENTACIO, DESTINATARIS, OBJECTIUS, PROGRAMA, NOM_PONENT, PONENTS, LLOC,
      COM_ARRIBAR, DESCOMPTE_TEMPS, FRAC, ID_IMG_LARGE, ID_IMG_SMALL, ID_VIDEO, HORES,
      ID_PREU, DATAI, ESTAT FROM jornades WHERE ID_AMIGABLE=? AND ESTAT=?";
    $sentencia = $connexio->prepare($consultaInfoJornades);
    /* Passas els parametres de la consulta*/
    $sentencia->bind_param("dd", $id_amigable, $estat);
    /* Passes els valors de la consulta */
    $estat = 1;
    /* Executes la consulta */
    $sentencia->execute();
    /* Guardes el resultat */
    $sentencia->store_result();
    /* Obtens el nombre de files */
    if ( $sentencia->num_rows() <= 0 ) {
      throw new Exception("La jornada no està disponible.",101);
    }
    /* Vincules les variables de resultat */
    $sentencia->bind_result($codi_curs, $titol, $subtitol, $text_data, $ciutat,
    $presentacio, $destinataris, $objectius, $programa, $nom_ponent, $ponents,
    $lloc, $com_arribar, $descompte_temps, $frac, $id_img_large, $id_img_small,
    $id_video, $hores, $id_preu, $datai, $estat);
    /* Obtens els valors */
    $sentencia->fetch();
    $this->estat = 1;

    require_once 'Text.php';
    if ($codi_curs!=null and $codi_curs!='')
      $this->codi = new Text($codi_curs);
    else
      $this->codi = null;

    if ($titol!=null and $titol!='')
      $this->titol = new Text($titol);
    else
      $this->titol = null;

    if ($subtitol!=null and $subtitol!='')
      $this->subtitol = new Text($subtitol);
    else
      $this->subtitol = null;

    if ($nom_ponent!=null and $nom_ponent!='')
      $this->carrec = new Text($nom_ponent);
    else
      $this->carrec = null;

    if ($ciutat!=null and $ciutat!='')
      $this->ciutat = new Text($ciutat);
    else
      $this->ciutat = null;

    if ($presentacio!=null and $presentacio!='')
      $this->presentacio = new Text($presentacio);
    else
      $this->presentacio = null;

    if ($destinataris!=null and $destinataris!='')
      $this->destinataris = new Text($destinataris);
    else
      $this->destinataris = null;

    if ($objectius!=null and $objectius!='')
      $this->objectius = new Text($objectius);
    else
      $this->objectius = null;

    if ($programa!=null and $programa!='')
      $this->programa = new Text($programa);
    else
      $this->programa = null;

    if ($ponents!=null and $ponents!='')
      $this->ponents = new Text($ponents);
    else
      $this->ponents = null;

    if ($lloc!=null and $lloc!='')
      $this->lloc = new Text($lloc);
    else
      $this->lloc = null;

    if ($com_arribar!=null and $com_arribar!='')
      $this->com_arribar = new Text($com_arribar);
    else
      $this->com_arribar = null;

    require_once "Numero.php";

    if ($hores!=null and $hores!='')
      $this->hores = new Numero($hores);
    else
      $this->hores = null;

    if ($datai!=null and $datai!='')
      $this->data_inici = new Text($datai);
    else
      $this->data_inici = null;

    if ($descompte_temps!=null and $descompte_temps!='')
      $this->desc_temps = new Text($descompte_temps);
    else
      $this->desc_temps = null;

    if ($frac!=null and $frac!='')
      $this->frac = new Numero($frac);
    else
      $this->frac = null;

    if ($id_preu!=null and $id_preu!='')
      $this->id_preu = new Numero($id_preu);
    else
      $this->id_preu = null;

    require_once 'Video.php';
    if ($id_video!=null and $id_video!='')
      $this->video = new Video($id_video);
    else
      $this->video = null;

    require_once 'Imatge.php';
    if ($id_img_large!=null and $id_img_large!='')
      $this->img_large = new Imatge($id_img_large);
    else
      $this->img_large = null;

    if ($id_img_small!=null and $id_img_small!='')
      $this->img_small = new Imatge($id_img_small);
    else
      $this->img_small = null;

    /* Tanques la sentencia */
    $connexio->closeStmt();
    /* Tancas la connexió */
    $connexio->desconectarBD();
  }

  /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

  /*
    * @brief Obtens el titol
    * @return Si la jornada no té titol, envia l'excepció «No existeix el titol de la jornada»
  */
  private function obtenirTitol() {
    if ($this->titol==null) {
      throw new Exception("La jornada no està disponible", 103);
    }

    return $this->titol;
  }

  /*
    * @brief Obtens el subtitol
    * @return Si la jornada no té subtitol, retora null;
  */
  private function obtenirSubTitol() {
    return $this->subtitol;
  }

  /*
    * @brief Obtens el subtitol
    * @return Si la jornada no té subtitol, retora null;
  */
  private function obtenirCarrec() {
    return $this->carrec;
  }

  /*
    * @brief Obtens la data d'inici
    * @return Si la jornada no té data d'inici, envia l'excepció «No existeix la data d'inici de la jornada»
  */
  private function obtenirDataInici() {
    if ($this->data_inici==null) {
      throw new Exception("La jornada no està disponiblea", 104);
    }

    return $this->data_inici;
  }

  /*
    * @brief Obtens la ciutat de la jornada
    * @return Si la jornada no té ciutat, envia l'excepció «No existeix la ciutat de la jornada»
  */
  private function obtenirCiutat() {
    if ($this->ciutat==null) {
      throw new Exception("La jornada no està disponible", 105);
    }

    return $this->ciutat;
  }

  /*
    * @brief Obtens la imatge llarga de la jornada
    * @return Si la jornada no té imatge llarga, envia l'excepció «No existeix la imatge llarga de la jornada»
  */
  private function obtenirImgLarge() {
    if ($this->img_large==null) {
      throw new Exception("La jornada no està disponible", 107);
    }

    return $this->img_large;
  }

  /*
    * @brief Obtens el url de la jornada
    * @return Si la jornada no té url, envia l'excepció «No existeix el url de la jornada»
  */
  private function obtenirUrl() {
    if ($this->url==null) {
      throw new Exception("La jornada no està disponible", 107);
    }

    return $this->url;
  }

  /*
      * @brief Obtens la presentacio
      * @return Obtens la presentacio
      * @throws Si la jornada no té una presentacio, envia l'excepció «No existeix la presentacio de la jornada»
  */
  private function obtenirPresentacio() {
    if ($this->presentacio==null) {
      throw new Exception("La jornada no està disponible", 108);
    }
    return $this->presentacio;
  }

  /*
      * @brief Obtens els destinataris
      * @return Obtens els destinataris
      * @throws Si el curs no té un destinatari, envia l'excepció «No existeix xxxx de la jornada»
  */
  private function obtenirDestinataris() {
    if ($this->destinataris==null) {
      throw new Exception("La jornada no està disponible", 109);
    }

    return $this->destinataris;
    }

  /*
      * @brief Obtens els objectius
      * @return Obtens els objectius
      * @throws Si la jornada  no té un objectiu, envia l'excepció «No existeix els objectius de la jornada»
  */
  private function obtenirObjectius() {
    if ($this->objectius==null) {
      throw new Exception("La jornada no està disponible", 110);
    }

    return $this->objectius;
    }

  /*
  * @brief Obtens el video
  * @return Si la jornada té un video, retorna el video de la jornada. Altrament, null.
  * @throws Si la jornada no té un video, envia l'excepció «No existeix el video de la jornada»
  */
  private function obtenirVideo() {
    return $this->video;
  }

  /*
      * @brief Obtens el programa
      * @return Obtens el programa
      * @throws Si el curs no té un programa, envia l'excepció «No existeix el programa de la jornada»
  */
  private function obtenirPrograma() {
    if ($this->programa==null) {
      throw new Exception("La jornada no està disponible", 111);
    }

    return $this->programa;
  }

  /*
  * @brief Obtens els ponents
  * @return Obtens els ponents
  * @throws Si la jornada no té ponents, envia l'excepció «No existeix els ponents»
  */
  private function obtenirPonents() {
    if ($this->ponents==null) {
      throw new Exception("La jornada no està disponible", 112);
    }

    return $this->ponents;
  }

  /*
    * @brief Obtens el lloc de la jornada
    * @return Si la jornada no té lloc, envia l'excepció «No existeix el lloc de la jornada»
  */
  private function obtenirLloc() {
    if ($this->lloc==null) {
      throw new Exception("La jornada no està disponible", 113);
    }

    return $this->lloc;
  }

  /*
    * @brief Obtens el com arribar-hi
    * @return Obtens el com arribar-hi de la jornada
  */
  private function obtenirComArribar() {
    return $this->com_arribar;
  }

  /*
    * @brief Obtens el preu de la jornada
    * @return Si la jornada no té el preu, envia l'excepció «No existeix el preu de la jornada»
  */
  private function obtenirIdPreu() {
    if ($this->id_preu==null) {
      throw new Exception("La jornada no està disponible", 114);
    }

    return $this->id_preu;
  }

  /*
    * @brief Obtens la data d'anticipi de la jornada
    * @return Obtens la data d'anticipi de la jornada
  */
  private function obtenirDataAnticipi() {
    return $this->desc_temps;
  }

  /*
    * @brief Consulta el valor de la possibilitat de fraccionar
    * @return Obtens el valor de la possibilitat de fraccionar i si no existeix, envia l'excepció «La jornada no està disponibl error 118»
  */
  private function obtenirFrac() {
    if ($this->frac==null) {
      throw new Exception("La jornada no està disponible", 118);
    }

    return $this->frac;
  }

  /*
    * @brief Consulta si existeix la possibilitat de fraccionar
    * @return Obtens TRUE si es pot fraccionar, altramanet retorna FALSE
  */
  private function esPotFraccionar() {
    if ($this->obtenirFrac()->obtenirNumero() == 1)
      return true;
    else
      return false;
  }

  /*
    * @brief Consulta si es visualitza des d'un mòbil
    * @return Obtens TRUE si es visualitza des d'un mòbil, altramanet retorna FALSE
  */

  private function __isMobile() {
    if ($this->dispositiu == "mobil")
      return true;
    else
      return false;
  }

  /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

  /*
      * @brief Mostra la pàgina de Jornada
      * @return Mostra la estructura i la informació de la jornada
  */
  public function mostrarJornada() {
    $mostrar = "<div class='prisma-jornada separacio-peu'>";

    if (!$this->__isMobile()) {
      $mostrar .= "<div class='container'><div class='row'>";

      $mostrar .= "<div class='col-md-12 titol-jornada'><h2 class='h1'>Jornades PrisMa</h2></div>";

      $mostrar .= $this->mostrarTitol(); //Mostrar TITOL
      $mostrar .= $this->mostrarImgLarge(); //Mostrar imatge
      $mostrar .= $this->mostrarInformacio(); //Mostrar el bloc de la informació de la jornada
      $mostrar .= "</div></div>";
    }
    else {
      $mostrar .= $this->mostrarVideo();
      $mostrar .= $this->mostrarTitol();
      $mostrar .= $this->mostrarInformacio();
      $mostrar .= $this->mostrarPartFixe();
    }
    $mostrar .="</div>";
    return $mostrar;
  }

  /*
      * @brief Mostra el video d'una jornada
      * @return Mostra el video d'una jornada
  */
  public function mostrarVideo() {
    $video = $this->obtenirVideo();
    $mostrar = $video->mostrarVideo();
    return $mostrar;
  }

  /*
      * @brief Mostra la informació del titol d'una jornada
      * @return Mostra la informació del titol, subtitol, data i lloc d'una jornada
  */
  public function mostrarTitol() {
    $data_inici = $this->obtenirDataInici()->obtenirDiaSetmana()." ".$this->obtenirDataInici()->convertirDataLlarga();
    $subtitol = $this->obtenirSubTitol();
    $carrec = $this->obtenirCarrec();

    $mostrar = "<div class='col-md-12 titol'>";
    $mostrar .= "<h1>".$this->obtenirTitol()->obtenirText()."</h1>";
    if ($subtitol != null)
      $mostrar .= "<p class='subtitol'>".$subtitol->obtenirText()."</p>";
    if ($carrec != null)
      $mostrar .= "<p class='carrec'>A càrrec de ".$carrec->obtenirText()."</p>";
    if ($carrec != null)
      $mostrar .= "<p class='data-ciutat'>".$data_inici.", ".$this->obtenirCiutat()->obtenirText().".</p>";
    else
      $mostrar .= "<p class='data-ciutat'>".$data_inici.", ".$this->obtenirCiutat()->obtenirText().".</p>";
    $mostrar .= "</div>";
    return $mostrar;
  }

  /**
    * @brief Mostra el banner de la jornada
    * @return Mostra la imatge llarga de la jornada en forma de banner
  */
  public function mostrarImgLarge() {
    $mostrar = "<div class='col-md-12 banner-jornada'>";
    $mostrar .= "<img role='img' class='width-100 border-radius-2' src=\"https://www.prisma.cat".$this->obtenirImgLarge()->obtenirLink()."\" alt=\"".$this->obtenirImgLarge()->obtenirAlt()."\"/>";
    $mostrar .= "</div>";
    return $mostrar;
  }

  /**
    * @brief Mostra el nom de l'etiqueta de Ponents
    * @return Mostra el nom de l'etiqueta de Ponents
  */
  public function mostrarNomPonent() {
    if ($this->obtenirCarrec() != null)
      $nom_ponents = "Ponent";
    else
      $nom_ponents = "Ponents";
    return $nom_ponents;
  }

  /**
      * @brief Mostra el contingut de la jornada
      * @return Mostra el contingut de la jornada en forma de banner
  */
  public function mostrarInformacio() {
    $link_curs = "https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
    $parts_link = explode('/',$link_curs);
    $part_url_amigable = $parts_link[count($parts_link)-1];
    $link_inscripcio = "https://www.prisma.cat/inscripcio/".$part_url_amigable."";
    $nom_ponents = $this->mostrarNomPonent();

    if (!$this->__isMobile()) {
      $mostrar = "<div class='col-md-12'>";
      $mostrar .= "<ul class='nav nav-tabs nav-justified' role='tablist' aria-label='Contingut de la jornada'>";
      $mostrar .= "<li class='active'><a class='text-centrat negreta500' href='#descripcio' data-toggle='tab' role='tab' aria-controls='descripcio' id='tab-descripcio'><i class='fa fa-bookmark'></i> Descripció</a></li>";
      $mostrar .= "<li class=''><a class='text-centrat negreta500' href='#programa' data-toggle='tab' role='tab' aria-controls='programa' id='tab-programa'><i class='fa fa-cube'></i> Programa</a></li>";
      $mostrar .= "<li class=''><a class='text-centrat negreta500' href='#ponents' data-toggle='tab' role='tab' aria-controls='ponents' id='tab-ponents'><i class='fa fa-user'></i> ".$nom_ponents."</a></li>";
      $mostrar .= "<li class=''><a class='text-centrat negreta500' href='#lloc' data-toggle='tab' role='tab' aria-controls='lloc' id='tab-lloc'><i class='fa fa-map-marker-alt'></i> Lloc</a></li>";
      $mostrar .= "<li class=''><a class='text-centrat negreta500' href='#preu' data-toggle='tab' role='tab' aria-controls='preu' id='tab-preu'><i class='fa fa-credit-card'></i> Preus</a></li>";
      $mostrar .= "<li class=''><a class='text-centrat inscripcio color-white negreta500' href='".$link_inscripcio."' id='tab-inscripcio'><i class='fas fa-edit'></i> Inscripció</a></li>";
      $mostrar .= "</ul>";
      $mostrar .= "<div class='tab-content'>";
      $mostrar .= $this->mostrarDescripcio();
      $mostrar .= $this->mostrarPrograma();
      $mostrar .= $this->mostrarPonents();
      $mostrar .= $this->mostrarLloc();
      $mostrar .= $this->mostrarPreu();
      $mostrar .= "</div>";
      $mostrar .= "<p><a role='link' class='mostrar-tots' href='https://www.prisma.cat/jornades' target='_self' title='Visualitza totes les jornades'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-left'></i> Mostra totes les jornades</a></p>";
      $mostrar .= "</div>";
    }
    else {
      $mostrar .= "<div id='accord' class='col-md-12'><div class='panel-group' id='accordion' role='tablist' aria-multiselectable='true'>";
      $mostrar .= $this->mostrarDescripcio();
      $mostrar .= $this->mostrarPrograma();
      $mostrar .= $this->mostrarPonents();
      $mostrar .= $this->mostrarLloc();
      $mostrar .= $this->mostrarPreu();
      $mostrar .= "</div></div>";
    }
    return $mostrar;
  }

  /**
      * @brief Mostra la pestanya descripció d'una jornada
      * @return Mostra la pestanya descripció d'una jornada
  */
  private function mostrarDescripcio() {
    $presentacio = $this->obtenirPresentacio()->obtenirText();
    $video = $this->obtenirVideo();
    $destinataris = $this->obtenirDestinataris()->obtenirText();
    $objectius = $this->obtenirObjectius()->obtenirText();

    if (!$this->__isMobile()) {
      $mostrar = "<div class='tab-pane active fade in' id='descripcio' role='tabpanel' aria-labelledby='tab-descripcio'>";
      $mostrar .= "<div class='col-md-12 content-tab-pane'>";
      $mostrar .= "<h2>Presentació</h2>";
      $mostrar .= $presentacio;
      if ($video != null)
        $mostrar .= $video->mostrarVideo();
      $mostrar .= "<h2>Destinataris</h2>".$destinataris;
      $mostrar .= "<h2>Objectius</h2>".$objectius;
      $mostrar .= "</div><div class='clear-both'></div></div>";
    }
    else {
      $mostrar .= "<div class='panel panel-default'>";
      $mostrar .= "<div class='panel-heading collapsed' role='tab' id='heading-desc' data-toggle='collapse' data-target='#collapse-desc' href='#collapse-desc' aria-expanded='false' aria-controls='collapse-desc'>";
      $mostrar .= "<p class='panel-title negreta500'><i class='fa fa-bookmark'></i> Descripció<i class='fas fa-plus posicio-dreta'></i></p>";
      $mostrar .= "</div><div id='collapse-desc' class='panel-collapse collapse' role='tabpanel' aria-labelledby='heading-desc' aria-expanded='false' style='height: 0px;'><div class='panel-body'>";
      //******** CONTINGUT DE L'ACCORDION *********
      $mostrar .= "<h2>Presentació</h2>";
      $mostrar .= $presentacio;
      $mostrar .= "<h2>Destinataris</h2>".$destinataris;
      $mostrar .= "<h2>Objectius</h2>".$objectius;
      //******** FI CONTINGUT DE L'ACCORDION *********
      $mostrar .= "</div></div></div>";
    }
    return $mostrar;
  }

  /**
      * @brief Mostra la pestanya Programa d'una jornada
      * @return Mostra la pestanya Programa d'una jornada
  */
  private function mostrarPrograma() {
    $contingut_prgrama = "<h2>Programa</h2>";
    $contingut_prgrama .= $this->obtenirPrograma()->obtenirText();
    if (!$this->__isMobile()) {
      $mostrar = "<div class='tab-pane fade' id='programa' role='tabpanel' aria-labelledby='tab-programa'>";
      $mostrar .= "<div class='col-md-12 content-tab-pane'>";
      $mostrar .= $contingut_prgrama;
      $mostrar .= "</div><div class='clear-both'></div></div>";
    }
    else {
      $mostrar .= "<div class='panel panel-default'>";
      $mostrar .= "<div class='panel-heading collapsed' role='tab' id='heading-prog' data-toggle='collapse' data-target='#collapse-prog' href='#collapse-prog' aria-expanded='false' aria-controls='collapse-prog'>";
      $mostrar .= "<p class='panel-title negreta'><i class='fa fa-cube'></i> Programa<i class='fas fa-plus posicio-dreta'></i></p>";
      $mostrar .= "</div><div id='collapse-prog' class='panel-collapse collapse' role='tabpanel' aria-labelledby='heading-prog' aria-expanded='false' style='height: 0px;'><div class='panel-body'>";
      //******** CONTINGUT DE L'ACCORDION *********
      $mostrar .= $contingut_prgrama;
      //******** FI CONTINGUT DE L'ACCORDION *********
      $mostrar .= "</div></div></div>";
    }
    return $mostrar;
  }

  /**
      * @brief Mostra la pestanya Ponents d'una jornada
      * @return Mostra la pestanya Ponents d'una jornada
  */
  private function mostrarPonents() {
  $nom_ponents = $this->mostrarNomPonent();
    $contingut_ponents = "<h2>".$nom_ponents."</h2>";
    $contingut_ponents .= $this->obtenirPonents()->obtenirText();
    if (!$this->__isMobile()) {
      $mostrar = "<div class='tab-pane fade' id='ponents' role='tabpanel' aria-labelledby='tab-ponents'><div class='col-md-12 content-tab-pane'>";
      $mostrar .= $contingut_ponents;
      $mostrar .= "</div><div class='clear-both'></div></div>";
    }
    else {
      $mostrar .= "<div class='panel panel-default'>";
      $mostrar .= "<div class='panel-heading collapsed' role='tab' id='heading-pon' data-toggle='collapse' data-target='#collapse-pon' href='#collapse-pon' aria-expanded='false' aria-controls='collapse-pon'>";
      $mostrar .= "<p class='panel-title negreta'><i class='fa fa-user'></i>".$nom_ponents." <i class='fas fa-plus posicio-dreta'></i></p>";
      $mostrar .= "</div><div id='collapse-pon' class='panel-collapse collapse' role='tabpanel' aria-labelledby='heading-pon' aria-expanded='false' style='height: 0px;'><div class='panel-body'>";
      //******** CONTINGUT DE L'ACCORDION *********
      $mostrar .= $contingut_ponents;
      //******** FI CONTINGUT DE L'ACCORDION *********
      $mostrar .= "</div></div></div>";
    }
    return $mostrar;
  }

  /**
      * @brief Mostra la pestanya Lloc d'una jornada
      * @return Mostra la pestanya Lloc d'una jornada
  */
  private function mostrarLloc() {
    $contingut_lloc = "<h2>Lloc</h2>";
    $contingut_lloc .= $this->obtenirLloc()->obtenirText();
    $contingut_lloc .= "<h2>Com arribar-hi</h2>";
    if ($this->obtenirComArribar()!=null)
      $contingut_lloc .= $this->obtenirComArribar()->obtenirText();
    if (!$this->__isMobile()) {
      $mostrar = "<div class='tab-pane fade' id='lloc' role='tabpanel' aria-labelledby='tab-lloc'><div class='col-md-12 content-tab-pane lloc'>";
      $mostrar .= $contingut_lloc;
      $mostrar .= "</div><div class='clear-both'></div></div>";
    }
    else {
      $mostrar .= "<div class='panel panel-default'>";
      $mostrar .= "<div class='panel-heading collapsed' role='tab' id='heading-lloc' data-toggle='collapse' data-target='#collapse-lloc' href='#collapse-lloc' aria-expanded='false' aria-controls='collapse-lloc'>";
      $mostrar .= "<p class='panel-title negreta'><i class='fa fa-map-marker-alt'></i> Lloc<i class='fas fa-plus posicio-dreta'></i></p>";
      $mostrar .= "</div><div id='collapse-lloc' class='panel-collapse collapse' role='tabpanel' aria-labelledby='heading-lloc' aria-expanded='false' style='height: 0px;'><div class='panel-body'>";
      //********   CONTINGUT DE L'ACCORDION   *********
      $mostrar .= $contingut_lloc;
      //******** FI CONTINGUT DE L'ACCORDION *********
      $mostrar .= "</div></div></div>";
    }
    return $mostrar;
  }

  /**
      * @brief Mostra la pestanya Preu d'una jornada
      * @return Mostra la pestanya Preu d'una jornada
  */
  private function mostrarPreu() {
    $mostrar = "";
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

    /* Prepares la consulta*/
    $consultaPreu = "SELECT IMPORT, DESCRIPCIO, TIPUS  FROM preu WHERE
      NUM = ? AND (DATAF is null OR (DATAI <=CURRENT_DATE AND
      CURRENT_DATE<=DATAF)) ORDER BY TIPUS";
    $sentencia = $connexio->prepare($consultaPreu);
    /* Passas els parametres de la consulta*/
    $sentencia->bind_param("d", $id_preu);
    /* Passes els calors de la consulta */
    $id_preu = $this->obtenirIdPreu()->obtenirNumero();

    /* Executes la consulta */
    $sentencia->execute();
    /* Guardes el resultat */
    $sentencia->store_result();
    /* Obtens el nombre de files */

    $num_preus = $sentencia->num_rows();

    /* Vincules les variables de resultat */
    $sentencia->bind_result($importCol, $descripcioCol, $tipusCol);

    if ($num_preus == 0) {
      // CAS ERROR
      throw new Exception("El preu de la jornada no està disponible", 115);
    }
    else if ($num_preus == 2) {
      // CAS QUE SIGUIN DOS PREUS: nou alumne i exalumne
      $contingut_preu .= "<h2>Preus</h2>";

      $taula = "<table class='width-100' cellspacing='0' cellpadding='2'>";
      $taula .= "<tbody>";
      /* Obtens els valors */
      while ($sentencia->fetch()) {
        $import = $importCol;
        $tipus = $tipusCol;
        $descripcio = $descripcioCol;

        if ($tipus == 1) { // Cas nou alumne
          $taula .= "<div class='cella1 negreta text-centrat'>".$import." euros</div>";
          $taula .= "<div class='cella2'>".$descripcio.".</div>";
        }
        else if ($tipus == 2) { // Cas ex alumne
          $taula .= "<div class='cella3 negreta text-centrat'>".$import." euros</div>";
          $taula .= "<div class='cella4'>".$descripcio.".</div>";
        }
      }
      $taula .= "<div class='clear-both'></div></div>";

      $contingut_preu .= $taula;
    }
    else if ($num_preus == 4) {
      // CAS QUE SIGUIN 4 PREUS: pagaments anticipat exalumne, pagament anticipat
      // nou alumne, pagament exalumne, pagament nou alumne. Per ex. MUS01

      $data_anticipi = $this->obtenirDataAnticipi();

      $dt = new DateTime($data_anticipi->obtenirText());
      $dt->modify('next day');
      $data_anticipi_dia_plus = new Text($dt->format('Y-m-d'));
      $data_anticipi_dia_seguent = $data_anticipi_dia_plus->convertirDataLlarga();

      if ($data_anticipi==null) {
        throw new Exception("El preu de la jornada no està disponible", 117);
      }

      $taula_exalumnes = "<div class='table'>";
      $taula_noualumnes = "<div class='table'>";

      while ($sentencia->fetch()) {
        $import = $importCol;
        $tipus = $tipusCol;

        if ($tipus == 1) { // Cas nou alumne amb pagament anticipi
          $taula_noualumnes .= "<div class='cella1 negreta text-centrat'>".$import." euros</div>";
          $taula_noualumnes .= "<div class='cella2'>Pagament anticipat (fins al dia ".$data_anticipi->convertirDataLlarga().").</div>";
        }
        else if ($tipus == 2) { // Cas ex alumne amb pagament anticipi
          $taula_exalumnes .= "<div class='cella1 negreta text-centrat'>".$import." euros</div>";
          $taula_exalumnes .= "<div class='cella2'>Pagament anticipat (fins al dia ".$data_anticipi->convertirDataLlarga().").</div>";
        }
        else if ($tipus == 3) { // Cas nou alumne sense pagament anticipi
          $taula_noualumnes .= "<div class='cella3 negreta text-centrat'>".$import." euros</div>";
          $taula_noualumnes .= "<div class='cella4'>Pagament (a partir del dia ".$data_anticipi_dia_seguent.").</div>";
        }
        else if ($tipus == 4) { // Cas ex alumne sense pagament anticipi
          $taula_exalumnes .= "<div class='cella3 negreta text-centrat'>".$import." euros</div>";
          $taula_exalumnes .= "<div class='cella4'>Pagament (a partir del dia ".$data_anticipi_dia_seguent.").</div>";
        }
      }

      $taula_exalumnes .= "<div class='clear-both'></div></div>";
      $taula_noualumnes .= "<div class='clear-both'></div></div>";

      $contingut_preu .= "<h2>Preu per alumnes de PrisMa o amb Carnet Jove</h2>";
      $contingut_preu .= $taula_exalumnes;
      $contingut_preu .= "<h2>Preu per a altres professionals</h2>";
      $contingut_preu .= $taula_noualumnes;

    }
    else {
      //  CAS NO CONTEMPLAT
      throw new Exception("El preu de la jornada no està disponible", 116);
    }

    $text_frac = "";
    if ($this->esPotFraccionar())
      $text_frac = " (amb possibilitat de pagament fraccionat sense recàrrec)";
    $contingut_preu .= "<p>Forma de pagament: transferència, ingrés bancari o targeta".$text_frac.".</p>";

    if (!$this->__isMobile()) {
      $mostrar = "<div class='tab-pane fade' id='preu' role='tabpanel' aria-labelledby='tab-preu'><div class='col-md-12 content-tab-pane'>";
      $mostrar .= $contingut_preu;
      $mostrar .= "</div><div class='clear-both'></div></div>";
    }
    else {
      $mostrar .= "<div class='panel panel-default'>";
      $mostrar .= "<div class='panel-heading collapsed' role='tab' id='heading-preu' data-toggle='collapse' data-target='#collapse-preu' href='#collapse-preu' aria-expanded='false' aria-controls='collapse-preu'>";
      $mostrar .= "<p class='panel-title negreta'><i class='fa fa-credit-card'></i> Preus<i class='fas fa-plus posicio-dreta'></i></p>";
      $mostrar .= "</div><div id='collapse-preu' class='panel-collapse collapse' role='tabpanel' aria-labelledby='heading-preu' aria-expanded='false' style='height: 0px;'><div class='panel-body'>";
      //********   CONTINGUT DE L'ACCORDION  *********
      $mostrar .= $contingut_preu;
      //******** FI CONTINGUT DE L'ACCORDION *********
      $mostrar .= "</div></div></div>";
    }
    /* Tanques la sentencia */
    $connexio->closeStmt();
    /* Tancas la connexió */
    $connexio->desconectarBD();
    return $mostrar;
  }

  /*
    * @brief Obtens la part fixe de la pàgina
    * @return Obtens la part fixe de la pàgina
  */
  private function mostrarPartFixe() {
    $link_curs = "https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
    $parts_link = explode('/',$link_curs);
    $part_url_amigable = $parts_link[count($parts_link)-1];

    $link_inscripcio = "https://www.prisma.cat/inscripcio/".$part_url_amigable."";
    $mostrar = "<div id='part-fixe'><a class='text-centrat inscripcio color-white negreta width-100' href='https://www.prisma.cat/inscripcio/musica-emocio-educacio' id='tab-inscripcio'><i class='fas fa-edit'></i> Inscripció</a><div class='clear-both'></div></div>";
    return $mostrar;
  }

}
?>
