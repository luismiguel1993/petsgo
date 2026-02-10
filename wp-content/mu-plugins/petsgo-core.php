<?php
/**
 * Plugin Name: PetsGo Core Marketplace
 * Description: Sistema central para PetsGo. API REST, Roles y Tablas personalizadas.
 * Version: 1.0.0
 * Author: Alexiandra Andrade (Admin)
 */

if (!defined('ABSPATH')) exit; // Seguridad Hard Constraint

// CORS para frontend React (Vite dev server)
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        $allowed = ['http://localhost:5177', 'http://localhost:5176', 'http://localhost:3000'];
        if (in_array($origin, $allowed)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        } else {
            header('Access-Control-Allow-Origin: http://localhost:5177');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type');
        return $value;
    });
}, 15);

class PetsGo_Core {

    private $db_version = '1.0';

    public function __construct() {
        add_action('init', [$this, 'register_roles']);
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
        // Hook de activación para crear tablas se debe ejecutar manualmente o al activar un plugin normal
        // En mu-plugins, corre directo. Verifica existencia de tablas aquí o usa un script de instalación.
    }

    /**
     * 2. MATRIZ DE USUARIOS Y PERMISOS
     */
    public function register_roles() {
        // Vendor
        add_role('petsgo_vendor', 'Tienda (Vendor)', [
            'read' => true, 
            'upload_files' => true,
            'manage_inventory' => true
        ]);

        // Rider
        add_role('petsgo_rider', 'Delivery (Rider)', [
            'read' => true,
            'manage_deliveries' => true
        ]);
        
        // Support
        add_role('petsgo_support', 'Soporte', [
            'read' => true,
            'moderate_comments' => true,
            'manage_support_tickets' => true
        ]);
    }

    /**
     * 5. REGLAS DE CODIFICACIÓN (API Headless)
     */
    public function register_api_endpoints() {
        
        // --- PÚBLICO ---

        // Obtener Inventario (Público o por Tienda)
        register_rest_route('petsgo/v1', '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => '__return_true'
        ]);
        
        // Detalle de producto
        register_rest_route('petsgo/v1', '/products/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_detail'],
            'permission_callback' => '__return_true'
        ]);

        // Obtener Tiendas
        register_rest_route('petsgo/v1', '/vendors', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendors'],
            'permission_callback' => '__return_true'
        ]);
        
        // Detalle de Tienda
        register_rest_route('petsgo/v1', '/vendors/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendor_detail'],
            'permission_callback' => '__return_true'
        ]);

        // Planes (Suscripciones)
        register_rest_route('petsgo/v1', '/plans', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plans'],
            'permission_callback' => '__return_true'
        ]);

        // --- AUTENTICACIÓN PERSONALIZADA (JWT Simplificado o Session Cookie) ---
        // Nota: En un entorno real se usaría JWT Auth plugin. Aquí simulamos login básico que devuelve cookie.
        register_rest_route('petsgo/v1', '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login_user'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('petsgo/v1', '/auth/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register_user'],
            'permission_callback' => '__return_true'
        ]);

        // --- CLIENTE ---

        // Crear Orden
        register_rest_route('petsgo/v1', '/orders', [
            'methods' => 'POST',
            'callback' => [$this, 'create_order'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);

        // Mis Pedidos
        register_rest_route('petsgo/v1', '/orders/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_orders'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);

        // --- VENDOR ---
        
        // Inventario del Vendor
        register_rest_route('petsgo/v1', '/vendor/inventory', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_vendor_inventory'],
            'permission_callback' => [$this, 'check_vendor_role']
        ]);
        
        // Dashboard Vendor
        register_rest_route('petsgo/v1', '/vendor/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendor_dashboard_data'],
            'permission_callback' => [$this, 'check_vendor_role']
        ]);

        // --- ADMIN ---
        
        // Dashboard Admin
        register_rest_route('petsgo/v1', '/admin/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_admin_dashboard_data'],
            'permission_callback' => function() { return current_user_can('administrator'); }
        ]);

        // --- STATS (Legacy) ---
        register_rest_route('petsgo/v1', '/stats/(?P<vendor_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendor_stats'],
            'permission_callback' => [$this, 'check_vendor_permissions']
        ]);
    }

    /**
     * Lógica de Productos
     */
    public function get_products($request) {
        global $wpdb;
        $vendor_id = $request->get_param('vendor_id');
        $category = $request->get_param('category');
        $search = $request->get_param('search');
        
        $sql = "SELECT i.*, v.store_name, v.logo_url 
                FROM {$wpdb->prefix}petsgo_inventory i
                JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id = v.id WHERE 1=1";
        
        $args = [];

        if ($vendor_id) {
            $sql .= " AND i.vendor_id = %d";
            $args[] = $vendor_id;
        }
        
        if ($category && $category !== 'Todos') {
            $sql .= " AND i.category = %s";
            $args[] = $category;
        }

        if ($search) {
            $sql .= " AND i.product_name LIKE %s";
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, ...$args);
        }

        $products = $wpdb->get_results($sql);
        
        return rest_ensure_response(['data' => $this->format_products($products)]);
    }

    public function get_product_detail($request) {
        global $wpdb;
        $id = $request->get_param('id');
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, v.store_name, v.logo_url FROM {$wpdb->prefix}petsgo_inventory i JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id = v.id WHERE i.id = %d", 
            $id
        ));
        
        if (!$product) return new WP_Error('not_found', 'Producto no encontrado', ['status' => 404]);

        return rest_ensure_response($this->format_products([$product])[0]);
    }

    private function format_products($products) {
        return array_map(function($product) {
            return [
                'id' => (int)$product->id,
                'product_name' => $product->product_name,
                'price' => (float)$product->price,
                'stock' => (int)$product->stock,
                'category' => $product->category,
                'store_name' => $product->store_name,
                'logo_url' => $product->logo_url,
                'rating' => 4.8, // Mockup rating
                'image_url' => $product->image_id ? wp_get_attachment_url($product->image_id) : null
            ];
        }, $products);
    }

    /**
     * Lógica de Vendedores
     */
    public function get_vendors($request) {
        global $wpdb;
        $vendors = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE status = 'active'");
        return rest_ensure_response(['data' => $vendors]);
    }
    
    public function get_vendor_detail($request) {
        global $wpdb;
        $id = $request->get_param('id');
        $vendor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id = %d", $id));
        if (!$vendor) return new WP_Error('not_found', 'Tienda no encontrada', ['status' => 404]);
        return rest_ensure_response($vendor);
    }

    /**
     * Planes
     */
    public function get_plans() {
        global $wpdb;
        $plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_subscriptions");
        // Mockup si no hay tabla aun
        if (empty($plans)) {
            $plans = [
                ['id' => 1, 'plan_name' => 'Básico', 'monthly_price' => 19990, 'features' => ['Hasta 50 productos', 'Comisión 10%']],
                ['id' => 2, 'plan_name' => 'Pro', 'monthly_price' => 39990, 'features' => ['Hasta 500 productos', 'Comisión 7%']],
            ];
        }
        return rest_ensure_response(['data' => $plans]);
    }

    /**
     * Auth (Simple)
     */
    public function login_user($request) {
        $params = $request->get_json_params();
        $creds = [
            'user_login'    => $params['username'],
            'user_password' => $params['password'],
            'remember'      => true
        ];
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) {
            return new WP_Error('auth_failed', 'Credenciales inválidas', ['status' => 401]);
        }
        
        // Determinar rol para el frontend
        $role = 'customer';
        if (in_array('administrator', $user->roles)) $role = 'admin';
        elseif (in_array('petsgo_vendor', $user->roles)) $role = 'vendor';
        elseif (in_array('petsgo_rider', $user->roles)) $role = 'rider';

        return rest_ensure_response([
            'token' => 'session_cookie', // Frontend usa cookies, esto es flag
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'displayName' => $user->display_name,
                'email' => $user->user_email,
                'role' => $role
            ]
        ]);
    }

    public function register_user($request) {
        $params = $request->get_json_params();
        $user_id = wp_create_user($params['username'], $params['password'], $params['email']);
        if (is_wp_error($user_id)) {
            return new WP_Error('register_failed', $user_id->get_error_message(), ['status' => 400]);
        }
        return rest_ensure_response(['message' => 'Usuario creado exitosamente']);
    }

    /**
     * Lógica de Ordenes
     */
    public function create_order($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        // Validación básica
        if (!isset($params['vendor_id'], $params['items'], $params['total'])) {
            return new WP_Error('missing_params', 'Faltan datos', ['status' => 400]);
        }

        // Obtener comisión del vendedor
        $vendor = $wpdb->get_row($wpdb->prepare(
            "SELECT sales_commission FROM {$wpdb->prefix}petsgo_vendors WHERE id = %d", 
            $params['vendor_id']
        ));

        if (!$vendor) return new WP_Error('invalid_vendor', 'Vendedor no existe', ['status' => 404]);

        // Cálculo Matemático Exacto (Instrucción Final)
        $total = floatval($params['total']);
        $commission_rate = floatval($vendor->sales_commission) / 100;
        $commission_amount = round($total * $commission_rate, 2);
        
        // Delivery fee
        $delivery_fee = isset($params['delivery_fee']) ? floatval($params['delivery_fee']) : 0;

        // Insertar Orden
        $result = $wpdb->insert(
            "{$wpdb->prefix}petsgo_orders",
            [
                'customer_id' => $user_id,
                'vendor_id' => $params['vendor_id'],
                'total_amount' => $total,
                'petsgo_commission' => $commission_amount,
                'delivery_fee' => $delivery_fee,
                'status' => 'pending'
            ],
            ['%d', '%d', '%f', '%f', '%f', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Error al guardar orden', ['status' => 500]);
        }

        return rest_ensure_response([
            'order_id' => $wpdb->insert_id, 
            'message' => 'Orden creada exitosamente',
            'commission_logged' => $commission_amount
        ]);
    }

    public function get_my_orders($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, v.store_name FROM {$wpdb->prefix}petsgo_orders o 
             JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id = v.id 
             WHERE o.customer_id = %d ORDER BY o.created_at DESC", 
            $user_id
        ));
        return rest_ensure_response(['data' => $orders]);
    }

    /**
     * Gestión Vendor
     */
    public function check_vendor_role() {
        $user = wp_get_current_user();
        return in_array('petsgo_vendor', (array) $user->roles) || in_array('administrator', (array) $user->roles);
    }
    
    public function handle_vendor_inventory($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Obtener ID de vendor asociado al usuario
        $vendor_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_vendors WHERE user_id = %d", $user_id));
        
        if (!$vendor_id && !current_user_can('administrator')) {
            return new WP_Error('no_vendor', 'No tienes una tienda asociada', ['status' => 403]);
        }

        if ($request->get_method() === 'GET') {
            $products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id = %d", 
                $vendor_id
            ));
            return rest_ensure_response(['data' => $products]);
        }
        
        if ($request->get_method() === 'POST') {
            $params = $request->get_json_params();
            $wpdb->insert("{$wpdb->prefix}petsgo_inventory", [
                'vendor_id' => $vendor_id,
                'product_name' => $params['product_name'],
                'price' => $params['price'],
                'stock' => $params['stock'],
                'category' => $params['category']
            ]);
            return rest_ensure_response(['id' => $wpdb->insert_id, 'message' => 'Producto agregado']);
        }
    }
    
    public function get_vendor_dashboard_data($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $vendor_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_vendors WHERE user_id = %d", $user_id));
        
        if (!$vendor_id) return new WP_Error('no_vendor', 'Sin tienda', ['status' => 403]);
        
        $total_sales = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id = %d AND status = 'delivered'", $vendor_id));
        $pending_orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id = %d AND status = 'pending'", $vendor_id));
        
        return rest_ensure_response([
            'sales' => (float)$total_sales,
            'pending_orders' => (int)$pending_orders,
            'store_status' => 'active'
        ]);
    }
    
    public function get_admin_dashboard_data() {
        global $wpdb;
        $total_sales = $wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}petsgo_orders WHERE status = 'delivered'");
        $commissions = $wpdb->get_var("SELECT SUM(petsgo_commission) FROM {$wpdb->prefix}petsgo_orders WHERE status = 'delivered'");
        $vendors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_vendors");
        
        return rest_ensure_response([
            'total_sales' => (float)$total_sales,
            'total_commissions' => (float)$commissions,
            'active_vendors' => (int)$vendors
        ]);
    }

    /**
     * Permisos y Seguridad (Impersonate Mode Check)
     */
    public function check_vendor_permissions($request) {
        $user = wp_get_current_user();
        $vendor_id = $request->get_param('vendor_id');

        // Admin (Alexiandra) puede ver todo (Impersonate Mode)
        if (in_array('administrator', (array) $user->roles)) {
            return true;
        }

        // Vendor solo puede ver su propia data
        global $wpdb;
        $my_vendor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}petsgo_vendors WHERE user_id = %d", 
            $user->ID
        ));

        return intval($my_vendor_id) === intval($vendor_id);
    }
    
    public function get_vendor_stats($request) {
        // Lógica de dashboard aquí...
        return ['sales' => 15000, 'orders' => 12]; // Mockup
    }
}

new PetsGo_Core();