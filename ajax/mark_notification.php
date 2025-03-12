<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode(array('success' => false, 'message' => 'Vous n\'êtes pas autorisé à effectuer cette action.'));
    exit();
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['notification_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Données manquantes.'));
    exit();
}

$notification_id = intval($_POST['notification_id']);

// Vérifier si la notification existe et appartient à l'utilisateur
$sql = "SELECT * FROM notifications WHERE id = $notification_id AND user_id = {$_SESSION['user_id']}";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo json_encode(array('success' => false, 'message' => 'Notification introuvable ou non autorisée.'));
    exit();
}

// Marquer la notification comme lue
if (markNotificationAsRead($conn, $notification_id)) {
    echo json_encode(array('success' => true));
} else {
    echo json_encode(array('success' => false, 'message' => 'Une erreur est survenue. Veuillez réessayer.'));
}
?>

