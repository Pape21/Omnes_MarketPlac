<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté et est un vendeur
if (!isLoggedIn() || !isSeller()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['negotiation_id']) || empty($_POST['negotiation_id']) || !isset($_POST['response']) || $_POST['response'] === '') {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$negotiation_id = $_POST['negotiation_id'];
$response = intval($_POST['response']);
$counter_offer = isset($_POST['counter_offer']) && !empty($_POST['counter_offer']) ? floatval($_POST['counter_offer']) : null;

// Récupérer les informations sur la négociation
$sql = "SELECT n.*, p.name as product_name, p.seller_id, p.price 
        FROM negotiations n 
        JOIN products p ON n.product_id = p.id 
        WHERE n.id = $negotiation_id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Négociation introuvable']);
    exit();
}

$negotiation = $result->fetch_assoc();

// Vérifier si l'utilisateur est le vendeur du produit
if ($negotiation['seller_id'] != $_SESSION['user_id'] && !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas autorisé à répondre à cette négociation']);
    exit();
}

// Vérifier si la contre-offre est valide
if ($counter_offer !== null) {
    if ($counter_offer <= 0 || $counter_offer >= $negotiation['price']) {
        echo json_encode(['success' => false, 'message' => 'Le montant de la contre-offre doit être positif et inférieur au prix affiché']);
        exit();
    }
}

// Répondre à la négociation
if (respondToNegotiation($conn, $negotiation_id, $response, $counter_offer)) {
    // Notifier l'acheteur
    $message = $response == 1 
        ? 'Votre offre de ' . $negotiation['amount'] . ' € pour le produit "' . $negotiation['product_name'] . '" a été acceptée' 
        : 'Votre offre de ' . $negotiation['amount'] . ' € pour le produit "' . $negotiation['product_name'] . '" a été refusée';
    
    if ($counter_offer !== null) {
        $message .= '. Contre-offre: ' . $counter_offer . ' €';
    }
    
    sendNotification($conn, $negotiation['user_id'], $message, 'product.php?id=' . $negotiation['product_id']);
    
    // Retourner une réponse de succès
    echo json_encode([
        'success' => true, 
        'message' => 'Réponse envoyée avec succès', 
        'response' => $response,
        'counter_offer' => $counter_offer
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la réponse']);
}
?>

