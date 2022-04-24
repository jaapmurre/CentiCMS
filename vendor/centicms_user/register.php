<?php

require 'vendor/password_compat/lib/password.php'; // Many hosting providers have old PHP versions that do not yet support these superior functions

ini_set('session.gc_maxlifetime',3600*24*365); // Server should keep session data for AT LEAST 1 year
session_set_cookie_params(3600*24*365); // Each client should remember their session id for EXACTLY 1 year
session_start();

if (array_key_exists('username',$_SESSION)) {
    $_SESSION['message'] = 'you_are_already_logged_in';
    header('Location: ../../register'); 
    exit();
}

if (isset($_POST['submit']))
{     
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (file_exists("../../data/users/$username.txt")) { 
        $_SESSION['message'] = "username_already_taken"; 
        $_SESSION['wrong_username'] = $username;
        header('Location: ../../register'); // Redirect back to form again for further editing
        exit();
    } else {
        if (!is_dir('../../data')) {
            mkdir('../../data', 0777, true);
            file_put_contents('../../data/.htaccess',"deny from all"); // Prevent external access via Internet
        }
        if (!is_dir('../../data/users')) {
            mkdir('../../data/users', 0777, true);
        }
        $hash = password_hash($password,PASSWORD_BCRYPT); // Encrypt password
        file_put_contents("../../data/users/$username.txt", "password: $hash"); // Save password in username-named file
        $_SESSION['username'] = $username; 
        $_SESSION['message'] = "you_are_registered"; 
        header('Location: ../../register'); 
        exit();
    }
}

