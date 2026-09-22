<?php
/**
    * @class Contacte
    * @brief Conté tota la informació relacionada amb un Contacte
*/
class Mail {
    private $headers; /**< Text LA capçalera del missatge*/
    private $subject; /**< Text L'assumpe del missatge */
    private $to; /**< Text el destí del messatge*/
    private $missatge; /**< Text el contingut del missatge a enviar */
    private $firma; /**< Text firma del missatge */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $qui el nom de la persona que portarà la firma
   * @param $departament el departament de la persona que portarà la firma
   * @return El contacte està creat amb la informació del codi i de la durada obtinguda de la taula VIDEOS
   */
   public function __construct( $qui, $departament ) {
      $headers ="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
		$headers.="From: <suport.informatic@prisma.cat>\r\nReply-To: suport.informatic@prisma.cat";

      require_once 'Text.php';
      $this->headers = new Text($headers);
      $this->subject = new Text("Inici de contacte");
      $this->to = new Text("suport.informatic@prism.cat");
      $this->missatge = new Text("S'ha construit el missatge");

      $depart = $qui."<br>".$departament."<br>
   	Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa<br>
   	972 21 75 65 - 678 12 36 87<br>";

      $urlPrisma = "<a style='text-decoration:none; font-size: 16px; color: #496baa!important' href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a><br />";

      $xarxes = "<div style='padding-top: 6px; border-bottom: 1px solid #7a7a7b; padding-bottom: 6px; max-width: 352px'>
   		<a title='Instagram' name='Instagram' href='https://www.instagram.com/prisma.educacio/' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/instagram.png'>
   		</a><a title='YouTube' name='YouTube' href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/youtube.png'>
   		</a><a title='Facebook' name='Facebook' href='https://www.facebook.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/facebook.png'>
   		</a><a title='X' name='X' href='https://x.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/x.png'>
   		</a><a title='Tiktok' name='Tiktok' href='https://www.tiktok.com/@prisma.educacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/tiktok.png'>
   		</a>
   	</div>";

   	$priv = "<p style='font-size:10px' align=justify><br />Aquest missatge es dirigeix exclusivament al seu destinatari; si no &eacute;s aix&iacute;, et preguem que ens ho comuniquis i l’esborris. La informaci&oacute; tractada pot ser confidencial i no est&agrave; permesa la seva comunicaci&oacute;, reproducci&oacute; o distribuci&oacute;. De conformitat amb el que disposa la normativa vigent en protecció de dades (<em>RGPD</em> i <em>LOPD</em>), les teves dades personals estan incorporades als nostres fitxers amb la finalitat de dur a terme correctament les gestions acad&eacute;miques i administratives i mantenir el contacte amb tu per via correu electr&ograve;nic. En qualsevol moment pots exercir els teus drets d'acc&eacute;s, rectificaci&oacute;, cancel·laci&oacute; i oposici&oacute; escrivint a l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa a atencio.usuari@prisma.cat. Consulta l’<a title=\"Avís legal\" name=\"Avís legal\" href=\"https://www.prisma.cat/avis-legal\" target=\"_blank\">Av&iacute;s legal</a> per a més informaci&oacute;.</p>";
         
      $this->firma = new Text("<div style='font: 13px/ 1.5 Arial,Helvetica,sans-serif; '>");
      $this->firma->addLast($depart);
      $this->firma->addLast($urlPrisma);
      $this->firma->addLast($xarxes);
      $this->firma->addLast($priv);
      $this->firma->addLast('</div>');
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
      * @brief Obtenim el header del correu
      * @return El header del correu
      * @throws Si el correu no té un header, envia l'excepció 6001
   */
   private function __getHeaders() {
      if ($this->headers==null) {
         throw new Exception('', 6001);
      }
      return $this->headers;
   }

   /*
      * @brief Obtenim l'subject del correu
      * @return L'subject del correu
      * @throws Si el correu no té un subject, envia l'excepció 6002
   */
   private function __getSubject() {
      if ($this->subject==null) {
         throw new Exception('', 6002);
      }
      return $this->subject;
   }

   /*
      * @brief Obtenim el to del correu
      * @return El to del correu
      * @throws Si el correu no té un to, envia l'excepció 6003
   */
   private function __getTo() {
      if ($this->to==null) {
         throw new Exception('', 6003);
      }
      return $this->to;
   }

   /*
      * @brief Obtenim el missatge del correu
      * @return El missatge del correu
      * @throws Si el correu no té un missatge, envia l'excepció 6004
   */
   private function __getMessage() {
      if ($this->missatge==null) {
         throw new Exception('', 6004);
      }
      return $this->missatge;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /*
      * @brief Afegeix el header del correu
      * @return Afegeix el header on el from del missatge es $nomFrom i el $correuFrom i com a reply $correuReply
   */
   public function setHeaders($nomFrom, $correuFrom, $correuReply) {
      $this->headers = new Text("MIME-Version: 1.0\r\n");
      $this->headers->addLast("Content-type: text/html; charset=UTF-8\r\n");
      $this->headers->addLast("From: ".$nomFrom." <".$correuFrom.">\r\n");
      $this->headers->addLast("Reply-To: ".$correuReply);
   }

   /*
      * @brief Afegeix el subjecte del correu
      * @return Afegeix el subjecte $subject en el teu correu
   */
   public function setSubject($subject) {
      $this->subject = new Text($subject);
   }

   /*
      * @brief Afegeix el to del correu
      * @return Afegeix el to $to en el teu correu
   */
   public function setTo($to) {
      $this->to = new Text($to);
   }

   /*
      * @brief Afegeix el missatge  del correu en format tiquet
      * @return Afegeix el missatge $missPrev, seguit amb un format
         d'encapçulament el missatge $missatge i després del encapçulmanet el $missPostDiv
   */
   public function setMissatgeTiquet($missPreDiv, $missatge, $missPostDiv) {
      $this->missatge = new Text($missPreDiv);
      $this->missatge->addLast("<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 25px; margin-bottom: 20px'>");
      $this->missatge->addLast($missatge);
      $this->missatge->addLast('</div>');
      if ($missPostDiv!='')
         $this->missatge->addLast($missPostDiv);
      $this->missatge->addLast( $this->firma->get() );
   }

   /*
      * @brief Afegeix el missatge  del correu
      * @return Afegeix el missatge seguit de
   */
   public function setMissatge($missatge) {
      $this->missatge = new Text($missatge);
      $this->missatge->addLast( $this->firma->get() );
   }

   /*
      * @brief Envia el correu
      * @return Envia el correu
   */
   public function sendMessage() {
      $to = $this->__getTo()->get();
      $headers = $this->__getHeaders()->get();
      $subject = $this->__getSubject()->get();
      $missatge = $this->__getMessage()->get();
      mb_send_mail($to, $subject, $missatge, $headers);
   }

}
?>
