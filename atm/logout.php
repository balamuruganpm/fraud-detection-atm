<?php
session_start();

// Clear ATM session variables
unset($_SESSION['atm_card_id']);
unset($_SESSION['atm_user_id']);
unset($_SESSION['atm_user_name']);
unset($_SESSION['atm_id']);

// Redirect to authentication page
header('Location: authenticate.php');
exit;
?>
