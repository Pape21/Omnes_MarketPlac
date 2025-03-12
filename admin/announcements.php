<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est un administrateur
requireAdmin();

// Traitement de la création/modification d'une annonce
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['create_announcement']) || isset($_POST['update_announcement'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $target = $_POST['target'];
        $status = $_POST['status'];
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        // Validation
        if (empty($title) || empty($content)) {
            $error_message = "Le titre et le contenu sont obligatoires.";
        } else {
            if (isset($_POST['update_announcement']) && !empty($_POST['announcement_id'])) {
                // Mise à jour d'une annonce existante
                $announcement_id = $_POST['announcement_id'];
                
                $sql = "UPDATE announcements 
                        SET title = ?, content = ?, target = ?, status = ?, 
                            start_date = ?, end_date = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $title, $content, $target, $status, $start_date, $end_date, $announcement_id);
                
                if ($stmt->execute()) {
                    $success_message = "L'annonce a été mise à jour avec succès.";
                    
                    // Si l'annonce est publiée, envoyer des notifications aux utilisateurs concernés
                    if ($status === 'published') {
                        if ($target === 'all') {
                            // Notifier tous les utilisateurs
                            $sql = "SELECT id FROM users";
                            $result = $conn->query($sql);
                            
                            while ($row = $result->fetch_assoc()) {
                                addNotification($conn, $row['id'], 'announcement', $title, "announcement.php?id=$announcement_id");
                            }
                        } elseif ($target === 'sellers') {
                            // Notifier tous les vendeurs
                            notifyAllSellers($conn, 'announcement', $title, "announcement.php?id=$announcement_id");
                        } elseif ($target === 'buyers') {
                            // Notifier tous les acheteurs
                            $sql = "SELECT id FROM users WHERE role = 'buyer'";
                            $result = $conn->query($sql);
                            
                            while ($row = $result->fetch_assoc()) {
                                addNotification($conn, $row['id'], 'announcement', $title, "announcement.php?id=$announcement_id");
                            }
                        }
                    }
                } else {
                    $error_message = "Erreur lors de la mise à jour de l'annonce: " . $stmt->error;
                }
            } else {
                // Création d'une nouvelle annonce
                $sql = "INSERT INTO announcements (title, content, target, status, start_date, end_date, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $created_by = $_SESSION['user_id'];
                $stmt->bind_param("ssssssi", $title, $content, $target, $status, $start_date, $end_date, $created_by);
                
                if ($stmt->execute()) {
                    $announcement_id = $stmt->insert_id;
                    $success_message = "L'annonce a été créée avec succès.";
                    
                    // Si l'annonce est publiée, envoyer des notifications aux utilisateurs concernés
                    if ($status === 'published') {
                        if ($target === 'all') {
                            // Notifier tous les utilisateurs
                            $sql = "SELECT id FROM users";
                            $result = $conn->query($sql);
                            
                            while ($row = $result->fetch_assoc()) {
                                addNotification($conn, $row['id'], 'announcement', $title, "announcement.php?id=$announcement_id");
                            }
                        } elseif ($target === 'sellers') {
                            // Notifier tous les vendeurs
                            notifyAllSellers($conn, 'announcement', $title, "announcement.php?id=$announcement_id");
                        } elseif ($target === 'buyers') {
                            // Notifier tous les acheteurs
                            $sql = "SELECT id FROM users WHERE role = 'buyer'";
                            $result = $conn->query($sql);
                            
                            while ($row = $result->fetch_assoc()) {
                                addNotification($conn, $row['id'], 'announcement', $title, "announcement.php?id=$announcement_id");
                            }
                        }
                    }
                } else {
                    $error_message = "Erreur lors de la création de l'annonce: " . $stmt->error;
                }
            }
        }
    } elseif (isset($_POST['delete_announcement']) && !empty($_POST['announcement_id'])) {
        // Suppression d'une annonce
        $announcement_id = $_POST['announcement_id'];
        
        $sql = "DELETE FROM announcements WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $announcement_id);
        
        if ($stmt->execute()) {
            $success_message = "L'annonce a été supprimée avec succès.";
        } else {
            $error_message = "Erreur lors de la suppression de l'annonce: " . $stmt->error;
        }
    }
}

// Récupérer l'annonce à modifier si l'ID est fourni
$announcement_to_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    $sql = "SELECT * FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $announcement_to_edit = $result->fetch_assoc();
    }
}

// Récupérer les annonces avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$target_filter = isset($_GET['target']) ? $_GET['target'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête SQL avec les filtres
$where_clause = "";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $where_clause .= "WHERE status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($target_filter !== 'all') {
    $where_clause .= (empty($where_clause) ? "WHERE " : " AND ") . "target = ?";
    $params[] = $target_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_clause .= (empty($where_clause) ? "WHERE " : " AND ") . "(title LIKE ? OR content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Requête pour compter le nombre total d'annonces
$count_sql = "SELECT COUNT(*) as total FROM announcements $where_clause";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_announcements = $total_row['total'];
$total_pages = ceil($total_announcements / $limit);

// Requête pour récupérer les annonces avec pagination
$sql = "SELECT a.*, u.username, u.first_name, u.last_name 
        FROM announcements a 
        JOIN users u ON a.created_by = u.id 
        $where_clause 
        ORDER BY a.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$announcements = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des annonces - Administration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
</head>
<body>
    <?php include("includes/admin_header.php"); ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include("includes/admin_sidebar.php"); ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des annonces</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                <i class="fas fa-plus"></i> Nouvelle annonce
                            </button>
                        </div>
                    </div>
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
                
                <!-- Filtres et recherche -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="announcements.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous</option>
                                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Publié</option>
                                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archivé</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="target" class="form-label">Cible</label>
                                <select class="form-select" id="target" name="target">
                                    <option value="all" <?php echo $target_filter === 'all' ? 'selected' : ''; ?>>Tous</option>
                                    <option value="all" <?php echo $target_filter === 'all' ? 'selected' : ''; ?>>Tous les utilisateurs</option>
                                    <option value="sellers" <?php echo $target_filter === 'sellers' ? 'selected' : ''; ?>>Vendeurs</option>
                                    <option value="buyers" <?php echo $target_filter === 'buyers' ? 'selected' : ''; ?>>Acheteurs</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Titre ou contenu..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des annonces -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Liste des annonces (<?php echo $total_announcements; ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Titre</th>
                                        <th scope="col">Cible</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col">Période</th>
                                        <th scope="col">Créée par</th>
                                        <th scope="col">Date</th>
                                        <th scope="col" width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($announcements)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">Aucune annonce trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                                    <small class="text-muted"><?php echo mb_substr(strip_tags($announcement['content']), 0, 50) . (mb_strlen(strip_tags($announcement['content'])) > 50 ? '...' : ''); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($announcement['target'] === 'all'): ?>
                                                        <span class="badge bg-primary rounded-pill">Tous</span>
                                                    <?php elseif ($announcement['target'] === 'sellers'): ?>
                                                        <span class="badge bg-success rounded-pill">Vendeurs</span>
                                                    <?php elseif ($announcement['target'] === 'buyers'): ?>
                                                        <span class="badge bg-info rounded-pill">Acheteurs</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($announcement['status'] === 'draft'): ?>
                                                        <span class="badge bg-secondary">Brouillon</span>
                                                    <?php elseif ($announcement['status'] === 'published'): ?>
                                                        <span class="badge bg-success">Publié</span>
                                                    <?php elseif ($announcement['status'] === 'archived'): ?>
                                                        <span class="badge bg-warning text-dark">Archivé</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($announcement['start_date']) && !empty($announcement['end_date'])): ?>
                                                        <?php echo date('d/m/Y', strtotime($announcement['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($announcement['end_date'])); ?>
                                                    <?php elseif (!empty($announcement['start_date'])): ?>
                                                        À partir du <?php echo date('d/m/Y', strtotime($announcement['start_date'])); ?>
                                                    <?php elseif (!empty($announcement['end_date'])): ?>
                                                        Jusqu'au <?php echo date('d/m/Y', strtotime($announcement['end_date'])); ?>
                                                    <?php else: ?>
                                                        <em>Non définie</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $announcement['first_name'] . ' ' . $announcement['last_name']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($announcement['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="announcement-preview.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="announcements.php?edit=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAnnouncementModal<?php echo $announcement['id']; ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Modal de suppression -->
                                                    <div class="modal fade" id="deleteAnnouncementModal<?php echo $announcement['id']; ?>" tabindex="-1" aria-labelledby="deleteAnnouncementModalLabel<?php echo $announcement['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="POST" action="announcements.php">
                                                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="deleteAnnouncementModalLabel<?php echo $announcement['id']; ?>">Supprimer l'annonce</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>Êtes-vous sûr de vouloir supprimer l'annonce <strong><?php echo htmlspecialchars($announcement['title']); ?></strong> ?</p>
                                                                        <p class="text-danger">Cette action est irréversible.</p>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                        <button type="submit" name="delete_announcement" class="btn btn-danger">Supprimer</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-white">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&target=<?php echo $target_filter; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&target=<?php echo $target_filter; ?>&search=<?php echo urlencode($search); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&target=<?php echo $target_filter; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal de création d'annonce -->
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="announcements.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createAnnouncementModalLabel">Nouvelle annonce</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label fw-medium">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Contenu <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="target" class="form-label">Cible <span class="text-danger">*</span></label>
                                <select class="form-select" id="target" name="target" required>
                                    <option value="all">Tous les utilisateurs</option>
                                    <option value="sellers">Vendeurs uniquement</option>
                                    <option value="buyers">Acheteurs uniquement</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="form-label">Statut <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="draft">Brouillon</option>
                                    <option value="published">Publier immédiatement</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Date de début (optionnel)</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Date de fin (optionnel)</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="create_announcement" class="btn btn-primary">Créer l'annonce</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de modification d'annonce -->
    <?php if ($announcement_to_edit): ?>
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="announcements.php">
                    <input type="hidden" name="announcement_id" value="<?php echo $announcement_to_edit['id']; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAnnouncementModalLabel">Modifier l'annonce</h5>
                        <a href="announcements.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label fw-medium">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_title" name="title" value="<?php echo htmlspecialchars($announcement_to_edit['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Contenu <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_content" name="content" rows="6" required><?php echo htmlspecialchars($announcement_to_edit['content']); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_target" class="form-label">Cible <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_target" name="target" required>
                                    <option value="all" <?php echo $announcement_to_edit['target'] === 'all' ? 'selected' : ''; ?>>Tous les utilisateurs</option>
                                    <option value="sellers" <?php echo $announcement_to_edit['target'] === 'sellers' ? 'selected' : ''; ?>>Vendeurs uniquement</option>
                                    <option value="buyers" <?php echo $announcement_to_edit['target'] === 'buyers' ? 'selected' : ''; ?>>Acheteurs uniquement</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label">Statut <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="draft" <?php echo $announcement_to_edit['status'] === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="published" <?php echo $announcement_to_edit['status'] === 'published' ? 'selected' : ''; ?>>Publié</option>
                                    <option value="archived" <?php echo $announcement_to_edit['status'] === 'archived' ? 'selected' : ''; ?>>Archivé</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_start_date" class="form-label">Date de début (optionnel)</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" value="<?php echo $announcement_to_edit['start_date']; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_end_date" class="form-label">Date de fin (optionnel)</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date" value="<?php echo $announcement_to_edit['end_date']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="announcements.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="update_announcement" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        $('#editAnnouncementModal').modal('show');
    });
    </script>
    <?php endif; ?>
    
    <script>
    $(document).ready(function() {
        // Initialiser l'éditeur de texte riche
        $('#content, #edit_content').summernote({
            height: 200,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
        
        // Auto-submit form when status changes
        $('#status, #target').change(function() {
            $(this).closest('form').submit();
        });
    });
    </script>
</body>
</html>

