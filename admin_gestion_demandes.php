<?php
session_start();
require_once 'database.php';

// Vérifier si admin connecté
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: connection.php");
    exit;
}

$successMessage = '';
$errorMessage = '';
$demande_details_to_create = null; 
$demande_details_to_reject = null; 

// --- LOGIQUE DE TRAITEMENT (CRÉATION AUTORISATION / REJET) ---

// Logique pour CRÉER L'AUTORISATION (Soumission du formulaire de création)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer_autorisation') {
    
    $demande_id = trim($_POST['demande_id'] ?? '');
    $dateAutorisation = trim($_POST['dateAutorisation'] ?? date('Y-m-d'));
    $dateFin = trim($_POST['dateFin'] ?? '');
    $base_demande = trim($_POST['base_demande'] ?? '');
    $type_demande = trim($_POST['type_demande'] ?? '');

    if (empty($demande_id) || empty($dateFin) || !isset($_FILES['fichierPDF']) || $_FILES['fichierPDF']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = "Erreur : Tous les champs de l'autorisation sont requis (ID: {$demande_id}).";
    } else {
        $upload_dir = "uploads/autorisations/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $fileName = $demande_id . '_AUTORISATION_' . time() . '_' . basename($_FILES['fichierPDF']['name']);
        $filePath = $upload_dir . $fileName;

        if (move_uploaded_file($_FILES['fichierPDF']['tmp_name'], $filePath)) {
            try {
                $pdo->beginTransaction();
                
                // 1. Insertion dans la table 'autorisation'
                $stmtA = $pdo->prepare("INSERT INTO autorisation (idDemande, dateAutorisation, dateFin, fichierPDF) VALUES (?, ?, ?, ?)");
                $stmtA->execute([$demande_id, $dateAutorisation, $dateFin, $filePath]);
                
                // 2. Mise à jour de l'état dans la table 'demande' à 'acceptee'
                $stmtD = $pdo->prepare("UPDATE demande SET etat = 'acceptee', motifRejet = NULL WHERE id = ?");
                $stmtD->execute([$demande_id]);

                // 3. Si c'est un renouvellement, mettre à jour le statut autorisation précédente
                if ($type_demande === 'renouvellement') {
                    $stmtOld = $pdo->prepare("
                        UPDATE autorisation
                        SET statut = 'renouvelee'
                        WHERE id = (SELECT id FROM autorisation WHERE idDemande = ? ORDER BY id DESC LIMIT 1)
                    ");
                }
                $stmtOld->execute([$base_demande]);

                
                $pdo->commit();
                
                $successMessage = "Autorisation créée et Demande N°{$demande_id} ACCEPTÉE avec succès.";
                header("Location: admin_gestion_demandes.php?success=" . urlencode($successMessage));
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                if (file_exists($filePath)) unlink($filePath); 
                $errorMessage = "Erreur de base de données : " . $e->getMessage();
            }
        } else {
            $errorMessage = "Erreur lors du téléchargement du fichier d'autorisation.";
        }
    }
}


// Logique de soumission du motif de rejet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rejeter_demande') {
    $demande_id = trim($_POST['demande_id'] ?? '');
    $motifRejet = trim($_POST['motifRejet'] ?? '');
    
    if (empty($demande_id) || empty($motifRejet)) {
        $errorMessage = "Erreur : Le motif de rejet est obligatoire.";
    } else {
        try {
            // Mettre à jour l'état et enregistrer le motif
            $stmt = $pdo->prepare("UPDATE demande SET etat = 'refusee', motifRejet = ? WHERE id = ?");
            $stmt->execute([$motifRejet, $demande_id]);
            
            $successMessage = "La demande N°{$demande_id} a été REJETÉE avec le motif spécifié.";
            header("Location: admin_gestion_demandes.php?success=" . urlencode($successMessage));
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Erreur BDD lors du rejet : " . $e->getMessage();
        }
    }
}


// Récupérer le message de succès après redirection
if (isset($_GET['success'])) {
    $successMessage = urldecode($_GET['success']);
}


// --- GESTION DE L'AFFICHAGE DES FORMULAIRES ---

// Affichage du formulaire de CRÉATION D'AUTORISATION
$demande_id_to_create = $_GET['creer_autorisation_id'] ?? null;
if ($demande_id_to_create) {
    try {
        $stmtD = $pdo->prepare("SELECT d.*, u.nom, u.prenom FROM demande d JOIN utilisateur u ON d.idUtilisateur = u.id WHERE d.id = ?");
        $stmtD->execute([$demande_id_to_create]);
        $demande_details_to_create = $stmtD->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande_details_to_create) { $errorMessage = "Demande introuvable pour la création de l'autorisation."; }
    } catch (PDOException $e) { $errorMessage = "Erreur BDD lors de la récupération des détails de la demande: " . $e->getMessage(); }
}

// Affichage du formulaire de REJET
$demande_id_to_reject = $_GET['rejeter_demande_id'] ?? null;
if ($demande_id_to_reject) {
    try {
        $stmtR = $pdo->prepare("SELECT d.*, u.nom, u.prenom FROM demande d JOIN utilisateur u ON d.idUtilisateur = u.id WHERE d.id = ?");
        $stmtR->execute([$demande_id_to_reject]);
        $demande_details_to_reject = $stmtR->fetch(PDO::FETCH_ASSOC);
        if (!$demande_details_to_reject) { $errorMessage = "Demande introuvable pour le rejet."; }
    } catch (PDOException $e) { $errorMessage = "Erreur BDD lors de la récupération des détails de la demande: " . $e->getMessage(); }
}


// --- LOGIQUE DE FILTRAGE ET DE RÉCUPÉRATION DES DONNÉES ---

$filterPort = $_GET['port'] ?? '';
$filterEtat = $_GET['etat'] ?? '';

$sql = "SELECT d.*, u.nom, u.prenom , a.fichierPDF 
        FROM demande d  
        LEFT JOIN autorisation a ON d.base_demande = a.idDemande
        JOIN utilisateur u ON d.idUtilisateur = u.id
        WHERE 1=1";
        
$params = [];

// Filtre par État
if (!empty($filterEtat)) {
    $sql .= " AND d.etat = ?";
    $params[] = $filterEtat;
}

// Filtre par Port
if (!empty($filterPort)) {
    $sql .= " AND d.port = ?";
    $params[] = $filterPort;
}

$sql .= " ORDER BY d.dateDepot DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function display_etat_badge($etat) {
    switch ($etat) {
        case 'acceptee': return '<span class="badge bg-success">Acceptée</span>';
        case 'refusee': return '<span class="badge bg-danger">Rejetée</span>';
        case 'en_attente': 
        default: return '<span class="badge bg-warning text-dark">En Attente</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gestion des Demandes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="src\anp-maroc-seeklogo.png" alt="ANP LOGO" width="45" height="25">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="admin_gestion_comptes.php">GESTION COMPTES</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="#">GESTION DEMANDES ET AUTORISATIONS</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="logout.php">DECONNEXION</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h3 class="mb-4">Gestion des Demandes d'Occupation</h3>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>
    
    <?php if ($demande_details_to_create): ?>
        <div class="card mb-5 border-success">
            <div class="card-header bg-success text-white">
                Création de l'Autorisation pour la Demande N°<?= htmlspecialchars($demande_details_to_create['id']) ?>
            </div>
            <div class="card-body">
                <p><strong>Demandeur:</strong> <?= htmlspecialchars($demande_details_to_create['nom']) . ' ' . htmlspecialchars($demande_details_to_create['prenom']) ?></p>
                <p><strong>Superficie / Durée Demandées:</strong> <?= htmlspecialchars($demande_details_to_create['superficie']) ?> m² / <?= htmlspecialchars($demande_details_to_create['duree']) ?> ans</p>

                <form method="post" enctype="multipart/form-data" action="admin_gestion_demandes.php">
                    <input type="hidden" name="action" value="creer_autorisation">
                    <input type="hidden" name="demande_id" value="<?= htmlspecialchars($demande_details_to_create['id']) ?>">
                    <input type="hidden" name="base_demande" value="<?= htmlspecialchars($demande_details_to_create['base_demande']) ?>">
                    <input type="hidden" name="type_demande" value="<?= htmlspecialchars($demande_details_to_create['type']) ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="dateAutorisation" class="form-label">Date d'Autorisation</label>
                            <input type="date" class="form-control" id="dateAutorisation" name="dateAutorisation" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="dateFin" class="form-label">Date de Fin d'Occupation</label>
                            <?php
                                $duree_annees = (int)$demande_details_to_create['duree'];
                                $date_fin_suggerée = (new DateTime())->modify("+{$duree_annees} years")->format('Y-m-d');
                            ?>
                            <input type="date" class="form-control" id="dateFin" name="dateFin" value="<?= htmlspecialchars($date_fin_suggerée) ?>" required>
                            <small class="text-muted">Durée suggérée: <?= htmlspecialchars($duree_annees) ?> ans.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="fichierPDF" class="form-label">Document PDF de l'Autorisation</label>
                        <input class="form-control" type="file" id="fichierPDF" name="fichierPDF" accept="application/pdf" required>
                    </div>

                    <button type="submit" class="btn btn-success">VALIDER ET ENREGISTRER L'AUTORISATION</button>
                    <a href="admin_gestion_demandes.php" class="btn btn-secondary">Annuler</a>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($demande_details_to_reject)): ?>
        <div class="card mb-5 border-danger">
            <div class="card-header bg-danger text-white">
                Rejet de la Demande N°<?= htmlspecialchars($demande_details_to_reject['id']) ?>
            </div>
            <div class="card-body">
                <p><strong>Demandeur:</strong> <?= htmlspecialchars($demande_details_to_reject['nom']) . ' ' . htmlspecialchars($demande_details_to_reject['prenom']) ?></p>

                <form method="post" action="admin_gestion_demandes.php">
                    <input type="hidden" name="action" value="rejeter_demande">
                    <input type="hidden" name="demande_id" value="<?= htmlspecialchars($demande_details_to_reject['id']) ?>">
                    
                    <div class="mb-3">
                        <label for="motifRejet" class="form-label">Motif de Rejet <span class="text-danger">*</span></label>
                        <select class="form-select" id="motifRejet" name="motifRejet" required>
                            <option value="" selected disabled>Sélectionnez la raison principale du rejet</option>
                            <option value="DOC_INCOMPLET">Dossier de demande incomplet (pièces manquantes)</option>
                            <option value="DOC_NON_CONFORME">Document de demande illisible ou non conforme</option>
                            <option value="SUPERFICIE_EXCEDEE">Superficie demandée excède la surface disponible</option>
                            <option value="LOCALISATION_INDISP">Localisation ou site portuaire demandé indisponible</option>
                            <option value="INCOMPAT_ACTIVITE">Activité prévue incompatible avec la zone portuaire</option>
                            <option value="DUREE_NON_ACCEPTEE">Durée d'occupation demandée est trop longue</option>
                            <option value="INTERET_PUBLIC_FAIBLE">Projet ne présentant pas un intérêt public suffisant</option>
                            <option value="CONTRAINTE_TECHNIQUE">Demande non réalisable pour des contraintes techniques</option>
                            <option value="AUTRE_RAISON">Autre raison (Non spécifié)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-danger">CONFIRMER LE REJET</button>
                    <a href="admin_gestion_demandes.php" class="btn btn-secondary">Annuler</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <form method="get" class="form-inline">
            <label for="etatFilter" class="me-2">Filtrer par État :</label>
            <select name="etat" id="etatFilter" class="form-select w-auto d-inline-block me-2">
                <option value="">Tous les états</option>
                <option value="en_attente" <?= $filterEtat === 'en_attente' ? 'selected' : '' ?>>En Attente</option>
                <option value="acceptee" <?= $filterEtat === 'acceptee' ? 'selected' : '' ?>>Acceptée</option>
                <option value="refusee" <?= $filterEtat === 'refusee' ? 'selected' : '' ?>>Refusée</option>
            </select>
            
            <label for="portFilter" class="me-2">Filtrer par Port :</label>
            <select name="port" id="portFilter" class="form-select w-auto d-inline-block me-2">
                <option value="">Tous les ports</option>
                <option value="Casablanca" <?= $filterPort === 'Casablanca' ? 'selected' : '' ?>>Port de Casablanca</option>
                <option value="Mohammedia" <?= $filterPort === 'Mohammedia' ? 'selected' : '' ?>>Port de Mohammedia</option>
                <option value="Tanger Med" <?= $filterPort === 'Tanger Med' ? 'selected' : '' ?>>Port Tanger Med</option>
                <option value="Agadir" <?= $filterPort === 'Agadir' ? 'selected' : '' ?>>Port d'Agadir</option>
                <option value="Essaouira" <?= $filterPort === 'Essaouira' ? 'selected' : '' ?>>Port d'Essaouira</option>
                <option value="Dakhla" <?= $filterPort === 'Dakhla' ? 'selected' : '' ?>>Port de Dakhla</option>
                <option value="Safi" <?= $filterPort === 'Safi' ? 'selected' : '' ?>>Port de Safi Ville</option>
                <option value="Nador" <?= $filterPort === 'Nador' ? 'selected' : '' ?>>Port de Nador</option>
                <option value="Jorf Lasfar" <?= $filterPort === 'Jorf Lasfar' ? 'selected' : '' ?>>Port de Jorf Lasfar</option>
                <option value="Al Hoceima" <?= $filterPort === 'Al Hoceima' ? 'selected' : '' ?>>Port d'Al Hoceima</option>
                <option value="Larache" <?= $filterPort === 'Larache' ? 'selected' : '' ?>>Port de Larache</option>
                <option value="Kénitra" <?= $filterPort === 'Kénitra' ? 'selected' : '' ?>>Port de Kénitra</option>
                <option value="Autre" <?= $filterPort === 'Autre' ? 'selected' : '' ?>>Autre Port</option>
            </select>
            
            <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
            <a href="admin_gestion_demandes.php" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
        </form>
    </div>

    <?php if (empty($demandes)): ?>
        <div class="alert alert-info">Aucune demande trouvée.</div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Date Dépôt</th>
                    <th>Demandeur</th>
                    <th>ID Demandeur</th>
                    <th>Type Demande</th>
                    <th>Port</th>
                    <th>Superficie (m²)</th>
                    <th>Durée (ans)</th>
                    <th>Activité</th>
                    <th>État</th>
                    <th>Document</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($demandes as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['id']) ?></td>
                        <td><?= htmlspecialchars($d['dateDepot']) ?></td>
                        <td><?= htmlspecialchars($d['nom']) . ' ' . htmlspecialchars($d['prenom']) ?></td>
                        <td><?= htmlspecialchars($d['idUtilisateur']) ?></td>
                        <td><?= htmlspecialchars($d['type']) ?></td>
                        <td><?= htmlspecialchars($d['port']) ?></td>
                        <td><?= htmlspecialchars($d['superficie']) ?></td>
                        <td>
                            <?php if ($d['type'] === 'nouvelle'): ?>
                                <?= htmlspecialchars($d['duree']) ?> 
                            <?php elseif ($d['type'] === 'renouvellement'): ?>
                                + <?= htmlspecialchars($d['duree']) ?> 
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($d['activite']) ?></td>
                        <td><?= display_etat_badge($d['etat']) ?></td>
                        <td>
                            <?php if ($d['type'] === 'nouvelle' && $d['demandePDF']): ?>
                                <a href="<?= htmlspecialchars($d['demandePDF']) ?>" target="_blank" class="btn btn-sm btn-info">Voir PDF</a>
                            <?php elseif ($d['type'] === 'renouvellement' && $d['demandePDF'] ):?>
                                <a href="<?= htmlspecialchars($d['demandePDF']) ?>" target="_blank" class="btn btn-sm btn-info">Voir PDF du 1ere demande</a>
                                <a href="<?= htmlspecialchars($d['fichierPDF']) ?>" target="_blank" class="btn btn-sm btn-info mt-1">Voir PDF Autorisation</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($d['etat'] === 'en_attente' && $d['type'] === 'nouvelle'): ?>
                                <a href="admin_gestion_demandes.php?creer_autorisation_id=<?= urlencode($d['id']) ?>" class="btn btn-success btn-sm mb-1">Créer Autorisation</a>
                                <a href="admin_gestion_demandes.php?rejeter_demande_id=<?= urlencode($d['id']) ?>" class="btn btn-danger btn-sm mb-1">Rejeter</a>
                            <?php elseif ($d['etat'] === 'en_attente' && $d['type'] === 'renouvellement'): ?>
                                <a href="admin_gestion_demandes.php?creer_autorisation_id=<?= urlencode($d['id']) ?>" class="btn btn-success btn-sm mb-1">Renouveler Autorisation</a>
                                <a href="admin_gestion_demandes.php?rejeter_demande_id=<?= urlencode($d['id']) ?>" class="btn btn-danger btn-sm mb-1">Rejeter</a>
                            <?php elseif ($d['etat'] === 'acceptee'): ?>
                                <span class="text-success">Autorisée</span>
                                <a href="<?= htmlspecialchars($d['fichierPDF']) ?>" target="_blank" class="btn btn-sm btn-outline-success mt-1">Voir Autorisation</a>
                            <?php elseif ($d['etat'] === 'refusee'): ?>
                                <span class="text-danger">Rejetée</span>
                                <?php if (!empty($d['motifRejet'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1" data-bs-toggle="collapse" data-bs-target="#motif-<?= $d['id'] ?>">
                                        Voir Motif

                                    </button>
                                    <div class="collapse mt-1" id="motif-<?= $d['id'] ?>">
                                        <small class="text-danger d-block"><?= nl2br(htmlspecialchars($d['motifRejet'])) ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>