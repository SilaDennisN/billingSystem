<?php
date_default_timezone_set('Africa/Nairobi');
$current = $_SERVER['REQUEST_URI'];

function isActive($path)
{
    global $current;
    return strpos($current, $path) !== false ? 'active' : '';
}

function isShow($paths = [])
{
    global $current;
    foreach ($paths as $path) {
        if (strpos($current, $path) !== false) {
            return 'show';
        }
    }
    return '';
}

function isCollapsed($paths = [])
{
    global $current;
    foreach ($paths as $path) {
        if (strpos($current, $path) !== false) {
            return '';
        }
    }
    return 'collapsed';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Inovatech - Billing System</title>
    <!-- Load Favicon-->
    <link href="../assets/favicon.png" rel="shortcut icon" type="image/x-icon" />
    <!-- Load Material Icons from Google Fonts-->
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet" />
    <!-- Roboto and Roboto Mono fonts from Google Fonts-->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Mono:400,500" rel="stylesheet" />
    <link href="../assets/font-awesome-4.7.0/css/font-awesome.min.css" rel="stylesheet" />
    <!-- Load main stylesheet-->
    <link href="../assets/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="../assets/css/styles.css" rel="stylesheet" />
</head>