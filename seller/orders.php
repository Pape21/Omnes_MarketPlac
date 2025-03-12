<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo "<div style='color:red; padding:20px; background:#ffeeee; border:1px solid red;'>";
    echo "Erreur: Vous devez être connecté pour accéder à cette page.<br>";
    echo "Cette page devrait normalement rediriger vers ../login.php";
    echo "</div>";
    // Commentez temporairement la redirection pour voir le message
    // $_SESSION['error'] = "Vous devez être connecté pour accéder à cette page.";
    // header("Location: ../login.php");
    // exit();
}

// Récupérer l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Vérifier le rôle de l'utilisateur dans la base de données
$role_query = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$role_result = $stmt->get_result();

if ($role_result->num_rows == 1) {
    $user_role = $role_result->fetch_assoc()['role'];
    $_SESSION['role'] = $user_role; // Mettre à jour la session avec le rôle
    
    // Vérifier si l'utilisateur est un vendeur ou un administrateur
    if ($user_role !== 'seller' && $user_role !== 'admin') {
        echo "<div style='color:red; padding:20px; background:#ffeeee; border:1px solid red;'>";
        echo "Erreur: Vous n'avez pas les droits d'accès à l'espace vendeur.<br>";
        echo "Cette page devrait normalement rediriger vers ../login.php";
        echo "</div>";
        // Commentez temporairement la redirection pour voir le message
        // $_SESSION['error'] = "Vous n'avez pas les droits d'accès à l'espace vendeur.";
        // header("Location: ../login.php");
        // exit();
    }
} else {
    echo "<div style='color:red; padding:20px; background:#ffeeee; border:1px solid red;'>";
    echo "Erreur: Utilisateur non trouvé dans la base de données.<br>";
    echo "Cette page devrait normalement rediriger vers ../login.php";
    echo "</div>";
    // Commentez temporairement la redirection pour voir le message
    // session_destroy();
    // $_SESSION['error'] = "Session invalide. Veuillez vous reconnecter.";
    // header("Location: ../login.php");
    // exit();
}

// Récupérer l'ID du vendeur
$seller_id = $_SESSION['user_id'];

// Vérifier si la colonne order_status existe dans la table orders
$checkColumnQuery = "SHOW COLUMNS FROM orders LIKE 'order_status'";
$columnResult = $conn->query($checkColumnQuery);

if ($columnResult && $columnResult->num_rows == 0) {
    // La colonne order_status n'existe pas, on la crée
    $addColumnQuery = "ALTER TABLE orders ADD COLUMN order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'";
    $conn->query($addColumnQuery);
    // Mettre à jour les commandes existantes
    $conn->query("UPDATE orders SET order_status = 'pending' WHERE order_status IS NULL");
}

// Gérer la mise à jour du statut d'une commande
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    // Vérifier que la commande contient des produits du vendeur
    $check_query = "
        SELECT COUNT(*) as count 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN products p ON oi.product_id = p.id 
        WHERE o.id = ? AND p.seller_id = ?
    ";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($count > 0) {
        // Mettre à jour le statut de la commande
        $update_query = "UPDATE orders SET order_status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $order_id);
        
        if ($stmt->execute()) {
            // Ajouter à l'historique des statuts
            $history_query = "INSERT INTO status_history (order_id, user_id, status, created_at) VALUES (?, ?, ?, NOW())";
            $stmt_history = $conn->prepare($history_query);
            $stmt_history->bind_param("iis", $order_id, $seller_id, $new_status);
            $stmt_history->execute();
            $stmt_history->close();
            
            // Récupérer les informations de l'acheteur pour la notification
            $buyer_query = "SELECT user_id FROM orders WHERE id = ?";
            $stmt_buyer = $conn->prepare($buyer_query);
            $stmt_buyer->bind_param("i", $order_id);
            $stmt_buyer->execute();
            $buyer_result = $stmt_buyer->get_result();
            $buyer_id = $buyer_result->fetch_assoc()['user_id'];
            $stmt_buyer->close();
            
            // Créer un message de notification basé sur le nouveau statut
            $status_messages = [
                'processing' => 'Votre commande #' . $order_id . ' est en cours de traitement.',
                'shipped' => 'Votre commande #' . $order_id . ' a été expédiée.',
                'delivered' => 'Votre commande #' . $order_id . ' a été livrée.',
                'cancelled' => 'Votre commande #' . $order_id . ' a été annulée.'
            ];
            
            $notification_message = $status_messages[$new_status] ?? 'Le statut de votre commande #' . $order_id . ' a été mis à jour.';
            
            // Envoyer une notification à l'acheteur si la fonction existe
            if (function_exists('sendNotification')) {
                $notification_link = 'order_details.php?id=' . $order_id;
                sendNotification($conn, $buyer_id, $notification_message, $notification_link);
            }
            
            $_SESSION['success'] = "Le statut de la commande a été mis à jour avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour du statut de la commande.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à modifier cette commande.";
    }
    
    // Rediriger pour éviter la resoumission du formulaire
    header('Location: orders.php');
    exit;
}

// Filtrage des commandes
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$status_condition = '';
if ($status_filter) {
    $status_condition = "AND o.order_status = '$status_filter'";
}

// Récupérer les commandes qui contiennent des produits du vendeur
$orders_query = "
    SELECT DISTINCT o.*, u.username, u.email 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    JOIN users u ON o.user_id = u.id
    WHERE p.seller_id = ? $status_condition
    ORDER BY o.created_at DESC
";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Calculer les statistiques
$total_orders = count($orders);

$pending_orders = array_filter($orders, function($order) {
    return $order['order_status'] == 'pending';
});

$processing_orders = array_filter($orders, function($order) {
    return $order['order_status'] == 'processing';
});

$shipped_orders = array_filter($orders, function($order) {
    return $order['order_status'] == 'shipped';
});

$delivered_orders = array_filter($orders, function($order) {
    return $order['order_status'] == 'delivered';
});

$cancelled_orders = array_filter($orders, function($order) {
    return $order['order_status'] == 'cancelled';
});

// Calculer le chiffre d'affaires du vendeur
$seller_revenue_query = "
    SELECT SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.seller_id = ? AND o.payment_status = 'paid'
";
$stmt = $conn->prepare($seller_revenue_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$revenue_result = $stmt->get_result();
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'] ?? 0;
$stmt->close();

// Titre de la page
$page_title = "Gestion des Commandes";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Espace Vendeur</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/seller.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <!-- Sidebar content -->
                
                <div class="position-sticky pt-3">
                    <div class="d-flex align-items-center px-3 mb-3 text-white">
                        <i class="fas fa-store me-2"></i>
                        <span class="fs-5">Espace Vendeur</span>
                    </div>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>" href="products.php">
                                <i class="fas fa-box me-2"></i>
                                Produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Commandes
                            </a>
                        </li>
                    </ul>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="javascript:history.back()">
                                <i class="fas fa-arrow-left me-2"></i>
                                Retour
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Retour au site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des Commandes</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total</h5>
                                <p class="card-text fs-2"><?php echo $total_orders; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">En attente</h5>
                                <p class="card-text fs-2"><?php echo count($pending_orders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">En traitement</h5>
                                <p class="card-text fs-2"><?php echo count($processing_orders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Expédiées</h5>
                                <p class="card-text fs-2"><?php echo count($shipped_orders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Livrées</h5>
                                <p class="card-text fs-2"><?php echo count($delivered_orders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Chiffre d'affaires</h5>
                                <p class="card-text fs-2"><?php echo number_format($total_revenue, 2); ?> €</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-filter me-1"></i>
                        Filtrer les commandes
                    </div>
                    <div class="card-body">
                        <form action="orders.php" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Statut de la commande</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Tous les statuts</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>En traitement</option>
                                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                <?php if ($status_filter): ?>
                                    <a href="orders.php" class="btn btn-outline-secondary ms-2">Réinitialiser</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-shopping-cart me-1"></i>
                        Commandes (<?php echo $total_orders; ?>)
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Email</th>
                                        <th>Montant</th>
                                        <th>Date</th>
                                        <th>Statut paiement</th>
                                        <th>Statut commande</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($orders) > 0): ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                                            <td><?php echo htmlspecialchars($order['email']); ?></td>
                                            <td><?php echo number_format($order['total_amount'], 2); ?> €</td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                $payment_status_class = '';
                                                switch ($order['payment_status']) {
                                                    case 'pending': $payment_status_class = 'bg-warning'; break;
                                                    case 'paid': $payment_status_class = 'bg-success'; break;
                                                    case 'failed': $payment_status_class = 'bg-danger'; break;
                                                    case 'completed': $payment_status_class = 'bg-info'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $payment_status_class; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $order_status_class = '';
                                                switch ($order['order_status']) {
                                                    case 'pending': $order_status_class = 'bg-warning'; break;
                                                    case 'processing': $order_status_class = 'bg-info'; break;
                                                    case 'shipped': $order_status_class = 'bg-primary'; break;
                                                    case 'delivered': $order_status_class = 'bg-success'; break;
                                                    case 'cancelled': $order_status_class = 'bg-danger'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $order_status_class; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($order['payment_status'] === 'paid' || $order['payment_status'] === 'completed'): ?>
                                                            <?php if ($order['order_status'] === 'pending'): ?>
                                                                <li>
                                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $order['id']; ?>">
                                                                        <i class="fas fa-cogs"></i> Mettre à jour le statut
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($order['order_status'] === 'processing'): ?>
                                                                <li>
                                                                    <form action="orders.php" method="POST">
                                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                        <input type="hidden" name="new_status" value="shipped">
                                                                        <button type="submit" name="update_status" class="dropdown-item">
                                                                            <i class="fas fa-shipping-fast"></i> Marquer comme expédiée
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($order['order_status'] === 'shipped'): ?>
                                                                <li>
                                                                    <form action="orders.php" method="POST">
                                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                        <input type="hidden" name="new_status" value="delivered">
                                                                        <button type="submit" name="update_status" class="dropdown-item">
                                                                            <i class="fas fa-check-circle"></i> Marquer comme livrée
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($order['order_status'] !== 'cancelled' && $order['order_status'] !== 'delivered'): ?>
                                                            <li>
                                                                <form action="orders.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette commande?');">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="cancelled">
                                                                    <button type="submit" name="update_status" class="dropdown-item text-danger">
                                                                        <i class="fas fa-times-circle"></i> Annuler la commande
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a href="mailto:<?php echo $order['email']; ?>" class="dropdown-item">
                                                                <i class="fas fa-envelope"></i> Contacter le client
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Modal de mise à jour du statut -->
                                                <div class="modal fade" id="updateStatusModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="updateStatusModalLabel<?php echo $order['id']; ?>">Mettre à jour le statut de la commande #<?php echo $order['id']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form id="updateStatusForm<?php echo $order['id']; ?>" action="orders.php" method="POST">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label for="new_status<?php echo $order['id']; ?>" class="form-label">Statut</label>
                                                                        <select class="form-select" id="new_status<?php echo $order['id']; ?>" name="new_status">
                                                                            <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                                                            <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'selected' : ''; ?>>En traitement</option>
                                                                            <option value="shipped" <?php echo $order['order_status'] == 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                                                                            <option value="delivered" <?php echo $order['order_status'] == 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                                                            <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                                                        </select>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <button type="submit" form="updateStatusForm<?php echo $order['id']; ?>" name="update_status" class="btn btn-primary">Mettre à jour</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucune commande trouvée</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Légende des statuts -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Légende des statuts
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Statut de paiement</h5>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        En attente
                                        <span class="badge bg-warning">pending</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Payé
                                        <span class="badge bg-success">paid</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Échoué
                                        <span class="badge bg-danger">failed</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Complété
                                        <span class="badge bg-info">completed</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5>Statut de commande</h5>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        En attente
                                        <span class="badge bg-warning">pending</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        En traitement
                                        <span class="badge bg-info">processing</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Expédiée
                                        <span class="badge bg-primary">shipped</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Livrée
                                        <span class="badge bg-success">delivered</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Annulée
                                        <span class="badge bg-danger">cancelled</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewOrderDetails(orderId) {
            // Rediriger vers la page de détails de la commande
            window.location.href = 'order_details.php?id=' + orderId;
        }
    </script>
</body>
</html>

