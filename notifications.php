<?php
session_start();
require_once("includes/db_connect.php");
require_once("includes/functions.php");

// Rediriger si non connecté
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationAsRead($conn, $_GET['mark_read']);
    header("Location: notifications.php");
    exit();
}

// Marquer toutes les notifications comme lues
if (isset($_GET['mark_all_read'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id";
    $conn->query($sql);
    header("Location: notifications.php");
    exit();
}

// Récupérer les notifications de l'utilisateur
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC";
$result = $conn->query($sql);

$notifications = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Omnes MarketPlace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include("includes/header.php"); ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Notifications</h1>
            <?php if (!empty($notifications)): ?>
                <a href="notifications.php?mark_all_read=1" class="btn btn-outline-primary">Marquer tout comme lu</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="alert alert-info">
                <p>Vous n'avez aucune notification.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div id="notification-<?php echo $notification['id']; ?>" class="notification <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                    <div class="d-flex justify-content-between">
                        <h5><?php echo $notification['is_read'] ? '' : '<span class="badge bg-primary">Nouveau</span> '; ?><?php echo $notification['message']; ?></h5>
                        <?php if (!$notification['is_read']): ?>
                            <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="text-muted mark-as-read" data-notification-id="<?php echo $notification['id']; ?>">Marquer comme lu</a>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted">
                        <small><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                    </p>
                    <?php if ($notification['link']): ?>
                        <a href="<?php echo $notification['link']; ?>" class="btn btn-sm btn-primary">Voir détails</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include("includes/footer.php"); ?>
    
    <script src="js/main.js"></script>
</body>
</html>

