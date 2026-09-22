<?php

   /**
       * @class Contacte
       * @brief Conté tota la informació relacionada amb un Contacte
   */
   class Contacte {
       private $dispositiu; /**< dispositiu */
       private $adreca; /**< Text Adreça de contacte */
       private $cp; /**< Text Codi postal de contacte */
       private $poble; /**< Text Població de contacte */
       private $fix; /**< Número Telèfon fix de contacte */
       private $mbl; /**< Número Telèfon mòbil de contacte */
       private $email; /**< Text E-mail de contacte */
       private $horari; /**< Text Horari habitual de contacte */
       private $hestiu; /**< Text Horari estiu de contacte */
       private $mapa; /**< Text Google Maps de contacte */

       /* ####################### FUNCIONS CONSTRUCTORS ####################### */

       /*
       * @brief Constructor de la classe.
       * @param $id La id corresponent al contacte
       * @return El contacte està creat amb la informació del codi i de la durada obtinguda de la taula VIDEOS
       */
       public function __construct($dispositiu) {
          $this->dispositiu = $dispositiu;
          require_once 'ConnexioBBDD_PreparedStatment.php';
          $connexio = new ConnexioBBDDSTMT();
          $connexio->connectarBD();

          /* Es busquen les dades del registre que la ID de la taula CONTACTE correspon a la ID passada per paràmetre */
          $cnsCont = "SELECT ADRECA, CP, POBLE, FIX, MBL, EMAIL, HORARI, HESTIU, MAPA
             FROM contacte WHERE ESTAT=1";
          $stmt = $connexio->prepare($cnsCont);
          $stmt->execute();
          $stmt->bind_result($adreca, $cp, $poble, $fix, $mbl, $email, $horari, $hestiu, $mapa);
          $stmt->fetch();
          $connexio->closeStmt();

          if ($adreca!=null and $adreca!='')
             $this->adreca = new Text($adreca);
          else
             $this->adreca = null;

          if ($cp!=null and $cp!='')
             $this->cp = new Text($cp);
          else
             $this->cp = null;

          if ($poble!=null and $poble!='')
             $this->poble = new Text($poble);
          else
             $this->poble = null;

          if ($fix!=null and $fix!='')
             $this->fix = new Text($fix);
          else
             $this->fix = null;

          if ($mbl!=null and $mbl!='')
             $this->mbl = new Text($mbl);
          else
             $this->mbl = null;

          if ($horari!=null and $horari!='')
             $this->horari = new Text($horari);
          else
             $this->horari = null;

          if ($hestiu!=null and $hestiu!='')
             $this->hestiu = new Text($hestiu);
          else
             $this->hestiu = null;

          if ($mapa!=null and $mapa!='')
             $this->mapa = new Text($mapa);
          else
             $this->mapa = null;

          /* Es busca el registre on la DATA ACTUAL es trobi entre la DATAI i DAFAF o que sigui 3 dies abans de la DATAI */
         $cnsAtencio = "SELECT ATENCIO FROM festius_text AS ft INNER JOIN festius_dates AS fd
            ON ft.ID=fd.ID_FESTIU WHERE DATAI-3<=CURRENT_TIMESTAMP
            AND (CURRENT_TIMESTAMP<=DATAF OR DATAF LIKE '%0000-00-00%') ORDER BY DATAI DESC LIMIT 1";
         $stmt = $connexio->prepare($cnsAtencio);
         $stmt->execute();
         $stmt->store_result();
         if ($stmt->num_rows() > 0) {
            $stmt->bind_result($atencio);
            $stmt->fetch();
            if ($atencio!=null and $atencio!='')
               $this->atencio = new Text($atencio);
            else
               $this->atencio = null;
         }
         else {
            $this->atencio = null;
         }

         $connexio->closeStmt();
         $connexio->desconectarBD();
       }

       /* ####################### FUNCIONS CONSULTORES ####################### */

       /*
          * @brief Obtenim el codi del contacte
          * @return El codi del contacte
          * @throws Si el contacte no té un codi, envia l'excepció 0501
       */
       private function __obtenirCodi() {
          if ($this->codi==null) {
             throw new Exception('', 0501);
          }
          return $this->codi;
       }

       /*
          * @brief Obtenim l'adreça del contacte
          * @return L'adreça del contacte
          * @throws Si l'adreça no és un text, envia l'excepció 0502
       */
       private function __obtenirAdreca() {
          if ($this->adreca==null) {
             throw new Exception('', 0502);
          }
          return $this->adreca;
       }

       /*
          * @brief Obtenim el codi postal del contacte
          * @return El codi postal del contacte
          * @throws Si el codi postal no és un text, envia l'excepció 0503
       */
       private function __obtenirCp() {
          if ($this->cp==null) {
             throw new Exception('', 0503);
          }
          return $this->cp;
       }

       /*
          * @brief Obtenim la població del contacte
          * @return La població del contacte
          * @throws Si la població no és un text, envia l'excepció 0504
       */
       private function __obtenirPoble() {
          if ($this->poble==null) {
             throw new Exception('', 0504);
          }
          return $this->poble;
       }

       /*
          * @brief Obtenim el telèfon fix del contacte
          * @return El telèfon fix del contacte
          * @throws Si el telèfon fix no té un número, envia l'excepció 0505
       */
       private function __obtenirFix() {
          if ($this->fix==null) {
             throw new Exception('', 0505);
          }
          return $this->fix;
       }

       /*
          * @brief Obtenim el telèfon fix del contacte
          * @return El telèfon fix del contacte
          * @throws Si el telèfon fix no té un número, envia l'excepció 0505
       */
       private function __obtenirMbl() {
          if ($this->mbl==null) {
             throw new Exception('', 0506);
          }
          return $this->mbl;
       }

       /*
          * @brief Obtenim l'horari del contacte
          * @return L'horari del contacte
          * @throws Si l'horari no és un text, envia l'excepció 0507
       */
       private function __obtenirHorari() {
          if ($this->horari==null) {
             throw new Exception('', 0507);
          }
          return $this->horari;
       }

       /*
          * @brief Obtenim l'horari d'estiu del contacte
          * @return L'horari d'estiu del contacte
          * @throws Si l'horari d'estiu no és un text, envia l'excepció 0508
       */
       private function __obtenirHestiu() {
          return $this->hestiu;
       }

       /*
          * @brief Obtenim el mapa del contacte
          * @return El mapa del contacte
          * @throws Si el mapa no és un text, envia l'excepció 0509
       */
       private function __obtenirMapa() {
          return $this->mapa;
       }

       /*
          * @brief Obtenim el text d'atenció usuari
          * @return El text d'atenció usuari
          * @throws Si el text t'atenció usuari no és un text, envia l'excepció 0510
       */
       private function __obtenirAtencio() {
          return $this->atencio;
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

       /* ####################### FUNCIONS MODIFICAR ####################### */

       /*
          * @brief Mostra el mapa de Google Maps del contacte de PrisMa
          * @return El codi per mostrar el mapa de Gogle Maps
       */
       public function mostrarIcones() {
          $mostrar = "<h1>Contacte</h1>
          <p class='text-justify'>Si tens qualsevol pregunta sobre els cursos o serveis de PrisMa, no dubtis a visitar-nos o a contactar amb
          nosaltres per telèfon o mitjançant el nostre formulari.</p>
          <div class='d-flex flex-column flex-md-row w-100'>
          <div class='col-12 col-md-4 text-center'>
          <div class='icones'><i class='fas fa-user-clock'></i></div>
          <h2 class='text-center'>Horari</h2>
          <p>".$this->__obtenirHorari()->obtenirText()."</p>";
          if (date(n)>=6 && date(n)<=9) {
             $mostrar .= "<p>".$this->__obtenirHestiu()->obtenirText()."</p>";
          }
          $mostrar .= "</div>";
          $mostrar .= "<div class='col-12 col-md-4 text-center'>";
          $numFix=$this->__obtenirFix()->obtenirText();
          $numMbl=$this->__obtenirMbl()->obtenirText();
          if (!$this->__isMobile()) {
             $mostrar.="<div class='icones'><i class='fa fa-phone' style='transform: rotate(90deg);'></i></div>";
             $mostrar.="<h2 class='text-center'>Telèfons</h2>";
             $mostrar.="<p>Fix: ".$numFix."<br />Mòbil: ".$numMbl."</p>";
          }
          else {
             $mostrar.="<div class='icones'><a href='tel:+34".$numFix."' style='display:block'>";
             $mostrar.="<i class='fa fa-phone' style='transform: rotate(90deg);'></i></a></div>";
             $mostrar.="<h2 class='text-center'>Telèfons</h2>";
             $mostrar.="<p>Fix: <a href='tel:+34".$numFix."'>".$numFix."</a>";
             $mostrar.="<p>Mòbil: <a href='tel:+34".$numMbl."'>".$numMbl."</a>";
          }
          $mostrar.="</div>";

    	  $mostrar.="<div class='col-12 col-md-4 text-center'>
          <div class='icones'><i class='fab fa-whatsapp' style='font-size:4.3rem;margin-top:13px'></i></div>
          <h2 class='text-center'>WhatsApp</h2>
          <p><a href='https://wa.me/34".str_replace(' ', '', $numMbl)."' target='_blank'>".$numMbl."</a></p>
    	  </div></div>";

          if ($this->__obtenirAtencio() != null)
             $mostrar .= "<div class='col-12 horari-atencio text-center'>".$this->__obtenirAtencio()->obtenirText()."</div>";

          return $mostrar;
       }


       /*
          * @brief Mostra el formulari de contacte de PrisMa
          * @return El formulari de contacte de PrisMa llest per omplir
       */
       public function mostrarFormulari() {
          $mostrar .= "<div class='prisma-contact border-radius-2 p-4 mt-4'>";
          $mostrar .= "<h2 class='titol text-center'>Deixa'ns la teva consulta</h2>";

          $mostrar .= "<div class='d-flex flex-column flex-md-row'>
             <div class='col-12 col-md-4'>".$this->mostrarInput("name", "Nom", "text", "nom_erroni")."</div>
             <div class='col-12 col-md-4'>".$this->mostrarInput("email", "Adreça electrònica", "email", "correu_erroni")."</div>
             <div class='col-12 col-md-4'>".$this->mostrarInput("telf", "Telèfon", "tel", "telf_erroni")."</div>
             ".$this->__modalCorreuValid()."
          </div>";

          $mostrar .= "<div class='col-12'><div class='form-group field-wrap position-relative'>
          <label for='message'><span class='camp'>Missatge</span><span class='req'>*</span></label>
          <textarea type='text' class='form-control' id='message' aria-required required></textarea>
          <span id='message_erroni'></span></div></div>";
          $mostrar .= "<label for='comprovaSpam' class='comprovaSpam d-none'>Si veus això, no omplis el camp!</label>
          <input id='comprovaSpam' name='comprovaSpam' class='comprovaSpam d-none' value='' />";
          $mostrar .= "<div class='col-12 text-center cnt_enviar_dades'>
          <button role='button' id='form_enviar_dades' class='boto-blau enviar-consulta
          border-0 border-radius-2 text-center text-white'>Envia</button>";
          $mostrar .= "</div></div>";

          return $mostrar;
       }

       /*
          * @brief Mostra un input del formulari de contacte de PrisMa
          * @return El formulari de contacte de PrisMa llest per omplir
       */
       function mostrarInput($idInput, $nomInput, $typeInput, $idError) {
          $mostrar .= "<div class='form-group field-wrap position-relative'>
          <label for='".$idInput."'><span class='camp'>".$nomInput."</span><span class='req'>*</span></label>
          <input type='".$typeInput."' class='form-control' id='".$idInput."' aria-required required>
          <span id='".$idError."'></span></div>";

          return $mostrar;
       }

       /**
          * @brief Mostra el modal de veure un tastet
          * @return   Mostra la informació breu d'un curs
       */
       public function consultaEnviada(){
          $mostrar = "<div class='modal fade in' id='modalSuccess' tabindex='-1' role='dialog' aria-labelledby='modalSuccessTitle' aria-hidden='true'>";
          $mostrar .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-success' role='document'>";
          $mostrar .= "<div class='modal-content'><div class='modal-header border-0 background-prisma text-white'>";
          $mostrar .= "<p class='modal-title modal-title-success text-white' id='modalSuccessTitle'>Consulta enviada</p>";
          $mostrar .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>";
          $mostrar .= "<div class='modal-body' id='modalSuccessBody'></div>";
          $mostrar .= "<div class='modal-footer justify-content-center text-center'>";
          $mostrar .= "<a role='button' class='btn boto-blau text-white waves-effect waves-light' id='close-sucess' aria-label='Close' data-dismiss='modal'>Tanca</a>";
          $mostrar .= "</div></div></div></div>";

          return $mostrar;
       }

       /**
          * @brief Mostra el modal d'error
          * @return Mostra el modal d'error
       */
       public function modalError() {
          $mostrar = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>";
          $mostrar .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>";
          $mostrar .= "<div class='modal-content w-100'><div class='modal-header border-0 text-white'>";
          $mostrar .= "<p class='modal-title modal-title-danger text-white' id='modalErrorsTitle'>Errors</p>";
          $mostrar .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>";
          $mostrar .= "<div class='modal-body' id='modalErrorsBody'></div>";
          $mostrar .= "<div class='modal-footer justify-content-center text-center'>";
          $mostrar .= "<a role='button' class='btn btn-danger waves-effect waves-light' aria-label='Close' data-dismiss='modal'>Tanca</a>";
          $mostrar .= "</div></div></div></div>";

          return $mostrar;
       }

       /*
          * @brief Mostra el mapa de Google Maps del contacte de PrisMa
          * @return El codi per mostrar el mapa de Gogle Maps
       */
       public function mostrarMapa() {
         // $mostrar = "<div class='map-responsive w-100 position-relative padding-top-middle'>".$this->__obtenirMapa()->obtenirText()."</div>";

    	  $mostrar = "";

          return $mostrar;
       }

       /*
       * @brief Mostra un modal d'avis
       * @return Retorna un modal d'avis
       */
       private function __modalCorreuValid() {
          $mostrar = "<div class='modal fade in' id='modalCorreuValid' tabindex='-1' role='dialog' aria-labelledby='modalCorreuValidTitle' aria-hidden='true'>
            <div class='modal-dialog modal-dialog-centered modal-notify modal-warning' role='document'>
               <div class='modal-content border-0'>
                  <div class='modal-header bg-warning justify-content-center'>
                     <p class='modal-title modal-title-warning font-weight-bold m-0' id='modalCorreuValidTitle'>Avís</p>
                     <button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button>
                  </div>
                  <div class='modal-body text-center' id='modalCorreuValidBody'></div>
                  <div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
                     <button role='button' data-dismiss='modal' class='btn btn-warning border-0 border-radius-2 text-center negreta500 m-0 mr-3'>D'acord</button>
                  </div>
               </div>
            </div>
         </div>";

         return $mostrar;
       }
   }

?>
