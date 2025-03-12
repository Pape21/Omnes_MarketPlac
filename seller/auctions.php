<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un vendeur
if (!isLoggedIn() || !isSeller()) {
    header("Location: ../login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Traitement de l'envoi de notification
if (isset($_POST['send_notification']) && isset($_POST['product_id']) && isset($_POST['winner_id'])) {
    $product_id = intval($_POST['product_id']);
    $winner_id = intval($_POST['winner_id']);
    $message = isset($_POST['message']) ? trim($_POST['message']) : "Félicitations ! Vous avez remporté l'enchère.";
    
    // Vérifier que le produit appartient bien au vendeur
    $check_product = "SELECT * FROM products WHERE id = $product_id AND seller_id = $seller_id";
    $product_result = $conn->query($check_product);
    
    if ($product_result && $product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        $product_name = $product['name'];
        
        // Créer un lien vers la page du produit
        $link = "product.php?id=$product_id";
        
        // Envoyer la notification
        if (sendNotification($conn, $winner_id, $message, $link)) {
            $success_message = "Notification envoyée avec succès au gagnant de l'enchère pour le produit '$product_name'.";
        } else {
            $error_message = "Erreur lors de l'envoi de la notification.";
        }
    } else {
        $error_message = "Vous n'êtes pas autorisé à envoyer une notification pour ce produit.";
    }
}

// Récupérer les enchères terminées du vendeur avec les gagnants
$current_date = date('Y-m-d H:i:s');
$auctions_query = "SELECT p.*, 
                  (SELECT e.user_id FROM encheres e WHERE e.product_id = p.id ORDER BY e.amount DESC LIMIT 1) as winner_id,
                  (SELECT e.amount FROM encheres e WHERE e.product_id = p.id ORDER BY e.amount DESC LIMIT 1) as winning_bid,
                  (SELECT u.username FROM users u WHERE u.id = (SELECT e2.user_id FROM encheres e2 WHERE e2.product_id = p.id ORDER BY e2.amount DESC LIMIT 1)) as winner_name,
                  (SELECT COUNT(*) FROM encheres e WHERE e.product_id = p.id) as bid_count
                  FROM products p 
                  WHERE p.seller_id = $seller_id 
                  AND p.sale_type = 'auction' 
                  AND p.auction_end < '$current_date'
                  ORDER BY p.auction_end DESC";

$auctions_result = $conn->query($auctions_query);
$auctions = [];

if ($auctions_result && $auctions_result->num_rows > 0) {
    while ($row = $auctions_result->fetch_assoc()) {
        $auctions[] = $row;
    }
}

// Récupérer les enchères en cours du vendeur
$active_auctions_query = "SELECT p.*, 
                         (SELECT e.amount FROM encheres e WHERE e.product_id = p.id ORDER BY e.amount DESC LIMIT 1) as current_bid,
                         (SELECT COUNT(*) FROM encheres e WHERE e.product_id = p.id) as bid_count
                         FROM products p 
                         WHERE p.seller_id = $seller_id 
                         AND p.sale_type = 'auction' 
                         AND p.auction_end >= '$current_date'
                         ORDER BY p.auction_end ASC";

$active_auctions_result = $conn->query($active_auctions_query);
$active_auctions = [];

if ($active_auctions_result && $active_auctions_result->num_rows > 0) {
    while ($row = $active_auctions_result->fetch_assoc()) {
        $active_auctions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Enchères - Espace Vendeur</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/seller.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Omnes MarketPlace</h5>
                        <p class="text-white-50">Espace Vendeur</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box"></i> Mes produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_product.php">
                                <i class="fas fa-plus"></i> Ajouter un produit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart"></i> Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="negotiations.php">
                                <i class="fas fa-comments-dollar"></i> Négociations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="auctions.php">
                                <i class="fas fa-gavel"></i> Enchères
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Rapports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Paramètres
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home"></i> Retour au site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des Enchères</h1>
                </div>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Enchères actives -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Enchères en cours</h6>
                        <span class="badge bg-primary"><?php echo count($active_auctions); ?> enchère(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_auctions)): ?>
                            <div class="alert alert-info">
                                <p class="mb-0">Vous n'avez pas d'enchères en cours.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Prix de départ</th>
                                            <th>Enchère actuelle</th>
                                            <th>Nombre d'enchères</th>
                                            <th>Fin de l'enchère</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_auctions as $auction): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo $auction['image']; ?>" alt="<?php echo $auction['name']; ?>" class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                                        <div>
                                                            <strong><?php echo $auction['name']; ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?php echo $auction['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($auction['price'], 2); ?> €</td>
                                                <td>
                                                    <?php if ($auction['current_bid']): ?>
                                                        <span class="text-success"><?php echo number_format($auction['current_bid'], 2); ?> €</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucune enchère</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $auction['bid_count']; ?></td>
                                                <td>
                                                    <?php 
                                                    $end_date = new DateTime($auction['auction_end']);
                                                    $now = new DateTime();
                                                    $interval = $now->diff($end_date);
                                                    
                                                    if ($interval->days > 0) {
                                                        echo $interval->format('%a jours, %h heures');
                                                    } else if ($interval->h > 0) {
                                                        echo $interval->format('%h heures, %i minutes');
                                                    } else {
                                                        echo $interval->format('%i minutes');
                                                    }
                                                    ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($auction['auction_end'])); ?></small>
                                                </td>
                                                <td>
                                                    <a href="../product.php?id=<?php echo $auction['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="fas fa-eye"></i> Voir
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Enchères terminées -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Enchères terminées</h6>
                        <span class="badge bg-secondary"><?php echo count($auctions); ?> enchère(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($auctions)): ?>
                            <div class="alert alert-info">
                                <p class="mb-0">Vous n'avez pas d'enchères terminées.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Prix de départ</th>
                                            <th>Enchère gagnante</th>
                                            <th>Gagnant</th>
                                            <th>Nombre d'enchères</th>
                                            <th>Date de fin</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($auctions as $auction): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo $auction['image']; ?>" alt="<?php echo $auction['name']; ?>" class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                                        <div>
                                                            <strong><?php echo $auction['name']; ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?php echo $auction['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($auction['price'], 2); ?> €</td>
                                                <td>
                                                    <?php if ($auction['winning_bid']): ?>
                                                        <span class="text-success"><?php echo number_format($auction['winning_bid'], 2); ?> €</span>
                                                    <?php else: ?>
                                                        <span class="text-danger">Aucune enchère</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($auction['winner_name']): ?>
                                                        <span class="text-primary"><?php echo $auction['winner_name']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-danger">Aucun gagnant</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $auction['bid_count']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($auction['auction_end'])); ?></td>
                                                <td>
                                                    <a href="../product.php?id=<?php echo $auction['id']; ?>" class="btn btn-sm btn-info mb-1" target="_blank">
                                                        <i class="fas fa-eye"></i> Voir
                                                    </a>
                                                    
                                                    <?php if ($auction['winner_id']): ?>
                                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#notifyModal<?php echo $auction['id']; ?>">
                                                            <i class="fas fa-bell"></i> Notifier
                                                        </button>
                                                        
                                                        <!-- Modal de notification -->
                                                        <div class="modal fade" id="notifyModal<?php echo $auction['id']; ?>" tabindex="-1" aria-labelledby="notifyModalLabel<?php echo $auction['id']; ?>" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="notifyModalLabel<?php echo $auction['id']; ?>">Notifier le gagnant de l'enchère</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <form action="" method="POST">
                                                                        <div class="modal-body">
                                                                            <input type="hidden" name="product_id" value="<?php echo $auction['id']; ?>">
                                                                            <input type="hidden" name="winner_id" value="<?php echo $auction['winner_id']; ?>">
                                                                            
                                                                            <div class="mb-3">
                                                                                <label for="message<?php echo $auction['id']; ?>" class="form-label">Message</label>
                                                                                <textarea class="form-control" id="message<?php echo $auction['id']; ?>" name="message" rows="3">Félicitations ! Vous avez remporté l'enchère pour "<?php echo $auction['name']; ?>" au prix de <?php echo number_format($auction['winning_bid'], 2); ?> €. Veuillez procéder au paiement pour finaliser votre achat.</textarea>
                                                                            </div>
                                                                            
                                                                            <p class="text-muted small">
                                                                                <i class="fas fa-info-circle"></i> Cette notification sera envoyée à <strong><?php echo $auction['winner_name']; ?></strong> et apparaîtra dans son centre de notifications.
                                                                            </p>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                            <button type="submit" name="send_notification" class="btn btn-primary">Envoyer la notification</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

