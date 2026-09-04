<?php

require_once __DIR__ . '/../includes/auth.php';

// Supprimer toutes les données de session
$_SESSION = [];

// Détruire la session
session_destroy();

// Retourner vers la connexion
header('Location: /flux_hotel/auth/login.php');
exit;
