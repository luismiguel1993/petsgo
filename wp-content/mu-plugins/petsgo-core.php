<?php
/**
 * Plugin Name: PetsGo Core Marketplace
 * Description: Sistema central para PetsGo. API REST, Roles, Admin Panel con permisos por perfil.
 * Version: 2.0.0
 * Author: Alexiandra Andrade (Admin)
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// CORS para frontend React (Vite dev server)
// ============================================================
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

// ============================================================
// CLASE PRINCIPAL
// ============================================================
class PetsGo_Core {

    private $db_version = '3.0';

    public function __construct() {
        add_action('init', [$this, 'register_roles']);
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        // AJAX handlers
        $ajax_actions = [
            'petsgo_search_products', 'petsgo_save_product', 'petsgo_delete_product',
            'petsgo_search_vendors', 'petsgo_save_vendor', 'petsgo_delete_vendor',
            'petsgo_search_orders', 'petsgo_update_order_status',
            'petsgo_search_users', 'petsgo_save_user', 'petsgo_delete_user',
            'petsgo_search_riders', 'petsgo_save_rider_assignment',
            'petsgo_search_plans', 'petsgo_save_plan', 'petsgo_delete_plan',
            'petsgo_search_invoices', 'petsgo_generate_invoice', 'petsgo_download_invoice',
            'petsgo_search_audit_log',
            'petsgo_save_vendor_invoice_config',
        ];
        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_{$action}", [$this, $action]);
        }
        // Public AJAX for invoice download
        add_action('wp_ajax_nopriv_petsgo_download_invoice', [$this, 'petsgo_download_invoice']);
    }

    // ============================================================
    // AUDIT LOG — registra TODA acción de TODOS los usuarios
    // ============================================================
    private function audit($action, $entity_type = '', $entity_id = 0, $details = '') {
        global $wpdb;
        $user = wp_get_current_user();
        $wpdb->insert("{$wpdb->prefix}petsgo_audit_log", [
            'user_id'     => get_current_user_id(),
            'user_name'   => $user->display_name ?: $user->user_login ?: 'system',
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'details'     => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    }

    // ============================================================
    // HELPERS: Rol y Vendor del usuario actual
    // ============================================================
    private function is_admin() {
        return current_user_can('manage_options');
    }
    private function is_vendor() {
        $user = wp_get_current_user();
        return in_array('petsgo_vendor', (array)$user->roles);
    }
    private function is_rider() {
        $user = wp_get_current_user();
        return in_array('petsgo_rider', (array)$user->roles);
    }
    private function get_my_vendor_id() {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}petsgo_vendors WHERE user_id = %d",
            get_current_user_id()
        ));
    }
    private function can_access_admin() {
        return $this->is_admin() || $this->is_vendor() || $this->is_rider();
    }

    // ============================================================
    // MENÚS — visibles según rol
    // ============================================================
    public function register_admin_menus() {
        $cap_all   = 'read'; // Admin, vendor, rider pueden ver el menú base
        $cap_admin = 'manage_options';

        add_menu_page('PetsGo', 'PetsGo', $cap_all, 'petsgo-dashboard', [$this, 'page_dashboard'], 'dashicons-store', 3);

        // Submenús
        add_submenu_page('petsgo-dashboard', 'Dashboard', 'Dashboard', $cap_all, 'petsgo-dashboard', [$this, 'page_dashboard']);
        add_submenu_page('petsgo-dashboard', 'Productos', 'Productos', $cap_all, 'petsgo-products', [$this, 'page_products']);
        add_submenu_page(null, 'Producto', 'Producto', $cap_all, 'petsgo-product-form', [$this, 'page_product_form']);

        // Solo admin
        add_submenu_page('petsgo-dashboard', 'Tiendas', 'Tiendas', $cap_admin, 'petsgo-vendors', [$this, 'page_vendors']);
        add_submenu_page(null, 'Tienda', 'Tienda', $cap_admin, 'petsgo-vendor-form', [$this, 'page_vendor_form']);

        // Pedidos — vendor ve solo los suyos
        add_submenu_page('petsgo-dashboard', 'Pedidos', 'Pedidos', $cap_all, 'petsgo-orders', [$this, 'page_orders']);

        // Solo admin
        add_submenu_page('petsgo-dashboard', 'Usuarios', 'Usuarios', $cap_admin, 'petsgo-users', [$this, 'page_users']);
        add_submenu_page(null, 'Usuario', 'Usuario', $cap_admin, 'petsgo-user-form', [$this, 'page_user_form']);

        // Delivery — admin y riders
        add_submenu_page('petsgo-dashboard', 'Delivery', 'Delivery', $cap_all, 'petsgo-delivery', [$this, 'page_delivery']);

        // Planes — solo admin
        add_submenu_page('petsgo-dashboard', 'Planes', 'Planes', $cap_admin, 'petsgo-plans', [$this, 'page_plans']);

        // Boletas — admin + vendor (los suyos)
        add_submenu_page('petsgo-dashboard', 'Boletas', 'Boletas', $cap_all, 'petsgo-invoices', [$this, 'page_invoices']);

        // Config Boleta Tienda (hidden page)
        add_submenu_page('petsgo-dashboard', 'Config Boleta', '🧾 Config Boleta', $cap_all, 'petsgo-invoice-config', [$this, 'page_invoice_config']);

        // Auditoría — solo admin
        add_submenu_page('petsgo-dashboard', 'Auditoría', 'Auditoría', $cap_admin, 'petsgo-audit', [$this, 'page_audit_log']);
    }

    // ============================================================
    // CSS + JS GLOBAL ADMIN
    // ============================================================
    public function admin_assets($hook) {
        if (strpos($hook, 'petsgo') === false) return;
        wp_enqueue_media();
        $this->print_admin_css();
        $this->print_admin_js();
    }

    private function print_admin_css() {
        echo '<style>
        .petsgo-wrap{max-width:1200px}.petsgo-wrap h1{color:#00A8E8}
        .petsgo-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin:20px 0}
        .petsgo-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center}
        .petsgo-card h2{font-size:32px;margin:0;color:#00A8E8}.petsgo-card p{color:#666;margin:8px 0 0}
        .petsgo-table{border-collapse:collapse;width:100%;background:#fff}
        .petsgo-table th{background:#00A8E8;color:#fff;padding:10px 14px;text-align:left}
        .petsgo-table td{padding:10px 14px;border-bottom:1px solid #eee;vertical-align:middle}
        .petsgo-table tr:hover td{background:#f0faff}
        .petsgo-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .petsgo-badge.active,.petsgo-badge.delivered{background:#d4edda;color:#155724}
        .petsgo-badge.pending,.petsgo-badge.payment_pending{background:#fff3cd;color:#856404}
        .petsgo-badge.processing,.petsgo-badge.in_transit{background:#cce5ff;color:#004085}
        .petsgo-badge.cancelled{background:#f8d7da;color:#721c24}
        .petsgo-badge.inactive{background:#e2e3e5;color:#383d41}
        .petsgo-btn{display:inline-block;padding:6px 16px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:.2s}
        .petsgo-btn-primary{background:#00A8E8;color:#fff}.petsgo-btn-primary:hover{background:#0090c7;color:#fff}
        .petsgo-btn-warning{background:#FFC400;color:#2F3A40}.petsgo-btn-warning:hover{background:#e6b000;color:#2F3A40}
        .petsgo-btn-danger{background:#dc3545;color:#fff}.petsgo-btn-danger:hover{background:#c82333;color:#fff}
        .petsgo-btn-success{background:#28a745;color:#fff}.petsgo-btn-success:hover{background:#218838;color:#fff}
        .petsgo-btn-sm{padding:4px 10px;font-size:12px}
        .petsgo-search-bar{display:flex;gap:10px;margin:16px 0;align-items:center;flex-wrap:wrap}
        .petsgo-search-bar input,.petsgo-search-bar select{padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px}
        .petsgo-search-bar input[type=text]{min-width:250px}
        .petsgo-thumb{width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #ddd}
        .petsgo-stock-low{color:#dc3545;font-weight:700}.petsgo-stock-ok{color:#28a745}
        .petsgo-form-grid{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-top:16px}
        .petsgo-form-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px}
        .petsgo-form-section h3{margin-top:0;color:#2F3A40;border-bottom:2px solid #00A8E8;padding-bottom:8px}
        .petsgo-field{margin-bottom:16px}
        .petsgo-field label{display:block;font-weight:600;margin-bottom:4px;color:#333;font-size:13px}
        .petsgo-field input,.petsgo-field select,.petsgo-field textarea{width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px;box-sizing:border-box}
        .petsgo-field input[readonly],.petsgo-field select[disabled]{background:#f0f0f0;color:#666;cursor:not-allowed}
        .petsgo-field textarea{resize:vertical;min-height:80px}
        .petsgo-field .field-hint{font-size:11px;color:#888;margin-top:3px}
        .petsgo-field .field-error{font-size:12px;color:#dc3545;margin-top:3px;display:none}
        .petsgo-field.has-error input,.petsgo-field.has-error select,.petsgo-field.has-error textarea{border-color:#dc3545}
        .petsgo-field.has-error .field-error{display:block}
        .petsgo-img-upload{display:flex;gap:16px;flex-wrap:wrap}
        .petsgo-img-slot{width:160px;height:160px;border:2px dashed #ccc;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;position:relative;overflow:hidden;background:#fafafa;transition:.2s}
        .petsgo-img-slot:hover{border-color:#00A8E8;background:#f0faff}
        .petsgo-img-slot img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0}
        .petsgo-img-slot .slot-label{font-size:12px;color:#888;text-align:center;pointer-events:none}
        .petsgo-img-slot .slot-label .dashicons{font-size:28px;display:block;margin:0 auto 4px;color:#bbb}
        .petsgo-img-slot .remove-img{position:absolute;top:4px;right:4px;background:rgba(220,53,69,.9);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:14px;cursor:pointer;display:none;z-index:2;line-height:20px;text-align:center}
        .petsgo-img-slot.has-image .remove-img{display:block}
        .petsgo-img-slot.has-image .slot-label{display:none}
        .petsgo-img-specs{background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:10px 14px;margin-top:10px;font-size:12px;color:#555}
        .petsgo-preview-card{background:#fff;border:1px solid #ddd;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
        .petsgo-preview-card .preview-imgs{display:flex;gap:4px;height:180px;background:#f5f5f5;overflow:hidden}
        .petsgo-preview-card .preview-imgs img{flex:1;object-fit:cover;min-width:0}
        .petsgo-preview-card .preview-imgs .no-img{flex:1;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:40px}
        .petsgo-preview-card .preview-body{padding:16px}
        .petsgo-preview-card .preview-body h4{margin:0 0 6px;font-size:16px;color:#2F3A40}
        .petsgo-preview-card .preview-body .preview-price{font-size:22px;font-weight:700;color:#00A8E8}
        .petsgo-preview-card .preview-body .preview-cat{display:inline-block;background:#e3f5fc;color:#00A8E8;font-size:11px;padding:2px 8px;border-radius:10px;margin-top:6px}
        .petsgo-preview-card .preview-body .preview-desc{font-size:13px;color:#666;margin-top:8px;line-height:1.4}
        .petsgo-preview-card .preview-body .preview-stock{font-size:12px;margin-top:6px}
        .petsgo-loader{display:none}.petsgo-loader.active{display:inline-block}
        .petsgo-role-tag{font-size:11px;padding:2px 8px;border-radius:10px;font-weight:600}
        .petsgo-role-tag.admin{background:#00A8E8;color:#fff}
        .petsgo-role-tag.vendor{background:#FFC400;color:#2F3A40}
        .petsgo-role-tag.rider{background:#6f42c1;color:#fff}
        .petsgo-role-tag.customer{background:#e2e3e5;color:#383d41}
        .petsgo-role-tag.support{background:#17a2b8;color:#fff}
        .petsgo-info-bar{background:#e3f5fc;border-left:4px solid #00A8E8;padding:10px 16px;margin:10px 0;border-radius:0 6px 6px 0;font-size:13px;color:#004085}
        </style>';
    }

    private function print_admin_js() {
        ?>
        <script>
        var PG = {
            nonce: '<?php echo wp_create_nonce("petsgo_ajax"); ?>',
            adminUrl: '<?php echo admin_url("admin.php"); ?>',
            ajaxUrl: ajaxurl,
            isAdmin: <?php echo $this->is_admin() ? 'true' : 'false'; ?>,
            isVendor: <?php echo $this->is_vendor() ? 'true' : 'false'; ?>,
            isRider: <?php echo $this->is_rider() ? 'true' : 'false'; ?>,
            myVendorId: <?php echo $this->get_my_vendor_id(); ?>,
            esc: function(s){ return jQuery('<span>').text(s||'').html(); },
            money: function(n){ return '$' + Number(n||0).toLocaleString('es-CL'); },
            post: function(action, data, cb){
                data.action = action;
                data._ajax_nonce = PG.nonce;
                jQuery.post(PG.ajaxUrl, data, cb);
            },
            badge: function(status){
                var label = (status||'').replace(/_/g,' ');
                return '<span class="petsgo-badge ' + (status||'') + '">' + label.charAt(0).toUpperCase()+label.slice(1) + '</span>';
            }
        };
        </script>
        <?php
    }

    // ============================================================
    // 1. DASHBOARD — por rol
    // ============================================================
    public function page_dashboard() {
        global $wpdb;
        $is_admin  = $this->is_admin();
        $is_vendor = $this->is_vendor();
        $is_rider  = $this->is_rider();
        $vid = $this->get_my_vendor_id();

        if ($is_admin) {
            $products    = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory");
            $vendors     = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_vendors");
            $orders      = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders");
            $revenue     = $wpdb->get_var("SELECT COALESCE(SUM(total_amount),0) FROM {$wpdb->prefix}petsgo_orders WHERE status='delivered'");
            $commissions = $wpdb->get_var("SELECT COALESCE(SUM(petsgo_commission),0) FROM {$wpdb->prefix}petsgo_orders WHERE status='delivered'");
            $users       = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        } elseif ($is_vendor && $vid) {
            $products    = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id=%d", $vid));
            $orders      = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d", $vid));
            $revenue     = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_amount),0) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d AND status='delivered'", $vid));
            $pending     = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d AND status='pending'", $vid));
        } elseif ($is_rider) {
            $assigned    = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d", get_current_user_id()));
            $delivered   = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", get_current_user_id()));
            $in_transit  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='in_transit'", get_current_user_id()));
        }
        ?>
        <div class="wrap petsgo-wrap">
        <?php if ($is_admin): ?>
            <h1>🐾 PetsGo — Dashboard Administrador</h1>
            <div class="petsgo-cards">
                <div class="petsgo-card"><h2><?php echo $products; ?></h2><p>Productos</p></div>
                <div class="petsgo-card"><h2><?php echo $vendors; ?></h2><p>Tiendas</p></div>
                <div class="petsgo-card"><h2><?php echo $orders; ?></h2><p>Pedidos</p></div>
                <div class="petsgo-card"><h2><?php echo $users; ?></h2><p>Usuarios</p></div>
                <div class="petsgo-card"><h2><?php echo $this->fmt($revenue); ?></h2><p>Ventas Totales</p></div>
                <div class="petsgo-card"><h2><?php echo $this->fmt($commissions); ?></h2><p>Comisiones PetsGo</p></div>
            </div>
            <h3>Últimos 10 Pedidos</h3>
            <?php $this->render_recent_orders(null, 10); ?>

        <?php elseif ($is_vendor && $vid): 
            $store = $wpdb->get_row($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d", $vid));
        ?>
            <h1>🏪 Mi Tienda — <?php echo esc_html($store->store_name ?? ''); ?></h1>
            <div class="petsgo-cards">
                <div class="petsgo-card"><h2><?php echo $products; ?></h2><p>Mis Productos</p></div>
                <div class="petsgo-card"><h2><?php echo $orders; ?></h2><p>Mis Pedidos</p></div>
                <div class="petsgo-card"><h2><?php echo $this->fmt($revenue); ?></h2><p>Ventas</p></div>
                <div class="petsgo-card"><h2><?php echo $pending; ?></h2><p>Pendientes</p></div>
            </div>
            <h3>Últimos Pedidos de mi Tienda</h3>
            <?php $this->render_recent_orders($vid, 10); ?>

        <?php elseif ($is_rider): ?>
            <h1>🚴 Panel Delivery — <?php echo esc_html(wp_get_current_user()->display_name); ?></h1>
            <div class="petsgo-cards">
                <div class="petsgo-card"><h2><?php echo $assigned; ?></h2><p>Asignados</p></div>
                <div class="petsgo-card"><h2><?php echo $in_transit; ?></h2><p>En Tránsito</p></div>
                <div class="petsgo-card"><h2><?php echo $delivered; ?></h2><p>Entregados</p></div>
            </div>

        <?php else: ?>
            <h1>🐾 PetsGo</h1>
            <p>No tienes una tienda o rol asignado. Contacta al administrador.</p>
        <?php endif; ?>
        </div>
        <?php
    }

    private function render_recent_orders($vendor_id = null, $limit = 10) {
        global $wpdb;
        $sql = "SELECT o.*, v.store_name, u.display_name AS customer_name 
                FROM {$wpdb->prefix}petsgo_orders o 
                LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id = v.id
                LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID";
        if ($vendor_id) {
            $sql .= $wpdb->prepare(" WHERE o.vendor_id = %d", $vendor_id);
        }
        $sql .= " ORDER BY o.created_at DESC LIMIT $limit";
        $orders = $wpdb->get_results($sql);
        if (!$orders) { echo '<p>No hay pedidos aún.</p>'; return; }
        ?>
        <table class="petsgo-table">
            <thead><tr><th>#</th><th>Cliente</th><th>Tienda</th><th>Total</th><th>Comisión</th><th>Estado</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><?php echo $o->id; ?></td>
                <td><?php echo esc_html($o->customer_name ?? 'N/A'); ?></td>
                <td><?php echo esc_html($o->store_name ?? 'N/A'); ?></td>
                <td><?php echo $this->fmt($o->total_amount); ?></td>
                <td><?php echo $this->fmt($o->petsgo_commission); ?></td>
                <td><span class="petsgo-badge <?php echo esc_attr($o->status); ?>"><?php echo ucfirst(str_replace('_',' ',$o->status)); ?></span></td>
                <td><?php echo esc_html($o->created_at); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function fmt($n) {
        return '$' . number_format((float)$n, 0, ',', '.');
    }

    // ============================================================
    // 2. PRODUCTOS — lista AJAX + filtro por vendor
    // ============================================================
    public function page_products() {
        global $wpdb;
        $is_admin = $this->is_admin();
        $vid = $this->get_my_vendor_id();
        $vendors = $is_admin ? $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name") : [];
        $categories = ['Alimento','Juguetes','Salud','Accesorios','Higiene','Ropa','Camas','Transporte'];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🛒 Productos (<span id="pg-total">...</span>)</h1>
            <?php if (!$is_admin && $vid): ?>
                <div class="petsgo-info-bar">📌 Estás viendo solo los productos de tu tienda.</div>
            <?php endif; ?>

            <div class="petsgo-search-bar">
                <input type="text" id="pg-search" placeholder="🔍 Buscar por nombre..." autocomplete="off">
                <select id="pg-filter-cat">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $c): ?><option><?php echo $c; ?></option><?php endforeach; ?>
                </select>
                <?php if ($is_admin): ?>
                <select id="pg-filter-vendor">
                    <option value="">Todas las tiendas</option>
                    <?php foreach ($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <span class="petsgo-loader" id="pg-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-product-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nuevo Producto</a>
            </div>

            <table class="petsgo-table">
                <thead><tr><th style="width:50px">Foto</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Categoría</th><?php if($is_admin): ?><th>Tienda</th><?php endif; ?><th style="width:140px">Acciones</th></tr></thead>
                <tbody id="pg-products-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody>
            </table>
        </div>
        <script>
        jQuery(function($){
            var timer;
            function load(){
                $('#pg-loader').addClass('active');
                var d = {search:$('#pg-search').val(), category:$('#pg-filter-cat').val()};
                <?php if($is_admin): ?>d.vendor_id=$('#pg-filter-vendor').val();<?php endif; ?>
                PG.post('petsgo_search_products', d, function(r){
                    $('#pg-loader').removeClass('active');
                    if(!r.success) return;
                    $('#pg-total').text(r.data.length);
                    var rows='';
                    if(!r.data.length){ rows='<tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Sin resultados.</td></tr>'; }
                    $.each(r.data, function(i,p){
                        var thumb = p.image_url ? '<img src="'+p.image_url+'" class="petsgo-thumb">' : '<span style="display:inline-block;width:50px;height:50px;background:#f0f0f0;border-radius:6px;text-align:center;line-height:50px;color:#bbb;">📷</span>';
                        var sc = p.stock<5?'petsgo-stock-low':'petsgo-stock-ok';
                        rows+='<tr><td>'+thumb+'</td>';
                        rows+='<td><strong>'+PG.esc(p.product_name)+'</strong>';
                        if(p.description) rows+='<br><small style="color:#888;">'+PG.esc(p.description.substring(0,60))+(p.description.length>60?'...':'')+'</small>';
                        rows+='</td><td>'+PG.money(p.price)+'</td><td class="'+sc+'">'+p.stock+'</td>';
                        rows+='<td>'+PG.esc(p.category||'—')+'</td>';
                        <?php if($is_admin): ?>rows+='<td>'+PG.esc(p.store_name||'—')+'</td>';<?php endif; ?>
                        rows+='<td><a href="'+PG.adminUrl+'?page=petsgo-product-form&id='+p.id+'" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm">✏️</a> ';
                        <?php if($is_admin): ?>rows+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pg-del" data-id="'+p.id+'">🗑️</button>';<?php endif; ?>
                        rows+='</td></tr>';
                    });
                    $('#pg-products-body').html(rows);
                });
            }
            $('#pg-search').on('input',function(){clearTimeout(timer);timer=setTimeout(load,300);});
            $('#pg-filter-cat<?php if($is_admin): ?>, #pg-filter-vendor<?php endif; ?>').on('change',load);
            $(document).on('click','.pg-del',function(){
                if(!confirm('¿Eliminar este producto?')) return;
                PG.post('petsgo_delete_product',{id:$(this).data('id')},function(r){if(r.success)load();else alert(r.data);});
            });
            load();
        });
        </script>
        <?php
    }

    // ============================================================
    // 2b. PRODUCTO FORM (crear/editar) — vendor field locked
    // ============================================================
    public function page_product_form() {
        global $wpdb;
        $is_admin = $this->is_admin();
        $vid = $this->get_my_vendor_id();
        $pid = intval($_GET['id'] ?? 0);
        $product = $pid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_inventory WHERE id=%d", $pid)) : null;

        // Vendor solo puede editar sus propios productos
        if (!$is_admin && $product && (int)$product->vendor_id !== $vid) {
            echo '<div class="wrap"><h1>⛔ Sin acceso</h1><p>Este producto no pertenece a tu tienda.</p></div>';
            return;
        }

        $vendors = $is_admin ? $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name") : [];
        $categories = ['Alimento','Juguetes','Salud','Accesorios','Higiene','Ropa','Camas','Transporte'];
        $images = ['','',''];
        if ($product) {
            $images[0] = $product->image_id ? wp_get_attachment_url($product->image_id) : '';
            $images[1] = $product->image_id_2 ? wp_get_attachment_url($product->image_id_2) : '';
            $images[2] = $product->image_id_3 ? wp_get_attachment_url($product->image_id_3) : '';
        }
        $title = $pid ? 'Editar Producto #'.$pid : 'Nuevo Producto';
        // Si es vendor, obtener nombre de tienda
        $vendor_name = '';
        if (!$is_admin && $vid) {
            $vendor_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d", $vid));
        }
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🛒 <?php echo $title; ?></h1>
            <a href="<?php echo admin_url('admin.php?page=petsgo-products'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" style="margin-bottom:16px;display:inline-block;">← Volver</a>

            <form id="petsgo-product-form" novalidate>
                <input type="hidden" id="pf-id" value="<?php echo $pid; ?>">
                <?php wp_nonce_field('petsgo_ajax', 'pf-nonce'); ?>
                <div class="petsgo-form-grid">
                    <div>
                        <div class="petsgo-form-section">
                            <h3>📝 Información del Producto</h3>
                            <div class="petsgo-field" id="field-name">
                                <label>Nombre del producto *</label>
                                <input type="text" id="pf-name" maxlength="255" placeholder="Ej: Royal Canin Adulto 15kg" value="<?php echo esc_attr($product->product_name ?? ''); ?>">
                                <div class="field-hint">Máx. 255 caracteres.</div>
                                <div class="field-error">Nombre obligatorio (mín. 3 caracteres).</div>
                            </div>
                            <div class="petsgo-field" id="field-desc">
                                <label>Descripción *</label>
                                <textarea id="pf-desc" maxlength="1000" rows="4" placeholder="Características, ingredientes, beneficios..."><?php echo esc_textarea($product->description ?? ''); ?></textarea>
                                <div class="field-hint">Máx. 1000 caracteres.</div>
                                <div class="field-error">Descripción obligatoria (mín. 10 caracteres).</div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="petsgo-field" id="field-price">
                                    <label>Precio (CLP) *</label>
                                    <input type="number" id="pf-price" min="1" max="99999999" step="1" placeholder="25990" value="<?php echo esc_attr($product->price ?? ''); ?>">
                                    <div class="field-error">Precio debe ser mayor a 0.</div>
                                </div>
                                <div class="petsgo-field" id="field-stock">
                                    <label>Stock (unidades) *</label>
                                    <input type="number" id="pf-stock" min="0" max="999999" step="1" placeholder="50" value="<?php echo esc_attr($product->stock ?? ''); ?>">
                                    <div class="field-error">Stock debe ser 0 o mayor.</div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="petsgo-field" id="field-category">
                                    <label>Categoría *</label>
                                    <select id="pf-category">
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($categories as $c): ?><option <?php selected(($product->category ?? ''), $c); ?>><?php echo $c; ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="field-error">Selecciona una categoría.</div>
                                </div>
                                <div class="petsgo-field" id="field-vendor">
                                    <label>Tienda *</label>
                                    <?php if ($is_admin): ?>
                                    <select id="pf-vendor">
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($vendors as $v): ?><option value="<?php echo $v->id; ?>" <?php selected(($product->vendor_id ?? ''), $v->id); ?>><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                                    </select>
                                    <?php else: ?>
                                    <input type="text" value="<?php echo esc_attr($vendor_name); ?>" readonly>
                                    <input type="hidden" id="pf-vendor" value="<?php echo $vid; ?>">
                                    <div class="field-hint">Tu tienda se asigna automáticamente.</div>
                                    <?php endif; ?>
                                    <div class="field-error">Selecciona una tienda.</div>
                                </div>
                            </div>
                        </div>
                        <!-- FOTOS -->
                        <div class="petsgo-form-section" style="margin-top:20px;">
                            <h3>📸 Fotos del Producto (máx. 3)</h3>
                            <div class="petsgo-img-upload">
                                <?php for ($i = 0; $i < 3; $i++):
                                    $label = $i === 0 ? 'Foto Principal *' : 'Foto '.($i+1).' (opcional)';
                                    $has = !empty($images[$i]);
                                ?>
                                <div class="petsgo-img-slot <?php echo $has?'has-image':''; ?>" data-index="<?php echo $i; ?>">
                                    <?php if ($has): ?><img src="<?php echo esc_url($images[$i]); ?>"><?php endif; ?>
                                    <span class="slot-label"><span class="dashicons dashicons-cloud-upload"></span><?php echo $label; ?></span>
                                    <button type="button" class="remove-img">×</button>
                                    <input type="hidden" class="img-id-input" id="pf-image-<?php echo $i; ?>" value="<?php
                                        if ($i===0) echo esc_attr($product->image_id ?? '');
                                        elseif ($i===1) echo esc_attr($product->image_id_2 ?? '');
                                        else echo esc_attr($product->image_id_3 ?? '');
                                    ?>">
                                </div>
                                <?php endfor; ?>
                            </div>
                            <div class="petsgo-img-specs">
                                <strong>📐 Especificaciones:</strong> JPG / PNG / WebP — Recomendado 800×800 px — Mín: 400×400 — Máx: 2000×2000 — Peso máx: 2 MB
                            </div>
                            <div class="field-error" id="img-error" style="display:none;">La foto principal es obligatoria.</div>
                        </div>
                        <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                            <button type="submit" class="petsgo-btn petsgo-btn-primary" style="font-size:15px;padding:10px 30px;"><?php echo $pid ? '💾 Guardar Cambios' : '✅ Crear Producto'; ?></button>
                            <a href="<?php echo admin_url('admin.php?page=petsgo-products'); ?>" class="petsgo-btn" style="background:#e2e3e5;color:#333;">Cancelar</a>
                            <span class="petsgo-loader" id="pf-loader"><span class="spinner is-active" style="float:none;margin:0;"></span> Guardando...</span>
                            <div id="pf-message" style="display:none;"></div>
                        </div>
                    </div>
                    <div>
                        <div class="petsgo-form-section" style="position:sticky;top:40px;">
                            <h3>👁️ Vista Previa</h3>
                            <div class="petsgo-preview-card">
                                <div class="preview-imgs" id="preview-imgs"><div class="no-img">📷</div></div>
                                <div class="preview-body">
                                    <span class="preview-cat" id="preview-cat">Categoría</span>
                                    <h4 id="preview-name">Nombre del producto</h4>
                                    <div class="preview-price" id="preview-price">$0</div>
                                    <div class="preview-desc" id="preview-desc">Descripción...</div>
                                    <div class="preview-stock" id="preview-stock">Stock: —</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(function($){
            // Media uploader
            $('.petsgo-img-slot').on('click',function(e){
                if($(e.target).hasClass('remove-img'))return;
                var slot=$(this);
                var frame=wp.media({title:'Seleccionar imagen',button:{text:'Usar imagen'},multiple:false,library:{type:['image/jpeg','image/png','image/webp']}});
                frame.on('select',function(){
                    var a=frame.state().get('selection').first().toJSON();
                    if(a.width<400||a.height<400){alert('⚠️ Imagen muy pequeña. Mín 400×400px. Actual: '+a.width+'×'+a.height);return;}
                    if(a.width>2000||a.height>2000){alert('⚠️ Imagen muy grande. Máx 2000×2000px.');return;}
                    if(a.filesizeInBytes>2*1024*1024){alert('⚠️ Peso > 2MB');return;}
                    var url=a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url;
                    slot.find('img').remove();slot.prepend('<img src="'+url+'">');
                    slot.addClass('has-image');slot.find('.img-id-input').val(a.id);upd();
                });
                frame.open();
            });
            $('.remove-img').on('click',function(e){e.stopPropagation();var s=$(this).closest('.petsgo-img-slot');s.find('img').remove();s.removeClass('has-image');s.find('.img-id-input').val('');upd();});

            function upd(){
                $('#preview-name').text($('#pf-name').val()||'Nombre');
                $('#preview-price').text(PG.money($('#pf-price').val()));
                $('#preview-cat').text($('#pf-category').val()||'Categoría');
                var d=$('#pf-desc').val()||'Descripción...';$('#preview-desc').text(d.length>120?d.substring(0,120)+'...':d);
                var s=$('#pf-stock').val();
                if(s===''||s===undefined){$('#preview-stock').html('Stock: —');}else{var n=parseInt(s);$('#preview-stock').html('Stock: <strong style="color:'+(n<5?'#dc3545':'#28a745')+'">'+n+' uds</strong>');}
                var imgs='';$('.petsgo-img-slot').each(function(){var im=$(this).find('img');if(im.length)imgs+='<img src="'+im.attr('src')+'">';});
                $('#preview-imgs').html(imgs||'<div class="no-img">📷</div>');
            }
            $('#pf-name,#pf-price,#pf-stock,#pf-category,#pf-desc').on('input change',upd);upd();

            function validate(){
                var ok=true;
                if($.trim($('#pf-name').val()).length<3){$('#field-name').addClass('has-error');ok=false;}else{$('#field-name').removeClass('has-error');}
                if($.trim($('#pf-desc').val()).length<10){$('#field-desc').addClass('has-error');ok=false;}else{$('#field-desc').removeClass('has-error');}
                if(!parseFloat($('#pf-price').val())||parseFloat($('#pf-price').val())<=0){$('#field-price').addClass('has-error');ok=false;}else{$('#field-price').removeClass('has-error');}
                var st=$('#pf-stock').val();if(st===''||parseInt(st)<0){$('#field-stock').addClass('has-error');ok=false;}else{$('#field-stock').removeClass('has-error');}
                if(!$('#pf-category').val()){$('#field-category').addClass('has-error');ok=false;}else{$('#field-category').removeClass('has-error');}
                if(!$('#pf-vendor').val()){$('#field-vendor').addClass('has-error');ok=false;}else{$('#field-vendor').removeClass('has-error');}
                if(!$('#pf-image-0').val()){$('#img-error').show();ok=false;}else{$('#img-error').hide();}
                return ok;
            }
            $('.petsgo-field input,.petsgo-field select,.petsgo-field textarea').on('input change',function(){$(this).closest('.petsgo-field').removeClass('has-error');});

            $('#petsgo-product-form').on('submit',function(e){
                e.preventDefault();if(!validate())return;
                $('#pf-loader').addClass('active');$('#pf-message').hide();
                PG.post('petsgo_save_product',{
                    id:$('#pf-id').val(),product_name:$.trim($('#pf-name').val()),description:$.trim($('#pf-desc').val()),
                    price:$('#pf-price').val(),stock:$('#pf-stock').val(),category:$('#pf-category').val(),vendor_id:$('#pf-vendor').val(),
                    image_id:$('#pf-image-0').val(),image_id_2:$('#pf-image-1').val(),image_id_3:$('#pf-image-2').val()
                },function(r){
                    $('#pf-loader').removeClass('active');
                    if(r.success){
                        $('#pf-message').html('<div class="notice notice-success" style="padding:10px"><p>✅ '+r.data.message+'</p></div>').show();
                        if(!$('#pf-id').val()&&r.data.id){$('#pf-id').val(r.data.id);history.replaceState(null,'',PG.adminUrl+'?page=petsgo-product-form&id='+r.data.id);}
                    }else{
                        $('#pf-message').html('<div class="notice notice-error" style="padding:10px"><p>❌ '+(r.data||'Error')+'</p></div>').show();
                    }
                });
            });
        });
        </script>
        <?php
    }

    // ============================================================
    // 3. TIENDAS / VENDORS — solo admin, con AJAX + form
    // ============================================================
    public function page_vendors() {
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🏪 Tiendas (<span id="pv-total">...</span>)</h1>
            <div class="petsgo-search-bar">
                <input type="text" id="pv-search" placeholder="🔍 Buscar tienda..." autocomplete="off">
                <select id="pv-filter-status"><option value="">Todos los estados</option><option value="active">Activas</option><option value="pending">Pendientes</option><option value="inactive">Inactivas</option></select>
                <span class="petsgo-loader" id="pv-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-vendor-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nueva Tienda</a>
            </div>
            <table class="petsgo-table"><thead><tr><th>ID</th><th>Tienda</th><th>RUT</th><th>Email</th><th>Teléfono</th><th>Comisión</th><th>Productos</th><th>Pedidos</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody id="pv-body"><tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var t;
            function load(){
                $('#pv-loader').addClass('active');
                PG.post('petsgo_search_vendors',{search:$('#pv-search').val(),status:$('#pv-filter-status').val()},function(r){
                    $('#pv-loader').removeClass('active');if(!r.success)return;
                    $('#pv-total').text(r.data.length);var rows='';
                    if(!r.data.length){rows='<tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Sin resultados.</td></tr>';}
                    $.each(r.data,function(i,v){
                        rows+='<tr><td>'+v.id+'</td><td><strong>'+PG.esc(v.store_name)+'</strong></td><td>'+PG.esc(v.rut)+'</td>';
                        rows+='<td>'+PG.esc(v.email)+'</td><td>'+PG.esc(v.phone)+'</td><td>'+v.sales_commission+'%</td>';
                        rows+='<td>'+v.total_products+'</td><td>'+v.total_orders+'</td>';
                        rows+='<td>'+PG.badge(v.status)+'</td>';
                        rows+='<td><a href="'+PG.adminUrl+'?page=petsgo-vendor-form&id='+v.id+'" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm" title="Editar">✏️</a> ';
                        rows+='<a href="'+PG.adminUrl+'?page=petsgo-invoice-config&vendor_id='+v.id+'" class="petsgo-btn petsgo-btn-sm" style="background:#00A8E8;color:#fff;" title="Config Boleta">🧾</a> ';
                        rows+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pv-del" data-id="'+v.id+'">🗑️</button></td></tr>';
                    });
                    $('#pv-body').html(rows);
                });
            }
            $('#pv-search').on('input',function(){clearTimeout(t);t=setTimeout(load,300);});
            $('#pv-filter-status').on('change',load);
            $(document).on('click','.pv-del',function(){if(!confirm('¿Eliminar tienda?'))return;PG.post('petsgo_delete_vendor',{id:$(this).data('id')},function(r){if(r.success)load();else alert(r.data);});});
            load();
        });
        </script>
        <?php
    }

    public function page_vendor_form() {
        global $wpdb;
        $vid = intval($_GET['id'] ?? 0);
        $vendor = $vid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d", $vid)) : null;
        $users = $wpdb->get_results("SELECT ID, user_login, display_name FROM {$wpdb->users} ORDER BY display_name");
        // Chile regions/comunas
        $regiones = [
            'Arica y Parinacota'=>['Arica','Camarones','General Lagos','Putre'],
            'Tarapacá'=>['Alto Hospicio','Camina','Colchane','Huara','Iquique','Pica','Pozo Almonte'],
            'Antofagasta'=>['Antofagasta','Calama','María Elena','Mejillones','Ollagüe','San Pedro de Atacama','Sierra Gorda','Taltal','Tocopilla'],
            'Atacama'=>['Caldera','Chañaral','Copiapó','Diego de Almagro','Freirina','Huasco','Tierra Amarilla','Vallenar'],
            'Coquimbo'=>['Andacollo','Canela','Combarbalá','Coquimbo','Illapel','La Higuera','La Serena','Los Vilos','Monte Patria','Ovalle','Paihuano','Punitaqui','Río Hurtado','Salamanca','Vicuña'],
            'Valparaíso'=>['Algarrobo','Cabildo','Calera','Cartagena','Casablanca','Catemu','Concón','El Quisco','El Tabo','Hijuelas','Isla de Pascua','Juan Fernández','La Cruz','La Ligua','Limache','Llaillay','Los Andes','Nogales','Olmué','Panquehue','Papudo','Petorca','Puchuncaví','Putaendo','Quillota','Quilpué','Quintero','Rinconada','San Antonio','San Esteban','San Felipe','Santa María','Santo Domingo','Valparaíso','Villa Alemana','Viña del Mar','Zapallar'],
            'Metropolitana'=>['Alhué','Buin','Calera de Tango','Cerrillos','Cerro Navia','Colina','Conchalí','Curacaví','El Bosque','El Monte','Estación Central','Huechuraba','Independencia','Isla de Maipo','La Cisterna','La Florida','La Granja','La Pintana','La Reina','Lampa','Las Condes','Lo Barnechea','Lo Espejo','Lo Prado','Macul','Maipú','María Pinto','Melipilla','Ñuñoa','Padre Hurtado','Paine','Pedro Aguirre Cerda','Peñaflor','Peñalolén','Pirque','Providencia','Pudahuel','Puente Alto','Quilicura','Quinta Normal','Recoleta','Renca','San Bernardo','San Joaquín','San José de Maipo','San Miguel','San Pedro','San Ramón','Santiago','Talagante','Tiltil','Vitacura'],
            'O\'Higgins'=>['Chépica','Chimbarongo','Codegua','Coinco','Coltauco','Doñihue','Graneros','La Estrella','Las Cabras','Litueche','Lolol','Machalí','Malloa','Marchihue','Mostazal','Nancagua','Navidad','Olivar','Palmilla','Paredones','Peralillo','Peumo','Pichidegua','Pichilemu','Placilla','Pumanque','Quinta de Tilcoco','Rancagua','Rengo','Requínoa','San Fernando','San Vicente','Santa Cruz'],
            'Maule'=>['Cauquenes','Chanco','Colbún','Constitución','Curepto','Curicó','Empedrado','Hualañé','Licantén','Linares','Longaví','Maule','Molina','Parral','Pelarco','Pelluhue','Pencahue','Rauco','Retiro','Río Claro','Romeral','Sagrada Familia','San Clemente','San Javier','San Rafael','Talca','Teno','Vichuquén','Villa Alegre','Yerbas Buenas'],
            'Ñuble'=>['Bulnes','Chillán','Chillán Viejo','Cobquecura','Coelemu','Coihueco','El Carmen','Ninhue','Ñiquén','Pemuco','Pinto','Portezuelo','Quillón','Quirihue','Ránquil','San Carlos','San Fabián','San Ignacio','San Nicolás','Treguaco','Yungay'],
            'Biobío'=>['Alto Biobío','Antuco','Arauco','Cabrero','Cañete','Chiguayante','Concepción','Contulmo','Coronel','Curanilahue','Florida','Hualpén','Hualqui','Laja','Lebu','Los Álamos','Los Ángeles','Lota','Mulchén','Nacimiento','Negrete','Penco','Quilaco','Quilleco','San Pedro de la Paz','San Rosendo','Santa Bárbara','Santa Juana','Talcahuano','Tirúa','Tomé','Tucapel','Yumbel'],
            'Araucanía'=>['Angol','Carahue','Cholchol','Collipulli','Cunco','Curacautín','Curarrehue','Ercilla','Freire','Galvarino','Gorbea','Lautaro','Loncoche','Lonquimay','Los Sauces','Lumaco','Melipeuco','Nueva Imperial','Padre Las Casas','Perquenco','Pitrufquén','Pucón','Purén','Renaico','Saavedra','Temuco','Teodoro Schmidt','Toltén','Traiguén','Victoria','Vilcún','Villarrica'],
            'Los Ríos'=>['Corral','Futrono','La Unión','Lago Ranco','Lanco','Los Lagos','Máfil','Mariquina','Paillaco','Panguipulli','Río Bueno','Valdivia'],
            'Los Lagos'=>['Ancud','Calbuco','Castro','Chaitén','Chonchi','Cochamó','Curaco de Vélez','Dalcahue','Fresia','Frutillar','Futaleufú','Hualaihué','Llanquihue','Los Muermos','Maullín','Osorno','Palena','Puerto Montt','Puerto Octay','Puerto Varas','Puqueldón','Purranque','Puyehue','Queilén','Quellón','Quemchi','Quinchao','Río Negro','San Juan de la Costa','San Pablo'],
            'Aysén'=>['Aysén','Chile Chico','Cisnes','Cochrane','Coyhaique','Guaitecas','Lago Verde','O\'Higgins','Río Ibáñez','Tortel'],
            'Magallanes'=>['Antártica','Cabo de Hornos','Laguna Blanca','Natales','Porvenir','Primavera','Punta Arenas','Río Verde','San Gregorio','Timaukel','Torres del Paine']
        ];
        $regiones_json = json_encode($regiones, JSON_UNESCAPED_UNICODE);
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🏪 <?php echo $vid ? 'Editar Tienda #'.$vid : 'Nueva Tienda'; ?></h1>
            <div style="display:flex;gap:8px;margin-bottom:16px;">
                <a href="<?php echo admin_url('admin.php?page=petsgo-vendors'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm">← Volver</a>
                <?php if ($vid): ?>
                <a href="<?php echo admin_url('admin.php?page=petsgo-invoice-config&vendor_id='.$vid); ?>" class="petsgo-btn petsgo-btn-sm" style="background:#00A8E8;color:#fff;">🧾 Config Boleta</a>
                <?php endif; ?>
            </div>
            <form id="vendor-form" novalidate>
                <input type="hidden" id="vf-id" value="<?php echo $vid; ?>">
                <div class="petsgo-form-section" style="max-width:700px;">
                    <h3>📋 Datos de la Tienda</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="petsgo-field" id="vf-f-name"><label>Nombre tienda *</label><input type="text" id="vf-name" value="<?php echo esc_attr($vendor->store_name ?? ''); ?>" maxlength="255"><div class="field-error">Obligatorio.</div></div>
                        <div class="petsgo-field" id="vf-f-rut"><label>RUT *</label><input type="text" id="vf-rut" value="<?php echo esc_attr($vendor->rut ?? ''); ?>" maxlength="20" placeholder="12.345.678-9"><div class="field-error">Obligatorio.</div></div>
                        <div class="petsgo-field" id="vf-f-email"><label>Email *</label><input type="email" id="vf-email" value="<?php echo esc_attr($vendor->email ?? ''); ?>"><div class="field-error">Email válido obligatorio.</div></div>
                        <div class="petsgo-field" id="vf-f-phone"><label>Teléfono</label><input type="text" id="vf-phone" value="<?php echo esc_attr($vendor->phone ?? ''); ?>"></div>
                    </div>
                    <h3 style="margin-top:24px;">📍 Ubicación</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="petsgo-field"><label>Región</label>
                            <select id="vf-region"><option value="">— Seleccionar Región —</option>
                            <?php foreach (array_keys($regiones) as $r): ?>
                            <option value="<?php echo esc_attr($r); ?>" <?php selected(($vendor->region ?? ''), $r); ?>><?php echo esc_html($r); ?></option>
                            <?php endforeach; ?></select></div>
                        <div class="petsgo-field"><label>Comuna</label>
                            <select id="vf-comuna"><option value="">— Seleccionar Comuna —</option></select></div>
                        <div class="petsgo-field" style="grid-column:1/3;"><label>Dirección (calle, número, depto.)</label><input type="text" id="vf-address" value="<?php echo esc_attr($vendor->address ?? ''); ?>" placeholder="Av. Libertador 1234, Depto 5B"></div>
                    </div>
                    <h3 style="margin-top:24px;">⚙️ Configuración</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="petsgo-field" id="vf-f-user"><label>Usuario WP asociado *</label>
                            <select id="vf-user"><option value="">— Seleccionar —</option>
                            <?php foreach ($users as $u): ?><option value="<?php echo $u->ID; ?>" <?php selected(($vendor->user_id ?? ''), $u->ID); ?>><?php echo esc_html($u->display_name . ' (' . $u->user_login . ')'); ?></option><?php endforeach; ?>
                            </select><div class="field-error">Selecciona un usuario.</div></div>
                        <div class="petsgo-field"><label>Comisión venta (%)</label><input type="number" id="vf-commission" value="<?php echo esc_attr($vendor->sales_commission ?? '10'); ?>" min="0" max="100" step="0.1"></div>
                        <div class="petsgo-field"><label>Plan</label><select id="vf-plan">
                            <?php $plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_subscriptions ORDER BY monthly_price"); foreach($plans as $pl): ?>
                            <option value="<?php echo $pl->id; ?>" <?php selected(($vendor->plan_id ?? 1), $pl->id); ?>><?php echo esc_html($pl->plan_name); ?></option>
                            <?php endforeach; ?></select></div>
                        <div class="petsgo-field"><label>Estado</label><select id="vf-status">
                            <option value="active" <?php selected(($vendor->status ?? 'pending'), 'active'); ?>>Activa</option>
                            <option value="pending" <?php selected(($vendor->status ?? 'pending'), 'pending'); ?>>Pendiente</option>
                            <option value="inactive" <?php selected(($vendor->status ?? ''), 'inactive'); ?>>Inactiva</option>
                        </select></div>
                    </div>
                    <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="petsgo-btn petsgo-btn-primary"><?php echo $vid ? '💾 Guardar' : '✅ Crear'; ?></button>
                        <span class="petsgo-loader" id="vf-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                        <div id="vf-msg" style="display:none;"></div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(function($){
            var regiones=<?php echo $regiones_json; ?>;
            // Populate comunas based on region
            function loadComunas(region,selected){
                var $c=$('#vf-comuna');$c.html('<option value="">— Seleccionar Comuna —</option>');
                if(region && regiones[region]){
                    $.each(regiones[region],function(i,com){
                        $c.append('<option value="'+com+'"'+(com===selected?' selected':'')+'>'+com+'</option>');
                    });
                }
            }
            $('#vf-region').on('change',function(){loadComunas($(this).val(),'');});
            // Load saved comuna on page load
            <?php if($vendor && !empty($vendor->region)): ?>
            loadComunas('<?php echo esc_js($vendor->region); ?>','<?php echo esc_js($vendor->comuna ?? ''); ?>');
            <?php endif; ?>

            $('#vendor-form').on('submit',function(e){
                e.preventDefault();var ok=true;
                if(!$.trim($('#vf-name').val())){$('#vf-f-name').addClass('has-error');ok=false;}else{$('#vf-f-name').removeClass('has-error');}
                if(!$.trim($('#vf-rut').val())){$('#vf-f-rut').addClass('has-error');ok=false;}else{$('#vf-f-rut').removeClass('has-error');}
                if(!$('#vf-email').val()||$('#vf-email').val().indexOf('@')<1){$('#vf-f-email').addClass('has-error');ok=false;}else{$('#vf-f-email').removeClass('has-error');}
                if(!$('#vf-user').val()){$('#vf-f-user').addClass('has-error');ok=false;}else{$('#vf-f-user').removeClass('has-error');}
                if(!ok)return;
                $('#vf-loader').addClass('active');$('#vf-msg').hide();
                PG.post('petsgo_save_vendor',{
                    id:$('#vf-id').val(),store_name:$('#vf-name').val(),rut:$('#vf-rut').val(),email:$('#vf-email').val(),
                    phone:$('#vf-phone').val(),region:$('#vf-region').val(),comuna:$('#vf-comuna').val(),address:$('#vf-address').val(),
                    user_id:$('#vf-user').val(),sales_commission:$('#vf-commission').val(),plan_id:$('#vf-plan').val(),status:$('#vf-status').val()
                },function(r){
                    $('#vf-loader').removeClass('active');
                    var cls=r.success?'notice-success':'notice-error';
                    $('#vf-msg').html('<div class="notice '+cls+'" style="padding:10px"><p>'+(r.success?'✅ '+r.data.message:'❌ '+r.data)+'</p></div>').show();
                    if(r.success&&!$('#vf-id').val()&&r.data.id){$('#vf-id').val(r.data.id);history.replaceState(null,'',PG.adminUrl+'?page=petsgo-vendor-form&id='+r.data.id);}
                });
            });
            $('.petsgo-field input,.petsgo-field select').on('input change',function(){$(this).closest('.petsgo-field').removeClass('has-error');});
        });
        </script>
        <?php
    }

    // ============================================================
    // 4. PEDIDOS — vendor ve solo los suyos, admin ve todo
    // ============================================================
    public function page_orders() {
        global $wpdb;
        $is_admin = $this->is_admin();
        $vid = $this->get_my_vendor_id();
        $vendors = $is_admin ? $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name") : [];
        $statuses = ['pending','processing','in_transit','delivered','cancelled'];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📦 Pedidos (<span id="po-total">...</span>)</h1>
            <?php if (!$is_admin && $vid): ?><div class="petsgo-info-bar">📌 Estás viendo solo los pedidos de tu tienda.</div><?php endif; ?>
            <div class="petsgo-search-bar">
                <input type="text" id="po-search" placeholder="🔍 Buscar por cliente..." autocomplete="off">
                <select id="po-filter-status"><option value="">Todos los estados</option>
                    <?php foreach ($statuses as $s): ?><option value="<?php echo $s; ?>"><?php echo ucfirst(str_replace('_',' ',$s)); ?></option><?php endforeach; ?>
                </select>
                <?php if ($is_admin): ?>
                <select id="po-filter-vendor"><option value="">Todas las tiendas</option>
                    <?php foreach ($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <span class="petsgo-loader" id="po-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead><tr><th>#</th><th>Cliente</th><?php if($is_admin): ?><th>Tienda</th><?php endif; ?><th>Total</th><th>Comisión</th><th>Delivery</th><th>Rider</th><th>Estado</th><th>Fecha</th><?php if($is_admin): ?><th>Cambiar</th><?php endif; ?></tr></thead>
            <tbody id="po-body"><tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var t;var statuses=<?php echo json_encode($statuses); ?>;
            function load(){
                $('#po-loader').addClass('active');
                var d={search:$('#po-search').val(),status:$('#po-filter-status').val()};
                <?php if($is_admin): ?>d.vendor_id=$('#po-filter-vendor').val();<?php endif; ?>
                PG.post('petsgo_search_orders',d,function(r){
                    $('#po-loader').removeClass('active');if(!r.success)return;
                    $('#po-total').text(r.data.length);var rows='';
                    if(!r.data.length){rows='<tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Sin pedidos.</td></tr>';}
                    $.each(r.data,function(i,o){
                        rows+='<tr><td>'+o.id+'</td><td>'+PG.esc(o.customer_name||'N/A')+'</td>';
                        <?php if($is_admin): ?>rows+='<td>'+PG.esc(o.store_name||'N/A')+'</td>';<?php endif; ?>
                        rows+='<td>'+PG.money(o.total_amount)+'</td><td>'+PG.money(o.petsgo_commission)+'</td><td>'+PG.money(o.delivery_fee)+'</td>';
                        rows+='<td>'+PG.esc(o.rider_name||'Sin asignar')+'</td>';
                        rows+='<td>'+PG.badge(o.status)+'</td><td>'+PG.esc(o.created_at)+'</td>';
                        <?php if($is_admin): ?>
                        rows+='<td><select class="po-status-sel" data-id="'+o.id+'" style="font-size:12px;">';
                        $.each(statuses,function(j,s){rows+='<option value="'+s+'"'+(o.status===s?' selected':'')+'>'+s.replace(/_/g,' ')+'</option>';});
                        rows+='</select> <button class="petsgo-btn petsgo-btn-sm petsgo-btn-success po-status-btn" data-id="'+o.id+'">✓</button>';
                        if(!o.has_invoice) rows+=' <button class="petsgo-btn petsgo-btn-sm petsgo-btn-primary po-gen-invoice" data-id="'+o.id+'" title="Generar Boleta">🧾</button>';
                        else rows+=' <a href="'+PG.adminUrl+'?page=petsgo-invoice-config&preview='+o.invoice_id+'" class="petsgo-btn petsgo-btn-sm" title="Ver Boleta" target="_blank">🧾✅</a>';
                        rows+='</td>';
                        <?php endif; ?>
                        rows+='</tr>';
                    });
                    $('#po-body').html(rows);
                });
            }
            $('#po-search').on('input',function(){clearTimeout(t);t=setTimeout(load,300);});
            $('#po-filter-status<?php if($is_admin): ?>, #po-filter-vendor<?php endif; ?>').on('change',load);
            $(document).on('click','.po-status-btn',function(){
                var id=$(this).data('id');var ns=$('.po-status-sel[data-id="'+id+'"]').val();
                PG.post('petsgo_update_order_status',{id:id,status:ns},function(r){if(r.success)load();else alert(r.data);});
            });
            $(document).on('click','.po-gen-invoice',function(){
                var btn=$(this);var id=btn.data('id');btn.prop('disabled',true).text('⏳');
                PG.post('petsgo_generate_invoice',{order_id:id},function(r){
                    if(r.success){alert('✅ '+r.data.message);load();}else{alert('❌ '+r.data);btn.prop('disabled',false).text('🧾');}
                });
            });
            load();
        });
        </script>
        <?php
    }

    // ============================================================
    // 5. USUARIOS — solo admin, AJAX
    // ============================================================
    public function page_users() {
        $roles = ['administrator'=>'Admin','petsgo_vendor'=>'Tienda','petsgo_rider'=>'Rider','subscriber'=>'Cliente','petsgo_support'=>'Soporte'];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>👥 Usuarios (<span id="pu-total">...</span>)</h1>
            <div class="petsgo-search-bar">
                <input type="text" id="pu-search" placeholder="🔍 Buscar usuario..." autocomplete="off">
                <select id="pu-filter-role"><option value="">Todos los roles</option>
                    <?php foreach ($roles as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                </select>
                <span class="petsgo-loader" id="pu-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-user-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nuevo Usuario</a>
            </div>
            <table class="petsgo-table"><thead><tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Tienda</th><th>Registrado</th><th>Acciones</th></tr></thead>
            <tbody id="pu-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var t;
            function load(){
                $('#pu-loader').addClass('active');
                PG.post('petsgo_search_users',{search:$('#pu-search').val(),role:$('#pu-filter-role').val()},function(r){
                    $('#pu-loader').removeClass('active');if(!r.success)return;
                    $('#pu-total').text(r.data.length);var rows='';
                    if(!r.data.length){rows='<tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Sin resultados.</td></tr>';}
                    $.each(r.data,function(i,u){
                        rows+='<tr><td>'+u.ID+'</td><td>'+PG.esc(u.user_login)+'</td><td>'+PG.esc(u.display_name)+'</td>';
                        rows+='<td>'+PG.esc(u.user_email)+'</td>';
                        rows+='<td><span class="petsgo-role-tag '+u.role_key+'">'+PG.esc(u.role_label)+'</span></td>';
                        rows+='<td>'+PG.esc(u.store_name||'—')+'</td>';
                        rows+='<td>'+PG.esc(u.user_registered)+'</td>';
                        rows+='<td><a href="'+PG.adminUrl+'?page=petsgo-user-form&id='+u.ID+'" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm">✏️</a> ';
                        if(u.ID!=1) rows+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pu-del" data-id="'+u.ID+'">🗑️</button>';
                        rows+='</td></tr>';
                    });
                    $('#pu-body').html(rows);
                });
            }
            $('#pu-search').on('input',function(){clearTimeout(t);t=setTimeout(load,300);});
            $('#pu-filter-role').on('change',load);
            $(document).on('click','.pu-del',function(){if(!confirm('¿Eliminar usuario?'))return;PG.post('petsgo_delete_user',{id:$(this).data('id')},function(r){if(r.success)load();else alert(r.data);});});
            load();
        });
        </script>
        <?php
    }

    public function page_user_form() {
        global $wpdb;
        $uid = intval($_GET['id'] ?? 0);
        $user = $uid ? get_userdata($uid) : null;
        $roles = ['subscriber'=>'Cliente','petsgo_vendor'=>'Tienda (Vendor)','petsgo_rider'=>'Delivery (Rider)','petsgo_support'=>'Soporte','administrator'=>'Administrador'];
        $current_role = $user ? (in_array('administrator',$user->roles)?'administrator':(in_array('petsgo_vendor',$user->roles)?'petsgo_vendor':(in_array('petsgo_rider',$user->roles)?'petsgo_rider':(in_array('petsgo_support',$user->roles)?'petsgo_support':'subscriber')))) : 'subscriber';
        ?>
        <div class="wrap petsgo-wrap">
            <h1>👤 <?php echo $uid ? 'Editar Usuario #'.$uid : 'Nuevo Usuario'; ?></h1>
            <a href="<?php echo admin_url('admin.php?page=petsgo-users'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" style="margin-bottom:16px;display:inline-block;">← Volver</a>
            <form id="user-form" novalidate>
                <input type="hidden" id="uf-id" value="<?php echo $uid; ?>">
                <div class="petsgo-form-section" style="max-width:600px;">
                    <h3>📋 Datos del Usuario</h3>
                    <div class="petsgo-field" id="uf-f-login"><label>Usuario *</label><input type="text" id="uf-login" value="<?php echo esc_attr($user->user_login ?? ''); ?>" <?php echo $uid?'readonly':''; ?> maxlength="60"><div class="field-error">Obligatorio.</div></div>
                    <div class="petsgo-field" id="uf-f-name"><label>Nombre / Display Name *</label><input type="text" id="uf-name" value="<?php echo esc_attr($user->display_name ?? ''); ?>"><div class="field-error">Obligatorio.</div></div>
                    <div class="petsgo-field" id="uf-f-email"><label>Email *</label><input type="email" id="uf-email" value="<?php echo esc_attr($user->user_email ?? ''); ?>"><div class="field-error">Email válido obligatorio.</div></div>
                    <div class="petsgo-field"><label>Contraseña <?php echo $uid?'(dejar vacío para no cambiar)':'*'; ?></label><input type="password" id="uf-pass" autocomplete="new-password"><div class="field-hint"><?php echo $uid?'':'Mín. 6 caracteres.'; ?></div></div>
                    <div class="petsgo-field" id="uf-f-role"><label>Rol *</label>
                        <select id="uf-role"><?php foreach ($roles as $k=>$v): ?><option value="<?php echo $k; ?>" <?php selected($current_role, $k); ?>><?php echo $v; ?></option><?php endforeach; ?></select>
                    </div>
                    <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="petsgo-btn petsgo-btn-primary"><?php echo $uid?'💾 Guardar':'✅ Crear'; ?></button>
                        <span class="petsgo-loader" id="uf-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                        <div id="uf-msg" style="display:none;"></div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(function($){
            $('#user-form').on('submit',function(e){
                e.preventDefault();var ok=true;
                if(!$.trim($('#uf-login').val())){$('#uf-f-login').addClass('has-error');ok=false;}else{$('#uf-f-login').removeClass('has-error');}
                if(!$.trim($('#uf-name').val())){$('#uf-f-name').addClass('has-error');ok=false;}else{$('#uf-f-name').removeClass('has-error');}
                if(!$('#uf-email').val()||$('#uf-email').val().indexOf('@')<1){$('#uf-f-email').addClass('has-error');ok=false;}else{$('#uf-f-email').removeClass('has-error');}
                if(!ok)return;
                $('#uf-loader').addClass('active');$('#uf-msg').hide();
                PG.post('petsgo_save_user',{
                    id:$('#uf-id').val(),user_login:$('#uf-login').val(),display_name:$('#uf-name').val(),
                    user_email:$('#uf-email').val(),password:$('#uf-pass').val(),role:$('#uf-role').val()
                },function(r){
                    $('#uf-loader').removeClass('active');
                    var cls=r.success?'notice-success':'notice-error';
                    $('#uf-msg').html('<div class="notice '+cls+'" style="padding:10px"><p>'+(r.success?'✅ '+r.data.message:'❌ '+r.data)+'</p></div>').show();
                    if(r.success&&!$('#uf-id').val()&&r.data.id){$('#uf-id').val(r.data.id);}
                });
            });
            $('.petsgo-field input,.petsgo-field select').on('input change',function(){$(this).closest('.petsgo-field').removeClass('has-error');});
        });
        </script>
        <?php
    }

    // ============================================================
    // 6. DELIVERY — admin ve todo, rider ve los suyos
    // ============================================================
    public function page_delivery() {
        global $wpdb;
        $is_admin = $this->is_admin();
        $riders = $is_admin ? $wpdb->get_results("SELECT u.ID, u.display_name FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON u.ID=m.user_id WHERE m.meta_key='{$wpdb->prefix}capabilities' AND m.meta_value LIKE '%petsgo_rider%'") : [];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🚴 Delivery (<span id="pd-total">...</span>)</h1>
            <?php if (!$is_admin): ?><div class="petsgo-info-bar">📌 Estás viendo solo tus entregas asignadas.</div><?php endif; ?>
            <div class="petsgo-search-bar">
                <select id="pd-filter-status"><option value="">Todos</option><option value="in_transit">En Tránsito</option><option value="delivered">Entregados</option><option value="pending">Pendientes</option></select>
                <?php if ($is_admin): ?>
                <select id="pd-filter-rider"><option value="">Todos los riders</option><option value="unassigned">Sin asignar</option>
                    <?php foreach ($riders as $r): ?><option value="<?php echo $r->ID; ?>"><?php echo esc_html($r->display_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <span class="petsgo-loader" id="pd-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead><tr><th>Pedido #</th><th>Cliente</th><th>Tienda</th><th>Total</th><th>Fee Delivery</th><th>Rider</th><th>Estado</th><th>Fecha</th><?php if($is_admin): ?><th>Asignar Rider</th><?php endif; ?></tr></thead>
            <tbody id="pd-body"><tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var riders=<?php echo json_encode($riders); ?>;
            function load(){
                $('#pd-loader').addClass('active');
                var d={status:$('#pd-filter-status').val()};
                <?php if($is_admin): ?>d.rider_id=$('#pd-filter-rider').val();<?php endif; ?>
                PG.post('petsgo_search_riders',d,function(r){
                    $('#pd-loader').removeClass('active');if(!r.success)return;
                    $('#pd-total').text(r.data.length);var rows='';
                    if(!r.data.length){rows='<tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Sin entregas.</td></tr>';}
                    $.each(r.data,function(i,o){
                        rows+='<tr><td>'+o.id+'</td><td>'+PG.esc(o.customer_name||'N/A')+'</td><td>'+PG.esc(o.store_name||'N/A')+'</td>';
                        rows+='<td>'+PG.money(o.total_amount)+'</td><td>'+PG.money(o.delivery_fee)+'</td>';
                        rows+='<td>'+PG.esc(o.rider_name||'Sin asignar')+'</td>';
                        rows+='<td>'+PG.badge(o.status)+'</td><td>'+PG.esc(o.created_at)+'</td>';
                        <?php if($is_admin): ?>
                        rows+='<td><select class="pd-rider-sel" data-id="'+o.id+'" style="font-size:12px;"><option value="">—</option>';
                        $.each(riders,function(j,rd){rows+='<option value="'+rd.ID+'"'+(o.rider_id==rd.ID?' selected':'')+'>'+PG.esc(rd.display_name)+'</option>';});
                        rows+='</select> <button class="petsgo-btn petsgo-btn-sm petsgo-btn-success pd-assign" data-id="'+o.id+'">✓</button></td>';
                        <?php endif; ?>
                        rows+='</tr>';
                    });
                    $('#pd-body').html(rows);
                });
            }
            $('#pd-filter-status<?php if($is_admin): ?>, #pd-filter-rider<?php endif; ?>').on('change',load);
            $(document).on('click','.pd-assign',function(){
                var id=$(this).data('id');var rid=$('.pd-rider-sel[data-id="'+id+'"]').val();
                PG.post('petsgo_save_rider_assignment',{order_id:id,rider_id:rid},function(r){if(r.success)load();else alert(r.data);});
            });
            load();
        });
        </script>
        <?php
    }

    // ============================================================
    // 7. PLANES — solo admin, AJAX
    // ============================================================
    public function page_plans() {
        global $wpdb;
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📋 Planes (<span id="pp-total">...</span>)</h1>
            <div class="petsgo-search-bar">
                <span class="petsgo-loader" id="pp-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <button class="petsgo-btn petsgo-btn-primary" id="pp-add-btn" style="margin-left:auto;">➕ Nuevo Plan</button>
            </div>
            <table class="petsgo-table"><thead><tr><th>ID</th><th>Plan</th><th>Precio/mes</th><th>Características</th><th>Acciones</th></tr></thead>
            <tbody id="pp-body"><tr><td colspan="5" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>

            <!-- Frontend Preview -->
            <div style="margin-top:30px;padding:24px;background:#f8f9fa;border-radius:12px;border:1px solid #e5e7eb;">
                <h3 style="margin:0 0 16px;color:#2F3A40;">👁️ Vista Previa — Así se verán los planes en el frontend</h3>
                <div id="pp-preview" style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:900px;"></div>
            </div>

            <!-- Inline form -->
            <div id="pp-form-wrap" style="display:none;margin-top:20px;">
                <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;max-width:960px;">
                    <div class="petsgo-form-section">
                        <h3 id="pp-form-title">Nuevo Plan</h3>
                        <input type="hidden" id="pp-id" value="0">
                        <div class="petsgo-field"><label>Nombre *</label><input type="text" id="pp-name" placeholder="Ej: Básico, Pro, Enterprise"></div>
                        <div class="petsgo-field"><label>Precio mensual (CLP) *</label><input type="number" id="pp-price" min="0" step="1" placeholder="29990"></div>
                        <div class="petsgo-field"><label>Características (JSON)</label>
                            <textarea id="pp-features" rows="6" placeholder='{"max_products":50,"commission_rate":15,"support":"email","analytics":false,"featured":false}'></textarea>
                            <small style="color:#888;">Campos: max_products (-1=ilimitado), commission_rate, support (email|email+chat|prioritario), analytics (true/false), featured (true/false), api_access (true/false)</small>
                        </div>
                        <div style="display:flex;gap:12px;margin-top:12px;">
                            <button class="petsgo-btn petsgo-btn-primary" id="pp-save">💾 Guardar</button>
                            <button class="petsgo-btn" style="background:#e2e3e5;color:#333;" id="pp-cancel">Cancelar</button>
                            <div id="pp-msg" style="display:none;"></div>
                        </div>
                    </div>
                    <!-- Live preview card while editing -->
                    <div>
                        <h4 style="margin:0 0 12px;color:#666;">Vista previa tarjeta:</h4>
                        <div id="pp-live-card" style="background:#fff;border-radius:20px;padding:28px 24px;border:2px solid #00A8E8;box-shadow:0 8px 30px rgba(0,168,232,0.1);"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            var planColors=['#00A8E8','#FFC400','#2F3A40'];
            function fmtMoney(v){return '$'+parseInt(v).toLocaleString('es-CL');}
            function parseFeatures(json){
                try{
                    var obj=typeof json==='string'?JSON.parse(json):json;
                    if(Array.isArray(obj))return obj;
                    var l=[];
                    if(obj.max_products===-1)l.push('Productos ilimitados');
                    else if(obj.max_products)l.push('Hasta '+obj.max_products+' productos');
                    if(obj.commission_rate)l.push('Comisión '+obj.commission_rate+'%');
                    if(obj.support==='email')l.push('Soporte por email');
                    else if(obj.support==='email+chat')l.push('Soporte email + chat');
                    else if(obj.support==='prioritario')l.push('Soporte prioritario 24/7');
                    if(obj.analytics)l.push('Dashboard con analíticas');
                    if(obj.featured)l.push('Productos destacados');
                    if(obj.api_access)l.push('Acceso API personalizado');
                    return l;
                }catch(e){return json?json.split('\n').filter(function(x){return x.trim();}):[]; }
            }
            function renderCard(name,price,featuresJson,color,isPro){
                var feats=parseFeatures(featuresJson);
                var h='<div style="width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;background:'+color+'20;font-size:22px;">'+(isPro?'👑':'⭐')+'</div>';
                h+='<h3 style="font-size:20px;font-weight:800;color:#2F3A40;margin:0 0 6px;">'+PG.esc(name||'Nombre del plan')+'</h3>';
                h+='<div style="display:flex;align-items:baseline;gap:4px;margin-bottom:20px;">';
                h+='<span style="font-size:32px;font-weight:900;color:'+color+';">'+fmtMoney(price||0)+'</span>';
                h+='<span style="color:#9ca3af;font-size:13px;">/mes</span></div>';
                h+='<div style="border-top:1px solid #f3f4f6;padding-top:16px;">';
                if(feats.length){$.each(feats,function(i,f){
                    h+='<div style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px;color:#4b5563;">';
                    h+='<div style="width:20px;height:20px;border-radius:6px;background:'+color+'20;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span style="color:'+color+';font-size:12px;font-weight:bold;">✓</span></div>';
                    h+=PG.esc(f)+'</div>';
                });}else{h+='<p style="color:#ccc;font-size:13px;">Sin características definidas</p>';}
                h+='</div>';
                h+='<button style="width:100%;padding:12px;border-radius:12px;font-size:13px;font-weight:700;margin-top:16px;cursor:pointer;border:2px solid '+color+';background:'+(isPro?color:'transparent')+';color:'+(isPro?'#fff':color)+';">Elegir Plan</button>';
                return h;
            }
            function renderPreview(plans){
                var html='';
                $.each(plans,function(i,p){
                    var color=planColors[i%3];var isPro=(i===1);
                    html+='<div style="background:#fff;border-radius:20px;padding:28px 24px;border:'+(isPro?'2px solid #FFC400':'1px solid #f0f0f0')+';box-shadow:'+(isPro?'0 16px 50px rgba(255,196,0,0.12)':'0 4px 16px rgba(0,0,0,0.04)')+';position:relative;'+(isPro?'transform:scale(1.03);z-index:2;':'')+'">';
                    if(isPro) html+='<span style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#FFC400,#ffb300);color:#2F3A40;font-size:10px;font-weight:800;padding:4px 16px;border-radius:50px;letter-spacing:1px;">⭐ MÁS POPULAR</span>';
                    html+=renderCard(p.plan_name,p.monthly_price,p.features_json,color,isPro);
                    html+='</div>';
                });
                $('#pp-preview').html(html);
            }
            function load(){
                $('#pp-loader').addClass('active');
                PG.post('petsgo_search_plans',{},function(r){
                    $('#pp-loader').removeClass('active');if(!r.success)return;
                    $('#pp-total').text(r.data.length);var rows='';
                    $.each(r.data,function(i,p){
                        var feats=parseFeatures(p.features_json);
                        var featHtml=feats.length?'<ul style="margin:0;padding-left:16px;">':'';
                        $.each(feats,function(j,f){featHtml+='<li style="font-size:13px;margin:2px 0;">'+PG.esc(f)+'</li>';});
                        if(feats.length)featHtml+='</ul>';else featHtml='<span style="color:#999;font-size:12px;">—</span>';
                        rows+='<tr><td>'+p.id+'</td><td><strong>'+PG.esc(p.plan_name)+'</strong></td>';
                        rows+='<td>'+PG.money(p.monthly_price)+'</td>';
                        rows+='<td>'+featHtml+'</td>';
                        rows+='<td><button class="petsgo-btn petsgo-btn-warning petsgo-btn-sm pp-edit" data-id="'+p.id+'" data-name="'+PG.esc(p.plan_name)+'" data-price="'+p.monthly_price+'" data-features="'+PG.esc(p.features_json||'').replace(/"/g,'&quot;')+'">✏️</button> ';
                        rows+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pp-del" data-id="'+p.id+'">🗑️</button></td></tr>';
                    });
                    if(!r.data.length) rows='<tr><td colspan="5" style="text-align:center;padding:30px;color:#999;">Sin planes.</td></tr>';
                    $('#pp-body').html(rows);
                    renderPreview(r.data);
                });
            }
            function updateLiveCard(){
                var color='#00A8E8';
                $('#pp-live-card').html(renderCard($('#pp-name').val(),$('#pp-price').val(),$('#pp-features').val(),color,false));
            }
            function resetForm(){$('#pp-id').val(0);$('#pp-name,#pp-price,#pp-features').val('');$('#pp-form-title').text('Nuevo Plan');$('#pp-msg').hide();updateLiveCard();}
            $('#pp-add-btn').on('click',function(){resetForm();$('#pp-form-wrap').slideDown();});
            $('#pp-cancel').on('click',function(){$('#pp-form-wrap').slideUp();});
            $('#pp-name,#pp-price,#pp-features').on('input',function(){updateLiveCard();});
            $(document).on('click','.pp-edit',function(){
                $('#pp-id').val($(this).data('id'));$('#pp-name').val($(this).data('name'));$('#pp-price').val($(this).data('price'));
                var ft=$(this).data('features')||'';$('#pp-features').val(ft);
                $('#pp-form-title').text('Editar Plan #'+$(this).data('id'));$('#pp-form-wrap').slideDown();$('#pp-msg').hide();updateLiveCard();
            });
            $('#pp-save').on('click',function(){
                if(!$.trim($('#pp-name').val())||!$('#pp-price').val()){alert('Nombre y precio obligatorios');return;}
                // Validate JSON
                var fv=$('#pp-features').val();
                if(fv){try{JSON.parse(fv);}catch(e){alert('Las características deben ser un JSON válido. Ejemplo:\n{"max_products":50,"commission_rate":15,"support":"email","analytics":false,"featured":false}');return;}}
                PG.post('petsgo_save_plan',{id:$('#pp-id').val(),plan_name:$('#pp-name').val(),monthly_price:$('#pp-price').val(),features:$('#pp-features').val()},function(r){
                    if(r.success){$('#pp-form-wrap').slideUp();load();}else{$('#pp-msg').html('<span style="color:red;">'+r.data+'</span>').show();}
                });
            });
            $(document).on('click','.pp-del',function(){if(!confirm('¿Eliminar plan?'))return;PG.post('petsgo_delete_plan',{id:$(this).data('id')},function(r){if(r.success)load();else alert(r.data);});});
            load();
        });
        </script>
        <?php
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================

    // --- PRODUCTS ---
    public function petsgo_search_products() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $vid = $this->is_admin() ? intval($_POST['vendor_id'] ?? 0) : $this->get_my_vendor_id();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');

        $sql = "SELECT i.*, v.store_name FROM {$wpdb->prefix}petsgo_inventory i LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE 1=1";
        $args = [];
        if (!$this->is_admin() && $vid) { $sql .= " AND i.vendor_id=%d"; $args[] = $vid; }
        elseif ($this->is_admin() && $vid) { $sql .= " AND i.vendor_id=%d"; $args[] = $vid; }
        if ($search) { $sql .= " AND i.product_name LIKE %s"; $args[] = '%'.$wpdb->esc_like($search).'%'; }
        if ($category) { $sql .= " AND i.category=%s"; $args[] = $category; }
        $sql .= " ORDER BY i.id DESC";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);

        $data = array_map(function($p) {
            return ['id'=>(int)$p->id,'product_name'=>$p->product_name,'description'=>$p->description,'price'=>(float)$p->price,'stock'=>(int)$p->stock,'category'=>$p->category,'store_name'=>$p->store_name,'image_url'=>$p->image_id?wp_get_attachment_image_url($p->image_id,'thumbnail'):null];
        }, $wpdb->get_results($sql));
        wp_send_json_success($data);
    }

    public function petsgo_save_product() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $vendor_id = intval($_POST['vendor_id'] ?? 0);

        // Vendor solo puede guardar en su tienda
        if (!$this->is_admin()) {
            $my_vid = $this->get_my_vendor_id();
            if ($vendor_id !== $my_vid) wp_send_json_error('No puedes asignar productos a otra tienda.');
            if ($id) {
                $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT vendor_id FROM {$wpdb->prefix}petsgo_inventory WHERE id=%d", $id));
                if ($owner !== $my_vid) wp_send_json_error('Este producto no es de tu tienda.');
            }
        }

        $name=$this->san('product_name');$desc=sanitize_textarea_field($_POST['description']??'');$price=floatval($_POST['price']??0);$stock=intval($_POST['stock']??0);$cat=$this->san('category');
        $img1=intval($_POST['image_id']??0)?:null;$img2=intval($_POST['image_id_2']??0)?:null;$img3=intval($_POST['image_id_3']??0)?:null;

        $errors=[];
        if(strlen($name)<3)$errors[]='Nombre mín 3 chars';if(strlen($desc)<10)$errors[]='Descripción mín 10 chars';
        if($price<=0)$errors[]='Precio > 0';if($stock<0)$errors[]='Stock >= 0';if(!$cat)$errors[]='Categoría obligatoria';
        if(!$vendor_id)$errors[]='Tienda obligatoria';if(!$img1)$errors[]='Foto principal obligatoria';
        if($errors) wp_send_json_error(implode('. ',$errors));

        $data=['vendor_id'=>$vendor_id,'product_name'=>$name,'description'=>$desc,'price'=>$price,'stock'=>$stock,'category'=>$cat,'image_id'=>$img1,'image_id_2'=>$img2,'image_id_3'=>$img3];
        if($id){$wpdb->update("{$wpdb->prefix}petsgo_inventory",$data,['id'=>$id]);$this->audit($id?'product_update':'product_create','product',$id,$name);wp_send_json_success(['message'=>'Producto actualizado','id'=>$id]);}
        else{$wpdb->insert("{$wpdb->prefix}petsgo_inventory",$data);$nid=$wpdb->insert_id;$this->audit('product_create','product',$nid,$name);wp_send_json_success(['message'=>'Producto creado','id'=>$nid]);}
    }

    public function petsgo_delete_product() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Solo admin puede eliminar.');
        global $wpdb;$did=intval($_POST['id']??0);$wpdb->delete("{$wpdb->prefix}petsgo_inventory",['id'=>$did]);
        $this->audit('product_delete','product',$did);
        wp_send_json_success(['message'=>'Eliminado']);
    }

    // --- VENDORS ---
    public function petsgo_search_vendors() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $sql = "SELECT v.*, (SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id=v.id) AS total_products, (SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=v.id) AS total_orders FROM {$wpdb->prefix}petsgo_vendors v WHERE 1=1";
        $args = [];
        if($search){$sql.=" AND v.store_name LIKE %s";$args[]='%'.$wpdb->esc_like($search).'%';}
        if($status){$sql.=" AND v.status=%s";$args[]=$status;}
        $sql.=" ORDER BY v.id DESC";
        if($args) $sql=$wpdb->prepare($sql,...$args);
        wp_send_json_success($wpdb->get_results($sql));
    }

    public function petsgo_save_vendor() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id=intval($_POST['id']??0);
        $data=['store_name'=>$this->san('store_name'),'rut'=>$this->san('rut'),'email'=>sanitize_email($_POST['email']??''),'phone'=>$this->san('phone'),'region'=>$this->san('region'),'comuna'=>$this->san('comuna'),'address'=>sanitize_textarea_field($_POST['address']??''),'user_id'=>intval($_POST['user_id']??0),'sales_commission'=>floatval($_POST['sales_commission']??10),'plan_id'=>intval($_POST['plan_id']??1),'status'=>$this->san('status')];
        $errors=[];
        if(!$data['store_name'])$errors[]='Nombre obligatorio';if(!$data['rut'])$errors[]='RUT obligatorio';
        if(!$data['email'])$errors[]='Email obligatorio';if(!$data['user_id'])$errors[]='Usuario obligatorio';
        if($errors)wp_send_json_error(implode('. ',$errors));

        // Asignar rol vendor al usuario
        $user = get_userdata($data['user_id']);
        if ($user && !in_array('petsgo_vendor', $user->roles) && !in_array('administrator', $user->roles)) {
            $user->set_role('petsgo_vendor');
        }

        if($id){$wpdb->update("{$wpdb->prefix}petsgo_vendors",$data,['id'=>$id]);$this->audit('vendor_update','vendor',$id,$data['store_name']);wp_send_json_success(['message'=>'Tienda actualizada','id'=>$id]);}
        else{$wpdb->insert("{$wpdb->prefix}petsgo_vendors",$data);$nid=$wpdb->insert_id;$this->audit('vendor_create','vendor',$nid,$data['store_name']);wp_send_json_success(['message'=>'Tienda creada','id'=>$nid]);}
    }

    public function petsgo_delete_vendor() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;$did=intval($_POST['id']??0);$wpdb->delete("{$wpdb->prefix}petsgo_vendors",['id'=>$did]);
        $this->audit('vendor_delete','vendor',$did);
        wp_send_json_success(['message'=>'Tienda eliminada']);
    }

    // --- ORDERS ---
    public function petsgo_search_orders() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $is_admin=$this->is_admin();$vid=$this->get_my_vendor_id();
        $search=sanitize_text_field($_POST['search']??'');$status=sanitize_text_field($_POST['status']??'');$filter_vid=intval($_POST['vendor_id']??0);

        $sql="SELECT o.*, v.store_name, u.display_name AS customer_name, r.display_name AS rider_name, inv.id AS invoice_id FROM {$wpdb->prefix}petsgo_orders o LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID LEFT JOIN {$wpdb->users} r ON o.rider_id=r.ID LEFT JOIN {$wpdb->prefix}petsgo_invoices inv ON inv.order_id=o.id WHERE 1=1";
        $args=[];
        if(!$is_admin&&$vid){$sql.=" AND o.vendor_id=%d";$args[]=$vid;}
        elseif($is_admin&&$filter_vid){$sql.=" AND o.vendor_id=%d";$args[]=$filter_vid;}
        if($this->is_rider()&&!$is_admin){$sql.=" AND o.rider_id=%d";$args[]=get_current_user_id();}
        if($search){$sql.=" AND u.display_name LIKE %s";$args[]='%'.$wpdb->esc_like($search).'%';}
        if($status){$sql.=" AND o.status=%s";$args[]=$status;}
        $sql.=" ORDER BY o.created_at DESC";
        if($args) $sql=$wpdb->prepare($sql,...$args);
        $results = $wpdb->get_results($sql);
        foreach ($results as &$r) { $r->has_invoice = !empty($r->invoice_id); }
        wp_send_json_success($results);
    }

    public function petsgo_update_order_status() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Solo admin puede cambiar estado.');
        global $wpdb;
        $oid=intval($_POST['id']??0);$ns=sanitize_text_field($_POST['status']??'');
        $wpdb->update("{$wpdb->prefix}petsgo_orders",['status'=>$ns],['id'=>$oid]);
        $this->audit('order_status_change','order',$oid,'Nuevo estado: '.$ns);
        wp_send_json_success(['message'=>'Estado actualizado']);
    }

    // --- USERS ---
    public function petsgo_search_users() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $search=sanitize_text_field($_POST['search']??'');$role=sanitize_text_field($_POST['role']??'');
        $role_map=['administrator'=>'Admin','petsgo_vendor'=>'Tienda','petsgo_rider'=>'Rider','subscriber'=>'Cliente','petsgo_support'=>'Soporte'];

        $args=['orderby'=>'ID','order'=>'DESC','number'=>200];
        if($search) $args['search']='*'.$search.'*';
        if($role) $args['role']=$role;
        $query=new WP_User_Query($args);$users=$query->get_results();

        $data=[];
        foreach($users as $u){
            $rk='subscriber';
            if(in_array('administrator',$u->roles))$rk='administrator';
            elseif(in_array('petsgo_vendor',$u->roles))$rk='petsgo_vendor';
            elseif(in_array('petsgo_rider',$u->roles))$rk='petsgo_rider';
            elseif(in_array('petsgo_support',$u->roles))$rk='petsgo_support';
            $store = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}petsgo_vendors WHERE user_id=%d", $u->ID));
            $data[]=['ID'=>$u->ID,'user_login'=>$u->user_login,'display_name'=>$u->display_name,'user_email'=>$u->user_email,'role_key'=>str_replace('petsgo_','',$rk),'role_label'=>$role_map[$rk]??$rk,'store_name'=>$store,'user_registered'=>$u->user_registered];
        }
        wp_send_json_success($data);
    }

    public function petsgo_save_user() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        $id=intval($_POST['id']??0);$login=$this->san('user_login');$name=$this->san('display_name');$email=sanitize_email($_POST['user_email']??'');$pass=$_POST['password']??'';$role=sanitize_text_field($_POST['role']??'subscriber');
        $errors=[];
        if(!$login)$errors[]='Usuario obligatorio';if(!$name)$errors[]='Nombre obligatorio';if(!$email)$errors[]='Email obligatorio';
        if(!$id&&strlen($pass)<6)$errors[]='Contraseña mín 6 caracteres';
        if($errors)wp_send_json_error(implode('. ',$errors));

        if($id){
            wp_update_user(['ID'=>$id,'display_name'=>$name,'user_email'=>$email]);
            if($pass) wp_set_password($pass,$id);
            $user=get_userdata($id);$user->set_role($role);
            $this->audit('user_update','user',$id,$name);
            wp_send_json_success(['message'=>'Usuario actualizado','id'=>$id]);
        }else{
            $uid=wp_create_user($login,$pass,$email);
            if(is_wp_error($uid)) wp_send_json_error($uid->get_error_message());
            wp_update_user(['ID'=>$uid,'display_name'=>$name]);
            $user=get_userdata($uid);$user->set_role($role);
            $this->audit('user_create','user',$uid,$name);
            wp_send_json_success(['message'=>'Usuario creado','id'=>$uid]);
        }
    }

    public function petsgo_delete_user() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        $id=intval($_POST['id']??0);
        if($id==get_current_user_id()) wp_send_json_error('No puedes eliminarte a ti mismo.');
        require_once(ABSPATH.'wp-admin/includes/user.php');
        wp_delete_user($id);
        $this->audit('user_delete','user',$id);
        wp_send_json_success(['message'=>'Usuario eliminado']);
    }

    // --- DELIVERY ---
    public function petsgo_search_riders() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $is_admin=$this->is_admin();
        $status=sanitize_text_field($_POST['status']??'');$rider_filter=$_POST['rider_id']??'';

        $sql="SELECT o.*, v.store_name, u.display_name AS customer_name, r.display_name AS rider_name FROM {$wpdb->prefix}petsgo_orders o LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID LEFT JOIN {$wpdb->users} r ON o.rider_id=r.ID WHERE 1=1";
        $args=[];
        if(!$is_admin){$sql.=" AND o.rider_id=%d";$args[]=get_current_user_id();}
        elseif($rider_filter==='unassigned'){$sql.=" AND o.rider_id IS NULL";}
        elseif($rider_filter&&is_numeric($rider_filter)){$sql.=" AND o.rider_id=%d";$args[]=intval($rider_filter);}
        if($status){$sql.=" AND o.status=%s";$args[]=$status;}
        $sql.=" ORDER BY o.created_at DESC";
        if($args) $sql=$wpdb->prepare($sql,...$args);
        wp_send_json_success($wpdb->get_results($sql));
    }

    public function petsgo_save_rider_assignment() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Solo admin');
        global $wpdb;
        $order_id=intval($_POST['order_id']??0);$rider_id=intval($_POST['rider_id']??0)?:null;
        $wpdb->update("{$wpdb->prefix}petsgo_orders",['rider_id'=>$rider_id],['id'=>$order_id]);
        $this->audit('rider_assign','order',$order_id,'Rider ID: '.($rider_id??'none'));
        wp_send_json_success(['message'=>'Rider asignado']);
    }

    // --- PLANS ---
    public function petsgo_search_plans() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        wp_send_json_success($wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_subscriptions ORDER BY monthly_price ASC"));
    }

    public function petsgo_save_plan() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id=intval($_POST['id']??0);
        $data=['plan_name'=>$this->san('plan_name'),'monthly_price'=>floatval($_POST['monthly_price']??0),'features_json'=>sanitize_textarea_field($_POST['features']??'')];
        if(!$data['plan_name'])wp_send_json_error('Nombre obligatorio');
        if($id){$wpdb->update("{$wpdb->prefix}petsgo_subscriptions",$data,['id'=>$id]);$this->audit('plan_update','plan',$id,$data['plan_name']);wp_send_json_success(['message'=>'Plan actualizado']);}
        else{$wpdb->insert("{$wpdb->prefix}petsgo_subscriptions",$data);$nid=$wpdb->insert_id;$this->audit('plan_create','plan',$nid,$data['plan_name']);wp_send_json_success(['message'=>'Plan creado','id'=>$nid]);}
    }

    public function petsgo_delete_plan() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;$did=intval($_POST['id']??0);$wpdb->delete("{$wpdb->prefix}petsgo_subscriptions",['id'=>$did]);
        $this->audit('plan_delete','plan',$did);
        wp_send_json_success(['message'=>'Plan eliminado']);
    }

    // Sanitize helper
    private function san($key) { return sanitize_text_field($_POST[$key] ?? ''); }

    // ============================================================
    // 8. BOLETAS / INVOICES
    // ============================================================
    public function page_invoices() {
        global $wpdb;
        $is_admin = $this->is_admin();
        $vid = $this->get_my_vendor_id();
        $vendors = $is_admin ? $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name") : [];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🧾 Boletas (<span id="bi-total">...</span>)</h1>
            <div class="petsgo-search-bar">
                <input type="text" id="bi-search" placeholder="🔍 Buscar Nº boleta, cliente..." autocomplete="off">
                <?php if($is_admin): ?>
                <select id="bi-filter-vendor"><option value="">Todas las tiendas</option>
                    <?php foreach($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <span class="petsgo-loader" id="bi-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead><tr><th>#</th><th>Nº Boleta</th><th>Pedido</th><th>Tienda</th><th>Cliente</th><th>Total</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody id="bi-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            function load(){
                $('#bi-loader').addClass('active');
                var d={search:$('#bi-search').val()};
                <?php if($is_admin): ?>d.vendor_id=$('#bi-filter-vendor').val();<?php endif; ?>
                PG.post('petsgo_search_invoices',d,function(r){
                    $('#bi-loader').removeClass('active');if(!r.success)return;
                    $('#bi-total').text(r.data.length);var rows='';
                    if(!r.data.length){rows='<tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Sin boletas.</td></tr>';}
                    $.each(r.data,function(i,b){
                        rows+='<tr><td>'+b.id+'</td><td><strong>'+PG.esc(b.invoice_number)+'</strong></td>';
                        rows+='<td>#'+b.order_id+'</td><td>'+PG.esc(b.store_name||'')+'</td>';
                        rows+='<td>'+PG.esc(b.customer_name||'')+'</td><td>'+PG.money(b.total_amount)+'</td>';
                        rows+='<td>'+PG.esc(b.created_at)+'</td>';
                        rows+='<td>';
                        rows+='<a href="'+PG.adminUrl+'?page=petsgo-invoice-config&preview='+b.id+'" class="petsgo-btn petsgo-btn-sm petsgo-btn-primary" target="_blank">👁️ Ver</a> ';
                        rows+='<a href="'+PG.ajaxUrl+'?action=petsgo_download_invoice&_wpnonce='+PG.nonce+'&id='+b.id+'" class="petsgo-btn petsgo-btn-sm petsgo-btn-success" target="_blank">📥 PDF</a>';
                        rows+='</td></tr>';
                    });
                    $('#bi-body').html(rows);
                });
            }
            load();var t;$('#bi-search,#bi-filter-vendor').on('input change',function(){clearTimeout(t);t=setTimeout(load,400);});
        });
        </script>
        <?php
    }

    // ============================================================
    // 9. CONFIG BOLETA (per vendor)
    // ============================================================
    public function page_invoice_config() {
        global $wpdb;
        // If preview mode, show invoice preview
        $preview_id = intval($_GET['preview'] ?? 0);
        if ($preview_id) {
            $inv = $wpdb->get_row($wpdb->prepare("SELECT i.*, o.total_amount, o.customer_id, v.store_name FROM {$wpdb->prefix}petsgo_invoices i JOIN {$wpdb->prefix}petsgo_orders o ON i.order_id=o.id JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE i.id=%d", $preview_id));
            if (!$inv) { echo '<div class="wrap"><h1>Boleta no encontrada</h1></div>'; return; }
            $customer = get_userdata($inv->customer_id);
            ?>
            <div class="wrap petsgo-wrap">
                <h1>👁️ Vista Previa — <?php echo esc_html($inv->invoice_number); ?></h1>
                <a href="<?php echo admin_url('admin.php?page=petsgo-invoices'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" style="margin-bottom:16px;display:inline-block;">← Volver a Boletas</a>
                <div style="background:#fff;padding:24px;border:1px solid #ddd;border-radius:8px;max-width:800px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:3px solid #00A8E8;padding-bottom:16px;">
                        <div><img src="<?php echo plugins_url('petsgo-lib/logo-petsgo.png', __FILE__); ?>" alt="PetsGo" style="max-height:60px;" onerror="this.style.display='none'"><h2 style="color:#00A8E8;margin:4px 0 0;">PetsGo</h2></div>
                        <div style="text-align:right;"><h3 style="margin:0;"><?php echo esc_html($inv->store_name); ?></h3></div>
                    </div>
                    <table style="width:100%;margin-bottom:20px;"><tr>
                        <td><strong>Nº Boleta:</strong> <?php echo esc_html($inv->invoice_number); ?></td>
                        <td><strong>Fecha:</strong> <?php echo esc_html($inv->created_at); ?></td>
                    </tr><tr>
                        <td><strong>Cliente:</strong> <?php echo esc_html($customer ? $customer->display_name : 'N/A'); ?></td>
                        <td><strong>Total:</strong> $<?php echo number_format($inv->total_amount, 0, ',', '.'); ?></td>
                    </tr></table>
                    <?php echo wp_kses_post($inv->html_content); ?>
                    <?php if($inv->qr_token): ?>
                    <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid #eee;">
                        <p><strong>Código QR de Validación</strong></p>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode(site_url('/wp-json/petsgo/v1/invoice/validate/'.$inv->qr_token)); ?>" alt="QR">
                        <p style="font-size:11px;color:#999;">Escanee para validar esta boleta</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:16px;">
                    <a href="<?php echo admin_url('admin-ajax.php?action=petsgo_download_invoice&_wpnonce='.wp_create_nonce('petsgo_ajax').'&id='.$inv->id); ?>" class="petsgo-btn petsgo-btn-success" target="_blank">📥 Descargar PDF</a>
                </div>
            </div>
            <?php
            return;
        }

        // Vendor config form
        $is_admin = $this->is_admin();
        $config_vid = intval($_GET['vendor_id'] ?? 0);
        if (!$is_admin) {
            $config_vid = $this->get_my_vendor_id();
            if (!$config_vid) { echo '<div class="wrap"><h1>No tienes tienda asignada</h1></div>'; return; }
        }
        if (!$config_vid && $is_admin) {
            // Admin: show vendor selector
            $vendors = $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name");
            ?>
            <div class="wrap petsgo-wrap">
                <h1>⚙️ Configuración de Boleta</h1>
                <p>Selecciona una tienda para configurar su boleta:</p>
                <div style="max-width:400px;">
                    <?php foreach($vendors as $v): ?>
                    <a href="<?php echo admin_url('admin.php?page=petsgo-invoice-config&vendor_id='.$v->id); ?>" class="petsgo-btn petsgo-btn-primary" style="display:block;margin-bottom:8px;">🏪 <?php echo esc_html($v->store_name); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            return;
        }

        $vendor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d", $config_vid));
        if (!$vendor) { echo '<div class="wrap"><h1>Tienda no encontrada</h1></div>'; return; }
        $logo_url = $vendor->invoice_logo_id ? wp_get_attachment_url($vendor->invoice_logo_id) : '';
        ?>
        <div class="wrap petsgo-wrap">
            <h1>⚙️ Configuración Boleta — <?php echo esc_html($vendor->store_name); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=petsgo-invoices'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" style="margin-bottom:16px;display:inline-block;">← Volver</a>
            <form id="ic-form" novalidate>
                <input type="hidden" id="ic-vid" value="<?php echo $config_vid; ?>">
                <div class="petsgo-form-section" style="max-width:700px;">
                    <h3>📱 Datos de Contacto para Boleta</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="petsgo-field"><label>RUT (de tabla vendedores)</label><input type="text" value="<?php echo esc_attr($vendor->rut); ?>" disabled style="background:#f0f0f0;"></div>
                        <div class="petsgo-field"><label>Teléfono de contacto</label><input type="text" id="ic-phone" value="<?php echo esc_attr($vendor->contact_phone ?? ''); ?>" placeholder="+56 9 1234 5678"></div>
                        <div class="petsgo-field"><label>Facebook</label><input type="text" id="ic-facebook" value="<?php echo esc_attr($vendor->social_facebook ?? ''); ?>" placeholder="facebook.com/mitienda"></div>
                        <div class="petsgo-field"><label>Instagram</label><input type="text" id="ic-instagram" value="<?php echo esc_attr($vendor->social_instagram ?? ''); ?>" placeholder="@mitienda"></div>
                        <div class="petsgo-field"><label>WhatsApp</label><input type="text" id="ic-whatsapp" value="<?php echo esc_attr($vendor->social_whatsapp ?? ''); ?>" placeholder="+56912345678"></div>
                        <div class="petsgo-field"><label>Sitio Web</label><input type="text" id="ic-website" value="<?php echo esc_attr($vendor->social_website ?? ''); ?>" placeholder="https://www.mitienda.cl"></div>
                    </div>
                    <h3 style="margin-top:24px;">🖼️ Logo para Boleta</h3>
                    <div style="display:flex;gap:16px;align-items:center;">
                        <input type="hidden" id="ic-logo-id" value="<?php echo intval($vendor->invoice_logo_id ?? 0); ?>">
                        <img id="ic-logo-preview" src="<?php echo esc_url($logo_url); ?>" style="max-height:80px;max-width:160px;border:1px solid #ddd;border-radius:4px;<?php echo $logo_url?'':'display:none;'; ?>">
                        <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="ic-upload-logo">📤 Subir Logo</button>
                        <button type="button" class="petsgo-btn petsgo-btn-danger petsgo-btn-sm" id="ic-remove-logo" style="<?php echo $logo_url?'':'display:none;'; ?>">✕ Quitar</button>
                    </div>
                    <div style="margin-top:24px;display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="petsgo-btn petsgo-btn-primary">💾 Guardar Configuración</button>
                        <span class="petsgo-loader" id="ic-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                        <div id="ic-msg" style="display:none;"></div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(function($){
            // Media uploader for logo
            var frame;
            $('#ic-upload-logo').on('click',function(e){
                e.preventDefault();
                if(frame){frame.open();return;}
                frame=wp.media({title:'Seleccionar Logo',button:{text:'Usar como Logo'},multiple:false});
                frame.on('select',function(){
                    var att=frame.state().get('selection').first().toJSON();
                    $('#ic-logo-id').val(att.id);
                    $('#ic-logo-preview').attr('src',att.url).show();
                    $('#ic-remove-logo').show();
                });
                frame.open();
            });
            $('#ic-remove-logo').on('click',function(){
                $('#ic-logo-id').val(0);$('#ic-logo-preview').hide();$(this).hide();
            });
            // Save
            $('#ic-form').on('submit',function(e){
                e.preventDefault();$('#ic-loader').addClass('active');$('#ic-msg').hide();
                PG.post('petsgo_save_vendor_invoice_config',{
                    vendor_id:$('#ic-vid').val(),
                    contact_phone:$('#ic-phone').val(),
                    social_facebook:$('#ic-facebook').val(),
                    social_instagram:$('#ic-instagram').val(),
                    social_whatsapp:$('#ic-whatsapp').val(),
                    social_website:$('#ic-website').val(),
                    invoice_logo_id:$('#ic-logo-id').val()
                },function(r){
                    $('#ic-loader').removeClass('active');
                    var cls=r.success?'notice-success':'notice-error';
                    $('#ic-msg').html('<div class="notice '+cls+'" style="padding:10px"><p>'+(r.success?'✅ '+r.data.message:'❌ '+r.data)+'</p></div>').show();
                });
            });
        });
        </script>
        <?php
    }

    // ============================================================
    // 10. AUDITORÍA
    // ============================================================
    public function page_audit_log() {
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🔍 Auditoría (<span id="au-total">...</span>)</h1>
            <div class="petsgo-search-bar">
                <input type="text" id="au-search" placeholder="🔍 Buscar usuario, acción..." autocomplete="off">
                <select id="au-entity"><option value="">Todas las entidades</option>
                    <option value="product">Producto</option><option value="vendor">Tienda</option><option value="order">Pedido</option>
                    <option value="user">Usuario</option><option value="plan">Plan</option><option value="invoice">Boleta</option>
                    <option value="login">Login</option>
                </select>
                <input type="date" id="au-date-from" title="Desde">
                <input type="date" id="au-date-to" title="Hasta">
                <span class="petsgo-loader" id="au-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead><tr><th>#</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>ID Ent.</th><th>Detalles</th><th>IP</th><th>Fecha</th></tr></thead>
            <tbody id="au-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            function load(){
                $('#au-loader').addClass('active');
                PG.post('petsgo_search_audit_log',{
                    search:$('#au-search').val(),entity:$('#au-entity').val(),
                    date_from:$('#au-date-from').val(),date_to:$('#au-date-to').val()
                },function(r){
                    $('#au-loader').removeClass('active');if(!r.success)return;
                    $('#au-total').text(r.data.length);var rows='';
                    if(!r.data.length){rows='<tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Sin registros.</td></tr>';}
                    $.each(r.data,function(i,a){
                        rows+='<tr><td>'+a.id+'</td><td>'+PG.esc(a.user_name)+'</td><td><code>'+PG.esc(a.action)+'</code></td>';
                        rows+='<td>'+PG.esc(a.entity_type||'')+'</td><td>'+(a.entity_id||'-')+'</td>';
                        rows+='<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+PG.esc(a.details||'')+'">'+PG.esc(a.details||'')+'</td>';
                        rows+='<td><small>'+PG.esc(a.ip_address)+'</small></td><td><small>'+PG.esc(a.created_at)+'</small></td></tr>';
                    });
                    $('#au-body').html(rows);
                });
            }
            load();var t;$('#au-search,#au-entity,#au-date-from,#au-date-to').on('input change',function(){clearTimeout(t);t=setTimeout(load,400);});
        });
        </script>
        <?php
    }

    // ============================================================
    // AJAX HANDLERS — Invoices
    // ============================================================
    public function petsgo_search_invoices() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $is_admin = $this->is_admin();
        $vid = $this->get_my_vendor_id();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $filter_vid = intval($_POST['vendor_id'] ?? 0);

        $sql = "SELECT inv.*, o.total_amount, v.store_name, u.display_name AS customer_name
                FROM {$wpdb->prefix}petsgo_invoices inv
                JOIN {$wpdb->prefix}petsgo_orders o ON inv.order_id = o.id
                JOIN {$wpdb->prefix}petsgo_vendors v ON inv.vendor_id = v.id
                LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID WHERE 1=1";
        $args = [];
        if (!$is_admin && $vid) { $sql .= " AND inv.vendor_id=%d"; $args[] = $vid; }
        elseif ($is_admin && $filter_vid) { $sql .= " AND inv.vendor_id=%d"; $args[] = $filter_vid; }
        if ($search) { $sql .= " AND (inv.invoice_number LIKE %s OR u.display_name LIKE %s)"; $args[] = '%'.$wpdb->esc_like($search).'%'; $args[] = '%'.$wpdb->esc_like($search).'%'; }
        $sql .= " ORDER BY inv.created_at DESC LIMIT 200";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        wp_send_json_success($wpdb->get_results($sql));
    }

    public function petsgo_generate_invoice() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) wp_send_json_error('Falta order_id');

        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_invoices WHERE order_id=%d", $order_id));
        if ($exists) wp_send_json_error('Ya existe boleta para este pedido');

        $order = $wpdb->get_row($wpdb->prepare("SELECT o.*, v.store_name, v.rut AS vendor_rut, v.address AS vendor_address, v.phone AS vendor_phone, v.email AS vendor_email, v.contact_phone, v.social_facebook, v.social_instagram, v.social_whatsapp, v.social_website, v.invoice_logo_id, u.display_name AS customer_name, u.user_email AS customer_email FROM {$wpdb->prefix}petsgo_orders o JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID WHERE o.id=%d", $order_id));
        if (!$order) wp_send_json_error('Pedido no encontrado');

        // Generate invoice number: PG-YYYYMMDD-XXXX
        $today = date('Ymd');
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_invoices WHERE invoice_number LIKE %s", 'PG-'.$today.'-%'));
        $inv_number = 'PG-' . $today . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // QR token
        $qr_token = wp_generate_uuid4();

        // HTML content for preview
        $items_html = '<table style="width:100%;border-collapse:collapse;margin:12px 0;"><tr style="background:#00A8E8;color:#fff;"><th style="padding:8px;text-align:left;">Concepto</th><th style="padding:8px;text-align:right;">Monto</th></tr>';
        $items_html .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;">Pedido #'.$order_id.'</td><td style="padding:8px;text-align:right;">$'.number_format($order->total_amount,0,',','.').'</td></tr>';
        if ($order->delivery_fee > 0) {
            $items_html .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;">Delivery</td><td style="padding:8px;text-align:right;">$'.number_format($order->delivery_fee,0,',','.').'</td></tr>';
        }
        $grand = $order->total_amount + ($order->delivery_fee ?? 0);
        $neto = round($grand / 1.19);
        $iva = $grand - $neto;
        $items_html .= '</table>';
        $items_html .= '<p style="text-align:right;"><strong>Neto:</strong> $'.number_format($neto,0,',','.').
            ' | <strong>IVA (19%):</strong> $'.number_format($iva,0,',','.').
            ' | <strong>Total:</strong> $'.number_format($grand,0,',','.').'</p>';

        // Generate PDF
        require_once __DIR__ . '/petsgo-lib/invoice-pdf.php';
        $pdf_gen = new PetsGo_Invoice_PDF();
        $vendor_data = [
            'store_name' => $order->store_name,
            'rut' => $order->vendor_rut,
            'address' => $order->vendor_address,
            'phone' => $order->vendor_phone,
            'email' => $order->vendor_email,
            'contact_phone' => $order->contact_phone,
            'social_facebook' => $order->social_facebook,
            'social_instagram' => $order->social_instagram,
            'social_whatsapp' => $order->social_whatsapp,
            'social_website' => $order->social_website,
            'logo_url' => $order->invoice_logo_id ? wp_get_attachment_url($order->invoice_logo_id) : '',
        ];
        $invoice_data = [
            'invoice_number' => $inv_number,
            'date' => date('d/m/Y H:i'),
            'customer_name' => $order->customer_name ?? 'N/A',
            'customer_email' => $order->customer_email ?? '',
        ];
        $items = [['name' => 'Pedido #'.$order_id, 'qty' => 1, 'price' => $order->total_amount]];
        if ($order->delivery_fee > 0) {
            $items[] = ['name' => 'Delivery', 'qty' => 1, 'price' => $order->delivery_fee];
        }

        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/petsgo-invoices/';
        $pdf_filename = $inv_number . '.pdf';
        $pdf_path = $pdf_dir . $pdf_filename;

        $qr_url = site_url('/wp-json/petsgo/v1/invoice/validate/' . $qr_token);
        $pdf_gen->generate($vendor_data, $invoice_data, $items, $grand, $qr_url, $qr_token, $pdf_path);

        $pdf_rel = 'petsgo-invoices/' . $pdf_filename;

        // Save to DB
        $wpdb->insert("{$wpdb->prefix}petsgo_invoices", [
            'order_id' => $order_id,
            'vendor_id' => $order->vendor_id,
            'invoice_number' => $inv_number,
            'qr_token' => $qr_token,
            'html_content' => $items_html,
            'pdf_path' => $pdf_rel,
        ]);
        $inv_id = $wpdb->insert_id;

        $this->audit('invoice_generate', 'invoice', $inv_id, $inv_number . ' para pedido #' . $order_id);

        // Send email to customer + BCC store + contacto@petsgo.cl
        $this->send_invoice_email($order, $inv_number, $pdf_path);

        wp_send_json_success(['message' => 'Boleta generada: ' . $inv_number, 'id' => $inv_id]);
    }

    public function petsgo_download_invoice() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'petsgo_ajax')) {
            wp_die('Nonce inválido');
        }
        global $wpdb;
        $id = intval($_GET['id'] ?? 0);
        $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_invoices WHERE id=%d", $id));
        if (!$inv) wp_die('Boleta no encontrada');

        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/' . $inv->pdf_path;
        if (!file_exists($pdf_path)) wp_die('Archivo PDF no encontrado');

        $this->audit('invoice_download', 'invoice', $id, $inv->invoice_number);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $inv->invoice_number . '.pdf"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        exit;
    }

    // ============================================================
    // AJAX HANDLERS — Audit Log
    // ============================================================
    public function petsgo_search_audit_log() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Solo admin');
        global $wpdb;

        $search = sanitize_text_field($_POST['search'] ?? '');
        $entity = sanitize_text_field($_POST['entity'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        $sql = "SELECT * FROM {$wpdb->prefix}petsgo_audit_log WHERE 1=1";
        $args = [];
        if ($search) { $sql .= " AND (user_name LIKE %s OR action LIKE %s OR details LIKE %s)"; $args[] = '%'.$wpdb->esc_like($search).'%'; $args[] = '%'.$wpdb->esc_like($search).'%'; $args[] = '%'.$wpdb->esc_like($search).'%'; }
        if ($entity) { $sql .= " AND entity_type=%s"; $args[] = $entity; }
        if ($date_from) { $sql .= " AND created_at >= %s"; $args[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $sql .= " AND created_at <= %s"; $args[] = $date_to . ' 23:59:59'; }
        $sql .= " ORDER BY created_at DESC LIMIT 500";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        wp_send_json_success($wpdb->get_results($sql));
    }

    // ============================================================
    // AJAX HANDLERS — Vendor Invoice Config
    // ============================================================
    public function petsgo_save_vendor_invoice_config() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) wp_send_json_error('Falta vendor_id');

        // Permission: admin or the vendor's own user
        if (!$this->is_admin()) {
            $my_vid = $this->get_my_vendor_id();
            if ($my_vid != $vendor_id) wp_send_json_error('Sin permisos');
        }

        $data = [
            'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),
            'social_facebook' => esc_url_raw($_POST['social_facebook'] ?? ''),
            'social_instagram' => sanitize_text_field($_POST['social_instagram'] ?? ''),
            'social_whatsapp' => sanitize_text_field($_POST['social_whatsapp'] ?? ''),
            'social_website' => esc_url_raw($_POST['social_website'] ?? ''),
            'invoice_logo_id' => intval($_POST['invoice_logo_id'] ?? 0),
        ];
        $wpdb->update("{$wpdb->prefix}petsgo_vendors", $data, ['id' => $vendor_id]);
        $vendor = $wpdb->get_row($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d", $vendor_id));
        $this->audit('vendor_invoice_config', 'vendor', $vendor_id, ($vendor->store_name ?? '') . ' — config boleta');
        wp_send_json_success(['message' => 'Configuración guardada']);
    }

    // ============================================================
    // AUTO-GENERATE INVOICE (called from api_create_order)
    // ============================================================
    private function auto_generate_invoice($order_id) {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, v.store_name, v.rut AS vendor_rut, v.address AS vendor_address, v.phone AS vendor_phone, v.email AS vendor_email,
             v.contact_phone, v.social_facebook, v.social_instagram, v.social_whatsapp, v.social_website, v.invoice_logo_id,
             u.display_name AS customer_name, u.user_email AS customer_email
             FROM {$wpdb->prefix}petsgo_orders o
             JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id
             LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID
             WHERE o.id=%d", $order_id));
        if (!$order) return;

        $today = date('Ymd');
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_invoices WHERE invoice_number LIKE %s", 'PG-'.$today.'-%'));
        $inv_number = 'PG-' . $today . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $qr_token = wp_generate_uuid4();

        $grand = $order->total_amount + ($order->delivery_fee ?? 0);
        $neto = round($grand / 1.19);
        $iva = $grand - $neto;

        $items_html = '<table style="width:100%;border-collapse:collapse;margin:12px 0;"><tr style="background:#00A8E8;color:#fff;"><th style="padding:8px;text-align:left;">Concepto</th><th style="padding:8px;text-align:right;">Monto</th></tr>';
        $items_html .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;">Pedido #'.$order_id.'</td><td style="padding:8px;text-align:right;">$'.number_format($order->total_amount,0,',','.').'</td></tr>';
        if ($order->delivery_fee > 0) $items_html .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;">Delivery</td><td style="padding:8px;text-align:right;">$'.number_format($order->delivery_fee,0,',','.').'</td></tr>';
        $items_html .= '</table><p style="text-align:right;"><strong>Neto:</strong> $'.number_format($neto,0,',','.').
            ' | <strong>IVA (19%):</strong> $'.number_format($iva,0,',','.').
            ' | <strong>Total:</strong> $'.number_format($grand,0,',','.').'</p>';

        require_once __DIR__ . '/petsgo-lib/invoice-pdf.php';
        $pdf_gen = new PetsGo_Invoice_PDF();
        $vendor_data = [
            'store_name' => $order->store_name, 'rut' => $order->vendor_rut, 'address' => $order->vendor_address,
            'phone' => $order->vendor_phone, 'email' => $order->vendor_email, 'contact_phone' => $order->contact_phone,
            'social_facebook' => $order->social_facebook, 'social_instagram' => $order->social_instagram,
            'social_whatsapp' => $order->social_whatsapp, 'social_website' => $order->social_website,
            'logo_url' => $order->invoice_logo_id ? wp_get_attachment_url($order->invoice_logo_id) : '',
        ];
        $invoice_data = ['invoice_number' => $inv_number, 'date' => date('d/m/Y H:i'), 'customer_name' => $order->customer_name ?? 'N/A', 'customer_email' => $order->customer_email ?? ''];
        $items = [['name' => 'Pedido #'.$order_id, 'qty' => 1, 'price' => $order->total_amount]];
        if ($order->delivery_fee > 0) $items[] = ['name' => 'Delivery', 'qty' => 1, 'price' => $order->delivery_fee];

        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/petsgo-invoices/' . $inv_number . '.pdf';
        $qr_url = site_url('/wp-json/petsgo/v1/invoice/validate/' . $qr_token);
        $pdf_gen->generate($vendor_data, $invoice_data, $items, $grand, $qr_url, $qr_token, $pdf_path);

        $wpdb->insert("{$wpdb->prefix}petsgo_invoices", [
            'order_id' => $order_id, 'vendor_id' => $order->vendor_id, 'invoice_number' => $inv_number,
            'qr_token' => $qr_token, 'html_content' => $items_html, 'pdf_path' => 'petsgo-invoices/' . $inv_number . '.pdf',
        ]);
        $inv_id = $wpdb->insert_id;
        $this->audit('invoice_auto_generate', 'invoice', $inv_id, $inv_number . ' para pedido #' . $order_id);
        $this->send_invoice_email($order, $inv_number, $pdf_path);
    }

    // ============================================================
    // EMAIL — Send invoice to customer + BCC
    // ============================================================
    private function send_invoice_email($order, $inv_number, $pdf_path) {
        $to = $order->customer_email;
        if (!$to) return;

        $subject = 'PetsGo — Tu Boleta ' . $inv_number;
        $body = '<html><body style="font-family:Arial,sans-serif;color:#333;">';
        $body .= '<div style="max-width:600px;margin:0 auto;">';
        $body .= '<div style="background:#00A8E8;color:#fff;padding:20px;text-align:center;border-radius:8px 8px 0 0;">';
        $body .= '<h1 style="margin:0;">🐾 PetsGo</h1>';
        $body .= '<p style="margin:4px 0 0;">Tu compra ha sido procesada</p></div>';
        $body .= '<div style="padding:20px;background:#fff;border:1px solid #eee;">';
        $body .= '<p>Hola <strong>' . esc_html($order->customer_name ?? 'Cliente') . '</strong>,</p>';
        $body .= '<p>Gracias por tu compra en <strong>' . esc_html($order->store_name) . '</strong>.</p>';
        $body .= '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Nº Boleta:</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">' . $inv_number . '</td></tr>';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Total:</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">$' . number_format($order->total_amount, 0, ',', '.') . '</td></tr>';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Tienda:</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">' . esc_html($order->store_name) . '</td></tr>';
        $body .= '</table>';
        $body .= '<p>Adjuntamos tu boleta en formato PDF. También puedes verificar su validez escaneando el código QR incluido.</p>';
        $body .= '<p style="color:#999;font-size:12px;">Si tienes preguntas, contacta a contacto@petsgo.cl</p>';
        $body .= '</div>';
        $body .= '<div style="background:#2F3A40;color:#fff;padding:12px;text-align:center;border-radius:0 0 8px 8px;font-size:12px;">© ' . date('Y') . ' PetsGo — Marketplace de mascotas</div>';
        $body .= '</div></body></html>';

        // BCC: admin + vendor
        $bcc = ['contacto@petsgo.cl'];
        if (!empty($order->vendor_email)) $bcc[] = $order->vendor_email;

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($bcc as $b) { $headers[] = 'Bcc: ' . $b; }

        $attachments = [];
        if (file_exists($pdf_path)) $attachments[] = $pdf_path;

        wp_mail($to, $subject, $body, $headers, $attachments);
    }

    // ============================================================
    // ROLES
    // ============================================================
    public function register_roles() {
        add_role('petsgo_vendor', 'Tienda (Vendor)', ['read'=>true,'upload_files'=>true,'manage_inventory'=>true]);
        add_role('petsgo_rider', 'Delivery (Rider)', ['read'=>true,'manage_deliveries'=>true]);
        add_role('petsgo_support', 'Soporte', ['read'=>true,'moderate_comments'=>true,'manage_support_tickets'=>true]);
    }

    // ============================================================
    // REST API (para frontend React)
    // ============================================================
    public function register_api_endpoints() {
        // Público
        register_rest_route('petsgo/v1','/products',['methods'=>'GET','callback'=>[$this,'api_get_products'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/products/(?P<id>\d+)',['methods'=>'GET','callback'=>[$this,'api_get_product_detail'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/vendors',['methods'=>'GET','callback'=>[$this,'api_get_vendors'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/vendors/(?P<id>\d+)',['methods'=>'GET','callback'=>[$this,'api_get_vendor_detail'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/plans',['methods'=>'GET','callback'=>[$this,'api_get_plans'],'permission_callback'=>'__return_true']);
        // Auth
        register_rest_route('petsgo/v1','/auth/login',['methods'=>'POST','callback'=>[$this,'api_login'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/register',['methods'=>'POST','callback'=>[$this,'api_register'],'permission_callback'=>'__return_true']);
        // Cliente
        register_rest_route('petsgo/v1','/orders',['methods'=>'POST','callback'=>[$this,'api_create_order'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/orders/mine',['methods'=>'GET','callback'=>[$this,'api_get_my_orders'],'permission_callback'=>function(){return is_user_logged_in();}]);
        // Vendor
        register_rest_route('petsgo/v1','/vendor/inventory',['methods'=>['GET','POST'],'callback'=>[$this,'api_vendor_inventory'],'permission_callback'=>[$this,'check_vendor_role']]);
        register_rest_route('petsgo/v1','/vendor/dashboard',['methods'=>'GET','callback'=>[$this,'api_vendor_dashboard'],'permission_callback'=>[$this,'check_vendor_role']]);
        // Admin
        register_rest_route('petsgo/v1','/admin/dashboard',['methods'=>'GET','callback'=>[$this,'api_admin_dashboard'],'permission_callback'=>function(){return current_user_can('administrator');}]);
        // Invoice QR Validation (public)
        register_rest_route('petsgo/v1','/invoice/validate/(?P<token>[a-f0-9\-]+)',['methods'=>'GET','callback'=>[$this,'api_validate_invoice'],'permission_callback'=>'__return_true']);
    }

    // --- API Productos ---
    public function api_get_products($request) {
        global $wpdb;
        $sql="SELECT i.*,v.store_name,v.logo_url FROM {$wpdb->prefix}petsgo_inventory i JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE 1=1";$args=[];
        if($vid=$request->get_param('vendor_id')){$sql.=" AND i.vendor_id=%d";$args[]=$vid;}
        if($cat=$request->get_param('category')){if($cat!=='Todos'){$sql.=" AND i.category=%s";$args[]=$cat;}}
        if($s=$request->get_param('search')){$sql.=" AND i.product_name LIKE %s";$args[]='%'.$wpdb->esc_like($s).'%';}
        if($args) $sql=$wpdb->prepare($sql,...$args);
        $products=$wpdb->get_results($sql);
        return rest_ensure_response(['data'=>array_map(function($p){return['id'=>(int)$p->id,'product_name'=>$p->product_name,'price'=>(float)$p->price,'stock'=>(int)$p->stock,'category'=>$p->category,'store_name'=>$p->store_name,'logo_url'=>$p->logo_url,'rating'=>4.8,'image_url'=>$p->image_id?wp_get_attachment_url($p->image_id):null];},$products)]);
    }
    public function api_get_product_detail($request) {
        global $wpdb;$id=$request->get_param('id');
        $p=$wpdb->get_row($wpdb->prepare("SELECT i.*,v.store_name,v.logo_url FROM {$wpdb->prefix}petsgo_inventory i JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE i.id=%d",$id));
        if(!$p) return new WP_Error('not_found','Producto no encontrado',['status'=>404]);
        return rest_ensure_response(['id'=>(int)$p->id,'product_name'=>$p->product_name,'price'=>(float)$p->price,'stock'=>(int)$p->stock,'category'=>$p->category,'store_name'=>$p->store_name,'logo_url'=>$p->logo_url,'description'=>$p->description,'image_url'=>$p->image_id?wp_get_attachment_url($p->image_id):null]);
    }
    // --- API Vendors ---
    public function api_get_vendors() { global $wpdb; return rest_ensure_response(['data'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE status='active'")]); }
    public function api_get_vendor_detail($request) { global $wpdb;$v=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d",$request->get_param('id')));if(!$v) return new WP_Error('not_found','No encontrada',['status'=>404]);return rest_ensure_response($v); }
    // --- API Plans ---
    public function api_get_plans() {
        global $wpdb;$plans=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_subscriptions");
        if(empty($plans)) $plans=[['id'=>1,'plan_name'=>'Básico','monthly_price'=>19990,'features'=>['Hasta 50 productos','Comisión 10%']],['id'=>2,'plan_name'=>'Pro','monthly_price'=>39990,'features'=>['Hasta 500 productos','Comisión 7%']]];
        return rest_ensure_response(['data'=>$plans]);
    }
    // --- API Auth ---
    public function api_login($request) {
        $p=$request->get_json_params();$user=wp_signon(['user_login'=>$p['username'],'user_password'=>$p['password'],'remember'=>true],false);
        if(is_wp_error($user)) return new WP_Error('auth_failed','Credenciales inválidas',['status'=>401]);
        $role='customer';if(in_array('administrator',$user->roles))$role='admin';elseif(in_array('petsgo_vendor',$user->roles))$role='vendor';elseif(in_array('petsgo_rider',$user->roles))$role='rider';
        $this->audit('login', 'login', $user->ID, $user->user_login . ' (' . $role . ')');
        return rest_ensure_response(['token'=>'session_cookie','user'=>['id'=>$user->ID,'username'=>$user->user_login,'displayName'=>$user->display_name,'email'=>$user->user_email,'role'=>$role]]);
    }
    public function api_register($request) {$p=$request->get_json_params();$uid=wp_create_user($p['username'],$p['password'],$p['email']);if(is_wp_error($uid)) return new WP_Error('register_failed',$uid->get_error_message(),['status'=>400]);return rest_ensure_response(['message'=>'Usuario creado']);}
    // --- API Orders ---
    public function api_create_order($request) {
        global $wpdb;$uid=get_current_user_id();$p=$request->get_json_params();
        if(!isset($p['vendor_id'],$p['items'],$p['total'])) return new WP_Error('missing','Faltan datos',['status'=>400]);
        $vendor=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d",$p['vendor_id']));
        if(!$vendor) return new WP_Error('invalid','Vendedor no existe',['status'=>404]);
        $total=floatval($p['total']);$comm=round($total*(floatval($vendor->sales_commission)/100),2);$del=floatval($p['delivery_fee']??0);
        $wpdb->insert("{$wpdb->prefix}petsgo_orders",['customer_id'=>$uid,'vendor_id'=>$p['vendor_id'],'total_amount'=>$total,'petsgo_commission'=>$comm,'delivery_fee'=>$del,'status'=>'pending'],['%d','%d','%f','%f','%f','%s']);
        $order_id = $wpdb->insert_id;
        $this->audit('order_create', 'order', $order_id, 'Total: $'.number_format($total,0,',','.'));

        // Auto-generate invoice
        $this->auto_generate_invoice($order_id);

        return rest_ensure_response(['order_id'=>$order_id,'message'=>'Orden creada','commission_logged'=>$comm]);
    }
    public function api_get_my_orders() {global $wpdb;$uid=get_current_user_id();return rest_ensure_response(['data'=>$wpdb->get_results($wpdb->prepare("SELECT o.*,v.store_name FROM {$wpdb->prefix}petsgo_orders o JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id WHERE o.customer_id=%d ORDER BY o.created_at DESC",$uid))]);}
    // --- API Vendor Dashboard ---
    public function check_vendor_role() { $u=wp_get_current_user(); return in_array('petsgo_vendor',(array)$u->roles)||in_array('administrator',(array)$u->roles); }
    public function api_vendor_inventory($request) {
        global $wpdb;$uid=get_current_user_id();$vid=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_vendors WHERE user_id=%d",$uid));
        if(!$vid&&!current_user_can('administrator')) return new WP_Error('no_vendor','Sin tienda',['status'=>403]);
        if($request->get_method()==='GET') return rest_ensure_response(['data'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id=%d",$vid))]);
        $p=$request->get_json_params();$wpdb->insert("{$wpdb->prefix}petsgo_inventory",['vendor_id'=>$vid,'product_name'=>$p['product_name'],'price'=>$p['price'],'stock'=>$p['stock'],'category'=>$p['category']]);
        return rest_ensure_response(['id'=>$wpdb->insert_id,'message'=>'Producto agregado']);
    }
    public function api_vendor_dashboard() {
        global $wpdb;$uid=get_current_user_id();$vid=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_vendors WHERE user_id=%d",$uid));
        if(!$vid) return new WP_Error('no_vendor','Sin tienda',['status'=>403]);
        return rest_ensure_response(['sales'=>(float)$wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d AND status='delivered'",$vid)),'pending_orders'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d AND status='pending'",$vid)),'store_status'=>'active']);
    }
    public function api_admin_dashboard() {
        global $wpdb;
        return rest_ensure_response(['total_sales'=>(float)$wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}petsgo_orders WHERE status='delivered'"),'total_commissions'=>(float)$wpdb->get_var("SELECT SUM(petsgo_commission) FROM {$wpdb->prefix}petsgo_orders WHERE status='delivered'"),'active_vendors'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_vendors")]);
    }

    // --- API Invoice QR Validation ---
    public function api_validate_invoice($request) {
        global $wpdb;
        $token = sanitize_text_field($request->get_param('token'));
        $inv = $wpdb->get_row($wpdb->prepare(
            "SELECT inv.*, o.total_amount, o.status AS order_status, v.store_name, v.rut AS vendor_rut, u.display_name AS customer_name
             FROM {$wpdb->prefix}petsgo_invoices inv
             JOIN {$wpdb->prefix}petsgo_orders o ON inv.order_id = o.id
             JOIN {$wpdb->prefix}petsgo_vendors v ON inv.vendor_id = v.id
             LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
             WHERE inv.qr_token = %s", $token));
        if (!$inv) return new WP_Error('not_found', 'Boleta no encontrada o token inválido', ['status' => 404]);
        return rest_ensure_response([
            'valid' => true,
            'invoice_number' => $inv->invoice_number,
            'store_name' => $inv->store_name,
            'vendor_rut' => $inv->vendor_rut,
            'customer_name' => $inv->customer_name,
            'total' => (float)$inv->total_amount,
            'order_status' => $inv->order_status,
            'issued_at' => $inv->created_at,
            'message' => 'Boleta válida y verificada por PetsGo.'
        ]);
    }
}

new PetsGo_Core();
