<?php
session_start();
$message = array_key_exists('username',$_SESSION) ? $_SESSION['message'] : 'login_in_to_save';
if (!array_key_exists('username',$_SESSION)) { // If not logged in
    header("Location: ../../login");
    exit();
}
if (isset($_POST['cmd']) && $_POST['cmd'] == 'update' && isset($_POST['text']) && isset($_POST['fullpath']))
{
    $fullpath = $_POST['fullpath'];
    file_put_contents("../../$fullpath", $_POST['text']); // Save the text file contents
    echo "{result: 'success'}";
} 
elseif (isset($_POST['cmd']) && $_POST['cmd'] == 'delete' && isset($_POST['fullpath'])) 
{
    $fullpath = "../../" . $_POST['fullpath'];
    $file = basename($fullpath);
    $path = dirname($fullpath);
    $old = getcwd(); // Save the current directory
    chdir($path);
    if(is_file($file) && @unlink($file)){
        echo "{result: 'success'}";
    } else if (is_file ($file)) {
        echo "{result: 'delete failed'}";
    } else {
        echo "{result: 'file $file does not exist'}";
    }
    chdir($old); // Restore the old working directory   
} 
elseif (isset($_POST['cmd']) && ($_POST['cmd'] == 'settings' || $_POST['cmd'] == 'add'))
{
    $file_ignore = $_POST['file_ignore'] === 'true' ? '_' : '';
    $file_visible = $_POST['file_visible'] === 'true';
    if ($file_visible)
    {
        if ($_POST['file_order']) {
            $file_order = $_POST['file_order'] . '-';
        } else {
            $file_order = $_POST['file_order'] . '0-'; // 0 is the default
        }
    } else {
        $file_order = '';
    }
    $file_name = $_POST['file_name'];
    $file = $file_ignore . $file_order . $file_name;
    $localpath = explode("/",$_POST['localpath']);
    array_pop($localpath);
    $localpath = implode("/",$localpath);
    $localpath = "../../" . $localpath;

    if ($_POST['cmd'] == 'settings')
    {
        $old = getcwd(); // Save the current directory
        $oldname = $_POST['oldname'];
        chdir($localpath);
        if (is_dir($oldname)) {
            rename($oldname,$file);
            echo "{result: 'succes'}";        
        } else {
            echo "{result: 'directory $oldname does not exist'}";        
        }
        chdir($old); // Restore the old working directory   
    } 
    elseif ($_POST['cmd'] == 'add')
    {
        $parentname = $_POST['parentname'];
        $fpath = "$localpath/$parentname/$file";
        if (!file_exists($fpath)) {
            mkdir($fpath,0777,true);
            echo "{result: 'succes'}";        
        } else {
            echo "{result: 'subdirectory $file already exists'}";
        }
    } 
}

exit();