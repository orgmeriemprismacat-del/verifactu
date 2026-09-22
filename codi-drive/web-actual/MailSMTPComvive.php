<?php
/**
    * @class MailSMTP
    * @brief Conté tota la informació relacionada amb un MailSMTP
*/
class MailSMTPComvive {
    private $mailer; /**< Text PHPMailerAutoload*/
    private $firma; /**< Text firma del missatge */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @return Es crea un mailstmp.
             Afegeix l'autentificació del smtp.
             Afegeix el header on el from del missatge es $nomFrom i el $correuFrom i com a reply $correuReply
             Afegeix el subjecte $subject en el teu correu
             Afegeix el to $nomto en el teu correu
             Afegeix el missatge  del correu
   */
   public function __construct($username, $password, $nomFrom, $correuFrom, $nomReply, $correuReply, $nomTo, $correuTo, $subject, $missatge) {
      date_default_timezone_set('Europe/Madrid');

      require_once 'PHPMailerAutoload.php';
      $this->mailer = new PHPMailer;
      $this->mailer->CharSet = 'UTF-8';
      $this->mailer->isSMTP();
      $this->mailer->SMTPDebug = 0;
      $this->mailer->Debugoutput = 'html';
      $this->mailer->Host = 'prisma.cat';
      $this->mailer->Port = '587';
      $this->mailer->SMTPSecure = 'tls';
      $this->mailer->SMTPAuth = true;

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
   	$priv = "<p style='font-size:10px' align=justify><br />Aquest missatge es dirigeix exclusivament al seu destinatari; si no &eacute;s aix&iacute;, et preguem que ens ho comuniquis i l’esborris. La informaci&oacute; tractada pot ser confidencial i no est&agrave; permesa la seva comunicaci&oacute;, reproducci&oacute; o distribuci&oacute;. De conformitat amb el que disposa la normativa vigent en protecció de dades (<em>RGPD</em> i <em>LOPD</em>), les teves dades personals estan incorporades als nostres fitxers amb la finalitat de dur a terme correctament les gestions acad&eacute;miques i administratives i mantenir el contacte amb tu per via correu electr&ograve;nic. En qualsevol moment pots exercir els teus drets d'acc&eacute;s, rectificaci&oacute;, cancel·laci&oacute; i oposici&oacute; escrivint a l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa  a atencio.usuari@prisma.cat. Consulta l’<a title=\"Avís legal\" name=\"Avís legal\" href=\"https://www.prisma.cat/avis-legal\" target=\"_blank\">Av&iacute;s legal</a> per a més informaci&oacute;.</p>";

      $firma = "<div style='font: 13px/ 1.5  Arial,Helvetica,sans-serif; '>
         ".$depart."
         ".$xarxes."
         ".$priv."
      </div>";

      $this->firma = new Text($firma);

      $this->mailer->Username = $username;
   	$this->mailer->Password = $password;
      $this->mailer->setFrom($correuFrom, $nomFrom);
      $this->mailer->addReplyTo($correuReply, $nomReply);
      $this->mailer->addAddress($correuTo, $nomTo);
      $this->mailer->Subject = $subject;
      $missatges = $missatge.$this->firma->obtenirText();
      $this->mailer->msgHTML($missatges);
      $this->mailer->send();
   }
}
?>
