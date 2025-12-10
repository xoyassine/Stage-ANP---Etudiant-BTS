<?php
session_start();
require_once 'database.php';

// 1. Sécuriser l'accès au tableau de bord
// Vérifier si l'utilisateur est connecté et n'est PAS un admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] === 'admin') {
    header("Location: connection.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$demandes = [];

try {
    // 2. Récupérer toutes les demandes faites par cet utilisateur
    // Utilisation de requêtes préparées pour la sécurité
    $stmt = $pdo->prepare("SELECT id, dateDepot, superficie, duree, activite, port, etat, demandePDF, motifRejet 
                          FROM demande 
                          WHERE idUtilisateur = ? 
                          ORDER BY dateDepot DESC");
    $stmt->execute([$user_id]);
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Afficher une erreur si la requête échoue
    $errorMessage = "Erreur lors du chargement des demandes: " . $e->getMessage();
}

// Fonction pour afficher l'état avec une couleur Bootstrap
function display_etat($etat) {
    switch ($etat) {
        case 'acceptee':
            return '<span class="badge text-bg-success">Acceptée</span>';
        case 'refusee':
            return '<span class="badge text-bg-danger">Refusée</span>';
        case 'en_attente':
        default:
            return '<span class="badge text-bg-warning">En Attente</span>';
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
    <title>DEMANDES DASHBOARD</title>
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
                        <a class="nav-link active" aria-current="page" href="#">DEMANDES</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="autorisationsDashboard.php">AUTORISATIONS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nouvelle_demande.php">NOUVELLE DEMANDE</a>
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

    <div class="container mt-5">
        <h3 class="mb-4">Historique de mes demandes</h3>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php elseif (empty($demandes)): ?>
            <div class="alert alert-info">Vous n'avez pas encore soumis de demande.</div>
        <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th scope="col">Numéro</th>
                        <th scope="col">Date du Dépôt</th>
                        <th scope="col">Superficie</th>
                        <th scope="col">Durée</th>
                        <th scope="col">Activité</th>
                        <th scope="col">Port</th>
                        <th scope="col">État</th>
                        <th scope="col">Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandes as $demande): ?>
                        <tr>
                            <th scope="row"><?= htmlspecialchars($demande['id']) ?></th>
                            <td><?= htmlspecialchars($demande['dateDepot']) ?></td>
                            <td><?= htmlspecialchars($demande['superficie']) ?> m²</td>
                            <td><?= htmlspecialchars($demande['duree']) ?> ans</td>
                            <td><?= htmlspecialchars($demande['activite']) ?></td>
                            <td><?= htmlspecialchars($demande['port']) ?></td>
                            <td><?= display_etat($demande['etat']) ?></td>
                            <td>
                                <?php if ($demande['demandePDF']): ?>
                                    <a href="<?= htmlspecialchars($demande['demandePDF']) ?>" target="_blank" class="btn btn-sm btn-info mb-1">Voir PDF</a>
                                <?php endif; ?>
                                
                                <?php if ($demande['etat'] === 'refusee' && !empty($demande['motifRejet'])): ?>
                                    <button class="btn btn-sm btn-secondary mb-1" type="button" data-bs-toggle="collapse" data-bs-target="#rejet-<?= $demande['id'] ?>" aria-expanded="false">
                                        Motif Rejet
                                    </button>
                                    <div class="collapse mt-1" id="rejet-<?= $demande['id'] ?>">
                                        <div class="card card-body p-2 text-danger">
                                            <?= htmlspecialchars($demande['motifRejet']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($demande['etat'] === 'acceptee'): ?>
                                    <a href="autorisationsDashboard.php?demande_id=<?= $demande['id'] ?>" class="btn btn-sm btn-success mb-1">Voir La Page Autorisation</a>
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