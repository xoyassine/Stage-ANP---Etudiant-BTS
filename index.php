<?php
// 1. Démarrer la session en tout premier lieu
session_start();

// 2. Vérifier si l'utilisateur est connecté (la session est définie)
if (isset($_SESSION['user_id'])) {
    
    // L'utilisateur est connecté. On vérifie son rôle.
    
    $role = $_SESSION['user_role'] ; // Récupérer le rôle de l'utilisateur depuis la session

    if ($role === 'admin') {
        // Si l'utilisateur est un admin
        header("Location: admin_gestion_comptes.php");
        exit(); 
        
    } elseif ($role === 'client_physique' || $role === 'client_morale') {
        // Si l'utilisateur est un client (ou tout autre rôle par défaut)
        header("Location: demandesDashboard.php");
        exit();
        
    } else {
        // Si un rôle inattendu est trouvé, rediriger vers une page par défaut
        header("Location: demandesDashboard.php");
        exit();
    }
    
} else {
    // 3. Si l'utilisateur n'est PAS connecté
    header("Location: connection.php");
    exit();
}

?>