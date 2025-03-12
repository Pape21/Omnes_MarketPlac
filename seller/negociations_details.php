<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un vendeur
if (!isLoggedIn() || !isSeller()) {
    header("Location: ../login.php");
    exit();
}

// Récupérer l'ID du vendeur
$seller_id = $_SESSION['user_id'];

// Vérifier si l'ID de négociation est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de négociation non spécifié.";
    header("Location: negotiations.php");
    exit();
}

$negotiation_id = intval($_GET['id']);

// Récupérer les détails de la négociation
$query = "SELECT n.*, p.name as product_name, p.price as product_price, p.image as product_image, 
          p.description as product_description, p.seller_id, 
          u.username as buyer_name, u.email as buyer_email, u.phone as buyer_phone 
          FROM negotiations n 
          JOIN products p ON n.product_id = p.id 
          JOIN users u ON n.user_id = u.id 
          WHERE n.id = ? AND p.seller_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $negotiation_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Négociation non trouvée ou non autorisée.";
    header("Location: negotiations.php");
    exit();
}

$negotiation = $result->fetch_assoc();

// Récupérer les messages de la négociation
$messages_query = "SELECT m.*, u.username, u.role 
                  FROM negotiation_messages m 
                  JOIN users u ON m.user_id = u.id 
                  WHERE m.negotiation_id = ? 
                  ORDER BY m.created_at ASC";

$stmt = $conn->prepare($messages_query);
$stmt->bind_param("i", $negotiation_id);
$stmt->execute();
$messages_result = $stmt->get_result();

$messages = [];
while ($message = $messages_result->fetch_assoc()) {
    $messages[] = $message;
}

// Traitement des actions (accepter/refuser une offre)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'accept') {
            // Accepter l'offre
            $response = 1;
            $counter_offer = null;
            $success_message = "Offre acceptée avec succès.";
            
            // Notifier l'acheteur
            $notification_message = "Votre offre de " . number_format($negotiation['amount'], 2) . " € pour le produit \"" . $negotiation['product_name'] . "\" a été acceptée par le vendeur.";
            $notification_link = "product.php?id=" . $negotiation['product_id'] . "&accepted_offer=" . $negotiation_id;
            sendNotification($conn, $negotiation['user_id'], $notification_message, $notification_link);
            
        } elseif ($action === 'reject') {
            // Refuser l'offre
            $response = 0;
            $counter_offer = null;
            $success_message = "Offre refusée.";
            
            // Notifier l'acheteur
            $notification_message = "Votre offre de " . number_format($negotiation['amount'], 2) . " € pour le produit \"" . $negotiation['product_name'] . "\" a été refusée par le vendeur.";
            $notification_link = "product.php?id=" . $negotiation['product_id'];
            sendNotification($conn, $negotiation['user_id'], $notification_message, $notification_link);
            
        } elseif ($action === 'counter') {
            // Faire une contre-offre
            if (isset($_POST['counter_amount']) && is_numeric($_POST['counter_amount'])) {
                $counter_offer = floatval($_POST['counter_amount']);
                $response = 2; // Code pour contre-offre
                $success_message = "Contre-offre envoyée avec succès.";
                
                // Notifier l'acheteur
                $notification_message = "Le vendeur a fait une contre-offre de " . number_format($counter_offer, 2) . " € pour le produit \"" . $negotiation['product_name'] . "\".";
                $notification_link = "product.php?id=" . $negotiation['product_id'] . "&negotiation=" . $negotiation_id;
                sendNotification($conn, $negotiation['user_id'], $notification_message, $notification_link);
            } else {
                $error_message = "Montant de la contre-offre invalide.";
            }
        }
        
        // Si pas d'erreur, mettre à jour la négociation
        if (!isset($error_message)) {
            $update_query = "UPDATE negotiations SET seller_response = ?, counter_offer = ?, response_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("idi", $response, $counter_offer, $negotiation_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = $success_message;
                
                // Ajouter un message système
                $system_message = "";
                if ($action === 'accept') {
                    $system_message = "Le vendeur a accepté l'offre de " . number_format($negotiation['amount'], 2) . " €.";
                } elseif ($action === 'reject') {
                    $system_message = "Le vendeur a refusé l'offre de " . number_format($negotiation['amount'], 2) . " €.";
                } elseif ($action === 'counter') {
                    $system_message = "Le vendeur a fait une contre-offre de " . number_format($counter_offer, 2) . " €.";
                }
                
                if (!empty($system_message)) {
                    $insert_message_query = "INSERT INTO negotiation_messages (negotiation_id, user_id, message, is_system, created_at) 
                                           VALUES (?, ?, ?, 1, NOW())";
                    $stmt = $conn->prepare($insert_message_query);
                    $stmt->bind_param("iis", $negotiation_id, $seller_id, $system_message);
                    $stmt->execute();
                }
                
                // Rafraîchir les données de la négociation
                header("Location: negotiation_details.php?id=" . $negotiation_id);
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de la mise à jour de la négociation.";
            }
        } else {
            $_SESSION['error'] = $error_message;
        }
    }
    
    // Traitement de l'envoi d'un message
    if (isset($_POST['send_message']) && isset($_POST['message'])) {
        $message = trim($_POST['message']);
        
        if (!empty($message)) {
            // Insérer le message
            $insert_query = "INSERT INTO negotiation_messages (negotiation_id, user_id, message, created_at) 
                            VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iis", $negotiation_id, $seller_id, $message);
            
            if ($stmt->execute()) {
                // Notifier l'acheteur
                $notification_message = "Vous avez reçu un nouveau message concernant votre offre pour \"" . $negotiation['product_name'] . "\".";
                $notification_link = "product.php?id=" . $negotiation['product_id'] . "&negotiation=" . $negotiation_id;
                sendNotification($conn, $negotiation['user_id'], $notification_message, $notification_link);
                
                $_SESSION['success'] = "Message envoyé avec succès.";
                
                // Rafraîchir la page pour afficher le nouveau message
                header("Location: negotiation_details.php?id=" . $negotiation_id);
                exit();
            } else {
                $_SESSION['error'] = "Erreur lors de l'envoi du message.";
            }
        } else {
            $_SESSION['error'] = "Le message ne peut pas être vide.";
        }
    }
}

// Titre de la page
$page_title = "Détails de la négociation #" . $negotiation_id;
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
    <style>
        .chat-container {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
        }
        .message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        .message-buyer {
            margin-right: auto;
        }
        .message-seller {
            margin-left: auto;
        }
        .message-system {
            margin-left: auto;
            margin-right: auto;
            max-width: 90%;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
        }
        .message-buyer .message-content {
            background-color: #e9ecef;
            border-bottom-left-radius: 0;
        }
        .message-seller .message-content {
            background-color: #007bff;
            color: white;
            border-bottom-right-radius: 0;
        }
        .message-system .message-content {
            background-color: #ffc107;
            color: #212529;
            text-align: center;
            border-radius: 10px;
        }
        .message-info {
            font-size: 0.75rem;
            margin-top: 5px;
            color: #6c757d;
        }
        .message-buyer .message-info {
            text-align: left;
        }
        .message-seller .message-info {
            text-align: right;
        }
        .message-system .message-info {
            text-align: center;
        }
        .price-comparison {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .price-arrow {
            color: #6c757d;
            font-size: 1.2rem;
        }
        .price-original {
            text-decoration: line-through;
            color: #6c757d;
        }
        .price-offer {
            font-weight: bold;
            color: #28a745;
        }
        .discount-percentage {
            background-color: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="d-flex align-items-center px-3 mb-3 text-white">
                        <i class="fas fa-store me-2"></i>
                        <span class="fs-5">Espace Vendeur</span>
                    </div>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="products.php">
                                <i class="fas fa-box me-2"></i>
                                Produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="negotiations.php">
                                <i class="fas fa-comments-dollar me-2"></i>
                                Négociations
                            </a>
                        </li>
                    </ul>
                    <hr class="text-white">
                    <ul class="nav flex-column">
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
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Détails de la négociation #<?php echo $negotiation_id; ?></h1>
                    <a href="negotiations.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Retour aux négociations
                    </a>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Informations sur le produit et la négociation -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informations sur le produit</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <img src="<?php echo get_image_url($negotiation['product_image']); ?>" alt="<?php echo htmlspecialchars($negotiation['product_name']); ?>" class="img-fluid rounded" style="max-height: 200px;">
                                </div>
                                <h5><?php echo htmlspecialchars($negotiation['product_name']); ?></h5>
                                <p class="text-muted"><?php echo substr(htmlspecialchars($negotiation['product_description']), 0, 150) . (strlen($negotiation['product_description']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="price-comparison">
                                    <span class="price-original"><?php echo number_format($negotiation['product_price'], 2); ?> €</span>
                                    <span class="price-arrow"><i class="fas fa-long-arrow-alt-right"></i></span>
                                    <span class="price-offer"><?php echo number_format($negotiation['amount'], 2); ?> €</span>
                                    <?php 
                                    $discount_percentage = round(($negotiation['product_price'] - $negotiation['amount']) / $negotiation['product_price'] * 100);
                                    if ($discount_percentage > 0):
                                    ?>
                                    <span class="discount-percentage">-<?php echo $discount_percentage; ?>%</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <a href="../product.php?id=<?php echo $negotiation['product_id']; ?>" class="btn btn-outline-primary" target="_blank">
                                        <i class="fas fa-external-link-alt me-1"></i> Voir le produit
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informations sur l'acheteur</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Nom:</strong> <?php echo htmlspecialchars($negotiation['buyer_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($negotiation['buyer_email']); ?></p>
                                <?php if (!empty($negotiation['buyer_phone'])): ?>
                                    <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($negotiation['buyer_phone']); ?></p>
                                <?php endif; ?>
                                <p><strong>Date de l'offre:</strong> <?php echo date('d/m/Y H:i', strtotime($negotiation['created_at'])); ?></p>
                                
                                <?php if (!empty($negotiation['message'])): ?>
                                    <div class="alert alert-light mt-3">
                                        <strong>Message initial:</strong><br>
                                        <?php echo htmlspecialchars($negotiation['message']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($negotiation['seller_response'] === null): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#acceptModal">
                                            <i class="fas fa-check me-1"></i> Accepter l'offre
                                        </button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#counterModal">
                                            <i class="fas fa-exchange-alt me-1"></i> Faire une contre-offre
                                        </button>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                            <i class="fas fa-times me-1"></i> Refuser l'offre
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Statut de la négociation</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($negotiation['seller_response'] == 1): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i> Offre acceptée le <?php echo date('d/m/Y H:i', strtotime($negotiation['response_date'])); ?>
                                        </div>
                                    <?php elseif ($negotiation['seller_response'] == 0): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-times-circle me-2"></i> Offre refusée le <?php echo date('d/m/Y H:i', strtotime($negotiation['response_date'])); ?>
                                        </div>
                                    <?php elseif ($negotiation['seller_response'] == 2): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-exchange-alt me-2"></i> Contre-offre de <?php echo number_format($negotiation['counter_offer'], 2); ?> € envoyée le <?php echo date('d/m/Y H:i', strtotime($negotiation['response_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Chat de négociation -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Conversation</h5>
                            </div>
                            <div class="card-body">
                                <div class="chat-container" id="chatContainer">
                                    <?php if (empty($messages)): ?>
                                        <div class="text-center text-muted">
                                            <p>Aucun message dans cette conversation.</p>
                                            <p>Utilisez le formulaire ci-dessous pour envoyer un message à l'acheteur.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): ?>
                                            <?php if ($msg['is_system'] == 1): ?>
                                                <div class="message message-system">
                                                    <div class="message-content">
                                                        <?php echo htmlspecialchars($msg['message']); ?>
                                                    </div>
                                                    <div class="message-info">
                                                        <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php elseif ($msg['user_id'] == $seller_id): ?>
                                                <div class="message message-seller">
                                                    <div class="message-content">
                                                        <?php echo htmlspecialchars($msg['message']); ?>
                                                    </div>
                                                    <div class="message-info">
                                                        Vous, <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="message message-buyer">
                                                    <div class="message-content">
                                                        <?php echo htmlspecialchars($msg['message']); ?>
                                                    </div>
                                                    <div class="message-info">
                                                        <?php echo htmlspecialchars($msg['username']); ?>, <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <form action="negotiation_details.php?id=<?php echo $negotiation_id; ?>" method="POST" class="mt-3">
                                    <div class="input-group">
                                        <input type="text" name="message" class="form-control" placeholder="Tapez votre message..." required>
                                        <input type="hidden" name="send_message" value="1">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i> Envoyer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modals pour les actions -->
                <!-- Modal pour accepter l'offre -->
                <div class="modal fade" id="acceptModal" tabindex="-1" aria-labelledby="acceptModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="acceptModalLabel">Accepter l'offre</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Êtes-vous sûr de vouloir accepter l'offre de <strong><?php echo number_format($negotiation['amount'], 2); ?> €</strong> pour le produit <strong><?php echo htmlspecialchars($negotiation['product_name']); ?></strong> ?</p>
                                <p>Le client sera notifié et pourra procéder à l'achat au prix négocié.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <form action="negotiation_details.php?id=<?php echo $negotiation_id; ?>" method="POST">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="btn btn-success">Accepter l'offre</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal pour refuser l'offre -->
                <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="rejectModalLabel">Refuser l'offre</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Êtes-vous sûr de vouloir refuser l'offre de <strong><?php echo number_format($negotiation['amount'], 2); ?> €</strong> pour le produit <strong><?php echo htmlspecialchars($negotiation['product_name']); ?></strong> ?</p>
                                <p>Le client sera notifié que son offre a été refusée.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <form action="negotiation_details.php?id=<?php echo $negotiation_id; ?>" method="POST">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger">Refuser l'offre</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal pour faire une contre-offre -->
                <div class="modal fade" id="counterModal" tabindex="-1" aria-labelledby="counterModalLabel" aria-hidden="true">
                    <div class="modal  aria-labelledby="counterModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="counterModalLabel">Faire une contre-offre</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="negotiation_details.php?id=<?php echo $negotiation_id; ?>" method="POST">
                                <div class="modal-body">
                                    <p>Produit: <strong><?php echo htmlspecialchars($negotiation['product_name']); ?></strong></p>
                                    <p>Prix original: <strong><?php echo number_format($negotiation['product_price'], 2); ?> €</strong></p>
                                    <p>Offre du client: <strong><?php echo number_format($negotiation['amount'], 2); ?> €</strong></p>
                                    
                                    <div class="mb-3">
                                        <label for="counter_amount" class="form-label">Votre contre-offre (€)</label>
                                        <input type="number" class="form-control" id="counter_amount" name="counter_amount" min="0.01" step="0.01" value="<?php echo number_format(($negotiation['product_price'] + $negotiation['amount']) / 2, 2, '.', ''); ?>" required>
                                        <div class="form-text">Proposez un montant entre l'offre du client et votre prix original.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <input type="hidden" name="action" value="counter">
                                    <button type="submit" class="btn btn-primary">Envoyer la contre-offre</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Faire défiler automatiquement la conversation vers le bas
        document.addEventListener('DOMContentLoaded', function() {
            var chatContainer = document.getElementById('chatContainer');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>

