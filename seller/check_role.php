<?php
session_start();
require_once '../includes/db_connect.php';

// Vérifier si l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Récupérer le rôle de l'utilisateur depuis la base de données
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['role'] = $user['role'];
        
        // Vérifier si l'utilisateur est un vendeur ou un administrateur
        if ($_SESSION['role'] !== 'seller' && $_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = "Vous n'avez pas les droits d'accès à l'espace vendeur.";
            header("Location: ../login.php");
            exit();
        }
    } else {
        // Utilisateur non trouvé dans la base de données
        session_destroy();
        $_SESSION['error'] = "Session invalide. Veuillez vous reconnecter.";
        header("Location: ../login.php");
        exit();
    }
} else {
    // Utilisateur non connecté
    $_SESSION['error'] = "Vous devez être connecté pour accéder à cette page.";
    header("Location: ../login.php");
    exit();
}
?>

