<?php
// classes/database.php - Centralized Database Connection for HAPPY-SPRAYS

class Database {
    private static $instance = null;
    private $connection;
    
    // Database configuration
    private $host = 'localhost';
    private $database = 'happy_sprays';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    // Private constructor to prevent direct instantiation
    private function __construct() {
        $this->connect();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    // Get the singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Create the database connection
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    // Get the PDO connection
    public function getConnection() {
        return $this->connection;
    }

    // =================================================================
    // GENERIC QUERY HELPERS
    // =================================================================
    
    public function select($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Select query failed: " . $e->getMessage());
        }
    }
    
    public function fetch($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Fetch query failed: " . $e->getMessage());
        }
    }
    
    public function insert($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Insert query failed: " . $e->getMessage());
        }
    }
    
    public function update($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Update query failed: " . $e->getMessage());
        }
    }
    
    public function delete($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Delete query failed: " . $e->getMessage());
        }
    }
    public function getAllCustomers() {
        return $this->select("SELECT customer_id, customer_firstname, customer_lastname, customer_username, customer_email, is_verified, cs_created_at 
                          FROM customers 
                          ORDER BY cs_created_at DESC");
    }
    // Helper method to delete a record safely by ID
    public function deleteById($table, $id) {
        try {
            $stmt = $this->connection->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Delete failed: " . $e->getMessage());
        }
    }

    // =================================================================
    // SESSION MANAGEMENT HELPERS
    // =================================================================
    
    public function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // =================================================================
// CUSTOMER REGISTRATION
// =================================================================
public function registerCustomer($customer_firstname, $customer_lastname, $customer_username, $customer_email, $customer_password) {
    try {
        // Check duplicates (username OR email) in customers table
        $existing = $this->fetch(
            "SELECT customer_id FROM customers WHERE customer_username = ? OR customer_email = ? LIMIT 1",
            [$customer_username, $customer_email]
        );

        if ($existing) {
            return "Username or Email already exists.";
        }

        // Hash password
        $hashedPassword = password_hash($customer_password, PASSWORD_BCRYPT);

        // Insert new customer
        $success = $this->insert(
            "INSERT INTO customers (customer_firstname, customer_lastname, customer_username, customer_email, customer_password, cs_created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$customer_firstname, $customer_lastname, $customer_username, $customer_email, $hashedPassword]
        );

        return $success ? true : "Registration failed. Please try again.";

    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        return "Registration failed. Please try again.";
    }
}


// =================================================================
// UNIFIED LOGIN (Admins + Customers)
// =================================================================
public function login($usernameOrEmail, $password) {
    $this->startSession();

    try {
        // 1. Check in admins table
        $admin = $this->fetch(
            "SELECT admin_id, admin_username, admin_password 
             FROM admins 
             WHERE admin_username = ? 
             LIMIT 1",
            [$usernameOrEmail]
        );

        if ($admin && password_verify($password, $admin['admin_password'])) {
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['admin_username'];
            $_SESSION['role'] = "admin";

            return [
                "success" => true,
                "redirect" => "admin_dashboard.php",
                "user" => $admin
            ];
        }

        // 2. Check in customers table
        $customer = $this->fetch(
            "SELECT customer_id, customer_firstname, customer_lastname, customer_username, customer_email, customer_password, is_verified
             FROM customers
             WHERE customer_username = ? OR customer_email = ?
             LIMIT 1",
            [$usernameOrEmail, $usernameOrEmail]
        );

        //verify
        if ($customer && password_verify($password, $customer['customer_password'])) {
            // Check if account is verified
            if ($customer['is_verified'] == 0) {
                return ["success" => false, "message" => "Please verify your account before logging in."];
            }

            // If everything is okay, log them in
            $_SESSION['customer_id'] = $customer['customer_id'];
            $_SESSION['customer_username'] = $customer['customer_username'];
            $_SESSION['customer_name'] = $customer['customer_firstname'] . " " . $customer['customer_lastname'];
            $_SESSION['customer_email'] = $customer['customer_email'];
            $_SESSION['role'] = "customer";

            return [
                "success" => true,
                "redirect" => "index.php",
                "user" => $customer
            ];
        }

        // If not found anywhere
        return ["success" => false, "message" => "Invalid username/email or password."];

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return ["success" => false, "message" => "Login failed. Please try again."];
    }
}


// =================================================================
// LOGOUT (works for both Admin + Customer)
// =================================================================
public function logout() {
    $this->startSession();

    $_SESSION = array();

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}


// =================================================================
// SESSION HELPERS
// =================================================================
public function isLoggedIn() {
    $this->startSession();
    return isset($_SESSION['role']); // admin or customer
}

public function getCurrentUserRole() {
    $this->startSession();
    return $_SESSION['role'] ?? null;
}

public function getCurrentCustomerId() {
    $this->startSession();
    return $_SESSION['customer_id'] ?? null;
}

public function getCurrentCustomer() {
    $customerId = $this->getCurrentCustomerId();
    if (!$customerId) {
        return null;
    }

    return $this->fetch(
        "SELECT * FROM customers WHERE customer_id = ? LIMIT 1",
        [$customerId]
    );
}


    // PAGINATION HELPER
    
    public function getPaginatedResults($table, $page = 1, $limit = 10, $orderBy = "id DESC") {
        try {
            $offset = ($page - 1) * $limit;

            // Count total rows
            $countStmt = $this->connection->prepare("SELECT COUNT(*) as total FROM {$table}");
            $countStmt->execute();
            $total = $countStmt->fetch()['total'];

            // Fetch paginated data  
            $stmt = $this->connection->prepare("SELECT * FROM {$table} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll();

            return [
                "data" => $data,
                "total" => $total,
                "total_pages" => ceil($total / $limit),
                "current_page" => $page,
                "per_page" => $limit
            ];
        } catch (PDOException $e) {
            throw new Exception("Pagination failed: " . $e->getMessage());
        }
    }

    // =================================================================
// ORDER MANAGEMENT METHODS (Add these to your Database.php class)
// =================================================================

public function getAllOrders($orderBy = 'created_at DESC') {
    try {
        return $this->select("SELECT * FROM orders ORDER BY {$orderBy}");
    } catch (Exception $e) {
        error_log("Get all orders error: " . $e->getMessage());
        return [];
    }
}

public function getOrderById($order_id) {
    try {
        return $this->fetch("SELECT * FROM orders WHERE order_id = ? LIMIT 1", [$order_id]);
    } catch (Exception $e) {
        error_log("Get order by ID error: " . $e->getMessage());
        return null;
    }
}
public function getOrderItems($order_id) {
    return $this->select(
        "SELECT * FROM order_items WHERE order_id = ?",
        [$order_id]
    );
}

//marielle ayaw sumagot ng mga hayop
public function updateOrderStatus($order_id, $status) {
    try {
        // Validate status
        $validStatuses = ['processing', 'preparing', 'out for delivery', 'received', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        $success = $this->update(
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $order_id]
        );

        if ($success) {
            return ['success' => true, 'message' => 'Order status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update order status'];
        }
    } catch (Exception $e) {
        error_log("Update order status error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating order status'];
    }
}

public function getOrderStatuses() {
    return [
        'processing' => 'Processing',
        'preparing' => 'Preparing',
        'out for delivery' => 'Out for Delivery',
        'received' => 'Received',
        'cancelled' => 'Cancelled'
    ];
}

public function getOrdersByStatus($status) {
    try {
        return $this->select("SELECT * FROM orders WHERE order_status = ? ORDER BY o_created_at DESC", [$status]);
    } catch (Exception $e) {
        error_log("Get orders by status error: " . $e->getMessage());
        return [];
    }
}

public function getOrderStats() {
    try {
        $stats = [];
        $statuses = array_keys($this->getOrderStatuses()); 

        foreach ($statuses as $status) {
            $row = $this->fetch("SELECT COUNT(order_id) AS order_count FROM orders WHERE order_status = ?",[$status]);
            $stats[$status] = $row['order_count'] ?? 0;
        }

        // overall stats
        $row = $this->fetch("SELECT COUNT(order_id) AS total_orders, SUM(total_amount) AS total_revenue FROM orders");

        $stats['total_orders'] = $row['total_orders'] ?? 0;
        $stats['total_revenue'] = $row['total_revenue'] ?? 0;

        return $stats;
    } catch (Exception $e) {
        error_log("Get order stats error: " . $e->getMessage());
        return [];
    }
}


public function searchOrders($search_term) {
    try {
        $search = "%{$search_term}%";
        return $this->select(
            "SELECT o.order_id, o.status, o.total_amount, o.created_at, c.firstname, c.lastname, c.email
             FROM orders o
             JOIN customers c ON o.customer_id = c.customer_id
             WHERE c.firstname LIKE ? 
                OR c.lastname LIKE ?
                OR c.email LIKE ?
                OR o.order_id LIKE ?
             ORDER BY o.created_at DESC",
            [$search, $search, $search, $search]
        );
    } catch (Exception $e) {
        error_log('Search orders error: ' . $e->getMessage());
        return [];
    }
}



   // =================================================================
// CUSTOMER ORDER MANAGEMENT METHODS
// =================================================================

public function getCustomerOrders($customer_id = null, $limit = null, $offset = 0) {
    try {
        if ($customer_id === null) {
            $customer_id = $this->getCurrentCustomerId();
        }
        
        if (!$customer_id) {
            return [];
        }
        
        $sql = "SELECT * FROM orders WHERE customer_id = ? ORDER BY o_created_at DESC";
        $params = [$customer_id];
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }
        
        return $this->select($sql, $params);
        
    } catch (Exception $e) {
        error_log("Get customer orders error: " . $e->getMessage());
        return [];
    }
}

public function getCustomerOrder($orderId, $customer_id = null) {
    try {
        if ($customer_id === null) {
            $customer_id = $this->getCurrentCustomerId();
        }
        
        if (!$customer_id) {
            return null;
        }
        
        $order = $this->fetch(
            "SELECT * FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1",
            [$orderId, $customer_id]
        );

        if (!$order) {
            return null;
        }

        // Format fields for UI
        $order['formatted_status'] = $this->formatOrderStatus($order['order_status']);
        $order['status_class']     = $this->getOrderStatusClass($order['order_status']);
        $order['formatted_total']  = $this->formatPrice($order['total_amount']);
        $order['formatted_date']   = $this->formatOrderDate($order['o_created_at']);

        return $order;
        
    } catch (Exception $e) {
        error_log("Get customer order error: " . $e->getMessage());
        return null;
    }
}

public function getCustomerOrderItems($orderId, $customer_id = null) {
    try {
        $order = $this->getCustomerOrder($orderId, $customer_id);
        if (!$order) {
            return [];
        }
        
        return $this->select(
            "SELECT oi.order_item_id, oi.order_id, oi.perfume_id, oi.order_quantity, oi.order_price
             FROM order_items oi 
             LEFT JOIN perfumes p ON oi.perfume_id = p.perfume_id 
             WHERE oi.order_id = ? 
             ORDER BY oi.order_item_id",
            [$orderId]
        );
        
    } catch (Exception $e) {
        error_log("Get customer order items error: " . $e->getMessage());
        return [];
    }
}

public function getCustomerOrdersCount($customer_id = null) {
    try {
        if ($customer_id === null) {
            $customer_id = $this->getCurrentCustomerId();
        }
        
        if (!$customer_id) {
            return 0;
        }
        
        $result = $this->fetch(
            "SELECT COUNT(*) as total FROM orders WHERE customer_id = ?",
            [$customer_id]
        );

        return $result ? (int)$result['total_amount'] : 0;

    } catch (Exception $e) {
        error_log("Get customer orders count error: " . $e->getMessage());
        return 0;
    }
}

public function getCustomerOrdersByStatus($status, $customer_id = null) {
    try {
        if ($customer_id === null) {
            $customer_id = $this->getCurrentCustomerId();
        }
        
        if (!$customer_id) {
            return [];
        }
        
        return $this->select(
            "SELECT * FROM orders WHERE customer_id = ? AND status = ? ORDER BY created_at DESC",
            [$customer_id, $status]
        );
        
    } catch (Exception $e) {
        error_log("Get customer orders by status error: " . $e->getMessage());
        return [];
    }
}

public function getCustomerRecentOrders($limit = 5, $customer_id = null) {
    return $this->getCustomerOrders($customer_id, $limit);
}

// =================================================================
// ORDER HELPER FORMATTING METHODS
// =================================================================

public function formatOrderStatus($status) {
    $map = [
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled'
    ];
    return $map[strtolower($status)] ?? ucfirst($status);
}

public function getOrderStatusClass($status) {
    $map = [
        'pending'    => 'status-pending',
        'processing' => 'status-processing',
        'shipped'    => 'status-shipped',
        'delivered'  => 'status-delivered',
        'completed'  => 'status-completed',
        'cancelled'  => 'status-cancelled'
    ];
    return $map[strtolower($status)] ?? '';
}

public function formatPrice($amount) {
    return "₱" . number_format((float)$amount, 2);
}

public function formatOrderDate($date) {
    return date("M d, Y h:i A", strtotime($date));
}

    // =================================================================
    // CUSTOMER PROFILE MANAGEMENT METHODS
    // =================================================================
    
    public function updateCustomerProfile($data, $customer_id = null) {
        try {
            // If no customer_id provided, use current logged-in customer
            if ($customer_id === null) {
                $customer_id = $this->getCurrentCustomerId();
            }
            
            if (!$customer_id) {
                return ['success' => false, 'message' => 'Please login first.'];
            }
            
            // Validate required fields
            $requiredFields = ['customer_firstname', 'customer_lastname', 'customer_username'];
            foreach ($requiredFields as $field) {
                if (empty(trim($data[$field] ?? ''))) {
                    return ['success' => false, 'message' => ucfirst($field) . ' is required.'];
                }
            }
            
            // Validate email format
            if (!filter_var($data['customer_username'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Please enter a valid email address.'];
            }
            
            // Check if username is taken by another user
            $existing = $this->fetch(
                "SELECT customer_id FROM customers WHERE customer_username = ? AND customer_id != ? LIMIT 1",
                [$data['customer_username'], $customer_id]
            );
            
            if ($existing) {
                return ['success' => false, 'message' => 'Email address already exists.'];
            }
            
            // Update profile
            $rowsAffected = $this->update(
                "UPDATE customers SET customer_firstname = ?, customer_lastname = ?, customer_username = ? WHERE customer_id = ?",
                [
                    trim($data['customer_firstname']),
                    trim($data['customer_lastname']),
                    trim($data['customer_username']),
                    $customer_id
                ]
            );
            
            if ($rowsAffected > 0) {
                // Update session data if it's current user
                if ($customer_id == $this->getCurrentCustomerId()) {
                    $_SESSION['customer_username'] = $data['customer_username'];
                    $_SESSION['customer_name'] = $data['customer_firstname'] . ' ' . $data['customer_lastname'];
                }
                
                return ['success' => true, 'message' => 'Profile updated successfully!'];
            } else {
                return ['success' => false, 'message' => 'No changes were made.'];
            }
            
        } catch (Exception $e) {
            error_log("Customer profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed. Please try again.'];
        }
    }
    
    public function changeCustomerPassword($currentPassword, $newPassword, $confirmPassword, $customer_id = null) {
        try {
            // If no customer_id provided, use current logged-in customer
            if ($customer_id === null) {
                $customer_id = $this->getCurrentCustomerId();
            }
            
            if (!$customer_id) {
                return ['success' => false, 'message' => 'Please login first.'];
            }
            
            // Validate inputs
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                return ['success' => false, 'message' => 'All password fields are required.'];
            }
            
            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'New passwords do not match.'];
            }
            
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
            }
            
            // Verify current password
            $customer = $this->fetch(
                "SELECT customer_password FROM customers WHERE customer_id = ? LIMIT 1",
                [$customer_id]
            );
            
            if (!$customer || !password_verify($currentPassword, $customer['customer_password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $rowsAffected = $this->update(
                "UPDATE customers SET customer_password = ? WHERE customer_id = ?",
                [$hashedPassword, $customer_id]
            );
            
            if ($rowsAffected > 0) {
                return ['success' => true, 'message' => 'Password changed successfully!'];
            } else {
                return ['success' => false, 'message' => 'Password change failed.'];
            }
            
        } catch (Exception $e) {
            error_log("Customer password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password change failed. Please try again.'];
        }
    }

    // =================================================================
    // UTILITY/FORMATTING METHODS
    // =================================================================
  
    // =================================================================
    // DASHBOARD HELPERS
    // =================================================================
    
    public function getProductsCount() {
        try {
            $result = $this->fetch("SELECT COUNT(perfume_id) as total FROM perfumes");
            return $result ? (int)$result['total'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public function getUsersCount() {
        try {
            $tableExists = $this->fetch("SHOW TABLES LIKE 'customers'");
            if ($tableExists) {
                $result = $this->fetch("SELECT COUNT(*) as cnt FROM customers");
                return $result ? (int)$result['cnt'] : 0;
            }
        } catch (Exception $e) {
            return 0;
        }
        return 0;
    }
    
    public function getOrdersCount() {
        try {
            $tableExists = $this->fetch("SHOW TABLES LIKE 'orders'");
            if ($tableExists) {
                $result = $this->fetch("SELECT COUNT(*) as cnt FROM orders");
                return $result ? (int)$result['cnt'] : 0;
            }
        } catch (Exception $e) {
            return 0;
        }
        return 0;
    }
    
    public function getLowStockProducts($threshold = 10) {
        return $this->select("SELECT * FROM perfumes WHERE stock < ? ORDER BY stock ASC", [$threshold]);
    }
    
    public function getCustomerDashboardData($customer_id = null) {
        try {
            // If no customer_id provided, use current logged-in customer
            if ($customer_id === null) {
                $customer_id = $this->getCurrentCustomerId();
            }
            
            if (!$customer_id) {
                return null;
            }
            
            return [
                'customer' => $this->getCurrentCustomer(),
                'total_orders' => $this->getCustomerOrdersCount($customer_id),
                'recent_orders' => $this->getCustomerRecentOrders(3, $customer_id),
                'pending_orders' => count($this->getCustomerOrdersByStatus('pending', $customer_id)),
                'completed_orders' => count($this->getCustomerOrdersByStatus('completed', $customer_id)),
                'cancelled_orders' => count($this->getCustomerOrdersByStatus('cancelled', $customer_id))
            ];
            
        } catch (Exception $e) {
            error_log("Get customer dashboard data error: " . $e->getMessage());
            return null;
        }
    }

    // =================================================================
    // PRODUCT MANAGEMENT METHODS  
    // =================================================================
    
    public function getProductById($id) {
        return $this->fetch("SELECT * FROM perfumes WHERE perfume_id = ?", [$id]);
    }
    public function getPerfumes($sex_filter = null, $search_query = null) {
        $sql = "SELECT * FROM perfumes WHERE 1";
        $params = [];

        if ($sex_filter === 'Male' || $sex_filter === 'Female') {
            $sql .= " AND sex = ?";
            $params[] = $sex_filter;
        }

        if (!empty($search_query)) {
            $sql .= " AND (perfume_name LIKE ? OR perfume_descr LIKE ? OR perfume_price LIKE ?)";
            $search_like = "%" . $search_query . "%";
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        //tanggalin if may error
        $sql .= " ORDER BY perfume_id DESC"; //sort by latest perfume

        return $this->select($sql, $params);
    }
    
    public function addProduct($data, $files) {
        $name = $data['perfume_name'];
        $brand = $data['perfume_brand'];
        $price = $data['perfume_price'];
        $sex = $data['sex'];
        $stock = $data['stock'] ?? 0;
        $description = $data['perfume_desc'] ?? '';
        $perfume_ml = $data['perfume_ml'] ?? '';
        $scent_family = $data['scent_family'] ?? '';
        $admin_id = $data['admin_id'] ?? null;

        return $this->insert(
            "INSERT INTO perfumes (admin_id, perfume_name, perfume_brand, perfume_price, perfume_ml, sex, perfume_desc, stock, scent_family, p_created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$admin_id, $name, $brand, $price, $perfume_ml, $sex, $description, $stock, $scent_family]
        );
    }
    
    public function updateProduct($data, $files) {
        $id = intval($data['perfume_id']);
        $name = $data['perfume_name'];
        $brand = $data['perfume_brand'];
        $price = $data['perfume_price'];
        $sex = $data['sex'];
        $stock = $data['stock'];
        $description = $data['perfume_desc'];
        $perfume_ml = $data['perfume_ml'];
        $scent_family = $data['scent_family'] ?? '';

        $params = [$name, $brand, $price, $sex, $stock, $description, $perfume_ml, $scent_family];
        $updateQuery = "UPDATE perfumes SET perfume_name = ?, perfume_brand = ?, perfume_price = ?, sex = ?, stock = ?, perfume_desc = ?, perfume_ml = ?, scent_family = ? WHERE perfume_id";

        return $this->update($updateQuery, $params);
    }

    // =================================================================
    // CART MANAGEMENT METHODS (Session-based)
    // =================================================================
    
    public function getCart() {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    public function addToCart($perfume_id, $perfume_name, $perfume_price, $qty = 1) {
        $qty = max(1, (int)$qty);

        if (isset($_SESSION['cart'][$perfume_id])) {
            $_SESSION['cart'][$perfume_id]['perfume_quantity'] += $qty;
        } else {
            $_SESSION['cart'][$perfume_id] = [
                'perfume_name' => $perfume_name,
                'perfume_price' => (float)$perfume_price,
                'perfume_quantity' => $qty,
            ];
        }
    }

    public function updateCartQuantity($id, $qty) {
        $qty = max(1, (int)$qty);
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['perfume_quantity'] = $qty;
        }
    }

    public function updateCartQuantity($id, $qty) {
        $qty = max(1, (int)$qty);
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['perfume_quantity'] = $qty;
        }
    }

    public function removeFromCart($id) {
        if (isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }
    }

    public function clearCart() {
        $_SESSION['cart'] = [];
    }

    public function isCartEmpty() {
        return empty($_SESSION['cart']);
    }

    public function getCartTotals() {
        $cart = $this->getCart();
        $grandTotal = 0;
        foreach ($cart as $item) {
            $grandTotal += $item['perfume_price'] * $item['perfume_quantity'];
        }
        return $grandTotal;
    }

    public function calculateGrandTotal() {
        return $this->getCartTotals(); // Same as getCartTotals
    }

    public function getCartItems() {
        return $this->getCart(); // Same as getCart
    }

    // =================================================================
    // ORDER MANAGEMENT METHODS
    // =================================================================
    
    public function createOrder($customerId, $cartItems, $totalAmount, $paymentMethod, $gcashProof = null) {
        try {
            // Start transaction
            $this->connection->beginTransaction();
            
            // Insert order
            $orderId = $this->insert(
                "INSERT INTO orders (customer_id, payment_method, total_amount, gcash_proof, order_status, o_created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$customerId, $paymentMethod, $totalAmount, $gcashProof]
            );
            
            // Insert order items
            foreach ($cartItems as $perfumeId => $item) {
                $this->insert(
                    "INSERT INTO order_items (order_id, perfume_id, order_quantity, order_price) VALUES (?, ?, ?, ?)",
                    [$orderId, $perfumeId, $item['order_quantity'], $item['order_price']]
                );
                
                // Update stock
                $this->update(
                    "UPDATE perfumes SET perfume_stock = perfume_stock - ? WHERE id = ?",
                    [$item['order_quantity'], $perfumeId]
                );
            }
            
            // Commit transaction
            $this->connection->commit();
            return $orderId;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->connection->rollBack();
            throw new Exception("Order creation failed: " . $e->getMessage());
        }
    }
    
    // =================================================================
    // CHECKOUT HELPER METHODS
    // =================================================================
    
    public function validateCheckoutData($data) {
        $errors = [];
        
        // Required fields
        $required = ['customer_firstname', 'customer_lastname', 'customer_email', 'street', 'city', 'province', 'postal_code', 'payment_method'];
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
            }
        }
        
        // Email validation
        if (!empty($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        // Payment method validation
        if (!in_array($data['payment_method'] ?? '', ['cod', 'gcash'])) {
            $errors[] = "Please select a valid payment method.";
        }
        
        // GCash validation
        if (($data['payment_method'] ?? '') === 'gcash') {
            if (empty($_FILES['gcash_ref']['name'])) {
                $errors[] = "Please upload proof of payment for GCash.";
            } elseif ($_FILES['gcash_ref']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading proof of payment.";
            }
        }
        
        return $errors;
    }
    
    //hindi pa din okay
    public function processCheckout($data, $files) {
        // Validate cart is not empty
        if ($this->isCartEmpty()) {
            throw new Exception("Your cart is empty.");
        }
        
        // Validate form data
        $errors = $this->validateCheckoutData($data);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }
        
        // Get cart data
        $cartItems = $this->getCartItems();
        $grandTotal = $this->calculateGrandTotal();
        
        // Handle GCash proof upload
        $proofFileName = null;
        if ($data['payment_method'] === 'gcash' && !empty($files['gcash_ref']['name'])) {
            $proofFileName = $this->handleProofUpload($files['gcash_ref']);
        }
        
        // Prepare customer data
        $customerData = [
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => trim($data['phone'] ?? ''),
            'address' => $this->formatAddress($data),
            'payment_method' => $data['payment'],
            'proof_of_payment' => $proofFileName
        ];
        
        // Create order
        $orderId = $this->createOrderWithDetails($customerData, $cartItems, $grandTotal);
        
        // Clear cart after successful order
        $this->clearCart();
        
        return $orderId;
    }
    
    private function formatAddress($data) {
        return trim($data['street']) . ', ' . 
               trim($data['barangay']) . ', ' .
               trim($data['city']) . ', ' . 
               trim($data['province']) . ' ' . 
               trim($data['postal_code']);
    }
    
    //images for proof of payment
    private function handleProofUpload($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Only JPEG, PNG files are allowed for proof of payment.");
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception("Proof of payment file is too large. Maximum 5MB allowed.");
        }
        
        $fileName = 'proof_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadPath = "uploads/proofs/" . $fileName;
        
        // Create directory if it doesn't exist
        if (!file_exists("uploads/proofs/")) {
            mkdir("uploads/proofs/", 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return $fileName;
        } else {
            throw new Exception("Failed to upload proof of payment.");
        }
    }
    
    private function createOrderWithDetails($customerId, $cartItems, $totalAmount, $paymentMethod, $gcashProof = null) {
        try {
            // Start transaction
            $this->connection->beginTransaction();
            
            // Insert order with additional fields
            $orderId = $this->insert(
                "INSERT INTO orders (customer_id, payment_method, total_amount, gcash_proof, order_status, o_created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$customerId, $paymentMethod, $totalAmount, $gcashProof]
            );
            
            // Insert order items and update stock
            foreach ($cartItems as $perfumeId => $item) {
                // Check if enough stock available
                $product = $this->getProductById($perfumeId);
                if (!$product || $product['perfume_stock'] < $item['order_quantity']) {
                    throw new Exception("Insufficient stock for " . $item['perfume_name']);
                }
            
                //insert sa oi
                $this->insert(
                    "INSERT INTO order_items (order_id, perfume_id, order_quantity, order_price) VALUES (?, ?, ?, ?)",
                    [$orderId, $perfumeId, $item['order_quantity'], $item['order_price']]
                );
                
                $this->update(
                    "UPDATE perfumes SET perfume_stock = perfume_stock - ? WHERE perfume_id = ?",
                    [$item['order_quantity'], $perfumeId]
                );
            }
            
            // Commit transaction
            $this->connection->commit();
            return $orderId;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->connection->rollBack();
            throw new Exception("Order creation failed: " . $e->getMessage());
        }
    }
    
    public function isUserLoggedIn() {
        return isset($_SESSION['customer_id']) || isset($_SESSION['admin_id']);
    }
    
    public function getCheckoutSummary() {
        if ($this->isCartEmpty()) {
            return null;
        }
        $cartItems = $_SESSION['cart'];
        
        return [
            'items' => $cartItems,
            'total' => $this->calculateGrandTotal(),
            'item_count' => array_sum(array_column($cartItems, 'perfume_quantity'))
        ];
    }
}
?>