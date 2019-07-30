<?php

require_once realpath(__DIR__ . '/..') . '/utils/PHPmailer.php';

class sendMailModel {

    public function sendMailList($email, $msg) {
        $mail = new PHPMailer();
        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->Host = "smtp.office365.com"; // sets the SMTP server
        $mail->Port = "587";                    // set the SMTP port for the GMAIL server
        $mail->Username = "noreply@ravelap.com"; // SMTP account username
        $mail->Password = "S/&@Z8:u!e";        // SMTP account password
        $mail->SMTPSecure = 'tls';
//        $mail->SMTPDebug = 2;                     // enables SMTP debug information (for testing)
        $mail->SMTPAuth = true;                  // enable SMTP authentication
        $mail->SetFrom('noreply@ravelap.com', 'Ravel App');
//        $mail->AddReplyTo("", "[Do not Reply]");
        $mail->Subject = "Reset Password";
        $mail->AltBody = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

        $body = $msg;

        $mail->MsgHTML($body);
        $mail->AddAddress($email, $email);
        if (!$mail->send()) {
            return FALSE;
        } else {

            return TRUE;
        }
    }

}