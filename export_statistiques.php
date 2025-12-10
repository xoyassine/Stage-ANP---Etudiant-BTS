<?php

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="statistiques_ANP_' . date('Ymd') . '.csv"');

session_start();
require_once 'database.php';

// Vérifier si admin connecté
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: connection.php");
    exit;
}

// Récupérer les statistiques des demandes
// Fichier : export_stats.php (ou le fichier d'export)

// ... (votre code PHP précédent)

try {
    $sql = "SELECT
        -- Champs de la table demande
        d.id AS demande_id,
        d.dateDepot,
        d.superficie,
        d.duree AS duree_demandee,
        d.activite,
        d.port,
        d.type AS type_demande,
        d.etat AS etat_demande,
        d.motifRejet,
        d.base_demande,
        -- Champs de la table utilisateur
        u.id AS utilisateur_id,
        u.nom,
        u.prenom,
        u.email,
        u.telephone,
        u.role AS type_client,
        u.date_inscription,
        -- Champs spécifiques aux clients morales
        CASE WHEN u.role = 'client_morale' THEN cm.ICE ELSE NULL END AS client_ice,
        CASE WHEN u.role = 'client_morale' THEN cm.raisonSocial ELSE NULL END AS client_raison_sociale,
        CASE WHEN u.role = 'client_morale' THEN cm.secteurActivite ELSE NULL END AS client_secteur_activite,
        -- Champs spécifiques aux clients physiques
        CASE WHEN u.role = 'client_physique' THEN cp.numeroCIN ELSE NULL END AS client_cin,
        -- Champs de la table autorisation
        a.id AS autorisation_id,
        a.dateAutorisation,
        a.dateFin AS date_fin_autorisation,
        a.fichierPDF AS autorisation_pdf
        -- Jointures
        FROM
            `demande` d
        JOIN
            `utilisateur` u ON d.idUtilisateur = u.id
        LEFT JOIN
            `client_physique` cp ON u.id = cp.id 
        LEFT JOIN
            `client_morale` cm ON u.id = cm.id 
        LEFT JOIN
            `autorisation` a ON d.id = a.idDemande
        
        ORDER BY
            d.dateDepot DESC";

    $stmt = $pdo->query($sql);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC); 

} catch (PDOException $e) {
    die('Erreur lors de la récupération des statistiques: ' . $e->getMessage());
}

$output = fopen('php://output', 'w');

// En-têtes CSV
fputcsv($output, [
    'Demande ID', 'Date Dépôt', 'Superficie', 'Durée Demandée', 'Activité', 'Port', 'Type Demande', 'État Demande', 'Motif de Rejet', 'La demande est basée sur',
    'Utilisateur ID', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Type Client', 'Date Inscription',
    'Client ICE', 'Client Raison Sociale', 'Client Secteur Activité', 'Client CIN',
    'Autorisation ID', 'Date Autorisation', 'Date Fin Autorisation', 'Autorisation PDF'
]);

// Données CSV
foreach ($stats as $row) {
    fputcsv($output, $row);
}

fclose($output);

exit;
?>