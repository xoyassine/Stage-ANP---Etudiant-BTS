

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="src\anp-maroc-seeklogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Inscription</title>
</head>
<body>
<nav class="navbar bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="src\anp-maroc-seeklogo.png" alt="anp" width="45" height="25">
        </a>
    </div>
</nav>

<div class="container mt-5" style="max-width: 500px;">

    <h3 class="mb-4 text-center fw-bold">Inscription</h3>

    <?php
            require_once 'database.php';

            // TRAITEMENT DU FORMULAIRE
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                // Déterminer les champs selon le type d'utilisateur et récupérer les valeurs dans de variables
                $role = isset($_POST['nom_phys']) ? 'client_physique' : 'client_morale';
                $id = trim($_POST['cin_phys'] ?? $_POST['ice_mor']);
                $nom = trim($_POST['nom_phys'] ?? $_POST['nom_mor'] ?? '');
                $prenom = trim($_POST['prenom_phys'] ?? $_POST['prenom_mor'] ?? '');
                $email = trim($_POST['email_phys'] ?? $_POST['email_mor'] ?? '');
                $telephone = trim($_POST['telephone_phys'] ?? $_POST['telephone_mor'] ?? '');
                $adresse = trim($_POST['adresse_phys'] ?? $_POST['adresse_mor'] ?? '');
                $password = password_hash(trim($_POST['password_phys'] ?? $_POST['password_mor'] ?? ''), PASSWORD_DEFAULT);

                // Vérifier si l'utilisateur existe déjà
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE id = ?");
                $stmt_check->execute([$id]);

                // Si existe, afficher message d'erreur
                if ($stmt_check->fetchColumn() > 0) {
                    die("<meta http-equiv='refresh' content='3; url=inscription.php'>
                            <div class='container mt-5'>
                                <div class='alert alert-danger'>
                                    Erreur : cet utilisateur existe déjà.
                                </div>
                            </div>
                        ");
                }
                // Sinon
                // Ajouter utilisateur dans la table utilisateur
                $stmt = $pdo->prepare("INSERT INTO utilisateur (id, mot_de_passe, nom, prenom, email, telephone, adresse, role, is_valid) VALUES (?,?,?,?,?,?,?,?,0)");
                $stmt->execute([$id, $password, $nom, $prenom, $email, $telephone, $adresse, $role]);

                // Répertoire pour les fichiers
                $upload_dir = "uploads/fichiers_utilisateurs/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755);


                if ($role === 'client_physique') {
                    // Nom unique pour éviter écrasement
                    $CINRecto = $upload_dir . $id . "_recto_" . basename($_FILES['CINRecto']['name']);
                    $CINVerso = $upload_dir . $id . "_verso_" . basename($_FILES['CINVerso']['name']);

                    // Déplacer les fichiers uploadés
                    move_uploaded_file($_FILES['CINRecto']['tmp_name'], $CINRecto);
                    move_uploaded_file($_FILES['CINVerso']['tmp_name'], $CINVerso);

                    // Insérer dans la table client_physique
                    $stmt2 = $pdo->prepare("INSERT INTO client_physique (id, numeroCIN, docCIN, docCINVerso) VALUES (?, ?, ?, ?)");
                    $stmt2->execute([$id, $_POST['cin_phys'], $CINRecto, $CINVerso]);

                } else {
                    $docReg = $upload_dir . $id . "_" . basename($_FILES['Registre']['name']);
                    move_uploaded_file($_FILES['Registre']['tmp_name'], $docReg);

                    $stmt2 = $pdo->prepare("INSERT INTO client_morale (id, ICE, docRegistreCommerce, raisonSocial, secteurActivite) VALUES (?,?,?,?,?)");
                    $stmt2->execute([$id, $_POST['ice_mor'], $docReg, $_POST['nom_mor'] . ' ' . $_POST['prenom_mor'], $_POST['secteurActivite']]);
                }

                echo "<div class='container mt-5'>
                        <div class='alert alert-success'>Inscription réussie ! En attente de validation par l'administrateur.</div>
                    </div>";
            }
    ?>

    
    <ul class="nav nav-tabs d-flex justify-content-center" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="physique-tab" data-bs-toggle="tab" data-bs-target="#physique" type="button" role="tab">Personne Physique</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="morale-tab" data-bs-toggle="tab" data-bs-target="#morale" type="button" role="tab">Personne Morale</button>
        </li>
    </ul>
    <br>
    <div class="tab-content">

        <!-- Personne Physique -->
        <div class="tab-pane fade show active" id="physique" role="tabpanel">
            <form action="inscription.php" method="post" enctype="multipart/form-data">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nom_phys" name="nom_phys" placeholder="Nom" required>
                    <label for="nom_phys">Nom</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="prenom_phys" name="prenom_phys" placeholder="Prenom" required>
                    <label for="prenom_phys">Prenom</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="adresse_phys" name="adresse_phys" placeholder="Adresse" required>
                    <label for="adresse_phys">Adresse</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email_phys" name="email_phys" placeholder="Email" required>
                    <label for="email_phys">Email</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="tel" class="form-control" id="telephone_phys" name="telephone_phys" placeholder="Téléphone" required>
                    <label for="telephone_phys">Téléphone</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="cin_phys" name="cin_phys" placeholder="Numéro CIN" required>
                    <label for="cin_phys">Numéro CIN</label>
                </div>
                <div class="mb-3">
                    <label for="CINRecto" class="form-label">CIN recto</label>
                    <input class="form-control" type="file" id="CINRecto" name="CINRecto" required>
                </div>
                <div class="mb-3">
                    <label for="CINVerso" class="form-label">CIN verso</label>
                    <input class="form-control" type="file" id="CINVerso" name="CINVerso" required>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password_phys" name="password_phys" placeholder="Mot de passe" required>
                    <label for="password_phys">Mot de passe</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password_phys_conf" name="password_phys_conf" placeholder="Confirmer mot de passe" required>
                    <label for="password_phys_conf">Confirmer mot de passe</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">S'inscrire</button>
                <div class="col-12 mt-2 mb-3">
                    <a href="connection.php" class="btn btn-outline-secondary w-100">Déja inscrit</a>
                </div>
            </form>
        </div>

        <!-- Personne Morale -->
        <div class="tab-pane fade" id="morale" role="tabpanel">
            <form action="inscription.php" method="post" enctype="multipart/form-data">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nom_mor" name="nom_mor" placeholder="Nom" required>
                    <label for="nom_mor">Nom</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="prenom_mor" name="prenom_mor" placeholder="Prenom" required>
                    <label for="prenom_mor">Prenom</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="adresse_mor" name="adresse_mor" placeholder="Adresse" required>
                    <label for="adresse_mor">Adresse</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email_mor" name="email_mor" placeholder="Email" required>
                    <label for="email_mor">Email</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="tel" class="form-control" id="telephone_mor" name="telephone_mor" placeholder="Téléphone" required>
                    <label for="telephone_mor">Téléphone</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="ice_mor" name="ice_mor" placeholder="ICE" required
                        pattern="[0-9]{15}" minlength="15" maxlength="15">
                    <label for="ice_mor">ICE (15 chiffres)</label>
                </div>
                <div class="mb-3">
                    <label for="Registre" class="form-label">Registre Commerce</label>
                    <input class="form-control" type="file" id="Registre" name="Registre" required>
                </div>
                <select class="form-select form-select-lg mb-3" name="secteurActivite" aria-label="secteurActivite">
                    <option selected>Secteur d'Activité</option>
                    <option value="Agriculture">Agriculture</option>
                    <option value="Pêche">Pêche</option>
                    <option value="Industrie">Industrie</option>
                    <option value="BTP">BTP</option>
                    <option value="ProductionEnergie">Production d'énergie</option>
                    <option value="Commerce">Commerce</option>
                    <option value="ServicesEntreprise">Services aux entreprises</option>
                    <option value="ServicesParticuliers">Services aux particuliers</option>
                    <option value="Logistique">Logistique</option>
                    <option value="Finance">Finance</option>
                    <option value="Immobilier">Immobilier</option>
                    <option value="Information">Information</option>
                    <option value="Santé">Santé</option>
                    <option value="Culture">Culture</option>
                    <option value="Publique">Administration publique</option>
                </select>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password_mor" name="password_mor" placeholder="Mot de passe" required>
                    <label for="password_mor">Mot de passe</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password_mor_conf" name="password_mor_conf" placeholder="Confirmer mot de passe" required>
                    <label for="password_mor_conf">Confirmer mot de passe</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">S'inscrire</button>
                <div class="col-12 mt-3 mb-3">
                    <a href="connection.php" class="btn btn-outline-secondary w-100">Déja inscrit</a>
                </div>
            </form>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
