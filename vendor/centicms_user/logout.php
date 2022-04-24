<?php
session_start();
if (!array_key_exists('username',$_SESSION)) {
    $message = "were_logged_out";
} else {
    $message = "are_logged_out";
}
$_SESSION = array();
session_destroy();
$lang = array_key_exists('language',$_POST) ? "" . $_POST['language'] : "";
header(sprintf("Location: ../.." . $lang . "/logout"));
exit();