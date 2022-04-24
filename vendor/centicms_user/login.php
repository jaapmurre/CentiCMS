<?php

require 'vendor/password_compat/lib/password.php'; // Many hosting providers have old PHP versions that do not yet support these superior functions

ini_set('session.gc_maxlifetime',3600*24*365); // Server should keep session data for AT LEAST 1 year
session_set_cookie_params(3600*24*365); // Each client should remember their session id for EXACTLY 1 year
session_start();

if (array_key_exists('username',$_SESSION)) {
    $_SESSION['message'] = $message = "you_are_already_logged_in";
    header("Location: ../../login");
    exit();
}

if (isset($_POST['submit'])) {     
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (file_exists("../../data/users/$username.txt")) {
        $file = @file_get_contents("users/$username.txt"); 
    } else {
        $_SESSION['message'] = "username_not_recognized"; 
        $_SESSION['wrong_username'] = $username;
        header("Location: ../../login");
        exit();
    }

    $passwordhash = '';
    $file = @file_get_contents("../../data/users/$username.txt");        
    if (trim($file) != "") {
        $vars = preg_split("/\s*---(-)+\s*/",$file);
        foreach ($vars as $var) {
            $key_value = explode(":",$var,2);
            $key = strtolower(trim($key_value[0]));
            $value = trim($key_value[1]);
            if ($key == 'password') {
                $passwordhash = $value;
            } else {
                $_SESSION[$key] = $value;
            }
        }
    }

    if (password_verify($password, $passwordhash)) {
        $_SESSION['username'] = $username; 
        $_SESSION['message'] = "You are logged in as '$username'"; 
        header(sprintf("Location: ../../"));
//        header("Location: {$_SERVER['HTTP_REFERER']}"); // Move back to referring page
        exit();
    } else {
        $message = "<h2>Your username or password was not correct</h2>";
    }
}

?>