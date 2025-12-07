<?php
// Démarrer la session
session_start();

// Détruire toutes les variables de session
// Cela supprime les clés stockées comme $_SESSION['user_id'], $_SESSION['logged_in'], etc.
$_SESSION = array();

// Finalement, détruire la session.
session_destroy();

// Rediriger l'utilisateur vers la page de connexion
header("Location: connection.php");
exit;
?>