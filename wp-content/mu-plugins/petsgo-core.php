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
        add_action('wp_ajax_petsgo_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_petsgo_get_product', [$this, 'ajax_get_product']);
        add_action('wp_ajax_petsgo_save_product', [$this, 'ajax_save_product']);
        add_action('wp_ajax_petsgo_delete_product', [$this, 'ajax_delete_product']);
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
        add_submenu_page(null, 'Agregar/Editar Producto', 'Agregar Producto', 'manage_options', 'petsgo-product-form', [$this, 'admin_page_product_form']);
        add_submenu_page('petsgo-dashboard', 'Tiendas / Vendors', 'Tiendas', 'manage_options', 'petsgo-vendors', [$this, 'admin_page_vendors']);
        add_submenu_page('petsgo-dashboard', 'Pedidos', 'Pedidos', 'manage_options', 'petsgo-orders', [$this, 'admin_page_orders']);
        add_submenu_page('petsgo-dashboard', 'Planes', 'Planes', 'manage_options', 'petsgo-plans', [$this, 'admin_page_plans']);
    }

    public function admin_styles($hook) {
        if (strpos($hook, 'petsgo') === false) return;
        // Cargar jQuery UI para el datepicker si se necesita
        wp_enqueue_media(); // Media uploader de WordPress
        echo '<style>
            .petsgo-wrap { max-width: 1200px; }
            .petsgo-wrap h1 { color: #00A8E8; }
            .petsgo-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0; }
            .petsgo-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center; }
            .petsgo-card h2 { font-size: 32px; margin: 0; color: #00A8E8; }
            .petsgo-card p { color: #666; margin: 8px 0 0; }
            .petsgo-table { border-collapse: collapse; width: 100%; background: #fff; }
            .petsgo-table th { background: #00A8E8; color: #fff; padding: 10px 14px; text-align: left; }
            .petsgo-table td { padding: 10px 14px; border-bottom: 1px solid #eee; vertical-align: middle; }
            .petsgo-table tr:hover td { background: #f0faff; }
            .petsgo-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .petsgo-badge.active, .petsgo-badge.delivered { background: #d4edda; color: #155724; }
            .petsgo-badge.pending { background: #fff3cd; color: #856404; }
            .petsgo-badge.processing, .petsgo-badge.in_transit { background: #cce5ff; color: #004085; }
            .petsgo-badge.cancelled { background: #f8d7da; color: #721c24; }
            .petsgo-badge.inactive { background: #e2e3e5; color: #383d41; }
            .petsgo-btn { display: inline-block; padding: 6px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; transition: .2s; }
            .petsgo-btn-primary { background: #00A8E8; color: #fff; }
            .petsgo-btn-primary:hover { background: #0090c7; color: #fff; }
            .petsgo-btn-warning { background: #FFC400; color: #2F3A40; }
            .petsgo-btn-warning:hover { background: #e6b000; color: #2F3A40; }
            .petsgo-btn-danger { background: #dc3545; color: #fff; }
            .petsgo-btn-danger:hover { background: #c82333; color: #fff; }
            .petsgo-btn-sm { padding: 4px 10px; font-size: 12px; }
            .petsgo-search-bar { display: flex; gap: 10px; margin: 16px 0; align-items: center; flex-wrap: wrap; }
            .petsgo-search-bar input, .petsgo-search-bar select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
            .petsgo-search-bar input[type=text] { min-width: 280px; }
            .petsgo-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
            .petsgo-stock-low { color: #dc3545; font-weight: 700; }
            .petsgo-stock-ok { color: #28a745; }
            /* --- Product Form Page --- */
            .petsgo-form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 16px; }
            .petsgo-form-section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
            .petsgo-form-section h3 { margin-top: 0; color: #2F3A40; border-bottom: 2px solid #00A8E8; padding-bottom: 8px; }
            .petsgo-field { margin-bottom: 16px; }
            .petsgo-field label { display: block; font-weight: 600; margin-bottom: 4px; color: #333; font-size: 13px; }
            .petsgo-field input, .petsgo-field select, .petsgo-field textarea { width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
            .petsgo-field textarea { resize: vertical; min-height: 80px; }
            .petsgo-field .field-hint { font-size: 11px; color: #888; margin-top: 3px; }
            .petsgo-field .field-error { font-size: 12px; color: #dc3545; margin-top: 3px; display: none; }
            .petsgo-field.has-error input, .petsgo-field.has-error select, .petsgo-field.has-error textarea { border-color: #dc3545; }
            .petsgo-field.has-error .field-error { display: block; }
            .petsgo-img-upload { display: flex; gap: 16px; flex-wrap: wrap; }
            .petsgo-img-slot { width: 160px; height: 160px; border: 2px dashed #ccc; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; background: #fafafa; transition: .2s; }
            .petsgo-img-slot:hover { border-color: #00A8E8; background: #f0faff; }
            .petsgo-img-slot img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }
            .petsgo-img-slot .slot-label { font-size: 12px; color: #888; text-align: center; pointer-events: none; }
            .petsgo-img-slot .slot-label .dashicons { font-size: 28px; display: block; margin: 0 auto 4px; color: #bbb; }
            .petsgo-img-slot .remove-img { position: absolute; top: 4px; right: 4px; background: rgba(220,53,69,.9); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 14px; cursor: pointer; display: none; z-index: 2; line-height: 20px; text-align: center; }
            .petsgo-img-slot.has-image .remove-img { display: block; }
            .petsgo-img-slot.has-image .slot-label { display: none; }
            .petsgo-img-specs { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 10px 14px; margin-top: 10px; font-size: 12px; color: #555; }
            .petsgo-img-specs strong { color: #333; }
            .petsgo-preview-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
            .petsgo-preview-card .preview-imgs { display: flex; gap: 4px; height: 180px; background: #f5f5f5; overflow: hidden; }
            .petsgo-preview-card .preview-imgs img { flex: 1; object-fit: cover; min-width: 0; }
            .petsgo-preview-card .preview-imgs .no-img { flex: 1; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: 40px; }
            .petsgo-preview-card .preview-body { padding: 16px; }
            .petsgo-preview-card .preview-body h4 { margin: 0 0 6px; font-size: 16px; color: #2F3A40; }
            .petsgo-preview-card .preview-body .preview-price { font-size: 22px; font-weight: 700; color: #00A8E8; }
            .petsgo-preview-card .preview-body .preview-cat { display: inline-block; background: #e3f5fc; color: #00A8E8; font-size: 11px; padding: 2px 8px; border-radius: 10px; margin-top: 6px; }
            .petsgo-preview-card .preview-body .preview-desc { font-size: 13px; color: #666; margin-top: 8px; line-height: 1.4; }
            .petsgo-preview-card .preview-body .preview-stock { font-size: 12px; margin-top: 6px; }
            .petsgo-loader { display: none; }
            .petsgo-loader.active { display: inline-block; }
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

    /* --- PRODUCTOS (Lista con AJAX search) --- */
    public function admin_page_products() {
        global $wpdb;
        $vendors = $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name");
        $categories = ['Alimento','Juguetes','Salud','Accesorios','Higiene','Ropa','Camas','Transporte'];
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory");
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🛒 Productos (<span id="pg-total"><?php echo $total; ?></span>)</h1>

            <div class="petsgo-search-bar">
                <input type="text" id="pg-search" placeholder="🔍 Buscar por nombre..." autocomplete="off">
                <select id="pg-filter-cat">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $c): ?><option><?php echo $c; ?></option><?php endforeach; ?>
                </select>
                <select id="pg-filter-vendor">
                    <option value="">Todas las tiendas</option>
                    <?php foreach ($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <span class="petsgo-loader" id="pg-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-product-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nuevo Producto</a>
            </div>

            <table class="petsgo-table" id="pg-products-table">
                <thead>
                    <tr>
                        <th style="width:50px;">Foto</th>
                        <th>Producto</th>
                        <th style="width:100px;">Precio</th>
                        <th style="width:70px;">Stock</th>
                        <th>Categoría</th>
                        <th>Tienda</th>
                        <th style="width:150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="pg-products-body">
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">Cargando productos...</td></tr>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(function($){
            var timer = null;
            function loadProducts(){
                $('#pg-loader').addClass('active');
                $.post(ajaxurl, {
                    action: 'petsgo_search_products',
                    _ajax_nonce: '<?php echo wp_create_nonce("petsgo_ajax"); ?>',
                    search: $('#pg-search').val(),
                    category: $('#pg-filter-cat').val(),
                    vendor_id: $('#pg-filter-vendor').val()
                }, function(res){
                    $('#pg-loader').removeClass('active');
                    if(!res.success) return;
                    var rows = '';
                    $('#pg-total').text(res.data.length);
                    if(res.data.length === 0){
                        rows = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">No se encontraron productos.</td></tr>';
                    }
                    $.each(res.data, function(i, p){
                        var thumb = p.image_url ? '<img src="'+p.image_url+'" class="petsgo-thumb">' : '<span style="display:inline-block;width:50px;height:50px;background:#f0f0f0;border-radius:6px;text-align:center;line-height:50px;color:#bbb;">📷</span>';
                        var stockClass = p.stock < 5 ? 'petsgo-stock-low' : 'petsgo-stock-ok';
                        rows += '<tr>';
                        rows += '<td>' + thumb + '</td>';
                        rows += '<td><strong>' + $('<span>').text(p.product_name).html() + '</strong>';
                        if(p.description) rows += '<br><small style="color:#888;">' + $('<span>').text(p.description.substring(0,60)).html() + (p.description.length > 60 ? '...' : '') + '</small>';
                        rows += '</td>';
                        rows += '<td>$' + Number(p.price).toLocaleString('es-CL') + '</td>';
                        rows += '<td class="'+stockClass+'">' + p.stock + '</td>';
                        rows += '<td>' + $('<span>').text(p.category || '—').html() + '</td>';
                        rows += '<td>' + $('<span>').text(p.store_name || '—').html() + '</td>';
                        rows += '<td>';
                        rows += '<a href="<?php echo admin_url("admin.php?page=petsgo-product-form&id="); ?>' + p.id + '" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm">✏️ Editar</a> ';
                        rows += '<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pg-delete-btn" data-id="'+p.id+'">🗑️</button>';
                        rows += '</td>';
                        rows += '</tr>';
                    });
                    $('#pg-products-body').html(rows);
                });
            }

            // Búsqueda en tiempo real (debounce 300ms)
            $('#pg-search').on('input', function(){ clearTimeout(timer); timer = setTimeout(loadProducts, 300); });
            $('#pg-filter-cat, #pg-filter-vendor').on('change', loadProducts);

            // Eliminar producto
            $(document).on('click', '.pg-delete-btn', function(){
                if(!confirm('¿Seguro que deseas eliminar este producto?')) return;
                var btn = $(this);
                $.post(ajaxurl, {
                    action: 'petsgo_delete_product',
                    _ajax_nonce: '<?php echo wp_create_nonce("petsgo_ajax"); ?>',
                    id: btn.data('id')
                }, function(res){
                    if(res.success) loadProducts();
                    else alert(res.data || 'Error al eliminar');
                });
            });

            // Carga inicial
            loadProducts();
        });
        </script>
        <?php
    }

    /* --- FORMULARIO AGREGAR / EDITAR PRODUCTO (página separada) --- */
    public function admin_page_product_form() {
        global $wpdb;
        $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $product = null;
        $images = ['', '', ''];

        if ($product_id) {
            $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_inventory WHERE id = %d", $product_id));
            if ($product) {
                $images[0] = $product->image_id ? wp_get_attachment_url($product->image_id) : '';
                $images[1] = $product->image_id_2 ? wp_get_attachment_url($product->image_id_2) : '';
                $images[2] = $product->image_id_3 ? wp_get_attachment_url($product->image_id_3) : '';
            }
        }

        $vendors = $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name");
        $categories = ['Alimento','Juguetes','Salud','Accesorios','Higiene','Ropa','Camas','Transporte'];
        $page_title = $product_id ? 'Editar Producto #' . $product_id : 'Nuevo Producto';
        ?>
        <div class="wrap petsgo-wrap">
            <h1>🛒 <?php echo $page_title; ?></h1>
            <a href="<?php echo admin_url('admin.php?page=petsgo-products'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" style="margin-bottom:16px; display:inline-block;">← Volver a Productos</a>

            <form id="petsgo-product-form" novalidate>
                <input type="hidden" id="pf-id" value="<?php echo $product_id; ?>">
                <?php wp_nonce_field('petsgo_ajax', 'pf-nonce'); ?>

                <div class="petsgo-form-grid">
                    <!-- COLUMNA IZQUIERDA: Datos -->
                    <div>
                        <div class="petsgo-form-section">
                            <h3>📝 Información del Producto</h3>

                            <div class="petsgo-field" id="field-name">
                                <label for="pf-name">Nombre del producto *</label>
                                <input type="text" id="pf-name" maxlength="255" placeholder="Ej: Royal Canin Adulto 15kg" value="<?php echo esc_attr($product->product_name ?? ''); ?>">
                                <div class="field-hint">Máx. 255 caracteres. Sé descriptivo con marca y presentación.</div>
                                <div class="field-error">El nombre es obligatorio (mín. 3 caracteres).</div>
                            </div>

                            <div class="petsgo-field" id="field-desc">
                                <label for="pf-desc">Descripción *</label>
                                <textarea id="pf-desc" maxlength="1000" rows="4" placeholder="Describe el producto, sus beneficios y características..."><?php echo esc_textarea($product->description ?? ''); ?></textarea>
                                <div class="field-hint">Máx. 1000 caracteres. Incluye materiales, tamaños, ingredientes relevantes.</div>
                                <div class="field-error">La descripción es obligatoria (mín. 10 caracteres).</div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="petsgo-field" id="field-price">
                                    <label for="pf-price">Precio (CLP) *</label>
                                    <input type="number" id="pf-price" min="1" max="99999999" step="1" placeholder="Ej: 25990" value="<?php echo esc_attr($product->price ?? ''); ?>">
                                    <div class="field-error">Precio debe ser mayor a 0.</div>
                                </div>
                                <div class="petsgo-field" id="field-stock">
                                    <label for="pf-stock">Stock (unidades) *</label>
                                    <input type="number" id="pf-stock" min="0" max="999999" step="1" placeholder="Ej: 50" value="<?php echo esc_attr($product->stock ?? ''); ?>">
                                    <div class="field-error">Stock debe ser 0 o mayor.</div>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="petsgo-field" id="field-category">
                                    <label for="pf-category">Categoría *</label>
                                    <select id="pf-category">
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($categories as $c): ?>
                                        <option <?php selected(($product->category ?? ''), $c); ?>><?php echo $c; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-error">Selecciona una categoría.</div>
                                </div>
                                <div class="petsgo-field" id="field-vendor">
                                    <label for="pf-vendor">Tienda *</label>
                                    <select id="pf-vendor">
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($vendors as $v): ?>
                                        <option value="<?php echo $v->id; ?>" <?php selected(($product->vendor_id ?? ''), $v->id); ?>><?php echo esc_html($v->store_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-error">Selecciona una tienda.</div>
                                </div>
                            </div>
                        </div>

                        <!-- FOTOS -->
                        <div class="petsgo-form-section" style="margin-top:20px;">
                            <h3>📸 Fotos del Producto (máx. 3)</h3>
                            <div class="petsgo-img-upload">
                                <?php for ($i = 0; $i < 3; $i++): 
                                    $label = $i === 0 ? 'Foto Principal *' : 'Foto ' . ($i + 1) . ' (opcional)';
                                    $has = !empty($images[$i]);
                                ?>
                                <div class="petsgo-img-slot <?php echo $has ? 'has-image' : ''; ?>" id="img-slot-<?php echo $i; ?>" data-index="<?php echo $i; ?>">
                                    <?php if ($has): ?><img src="<?php echo esc_url($images[$i]); ?>"><?php endif; ?>
                                    <span class="slot-label"><span class="dashicons dashicons-cloud-upload"></span><?php echo $label; ?></span>
                                    <button type="button" class="remove-img" title="Quitar imagen">×</button>
                                    <input type="hidden" class="img-id-input" id="pf-image-<?php echo $i; ?>" value="<?php 
                                        if ($i === 0) echo esc_attr($product->image_id ?? '');
                                        elseif ($i === 1) echo esc_attr($product->image_id_2 ?? '');
                                        else echo esc_attr($product->image_id_3 ?? '');
                                    ?>">
                                </div>
                                <?php endfor; ?>
                            </div>
                            <div class="petsgo-img-specs">
                                <strong>📐 Especificaciones de imagen:</strong><br>
                                • <strong>Formato:</strong> JPG, PNG o WebP<br>
                                • <strong>Dimensiones recomendadas:</strong> 800×800 px (cuadrada) — Mínimo: 400×400 px — Máximo: 2000×2000 px<br>
                                • <strong>Peso máximo:</strong> 2 MB por imagen<br>
                                • <strong>Fondo:</strong> Preferiblemente blanco o neutro para mejor visualización
                            </div>
                            <div class="field-error" id="img-error" style="display:none;">La foto principal es obligatoria.</div>
                        </div>

                        <!-- BOTONES -->
                        <div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
                            <button type="submit" class="petsgo-btn petsgo-btn-primary" style="font-size:15px; padding:10px 30px;">
                                <?php echo $product_id ? '💾 Guardar Cambios' : '✅ Crear Producto'; ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=petsgo-products'); ?>" class="petsgo-btn" style="background:#e2e3e5; color:#333;">Cancelar</a>
                            <span class="petsgo-loader" id="pf-loader"><span class="spinner is-active" style="float:none; margin:0;"></span> Guardando...</span>
                            <div id="pf-message" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: Vista Previa -->
                    <div>
                        <div class="petsgo-form-section" style="position:sticky; top:40px;">
                            <h3>👁️ Vista Previa</h3>
                            <div class="petsgo-preview-card" id="pf-preview">
                                <div class="preview-imgs" id="preview-imgs">
                                    <div class="no-img">📷</div>
                                </div>
                                <div class="preview-body">
                                    <span class="preview-cat" id="preview-cat">Categoría</span>
                                    <h4 id="preview-name">Nombre del producto</h4>
                                    <div class="preview-price" id="preview-price">$0</div>
                                    <div class="preview-desc" id="preview-desc">Descripción del producto...</div>
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
            // --- Media Uploader ---
            $('.petsgo-img-slot').on('click', function(e){
                if($(e.target).hasClass('remove-img')) return;
                var slot = $(this);
                var idx = slot.data('index');
                var frame = wp.media({
                    title: 'Seleccionar imagen del producto',
                    button: { text: 'Usar esta imagen' },
                    multiple: false,
                    library: { type: ['image/jpeg','image/png','image/webp'] }
                });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    // Validar dimensiones
                    if(att.width < 400 || att.height < 400){
                        alert('⚠️ La imagen es muy pequeña. Mínimo 400×400 px.\nActual: ' + att.width + '×' + att.height + ' px');
                        return;
                    }
                    if(att.width > 2000 || att.height > 2000){
                        alert('⚠️ La imagen es muy grande. Máximo 2000×2000 px.\nActual: ' + att.width + '×' + att.height + ' px');
                        return;
                    }
                    if(att.filesizeInBytes > 2 * 1024 * 1024){
                        alert('⚠️ La imagen pesa más de 2 MB (' + (att.filesizeInBytes / 1024 / 1024).toFixed(1) + ' MB).');
                        return;
                    }
                    var allowed = ['image/jpeg','image/png','image/webp'];
                    if(allowed.indexOf(att.mime) === -1){
                        alert('⚠️ Formato no válido. Solo JPG, PNG o WebP.');
                        return;
                    }
                    // Preview
                    var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    slot.find('img').remove();
                    slot.prepend('<img src="'+url+'">');
                    slot.addClass('has-image');
                    slot.find('.img-id-input').val(att.id);
                    updatePreview();
                });
                frame.open();
            });

            // Quitar imagen
            $('.remove-img').on('click', function(e){
                e.stopPropagation();
                var slot = $(this).closest('.petsgo-img-slot');
                slot.find('img').remove();
                slot.removeClass('has-image');
                slot.find('.img-id-input').val('');
                updatePreview();
            });

            // --- Vista previa en tiempo real ---
            function updatePreview(){
                var name = $('#pf-name').val() || 'Nombre del producto';
                var price = parseFloat($('#pf-price').val()) || 0;
                var cat = $('#pf-category').val() || 'Categoría';
                var desc = $('#pf-desc').val() || 'Descripción del producto...';
                var stock = $('#pf-stock').val();

                $('#preview-name').text(name);
                $('#preview-price').text('$' + price.toLocaleString('es-CL'));
                $('#preview-cat').text(cat);
                $('#preview-desc').text(desc.length > 120 ? desc.substring(0, 120) + '...' : desc);

                if(stock === '' || stock === undefined){
                    $('#preview-stock').html('Stock: —');
                } else {
                    var s = parseInt(stock);
                    var color = s < 5 ? '#dc3545' : '#28a745';
                    $('#preview-stock').html('Stock: <strong style="color:'+color+';">' + s + ' unidades</strong>');
                }

                // Imágenes
                var imgs = '';
                $('.petsgo-img-slot').each(function(){
                    var img = $(this).find('img');
                    if(img.length) imgs += '<img src="'+img.attr('src')+'">';
                });
                if(imgs){
                    $('#preview-imgs').html(imgs);
                } else {
                    $('#preview-imgs').html('<div class="no-img">📷</div>');
                }
            }

            $('#pf-name, #pf-price, #pf-stock, #pf-category, #pf-desc').on('input change', updatePreview);
            updatePreview(); // init

            // --- Validación ---
            function validate(){
                var ok = true;
                // Nombre
                var name = $.trim($('#pf-name').val());
                if(name.length < 3){ 
                    $('#field-name').addClass('has-error'); ok = false; 
                } else { 
                    $('#field-name').removeClass('has-error'); 
                }
                // Descripcion
                var desc = $.trim($('#pf-desc').val());
                if(desc.length < 10){ 
                    $('#field-desc').addClass('has-error'); ok = false; 
                } else { 
                    $('#field-desc').removeClass('has-error'); 
                }
                // Precio
                var price = parseFloat($('#pf-price').val());
                if(!price || price <= 0){ 
                    $('#field-price').addClass('has-error'); ok = false; 
                } else { 
                    $('#field-price').removeClass('has-error'); 
                }
                // Stock
                var stock = $('#pf-stock').val();
                if(stock === '' || parseInt(stock) < 0){ 
                    $('#field-stock').addClass('has-error'); ok = false; 
                } else { 
                    $('#field-stock').removeClass('has-error'); 
                }
                // Categoría
                if(!$('#pf-category').val()){ 
                    $('#field-category').addClass('has-error'); ok = false; 
                } else { 
                    $('#field-category').removeClass('has-error'); 
                }
                // Vendor
                if(!$('#pf-vendor').val()){ 
                    $('#field-vendor').addClass('has-error'); ok = false; 
                } else { 
                    $('#field-vendor').removeClass('has-error'); 
                }
                // Foto principal
                if(!$('#pf-image-0').val()){
                    $('#img-error').show(); ok = false;
                } else {
                    $('#img-error').hide();
                }
                return ok;
            }

            // Quitar error al interactuar
            $('.petsgo-field input, .petsgo-field select, .petsgo-field textarea').on('input change', function(){
                $(this).closest('.petsgo-field').removeClass('has-error');
            });

            // --- Submit (AJAX) ---
            $('#petsgo-product-form').on('submit', function(e){
                e.preventDefault();
                if(!validate()) return;

                $('#pf-loader').addClass('active');
                $('#pf-message').hide();

                $.post(ajaxurl, {
                    action: 'petsgo_save_product',
                    _ajax_nonce: $('#pf-nonce').val(),
                    id:           $('#pf-id').val(),
                    product_name: $.trim($('#pf-name').val()),
                    description:  $.trim($('#pf-desc').val()),
                    price:        $('#pf-price').val(),
                    stock:        $('#pf-stock').val(),
                    category:     $('#pf-category').val(),
                    vendor_id:    $('#pf-vendor').val(),
                    image_id:     $('#pf-image-0').val(),
                    image_id_2:   $('#pf-image-1').val(),
                    image_id_3:   $('#pf-image-2').val()
                }, function(res){
                    $('#pf-loader').removeClass('active');
                    if(res.success){
                        var msg = '<div class="notice notice-success" style="padding:10px;"><p>✅ ' + res.data.message + '</p></div>';
                        $('#pf-message').html(msg).show();
                        if(!$('#pf-id').val() && res.data.id){
                            $('#pf-id').val(res.data.id);
                            // Update URL sin recargar
                            history.replaceState(null, '', '<?php echo admin_url("admin.php?page=petsgo-product-form&id="); ?>' + res.data.id);
                        }
                    } else {
                        var msg = '<div class="notice notice-error" style="padding:10px;"><p>❌ ' + (res.data || 'Error desconocido') + '</p></div>';
                        $('#pf-message').html(msg).show();
                    }
                }).fail(function(){
                    $('#pf-loader').removeClass('active');
                    $('#pf-message').html('<div class="notice notice-error" style="padding:10px;"><p>❌ Error de conexión</p></div>').show();
                });
            });
        });
        </script>
        <?php
    }

    /* === AJAX HANDLERS === */

    // Buscar productos (AJAX live search)
    public function ajax_search_products() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;

        $search    = sanitize_text_field($_POST['search'] ?? '');
        $category  = sanitize_text_field($_POST['category'] ?? '');
        $vendor_id = intval($_POST['vendor_id'] ?? 0);

        $sql = "SELECT i.*, v.store_name FROM {$wpdb->prefix}petsgo_inventory i 
                LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id = v.id WHERE 1=1";
        $args = [];

        if ($search) {
            $sql .= " AND i.product_name LIKE %s";
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }
        if ($category) {
            $sql .= " AND i.category = %s";
            $args[] = $category;
        }
        if ($vendor_id) {
            $sql .= " AND i.vendor_id = %d";
            $args[] = $vendor_id;
        }

        $sql .= " ORDER BY i.id DESC";

        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, ...$args);
        }

        $products = $wpdb->get_results($sql);

        $data = array_map(function($p) {
            return [
                'id'           => (int) $p->id,
                'product_name' => $p->product_name,
                'description'  => $p->description,
                'price'        => (float) $p->price,
                'stock'        => (int) $p->stock,
                'category'     => $p->category,
                'store_name'   => $p->store_name,
                'image_url'    => $p->image_id ? wp_get_attachment_image_url($p->image_id, 'thumbnail') : null,
            ];
        }, $products);

        wp_send_json_success($data);
    }

    // Obtener un producto (AJAX)
    public function ajax_get_product() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $id = intval($_POST['id']);
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_inventory WHERE id = %d", $id));
        if (!$p) wp_send_json_error('Producto no encontrado');
        wp_send_json_success($p);
    }

    // Guardar producto (crear o editar, AJAX)
    public function ajax_save_product() {
        check_ajax_referer('petsgo_ajax');
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);

        // Validación server-side
        $name  = sanitize_text_field($_POST['product_name'] ?? '');
        $desc  = sanitize_textarea_field($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $cat   = sanitize_text_field($_POST['category'] ?? '');
        $vendor = intval($_POST['vendor_id'] ?? 0);
        $img1  = intval($_POST['image_id'] ?? 0) ?: null;
        $img2  = intval($_POST['image_id_2'] ?? 0) ?: null;
        $img3  = intval($_POST['image_id_3'] ?? 0) ?: null;

        $errors = [];
        if (strlen($name) < 3) $errors[] = 'Nombre muy corto (mín. 3 caracteres)';
        if (strlen($desc) < 10) $errors[] = 'Descripción muy corta (mín. 10 caracteres)';
        if ($price <= 0) $errors[] = 'Precio debe ser mayor a 0';
        if ($stock < 0) $errors[] = 'Stock no puede ser negativo';
        if (!$cat) $errors[] = 'Categoría obligatoria';
        if (!$vendor) $errors[] = 'Tienda obligatoria';
        if (!$img1) $errors[] = 'Foto principal obligatoria';

        // Validar dimensiones de imágenes
        foreach ([$img1, $img2, $img3] as $img_id) {
            if ($img_id) {
                $meta = wp_get_attachment_metadata($img_id);
                if ($meta) {
                    if (($meta['width'] ?? 0) < 400 || ($meta['height'] ?? 0) < 400) {
                        $errors[] = 'Imagen ID ' . $img_id . ' es menor a 400×400 px';
                    }
                    if (($meta['width'] ?? 0) > 2000 || ($meta['height'] ?? 0) > 2000) {
                        $errors[] = 'Imagen ID ' . $img_id . ' excede 2000×2000 px';
                    }
                }
                $mime = get_post_mime_type($img_id);
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                if ($mime && !in_array($mime, $allowed_mimes)) {
                    $errors[] = 'Imagen ID ' . $img_id . ': formato no permitido (' . $mime . ')';
                }
            }
        }

        if (!empty($errors)) wp_send_json_error(implode('. ', $errors));

        $data = [
            'vendor_id'    => $vendor,
            'product_name' => $name,
            'description'  => $desc,
            'price'        => $price,
            'stock'        => $stock,
            'category'     => $cat,
            'image_id'     => $img1,
            'image_id_2'   => $img2,
            'image_id_3'   => $img3,
        ];

        if ($id) {
            $wpdb->update("{$wpdb->prefix}petsgo_inventory", $data, ['id' => $id]);
            wp_send_json_success(['message' => 'Producto actualizado correctamente', 'id' => $id]);
        } else {
            $wpdb->insert("{$wpdb->prefix}petsgo_inventory", $data);
            wp_send_json_success(['message' => 'Producto creado correctamente', 'id' => $wpdb->insert_id]);
        }
    }

    // Eliminar producto (AJAX)
    public function ajax_delete_product() {
        check_ajax_referer('petsgo_ajax');
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('ID inválido');
        $wpdb->delete("{$wpdb->prefix}petsgo_inventory", ['id' => $id]);
        wp_send_json_success(['message' => 'Producto eliminado']);
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