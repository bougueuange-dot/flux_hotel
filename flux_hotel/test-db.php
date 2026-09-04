<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

echo "<h1>TEST HOTEL FLOW</h1>";

echo "<pre>";
print_r($_SESSION);
echo "</pre>";
