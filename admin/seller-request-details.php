<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est un administrateur
requireAdmin();

// Vérifier si l'ID de la demande est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: seller-requests.php");
    exit();
}

$request_id = $_GET['id'];

// Récupérer les détails de la demande
$sql = "SELECT sr.*, u.username, u.email, u.first_name, u.last_name, u.phone, u.address, 
        u.city, u.postal_code, u.country, u.profile_image, u.created_at as user_created_at 
        FROM seller_requests sr 
        JOIN users u ON sr.user_id = u.id 
        WHERE sr.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: seller-requests.php");
    exit();
}

$request = $result->fetch_assoc();

// Récupérer les informations de l'administrateur qui a traité la demande
$admin_info = null;
if ($request['processed_by']) {
    $sql = "SELECT username, first_name, last_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request['processed_by']);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    if ($admin_result->num_rows > 0) {
        $admin_info = $admin_result->fetch_assoc();
    }
}

// Récupérer l'historique des commandes de l'utilisateur
$sql = "SELECT COUNT(*) as order_count, SUM(total) as total_spent 
        FROM orders 
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request['user_id']);
$stmt->execute();
$order_stats = $stmt->get_result()->fetch_assoc();

// Traitement des actions (approbation/refus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $comment = isset($_POST['comment']) ? $_POST['comment'] : '';
    
    if ($action === 'approve') {
        // Mettre à jour le rôle de l'utilisateur
        $sql = "UPDATE users SET role = 'seller' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request['user_id']);
        $stmt->execute();
        
        // Mettre à jour le statut de la demande
        $sql = "UPDATE seller_requests SET status = 'approved', admin_comment = ?, processed_at = NOW(), processed_by = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $comment, $_SESSION['user_id'], $request_id);
        $stmt->execute();
        
        // Envoyer une notification à l'utilisateur
        $notification = "Félicitations ! Votre demande pour devenir vendeur a été approuvée.";
        addNotification($conn, $request['user_id'], 'seller_request_approved', $notification);
        
        // Rediriger avec un message de succès
        header("Location: seller-request-details.php?id=$request_id&success=approved");
        exit();
    } elseif ($action === 'reject') {
        // Mettre à jour le statut de la demande
        $sql = "UPDATE seller_requests SET status = 'rejected', admin_comment = ?, processed_at = NOW(), processed_by = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $comment, $_SESSION['user_id'], $request_id);
        $stmt->execute();
        
        // Envoyer une notification à l'utilisateur
        $notification = "Votre demande pour devenir vendeur a été refusée. Raison : " . $comment;
        addNotification($conn, $request['user_id'], 'seller_request_rejected', $notification);
        
        // Rediriger avec un message de succès
        header("Location: seller-request-details.php?id=$request_id&success=rejected");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la demande vendeur - Administration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/admin_header.php"); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include("includes/admin_sidebar.php"); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Détails de la demande vendeur</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="seller-requests.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Retour à la liste
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php if ($_GET['success'] === 'approved'): ?>
                            <strong>Succès!</strong> La demande a été approuvée et l'utilisateur est maintenant un vendeur.
                        <?php elseif ($_GET['success'] === 'rejected'): ?>
                            <strong>Succès!</strong> La demande a été refusée.
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Informations sur la demande -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Informations sur la demande</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">ID de la demande:</div>
                                    <div class="col-md-8"><?php echo $request['id']; ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Date de soumission:</div>
                                    <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Statut:</div>
                                    <div class="col-md-8">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">En attente</span>
                                        <?php elseif ($request['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approuvée</span>
                                        <?php elseif ($request['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Refusée</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($request['status'] !== 'pending'): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-4 fw-bold">Traité le:</div>
                                        <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($request['processed_at'])); ?></div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4 fw-bold">Traité par:</div>
                                        <div class="col-md-8">
                                            <?php if ($admin_info): ?>
                                                <?php echo $admin_info['first_name'] . ' ' . $admin_info['last_name']; ?> (<?php echo $admin_info['username']; ?>)
                                            <?php else: ?>
                                                <em>Administrateur inconnu</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4 fw-bold">Commentaire:</div>
                                        <div class="col-md-8">
                                            <?php if (!empty($request['admin_comment'])): ?>
                                                <?php echo nl2br(htmlspecialchars($request['admin_comment'])); ?>
                                            <?php else: ?>
                                                <em>Aucun commentaire</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Motivation du vendeur</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3">Pourquoi souhaitez-vous devenir vendeur ?</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($request['motivation'])); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3">Quels types de produits souhaitez-vous vendre ?</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($request['product_types'])); ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <h6 class="fw-bold mb-3">Expérience professionnelle</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($request['experience'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($request['status'] === 'pending'): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                            <i class="fas fa-check me-2"></i> Approuver la demande
                                        </button>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                            <i class="fas fa-times me-2"></i> Refuser la demande
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modal d'approbation -->
                            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="seller-request-details.php?id=<?php echo $request_id; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="approveModalLabel">Approuver la demande</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Êtes-vous sûr de vouloir approuver la demande de <strong><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></strong> ?</p>
                                                <p>L'utilisateur deviendra un vendeur et pourra publier des produits sur la plateforme.</p>
                                                
                                                <div class="mb-3">
                                                    <label for="approveComment" class="form-label">Commentaire (optionnel)</label>
                                                    <textarea class="form-control" id="approveComment" name="comment" rows="3" placeholder="Ajouter un commentaire ou des instructions pour le vendeur..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                <button type="submit" class="btn btn-success">Approuver</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modal de refus -->
                            <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="seller-request-details.php?id=<?php echo $request_id; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="rejectModalLabel">Refuser la demande</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Êtes-vous sûr de vouloir refuser la demande de <strong><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></strong> ?</p>
                                                
                                                <div class="mb-3">
                                                    <label for="rejectComment" class="form-label">Motif du refus <span class="text-danger">*</span></label>
                                                    <textarea class="form-control" id="rejectComment" name="comment" rows="3" placeholder="Expliquez pourquoi la demande est refusée..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                <button type="submit" class="btn btn-danger">Refuser</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informations sur l'utilisateur -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Informations sur l'utilisateur</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <?php if (!empty($request['profile_image'])): ?>
                                        <img src="<?php echo $request['profile_image']; ?>" class="rounded-circle mb-3" width="100" height="100" alt="Profile">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2rem;">
                                            <?php echo strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="mb-0"><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></h5>
                                    <p class="text-muted"><?php echo $request['username']; ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="fw-bold mb-1">Email:</div>
                                    <div><?php echo $request['email']; ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="fw-bold mb-1">Téléphone:</div>
                                    <div><?php echo !empty($request['phone']) ? $request['phone'] : '<em>Non renseigné</em>'; ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="fw-bold mb-1">Adresse:</div>
                                    <div>
                                        <?php if (!empty($request['address'])): ?>
                                            <?php echo $request['address']; ?><br>
                                            <?php echo $request['postal_code'] . ' ' . $request['city']; ?><br>
                                            <?php echo $request['country']; ?>
                                        <?php else: ?>
                                            <em>Non renseignée</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="fw-bold mb-1">Membre depuis:</div>
                                    <div><?php echo date('d/m/Y', strtotime($request['user_created_at'])); ?></div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <div class="fw-bold mb-1">Commandes passées:</div>
                                    <div><?php echo $order_stats['order_count']; ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="fw-bold mb-1">Montant total dépensé:</div>
                                    <div><?php echo number_format($order_stats['total_spent'], 2, ',', ' '); ?> €</div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <a href="user-details.php?id=<?php echo $request['user_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-user me-2"></i> Voir le profil complet
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

