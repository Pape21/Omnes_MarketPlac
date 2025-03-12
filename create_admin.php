<?php
// Script de création d'un compte administrateur par défaut
// À exécuter une seule fois lors de l'installation initiale
// IMPORTANT: Supprimer ou renommer ce fichier après utilisation

session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Vérifier si le script a déjà été exécuté
$admin_setup_file = 'admin_setup_complete.txt';

if (file_exists($admin_setup_file)) {
    die("Le compte administrateur a déjà été créé. Par mesure de sécurité, ce script ne peut être exécuté qu'une seule fois.<br>
    Si vous devez créer un nouvel administrateur, connectez-vous avec le compte admin existant et utilisez l'interface d'administration.");
}

// Fonction pour générer un mot de passe mémorisable mais sécurisé
function generateSecurePassword() {
    $adjectives = ['Super', 'Grand', 'Petit', 'Rapide', 'Fort', 'Brillant', 'Nouveau'];
    $nouns = ['Marché', 'Commerce', 'Vendeur', 'Produit', 'Client', 'Système', 'Projet'];
    $numbers = rand(100, 999);
    $symbols = ['!', '@', '#', '$', '%', '&'];
    $symbol = $symbols[array_rand($symbols)];
    
    return $adjectives[array_rand($adjectives)] . $nouns[array_rand($nouns)] . $numbers . $symbol;
}

// Informations pour le compte admin par défaut
$username = "admin";
$email = "admin@omnesmarketplace.com";
$password = generateSecurePassword();
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$first_name = "Admin";
$last_name = "Système";
$role = "admin";

// Vérifier si un admin existe déjà
$sql = "SELECT * FROM users WHERE role = 'admin'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    die("Un compte administrateur existe déjà dans la base de données.");
}

// Insérer l'administrateur dans la base de données
$sql = "INSERT INTO users (username, email, password, first_name, last_name, role, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $username, $email, $hashed_password, $first_name, $last_name, $role);

$success = false;
$error_message = "";

if ($stmt->execute()) {
    $success = true;
    
    // Créer un fichier pour indiquer que le script a été exécuté
    file_put_contents($admin_setup_file, date('Y-m-d H:i:s'));
    
    // Enregistrer l'action dans les logs
    $log_message = "Compte administrateur créé: $username ($email) à " . date('Y-m-d H:i:s');
    file_put_contents('logs/admin_creation.log', $log_message . PHP_EOL, FILE_APPEND);
} else {
    $error_message = "Erreur lors de la création du compte: " . $stmt->error;
}

// Fermer la connexion
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création du compte administrateur - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-setup-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .credentials-box {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .security-warning {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-setup-container">
            <h1 class="text-center mb-4">Configuration du compte administrateur</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4><i class="fas fa-check-circle"></i> Compte administrateur créé avec succès!</h4>
                    <p>Vous pouvez maintenant vous connecter avec les identifiants suivants:</p>
                </div>
                
                <div class="credentials-box">
                    <p><strong>Nom d'utilisateur:</strong> <?php echo $username; ?></p>
                    <p><strong>Email:</strong> <?php echo $email; ?></p>
                    <p><strong>Mot de passe:</strong> <?php echo $password; ?></p>
                </div>
                
                <div class="security-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Important - Actions requises:</h5>
                    <ol>
                        <li>Notez ces informations immédiatement. Elles ne seront plus affichées.</li>
                        <li>Connectez-vous dès maintenant à <a href="login.php">la page de connexion</a>.</li>
                        <li>Changez votre mot de passe dans les paramètres du compte.</li>
                        <li><strong>Supprimez ce fichier (create_admin.php) du serveur</strong> pour des raisons de sécurité.</li>
                    </ol>
                </div>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">Aller à la page de connexion</a>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h4><i class="fas fa-times-circle"></i> Erreur lors de la création du compte</h4>
                    <p><?php echo $error_message; ?></p>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>

