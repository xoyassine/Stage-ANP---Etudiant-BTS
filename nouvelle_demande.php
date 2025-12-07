<?php
session_start();
require_once 'database.php';

// 1. Sécuriser l'accès
// Vérifier si l'utilisateur est connecté et n'est PAS un admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] === 'admin') {
    header("Location: connection.php");
    exit;
}

$user_id = strtolower($_SESSION['user_id']); // Utiliser l'ID en minuscules pour la sécurité de la casse
$successMessage = "";
$errorMessage = "";

// TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupération des données du formulaire
    $superficie = trim($_POST['superficie'] ?? '');
    $duree = trim($_POST['duree'] ?? '');
    $activite = trim($_POST['activite'] ?? '');
    $port = trim($_POST['port'] ?? '');

    // Validation minimale
    if (empty($superficie) || empty($duree) || empty($activite) || empty($port) || !isset($_FILES['demandePDF']) || $_FILES['demandePDF']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = "Veuillez remplir tous les champs et fournir un document PDF valide.";
    } else {
        
        // Configuration de l'upload du fichier PDF
        $upload_dir = "uploads/demandes/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); // Créer le répertoire si inexistant
        }

        // Nom unique du fichier : ID utilisateur + timestamp + nom du fichier
        $fileName = $user_id . '_' . time() . '_' . basename($_FILES['demandePDF']['name']);
        $filePath = $upload_dir . $fileName;

        if (move_uploaded_file($_FILES['demandePDF']['tmp_name'], $filePath)) {
            
            try {
                // 2. Préparation et Insertion dans la table 'demande'
                $stmt = $pdo->prepare("INSERT INTO demande 
                    (idUtilisateur, dateDepot, superficie, duree, activite, port, demandePDF, etat, motifRejet) 
                    VALUES (?, NOW(), ?, ?, ?, ?, ?, 'en_attente', NULL)");
                
                $stmt->execute([
                    $user_id,
                    $superficie,
                    $duree,
                    $activite,
                    $port,
                    $filePath
                ]);
                
                $successMessage = "Votre demande a été déposée avec succès (N° " . $pdo->lastInsertId() . ") et est en attente de traitement.";

            } catch (PDOException $e) {
                // Si l'insertion échoue, supprimer le fichier uploadé pour éviter les orphelins
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $errorMessage = "Erreur de base de données lors du dépôt : " . $e->getMessage();
            }
            
        } else {
            $errorMessage = "Erreur lors du téléchargement du fichier.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="src\anp-maroc-seeklogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Nouvelle Demande</title>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="demandesDashboard.php">
                <img src="src\anp-maroc-seeklogo.png" alt="ANP LOGO" width="45" height="25">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="demandesDashboard.php">DEMANDES</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="nouvelle_demande.php">NOUVELLE DEMANDE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="autorisationsDashboard.php">AUTORISATIONS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="renouveler_autorisations.php">RENOUVELLER AUTORISATIONS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">PROFILE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">DECONNEXION</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5" style="max-width: 600px;">
        <h3 class="mb-4 text-center fw-bold">Dépôt d'une Nouvelle Demande</h3>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage) ?> <a href="demandesDashboard.php">Voir mes demandes.</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            
            <div class="form-floating mb-3">
                <input type="number" step="0.01" class="form-control" id="superficie" name="superficie" placeholder="Superficie (m²)" required>
                <label for="superficie">Superficie demandée (m²)</label>
            </div>

            <div class="form-floating mb-3">
                <input type="number" class="form-control" id="duree" name="duree" placeholder="Durée (années)" required>
                <label for="duree">Durée d'occupation (années)</label>
            </div>
            
            <div class="form-floating mb-3">
                <select class="form-select" id="activite" name="activite" required>
                    <option value="" selected disabled>Sélectionner l'activité</option>
                    <option value="Dépôts divers sur terrains équipés">Dépôts divers sur terrains équipés</option>
                    <option value="Dépôts divers sur terrains non équipés">Dépôts divers sur terrains non équipés</option>
                    <option value="Stations de distribution carburant">Stations de distribution carburant (pompe et citerne)</option>
                    <option value="Citernes de stockage carburant">Citernes de stockage de carburant</option>
                    <option value="Pipe-line et canalisations">Pipe-line, canalisations et conduites</option>
                    <option value="Snack et restaurants">Snack, cafés, buvette, Crémerie et restaurant</option>
                    <option value="Bureaux">Bureaux</option>
                    <option value="Foyers marins pêcheurs">Foyers et centres exploités par les associations des marins pêcheurs</option>
                    <option value="Entrepôts marchandises">Entrepôts de marchandises</option>
                    <option value="Entrepôts frigorifiques">Entrepôts frigorifiques et stalles</option>
                    <option value="Bureau de tabac">Bureau de tabac</option>
                    <option value="Fabriques de glace">Fabriques de glace</option>
                    <option value="Locaux commerciaux">Locaux à usage commercial</option>
                    <option value="Banques">Banques</option>
                    <option value="Dépôts pêcheurs">Dépôts des pêcheurs dans le port de pêche</option>
                    <option value="Ateliers navals">Ateliers & chantiers navals</option>
                    <option value="Usines">Usines</option>
                </select>
                <label for="activite">Activité prévue</label>
            </div>

            <div class="form-floating mb-3">
                <select class="form-select" id="port" name="port" required>
                    <option value="" selected disabled>Sélectionner le Port</option>
                    <option value="Casablanca">Port de Casablanca</option>
                    <option value="Mohammedia">Port de Mohammedia</option>
                    <option value="Tanger Med">Port Tanger Med</option>
                    <option value="Agadir">Port d'Agadir</option>
                    <option value="Essaouira">Port d'Essaouira</option>
                    <option value="Dakhla">Port de Dakhla</option>
                    <option value="Safi">Port de Safi Ville</option>
                    <option value="Nador">Port de Nador</option>
                    <option value="Jorf Lasfar">Port de Jorf Lasfar</option>
                    <option value="Al Hoceima">Port d'Al Hoceima</option>
                    <option value="Larache">Port de Larache</option>
                    <option value="Kénitra">Port de Kénitra</option>
                    <option value="Autre">Autre Port</option>
                </select>
                <label for="port">Port concerné</label>
            </div>

            <div class="mb-3">
                <label for="demandePDF" class="form-label">Document de Demande (PDF)</label>
                <input class="form-control" type="file" id="demandePDF" name="demandePDF" accept="application/pdf" required>
                <small class="form-text text-muted">Veuillez joindre votre document de demande signé (format PDF).</small>
            </div>

            <button class="btn btn-primary w-100 mt-3" type="submit">Soumettre la Demande</button>

        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>