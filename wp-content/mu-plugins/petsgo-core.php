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
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
    }

    /* ===================================================
       MENÚS DE ADMINISTRACIÓN
    =================================================== */
    public function register_admin_menus() {
        // Menú principal PetsGo
        add_menu_page(
            'PetsGo Marketplace',
            'PetsGo',
            'manage_options',
            'petsgo-dashboard',
            [$this, 'admin_page_dashboard'],
            'dashicons-store',
            3
        );

        add_submenu_page('petsgo-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'petsgo-dashboard', [$this, 'admin_page_dashboard']);
        add_submenu_page('petsgo-dashboard', 'Productos', 'Productos', 'manage_options', 'petsgo-products', [$this, 'admin_page_products']);
        add_submenu_page('petsgo-dashboard', 'Tiendas / Vendors', 'Tiendas', 'manage_options', 'petsgo-vendors', [$this, 'admin_page_vendors']);
        add_submenu_page('petsgo-dashboard', 'Pedidos', 'Pedidos', 'manage_options', 'petsgo-orders', [$this, 'admin_page_orders']);
        add_submenu_page('petsgo-dashboard', 'Planes', 'Planes', 'manage_options', 'petsgo-plans', [$this, 'admin_page_plans']);
    }

    public function admin_styles($hook) {
        if (strpos($hook, 'petsgo') === false) return;
        echo '<style>
            .petsgo-wrap { max-width: 1200px; }
            .petsgo-wrap h1 { color: #00A8E8; }
            .petsgo-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0; }
            .petsgo-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center; }
            .petsgo-card h2 { font-size: 32px; margin: 0; color: #00A8E8; }
            .petsgo-card p { color: #666; margin: 8px 0 0; }
            .petsgo-table { border-collapse: collapse; width: 100%; background: #fff; }
            .petsgo-table th { background: #00A8E8; color: #fff; padding: 10px 14px; text-align: left; }
            .petsgo-table td { padding: 10px 14px; border-bottom: 1px solid #eee; }
            .petsgo-table tr:hover td { background: #f0faff; }
            .petsgo-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .petsgo-badge.active, .petsgo-badge.delivered { background: #d4edda; color: #155724; }
            .petsgo-badge.pending { background: #fff3cd; color: #856404; }
            .petsgo-badge.processing, .petsgo-badge.in_transit { background: #cce5ff; color: #004085; }
            .petsgo-badge.cancelled { background: #f8d7da; color: #721c24; }
            .petsgo-badge.inactive { background: #e2e3e5; color: #383d41; }
        </style>';
    }

    /* --- DASHBOARD --- */
    public function admin_page_dashboard() {
        global $wpdb;
        $products  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory");
        $vendors   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_vendors");
        $orders    = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders");
        $revenue   = $wpdb->get_var("SELECT COALESCE(SUM(total_amount),0) FROM {$wpdb->prefix}petsgo_orders WHERE status='delivered'");
        $commissions = $wpdb->get_var("SELECT COALESCE(SUM(petsgo_commission),0) FROM {$wpdb->prefix}petsgo_orders WHERE status='delivered'");
        $users     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🐾 PetsGo Marketplace — Dashboard</h1>
            <div class="petsgo-cards">
                <div class="petsgo-card"><h2><?php echo $products; ?></h2><p>Productos</p></div>
                <div class="petsgo-card"><h2><?php echo $vendors; ?></h2><p>Tiendas</p></div>
                <div class="petsgo-card"><h2><?php echo $orders; ?></h2><p>Pedidos</p></div>
                <div class="petsgo-card"><h2><?php echo $users; ?></h2><p>Usuarios</p></div>
                <div class="petsgo-card"><h2>$<?php echo number_format($revenue, 0, ',', '.'); ?></h2><p>Ventas Totales</p></div>
                <div class="petsgo-card"><h2>$<?php echo number_format($commissions, 0, ',', '.'); ?></h2><p>Comisiones PetsGo</p></div>
            </div>
            <h3>Últimos Pedidos</h3>
            <?php
            $recent = $wpdb->get_results("SELECT o.*, v.store_name, u.display_name AS customer_name FROM {$wpdb->prefix}petsgo_orders o 
                LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id = v.id
                LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
                ORDER BY o.created_at DESC LIMIT 10");
            if ($recent): ?>
            <table class="petsgo-table">
                <thead><tr><th>#</th><th>Cliente</th><th>Tienda</th><th>Total</th><th>Comisión</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                <tr>
                    <td><?php echo $o->id; ?></td>
                    <td><?php echo esc_html($o->customer_name ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($o->store_name ?? 'N/A'); ?></td>
                    <td>$<?php echo number_format($o->total_amount, 0, ',', '.'); ?></td>
                    <td>$<?php echo number_format($o->petsgo_commission, 0, ',', '.'); ?></td>
                    <td><span class="petsgo-badge <?php echo esc_attr($o->status); ?>"><?php echo esc_html(ucfirst($o->status)); ?></span></td>
                    <td><?php echo esc_html($o->created_at); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: echo '<p>No hay pedidos aún.</p>'; endif; ?>
        </div>
        <?php
    }

    /* --- PRODUCTOS --- */
    public function admin_page_products() {
        global $wpdb;
        
        // Manejar acciones (eliminar, editar)
        if (isset($_POST['petsgo_add_product']) && wp_verify_nonce($_POST['_wpnonce'], 'petsgo_product')) {
            $wpdb->insert("{$wpdb->prefix}petsgo_inventory", [
                'vendor_id'    => intval($_POST['vendor_id']),
                'product_name' => sanitize_text_field($_POST['product_name']),
                'price'        => floatval($_POST['price']),
                'stock'        => intval($_POST['stock']),
                'category'     => sanitize_text_field($_POST['category']),
            ]);
            echo '<div class="notice notice-success"><p>Producto agregado correctamente.</p></div>';
        }
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['product_id'])) {
            $wpdb->delete("{$wpdb->prefix}petsgo_inventory", ['id' => intval($_GET['product_id'])]);
            echo '<div class="notice notice-warning"><p>Producto eliminado.</p></div>';
        }

        $products = $wpdb->get_results("SELECT i.*, v.store_name FROM {$wpdb->prefix}petsgo_inventory i LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id = v.id ORDER BY i.id DESC");
        $vendors = $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name");
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🛒 Productos (<?php echo count($products); ?>)</h1>
            
            <details style="margin:16px 0; background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px;">
                <summary style="cursor:pointer; font-weight:600; font-size:14px;">➕ Agregar Nuevo Producto</summary>
                <form method="post" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <?php wp_nonce_field('petsgo_product'); ?>
                    <label>Nombre: <input type="text" name="product_name" required style="width:100%;"></label>
                    <label>Precio: <input type="number" name="price" step="0.01" required style="width:100%;"></label>
                    <label>Stock: <input type="number" name="stock" required style="width:100%;"></label>
                    <label>Categoría: 
                        <select name="category" style="width:100%;">
                            <option>Alimento</option><option>Juguetes</option><option>Salud</option>
                            <option>Accesorios</option><option>Higiene</option><option>Ropa</option>
                        </select>
                    </label>
                    <label>Tienda:
                        <select name="vendor_id" style="width:100%;">
                            <?php foreach ($vendors as $v): ?>
                            <option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div><button type="submit" name="petsgo_add_product" class="button button-primary" style="margin-top:20px;">Guardar Producto</button></div>
                </form>
            </details>
            
            <table class="petsgo-table">
                <thead><tr><th>ID</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Categoría</th><th>Tienda</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><?php echo $p->id; ?></td>
                    <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                    <td>$<?php echo number_format($p->price, 0, ',', '.'); ?></td>
                    <td><?php echo $p->stock; ?></td>
                    <td><?php echo esc_html($p->category); ?></td>
                    <td><?php echo esc_html($p->store_name ?? '—'); ?></td>
                    <td><a href="<?php echo admin_url('admin.php?page=petsgo-products&action=delete&product_id=' . $p->id); ?>" onclick="return confirm('¿Eliminar este producto?')" style="color:#dc3545;">Eliminar</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* --- TIENDAS / VENDORS --- */
    public function admin_page_vendors() {
        global $wpdb;
        
        if (isset($_POST['petsgo_add_vendor']) && wp_verify_nonce($_POST['_wpnonce'], 'petsgo_vendor')) {
            $wpdb->insert("{$wpdb->prefix}petsgo_vendors", [
                'store_name'       => sanitize_text_field($_POST['store_name']),
                'owner_name'       => sanitize_text_field($_POST['owner_name']),
                'email'            => sanitize_email($_POST['email']),
                'phone'            => sanitize_text_field($_POST['phone']),
                'address'          => sanitize_text_field($_POST['address']),
                'city'             => sanitize_text_field($_POST['city']),
                'sales_commission' => floatval($_POST['commission']),
                'status'           => 'active',
            ]);
            echo '<div class="notice notice-success"><p>Tienda registrada correctamente.</p></div>';
        }

        $vendors = $wpdb->get_results("SELECT v.*, (SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id = v.id) AS total_products, (SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id = v.id) AS total_orders FROM {$wpdb->prefix}petsgo_vendors v ORDER BY v.id DESC");
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🏪 Tiendas / Vendors (<?php echo count($vendors); ?>)</h1>
            
            <details style="margin:16px 0; background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px;">
                <summary style="cursor:pointer; font-weight:600; font-size:14px;">➕ Registrar Nueva Tienda</summary>
                <form method="post" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <?php wp_nonce_field('petsgo_vendor'); ?>
                    <label>Nombre tienda: <input type="text" name="store_name" required style="width:100%;"></label>
                    <label>Propietario: <input type="text" name="owner_name" required style="width:100%;"></label>
                    <label>Email: <input type="email" name="email" required style="width:100%;"></label>
                    <label>Teléfono: <input type="text" name="phone" style="width:100%;"></label>
                    <label>Dirección: <input type="text" name="address" style="width:100%;"></label>
                    <label>Ciudad: <input type="text" name="city" style="width:100%;"></label>
                    <label>Comisión (%): <input type="number" name="commission" value="8" step="0.1" style="width:100%;"></label>
                    <div><button type="submit" name="petsgo_add_vendor" class="button button-primary" style="margin-top:20px;">Registrar Tienda</button></div>
                </form>
            </details>

            <table class="petsgo-table">
                <thead><tr><th>ID</th><th>Tienda</th><th>Propietario</th><th>Ciudad</th><th>Comisión</th><th>Productos</th><th>Pedidos</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($vendors as $v): ?>
                <tr>
                    <td><?php echo $v->id; ?></td>
                    <td><strong><?php echo esc_html($v->store_name); ?></strong></td>
                    <td><?php echo esc_html($v->owner_name); ?></td>
                    <td><?php echo esc_html($v->city); ?></td>
                    <td><?php echo $v->sales_commission; ?>%</td>
                    <td><?php echo $v->total_products; ?></td>
                    <td><?php echo $v->total_orders; ?></td>
                    <td><span class="petsgo-badge <?php echo esc_attr($v->status); ?>"><?php echo ucfirst($v->status); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* --- PEDIDOS --- */
    public function admin_page_orders() {
        global $wpdb;
        
        // Cambiar estado
        if (isset($_POST['petsgo_update_status']) && wp_verify_nonce($_POST['_wpnonce'], 'petsgo_order_status')) {
            $wpdb->update(
                "{$wpdb->prefix}petsgo_orders",
                ['status' => sanitize_text_field($_POST['new_status'])],
                ['id' => intval($_POST['order_id'])]
            );
            echo '<div class="notice notice-success"><p>Estado del pedido actualizado.</p></div>';
        }

        $orders = $wpdb->get_results("SELECT o.*, v.store_name, u.display_name AS customer_name FROM {$wpdb->prefix}petsgo_orders o 
            LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id = v.id
            LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
            ORDER BY o.created_at DESC");
        $statuses = ['pending', 'processing', 'in_transit', 'delivered', 'cancelled'];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📦 Pedidos (<?php echo count($orders); ?>)</h1>
            <table class="petsgo-table">
                <thead><tr><th>#</th><th>Cliente</th><th>Tienda</th><th>Total</th><th>Comisión</th><th>Delivery</th><th>Estado</th><th>Fecha</th><th>Cambiar Estado</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?php echo $o->id; ?></td>
                    <td><?php echo esc_html($o->customer_name ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($o->store_name ?? 'N/A'); ?></td>
                    <td>$<?php echo number_format($o->total_amount, 0, ',', '.'); ?></td>
                    <td>$<?php echo number_format($o->petsgo_commission, 0, ',', '.'); ?></td>
                    <td>$<?php echo number_format($o->delivery_fee, 0, ',', '.'); ?></td>
                    <td><span class="petsgo-badge <?php echo esc_attr($o->status); ?>"><?php echo ucfirst(str_replace('_',' ',$o->status)); ?></span></td>
                    <td><?php echo esc_html($o->created_at); ?></td>
                    <td>
                        <form method="post" style="display:flex;gap:4px;">
                            <?php wp_nonce_field('petsgo_order_status'); ?>
                            <input type="hidden" name="order_id" value="<?php echo $o->id; ?>">
                            <select name="new_status" style="font-size:12px;">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo $s; ?>" <?php selected($o->status, $s); ?>><?php echo ucfirst(str_replace('_',' ',$s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="petsgo_update_status" class="button button-small">✓</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* --- PLANES --- */
    public function admin_page_plans() {
        global $wpdb;
        
        if (isset($_POST['petsgo_add_plan']) && wp_verify_nonce($_POST['_wpnonce'], 'petsgo_plan')) {
            $wpdb->insert("{$wpdb->prefix}petsgo_subscriptions", [
                'plan_name'     => sanitize_text_field($_POST['plan_name']),
                'monthly_price' => floatval($_POST['monthly_price']),
                'features'      => sanitize_textarea_field($_POST['features']),
            ]);
            echo '<div class="notice notice-success"><p>Plan agregado correctamente.</p></div>';
        }

        $plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_subscriptions ORDER BY monthly_price ASC");
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📋 Planes de Suscripción (<?php echo count($plans); ?>)</h1>
            
            <details style="margin:16px 0; background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px;">
                <summary style="cursor:pointer; font-weight:600; font-size:14px;">➕ Agregar Nuevo Plan</summary>
                <form method="post" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
                    <?php wp_nonce_field('petsgo_plan'); ?>
                    <label>Nombre del plan: <input type="text" name="plan_name" required style="width:100%;"></label>
                    <label>Precio mensual: <input type="number" name="monthly_price" step="1" required style="width:100%;"></label>
                    <label style="grid-column:1/-1;">Características (una por línea): <textarea name="features" rows="4" style="width:100%;"></textarea></label>
                    <div><button type="submit" name="petsgo_add_plan" class="button button-primary">Guardar Plan</button></div>
                </form>
            </details>

            <div class="petsgo-cards">
                <?php foreach ($plans as $plan): ?>
                <div class="petsgo-card" style="text-align:left;">
                    <h2 style="font-size:20px; margin-bottom:8px;"><?php echo esc_html($plan->plan_name); ?></h2>
                    <p style="font-size:24px; color:#00A8E8; font-weight:700;">$<?php echo number_format($plan->monthly_price, 0, ',', '.'); ?><span style="font-size:14px; color:#999;">/mes</span></p>
                    <?php if (!empty($plan->features)): ?>
                    <ul style="margin:10px 0; padding-left:18px;">
                        <?php foreach (explode("\n", $plan->features) as $f): if (trim($f)): ?>
                        <li><?php echo esc_html(trim($f)); ?></li>
                        <?php endif; endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
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