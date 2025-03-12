<?php
// Inclure les fichiers nécessaires
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Vérifier si l'utilisateur est connecté et est un vendeur
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'seller' && $_SESSION['role'] !== 'admin')) {
    // Rediriger vers la page de connexion avec un message d'erreur
    $_SESSION['error'] = "Vous devez être connecté en tant que vendeur pour accéder à cette page.";
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID du vendeur
$seller_id = $_SESSION['user_id'];

// Vérifier si l'ID de commande est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de commande non spécifié.";
    header('Location: orders.php');
    exit;
}

$order_id = intval($_GET['id']);

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

if ($count == 0) {
    $_SESSION['error'] = "Vous n'êtes pas autorisé à voir cette commande ou la commande n'existe pas.";
    header('Location: orders.php');
    exit;
}

// Récupérer les détails de la commande
$order_query = "
    SELECT o.*, u.username, u.email, u.phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();
$stmt->close();

// Récupérer les articles de la commande qui appartiennent au vendeur
$items_query = "
    SELECT oi.*, p.name, p.image, p.seller_id
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? AND p.seller_id = ?
";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("ii", $order_id, $seller_id);
$stmt->execute();
$items_result = $stmt->get_result();
$stmt->close();

// Calculer le sous-total des articles du vendeur
$seller_subtotal = 0;
$order_items = [];
while ($item = $items_result->fetch_assoc()) {
    $seller_subtotal += $item['price'] * $item['quantity'];
    $order_items[] = $item;
}

// Traitement de l'ajout d'une note
if (isset($_POST['add_note']) && isset($_POST['note_content'])) {
    $note_content = $conn->real_escape_string($_POST['note_content']);
    $note_type = $conn->real_escape_string($_POST['note_type']);
    
    $insert_note_query = "
        INSERT INTO order_notes (order_id, user_id, note_content, note_type, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ";
    $stmt = $conn->prepare($insert_note_query);
    $stmt->bind_param("iiss", $order_id, $seller_id, $note_content, $note_type);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Note ajoutée avec succès.";
        
        // Si la note est visible pour le client, envoyer une notification
        if (isset($_POST['notify_customer']) && $_POST['notify_customer'] == 1) {
            $notification_message = "Le vendeur a ajouté une note à votre commande #" . $order_id;
            $notification_link = 'order_details.php?id=' . $order_id;
            sendNotification($conn, $order['user_id'], $notification_message, $notification_link);
        }
    } else {
        $_SESSION['error'] = "Erreur lors de l'ajout de la note.";
    }
    $stmt->close();
    
    // Rediriger pour éviter la resoumission du formulaire
    header('Location: order_details.php?id=' . $order_id);
    exit;
}

// Récupérer les notes de la commande
$notes_query = "
    SELECT n.*, u.username, u.role
    FROM order_notes n
    JOIN users u ON n.user_id = u.id
    WHERE n.order_id = ?
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($notes_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$notes_result = $stmt->get_result();
$notes = [];
while ($note = $notes_result->fetch_assoc()) {
    $notes[] = $note;
}
$stmt->close();

// Récupérer l'historique des statuts de la commande
$status_history_query = "
    SELECT sh.*, u.username
    FROM status_history sh
    LEFT JOIN users u ON sh.user_id = u.id
    WHERE sh.order_id = ?
    ORDER BY sh.created_at DESC
";
$stmt = $conn->prepare($status_history_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$status_history_result = $stmt->get_result();
$status_history = [];
while ($status = $status_history_result->fetch_assoc()) {
    $status_history[] = $status;
}
$stmt->close();

// Inclure l'en-tête
$page_title = "Détails de la commande #" . $order_id;
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">Commandes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Commande #<?php echo $order_id; ?></li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Détails de la commande #<?php echo $order_id; ?></h1>
                <div class="btn-group">
                    <a href="generate_invoice.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-file-invoice me-1"></i> Générer facture
                    </a>
                    <a href="generate_shipping_label.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary" target="_blank">
                        <i class="fas fa-shipping-fast me-1"></i> Bon de livraison
                    </a>
                </div>
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
        </div>
    </div>
    
    <div class="row">
        <!-- Informations de la commande -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Informations de la commande
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Numéro de commande:</th>
                            <td>#<?php echo $order_id; ?></td>
                        </tr>
                        <tr>
                            <th>Date de commande:</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>Statut de paiement:</th>
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
                        </tr>
                        <tr>
                            <th>Statut de la commande:</th>
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
                        </tr>
                        <tr>
                            <th>Méthode de paiement:</th>
                            <td><?php echo ucfirst($order['payment_method']); ?></td>
                        </tr>
                    </table>
                    
                    <?php if ($order['order_status'] !== 'cancelled' && $order['order_status'] !== 'delivered'): ?>
                        <div class="mt-3">
                            <h5>Mettre à jour le statut</h5>
                            <form action="orders.php" method="POST" class="row g-3">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <div class="col-md-8">
                                    <select name="new_status" class="form-select">
                                        <?php if ($order['order_status'] === 'pending'): ?>
                                            <option value="processing">En traitement</option>
                                        <?php elseif ($order['order_status'] === 'processing'): ?>
                                            <option value="shipped">Expédiée</option>
                                        <?php elseif ($order['order_status'] === 'shipped'): ?>
                                            <option value="delivered">Livrée</option>
                                        <?php endif; ?>
                                        <option value="cancelled">Annulée</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="update_status" class="btn btn-primary">Mettre à jour</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Informations du client -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    Informations du client
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Nom d'utilisateur:</th>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($order['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Téléphone:</th>
                            <td><?php echo htmlspecialchars($order['phone'] ?? 'Non spécifié'); ?></td>
                        </tr>
                    </table>
                    
                    <h5 class="mt-3">Adresse de livraison</h5>
                    <address>
                        <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_city']); ?>, <?php echo htmlspecialchars($order['shipping_postal_code']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_country']); ?><br>
                        Téléphone: <?php echo htmlspecialchars($order['shipping_phone']); ?>
                    </address>
                    
                    <div class="mt-3">
                        <a href="mailto:<?php echo $order['email']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-1"></i> Contacter le client
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Articles de la commande -->
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-box me-1"></i>
                    Articles de la commande (vos produits uniquement)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Prix unitaire</th>
                                    <th>Quantité</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo get_image_url($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="img-thumbnail me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <div>
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo $item['product_id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($item['price'], 2); ?> €</td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?> €</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Sous-total (vos produits):</th>
                                    <th><?php echo number_format($seller_subtotal, 2); ?> €</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Note: Ce tableau n'affiche que les produits de cette commande qui vous appartiennent. Le montant total de la commande peut être différent si d'autres vendeurs sont impliqués.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historique des statuts -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-history me-1"></i>
                    Historique des statuts
                </div>
                <div class="card-body">
                    <?php if (empty($status_history)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun historique de statut disponible.
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($status_history as $status): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <?php 
                                        $status_icon = '';
                                        switch ($status['status']) {
                                            case 'pending': $status_icon = 'clock'; break;
                                            case 'processing': $status_icon = 'cogs'; break;
                                            case 'shipped': $status_icon = 'shipping-fast'; break;
                                            case 'delivered': $status_icon = 'check-circle'; break;
                                            case 'cancelled': $status_icon = 'times-circle'; break;
                                            default: $status_icon = 'circle'; break;
                                        }
                                        ?>
                                        <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-0"><?php echo ucfirst($status['status']); ?></h6>
                                        <p class="text-muted mb-0">
                                            <?php echo date('d/m/Y H:i', strtotime($status['created_at'])); ?>
                                            <?php if ($status['username']): ?>
                                                par <?php echo htmlspecialchars($status['username']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($status['comment']): ?>
                                            <p class="mt-1"><?php echo htmlspecialchars($status['comment']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Notes de commande -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-sticky-note me-1"></i>
                    Notes de commande
                </div>
                <div class="card-body">
                    <form action="order_details.php?id=<?php echo $order_id; ?>" method="POST" class="mb-4">
                        <div class="mb-3">
                            <label for="note_content" class="form-label">Ajouter une note</label>
                            <textarea name="note_content" id="note_content" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="note_type" class="form-label">Type de note</label>
                            <select name="note_type" id="note_type" class="form-select">
                                <option value="internal">Note interne (visible uniquement par les vendeurs)</option>
                                <option value="customer">Note client (visible par le client)</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="notify_customer" id="notify_customer" value="1" class="form-check-input">
                            <label for="notify_customer" class="form-check-label">Notifier le client</label>
                        </div>
                        <button type="submit" name="add_note" class="btn btn-primary">Ajouter la note</button>
                    </form>
                    
                    <hr>
                    
                    <?php if (empty($notes)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucune note disponible pour cette commande.
                        </div>
                    <?php else: ?>
                        <h5>Notes existantes</h5>
                        <?php foreach ($notes as $note): ?>
                            <div class="card mb-2 <?php echo $note['note_type'] === 'internal' ? 'bg-light' : 'bg-info-subtle'; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="badge <?php echo $note['note_type'] === 'internal' ? 'bg-secondary' : 'bg-info'; ?>">
                                                <?php echo $note['note_type'] === 'internal' ? 'Note interne' : 'Note client'; ?>
                                            </span>
                                            <small class="text-muted ms-2">
                                                Par <?php echo htmlspecialchars($note['username']); ?> 
                                                (<?php echo ucfirst($note['role']); ?>)
                                            </small>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note_content'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Numéros de suivi -->
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-truck me-1"></i>
                    Informations d'expédition
                </div>
                <div class="card-body">
                    <?php if ($order['order_status'] === 'processing' || $order['order_status'] === 'shipped' || $order['order_status'] === 'delivered'): ?>
                        <form action="update_tracking.php" method="POST" class="row g-3">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            
                            <div class="col-md-4">
                                <label for="shipping_carrier" class="form-label">Transporteur</label>
                                <select name="shipping_carrier" id="shipping_carrier" class="form-select">
                                    <option value="">Sélectionner un transporteur</option>
                                    <option value="chronopost" <?php echo isset($order['shipping_carrier']) && $order['shipping_carrier'] === 'chronopost' ? 'selected' : ''; ?>>Chronopost</option>
                                    <option value="colissimo" <?php echo isset($order['shipping_carrier']) && $order['shipping_carrier'] === 'colissimo' ? 'selected' : ''; ?>>Colissimo</option>
                                    <option value="dhl" <?php echo isset($order['shipping_carrier']) && $order['shipping_carrier'] === 'dhl' ? 'selected' : ''; ?>>DHL</option>
                                    <option value="ups" <?php echo isset($order['shipping_carrier']) && $order['shipping_carrier'] === 'ups' ? 'selected' : ''; ?>>UPS</option>
                                    <option value="fedex" <?php echo isset($order['shipping_carrier']) && $order['shipping_carrier'] === 'fedex' ? 'selected' : ''; ?>>FedEx</option>
                                    <option value="other" <?php echo isset($order['shipping_carrier']) && $order['shipping_carrier'] === 'other' ? 'selected' : ''; ?>>Autre</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="tracking_number" class="form-label">Numéro de suivi</label>
                                <input type="text" name="tracking_number" id="tracking_number" class="form-control" value="<?php echo isset($order['tracking_number']) ? htmlspecialchars($order['tracking_number']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="shipping_date" class="form-label">Date d'expédition</label>
                                <input type="date" name="shipping_date" id="shipping_date" class="form-control" value="<?php echo isset($order['shipping_date']) ? date('Y-m-d', strtotime($order['shipping_date'])) : date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <label for="shipping_notes" class="form-label">Notes d'expédition</label>
                                <textarea name="shipping_notes" id="shipping_notes" class="form-control" rows="2"><?php echo isset($order['shipping_notes']) ? htmlspecialchars($order['shipping_notes']) : ''; ?></textarea>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" name="notify_customer_tracking" id="notify_customer_tracking" value="1" class="form-check-input" checked>
                                    <label for="notify_customer_tracking" class="form-check-label">Notifier le client des informations de suivi</label>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" name="update_tracking" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Enregistrer les informations d'expédition
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Les informations d'expédition seront disponibles une fois que la commande sera en cours de traitement.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Retour aux commandes
            </a>
            
            <?php if ($order['order_status'] === 'processing'): ?>
                <form action="orders.php" method="POST" class="d-inline">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="new_status" value="shipped">
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-shipping-fast me-1"></i> Marquer comme expédiée
                    </button>
                </form>
            <?php elseif ($order['order_status'] === 'shipped'): ?>
                <form action="orders.php" method="POST" class="d-inline">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="new_status" value="delivered">
                    <button type="submit" name="update_status" class="btn btn-success">
                        <i class="fas fa-check-circle me-1"></i> Marquer comme livrée
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if ($order['order_status'] !== 'cancelled' && $order['order_status'] !== 'delivered'): ?>
                <form action="orders.php" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette commande?');">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="new_status" value="cancelled">
                    <button type="submit" name="update_status" class="btn btn-danger">
                        <i class="fas fa-times-circle me-1"></i> Annuler la commande
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Style pour la timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 50%;
    border: 2px solid #dee2e6;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: -20px;
    height: 100%;
    width: 2px;
    background-color: #dee2e6;
}
</style>

<?php
// Inclure le pied de page
include_once '../includes/footer.php';
?>

