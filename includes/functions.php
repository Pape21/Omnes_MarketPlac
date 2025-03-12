<?php
// Fonction pour récupérer les produits en vedette
function getFeaturedProducts($conn, $limit = 3) {
    $sql = "SELECT * FROM products WHERE featured = 1 LIMIT $limit";
    $result = $conn->query($sql);

    $products = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    return $products;
}

// Fonction pour récupérer les nouveaux produits
function getNewProducts($conn, $limit = 4) {
    $sql = "SELECT * FROM products ORDER BY created_at DESC LIMIT $limit";
    $result = $conn->query($sql);

    $products = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    return $products;
}

// Fonction pour récupérer les produits les plus vendus
function getBestSellers($conn, $limit = 4) {
    $sql = "SELECT p.*, COUNT(o.id) as order_count 
            FROM products p 
            LEFT JOIN order_items o ON p.id = o.product_id 
            GROUP BY p.id 
            ORDER BY order_count DESC 
            LIMIT $limit";
    $result = $conn->query($sql);

    $products = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    return $products;
}

// Fonction pour récupérer les produits par catégorie
function getProductsByCategory($conn, $category_id, $limit = 8) {
    $sql = "SELECT * FROM products WHERE category_id = $category_id LIMIT $limit";
    $result = $conn->query($sql);

    $products = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    return $products;
}

// Fonction pour récupérer les produits par type de vente
function getProductsBySaleType($conn, $sale_type, $limit = 8) {
    $sql = "SELECT * FROM products WHERE sale_type = '$sale_type' LIMIT $limit";
    $result = $conn->query($sql);

    $products = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    return $products;
}

// Fonction pour obtenir l'enchère la plus élevée pour un produit
function getHighestBid($conn, $product_id) {
    $sql = "SELECT * FROM encheres WHERE product_id = $product_id ORDER BY amount DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
      return $result->fetch_assoc();
    }

    return null;
}

// Fonction pour récupérer un produit par son ID
function getProductById($conn, $id) {
    $sql = "SELECT p.*, c.name as category_name, u.username as seller_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.seller_id = u.id 
            WHERE p.id = $id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// Fonction pour récupérer les catégories
function getCategories($conn) {
    $sql = "SELECT * FROM categories ORDER BY parent_id IS NULL DESC, name";
    $result = $conn->query($sql);

    $categories = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}

// Fonction pour vérifier si un utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour vérifier si un utilisateur est un administrateur
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Fonction pour vérifier si un utilisateur est un vendeur
function isSeller() {
    return isset($_SESSION['role']) && ($_SESSION['role'] == 'seller' || $_SESSION['role'] == 'admin');
}

// Fonction pour ajouter un produit au panier
function addToCart($product_id, $quantity = 1) {
    $product_id = intval($product_id);
    $quantity = intval($quantity);
    
    if ($product_id <= 0 || $quantity <= 0) {
        return false;
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
    }

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    
    return true;
}

// Fonction pour supprimer un produit du panier
function removeFromCart($product_id) {
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

// Fonction pour obtenir le contenu du panier
function getCartItems($conn) {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return array();
    }

    $items = array();
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $product = getProductById($conn, $product_id);
        if ($product) {
            $product['quantity'] = $quantity;
            $items[] = $product;
        }
    }

    return $items;
}

// Fonction pour calculer le total du panier
function getCartTotal($conn) {
    $items = getCartItems($conn);
    $total = 0;

    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    return $total;
}

// Fonction pour enregistrer une enchère
function placeBid($conn, $product_id, $user_id, $amount) {
    // Utiliser des requêtes préparées pour éviter les injections SQL
    $stmt = $conn->prepare("INSERT INTO encheres (product_id, user_id, amount, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iid", $product_id, $user_id, $amount);
    return $stmt->execute();
}

// Fonction pour enregistrer une négociation
function placeNegotiation($conn, $product_id, $user_id, $amount, $message) {
    // Utiliser des requêtes préparées pour éviter les injections SQL
    $stmt = $conn->prepare("INSERT INTO negotiations (product_id, user_id, amount, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iids", $product_id, $user_id, $amount, $message);
    return $stmt->execute();
}

// Fonction pour obtenir les négociations pour un produit
function getNegotiations($conn, $product_id, $user_id = null) {
    $sql = "SELECT n.*, u.username 
            FROM negotiations n 
            JOIN users u ON n.user_id = u.id 
            WHERE n.product_id = ?";
    
    $params = [$product_id];
    $types = "i";

    if ($user_id) {
        $sql .= " AND (n.user_id = ? OR n.seller_response = 1)";
        $params[] = $user_id;
        $types .= "i";
    }

    $sql .= " ORDER BY n.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $negotiations = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $negotiations[] = $row;
        }
    }

    return $negotiations;
}

// Fonction pour répondre à une négociation
function respondToNegotiation($conn, $negotiation_id, $response, $counter_offer = null) {
    $sql = "UPDATE negotiations SET 
            seller_response = ?, 
            counter_offer = ?, 
            response_date = NOW() 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idi", $response, $counter_offer, $negotiation_id);
    return $stmt->execute();
}

// Fonction pour vérifier les informations de paiement
function validatePaymentCard($conn, $card_type, $card_number, $card_name, $expiry_date, $cvv) {
    // Utiliser des requêtes préparées pour éviter les injections SQL
    $stmt = $conn->prepare("SELECT * FROM payment_cards 
                           WHERE card_type = ? 
                           AND card_number = ? 
                           AND card_name = ? 
                           AND expiry_date = ? 
                           AND cvv = ?");
    
    $card_name = strtoupper($card_name); // Convertir en majuscules
    $card_number = preg_replace('/\s+/', '', $card_number); // Supprimer les espaces
    
    $stmt->bind_param("sssss", $card_type, $card_number, $card_name, $expiry_date, $cvv);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Si une correspondance est trouvée, la carte est valide
    return ($result && $result->num_rows > 0);
}

// Fonction pour créer une commande
function createOrder($conn, $user_id, $items, $total, $shipping_info, $payment_info) {
    // Vérifier les informations de paiement
    $payment_valid = validatePaymentCard(
        $conn, 
        $payment_info['method'], 
        $payment_info['card_number'], 
        $payment_info['card_name'], 
        $payment_info['card_expiry'], 
        $payment_info['card_cvv']
    );

    if (!$payment_valid) {
        return false;
    }

    // Insérer la commande avec le statut de paiement "completed" si la validation est réussie
    $payment_status = $payment_valid ? 'completed' : 'pending';

    // Utiliser des requêtes préparées pour éviter les injections SQL
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, shipping_city, shipping_postal_code, shipping_country, shipping_phone, payment_method, payment_status, order_status, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    
    $stmt->bind_param("idssssss", 
        $user_id, 
        $total, 
        $shipping_info['address'], 
        $shipping_info['city'], 
        $shipping_info['postal_code'], 
        $shipping_info['country'], 
        $shipping_info['phone'], 
        $payment_info['method'], 
        $payment_status
    );
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Insérer les articles de la commande
        foreach ($items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
            $stmt->execute();
            
            // Mettre à jour le stock du produit
            $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
        }
        
        return $order_id;
    }

    return false;
}

// Fonction pour obtenir les commandes d'un utilisateur
function getUserOrders($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    return $orders;
}

// Fonction pour obtenir les détails d'une commande
function getOrderDetails($conn, $order_id) {
    $stmt = $conn->prepare("SELECT o.*, oi.product_id, oi.quantity, oi.price, p.name as product_name, p.image 
                           FROM orders o 
                           JOIN order_items oi ON o.id = oi.order_id 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $order = array();
    $items = array();

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            if (empty($order)) {
                $order = array(
                    'id' => $row['id'],
                    'user_id' => $row['user_id'],
                    'total_amount' => $row['total_amount'],
                    'shipping_address' => $row['shipping_address'],
                    'shipping_city' => $row['shipping_city'],
                    'shipping_postal_code' => $row['shipping_postal_code'],
                    'shipping_country' => $row['shipping_country'],
                    'shipping_phone' => $row['shipping_phone'],
                    'payment_method' => $row['payment_method'],
                    'payment_status' => $row['payment_status'],
                    'order_status' => $row['order_status'],
                    'created_at' => $row['created_at']
                );
            }
            
            $items[] = array(
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'image' => $row['image'],
                'quantity' => $row['quantity'],
                'price' => $row['price']
            );
        }

        $order['items'] = $items;
        return $order;
    }

    return null;
}

// Fonction pour envoyer une notification
function sendNotification($conn, $user_id, $message, $link = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, created_at, is_read) 
                           VALUES (?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iss", $user_id, $message, $link);
    return $stmt->execute();
}

// Fonction pour obtenir les notifications d'un utilisateur
function getUserNotifications($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    return $notifications;
}

// Fonction pour marquer une notification comme lue
function markNotificationAsRead($conn, $notification_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    return $stmt->execute();
}

// Fonction pour obtenir le nombre de notifications non lues
function getUnreadNotificationsCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    return 0;
}

// Fonction pour rechercher des produits
function searchProducts($conn, $keyword) {
    $keyword = '%' . $keyword . '%';
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ?");
    $stmt->bind_param("ss", $keyword, $keyword);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    return $products;
}

// Fonction pour envoyer un email de confirmation de commande
function sendOrderConfirmationEmail($order_id, $user_email, $username, $total_amount) {
    // En production, utilisez PHPMailer ou une autre bibliothèque robuste
    $to = $user_email;
    $subject = "Confirmation de votre commande #$order_id - Omnes MarketPlace";
    
    $message = "
    <html>
    <head>
        <title>Confirmation de commande</title>
    </head>
    <body>
        <h2>Merci pour votre commande, $username!</h2>
        <p>Nous avons bien reçu votre commande #$order_id d'un montant total de $total_amount €.</p>
        <p>Vous pouvez suivre l'état de votre commande dans votre espace client.</p>
        <p>L'équipe Omnes MarketPlace vous remercie pour votre confiance!</p>
    </body>
    </html>
    ";
    
    // En-têtes pour envoyer un email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@omnesmarketplace.fr" . "\r\n";
    
    // Envoyer l'email
    // Note: En environnement de développement, cela peut ne pas fonctionner sans configuration SMTP
    $mail_sent = mail($to, $subject, $message, $headers);
    
    return $mail_sent;
}

// Fonction pour envoyer un SMS de confirmation de commande
function sendOrderConfirmationSMS($phone_number, $order_id, $total_amount) {
    // Cette fonction simule l'envoi d'un SMS
    // En production, utilisez un service comme Twilio, Nexmo, etc.
    
    $message = "Merci pour votre commande #$order_id sur Omnes MarketPlace! Montant: $total_amount €. Suivez votre commande sur notre site.";
    
    // Simuler l'envoi du SMS (toujours réussi pour la démonstration)
    $sms_sent = true;
    
    // Log l'envoi du SMS (pour la démonstration)
    error_log("SMS envoyé à $phone_number: $message");
    
    return $sms_sent;
}

// Fonction pour obtenir les enchères pour un produit
function getBids($conn, $product_id) {
    $stmt = $conn->prepare("SELECT e.*, u.username 
                           FROM encheres e 
                           JOIN users u ON e.user_id = u.id 
                           WHERE e.product_id = ? 
                           ORDER BY e.amount DESC");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $bids = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $bids[] = $row;
        }
    }

    return $bids;
}

// Fonction pour obtenir les autres enchérisseurs d'un produit
function getOtherBidders($conn, $product_id, $current_user_id) {
    $stmt = $conn->prepare("SELECT DISTINCT user_id FROM encheres WHERE product_id = ? AND user_id != ?");
    $stmt->bind_param("ii", $product_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $bidders = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $bidders[] = $row;
        }
    }

    return $bidders;
}

// Fonction pour ajouter une notification
function addNotification($conn, $user_id, $type, $message, $link = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link, created_at, is_read) 
                           VALUES (?, ?, ?, ?, NOW(), 0)");
    $stmt->bind_param("isss", $user_id, $type, $message, $link);
    return $stmt->execute();
}

// Fonction pour ajouter une notification admin
function addAdminNotification($conn, $type, $message, $link = null) {
    // Récupérer tous les administrateurs
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            if (addNotification($conn, $row['id'], $type, $message, $link)) {
                $count++;
            }
        }
    }
    
    return $count;
}

// Fonction pour notifier tous les vendeurs
function notifyAllSellers($conn, $type, $message, $link = null) {
    // Récupérer tous les vendeurs
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'seller'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            if (addNotification($conn, $row['id'], $type, $message, $link)) {
                $count++;
            }
        }
    }
    
    return $count;
}

// Fonction pour vérifier si l'utilisateur est un administrateur et rediriger si non
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: ../login.php");
        exit();
    }
}

// Fonction pour obtenir l'URL d'une image
function get_image_url($image_path) {
    if (empty($image_path)) {
        return '/omnes-marketplace/images/placeholder.jpg';
    }
    
    if (substr($image_path, 0, 1) === '/') {
        return '/omnes-marketplace' . $image_path;
    } else {
        return $image_path;
    }
}
?>

