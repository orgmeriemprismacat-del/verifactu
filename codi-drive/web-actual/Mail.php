<?php
/**
    * @class Contacte
    * @brief Conté tota la informació relacionada amb un Contacte
*/
class Mail {
    private $headers; /**< Text LA capçalera del missatge*/
    private $assumpte; /**< Text L'assumpe del missatge */
    private $to; /**< Text el destí del messatge*/
    private $missatge; /**< Text el contingut del missatge a enviar */
    private $firma; /**< Text firma del missatge */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $id La id corresponent al contacte
   * @return El contacte està creat amb la informació del codi i de la durada obtinguda de la taula VIDEOS
   */
   public function __construct() {
      $headers ="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
		$headers.="From: <suport.informatic@prisma.cat>\r\nReply-To: suport.informatic@prisma.cat";

      require_once 'Text.php';
      $this->headers = new Text($headers);
      $this->assumpte = new Text("Inici de contacte");
      $this->to = new Text("suport.informatic@prism.cat");
      $this->missatge = new Text("S'ha construit el missatge");

      $depart = "Departament de Formaci&oacute;<br>
   	Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa<br>
   	972 21 75 65 - 678 12 36 87<br>
      <a style='text-decoration:none; font-size: 16px; color: #496baa!important' href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a><br />";
      $xarxes = "<div style='padding-top: 6px; border-bottom: 1px solid #7a7a7b; padding-bottom: 6px; max-width: 352px'>
   		<a title='Instagram' name='Instagram' href='https://www.instagram.com/prisma.educacio/' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 28px;' src='https://www.prisma.cat/img/social/firma/instagram.png'>
   		</a><a title='YouTube' name='YouTube' href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 28px;' src='https://www.prisma.cat/img/social/firma/youtube.png'>
   		</a><a title='Facebook' name='Facebook' href='https://www.facebook.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 28px;' src='https://www.prisma.cat/img/social/firma/facebook.png'>
   		</a><a title='X' name='X' href='https://x.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 28px;' src='https://www.prisma.cat/img/social/firma/x.png'>
   		</a><a title='Tiktok' name='Tiktok' href='https://www.tiktok.com/@prisma.educacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 28px;' src='https://www.prisma.cat/img/social/firma/tiktok.png'>
   		</a>
   	</div>";
     $priv = "<p style='font-size:10px' align=justify><br />Aquest missatge es dirigeix exclusivament al seu destinatari; si no &eacute;s aix&iacute;, et preguem que ens ho comuniquis i l’esborris. La informaci&oacute; tractada pot ser confidencial i no est&agrave; permesa la seva comunicaci&oacute;, reproducci&oacute; o distribuci&oacute;. De conformitat amb el que disposa la normativa vigent en protecció de dades (<em>RGPD</em> i <em>LOPD</em>), les teves dades personals estan incorporades als nostres fitxers amb la finalitat de dur a terme correctament les gestions acad&eacute;miques i administratives i mantenir el contacte amb tu per via correu electr&ograve;nic. En qualsevol moment pots exercir els teus drets d'acc&eacute;s, rectificaci&oacute;, cancel·laci&oacute; i oposici&oacute; escrivint a l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa a atencio.usuari@prisma.cat. Consulta l’<a title=\"Avís legal\" name=\"Avís legal\" href=\"https://www.prisma.cat/avis-legal\" target=\"_blank\">Av&iacute;s legal</a> per a més informaci&oacute;.</p>";

      $firma = "<div style='font: 13px/ 1.5  Arial,Helvetica,sans-serif; '>
         ".$depart."
         ".$xarxes."
         ".$priv."
      </div>";

      $this->firma = new Text($firma);
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
      * @brief Obtenim el header del correu
      * @return El header del correu
      * @throws Si el correu no té un header, envia l'excepció 1101
   */
   private function __obtenirHeaders() {
      if ($this->headers==null) {
         throw new Exception('', 1101);
      }
      return $this->headers;
   }

   /*
      * @brief Obtenim l'assumpte del correu
      * @return L'assumpte del correu
      * @throws Si el correu no té un assumpte, envia l'excepció 1102
   */
   private function __obtenirAssumpte() {
      if ($this->assumpte==null) {
         throw new Exception('', 1102);
      }
      return $this->assumpte;
   }

   /*
      * @brief Obtenim el to del correu
      * @return El to del correu
      * @throws Si el correu no té un to, envia l'excepció 1103
   */
   private function __obtenirTo() {
      if ($this->to==null) {
         throw new Exception('', 1103);
      }
      return $this->to;
   }

   /*
      * @brief Obtenim el missatge del correu
      * @return El missatge del correu
      * @throws Si el correu no té un missatge, envia l'excepció 1104
   */
   private function __obtenirMissatge() {
      if ($this->missatge==null) {
         throw new Exception('', 1104);
      }
      return $this->missatge;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /*
      * @brief Afegeix el header del correu
      * @return Afegeix el header on el from del missatge es $nomFrom i el $correuFrom i com a reply $correuReply
   */
   public function addHeaders($nomFrom, $correuFrom, $correuReply) {
      $this->headers = new Text("MIME-Version: 1.0\r\n");
      $this->headers->afegirFinal("Content-type: text/html; charset=UTF-8\r\n");
      $this->headers->afegirFinal("From: ".$nomFrom." <".$correuFrom.">\r\n");
      $this->headers->afegirFinal("Reply-To: ".$correuReply);
   }

   /*
      * @brief Afegeix el subjecte del correu
      * @return Afegeix el subjecte $subject en el teu correu
   */
   public function addSubject($subject) {
      $this->assumpte = new Text($subject);
   }

   /*
      * @brief Afegeix el to del correu
      * @return Afegeix el to $to en el teu correu
   */
   public function addTo($to) {
      $this->to = new Text($to);
   }

   /*
      * @brief Afegeix el missatge  del correu en format tiquet
      * @return Afegeix el missatge $missPrev, seguit amb un format
         d'encapçulament el missatge $missatge i després del encapçulmanet el $missPostDiv
   */
   public function addMissatgeTiquet($missPreDiv, $missatge, $missPostDiv) {
      $this->missatge = new Text($missPreDiv);
      $this->missatge->afegirFinal("<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 25px; margin-bottom: 20px'>");
      $this->missatge->afegirFinal($missatge);
      $this->missatge->afegirFinal('</div>');
      if ($missPostDiv!='')$this->missatge->afegirFinal($missPostDiv);
      $this->missatge->afegirFinal($this->firma->obtenirText());
   }

   /*
      * @brief Afegeix el missatge  del correu
      * @return Afegeix el missatge seguit de
   */
   public function addMissatge($missatge) {
      $this->missatge = new Text($missatge);
      $this->missatge->afegirFinal($this->firma->obtenirText());
   }

   /*
      * @brief Envia el correu
      * @return Envia el correu
   */
   public function sendMessage() {
      $to = $this->to->obtenirText();
      $headers = $this->headers->obtenirText();
      $subject = $this->assumpte->obtenirText();
      $missatge = $this->missatge->obtenirText();
      mb_send_mail($to, $subject, $missatge, $headers);
   }

}
?>
