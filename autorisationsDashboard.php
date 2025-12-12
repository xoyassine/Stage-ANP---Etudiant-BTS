<?php
session_start();
require_once 'database.php';

// 1. Sécuriser l'accès
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] === 'admin') {
    header("Location: connection.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$autorisations = [];
$errorMessage = "";
$successMessage = "";

// --- LOGIQUE DE RENOUVELLEMENT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renouveler') {
    
    $autorisation_id = trim($_POST['autorisation_id'] ?? '');
    $nouvelle_duree = trim($_POST['nouvelle_duree'] ?? '');
    
    if (empty($autorisation_id) || empty($nouvelle_duree) || !is_numeric($nouvelle_duree) || $nouvelle_duree <= 0) {
        $errorMessage = "Erreur: Veuillez sélectionner une autorisation et spécifier une durée valide pour le renouvellement.";
    } else {
        try {
            // 1. Récupérer les détails de l'ancienne autorisation pour la nouvelle demande
            $stmtOldAuth = $pdo->prepare("
                SELECT d.superficie, d.activite, d.port, a.idDemande, d.demandePDF, d.base_demande
                FROM autorisation a 
                JOIN demande d ON a.idDemande = d.id 
                WHERE a.id = ?");
            $stmtOldAuth->execute([$autorisation_id]);
            $oldDetails = $stmtOldAuth->fetch(PDO::FETCH_ASSOC);

            if (!$oldDetails) {
                $errorMessage = "Autorisation d'origine introuvable.";
            } else {
                // Déterminer la base de la demande pour le renouvellement (utilise l'ancienne base ou l'id de la demande initiale)
                $baseDemandeId = $oldDetails['base_demande'] ?? $oldDetails['idDemande'];
                
                // 2. Insérer une NOUVELLE DEMANDE (Type Renouvellement)
                $stmtNewDemande = $pdo->prepare("
                    INSERT INTO demande 
                    (idUtilisateur, dateDepot, superficie, duree, activite, type, base_demande, port, etat, demandePDF, motifRejet) 
                    VALUES (?, NOW(), ?, ?, ?, 'renouvellement', ?, ?, 'en_attente', ?, NULL)");
                
                $stmtNewDemande->execute([
                    $user_id,
                    $oldDetails['superficie'],
                    $nouvelle_duree,
                    $oldDetails['activite'],
                    $baseDemandeId,
                    $oldDetails['port'],
                    $oldDetails['demandePDF'] 
                ]);
                
                $newDemandeId = $pdo->lastInsertId();

                $successMessage = "Demande de renouvellement pour l'Autorisation N°{$autorisation_id} soumise avec succès (Nouvelle Demande N°{$newDemandeId}).";
            }

        } catch (PDOException $e) {
            $errorMessage = "Erreur de base de données lors du renouvellement : " . $e->getMessage();
        }
    }
}


// --- LOGIQUE DE RÉCUPÉRATION DES AUTORISATIONS ---

try {
    // Récupérer les autorisations actuelles de l'utilisateur avec le statut de renouvellement
    $sql = "SELECT
                a.numero_autorisation AS idAutorisation,
                a.dateAutorisation,
                a.dateFin,
                a.fichierPDF,
                d.id AS idDemande,
                d.superficie,
                d.duree,
                d.activite,
                d.port
                /* Vérifie si une autre demande acceptée existe pour cette autorisation de base (Renouvellement) */
                , COALESCE(
                    (SELECT 'oui' FROM demande d2 
                     WHERE d2.base_demande = d.base_demande 
                       AND d2.etat = 'acceptee' 
                       AND d2.id > d.id
                     LIMIT 1), 'non') AS est_renouvelee
            FROM
                autorisation a
            JOIN
                demande d ON a.idDemande = d.id
            WHERE
                d.idUtilisateur = ?
            ORDER BY
                a.dateFin ASC"; 
                
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $autorisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMessage = "Erreur lors du chargement des autorisations.";
}

// 3. Fonction pour déterminer le statut de l'autorisation (Combinée)
function get_autorisation_status($dateFin, $estRenouvelee) {
    try {
        $dateFinObj = new DateTime($dateFin);
        $aujourdhui = new DateTime();
        $interval = $aujourdhui->diff($dateFinObj);
    } catch (Exception $e) {
        return '<span class="badge bg-secondary">Date Invalide</span>';
    }

    // 1. Vérification du statut 'Renouvelée'
    if ($estRenouvelee === 'oui') {
        return '<span class="badge bg-info text-white">Renouvelée</span>';
    }

    // 2. Vérification du statut 'Échouée' (Expirée)
    if ($dateFinObj < $aujourdhui) {
        return '<span class="badge bg-danger">Échouée</span>';
    }
    
    // 3. Vérification du statut 'En Cours' (Valide) et alerte
    if ($interval->days < 90) {
        return '<span class="badge bg-warning text-dark">Expire dans ' . $interval->days . ' jours</span>';
    } else {
        return '<span class="badge bg-success">En Cours</span>';
    }
}

// Fonction utilitaire pour le statut d'expiration (Utilisée dans la Modale)
function get_expiration_status($dateFin) {
    try {
        $dateFinObj = new DateTime($dateFin);
        $aujourdhui = new DateTime();
        $interval = $aujourdhui->diff($dateFinObj);
        
        if ($interval->invert) {
            return '<span class="badge bg-danger">Expirée</span>';
        } elseif ($interval->days < 90) {
            return '<span class="badge bg-warning text-dark">Expire dans ' . $interval->days . ' jours</span>';
        } else {
            return '<span class="badge bg-success">Valide</span>';
        }
    } catch (Exception $e) {
        return '<span class="badge bg-secondary">Date Invalide</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Renouvellement d'Autorisation</title>
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
                        <a class="nav-link" href="demandesDashboard.php">DEMANDES</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">AUTORISATIONS</a>
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
        <h3 class="mb-4">Renouvellement d'Autorisations d'Occupation</h3>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($autorisations)): ?>
            <div class="alert alert-info">Vous n'avez aucune autorisation à renouveler.</div>
        <?php else: ?>
            
            <h5 class="mb-3">Autorisations Actuelles</h5>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th scope="col">N° Aut.</th>
                        <th scope="col">Date Fin</th>
                        <th scope="col">Port</th>
                        <th scope="col">Superficie (m²)</th>
                        <th scope="col">Activité</th>
                        <th scope="col">PDF</th>
                        <th scope="col">Facture</th>
                        <th scope="col">Statut</th>
                        <th scope="col"> </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($autorisations as $auth): ?>
                        <tr>
                            <td><?= htmlspecialchars($auth['idAutorisation']) ?></td>
                            <td><?= htmlspecialchars($auth['dateFin']) ?></td>
                            <td><?= htmlspecialchars($auth['port']) ?></td>
                            <td><?= htmlspecialchars($auth['superficie']) ?></td>
                            <td><?= htmlspecialchars($auth['activite']) ?></td>
                            <td>
                                <?php if ($auth['fichierPDF']): ?>
                                    <a href="<?= htmlspecialchars($auth['fichierPDF']) ?>" target="_blank" class="btn btn-sm btn-info mb-1">Voir PDF</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($auth['fichierPDF']): ?>
                                    <a href="<?= htmlspecialchars($auth['fichierPDF']) ?>" target="_blank" class="btn btn-sm btn-info mb-1">Voir Facture</a>
                                <?php endif; ?>
                            </td>
                            <td><?= get_autorisation_status($auth['dateFin'], $auth['est_renouvelee']) ?></td>
                            <td>
                                <?php 
                                    // Afficher le bouton de renouvellement si l'autorisation n'est pas déjà marquée comme Renouvelée
                                    if ($auth['est_renouvelee'] === 'non'): 
                                ?>
                                <button type="button" 
                                        class="btn btn-sm btn-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#renouvellementModal"
                                        data-auth-id="<?= $auth['idAutorisation'] ?>"
                                        data-auth-port="<?= htmlspecialchars($auth['port']) ?>"
                                        data-auth-fin="<?= htmlspecialchars($auth['dateFin']) ?>">
                                    Renouveler
                                </button>
                                <?php else: ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="modal fade" id="renouvellementModal" tabindex="-1" aria-labelledby="renouvellementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="autorisationsDashboard.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="renouvellementModalLabel">Renouveler Autorisation N° <span id="modal-auth-id"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="renouveler">
                        <input type="hidden" name="autorisation_id" id="input-auth-id">

                        <p><strong>Date de Fin Actuelle:</strong> <span id="modal-auth-fin" class="text-danger"></span></p>
                        <p><strong>Port:</strong> <span id="modal-auth-port"></span></p>
                        
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control" id="nouvelle_duree" name="nouvelle_duree" placeholder="Nouvelle durée (années)" min="1" required>
                            <label for="nouvelle_duree">Durée de renouvellement (années)</label>
                        </div>
                        
                        <div class="alert alert-warning small">
                            La soumission créera une nouvelle demande d'occupation qui sera traitée par l'administration.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Soumettre la Demande</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script JavaScript pour préremplir la modale
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('renouvellementModal');
        modal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            
            var authId = button.getAttribute('data-auth-id');
            var authPort = button.getAttribute('data-auth-port');
            var authFin = button.getAttribute('data-auth-fin');

            // Mise à jour des champs dans la modale
            document.getElementById('modal-auth-id').textContent = authId;
            document.getElementById('input-auth-id').value = authId;
            
            document.getElementById('modal-auth-port').textContent = authPort;
            document.getElementById('modal-auth-fin').textContent = authFin;
        });
    });
</script>
</body>
</html>