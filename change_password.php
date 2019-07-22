<?php
define('QUADODO_IN_SYSTEM', true);
require_once $_SERVER['DOCUMENT_ROOT'].'/app/includes/header.php';
?>



<?php
if ($qls->User->check_password_code()) {
	if (isset($_POST['process'])) {
		if ($qls->User->change_password()) {
		    echo CHANGE_PASSWORD_SUCCESS;
		}
		else {
		    printf($qls->User->change_password_error . CHANGE_PASSWORD_TRY_AGAIN, htmlentities(strip_tags($_GET['code']), ENT_QUOTES));
		}
	}
	else {
	    require_once('html/change_password_form.php');
	}
}
else {
	// Are we just sending the email?
	if (!isset($_GET['code'])) {
		if (isset($_POST['process'])) {
			if($change_link = $qls->User->get_password_reset_link()) {
				$recipientEmail = $qls->Security->make_safe($_POST['username']);
				$subject = 'Otterm8 - Reset Password';
				$msg = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/app/html/email_password-reset.html');
				$msg = str_replace('<!--btnURL-->', $change_link, $msg);
			
				$attributes = array('recipient', 'sender', 'subject', 'message');
				$values = array($recipientEmail, 'admin@otterm8.com', $subject, $msg);
				$qls->SQL->insert('email_queue', $attributes, $values);
			
				$submitResponse = SEND_PASSWORD_EMAIL_SUCCESS;
				
			} else {
				$submitResponse = $qls->User->send_password_email_error . SEND_PASSWORD_EMAIL_TRY_AGAIN;
			}
		}
		require_once('html/request_password_change_form.php');
	} else {
	    echo CHANGE_PASSWORD_INVALID_CODE;
	}
}
?>