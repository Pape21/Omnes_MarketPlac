<?php
session_start();
require_once("../includes/db_connect.php");
require_once("../includes/functions.php");

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
  echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour négocier']);
  exit();
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
  exit();
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['product_id']) || empty($_POST['product_id']) || !isset($_POST['amount']) || empty($_POST['amount'])) {
  echo json_encode(['success' => false, 'message' => 'Données manquantes']);
  exit();
}

$product_id = $_POST['product_id'];
$amount = floatval($_POST['amount']);
$message = isset($_POST['message']) ? $_POST['message'] : '';
$user_id = $_SESSION['user_id'];

// Vérifier si le produit existe et est de type négociation
$product = getProductById($conn, $product_id);
if (!$product) {
  echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
  exit();
}

if ($product['sale_type'] != 'negotiation') {
  echo json_encode(['success' => false, 'message' => 'Ce produit n\'est pas disponible à la négociation']);
  exit();
}

// Vérifier si le montant de l'offre est valide
if ($amount <= 0 || $amount > $product['price']) {
  echo json_encode(['success' => false, 'message' => 'Le montant de l\'offre doit être positif et inférieur au prix affiché']);
  exit();
}

// Échapper le message pour éviter les injections SQL
$message = $conn->real_escape_string($message);

// Placer la négociation
$sql = "INSERT INTO negotiations (product_id, user_id, amount, message, created_at) 
      VALUES ($product_id, $user_id, $amount, '$message', NOW())";

if ($conn->query($sql)) {
  $negotiation_id = $conn->insert_id;
  
  // Notifier le vendeur
  $seller_id = $product['seller_id'];
  $notification_message = 'Nouvelle offre de négociation de ' . $amount . ' € sur votre produit "' . $product['name'] . '"';
  $notification_link = 'seller/negotiations.php?product_id=' . $product_id;
  
  $sql = "INSERT INTO notifications (user_id, message, link, created_at, is_read) 
          VALUES ($seller_id, '$notification_message', '$notification_link', NOW(), 0)";
  $conn->query($sql);
  
  // Retourner une réponse de succès
  echo json_encode([
    'success' => true, 
    'message' => 'Offre de négociation envoyée avec succès', 
    'amount' => $amount,
    'username' => $_SESSION['username'],
    'message' => $message,
    'negotiation_id' => $negotiation_id
  ]);
} else {
  echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la négociation: ' . $conn->error]);
}
?>

