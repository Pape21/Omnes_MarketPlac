<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Vérifier si l'utilisateur est déjà un vendeur
if ($_SESSION['role'] === 'seller') {
    header("Location: seller/dashboard.php");
    exit();
}

// Vérifier si l'utilisateur a déjà une demande en cours
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM seller_requests WHERE user_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $pending_request = $result->fetch_assoc();
}

// Traitement du formulaire
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    $motivation = $_POST['motivation'];
    $product_types = $_POST['product_types'];
    $experience = $_POST['experience'];
    
    // Validation
    if (empty($motivation) || empty($product_types)) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } else {
        // Insérer la demande dans la base de données
        $sql = "INSERT INTO seller_requests (user_id, motivation, product_types, experience, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $user_id, $motivation, $product_types, $experience);
        
        if ($stmt->execute()) {
            $success_message = "Votre demande a été soumise avec succès. Nous l'examinerons dans les plus brefs délais.";
            
            // Notifier les administrateurs
            $admin_notification = "Nouvelle demande de statut vendeur de " . $_SESSION['username'];
            addAdminNotification($conn, 'seller_request', $admin_notification, "admin/seller-requests.php");
            
            // Rediriger vers la page de confirmation
            header("Location: seller-request-confirmation.php");
            exit();
        } else {
            $error_message = "Une erreur s'est produite lors de la soumission de votre demande. Veuillez réessayer.";
        }
    }
}

// Récupérer les informations de l'utilisateur
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenir vendeur - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/header.php"); ?>
    
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h3 mb-0">Devenir vendeur sur Omnes MarketPlace</h1>
                    </div>
                    <div class="card-body">
                        <?php if (isset($pending_request)): ?>
                            <div class="alert alert-info">
                                <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Demande en cours de traitement</h4>
                                <p>Vous avez déjà soumis une demande pour devenir vendeur le <?php echo date('d/m/Y à H:i', strtotime($pending_request['created_at'])); ?>.</p>
                                <p>Votre demande est actuellement en cours d'examen par notre équipe. Nous vous informerons par email dès qu'une décision aura été prise.</p>
                                <hr>
                                <p class="mb-0">Si vous avez des questions, n'hésitez pas à <a href="contact.php">nous contacter</a>.</p>
                            </div>
                        <?php elseif (!empty($success_message)): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Demande soumise avec succès !</h4>
                                <p><?php echo $success_message; ?></p>
                                <hr>
                                <p class="mb-0">Vous recevrez une notification dès que votre demande aura été traitée.</p>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <h5 class="text-primary">Pourquoi devenir vendeur ?</h5>
                                <div class="row mt-3">
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100 animate-on-scroll">
                                            <div class="card-body text-center">
                                                <i class="fas fa-globe fa-3x text-primary mb-3"></i>
                                                <h6>Touchez une audience mondiale</h6>
                                                <p class="small text-muted">Vendez vos produits à des clients du monde entier.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100 animate-on-scroll">
                                            <div class="card-body text-center">
                                                <i class="fas fa-hand-holding-usd fa-3x text-primary mb-3"></i>
                                                <h6>Commissions compétitives</h6>
                                                <p class="small text-muted">Profitez de nos taux de commission parmi les plus bas du marché.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100 animate-on-scroll">
                                            <div class="card-body text-center">
                                                <i class="fas fa-tools fa-3x text-primary mb-3"></i>
                                                <h6>Outils de gestion avancés</h6>
                                                <p class="small text-muted">Accédez à des outils puissants pour gérer vos ventes et votre inventaire.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="seller-request.php" class="mt-4">
                                <div class="mb-4">
                                    <h5 class="text-primary">Informations personnelles</h5>
                                    <div class="row mt-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">Prénom</label>
                                            <input type="text" class="form-control" id="first_name" value="<?php echo $user['first_name']; ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Nom</label>
                                            <input type="text" class="form-control" id="last_name" value="<?php echo $user['last_name']; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" value="<?php echo $user['email']; ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Téléphone</label>
                                            <input type="tel" class="form-control" id="phone" value="<?php echo $user['phone']; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="text-primary">Votre demande</h5>
                                    <div class="mt-3">
                                        <div class="mb-3">
                                            <label for="motivation" class="form-label fw-medium">Pourquoi souhaitez-vous devenir vendeur sur Omnes MarketPlace ? <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="motivation" name="motivation" rows="4" required></textarea>
                                            <div class="form-text">Expliquez vos motivations et ce que vous espérez accomplir en tant que vendeur.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="product_types" class="form-label">Quels types de produits souhaitez-vous vendre ? <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="product_types" name="product_types" rows="3" required></textarea>
                                            <div class="form-text">Décrivez les catégories de produits que vous prévoyez de vendre.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="experience" class="form-label">Avez-vous une expérience préalable dans la vente en ligne ?</label>
                                            <textarea class="form-control" id="experience" name="experience" rows="3"></textarea>
                                            <div class="form-text">Partagez votre expérience professionnelle pertinente (optionnel).</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="text-primary">Conditions générales</h5>
                                    <div class="mt-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="terms" required>
                                            <label class="form-check-label" for="terms">
                                                J'accepte les <a href="terms.php" target="_blank">conditions générales de vente</a> et la <a href="privacy.php" target="_blank">politique de confidentialité</a> d'Omnes MarketPlace.
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="seller_agreement" required>
                                            <label class="form-check-label" for="seller_agreement">
                                                Je comprends et j'accepte les <a href="seller-terms.php" target="_blank">conditions spécifiques aux vendeurs</a>, y compris les frais et commissions applicables.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Retour
                                    </a>
                                    <button type="submit" name="submit_request" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Soumettre ma demande
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include("includes/footer.php"); ?>
</body>
</html>

