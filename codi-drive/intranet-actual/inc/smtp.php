<?php
// si canvia la contrasenya de secretaria@prisma.cat no funcionar�, cal canviar-la aqu� tamb�
// incl�s a informacio/inscripcio.php

//SMTP needs accurate times, and the PHP time zone MUST be set
//This should be done in your php.ini, but this is how to do it if you don't have access to that

date_default_timezone_set('Europe/Madrid');

require 'PHPMailerAutoload.php';

//Create a new PHPMailer instance
$mail = new PHPMailer;

$mail->CharSet = "UTF-8";

//Tell PHPMailer to use SMTP
$mail->isSMTP();

//Enable SMTP debugging
// 0 = off (for production use)
// 1 = client messages
// 2 = client and server messages
$mail->SMTPDebug = 0;

//Ask for HTML-friendly debug output
$mail->Debugoutput = 'html';

//Set the hostname of the mail server
$mail->Host = 'smtp.gmail.com';

//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
$mail->Port = 587;

//Set the encryption system to use - ssl (deprecated) or tls
$mail->SMTPSecure = 'tls';

//Whether to use SMTP authentication
$mail->SMTPAuth = true;

//Username to use for SMTP authentication - use full email address for gmail
$mail->Username = "secretaria@prisma.cat";

//Password to use for SMTP authentication
$mail->Password = "17GiRoNa";

//Set who the message is to be sent from
$mail->setFrom('secretaria@prisma.cat', 'PrisMa Secretaria');

//Set an alternative reply-to address
$mail->addReplyTo('secretaria@prisma.cat', 'PrisMa Secretaria');

$nom_to = str_replace("''","'",str_replace("\'","'",$nom2));

//Set who the message is to be sent to
$mail->addAddress($email, $nom_to);

//Set BCC address
$mail->addBCC('inscripcions@prisma.cat', 'PrisMa Inscripcions');

$assumpte = $subject2;

//Set the subject line
$mail->Subject = $assumpte;

//Read an HTML message body from an external file, convert referenced images to embedded,
//convert HTML into a basic plain-text alternative body
$mail->msgHTML($message2);

//Replace the plain text body with one created manually
//$mail->AltBody = 'This is a plain-text message body';

//Attach an image file
//$mail->addAttachment('images/phpmailer_mini.png');

//send the message, check for errors
/*if (!$mail->send()) {
    echo "Mailer Error: " . $mail->ErrorInfo;
} else {
    echo "Message sent!";
}*/
