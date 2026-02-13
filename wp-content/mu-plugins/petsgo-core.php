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
        $allowed = ['http://localhost:5173', 'http://localhost:5174', 'http://localhost:5177', 'http://localhost:5176', 'http://localhost:3000'];
        if (in_array($origin, $allowed)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        } else {
            header('Access-Control-Allow-Origin: http://localhost:5173');
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

    private $db_version = '4.0';

    public function __construct() {
        add_action('init', [$this, 'register_roles']);
        add_action('init', [$this, 'ensure_subscription_columns']);
        add_action('init', [$this, 'ensure_rider_tables']);
        add_action('init', [$this, 'ensure_category_table']);
        add_action('init', [$this, 'ensure_ticket_tables']);
        add_action('init', [$this, 'schedule_renewal_cron']);
        add_action('petsgo_check_renewals', [$this, 'process_renewal_reminders']);
        add_filter('determine_current_user', [$this, 'resolve_api_token'], 20);
        add_filter('rest_authentication_errors', [$this, 'bypass_cookie_nonce_for_token'], 90);
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_footer', [$this, 'admin_ticket_notifier']);
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
            'petsgo_dashboard_data',
            'petsgo_save_settings',
            'petsgo_preview_email',
            'petsgo_search_pets',
            'petsgo_save_pet',
            'petsgo_delete_pet',
            'petsgo_search_leads',
            'petsgo_update_lead',
            'petsgo_download_demo_invoice',
            'petsgo_download_demo_subscription',
            'petsgo_search_rider_docs',
            'petsgo_review_rider_doc',
            'petsgo_search_categories',
            'petsgo_save_category',
            'petsgo_delete_category',
            'petsgo_search_tickets',
            'petsgo_update_ticket',
            'petsgo_assign_ticket',
            'petsgo_add_ticket_reply',
            'petsgo_ticket_count',
            'petsgo_save_legal',
            'petsgo_save_faqs',
            'petsgo_search_rider_payouts',
            'petsgo_process_payout',
            'petsgo_generate_weekly_payouts',
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
    // STOCK ALERT — Envía email cuando stock < 5 o == 0
    // ============================================================

    // ---- Validation helpers ----
    public static function validate_rut($rut) {
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        if (strlen($rut) < 8 || strlen($rut) > 9) return false;
        $body = substr($rut, 0, -1);
        $dv   = substr($rut, -1);
        $sum  = 0; $mul = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += intval($body[$i]) * $mul;
            $mul = $mul === 7 ? 2 : $mul + 1;
        }
        $expected = 11 - ($sum % 11);
        if ($expected === 11) $expected = '0';
        elseif ($expected === 10) $expected = 'K';
        else $expected = (string)$expected;
        return $dv === $expected;
    }
    public static function validate_chilean_phone($phone) {
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        // +569XXXXXXXX (12 chars) or 9XXXXXXXX (9 chars) or XXXXXXXX (8 digits only)
        if (preg_match('/^\+569\d{8}$/', $clean)) return true;
        if (preg_match('/^9\d{8}$/', $clean)) return true;
        if (preg_match('/^\d{8}$/', $clean)) return true;
        return false;
    }
    public static function normalize_phone($phone) {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) === 8) return '+569' . $clean;
        if (strlen($clean) === 9 && $clean[0] === '9') return '+56' . $clean;
        if (strlen($clean) === 11 && substr($clean, 0, 3) === '569') return '+' . $clean;
        return '+56' . $clean;
    }
    public static function validate_password_strength($pass) {
        $errors = [];
        if (strlen($pass) < 8) $errors[] = 'Mínimo 8 caracteres';
        if (!preg_match('/[A-Z]/', $pass)) $errors[] = 'Debe tener al menos una mayúscula';
        if (!preg_match('/[a-z]/', $pass)) $errors[] = 'Debe tener al menos una minúscula';
        if (!preg_match('/[0-9]/', $pass)) $errors[] = 'Debe tener al menos un número';
        if (!preg_match('/[^A-Za-z0-9]/', $pass)) $errors[] = 'Debe tener al menos un carácter especial';
        return $errors;
    }
    public static function format_rut($rut) {
        $clean = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        $dv = substr($clean, -1);
        $body = substr($clean, 0, -1);
        return number_format((int)$body, 0, '', '.') . '-' . $dv;
    }

    /** Validate name: only letters, spaces, accents, hyphens, apostrophes. Min 2 chars. */
    public static function validate_name($name) {
        $clean = trim($name);
        if (mb_strlen($clean) < 2) return false;
        // Allow letters (including accented), spaces, hyphens, apostrophes
        return (bool) preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\\s'-]+$/u", $clean);
    }

    /** Strip non-letter characters from a name (except spaces, hyphens, apostrophes) */
    public static function sanitize_name($name) {
        return preg_replace("/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\\s'-]/u", '', trim($name));
    }

    /** Detect common SQL injection patterns in a string. Returns true if suspicious. */
    public static function detect_sql_injection($value) {
        if (!is_string($value) || $value === '') return false;
        $patterns = [
            '/(\bUNION\b\s+(ALL\s+)?SELECT\b)/i',
            '/(\bSELECT\b\s+.*\bFROM\b)/i',
            '/(\bINSERT\b\s+INTO\b)/i',
            '/(\bUPDATE\b\s+\S+\s+SET\b)/i',
            '/(\bDELETE\b\s+FROM\b)/i',
            '/(\bDROP\b\s+(TABLE|DATABASE|INDEX|VIEW)\b)/i',
            '/(\bALTER\b\s+TABLE\b)/i',
            '/(\bCREATE\b\s+(TABLE|DATABASE|INDEX|VIEW|PROCEDURE|FUNCTION|TRIGGER)\b)/i',
            '/(\bEXEC(\s|UTE)?\b\s*\()/i',
            '/(\bTRUNCATE\b\s+TABLE\b)/i',
            '/(\bDECLARE\b\s+@)/i',
            '/(--|#|\/\*|\*\/)/i',
            '/(\bxp_\w+)/i',
            '/(\bsp_\w+)/i',
            '/(\bCHAR\s*\()/i',
            '/(\bCONCAT\s*\()/i',
            '/(\bSLEEP\s*\()/i',
            '/(\bBENCHMARK\s*\()/i',
            '/(\bLOAD_FILE\s*\()/i',
            '/(\bINFORMATION_SCHEMA\b)/i',
            '/(\bOUTFILE\b)/i',
            "/('+\s*(OR|AND)\s+')/i",
            "/('+\s*=\s*')/i",
            '/0x[0-9a-fA-F]{4,}/',
            '/(;\s*(DROP|DELETE|UPDATE|INSERT|ALTER|CREATE|EXEC|SELECT)\b)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) return true;
        }
        return false;
    }

    /** Scan array of form values for SQL injection. Returns error string or empty. */
    public static function check_form_sql_injection($fields) {
        foreach ($fields as $key => $val) {
            if (is_string($val) && self::detect_sql_injection($val)) {
                return 'Se detectaron caracteres no permitidos en los datos enviados. Por favor revisa el formulario.';
            }
        }
        return '';
    }

    /**
     * IP-based rate limiting for public endpoints.
     * @param string $action   Unique key for the action (e.g. 'register', 'register_rider', 'vendor_lead')
     * @param int    $max      Max allowed requests in $window seconds (default 3)
     * @param int    $window   Time window in seconds to count requests (default 1200 = 20 min)
     * @param int    $cooldown Cooldown in seconds after exceeding limit (default 900 = 15 min)
     * @return WP_Error|null   Returns WP_Error if rate limited, null if OK
     */
    private function check_rate_limit($action, $max = 3, $window = 1200, $cooldown = 900) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip_hash = md5($ip . $action);
        $transient_key  = 'petsgo_rl_' . $ip_hash;
        $cooldown_key   = 'petsgo_cd_' . $ip_hash;

        // Check if currently in cooldown
        $cd = get_transient($cooldown_key);
        if ($cd) {
            $remaining = intval($cd) - time();
            if ($remaining <= 0) $remaining = $cooldown;
            $mins = ceil($remaining / 60);
            return new WP_Error(
                'rate_limited',
                "Demasiadas solicitudes desde tu dirección. Por favor espera {$mins} minutos e intenta nuevamente.",
                ['status' => 429]
            );
        }

        // Get current request log
        $log = get_transient($transient_key);
        if (!is_array($log)) $log = [];

        $now = time();
        // Filter to only requests within the window
        $log = array_filter($log, function($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        });

        if (count($log) >= $max) {
            // Set cooldown
            set_transient($cooldown_key, $now + $cooldown, $cooldown);
            $mins = ceil($cooldown / 60);
            $this->audit('rate_limit', 'security', 0, "IP {$ip} bloqueada {$mins}min en {$action} ({$max} solicitudes en " . ($window/60) . "min)");
            return new WP_Error(
                'rate_limited',
                "Has excedido el límite de solicitudes. Por favor espera {$mins} minutos e intenta nuevamente.",
                ['status' => 429]
            );
        }

        // Log this request
        $log[] = $now;
        set_transient($transient_key, $log, $window);

        return null;
    }

    private function check_stock_alert($product_id) {
        global $wpdb;
        $pfx = $wpdb->prefix;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT i.product_name, i.stock, i.vendor_id, i.image_id, v.store_name, v.email AS vendor_email
             FROM {$pfx}petsgo_inventory i
             JOIN {$pfx}petsgo_vendors v ON i.vendor_id = v.id
             WHERE i.id = %d", $product_id
        ));
        if (!$row || !$row->vendor_email) return;
        $stock = (int) $row->stock;
        if ($stock > 4) return;

        $to  = $row->vendor_email;
        $img = $row->image_id ? wp_get_attachment_image_url(intval($row->image_id), 'medium') : '';

        if ($stock === 0) {
            $subject = 'PetsGo — Alerta de Inventario: ' . $row->product_name . ' sin stock';
            $body = $this->stock_email_html($row->store_name, $row->product_name, $stock, true, $to, $img);
        } else {
            $subject = 'PetsGo — Alerta de Inventario: Stock bajo en ' . $row->product_name;
            $body = $this->stock_email_html($row->store_name, $row->product_name, $stock, false, $to, $img);
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->pg_setting('company_name','PetsGo') . ' Notificaciones <' . $this->pg_setting('company_from_email','notificaciones@petsgo.cl') . '>',
            'Reply-To: ' . $this->pg_setting('company_name','PetsGo') . ' Soporte <' . $this->pg_setting('company_email','contacto@petsgo.cl') . '>',
            'Bcc: ' . $this->pg_setting('company_bcc_email','contacto@petsgo.cl'),
            'List-Unsubscribe: <mailto:' . $this->pg_setting('company_email','contacto@petsgo.cl') . '?subject=Desuscribir%20alertas%20stock>',
            'X-Mailer: PetsGo/1.0',
        ];

        wp_mail($to, $subject, $body, $headers);
        $this->audit('stock_alert', 'product', $product_id, 'Stock: ' . $stock . ' — Email a ' . $to);
    }

    /**
     * Logo URL pública para correos
     */
    private function get_email_logo_url() {
        return content_url('uploads/petsgo-assets/logo-petsgo-blanco.png');
    }

    /**
     * Plantilla base de email corporativo PetsGo (anti-spam compliant)
     */
    private function email_wrap($inner_html, $preheader = '') {
        $logo_id  = intval($this->pg_setting('logo_id', 0));
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : $this->get_email_logo_url();
        $year     = date('Y');
        $site_url = $this->pg_setting('company_website', home_url());
        $name     = $this->pg_setting('company_name', 'PetsGo');
        $tagline  = $this->pg_setting('company_tagline', 'Marketplace de mascotas');
        $address  = $this->pg_setting('company_address', 'Santiago, Chile');
        $email    = $this->pg_setting('company_email', 'contacto@petsgo.cl');
        $unsub    = $this->pg_setting('company_email', 'contacto@petsgo.cl');
        $primary  = $this->pg_setting('color_primary', '#00A8E8');
        $secondary= $this->pg_setting('color_secondary', '#FFC400');
        $dark     = $this->pg_setting('color_dark', '#2F3A40');
        $ig       = $this->pg_setting('social_instagram', 'https://www.instagram.com/petsgo.cl');
        $fb       = $this->pg_setting('social_facebook', 'https://www.facebook.com/petsgo.cl');

        return '<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>PetsGo</title>
<!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
<style>
  body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
  table,td{mso-table-lspace:0;mso-table-rspace:0}
  img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
  body{margin:0;padding:0;width:100%!important;background-color:#f4f6f8;font-family:"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .email-body{background-color:#f4f6f8}
  @media only screen and (max-width:620px){
    .email-container{width:100%!important;padding:0 12px!important}
    .content-cell{padding:24px 16px!important}
    .kpi-td{display:block!important;width:100%!important;padding:8px 0!important}
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;">
' . ($preheader ? '<div style="display:none;font-size:1px;color:#f4f6f8;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . esc_html($preheader) . '</div>' : '') . '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="email-body" style="background-color:#f4f6f8;">
<tr><td align="center" style="padding:24px 0;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="580" class="email-container" style="max-width:580px;width:100%;">

  <!-- HEADER -->
  <tr>
    <td style="background:linear-gradient(135deg,' . esc_attr($primary) . ' 0%,#0090c7 100%);padding:28px 32px;text-align:center;border-radius:12px 12px 0 0;">
      <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($name) . '" width="160" style="display:block;margin:0 auto;max-width:160px;height:auto;">
      <p style="color:rgba(255,255,255,0.85);font-size:12px;margin:10px 0 0;letter-spacing:0.5px;">' . esc_html($tagline) . '</p>
    </td>
  </tr>

  <!-- BODY -->
  <tr>
    <td class="content-cell" style="background-color:#ffffff;padding:32px;border-left:1px solid #e9ecef;border-right:1px solid #e9ecef;">
      ' . $inner_html . '
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style="background-color:' . esc_attr($dark) . ';padding:24px 32px;border-radius:0 0 12px 12px;text-align:center;">
      <p style="color:#ffffff;font-size:13px;margin:0 0 6px;font-weight:600;">' . esc_html($name) . '</p>
      <p style="color:rgba(255,255,255,0.6);font-size:11px;margin:0 0 12px;line-height:1.5;">
        ' . esc_html($tagline) . ' &middot; ' . esc_html($address) . '<br>
        <a href="mailto:' . esc_attr($email) . '" style="color:' . esc_attr($secondary) . ';text-decoration:none;">' . esc_html($email) . '</a>
      </p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
        <tr>
          <td style="padding:0 6px;"><a href="' . esc_url($ig) . '" style="color:' . esc_attr($secondary) . ';font-size:12px;text-decoration:none;">Instagram</a></td>
          <td style="color:rgba(255,255,255,0.3);font-size:12px;">&middot;</td>
          <td style="padding:0 6px;"><a href="' . esc_url($fb) . '" style="color:' . esc_attr($secondary) . ';font-size:12px;text-decoration:none;">Facebook</a></td>
          <td style="color:rgba(255,255,255,0.3);font-size:12px;">&middot;</td>
          <td style="padding:0 6px;"><a href="' . esc_url($site_url) . '" style="color:' . esc_attr($secondary) . ';font-size:12px;text-decoration:none;">' . esc_html(str_replace(['https://','http://'],'',$site_url)) . '</a></td>
        </tr>
      </table>
      <p style="color:rgba(255,255,255,0.35);font-size:10px;margin:14px 0 0;line-height:1.5;">
        &copy; ' . $year . ' ' . esc_html($name) . '. Todos los derechos reservados.<br>
        Recibes este correo porque eres usuario registrado en ' . esc_html($name) . '.<br>
        <a href="mailto:' . esc_attr($unsub) . '?subject=Desuscribir" style="color:rgba(255,255,255,0.5);text-decoration:underline;">Desuscribirse de notificaciones</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }

    private function stock_email_html($store_name, $product_name, $stock, $is_zero, $vendor_email = '', $product_image = '') {
        $accent  = $is_zero ? '#dc3545' : '#e67e00';
        $bg_bar  = $is_zero ? '#fdf0f0' : '#fff8ed';
        $title   = $is_zero ? 'Producto sin stock' : 'Stock bajo detectado';
        $pretext = $is_zero
            ? $product_name . ' de ' . $store_name . ' se ha quedado sin stock (0 unidades).'
            : $product_name . ' de ' . $store_name . ' tiene stock bajo (' . $stock . ' uds).';
        $greeting = 'Hola, equipo de <strong>' . esc_html($store_name) . '</strong>';

        $message = $is_zero
            ? 'Te informamos que el siguiente producto se ha quedado <strong>sin stock (0 unidades)</strong>. Mientras no repongas inventario, los clientes no podrán adquirirlo en la plataforma.'
            : 'Te informamos que el siguiente producto tiene <strong>stock bajo (' . $stock . ' unidades restantes)</strong>. Te recomendamos reponer inventario para no perder ventas.';

        $icon_stock = $is_zero ? '0' : $stock;
        $admin_url  = admin_url('admin.php?page=petsgo-products');
        $placeholder_img = content_url('uploads/petsgo-assets/huella-petsgo.png');
        $img_src = $product_image ? $product_image : $placeholder_img;

        $inner = '
      <!-- Alert banner -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
          <td style="background-color:' . $bg_bar . ';border-left:4px solid ' . $accent . ';border-radius:6px;padding:14px 18px;">
            <p style="margin:0;font-size:15px;font-weight:700;color:' . $accent . ';">' . ($is_zero ? '&#9888;' : '&#9888;') . '&nbsp; ' . esc_html($title) . '</p>
          </td>
        </tr>
      </table>

      <!-- Greeting -->
      <p style="color:#333;font-size:15px;line-height:1.6;margin:24px 0 8px;">' . $greeting . ',</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">' . $message . '</p>

      <!-- Product image + detail card -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e9ecef;border-radius:8px;overflow:hidden;">
        <tr style="background-color:#f8f9fa;">
          <td style="padding:12px 18px;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.5px;" colspan="2">Detalle del producto</td>
        </tr>
        <tr>
          <td colspan="2" style="padding:16px 18px;border-top:1px solid #f0f0f0;text-align:center;">
            <img src="' . esc_url($img_src) . '" alt="' . esc_attr($product_name) . '" width="180" style="max-width:180px;max-height:180px;height:auto;border-radius:8px;border:1px solid #e9ecef;object-fit:cover;">
          </td>
        </tr>
        <tr>
          <td style="padding:12px 18px;font-size:13px;font-weight:600;color:#555;width:130px;border-top:1px solid #f0f0f0;">Producto</td>
          <td style="padding:12px 18px;font-size:14px;color:#333;border-top:1px solid #f0f0f0;font-weight:600;">' . esc_html($product_name) . '</td>
        </tr>
        <tr>
          <td style="padding:12px 18px;font-size:13px;font-weight:600;color:#555;border-top:1px solid #f0f0f0;">Tienda</td>
          <td style="padding:12px 18px;font-size:14px;color:#333;border-top:1px solid #f0f0f0;">' . esc_html($store_name) . '</td>
        </tr>
        <tr>
          <td style="padding:12px 18px;font-size:13px;font-weight:600;color:#555;border-top:1px solid #f0f0f0;">Stock actual</td>
          <td style="padding:12px 18px;border-top:1px solid #f0f0f0;">
            <span style="display:inline-block;background:' . $accent . ';color:#fff;font-size:14px;font-weight:700;padding:4px 14px;border-radius:20px;">' . $icon_stock . ' ' . ($stock === 1 ? 'unidad' : 'unidades') . '</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 18px;font-size:13px;font-weight:600;color:#555;border-top:1px solid #f0f0f0;">Fecha alerta</td>
          <td style="padding:12px 18px;font-size:13px;color:#666;border-top:1px solid #f0f0f0;">' . date('d/m/Y H:i') . ' hrs</td>
        </tr>
      </table>

      <!-- CTA Button -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:28px 0 8px;">
        <tr>
          <td align="center">
            <a href="' . esc_url($admin_url) . '" target="_blank" style="display:inline-block;background:#00A8E8;color:#ffffff;font-size:14px;font-weight:700;padding:12px 32px;border-radius:8px;text-decoration:none;letter-spacing:0.3px;">Gestionar inventario</a>
          </td>
        </tr>
      </table>

      <!-- Tip -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:24px;">
        <tr>
          <td style="background-color:#f0faff;border-radius:8px;padding:16px 18px;">
            <p style="margin:0;font-size:12px;color:#00718a;line-height:1.6;">
              <strong>&#128161; Consejo:</strong> Mantener productos con stock adecuado mejora tu posicionamiento en PetsGo y la experiencia de tus clientes. Puedes configurar tus niveles de stock directamente desde el panel de administración.
            </p>
          </td>
        </tr>
      </table>

      <!-- Disclaimer -->
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este mensaje es una notificación automática del sistema de inventario de PetsGo.<br>
        Se envió a <span style="color:#888;">' . esc_html($vendor_email) . '</span> por ser el correo registrado de la tienda.
      </p>';

        return $this->email_wrap($inner, $pretext);
    }

    // ============================================================
    // SETTINGS HELPER — lee opciones de petsgo_settings
    // ============================================================
    public function pg_setting($key, $default = '') {
        static $cache = null;
        if ($cache === null) {
            $cache = get_option('petsgo_settings', []);
        }
        return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
    }

    private function pg_defaults() {
        return [
            'company_name'      => 'PetsGo',
            'company_tagline'   => 'Marketplace de mascotas',
            'company_rut'       => '77.123.456-7',
            'company_address'   => 'Santiago, Chile',
            'company_phone'     => '+56 9 1234 5678',
            'company_email'     => 'contacto@petsgo.cl',
            'company_bcc_email' => 'contacto@petsgo.cl',
            'company_from_email'=> 'notificaciones@petsgo.cl',
            'company_website'   => 'https://petsgo.cl',
            'social_instagram'  => 'https://www.instagram.com/petsgo.cl',
            'social_facebook'   => 'https://www.facebook.com/petsgo.cl',
            'social_whatsapp'   => '+56912345678',
            'color_primary'     => '#00A8E8',
            'color_secondary'   => '#FFC400',
            'color_dark'        => '#2F3A40',
            'color_success'     => '#28a745',
            'color_danger'      => '#dc3545',
            'logo_id'           => '',
            'plan_annual_free_months' => '2',
            'free_shipping_min' => '39990',
            'tickets_bcc_email' => '',
            // ── Rider / Delivery / Comisiones ──
            'rider_commission_pct'  => '88',   // % que recibe el rider del delivery_fee
            'store_fee_pct'         => '5',    // % que paga la tienda sobre delivery_fee
            'petsgo_fee_pct'        => '7',    // % que retiene PetsGo del delivery_fee
            'delivery_fee_min'      => '2000', // fee mínimo CLP
            'delivery_fee_max'      => '5000', // fee máximo CLP
            'delivery_base_rate'    => '2000', // tarifa base CLP
            'delivery_per_km'       => '400',  // CLP extra por km
            'payout_day'            => 'thursday',  // día de pago semanal
            'payout_cycle'          => 'weekly',    // ciclo: weekly
        ];
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
    private function is_support() {
        $user = wp_get_current_user();
        return in_array('petsgo_support', (array)$user->roles);
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

        // Configuración — solo admin
        add_submenu_page('petsgo-dashboard', 'Configuración', '⚙️ Configuración', $cap_admin, 'petsgo-settings', [$this, 'page_settings']);

        // Categorías — solo admin
        add_submenu_page('petsgo-dashboard', 'Categorías', '📂 Categorías', $cap_admin, 'petsgo-categories', [$this, 'page_categories']);

        // Tickets / Soporte — admin y soporte
        add_submenu_page('petsgo-dashboard', 'Tickets', '🎫 Tickets', $cap_all, 'petsgo-tickets', [$this, 'page_tickets']);

        // Leads — solo admin
        add_submenu_page('petsgo-dashboard', 'Leads', '📩 Leads', $cap_admin, 'petsgo-leads', [$this, 'page_leads']);

        // Preview Emails — hidden page (solo admin)
        add_submenu_page(null, 'Preview Emails', '', $cap_admin, 'petsgo-email-preview', [$this, 'page_email_preview']);

        // Contenido Legal — admin
        add_submenu_page('petsgo-dashboard', 'Contenido Legal', '📄 Contenido Legal', $cap_admin, 'petsgo-legal', [$this, 'page_legal']);
    }

    // ============================================================
    // CSS + JS GLOBAL ADMIN
    // ============================================================
    public function admin_assets($hook) {
        if (strpos($hook, 'petsgo') === false) return;
        wp_enqueue_media();
        // CSS: safe to output anytime in head
        add_action('admin_head', [$this, 'print_admin_css'], 1);
        // JS: use wp_add_inline_script to GUARANTEE jQuery loads first
        // This solves the issue where admin_enqueue_scripts or admin_head
        // may fire before jQuery is available (WP 6.9+ deferred scripts)
        ob_start();
        $this->print_admin_js();
        $raw = ob_get_clean();
        $js = trim(preg_replace('#</?script[^>]*>#i', '', $raw));
        wp_register_script('petsgo-admin-js', false, ['jquery'], '1.0', false);
        wp_enqueue_script('petsgo-admin-js');
        wp_add_inline_script('petsgo-admin-js', $js);
    }

    public function print_admin_css() {
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
        .petsgo-role-tag.administrator{background:linear-gradient(135deg,#00A8E8,#0077b6);color:#fff;box-shadow:0 1px 3px rgba(0,168,232,.35)}
        .petsgo-role-tag.vendor{background:#FFC400;color:#2F3A40}
        .petsgo-role-tag.rider{background:#6f42c1;color:#fff}
        .petsgo-role-tag.subscriber{background:linear-gradient(135deg,#28a745,#20894d);color:#fff;box-shadow:0 1px 3px rgba(40,167,69,.35)}
        .petsgo-role-tag.support{background:#17a2b8;color:#fff}
        .petsgo-info-bar{background:#e3f5fc;border-left:4px solid #00A8E8;padding:10px 16px;margin:10px 0;border-radius:0 6px 6px 0;font-size:13px;color:#004085}
        /* ── Multi-select Checklist ── */
        .pgcl-wrap{position:relative;display:inline-block;min-width:200px;vertical-align:middle}
        .pgcl-btn{padding:9px 32px 9px 12px;border:1px solid #b0b0b0;border-radius:6px;font-size:13px;cursor:pointer;background:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px;font-family:inherit;color:#333;transition:border-color .15s,box-shadow .15s;position:relative}
        .pgcl-btn::after{content:"";position:absolute;right:12px;top:50%;transform:translateY(-50%);border-left:5px solid transparent;border-right:5px solid transparent;border-top:6px solid #666;transition:transform .2s}
        .pgcl-btn.open::after{transform:translateY(-50%) rotate(180deg)}
        .pgcl-btn:hover,.pgcl-btn.open{border-color:#00A8E8;box-shadow:0 0 0 2px rgba(0,168,232,.15)}
        .pgcl-dd{display:none;position:absolute;top:calc(100% + 3px);left:0;min-width:100%;width:max-content;max-width:360px;max-height:280px;overflow-y:auto;background:#fff;border:1px solid #ccc;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.15);z-index:999;padding:0}
        .pgcl-dd.open{display:block}
        .pgcl-dd-search{display:block;width:100%;padding:8px 12px;border:none;border-bottom:1px solid #eee;font-size:13px;outline:none;box-sizing:border-box;color:#333}
        .pgcl-dd-search::placeholder{color:#aaa}
        .pgcl-dd-list{max-height:240px;overflow-y:auto;padding:4px 0}
        .pgcl-item{display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;font-size:14px;color:#333;transition:background .12s,color .12s;user-select:none}
        .pgcl-item:hover{background:#e8f4fd}
        .pgcl-item.checked{background:#00A8E8;color:#fff}
        .pgcl-item.checked:hover{background:#0096d0}
        .pgcl-item input[type=checkbox]{accent-color:#00A8E8;width:17px;height:17px;min-width:17px;margin:0;cursor:pointer}
        .pgcl-item.checked input[type=checkbox]{accent-color:#fff}
        .pgcl-item span{pointer-events:none;white-space:nowrap}
        .pgcl-clear:hover,.pgcl-sel-all:hover{background:#f0faff}
        .pgcl-actions{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:6px 12px;font-size:12px}
        .pgcl-actions span{cursor:pointer;color:#00A8E8;font-weight:500}
        .pgcl-actions span:hover{text-decoration:underline}
        /* ── Sortable Table Headers ── */
        .petsgo-table th.pg-sortable{cursor:pointer;user-select:none;position:relative;padding-right:22px!important;transition:background .15s}
        .petsgo-table th.pg-sortable:hover{background:#0090c7}
        .petsgo-table th.pg-sortable::after{content:"⇅";position:absolute;right:6px;top:50%;transform:translateY(-50%);font-size:11px;opacity:.5}
        .petsgo-table th.pg-sortable.asc::after{content:"▲";opacity:1}
        .petsgo-table th.pg-sortable.desc::after{content:"▼";opacity:1}
        /* ── Pagination ── */
        .pg-pagination{display:flex;align-items:center;gap:8px;margin:18px 0;flex-wrap:wrap;font-size:13px;padding:12px 16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px}
        .pg-pagination button{padding:7px 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;font-size:13px;transition:.2s;font-weight:500}
        .pg-pagination button:hover:not(:disabled){background:#00A8E8;color:#fff;border-color:#00A8E8;box-shadow:0 2px 8px rgba(0,168,232,.25)}
        .pg-pagination button.active{background:#00A8E8;color:#fff;border-color:#00A8E8;font-weight:700;box-shadow:0 2px 8px rgba(0,168,232,.25)}
        .pg-pagination button:disabled{opacity:.35;cursor:not-allowed}
        .pg-pagination .pg-page-info{color:#4b5563;font-weight:600;margin-right:auto}
        .pg-pagination select{padding:6px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;font-weight:500}
        /* ── Global Responsive ── */
        @media(max-width:900px){.petsgo-form-grid{grid-template-columns:1fr}.petsgo-cards{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:600px){
            .petsgo-wrap{padding:0 8px!important}
            .petsgo-cards{grid-template-columns:1fr}
            .petsgo-search-bar{flex-direction:column;align-items:stretch}
            .petsgo-search-bar input[type=text]{min-width:auto;width:100%}
            .petsgo-table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;font-size:12px}
            .petsgo-table th,.petsgo-table td{padding:8px 10px;white-space:nowrap}
            .petsgo-form-section{padding:14px}
            .petsgo-img-upload{justify-content:center}
            .petsgo-img-slot{width:120px;height:120px}
            .petsgo-btn{padding:8px 12px;font-size:12px}
        }
        @media(max-width:400px){.petsgo-wrap h1{font-size:16px}.petsgo-table th,.petsgo-table td{padding:6px 8px;font-size:11px}}
        </style>';
    }

    public function print_admin_js() {
        ?>
        <script>
        (function($){
        window.PG = {
            nonce: '<?php echo wp_create_nonce("petsgo_ajax"); ?>',
            adminUrl: '<?php echo admin_url("admin.php"); ?>',
            ajaxUrl: ajaxurl,
            isAdmin: <?php echo $this->is_admin() ? 'true' : 'false'; ?>,
            isVendor: <?php echo $this->is_vendor() ? 'true' : 'false'; ?>,
            isRider: <?php echo $this->is_rider() ? 'true' : 'false'; ?>,
            myVendorId: <?php echo $this->get_my_vendor_id(); ?>,
            statusEs: {
                'pending':'Pendiente','payment_pending':'Pago Pendiente','preparing':'Preparando',
                'ready':'Listo','in_transit':'En Tránsito','delivered':'Entregado','cancelled':'Cancelado',
                'processing':'Procesando','active':'Activo','inactive':'Inactivo','completed':'Completado',
                'unassigned':'Sin Asignar'
            },
            sEs: function(s){ return PG.statusEs[(s||'').toLowerCase()]||(s||'').replace(/_/g,' '); },
            esc: function(s){ return jQuery('<span>').text(s||'').html(); },
            money: function(n){ return '$' + Number(n||0).toLocaleString('es-CL'); },
            post: function(action, data, cb){
                data.action = action;
                data._ajax_nonce = PG.nonce;
                jQuery.post(PG.ajaxUrl, data, cb).fail(function(xhr){
                    console.error('PG.post error:',action,xhr.status,xhr.statusText);
                    if(cb) cb({success:false,data:'Error de conexi\u00f3n ('+xhr.status+')'});
                });
            },
            badge: function(status){
                var label = PG.sEs(status);
                return '<span class="petsgo-badge ' + (status||'') + '">' + label + '</span>';
            },
            fdate: function(s){
                if(!s)return '—';
                var d=new Date(s.replace(' ','T'));
                if(isNaN(d.getTime()))return s;
                var dd=String(d.getDate()).padStart(2,'0'),mm=String(d.getMonth()+1).padStart(2,'0'),yy=d.getFullYear();
                var hh=String(d.getHours()).padStart(2,'0'),mi=String(d.getMinutes()).padStart(2,'0');
                return dd+'-'+mm+'-'+yy+' '+hh+':'+mi;
            }
        };
        /* ── Sortable Table + Pagination Engine ── */
        PG.table=function(cfg){
            var data=[],sorted=[],sortCol=cfg.defaultSort||null,sortDir=cfg.defaultDir||'asc';
            var page=1,perPage=cfg.perPage||25;
            var $body=$(cfg.body),$thead=$(cfg.thead),$pgWrap=null;
            // add sort classes to headers
            $thead.find('th').each(function(i){
                var key=cfg.columns[i];
                if(key&&key!=='_actions'&&key!=='_photo'){
                    $(this).addClass('pg-sortable').attr('data-col',key);
                    if(key===sortCol) $(this).addClass(sortDir);
                }
            });
            $thead.on('click','th.pg-sortable',function(){
                var col=$(this).attr('data-col');
                if(sortCol===col) sortDir=sortDir==='asc'?'desc':'asc'; else {sortCol=col;sortDir='asc';}
                $thead.find('th').removeClass('asc desc');
                $(this).addClass(sortDir);
                page=1;render();
            });
            // create pagination container
            $pgWrap=$('<div class="pg-pagination"></div>').insertAfter($(cfg.body).closest('table'));
            function compare(a,b){
                if(!sortCol)return 0;
                var va=a[sortCol],vb=b[sortCol];
                if(va==null)va='';if(vb==null)vb='';
                // numeric
                var na=parseFloat(va),nb=parseFloat(vb);
                if(!isNaN(na)&&!isNaN(nb)){return sortDir==='asc'?na-nb:nb-na;}
                va=String(va).toLowerCase();vb=String(vb).toLowerCase();
                if(va<vb)return sortDir==='asc'?-1:1;
                if(va>vb)return sortDir==='asc'?1:-1;
                return 0;
            }
            function render(){
                sorted=data.slice().sort(compare);
                var total=sorted.length,pages=Math.ceil(total/perPage)||1;
                if(page>pages)page=pages;
                var start=(page-1)*perPage,slice=sorted.slice(start,start+perPage);
                var rows='';
                if(!slice.length){rows='<tr><td colspan="'+cfg.columns.length+'" style="text-align:center;padding:30px;color:#999;">'+(cfg.emptyMsg||'Sin resultados.')+'</td></tr>';}
                else{ $.each(slice,function(i,item){rows+=cfg.renderRow(item,start+i);}); }
                $body.html(rows);
                // pagination — always visible
                if(!total){$pgWrap.html('');return;}
                var ph='<span class="pg-page-info">Mostrando '+(start+1)+'–'+Math.min(start+perPage,total)+' de '+total+' registros</span> ';
                if(pages>1){
                    ph+='<button class="pg-prev" '+(page<=1?'disabled':'')+'>← Ant</button> ';
                    var maxBtns=7,half=Math.floor(maxBtns/2),startP=Math.max(1,page-half),endP=Math.min(pages,startP+maxBtns-1);
                    if(endP-startP<maxBtns-1)startP=Math.max(1,endP-maxBtns+1);
                    for(var p=startP;p<=endP;p++){ph+='<button class="pg-go'+(p===page?' active':'')+'" data-p="'+p+'">'+p+'</button> ';}
                    ph+='<button class="pg-next" '+(page>=pages?'disabled':'')+'>Sig →</button> ';
                }
                ph+='<select class="pg-per-page">';
                [10,25,50,100].forEach(function(n){ph+='<option value="'+n+'"'+(n===perPage?' selected':'')+'>'+n+'/pág</option>';});
                ph+='</select>';
                $pgWrap.html(ph);
            }
            $pgWrap.on('click','.pg-prev',function(){if(page>1){page--;render();}});
            $pgWrap.on('click','.pg-next',function(){var pages=Math.ceil(data.length/perPage)||1;if(page<pages){page++;render();}});
            $pgWrap.on('click','.pg-go',function(){page=parseInt($(this).data('p'));render();});
            $pgWrap.on('change','.pg-per-page',function(){perPage=parseInt($(this).val());page=1;render();});
            return {setData:function(d){data=d;page=1;if(cfg.onTotal)cfg.onTotal(d.length);render();},getData:function(){return data;}};
        };
        /* ── Multi-select Checklist Widget ── */
            $.fn.pgChecklist=function(opts){
                return this.each(function(){
                    var $sel=$(this).hide(),ph=opts&&opts.placeholder||'Todos';
                    if($sel.data('pgcl'))return; // already initialized
                    $sel.data('pgcl',true);
                    var $wrap=$('<div class="pgcl-wrap"></div>').insertAfter($sel);
                    var $btn=$('<div class="pgcl-btn">'+ph+'</div>').appendTo($wrap);
                    var $dd=$('<div class="pgcl-dd"></div>').appendTo($wrap);
                    var items=$sel.find('option[value!=""]');
                    var hasMany=items.length>5;
                    if(hasMany) $dd.append('<input type="text" class="pgcl-dd-search" placeholder="Buscar...">');
                    // Select all / Deselect all bar
                    if(items.length>1) $dd.append('<div class="pgcl-actions"><span class="pgcl-sel-all">✅ Seleccionar todo</span><span class="pgcl-clear">✕ Limpiar</span></div>');
                    var $list=$('<div class="pgcl-dd-list"></div>').appendTo($dd);
                    items.each(function(){
                        var v=$(this).val(),t=$(this).text();
                        $list.append('<label class="pgcl-item" data-val="'+v+'"><input type="checkbox" value="'+v+'"><span>'+t+'</span></label>');
                    });
                    function syncBtn(){
                        var checked=$list.find('input:checked');
                        var count=checked.length;
                        if(!count){ $btn.text(ph).removeAttr('title'); }
                        else if(count<=2){
                            var names=checked.map(function(){return $(this).next().text();}).get();
                            $btn.text(names.join(', ')).attr('title',names.join(', '));
                        } else {
                            $btn.text(count+' seleccionados').attr('title',checked.map(function(){return $(this).next().text();}).get().join(', '));
                        }
                    }
                    $btn.on('click',function(e){e.stopPropagation();$('.pgcl-dd').not($dd).removeClass('open');$('.pgcl-btn').not($btn).removeClass('open');$dd.toggleClass('open');$btn.toggleClass('open');if($dd.hasClass('open')&&hasMany)$dd.find('.pgcl-dd-search').focus();});
                    $(document).on('click.pgcl',function(){$dd.removeClass('open');$btn.removeClass('open');});
                    $dd.on('click',function(e){e.stopPropagation();});
                    $list.on('change','input',function(){
                        var $it=$(this).closest('.pgcl-item');
                        if(this.checked) $it.addClass('checked'); else $it.removeClass('checked');
                        var vals=[];$list.find('input:checked').each(function(){vals.push($(this).val());});
                        $sel.val(vals).trigger('change');
                        syncBtn();
                    });
                    $dd.on('input','.pgcl-dd-search',function(){
                        var q=$.trim($(this).val()).toLowerCase();
                        $list.find('.pgcl-item').each(function(){$(this).toggle(!q||$(this).find('span').text().toLowerCase().indexOf(q)>-1);});
                    });
                    $dd.on('click','.pgcl-clear',function(){
                        $list.find('input:checked').prop('checked',false);$list.find('.pgcl-item').removeClass('checked');
                        $sel.val([]).trigger('change');syncBtn();
                    });
                    $dd.on('click','.pgcl-sel-all',function(){
                        $list.find('.pgcl-item:visible input:not(:checked)').prop('checked',true).closest('.pgcl-item').addClass('checked');
                        var vals=[];$list.find('input:checked').each(function(){vals.push($(this).val());});
                        $sel.val(vals).trigger('change');syncBtn();
                    });
                    // Public reset
                    $sel.data('pgcl-reset',function(){
                        $list.find('input:checked').prop('checked',false);$list.find('.pgcl-item').removeClass('checked');
                        $sel.val([]).trigger('change');$btn.text(ph).removeAttr('title');$dd.removeClass('open');$btn.removeClass('open');
                    });
                });
            };
        })(jQuery);
        </script>
        <?php
    }

    // ============================================================
    // 1. DASHBOARD ANALÍTICO — AJAX-driven, filtros dinámicos
    // ============================================================
    public function page_dashboard() {
        global $wpdb;
        $is_admin  = $this->is_admin();
        $is_vendor = $this->is_vendor();
        $is_rider  = $this->is_rider();
        $vid       = $this->get_my_vendor_id();

        // Vendor plan & analytics access
        $has_analytics = $is_admin;
        $vendor_plan   = 'basico';
        $store_name    = '';
        $vendor_inactive = false;
        $vendor_sub_end  = '';
        if ($is_vendor && $vid) {
            $vr = $wpdb->get_row($wpdb->prepare("SELECT v.store_name, v.plan_id, v.status, v.subscription_end, s.plan_name FROM {$wpdb->prefix}petsgo_vendors v LEFT JOIN {$wpdb->prefix}petsgo_subscriptions s ON v.plan_id=s.id WHERE v.id=%d", $vid));
            $store_name  = $vr->store_name ?? '';
            $vendor_plan = strtolower($vr->plan_name ?? 'basico');
            $has_analytics = in_array($vendor_plan, ['pro','enterprise']);
            $vendor_sub_end = $vr->subscription_end ?? '';
            // Auto-deactivate if subscription expired
            if ($vr && $vr->subscription_end && $vr->subscription_end < date('Y-m-d') && $vr->status === 'active') {
                $wpdb->update("{$wpdb->prefix}petsgo_vendors", ['status' => 'inactive'], ['id' => $vid]);
                $vr->status = 'inactive';
            }
            if ($vr && $vr->status !== 'active') {
                $vendor_inactive = true;
            }
        }

        // Vendor/category lists for filter dropdowns (admin only)
        $all_vendors    = $is_admin ? $wpdb->get_results("SELECT id, store_name FROM {$wpdb->prefix}petsgo_vendors ORDER BY store_name") : [];
        $all_categories = ['Alimento','Juguetes','Salud','Accesorios','Higiene','Ropa','Camas','Transporte'];
        $all_statuses   = ['pending'=>'Pendiente','payment_pending'=>'Pago Pendiente','preparing'=>'Preparando','ready'=>'Listo','in_transit'=>'En Tránsito','delivered'=>'Entregado','cancelled'=>'Cancelado'];

        // Rider simple dashboard (no filters needed)
        if ($is_rider) {
            $uid = get_current_user_id();
            $r_assigned  = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d", $uid));
            $r_delivered = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", $uid));
            $r_transit   = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='in_transit'", $uid));
            $r_fees      = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(delivery_fee),0) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", $uid));
        }
        ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
        <style>
        .pg-dash{font-family:'Poppins',sans-serif;padding:0 0 40px}
        .pg-dash h1{font-size:24px;font-weight:800;color:#2F3A40;margin:0 0 4px}
        .pg-dash .dash-sub{font-size:14px;color:#777;margin:0 0 20px}
        /* Filter bar */
        .pg-filter-bar{background:#fff;border-radius:14px;padding:16px 20px;box-shadow:0 2px 12px rgba(0,0,0,.04);border:1px solid #f0f0f0;margin-bottom:24px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
        .pg-filter-bar .fg{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1 1 180px}
        .pg-filter-bar .fg label{font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px}
        .pg-filter-bar .fg > select,.pg-filter-bar .fg > input{padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:Poppins,sans-serif;background:#fff;color:#333;width:100%;box-sizing:border-box}
        .pg-filter-bar .fg > select[multiple]{min-height:68px;padding:4px}
        .pg-filter-bar .fg > select[multiple] option{padding:4px 8px;border-radius:4px;margin:1px 0}
        .pg-filter-bar .filter-actions{display:flex;gap:8px;align-items:flex-end;flex:0 0 auto}
        .pg-filter-bar .fbtn{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;white-space:nowrap}
        .pg-filter-bar .fbtn.apply{background:#00A8E8;color:#fff}.pg-filter-bar .fbtn.apply:hover{background:#0090c5}
        .pg-filter-bar .fbtn.reset{background:#e2e3e5;color:#333}.pg-filter-bar .fbtn.reset:hover{background:#d6d8db}
        /* Export bar */
        .pg-export-bar{display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;align-items:center}
        .pg-export-bar .ebtn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all .15s}
        .pg-export-bar .ebtn.pdf{background:#dc3545;color:#fff}.pg-export-bar .ebtn.pdf:hover{background:#c82333}
        .pg-export-bar .ebtn.img{background:#00A8E8;color:#fff}.pg-export-bar .ebtn.img:hover{background:#0090c5}
        .pg-export-bar .ebtn.print{background:#6f42c1;color:#fff}.pg-export-bar .ebtn.print:hover{background:#5a32a3}
        /* KPIs */
        .pg-kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px}
        .pg-kpi{background:#fff;border-radius:14px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.04);border:1px solid #f0f0f0;position:relative;overflow:hidden}
        .pg-kpi .ki{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:8px}
        .pg-kpi .kv{font-size:24px;font-weight:800;color:#2F3A40;line-height:1.1;word-break:break-all}
        .pg-kpi .kl{font-size:11px;color:#888;margin-top:3px;font-weight:500}
        .pg-kpi .kt{position:absolute;top:14px;right:14px;font-size:10px;font-weight:700;padding:3px 7px;border-radius:6px}
        .pg-kpi .kt.up{background:#d4edda;color:#155724}.pg-kpi .kt.down{background:#f8d7da;color:#721c24}.pg-kpi .kt.neutral{background:#e2e3e5;color:#383d41}
        /* Charts */
        .pg-charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px}
        .pg-chart-card{background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.04);border:1px solid #f0f0f0}
        .pg-chart-card h3{font-size:14px;font-weight:700;color:#2F3A40;margin:0 0 14px}
        .pg-chart-card canvas{max-height:250px}
        /* Sections / tables */
        .pg-section{background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.04);border:1px solid #f0f0f0;margin-bottom:18px}
        .pg-section h3{font-size:14px;font-weight:700;color:#2F3A40;margin:0 0 14px;display:flex;align-items:center;gap:8px}
        .pg-section .tbl-filter{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
        .pg-section .tbl-filter input,.pg-section .tbl-filter select{padding:6px 10px;border:1px solid #ddd;border-radius:8px;font-size:12px;font-family:Poppins,sans-serif}
        .pg-section .petsgo-table{font-size:12px}
        .pg-section .petsgo-table th{font-size:11px;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
        .pg-section .petsgo-table td{vertical-align:middle}
        /* Analytics lock */
        .pg-lock{background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:14px;padding:36px;text-align:center;margin-bottom:20px;border:2px dashed #ddd}
        .pg-lock h3{color:#555;margin:0 0 8px;font-size:17px}.pg-lock p{color:#888;margin:0 0 14px;font-size:13px}
        .pg-lock a{display:inline-block;padding:10px 24px;background:#FFC400;color:#2F3A40;border-radius:10px;font-weight:700;text-decoration:none;font-size:14px}
        .pg-dash-loader{text-align:center;padding:40px;color:#999;font-size:14px}
        /* Responsive */
        @media(max-width:1100px){.pg-filter-bar .fg{flex:1 1 140px}}
        @media(max-width:900px){.pg-charts-grid{grid-template-columns:1fr}.pg-kpi-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:600px){.pg-kpi-grid{grid-template-columns:1fr}.pg-filter-bar{flex-direction:column}.pg-filter-bar .fg{flex:1 1 100%}.pg-export-bar{flex-direction:column;align-items:stretch}.pg-export-bar .ebtn{justify-content:center}.pg-section .tbl-filter{flex-direction:column}}
        @media(max-width:480px){.pg-dash h1{font-size:18px}.pg-kpi .kv{font-size:20px}.pg-section .petsgo-table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}}
        @media print{.no-print,.pg-filter-bar,.pg-export-bar{display:none!important}.pg-dash{padding:0}.pg-kpi,.pg-chart-card,.pg-section{break-inside:avoid}}
        </style>

        <div class="wrap petsgo-wrap pg-dash" id="pg-dashboard-content">

        <?php if ($is_rider): ?>
            <h1>🚴 Panel Delivery — <?php echo esc_html(wp_get_current_user()->display_name); ?></h1>
            <p class="dash-sub">Resumen de tu actividad · <?php echo date('d-m-Y H:i'); ?></p>
            <div class="pg-kpi-grid">
                <div class="pg-kpi"><div class="ki" style="background:#e3f5fc">📦</div><div class="kv"><?php echo $r_assigned; ?></div><div class="kl">Asignados</div></div>
                <div class="pg-kpi"><div class="ki" style="background:#fff3cd">🚚</div><div class="kv"><?php echo $r_transit; ?></div><div class="kl">En Tránsito</div></div>
                <div class="pg-kpi"><div class="ki" style="background:#d4edda">✅</div><div class="kv"><?php echo $r_delivered; ?></div><div class="kl">Entregados</div></div>
                <div class="pg-kpi"><div class="ki" style="background:#d1ecf1">💰</div><div class="kv"><?php echo $this->fmt($r_fees); ?></div><div class="kl">Ganado en Delivery</div></div>
            </div>

        <?php elseif (!$is_admin && !$is_vendor): ?>
            <h1>🐾 PetsGo</h1><p>No tienes una tienda o rol asignado.</p>

        <?php elseif ($is_vendor && $vendor_inactive): ?>
            <h1>🚫 Suscripción Inactiva</h1>
            <div style="max-width:600px;margin:40px auto;text-align:center;">
                <div style="background:#fce4ec;border-radius:16px;padding:40px 30px;border:2px solid #ef9a9a;">
                    <div style="font-size:60px;margin-bottom:16px;">🔒</div>
                    <h2 style="color:#c62828;margin:0 0 12px;font-size:22px;">Tu tienda está inactiva</h2>
                    <p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 8px;">
                        <strong><?php echo esc_html($store_name); ?></strong> no tiene una suscripción activa.
                    </p>
                    <?php if ($vendor_sub_end): ?>
                    <p style="color:#888;font-size:13px;margin:0 0 20px;">
                        Tu suscripción venció el <strong style="color:#c62828;"><?php echo date('d/m/Y', strtotime($vendor_sub_end)); ?></strong>
                    </p>
                    <?php endif; ?>
                    <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
                        Para volver a publicar productos y recibir pedidos, debes renovar tu plan de suscripción.
                    </p>
                    <div style="background:#f8f9fa;border-radius:10px;padding:16px 20px;margin-bottom:20px;text-align:left;font-size:13px;color:#555;">
                        <p style="margin:0 0 8px;font-weight:700;color:#333;">⚠️ Mientras tu tienda esté inactiva:</p>
                        <ul style="margin:0;padding-left:20px;line-height:2;">
                            <li>Tus productos no serán visibles para los clientes</li>
                            <li>No podrás recibir nuevos pedidos</li>
                            <li>No tendrás acceso al panel de administración</li>
                        </ul>
                    </div>
                    <a href="mailto:contacto@petsgo.cl?subject=Renovación suscripción - <?php echo esc_attr($store_name); ?>" style="display:inline-block;background:#00A8E8;color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:10px;">📧 Contactar para renovar</a>
                    <p style="color:#aaa;font-size:12px;margin:16px 0 0;">contacto@petsgo.cl · +56 9 1234 5678</p>
                </div>
            </div>

        <?php else: ?>
            <?php if ($is_admin): ?>
                <h1>🐾 PetsGo — Dashboard Analítico</h1>
                <p class="dash-sub">Métricas globales · <span id="dash-updated"></span></p>
            <?php else: ?>
                <h1>🏪 <?php echo esc_html($store_name); ?> — Dashboard</h1>
                <p class="dash-sub">Plan: <strong style="color:#FFC400;"><?php echo ucfirst($vendor_plan); ?></strong> · <span id="dash-updated"></span></p>
            <?php endif; ?>

            <!-- ===== FILTER BAR (admin: full, vendor: dates+status+category) ===== -->
            <div class="pg-filter-bar no-print">
                <?php if ($is_admin): ?>
                <div class="fg" style="flex:1 1 220px">
                    <label>🏪 Tiendas</label>
                    <select id="df-vendors" multiple>
                        <?php foreach ($all_vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="fg">
                    <label>📁 Categoría</label>
                    <select id="df-category" multiple><option value="">Todas</option>
                        <?php foreach ($all_categories as $c): ?><option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>📋 Estado pedido</label>
                    <select id="df-status" multiple><option value="">Todos</option>
                        <?php foreach ($all_statuses as $st => $st_label): ?><option value="<?php echo $st; ?>"><?php echo $st_label; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>📅 Desde</label>
                    <input type="date" id="df-from">
                </div>
                <div class="fg">
                    <label>📅 Hasta</label>
                    <input type="date" id="df-to">
                </div>
                <div class="fg">
                    <label>🔍 Buscar</label>
                    <input type="text" id="df-search" placeholder="Producto, cliente...">
                </div>
                <div class="filter-actions">
                    <button class="fbtn apply" id="df-apply">🔍 Aplicar</button>
                    <button class="fbtn reset" id="df-reset">✕ Limpiar</button>
                </div>
            </div>

            <!-- Export Bar -->
            <div class="pg-export-bar no-print">
                <button class="ebtn pdf" onclick="pgExportPDF()">📄 Exportar PDF</button>
                <button class="ebtn img" onclick="pgExportImage()">🖼️ Exportar Imagen</button>
                <button class="ebtn print" onclick="window.print()">🖨️ Imprimir</button>
                <span style="margin-left:auto;font-size:11px;color:#aaa;" class="no-print">Gráficos incluidos en exportación</span>
            </div>

            <!-- Dynamic content (loaded via AJAX) -->
            <div id="dash-kpis"><div class="pg-dash-loader">⏳ Cargando métricas...</div></div>

            <?php if ($has_analytics): ?>
            <div class="pg-charts-grid" id="dash-charts">
                <div class="pg-chart-card"><h3>📊 Pedidos por Estado</h3><canvas id="pgChartStatus" height="250"></canvas></div>
                <div class="pg-chart-card"><h3>📈 Ingresos Mensuales</h3><canvas id="pgChartRevenue" height="250"></canvas></div>
                <div class="pg-chart-card"><h3>🏷️ Productos por Categoría</h3><canvas id="pgChartCategories" height="250"></canvas></div>
                <div class="pg-chart-card"><h3>📦 Pedidos Mensuales</h3><canvas id="pgChartOrders" height="250"></canvas></div>
            </div>
            <div id="dash-tables"></div>
            <?php else: ?>
            <div class="pg-lock">
                <h3>📊 Analíticas Avanzadas</h3>
                <p>Gráficos, riders destacados, ranking de tiendas y métricas avanzadas requieren plan <strong>Pro</strong> o <strong>Enterprise</strong>.</p>
                <p style="color:#aaa;font-size:12px;margin-bottom:14px;">Plan actual: <strong><?php echo ucfirst($vendor_plan); ?></strong></p>
                <a href="<?php echo admin_url('admin.php?page=petsgo-plans'); ?>">⬆️ Mejorar Plan</a>
            </div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
            <!-- Renewal Alerts -->
            <?php
            $renewal_vendors = $wpdb->get_results(
                "SELECT v.id, v.store_name, v.email, v.subscription_end, s.plan_name FROM {$wpdb->prefix}petsgo_vendors v LEFT JOIN {$wpdb->prefix}petsgo_subscriptions s ON v.plan_id=s.id WHERE v.subscription_end IS NOT NULL AND v.subscription_end <= DATE_ADD(CURDATE(), INTERVAL 10 DAY) AND v.status='active' ORDER BY v.subscription_end ASC"
            );
            if (!empty($renewal_vendors)): ?>
            <div class="pg-section" style="border-left:4px solid #e65100;">
                <h3>⏰ Alertas de Renovación de Suscripción</h3>
                <p style="font-size:12px;color:#888;margin:0 0 12px;">Tiendas cuya suscripción vence en los próximos 10 días o ya venció.</p>
                <table class="petsgo-table">
                    <thead><tr><th>ID</th><th>Tienda</th><th>Plan</th><th>Email</th><th>Vencimiento</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($renewal_vendors as $rv):
                        $diff = (int) round((strtotime($rv->subscription_end) - time()) / 86400);
                        $color = $diff <= 0 ? '#dc3545' : ($diff <= 3 ? '#e65100' : ($diff <= 5 ? '#FFC400' : '#28a745'));
                        $label = $diff <= 0 ? '⚠️ Vencida' : $diff . ' día' . ($diff > 1 ? 's' : '');
                        $badge_bg = $diff <= 0 ? '#fce4ec' : ($diff <= 3 ? '#fff3e0' : '#fff8e1');
                    ?>
                    <tr>
                        <td><?php echo $rv->id; ?></td>
                        <td><strong><?php echo esc_html($rv->store_name); ?></strong></td>
                        <td><span style="background:#e0f7fa;color:#00695c;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;"><?php echo esc_html($rv->plan_name ?: 'N/A'); ?></span></td>
                        <td style="font-size:12px;"><?php echo esc_html($rv->email); ?></td>
                        <td style="font-size:12px;"><?php echo date('d/m/Y', strtotime($rv->subscription_end)); ?></td>
                        <td><span style="background:<?php echo $badge_bg; ?>;color:<?php echo $color; ?>;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;"><?php echo $label; ?></span></td>
                        <td><a href="<?php echo admin_url('admin.php?page=petsgo-vendor-form&id=' . $rv->id); ?>" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm">✏️ Editar</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Recent Orders (always) -->
            <div id="dash-orders"><div class="pg-dash-loader">⏳ Cargando pedidos...</div></div>

        <?php endif; ?>
        </div>

        <?php if (!$is_rider && ($is_admin || $is_vendor)): ?>
        <script>
        jQuery(function($){
            var hasAnalytics=<?php echo $has_analytics?'true':'false'; ?>;
            var isAdmin=PG.isAdmin;
            var charts={};
            var statusColors={'delivered':'#28a745','pending':'#FFC400','in_transit':'#00A8E8','cancelled':'#dc3545','payment_pending':'#fd7e14','preparing':'#6f42c1','ready':'#17a2b8'};
            var chartColors=['#00A8E8','#FFC400','#28a745','#dc3545','#6f42c1','#fd7e14','#17a2b8','#e83e8c'];

            // Initialize checklist widgets
            <?php if($is_admin): ?>$('#df-vendors').pgChecklist({placeholder:'Todas las tiendas'});<?php endif; ?>
            $('#df-category').pgChecklist({placeholder:'Todas las categorías'});
            $('#df-status').pgChecklist({placeholder:'Todos los estados'});

            function getFilters(){
                var vids=[];
                if(isAdmin){var v=$('#df-vendors').val()||[];vids=Array.isArray(v)?v:[];}
                var cats=$('#df-category').val()||[];
                var sts=$('#df-status').val()||[];
                return{
                    vendor_ids:vids.join(','),
                    category:Array.isArray(cats)?cats.join(','):(cats||''),
                    status:Array.isArray(sts)?sts.join(','):(sts||''),
                    date_from:$('#df-from').val()||'',
                    date_to:$('#df-to').val()||'',
                    search:$('#df-search').val()||''
                };
            }

            function loadDashboard(){
                var f=getFilters();
                PG.post('petsgo_dashboard_data',f,function(r){
                    if(!r.success){
                        $('#dash-kpis').html('<div class="pg-dash-loader" style="color:#dc3545;">❌ Error cargando datos: '+(r.data||'Sin respuesta')+'. <a href="javascript:loadDashboard()">Reintentar</a></div>');
                        $('#dash-orders').html('');
                        return;
                    }
                    var d=r.data;
                    $('#dash-updated').text(PG.fdate(d.updated_at));
                    renderKPIs(d);
                    if(hasAnalytics){renderCharts(d);renderTables(d);}
                    renderOrders(d);
                });
            }

            function renderKPIs(d){
                var h='<div class="pg-kpi-grid">';
                var kpis=[
                    {icon:'📦',bg:'#e3f5fc',val:d.total_orders,label:'Total Pedidos'},
                    {icon:'✅',bg:'#d4edda',val:d.delivered_orders,label:'Entregados',trend:d.success_rate+'% éxito',trend_cls:d.success_rate>=80?'up':(d.success_rate>=50?'neutral':'down')},
                    {icon:'⏳',bg:'#fff3cd',val:d.pending_orders,label:'Pendientes'},
                    {icon:'🚚',bg:'#d1ecf1',val:d.in_transit,label:'En Tránsito'},
                    {icon:'💰',bg:'#cce5ff',val:PG.money(d.total_revenue),label:'Ventas (entregados)',trend:(d.rev_trend>=0?'+':'')+d.rev_trend+'% vs 30d',trend_cls:d.rev_trend>=0?'up':'down'}
                ];
                if(isAdmin) kpis.push({icon:'🏦',bg:'#f5e6ff',val:PG.money(d.total_commission),label:'Comisiones PetsGo'});
                kpis.push({icon:'🎫',bg:'#ffecd2',val:PG.money(d.avg_ticket),label:'Ticket Promedio'});
                kpis.push({icon:'🛒',bg:'#e8f5e9',val:d.total_products,label:'Productos'});
                kpis.push({icon:'⚠️',bg:'#fff3cd',val:d.low_stock,label:'Stock Bajo (<5)'});
                kpis.push({icon:'🚫',bg:'#f8d7da',val:d.out_of_stock,label:'Sin Stock'});
                if(isAdmin){
                    kpis.push({icon:'🏪',bg:'#d1ecf1',val:d.active_vendors+' / '+d.total_vendors,label:'Tiendas Activas'});
                    kpis.push({icon:'👥',bg:'#e2e3e5',val:d.total_users,label:'Usuarios'});
                    kpis.push({icon:'🧾',bg:'#cce5ff',val:d.total_invoices,label:'Boletas'});
                    kpis.push({icon:'🚴',bg:'#f5e6ff',val:PG.money(d.total_delivery_fees),label:'Fees Delivery'});
                }
                if(d.cancelled_orders>0) kpis.push({icon:'❌',bg:'#f8d7da',val:d.cancelled_orders,label:'Cancelados'});
                $.each(kpis,function(i,k){
                    h+='<div class="pg-kpi"><div class="ki" style="background:'+k.bg+'">'+k.icon+'</div><div class="kv">'+k.val+'</div><div class="kl">'+k.label+'</div>';
                    if(k.trend) h+='<div class="kt '+k.trend_cls+'">'+k.trend+'</div>';
                    h+='</div>';
                });
                h+='</div>';
                $('#dash-kpis').html(h);
            }

            function destroyCharts(){$.each(charts,function(k,c){if(c)c.destroy();});charts={};}

            function renderCharts(d){
                destroyCharts();
                // Status donut
                if(d.status_labels.length){
                    charts.status=new Chart($('#pgChartStatus')[0],{
                        type:'doughnut',
                        data:{labels:d.status_labels.map(function(s){return PG.sEs(s);}),datasets:[{data:d.status_values,backgroundColor:d.status_labels.map(function(s){return statusColors[s]||'#aaa';}),borderWidth:2,borderColor:'#fff'}]},
                        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{padding:10,font:{size:11,family:'Poppins'}}}}}
                    });
                }
                // Revenue bar
                if(d.monthly_labels.length){
                    charts.revenue=new Chart($('#pgChartRevenue')[0],{
                        type:'bar',
                        data:{labels:d.monthly_labels,datasets:[{label:'Ingresos',data:d.monthly_revenue,backgroundColor:'rgba(0,168,232,0.7)',borderRadius:8,borderSkipped:false}]},
                        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:function(v){return'$'+Number(v).toLocaleString('es-CL')}}},x:{grid:{display:false}}}}
                    });
                }
                // Categories
                if(d.cat_labels.length){
                    charts.cats=new Chart($('#pgChartCategories')[0],{
                        type:'pie',
                        data:{labels:d.cat_labels,datasets:[{data:d.cat_values,backgroundColor:chartColors.slice(0,d.cat_labels.length),borderWidth:2,borderColor:'#fff'}]},
                        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{padding:10,font:{size:11,family:'Poppins'}}}}}
                    });
                }
                // Orders line
                if(d.monthly_labels.length){
                    charts.orders=new Chart($('#pgChartOrders')[0],{
                        type:'line',
                        data:{labels:d.monthly_labels,datasets:[{label:'Pedidos',data:d.monthly_orders,borderColor:'#FFC400',backgroundColor:'rgba(255,196,0,0.1)',fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#FFC400'}]},
                        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}},x:{grid:{display:false}}}}
                    });
                }
            }

            function renderTables(d){
                var h='';
                // Top Riders
                if(d.top_riders&&d.top_riders.length){
                    h+='<div class="pg-section"><h3>🚴 Riders Destacados</h3>';
                    h+='<div class="tbl-filter"><input type="text" placeholder="Buscar rider..." onkeyup="pgTblFilter(this,\'tbl-riders\')"></div>';
                    h+='<div style="overflow-x:auto"><table class="petsgo-table" id="tbl-riders"><thead><tr><th>#</th><th>Rider</th><th>Entregas</th><th>Asignadas</th><th>Tasa Éxito</th><th>Fees</th></tr></thead><tbody>';
                    $.each(d.top_riders,function(i,r){
                        var rate=r.total>0?Math.round(r.delivered/r.total*1000)/10:0;
                        var cls=rate>=80?'active':(rate>=50?'pending':'cancelled');
                        h+='<tr><td>'+(i+1)+'</td><td><strong>'+PG.esc(r.name)+'</strong></td>';
                        h+='<td>'+PG.badge('delivered')+' '+r.delivered+'</td><td>'+r.total+'</td>';
                        h+='<td>'+PG.badge(cls)+' '+rate+'%</td><td>'+PG.money(r.fees)+'</td></tr>';
                    });
                    h+='</tbody></table></div></div>';
                }
                // Top Vendors (admin)
                if(isAdmin&&d.top_vendors&&d.top_vendors.length){
                    h+='<div class="pg-section"><h3>🏆 Top Tiendas por Ingresos</h3>';
                    h+='<div class="tbl-filter"><input type="text" placeholder="Buscar tienda..." onkeyup="pgTblFilter(this,\'tbl-vendors\')"></div>';
                    h+='<div style="overflow-x:auto"><table class="petsgo-table" id="tbl-vendors"><thead><tr><th>#</th><th>Tienda</th><th>Estado</th><th>Pedidos</th><th>Ingresos</th><th>Comisión</th></tr></thead><tbody>';
                    $.each(d.top_vendors,function(i,v){
                        h+='<tr><td>'+(i+1)+'</td><td><strong>'+PG.esc(v.store_name)+'</strong></td>';
                        h+='<td>'+PG.badge(v.status)+'</td><td>'+v.orders+'</td>';
                        h+='<td><strong>'+PG.money(v.revenue)+'</strong></td><td>'+PG.money(v.commission)+'</td></tr>';
                    });
                    h+='</tbody></table></div></div>';
                }
                // Products inventory
                if(d.products&&d.products.length){
                    h+='<div class="pg-section"><h3>📋 Inventario</h3>';
                    h+='<div class="tbl-filter">';
                    h+='<input type="text" placeholder="Buscar producto..." onkeyup="pgTblFilter(this,\'tbl-products\')">';
                    h+='<select onchange="pgTblFilterCol(this,\'tbl-products\',1)"><option value="">Categoría</option>';
                    $.each(<?php echo json_encode($all_categories); ?>,function(i,c){h+='<option>'+c+'</option>';});
                    h+='</select>';
                    h+='<select onchange="pgTblFilterCol(this,\'tbl-products\',-1)"><option value="">Stock</option><option value="sinstock">Sin Stock</option><option value="bajo">Stock Bajo</option><option value="ok">OK</option></select>';
                    h+='</div>';
                    h+='<div style="overflow-x:auto"><table class="petsgo-table" id="tbl-products"><thead><tr><th>Producto</th><th>Categoría</th>';
                    if(isAdmin) h+='<th>Tienda</th>';
                    h+='<th>Precio</th><th>Stock</th><th>Estado</th></tr></thead><tbody>';
                    $.each(d.products,function(i,p){
                        var cls=p.stock==0?'cancelled':(p.stock<5?'pending':'active');
                        var lbl=p.stock==0?'Sin Stock':(p.stock<5?'Stock Bajo':'OK');
                        h+='<tr data-stock="'+(p.stock==0?'sinstock':(p.stock<5?'bajo':'ok'))+'">';
                        h+='<td><strong>'+PG.esc(p.product_name)+'</strong></td><td>'+PG.esc(p.category)+'</td>';
                        if(isAdmin) h+='<td>'+PG.esc(p.store_name)+'</td>';
                        h+='<td>'+PG.money(p.price)+'</td><td>'+p.stock+'</td>';
                        h+='<td>'+PG.badge(cls)+' '+lbl+'</td></tr>';
                    });
                    h+='</tbody></table></div></div>';
                }
                $('#dash-tables').html(h);
            }

            function renderOrders(d){
                var h='<div class="pg-section"><h3>🕐 Últimos Pedidos</h3>';
                h+='<div class="tbl-filter">';
                h+='<input type="text" placeholder="Buscar por cliente, tienda..." onkeyup="pgTblFilter(this,\'tbl-orders\')">';
                h+='<select onchange="pgTblFilterCol(this,\'tbl-orders\',-2)"><option value="">Estado</option>';
                $.each(<?php echo json_encode($all_statuses); ?>,function(k,lbl){h+='<option value="'+k+'">'+lbl+'</option>';});
                h+='</select></div>';
                if(!d.recent_orders||!d.recent_orders.length){
                    h+='<p style="color:#999;text-align:center;padding:20px;">No hay pedidos con estos filtros.</p>';
                }else{
                    h+='<div style="overflow-x:auto"><table class="petsgo-table" id="tbl-orders"><thead><tr><th>#</th><th>Cliente</th>';
                    if(isAdmin) h+='<th>Tienda</th>';
                    h+='<th>Total</th><th>Comisión</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
                    $.each(d.recent_orders,function(i,o){
                        h+='<tr data-status="'+o.status+'">';
                        h+='<td>'+o.id+'</td><td>'+PG.esc(o.customer_name||'N/A')+'</td>';
                        if(isAdmin) h+='<td>'+PG.esc(o.store_name||'N/A')+'</td>';
                        h+='<td>'+PG.money(o.total_amount)+'</td><td>'+PG.money(o.petsgo_commission)+'</td>';
                        h+='<td>'+PG.badge(o.status)+'</td><td>'+PG.fdate(o.created_at)+'</td></tr>';
                    });
                    h+='</tbody></table></div>';
                }
                h+='</div>';
                $('#dash-orders').html(h);
            }

            // Table filter helpers
            window.pgTblFilter=function(el,tid){
                var q=$(el).val().toLowerCase();
                $('#'+tid+' tbody tr').each(function(){$(this).toggle($(this).text().toLowerCase().indexOf(q)>-1);});
            };
            window.pgTblFilterCol=function(el,tid,colOrSpecial){
                var v=$(el).val().toLowerCase();
                if(colOrSpecial===-1){
                    // Stock filter by data-stock
                    $('#'+tid+' tbody tr').each(function(){if(!v)$(this).show();else $(this).toggle($(this).data('stock')===v);});
                }else if(colOrSpecial===-2){
                    // Status filter by data-status
                    $('#'+tid+' tbody tr').each(function(){if(!v)$(this).show();else $(this).toggle($(this).data('status')===v);});
                }else{
                    $('#'+tid+' tbody tr').each(function(){
                        if(!v){$(this).show();return;}
                        var cell=$(this).find('td').eq(colOrSpecial).text().toLowerCase();
                        $(this).toggle(cell===v);
                    });
                }
            };

            // Filter events
            $('#df-apply').on('click',loadDashboard);
            $('#df-reset').on('click',function(){
                // Reset pgChecklist widgets
                $('#df-vendors,#df-category,#df-status').each(function(){
                    var reset=$(this).data('pgcl-reset');
                    if(reset) reset(); else $(this).val([]);
                });
                $('#df-from,#df-to,#df-search').val('');
                loadDashboard();
            });
            // Enter key on search
            $('#df-search').on('keydown',function(e){if(e.keyCode===13){e.preventDefault();loadDashboard();}});

            // Export functions
            window.pgExportPDF=function(){
                var el=document.getElementById('pg-dashboard-content');
                var np=el.querySelectorAll('.no-print');np.forEach(function(b){b.style.display='none';});
                html2canvas(el,{scale:2,useCORS:true,logging:false}).then(function(canvas){
                    np.forEach(function(b){b.style.display='';});
                    var pdf=new jspdf.jsPDF({orientation:'p',unit:'mm',format:'a4'});
                    var w=pdf.internal.pageSize.getWidth(),ch=(canvas.height*w)/canvas.width,ph=pdf.internal.pageSize.getHeight(),y=0;
                    while(y<ch){if(y>0)pdf.addPage();pdf.addImage(canvas.toDataURL('image/jpeg',0.95),'JPEG',0,-y,w,ch);y+=ph;}
                    pdf.save('PetsGo_Dashboard_'+new Date().toISOString().slice(0,10)+'.pdf');
                });
            };
            window.pgExportImage=function(){
                var el=document.getElementById('pg-dashboard-content');
                var np=el.querySelectorAll('.no-print');np.forEach(function(b){b.style.display='none';});
                html2canvas(el,{scale:2,useCORS:true,logging:false}).then(function(canvas){
                    np.forEach(function(b){b.style.display='';});
                    var a=document.createElement('a');
                    a.download='PetsGo_Dashboard_'+new Date().toISOString().slice(0,10)+'.png';
                    a.href=canvas.toDataURL('image/png');a.click();
                });
            };

            // Initial load
            loadDashboard();
        });
        </script>
        <?php endif; ?>
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
                <select id="pg-filter-cat" multiple>
                    <?php foreach ($categories as $c): ?><option value="<?php echo esc_attr($c); ?>"><?php echo $c; ?></option><?php endforeach; ?>
                </select>
                <?php if ($is_admin): ?>
                <select id="pg-filter-vendor" multiple>
                    <?php foreach ($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pg-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="pg-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-product-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nuevo Producto</a>
            </div>

            <table class="petsgo-table">
                <thead id="pg-thead"><tr><th style="width:50px">Foto</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Categoría</th><?php if($is_admin): ?><th>Tienda</th><?php endif; ?><th style="width:140px">Acciones</th></tr></thead>
                <tbody id="pg-products-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody>
            </table>
        </div>
        <script>
        jQuery(function($){
            var timer;
            $('#pg-filter-cat').pgChecklist({placeholder:'Todas las categorías'});
            <?php if($is_admin): ?>$('#pg-filter-vendor').pgChecklist({placeholder:'Todas las tiendas'});<?php endif; ?>
            var cols=['_photo','product_name','price','stock','category'<?php if($is_admin): ?>,'store_name'<?php endif; ?>,'_actions'];
            var tbl=PG.table({
                thead:'#pg-thead',body:'#pg-products-body',perPage:25,defaultSort:'product_name',defaultDir:'asc',
                columns:cols,emptyMsg:'Sin resultados.',
                onTotal:function(n){$('#pg-total').text(n);},
                renderRow:function(p){
                    var thumb = p.image_url ? '<img src="'+p.image_url+'" class="petsgo-thumb">' : '<span style="display:inline-block;width:50px;height:50px;background:#f0f0f0;border-radius:6px;text-align:center;line-height:50px;color:#bbb;">📷</span>';
                    var sc = p.stock<5?'petsgo-stock-low':'petsgo-stock-ok';
                    var r='<tr><td>'+thumb+'</td>';
                    r+='<td><strong>'+PG.esc(p.product_name)+'</strong>';
                    if(p.description) r+='<br><small style="color:#888;">'+PG.esc(p.description.substring(0,60))+(p.description.length>60?'...':'')+'</small>';
                    r+='</td><td>'+PG.money(p.price)+'</td><td class="'+sc+'">'+p.stock+'</td>';
                    r+='<td>'+PG.esc(p.category||'—')+'</td>';
                    <?php if($is_admin): ?>r+='<td>'+PG.esc(p.store_name||'—')+'</td>';<?php endif; ?>
                    r+='<td><a href="'+PG.adminUrl+'?page=petsgo-product-form&id='+p.id+'" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm">✏️</a> ';
                    <?php if($is_admin): ?>r+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pg-del" data-id="'+p.id+'">🗑️</button>';<?php endif; ?>
                    r+='</td></tr>';
                    return r;
                }
            });
            function load(){
                $('#pg-loader').addClass('active');
                var cv=$('#pg-filter-cat').val()||[];
                var d = {search:$('#pg-search').val(), category:Array.isArray(cv)?cv.join(','):(cv||'')};
                <?php if($is_admin): ?>var vv=$('#pg-filter-vendor').val()||[];d.vendor_id=Array.isArray(vv)?vv.join(','):(vv||'');<?php endif; ?>
                PG.post('petsgo_search_products', d, function(r){
                    $('#pg-loader').removeClass('active');
                    if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            $('#pg-search').on('input',function(){clearTimeout(timer);timer=setTimeout(load,300);});
            $('#pg-filter-cat<?php if($is_admin): ?>, #pg-filter-vendor<?php endif; ?>').on('change',load);
            $('#pg-btn-search').on('click',load);
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
                        <!-- DESCUENTO -->
                        <div class="petsgo-form-section" style="margin-top:20px;">
                            <h3>🏷️ Descuento</h3>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="petsgo-field">
                                    <label>Descuento (%)</label>
                                    <input type="number" id="pf-discount" min="0" max="99" step="1" placeholder="0 = sin descuento" value="<?php echo esc_attr($product->discount_percent ?? '0'); ?>">
                                    <div class="field-hint">0 = sin descuento. Máx 99%.</div>
                                </div>
                                <div class="petsgo-field">
                                    <label>Vigencia</label>
                                    <select id="pf-discount-type">
                                        <option value="none" <?php if(empty($product->discount_percent) || floatval($product->discount_percent ?? 0) == 0) echo 'selected'; ?>>Sin descuento</option>
                                        <option value="indefinite" <?php if(floatval($product->discount_percent ?? 0) > 0 && empty($product->discount_start) && empty($product->discount_end)) echo 'selected'; ?>>Indefinido</option>
                                        <option value="scheduled" <?php if(!empty($product->discount_start) || !empty($product->discount_end)) echo 'selected'; ?>>Programado (fecha/hora)</option>
                                    </select>
                                </div>
                            </div>
                            <div id="pf-discount-dates" style="display:<?php echo (!empty($product->discount_start) || !empty($product->discount_end)) ? 'grid' : 'none'; ?>;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">
                                <div class="petsgo-field">
                                    <label>Desde (fecha y hora)</label>
                                    <input type="datetime-local" id="pf-discount-start" value="<?php echo esc_attr($product->discount_start ? date('Y-m-d\TH:i', strtotime($product->discount_start)) : ''); ?>">
                                </div>
                                <div class="petsgo-field">
                                    <label>Hasta (fecha y hora)</label>
                                    <input type="datetime-local" id="pf-discount-end" value="<?php echo esc_attr($product->discount_end ? date('Y-m-d\TH:i', strtotime($product->discount_end)) : ''); ?>">
                                </div>
                            </div>
                            <div id="pf-discount-preview" style="display:none;margin-top:12px;padding:12px;background:#fff3cd;border-radius:8px;border:1px solid #ffc107;">
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
                                    <div id="preview-price-wrap">
                                        <div class="preview-price" id="preview-price">$0</div>
                                        <div id="preview-discount" style="display:none;margin-top:4px;">
                                            <span style="text-decoration:line-through;color:#999;font-size:13px;" id="preview-original-price"></span>
                                            <span style="background:#dc3545;color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;font-weight:700;margin-left:6px;" id="preview-discount-badge"></span>
                                        </div>
                                    </div>
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
                var price=parseFloat($('#pf-price').val())||0;
                var disc=parseFloat($('#pf-discount').val())||0;
                var dtype=$('#pf-discount-type').val();
                if(disc>0 && dtype!=='none'){
                    var finalPrice=Math.round(price*(1-disc/100));
                    $('#preview-price').text(PG.money(finalPrice)).css('color','#dc3545');
                    $('#preview-original-price').text(PG.money(price));
                    $('#preview-discount-badge').text('-'+disc+'%');
                    $('#preview-discount').show();
                    $('#pf-discount-preview').html('💰 <strong>Precio original:</strong> '+PG.money(price)+' → <strong>Con descuento:</strong> '+PG.money(finalPrice)+' <span style=\"color:#dc3545;font-weight:700;\">(-'+disc+'%)</span>'+(dtype==='scheduled'?' <br>📅 Programado':'  ♾️ Indefinido')).show();
                }else{
                    $('#preview-price').text(PG.money(price)).css('color','#00A8E8');
                    $('#preview-discount').hide();
                    $('#pf-discount-preview').hide();
                }
                $('#preview-cat').text($('#pf-category').val()||'Categoría');
                var d=$('#pf-desc').val()||'Descripción...';$('#preview-desc').text(d.length>120?d.substring(0,120)+'...':d);
                var s=$('#pf-stock').val();
                if(s===''||s===undefined){$('#preview-stock').html('Stock: —');}else{var n=parseInt(s);$('#preview-stock').html('Stock: <strong style="color:'+(n<5?'#dc3545':'#28a745')+'">'+n+' uds</strong>');}
                var imgs='';$('.petsgo-img-slot').each(function(){var im=$(this).find('img');if(im.length)imgs+='<img src="'+im.attr('src')+'">';});
                $('#preview-imgs').html(imgs||'<div class="no-img">📷</div>');
            }
            $('#pf-name,#pf-price,#pf-stock,#pf-category,#pf-desc,#pf-discount,#pf-discount-type').on('input change',upd);
            // Toggle discount dates visibility
            $('#pf-discount-type').on('change',function(){
                if($(this).val()==='scheduled'){$('#pf-discount-dates').css('display','grid');}
                else{$('#pf-discount-dates').hide();$('#pf-discount-start,#pf-discount-end').val('');}
                if($(this).val()==='none'){$('#pf-discount').val(0);}
                upd();
            });
            upd();

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
                    image_id:$('#pf-image-0').val(),image_id_2:$('#pf-image-1').val(),image_id_3:$('#pf-image-2').val(),
                    discount_percent:$('#pf-discount').val()||0,discount_type:$('#pf-discount-type').val(),
                    discount_start:$('#pf-discount-start').val()||'',discount_end:$('#pf-discount-end').val()||''
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
                <select id="pv-filter-status" multiple><option value="">Todos los estados</option><option value="active">Activo</option><option value="pending">Pendiente</option><option value="inactive">Inactivo</option></select>
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pv-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="pv-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-vendor-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nueva Tienda</a>
            </div>
            <table class="petsgo-table"><thead id="pv-thead"><tr><th>ID</th><th>Tienda</th><th>RUT</th><th>Email</th><th>Plan</th><th>Suscripción</th><th>Comisión</th><th>Productos</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody id="pv-body"><tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var t;
            $('#pv-filter-status').pgChecklist({placeholder:'Todos los estados'});
            var tbl=PG.table({
                thead:'#pv-thead',body:'#pv-body',perPage:25,defaultSort:'id',defaultDir:'desc',
                columns:['id','store_name','rut','email','plan_name','subscription_end','sales_commission','total_products','status','_actions'],
                emptyMsg:'Sin resultados.',
                onTotal:function(n){$('#pv-total').text(n);},
                renderRow:function(v){
                    var r='<tr><td>'+v.id+'</td><td><strong>'+PG.esc(v.store_name)+'</strong></td><td>'+PG.esc(v.rut)+'</td>';
                    r+='<td>'+PG.esc(v.email)+'</td>';
                    r+='<td><span style="background:#e0f7fa;color:#00695c;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">'+(PG.esc(v.plan_name)||'—')+'</span></td>';
                    // Subscription dates with color coding
                    var subHtml='—';
                    if(v.subscription_start&&v.subscription_end){
                        var end=new Date(v.subscription_end);var now=new Date();var diff=Math.ceil((end-now)/(86400000));
                        var color=diff<=0?'#dc3545':diff<=10?'#e65100':'#2e7d32';
                        var label=diff<=0?'Vencida':'Activa';
                        subHtml='<div style="font-size:11px;line-height:1.4;"><div>'+PG.fdate(v.subscription_start)+' — '+PG.fdate(v.subscription_end)+'</div><span style="color:'+color+';font-weight:600;">'+label+(diff>0?' ('+diff+' días)':' ⚠️')+'</span></div>';
                    }
                    r+='<td>'+subHtml+'</td>';
                    r+='<td>'+v.sales_commission+'%</td>';
                    r+='<td>'+v.total_products+'</td>';
                    r+='<td>'+PG.badge(v.status)+'</td>';
                    r+='<td><a href="'+PG.adminUrl+'?page=petsgo-vendor-form&id='+v.id+'" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm" title="Editar">✏️</a> ';
                    r+='<a href="'+PG.adminUrl+'?page=petsgo-invoice-config&vendor_id='+v.id+'" class="petsgo-btn petsgo-btn-sm" style="background:#00A8E8;color:#fff;" title="Config Boleta">🧾</a> ';
                    r+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pv-del" data-id="'+v.id+'">🗑️</button></td></tr>';
                    return r;
                }
            });
            function load(){
                $('#pv-loader').addClass('active');
                var sv=$('#pv-filter-status').val()||[];
                PG.post('petsgo_search_vendors',{search:$('#pv-search').val(),status:Array.isArray(sv)?sv.join(','):(sv||'')},function(r){
                    $('#pv-loader').removeClass('active');if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            $('#pv-search').on('input',function(){clearTimeout(t);t=setTimeout(load,300);});
            $('#pv-filter-status').on('change',load);
            $('#pv-btn-search').on('click',load);
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
                        <div class="petsgo-field"><label>Inicio suscripción</label><input type="date" id="vf-sub-start" value="<?php echo esc_attr($vendor->subscription_start ?? ''); ?>"></div>
                        <div class="petsgo-field"><label>Fin suscripción</label><input type="date" id="vf-sub-end" value="<?php echo esc_attr($vendor->subscription_end ?? ''); ?>"></div>
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
                    user_id:$('#vf-user').val(),sales_commission:$('#vf-commission').val(),plan_id:$('#vf-plan').val(),
                    subscription_start:$('#vf-sub-start').val(),subscription_end:$('#vf-sub-end').val(),
                    status:$('#vf-status').val()
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
        $statuses = ['pending'=>'Pendiente','processing'=>'Procesando','in_transit'=>'En Tránsito','delivered'=>'Entregado','cancelled'=>'Cancelado'];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📦 Pedidos (<span id="po-total">...</span>)</h1>
            <?php if (!$is_admin && $vid): ?><div class="petsgo-info-bar">📌 Estás viendo solo los pedidos de tu tienda.</div><?php endif; ?>
            <div class="petsgo-search-bar">
                <input type="text" id="po-search" placeholder="🔍 Buscar por cliente..." autocomplete="off">
                <select id="po-filter-status" multiple><option value="">Todos los estados</option>
                    <?php foreach ($statuses as $sk => $sl): ?><option value="<?php echo $sk; ?>"><?php echo $sl; ?></option><?php endforeach; ?>
                </select>
                <?php if ($is_admin): ?>
                <select id="po-filter-vendor" multiple><option value="">Todas las tiendas</option>
                    <?php foreach ($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="po-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="po-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead id="po-thead"><tr><th>#</th><th>Cliente</th><?php if($is_admin): ?><th>Tienda</th><?php endif; ?><th>Total</th><th>Comisión</th><th>Delivery</th><th>Rider</th><th>Estado</th><th>Fecha</th><?php if($is_admin): ?><th>Cambiar</th><?php endif; ?></tr></thead>
            <tbody id="po-body"><tr><td colspan="10" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var t;var statuses=<?php echo json_encode($statuses); ?>;
            $('#po-filter-status').pgChecklist({placeholder:'Todos los estados'});
            <?php if($is_admin): ?>$('#po-filter-vendor').pgChecklist({placeholder:'Todas las tiendas'});<?php endif; ?>
            var cols=['id','customer_name'<?php if($is_admin): ?>,'store_name'<?php endif; ?>,'total_amount','petsgo_commission','delivery_fee','rider_name','status','created_at'<?php if($is_admin): ?>,'_actions'<?php endif; ?>];
            var tbl=PG.table({
                thead:'#po-thead',body:'#po-body',perPage:25,defaultSort:'id',defaultDir:'desc',
                columns:cols,emptyMsg:'Sin pedidos.',
                onTotal:function(n){$('#po-total').text(n);},
                renderRow:function(o){
                    var r='<tr><td>'+o.id+'</td><td>'+PG.esc(o.customer_name||'N/A')+'</td>';
                    <?php if($is_admin): ?>r+='<td>'+PG.esc(o.store_name||'N/A')+'</td>';<?php endif; ?>
                    r+='<td>'+PG.money(o.total_amount)+'</td><td>'+PG.money(o.petsgo_commission)+'</td><td>'+PG.money(o.delivery_fee)+'</td>';
                    r+='<td>'+PG.esc(o.rider_name||'Sin asignar')+'</td>';
                    r+='<td>'+PG.badge(o.status)+'</td><td>'+PG.fdate(o.created_at)+'</td>';
                    <?php if($is_admin): ?>
                    r+='<td><select class="po-status-sel" data-id="'+o.id+'" style="font-size:12px;">';
                    $.each(statuses,function(k,lbl){r+='<option value="'+k+'"'+(o.status===k?' selected':'')+'>'+lbl+'</option>';});
                    r+='</select> <button class="petsgo-btn petsgo-btn-sm petsgo-btn-success po-status-btn" data-id="'+o.id+'">✓</button>';
                    if(!o.has_invoice) r+=' <button class="petsgo-btn petsgo-btn-sm petsgo-btn-primary po-gen-invoice" data-id="'+o.id+'" title="Generar Boleta">🧾</button>';
                    else r+=' <a href="'+PG.adminUrl+'?page=petsgo-invoice-config&preview='+o.invoice_id+'" class="petsgo-btn petsgo-btn-sm" title="Ver Boleta" target="_blank">🧾✅</a>';
                    r+='</td>';
                    <?php endif; ?>
                    r+='</tr>';
                    return r;
                }
            });
            function load(){
                $('#po-loader').addClass('active');
                var sv=$('#po-filter-status').val()||[];
                var d={search:$('#po-search').val(),status:Array.isArray(sv)?sv.join(','):(sv||'')};
                <?php if($is_admin): ?>var vv=$('#po-filter-vendor').val()||[];d.vendor_id=Array.isArray(vv)?vv.join(','):(vv||'');<?php endif; ?>
                PG.post('petsgo_search_orders',d,function(r){
                    $('#po-loader').removeClass('active');if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            $('#po-search').on('input',function(){clearTimeout(t);t=setTimeout(load,300);});
            $('#po-filter-status<?php if($is_admin): ?>, #po-filter-vendor<?php endif; ?>').on('change',load);
            $('#po-btn-search').on('click',load);
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
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pu-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="pu-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-user-form'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">➕ Nuevo Usuario</a>
            </div>
            <table class="petsgo-table"><thead id="pu-thead"><tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>RUT/Doc</th><th>Rol</th><th>Tienda</th><th>🐾</th><th>Registrado</th><th>Acciones</th></tr></thead>
            <tbody id="pu-body"><tr><td colspan="11" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            var t;
            var tbl=PG.table({
                thead:'#pu-thead',body:'#pu-body',perPage:25,defaultSort:'ID',defaultDir:'desc',
                columns:['ID','user_login','display_name','user_email','phone','id_number','role_label','store_name','pet_count','user_registered','_actions'],
                emptyMsg:'Sin resultados.',
                onTotal:function(n){$('#pu-total').text(n);},
                renderRow:function(u){
                    var r='<tr><td>'+u.ID+'</td><td>'+PG.esc(u.user_login)+'</td><td>'+PG.esc(u.display_name)+'</td>';
                    r+='<td>'+PG.esc(u.user_email)+'</td>';
                    r+='<td style="font-size:12px;">'+PG.esc(u.phone||'—')+'</td>';
                    r+='<td style="font-size:12px;">'+PG.esc(u.id_number||'—')+'</td>';
                    r+='<td><span class="petsgo-role-tag '+u.role_key+'">'+PG.esc(u.role_label)+'</span></td>';
                    r+='<td>'+PG.esc(u.store_name||'—')+'</td>';
                    r+='<td style="text-align:center;">'+(u.pet_count>0?'🐾 '+u.pet_count:'—')+'</td>';
                    r+='<td>'+PG.esc(u.user_registered)+'</td>';
                    r+='<td><a href="'+PG.adminUrl+'?page=petsgo-user-form&id='+u.ID+'" class="petsgo-btn petsgo-btn-warning petsgo-btn-sm">✏️</a> ';
                    if(u.ID!=1) r+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pu-del" data-id="'+u.ID+'">🗑️</button>';
                    r+='</td></tr>';
                    return r;
                }
            });
            function load(){
                $('#pu-loader').addClass('active');
                PG.post('petsgo_search_users',{search:$('#pu-search').val(),role:$('#pu-filter-role').val()},function(r){
                    $('#pu-loader').removeClass('active');if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            $('#pu-search').on('input',function(){clearTimeout(t);t=setTimeout(load,300);});
            $('#pu-filter-role').on('change',load);
            $('#pu-btn-search').on('click',load);
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
        $profile = $uid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $uid)) : null;
        $roles = ['subscriber'=>'Cliente','petsgo_vendor'=>'Tienda (Vendor)','petsgo_rider'=>'Delivery (Rider)','petsgo_support'=>'Soporte','administrator'=>'Administrador'];
        $current_role = $user ? (in_array('administrator',$user->roles)?'administrator':(in_array('petsgo_vendor',$user->roles)?'petsgo_vendor':(in_array('petsgo_rider',$user->roles)?'petsgo_rider':(in_array('petsgo_support',$user->roles)?'petsgo_support':'subscriber')))) : 'subscriber';
        $pet_types = ['perro'=>'🐕 Perro','gato'=>'🐱 Gato','ave'=>'🐦 Ave','conejo'=>'🐰 Conejo','hamster'=>'🐹 Hámster','pez'=>'🐟 Pez','reptil'=>'🦎 Reptil','otro'=>'🐾 Otro'];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>👤 <?php echo $uid ? 'Editar Usuario #'.$uid : 'Nuevo Usuario'; ?></h1>
            <a href="<?php echo admin_url('admin.php?page=petsgo-users'); ?>" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" style="margin-bottom:16px;display:inline-block;">← Volver</a>
            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
            <!-- Left: User data -->
            <form id="user-form" novalidate style="flex:1;min-width:320px;max-width:600px;">
                <input type="hidden" id="uf-id" value="<?php echo $uid; ?>">
                <div class="petsgo-form-section">
                    <h3>📋 Datos del Usuario</h3>
                    <div class="petsgo-field" id="uf-f-login"><label>Usuario *</label><input type="text" id="uf-login" value="<?php echo esc_attr($user->user_login ?? ''); ?>" <?php echo $uid?'readonly':''; ?> maxlength="60"><div class="field-error">Obligatorio.</div></div>
                    <div style="display:flex;gap:12px;">
                        <div class="petsgo-field" id="uf-f-fname" style="flex:1;"><label>Nombre *</label><input type="text" id="uf-fname" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-]+" oninput="this.value=this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-]/g,'')" value="<?php echo esc_attr($profile->first_name ?? ($user->first_name ?? '')); ?>"><div class="field-error">Obligatorio.</div></div>
                        <div class="petsgo-field" id="uf-f-lname" style="flex:1;"><label>Apellido *</label><input type="text" id="uf-lname" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-]+" oninput="this.value=this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-]/g,'')" value="<?php echo esc_attr($profile->last_name ?? ($user->last_name ?? '')); ?>"><div class="field-error">Obligatorio.</div></div>
                    </div>
                    <div class="petsgo-field" id="uf-f-email"><label>Email *</label><input type="email" id="uf-email" value="<?php echo esc_attr($user->user_email ?? ''); ?>"><div class="field-error">Email válido obligatorio.</div></div>
                    <div style="display:flex;gap:12px;">
                        <div class="petsgo-field" style="width:140px;"><label>Tipo Doc.</label>
                            <select id="uf-idtype"><option value="rut" <?php selected($profile->id_type??'rut','rut'); ?>>RUT</option><option value="dni" <?php selected($profile->id_type??'','dni'); ?>>DNI</option><option value="passport" <?php selected($profile->id_type??'','passport'); ?>>Pasaporte</option></select>
                        </div>
                        <div class="petsgo-field" id="uf-f-idnum" style="flex:1;"><label>N° Documento *</label><input type="text" id="uf-idnum" value="<?php echo esc_attr($profile->id_number ?? ''); ?>" placeholder="12.345.678-K"><div class="field-error">Documento inválido.</div></div>
                    </div>
                    <div style="display:flex;gap:12px;">
                        <div class="petsgo-field" style="flex:1;"><label>Teléfono</label><div style="display:flex;"><span style="padding:8px 10px;background:#e5e7eb;border-radius:4px 0 0 4px;border:1px solid #8c8f94;border-right:none;font-weight:600;white-space:nowrap;">+569</span><input type="text" id="uf-phone" value="<?php $ph=$profile->phone??''; $phClean=preg_replace('/[^0-9]/','', $ph); if(strlen($phClean)===11&&substr($phClean,0,3)==='569') $phClean=substr($phClean,3); elseif(strlen($phClean)===9&&$phClean[0]==='9') $phClean=substr($phClean,1); echo esc_attr(substr($phClean,0,8)); ?>" placeholder="XXXXXXXX" maxlength="8" style="border-radius:0 4px 4px 0;"></div></div>
                        <div class="petsgo-field" style="flex:1;"><label>Fecha Nacimiento</label><input type="date" id="uf-birth" value="<?php echo esc_attr($profile->birth_date ?? ''); ?>"></div>
                    </div>
                    <div class="petsgo-field"><label>Contraseña <?php echo $uid?'(dejar vacío para no cambiar)':'*'; ?></label><input type="password" id="uf-pass" autocomplete="new-password"><div class="field-hint"><?php echo $uid?'':'Mín. 8 caracteres, mayúscula, minúscula, número y especial.'; ?></div></div>
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

            <!-- Right: Pets (only if editing existing user) -->
            <?php if ($uid): ?>
            <div class="petsgo-form-section" style="flex:1;min-width:320px;max-width:550px;">
                <h3>🐾 Mascotas de <?php echo esc_html($user->display_name); ?> (<span id="pet-count">0</span>)</h3>
                <div id="pet-list" style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;"></div>
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pet-add-btn">➕ Agregar Mascota</button>
                <span class="petsgo-loader" id="pet-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>

                <!-- Pet edit modal -->
                <div id="pet-modal" style="display:none;margin-top:16px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:12px;padding:20px;">
                    <h4 id="pet-modal-title" style="margin:0 0 16px;">🐾 Nueva Mascota</h4>
                    <input type="hidden" id="pm-id" value="0">
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <div class="petsgo-field" style="width:140px;"><label>Tipo *</label>
                            <select id="pm-type"><?php foreach ($pet_types as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="petsgo-field" style="flex:1;min-width:150px;"><label>Nombre *</label><input type="text" id="pm-name"></div>
                    </div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <div class="petsgo-field" style="flex:1;"><label>Raza</label><input type="text" id="pm-breed" placeholder="Ej: Labrador, Siamés..."></div>
                        <div class="petsgo-field" style="width:160px;"><label>Fecha Nacimiento</label><input type="date" id="pm-birth"></div>
                    </div>
                    <div class="petsgo-field"><label>Foto</label>
                        <div id="pm-photo-wrap" style="display:flex;align-items:center;gap:14px;margin-bottom:4px;">
                            <div id="pm-photo-preview" style="width:64px;height:64px;border-radius:12px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f9fafb;flex-shrink:0;">
                                <span style="font-size:24px;">📷</span>
                            </div>
                            <div>
                                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pm-photo-btn">📁 Seleccionar foto</button>
                                <button type="button" class="petsgo-btn petsgo-btn-danger petsgo-btn-sm" id="pm-photo-clear" style="display:none;margin-left:6px;">🗑️ Quitar</button>
                                <p style="font-size:11px;color:#999;margin:4px 0 0;">JPG, PNG, WebP. O pega una URL abajo.</p>
                            </div>
                        </div>
                        <input type="text" id="pm-photo" placeholder="https://... (o usa el botón de arriba)" style="font-size:12px;">
                    </div>
                    <div class="petsgo-field"><label>Notas</label><textarea id="pm-notes" rows="2" style="width:100%;"></textarea></div>
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pm-save">💾 Guardar</button>
                        <button type="button" class="petsgo-btn petsgo-btn-sm" id="pm-cancel" style="background:#e9ecef;color:#555;">Cancelar</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </div>
        <script>
        jQuery(function($){
            // --- User form ---
            $('#user-form').on('submit',function(e){
                e.preventDefault();var ok=true;
                if(!$.trim($('#uf-login').val())){$('#uf-f-login').addClass('has-error');ok=false;}else{$('#uf-f-login').removeClass('has-error');}
                if(!$.trim($('#uf-fname').val())){$('#uf-f-fname').addClass('has-error');ok=false;}else{$('#uf-f-fname').removeClass('has-error');}
                if(!$.trim($('#uf-lname').val())){$('#uf-f-lname').addClass('has-error');ok=false;}else{$('#uf-f-lname').removeClass('has-error');}
                if(!$('#uf-email').val()||$('#uf-email').val().indexOf('@')<1){$('#uf-f-email').addClass('has-error');ok=false;}else{$('#uf-f-email').removeClass('has-error');}
                if(!ok)return;
                $('#uf-loader').addClass('active');$('#uf-msg').hide();
                PG.post('petsgo_save_user',{
                    id:$('#uf-id').val(),user_login:$('#uf-login').val(),
                    display_name:$.trim($('#uf-fname').val()+' '+$('#uf-lname').val()),
                    first_name:$('#uf-fname').val(),last_name:$('#uf-lname').val(),
                    user_email:$('#uf-email').val(),password:$('#uf-pass').val(),role:$('#uf-role').val(),
                    id_type:$('#uf-idtype').val(),id_number:$('#uf-idnum').val(),
                    phone:'+569'+$('#uf-phone').val(),birth_date:$('#uf-birth').val()
                },function(r){
                    $('#uf-loader').removeClass('active');
                    var cls=r.success?'notice-success':'notice-error';
                    $('#uf-msg').html('<div class="notice '+cls+'" style="padding:10px"><p>'+(r.success?'✅ '+r.data.message:'❌ '+r.data)+'</p></div>').show();
                    if(r.success&&!$('#uf-id').val()&&r.data.id){$('#uf-id').val(r.data.id);location.href=PG.adminUrl+'?page=petsgo-user-form&id='+r.data.id;}
                });
            });
            $('.petsgo-field input,.petsgo-field select').on('input change',function(){$(this).closest('.petsgo-field').removeClass('has-error');});

            <?php if ($uid): ?>
            // --- Pets ---
            function loadPets(){
                $('#pet-loader').addClass('active');
                PG.post('petsgo_search_pets',{user_id:<?php echo $uid; ?>},function(r){
                    $('#pet-loader').removeClass('active');
                    if(!r.success){$('#pet-list').html('<p style="color:#999;">Error cargando mascotas.</p>');return;}
                    var pets=r.data;$('#pet-count').text(pets.length);
                    if(!pets.length){$('#pet-list').html('<p style="color:#999;font-size:13px;">Sin mascotas registradas.</p>');return;}
                    var html='';
                    $.each(pets,function(i,p){
                        var img=p.photo_url?'<img src="'+PG.esc(p.photo_url)+'" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">':'<span style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:50%;background:#f0f9ff;font-size:24px;">🐾</span>';
                        html+='<div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:#fff;border:1px solid #e9ecef;border-radius:10px;">';
                        html+=img;
                        html+='<div style="flex:1;"><strong style="font-size:14px;">'+PG.esc(p.name)+'</strong>';
                        html+=' <span style="font-size:11px;color:#888;background:#f0f0f0;padding:2px 8px;border-radius:12px;margin-left:6px;">'+PG.esc(p.pet_type)+'</span>';
                        if(p.breed) html+='<br><span style="font-size:12px;color:#666;">'+PG.esc(p.breed)+'</span>';
                        if(p.age) html+=' <span style="font-size:11px;color:#00A8E8;">• '+PG.esc(p.age)+'</span>';
                        html+='</div>';
                        html+='<button class="petsgo-btn petsgo-btn-warning petsgo-btn-sm pet-edit" data-pet=\''+JSON.stringify(p)+'\'>✏️</button>';
                        html+=' <button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pet-del" data-id="'+p.id+'">🗑️</button>';
                        html+='</div>';
                    });
                    $('#pet-list').html(html);
                });
            }
            function resetPetModal(){$('#pm-id').val(0);$('#pm-type').val('perro');$('#pm-name,#pm-breed,#pm-photo,#pm-notes').val('');$('#pm-birth').val('');$('#pet-modal-title').text('🐾 Nueva Mascota');updatePhotoPreview('');}
            function updatePhotoPreview(url){
                if(url){$('#pm-photo-preview').html('<img src="'+url+'" style="width:100%;height:100%;object-fit:cover;">');$('#pm-photo-clear').show();}
                else{$('#pm-photo-preview').html('<span style="font-size:24px;">📷</span>');$('#pm-photo-clear').hide();}
            }
            $('#pm-photo').on('input change',function(){updatePhotoPreview($(this).val());});
            $('#pm-photo-clear').on('click',function(){$('#pm-photo').val('');updatePhotoPreview('');});
            // WordPress Media Uploader
            var petMediaFrame;
            $('#pm-photo-btn').on('click',function(e){
                e.preventDefault();
                if(petMediaFrame){petMediaFrame.open();return;}
                petMediaFrame=wp.media({title:'Seleccionar foto de mascota',button:{text:'Usar esta foto'},multiple:false,library:{type:'image'}});
                petMediaFrame.on('select',function(){
                    var att=petMediaFrame.state().get('selection').first().toJSON();
                    var url=att.sizes&&att.sizes.medium?att.sizes.medium.url:att.url;
                    $('#pm-photo').val(url);
                    updatePhotoPreview(url);
                });
                petMediaFrame.open();
            });
            $('#pet-add-btn').on('click',function(){resetPetModal();$('#pet-modal').slideDown(200);});
            $('#pm-cancel').on('click',function(){$('#pet-modal').slideUp(200);});
            $(document).on('click','.pet-edit',function(){
                var p=JSON.parse($(this).attr('data-pet'));
                $('#pm-id').val(p.id);$('#pm-type').val(p.pet_type);$('#pm-name').val(p.name);
                $('#pm-breed').val(p.breed);$('#pm-birth').val(p.birth_date||'');
                $('#pm-photo').val(p.photo_url);$('#pm-notes').val(p.notes||'');
                updatePhotoPreview(p.photo_url||'');
                $('#pet-modal-title').text('✏️ Editar: '+p.name);
                $('#pet-modal').slideDown(200);
            });
            $(document).on('click','.pet-del',function(){
                if(!confirm('¿Eliminar esta mascota?'))return;
                PG.post('petsgo_delete_pet',{id:$(this).data('id')},function(r){if(r.success)loadPets();else alert(r.data);});
            });
            $('#pm-save').on('click',function(){
                if(!$.trim($('#pm-name').val())){alert('Nombre de mascota obligatorio');return;}
                PG.post('petsgo_save_pet',{
                    id:$('#pm-id').val(),user_id:<?php echo $uid; ?>,
                    pet_type:$('#pm-type').val(),name:$('#pm-name').val(),breed:$('#pm-breed').val(),
                    birth_date:$('#pm-birth').val(),photo_url:$('#pm-photo').val(),notes:$('#pm-notes').val()
                },function(r){
                    if(r.success){$('#pet-modal').slideUp(200);loadPets();}else{alert(r.data);}
                });
            });
            loadPets();
            <?php endif; ?>
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
            <?php if ($is_admin): ?>
            <div class="petsgo-tabs" style="margin-bottom:16px;">
                <button class="petsgo-tab active" data-tab="pd-tab-deliveries" onclick="document.querySelectorAll('.petsgo-tab').forEach(t=>t.classList.remove('active'));this.classList.add('active');document.querySelectorAll('.pd-tab-content').forEach(c=>c.style.display='none');document.getElementById(this.dataset.tab).style.display='block';">📦 Entregas</button>
                <button class="petsgo-tab" data-tab="pd-tab-riders" onclick="document.querySelectorAll('.petsgo-tab').forEach(t=>t.classList.remove('active'));this.classList.add('active');document.querySelectorAll('.pd-tab-content').forEach(c=>c.style.display='none');document.getElementById(this.dataset.tab).style.display='block';loadRiderDocs();">📋 Documentos Riders</button>
                <button class="petsgo-tab" data-tab="pd-tab-ratings" onclick="document.querySelectorAll('.petsgo-tab').forEach(t=>t.classList.remove('active'));this.classList.add('active');document.querySelectorAll('.pd-tab-content').forEach(c=>c.style.display='none');document.getElementById(this.dataset.tab).style.display='block';loadRiderRatings();">⭐ Valoraciones</button>
                <button class="petsgo-tab" data-tab="pd-tab-payouts" onclick="document.querySelectorAll('.petsgo-tab').forEach(t=>t.classList.remove('active'));this.classList.add('active');document.querySelectorAll('.pd-tab-content').forEach(c=>c.style.display='none');document.getElementById(this.dataset.tab).style.display='block';loadPayouts();">💰 Pagos Riders</button>
            </div>
            <?php endif; ?>
            <?php if (!$is_admin): ?><div class="petsgo-info-bar">📌 Estás viendo solo tus entregas asignadas.</div><?php endif; ?>
            <div id="pd-tab-deliveries" class="pd-tab-content">
            <div class="petsgo-search-bar">
                <select id="pd-filter-status" multiple><option value="">Todos</option><option value="in_transit">En Tránsito</option><option value="delivered">Entregado</option><option value="pending">Pendiente</option><option value="preparing">Preparando</option><option value="ready">Listo</option><option value="cancelled">Cancelado</option></select>
                <?php if ($is_admin): ?>
                <select id="pd-filter-rider" multiple><option value="">Todos los riders</option><option value="unassigned">Sin asignar</option>
                    <?php foreach ($riders as $r): ?><option value="<?php echo $r->ID; ?>"><?php echo esc_html($r->display_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="pd-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="pd-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead id="pd-thead"><tr><th>Pedido #</th><th>Cliente</th><th>Tienda</th><th>Total</th><th>Fee Delivery</th><th>Rider</th><th>Estado</th><th>Fecha</th><?php if($is_admin): ?><th>Asignar Rider</th><?php endif; ?></tr></thead>
            <tbody id="pd-body"><tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
            </div>
            <?php if ($is_admin): ?>
            <!-- Documentos Riders Tab -->
            <div id="pd-tab-riders" class="pd-tab-content" style="display:none;">
                <div class="petsgo-search-bar"><select id="pdr-filter-status"><option value="">Todos los estados</option><option value="pending">⏳ Pendientes</option><option value="approved">✅ Aprobados</option><option value="rejected">❌ Rechazados</option></select>
                <button class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" onclick="loadRiderDocs()">🔍 Buscar</button>
                <span class="petsgo-loader" id="pdr-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span></div>
                <table class="petsgo-table"><thead><tr><th>Rider</th><th>Vehículo</th><th>Documento</th><th>Archivo</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                <tbody id="pdr-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Haz clic en Buscar para cargar.</td></tr></tbody></table>
            </div>
            <!-- Valoraciones Tab -->
            <div id="pd-tab-ratings" class="pd-tab-content" style="display:none;">
                <div class="petsgo-search-bar">
                <select id="pdrt-filter-rider"><option value="">Todos los riders</option>
                    <?php foreach ($riders as $r): ?><option value="<?php echo $r->ID; ?>"><?php echo esc_html($r->display_name); ?></option><?php endforeach; ?>
                </select>
                <button class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" onclick="loadRiderRatings()">🔍 Buscar</button>
                <span class="petsgo-loader" id="pdrt-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span></div>
                <table class="petsgo-table"><thead><tr><th>Rider</th><th>Pedido #</th><th>Valoró</th><th>Tipo</th><th>⭐ Rating</th><th>Comentario</th><th>Fecha</th></tr></thead>
                <tbody id="pdrt-body"><tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Haz clic en Buscar para cargar.</td></tr></tbody></table>
            </div>
            <!-- Pagos Riders Tab -->
            <div id="pd-tab-payouts" class="pd-tab-content" style="display:none;">
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
                    <select id="pdp-filter-status"><option value="">Todos los estados</option><option value="pending">⏳ Pendientes</option><option value="paid">✅ Pagados</option><option value="failed">❌ Fallidos</option></select>
                    <select id="pdp-filter-rider"><option value="">Todos los riders</option>
                        <?php foreach ($riders as $r): ?><option value="<?php echo $r->ID; ?>"><?php echo esc_html($r->display_name); ?></option><?php endforeach; ?>
                    </select>
                    <button class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" onclick="loadPayouts()">🔍 Buscar</button>
                    <button class="petsgo-btn petsgo-btn-success petsgo-btn-sm" onclick="generateWeeklyPayouts()" style="margin-left:auto;">📊 Generar Liquidación Semanal</button>
                    <span class="petsgo-loader" id="pdp-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                </div>
                <div id="pdp-summary" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
                    <div style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.08);text-align:center;">
                        <span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;">Total Pendiente</span>
                        <p id="pdp-total-pending" style="font-size:22px;font-weight:900;color:#F97316;margin:4px 0 0;">$0</p>
                    </div>
                    <div style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.08);text-align:center;">
                        <span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;">Total Pagado (mes)</span>
                        <p id="pdp-total-paid" style="font-size:22px;font-weight:900;color:#22C55E;margin:4px 0 0;">$0</p>
                    </div>
                    <div style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.08);text-align:center;">
                        <span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;">Riders Activos</span>
                        <p id="pdp-active-riders" style="font-size:22px;font-weight:900;color:#2F3A40;margin:4px 0 0;">0</p>
                    </div>
                    <div style="background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.08);text-align:center;">
                        <span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;">Comisión Rider</span>
                        <p style="font-size:22px;font-weight:900;color:#00A8E8;margin:4px 0 0;"><?php echo intval($this->pg_setting('rider_commission_pct', 88)); ?>%</p>
                    </div>
                </div>
                <table class="petsgo-table"><thead><tr><th>ID</th><th>Rider</th><th>Período</th><th>Entregas</th><th>Ganado</th><th>Neto</th><th>Estado</th><th>Pagado</th><th>Acciones</th></tr></thead>
                <tbody id="pdp-body"><tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Haz clic en Buscar para cargar.</td></tr></tbody></table>
            </div>
            <?php endif; ?>
        </div>
        <script>
        jQuery(function($){
            var riders=<?php echo json_encode($riders); ?>;
            $('#pd-filter-status').pgChecklist({placeholder:'Todos los estados'});
            <?php if($is_admin): ?>$('#pd-filter-rider').pgChecklist({placeholder:'Todos los riders'});<?php endif; ?>
            var cols=['id','customer_name','store_name','total_amount','delivery_fee','rider_name','status','created_at'<?php if($is_admin): ?>,'_actions'<?php endif; ?>];
            var tbl=PG.table({
                thead:'#pd-thead',body:'#pd-body',perPage:25,defaultSort:'id',defaultDir:'desc',
                columns:cols,emptyMsg:'Sin entregas.',
                onTotal:function(n){$('#pd-total').text(n);},
                renderRow:function(o){
                    var r='<tr><td>'+o.id+'</td><td>'+PG.esc(o.customer_name||'N/A')+'</td><td>'+PG.esc(o.store_name||'N/A')+'</td>';
                    r+='<td>'+PG.money(o.total_amount)+'</td><td>'+PG.money(o.delivery_fee)+'</td>';
                    r+='<td>'+PG.esc(o.rider_name||'Sin asignar')+'</td>';
                    r+='<td>'+PG.badge(o.status)+'</td><td>'+PG.fdate(o.created_at)+'</td>';
                    <?php if($is_admin): ?>
                    r+='<td><select class="pd-rider-sel" data-id="'+o.id+'" style="font-size:12px;"><option value="">—</option>';
                    $.each(riders,function(j,rd){r+='<option value="'+rd.ID+'"'+(o.rider_id==rd.ID?' selected':'')+'>'+PG.esc(rd.display_name)+'</option>';});
                    r+='</select> <button class="petsgo-btn petsgo-btn-sm petsgo-btn-success pd-assign" data-id="'+o.id+'">✓</button></td>';
                    <?php endif; ?>
                    r+='</tr>';
                    return r;
                }
            });
            function load(){
                $('#pd-loader').addClass('active');
                var sv=$('#pd-filter-status').val()||[];
                var d={status:Array.isArray(sv)?sv.join(','):(sv||'')};
                <?php if($is_admin): ?>var rv=$('#pd-filter-rider').val()||[];d.rider_id=Array.isArray(rv)?rv.join(','):(rv||'');<?php endif; ?>
                PG.post('petsgo_search_riders',d,function(r){
                    $('#pd-loader').removeClass('active');if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            $('#pd-filter-status<?php if($is_admin): ?>, #pd-filter-rider<?php endif; ?>').on('change',load);
            $('#pd-btn-search').on('click',load);
            $(document).on('click','.pd-assign',function(){
                var id=$(this).data('id');var rid=$('.pd-rider-sel[data-id="'+id+'"]').val();
                PG.post('petsgo_save_rider_assignment',{order_id:id,rider_id:rid},function(r){if(r.success)load();else alert(r.data);});
            });
            load();
            <?php if($is_admin): ?>
            // === Rider Documents tab ===
            window.loadRiderDocs=function(){
                $('#pdr-loader').addClass('active');
                PG.post('petsgo_search_rider_docs',{status:$('#pdr-filter-status').val()},function(r){
                    $('#pdr-loader').removeClass('active');
                    if(!r.success||!r.data.length){$('#pdr-body').html('<tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Sin documentos.</td></tr>');return;}
                    var h='';
                    $.each(r.data,function(i,d){
                        var docLabels={'license':'🪪 Licencia de conducir','vehicle_registration':'📄 Padrón vehículo','id_card':'🆔 Documento identidad','selfie':'📸 Selfie','vehicle_photo_1':'🚗 Foto vehículo #1','vehicle_photo_2':'🚗 Foto vehículo #2','vehicle_photo_3':'🚗 Foto vehículo #3'};
                        var stBadge=d.status==='approved'?'<span style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">✅ Aprobado</span>':
                            d.status==='rejected'?'<span style="background:#fce4ec;color:#c62828;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">❌ Rechazado</span>':
                            '<span style="background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">⏳ Pendiente</span>';
                        var vType=d.vehicle_type||'N/A';
                        var vtIcon=vType==='moto'?'🏍️':vType==='auto'?'🚗':vType==='bicicleta'?'🚲':vType==='scooter'?'🛵':'🚶';
                        h+='<tr><td>'+PG.esc(d.rider_name)+'<br><small style=\"color:#999;\">'+PG.esc(d.id_number||'')+'</small></td><td>'+vtIcon+' '+PG.esc(vType)+'</td>';
                        h+='<td>'+(docLabels[d.doc_type]||d.doc_type)+'</td>';
                        h+='<td><a href="'+d.file_url+'" target="_blank" style="color:#00A8E8;font-weight:600;">📎 '+PG.esc(d.file_name||'Ver')+'</a></td>';
                        h+='<td>'+stBadge+'</td><td>'+PG.fdate(d.uploaded_at)+'</td>';
                        h+='<td>';
                        if(d.status==='pending'){
                            h+='<button class="petsgo-btn petsgo-btn-sm petsgo-btn-success pdr-review" data-id="'+d.id+'" data-action="approved" title="Aprobar">✅</button> ';
                            h+='<button class="petsgo-btn petsgo-btn-sm petsgo-btn-danger pdr-review" data-id="'+d.id+'" data-action="rejected" title="Rechazar">❌</button>';
                        } else {
                            h+='<span style="color:#aaa;font-size:11px;">Revisado</span>';
                        }
                        h+='</td></tr>';
                    });
                    $('#pdr-body').html(h);
                });
            };
            $(document).on('click','.pdr-review',function(){
                var id=$(this).data('id'),action=$(this).data('action');
                var notes='';
                if(action==='rejected'){notes=prompt('Motivo del rechazo:');if(notes===null)return;}
                PG.post('petsgo_review_rider_doc',{doc_id:id,status:action,notes:notes},function(r){
                    if(r.success)loadRiderDocs();else alert(r.data);
                });
            });
            // === Ratings tab ===
            window.loadRiderRatings=function(){
                $('#pdrt-loader').addClass('active');
                PG.post('petsgo_search_rider_docs',{type:'ratings',rider_id:$('#pdrt-filter-rider').val()},function(r){
                    $('#pdrt-loader').removeClass('active');
                    if(!r.success||!r.data.length){$('#pdrt-body').html('<tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Sin valoraciones.</td></tr>');return;}
                    var h='';
                    $.each(r.data,function(i,d){
                        var stars='';for(var s=1;s<=5;s++)stars+=(s<=d.rating?'⭐':'☆');
                        var raterLabel=d.rater_type==='vendor'?'🏪 Tienda':'👤 Cliente';
                        h+='<tr><td>'+PG.esc(d.rider_name)+'</td><td>#'+d.order_id+'</td><td>'+PG.esc(d.rater_name)+'</td>';
                        h+='<td>'+raterLabel+'</td><td>'+stars+' ('+d.rating+')</td>';
                        h+='<td>'+PG.esc(d.comment||'Sin comentario')+'</td><td>'+PG.fdate(d.created_at)+'</td></tr>';
                    });
                    $('#pdrt-body').html(h);
                });
            };
            // === Payouts tab ===
            window.loadPayouts=function(){
                $('#pdp-loader').addClass('active');
                PG.post('petsgo_search_rider_payouts',{status:$('#pdp-filter-status').val(),rider_id:$('#pdp-filter-rider').val()},function(r){
                    $('#pdp-loader').removeClass('active');
                    if(!r.success||!r.data.length){$('#pdp-body').html('<tr><td colspan="9" style="text-align:center;padding:30px;color:#999;">Sin pagos registrados.</td></tr>');updatePayoutSummary([]);return;}
                    var h='',totalPending=0,totalPaid=0,activeRiders={};
                    $.each(r.data,function(i,p){
                        var stColors={pending:'#F97316',paid:'#22C55E',failed:'#EF4444',processing:'#3B82F6'};
                        var stLabels={pending:'⏳ Pendiente',paid:'✅ Pagado',failed:'❌ Fallido',processing:'🔄 Procesando'};
                        var stBadge='<span style="background:'+(stColors[p.status]||'#6b7280')+'15;color:'+(stColors[p.status]||'#6b7280')+';padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">'+(stLabels[p.status]||p.status)+'</span>';
                        if(p.status==='pending')totalPending+=parseFloat(p.net_amount);
                        if(p.status==='paid')totalPaid+=parseFloat(p.net_amount);
                        activeRiders[p.rider_id]=true;
                        h+='<tr><td>'+p.id+'</td><td>'+PG.esc(p.rider_name)+'</td>';
                        h+='<td>'+PG.fdate(p.period_start)+' — '+PG.fdate(p.period_end)+'</td>';
                        h+='<td>'+p.total_deliveries+'</td>';
                        h+='<td>'+PG.money(p.total_earned)+'</td><td>'+PG.money(p.net_amount)+'</td>';
                        h+='<td>'+stBadge+'</td><td>'+(p.paid_at?PG.fdate(p.paid_at):'—')+'</td>';
                        h+='<td>';
                        if(p.status==='pending'){
                            h+='<button class="petsgo-btn petsgo-btn-sm petsgo-btn-success pdp-action" data-id="'+p.id+'" data-action="paid" title="Marcar pagado">💸 Pagar</button> ';
                            h+='<button class="petsgo-btn petsgo-btn-sm petsgo-btn-danger pdp-action" data-id="'+p.id+'" data-action="failed" title="Marcar fallido">❌</button>';
                        } else h+='<span style="color:#aaa;font-size:11px;">Procesado</span>';
                        h+='</td></tr>';
                    });
                    $('#pdp-body').html(h);
                    $('#pdp-total-pending').text(PG.money(totalPending));
                    $('#pdp-total-paid').text(PG.money(totalPaid));
                    $('#pdp-active-riders').text(Object.keys(activeRiders).length);
                });
            };
            function updatePayoutSummary(data){$('#pdp-total-pending').text('$0');$('#pdp-total-paid').text('$0');$('#pdp-active-riders').text('0');}
            $(document).on('click','.pdp-action',function(){
                var id=$(this).data('id'),action=$(this).data('action');
                var notes='';
                if(action==='failed'){notes=prompt('Motivo del fallo:');if(notes===null)return;}
                if(action==='paid'&&!confirm('¿Confirmar pago de este payout?'))return;
                PG.post('petsgo_process_payout',{payout_id:id,payout_action:action,notes:notes},function(r){
                    if(r.success)loadPayouts();else alert(r.data);
                });
            });
            window.generateWeeklyPayouts=function(){
                if(!confirm('¿Generar liquidación semanal (lunes a domingo pasado)? Esto creará pagos pendientes para todos los riders con entregas.'))return;
                PG.post('petsgo_generate_weekly_payouts',{},function(r){
                    alert(r.data||r.message||'Procesado');
                    if(r.success)loadPayouts();
                });
            };
            <?php endif; ?>
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
                        <div class="petsgo-field"><label>Características</label>
                            <textarea id="pp-features" rows="6" placeholder="Hasta 50 productos
Comisión 15%
Soporte por email
Dashboard con analíticas"></textarea>
                            <small style="color:#888;">Una característica por línea. Se mostrará así en la tarjeta del plan.</small>
                        </div>
                        <div class="petsgo-field" style="margin-top:8px;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="pp-featured" style="width:18px;height:18px;"> <span>⭐ Plan destacado</span></label><small style="color:#888;">Se mostrará resaltado con badge "MÁS POPULAR" en el frontend. Solo 1 plan puede ser destacado.</small></div>
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
            function parseFeatures(raw){
                if(!raw)return [];
                // If it's already an array, return it
                if(Array.isArray(raw))return raw;
                // Try JSON parse
                try{
                    var obj=JSON.parse(raw);
                    if(Array.isArray(obj))return obj;
                    // Legacy JSON object → convert to readable list
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
                }catch(e){}
                // Plain text: one feature per line
                return raw.split('\n').map(function(x){return x.trim();}).filter(Boolean);
            }
            // Convert features (JSON or lines) to plain text lines for the textarea
            function featuresToLines(raw){
                var feats=parseFeatures(raw);
                return feats.join('\n');
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
                    var color=planColors[i%3];var isPro=(parseInt(p.is_featured)===1);
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
                        rows+='<td><button class="petsgo-btn petsgo-btn-warning petsgo-btn-sm pp-edit" data-id="'+p.id+'" data-name="'+PG.esc(p.plan_name)+'" data-price="'+p.monthly_price+'" data-featured="'+(p.is_featured||0)+'">✏️</button> ';
                        rows+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm pp-del" data-id="'+p.id+'">🗑️</button></td></tr>';
                        // Store features JSON in a separate map to avoid jQuery data() auto-parsing
                        window._ppFeatures=window._ppFeatures||{};window._ppFeatures[p.id]=p.features_json||'';
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
            function resetForm(){$('#pp-id').val(0);$('#pp-name,#pp-price,#pp-features').val('');$('#pp-featured').prop('checked',false);$('#pp-form-title').text('Nuevo Plan');$('#pp-msg').hide();updateLiveCard();}
            $('#pp-add-btn').on('click',function(){resetForm();$('#pp-form-wrap').slideDown();});
            $('#pp-cancel').on('click',function(){$('#pp-form-wrap').slideUp();});
            $('#pp-name,#pp-price,#pp-features').on('input',function(){updateLiveCard();});
            $(document).on('click','.pp-edit',function(){
                var pid=$(this).data('id');
                $('#pp-id').val(pid);$('#pp-name').val($(this).data('name'));$('#pp-price').val($(this).data('price'));
                var ft=window._ppFeatures&&window._ppFeatures[pid]?window._ppFeatures[pid]:'';
                $('#pp-features').val(featuresToLines(ft));
                $('#pp-featured').prop('checked',$(this).data('featured')==1);
                $('#pp-form-title').text('Editar Plan #'+pid);$('#pp-form-wrap').slideDown();$('#pp-msg').hide();updateLiveCard();
            });
            $('#pp-save').on('click',function(){
                if(!$.trim($('#pp-name').val())||!$('#pp-price').val()){alert('Nombre y precio obligatorios');return;}
                PG.post('petsgo_save_plan',{id:$('#pp-id').val(),plan_name:$('#pp-name').val(),monthly_price:$('#pp-price').val(),features:$('#pp-features').val(),is_featured:$('#pp-featured').is(':checked')?1:0},function(r){
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
        $is_admin = $this->is_admin();
        $vid_raw = sanitize_text_field($_POST['vendor_id'] ?? '');
        $my_vid = $this->get_my_vendor_id();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $cat_raw = sanitize_text_field($_POST['category'] ?? '');
        $categories = array_filter(array_map('sanitize_text_field', explode(',', $cat_raw)));

        $sql = "SELECT i.*, v.store_name FROM {$wpdb->prefix}petsgo_inventory i LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE 1=1";
        $args = [];
        if (!$is_admin && $my_vid) { $sql .= " AND i.vendor_id=%d"; $args[] = $my_vid; }
        elseif ($is_admin && $vid_raw) {
            $vids = array_filter(array_map('intval', explode(',', $vid_raw)));
            if (count($vids) === 1) { $sql .= " AND i.vendor_id=%d"; $args[] = $vids[0]; }
            elseif ($vids) { $sql .= " AND i.vendor_id IN (" . implode(',', $vids) . ")"; }
        }
        if ($search) { $sql .= " AND i.product_name LIKE %s"; $args[] = '%'.$wpdb->esc_like($search).'%'; }
        if ($categories) {
            $phs = implode(',', array_fill(0, count($categories), '%s'));
            $sql .= " AND i.category IN ($phs)";
            $args = array_merge($args, $categories);
        }
        $sql .= " ORDER BY i.id DESC";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);

        $data = array_map(function($p) {
            $disc = floatval($p->discount_percent ?? 0);
            $active = false;
            if ($disc > 0) {
                if (empty($p->discount_start) && empty($p->discount_end)) { $active = true; }
                else {
                    $now = current_time('mysql');
                    $active = (!$p->discount_start || $now >= $p->discount_start) && (!$p->discount_end || $now <= $p->discount_end);
                }
            }
            return ['id'=>(int)$p->id,'product_name'=>$p->product_name,'description'=>$p->description,'price'=>(float)$p->price,'stock'=>(int)$p->stock,'category'=>$p->category,'store_name'=>$p->store_name,'image_url'=>$p->image_id?wp_get_attachment_image_url($p->image_id,'thumbnail'):null,
                'discount_percent'=>$disc,'discount_start'=>$p->discount_start,'discount_end'=>$p->discount_end,'discount_active'=>$active,'final_price'=>$active?round((float)$p->price*(1-$disc/100)):$p->price];
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

        // Descuento
        $disc_type = sanitize_text_field($_POST['discount_type'] ?? 'none');
        $disc_pct  = floatval($_POST['discount_percent'] ?? 0);
        if ($disc_type === 'none' || $disc_pct <= 0) {
            $data['discount_percent'] = 0; $data['discount_start'] = null; $data['discount_end'] = null;
        } elseif ($disc_type === 'indefinite') {
            $data['discount_percent'] = min($disc_pct, 99); $data['discount_start'] = null; $data['discount_end'] = null;
        } else { // scheduled
            $data['discount_percent'] = min($disc_pct, 99);
            $ds = sanitize_text_field($_POST['discount_start'] ?? '');
            $de = sanitize_text_field($_POST['discount_end'] ?? '');
            $data['discount_start'] = $ds ? date('Y-m-d H:i:s', strtotime($ds)) : null;
            $data['discount_end']   = $de ? date('Y-m-d H:i:s', strtotime($de)) : null;
        }

        if($id){
            $wpdb->update("{$wpdb->prefix}petsgo_inventory",$data,['id'=>$id]);
            $this->audit('product_update','product',$id,$name);
            $this->check_stock_alert($id);
            wp_send_json_success(['message'=>'Producto actualizado','id'=>$id]);
        } else {
            $wpdb->insert("{$wpdb->prefix}petsgo_inventory",$data);
            $nid=$wpdb->insert_id;
            $this->audit('product_create','product',$nid,$name);
            $this->check_stock_alert($nid);
            wp_send_json_success(['message'=>'Producto creado','id'=>$nid]);
        }
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
        $status_raw = sanitize_text_field($_POST['status'] ?? '');
        $statuses = array_filter(array_map('sanitize_text_field', explode(',', $status_raw)));
        $sql = "SELECT v.*, s.plan_name, (SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id=v.id) AS total_products, (SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=v.id) AS total_orders FROM {$wpdb->prefix}petsgo_vendors v LEFT JOIN {$wpdb->prefix}petsgo_subscriptions s ON v.plan_id=s.id WHERE 1=1";
        $args = [];
        if($search){$sql.=" AND v.store_name LIKE %s";$args[]='%'.$wpdb->esc_like($search).'%';}
        if($statuses){$phs=implode(',',array_fill(0,count($statuses),'%s'));$sql.=" AND v.status IN ($phs)";$args=array_merge($args,$statuses);}
        $sql.=" ORDER BY v.id DESC";
        if($args) $sql=$wpdb->prepare($sql,...$args);
        wp_send_json_success($wpdb->get_results($sql));
    }

    public function petsgo_save_vendor() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id=intval($_POST['id']??0);
        $data=['store_name'=>$this->san('store_name'),'rut'=>$this->san('rut'),'email'=>sanitize_email($_POST['email']??''),'phone'=>$this->san('phone'),'region'=>$this->san('region'),'comuna'=>$this->san('comuna'),'address'=>sanitize_textarea_field($_POST['address']??''),'user_id'=>intval($_POST['user_id']??0),'sales_commission'=>floatval($_POST['sales_commission']??10),'plan_id'=>intval($_POST['plan_id']??1),'subscription_start'=>sanitize_text_field($_POST['subscription_start']??'') ?: null,'subscription_end'=>sanitize_text_field($_POST['subscription_end']??'') ?: null,'status'=>$this->san('status')];
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
        $search=sanitize_text_field($_POST['search']??'');
        $status_raw=sanitize_text_field($_POST['status']??'');
        $vendor_raw=sanitize_text_field($_POST['vendor_id']??'');
        $statuses=array_filter(array_map('sanitize_text_field',explode(',',$status_raw)));
        $vids=array_filter(array_map('intval',explode(',',$vendor_raw)));

        $sql="SELECT o.*, v.store_name, u.display_name AS customer_name, r.display_name AS rider_name, inv.id AS invoice_id FROM {$wpdb->prefix}petsgo_orders o LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID LEFT JOIN {$wpdb->users} r ON o.rider_id=r.ID LEFT JOIN {$wpdb->prefix}petsgo_invoices inv ON inv.order_id=o.id WHERE 1=1";
        $args=[];
        if(!$is_admin&&$vid){$sql.=" AND o.vendor_id=%d";$args[]=$vid;}
        elseif($is_admin&&$vids){$sql.=" AND o.vendor_id IN (".implode(',',$vids).")";}
        if($this->is_rider()&&!$is_admin){$sql.=" AND o.rider_id=%d";$args[]=get_current_user_id();}
        if($search){$sql.=" AND u.display_name LIKE %s";$args[]='%'.$wpdb->esc_like($search).'%';}
        if($statuses){$phs=implode(',',array_fill(0,count($statuses),'%s'));$sql.=" AND o.status IN ($phs)";$args=array_merge($args,$statuses);}
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
            $profile = $wpdb->get_row($wpdb->prepare("SELECT phone, id_number FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $u->ID));
            $pet_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_pets WHERE user_id=%d", $u->ID));
            $data[]=['ID'=>$u->ID,'user_login'=>$u->user_login,'display_name'=>$u->display_name,'user_email'=>$u->user_email,'role_key'=>str_replace('petsgo_','',$rk),'role_label'=>$role_map[$rk]??$rk,'store_name'=>$store,'user_registered'=>$u->user_registered,'phone'=>$profile->phone??'','id_number'=>$profile->id_number??'','pet_count'=>$pet_count];
        }
        wp_send_json_success($data);
    }

    public function petsgo_save_user() {
        check_ajax_referer('petsgo_ajax');
        if(!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id=intval($_POST['id']??0);$login=$this->san('user_login');$name=$this->san('display_name');$email=sanitize_email($_POST['user_email']??'');$pass=$_POST['password']??'';$role=sanitize_text_field($_POST['role']??'subscriber');
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
        $id_type    = sanitize_text_field($_POST['id_type'] ?? 'rut');
        $id_number  = sanitize_text_field($_POST['id_number'] ?? '');
        $phone      = sanitize_text_field($_POST['phone'] ?? '');
        $birth_date = sanitize_text_field($_POST['birth_date'] ?? '');
        if (!$name && $first_name) $name = trim($first_name . ' ' . $last_name);

        // SQL injection check
        $sql_err = self::check_form_sql_injection($_POST);
        if ($sql_err) wp_send_json_error($sql_err);

        // Name sanitization & validation
        $first_name = self::sanitize_name($first_name);
        $last_name  = self::sanitize_name($last_name);

        $errors=[];
        if(!$login)$errors[]='Usuario obligatorio';if(!$name)$errors[]='Nombre obligatorio';if(!$email)$errors[]='Email obligatorio';
        if(!$id&&strlen($pass)<6)$errors[]='Contraseña mín 6 caracteres';
        if($first_name && !self::validate_name($first_name)) $errors[]='Nombre solo puede contener letras';
        if($last_name && !self::validate_name($last_name)) $errors[]='Apellido solo puede contener letras';
        if($id_type==='rut' && $id_number && !self::validate_rut($id_number)) $errors[]='RUT inválido';
        if($phone && !self::validate_chilean_phone($phone)) $errors[]='Teléfono chileno inválido';
        if($errors)wp_send_json_error(implode('. ',$errors));

        if($id){
            wp_update_user(['ID'=>$id,'display_name'=>$name,'user_email'=>$email,'first_name'=>$first_name,'last_name'=>$last_name]);
            if($pass) wp_set_password($pass,$id);
            $user=get_userdata($id);$user->set_role($role);
            // Update or insert profile
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d",$id));
            $pdata = ['first_name'=>$first_name,'last_name'=>$last_name,'id_type'=>$id_type,'id_number'=>$id_type==='rut'&&$id_number?self::format_rut($id_number):$id_number,'phone'=>$phone?self::normalize_phone($phone):$phone,'birth_date'=>$birth_date?:null];
            if($exists){$wpdb->update("{$wpdb->prefix}petsgo_user_profiles",$pdata,['user_id'=>$id]);}
            else{$pdata['user_id']=$id;$wpdb->insert("{$wpdb->prefix}petsgo_user_profiles",$pdata);}
            $this->audit('user_update','user',$id,$name);
            wp_send_json_success(['message'=>'Usuario actualizado','id'=>$id]);
        }else{
            $uid=wp_create_user($login,$pass,$email);
            if(is_wp_error($uid)) wp_send_json_error($uid->get_error_message());
            wp_update_user(['ID'=>$uid,'display_name'=>$name,'first_name'=>$first_name,'last_name'=>$last_name]);
            $user=get_userdata($uid);$user->set_role($role);
            $wpdb->insert("{$wpdb->prefix}petsgo_user_profiles",['user_id'=>$uid,'first_name'=>$first_name,'last_name'=>$last_name,'id_type'=>$id_type,'id_number'=>$id_type==='rut'&&$id_number?self::format_rut($id_number):$id_number,'phone'=>$phone?self::normalize_phone($phone):$phone,'birth_date'=>$birth_date?:null]);
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

    // --- PETS ADMIN AJAX ---
    public function petsgo_search_pets() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) wp_send_json_error('user_id requerido');
        $pets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_pets WHERE user_id=%d ORDER BY id ASC", $user_id
        ));
        $data = [];
        foreach ($pets as $pet) {
            $age = '';
            if ($pet->birth_date) {
                $diff = date_diff(date_create($pet->birth_date), date_create());
                $age = $diff->y > 0 ? $diff->y . ' año' . ($diff->y > 1 ? 's' : '') : $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
            }
            $data[] = [
                'id' => (int)$pet->id, 'pet_type' => $pet->pet_type, 'name' => $pet->name,
                'breed' => $pet->breed, 'birth_date' => $pet->birth_date, 'age' => $age,
                'photo_url' => $pet->photo_url, 'notes' => $pet->notes,
            ];
        }
        wp_send_json_success($data);
    }

    public function petsgo_save_pet() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id       = intval($_POST['id'] ?? 0);
        $user_id  = intval($_POST['user_id'] ?? 0);
        $name     = sanitize_text_field($_POST['name'] ?? '');
        $pet_type = sanitize_text_field($_POST['pet_type'] ?? 'perro');
        $breed    = sanitize_text_field($_POST['breed'] ?? '');
        $birth_date = sanitize_text_field($_POST['birth_date'] ?? '');
        $photo_url  = esc_url_raw($_POST['photo_url'] ?? '');
        $notes    = sanitize_textarea_field($_POST['notes'] ?? '');

        if (!$user_id) wp_send_json_error('user_id requerido');
        if (!$name) wp_send_json_error('Nombre de mascota obligatorio');

        $data = [
            'user_id'    => $user_id,
            'pet_type'   => $pet_type,
            'name'       => $name,
            'breed'      => $breed,
            'birth_date' => $birth_date ?: null,
            'photo_url'  => $photo_url,
            'notes'      => $notes,
        ];

        if ($id) {
            $wpdb->update("{$wpdb->prefix}petsgo_pets", $data, ['id' => $id]);
            $this->audit('pet_update_admin', 'pet', $id, $name);
            wp_send_json_success(['message' => 'Mascota actualizada', 'id' => $id]);
        } else {
            $wpdb->insert("{$wpdb->prefix}petsgo_pets", $data, ['%d','%s','%s','%s','%s','%s','%s']);
            $new_id = $wpdb->insert_id;
            $this->audit('pet_add_admin', 'pet', $new_id, $name . ' para user #' . $user_id);
            wp_send_json_success(['message' => 'Mascota agregada', 'id' => $new_id]);
        }
    }

    public function petsgo_delete_pet() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $pet = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_pets WHERE id=%d", $id));
        if (!$pet) wp_send_json_error('Mascota no encontrada');
        $wpdb->delete("{$wpdb->prefix}petsgo_pets", ['id' => $id]);
        $this->audit('pet_delete_admin', 'pet', $id, $pet->name);
        wp_send_json_success(['message' => 'Mascota eliminada']);
    }

    // --- DELIVERY ---
    public function petsgo_search_riders() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $is_admin=$this->is_admin();
        $status_raw=sanitize_text_field($_POST['status']??'');
        $rider_raw=sanitize_text_field($_POST['rider_id']??'');
        $statuses=array_filter(array_map('sanitize_text_field',explode(',',$status_raw)));
        $rider_ids=array_filter(explode(',',$rider_raw));

        $sql="SELECT o.*, v.store_name, u.display_name AS customer_name, r.display_name AS rider_name FROM {$wpdb->prefix}petsgo_orders o LEFT JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID LEFT JOIN {$wpdb->users} r ON o.rider_id=r.ID WHERE 1=1";
        $args=[];
        if(!$is_admin){$sql.=" AND o.rider_id=%d";$args[]=get_current_user_id();}
        elseif(count($rider_ids)===1&&$rider_ids[0]==='unassigned'){$sql.=" AND o.rider_id IS NULL";}
        elseif($rider_ids){
            $has_unassigned=in_array('unassigned',$rider_ids);$numeric_ids=array_filter(array_map('intval',$rider_ids));
            $parts=[];
            if($numeric_ids)$parts[]="o.rider_id IN (".implode(',',$numeric_ids).")";
            if($has_unassigned)$parts[]="o.rider_id IS NULL";
            if($parts)$sql.=" AND (".implode(' OR ',$parts).")";
        }
        if($statuses){$phs=implode(',',array_fill(0,count($statuses),'%s'));$sql.=" AND o.status IN ($phs)";$args=array_merge($args,$statuses);}
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

    // --- RIDER DOCUMENTS (admin search + review) ---
    public function petsgo_search_rider_docs() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Solo admin');
        global $wpdb;

        $type = sanitize_text_field($_POST['type'] ?? '');

        // Ratings mode
        if ($type === 'ratings') {
            $sql = "SELECT dr.*, u.display_name AS rider_name, ru.display_name AS rater_name
                    FROM {$wpdb->prefix}petsgo_delivery_ratings dr
                    JOIN {$wpdb->users} u ON dr.rider_id = u.ID
                    JOIN {$wpdb->users} ru ON dr.rater_id = ru.ID
                    WHERE 1=1";
            $args = [];
            $rider_id = intval($_POST['rider_id'] ?? 0);
            if ($rider_id) { $sql .= " AND dr.rider_id = %d"; $args[] = $rider_id; }
            $sql .= " ORDER BY dr.created_at DESC LIMIT 200";
            wp_send_json_success($args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql));
            return;
        }

        // Documents mode
        $sql = "SELECT rd.*, u.display_name AS rider_name, up.vehicle_type, up.id_type, up.id_number,
                       (SELECT um.meta_value FROM {$wpdb->usermeta} um WHERE um.user_id = rd.rider_id AND um.meta_key = 'petsgo_rider_status') AS rider_status
                FROM {$wpdb->prefix}petsgo_rider_documents rd
                JOIN {$wpdb->users} u ON rd.rider_id = u.ID
                LEFT JOIN {$wpdb->prefix}petsgo_user_profiles up ON rd.rider_id = up.user_id
                WHERE 1=1";
        $args = [];
        $status = sanitize_text_field($_POST['status'] ?? '');
        if ($status) { $sql .= " AND rd.status = %s"; $args[] = $status; }
        $sql .= " ORDER BY rd.uploaded_at DESC LIMIT 200";
        wp_send_json_success($args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql));
    }

    public function petsgo_review_rider_doc() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Solo admin');
        global $wpdb;
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $notes  = sanitize_textarea_field($_POST['notes'] ?? '');
        if (!$doc_id || !in_array($status, ['approved', 'rejected'])) wp_send_json_error('Datos inválidos');

        $wpdb->update("{$wpdb->prefix}petsgo_rider_documents", [
            'status'      => $status,
            'admin_notes' => $notes,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time('mysql'),
        ], ['id' => $doc_id]);

        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_rider_documents WHERE id=%d", $doc_id));
        $this->audit('review_rider_doc', 'rider_document', $doc_id, "Doc {$doc->doc_type} → {$status}" . ($notes ? " ({$notes})" : ''));

        // Check if all required docs are approved — auto-approve rider
        if ($status === 'approved' && $doc) {
            $rider_id = $doc->rider_id;
            $vehicle_type = get_user_meta($rider_id, 'petsgo_vehicle', true);
            $needs_license = in_array($vehicle_type, ['moto', 'auto', 'scooter']);

            $approved = $wpdb->get_col($wpdb->prepare(
                "SELECT doc_type FROM {$wpdb->prefix}petsgo_rider_documents WHERE rider_id=%d AND status='approved'",
                $rider_id
            ));

            $all_ok = in_array('id_card', $approved) && in_array('selfie', $approved);
            $is_a_pie = ($vehicle_type === 'a_pie');
            if (!$is_a_pie) {
                if (!in_array('vehicle_photo_1', $approved)) $all_ok = false;
                if (!in_array('vehicle_photo_2', $approved)) $all_ok = false;
                if (!in_array('vehicle_photo_3', $approved)) $all_ok = false;
            }
            if ($needs_license && !in_array('license', $approved)) $all_ok = false;
            if ($needs_license && !in_array('vehicle_registration', $approved)) $all_ok = false;

            if ($all_ok) {
                update_user_meta($rider_id, 'petsgo_rider_status', 'approved');
                $this->audit('rider_approved', 'user', $rider_id, 'Todos los documentos aprobados');

                // Send WELCOME email (bienvenida) when admin approves
                $rider_user = get_userdata($rider_id);
                $rider_name = get_user_meta($rider_id, 'first_name', true) ?: $rider_user->display_name;
                $vehicle_type = get_user_meta($rider_id, 'petsgo_vehicle', true) ?: '';
                $vtLabels = ['bicicleta'=>'Bicicleta','moto'=>'Moto','auto'=>'Auto','scooter'=>'Scooter','a_pie'=>'A pie'];
                $vtLabel = $vtLabels[$vehicle_type] ?? $vehicle_type;

                $welcome_inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>' . esc_html($rider_name) . '</strong>! 🚴</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Bienvenido al <strong>equipo de Delivery de PetsGo</strong>. ¡Tu cuenta ha sido <strong style="color:#16a34a;">aprobada</strong> y ya puedes comenzar a realizar entregas!</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0fdf4;border-left:4px solid #22C55E;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 6px;font-size:14px;color:#166534;font-weight:700;">✅ Cuenta Aprobada</p>
          <p style="margin:0;font-size:13px;color:#555;line-height:1.6;">Todos tus documentos fueron verificados exitosamente. Ya formas parte del equipo.</p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fff8ed;border-left:4px solid #FFC400;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#333;font-weight:700;">📦 Pr' . chr(243) . 'ximos pasos:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>1️⃣ Ingresa a tu Panel de Rider</td></tr>
            <tr><td>2️⃣ Revisa las entregas disponibles</td></tr>
            <tr><td>3️⃣ Acepta y realiza entregas a los clientes</td></tr>
            <tr><td>4️⃣ Entrega con ❤️ y gana valoraciones positivas</td></tr>
          </table>
        </td></tr>
      </table>' . ($vtLabel ? '
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f8f9fa;border-radius:8px;padding:14px 20px;font-size:13px;color:#555;">
          🚗 Veh' . chr(237) . 'culo registrado: <strong style="color:#333;">' . esc_html($vtLabel) . '</strong>
        </td></tr>
      </table>' : '') . '
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(home_url()) . '" style="display:inline-block;background:#FFC400;color:#2F3A40;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Ir a mi Panel de Rider 🚴</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">' . esc_html($rider_user->user_email) . '</span> porque tu cuenta Rider fue aprobada en PetsGo.
      </p>';
                $welcome_html = $this->email_wrap($welcome_inner, '¡Bienvenido al equipo de Delivery, ' . $rider_name . '!');
                $company = $this->pg_setting('company_name', 'PetsGo');
                $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
                $hdrs = ['Content-Type: text/html; charset=UTF-8', "From: {$company} <{$from_email}>"];
                @wp_mail($rider_user->user_email, "¡Bienvenido al equipo Rider de {$company}! 🚴", $welcome_html, $hdrs);
            }
        }

        // If rejected, send rejection notification email
        if ($status === 'rejected' && $doc) {
            $rider_id = $doc->rider_id;
            $rider_user = get_userdata($rider_id);
            $rider_name = get_user_meta($rider_id, 'first_name', true) ?: $rider_user->display_name;
            $docLabels = ['license'=>'Licencia de Conducir','vehicle_registration'=>'Padr' . chr(243) . 'n del Veh' . chr(237) . 'culo','id_card'=>'Documento de Identidad','selfie'=>'Foto de Perfil (Selfie)','vehicle_photo_1'=>'Foto Veh' . chr(237) . 'culo #1','vehicle_photo_2'=>'Foto Veh' . chr(237) . 'culo #2','vehicle_photo_3'=>'Foto Veh' . chr(237) . 'culo #3'];
            $docLabel = $docLabels[$doc->doc_type] ?? $doc->doc_type;

            // Set back to pending_docs so rider can re-upload
            $current_rs = get_user_meta($rider_id, 'petsgo_rider_status', true);
            if ($current_rs === 'pending_review') {
                update_user_meta($rider_id, 'petsgo_rider_status', 'pending_docs');
            }

            $reject_inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($rider_name) . '</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Te informamos que uno de tus documentos ha sido <strong style="color:#dc2626;">rechazado</strong> durante la revisi' . chr(243) . 'n.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fef2f2;border-left:4px solid #ef4444;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 8px;font-size:14px;color:#991b1b;font-weight:700;">❌ Documento rechazado</p>
          <p style="margin:0 0 4px;font-size:13px;color:#555;">Tipo: <strong>' . esc_html($docLabel) . '</strong></p>' .
          ($notes ? '<p style="margin:0;font-size:13px;color:#555;">Motivo: <strong>' . esc_html($notes) . '</strong></p>' : '') . '
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fff8ed;border-left:4px solid #FFC400;border-radius:8px;padding:16px 24px;">
          <p style="margin:0;font-size:13px;color:#555;line-height:1.6;">📋 Ingresa a tu Panel de Rider y sube nuevamente el documento corregido para continuar con tu solicitud.</p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(home_url()) . '" style="display:inline-block;background:#F97316;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Subir Documento Corregido</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">' . esc_html($rider_user->user_email) . '</span> por la revisi' . chr(243) . 'n de tu cuenta Rider en PetsGo.
      </p>';
            $reject_html = $this->email_wrap($reject_inner, 'Documento rechazado - Acci' . chr(243) . 'n requerida');
            $company = $this->pg_setting('company_name', 'PetsGo');
            $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
            $hdrs = ['Content-Type: text/html; charset=UTF-8', "From: {$company} <{$from_email}>"];
            @wp_mail($rider_user->user_email, "Documento rechazado - {$company} ⚠️", $reject_html, $hdrs);
        }

        wp_send_json_success(['message' => 'Documento ' . ($status === 'approved' ? 'aprobado' : 'rechazado')]);
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
        $is_featured=intval($_POST['is_featured']??0);
        // Convert plain-text lines to JSON array for storage
        $raw_features = sanitize_textarea_field($_POST['features'] ?? '');
        $json_test = json_decode($raw_features, true);
        if (is_array($json_test)) {
            // Already valid JSON array or legacy JSON object → convert object to readable list
            if (array_values($json_test) !== $json_test) {
                // Associative (legacy object) → convert to feature lines
                $lines = [];
                if (isset($json_test['max_products'])) $lines[] = $json_test['max_products'] == -1 ? 'Productos ilimitados' : 'Hasta '.$json_test['max_products'].' productos';
                if (isset($json_test['commission_rate'])) $lines[] = 'Comisión '.$json_test['commission_rate'].'%';
                if (isset($json_test['support'])) { $s=$json_test['support']; $lines[]=$s==='email'?'Soporte por email':($s==='email+chat'?'Soporte email + chat':($s==='prioritario'?'Soporte prioritario 24/7':'Soporte: '.$s)); }
                if (!empty($json_test['analytics'])) $lines[] = 'Dashboard con analíticas';
                if (!empty($json_test['featured'])) $lines[] = 'Productos destacados';
                if (!empty($json_test['api_access'])) $lines[] = 'Acceso API personalizado';
                $features_json = wp_json_encode($lines, JSON_UNESCAPED_UNICODE);
            } else {
                $features_json = wp_json_encode($json_test, JSON_UNESCAPED_UNICODE);
            }
        } else {
            // Plain text lines → convert to JSON array
            $lines = array_values(array_filter(array_map('trim', explode("\n", $raw_features))));
            $features_json = wp_json_encode($lines, JSON_UNESCAPED_UNICODE);
        }
        $data=['plan_name'=>$this->san('plan_name'),'monthly_price'=>floatval($_POST['monthly_price']??0),'features_json'=>$features_json,'is_featured'=>$is_featured];
        if(!$data['plan_name'])wp_send_json_error('Nombre obligatorio');
        // Only one plan can be featured
        if($is_featured) $wpdb->update("{$wpdb->prefix}petsgo_subscriptions",['is_featured'=>0],['is_featured'=>1]);
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
                <select id="bi-filter-vendor" multiple><option value="">Todas las tiendas</option>
                    <?php foreach($vendors as $v): ?><option value="<?php echo $v->id; ?>"><?php echo esc_html($v->store_name); ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="bi-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="bi-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead id="bi-thead"><tr><th>#</th><th>Nº Boleta</th><th>Pedido</th><th>Tienda</th><th>Cliente</th><th>Total</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody id="bi-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            <?php if($is_admin): ?>$('#bi-filter-vendor').pgChecklist({placeholder:'Todas las tiendas'});<?php endif; ?>
            var tbl=PG.table({
                thead:'#bi-thead',body:'#bi-body',perPage:25,defaultSort:'id',defaultDir:'desc',
                columns:['id','invoice_number','order_id','store_name','customer_name','total_amount','created_at','_actions'],
                emptyMsg:'Sin boletas.',
                onTotal:function(n){$('#bi-total').text(n);},
                renderRow:function(b){
                    var r='<tr><td>'+b.id+'</td><td><strong>'+PG.esc(b.invoice_number)+'</strong></td>';
                    r+='<td>#'+b.order_id+'</td><td>'+PG.esc(b.store_name||'')+'</td>';
                    r+='<td>'+PG.esc(b.customer_name||'')+'</td><td>'+PG.money(b.total_amount)+'</td>';
                    r+='<td>'+PG.fdate(b.created_at)+'</td>';
                    r+='<td>';
                    r+='<a href="'+PG.adminUrl+'?page=petsgo-invoice-config&preview='+b.id+'" class="petsgo-btn petsgo-btn-sm petsgo-btn-primary" target="_blank">👁️ Ver</a> ';
                    r+='<a href="'+PG.ajaxUrl+'?action=petsgo_download_invoice&_wpnonce='+PG.nonce+'&id='+b.id+'" class="petsgo-btn petsgo-btn-sm petsgo-btn-success" target="_blank">📥 PDF</a>';
                    r+='</td></tr>';
                    return r;
                }
            });
            function load(){
                $('#bi-loader').addClass('active');
                var d={search:$('#bi-search').val()};
                <?php if($is_admin): ?>var vv=$('#bi-filter-vendor').val()||[];d.vendor_id=Array.isArray(vv)?vv.join(','):(vv||'');<?php endif; ?>
                PG.post('petsgo_search_invoices',d,function(r){
                    $('#bi-loader').removeClass('active');if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            load();var t;$('#bi-search,#bi-filter-vendor').on('input change',function(){clearTimeout(t);t=setTimeout(load,400);});
            $('#bi-btn-search').on('click',load);
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
                        <td><strong>Fecha:</strong> <?php echo date('d-m-Y H:i', strtotime($inv->created_at)); ?></td>
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
                <select id="au-entity" multiple><option value="">Todas las entidades</option>
                    <option value="product">Producto</option><option value="vendor">Tienda</option><option value="order">Pedido</option>
                    <option value="user">Usuario</option><option value="plan">Plan</option><option value="invoice">Boleta</option>
                    <option value="login">Login</option>
                </select>
                <input type="date" id="au-date-from" title="Desde">
                <input type="date" id="au-date-to" title="Hasta">
                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" id="au-btn-search">🔍 Buscar</button>
                <span class="petsgo-loader" id="au-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>
            <table class="petsgo-table"><thead id="au-thead"><tr><th>#</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>ID Ent.</th><th>Detalles</th><th>IP</th><th>Fecha</th></tr></thead>
            <tbody id="au-body"><tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Cargando...</td></tr></tbody></table>
        </div>
        <script>
        jQuery(function($){
            $('#au-entity').pgChecklist({placeholder:'Todas las entidades'});
            var tbl=PG.table({
                thead:'#au-thead',body:'#au-body',perPage:25,defaultSort:'id',defaultDir:'desc',
                columns:['id','user_name','action','entity_type','entity_id','details','ip_address','created_at'],
                emptyMsg:'Sin registros.',
                onTotal:function(n){$('#au-total').text(n);},
                renderRow:function(a){
                    var r='<tr><td>'+a.id+'</td><td>'+PG.esc(a.user_name)+'</td><td><code>'+PG.esc(a.action)+'</code></td>';
                    r+='<td>'+PG.esc(a.entity_type||'')+'</td><td>'+(a.entity_id||'-')+'</td>';
                    r+='<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+PG.esc(a.details||'')+'">'+PG.esc(a.details||'')+'</td>';
                    r+='<td><small>'+PG.esc(a.ip_address)+'</small></td><td><small>'+PG.fdate(a.created_at)+'</small></td></tr>';
                    return r;
                }
            });
            function load(){
                $('#au-loader').addClass('active');
                var ev=$('#au-entity').val()||[];
                PG.post('petsgo_search_audit_log',{
                    search:$('#au-search').val(),entity:Array.isArray(ev)?ev.join(','):(ev||''),
                    date_from:$('#au-date-from').val(),date_to:$('#au-date-to').val()
                },function(r){
                    $('#au-loader').removeClass('active');if(!r.success){tbl.setData([]);return;}
                    tbl.setData(r.data);
                });
            }
            load();var t;$('#au-search,#au-entity,#au-date-from,#au-date-to').on('input change',function(){clearTimeout(t);t=setTimeout(load,400);});
            $('#au-btn-search').on('click',load);
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
        $vendor_raw = sanitize_text_field($_POST['vendor_id'] ?? '');

        $sql = "SELECT inv.*, o.total_amount, v.store_name, u.display_name AS customer_name
                FROM {$wpdb->prefix}petsgo_invoices inv
                JOIN {$wpdb->prefix}petsgo_orders o ON inv.order_id = o.id
                JOIN {$wpdb->prefix}petsgo_vendors v ON inv.vendor_id = v.id
                LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID WHERE 1=1";
        $args = [];
        if (!$is_admin && $vid) { $sql .= " AND inv.vendor_id=%d"; $args[] = $vid; }
        elseif ($is_admin && $vendor_raw) {
            $vids = array_filter(array_map('intval', explode(',', $vendor_raw)));
            if ($vids) { $phs = implode(',', array_fill(0, count($vids), '%d')); $sql .= " AND inv.vendor_id IN ($phs)"; $args = array_merge($args, $vids); }
        }
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

        // Obtener items reales del pedido
        $order_items = $this->get_order_items($order_id);
        $grand = $order->total_amount + ($order->delivery_fee ?? 0);
        $neto = round($grand / 1.19);
        $iva = $grand - $neto;

        // HTML items table para preview
        $items_html = $this->build_items_html($order_items, $order, $order_id, $neto, $iva, $grand);

        // PDF items array
        $items = $this->build_items_array($order_items, $order);

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
        $this->send_invoice_email($order, $inv_number, $pdf_path, $order_items);

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

        // If PDF file doesn't exist, regenerate it on-the-fly
        if (!file_exists($pdf_path)) {
            $order = $wpdb->get_row($wpdb->prepare("SELECT o.*, v.store_name, v.rut AS vendor_rut, v.address AS vendor_address, v.phone AS vendor_phone, v.email AS vendor_email, v.contact_phone, v.social_facebook, v.social_instagram, v.social_whatsapp, v.social_website, v.invoice_logo_id FROM {$wpdb->prefix}petsgo_orders o JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id WHERE o.id=%d", $inv->order_id));
            $customer = get_userdata($wpdb->get_var($wpdb->prepare("SELECT customer_id FROM {$wpdb->prefix}petsgo_orders WHERE id=%d", $inv->order_id)));
            if ($order) {
                $order_items = $this->get_order_items($inv->order_id);
                $items = $this->build_items_array($order_items, $order);
                $grand = $order->total_amount + ($order->delivery_fee ?? 0);
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
                    'invoice_number' => $inv->invoice_number,
                    'date' => date('d/m/Y H:i', strtotime($inv->created_at)),
                    'customer_name' => $customer ? $customer->display_name : 'N/A',
                    'customer_email' => $customer ? $customer->user_email : '',
                ];
                $qr_url = site_url('/wp-json/petsgo/v1/invoice/validate/' . $inv->qr_token);
                $pdf_gen->generate($vendor_data, $invoice_data, $items, $grand, $qr_url, $inv->qr_token, $pdf_path);
            }
        }

        if (!file_exists($pdf_path)) wp_die('No se pudo generar el archivo PDF');

        $this->audit('invoice_download', 'invoice', $id, $inv->invoice_number);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $inv->invoice_number . '.pdf"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        exit;
    }

    /**
     * Generate and download a demo invoice PDF with sample data.
     * Used from the email preview page for design verification.
     */
    public function petsgo_download_demo_invoice() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'petsgo_ajax')) {
            wp_die('Nonce inválido');
        }
        if (!$this->is_admin()) wp_die('Sin permisos');

        require_once __DIR__ . '/petsgo-lib/invoice-pdf.php';

        // Demo vendor logo
        $upload_dir = wp_upload_dir();
        $demo_logo = $upload_dir['basedir'] . '/demo-vendor-logo.png';
        $demo_logo_url = file_exists($demo_logo) ? $upload_dir['baseurl'] . '/demo-vendor-logo.png' : '';

        $vendor_data = [
            'store_name'       => 'Mundo Animal (Demo)',
            'rut'              => '76.123.456-7',
            'address'          => 'Av. Providencia 1234, Santiago',
            'phone'            => '+56 9 1234 5678',
            'email'            => 'ventas@mundoanimal.cl',
            'contact_phone'    => '+56 9 8765 4321',
            'social_facebook'  => 'facebook.com/mundoanimal',
            'social_instagram' => '@mundoanimal',
            'social_whatsapp'  => '+56912345678',
            'social_website'   => 'www.mundoanimal.cl',
            'logo_url'         => $demo_logo_url,
            'delivery_fee'     => 2990,
        ];

        $invoice_data = [
            'invoice_number' => 'BOL-MA-' . date('Ymd') . '-DEMO',
            'date'           => date('d/m/Y H:i'),
            'customer_name'  => 'María González (Demo)',
            'customer_email' => 'maria@demo.cl',
            'customer_id'    => 0,
            'order_id'       => 'DEMO',
        ];

        $items = [
            ['name' => 'Royal Canin Medium Adult 15kg',   'qty' => 1, 'price' => 42990],
            ['name' => 'Collar Antipulgas Premium Perro',  'qty' => 2, 'price' => 8990],
            ['name' => 'Juguete Kong Classic Large',       'qty' => 1, 'price' => 15990],
            ['name' => 'Snack Dentastix Pack x7',          'qty' => 3, 'price' => 4590],
        ];

        $grand = 0;
        foreach ($items as $it) { $grand += $it['qty'] * $it['price']; }
        $grand += $vendor_data['delivery_fee'];

        $qr_token = 'demo-' . wp_generate_password(16, false);
        $qr_url   = site_url('/wp-json/petsgo/v1/invoice/validate/' . $qr_token);

        // Generate to temp file (use PHP tempnam for compatibility)
        $tmp = tempnam(sys_get_temp_dir(), 'petsgo_demo_invoice_');
        if ($tmp && substr($tmp, -4) !== '.pdf') $tmp .= '.pdf';
        $pdf_gen = new PetsGo_Invoice_PDF();
        $pdf_gen->generate($vendor_data, $invoice_data, $items, $grand, $qr_url, $qr_token, $tmp);

        if (!file_exists($tmp) || filesize($tmp) < 1000) wp_die('No se pudo generar el PDF demo');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice_data['invoice_number'] . '.pdf"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /**
     * Generate and download a demo subscription/plan PDF with sample data.
     */
    public function petsgo_download_demo_subscription() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'petsgo_ajax')) {
            wp_die('Nonce inválido');
        }
        if (!$this->is_admin()) wp_die('Sin permisos');

        require_once __DIR__ . '/petsgo-lib/subscription-pdf.php';

        // Read parametrizable free months from settings
        $free_months = intval($this->pg_setting('plan_annual_free_months', 2));

        $vendor_data = [
            'store_name'   => 'Patitas Chile (Demo)',
            'rut'          => '76.987.654-3',
            'email'        => 'contacto@patitaschile.cl',
            'contact_name' => 'Andrea Muñoz',
            'phone'        => '+56 9 5678 9012',
            'address'      => 'Los Leones 456, Providencia, Santiago',
        ];

        $plan_data = [
            'plan_name'      => 'Pro',
            'monthly_price'  => 59990,
            'billing_period' => 'Anual',
            'billing_months' => 12,
            'free_months'    => $free_months,
            'start_date'     => date('d/m/Y'),
            'end_date'       => date('d/m/Y', strtotime('+1 year')),
            'features'       => [
                'Hasta 200 productos publicados',
                'Panel de analíticas avanzado',
                'Soporte prioritario 24/7',
                'Integración con redes sociales',
                'Reportes mensuales de ventas',
                'Badge de Tienda Verificada',
            ],
        ];

        $invoice_number = 'SUB-PC-' . date('Ymd') . '-DEMO';
        $date = date('d/m/Y H:i');
        $qr_token = 'sub-demo-' . wp_generate_password(16, false);

        $tmp = tempnam(sys_get_temp_dir(), 'petsgo_demo_sub_');
        if ($tmp && substr($tmp, -4) !== '.pdf') $tmp .= '.pdf';

        $pdf_gen = new PetsGo_Subscription_PDF();
        $pdf_gen->generate($vendor_data, $plan_data, $invoice_number, $date, $qr_token, $tmp);

        if (!file_exists($tmp) || filesize($tmp) < 500) wp_die('No se pudo generar el PDF de suscripción demo');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice_number . '.pdf"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // ============================================================
    // AJAX HANDLERS — Audit Log
    // ============================================================
    // ============================================================
    // AJAX — Dashboard Data (filtros dinámicos)
    // ============================================================
    public function petsgo_dashboard_data() {
        check_ajax_referer('petsgo_ajax');
        global $wpdb;
        $pfx = $wpdb->prefix;
        $is_admin  = $this->is_admin();
        $is_vendor = $this->is_vendor();
        $vid       = $this->get_my_vendor_id();
        if (!$is_admin && !$is_vendor) wp_send_json_error('Sin permisos');

        // Filters
        $vendor_ids = array_filter(array_map('intval', explode(',', sanitize_text_field($_POST['vendor_ids'] ?? ''))));
        $categories = array_filter(array_map('sanitize_text_field', explode(',', $_POST['category'] ?? '')));
        $statuses   = array_filter(array_map('sanitize_text_field', explode(',', $_POST['status'] ?? '')));
        $date_from  = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to    = sanitize_text_field($_POST['date_to'] ?? '');
        $search     = sanitize_text_field($_POST['search'] ?? '');

        // Scope for orders
        $oWhere = " WHERE 1=1";
        $oArgs  = [];
        if (!$is_admin && $vid) { $oWhere .= " AND o.vendor_id=%d"; $oArgs[] = $vid; }
        elseif ($is_admin && $vendor_ids) { $oWhere .= " AND o.vendor_id IN (" . implode(',', $vendor_ids) . ")"; }
        if ($statuses) { $phs = implode(',', array_fill(0, count($statuses), '%s')); $oWhere .= " AND o.status IN ($phs)"; $oArgs = array_merge($oArgs, $statuses); }
        if ($date_from) { $oWhere .= " AND o.created_at >= %s"; $oArgs[] = $date_from . ' 00:00:00'; }
        if ($date_to) { $oWhere .= " AND o.created_at <= %s"; $oArgs[] = $date_to . ' 23:59:59'; }
        if ($search) { $oWhere .= " AND (o.id LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->users} u WHERE u.ID=o.customer_id AND u.display_name LIKE %s))"; $oArgs[] = '%'.$wpdb->esc_like($search).'%'; $oArgs[] = '%'.$wpdb->esc_like($search).'%'; }

        $oQ = function($sql) use ($wpdb, $oArgs) { return $oArgs ? $wpdb->prepare($sql, ...$oArgs) : $sql; };

        // Scope for products
        $pWhere = " WHERE 1=1";
        $pArgs  = [];
        if (!$is_admin && $vid) { $pWhere .= " AND i.vendor_id=%d"; $pArgs[] = $vid; }
        elseif ($is_admin && $vendor_ids) { $pWhere .= " AND i.vendor_id IN (" . implode(',', $vendor_ids) . ")"; }
        if ($categories) { $phs = implode(',', array_fill(0, count($categories), '%s')); $pWhere .= " AND i.category IN ($phs)"; $pArgs = array_merge($pArgs, $categories); }
        if ($search) { $pWhere .= " AND i.product_name LIKE %s"; $pArgs[] = '%'.$wpdb->esc_like($search).'%'; }

        $pQ = function($sql) use ($wpdb, $pArgs) { return $pArgs ? $wpdb->prepare($sql, ...$pArgs) : $sql; };

        // ── KPIs ──
        $total_orders     = (int)$wpdb->get_var($oQ("SELECT COUNT(*) FROM {$pfx}petsgo_orders o $oWhere"));
        $delivered_orders = (int)$wpdb->get_var($oQ("SELECT COUNT(*) FROM {$pfx}petsgo_orders o $oWhere AND o.status='delivered'"));
        $pending_orders   = (int)$wpdb->get_var($oQ("SELECT COUNT(*) FROM {$pfx}petsgo_orders o $oWhere AND o.status IN ('pending','payment_pending')"));
        $in_transit       = (int)$wpdb->get_var($oQ("SELECT COUNT(*) FROM {$pfx}petsgo_orders o $oWhere AND o.status='in_transit'"));
        $cancelled_orders = (int)$wpdb->get_var($oQ("SELECT COUNT(*) FROM {$pfx}petsgo_orders o $oWhere AND o.status='cancelled'"));
        $total_revenue    = (float)$wpdb->get_var($oQ("SELECT COALESCE(SUM(o.total_amount),0) FROM {$pfx}petsgo_orders o $oWhere AND o.status='delivered'"));
        $total_commission = (float)$wpdb->get_var($oQ("SELECT COALESCE(SUM(o.petsgo_commission),0) FROM {$pfx}petsgo_orders o $oWhere AND o.status='delivered'"));
        $total_delivery_fees = (float)$wpdb->get_var($oQ("SELECT COALESCE(SUM(o.delivery_fee),0) FROM {$pfx}petsgo_orders o $oWhere AND o.status='delivered'"));
        $avg_ticket       = $delivered_orders > 0 ? round($total_revenue / $delivered_orders) : 0;
        $success_rate     = $total_orders > 0 ? round(($delivered_orders / $total_orders) * 100, 1) : 0;

        // Revenue trend (30d vs previous 30d) — ignores date filters on purpose
        $scopeBase = " WHERE 1=1";
        $scopeArgs = [];
        if (!$is_admin && $vid) { $scopeBase .= " AND o.vendor_id=%d"; $scopeArgs[] = $vid; }
        elseif ($is_admin && $vendor_ids) { $scopeBase .= " AND o.vendor_id IN (" . implode(',', $vendor_ids) . ")"; }
        $sQ = function($sql) use ($wpdb, $scopeArgs) { return $scopeArgs ? $wpdb->prepare($sql, ...$scopeArgs) : $sql; };
        $rev_30 = (float)$wpdb->get_var($sQ("SELECT COALESCE(SUM(o.total_amount),0) FROM {$pfx}petsgo_orders o $scopeBase AND o.status='delivered' AND o.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)"));
        $rev_60 = (float)$wpdb->get_var($sQ("SELECT COALESCE(SUM(o.total_amount),0) FROM {$pfx}petsgo_orders o $scopeBase AND o.status='delivered' AND o.created_at>=DATE_SUB(NOW(),INTERVAL 60 DAY) AND o.created_at<DATE_SUB(NOW(),INTERVAL 30 DAY)"));
        $rev_trend = $rev_60 > 0 ? round((($rev_30 - $rev_60) / $rev_60) * 100, 1) : ($rev_30 > 0 ? 100 : 0);

        // Product stats
        $total_products = (int)$wpdb->get_var($pQ("SELECT COUNT(*) FROM {$pfx}petsgo_inventory i $pWhere"));
        $low_stock      = (int)$wpdb->get_var($pQ("SELECT COUNT(*) FROM {$pfx}petsgo_inventory i $pWhere AND i.stock<5 AND i.stock>0"));
        $out_of_stock   = (int)$wpdb->get_var($pQ("SELECT COUNT(*) FROM {$pfx}petsgo_inventory i $pWhere AND i.stock=0"));

        // Admin-only KPIs
        $total_vendors = $active_vendors = $total_users = $total_invoices = 0;
        if ($is_admin) {
            $total_vendors  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}petsgo_vendors");
            $active_vendors = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}petsgo_vendors WHERE status='active'");
            $total_users    = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
            $total_invoices = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$pfx}petsgo_invoices");
        }

        // ── Chart data ──
        // Status breakdown
        $status_rows = $wpdb->get_results($oQ("SELECT o.status, COUNT(*) as cnt FROM {$pfx}petsgo_orders o $oWhere GROUP BY o.status"));
        $status_labels = array_column($status_rows, 'status');
        $status_values = array_map('intval', array_column($status_rows, 'cnt'));

        // Monthly revenue & orders (6 months)
        $monthly = $wpdb->get_results($sQ("SELECT DATE_FORMAT(o.created_at,'%Y-%m') as month, SUM(CASE WHEN o.status='delivered' THEN o.total_amount ELSE 0 END) as rev, COUNT(*) as cnt FROM {$pfx}petsgo_orders o $scopeBase AND o.created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY month ORDER BY month"));
        $monthly_labels  = array_column($monthly, 'month');
        $monthly_revenue = array_map('floatval', array_column($monthly, 'rev'));
        $monthly_orders  = array_map('intval', array_column($monthly, 'cnt'));

        // Categories
        $cats = $wpdb->get_results($pQ("SELECT i.category, COUNT(*) as cnt FROM {$pfx}petsgo_inventory i $pWhere GROUP BY i.category ORDER BY cnt DESC"));
        $cat_labels = array_column($cats, 'category');
        $cat_values = array_map('intval', array_column($cats, 'cnt'));

        // ── Tables ──
        // Top riders (scoped to vendor filters)
        $top_riders = $wpdb->get_results($oQ("SELECT u.display_name as name, COUNT(o.id) as total, SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) as delivered, SUM(CASE WHEN o.status='delivered' THEN o.delivery_fee ELSE 0 END) as fees FROM {$pfx}petsgo_orders o JOIN {$wpdb->users} u ON o.rider_id=u.ID $oWhere AND o.rider_id IS NOT NULL GROUP BY o.rider_id ORDER BY delivered DESC LIMIT 10"));

        // Top vendors (admin only)
        $top_vendors = [];
        if ($is_admin) {
            $tvWhere = " WHERE 1=1";
            $tvArgs  = [];
            if ($vendor_ids) { $tvWhere .= " AND v.id IN (" . implode(',', $vendor_ids) . ")"; }
            $tvQ = function($sql) use ($wpdb, $tvArgs) { return $tvArgs ? $wpdb->prepare($sql, ...$tvArgs) : $sql; };
            $top_vendors = $wpdb->get_results($tvQ("SELECT v.store_name, v.status, COUNT(o.id) as orders, SUM(CASE WHEN o.status='delivered' THEN o.total_amount ELSE 0 END) as revenue, SUM(CASE WHEN o.status='delivered' THEN o.petsgo_commission ELSE 0 END) as commission FROM {$pfx}petsgo_vendors v LEFT JOIN {$pfx}petsgo_orders o ON v.id=o.vendor_id $tvWhere GROUP BY v.id ORDER BY revenue DESC LIMIT 10"));
        }

        // Products list
        $products = $wpdb->get_results($pQ("SELECT i.product_name, i.category, i.price, i.stock, v.store_name FROM {$pfx}petsgo_inventory i LEFT JOIN {$pfx}petsgo_vendors v ON i.vendor_id=v.id $pWhere ORDER BY i.stock ASC LIMIT 30"));

        // Recent orders
        $recent = $wpdb->get_results($oQ("SELECT o.*, v.store_name, u.display_name as customer_name FROM {$pfx}petsgo_orders o LEFT JOIN {$pfx}petsgo_vendors v ON o.vendor_id=v.id LEFT JOIN {$wpdb->users} u ON o.customer_id=u.ID $oWhere ORDER BY o.created_at DESC LIMIT 30"));

        wp_send_json_success([
            'updated_at'         => current_time('mysql'),
            'total_orders'       => $total_orders,
            'delivered_orders'   => $delivered_orders,
            'pending_orders'     => $pending_orders,
            'in_transit'         => $in_transit,
            'cancelled_orders'   => $cancelled_orders,
            'total_revenue'      => $total_revenue,
            'total_commission'   => $total_commission,
            'total_delivery_fees'=> $total_delivery_fees,
            'avg_ticket'         => $avg_ticket,
            'success_rate'       => $success_rate,
            'rev_trend'          => $rev_trend,
            'total_products'     => $total_products,
            'low_stock'          => $low_stock,
            'out_of_stock'       => $out_of_stock,
            'total_vendors'      => $total_vendors,
            'active_vendors'     => $active_vendors,
            'total_users'        => $total_users,
            'total_invoices'     => $total_invoices,
            'status_labels'      => $status_labels,
            'status_values'      => $status_values,
            'monthly_labels'     => $monthly_labels,
            'monthly_revenue'    => $monthly_revenue,
            'monthly_orders'     => $monthly_orders,
            'cat_labels'         => $cat_labels,
            'cat_values'         => $cat_values,
            'top_riders'         => $top_riders,
            'top_vendors'        => $top_vendors,
            'products'           => $products,
            'recent_orders'      => $recent,
        ]);
    }

    public function petsgo_search_audit_log() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Solo admin');
        global $wpdb;

        $search = sanitize_text_field($_POST['search'] ?? '');
        $entity_raw = sanitize_text_field($_POST['entity'] ?? '');
        $entities = array_filter(array_map('sanitize_text_field', explode(',', $entity_raw)));
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        $sql = "SELECT * FROM {$wpdb->prefix}petsgo_audit_log WHERE 1=1";
        $args = [];
        if ($search) { $sql .= " AND (user_name LIKE %s OR action LIKE %s OR details LIKE %s)"; $args[] = '%'.$wpdb->esc_like($search).'%'; $args[] = '%'.$wpdb->esc_like($search).'%'; $args[] = '%'.$wpdb->esc_like($search).'%'; }
        if ($entities) { $phs = implode(',', array_fill(0, count($entities), '%s')); $sql .= " AND entity_type IN ($phs)"; $args = array_merge($args, $entities); }
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
    // HELPER: Obtener items de un pedido
    // ============================================================
    private function get_order_items($order_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT product_name, quantity, unit_price, subtotal FROM {$wpdb->prefix}petsgo_order_items WHERE order_id=%d ORDER BY id ASC",
            $order_id
        ));
    }

    /**
     * Construye HTML de tabla de items para preview de boleta
     */
    private function build_items_html($order_items, $order, $order_id, $neto, $iva, $grand) {
        $html = '<table style="width:100%;border-collapse:collapse;margin:12px 0;">';
        $html .= '<tr style="background:#00A8E8;color:#fff;"><th style="padding:8px;text-align:left;">Producto</th><th style="padding:8px;text-align:center;">Cant.</th><th style="padding:8px;text-align:right;">P. Unit</th><th style="padding:8px;text-align:right;">Subtotal</th></tr>';

        if (!empty($order_items)) {
            foreach ($order_items as $item) {
                $html .= '<tr style="border-bottom:1px solid #eee;">';
                $html .= '<td style="padding:8px;">' . esc_html($item->product_name) . '</td>';
                $html .= '<td style="padding:8px;text-align:center;">' . intval($item->quantity) . '</td>';
                $html .= '<td style="padding:8px;text-align:right;">$' . number_format($item->unit_price, 0, ',', '.') . '</td>';
                $html .= '<td style="padding:8px;text-align:right;">$' . number_format($item->subtotal, 0, ',', '.') . '</td>';
                $html .= '</tr>';
            }
        } else {
            // Fallback para pedidos antiguos sin items desglosados
            $html .= '<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;">Pedido #'.$order_id.'</td><td style="padding:8px;text-align:center;">1</td><td style="padding:8px;text-align:right;">$'.number_format($order->total_amount,0,',','.').'</td><td style="padding:8px;text-align:right;">$'.number_format($order->total_amount,0,',','.').'</td></tr>';
        }

        if ($order->delivery_fee > 0) {
            $html .= '<tr style="border-bottom:1px solid #eee;background:#f8f9fa;"><td style="padding:8px;" colspan="3">Delivery</td><td style="padding:8px;text-align:right;">$'.number_format($order->delivery_fee,0,',','.').'</td></tr>';
        }
        $html .= '</table>';
        $html .= '<p style="text-align:right;font-size:13px;"><strong>Neto:</strong> $'.number_format($neto,0,',','.').
            ' | <strong>IVA (19%):</strong> $'.number_format($iva,0,',','.').
            ' | <strong style="font-size:15px;color:#00A8E8;">Total: $'.number_format($grand,0,',','.').'</strong></p>';
        return $html;
    }

    /**
     * Construye array de items para el PDF
     */
    private function build_items_array($order_items, $order) {
        $items = [];
        if (!empty($order_items)) {
            foreach ($order_items as $item) {
                $items[] = ['name' => $item->product_name, 'qty' => intval($item->quantity), 'price' => floatval($item->unit_price)];
            }
        } else {
            $items[] = ['name' => 'Compra en ' . ($order->store_name ?? 'Tienda'), 'qty' => 1, 'price' => floatval($order->total_amount)];
        }
        if ($order->delivery_fee > 0) {
            $items[] = ['name' => 'Delivery', 'qty' => 1, 'price' => floatval($order->delivery_fee)];
        }
        return $items;
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

        // Obtener items reales del pedido
        $order_items = $this->get_order_items($order_id);
        $grand = $order->total_amount + ($order->delivery_fee ?? 0);
        $neto = round($grand / 1.19);
        $iva = $grand - $neto;

        // HTML items table para preview
        $items_html = $this->build_items_html($order_items, $order, $order_id, $neto, $iva, $grand);

        // PDF items array
        $items = $this->build_items_array($order_items, $order);

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
        $this->send_invoice_email($order, $inv_number, $pdf_path, $order_items);
    }

    // ============================================================
    // EMAIL — Send invoice to customer + BCC
    // ============================================================
    private function send_invoice_email($order, $inv_number, $pdf_path, $order_items = []) {
        $to = $order->customer_email;
        if (!$to) return;

        $grand = $order->total_amount + ($order->delivery_fee ?? 0);
        $subject  = 'PetsGo — Tu Boleta ' . $inv_number;
        $pretext  = 'Boleta ' . $inv_number . ' por $' . number_format($grand, 0, ',', '.') . ' en ' . $order->store_name;

        // Construir tabla de productos
        $items_rows = '';
        if (!empty($order_items)) {
            foreach ($order_items as $item) {
                $items_rows .= '
        <tr>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;">' . esc_html($item->product_name) . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;text-align:center;">' . intval($item->quantity) . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;text-align:right;">$' . number_format($item->unit_price, 0, ',', '.') . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;text-align:right;font-weight:600;">$' . number_format($item->subtotal, 0, ',', '.') . '</td>
        </tr>';
            }
        } else {
            $items_rows = '
        <tr>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;">Compra en ' . esc_html($order->store_name) . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;text-align:center;">1</td>
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;text-align:right;">$' . number_format($order->total_amount, 0, ',', '.') . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;text-align:right;font-weight:600;">$' . number_format($order->total_amount, 0, ',', '.') . '</td>
        </tr>';
        }

        // Delivery row
        $delivery_row = '';
        if ($order->delivery_fee > 0) {
            $delivery_row = '
        <tr style="background-color:#f8f9fa;">
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;" colspan="3">🚚 Delivery</td>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;text-align:right;font-weight:600;">$' . number_format($order->delivery_fee, 0, ',', '.') . '</td>
        </tr>';
        }

        // Neto / IVA
        $neto = round($grand / 1.19);
        $iva = $grand - $neto;

        $inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($order->customer_name ?? 'Cliente') . '</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">Gracias por tu compra en <strong>' . esc_html($order->store_name) . '</strong>. Tu boleta ha sido generada exitosamente.</p>

      <!-- Invoice header -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:16px;">
        <tr>
          <td style="font-size:13px;color:#555;">N&ordm; Boleta: <strong style="color:#333;">' . esc_html($inv_number) . '</strong></td>
          <td style="font-size:13px;color:#555;text-align:right;">Tienda: <strong style="color:#333;">' . esc_html($order->store_name) . '</strong></td>
        </tr>
        <tr>
          <td style="font-size:13px;color:#555;">Fecha: ' . date('d/m/Y H:i') . ' hrs</td>
          <td></td>
        </tr>
      </table>

      <!-- Items table -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e9ecef;border-radius:8px;overflow:hidden;">
        <tr style="background-color:#00A8E8;">
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;">Producto</td>
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;text-align:center;width:60px;">Cant.</td>
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;text-align:right;width:90px;">P. Unit</td>
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;text-align:right;width:90px;">Subtotal</td>
        </tr>' . $items_rows . $delivery_row . '
      </table>

      <!-- Totals -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:12px;">
        <tr>
          <td style="text-align:right;font-size:12px;color:#888;padding:3px 14px;">Neto: $' . number_format($neto, 0, ',', '.') . '</td>
        </tr>
        <tr>
          <td style="text-align:right;font-size:12px;color:#888;padding:3px 14px;">IVA (19%): $' . number_format($iva, 0, ',', '.') . '</td>
        </tr>
        <tr>
          <td style="text-align:right;font-size:18px;font-weight:700;color:#00A8E8;padding:6px 14px;">Total: $' . number_format($grand, 0, ',', '.') . '</td>
        </tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:24px;">
        <tr>
          <td style="background-color:#f0faff;border-radius:8px;padding:16px 18px;">
            <p style="margin:0;font-size:13px;color:#00718a;line-height:1.6;">
              &#128206; Adjuntamos tu boleta en formato PDF. Tambi&eacute;n puedes verificar su validez escaneando el c&oacute;digo QR incluido en el documento.
            </p>
          </td>
        </tr>
      </table>

      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este mensaje es una notificaci&oacute;n autom&aacute;tica de compra de PetsGo.<br>
        Se envi&oacute; a <span style="color:#888;">' . esc_html($to) . '</span> por ser el correo de tu cuenta.
      </p>';

        $body = $this->email_wrap($inner, $pretext);

        $bcc = [$this->pg_setting('company_bcc_email','contacto@petsgo.cl')];
        if (!empty($order->vendor_email)) $bcc[] = $order->vendor_email;

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->pg_setting('company_name','PetsGo') . ' <' . $this->pg_setting('company_from_email','notificaciones@petsgo.cl') . '>',
            'Reply-To: ' . $this->pg_setting('company_name','PetsGo') . ' Soporte <' . $this->pg_setting('company_email','contacto@petsgo.cl') . '>',
            'List-Unsubscribe: <mailto:' . $this->pg_setting('company_email','contacto@petsgo.cl') . '?subject=Desuscribir>',
            'X-Mailer: PetsGo/1.0',
        ];
        foreach ($bcc as $b) { $headers[] = 'Bcc: ' . $b; }

        $attachments = [];
        if (file_exists($pdf_path)) $attachments[] = $pdf_path;

        wp_mail($to, $subject, $body, $headers, $attachments);
    }

    // ============================================================
    // CONFIGURACIÓN PETSGO — Admin settings page
    // ============================================================
    public function page_settings() {
        if (!$this->is_admin()) { echo '<div class="wrap"><h1>⛔ Sin acceso</h1></div>'; return; }
        $s = get_option('petsgo_settings', []);
        $d = $this->pg_defaults();
        $v = function($key) use ($s, $d) { return esc_attr($s[$key] ?? $d[$key] ?? ''); };
        $logo_id  = intval($s['logo_id'] ?? 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        $logo_preview = $logo_url ?: content_url('uploads/petsgo-assets/logo-petsgo.png');
        ?>
        <div class="wrap petsgo-wrap">
            <h1>⚙️ Configuración PetsGo</h1>
            <p class="petsgo-info-bar">Desde aquí puedes configurar toda la información corporativa de PetsGo. Estos datos se usan en correos, boletas, panel y frontend.</p>

            <form id="pg-settings-form" novalidate>
            <div class="petsgo-form-grid" style="grid-template-columns:1fr 1fr;max-width:1100px;">

                <!-- ===== DATOS EMPRESA ===== -->
                <div class="petsgo-form-section">
                    <h3>🏢 Datos de la Empresa</h3>
                    <div class="petsgo-field"><label>Nombre empresa</label><input type="text" id="ps-name" value="<?php echo $v('company_name'); ?>" maxlength="100"></div>
                    <div class="petsgo-field"><label>Slogan / Tagline</label><input type="text" id="ps-tagline" value="<?php echo $v('company_tagline'); ?>" maxlength="150"></div>
                    <div class="petsgo-field"><label>RUT empresa</label><input type="text" id="ps-rut" value="<?php echo $v('company_rut'); ?>" maxlength="20" placeholder="77.123.456-7"></div>
                    <div class="petsgo-field"><label>Dirección</label><input type="text" id="ps-address" value="<?php echo $v('company_address'); ?>" maxlength="255"></div>
                    <div class="petsgo-field"><label>Teléfono</label><input type="text" id="ps-phone" value="<?php echo $v('company_phone'); ?>" maxlength="30" placeholder="+56 9 1234 5678"></div>
                    <div class="petsgo-field"><label>Sitio web</label><input type="url" id="ps-website" value="<?php echo $v('company_website'); ?>" placeholder="https://petsgo.cl"></div>

                    <h3 style="margin-top:24px;">📧 Correo Electrónico</h3>
                    <div class="petsgo-field">
                        <label>Email de contacto</label>
                        <input type="email" id="ps-email" value="<?php echo $v('company_email'); ?>">
                        <div class="field-hint">Se muestra en correos y boletas como contacto principal.</div>
                    </div>
                    <div class="petsgo-field">
                        <label>Email BCC (copia oculta)</label>
                        <input type="email" id="ps-bcc" value="<?php echo $v('company_bcc_email'); ?>">
                        <div class="field-hint">Todos los correos enviados por PetsGo incluirán este email en copia oculta.</div>
                    </div>
                    <div class="petsgo-field">
                        <label>Email BCC Tickets (copia oculta)</label>
                        <input type="text" id="ps-tickets-bcc" value="<?php echo $v('tickets_bcc_email'); ?>">
                        <div class="field-hint">Emails BCC para notificaciones de tickets. Separar múltiples con coma. También configurable desde Vista Previa de Correos.</div>
                    </div>
                    <div class="petsgo-field">
                        <label>Email remitente (From)</label>
                        <input type="email" id="ps-from" value="<?php echo $v('company_from_email'); ?>">
                        <div class="field-hint">Dirección que aparece como remitente en los correos enviados.</div>
                    </div>
                </div>

                <!-- ===== IDENTIDAD VISUAL ===== -->
                <div class="petsgo-form-section">
                    <h3>🎨 Identidad Visual</h3>

                    <div class="petsgo-field">
                        <label>Logo principal</label>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <div id="ps-logo-preview" style="width:160px;height:80px;border:2px dashed #ccc;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" onclick="pgSelectLogo()">
                                <img src="<?php echo esc_url($logo_preview); ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
                            </div>
                            <input type="hidden" id="ps-logo-id" value="<?php echo $logo_id; ?>">
                            <div>
                                <button type="button" class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" onclick="pgSelectLogo()">📁 Seleccionar</button>
                                <?php if ($logo_id): ?>
                                <button type="button" class="petsgo-btn petsgo-btn-danger petsgo-btn-sm" onclick="pgRemoveLogo()" style="margin-top:4px;">✕ Quitar</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="field-hint">Se usa en cabecera de correos y boletas. Recomendado: PNG transparente, 320×160px.</div>
                    </div>

                    <h3 style="margin-top:24px;">🎨 Paleta de Colores</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="petsgo-field">
                            <label>Primario</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" id="ps-color-primary" value="<?php echo $v('color_primary'); ?>" style="width:44px;height:36px;padding:2px;border:1px solid #ccc;border-radius:6px;cursor:pointer;">
                                <input type="text" id="ps-color-primary-hex" value="<?php echo $v('color_primary'); ?>" maxlength="7" style="width:90px;font-family:monospace;" oninput="document.getElementById('ps-color-primary').value=this.value">
                            </div>
                        </div>
                        <div class="petsgo-field">
                            <label>Secundario</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" id="ps-color-secondary" value="<?php echo $v('color_secondary'); ?>" style="width:44px;height:36px;padding:2px;border:1px solid #ccc;border-radius:6px;cursor:pointer;">
                                <input type="text" id="ps-color-secondary-hex" value="<?php echo $v('color_secondary'); ?>" maxlength="7" style="width:90px;font-family:monospace;" oninput="document.getElementById('ps-color-secondary').value=this.value">
                            </div>
                        </div>
                        <div class="petsgo-field">
                            <label>Oscuro</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" id="ps-color-dark" value="<?php echo $v('color_dark'); ?>" style="width:44px;height:36px;padding:2px;border:1px solid #ccc;border-radius:6px;cursor:pointer;">
                                <input type="text" id="ps-color-dark-hex" value="<?php echo $v('color_dark'); ?>" maxlength="7" style="width:90px;font-family:monospace;" oninput="document.getElementById('ps-color-dark').value=this.value">
                            </div>
                        </div>
                        <div class="petsgo-field">
                            <label>Éxito</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" id="ps-color-success" value="<?php echo $v('color_success'); ?>" style="width:44px;height:36px;padding:2px;border:1px solid #ccc;border-radius:6px;cursor:pointer;">
                                <input type="text" id="ps-color-success-hex" value="<?php echo $v('color_success'); ?>" maxlength="7" style="width:90px;font-family:monospace;" oninput="document.getElementById('ps-color-success').value=this.value">
                            </div>
                        </div>
                        <div class="petsgo-field">
                            <label>Peligro</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" id="ps-color-danger" value="<?php echo $v('color_danger'); ?>" style="width:44px;height:36px;padding:2px;border:1px solid #ccc;border-radius:6px;cursor:pointer;">
                                <input type="text" id="ps-color-danger-hex" value="<?php echo $v('color_danger'); ?>" maxlength="7" style="width:90px;font-family:monospace;" oninput="document.getElementById('ps-color-danger').value=this.value">
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div style="margin-top:20px;padding:16px;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                        <p style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin:0 0 10px;">Vista previa paleta</p>
                        <div id="ps-palette-preview" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <div style="width:60px;height:40px;border-radius:6px;background:<?php echo $v('color_primary'); ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">Primary</div>
                            <div style="width:60px;height:40px;border-radius:6px;background:<?php echo $v('color_secondary'); ?>;display:flex;align-items:center;justify-content:center;color:#2F3A40;font-size:10px;font-weight:700;">Second</div>
                            <div style="width:60px;height:40px;border-radius:6px;background:<?php echo $v('color_dark'); ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">Dark</div>
                            <div style="width:60px;height:40px;border-radius:6px;background:<?php echo $v('color_success'); ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">Success</div>
                            <div style="width:60px;height:40px;border-radius:6px;background:<?php echo $v('color_danger'); ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">Danger</div>
                        </div>
                    </div>

                    <h3 style="margin-top:24px;">🌐 Redes Sociales</h3>
                    <div class="petsgo-field"><label>Instagram URL</label><input type="url" id="ps-instagram" value="<?php echo $v('social_instagram'); ?>" placeholder="https://www.instagram.com/petsgo.cl"></div>
                    <div class="petsgo-field"><label>Facebook URL</label><input type="url" id="ps-facebook" value="<?php echo $v('social_facebook'); ?>" placeholder="https://www.facebook.com/petsgo.cl"></div>
                    <div class="petsgo-field"><label>LinkedIn URL</label><input type="url" id="ps-linkedin" value="<?php echo $v('social_linkedin'); ?>" placeholder="https://www.linkedin.com/company/petsgo-cl"></div>
                    <div class="petsgo-field"><label>Twitter / X URL</label><input type="url" id="ps-twitter" value="<?php echo $v('social_twitter'); ?>" placeholder="https://x.com/petsgo_cl"></div>
                    <div class="petsgo-field"><label>WhatsApp</label><input type="text" id="ps-whatsapp" value="<?php echo $v('social_whatsapp'); ?>" placeholder="+56912345678"><div class="field-hint">Número con código país. Se usa para el botón de contacto en el footer.</div></div>

                    <h3 style="margin-top:24px;">📋 Suscripciones / Planes</h3>
                    <div class="petsgo-field">
                        <label>Meses de gracia (plan anual)</label>
                        <input type="number" id="ps-annual-free" value="<?php echo $v('plan_annual_free_months'); ?>" min="0" max="6" style="width:80px;">
                        <div class="field-hint">Cantidad de meses gratis al contratar un plan anual. Ej: 2 = paga 10 de 12 meses.</div>
                    </div>

                    <h3 style="margin-top:24px;">🚚 Despacho</h3>
                    <div class="petsgo-field">
                        <label>Monto mínimo para despacho gratis ($)</label>
                        <input type="number" id="ps-free-shipping" value="<?php echo $v('free_shipping_min'); ?>" min="0" step="1000" style="width:140px;">
                        <div class="field-hint">Monto mínimo de compra para despacho gratis. Ej: 39990 = $39.990. Se muestra en Header y HomePage.</div>
                    </div>

                    <h3 style="margin-top:24px;">💰 Comisiones y Tarifas de Delivery</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div class="petsgo-field">
                            <label>% Rider (del fee)</label>
                            <input type="number" id="ps-rider-pct" value="<?php echo $v('rider_commission_pct'); ?>" min="0" max="100" step="1" style="width:80px;">
                            <div class="field-hint">% del delivery fee que recibe el rider.</div>
                        </div>
                        <div class="petsgo-field">
                            <label>% PetsGo (del fee)</label>
                            <input type="number" id="ps-petsgo-pct" value="<?php echo $v('petsgo_fee_pct'); ?>" min="0" max="100" step="1" style="width:80px;">
                            <div class="field-hint">% que retiene PetsGo.</div>
                        </div>
                        <div class="petsgo-field">
                            <label>% Tienda (del fee)</label>
                            <input type="number" id="ps-store-pct" value="<?php echo $v('store_fee_pct'); ?>" min="0" max="100" step="1" style="width:80px;">
                            <div class="field-hint">% que paga la tienda.</div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="petsgo-field">
                            <label>Fee mínimo (CLP)</label>
                            <input type="number" id="ps-fee-min" value="<?php echo $v('delivery_fee_min'); ?>" min="0" step="500" style="width:110px;">
                        </div>
                        <div class="petsgo-field">
                            <label>Fee máximo (CLP)</label>
                            <input type="number" id="ps-fee-max" value="<?php echo $v('delivery_fee_max'); ?>" min="0" step="500" style="width:110px;">
                        </div>
                        <div class="petsgo-field">
                            <label>Tarifa base (CLP)</label>
                            <input type="number" id="ps-base-rate" value="<?php echo $v('delivery_base_rate'); ?>" min="0" step="100" style="width:110px;">
                        </div>
                        <div class="petsgo-field">
                            <label>CLP por km extra</label>
                            <input type="number" id="ps-per-km" value="<?php echo $v('delivery_per_km'); ?>" min="0" step="50" style="width:110px;">
                        </div>
                    </div>
                    <div class="petsgo-field">
                        <label>Día de pago semanal</label>
                        <select id="ps-payout-day" style="width:160px;">
                            <?php $pd = $v('payout_day'); foreach(['monday'=>'Lunes','tuesday'=>'Martes','wednesday'=>'Miércoles','thursday'=>'Jueves','friday'=>'Viernes'] as $dv=>$dl): ?>
                            <option value="<?php echo $dv; ?>" <?php echo $pd===$dv?'selected':''; ?>><?php echo $dl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">Día en que se procesan los pagos a riders. El ciclo es lunes a domingo.</div>
                    </div>
                    <div style="margin-top:12px;padding:12px 16px;background:#f0f9ff;border-radius:8px;border-left:4px solid #00A8E8;font-size:12px;color:#1e3a5f;">
                        <strong>ℹ️ Fórmula:</strong> delivery_fee = tarifa_base + (distancia_km × CLP_por_km), acotado entre fee_min y fee_max.<br>
                        <strong>Split:</strong> Rider <?php echo $v('rider_commission_pct'); ?>% · PetsGo <?php echo $v('petsgo_fee_pct'); ?>% · Tienda <?php echo $v('store_fee_pct'); ?>%.
                    </div>

                    <h3 style="margin-top:24px;">📝 Footer del Sitio</h3>
                    <div class="petsgo-field">
                        <label>Descripción del footer</label>
                        <textarea id="ps-footer-desc" rows="3" style="width:100%;font-family:inherit;font-size:13px;padding:10px;border:1px solid #ccc;border-radius:6px;resize:vertical;"><?php echo esc_textarea($v('footer_description')); ?></textarea>
                        <div class="field-hint">Texto que aparece junto al logo en el pie de página del sitio web.</div>
                    </div>
                </div>
            </div>

            <!-- Save button -->
            <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                <button type="submit" class="petsgo-btn petsgo-btn-primary" style="padding:10px 32px;font-size:15px;">💾 Guardar Configuración</button>
                <a href="<?php echo admin_url('admin.php?page=petsgo-email-preview'); ?>" class="petsgo-btn petsgo-btn-warning" style="padding:10px 24px;font-size:15px;">📧 Preview Correos</a>
                <span class="petsgo-loader" id="ps-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <div id="ps-msg" style="display:none;"></div>
            </div>
            </form>
        </div>

        <script>
        jQuery(function($){
            // Color picker sync
            ['primary','secondary','dark','success','danger'].forEach(function(c){
                $('#ps-color-'+c).on('input',function(){
                    $('#ps-color-'+c+'-hex').val($(this).val());
                    updatePalette();
                });
                $('#ps-color-'+c+'-hex').on('input',function(){
                    var v=$(this).val();
                    if(/^#[0-9A-Fa-f]{6}$/.test(v)) $('#ps-color-'+c).val(v);
                    updatePalette();
                });
            });
            function updatePalette(){
                var divs=$('#ps-palette-preview > div');
                divs.eq(0).css('background',$('#ps-color-primary').val());
                divs.eq(1).css('background',$('#ps-color-secondary').val());
                divs.eq(2).css('background',$('#ps-color-dark').val());
                divs.eq(3).css('background',$('#ps-color-success').val());
                divs.eq(4).css('background',$('#ps-color-danger').val());
            }

            // Logo selector
            window.pgSelectLogo = function(){
                var frame = wp.media({title:'Seleccionar Logo PetsGo',library:{type:'image'},multiple:false});
                frame.on('select',function(){
                    var att=frame.state().get('selection').first().toJSON();
                    $('#ps-logo-id').val(att.id);
                    $('#ps-logo-preview').html('<img src="'+att.url+'" style="max-width:100%;max-height:100%;object-fit:contain;">');
                });
                frame.open();
            };
            window.pgRemoveLogo = function(){
                $('#ps-logo-id').val('');
                var defaultLogo = '<?php echo esc_url(content_url('uploads/petsgo-assets/logo-petsgo.png')); ?>';
                $('#ps-logo-preview').html('<img src="'+defaultLogo+'" style="max-width:100%;max-height:100%;object-fit:contain;">');
            };

            // Save
            $('#pg-settings-form').on('submit',function(e){
                e.preventDefault();
                $('#ps-loader').addClass('active');$('#ps-msg').hide();
                PG.post('petsgo_save_settings',{
                    company_name:$('#ps-name').val(),
                    company_tagline:$('#ps-tagline').val(),
                    company_rut:$('#ps-rut').val(),
                    company_address:$('#ps-address').val(),
                    company_phone:$('#ps-phone').val(),
                    company_email:$('#ps-email').val(),
                    company_bcc_email:$('#ps-bcc').val(),
                    company_from_email:$('#ps-from').val(),
                    company_website:$('#ps-website').val(),
                    tickets_bcc_email:$('#ps-tickets-bcc').val(),
                    footer_description:$('#ps-footer-desc').val(),
                    social_instagram:$('#ps-instagram').val(),
                    social_facebook:$('#ps-facebook').val(),
                    social_linkedin:$('#ps-linkedin').val(),
                    social_twitter:$('#ps-twitter').val(),
                    social_whatsapp:$('#ps-whatsapp').val(),
                    color_primary:$('#ps-color-primary').val(),
                    color_secondary:$('#ps-color-secondary').val(),
                    color_dark:$('#ps-color-dark').val(),
                    color_success:$('#ps-color-success').val(),
                    color_danger:$('#ps-color-danger').val(),
                    logo_id:$('#ps-logo-id').val(),
                    plan_annual_free_months:$('#ps-annual-free').val(),
                    free_shipping_min:$('#ps-free-shipping').val(),
                    rider_commission_pct:$('#ps-rider-pct').val(),
                    petsgo_fee_pct:$('#ps-petsgo-pct').val(),
                    store_fee_pct:$('#ps-store-pct').val(),
                    delivery_fee_min:$('#ps-fee-min').val(),
                    delivery_fee_max:$('#ps-fee-max').val(),
                    delivery_base_rate:$('#ps-base-rate').val(),
                    delivery_per_km:$('#ps-per-km').val(),
                    payout_day:$('#ps-payout-day').val()
                },function(r){
                    $('#ps-loader').removeClass('active');
                    var c=r.success?'#d4edda':'#f8d7da',t=r.success?'#155724':'#721c24';
                    $('#ps-msg').html(r.data||'Error').css({display:'inline-block',background:c,color:t,padding:'8px 16px',borderRadius:'6px',fontSize:'13px',fontWeight:'600'});
                    if(r.success) setTimeout(function(){$('#ps-msg').fadeOut();},3000);
                });
            });
        });
        </script>
        <?php
    }

    public function petsgo_save_settings() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Solo admin puede configurar.');

        $allowed = array_keys($this->pg_defaults());
        $settings = get_option('petsgo_settings', []);

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                if ($key === 'tickets_bcc_email') {
                    // Multiple emails separated by comma
                    $settings[$key] = sanitize_text_field($_POST[$key]);
                } elseif (strpos($key, 'email') !== false) {
                    $settings[$key] = sanitize_email($_POST[$key]);
                } elseif (strpos($key, 'color') !== false) {
                    $settings[$key] = sanitize_hex_color($_POST[$key]) ?: '';
                } elseif ($key === 'logo_id' || $key === 'plan_annual_free_months' || $key === 'free_shipping_min') {
                    $settings[$key] = intval($_POST[$key]);
                } elseif (in_array($key, ['rider_commission_pct','store_fee_pct','petsgo_fee_pct','delivery_fee_min','delivery_fee_max','delivery_base_rate','delivery_per_km'])) {
                    $settings[$key] = strval(intval($_POST[$key]));
                } elseif (strpos($key, 'social_') === 0 || strpos($key, 'website') !== false) {
                    $settings[$key] = esc_url_raw($_POST[$key]);
                } else {
                    $settings[$key] = sanitize_text_field($_POST[$key]);
                }
            }
        }

        update_option('petsgo_settings', $settings);
        $this->audit('settings_update', 'settings', 0, 'Configuración actualizada');
        wp_send_json_success('✅ Configuración guardada correctamente.');
    }

    // ============================================================
    // PREVIEW EMAILS — Vista previa HTML de correos
    // ============================================================
    public function petsgo_preview_email() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        $type = sanitize_text_field($_POST['email_type'] ?? 'stock_zero');

        $demo_img = 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=300&h=300&fit=crop';

        switch ($type) {
            case 'stock_low':
                $html = $this->stock_email_html('Patitas Chile', 'Royal Canin Adulto 3kg', 3, false, 'patitas@demo.cl', $demo_img);
                break;
            case 'stock_zero':
                $html = $this->stock_email_html('Mundo Animal', 'Collar Antipulgas Premium', 0, true, 'mundoanimal@demo.cl', $demo_img);
                break;
            case 'invoice':
            default:
                $inv = 'BOL-MA-20260211-001';
                $demo_items = [
                    ['name' => 'Royal Canin Adulto 3kg', 'qty' => 2, 'price' => 18990],
                    ['name' => 'Collar Antipulgas Premium', 'qty' => 1, 'price' => 8990],
                    ['name' => 'Juguete Mordedor Dental', 'qty' => 3, 'price' => 4990],
                ];
                $demo_subtotal = 0;
                $items_rows = '';
                foreach ($demo_items as $di) {
                    $sub = $di['qty'] * $di['price'];
                    $demo_subtotal += $sub;
                    $items_rows .= '
        <tr>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;">' . esc_html($di['name']) . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;text-align:center;">' . $di['qty'] . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;text-align:right;">$' . number_format($di['price'], 0, ',', '.') . '</td>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;text-align:right;font-weight:600;">$' . number_format($sub, 0, ',', '.') . '</td>
        </tr>';
                }
                $delivery = 3990;
                $grand = $demo_subtotal + $delivery;
                $neto = round($grand / 1.19);
                $iva = $grand - $neto;

                $inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>Maria Gonzalez</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">Gracias por tu compra en <strong>Pets Happy Store</strong>. Tu boleta ha sido generada exitosamente.</p>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:16px;">
        <tr>
          <td style="font-size:13px;color:#555;">N&ordm; Boleta: <strong style="color:#333;">' . esc_html($inv) . '</strong></td>
          <td style="font-size:13px;color:#555;text-align:right;">Tienda: <strong style="color:#333;">Pets Happy Store</strong></td>
        </tr>
        <tr>
          <td style="font-size:13px;color:#555;">Fecha: ' . date('d/m/Y H:i') . ' hrs</td>
          <td></td>
        </tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e9ecef;border-radius:8px;overflow:hidden;">
        <tr style="background-color:#00A8E8;">
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;">Producto</td>
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;text-align:center;width:60px;">Cant.</td>
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;text-align:right;width:90px;">P. Unit</td>
          <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:0.5px;text-align:right;width:90px;">Subtotal</td>
        </tr>' . $items_rows . '
        <tr style="background-color:#f8f9fa;">
          <td style="padding:10px 14px;font-size:13px;color:#555;border-top:1px solid #f0f0f0;" colspan="3">🚚 Delivery</td>
          <td style="padding:10px 14px;font-size:13px;color:#333;border-top:1px solid #f0f0f0;text-align:right;font-weight:600;">$' . number_format($delivery, 0, ',', '.') . '</td>
        </tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:12px;">
        <tr>
          <td style="text-align:right;font-size:12px;color:#888;padding:3px 14px;">Neto: $' . number_format($neto, 0, ',', '.') . '</td>
        </tr>
        <tr>
          <td style="text-align:right;font-size:12px;color:#888;padding:3px 14px;">IVA (19%): $' . number_format($iva, 0, ',', '.') . '</td>
        </tr>
        <tr>
          <td style="text-align:right;font-size:18px;font-weight:700;color:#00A8E8;padding:6px 14px;">Total: $' . number_format($grand, 0, ',', '.') . '</td>
        </tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:24px;">
        <tr>
          <td style="background-color:#f0faff;border-radius:8px;padding:16px 18px;">
            <p style="margin:0;font-size:13px;color:#00718a;line-height:1.6;">
              &#128206; Adjuntamos tu boleta en formato PDF. Tambi&eacute;n puedes verificar su validez escaneando el c&oacute;digo QR incluido en el documento.
            </p>
          </td>
        </tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este mensaje es una notificaci&oacute;n autom&aacute;tica de compra de PetsGo.<br>
        Se envi&oacute; a <span style="color:#888;">maria@demo.cl</span> por ser el correo de tu cuenta.
      </p>';
                $html = $this->email_wrap($inner, 'Boleta ' . $inv . ' por $' . number_format($grand, 0, ',', '.') . ' en Pets Happy Store');
                break;

            case 'customer_welcome':
                $inner_cw = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>María</strong>! 🎉</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Te damos la bienvenida a <strong>PetsGo</strong>, el marketplace pensado para quienes aman a sus mascotas.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0faff;border-radius:10px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#333;font-weight:700;">🐾 Con tu cuenta puedes:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>✅ Explorar cientos de productos para tu mascota</td></tr>
            <tr><td>✅ Comprar de las mejores tiendas de Chile</td></tr>
            <tr><td>✅ Recibir delivery en tu puerta</td></tr>
            <tr><td>✅ Registrar a tus mascotas y llevar su perfil</td></tr>
          </table>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(home_url()) . '" style="display:inline-block;background:#00A8E8;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Ir a PetsGo</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">maria@demo.cl</span> porque creaste una cuenta en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_cw, '¡Bienvenida a PetsGo, María!');
                break;

            case 'rider_registro':
                $inner_reg = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>Carlos</strong>! 🚴</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Gracias por registrarte como <strong>Rider en PetsGo</strong>. Para continuar con tu solicitud, verifica tu correo electr&oacute;nico.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td align="center" style="background-color:#fff8ed;border-radius:12px;padding:30px 24px;">
          <p style="margin:0 0 8px;font-size:14px;color:#555;">Tu c&oacute;digo de verificaci&oacute;n es:</p>
          <p style="margin:0;font-size:36px;font-weight:900;letter-spacing:6px;color:#F59E0B;font-family:monospace;">A1B2C3</p>
          <p style="margin:12px 0 0;font-size:12px;color:#999;">V&aacute;lido por 48 horas</p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0f9ff;border-left:4px solid #00A8E8;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#333;font-weight:700;">📋 Pasos del registro:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>✅ Paso 1: Datos b&aacute;sicos registrados</td></tr>
            <tr><td>👉 <strong>Paso 2: Verifica tu email e ingresa tus documentos</strong></td></tr>
            <tr><td>⏳ Paso 3: Revisi&oacute;n y aprobaci&oacute;n del admin</td></tr>
          </table>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">carlos@demo.cl</span> porque te registraste como Rider en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_reg, 'Verifica tu email para continuar, Carlos 🚴');
                break;

            case 'rider_welcome':
                $inner_rw = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>Carlos</strong>! 🚴</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Bienvenido al <strong>equipo de Delivery de PetsGo</strong>. ¡Tu cuenta ha sido <strong style="color:#16a34a;">aprobada</strong> y ya puedes comenzar a realizar entregas!</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0fdf4;border-left:4px solid #22C55E;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 6px;font-size:14px;color:#166534;font-weight:700;">✅ Cuenta Aprobada</p>
          <p style="margin:0;font-size:13px;color:#555;line-height:1.6;">Todos tus documentos fueron verificados exitosamente. Ya formas parte del equipo.</p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fff8ed;border-left:4px solid #FFC400;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#333;font-weight:700;">📦 Pr&oacute;ximos pasos:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>1️⃣ Ingresa a tu Panel de Rider</td></tr>
            <tr><td>2️⃣ Revisa las entregas disponibles</td></tr>
            <tr><td>3️⃣ Acepta y realiza entregas a los clientes</td></tr>
            <tr><td>4️⃣ Entrega con ❤️ y gana valoraciones positivas</td></tr>
          </table>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f8f9fa;border-radius:8px;padding:14px 20px;font-size:13px;color:#555;">
          🚗 Veh&iacute;culo registrado: <strong style="color:#333;">Moto</strong>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(home_url()) . '" style="display:inline-block;background:#FFC400;color:#2F3A40;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Ir a mi Panel de Rider 🚴</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">carlos@demo.cl</span> porque tu cuenta Rider fue aprobada en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_rw, '¡Bienvenido al equipo de Delivery, Carlos!');
                break;

            case 'rider_rechazo':
                $inner_rej = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>Carlos</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Te informamos que uno de tus documentos ha sido <strong style="color:#dc2626;">rechazado</strong> durante la revisi&oacute;n.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fef2f2;border-left:4px solid #ef4444;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 8px;font-size:14px;color:#991b1b;font-weight:700;">❌ Documento rechazado</p>
          <p style="margin:0 0 4px;font-size:13px;color:#555;">Tipo: <strong>Licencia de Conducir</strong></p>
          <p style="margin:0;font-size:13px;color:#555;">Motivo: <strong>La imagen est&aacute; borrosa, por favor sube una foto m&aacute;s n&iacute;tida</strong></p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fff8ed;border-left:4px solid #FFC400;border-radius:8px;padding:16px 24px;">
          <p style="margin:0;font-size:13px;color:#555;line-height:1.6;">📋 Ingresa a tu Panel de Rider y sube nuevamente el documento corregido para continuar con tu solicitud.</p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(home_url()) . '" style="display:inline-block;background:#F97316;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Subir Documento Corregido</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">carlos@demo.cl</span> por la revisi&oacute;n de tu cuenta Rider en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_rej, 'Documento rechazado - Acci&oacute;n requerida');
                break;

            case 'vendor_welcome':
                $inner_vw = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola equipo de <strong>Patitas Chile</strong>! 🏪</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Estamos encantados de darles la bienvenida a <strong>PetsGo</strong>. Su tienda ya está activa y lista para vender.</p>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#e8f5e9;border-radius:10px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#2e7d32;font-weight:700;">🎯 Su tienda incluye:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>✅ Panel de administración de productos</td></tr>
            <tr><td>✅ Gestión de pedidos en tiempo real</td></tr>
            <tr><td>✅ Dashboard con analíticas de ventas</td></tr>
            <tr><td>✅ Configuración de boleta electrónica</td></tr>
            <tr><td>✅ Delivery integrado con riders PetsGo</td></tr>
          </table>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f8f9fa;border-radius:8px;padding:16px 20px;">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555;">
            <tr><td style="padding:3px 0;font-weight:600;width:140px;">Plan:</td><td style="padding:3px 0;"><strong style="color:#00A8E8;">Pro</strong></td></tr>
            <tr><td style="padding:3px 0;font-weight:600;">Tienda:</td><td style="padding:3px 0;">Patitas Chile</td></tr>
            <tr><td style="padding:3px 0;font-weight:600;">Email:</td><td style="padding:3px 0;">patitas@demo.cl</td></tr>
            <tr><td style="padding:3px 0;font-weight:600;">📅 Inicio suscripci&oacute;n:</td><td style="padding:3px 0;font-weight:600;color:#333;">' . date('d/m/Y') . '</td></tr>
            <tr><td style="padding:3px 0;font-weight:600;">📅 Fin suscripci&oacute;n:</td><td style="padding:3px 0;font-weight:600;color:#333;">' . date('d/m/Y', strtotime('+1 year')) . '</td></tr>
          </table>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(admin_url('admin.php?page=petsgo-dashboard')) . '" style="display:inline-block;background:#00A8E8;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Ir al Panel de Tienda</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">patitas@demo.cl</span> porque su tienda fue registrada en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_vw, '¡Bienvenida a PetsGo, Patitas Chile!');
                break;

            case 'subscription_payment':
                $free_m = intval($this->pg_setting('plan_annual_free_months', 2));
                $charged = 12 - $free_m;
                $monthly = 59990;
                $total_sub = $monthly * $charged;
                $neto_sub = round($total_sub / 1.19);
                $iva_sub = $total_sub - $neto_sub;
                $start_sub = date('d/m/Y');
                $end_sub = date('d/m/Y', strtotime('+1 year'));

                $inner_sp = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola equipo de <strong>Patitas Chile</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Confirmamos que hemos recibido el pago de tu suscripci&oacute;n al <strong>Plan Pro</strong> de PetsGo.</p>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#e0f7fa;border-radius:10px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#00695c;font-weight:700;">💳 Detalle del pago</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555;">
            <tr><td style="padding:4px 0;font-weight:600;width:140px;">Plan:</td><td style="padding:4px 0;"><strong style="color:#00A8E8;">Pro</strong></td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Per&iacute;odo:</td><td style="padding:4px 0;">Anual (12 meses)</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Precio mensual:</td><td style="padding:4px 0;">$' . number_format($monthly, 0, ',', '.') . '</td></tr>' .
            ($free_m > 0 ? '
            <tr><td style="padding:4px 0;font-weight:600;">Beneficio:</td><td style="padding:4px 0;color:#28a745;font-weight:600;">' . $free_m . ' mes(es) de gracia</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Meses cobrados:</td><td style="padding:4px 0;">' . $charged . ' de 12</td></tr>' : '') . '
            <tr><td style="padding:4px 0;font-weight:600;">Neto:</td><td style="padding:4px 0;">$' . number_format($neto_sub, 0, ',', '.') . '</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">IVA (19%):</td><td style="padding:4px 0;">$' . number_format($iva_sub, 0, ',', '.') . '</td></tr>
            <tr><td style="padding:4px 0;font-weight:700;font-size:15px;color:#00A8E8;">Total:</td><td style="padding:4px 0;font-weight:700;font-size:15px;color:#00A8E8;">$' . number_format($total_sub, 0, ',', '.') . '</td></tr>
          </table>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f8f9fa;border-radius:8px;padding:16px 20px;">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555;">
            <tr><td style="padding:3px 0;font-weight:600;width:140px;">📅 Vigencia desde:</td><td style="padding:3px 0;"><strong style="color:#333;">' . $start_sub . '</strong></td></tr>
            <tr><td style="padding:3px 0;font-weight:600;">📅 Vigencia hasta:</td><td style="padding:3px 0;"><strong style="color:#333;">' . $end_sub . '</strong></td></tr>
            <tr><td style="padding:3px 0;font-weight:600;">N&ordm; Boleta:</td><td style="padding:3px 0;">SUB-PC-' . date('Ymd') . '-001</td></tr>
          </table>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0faff;border-radius:8px;padding:16px 18px;">
          <p style="margin:0;font-size:13px;color:#00718a;line-height:1.6;">
            📎 Adjuntamos tu boleta de suscripci&oacute;n en formato PDF. Tambi&eacute;n puedes verificar su validez escaneando el c&oacute;digo QR incluido en el documento.
          </p>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(admin_url('admin.php?page=petsgo-dashboard')) . '" style="display:inline-block;background:#00A8E8;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Ir al Panel de Tienda</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">patitas@demo.cl</span> como confirmaci&oacute;n de pago de suscripci&oacute;n en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_sp, 'Confirmación de pago — Plan Pro por $' . number_format($total_sub, 0, ',', '.'));
                break;

            case 'renewal_reminder':
                $demo_vendor = (object) [
                    'store_name'        => 'Patitas Chile',
                    'plan_name'         => 'Pro',
                    'email'             => 'patitas@demo.cl',
                    'subscription_end'  => date('Y-m-d', strtotime('+5 days')),
                ];
                $html = $this->renewal_reminder_email_html($demo_vendor, 5);
                break;

            case 'lead_thankyou':
                $inner_lt = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>Andrea Muñoz</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Gracias por tu interés en unirte a <strong>PetsGo</strong> con tu tienda <strong>Pet Paradise</strong>. Hemos recibido tu solicitud y nuestro equipo la revisará a la brevedad.</p>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0faff;border-radius:10px;padding:20px 24px;">
          <p style="margin:0 0 10px;font-size:14px;color:#00A8E8;font-weight:700;">📋 Resumen de tu solicitud</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555;">
            <tr><td style="padding:4px 0;font-weight:600;width:120px;">Tienda:</td><td style="padding:4px 0;">Pet Paradise</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Contacto:</td><td style="padding:4px 0;">Andrea Muñoz</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Email:</td><td style="padding:4px 0;">andrea@petparadise.cl</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Teléfono:</td><td style="padding:4px 0;">+569 8765 4321</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Comuna:</td><td style="padding:4px 0;">Providencia</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Plan:</td><td style="padding:4px 0;">Pro</td></tr>
          </table>
          <p style="margin:12px 0 0;font-size:13px;color:#555;border-top:1px solid #e0f0f8;padding-top:12px;"><strong>Mensaje:</strong><br>Tenemos una tienda de mascotas con más de 200 productos y nos encantaría sumarnos a la plataforma.</p>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fff8ed;border-left:4px solid #FFC400;border-radius:8px;padding:16px 20px;">
          <p style="margin:0;font-size:13px;color:#8a6d00;line-height:1.6;">
            ⏱️ <strong>¿Qué sigue?</strong><br>
            Un ejecutivo de PetsGo se contactará contigo en las próximas <strong>24-48 horas hábiles</strong> para coordinar la incorporación de tu tienda.
          </p>
        </td></tr>
      </table>

      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">andrea@petparadise.cl</span> porque completaste el formulario de contacto en PetsGo.
      </p>';
                $html = $this->email_wrap($inner_lt, 'Gracias por tu interés en PetsGo, Andrea');
                break;

            // ── TICKETS ──
            case 'ticket_creator':
                $inner_tc = '<h2 style="color:#00A8E8;margin:0 0 16px;">🎫 Ticket Creado: TK-20260212-001</h2>
                <p>Hola <strong>María González</strong>,</p>
                <p>Tu solicitud ha sido recibida exitosamente. Nuestro equipo revisará tu caso y te responderá a la brevedad.</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">TK-20260212-001</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">Problema con mi pedido #1234</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Estado</td><td style="padding:8px 12px;border:1px solid #eee;"><span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Abierto</span></td></tr>
                </table>
                <p style="color:#888;font-size:13px;">Recibirás notificaciones por correo cuando haya actualizaciones en tu ticket.</p>';
                $html = $this->email_wrap($inner_tc, '🎫 Ticket Creado — TK-20260212-001');
                break;

            case 'ticket_admin':
                $inner_ta = '<h2 style="color:#dc3545;margin:0 0 16px;">🔔 Nuevo Ticket: TK-20260212-002</h2>
                <p>Se ha recibido un nuevo ticket de soporte.</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">TK-20260212-002</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Solicitante</td><td style="padding:8px 12px;border:1px solid #eee;">Andrea Muñoz <span style="background:#e2e3e5;padding:2px 8px;border-radius:10px;font-size:11px;">Tienda</span></td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">No puedo subir mis productos</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Categoría</td><td style="padding:8px 12px;border:1px solid #eee;">productos</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Prioridad</td><td style="padding:8px 12px;border:1px solid #eee;">🟠 Alta</td></tr>
                </table>
                <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:16px 0;">
                    <p style="font-weight:600;margin:0 0 8px;">Descripción:</p>
                    <p style="margin:0;">Intento agregar productos a mi tienda pero el formulario me da error al subir imágenes. Ya probé con diferentes formatos (JPG, PNG) y el problema persiste.</p>
                </div>
                <p><a href="'.admin_url('admin.php?page=petsgo-tickets').'" style="display:inline-block;background:#00A8E8;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;">Ver en Panel Admin</a></p>';
                $html = $this->email_wrap($inner_ta, '🔔 Nuevo Ticket — TK-20260212-002');
                break;

            case 'ticket_status':
                $inner_ts = '<h2 style="color:#00A8E8;margin:0 0 16px;">📋 Actualización de Ticket: TK-20260212-001</h2>
                <p>Hola <strong>María González</strong>,</p>
                <p>Tu ticket ha sido actualizado:</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">TK-20260212-001</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">Problema con mi pedido #1234</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Nuevo Estado</td><td style="padding:8px 12px;border:1px solid #eee;"><span style="background:#cce5ff;color:#004085;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">En Proceso</span></td></tr>
                </table>';
                $html = $this->email_wrap($inner_ts, '📋 Ticket Actualizado — TK-20260212-001');
                break;

            case 'ticket_assigned':
                $inner_tg = '<h2 style="color:#FFC400;margin:0 0 16px;">📌 Ticket Asignado: TK-20260212-002</h2>
                <p>Hola <strong>Admin PetsGo</strong>,</p>
                <p>Se te ha asignado el siguiente ticket de soporte:</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">TK-20260212-002</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Solicitante</td><td style="padding:8px 12px;border:1px solid #eee;">Andrea Muñoz</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">No puedo subir mis productos</td></tr>
                </table>
                <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:16px 0;">
                    <p style="font-weight:600;margin:0 0 8px;">Descripción:</p>
                    <p style="margin:0;">Intento agregar productos a mi tienda pero el formulario me da error al subir imágenes.</p>
                </div>
                <p><a href="'.admin_url('admin.php?page=petsgo-tickets').'" style="display:inline-block;background:#00A8E8;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;">Gestionar Ticket</a></p>';
                $html = $this->email_wrap($inner_tg, '📌 Ticket Asignado — TK-20260212-002');
                break;

            case 'ticket_reply':
                $inner_tr = '<h2 style="color:#00A8E8;margin:0 0 16px;">💬 Nueva Respuesta en Ticket: TK-20260212-001</h2>
                <p>Hola <strong>María González</strong>,</p>
                <p>Has recibido una nueva respuesta en tu ticket:</p>
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">TK-20260212-001</td></tr>
                    <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">Problema con mi pedido #1234</td></tr>
                </table>
                <div style="background:#f0f8ff;border-left:4px solid #00A8E8;border-radius:6px;padding:16px;margin:16px 0;">
                    <p style="margin:0;">Hola María, estamos revisando tu caso. Tu pedido fue reasignado a otro rider y debería llegar en las próximas 2 horas. Disculpa las molestias.</p>
                </div>
                <p style="color:#888;font-size:13px;">Puedes responder desde tu panel en PetsGo.</p>';
                $html = $this->email_wrap($inner_tr, '💬 Respuesta en Ticket — TK-20260212-001');
                break;
        }
        wp_send_json_success(['html' => $html]);
    }

    public function page_email_preview() {
        if (!$this->is_admin()) { echo '<div class="wrap"><h1>⛔ Sin acceso</h1></div>'; return; }
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📧 Vista Previa de Correos</h1>
            <p class="petsgo-info-bar">Previsualiza cómo se ven los correos que envía PetsGo. Estos usan la configuración actual (logo, colores, datos empresa).</p>

            <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
                <button type="button" class="petsgo-btn petsgo-btn-primary ep-tab active" data-type="invoice">🧾 Boleta / Factura</button>
                <button type="button" class="petsgo-btn petsgo-btn-warning ep-tab" data-type="stock_zero">🚫 Stock Agotado</button>
                <button type="button" class="petsgo-btn petsgo-btn-warning ep-tab" data-type="stock_low">⚠️ Stock Bajo</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="customer_welcome" style="background:#e3f2fd;color:#1565c0;border-color:#90caf9;">👤 Bienvenida Cliente</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="rider_registro" style="background:#fff8e1;color:#e65100;border-color:#ffe082;">📝 Registro Rider</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="rider_welcome" style="background:#e8f5e9;color:#166534;border-color:#86efac;">🚴 Activación Rider</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="rider_rechazo" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">❌ Rechazo Doc Rider</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="vendor_welcome" style="background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7;">🏪 Bienvenida Tienda</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="subscription_payment" style="background:#e0f7fa;color:#00695c;border-color:#80deea;">💳 Pago Suscripción</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="renewal_reminder" style="background:#fff3e0;color:#e65100;border-color:#ffcc80;">⏰ Recordatorio Renovación</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="lead_thankyou" style="background:#fce4ec;color:#c62828;border-color:#ef9a9a;">📩 Gracias Lead</button>
                <span style="display:inline-block;width:1px;height:28px;background:#ddd;margin:0 4px;"></span>
                <button type="button" class="petsgo-btn ep-tab" data-type="ticket_creator" style="background:#e8f0fe;color:#1a73e8;border-color:#a8c7fa;">🎫 Ticket Creado</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="ticket_admin" style="background:#fce8e6;color:#c5221f;border-color:#f5a6a1;">🔔 Ticket Admin</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="ticket_status" style="background:#e6f4ea;color:#137333;border-color:#a8dab5;">📋 Ticket Estado</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="ticket_assigned" style="background:#fff8e1;color:#ea8600;border-color:#fdd663;">📌 Ticket Asignado</button>
                <button type="button" class="petsgo-btn ep-tab" data-type="ticket_reply" style="background:#e8f0fe;color:#185abc;border-color:#a8c7fa;">💬 Ticket Respuesta</button>
                <span class="petsgo-loader" id="ep-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
                <a href="<?php echo admin_url('admin.php?page=petsgo-settings'); ?>" class="petsgo-btn petsgo-btn-primary" style="margin-left:auto;">⚙️ Editar Configuración</a>
            </div>

            <!-- Subject / Preheader info -->
            <div id="ep-info" style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:12px 18px;margin-bottom:16px;display:none;">
                <table style="width:100%;font-size:13px;color:#555;">
                    <tr><td style="font-weight:700;width:100px;padding:2px 0;">De:</td><td id="ep-from" style="padding:2px 0;"></td></tr>
                    <tr><td style="font-weight:700;padding:2px 0;">Para:</td><td id="ep-to" style="padding:2px 0;"></td></tr>
                    <tr><td style="font-weight:700;padding:2px 0;">BCC:</td><td id="ep-bcc" style="padding:2px 0;"></td></tr>
                    <tr><td style="font-weight:700;padding:2px 0;">Asunto:</td><td id="ep-subject" style="padding:2px 0;font-weight:600;color:#333;"></td></tr>
                </table>
            </div>

            <!-- Preview iframe -->
            <div style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;background:#fff;">
                <div style="background:#f1f3f5;padding:8px 16px;border-bottom:1px solid #dee2e6;display:flex;align-items:center;gap:8px;">
                    <span style="width:12px;height:12px;border-radius:50%;background:#ff5f56;display:inline-block;"></span>
                    <span style="width:12px;height:12px;border-radius:50%;background:#ffbd2e;display:inline-block;"></span>
                    <span style="width:12px;height:12px;border-radius:50%;background:#27c93f;display:inline-block;"></span>
                    <span style="flex:1;text-align:center;font-size:12px;color:#888;" id="ep-title">Vista previa del correo</span>
                </div>
                <iframe id="ep-frame" style="width:100%;height:750px;border:none;background:#f4f6f8;"></iframe>
            </div>

            <!-- Download demo PDF button (invoice tab) -->
            <div id="ep-pdf-download" style="margin-top:16px;">
                <a href="<?php echo admin_url('admin-ajax.php?action=petsgo_download_demo_invoice&_wpnonce=' . wp_create_nonce('petsgo_ajax')); ?>" class="petsgo-btn petsgo-btn-success" target="_blank" style="font-size:15px;padding:10px 24px;">
                    📥 Descargar PDF de ejemplo (Boleta Demo)
                </a>
                <span style="margin-left:12px;font-size:13px;color:#888;">Genera un PDF de boleta con datos ficticios para verificar el diseño.</span>
            </div>

            <!-- Download demo subscription PDF button (vendor_welcome tab) -->
            <div id="ep-pdf-subscription" style="margin-top:16px;display:none;">
                <a href="<?php echo admin_url('admin-ajax.php?action=petsgo_download_demo_subscription&_wpnonce=' . wp_create_nonce('petsgo_ajax')); ?>" class="petsgo-btn petsgo-btn-success" target="_blank" style="font-size:15px;padding:10px 24px;background:#00A8E8;border-color:#00A8E8;">
                    📥 Descargar PDF de ejemplo (Boleta Suscripcion)
                </a>
                <span style="margin-left:12px;font-size:13px;color:#888;">Genera un PDF de suscripcion de plan con datos ficticios para verificar el diseño.</span>
            </div>

            <!-- ===== CONFIGURACIÓN BCC TICKETS ===== -->
            <div style="margin-top:32px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:24px 28px;">
                <h3 style="margin:0 0 6px;font-size:16px;color:#2F3A40;">📧 Configuración de Copia Oculta (BCC) — Tickets</h3>
                <p style="margin:0 0 18px;font-size:13px;color:#888;">Configura los correos que recibirán copia oculta de <strong>todas</strong> las notificaciones de tickets (creación, asignación, estado, respuesta). Separar múltiples correos con coma.</p>
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:300px;">
                        <label style="display:block;font-size:12px;font-weight:700;color:#6b7280;margin-bottom:6px;">Emails BCC Tickets</label>
                        <input type="text" id="ep-tickets-bcc" value="<?php echo esc_attr($this->pg_setting('tickets_bcc_email', '')); ?>" placeholder="soporte@petsgo.cl, admin@petsgo.cl" style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:Poppins,sans-serif;transition:border-color 0.2s;" onfocus="this.style.borderColor='#00A8E8'" onblur="this.style.borderColor='#e5e7eb'">
                        <div style="font-size:11px;color:#aaa;margin-top:4px;">Ejemplo: soporte@petsgo.cl, admin@petsgo.cl — Déjalo vacío para no enviar BCC.</div>
                    </div>
                    <button type="button" id="ep-save-bcc" class="petsgo-btn petsgo-btn-primary" style="padding:10px 24px;font-size:14px;white-space:nowrap;">
                        💾 Guardar BCC
                    </button>
                    <span id="ep-bcc-msg" style="display:none;font-size:13px;font-weight:600;padding:8px 14px;border-radius:6px;"></span>
                </div>

                <div style="margin-top:16px;background:#f8f9fa;border-radius:8px;padding:14px 18px;">
                    <p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#6b7280;">📌 Correos BCC actuales por tipo:</p>
                    <table style="width:100%;font-size:12px;color:#555;border-collapse:collapse;">
                        <tr><td style="padding:4px 8px;font-weight:600;">Boletas/Stock/Tiendas/Leads:</td><td style="padding:4px 8px;"><?php echo esc_html($this->pg_setting('company_bcc_email', 'contacto@petsgo.cl')); ?> <span style="color:#aaa;">(Configuración → Email BCC)</span></td></tr>
                        <tr><td style="padding:4px 8px;font-weight:600;">Tickets de Soporte:</td><td style="padding:4px 8px;" id="ep-bcc-current"><?php $tb = $this->pg_setting('tickets_bcc_email', ''); echo $tb ? esc_html($tb) : '<span style="color:#ccc;">No configurado</span>'; ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            var meta = {
                invoice:    {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'maria@demo.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>, vendedor@tienda.cl',subject:'PetsGo — Tu Boleta BOL-MA-20260211-001',title:'Correo de boleta / factura'},
                stock_zero: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'mundoanimal@demo.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>',subject:'⚠️ PetsGo — Producto sin stock: Collar Antipulgas Premium',title:'Alerta stock agotado (0 uds)'},
                stock_low:  {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'patitas@demo.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>',subject:'⚠️ PetsGo — Stock bajo: Royal Canin Adulto 3kg (3 uds)',title:'Alerta stock bajo'},
                customer_welcome: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'maria@demo.cl',bcc:'',subject:'¡Bienvenida a PetsGo! 🐾',title:'Bienvenida nuevo cliente'},
                rider_registro: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'carlos@demo.cl',bcc:'',subject:'Verifica tu email - <?php echo $this->pg_setting("company_name","PetsGo"); ?> Rider 🚴',title:'Registro Rider - Código de verificación'},
                rider_welcome: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'carlos@demo.cl',bcc:'',subject:'¡Bienvenido al equipo Rider de <?php echo $this->pg_setting("company_name","PetsGo"); ?>! 🚴',title:'Activación Rider - Admin aprueba cuenta'},
                rider_rechazo: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'carlos@demo.cl',bcc:'',subject:'Documento rechazado - <?php echo $this->pg_setting("company_name","PetsGo"); ?> ⚠️',title:'Rechazo de documento - Admin rechaza documento'},
                vendor_welcome: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'patitas@demo.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>',subject:'¡Bienvenida a PetsGo, Patitas Chile! 🏪',title:'Bienvenida nueva tienda'},
                subscription_payment: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'patitas@demo.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>',subject:'PetsGo — Confirmación de pago Plan Pro 💳',title:'Confirmación pago suscripción'},
                renewal_reminder: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'patitas@demo.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>',subject:'⏰ PetsGo — Tu suscripción vence en 5 días',title:'Recordatorio de renovación'},
                lead_thankyou: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'andrea@petparadise.cl',bcc:'<?php echo $this->pg_setting("company_bcc_email","contacto@petsgo.cl"); ?>',subject:'Gracias por tu interés en PetsGo 🏪',title:'Gracias por tu interés (lead)'},
                ticket_creator: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'maria@demo.cl',bcc:'<?php $tb=$this->pg_setting("tickets_bcc_email",""); echo $tb?$tb:"(no configurado)"; ?>',subject:'PetsGo — Ticket TK-20260212-001 creado',title:'Notificación al creador del ticket'},
                ticket_admin: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'<?php echo $this->pg_setting("company_email","contacto@petsgo.cl"); ?>',bcc:'<?php $tb=$this->pg_setting("tickets_bcc_email",""); echo $tb?$tb:"(no configurado)"; ?>',subject:'PetsGo — Nuevo Ticket TK-20260212-002',title:'Alerta nuevo ticket para admin'},
                ticket_status: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'maria@demo.cl',bcc:'<?php $tb=$this->pg_setting("tickets_bcc_email",""); echo $tb?$tb:"(no configurado)"; ?>',subject:'PetsGo — Ticket TK-20260212-001 actualizado',title:'Cambio de estado del ticket'},
                ticket_assigned: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'admin@petsgo.cl',bcc:'<?php $tb=$this->pg_setting("tickets_bcc_email",""); echo $tb?$tb:"(no configurado)"; ?>',subject:'PetsGo — Te han asignado el Ticket TK-20260212-002',title:'Ticket asignado a admin/soporte'},
                ticket_reply: {from:'<?php echo $this->pg_setting("company_name","PetsGo")." <".$this->pg_setting("company_from_email","notificaciones@petsgo.cl").">"; ?>',to:'maria@demo.cl',bcc:'<?php $tb=$this->pg_setting("tickets_bcc_email",""); echo $tb?$tb:"(no configurado)"; ?>',subject:'PetsGo — Respuesta en Ticket TK-20260212-001',title:'Respuesta en ticket'}
            };
            function loadPreview(type){
                $('#ep-loader').addClass('active');
                var m=meta[type]||meta.invoice;
                $('#ep-from').text(m.from);$('#ep-to').text(m.to);$('#ep-bcc').text(m.bcc);$('#ep-subject').text(m.subject);$('#ep-title').text(m.title);$('#ep-info').show();
                // Show/hide PDF download buttons based on tab type
                if(type==='invoice'){$('#ep-pdf-download').show();}else{$('#ep-pdf-download').hide();}
                if(type==='vendor_welcome'||type==='subscription_payment'){$('#ep-pdf-subscription').show();}else{$('#ep-pdf-subscription').hide();}
                PG.post('petsgo_preview_email',{email_type:type},function(r){
                    $('#ep-loader').removeClass('active');
                    if(!r.success)return;
                    var frame=$('#ep-frame')[0];
                    var doc=frame.contentDocument||frame.contentWindow.document;
                    doc.open();doc.write(r.data.html);doc.close();
                });
            }
            $('.ep-tab').on('click',function(){
                $('.ep-tab').removeClass('active');$(this).addClass('active');
                loadPreview($(this).data('type'));
            });
            loadPreview('invoice');

            // Save tickets BCC
            $('#ep-save-bcc').on('click',function(){
                var bcc = $('#ep-tickets-bcc').val().trim();
                PG.post('petsgo_save_settings',{
                    tickets_bcc_email: bcc,
                    // pass existing values so they remain unchanged
                    company_name: '<?php echo esc_js($this->pg_setting("company_name","PetsGo")); ?>',
                    company_tagline: '<?php echo esc_js($this->pg_setting("company_tagline","")); ?>',
                    company_rut: '<?php echo esc_js($this->pg_setting("company_rut","")); ?>',
                    company_address: '<?php echo esc_js($this->pg_setting("company_address","")); ?>',
                    company_phone: '<?php echo esc_js($this->pg_setting("company_phone","")); ?>',
                    company_email: '<?php echo esc_js($this->pg_setting("company_email","contacto@petsgo.cl")); ?>',
                    company_bcc_email: '<?php echo esc_js($this->pg_setting("company_bcc_email","contacto@petsgo.cl")); ?>',
                    company_from_email: '<?php echo esc_js($this->pg_setting("company_from_email","notificaciones@petsgo.cl")); ?>',
                    company_website: '<?php echo esc_js($this->pg_setting("company_website","")); ?>',
                    social_instagram: '<?php echo esc_js($this->pg_setting("social_instagram","")); ?>',
                    social_facebook: '<?php echo esc_js($this->pg_setting("social_facebook","")); ?>',
                    social_whatsapp: '<?php echo esc_js($this->pg_setting("social_whatsapp","")); ?>',
                    color_primary: '<?php echo esc_js($this->pg_setting("color_primary","#00A8E8")); ?>',
                    color_secondary: '<?php echo esc_js($this->pg_setting("color_secondary","#FFC400")); ?>',
                    color_dark: '<?php echo esc_js($this->pg_setting("color_dark","#2F3A40")); ?>',
                    color_success: '<?php echo esc_js($this->pg_setting("color_success","#28a745")); ?>',
                    color_danger: '<?php echo esc_js($this->pg_setting("color_danger","#dc3545")); ?>',
                    logo_id: '<?php echo esc_js($this->pg_setting("logo_id","")); ?>',
                    plan_annual_free_months: '<?php echo esc_js($this->pg_setting("plan_annual_free_months","2")); ?>',
                    free_shipping_min: '<?php echo esc_js($this->pg_setting("free_shipping_min","39990")); ?>'
                },function(r){
                    var m=$('#ep-bcc-msg');
                    if(r.success){
                        m.css({display:'inline-block',background:'#d4edda',color:'#155724'}).text('✅ Guardado');
                        $('#ep-bcc-current').html(bcc||'<span style="color:#ccc;">No configurado</span>');
                        // update ticket meta bcc values
                        var bccDisplay = bcc || '(no configurado)';
                        ['ticket_creator','ticket_admin','ticket_status','ticket_assigned','ticket_reply'].forEach(function(k){ if(meta[k]) meta[k].bcc = bccDisplay; });
                    } else {
                        m.css({display:'inline-block',background:'#f8d7da',color:'#721c24'}).text('Error al guardar');
                    }
                    setTimeout(function(){m.fadeOut();},3000);
                });
            });
        });
        </script>
        <?php
    }

    // ============================================================
    // ROLES
    // ============================================================
    public function register_roles() {
        add_role('petsgo_vendor', 'Tienda (Vendor)', ['read'=>true,'upload_files'=>true,'manage_inventory'=>true]);
        add_role('petsgo_rider', 'Delivery (Rider)', ['read'=>true,'upload_files'=>true,'manage_deliveries'=>true]);
        add_role('petsgo_support', 'Soporte', ['read'=>true,'moderate_comments'=>true,'manage_support_tickets'=>true]);
    }

    /**
     * Ensure subscription_start / subscription_end columns exist in vendors table.
     * Runs once, stores flag in options.
     */
    public function ensure_subscription_columns() {
        if (get_option('petsgo_vendor_sub_cols', false)) return;
        global $wpdb;
        $table = $wpdb->prefix . 'petsgo_vendors';
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (!in_array('subscription_start', $cols)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN subscription_start DATE DEFAULT NULL AFTER plan_id");
        }
        if (!in_array('subscription_end', $cols)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN subscription_end DATE DEFAULT NULL AFTER subscription_start");
        }
        update_option('petsgo_vendor_sub_cols', true);
    }

    /**
     * Ensure rider documents, delivery ratings tables, and delivery_method column exist.
     */
    public function ensure_rider_tables() {
        if (get_option('petsgo_rider_tables_v3', false)) return;
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_rider_documents (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            rider_id bigint(20) NOT NULL,
            doc_type varchar(50) NOT NULL,
            file_url text NOT NULL,
            file_name varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            admin_notes text DEFAULT NULL,
            reviewed_by bigint(20) DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            uploaded_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rider_id (rider_id)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_delivery_ratings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            rider_id bigint(20) NOT NULL,
            rater_type varchar(20) NOT NULL,
            rater_id bigint(20) NOT NULL,
            rating tinyint(1) NOT NULL DEFAULT 5,
            comment text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_rating (order_id, rater_type),
            KEY rider_id (rider_id),
            KEY order_id (order_id)
        ) {$charset}");

        // Add delivery_method and shipping_address to orders
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}petsgo_orders", 0);
        if (!in_array('delivery_method', $cols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_orders ADD COLUMN delivery_method varchar(20) DEFAULT 'delivery' AFTER delivery_fee");
        }
        if (!in_array('shipping_address', $cols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_orders ADD COLUMN shipping_address text DEFAULT NULL AFTER delivery_method");
        }

        // Add vehicle_type to user_profiles
        $pcols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}petsgo_user_profiles", 0);
        if (!in_array('vehicle_type', $pcols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_user_profiles ADD COLUMN vehicle_type varchar(30) DEFAULT NULL AFTER phone");
        }

        // Update rider role to include upload_files
        $role = get_role('petsgo_rider');
        if ($role && !$role->has_cap('upload_files')) {
            $role->add_cap('upload_files');
        }

        // Rider payouts table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_rider_payouts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            rider_id bigint(20) NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            total_deliveries int DEFAULT 0,
            total_earned decimal(10,2) DEFAULT 0,
            total_tips decimal(10,2) DEFAULT 0,
            total_deductions decimal(10,2) DEFAULT 0,
            net_amount decimal(10,2) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            paid_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rider_id (rider_id),
            KEY period (period_start, period_end)
        ) {$charset}");

        // Bank account columns in user_profiles
        $pcols2 = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}petsgo_user_profiles", 0);
        if (!in_array('bank_name', $pcols2)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_user_profiles ADD COLUMN bank_name varchar(100) DEFAULT NULL AFTER vehicle_type");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_user_profiles ADD COLUMN bank_account_type varchar(30) DEFAULT NULL AFTER bank_name");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_user_profiles ADD COLUMN bank_account_number varchar(50) DEFAULT NULL AFTER bank_account_type");
        }

        // Region / comuna columns in user_profiles
        if (!in_array('region', $pcols2)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_user_profiles ADD COLUMN region varchar(60) DEFAULT NULL AFTER bank_account_number");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_user_profiles ADD COLUMN comuna varchar(60) DEFAULT NULL AFTER region");
        }

        // v3: rider_earning per order + delivery_distance_km + acceptance tracking
        $ocols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}petsgo_orders", 0);
        if (!in_array('rider_earning', $ocols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_orders ADD COLUMN rider_earning decimal(10,2) DEFAULT 0 AFTER petsgo_commission");
        }
        if (!in_array('delivery_distance_km', $ocols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_orders ADD COLUMN delivery_distance_km decimal(6,2) DEFAULT NULL AFTER delivery_fee");
        }
        if (!in_array('store_fee', $ocols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_orders ADD COLUMN store_fee decimal(10,2) DEFAULT 0 AFTER petsgo_commission");
        }
        if (!in_array('petsgo_delivery_fee', $ocols)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_orders ADD COLUMN petsgo_delivery_fee decimal(10,2) DEFAULT 0 AFTER store_fee");
        }

        // Rider acceptance tracking table
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_rider_delivery_offers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            rider_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            offered_at timestamp DEFAULT CURRENT_TIMESTAMP,
            responded_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY order_rider (order_id, rider_id),
            KEY rider_id (rider_id)
        ) {$charset}");

        update_option('petsgo_rider_tables_v3', true);
    }

    // ============================================================
    // CATEGORÍAS — Tabla parametrizable
    // ============================================================
    public function ensure_category_table() {
        global $wpdb;
        if (get_option('petsgo_categories_v1')) return;
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_categories (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            emoji varchar(20) DEFAULT '📦',
            description varchar(255) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            sort_order int DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset}");

        // Seed default categories
        $defaults = [
            ['Perros','Perros','🐕','Todo para tu perro','',1],
            ['Gatos','Gatos','🐱','Todo para tu gato','',2],
            ['Alimento','Alimento','🍖','Seco y húmedo','',3],
            ['Snacks','Snacks','🦴','Premios y dental','',4],
            ['Farmacia','Farmacia','💊','Antiparasitarios y salud','',5],
            ['Accesorios','Accesorios','🎾','Juguetes y más','',6],
            ['Higiene','Higiene','🧴','Shampoo y aseo','',7],
            ['Camas','Camas','🛏️','Descanso ideal','',8],
            ['Paseo','Paseo','🦮','Correas y arneses','',9],
            ['Ropa','Ropa','🧥','Abrigos y disfraces','',10],
            ['Ofertas','Ofertas','🔥','Los mejores descuentos','',11],
            ['Nuevos','Nuevos','✨','Recién llegados','',12],
        ];
        foreach ($defaults as $c) {
            $wpdb->insert("{$wpdb->prefix}petsgo_categories", [
                'name' => $c[0], 'slug' => $c[1], 'emoji' => $c[2],
                'description' => $c[3], 'image_url' => $c[4], 'sort_order' => $c[5], 'is_active' => 1,
            ]);
        }
        update_option('petsgo_categories_v1', true);
    }

    // ============================================================
    // TICKETS / SOPORTE — Tablas
    // ============================================================
    public function ensure_ticket_tables() {
        global $wpdb;
        if (get_option('petsgo_tickets_v1')) return;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_tickets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_number varchar(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_name varchar(100) DEFAULT '',
            user_email varchar(100) DEFAULT '',
            user_role varchar(30) DEFAULT 'cliente',
            subject varchar(255) NOT NULL,
            description text NOT NULL,
            category varchar(50) DEFAULT 'general',
            priority varchar(20) DEFAULT 'media',
            status varchar(20) DEFAULT 'abierto',
            assigned_to bigint(20) DEFAULT NULL,
            assigned_name varchar(100) DEFAULT '',
            resolved_at datetime DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_number (ticket_number),
            KEY user_id (user_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}petsgo_ticket_replies (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_name varchar(100) DEFAULT '',
            user_role varchar(30) DEFAULT '',
            message text NOT NULL,
            is_internal tinyint(1) DEFAULT 0,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) {$charset}");

        update_option('petsgo_tickets_v1', true);
    }

    /**
     * Schedule daily cron for subscription renewal reminders.
     */
    public function schedule_renewal_cron() {
        if (!wp_next_scheduled('petsgo_check_renewals')) {
            wp_schedule_event(time(), 'daily', 'petsgo_check_renewals');
        }
    }

    /**
     * Process renewal reminders: send email to vendors whose subscription expires in 10, 5, 3, or 1 day(s).
     * Also stores alerts in wp_options for admin dashboard display.
     */
    public function process_renewal_reminders() {
        global $wpdb;
        $reminder_days = [10, 5, 3, 1];
        $alerts = [];

        foreach ($reminder_days as $days) {
            $target_date = date('Y-m-d', strtotime("+{$days} days"));
            $vendors = $wpdb->get_results($wpdb->prepare(
                "SELECT v.*, s.plan_name FROM {$wpdb->prefix}petsgo_vendors v LEFT JOIN {$wpdb->prefix}petsgo_subscriptions s ON v.plan_id=s.id WHERE v.subscription_end = %s AND v.status = 'active'",
                $target_date
            ));

            foreach ($vendors as $vendor) {
                // Build reminder email
                $html = $this->renewal_reminder_email_html($vendor, $days);
                $subject = "⏰ PetsGo — Tu suscripción vence en {$days} día" . ($days > 1 ? 's' : '');
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
                $from_name  = $this->pg_setting('company_name', 'PetsGo');
                $headers[]  = "From: {$from_name} <{$from_email}>";
                $bcc = $this->pg_setting('company_bcc_email', '');
                if ($bcc) $headers[] = "Bcc: {$bcc}";

                wp_mail($vendor->email, $subject, $html, $headers);

                $alerts[] = [
                    'vendor_id'   => $vendor->id,
                    'store_name'  => $vendor->store_name,
                    'plan_name'   => $vendor->plan_name ?? 'N/A',
                    'email'       => $vendor->email,
                    'days_left'   => $days,
                    'end_date'    => $vendor->subscription_end,
                    'sent_at'     => current_time('mysql'),
                ];

                $this->audit('renewal_reminder', 'vendor', $vendor->id, "Recordatorio {$days} día(s): {$vendor->store_name}");
            }
        }

        // Also check already expired (0 or negative days) — auto-deactivate
        $expired = $wpdb->get_results(
            "SELECT v.*, s.plan_name FROM {$wpdb->prefix}petsgo_vendors v LEFT JOIN {$wpdb->prefix}petsgo_subscriptions s ON v.plan_id=s.id WHERE v.subscription_end < CURDATE() AND v.subscription_end IS NOT NULL AND v.status = 'active'"
        );
        foreach ($expired as $vendor) {
            // Auto-deactivate expired vendors
            $wpdb->update("{$wpdb->prefix}petsgo_vendors", ['status' => 'inactive'], ['id' => $vendor->id]);
            $this->audit('vendor_auto_deactivated', 'vendor', $vendor->id, "Suscripción vencida: {$vendor->store_name} ({$vendor->subscription_end})");

            $diff = (int) round((strtotime($vendor->subscription_end) - time()) / 86400);
            $alerts[] = [
                'vendor_id'   => $vendor->id,
                'store_name'  => $vendor->store_name,
                'plan_name'   => $vendor->plan_name ?? 'N/A',
                'email'       => $vendor->email,
                'days_left'   => $diff,
                'end_date'    => $vendor->subscription_end,
                'sent_at'     => current_time('mysql'),
            ];
        }

        // Store alerts for admin dashboard
        if (!empty($alerts)) {
            $existing = get_option('petsgo_renewal_alerts', []);
            $existing = array_merge($existing, $alerts);
            // Keep only last 100 alerts
            $existing = array_slice($existing, -100);
            update_option('petsgo_renewal_alerts', $existing);
        }
    }

    /**
     * Build renewal reminder email HTML.
     */
    private function renewal_reminder_email_html($vendor, $days) {
        $urgency_color = $days <= 1 ? '#dc3545' : ($days <= 3 ? '#e65100' : '#FFC400');
        $urgency_bg    = $days <= 1 ? '#fce4ec' : ($days <= 3 ? '#fff3e0' : '#fff8e1');
        $urgency_icon  = $days <= 1 ? '🚨' : ($days <= 3 ? '⚠️' : '⏰');
        $plan_name     = $vendor->plan_name ?? 'N/A';
        $end_date      = date('d/m/Y', strtotime($vendor->subscription_end));

        $inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola equipo de <strong>' . esc_html($vendor->store_name) . '</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Te informamos que tu suscripci&oacute;n al plan <strong style="color:#00A8E8;">' . esc_html($plan_name) . '</strong> est&aacute; pr&oacute;xima a vencer.</p>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:' . $urgency_bg . ';border-left:4px solid ' . $urgency_color . ';border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 8px;font-size:18px;font-weight:700;color:' . $urgency_color . ';">' . $urgency_icon . ' Quedan ' . $days . ' d&iacute;a' . ($days > 1 ? 's' : '') . ' para renovar</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555;">
            <tr><td style="padding:4px 0;font-weight:600;width:140px;">Plan actual:</td><td style="padding:4px 0;"><strong>' . esc_html($plan_name) . '</strong></td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Fecha de vencimiento:</td><td style="padding:4px 0;font-weight:700;color:' . $urgency_color . ';">' . $end_date . '</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Tienda:</td><td style="padding:4px 0;">' . esc_html($vendor->store_name) . '</td></tr>
          </table>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0faff;border-radius:8px;padding:16px 18px;">
          <p style="margin:0;font-size:13px;color:#00718a;line-height:1.6;">
            &#128161; <strong>&iquest;Qu&eacute; pasa si no renuevo?</strong><br>
            Tu tienda seguir&aacute; visible pero no podr&aacute;s publicar nuevos productos ni recibir pedidos. Renueva y sigue vendiendo sin interrupciones.
          </p>
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(admin_url('admin.php?page=petsgo-dashboard')) . '" style="display:inline-block;background:#00A8E8;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Renovar Suscripci&oacute;n</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">' . esc_html($vendor->email) . '</span> como recordatorio de renovaci&oacute;n de suscripci&oacute;n en PetsGo.
      </p>';

        return $this->email_wrap($inner, $urgency_icon . ' Tu suscripción vence en ' . $days . ' día' . ($days > 1 ? 's' : ''));
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
        register_rest_route('petsgo/v1','/public-settings',['methods'=>'GET','callback'=>[$this,'api_get_public_settings'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/legal/(?P<slug>[a-z_-]+)',['methods'=>'GET','callback'=>[$this,'api_get_legal_page'],'permission_callback'=>'__return_true']);
        // Auth
        register_rest_route('petsgo/v1','/auth/login',['methods'=>'POST','callback'=>[$this,'api_login'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/register',['methods'=>'POST','callback'=>[$this,'api_register'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/register-rider',['methods'=>'POST','callback'=>[$this,'api_register_rider'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/verify-rider-email',['methods'=>'POST','callback'=>[$this,'api_verify_rider_email'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/resend-rider-verification',['methods'=>'POST','callback'=>[$this,'api_resend_rider_verification'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/forgot-password',['methods'=>'POST','callback'=>[$this,'api_forgot_password'],'permission_callback'=>'__return_true']);
        register_rest_route('petsgo/v1','/auth/reset-password',['methods'=>'POST','callback'=>[$this,'api_reset_password'],'permission_callback'=>'__return_true']);
        // Profile (logged in)
        register_rest_route('petsgo/v1','/profile',['methods'=>'GET','callback'=>[$this,'api_get_profile'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/profile',['methods'=>'PUT','callback'=>[$this,'api_update_profile'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/profile/change-password',['methods'=>'POST','callback'=>[$this,'api_change_password'],'permission_callback'=>function(){return is_user_logged_in();}]);
        // Pets (logged in)
        register_rest_route('petsgo/v1','/pets',['methods'=>'GET','callback'=>[$this,'api_get_pets'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/pets',['methods'=>'POST','callback'=>[$this,'api_add_pet'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/pets/(?P<id>\d+)',['methods'=>'PUT','callback'=>[$this,'api_update_pet'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/pets/(?P<id>\d+)',['methods'=>'DELETE','callback'=>[$this,'api_delete_pet'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/pets/upload-photo',['methods'=>'POST','callback'=>[$this,'api_upload_pet_photo'],'permission_callback'=>function(){return is_user_logged_in();}]);
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
        // Vendor Lead (public)
        register_rest_route('petsgo/v1','/vendor-lead',['methods'=>'POST','callback'=>[$this,'api_submit_vendor_lead'],'permission_callback'=>'__return_true']);
        // Rider
        register_rest_route('petsgo/v1','/rider/documents',['methods'=>'GET','callback'=>[$this,'api_get_rider_documents'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        register_rest_route('petsgo/v1','/rider/documents/upload',['methods'=>'POST','callback'=>[$this,'api_upload_rider_document'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        register_rest_route('petsgo/v1','/rider/status',['methods'=>'GET','callback'=>[$this,'api_get_rider_status'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        register_rest_route('petsgo/v1','/rider/deliveries',['methods'=>'GET','callback'=>[$this,'api_get_rider_deliveries'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        register_rest_route('petsgo/v1','/rider/deliveries/(?P<id>\d+)/status',['methods'=>'PUT','callback'=>[$this,'api_update_delivery_status'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        // Rider profile & earnings
        register_rest_route('petsgo/v1','/rider/profile',['methods'=>'GET','callback'=>[$this,'api_get_rider_profile'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        register_rest_route('petsgo/v1','/rider/profile',['methods'=>'PUT','callback'=>[$this,'api_update_rider_profile'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        register_rest_route('petsgo/v1','/rider/earnings',['methods'=>'GET','callback'=>[$this,'api_get_rider_earnings'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        // Delivery ratings
        register_rest_route('petsgo/v1','/orders/(?P<id>\d+)/rate-rider',['methods'=>'POST','callback'=>[$this,'api_rate_rider'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/rider/ratings',['methods'=>'GET','callback'=>[$this,'api_get_rider_ratings'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        // Delivery fee calculator (public)
        register_rest_route('petsgo/v1','/delivery/calculate-fee',['methods'=>'POST','callback'=>[$this,'api_calculate_delivery_fee'],'permission_callback'=>'__return_true']);
        // Rider policy (public — for registration page & info)
        register_rest_route('petsgo/v1','/rider/policy',['methods'=>'GET','callback'=>[$this,'api_get_rider_policy'],'permission_callback'=>'__return_true']);
        // Rider stats report (rider or admin)
        register_rest_route('petsgo/v1','/rider/stats',['methods'=>'GET','callback'=>[$this,'api_get_rider_stats'],'permission_callback'=>function(){$u=wp_get_current_user();return in_array('petsgo_rider',(array)$u->roles)||in_array('administrator',(array)$u->roles);}]);
        // Admin: view any rider's stats
        register_rest_route('petsgo/v1','/admin/rider/(?P<rider_id>\d+)/stats',['methods'=>'GET','callback'=>[$this,'api_admin_rider_stats'],'permission_callback'=>function(){return current_user_can('manage_options');}]);
        // Admin: list all riders with summary stats
        register_rest_route('petsgo/v1','/admin/riders',['methods'=>'GET','callback'=>[$this,'api_admin_list_riders'],'permission_callback'=>function(){return current_user_can('manage_options');}]);
        // Categories (public read)
        register_rest_route('petsgo/v1','/categories',['methods'=>'GET','callback'=>[$this,'api_get_categories'],'permission_callback'=>'__return_true']);
        // Tickets (logged in)
        register_rest_route('petsgo/v1','/tickets',['methods'=>'GET','callback'=>[$this,'api_get_tickets'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/tickets',['methods'=>'POST','callback'=>[$this,'api_create_ticket'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/tickets/(?P<id>\d+)',['methods'=>'GET','callback'=>[$this,'api_get_ticket_detail'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('petsgo/v1','/tickets/(?P<id>\d+)/reply',['methods'=>'POST','callback'=>[$this,'api_add_ticket_reply_rest'],'permission_callback'=>function(){return is_user_logged_in();}]);
    }

    // --- API Productos ---
    public function api_get_products($request) {
        global $wpdb;
        $sql="SELECT i.*,v.store_name,v.logo_url FROM {$wpdb->prefix}petsgo_inventory i JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE v.status='active'";$args=[];
        if($vid=$request->get_param('vendor_id')){$sql.=" AND i.vendor_id=%d";$args[]=$vid;}
        if($cat=$request->get_param('category')){if($cat!=='Todos'){$sql.=" AND i.category=%s";$args[]=$cat;}}
        if($s=$request->get_param('search')){$sql.=" AND i.product_name LIKE %s";$args[]='%'.$wpdb->esc_like($s).'%';}
        if($args) $sql=$wpdb->prepare($sql,...$args);
        $products=$wpdb->get_results($sql);
        return rest_ensure_response(['data'=>array_map(function($p){
            $disc=floatval($p->discount_percent??0);$active=false;
            if($disc>0){if(empty($p->discount_start)&&empty($p->discount_end)){$active=true;}else{$now=current_time('mysql');$active=(!$p->discount_start||$now>=$p->discount_start)&&(!$p->discount_end||$now<=$p->discount_end);}}
            return['id'=>(int)$p->id,'vendor_id'=>(int)$p->vendor_id,'product_name'=>$p->product_name,'price'=>(float)$p->price,'stock'=>(int)$p->stock,'category'=>$p->category,'store_name'=>$p->store_name,'logo_url'=>$p->logo_url,'rating'=>4.8,'image_url'=>$p->image_id?wp_get_attachment_url($p->image_id):null,'discount_percent'=>$disc,'discount_active'=>$active,'final_price'=>$active?round((float)$p->price*(1-$disc/100)):(float)$p->price];
        },$products)]);
    }
    public function api_get_product_detail($request) {
        global $wpdb;$id=$request->get_param('id');
        $p=$wpdb->get_row($wpdb->prepare("SELECT i.*,v.store_name,v.logo_url,v.status AS vendor_status FROM {$wpdb->prefix}petsgo_inventory i JOIN {$wpdb->prefix}petsgo_vendors v ON i.vendor_id=v.id WHERE i.id=%d",$id));
        if(!$p) return new WP_Error('not_found','Producto no encontrado',['status'=>404]);
        if($p->vendor_status!=='active') return new WP_Error('vendor_inactive','La tienda de este producto se encuentra inactiva.',['status'=>403]);
        $disc=floatval($p->discount_percent??0);$active=false;
        if($disc>0){if(empty($p->discount_start)&&empty($p->discount_end)){$active=true;}else{$now=current_time('mysql');$active=(!$p->discount_start||$now>=$p->discount_start)&&(!$p->discount_end||$now<=$p->discount_end);}}
        return rest_ensure_response(['id'=>(int)$p->id,'product_name'=>$p->product_name,'price'=>(float)$p->price,'stock'=>(int)$p->stock,'category'=>$p->category,'store_name'=>$p->store_name,'logo_url'=>$p->logo_url,'description'=>$p->description,'image_url'=>$p->image_id?wp_get_attachment_url($p->image_id):null,'discount_percent'=>$disc,'discount_active'=>$active,'final_price'=>$active?round((float)$p->price*(1-$disc/100)):(float)$p->price]);
    }
    // --- API Vendors ---
    public function api_get_vendors() { global $wpdb; return rest_ensure_response(['data'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE status='active'")]); }
    public function api_get_vendor_detail($request) { global $wpdb;$v=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d",$request->get_param('id')));if(!$v) return new WP_Error('not_found','No encontrada',['status'=>404]);return rest_ensure_response($v); }

    // --- API Public Settings (no auth needed) ---
    public function api_get_public_settings() {
        $faqs = get_option('petsgo_help_faqs', []);
        if (!is_array($faqs)) $faqs = json_decode($faqs, true) ?: [];
        return rest_ensure_response([
            'free_shipping_min'       => intval($this->pg_setting('free_shipping_min', 39990)),
            'plan_annual_free_months' => intval($this->pg_setting('plan_annual_free_months', 2)),
            'company_name'            => $this->pg_setting('company_name', 'PetsGo'),
            'company_tagline'         => $this->pg_setting('company_tagline', 'Marketplace de mascotas'),
            'company_address'         => $this->pg_setting('company_address', 'Santiago, Chile'),
            'company_phone'           => $this->pg_setting('company_phone', '+56 9 1234 5678'),
            'company_email'           => $this->pg_setting('company_email', 'contacto@petsgo.cl'),
            'company_website'         => $this->pg_setting('company_website', 'https://petsgo.cl'),
            'footer_description'      => $this->pg_setting('footer_description', 'El marketplace más grande de Chile para mascotas. Conectamos tiendas locales con despacho ultra rápido garantizado.'),
            'social_instagram'        => $this->pg_setting('social_instagram', ''),
            'social_facebook'         => $this->pg_setting('social_facebook', ''),
            'social_linkedin'         => $this->pg_setting('social_linkedin', ''),
            'social_twitter'          => $this->pg_setting('social_twitter', ''),
            'social_whatsapp'         => $this->pg_setting('social_whatsapp', ''),
            'faqs'                    => $faqs,
        ]);
    }

    // --- Contenido por defecto Centro de Ayuda ---
    private function centro_ayuda_default_content() {
        return '<h2>¿Cómo crear un ticket de soporte?</h2>' . "\n"
            . '<p>Si necesitas ayuda con algún pedido, producto, envío o cuenta, puedes crear un ticket de soporte según tu rol en la plataforma. Nuestro equipo revisa los tickets en un plazo máximo de <strong>24 horas hábiles</strong>.</p>' . "\n\n"
            . '<h3>🛒 Clientes</h3>' . "\n"
            . '<ol>' . "\n"
            . '<li>Inicia sesión en tu cuenta de PetsGo</li>' . "\n"
            . '<li>Ve a tu perfil y selecciona &quot;Soporte&quot;</li>' . "\n"
            . '<li>Haz clic en &quot;Nuevo Ticket&quot;</li>' . "\n"
            . '<li>Selecciona la categoría y prioridad</li>' . "\n"
            . '<li>Describe tu problema con detalle</li>' . "\n"
            . '<li>Envía y recibirás un correo de confirmación</li>' . "\n"
            . '</ol>' . "\n\n"
            . '<h3>🚚 Riders</h3>' . "\n"
            . '<ol>' . "\n"
            . '<li>Inicia sesión con tu cuenta de rider</li>' . "\n"
            . '<li>Ve a tu perfil y selecciona &quot;Soporte&quot;</li>' . "\n"
            . '<li>Haz clic en &quot;Nuevo Ticket&quot;</li>' . "\n"
            . '<li>Indica la categoría (entregas, pagos, cuenta, etc.)</li>' . "\n"
            . '<li>Describe el problema e incluye el N° de pedido si aplica</li>' . "\n"
            . '<li>Envía y haz seguimiento desde la misma sección</li>' . "\n"
            . '</ol>' . "\n\n"
            . '<h3>🏪 Tiendas</h3>' . "\n"
            . '<ol>' . "\n"
            . '<li>Ingresa al Portal de Administración de PetsGo</li>' . "\n"
            . '<li>En el menú lateral, busca &quot;🎫 Tickets&quot;</li>' . "\n"
            . '<li>Los tickets de tus clientes aparecerán aquí</li>' . "\n"
            . '<li>Para crear un ticket propio, usa el botón correspondiente</li>' . "\n"
            . '<li>El equipo de PetsGo revisará y responderá tu solicitud</li>' . "\n"
            . '<li>Recibirás notificaciones por correo sobre actualizaciones</li>' . "\n"
            . '</ol>' . "\n\n"
            . '<hr />' . "\n"
            . '<p>💡 <strong>Consejo:</strong> Para reclamos formales siempre recomendamos crear un ticket para mejor seguimiento. También puedes contactarnos por WhatsApp para consultas rápidas.</p>';
    }

    private function terminos_default_content() {
        return '<h2>1. Aceptación de los Términos</h2>' . "\n"
            . '<p>Al acceder y utilizar PetsGo, aceptas estar sujeto a estos términos y condiciones de uso. Si no estás de acuerdo con alguno de estos términos, no utilices nuestro marketplace.</p>' . "\n\n"
            . '<h2>2. Descripción del Servicio</h2>' . "\n"
            . '<p>PetsGo es un marketplace en línea que conecta tiendas de mascotas con clientes, facilitando la compra y despacho de productos para mascotas en Chile. PetsGo actúa como intermediario entre compradores y vendedores.</p>' . "\n\n"
            . '<h2>3. Registro y Cuentas</h2>' . "\n"
            . '<p>Para utilizar ciertas funcionalidades, debes crear una cuenta proporcionando información veraz y actualizada. Eres responsable de mantener la confidencialidad de tu contraseña y de todas las actividades realizadas bajo tu cuenta.</p>' . "\n\n"
            . '<h2>4. Compras y Pagos</h2>' . "\n"
            . '<p>Los precios de los productos son establecidos por cada tienda vendedora. PetsGo se reserva el derecho de aplicar cargos por servicio o despacho según las políticas vigentes. Todos los pagos se procesan de manera segura a través de los medios de pago habilitados.</p>' . "\n\n"
            . '<h2>5. Despacho</h2>' . "\n"
            . '<p>Los tiempos de despacho dependerán de la disponibilidad del producto, la ubicación del cliente y la cobertura del servicio. PetsGo hará su mejor esfuerzo para cumplir con los plazos estimados pero no garantiza tiempos exactos de entrega.</p>' . "\n\n"
            . '<h2>6. Devoluciones y Reembolsos</h2>' . "\n"
            . '<p>Los clientes pueden solicitar devoluciones dentro de los plazos establecidos por la ley de protección al consumidor chilena. Los productos deben devolverse en su estado original y con su embalaje.</p>' . "\n\n"
            . '<h2>7. Responsabilidad</h2>' . "\n"
            . '<p>PetsGo no se hace responsable por la calidad de los productos vendidos por las tiendas adheridas, actuando únicamente como plataforma intermediaria. Cada tienda es responsable de sus productos y servicios.</p>' . "\n\n"
            . '<h2>8. Propiedad Intelectual</h2>' . "\n"
            . '<p>Todo el contenido del marketplace, incluyendo logos, diseños y textos, son propiedad de PetsGo SpA y están protegidos por las leyes de propiedad intelectual de Chile.</p>' . "\n\n"
            . '<h2>9. Modificaciones</h2>' . "\n"
            . '<p>PetsGo se reserva el derecho de modificar estos términos en cualquier momento. Las modificaciones entrarán en vigencia al ser publicadas en el sitio web. El uso continuado del servicio después de cualquier modificación constituye aceptación de los nuevos términos.</p>' . "\n\n"
            . '<h2>10. Legislación Aplicable</h2>' . "\n"
            . '<p>Estos términos se rigen por las leyes de la República de Chile. Cualquier controversia será resuelta por los tribunales ordinarios de justicia con sede en Santiago de Chile.</p>';
    }

    private function privacidad_default_content() {
        return '<h2>1. Información que Recopilamos</h2>' . "\n"
            . '<p>Recopilamos la información personal que nos proporcionas al registrarte, como nombre, correo electrónico, teléfono, dirección de despacho y datos de pago. También recopilamos información de uso del sitio de manera automática.</p>' . "\n\n"
            . '<h2>2. Uso de la Información</h2>' . "\n"
            . '<p>Tu información personal se utiliza para:</p>' . "\n"
            . '<ul>' . "\n"
            . '<li>Procesar y gestionar tus pedidos</li>' . "\n"
            . '<li>Comunicarnos contigo sobre el estado de tus compras</li>' . "\n"
            . '<li>Mejorar nuestros servicios y la experiencia de usuario</li>' . "\n"
            . '<li>Enviar notificaciones relevantes sobre tu cuenta</li>' . "\n"
            . '<li>Cumplir con obligaciones legales y tributarias</li>' . "\n"
            . '</ul>' . "\n\n"
            . '<h2>3. Compartir Información</h2>' . "\n"
            . '<p>Compartimos tu información solo con:</p>' . "\n"
            . '<ul>' . "\n"
            . '<li>Tiendas vendedoras (nombre y dirección de despacho para cumplir tu pedido)</li>' . "\n"
            . '<li>Riders (dirección de despacho para la entrega)</li>' . "\n"
            . '<li>Procesadores de pago autorizados</li>' . "\n"
            . '<li>Autoridades competentes cuando sea requerido por ley</li>' . "\n"
            . '</ul>' . "\n\n"
            . '<h2>4. Protección de Datos</h2>' . "\n"
            . '<p>Implementamos medidas de seguridad técnicas y organizativas para proteger tu información personal contra acceso no autorizado, pérdida o destrucción. Utilizamos encriptación SSL para todas las comunicaciones y almacenamos los datos de manera segura.</p>' . "\n\n"
            . '<h2>5. Cookies</h2>' . "\n"
            . '<p>Utilizamos cookies y tecnologías similares para mejorar la experiencia de navegación, recordar tus preferencias y analizar el uso del sitio. Puedes configurar tu navegador para rechazar cookies, aunque esto podría afectar la funcionalidad del sitio.</p>' . "\n\n"
            . '<h2>6. Derechos del Usuario</h2>' . "\n"
            . '<p>De acuerdo con la Ley N° 19.628 sobre Protección de la Vida Privada de Chile, tienes derecho a:</p>' . "\n"
            . '<ul>' . "\n"
            . '<li>Acceder a tus datos personales</li>' . "\n"
            . '<li>Solicitar la rectificación de datos inexactos</li>' . "\n"
            . '<li>Solicitar la eliminación de tus datos</li>' . "\n"
            . '<li>Oponerte al tratamiento de tus datos para fines de marketing</li>' . "\n"
            . '</ul>' . "\n\n"
            . '<h2>7. Retención de Datos</h2>' . "\n"
            . '<p>Conservamos tu información personal mientras tu cuenta esté activa o según sea necesario para cumplir con obligaciones legales y tributarias. Puedes solicitar la eliminación de tu cuenta en cualquier momento.</p>' . "\n\n"
            . '<h2>8. Contacto</h2>' . "\n"
            . '<p>Para consultas sobre privacidad, puedes contactarnos a través de nuestro Centro de Ayuda o escribirnos a contacto@petsgo.cl.</p>' . "\n\n"
            . '<h2>9. Cambios en la Política</h2>' . "\n"
            . '<p>Podemos actualizar esta política periódicamente. Te notificaremos sobre cambios significativos a través de tu correo electrónico registrado o mediante un aviso en nuestro sitio web.</p>';
    }

    private function envios_default_content() {
        return '<h2>1. Cobertura de Despacho</h2>' . "\n"
            . '<p>PetsGo realiza despachos a domicilio dentro de las zonas de cobertura habilitadas. La disponibilidad de despacho depende de la ubicación de la tienda vendedora y la dirección del cliente.</p>' . "\n\n"
            . '<h2>2. Tipos de Despacho</h2>' . "\n"
            . '<p>Ofrecemos las siguientes modalidades de despacho:</p>' . "\n"
            . '<ul>' . "\n"
            . '<li><strong>Despacho Express:</strong> Entrega en el mismo día para pedidos realizados antes de las 14:00 hrs (sujeto a disponibilidad).</li>' . "\n"
            . '<li><strong>Despacho Estándar:</strong> Entrega en 1-3 días hábiles.</li>' . "\n"
            . '<li><strong>Retiro en Tienda:</strong> Puedes retirar tu pedido directamente en la tienda vendedora (cuando aplique).</li>' . "\n"
            . '</ul>' . "\n\n"
            . '<h2>3. Costos de Despacho</h2>' . "\n"
            . '<p>El costo de despacho se calcula según la distancia entre la tienda y tu dirección. Los pedidos que superen el monto mínimo establecido para despacho gratuito no tendrán cargo por envío. El monto mínimo puede variar y se muestra al momento de la compra.</p>' . "\n\n"
            . '<h2>4. Seguimiento del Pedido</h2>' . "\n"
            . '<p>Una vez despachado tu pedido, podrás hacer seguimiento en tiempo real desde la sección &quot;Mis Pedidos&quot; en tu cuenta. Recibirás notificaciones por correo electrónico sobre el estado de tu pedido.</p>' . "\n\n"
            . '<h2>5. Recepción del Pedido</h2>' . "\n"
            . '<p>Al momento de la entrega, el rider verificará que la dirección sea correcta. En caso de no encontrarse nadie en el domicilio, el rider intentará comunicarse contigo. Si no es posible realizar la entrega tras los intentos correspondientes, el pedido será devuelto a la tienda.</p>' . "\n\n"
            . '<h2>6. Problemas con el Despacho</h2>' . "\n"
            . '<p>Si tu pedido llega dañado, incompleto o con productos incorrectos, debes reportarlo dentro de las primeras 24 horas a través de un ticket de soporte. PetsGo gestionará la solución correspondiente (reenvío, reembolso o cambio) junto con la tienda vendedora.</p>' . "\n\n"
            . '<h2>7. Restricciones de Despacho</h2>' . "\n"
            . '<p>Algunos productos pueden tener restricciones de despacho según su naturaleza (productos frescos, refrigerados, de gran tamaño, etc.). Estas restricciones serán informadas en la ficha de cada producto.</p>' . "\n\n"
            . '<h2>8. Fuerza Mayor</h2>' . "\n"
            . '<p>PetsGo no será responsable por retrasos en la entrega ocasionados por eventos de fuerza mayor como desastres naturales, restricciones sanitarias, manifestaciones u otros eventos fuera de nuestro control.</p>';
    }

    private function terminos_rider_default_content() {
        return '<h2>1. Aceptación de Términos</h2>' . "\n"
            . '<p>Al registrarte como Rider en PetsGo, aceptas los presentes términos y condiciones que regulan tu participación como repartidor independiente en nuestra plataforma.</p>' . "\n\n"
            . '<h2>2. Relación con PetsGo</h2>' . "\n"
            . '<p>Tu participación como Rider NO constituye una relación laboral con PetsGo. Actúas como prestador de servicios independiente, responsable de tus propias obligaciones tributarias y previsionales.</p>' . "\n\n"
            . '<h2>3. Estructura de Comisiones y Pagos</h2>' . "\n"
            . '<p>Por cada entrega completada, el costo de despacho se distribuye de la siguiente forma:</p>' . "\n"
            . '<ul>' . "\n"
            . '<li><strong>Rider:</strong> Recibe el 88% del costo de despacho.</li>' . "\n"
            . '<li><strong>PetsGo:</strong> Retiene el 7% como comisión por uso de plataforma.</li>' . "\n"
            . '<li><strong>Tienda:</strong> Contribuye con el 5% como fee de gestión.</li>' . "\n"
            . '</ul>' . "\n"
            . '<p><em>Nota: Los porcentajes pueden ser modificados por PetsGo con previo aviso de al menos 15 días.</em></p>' . "\n\n"
            . '<h2>4. Ciclo de Pagos</h2>' . "\n"
            . '<p>Los pagos se procesan de forma semanal:</p>' . "\n"
            . '<ul>' . "\n"
            . '<li>El período de liquidación va de <strong>lunes a domingo</strong>.</li>' . "\n"
            . '<li>Los pagos se procesan el <strong>jueves</strong> de la semana siguiente.</li>' . "\n"
            . '<li>El depósito se realiza en la cuenta bancaria registrada en tu perfil.</li>' . "\n"
            . '<li>Es tu responsabilidad mantener actualizada tu información bancaria.</li>' . "\n"
            . '</ul>' . "\n\n"
            . '<h2>5. Tarifa de Despacho</h2>' . "\n"
            . '<p>La tarifa de despacho se calcula según la distancia entre la tienda y el cliente, con un rango entre <strong>$2.000 y $5.000 CLP</strong>. La fórmula considera una tarifa base más un monto por kilómetro recorrido.</p>' . "\n\n"
            . '<h2>6. Obligaciones del Rider</h2>' . "\n"
            . '<ul>' . "\n"
            . '<li>Mantener documentos vigentes y actualizados.</li>' . "\n"
            . '<li>Cumplir con las entregas aceptadas en tiempo y forma.</li>' . "\n"
            . '<li>Tratar con respeto a clientes, tiendas y personal de PetsGo.</li>' . "\n"
            . '<li>Mantener su medio de transporte en condiciones adecuadas.</li>' . "\n"
            . '<li>Informar inmediatamente cualquier incidente durante la entrega.</li>' . "\n"
            . '</ul>' . "\n\n"
            . '<h2>7. Tasa de Aceptación</h2>' . "\n"
            . '<p>PetsGo monitorea tu tasa de aceptación de entregas. Una tasa de aceptación inferior al 60% de forma sostenida puede resultar en restricciones temporales o desactivación de tu cuenta.</p>' . "\n\n"
            . '<h2>8. Valoraciones</h2>' . "\n"
            . '<p>Clientes y tiendas pueden valorar tu servicio de 1 a 5 estrellas. Mantener un rating promedio superior a 3.5 es requisito para continuar como Rider activo.</p>' . "\n\n"
            . '<h2>9. Desactivación de Cuenta</h2>' . "\n"
            . '<p>PetsGo se reserva el derecho de desactivar tu cuenta de Rider en caso de incumplimiento grave de estos términos, comportamiento inadecuado, fraude o valoraciones consistentemente negativas.</p>' . "\n\n"
            . '<h2>10. Modificaciones</h2>' . "\n"
            . '<p>PetsGo puede modificar estos términos en cualquier momento, notificando los cambios con al menos 15 días de anticipación. El uso continuado de la plataforma después de dicho período implica la aceptación de los nuevos términos.</p>';
    }

    private function help_faqs_defaults() {
        return [
            ['q' => '¿Cómo creo un ticket de soporte?', 'a' => 'Si eres cliente o rider, inicia sesión y ve a la sección "Soporte" en tu perfil. Si eres una tienda, crea el ticket desde el portal de administración.'],
            ['q' => '¿Cuánto tardan en responder mi ticket?', 'a' => 'Nuestro equipo revisa los tickets en un plazo máximo de 24 horas hábiles. Los tickets urgentes son atendidos con mayor prioridad.'],
            ['q' => '¿Puedo dar seguimiento a mi reclamo?', 'a' => 'Sí, puedes ver el estado de todos tus tickets y agregar mensajes adicionales desde la sección "Soporte" en tu perfil.'],
            ['q' => '¿Qué tipo de problemas puedo reportar?', 'a' => 'Puedes reportar problemas con pedidos, pagos, entregas, tu cuenta, productos y cualquier otra consulta relacionada con PetsGo.'],
            ['q' => '¿Puedo contactar directamente por WhatsApp?', 'a' => 'Sí, puedes contactarnos por WhatsApp para consultas rápidas. Sin embargo, para reclamos formales te recomendamos crear un ticket para mejor seguimiento.'],
        ];
    }

    // --- Legal / Contenido público ---
    public function api_get_legal_page($request) {
        $slug = sanitize_key($request->get_param('slug'));
        $allowed = ['centro-de-ayuda','terminos-y-condiciones','politica-de-privacidad','politica-de-envios','terminos-rider'];
        if (!in_array($slug, $allowed)) return new WP_Error('not_found','Página no encontrada',['status'=>404]);
        $opt_key = 'petsgo_legal_' . str_replace('-','_',$slug);
        $content = get_option($opt_key);
        if (false === $content) {
            $defaults_map = [
                'centro-de-ayuda'        => 'centro_ayuda_default_content',
                'terminos-y-condiciones' => 'terminos_default_content',
                'politica-de-privacidad' => 'privacidad_default_content',
                'politica-de-envios'     => 'envios_default_content',
                'terminos-rider'         => 'terminos_rider_default_content',
            ];
            if (isset($defaults_map[$slug])) {
                $content = $this->{$defaults_map[$slug]}();
                update_option($opt_key, $content);
            } else {
                $content = '';
            }
        }
        $titles = [
            'centro-de-ayuda'        => 'Centro de Ayuda',
            'terminos-y-condiciones' => 'Términos y Condiciones',
            'politica-de-privacidad' => 'Política de Privacidad',
            'politica-de-envios'     => 'Política de Envíos',
        ];
        return rest_ensure_response([
            'slug'    => $slug,
            'title'   => $titles[$slug] ?? $slug,
            'content' => $content,
        ]);
    }

    // --- API Plans ---
    public function api_get_plans() {
        global $wpdb;$rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_subscriptions ORDER BY monthly_price ASC");
        if(empty($rows)) return rest_ensure_response(['data'=>[]]);
        $free_months = intval($this->pg_setting('plan_annual_free_months', 2));
        $plans=array_map(function($r){
            $features=json_decode($r->features_json,true);
            if(!is_array($features))$features=[];
            return['id'=>(int)$r->id,'plan_name'=>$r->plan_name,'monthly_price'=>(float)$r->monthly_price,'features'=>$features,'is_featured'=>(int)($r->is_featured??0)];
        },$rows);
        return rest_ensure_response(['data'=>$plans,'annual_free_months'=>$free_months]);
    }
    // --- API Auth ---
    /**
     * Resolve API token → user ID (runs on every REST request)
     */
    public function resolve_api_token($user_id) {
        if ($user_id) return $user_id; // Already authenticated
        $token = '';
        // Method 1: Custom header (never stripped by Apache)
        $custom = isset($_SERVER['HTTP_X_PETSGO_TOKEN']) ? trim($_SERVER['HTTP_X_PETSGO_TOKEN']) : '';
        if ($custom && preg_match('/^petsgo_[a-f0-9]{64}$/', $custom)) {
            $token = $custom;
        }
        // Method 2: Standard Authorization header
        if (!$token) {
            $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
            if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (!$auth && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
            if (!$auth && function_exists('getallheaders')) {
                foreach (getallheaders() as $k => $v) {
                    if (strtolower($k) === 'authorization') { $auth = $v; break; }
                }
            }
            if (preg_match('/^Bearer\s+(petsgo_[a-f0-9]{64})$/i', $auth, $m)) {
                $token = $m[1];
            }
        }
        if (!$token) return $user_id;
        global $wpdb;
        $uid = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='petsgo_api_token' AND meta_value=%s LIMIT 1",
            $token
        ));
        if ($uid) {
            wp_set_current_user($uid);
            return (int) $uid;
        }
        return $user_id;
    }

    /**
     * Bypass WordPress cookie nonce check when our custom token auth is active.
     * wp_signon sets WP cookies; WP REST then demands a _wpnonce alongside them.
     * Since we use Bearer/X-PetsGo-Token auth, suppress that check.
     */
    public function bypass_cookie_nonce_for_token($result) {
        if (get_current_user_id() > 0 &&
            (isset($_SERVER['HTTP_X_PETSGO_TOKEN']) || !empty($_SERVER['HTTP_AUTHORIZATION']))) {
            return true;
        }
        return $result;
    }

    public function api_login($request) {
        $p=$request->get_json_params();
        $user=wp_authenticate($p['username']??'', $p['password']??'');
        if(is_wp_error($user)) return new WP_Error('auth_failed','Credenciales inválidas',['status'=>401]);
        wp_set_current_user($user->ID);
        // Generate persistent API token
        $existing_token = get_user_meta($user->ID, 'petsgo_api_token', true);
        if (!$existing_token) {
            $existing_token = 'petsgo_' . bin2hex(random_bytes(32));
            update_user_meta($user->ID, 'petsgo_api_token', $existing_token);
        }
        $role='customer';if(in_array('administrator',$user->roles))$role='admin';elseif(in_array('petsgo_vendor',$user->roles))$role='vendor';elseif(in_array('petsgo_rider',$user->roles))$role='rider';

        // Block unverified riders — require email verification first
        if ($role === 'rider') {
            $rider_status = get_user_meta($user->ID, 'petsgo_rider_status', true) ?: 'pending_email';
            if ($rider_status === 'rejected') {
                return new WP_Error('rider_rejected', 'Tu solicitud como Rider ha sido rechazada. Contacta a contacto@petsgo.cl para más información.', ['status' => 403]);
            }
        }

        // Block inactive vendors from logging in
        if ($role === 'vendor') {
            global $wpdb;
            $vendor_status = $wpdb->get_row($wpdb->prepare(
                "SELECT v.status, v.subscription_end, v.store_name FROM {$wpdb->prefix}petsgo_vendors v WHERE v.user_id = %d", $user->ID
            ));
            if ($vendor_status && $vendor_status->status !== 'active') {
                return new WP_Error('vendor_inactive', 'Tu tienda "' . ($vendor_status->store_name ?? '') . '" se encuentra inactiva. Debes renovar tu suscripción para acceder a la plataforma PetsGo. Contacta a contacto@petsgo.cl para más información.', ['status' => 403]);
            }
            if ($vendor_status && $vendor_status->subscription_end && $vendor_status->subscription_end < date('Y-m-d')) {
                $wpdb->update("{$wpdb->prefix}petsgo_vendors", ['status' => 'inactive'], ['user_id' => $user->ID]);
                return new WP_Error('subscription_expired', 'Tu suscripción venció el ' . date('d/m/Y', strtotime($vendor_status->subscription_end)) . '. Renueva tu plan para seguir vendiendo en PetsGo. Contacta a contacto@petsgo.cl.', ['status' => 403]);
            }
        }

        // Get profile data
        global $wpdb;
        $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d",$user->ID));
        $this->audit('login', 'login', $user->ID, $user->user_login . ' (' . $role . ')');
        $resp = [
            'id'=>$user->ID,'username'=>$user->user_login,'displayName'=>$user->display_name,
            'email'=>$user->user_email,'role'=>$role,
            'firstName'=>$profile->first_name??'','lastName'=>$profile->last_name??'',
            'phone'=>$profile->phone??'','avatarUrl'=>$profile->avatar_url??'',
        ];
        if ($role === 'rider') {
            $resp['rider_status'] = get_user_meta($user->ID, 'petsgo_rider_status', true) ?: 'pending_email';
            $resp['vehicle_type'] = get_user_meta($user->ID, 'petsgo_vehicle', true) ?: '';
        }
        return rest_ensure_response(['token'=>$existing_token,'user'=>$resp]);
    }

    public function api_register($request) {
        global $wpdb;
        $p = $request->get_json_params();

        // Rate limiting
        $rl = $this->check_rate_limit('register');
        if ($rl) return $rl;

        // Validate required fields
        $errors = [];
        $first_name = sanitize_text_field($p['first_name'] ?? '');
        $last_name  = sanitize_text_field($p['last_name'] ?? '');
        $email      = sanitize_email($p['email'] ?? '');
        $password   = $p['password'] ?? '';
        $phone      = sanitize_text_field($p['phone'] ?? '');
        $id_type    = sanitize_text_field($p['id_type'] ?? 'rut');
        $id_number  = sanitize_text_field($p['id_number'] ?? '');
        $birth_date = sanitize_text_field($p['birth_date'] ?? '');
        $region     = sanitize_text_field($p['region'] ?? '');
        $comuna     = sanitize_text_field($p['comuna'] ?? '');
        $accept_terms = !empty($p['accept_terms']);

        // SQL injection check
        $sql_err = self::check_form_sql_injection($p);
        if ($sql_err) return new WP_Error('security_error', $sql_err, ['status' => 400]);

        // Name sanitization
        $first_name = self::sanitize_name($first_name);
        $last_name  = self::sanitize_name($last_name);

        if (!$first_name) $errors[] = 'Nombre es obligatorio';
        elseif (!self::validate_name($first_name)) $errors[] = 'Nombre solo puede contener letras';
        if (!$last_name) $errors[] = 'Apellido es obligatorio';
        elseif (!self::validate_name($last_name)) $errors[] = 'Apellido solo puede contener letras';
        if (!$accept_terms) $errors[] = 'Debes aceptar los términos y condiciones del rider';
        if (!$email || !is_email($email)) $errors[] = 'Email válido es obligatorio';
        if (email_exists($email)) $errors[] = 'Este email ya está registrado';

        // Password strength
        $pass_errors = self::validate_password_strength($password);
        $errors = array_merge($errors, $pass_errors);

        // Phone validation
        if (!$phone) {
            $errors[] = 'Teléfono es obligatorio';
        } elseif (!self::validate_chilean_phone($phone)) {
            $errors[] = 'Teléfono chileno inválido (debe ser +569XXXXXXXX)';
        }

        // ID validation
        if (!$id_number) {
            $errors[] = 'Documento de identidad es obligatorio';
        } elseif ($id_type === 'rut' && !self::validate_rut($id_number)) {
            $errors[] = 'RUT inválido';
        } elseif (in_array($id_type, ['dni','passport']) && strlen($id_number) < 5) {
            $errors[] = 'Número de documento demasiado corto';
        }

        // Region / Comuna
        if (!$region) $errors[] = 'Región es obligatoria';
        if (!$comuna) $errors[] = 'Comuna es obligatoria';

        if ($birth_date) {
            $bd = strtotime($birth_date);
            if (!$bd || $bd > time()) $errors[] = 'Fecha de nacimiento inválida';
        }

        if ($errors) return new WP_Error('validation_error', implode('. ', $errors), ['status' => 400]);

        // Generate username from email
        $username = strtolower(explode('@', $email)[0]);
        $base_username = $username;
        $i = 1;
        while (username_exists($username)) { $username = $base_username . $i; $i++; }

        $uid = wp_create_user($username, $password, $email);
        if (is_wp_error($uid)) return new WP_Error('register_failed', $uid->get_error_message(), ['status' => 400]);

        $display_name = $first_name . ' ' . $last_name;
        wp_update_user(['ID' => $uid, 'display_name' => $display_name, 'first_name' => $first_name, 'last_name' => $last_name]);

        // Save extended profile
        $wpdb->insert("{$wpdb->prefix}petsgo_user_profiles", [
            'user_id'    => $uid,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'id_type'    => $id_type,
            'id_number'  => $id_type === 'rut' ? self::format_rut($id_number) : $id_number,
            'birth_date' => $birth_date ?: null,
            'phone'      => self::normalize_phone($phone),
            'region'     => $region ?: null,
            'comuna'     => $comuna ?: null,
        ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s']);

        $this->audit('register', 'user', $uid, $display_name . ' (customer)');

        // Welcome email
        $welcome_inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>' . esc_html($first_name) . '</strong>! 🎉</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Te damos la bienvenida a <strong>PetsGo</strong>, el marketplace pensado para quienes aman a sus mascotas.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0faff;border-radius:10px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#333;font-weight:700;">🐾 Con tu cuenta puedes:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>✅ Explorar cientos de productos para tu mascota</td></tr>
            <tr><td>✅ Comprar de las mejores tiendas de Chile</td></tr>
            <tr><td>✅ Recibir delivery en tu puerta</td></tr>
            <tr><td>✅ Registrar a tus mascotas y llevar su perfil</td></tr>
          </table>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr><td align="center">
          <a href="' . esc_url(home_url()) . '" style="display:inline-block;background:#00A8E8;color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">Ir a PetsGo</a>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">' . esc_html($email) . '</span> porque creaste una cuenta en PetsGo.
      </p>';
        $welcome_html = $this->email_wrap($welcome_inner, '¡Bienvenido/a a PetsGo, ' . $first_name . '!');
        $company = $this->pg_setting('company_name', 'PetsGo');
        $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
        $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$company} <{$from_email}>"];
        @wp_mail($email, "¡Bienvenido/a a {$company}! 🐾", $welcome_html, $headers);

        return rest_ensure_response(['message' => 'Cuenta creada exitosamente', 'username' => $username]);
    }

    public function api_register_rider($request) {
        global $wpdb;
        $p = $request->get_json_params();

        // Rate limiting
        $rl = $this->check_rate_limit('register_rider');
        if ($rl) return $rl;

        $errors = [];
        $first_name = sanitize_text_field($p['first_name'] ?? '');
        $last_name  = sanitize_text_field($p['last_name'] ?? '');
        $email      = sanitize_email($p['email'] ?? '');
        $password   = $p['password'] ?? '';
        $phone      = sanitize_text_field($p['phone'] ?? '');
        $id_type    = sanitize_text_field($p['id_type'] ?? 'rut');
        $id_number  = sanitize_text_field($p['id_number'] ?? '');
        $birth_date = sanitize_text_field($p['birth_date'] ?? '');
        $vehicle    = sanitize_text_field($p['vehicle'] ?? '');
        $vehicle_type = sanitize_text_field($p['vehicle_type'] ?? '');
        $region     = sanitize_text_field($p['region'] ?? '');
        $comuna     = sanitize_text_field($p['comuna'] ?? '');

        // SQL injection check
        $sql_err = self::check_form_sql_injection($p);
        if ($sql_err) return new WP_Error('security_error', $sql_err, ['status' => 400]);

        // Name sanitization
        $first_name = self::sanitize_name($first_name);
        $last_name  = self::sanitize_name($last_name);

        if (!$first_name) $errors[] = 'Nombre es obligatorio';
        elseif (!self::validate_name($first_name)) $errors[] = 'Nombre solo puede contener letras';
        if (!$last_name) $errors[] = 'Apellido es obligatorio';
        elseif (!self::validate_name($last_name)) $errors[] = 'Apellido solo puede contener letras';
        if (!$email || !is_email($email)) $errors[] = 'Email válido es obligatorio';
        if (email_exists($email)) $errors[] = 'Este email ya está registrado';

        $pass_errors = self::validate_password_strength($password);
        $errors = array_merge($errors, $pass_errors);

        if (!$phone) {
            $errors[] = 'Teléfono es obligatorio';
        } elseif (!self::validate_chilean_phone($phone)) {
            $errors[] = 'Teléfono chileno inválido (debe ser +569XXXXXXXX)';
        }

        if (!$id_number) {
            $errors[] = 'Documento de identidad es obligatorio';
        } elseif ($id_type === 'rut' && !self::validate_rut($id_number)) {
            $errors[] = 'RUT inválido';
        } elseif (in_array($id_type, ['dni','passport']) && strlen($id_number) < 5) {
            $errors[] = 'Número de documento demasiado corto';
        }

        // Region / Comuna
        if (!$region) $errors[] = 'Región es obligatoria';
        if (!$comuna) $errors[] = 'Comuna es obligatoria';

        if ($errors) return new WP_Error('validation_error', implode('. ', $errors), ['status' => 400]);

        $username = strtolower(explode('@', $email)[0]);
        $base_username = $username; $i = 1;
        while (username_exists($username)) { $username = $base_username . $i; $i++; }

        $uid = wp_create_user($username, $password, $email);
        if (is_wp_error($uid)) return new WP_Error('register_failed', $uid->get_error_message(), ['status' => 400]);

        $display_name = $first_name . ' ' . $last_name;
        wp_update_user(['ID' => $uid, 'display_name' => $display_name, 'first_name' => $first_name, 'last_name' => $last_name]);
        $user = get_userdata($uid); $user->set_role('petsgo_rider');

        $wpdb->insert("{$wpdb->prefix}petsgo_user_profiles", [
            'user_id'    => $uid,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'id_type'    => $id_type,
            'id_number'  => $id_type === 'rut' ? self::format_rut($id_number) : $id_number,
            'birth_date' => $birth_date ?: null,
            'phone'      => self::normalize_phone($phone),
            'region'     => $region ?: null,
            'comuna'     => $comuna ?: null,
        ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s']);

        if ($vehicle) update_user_meta($uid, 'petsgo_vehicle', $vehicle);
        if ($vehicle_type) {
            update_user_meta($uid, 'petsgo_vehicle', $vehicle_type);
            $wpdb->update("{$wpdb->prefix}petsgo_user_profiles", ['vehicle_type' => $vehicle_type], ['user_id' => $uid]);
        }
        // Step 1: Set status to pending_email (must verify email before uploading docs)
        update_user_meta($uid, 'petsgo_rider_status', 'pending_email');
        if ($accept_terms) {
            update_user_meta($uid, 'petsgo_rider_terms_accepted', current_time('mysql'));
        }

        // Generate email verification token
        $verify_token = bin2hex(random_bytes(32));
        update_user_meta($uid, 'petsgo_rider_verify_token', $verify_token);
        update_user_meta($uid, 'petsgo_rider_verify_expiry', date('Y-m-d H:i:s', strtotime('+48 hours')));

        $this->audit('register_rider', 'user', $uid, $display_name . ' (rider)');

        // Verification email with code
        $verify_code = strtoupper(substr($verify_token, 0, 6));
        update_user_meta($uid, 'petsgo_rider_verify_code', $verify_code);

        $rider_inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>' . esc_html($first_name) . '</strong>! 🚴</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Gracias por registrarte como <strong>Rider en PetsGo</strong>. Para continuar con tu solicitud, verifica tu correo electr' . chr(243) . 'nico.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td align="center" style="background-color:#fff8ed;border-radius:12px;padding:30px 24px;">
          <p style="margin:0 0 8px;font-size:14px;color:#555;">Tu c' . chr(243) . 'digo de verificaci' . chr(243) . 'n es:</p>
          <p style="margin:0;font-size:36px;font-weight:900;letter-spacing:6px;color:#F59E0B;font-family:monospace;">' . $verify_code . '</p>
          <p style="margin:12px 0 0;font-size:12px;color:#999;">V' . chr(225) . 'lido por 48 horas</p>
        </td></tr>
      </table>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0f9ff;border-left:4px solid #00A8E8;border-radius:8px;padding:20px 24px;">
          <p style="margin:0 0 12px;font-size:14px;color:#333;font-weight:700;">📋 Pasos del registro:</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-size:13px;color:#555;line-height:2;">
            <tr><td>✅ Paso 1: Datos b' . chr(225) . 'sicos registrados</td></tr>
            <tr><td>👉 <strong>Paso 2: Verifica tu email e ingresa tus documentos</strong></td></tr>
            <tr><td>⏳ Paso 3: Revisi' . chr(243) . 'n y aprobaci' . chr(243) . 'n del admin</td></tr>
          </table>
        </td></tr>
      </table>
      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">' . esc_html($email) . '</span> porque te registraste como Rider en PetsGo.
      </p>';
        $rider_html = $this->email_wrap($rider_inner, 'Verifica tu email para continuar, ' . $first_name . ' 🚴');
        $company = $this->pg_setting('company_name', 'PetsGo');
        $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
        $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$company} <{$from_email}>"];
        @wp_mail($email, "Verifica tu email - {$company} Rider 🚴", $rider_html, $headers);

        return rest_ensure_response([
            'message' => 'Registro exitoso. Revisa tu correo para verificar tu email y continuar con el proceso.',
            'username' => $username,
            'step' => 1,
            'next_step' => 'verify_email',
        ]);
    }

    /** Step 2a: Verify rider email with code */
    public function api_verify_rider_email($request) {
        $p = $request->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        $code  = strtoupper(sanitize_text_field($p['code'] ?? ''));

        if (!$email || !$code) return new WP_Error('missing', 'Email y codigo son obligatorios', ['status' => 400]);

        $user = get_user_by('email', $email);
        if (!$user) return new WP_Error('not_found', 'Usuario no encontrado', ['status' => 404]);

        $stored_code   = get_user_meta($user->ID, 'petsgo_rider_verify_code', true);
        $stored_expiry = get_user_meta($user->ID, 'petsgo_rider_verify_expiry', true);
        $current_status = get_user_meta($user->ID, 'petsgo_rider_status', true);

        if ($current_status !== 'pending_email') {
            return rest_ensure_response(['message' => 'Tu email ya fue verificado previamente.', 'already_verified' => true]);
        }

        if (!$stored_code || $stored_code !== $code) {
            return new WP_Error('invalid_code', 'Codigo de verificacion invalido', ['status' => 400]);
        }

        if ($stored_expiry && strtotime($stored_expiry) < time()) {
            return new WP_Error('expired', 'El codigo ha expirado. Solicita uno nuevo.', ['status' => 400]);
        }

        // Email verified -> advance to step 2 (upload docs)
        update_user_meta($user->ID, 'petsgo_rider_status', 'pending_docs');
        delete_user_meta($user->ID, 'petsgo_rider_verify_token');
        delete_user_meta($user->ID, 'petsgo_rider_verify_code');
        delete_user_meta($user->ID, 'petsgo_rider_verify_expiry');
        $this->audit('rider_email_verified', 'user', $user->ID, $user->display_name);

        return rest_ensure_response([
            'message' => 'Email verificado exitosamente. Ahora puedes subir tus documentos.',
            'step' => 2,
            'next_step' => 'upload_documents',
        ]);
    }

    /** Resend rider verification email */
    public function api_resend_rider_verification($request) {
        $p = $request->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        if (!$email) return new WP_Error('missing', 'Email es obligatorio', ['status' => 400]);

        $user = get_user_by('email', $email);
        if (!$user) return new WP_Error('not_found', 'Usuario no encontrado', ['status' => 404]);

        $current_status = get_user_meta($user->ID, 'petsgo_rider_status', true);
        if ($current_status !== 'pending_email') {
            return rest_ensure_response(['message' => 'Tu email ya fue verificado.']);
        }

        $verify_code = strtoupper(substr(bin2hex(random_bytes(32)), 0, 6));
        update_user_meta($user->ID, 'petsgo_rider_verify_code', $verify_code);
        update_user_meta($user->ID, 'petsgo_rider_verify_expiry', date('Y-m-d H:i:s', strtotime('+48 hours')));

        $first_name = get_user_meta($user->ID, 'first_name', true) ?: $user->display_name;
        $inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">¡Hola <strong>' . esc_html($first_name) . '</strong>! 🚴</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Aqui tienes tu nuevo codigo de verificacion:</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td align="center" style="background-color:#fff8ed;border-radius:12px;padding:30px 24px;">
          <p style="margin:0;font-size:36px;font-weight:900;letter-spacing:6px;color:#F59E0B;font-family:monospace;">' . $verify_code . '</p>
          <p style="margin:12px 0 0;font-size:12px;color:#999;">Valido por 48 horas</p>
        </td></tr>
      </table>';
        $html = $this->email_wrap($inner, 'Nuevo codigo de verificacion');
        $company = $this->pg_setting('company_name', 'PetsGo');
        $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
        $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$company} <{$from_email}>"];
        @wp_mail($email, "Nuevo codigo de verificacion - {$company} 🚴", $html, $headers);

        return rest_ensure_response(['message' => 'Se ha enviado un nuevo codigo de verificacion a tu email.']);
    }

    // --- Rider Documents API ---
    public function api_get_rider_documents() {
        global $wpdb;
        $uid = get_current_user_id();
        $docs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_rider_documents WHERE rider_id=%d ORDER BY uploaded_at DESC", $uid
        ));
        $vehicle_type = get_user_meta($uid, 'petsgo_vehicle', true) ?: '';
        $rider_status = get_user_meta($uid, 'petsgo_rider_status', true) ?: 'pending';
        return rest_ensure_response(['documents' => $docs, 'vehicle_type' => $vehicle_type, 'rider_status' => $rider_status]);
    }

    public function api_upload_rider_document($request) {
        global $wpdb;
        $uid = get_current_user_id();

        // Must have verified email first
        $rider_status = get_user_meta($uid, 'petsgo_rider_status', true) ?: 'pending_email';
        if ($rider_status === 'pending_email') {
            return new WP_Error('email_not_verified', 'Debes verificar tu email antes de subir documentos', ['status' => 403]);
        }
        if ($rider_status === 'approved') {
            return new WP_Error('already_approved', 'Tu cuenta ya esta aprobada', ['status' => 400]);
        }

        if (empty($_FILES['document'])) {
            return new WP_Error('no_file', 'No se recibió ningún archivo', ['status' => 400]);
        }
        $doc_type = sanitize_text_field($_POST['doc_type'] ?? '');
        if (!in_array($doc_type, ['license', 'vehicle_registration', 'id_card', 'selfie', 'vehicle_photo_1', 'vehicle_photo_2', 'vehicle_photo_3'])) {
            return new WP_Error('invalid_type', 'Tipo de documento inválido', ['status' => 400]);
        }

        // Save vehicle type if provided
        $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
        if ($vehicle_type && in_array($vehicle_type, ['moto', 'auto', 'bicicleta', 'scooter', 'a_pie'])) {
            update_user_meta($uid, 'petsgo_vehicle', $vehicle_type);
            $wpdb->update("{$wpdb->prefix}petsgo_user_profiles", ['vehicle_type' => $vehicle_type], ['user_id' => $uid]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($_FILES['document']['type'], $allowed)) {
            return new WP_Error('invalid_file', 'Solo se permiten archivos JPG, PNG, WebP o PDF', ['status' => 400]);
        }
        if ($_FILES['document']['size'] > 5 * 1024 * 1024) {
            return new WP_Error('too_large', 'El archivo no puede superar 5 MB', ['status' => 400]);
        }

        $upload = wp_handle_upload($_FILES['document'], ['test_form' => false]);
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error'], ['status' => 500]);
        }

        // Delete previous doc of same type if exists
        $wpdb->delete("{$wpdb->prefix}petsgo_rider_documents", ['rider_id' => $uid, 'doc_type' => $doc_type]);

        $wpdb->insert("{$wpdb->prefix}petsgo_rider_documents", [
            'rider_id'  => $uid,
            'doc_type'  => $doc_type,
            'file_url'  => $upload['url'],
            'file_name' => basename($upload['file']),
            'status'    => 'pending',
        ], ['%d', '%s', '%s', '%s', '%s']);

        $this->audit('rider_doc_upload', 'rider_document', $wpdb->insert_id, "Tipo: {$doc_type}");

        // Check if all required docs uploaded -> advance to pending_review
        $current_rs = get_user_meta($uid, 'petsgo_rider_status', true);
        if ($current_rs === 'pending_docs') {
            $vtype = get_user_meta($uid, 'petsgo_vehicle', true) ?: '';
            $needs_motor = in_array($vtype, ['moto', 'auto', 'scooter']);
            $uploaded_types = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT doc_type FROM {$wpdb->prefix}petsgo_rider_documents WHERE rider_id=%d", $uid
            ));
            $has_selfie = in_array('selfie', $uploaded_types);
            $has_id = in_array('id_card', $uploaded_types);
            $has_license = in_array('license', $uploaded_types);
            $has_registration = in_array('vehicle_registration', $uploaded_types);
            $is_a_pie = ($vtype === 'a_pie');
            $has_vehicle_photos = in_array('vehicle_photo_1', $uploaded_types)
                && in_array('vehicle_photo_2', $uploaded_types)
                && in_array('vehicle_photo_3', $uploaded_types);

            $all_uploaded = $has_selfie && $has_id;
            if (!$is_a_pie) {
                $all_uploaded = $all_uploaded && $has_vehicle_photos;
            }
            if ($needs_motor) {
                $all_uploaded = $all_uploaded && $has_license && $has_registration;
            }
            if ($all_uploaded) {
                update_user_meta($uid, 'petsgo_rider_status', 'pending_review');
                $this->audit('rider_docs_complete', 'user', $uid, 'Todos los documentos subidos, pendiente de revision');
            }
        }

        return rest_ensure_response(['message' => 'Documento subido exitosamente', 'id' => $wpdb->insert_id]);
    }

    public function api_get_rider_status() {
        $uid = get_current_user_id();
        global $wpdb;
        $status = get_user_meta($uid, 'petsgo_rider_status', true) ?: 'pending_email';
        $vehicle = get_user_meta($uid, 'petsgo_vehicle', true) ?: '';
        $email_verified = !in_array($status, ['pending_email']);
        $docs = $wpdb->get_results($wpdb->prepare(
            "SELECT doc_type, status, admin_notes FROM {$wpdb->prefix}petsgo_rider_documents WHERE rider_id=%d", $uid
        ));
        $pending_docs = array_filter($docs, fn($d) => $d->status === 'pending');
        $rejected_docs = array_filter($docs, fn($d) => $d->status === 'rejected');

        // Required docs check
        $needs_motor = in_array($vehicle, ['moto', 'auto', 'scooter']);
        $is_a_pie = ($vehicle === 'a_pie');
        $required = ['selfie', 'id_card'];
        if (!$is_a_pie) {
            $required[] = 'vehicle_photo_1';
            $required[] = 'vehicle_photo_2';
            $required[] = 'vehicle_photo_3';
        }
        if ($needs_motor) { $required[] = 'license'; $required[] = 'vehicle_registration'; }
        $uploaded_types = array_map(fn($d) => $d->doc_type, $docs);
        $missing_docs = array_values(array_diff($required, $uploaded_types));

        // Get rider average rating
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $uid
        ));

        // Profile data for document matching
        $profile = $wpdb->get_row($wpdb->prepare("SELECT id_type, id_number FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $uid));

        return rest_ensure_response([
            'rider_status'    => $status,
            'email_verified'  => $email_verified,
            'vehicle_type'    => $vehicle,
            'documents'       => $docs,
            'required_docs'   => $required,
            'missing_docs'    => $missing_docs,
            'pending_count'   => count($pending_docs),
            'rejected_count'  => count(array_values($rejected_docs)),
            'average_rating'  => $avg ? round(floatval($avg), 1) : null,
            'id_type'         => $profile->id_type ?? '',
            'id_number'       => $profile->id_number ?? '',
        ]);
    }

    // --- Rider Profile (full) ---
    public function api_get_rider_profile() {
        global $wpdb;
        $uid = get_current_user_id();
        $user = get_userdata($uid);
        $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $uid));
        $status = get_user_meta($uid, 'petsgo_rider_status', true) ?: 'pending_email';
        $vehicle = get_user_meta($uid, 'petsgo_vehicle', true) ?: '';

        // Stats
        $total_deliveries = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", $uid
        ));
        $total_earned = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", $uid
        ));
        $avg_rating = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $uid
        ));
        $total_ratings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $uid
        ));
        // Current week earnings
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_earned = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered' AND created_at >= %s", $uid, $week_start
        ));
        $week_deliveries = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered' AND created_at >= %s", $uid, $week_start
        ));
        // Pending balance (unpaid)
        $last_payout_end = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(period_end) FROM {$wpdb->prefix}petsgo_rider_payouts WHERE rider_id=%d AND status='paid'", $uid
        ));
        $pending_from = $last_payout_end ?: '2020-01-01';
        $pending_balance = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered' AND created_at > %s", $uid, $pending_from
        ));

        // Acceptance rate
        $total_offers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_delivery_offers WHERE rider_id=%d", $uid
        ));
        $accepted_offers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_delivery_offers WHERE rider_id=%d AND status='accepted'", $uid
        ));
        $acceptance_rate = $total_offers > 0 ? round(($accepted_offers / $total_offers) * 100, 1) : 100;

        // Terms acceptance
        $terms_accepted = get_user_meta($uid, 'petsgo_rider_terms_accepted', true);

        $registered_at = $user->user_registered;

        return rest_ensure_response([
            'id'               => $uid,
            'email'            => $user->user_email,
            'displayName'      => $user->display_name,
            'firstName'        => $profile->first_name ?? '',
            'lastName'         => $profile->last_name ?? '',
            'phone'            => $profile->phone ?? '',
            'idType'           => $profile->id_type ?? '',
            'idNumber'         => $profile->id_number ?? '',
            'birthDate'        => $profile->birth_date ?? '',
            'vehicleType'      => $vehicle,
            'riderStatus'      => $status,
            'registeredAt'     => $registered_at,
            'avatarUrl'        => $profile->avatar_url ?? '',
            // Bank
            'bankName'         => $profile->bank_name ?? '',
            'bankAccountType'  => $profile->bank_account_type ?? '',
            'bankAccountNumber'=> $profile->bank_account_number ?? '',
            // Location
            'region'           => $profile->region ?? '',
            'comuna'           => $profile->comuna ?? '',
            // Stats
            'totalDeliveries'  => $total_deliveries,
            'totalEarned'      => $total_earned,
            'averageRating'    => $avg_rating ? round(floatval($avg_rating), 1) : null,
            'totalRatings'     => $total_ratings,
            'weekEarned'       => $week_earned,
            'weekDeliveries'   => $week_deliveries,
            'pendingBalance'   => $pending_balance,
            'acceptanceRate'   => $acceptance_rate,
            'totalOffers'      => $total_offers,
            'termsAccepted'    => $terms_accepted ? true : false,
        ]);
    }

    public function api_update_rider_profile($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $p = $request->get_json_params();
        $errors = [];

        $first_name = sanitize_text_field($p['firstName'] ?? '');
        $last_name  = sanitize_text_field($p['lastName'] ?? '');
        $phone      = sanitize_text_field($p['phone'] ?? '');
        $bank_name  = sanitize_text_field($p['bankName'] ?? '');
        $bank_type  = sanitize_text_field($p['bankAccountType'] ?? '');
        $bank_num   = sanitize_text_field($p['bankAccountNumber'] ?? '');
        $region     = sanitize_text_field($p['region'] ?? '');
        $comuna     = sanitize_text_field($p['comuna'] ?? '');

        // SQL injection check
        $sql_err = self::check_form_sql_injection($p);
        if ($sql_err) return new WP_Error('security_error', $sql_err, ['status' => 400]);

        // Sanitize & validate names
        if ($first_name) {
            $first_name = self::sanitize_name($first_name);
            if (!self::validate_name($first_name)) $errors[] = 'Nombre solo puede contener letras';
        }
        if ($last_name) {
            $last_name = self::sanitize_name($last_name);
            if (!self::validate_name($last_name)) $errors[] = 'Apellido solo puede contener letras';
        }

        if ($first_name) wp_update_user(['ID' => $uid, 'first_name' => $first_name]);
        if ($last_name) wp_update_user(['ID' => $uid, 'last_name' => $last_name]);
        if ($first_name || $last_name) {
            $dn = trim($first_name . ' ' . $last_name);
            if ($dn) wp_update_user(['ID' => $uid, 'display_name' => $dn]);
        }
        if ($phone && !self::validate_chilean_phone($phone)) {
            $errors[] = 'Tel' . chr(233) . 'fono chileno inv' . chr(225) . 'lido';
        }
        if ($errors) return new WP_Error('validation_error', implode('. ', $errors), ['status' => 400]);

        $data = [];
        if ($first_name) $data['first_name'] = $first_name;
        if ($last_name) $data['last_name'] = $last_name;
        if ($phone) $data['phone'] = self::normalize_phone($phone);
        if ($bank_name !== '') $data['bank_name'] = $bank_name;
        if ($bank_type !== '') $data['bank_account_type'] = $bank_type;
        if ($bank_num !== '') $data['bank_account_number'] = $bank_num;
        if ($region !== '') $data['region'] = $region;
        if ($comuna !== '') $data['comuna'] = $comuna;

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $uid));
        if ($exists) {
            $wpdb->update("{$wpdb->prefix}petsgo_user_profiles", $data, ['user_id' => $uid]);
        } else {
            $data['user_id'] = $uid;
            $wpdb->insert("{$wpdb->prefix}petsgo_user_profiles", $data);
        }

        $this->audit('rider_profile_update', 'user', $uid);
        return rest_ensure_response(['message' => 'Perfil actualizado']);
    }

    // --- Rider Earnings & Payouts ---
    public function api_get_rider_earnings() {
        global $wpdb;
        $uid = get_current_user_id();

        // Commission config
        $rider_pct  = floatval($this->pg_setting('rider_commission_pct', 88));
        $store_pct  = floatval($this->pg_setting('store_fee_pct', 5));
        $petsgo_pct = floatval($this->pg_setting('petsgo_fee_pct', 7));
        $payout_day = $this->pg_setting('payout_day', 'thursday');

        // All delivered orders for this rider (history)
        $deliveries = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id, o.total_amount, o.delivery_fee, o.rider_earning, o.store_fee,
                    o.petsgo_delivery_fee, o.delivery_distance_km, o.status, o.created_at,
                    v.store_name, u.display_name AS customer_name, o.shipping_address AS address
             FROM {$wpdb->prefix}petsgo_orders o
             JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id = v.id
             JOIN {$wpdb->users} u ON o.customer_id = u.ID
             WHERE o.rider_id = %d
             ORDER BY o.created_at DESC", $uid
        ));

        // Weekly earnings breakdown — use rider_earning when available, fallback delivery_fee
        $weekly = $wpdb->get_results($wpdb->prepare(
            "SELECT YEARWEEK(created_at,1) as yw,
                    MIN(DATE(created_at)) AS week_start,
                    MAX(DATE(created_at)) AS week_end,
                    COUNT(*) AS deliveries,
                    SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END) AS earned
             FROM {$wpdb->prefix}petsgo_orders
             WHERE rider_id=%d AND status='delivered'
             GROUP BY YEARWEEK(created_at,1)
             ORDER BY yw DESC
             LIMIT 12", $uid
        ));

        // Payouts
        $payouts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_rider_payouts WHERE rider_id=%d ORDER BY period_end DESC LIMIT 20", $uid
        ));

        // Summary stats — use rider_earning when available
        $total_earned = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0)
             FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", $uid
        ));
        $total_paid = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(net_amount),0) FROM {$wpdb->prefix}petsgo_rider_payouts WHERE rider_id=%d AND status='paid'", $uid
        ));

        // Acceptance rate
        $total_offers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_delivery_offers WHERE rider_id=%d", $uid
        ));
        $accepted_offers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_delivery_offers WHERE rider_id=%d AND status='accepted'", $uid
        ));
        $acceptance_rate = $total_offers > 0 ? round(($accepted_offers / $total_offers) * 100, 1) : 100;

        // Current week stats
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $current_week = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS deliveries,
                    COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) AS earned
             FROM {$wpdb->prefix}petsgo_orders
             WHERE rider_id=%d AND status='delivered' AND created_at >= %s", $uid, $week_start
        ));

        // Next payout date (next Thursday)
        $next_payout = $this->get_next_payout_date($payout_day);

        return rest_ensure_response([
            'deliveries'     => $deliveries,
            'weekly'         => $weekly,
            'payouts'        => $payouts,
            'totalEarned'    => $total_earned,
            'totalPaid'      => $total_paid,
            'pendingBalance' => $total_earned - $total_paid,
            'acceptanceRate' => $acceptance_rate,
            'totalOffers'    => $total_offers,
            'currentWeek'    => [
                'deliveries' => (int) ($current_week->deliveries ?? 0),
                'earned'     => (float) ($current_week->earned ?? 0),
                'start'      => $week_start,
                'end'        => date('Y-m-d', strtotime('sunday this week')),
            ],
            'nextPayout'     => $next_payout,
            'commission'     => [
                'rider_pct'  => $rider_pct,
                'store_pct'  => $store_pct,
                'petsgo_pct' => $petsgo_pct,
                'payout_day' => $payout_day,
            ],
        ]);
    }

    /** Calculate next payout date */
    private function get_next_payout_date($day = 'thursday') {
        $days_map = ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
        $target = $days_map[$day] ?? 4;
        $today = new \DateTime();
        $current_day = (int) $today->format('N');
        $diff = $target - $current_day;
        if ($diff <= 0) $diff += 7;
        $next = clone $today;
        $next->modify("+{$diff} days");
        return $next->format('Y-m-d');
    }

    /** API: Calculate delivery fee based on distance */
    public function api_calculate_delivery_fee($request) {
        $p = $request->get_json_params();
        $distance_km = floatval($p['distance_km'] ?? 0);

        $base_rate = floatval($this->pg_setting('delivery_base_rate', 2000));
        $per_km    = floatval($this->pg_setting('delivery_per_km', 400));
        $fee_min   = floatval($this->pg_setting('delivery_fee_min', 2000));
        $fee_max   = floatval($this->pg_setting('delivery_fee_max', 5000));

        $calculated = $base_rate + ($distance_km * $per_km);
        $fee = max($fee_min, min($fee_max, round($calculated)));

        // Commission splits preview
        $rider_pct  = floatval($this->pg_setting('rider_commission_pct', 88));
        $store_pct  = floatval($this->pg_setting('store_fee_pct', 5));
        $petsgo_pct = floatval($this->pg_setting('petsgo_fee_pct', 7));

        return rest_ensure_response([
            'delivery_fee'   => $fee,
            'distance_km'    => $distance_km,
            'breakdown' => [
                'base_rate'      => $base_rate,
                'per_km'         => $per_km,
                'rider_earning'  => round($fee * $rider_pct / 100),
                'store_fee'      => round($fee * $store_pct / 100),
                'petsgo_fee'     => round($fee * $petsgo_pct / 100),
            ],
        ]);
    }

    /** API: Get rider commission policy (public) */
    public function api_get_rider_policy() {
        $rider_pct  = floatval($this->pg_setting('rider_commission_pct', 88));
        $store_pct  = floatval($this->pg_setting('store_fee_pct', 5));
        $petsgo_pct = floatval($this->pg_setting('petsgo_fee_pct', 7));
        $fee_min    = floatval($this->pg_setting('delivery_fee_min', 2000));
        $fee_max    = floatval($this->pg_setting('delivery_fee_max', 5000));
        $base_rate  = floatval($this->pg_setting('delivery_base_rate', 2000));
        $per_km     = floatval($this->pg_setting('delivery_per_km', 400));
        $payout_day = $this->pg_setting('payout_day', 'thursday');

        $terms_content = get_option('petsgo_legal_terminos_rider', '');

        return rest_ensure_response([
            'rider_pct'   => $rider_pct,
            'store_pct'   => $store_pct,
            'petsgo_pct'  => $petsgo_pct,
            'fee_min'     => $fee_min,
            'fee_max'     => $fee_max,
            'base_rate'   => $base_rate,
            'per_km'      => $per_km,
            'payout_day'  => $payout_day,
            'payout_cycle'=> 'weekly',
            'terms'       => $terms_content,
        ]);
    }

    // ── Rider Stats Report (reusable core) ──
    private function build_rider_stats($rider_id, $params) {
        global $wpdb;
        $range   = sanitize_text_field($params['range'] ?? 'month');   // week|month|year|custom
        $from    = sanitize_text_field($params['from'] ?? '');
        $to      = sanitize_text_field($params['to'] ?? '');

        // Determine date boundaries
        $now = current_time('Y-m-d');
        switch ($range) {
            case 'week':
                $date_from = date('Y-m-d', strtotime('monday this week'));
                $date_to   = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $date_from = date('Y-m-01');
                $date_to   = date('Y-m-t');
                break;
            case 'year':
                $date_from = date('Y-01-01');
                $date_to   = date('Y-12-31');
                break;
            case 'custom':
                $date_from = $from ?: date('Y-m-01');
                $date_to   = $to ?: $now;
                break;
            default:
                $date_from = date('Y-m-01');
                $date_to   = $now;
        }

        $tbl = $wpdb->prefix . 'petsgo_orders';

        // Summary for the period
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS deliveries,
                    COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) AS earned,
                    COALESCE(SUM(delivery_fee),0) AS total_delivery_fees,
                    COALESCE(SUM(delivery_distance_km),0) AS total_km,
                    COALESCE(AVG(delivery_distance_km),0) AS avg_km,
                    COALESCE(AVG(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) AS avg_earning
             FROM {$tbl}
             WHERE rider_id=%d AND status='delivered' AND DATE(created_at) BETWEEN %s AND %s",
            $rider_id, $date_from, $date_to
        ));

        // Weekly aggregation
        $weekly = $wpdb->get_results($wpdb->prepare(
            "SELECT YEARWEEK(created_at,1) AS yw,
                    MIN(DATE(created_at)) AS week_start,
                    MAX(DATE(created_at)) AS week_end,
                    COUNT(*) AS deliveries,
                    SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END) AS earned,
                    SUM(delivery_distance_km) AS km
             FROM {$tbl}
             WHERE rider_id=%d AND status='delivered' AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY YEARWEEK(created_at,1)
             ORDER BY yw ASC",
            $rider_id, $date_from, $date_to
        ));

        // Monthly aggregation
        $monthly = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at,'%%Y-%%m') AS month,
                    COUNT(*) AS deliveries,
                    SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END) AS earned,
                    SUM(delivery_distance_km) AS km
             FROM {$tbl}
             WHERE rider_id=%d AND status='delivered' AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY DATE_FORMAT(created_at,'%%Y-%%m')
             ORDER BY month ASC",
            $rider_id, $date_from, $date_to
        ));

        // Daily aggregation (for weekly/monthly ranges)
        $daily = [];
        if (in_array($range, ['week', 'month', 'custom'])) {
            $daily = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(created_at) AS day,
                        COUNT(*) AS deliveries,
                        SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END) AS earned
                 FROM {$tbl}
                 WHERE rider_id=%d AND status='delivered' AND DATE(created_at) BETWEEN %s AND %s
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC",
                $rider_id, $date_from, $date_to
            ));
        }

        // Rider info
        $user = get_userdata($rider_id);
        $prof = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $rider_id
        ));

        // Lifetime totals
        $lifetime = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS deliveries,
                    COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) AS earned
             FROM {$tbl} WHERE rider_id=%d AND status='delivered'", $rider_id
        ));

        // Acceptance rate
        $total_offers   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_delivery_offers WHERE rider_id=%d", $rider_id));
        $accepted       = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_delivery_offers WHERE rider_id=%d AND status='accepted'", $rider_id));
        $acceptance_rate = $total_offers > 0 ? round(($accepted / $total_offers) * 100, 1) : 100;

        // Average rating
        $avg_rating = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $rider_id
        ));
        $total_ratings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $rider_id
        ));

        // Payouts in period
        $payouts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_rider_payouts
             WHERE rider_id=%d AND period_start >= %s AND period_end <= %s
             ORDER BY period_end DESC",
            $rider_id, $date_from, $date_to
        ));

        $total_paid_period = 0;
        foreach ($payouts as $p) { if ($p->status === 'paid') $total_paid_period += (float) $p->net_amount; }

        return [
            'rider' => [
                'id'       => $rider_id,
                'name'     => $user ? $user->display_name : 'Rider #' . $rider_id,
                'email'    => $user ? $user->user_email : '',
                'phone'    => $prof->phone ?? '',
                'vehicle'  => $prof->vehicle ?? '',
                'region'   => $prof->region ?? '',
                'comuna'   => $prof->comuna ?? '',
            ],
            'range'      => $range,
            'dateFrom'   => $date_from,
            'dateTo'     => $date_to,
            'summary'    => [
                'deliveries'       => (int) ($summary->deliveries ?? 0),
                'earned'           => (float) ($summary->earned ?? 0),
                'totalDeliveryFees'=> (float) ($summary->total_delivery_fees ?? 0),
                'totalKm'          => round((float) ($summary->total_km ?? 0), 1),
                'avgKm'            => round((float) ($summary->avg_km ?? 0), 1),
                'avgEarning'       => round((float) ($summary->avg_earning ?? 0)),
                'totalPaidPeriod'  => $total_paid_period,
            ],
            'weekly'     => $weekly,
            'monthly'    => $monthly,
            'daily'      => $daily,
            'payouts'    => $payouts,
            'lifetime'   => [
                'deliveries'     => (int) ($lifetime->deliveries ?? 0),
                'earned'         => (float) ($lifetime->earned ?? 0),
                'acceptanceRate' => $acceptance_rate,
                'totalOffers'    => $total_offers,
                'avgRating'      => $avg_rating ? round($avg_rating, 2) : null,
                'totalRatings'   => $total_ratings,
            ],
        ];
    }

    /** API: Rider's own stats */
    public function api_get_rider_stats($request) {
        $uid = get_current_user_id();
        $data = $this->build_rider_stats($uid, $request->get_params());
        return rest_ensure_response($data);
    }

    /** API: Admin views any rider's stats */
    public function api_admin_rider_stats($request) {
        $rider_id = (int) $request['rider_id'];
        $data = $this->build_rider_stats($rider_id, $request->get_params());
        return rest_ensure_response($data);
    }

    /** API: Admin list all riders with summary */
    public function api_admin_list_riders() {
        global $wpdb;
        $riders = get_users(['role' => 'petsgo_rider', 'number' => 200]);
        $result = [];
        foreach ($riders as $r) {
            $prof = $wpdb->get_row($wpdb->prepare(
                "SELECT vehicle, region, comuna, phone FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $r->ID
            ));
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS deliveries,
                        COALESCE(SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END),0) AS earned
                 FROM {$wpdb->prefix}petsgo_orders WHERE rider_id=%d AND status='delivered'", $r->ID
            ));
            $avg_rating = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(rating) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $r->ID
            ));
            $rider_status = get_user_meta($r->ID, 'petsgo_rider_status', true) ?: 'pending';
            $result[] = [
                'id'         => $r->ID,
                'name'       => $r->display_name,
                'email'      => $r->user_email,
                'phone'      => $prof->phone ?? '',
                'vehicle'    => $prof->vehicle ?? '',
                'region'     => $prof->region ?? '',
                'status'     => $rider_status,
                'deliveries' => (int) ($stats->deliveries ?? 0),
                'earned'     => (float) ($stats->earned ?? 0),
                'avgRating'  => $avg_rating ? round($avg_rating, 2) : null,
                'registered' => $r->user_registered,
            ];
        }
        return rest_ensure_response($result);
    }

    // ── AJAX: Search rider payouts (Admin) ──
    public function petsgo_search_rider_payouts() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;

        $status = sanitize_text_field($_POST['status'] ?? '');
        $rider_id = intval($_POST['rider_id'] ?? 0);

        $where = '1=1';
        $args = [];
        if ($status) { $where .= " AND p.status = %s"; $args[] = $status; }
        if ($rider_id) { $where .= " AND p.rider_id = %d"; $args[] = $rider_id; }

        $sql = "SELECT p.*, u.display_name AS rider_name
                FROM {$wpdb->prefix}petsgo_rider_payouts p
                JOIN {$wpdb->users} u ON p.rider_id = u.ID
                WHERE {$where}
                ORDER BY p.created_at DESC LIMIT 200";

        $payouts = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql);
        wp_send_json_success($payouts);
    }

    // ── AJAX: Process individual payout (mark as paid) ──
    public function petsgo_process_payout() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;

        $payout_id = intval($_POST['payout_id'] ?? 0);
        $action = sanitize_text_field($_POST['payout_action'] ?? 'paid');
        if (!$payout_id) wp_send_json_error('ID requerido');

        $payout = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_rider_payouts WHERE id=%d", $payout_id));
        if (!$payout) wp_send_json_error('Payout no encontrado');

        if ($action === 'paid') {
            $wpdb->update("{$wpdb->prefix}petsgo_rider_payouts", [
                'status' => 'paid', 'paid_at' => current_time('mysql'),
            ], ['id' => $payout_id]);
            $this->audit('payout_paid', 'payout', $payout_id, "Rider #{$payout->rider_id} · \${$payout->net_amount}");
        } elseif ($action === 'failed') {
            $notes = sanitize_text_field($_POST['notes'] ?? '');
            $wpdb->update("{$wpdb->prefix}petsgo_rider_payouts", [
                'status' => 'failed', 'notes' => $notes,
            ], ['id' => $payout_id]);
            $this->audit('payout_failed', 'payout', $payout_id, "Rider #{$payout->rider_id} · {$notes}");
        }

        wp_send_json_success('Payout actualizado');
    }

    // ── AJAX: Generate weekly payouts for all riders ──
    public function petsgo_generate_weekly_payouts() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;

        // Calculate period: last Monday to Sunday
        $period_end   = date('Y-m-d', strtotime('last sunday'));
        $period_start = date('Y-m-d', strtotime($period_end . ' -6 days'));

        // Check if already generated for this period
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_rider_payouts WHERE period_start=%s AND period_end=%s",
            $period_start, $period_end
        ));
        if ($exists > 0) {
            wp_send_json_error("Ya existen payouts para el período {$period_start} a {$period_end}");
            return;
        }

        // Get all riders with delivered orders in this period
        $riders = $wpdb->get_results($wpdb->prepare(
            "SELECT rider_id,
                    COUNT(*) AS total_deliveries,
                    SUM(CASE WHEN rider_earning > 0 THEN rider_earning ELSE delivery_fee END) AS total_earned
             FROM {$wpdb->prefix}petsgo_orders
             WHERE status='delivered' AND rider_id > 0
               AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY rider_id",
            $period_start, $period_end
        ));

        $count = 0;
        foreach ($riders as $r) {
            $net = floatval($r->total_earned);
            $wpdb->insert("{$wpdb->prefix}petsgo_rider_payouts", [
                'rider_id'         => $r->rider_id,
                'period_start'     => $period_start,
                'period_end'       => $period_end,
                'total_deliveries' => $r->total_deliveries,
                'total_earned'     => $net,
                'total_tips'       => 0,
                'total_deductions' => 0,
                'net_amount'       => $net,
                'status'           => 'pending',
            ], ['%d','%s','%s','%d','%f','%f','%f','%f','%s']);
            $count++;
        }

        $this->audit('payouts_generated', 'payout', 0, "Período {$period_start} → {$period_end}: {$count} riders");
        wp_send_json_success("✅ {$count} payouts generados para {$period_start} a {$period_end}");
    }

    public function api_get_rider_deliveries() {
        global $wpdb;
        $uid = get_current_user_id();
        $deliveries = $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, v.store_name, u.display_name AS customer_name, o.shipping_address AS address
             FROM {$wpdb->prefix}petsgo_orders o
             JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id = v.id
             JOIN {$wpdb->users} u ON o.customer_id = u.ID
             WHERE o.rider_id = %d AND o.delivery_method = 'delivery'
             ORDER BY o.created_at DESC", $uid
        ));
        return rest_ensure_response($deliveries);
    }

    public function api_update_delivery_status($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $order_id = intval($request['id']);
        $p = $request->get_json_params();
        $new_status = sanitize_text_field($p['status'] ?? '');

        $valid = ['ready_for_pickup', 'in_transit', 'delivered'];
        if (!in_array($new_status, $valid)) return new WP_Error('invalid', 'Estado inválido', ['status' => 400]);

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_orders WHERE id=%d AND rider_id=%d", $order_id, $uid));
        if (!$order) return new WP_Error('not_found', 'Pedido no encontrado o no asignado a ti', ['status' => 404]);

        $wpdb->update("{$wpdb->prefix}petsgo_orders", ['status' => $new_status], ['id' => $order_id]);
        $this->audit('delivery_status', 'order', $order_id, "Rider {$uid}: {$order->status} → {$new_status}");
        return rest_ensure_response(['message' => 'Estado actualizado']);
    }

    // --- Delivery Ratings API ---
    public function api_rate_rider($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $order_id = intval($request['id']);
        $p = $request->get_json_params();
        $rating  = intval($p['rating'] ?? 0);
        $comment = sanitize_textarea_field($p['comment'] ?? '');

        if ($rating < 1 || $rating > 5) return new WP_Error('invalid', 'Rating debe ser entre 1 y 5', ['status' => 400]);

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_orders WHERE id=%d", $order_id));
        if (!$order) return new WP_Error('not_found', 'Pedido no encontrado', ['status' => 404]);
        if (!$order->rider_id) return new WP_Error('no_rider', 'Este pedido no tiene rider asignado', ['status' => 400]);
        if ($order->status !== 'delivered') return new WP_Error('not_delivered', 'Solo puedes valorar pedidos entregados', ['status' => 400]);

        // Determine rater type
        $user = wp_get_current_user();
        $rater_type = null;
        if ($order->customer_id == $uid) {
            $rater_type = 'customer';
        } elseif (in_array('petsgo_vendor', (array)$user->roles)) {
            // Check if this vendor owns the order
            $vendor_user = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d", $order->vendor_id));
            if ($vendor_user == $uid) $rater_type = 'vendor';
        }
        if (!$rater_type && current_user_can('administrator')) $rater_type = 'vendor'; // admin can rate as vendor

        if (!$rater_type) return new WP_Error('unauthorized', 'No tienes permiso para valorar esta entrega', ['status' => 403]);

        // Check duplicate
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE order_id=%d AND rater_type=%s", $order_id, $rater_type
        ));
        if ($exists) return new WP_Error('duplicate', 'Ya valoraste esta entrega', ['status' => 400]);

        $wpdb->insert("{$wpdb->prefix}petsgo_delivery_ratings", [
            'order_id'   => $order_id,
            'rider_id'   => $order->rider_id,
            'rater_type' => $rater_type,
            'rater_id'   => $uid,
            'rating'     => $rating,
            'comment'    => $comment,
        ], ['%d', '%d', '%s', '%d', '%d', '%s']);

        $this->audit('rate_rider', 'order', $order_id, "Rider {$order->rider_id}: {$rating}⭐ por {$rater_type}");
        return rest_ensure_response(['message' => 'Valoración registrada. ¡Gracias!']);
    }

    public function api_get_rider_ratings() {
        global $wpdb;
        $uid = get_current_user_id();
        $ratings = $wpdb->get_results($wpdb->prepare(
            "SELECT dr.*, u.display_name AS rater_name
             FROM {$wpdb->prefix}petsgo_delivery_ratings dr
             JOIN {$wpdb->users} u ON dr.rater_id = u.ID
             WHERE dr.rider_id = %d ORDER BY dr.created_at DESC LIMIT 50", $uid
        ));
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM {$wpdb->prefix}petsgo_delivery_ratings WHERE rider_id=%d", $uid
        ));
        return rest_ensure_response(['ratings' => $ratings, 'average' => $avg ? round(floatval($avg), 1) : null]);
    }

    // --- Forgot / Reset password ---
    public function api_forgot_password($request) {
        global $wpdb;
        $p = $request->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        if (!$email) return new WP_Error('missing', 'Email es obligatorio', ['status' => 400]);

        $user = get_user_by('email', $email);
        // Always return success to prevent email enumeration
        if (!$user) return rest_ensure_response(['message' => 'Si el correo existe, recibirás instrucciones para restablecer tu contraseña.']);

        // Generate token
        $token = bin2hex(random_bytes(32));
        $wpdb->insert("{$wpdb->prefix}petsgo_password_resets", [
            'user_id'    => $user->ID,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hour
        ], ['%d','%s','%s']);

        // Send email
        $reset_url = home_url('/reset-password?token=' . $token);
        $inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($user->display_name) . '</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">Recibimos una solicitud para restablecer tu contraseña en PetsGo.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;">
        <tr>
          <td align="center">
            <a href="' . esc_url($reset_url) . '" style="display:inline-block;padding:14px 40px;background:#00A8E8;color:#fff;font-weight:700;font-size:16px;text-decoration:none;border-radius:12px;box-shadow:0 4px 14px rgba(0,168,232,0.35);">🔑 Restablecer Contraseña</a>
          </td>
        </tr>
      </table>
      <p style="color:#888;font-size:12px;">Este enlace expira en 1 hora. Si no solicitaste esto, ignora este correo.</p>';

        $body = $this->email_wrap($inner, 'Restablece tu contraseña de PetsGo');
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->pg_setting('company_name','PetsGo') . ' <' . $this->pg_setting('company_from_email','notificaciones@petsgo.cl') . '>',
        ];
        wp_mail($email, 'PetsGo — Restablecer Contraseña', $body, $headers);
        $this->audit('forgot_password', 'user', $user->ID, $email);
        return rest_ensure_response(['message' => 'Si el correo existe, recibirás instrucciones para restablecer tu contraseña.']);
    }

    public function api_reset_password($request) {
        global $wpdb;
        $p = $request->get_json_params();
        $token    = sanitize_text_field($p['token'] ?? '');
        $password = $p['password'] ?? '';
        if (!$token) return new WP_Error('missing', 'Token requerido', ['status' => 400]);

        $pass_errors = self::validate_password_strength($password);
        if ($pass_errors) return new WP_Error('weak_password', implode('. ', $pass_errors), ['status' => 400]);

        $reset = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_password_resets WHERE token=%s AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            $token
        ));
        if (!$reset) return new WP_Error('invalid_token', 'Token inválido o expirado. Solicita uno nuevo.', ['status' => 400]);

        wp_set_password($password, $reset->user_id);
        $wpdb->update("{$wpdb->prefix}petsgo_password_resets", ['used' => 1], ['id' => $reset->id]);
        $this->audit('reset_password', 'user', $reset->user_id);
        return rest_ensure_response(['message' => 'Contraseña restablecida exitosamente. Ahora puedes iniciar sesión.']);
    }

    // --- Profile API ---
    public function api_get_profile($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $user = get_userdata($uid);
        $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $uid));
        $pets = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_pets WHERE user_id=%d ORDER BY id ASC", $uid));
        $role = 'customer';
        if (in_array('administrator',$user->roles)) $role='admin';
        elseif (in_array('petsgo_vendor',$user->roles)) $role='vendor';
        elseif (in_array('petsgo_rider',$user->roles)) $role='rider';

        return rest_ensure_response([
            'id'         => $uid,
            'username'   => $user->user_login,
            'email'      => $user->user_email,
            'displayName'=> $user->display_name,
            'role'       => $role,
            'firstName'  => $profile->first_name ?? '',
            'lastName'   => $profile->last_name ?? '',
            'idType'     => $profile->id_type ?? 'rut',
            'idNumber'   => $profile->id_number ?? '',
            'birthDate'  => $profile->birth_date ?? '',
            'phone'      => $profile->phone ?? '',
            'avatarUrl'  => $profile->avatar_url ?? '',
            'pets'       => array_map(function($pet) {
                $age = '';
                if ($pet->birth_date) {
                    $diff = date_diff(date_create($pet->birth_date), date_create());
                    $age = $diff->y > 0 ? $diff->y . ' año' . ($diff->y > 1 ? 's':'') : $diff->m . ' mes' . ($diff->m > 1 ? 'es':'');
                }
                return [
                    'id'        => (int)$pet->id,
                    'petType'   => $pet->pet_type,
                    'name'      => $pet->name,
                    'breed'     => $pet->breed,
                    'birthDate' => $pet->birth_date,
                    'age'       => $age,
                    'photoUrl'  => $pet->photo_url,
                    'notes'     => $pet->notes,
                ];
            }, $pets),
        ]);
    }

    public function api_update_profile($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $p = $request->get_json_params();
        $errors = [];

        $first_name = sanitize_text_field($p['firstName'] ?? '');
        $last_name  = sanitize_text_field($p['lastName'] ?? '');
        $phone      = sanitize_text_field($p['phone'] ?? '');

        // SQL injection check
        $sql_err = self::check_form_sql_injection($p);
        if ($sql_err) return new WP_Error('security_error', $sql_err, ['status' => 400]);

        // Sanitize & validate names
        if ($first_name) {
            $first_name = self::sanitize_name($first_name);
            if (!self::validate_name($first_name)) $errors[] = 'Nombre solo puede contener letras';
        }
        if ($last_name) {
            $last_name = self::sanitize_name($last_name);
            if (!self::validate_name($last_name)) $errors[] = 'Apellido solo puede contener letras';
        }

        if ($first_name) wp_update_user(['ID' => $uid, 'first_name' => $first_name]);
        if ($last_name) wp_update_user(['ID' => $uid, 'last_name' => $last_name]);
        if ($first_name || $last_name) {
            $dn = trim($first_name . ' ' . $last_name);
            if ($dn) wp_update_user(['ID' => $uid, 'display_name' => $dn]);
        }

        if ($phone && !self::validate_chilean_phone($phone)) {
            $errors[] = 'Teléfono chileno inválido';
        }

        if ($errors) return new WP_Error('validation_error', implode('. ', $errors), ['status' => 400]);

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}petsgo_user_profiles WHERE user_id=%d", $uid));
        $data = [];
        if ($first_name) $data['first_name'] = $first_name;
        if ($last_name) $data['last_name'] = $last_name;
        if ($phone) $data['phone'] = self::normalize_phone($phone);

        if ($exists) {
            $wpdb->update("{$wpdb->prefix}petsgo_user_profiles", $data, ['user_id' => $uid]);
        } else {
            $data['user_id'] = $uid;
            $wpdb->insert("{$wpdb->prefix}petsgo_user_profiles", $data);
        }

        $this->audit('profile_update', 'user', $uid);
        return rest_ensure_response(['message' => 'Perfil actualizado']);
    }

    public function api_change_password($request) {
        $uid = get_current_user_id();
        $p = $request->get_json_params();
        $current  = $p['currentPassword'] ?? '';
        $new_pass = $p['newPassword'] ?? '';

        $user = get_userdata($uid);
        if (!wp_check_password($current, $user->user_pass, $uid)) {
            return new WP_Error('wrong_password', 'Contraseña actual incorrecta', ['status' => 400]);
        }
        $pass_errors = self::validate_password_strength($new_pass);
        if ($pass_errors) return new WP_Error('weak_password', implode('. ', $pass_errors), ['status' => 400]);

        wp_set_password($new_pass, $uid);
        // Regenerate token after password change
        $new_token = 'petsgo_' . bin2hex(random_bytes(32));
        update_user_meta($uid, 'petsgo_api_token', $new_token);
        $this->audit('change_password', 'user', $uid);
        return rest_ensure_response(['message' => 'Contraseña actualizada exitosamente']);
    }

    // --- Pets API ---
    public function api_get_pets($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $pets = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_pets WHERE user_id=%d ORDER BY id ASC", $uid));
        return rest_ensure_response(['data' => array_map(function($pet) {
            $age = '';
            if ($pet->birth_date) {
                $diff = date_diff(date_create($pet->birth_date), date_create());
                $age = $diff->y > 0 ? $diff->y . ' año' . ($diff->y > 1 ? 's':'') : $diff->m . ' mes' . ($diff->m > 1 ? 'es':'');
            }
            return ['id'=>(int)$pet->id,'petType'=>$pet->pet_type,'name'=>$pet->name,'breed'=>$pet->breed,'birthDate'=>$pet->birth_date,'age'=>$age,'photoUrl'=>$pet->photo_url,'notes'=>$pet->notes];
        }, $pets)]);
    }

    public function api_add_pet($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $p = $request->get_json_params();
        $name = sanitize_text_field($p['name'] ?? '');
        if (!$name) return new WP_Error('missing', 'Nombre de mascota es obligatorio', ['status' => 400]);

        $wpdb->insert("{$wpdb->prefix}petsgo_pets", [
            'user_id'    => $uid,
            'pet_type'   => sanitize_text_field($p['petType'] ?? 'perro'),
            'name'       => $name,
            'breed'      => sanitize_text_field($p['breed'] ?? ''),
            'birth_date' => !empty($p['birthDate']) ? sanitize_text_field($p['birthDate']) : null,
            'photo_url'  => esc_url_raw($p['photoUrl'] ?? ''),
            'notes'      => sanitize_textarea_field($p['notes'] ?? ''),
        ], ['%d','%s','%s','%s','%s','%s','%s']);
        $pet_id = $wpdb->insert_id;
        $this->audit('pet_add', 'pet', $pet_id, $name);
        return rest_ensure_response(['id' => $pet_id, 'message' => 'Mascota agregada']);
    }

    public function api_update_pet($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $pet_id = (int)$request->get_param('id');
        $pet = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_pets WHERE id=%d AND user_id=%d", $pet_id, $uid));
        if (!$pet) return new WP_Error('not_found', 'Mascota no encontrada', ['status' => 404]);
        $p = $request->get_json_params();
        $data = [];
        if (isset($p['name']))      $data['name']       = sanitize_text_field($p['name']);
        if (isset($p['petType']))    $data['pet_type']   = sanitize_text_field($p['petType']);
        if (isset($p['breed']))      $data['breed']      = sanitize_text_field($p['breed']);
        if (isset($p['birthDate']))  $data['birth_date'] = $p['birthDate'] ?: null;
        if (isset($p['photoUrl']))   $data['photo_url']  = esc_url_raw($p['photoUrl']);
        if (isset($p['notes']))      $data['notes']      = sanitize_textarea_field($p['notes']);
        if ($data) $wpdb->update("{$wpdb->prefix}petsgo_pets", $data, ['id' => $pet_id]);
        $this->audit('pet_update', 'pet', $pet_id, $data['name'] ?? $pet->name);
        return rest_ensure_response(['message' => 'Mascota actualizada']);
    }

    public function api_delete_pet($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $pet_id = (int)$request->get_param('id');
        $pet = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_pets WHERE id=%d AND user_id=%d", $pet_id, $uid));
        if (!$pet) return new WP_Error('not_found', 'Mascota no encontrada', ['status' => 404]);
        $wpdb->delete("{$wpdb->prefix}petsgo_pets", ['id' => $pet_id]);
        $this->audit('pet_delete', 'pet', $pet_id, $pet->name);
        return rest_ensure_response(['message' => 'Mascota eliminada']);
    }
    public function api_upload_pet_photo($request) {
        if (empty($_FILES['photo'])) return new WP_Error('no_file', 'No se envió ninguna foto', ['status' => 400]);
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) return new WP_Error('invalid_type', 'Tipo de imagen no permitido', ['status' => 400]);
        if ($file['size'] > 5 * 1024 * 1024) return new WP_Error('too_large', 'La imagen no debe exceder 5MB', ['status' => 400]);
        $upload_dir = wp_upload_dir();
        $pet_dir = $upload_dir['basedir'] . '/petsgo-pets/';
        if (!file_exists($pet_dir)) wp_mkdir_p($pet_dir);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'pet_' . get_current_user_id() . '_' . time() . '_' . wp_rand(100,999) . '.' . $ext;
        $dest = $pet_dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) return new WP_Error('upload_fail', 'Error al guardar la imagen', ['status' => 500]);
        $url = $upload_dir['baseurl'] . '/petsgo-pets/' . $filename;
        return rest_ensure_response(['url' => $url]);
    }

    // --- API Orders ---
    public function api_create_order($request) {
        global $wpdb;$uid=get_current_user_id();$p=$request->get_json_params();
        if(!isset($p['vendor_id'],$p['items'],$p['total'])) return new WP_Error('missing','Faltan datos',['status'=>400]);
        $vendor=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_vendors WHERE id=%d",$p['vendor_id']));
        if(!$vendor) return new WP_Error('invalid','Vendedor no existe',['status'=>404]);
        $total=floatval($p['total']);$comm=round($total*(floatval($vendor->sales_commission)/100),2);$del=floatval($p['delivery_fee']??0);
        $delivery_method = sanitize_text_field($p['delivery_method'] ?? 'delivery');
        if (!in_array($delivery_method, ['delivery', 'pickup'])) $delivery_method = 'delivery';
        if ($delivery_method === 'pickup') $del = 0; // Retiro en tienda = sin costo envío
        $shipping_address = sanitize_textarea_field($p['shipping_address'] ?? '');

        // ── Calcular splits del delivery fee ──
        $rider_pct   = floatval($this->pg_setting('rider_commission_pct', 88));
        $store_pct   = floatval($this->pg_setting('store_fee_pct', 5));
        $petsgo_pct  = floatval($this->pg_setting('petsgo_fee_pct', 7));
        $rider_earning     = round($del * ($rider_pct / 100), 2);
        $store_fee         = round($del * ($store_pct / 100), 2);
        $petsgo_del_fee    = round($del * ($petsgo_pct / 100), 2);
        $dist_km = floatval($p['delivery_distance_km'] ?? 0) ?: null;

        $wpdb->insert("{$wpdb->prefix}petsgo_orders",[
            'customer_id'=>$uid,'vendor_id'=>$p['vendor_id'],'total_amount'=>$total,
            'petsgo_commission'=>$comm,'rider_earning'=>$rider_earning,
            'store_fee'=>$store_fee,'petsgo_delivery_fee'=>$petsgo_del_fee,
            'delivery_fee'=>$del,'delivery_distance_km'=>$dist_km,
            'delivery_method'=>$delivery_method,'shipping_address'=>$shipping_address,
            'status'=>'pending'
        ],['%d','%d','%f','%f','%f','%f','%f','%f','%f','%s','%s','%s']);
        $order_id = $wpdb->insert_id;
        $this->audit('order_create', 'order', $order_id, 'Total: $'.number_format($total,0,',','.'));

        // Guardar items del pedido + descontar stock
        if (!empty($p['items']) && is_array($p['items'])) {
            foreach ($p['items'] as $item) {
                $pid = intval($item['product_id'] ?? 0);
                $qty = intval($item['quantity'] ?? 1);
                $unit_price = floatval($item['price'] ?? 0);
                if ($pid > 0 && $qty > 0) {
                    // Obtener nombre del producto
                    $pname = $wpdb->get_var($wpdb->prepare("SELECT product_name FROM {$wpdb->prefix}petsgo_inventory WHERE id=%d", $pid));
                    // Guardar item
                    $wpdb->insert("{$wpdb->prefix}petsgo_order_items", [
                        'order_id' => $order_id, 'product_id' => $pid,
                        'product_name' => $pname ?: ('Producto #'.$pid),
                        'quantity' => $qty, 'unit_price' => $unit_price,
                        'subtotal' => round($unit_price * $qty, 2),
                    ], ['%d','%d','%s','%d','%f','%f']);
                    // Descontar stock
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}petsgo_inventory SET stock = GREATEST(stock - %d, 0) WHERE id = %d",
                        $qty, $pid
                    ));
                    $this->check_stock_alert($pid);
                }
            }
        }

        // Auto-generate invoice
        $this->auto_generate_invoice($order_id);

        return rest_ensure_response(['order_id'=>$order_id,'message'=>'Orden creada','commission_logged'=>$comm]);
    }
    public function api_get_my_orders() {global $wpdb;$uid=get_current_user_id();return rest_ensure_response(['data'=>$wpdb->get_results($wpdb->prepare("SELECT o.*,v.store_name FROM {$wpdb->prefix}petsgo_orders o JOIN {$wpdb->prefix}petsgo_vendors v ON o.vendor_id=v.id WHERE o.customer_id=%d ORDER BY o.created_at DESC",$uid))]);}
    // --- API Vendor Dashboard ---
    public function check_vendor_role() {
        $u=wp_get_current_user();
        if (in_array('administrator',(array)$u->roles)) return true;
        if (!in_array('petsgo_vendor',(array)$u->roles)) return false;
        // Check if vendor is active
        global $wpdb;
        $vs = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}petsgo_vendors WHERE user_id=%d",$u->ID));
        if ($vs !== 'active') return new WP_Error('vendor_inactive','Tu tienda está inactiva. Renueva tu suscripción para acceder.',['status'=>403]);
        return true;
    }
    public function api_vendor_inventory($request) {
        global $wpdb;$uid=get_current_user_id();
        $vendor=$wpdb->get_row($wpdb->prepare("SELECT id,status FROM {$wpdb->prefix}petsgo_vendors WHERE user_id=%d",$uid));
        $vid=$vendor->id??0;
        if(!$vid&&!current_user_can('administrator')) return new WP_Error('no_vendor','Sin tienda',['status'=>403]);
        if($vendor&&$vendor->status!=='active'&&!current_user_can('administrator')) return new WP_Error('vendor_inactive','Tu tienda está inactiva. Renueva tu suscripción para acceder.',['status'=>403]);
        if($request->get_method()==='GET') return rest_ensure_response(['data'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_inventory WHERE vendor_id=%d",$vid))]);
        $p=$request->get_json_params();$wpdb->insert("{$wpdb->prefix}petsgo_inventory",['vendor_id'=>$vid,'product_name'=>$p['product_name'],'price'=>$p['price'],'stock'=>$p['stock'],'category'=>$p['category']]);
        return rest_ensure_response(['id'=>$wpdb->insert_id,'message'=>'Producto agregado']);
    }
    public function api_vendor_dashboard() {
        global $wpdb;$uid=get_current_user_id();
        $vendor=$wpdb->get_row($wpdb->prepare("SELECT id,status,subscription_end FROM {$wpdb->prefix}petsgo_vendors WHERE user_id=%d",$uid));
        $vid=$vendor->id??0;
        if(!$vid) return new WP_Error('no_vendor','Sin tienda',['status'=>403]);
        if($vendor->status!=='active') return new WP_Error('vendor_inactive','Tu tienda está inactiva. Renueva tu suscripción para acceder.',['status'=>403]);
        return rest_ensure_response(['sales'=>(float)$wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d AND status='delivered'",$vid)),'pending_orders'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}petsgo_orders WHERE vendor_id=%d AND status='pending'",$vid)),'store_status'=>$vendor->status]);
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

    // ============================================================
    // VENDOR LEADS — formulario de contacto tiendas interesadas
    // ============================================================
    public function api_submit_vendor_lead($request) {
        global $wpdb;
        $p = $request->get_json_params();

        // Rate limiting
        $rl = $this->check_rate_limit('vendor_lead');
        if ($rl) return $rl;

        $store_name   = sanitize_text_field($p['storeName'] ?? '');
        $contact_name = sanitize_text_field($p['contactName'] ?? '');
        $email        = sanitize_email($p['email'] ?? '');
        $phone        = sanitize_text_field($p['phone'] ?? '');
        $region       = sanitize_text_field($p['region'] ?? '');
        $comuna       = sanitize_text_field($p['comuna'] ?? '');
        $message      = sanitize_textarea_field($p['message'] ?? '');
        $plan         = sanitize_text_field($p['plan'] ?? '');

        // SQL injection check
        $sql_err = self::check_form_sql_injection($p);
        if ($sql_err) return new WP_Error('security_error', $sql_err, ['status' => 400]);

        // Sanitize contact name (letters only)
        $contact_name = self::sanitize_name($contact_name);

        $errors = [];
        if (!$store_name) $errors[] = 'Nombre de tienda es obligatorio';
        if (!$contact_name) $errors[] = 'Nombre de contacto es obligatorio';
        elseif (!self::validate_name($contact_name)) $errors[] = 'Nombre de contacto solo puede contener letras';
        if (!$email || !is_email($email)) $errors[] = 'Email válido es obligatorio';
        if (!$phone) $errors[] = 'Teléfono es obligatorio';
        if (!$region) $errors[] = 'Región es obligatoria';
        if (!$comuna) $errors[] = 'Comuna es obligatoria';

        if ($errors) return new WP_Error('validation_error', implode('. ', $errors), ['status' => 400]);

        // Ensure region column exists
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}petsgo_leads LIKE 'region'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}petsgo_leads ADD COLUMN region VARCHAR(100) DEFAULT '' AFTER phone");
        }

        $wpdb->insert("{$wpdb->prefix}petsgo_leads", [
            'store_name'   => $store_name,
            'contact_name' => $contact_name,
            'email'        => $email,
            'phone'        => $phone,
            'region'       => $region,
            'comuna'       => $comuna,
            'message'      => $message,
            'plan_name'    => $plan,
            'status'       => 'nuevo',
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        ]);

        // Thank-you email
        $lead_inner = '
      <p style="color:#333;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($contact_name) . '</strong>,</p>
      <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 20px;">Gracias por tu interés en unirte a <strong>PetsGo</strong> con tu tienda <strong>' . esc_html($store_name) . '</strong>. Hemos recibido tu solicitud y nuestro equipo la revisará a la brevedad.</p>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#f0faff;border-radius:10px;padding:20px 24px;">
          <p style="margin:0 0 10px;font-size:14px;color:#00A8E8;font-weight:700;">📋 Resumen de tu solicitud</p>
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555;">
            <tr><td style="padding:4px 0;font-weight:600;width:120px;">Tienda:</td><td style="padding:4px 0;">' . esc_html($store_name) . '</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Contacto:</td><td style="padding:4px 0;">' . esc_html($contact_name) . '</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Email:</td><td style="padding:4px 0;">' . esc_html($email) . '</td></tr>
            <tr><td style="padding:4px 0;font-weight:600;">Teléfono:</td><td style="padding:4px 0;">' . esc_html($phone) . '</td></tr>' .
            ($region ? '<tr><td style="padding:4px 0;font-weight:600;">Región:</td><td style="padding:4px 0;">' . esc_html($region) . '</td></tr>' : '') .
            ($comuna ? '<tr><td style="padding:4px 0;font-weight:600;">Comuna:</td><td style="padding:4px 0;">' . esc_html($comuna) . '</td></tr>' : '') .
            ($plan ? '<tr><td style="padding:4px 0;font-weight:600;">Plan:</td><td style="padding:4px 0;">' . esc_html($plan) . '</td></tr>' : '') . '
          </table>' .
          ($message ? '<p style="margin:12px 0 0;font-size:13px;color:#555;border-top:1px solid #e0f0f8;padding-top:12px;"><strong>Mensaje:</strong><br>' . nl2br(esc_html($message)) . '</p>' : '') . '
        </td></tr>
      </table>

      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;">
        <tr><td style="background-color:#fff8ed;border-left:4px solid #FFC400;border-radius:8px;padding:16px 20px;">
          <p style="margin:0;font-size:13px;color:#8a6d00;line-height:1.6;">
            ⏱️ <strong>¿Qué sigue?</strong><br>
            Un ejecutivo de PetsGo se contactará contigo en las próximas <strong>24-48 horas hábiles</strong> para coordinar la incorporación de tu tienda.
          </p>
        </td></tr>
      </table>

      <p style="color:#aaa;font-size:11px;line-height:1.5;margin:24px 0 0;text-align:center;">
        Este correo fue enviado a <span style="color:#888;">' . esc_html($email) . '</span> porque completaste el formulario de contacto en PetsGo.
      </p>';
        $lead_html = $this->email_wrap($lead_inner, 'Gracias por tu interés en PetsGo, ' . $contact_name);
        $company   = $this->pg_setting('company_name', 'PetsGo');
        $from_email = $this->pg_setting('company_from_email', 'notificaciones@petsgo.cl');
        $bcc_email = $this->pg_setting('company_bcc_email', 'contacto@petsgo.cl');
        $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$company} <{$from_email}>", "Bcc: {$bcc_email}"];
        @wp_mail($email, "Gracias por tu interés en {$company} 🏪", $lead_html, $headers);

        $this->audit('vendor_lead', 'lead', $wpdb->insert_id, $store_name . ' (' . $email . ')');

        return rest_ensure_response(['message' => '¡Gracias! Hemos recibido tu información. Te contactaremos pronto.']);
    }

    // ============================================================
    // LEADS ADMIN PAGE — Gestión de leads de tiendas
    // ============================================================
    public function page_leads() {
        if (!$this->is_admin()) { echo '<div class="wrap"><h1>⛔ Sin acceso</h1></div>'; return; }
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📩 Leads de Tiendas</h1>
            <p class="petsgo-info-bar">Tiendas interesadas que completaron el formulario de contacto en el frontend.</p>

            <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
                <input type="text" id="leads-search" placeholder="🔍 Buscar por tienda, contacto, email..." class="petsgo-search" style="flex:1;min-width:220px;">
                <select id="leads-status-filter" class="petsgo-select" style="min-width:160px;">
                    <option value="">Todos los estados</option>
                    <option value="nuevo">🆕 Nuevo</option>
                    <option value="contactado">📞 Contactado</option>
                    <option value="contratado">✅ Contratado</option>
                    <option value="declinado">❌ Declinado</option>
                </select>
                <button type="button" class="petsgo-btn petsgo-btn-primary" onclick="searchLeads()">Buscar</button>
                <span class="petsgo-loader" id="leads-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>

            <div id="leads-grid"></div>
        </div>

        <!-- Modal notas -->
        <div id="lead-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;justify-content:center;align-items:center;">
            <div style="background:#fff;border-radius:12px;padding:28px;width:95%;max-width:520px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 40px rgba(0,0,0,0.15);">
                <h3 style="margin:0 0 16px;color:#2F3A40;" id="lead-modal-title">Lead</h3>
                <div id="lead-modal-body"></div>
                <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="petsgo-btn" onclick="document.getElementById('lead-modal').style.display='none'">Cerrar</button>
                    <button type="button" class="petsgo-btn petsgo-btn-primary" id="lead-save-btn" onclick="saveLeadChanges()">💾 Guardar</button>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var currentLead = null;
            window.searchLeads = function(){
                $('#leads-loader').addClass('active');
                PG.post('petsgo_search_leads', {
                    search: $('#leads-search').val(),
                    status: $('#leads-status-filter').val()
                }, function(r) {
                    $('#leads-loader').removeClass('active');
                    if (!r.success) return;
                    var rows = r.data.rows;
                    if (!rows.length) {
                        $('#leads-grid').html('<div class="petsgo-empty">No se encontraron leads.</div>');
                        return;
                    }
                    var statusBadge = function(s) {
                        var m = {nuevo:'background:#e3f2fd;color:#1976d2',contactado:'background:#fff3e0;color:#e65100',contratado:'background:#e8f5e9;color:#2e7d32',declinado:'background:#fce4ec;color:#c62828'};
                        var icons = {nuevo:'🆕',contactado:'📞',contratado:'✅',declinado:'❌'};
                        return '<span style="display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;' + (m[s]||'') + '">' + (icons[s]||'') + ' ' + s.charAt(0).toUpperCase()+s.slice(1) + '</span>';
                    };
                    var html = '<table class="petsgo-table"><thead><tr><th>Tienda</th><th>Contacto</th><th>Email</th><th>Teléfono</th><th>Plan</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>';
                    rows.forEach(function(l) {
                        html += '<tr>';
                        html += '<td><strong>' + PG.esc(l.store_name) + '</strong>' + (l.comuna ? '<br><small style="color:#888;">' + PG.esc(l.comuna) + '</small>' : '') + '</td>';
                        html += '<td>' + PG.esc(l.contact_name) + '</td>';
                        html += '<td><a href="mailto:' + PG.esc(l.email) + '">' + PG.esc(l.email) + '</a></td>';
                        html += '<td>' + PG.esc(l.phone) + '</td>';
                        html += '<td>' + PG.esc(l.plan_name||'—') + '</td>';
                        html += '<td>' + statusBadge(l.status) + '</td>';
                        html += '<td style="font-size:12px;color:#888;white-space:nowrap;">' + (l.created_at||'').substring(0,10) + '</td>';
                        html += '<td><button class="petsgo-btn petsgo-btn-small" onclick=\'openLead(' + JSON.stringify(l).replace(/\'/g,"&#39;") + ')\'>✏️ Gestionar</button></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    html += '<div class="petsgo-pagination-bar"><span>' + rows.length + ' lead(s) encontrado(s)</span></div>';
                    $('#leads-grid').html(html);
                });
            };

            window.openLead = function(lead) {
                currentLead = lead;
                $('#lead-modal-title').text('📩 ' + lead.store_name);
                var b = '<div style="font-size:13px;color:#555;line-height:1.8;">';
                b += '<strong>Contacto:</strong> ' + PG.esc(lead.contact_name) + '<br>';
                b += '<strong>Email:</strong> ' + PG.esc(lead.email) + '<br>';
                b += '<strong>Teléfono:</strong> ' + PG.esc(lead.phone) + '<br>';
                if (lead.comuna) b += '<strong>Comuna:</strong> ' + PG.esc(lead.comuna) + '<br>';
                if (lead.plan_name) b += '<strong>Plan:</strong> ' + PG.esc(lead.plan_name) + '<br>';
                if (lead.message) b += '<strong>Mensaje:</strong><br><div style="background:#f8f9fa;padding:10px 14px;border-radius:8px;margin:4px 0 12px;white-space:pre-wrap;">' + PG.esc(lead.message) + '</div>';
                b += '</div>';
                b += '<div style="margin-top:14px;"><label style="font-weight:700;font-size:13px;color:#333;">Estado:</label><br>';
                b += '<select id="lead-status-select" class="petsgo-select" style="width:100%;margin-top:6px;">';
                ['nuevo','contactado','contratado','declinado'].forEach(function(s){
                    b += '<option value="' + s + '"' + (lead.status===s?' selected':'') + '>' + s.charAt(0).toUpperCase()+s.slice(1) + '</option>';
                });
                b += '</select></div>';
                b += '<div style="margin-top:14px;"><label style="font-weight:700;font-size:13px;color:#333;">Notas internas:</label><br>';
                b += '<textarea id="lead-notes" class="petsgo-textarea" style="width:100%;min-height:80px;margin-top:6px;" placeholder="Notas del admin...">' + PG.esc(lead.admin_notes||'') + '</textarea></div>';
                $('#lead-modal-body').html(b);
                document.getElementById('lead-modal').style.display = 'flex';
            };

            window.saveLeadChanges = function() {
                if (!currentLead) return;
                PG.post('petsgo_update_lead', {
                    lead_id: currentLead.id,
                    status: $('#lead-status-select').val(),
                    admin_notes: $('#lead-notes').val()
                }, function(r) {
                    if (r.success) {
                        document.getElementById('lead-modal').style.display = 'none';
                        PG.toast('Lead actualizado','success');
                        searchLeads();
                    } else {
                        PG.toast(r.data||'Error','error');
                    }
                });
            };

            // Initial load
            searchLeads();
        });
        </script>
        <?php
    }

    public function petsgo_search_leads() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $sql = "SELECT * FROM {$wpdb->prefix}petsgo_leads WHERE 1=1";
        $args = [];
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= " AND (store_name LIKE %s OR contact_name LIKE %s OR email LIKE %s)";
            $args[] = $like; $args[] = $like; $args[] = $like;
        }
        if ($status) { $sql .= " AND status = %s"; $args[] = $status; }
        $sql .= " ORDER BY created_at DESC LIMIT 200";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        wp_send_json_success(['rows' => $wpdb->get_results($sql)]);
    }

    public function petsgo_update_lead() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id     = intval($_POST['lead_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $notes  = sanitize_textarea_field($_POST['admin_notes'] ?? '');
        if (!$id || !in_array($status, ['nuevo','contactado','contratado','declinado'])) wp_send_json_error('Datos inválidos');
        $wpdb->update("{$wpdb->prefix}petsgo_leads", [
            'status' => $status,
            'admin_notes' => $notes,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_leads WHERE id=%d", $id));
        $this->audit('update_lead', 'lead', $id, ($lead->store_name ?? '') . ' → ' . $status);
        wp_send_json_success(['message' => 'Lead actualizado']);
    }

    // ============================================================
    // REST API — Categorías (público)
    // ============================================================
    public function api_get_categories() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC");
        return rest_ensure_response(['data' => $rows]);
    }

    // ============================================================
    // AJAX — Categorías (admin CRUD)
    // ============================================================
    public function petsgo_search_categories() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_categories ORDER BY sort_order ASC, name ASC");
        wp_send_json_success($rows);
    }

    public function petsgo_save_category() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Solo admin puede gestionar categorías.');
        global $wpdb;
        $id   = intval($_POST['id'] ?? 0);
        $data = [
            'name'        => sanitize_text_field($_POST['name'] ?? ''),
            'slug'        => sanitize_title($_POST['slug'] ?? $_POST['name'] ?? ''),
            'emoji'       => sanitize_text_field($_POST['emoji'] ?? '📦'),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'image_url'   => esc_url_raw($_POST['image_url'] ?? ''),
            'sort_order'  => intval($_POST['sort_order'] ?? 0),
            'is_active'   => intval($_POST['is_active'] ?? 1),
        ];
        if (empty($data['name'])) wp_send_json_error('El nombre es obligatorio.');
        if ($id) {
            $wpdb->update("{$wpdb->prefix}petsgo_categories", $data, ['id' => $id]);
            $this->audit('update_category', 'category', $id, $data['name']);
        } else {
            $wpdb->insert("{$wpdb->prefix}petsgo_categories", $data);
            $id = $wpdb->insert_id;
            $this->audit('create_category', 'category', $id, $data['name']);
        }
        wp_send_json_success(['message' => 'Categoría guardada', 'id' => $id]);
    }

    public function petsgo_delete_category() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('ID inválido');
        $cat = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_categories WHERE id=%d", $id));
        $wpdb->delete("{$wpdb->prefix}petsgo_categories", ['id' => $id]);
        $this->audit('delete_category', 'category', $id, $cat->name ?? '');
        wp_send_json_success(['message' => 'Categoría eliminada']);
    }

    // ============================================================
    // ADMIN PAGE — Categorías
    // ============================================================
    public function page_categories() {
        if (!$this->is_admin()) { echo '<div class="wrap"><h1>⛔ Sin acceso</h1></div>'; return; }
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📂 Categorías del Marketplace</h1>
            <p>Gestiona las categorías de productos. Se muestran en el frontend (Header, HomePage, Categoría).</p>

            <div style="display:flex;gap:12px;align-items:center;margin:16px 0;">
                <button class="petsgo-btn petsgo-btn-primary" onclick="pgCatEdit(0)">➕ Nueva Categoría</button>
                <span class="petsgo-loader" id="cat-loader"><span class="spinner is-active" style="float:none;margin:0;"></span></span>
            </div>

            <table class="petsgo-table" id="cat-table">
                <thead>
                    <tr>
                        <th>Orden</th><th>Emoji</th><th>Nombre</th><th>Slug</th><th>Descripción</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="cat-body"></tbody>
            </table>

            <!-- Modal -->
            <div id="cat-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;justify-content:center;align-items:center;">
                <div style="background:#fff;border-radius:12px;padding:28px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;">
                    <h3 id="cat-modal-title" style="margin-top:0;">Nueva Categoría</h3>
                    <input type="hidden" id="cat-id">
                    <div class="petsgo-field"><label>Nombre *</label><input type="text" id="cat-name" maxlength="100" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;"></div>
                    <div class="petsgo-field"><label>Slug (URL)</label><input type="text" id="cat-slug" maxlength="100" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;" placeholder="se genera automáticamente"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="petsgo-field"><label>Emoji</label><input type="text" id="cat-emoji" maxlength="10" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;" value="📦"></div>
                        <div class="petsgo-field"><label>Orden</label><input type="number" id="cat-order" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;" value="0"></div>
                    </div>
                    <div class="petsgo-field"><label>Descripción</label><input type="text" id="cat-desc" maxlength="255" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;"></div>
                    <div class="petsgo-field"><label>URL Imagen</label><input type="url" id="cat-img" style="width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;" placeholder="https://..."></div>
                    <div class="petsgo-field"><label><input type="checkbox" id="cat-active" checked> Activa</label></div>
                    <div style="display:flex;gap:10px;margin-top:16px;">
                        <button class="petsgo-btn petsgo-btn-primary" onclick="pgCatSave()">💾 Guardar</button>
                        <button class="petsgo-btn" onclick="document.getElementById('cat-modal').style.display='none'" style="background:#eee;">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            function loadCats(){
                $('#cat-loader').addClass('active');
                PG.post('petsgo_search_categories',{},function(r){
                    $('#cat-loader').removeClass('active');
                    if(!r.success) return;
                    var html='';
                    $.each(r.data,function(i,c){
                        html+='<tr>';
                        html+='<td>'+c.sort_order+'</td>';
                        html+='<td style="font-size:24px;">'+c.emoji+'</td>';
                        html+='<td><strong>'+c.name+'</strong></td>';
                        html+='<td><code>'+c.slug+'</code></td>';
                        html+='<td>'+c.description+'</td>';
                        html+='<td><span class="petsgo-badge '+(c.is_active=='1'?'active':'inactive')+'">'+(c.is_active=='1'?'Activa':'Inactiva')+'</span></td>';
                        html+='<td><button class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" onclick="pgCatEdit('+c.id+')">✏️</button> ';
                        html+='<button class="petsgo-btn petsgo-btn-danger petsgo-btn-sm" onclick="pgCatDel('+c.id+',\''+c.name+'\')">🗑️</button></td>';
                        html+='</tr>';
                    });
                    $('#cat-body').html(html);
                    window._pgCats = r.data;
                });
            }
            loadCats();

            window.pgCatEdit = function(id){
                if(id){
                    var c = (window._pgCats||[]).find(function(x){return x.id==id;});
                    if(!c) return;
                    $('#cat-id').val(c.id);$('#cat-name').val(c.name);$('#cat-slug').val(c.slug);
                    $('#cat-emoji').val(c.emoji);$('#cat-order').val(c.sort_order);
                    $('#cat-desc').val(c.description);$('#cat-img').val(c.image_url);
                    $('#cat-active').prop('checked',c.is_active=='1');
                    $('#cat-modal-title').text('Editar Categoría');
                } else {
                    $('#cat-id').val('');$('#cat-name').val('');$('#cat-slug').val('');
                    $('#cat-emoji').val('📦');$('#cat-order').val(0);$('#cat-desc').val('');$('#cat-img').val('');
                    $('#cat-active').prop('checked',true);
                    $('#cat-modal-title').text('Nueva Categoría');
                }
                $('#cat-modal').css('display','flex');
            };
            window.pgCatSave = function(){
                PG.post('petsgo_save_category',{
                    id:$('#cat-id').val(),name:$('#cat-name').val(),slug:$('#cat-slug').val(),
                    emoji:$('#cat-emoji').val(),sort_order:$('#cat-order').val(),
                    description:$('#cat-desc').val(),image_url:$('#cat-img').val(),
                    is_active:$('#cat-active').is(':checked')?1:0
                },function(r){
                    if(r.success){$('#cat-modal').hide();loadCats();}else{alert(r.data);}
                });
            };
            window.pgCatDel = function(id,name){
                if(!confirm('¿Eliminar categoría "'+name+'"?')) return;
                PG.post('petsgo_delete_category',{id:id},function(r){
                    if(r.success) loadCats(); else alert(r.data);
                });
            };
        });
        </script>
        <?php
    }

    // ============================================================
    // REST API — Tickets
    // ============================================================
    private function generate_ticket_number() {
        return 'TK-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    public function api_get_categories_rest() {
        return $this->api_get_categories();
    }

    public function api_create_ticket($request) {
        global $wpdb;
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $user_role = 'cliente';
        if (in_array('petsgo_vendor', $roles)) $user_role = 'tienda';
        elseif (in_array('petsgo_rider', $roles)) $user_role = 'rider';
        elseif (in_array('administrator', $roles)) $user_role = 'admin';

        $subject = sanitize_text_field($request->get_param('subject'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $category = sanitize_text_field($request->get_param('category') ?: 'general');
        $priority = sanitize_text_field($request->get_param('priority') ?: 'media');
        if (empty($subject) || empty($description)) {
            return new WP_Error('missing_fields', 'Asunto y descripción son obligatorios.', ['status' => 400]);
        }

        $ticket_number = $this->generate_ticket_number();
        $wpdb->insert("{$wpdb->prefix}petsgo_tickets", [
            'ticket_number' => $ticket_number,
            'user_id'       => $user->ID,
            'user_name'     => $user->display_name ?: $user->user_login,
            'user_email'    => $user->user_email,
            'user_role'     => $user_role,
            'subject'       => $subject,
            'description'   => $description,
            'category'      => $category,
            'priority'      => $priority,
            'status'        => 'abierto',
        ]);
        $ticket_id = $wpdb->insert_id;

        // Send email to ticket creator
        $this->send_ticket_email_creator($ticket_number, $subject, $user->user_email, $user->display_name);
        // Send email to admins
        $this->send_ticket_email_admin($ticket_number, $subject, $description, $user->display_name, $user_role, $category, $priority);

        $this->audit('create_ticket', 'ticket', $ticket_id, $ticket_number . ' — ' . $subject);

        return rest_ensure_response([
            'success' => true,
            'ticket_number' => $ticket_number,
            'message' => 'Ticket creado exitosamente. Recibirás un correo de confirmación.',
        ]);
    }

    public function api_get_tickets($request) {
        global $wpdb;
        $user = wp_get_current_user();
        $is_admin = current_user_can('administrator');
        if ($is_admin) {
            $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}petsgo_tickets ORDER BY created_at DESC LIMIT 200");
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}petsgo_tickets WHERE user_id=%d ORDER BY created_at DESC", $user->ID
            ));
        }
        return rest_ensure_response(['data' => $rows]);
    }

    public function api_get_ticket_detail($request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $user = wp_get_current_user();
        $is_admin = current_user_can('administrator');
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_tickets WHERE id=%d", $id));
        if (!$ticket) return new WP_Error('not_found', 'Ticket no encontrado', ['status' => 404]);
        if (!$is_admin && $ticket->user_id != $user->ID) return new WP_Error('forbidden','Sin acceso',['status'=>403]);
        $replies = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}petsgo_ticket_replies WHERE ticket_id=%d ORDER BY created_at ASC", $id
        ));
        return rest_ensure_response(['ticket' => $ticket, 'replies' => $replies]);
    }

    public function api_add_ticket_reply_rest($request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        $message = sanitize_textarea_field($request->get_param('message'));
        if (empty($message)) return new WP_Error('empty', 'El mensaje es obligatorio.', ['status' => 400]);
        $user = wp_get_current_user();
        $is_admin = current_user_can('administrator');
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_tickets WHERE id=%d", $id));
        if (!$ticket) return new WP_Error('not_found', 'Ticket no encontrado', ['status' => 404]);
        if (!$is_admin && $ticket->user_id != $user->ID) return new WP_Error('forbidden','Sin acceso',['status'=>403]);

        $roles = (array) $user->roles;
        $role_label = 'cliente';
        if (in_array('administrator', $roles)) $role_label = 'admin';
        elseif (in_array('petsgo_vendor', $roles)) $role_label = 'tienda';
        elseif (in_array('petsgo_rider', $roles)) $role_label = 'rider';

        $wpdb->insert("{$wpdb->prefix}petsgo_ticket_replies", [
            'ticket_id' => $id,
            'user_id'   => $user->ID,
            'user_name' => $user->display_name ?: $user->user_login,
            'user_role' => $role_label,
            'message'   => $message,
        ]);
        // If admin replies, mark as en_proceso
        if ($is_admin && $ticket->status === 'abierto') {
            $wpdb->update("{$wpdb->prefix}petsgo_tickets", ['status' => 'en_proceso'], ['id' => $id]);
        }
        return rest_ensure_response(['success' => true, 'message' => 'Respuesta agregada']);
    }

    // ============================================================
    // AJAX — Tickets (admin portal)
    // ============================================================
    public function petsgo_search_tickets() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $status   = sanitize_text_field($_POST['status'] ?? '');
        $search   = sanitize_text_field($_POST['search'] ?? '');
        $page     = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(5, min(100, intval($_POST['per_page'] ?? 20)));
        $tbl = "{$wpdb->prefix}petsgo_tickets";

        // --- build WHERE ---
        $where = " WHERE 1=1";
        $args  = [];
        if ($status === 'todos') {
            // include all statuses
        } elseif ($status) {
            $where .= " AND status=%s"; $args[] = $status;
        } else {
            // default: exclude cerrado
            $where .= " AND status != 'cerrado'";
        }
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (ticket_number LIKE %s OR subject LIKE %s OR user_name LIKE %s)";
            $args[] = $like; $args[] = $like; $args[] = $like;
        }

        // --- total count for pagination ---
        $count_sql = "SELECT COUNT(*) FROM {$tbl}{$where}";
        if ($args) $count_sql = $wpdb->prepare($count_sql, ...$args);
        $total = (int) $wpdb->get_var($count_sql);
        $total_pages = max(1, ceil($total / $per_page));
        if ($page > $total_pages) $page = $total_pages;
        $offset = ($page - 1) * $per_page;

        // --- fetch page ---
        $sql = "SELECT * FROM {$tbl}{$where} ORDER BY FIELD(status,'abierto','en_proceso','resuelto','cerrado'), created_at DESC LIMIT {$per_page} OFFSET {$offset}";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($sql);

        // --- global stats (always from ALL tickets) ---
        $stats = [];
        $st_rows = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$tbl} GROUP BY status");
        foreach (['abierto','en_proceso','resuelto','cerrado'] as $s) $stats[$s] = 0;
        foreach ($st_rows as $sr) $stats[$sr->status] = (int) $sr->cnt;

        wp_send_json_success([
            'tickets'     => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'stats'       => $stats,
        ]);
    }

    public function petsgo_update_ticket() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id     = intval($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        if (!$id || !in_array($status, ['abierto','en_proceso','resuelto','cerrado'])) wp_send_json_error('Datos inválidos');
        $data = ['status' => $status];
        if ($status === 'resuelto' || $status === 'cerrado') $data['resolved_at'] = current_time('mysql');
        $wpdb->update("{$wpdb->prefix}petsgo_tickets", $data, ['id' => $id]);
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_tickets WHERE id=%d", $id));
        $this->audit('update_ticket', 'ticket', $id, $ticket->ticket_number . ' → ' . $status);
        // Send email to user about status change
        if ($ticket) {
            $this->send_ticket_status_email($ticket->ticket_number, $ticket->subject, $ticket->user_email, $ticket->user_name, $status);
        }
        wp_send_json_success(['message' => 'Ticket actualizado']);
    }

    public function petsgo_assign_ticket() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        if (!$id) wp_send_json_error('ID inválido');
        $assigned_user = $assigned_to ? get_userdata($assigned_to) : null;
        $wpdb->update("{$wpdb->prefix}petsgo_tickets", [
            'assigned_to'   => $assigned_to ?: null,
            'assigned_name' => $assigned_user ? $assigned_user->display_name : '',
            'status'        => 'en_proceso',
        ], ['id' => $id]);
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_tickets WHERE id=%d", $id));
        $this->audit('assign_ticket', 'ticket', $id, $ticket->ticket_number . ' → ' . ($assigned_user ? $assigned_user->display_name : 'sin asignar'));
        // Send email to assigned person
        if ($assigned_user && $ticket) {
            $this->send_ticket_assigned_email($ticket->ticket_number, $ticket->subject, $ticket->description, $assigned_user->user_email, $assigned_user->display_name, $ticket->user_name);
        }
        wp_send_json_success(['message' => 'Ticket asignado']);
    }

    public function petsgo_add_ticket_reply() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $id = intval($_POST['ticket_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        if (!$id || !$message) wp_send_json_error('Datos inválidos');
        $user = wp_get_current_user();
        $wpdb->insert("{$wpdb->prefix}petsgo_ticket_replies", [
            'ticket_id' => $id,
            'user_id'   => $user->ID,
            'user_name' => $user->display_name ?: $user->user_login,
            'user_role' => 'admin',
            'message'   => $message,
        ]);
        // Update ticket status
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}petsgo_tickets WHERE id=%d", $id));
        if ($ticket && $ticket->status === 'abierto') {
            $wpdb->update("{$wpdb->prefix}petsgo_tickets", ['status' => 'en_proceso'], ['id' => $id]);
        }
        // Notify ticket creator
        if ($ticket) {
            $this->send_ticket_reply_email($ticket->ticket_number, $ticket->subject, $ticket->user_email, $ticket->user_name, $message);
        }
        $this->audit('reply_ticket', 'ticket', $id, $ticket->ticket_number ?? '');
        wp_send_json_success(['message' => 'Respuesta enviada']);
    }

    public function petsgo_ticket_count() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin() && !$this->is_support()) wp_send_json_error('Sin permisos');
        global $wpdb;
        $tbl = "{$wpdb->prefix}petsgo_tickets";
        $open = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$tbl} WHERE status IN ('abierto','en_proceso')");
        $latest = $wpdb->get_row("SELECT id, ticket_number, subject, user_name, user_role, priority, created_at FROM {$tbl} ORDER BY id DESC LIMIT 1");
        // Pending tickets list for startup modal (last 15)
        $pending = $wpdb->get_results("SELECT id, ticket_number, subject, user_name, user_role, priority, status, created_at FROM {$tbl} WHERE status IN ('abierto','en_proceso') ORDER BY FIELD(priority,'urgente','alta','media','baja'), created_at DESC LIMIT 15");
        wp_send_json_success([
            'count'   => $open,
            'latest'  => $latest,
            'pending' => $pending,
        ]);
    }

    /**
     * Global admin toast notification + startup modal for new/pending tickets.
     * Injected on ALL admin pages via admin_footer hook.
     * Visible only to admin and support roles.
     */
    public function admin_ticket_notifier() {
        if (!$this->is_admin() && !$this->is_support()) return;
        $nonce = wp_create_nonce('petsgo_ajax');
        $tickets_url = admin_url('admin.php?page=petsgo-tickets');
        ?>
        <style>
        #pg-ticket-toast-wrap{position:fixed;top:40px;right:24px;z-index:160000;display:flex;flex-direction:column;gap:10px;pointer-events:none}
        .pg-ticket-toast{pointer-events:auto;background:#fff;border-left:5px solid #00A8E8;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);padding:16px 50px 16px 18px;max-width:420px;min-width:300px;animation:pgToastIn .4s ease;position:relative;font-family:'Poppins','Segoe UI',sans-serif}
        .pg-ticket-toast.urgent{border-left-color:#dc3545}
        .pg-ticket-toast.alta{border-left-color:#cc5500}
        .pg-ticket-toast .pg-toast-close{position:absolute;top:8px;right:10px;background:none;border:none;font-size:18px;cursor:pointer;color:#999;padding:0;line-height:1}
        .pg-ticket-toast .pg-toast-close:hover{color:#333}
        .pg-ticket-toast .pg-toast-icon{font-size:22px;margin-right:10px;vertical-align:middle}
        .pg-ticket-toast .pg-toast-title{font-weight:700;font-size:14px;color:#2F3A40;margin-bottom:4px}
        .pg-ticket-toast .pg-toast-body{font-size:13px;color:#555;line-height:1.4;margin-bottom:8px}
        .pg-ticket-toast .pg-toast-body strong{color:#333}
        .pg-ticket-toast .pg-toast-link{display:inline-block;background:#00A8E8;color:#fff;padding:5px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;transition:.2s}
        .pg-ticket-toast .pg-toast-link:hover{background:#0090c7;color:#fff}
        .pg-ticket-toast .pg-toast-time{font-size:11px;color:#aaa;position:absolute;bottom:8px;right:12px}
        .pg-ticket-toast.hiding{animation:pgToastOut .35s ease forwards}
        @keyframes pgToastIn{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}
        @keyframes pgToastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(60px)}}
        /* ── Startup Modal: Pending Tickets ── */
        #pg-pending-modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:170000;display:flex;align-items:center;justify-content:center;animation:pgModalFadeIn .3s ease}
        #pg-pending-modal{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:660px;max-width:94vw;max-height:82vh;display:flex;flex-direction:column;animation:pgModalSlideIn .35s ease;font-family:'Poppins','Segoe UI',sans-serif}
        #pg-pending-modal .pgm-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid #eee}
        #pg-pending-modal .pgm-header h2{margin:0;font-size:18px;color:#2F3A40;font-weight:700}
        #pg-pending-modal .pgm-header .pgm-badge{background:#dc3545;color:#fff;padding:3px 10px;border-radius:12px;font-size:13px;font-weight:700;margin-left:10px}
        #pg-pending-modal .pgm-header .pgm-close{background:none;border:none;font-size:24px;cursor:pointer;color:#999;padding:0 4px;line-height:1}
        #pg-pending-modal .pgm-header .pgm-close:hover{color:#333}
        #pg-pending-modal .pgm-body{padding:12px 24px 8px;overflow-y:auto;flex:1}
        #pg-pending-modal .pgm-empty{text-align:center;padding:40px 20px;color:#999;font-size:15px}
        #pg-pending-modal .pgm-ticket-row{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;transition:background .15s;border-bottom:1px solid #f2f2f2}
        #pg-pending-modal .pgm-ticket-row:hover{background:#f0f9ff}
        #pg-pending-modal .pgm-ticket-row:last-child{border-bottom:none}
        #pg-pending-modal .pgm-pri{width:10px;height:10px;border-radius:50%;flex-shrink:0}
        #pg-pending-modal .pgm-pri.baja{background:#28a745}
        #pg-pending-modal .pgm-pri.media{background:#ffc107}
        #pg-pending-modal .pgm-pri.alta{background:#cc5500}
        #pg-pending-modal .pgm-pri.urgente{background:#dc3545;box-shadow:0 0 6px rgba(220,53,69,.5)}
        #pg-pending-modal .pgm-info{flex:1;min-width:0}
        #pg-pending-modal .pgm-info .pgm-num{font-weight:700;font-size:13px;color:#00A8E8;margin-right:6px}
        #pg-pending-modal .pgm-info .pgm-subj{font-size:13px;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
        #pg-pending-modal .pgm-info .pgm-meta{font-size:11px;color:#999;margin-top:2px}
        #pg-pending-modal .pgm-status{font-size:11px;padding:3px 10px;border-radius:12px;font-weight:600;white-space:nowrap}
        #pg-pending-modal .pgm-status.abierto{background:#fff3cd;color:#856404}
        #pg-pending-modal .pgm-status.en_proceso{background:#cce5ff;color:#004085}
        #pg-pending-modal .pgm-footer{padding:14px 24px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
        #pg-pending-modal .pgm-footer .pgm-btn{padding:8px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:.2s}
        #pg-pending-modal .pgm-footer .pgm-btn-primary{background:#00A8E8;color:#fff}
        #pg-pending-modal .pgm-footer .pgm-btn-primary:hover{background:#0090c7}
        #pg-pending-modal .pgm-footer .pgm-btn-secondary{background:#f0f0f0;color:#555}
        #pg-pending-modal .pgm-footer .pgm-btn-secondary:hover{background:#e0e0e0}
        @keyframes pgModalFadeIn{from{opacity:0}to{opacity:1}}
        @keyframes pgModalSlideIn{from{opacity:0;transform:translateY(-30px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
        #pg-pending-modal-overlay.hiding{animation:pgModalFadeOut .25s ease forwards}
        #pg-pending-modal-overlay.hiding #pg-pending-modal{animation:pgModalSlideOut .25s ease forwards}
        @keyframes pgModalFadeOut{from{opacity:1}to{opacity:0}}
        @keyframes pgModalSlideOut{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(-20px) scale(.96)}}
        </style>
        <div id="pg-ticket-toast-wrap"></div>
        <script>
        (function(){
            if(typeof jQuery==='undefined') return;
            var nonce='<?php echo $nonce; ?>',
                ajaxUrl='<?php echo admin_url("admin-ajax.php"); ?>',
                ticketsUrl='<?php echo esc_js($tickets_url); ?>',
                storageKey='pgLastTicketId',
                modalSessionKey='pgPendingModalShown',
                wrap=document.getElementById('pg-ticket-toast-wrap');

            function getLastId(){ return parseInt(localStorage.getItem(storageKey)||'0',10); }
            function setLastId(id){ localStorage.setItem(storageKey, String(id)); }

            // ── Startup Modal ──
            function showPendingModal(pending, totalOpen){
                if(!pending||!pending.length) return;
                // Only show once per session
                if(sessionStorage.getItem(modalSessionKey)) return;
                sessionStorage.setItem(modalSessionKey,'1');

                var priLabels={baja:'Baja',media:'Media',alta:'Alta',urgente:'Urgente'};
                var statusLabels={abierto:'Abierto',en_proceso:'En Proceso'};
                var roleLabels={cliente:'Cliente',tienda:'Tienda',rider:'Rider',admin:'Admin',support:'Soporte'};

                var rows='';
                for(var i=0;i<pending.length;i++){
                    var t=pending[i];
                    var subj=t.subject.length>45?t.subject.substring(0,45)+'…':t.subject;
                    var dateStr=new Date(t.created_at).toLocaleDateString('es-CL',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
                    rows+='<div class="pgm-ticket-row">'+
                        '<span class="pgm-pri '+(t.priority||'media')+'" title="'+(priLabels[t.priority]||t.priority)+'"></span>'+
                        '<div class="pgm-info">'+
                            '<span class="pgm-subj"><span class="pgm-num">'+t.ticket_number+'</span>'+subj+'</span>'+
                            '<span class="pgm-meta">'+t.user_name+' ('+(roleLabels[t.user_role]||t.user_role)+') &middot; '+dateStr+'</span>'+
                        '</div>'+
                        '<span class="pgm-status '+(t.status||'')+'">'+(statusLabels[t.status]||t.status)+'</span>'+
                    '</div>';
                }

                var overlay=document.createElement('div');
                overlay.id='pg-pending-modal-overlay';
                overlay.innerHTML=
                    '<div id="pg-pending-modal">'+
                        '<div class="pgm-header">'+
                            '<h2>🎫 Tickets Pendientes<span class="pgm-badge">'+totalOpen+'</span></h2>'+
                            '<button class="pgm-close" title="Cerrar">&times;</button>'+
                        '</div>'+
                        '<div class="pgm-body">'+rows+'</div>'+
                        '<div class="pgm-footer">'+
                            '<button class="pgm-btn pgm-btn-secondary" id="pgm-dismiss">Cerrar</button>'+
                            '<a href="'+ticketsUrl+'" class="pgm-btn pgm-btn-primary">🎫 Ir a Tickets</a>'+
                        '</div>'+
                    '</div>';
                document.body.appendChild(overlay);

                function closeModal(){
                    overlay.classList.add('hiding');
                    setTimeout(function(){ if(overlay.parentNode) overlay.parentNode.removeChild(overlay); },300);
                }
                overlay.querySelector('.pgm-close').addEventListener('click',closeModal);
                overlay.querySelector('#pgm-dismiss').addEventListener('click',closeModal);
                overlay.addEventListener('click',function(e){ if(e.target===overlay) closeModal(); });
            }

            // ── Toast ──
            function showToast(ticket){
                var priBorder = ticket.priority==='urgente'?'urgent':(ticket.priority==='alta'?'alta':'');
                var priLabel = {baja:'🟢 Baja',media:'🟡 Media',alta:'🟠 Alta',urgente:'🔴 Urgente'};
                var roleLabel = {cliente:'Cliente',tienda:'Tienda',rider:'Rider',admin:'Admin',support:'Soporte'};
                var subj = ticket.subject.length>50 ? ticket.subject.substring(0,50)+'…' : ticket.subject;
                var div = document.createElement('div');
                div.className = 'pg-ticket-toast' + (priBorder?' '+priBorder:'');
                div.innerHTML =
                    '<button class="pg-toast-close" title="Cerrar">&times;</button>'+
                    '<div class="pg-toast-title"><span class="pg-toast-icon">🎫</span> Nuevo Ticket: '+ticket.ticket_number+'</div>'+
                    '<div class="pg-toast-body">'+
                        '<strong>'+ticket.user_name+'</strong> ('+(roleLabel[ticket.user_role]||ticket.user_role)+')<br>'+
                        subj+'<br>'+
                        'Prioridad: '+(priLabel[ticket.priority]||ticket.priority)+
                    '</div>'+
                    '<a href="'+ticketsUrl+'" class="pg-toast-link">🎫 Ver Tickets</a>'+
                    '<span class="pg-toast-time">'+new Date(ticket.created_at).toLocaleString('es-CL')+'</span>';
                wrap.appendChild(div);
                div.querySelector('.pg-toast-close').addEventListener('click',function(){ dismissToast(div); });
                setTimeout(function(){ dismissToast(div); }, 20000);
                try{
                    var ctx=new(window.AudioContext||window.webkitAudioContext)();
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);
                    o.type='sine';o.frequency.setValueAtTime(880,ctx.currentTime);
                    o.frequency.setValueAtTime(1100,ctx.currentTime+0.1);
                    g.gain.setValueAtTime(0.15,ctx.currentTime);
                    g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.5);
                    o.start(ctx.currentTime);o.stop(ctx.currentTime+0.5);
                }catch(e){}
            }

            function dismissToast(el){
                if(el._dismissed) return;
                el._dismissed=true;
                el.classList.add('hiding');
                setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 400);
            }

            var firstPoll=true;
            function pollTickets(){
                jQuery.post(ajaxUrl,{action:'petsgo_ticket_count',_ajax_nonce:nonce},function(r){
                    if(!r||!r.success||!r.data) return;

                    // Startup modal — show on first poll if pending tickets exist
                    if(firstPoll && r.data.pending && r.data.pending.length>0){
                        showPendingModal(r.data.pending, r.data.count);
                    }
                    firstPoll=false;

                    if(!r.data.latest) return;
                    var latestId = parseInt(r.data.latest.id,10);
                    var lastId = getLastId();
                    if(!lastId){ setLastId(latestId); return; }
                    if(latestId > lastId){
                        showToast(r.data.latest);
                        setLastId(latestId);
                    }
                    // Update badge in menu
                    var count = r.data.count || 0;
                    var badge = document.getElementById('petsgo-ticket-badge-global');
                    var menuLink = document.querySelector('a[href*="petsgo-tickets"]');
                    if(count > 0 && menuLink){
                        if(!badge){
                            badge = document.createElement('span');
                            badge.id = 'petsgo-ticket-badge-global';
                            badge.style.cssText = 'background:#dc3545;color:#fff;border-radius:50%;padding:2px 7px;font-size:11px;font-weight:700;margin-left:6px;';
                            menuLink.appendChild(badge);
                        }
                        badge.textContent = count;
                    } else if(badge){
                        badge.textContent = '';
                    }
                });
            }
            // Initial poll after 2 seconds, then every 30 seconds
            setTimeout(pollTickets, 2000);
            setInterval(pollTickets, 30000);
        })();
        </script>
        <?php
    }

    // ============================================================
    // EMAIL — Tickets
    // ============================================================
    private function send_ticket_email_creator($ticket_number, $subject, $email, $name) {
        $inner = '<h2 style="color:#00A8E8;margin:0 0 16px;">🎫 Ticket Creado: '.$ticket_number.'</h2>
        <p>Hola <strong>'.esc_html($name).'</strong>,</p>
        <p>Tu solicitud ha sido recibida exitosamente. Nuestro equipo revisará tu caso y te responderá a la brevedad.</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">'.$ticket_number.'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($subject).'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Estado</td><td style="padding:8px 12px;border:1px solid #eee;"><span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Abierto</span></td></tr>
        </table>
        <p style="color:#888;font-size:13px;">Recibirás notificaciones por correo cuando haya actualizaciones en tu ticket.</p>';
        $html = $this->email_wrap($inner, '🎫 Ticket Creado — ' . $ticket_number);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $bcc = $this->pg_setting('tickets_bcc_email', '');
        if ($bcc) $headers[] = 'Bcc: ' . $bcc;
        wp_mail($email, 'PetsGo — Ticket ' . $ticket_number . ' creado', $html, $headers);
    }

    private function send_ticket_email_admin($ticket_number, $subject, $description, $user_name, $user_role, $category, $priority) {
        $role_labels = ['cliente'=>'Cliente','tienda'=>'Tienda','rider'=>'Rider','admin'=>'Admin'];
        $priority_labels = ['baja'=>'🟢 Baja','media'=>'🟡 Media','alta'=>'🟠 Alta','urgente'=>'🔴 Urgente'];
        $inner = '<h2 style="color:#dc3545;margin:0 0 16px;">🔔 Nuevo Ticket: '.$ticket_number.'</h2>
        <p>Se ha recibido un nuevo ticket de soporte.</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">'.$ticket_number.'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Solicitante</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($user_name).' <span style="background:#e2e3e5;padding:2px 8px;border-radius:10px;font-size:11px;">'.($role_labels[$user_role]??$user_role).'</span></td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($subject).'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Categoría</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($category).'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Prioridad</td><td style="padding:8px 12px;border:1px solid #eee;">'.($priority_labels[$priority]??$priority).'</td></tr>
        </table>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:16px 0;">
            <p style="font-weight:600;margin:0 0 8px;">Descripción:</p>
            <p style="margin:0;">'.nl2br(esc_html($description)).'</p>
        </div>
        <p><a href="'.admin_url('admin.php?page=petsgo-tickets').'" style="display:inline-block;background:#00A8E8;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;">Ver en Panel Admin</a></p>';
        $html = $this->email_wrap($inner, '🔔 Nuevo Ticket — ' . $ticket_number);
        $admin_email = $this->pg_setting('company_email', 'contacto@petsgo.cl');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $bcc = $this->pg_setting('tickets_bcc_email', '');
        if ($bcc) $headers[] = 'Bcc: ' . $bcc;
        wp_mail($admin_email, 'PetsGo — Nuevo Ticket ' . $ticket_number, $html, $headers);
    }

    private function send_ticket_status_email($ticket_number, $subject, $email, $name, $status) {
        $status_labels = ['abierto'=>'Abierto','en_proceso'=>'En Proceso','resuelto'=>'Resuelto','cerrado'=>'Cerrado'];
        $status_colors = ['abierto'=>'#fff3cd;color:#856404','en_proceso'=>'#cce5ff;color:#004085','resuelto'=>'#d4edda;color:#155724','cerrado'=>'#e2e3e5;color:#383d41'];
        $inner = '<h2 style="color:#00A8E8;margin:0 0 16px;">📋 Actualización de Ticket: '.$ticket_number.'</h2>
        <p>Hola <strong>'.esc_html($name).'</strong>,</p>
        <p>Tu ticket ha sido actualizado:</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">'.$ticket_number.'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($subject).'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Nuevo Estado</td><td style="padding:8px 12px;border:1px solid #eee;"><span style="background:'.($status_colors[$status]??'#e2e3e5;color:#383d41').';padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">'.($status_labels[$status]??$status).'</span></td></tr>
        </table>';
        $html = $this->email_wrap($inner, '📋 Ticket Actualizado — ' . $ticket_number);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $bcc = $this->pg_setting('tickets_bcc_email', '');
        if ($bcc) $headers[] = 'Bcc: ' . $bcc;
        wp_mail($email, 'PetsGo — Ticket ' . $ticket_number . ' actualizado', $html, $headers);
    }

    private function send_ticket_assigned_email($ticket_number, $subject, $description, $email, $assigned_name, $requester_name) {
        $inner = '<h2 style="color:#FFC400;margin:0 0 16px;">📌 Ticket Asignado: '.$ticket_number.'</h2>
        <p>Hola <strong>'.esc_html($assigned_name).'</strong>,</p>
        <p>Se te ha asignado el siguiente ticket de soporte:</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">'.$ticket_number.'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Solicitante</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($requester_name).'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($subject).'</td></tr>
        </table>
        <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:16px 0;">
            <p style="font-weight:600;margin:0 0 8px;">Descripción:</p>
            <p style="margin:0;">'.nl2br(esc_html($description)).'</p>
        </div>
        <p><a href="'.admin_url('admin.php?page=petsgo-tickets').'" style="display:inline-block;background:#00A8E8;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;">Gestionar Ticket</a></p>';
        $html = $this->email_wrap($inner, '📌 Ticket Asignado — ' . $ticket_number);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $bcc = $this->pg_setting('tickets_bcc_email', '');
        if ($bcc) $headers[] = 'Bcc: ' . $bcc;
        wp_mail($email, 'PetsGo — Te han asignado el Ticket ' . $ticket_number, $html, $headers);
    }

    private function send_ticket_reply_email($ticket_number, $subject, $email, $name, $reply_message) {
        $inner = '<h2 style="color:#00A8E8;margin:0 0 16px;">💬 Nueva Respuesta en Ticket: '.$ticket_number.'</h2>
        <p>Hola <strong>'.esc_html($name).'</strong>,</p>
        <p>Has recibido una nueva respuesta en tu ticket:</p>
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">N° Ticket</td><td style="padding:8px 12px;border:1px solid #eee;">'.$ticket_number.'</td></tr>
            <tr><td style="padding:8px 12px;background:#f8f9fa;font-weight:600;border:1px solid #eee;">Asunto</td><td style="padding:8px 12px;border:1px solid #eee;">'.esc_html($subject).'</td></tr>
        </table>
        <div style="background:#f0f8ff;border-left:4px solid #00A8E8;border-radius:6px;padding:16px;margin:16px 0;">
            <p style="margin:0;">'.nl2br(esc_html($reply_message)).'</p>
        </div>
        <p style="color:#888;font-size:13px;">Puedes responder desde tu panel en PetsGo.</p>';
        $html = $this->email_wrap($inner, '💬 Respuesta en Ticket — ' . $ticket_number);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $bcc = $this->pg_setting('tickets_bcc_email', '');
        if ($bcc) $headers[] = 'Bcc: ' . $bcc;
        wp_mail($email, 'PetsGo — Respuesta en Ticket ' . $ticket_number, $html, $headers);
    }

    // ============================================================
    // ADMIN PAGE — Tickets
    // ============================================================
    public function page_tickets() {
        if (!$this->is_admin()) { echo '<div class="wrap"><h1>⛔ Sin acceso</h1></div>'; return; }
        global $wpdb;
        $admins = get_users(['role' => 'administrator', 'fields' => ['ID','display_name']]);
        ?>
        <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>
        <div class="wrap petsgo-wrap">
            <h1>🎫 Tickets de Soporte</h1>
            <p>Gestiona las solicitudes de clientes, tiendas y riders.</p>

            <div class="petsgo-search-bar" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <input type="text" id="tk-search" placeholder="Buscar por N°, asunto o nombre..." style="flex:1;min-width:200px;">
                <select id="tk-status-filter">
                    <option value="">Activos (sin cerrados)</option>
                    <option value="abierto">🟡 Abierto</option>
                    <option value="en_proceso">🔵 En Proceso</option>
                    <option value="resuelto">🟢 Resuelto</option>
                    <option value="cerrado">⚫ Cerrado</option>
                    <option value="todos">📋 Todos los estados</option>
                </select>
                <select id="tk-per-page" style="width:auto;">
                    <option value="10">10 / pág</option>
                    <option value="20" selected>20 / pág</option>
                    <option value="50">50 / pág</option>
                    <option value="100">100 / pág</option>
                </select>
                <button class="petsgo-btn petsgo-btn-primary" onclick="loadTickets(1)">🔍 Buscar</button>
                <button class="petsgo-btn" onclick="pgExportTicketsPDF()" style="background:#dc3545;color:#fff;" title="Exportar resultados a PDF">📄 PDF</button>
            </div>

            <!-- Stats -->
            <div class="petsgo-cards" id="tk-stats" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;"></div>

            <table class="petsgo-table" id="tk-table">
                <thead>
                    <tr>
                        <th>N° Ticket</th><th>Solicitante</th><th>Rol</th><th>Asunto</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Asignado</th><th>Fecha</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tk-body"></tbody>
            </table>

            <!-- Pagination -->
            <div id="tk-pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px;">
                <span id="tk-page-info" style="font-size:13px;color:#666;"></span>
                <div id="tk-page-btns" style="display:flex;gap:4px;flex-wrap:wrap;"></div>
            </div>

            <!-- Detail Modal -->
            <div id="tk-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;justify-content:center;align-items:center;">
                <div style="background:#fff;border-radius:12px;padding:28px;max-width:700px;width:95%;max-height:85vh;overflow-y:auto;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 id="tk-modal-title" style="margin:0;">Detalle Ticket</h3>
                        <button onclick="document.getElementById('tk-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">✕</button>
                    </div>
                    <div id="tk-detail"></div>
                    <hr style="margin:20px 0;">
                    <h4>💬 Respuestas</h4>
                    <div id="tk-replies" style="max-height:250px;overflow-y:auto;margin-bottom:16px;"></div>
                    <div style="display:flex;gap:8px;">
                        <input type="hidden" id="tk-reply-id">
                        <textarea id="tk-reply-msg" rows="3" style="flex:1;padding:10px;border:1px solid #ccc;border-radius:8px;" placeholder="Escribe una respuesta..."></textarea>
                        <button class="petsgo-btn petsgo-btn-primary" onclick="pgTicketReply()" style="align-self:flex-end;">📤 Enviar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            var admins = <?php echo json_encode($admins); ?>;
            var _tkPage = 1;

            function loadTickets(page){
                _tkPage = page || _tkPage || 1;
                var perPage = parseInt($('#tk-per-page').val()) || 20;
                PG.post('petsgo_search_tickets',{status:$('#tk-status-filter').val(),search:$('#tk-search').val(),page:_tkPage,per_page:perPage},function(r){
                    if(!r.success) return;
                    var resp = r.data;
                    var data = resp.tickets;
                    var stats = resp.stats;
                    var totalAll = (stats.abierto||0)+(stats.en_proceso||0)+(stats.resuelto||0)+(stats.cerrado||0);
                    var html='';
                    var roleBadge={cliente:'background:#cce5ff;color:#004085',tienda:'background:#d4edda;color:#155724',rider:'background:#fff3cd;color:#856404',admin:'background:#f8d7da;color:#721c24'};
                    var priBadge={baja:'background:#d4edda;color:#155724',media:'background:#fff3cd;color:#856404',alta:'background:#ffe0cc;color:#cc5500',urgente:'background:#f8d7da;color:#721c24'};
                    var stBadge={abierto:'background:#fff3cd;color:#856404',en_proceso:'background:#cce5ff;color:#004085',resuelto:'background:#d4edda;color:#155724',cerrado:'background:#e2e3e5;color:#383d41'};
                    var stLabel={abierto:'Abierto',en_proceso:'En Proceso',resuelto:'Resuelto',cerrado:'Cerrado'};
                    $.each(data,function(i,t){
                        var assignOpts='<option value="">Sin asignar</option>';
                        $.each(admins,function(_,a){ assignOpts+='<option value="'+a.ID+'" '+(t.assigned_to==a.ID?'selected':'')+'>'+a.display_name+'</option>'; });
                        html+='<tr>';
                        html+='<td><strong style="color:#00A8E8;">'+t.ticket_number+'</strong></td>';
                        html+='<td>'+t.user_name+'<br><small style="color:#999;">'+t.user_email+'</small></td>';
                        html+='<td><span class="petsgo-badge" style="'+(roleBadge[t.user_role]||'')+'">'+(t.user_role||'')+'</span></td>';
                        html+='<td style="max-width:200px;">'+t.subject+'</td>';
                        html+='<td>'+t.category+'</td>';
                        html+='<td><span class="petsgo-badge" style="'+(priBadge[t.priority]||'')+'">'+t.priority+'</span></td>';
                        html+='<td><span class="petsgo-badge" style="'+(stBadge[t.status]||'')+'">'+stLabel[t.status]+'</span></td>';
                        html+='<td><select onchange="pgTicketAssign('+t.id+',this.value)" style="padding:4px;border-radius:4px;border:1px solid #ccc;font-size:12px;">'+assignOpts+'</select></td>';
                        html+='<td style="white-space:nowrap;font-size:12px;">'+new Date(t.created_at).toLocaleDateString('es-CL')+'</td>';
                        html+='<td style="white-space:nowrap;">';
                        html+='<button class="petsgo-btn petsgo-btn-primary petsgo-btn-sm" onclick="pgTicketDetail('+t.id+')" title="Ver detalle">👁️</button> ';
                        if(t.status!='cerrado'){
                            var nextSt = t.status=='abierto'?'en_proceso':(t.status=='en_proceso'?'resuelto':'cerrado');
                            var nextLbl = nextSt=='en_proceso'?'▶️ Procesar':(nextSt=='resuelto'?'✅ Resolver':'🔒 Cerrar');
                            html+='<button class="petsgo-btn petsgo-btn-success petsgo-btn-sm" onclick="pgTicketStatus('+t.id+',\''+nextSt+'\')">'+nextLbl+'</button>';
                        }
                        html+='</td>';
                        html+='</tr>';
                    });
                    $('#tk-body').html(html||'<tr><td colspan="10" style="text-align:center;color:#999;padding:30px;">No hay tickets</td></tr>');

                    // Stats cards (global – always from all tickets)
                    $('#tk-stats').html(
                        '<div class="petsgo-card" style="border-left:4px solid #FFC400;"><h2>'+(stats.abierto||0)+'</h2><p>🟡 Abiertos</p></div>'+
                        '<div class="petsgo-card" style="border-left:4px solid #00A8E8;"><h2>'+(stats.en_proceso||0)+'</h2><p>🔵 En Proceso</p></div>'+
                        '<div class="petsgo-card" style="border-left:4px solid #28a745;"><h2>'+(stats.resuelto||0)+'</h2><p>🟢 Resueltos</p></div>'+
                        '<div class="petsgo-card" style="border-left:4px solid #6c757d;"><h2>'+(stats.cerrado||0)+'</h2><p>⚫ Cerrados</p></div>'+
                        '<div class="petsgo-card" style="border-left:4px solid #17a2b8;"><h2>'+totalAll+'</h2><p>📋 Total</p></div>'
                    );

                    // Pagination
                    var from = resp.total ? ((resp.page-1)*resp.per_page+1) : 0;
                    var to   = Math.min(resp.page*resp.per_page, resp.total);
                    $('#tk-page-info').text('Mostrando '+from+' – '+to+' de '+resp.total+' ticket'+(resp.total!=1?'s':''));
                    var pb='';
                    if(resp.total_pages > 1){
                        pb+='<button class="petsgo-btn petsgo-btn-sm" '+(resp.page<=1?'disabled':'')+' onclick="loadTickets('+(resp.page-1)+')">◀ Anterior</button>';
                        var maxBtns=7, startP=Math.max(1,resp.page-3), endP=Math.min(resp.total_pages,startP+maxBtns-1);
                        if(endP-startP<maxBtns-1) startP=Math.max(1,endP-maxBtns+1);
                        if(startP>1) pb+='<button class="petsgo-btn petsgo-btn-sm" onclick="loadTickets(1)">1</button><span style="padding:0 4px;">…</span>';
                        for(var p=startP;p<=endP;p++){
                            pb+='<button class="petsgo-btn petsgo-btn-sm'+(p==resp.page?' petsgo-btn-primary':'')+'" onclick="loadTickets('+p+')">'+p+'</button>';
                        }
                        if(endP<resp.total_pages) pb+='<span style="padding:0 4px;">…</span><button class="petsgo-btn petsgo-btn-sm" onclick="loadTickets('+resp.total_pages+')">'+resp.total_pages+'</button>';
                        pb+='<button class="petsgo-btn petsgo-btn-sm" '+(resp.page>=resp.total_pages?'disabled':'')+' onclick="loadTickets('+(resp.page+1)+')">Siguiente ▶</button>';
                    }
                    $('#tk-page-btns').html(pb);

                    window._pgTickets = data;
                    window._pgTicketsMeta = resp;
                });
            }
            window.loadTickets = loadTickets;
            loadTickets(1);

            // Reset page on filter change
            $('#tk-status-filter,#tk-per-page').on('change',function(){ loadTickets(1); });
            $('#tk-search').on('keydown',function(e){ if(e.key==='Enter') loadTickets(1); });

            window.pgTicketStatus = function(id,status){
                PG.post('petsgo_update_ticket',{id:id,status:status},function(r){
                    if(r.success) loadTickets(); else alert(r.data);
                });
            };
            window.pgTicketAssign = function(id,userId){
                PG.post('petsgo_assign_ticket',{id:id,assigned_to:userId},function(r){
                    if(r.success) loadTickets();
                });
            };
            window.pgTicketDetail = function(id){
                var t = (window._pgTickets||[]).find(function(x){return x.id==id;});
                if(!t) return;
                var stLabel={abierto:'Abierto',en_proceso:'En Proceso',resuelto:'Resuelto',cerrado:'Cerrado'};
                $('#tk-modal-title').html('Ticket: <span style="color:#00A8E8;">'+t.ticket_number+'</span>');
                $('#tk-detail').html(
                    '<table style="width:100%;border-collapse:collapse;">'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Solicitante</td><td style="padding:6px 10px;border:1px solid #eee;">'+t.user_name+' ('+t.user_role+')</td></tr>'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Email</td><td style="padding:6px 10px;border:1px solid #eee;">'+t.user_email+'</td></tr>'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Asunto</td><td style="padding:6px 10px;border:1px solid #eee;">'+t.subject+'</td></tr>'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Categoría</td><td style="padding:6px 10px;border:1px solid #eee;">'+t.category+'</td></tr>'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Prioridad</td><td style="padding:6px 10px;border:1px solid #eee;">'+t.priority+'</td></tr>'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Estado</td><td style="padding:6px 10px;border:1px solid #eee;">'+(stLabel[t.status]||t.status)+'</td></tr>'+
                    '<tr><td style="padding:6px 10px;font-weight:600;background:#f8f9fa;border:1px solid #eee;">Asignado a</td><td style="padding:6px 10px;border:1px solid #eee;">'+(t.assigned_name||'Sin asignar')+'</td></tr>'+
                    '</table>'+
                    '<div style="background:#f8f9fa;border-radius:8px;padding:14px;margin-top:12px;"><p style="font-weight:600;margin:0 0 6px;">Descripción:</p><p style="margin:0;">'+t.description.replace(/\n/g,'<br>')+'</p></div>'
                );
                $('#tk-reply-id').val(id);
                var repliesHtml='<p style="color:#999;font-size:13px;">Cargando respuestas…</p>';
                $('#tk-replies').html(repliesHtml);
                // Use REST to get replies
                $.ajax({url:'/wp-json/petsgo/v1/tickets/'+id,method:'GET',beforeSend:function(xhr){var tk=localStorage.getItem('petsgo_token');if(tk)xhr.setRequestHeader('Authorization','Bearer '+tk);},success:function(r){
                    if(r.replies && r.replies.length){
                        var rh='';
                        $.each(r.replies,function(_,rp){
                            var isAdmin=rp.user_role=='admin';
                            rh+='<div style="padding:10px 14px;margin-bottom:8px;border-radius:8px;'+(isAdmin?'background:#e8f4fd;border-left:3px solid #00A8E8;':'background:#f8f9fa;border-left:3px solid #ccc;')+'">';
                            rh+='<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><strong style="font-size:13px;">'+rp.user_name+' <span style="color:#999;font-weight:400;">('+rp.user_role+')</span></strong><span style="font-size:11px;color:#999;">'+new Date(rp.created_at).toLocaleString('es-CL')+'</span></div>';
                            rh+='<p style="margin:0;font-size:13px;">'+rp.message.replace(/\n/g,'<br>')+'</p></div>';
                        });
                        $('#tk-replies').html(rh);
                    } else {
                        $('#tk-replies').html('<p style="color:#999;font-size:13px;">Sin respuestas aún.</p>');
                    }
                },error:function(){ $('#tk-replies').html('<p style="color:#999;font-size:13px;">Sin respuestas aún.</p>'); }});
                $('#tk-modal').css('display','flex');
            };
            window.pgTicketReply = function(){
                var msg=$('#tk-reply-msg').val().trim();
                if(!msg){alert('Escribe un mensaje');return;}
                PG.post('petsgo_add_ticket_reply',{ticket_id:$('#tk-reply-id').val(),message:msg},function(r){
                    if(r.success){$('#tk-reply-msg').val('');pgTicketDetail(parseInt($('#tk-reply-id').val()));loadTickets();}else{alert(r.data);}
                });
            };

            // PDF Export — current search results
            window.pgExportTicketsPDF = function(){
                var tickets = window._pgTickets || [];
                if(!tickets.length){ alert('No hay tickets para exportar'); return; }
                var stLabel={abierto:'Abierto',en_proceso:'En Proceso',resuelto:'Resuelto',cerrado:'Cerrado'};
                var meta = window._pgTicketsMeta || {};
                var doc = new jspdf.jsPDF({orientation:'landscape',unit:'mm',format:'a4'});
                // Header
                doc.setFontSize(16);
                doc.setTextColor(0,168,232);
                doc.text('PetsGo - Reporte de Tickets',14,18);
                doc.setFontSize(9);
                doc.setTextColor(100);
                var filterTxt = 'Filtro: '+ ($('#tk-status-filter option:selected').text()) + ' | Búsqueda: '+ ($('#tk-search').val()||'(todas)');
                doc.text(filterTxt, 14, 25);
                doc.text('Generado: '+new Date().toLocaleString('es-CL'), 14, 30);
                if(meta.stats){
                    doc.text('Abiertos: '+(meta.stats.abierto||0)+' | En Proceso: '+(meta.stats.en_proceso||0)+' | Resueltos: '+(meta.stats.resuelto||0)+' | Cerrados: '+(meta.stats.cerrado||0)+' | Total: '+meta.total, 14, 35);
                }
                // Table
                var rows = [];
                tickets.forEach(function(t){
                    rows.push([
                        t.ticket_number,
                        t.user_name,
                        t.user_role||'',
                        t.subject.length>40 ? t.subject.substring(0,40)+'…' : t.subject,
                        t.category||'',
                        t.priority||'',
                        stLabel[t.status]||t.status,
                        t.assigned_name||'Sin asignar',
                        new Date(t.created_at).toLocaleDateString('es-CL')
                    ]);
                });
                doc.autoTable({
                    startY: 40,
                    head:[['N° Ticket','Solicitante','Rol','Asunto','Categoría','Prioridad','Estado','Asignado','Fecha']],
                    body: rows,
                    styles:{fontSize:8,cellPadding:2,font:'helvetica'},
                    headStyles:{fillColor:[0,168,232],textColor:255,fontStyle:'bold'},
                    alternateRowStyles:{fillColor:[248,249,250]},
                    columnStyles:{3:{cellWidth:50}},
                    margin:{left:14,right:14},
                    didParseCell:function(data){
                        if(data.section==='body' && data.column.index===6){
                            var s=data.cell.raw;
                            if(s==='Abierto'){data.cell.styles.textColor=[133,100,4];data.cell.styles.fillColor=[255,243,205];}
                            else if(s==='En Proceso'){data.cell.styles.textColor=[0,64,133];data.cell.styles.fillColor=[204,229,255];}
                            else if(s==='Resuelto'){data.cell.styles.textColor=[21,87,36];data.cell.styles.fillColor=[212,237,218];}
                            else if(s==='Cerrado'){data.cell.styles.textColor=[56,61,65];data.cell.styles.fillColor=[226,227,229];}
                        }
                    }
                });
                // Footer
                var pageCount = doc.internal.getNumberOfPages();
                for(var i=1;i<=pageCount;i++){
                    doc.setPage(i);
                    doc.setFontSize(8);
                    doc.setTextColor(150);
                    doc.text('PetsGo Tickets — Página '+i+' de '+pageCount, doc.internal.pageSize.getWidth()/2, doc.internal.pageSize.getHeight()-8,{align:'center'});
                }
                doc.save('PetsGo_Tickets_'+new Date().toISOString().slice(0,10)+'.pdf');
            };

            // Auto-refresh notification count
            function refreshTicketBadge(){
                PG.post('petsgo_ticket_count',{},function(r){
                    if(r.success && r.data.count > 0){
                        var badge = document.getElementById('petsgo-ticket-badge');
                        if(!badge){
                            var menuLink = document.querySelector('a[href*="petsgo-tickets"]');
                            if(menuLink){
                                badge = document.createElement('span');
                                badge.id = 'petsgo-ticket-badge';
                                badge.style.cssText = 'background:#dc3545;color:#fff;border-radius:50%;padding:2px 7px;font-size:11px;font-weight:700;margin-left:6px;';
                                menuLink.appendChild(badge);
                            }
                        }
                        if(badge) badge.textContent = r.data.count;
                    }
                });
            }
            refreshTicketBadge();
            setInterval(refreshTicketBadge, 30000);
        });
        </script>
        <?php
    }

    // ============================================================
    // AJAX — Contenido Legal
    // ============================================================
    public function petsgo_save_legal() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        $slug = sanitize_key($_POST['slug'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $allowed = ['centro_de_ayuda','terminos_y_condiciones','politica_de_privacidad','politica_de_envios'];
        if (!in_array($slug, $allowed)) wp_send_json_error('Slug inválido');
        update_option('petsgo_legal_' . $slug, $content);
        $this->audit('save_legal', 'legal', 0, $slug);
        wp_send_json_success(['message' => 'Contenido guardado correctamente']);
    }

    public function petsgo_save_faqs() {
        check_ajax_referer('petsgo_ajax');
        if (!$this->is_admin()) wp_send_json_error('Sin permisos');
        $raw = stripslashes($_POST['faqs'] ?? '[]');
        $faqs = json_decode($raw, true);
        if (!is_array($faqs)) wp_send_json_error('Formato inválido');
        $clean = [];
        foreach ($faqs as $faq) {
            $q = sanitize_text_field($faq['q'] ?? '');
            $a = sanitize_textarea_field($faq['a'] ?? '');
            if ($q && $a) $clean[] = ['q' => $q, 'a' => $a];
        }
        update_option('petsgo_help_faqs', $clean);
        $this->audit('save_faqs', 'legal', 0, count($clean) . ' FAQs');
        wp_send_json_success(['message' => 'FAQs guardadas correctamente']);
    }

    // ============================================================
    // ADMIN PAGE — Contenido Legal
    // ============================================================
    public function page_legal() {
        if (!$this->is_admin()) { echo '<div class="wrap"><h1>⛔ Sin acceso</h1></div>'; return; }
        $pages = [
            'centro_de_ayuda'        => ['title' => 'Centro de Ayuda', 'icon' => '🆘', 'desc' => 'Información para que los clientes, riders y tiendas sepan cómo usar el soporte y crear tickets.'],
            'terminos_y_condiciones' => ['title' => 'Términos y Condiciones', 'icon' => '📋', 'desc' => 'Términos de uso del marketplace PetsGo.'],
            'politica_de_privacidad' => ['title' => 'Política de Privacidad', 'icon' => '🔒', 'desc' => 'Política de privacidad y protección de datos.'],
            'politica_de_envios'     => ['title' => 'Política de Envíos', 'icon' => '🚚', 'desc' => 'Política de despacho a domicilio.'],
            'terminos_rider'         => ['title' => 'Términos del Rider', 'icon' => '🚴', 'desc' => 'Términos y condiciones que el rider debe aceptar al registrarse. Incluye políticas de pago, comisiones y responsabilidades.'],
        ];
        // Auto-populate defaults if never saved
        $legal_defaults = [
            'centro_de_ayuda'        => 'centro_ayuda_default_content',
            'terminos_y_condiciones' => 'terminos_default_content',
            'politica_de_privacidad' => 'privacidad_default_content',
            'politica_de_envios'     => 'envios_default_content',
            'terminos_rider'         => 'terminos_rider_default_content',
        ];
        foreach ($legal_defaults as $key => $method) {
            if (false === get_option('petsgo_legal_' . $key)) {
                update_option('petsgo_legal_' . $key, $this->{$method}());
            }
        }
        if (false === get_option('petsgo_help_faqs')) {
            update_option('petsgo_help_faqs', $this->help_faqs_defaults());
        }
        $faqs = get_option('petsgo_help_faqs', []);
        if (!is_array($faqs)) $faqs = [];
        ?>
        <div class="wrap petsgo-wrap">
            <h1>📄 Contenido Legal y Ayuda</h1>
            <p>Edita el contenido de las páginas públicas del marketplace. El contenido se muestra en el sitio web tal como lo escribes aquí (soporta HTML).</p>

            <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
                <?php foreach ($pages as $slug => $info): ?>
                <button type="button" class="button legal-tab-btn <?php echo array_key_first($pages)===$slug?'button-primary':''; ?>" data-slug="<?php echo $slug; ?>">
                    <?php echo $info['icon'] . ' ' . $info['title']; ?>
                </button>
                <?php endforeach; ?>
                <span style="border-left:2px solid #ccc;margin:0 4px;"></span>
                <button type="button" class="button legal-tab-btn" data-slug="faqs">
                    💬 Preguntas Frecuentes
                </button>
            </div>

            <?php foreach ($pages as $slug => $info):
                $content = get_option('petsgo_legal_' . $slug, '');
            ?>
            <div class="legal-panel" data-slug="<?php echo $slug; ?>" style="<?php echo array_key_first($pages)!==$slug?'display:none;':''; ?>">
                <div class="petsgo-card" style="padding:24px;">
                    <h2><?php echo $info['icon'] . ' ' . $info['title']; ?></h2>
                    <p style="color:#666;font-size:13px;margin-bottom:16px;"><?php echo $info['desc']; ?></p>
                    <?php
                    $editor_id = 'legal_editor_' . $slug;
                    wp_editor($content, $editor_id, [
                        'textarea_name' => 'legal_content_' . $slug,
                        'media_buttons' => true,
                        'textarea_rows' => 20,
                        'teeny'         => false,
                        'quicktags'     => true,
                    ]);
                    ?>
                    <div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
                        <button type="button" class="button button-primary legal-save-btn" data-slug="<?php echo $slug; ?>">
                            💾 Guardar <?php echo $info['title']; ?>
                        </button>
                        <span class="legal-msg" data-slug="<?php echo $slug; ?>" style="font-weight:600;font-size:13px;"></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ===== FAQs Panel ===== -->
            <div class="legal-panel" data-slug="faqs" style="display:none;">
                <div class="petsgo-card" style="padding:24px;">
                    <h2>💬 Preguntas Frecuentes (Centro de Ayuda)</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:16px;">Gestiona las preguntas frecuentes que se muestran en la página Centro de Ayuda del sitio web. Puedes agregar, editar, reordenar y eliminar preguntas.</p>
                    <div id="faqs-container">
                        <?php if (empty($faqs)): ?>
                        <p style="color:#999;font-style:italic;" id="faqs-empty">No hay preguntas frecuentes. Haz clic en "Agregar Pregunta" para comenzar.</p>
                        <?php endif; ?>
                        <?php foreach ($faqs as $idx => $faq): ?>
                        <div class="faq-item" style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:16px;margin-bottom:12px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                                <span style="font-weight:700;font-size:12px;color:#888;">Pregunta #<?php echo $idx+1; ?></span>
                                <button type="button" class="button faq-remove-btn" style="color:#dc3545;border-color:#dc3545;font-size:12px;padding:2px 10px;">✕ Eliminar</button>
                            </div>
                            <div style="margin-bottom:8px;">
                                <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">Pregunta</label>
                                <input type="text" class="faq-q regular-text" value="<?php echo esc_attr($faq['q']); ?>" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" placeholder="Ej: ¿Cómo creo un ticket de soporte?">
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">Respuesta</label>
                                <textarea class="faq-a" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;resize:vertical;" placeholder="Escribe la respuesta..."><?php echo esc_textarea($faq['a']); ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button type="button" class="button" id="faq-add-btn">➕ Agregar Pregunta</button>
                        <button type="button" class="button button-primary" id="faq-save-btn">💾 Guardar FAQs</button>
                        <span id="faq-msg" style="font-weight:600;font-size:13px;"></span>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            // Tab switching
            $('.legal-tab-btn').on('click', function(){
                var slug = $(this).data('slug');
                $('.legal-tab-btn').removeClass('button-primary');
                $(this).addClass('button-primary');
                $('.legal-panel').hide();
                $('.legal-panel[data-slug="'+slug+'"]').show();
            });
            // Save
            $('.legal-save-btn').on('click', function(){
                var slug = $(this).data('slug');
                var $msg = $('.legal-msg[data-slug="'+slug+'"]');
                var editorId = 'legal_editor_' + slug;
                var content = '';
                // Get content from TinyMCE or textarea
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                    content = tinyMCE.get(editorId).getContent();
                } else {
                    content = $('#' + editorId).val();
                }
                $msg.text('Guardando...').css('color','#666');
                $.post(ajaxurl, {
                    action: 'petsgo_save_legal',
                    _ajax_nonce: '<?php echo wp_create_nonce('petsgo_ajax'); ?>',
                    slug: slug,
                    content: content,
                }, function(r){
                    if (r.success) {
                        $msg.text('✅ Guardado').css('color','#28a745');
                    } else {
                        $msg.text('❌ Error').css('color','#dc3545');
                    }
                    setTimeout(function(){ $msg.text(''); }, 3000);
                }).fail(function(){
                    $msg.text('❌ Error de red').css('color','#dc3545');
                });
            });

            // ===== FAQs Management =====
            var faqCount = $('#faqs-container .faq-item').length;
            function faqTemplate(num){
                return '<div class="faq-item" style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:16px;margin-bottom:12px;">'+
                    '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">'+
                    '<span style="font-weight:700;font-size:12px;color:#888;">Pregunta #'+num+'</span>'+
                    '<button type="button" class="button faq-remove-btn" style="color:#dc3545;border-color:#dc3545;font-size:12px;padding:2px 10px;">✕ Eliminar</button></div>'+
                    '<div style="margin-bottom:8px;"><label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">Pregunta</label>'+
                    '<input type="text" class="faq-q regular-text" value="" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" placeholder="Ej: ¿Cómo creo un ticket de soporte?"></div>'+
                    '<div><label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">Respuesta</label>'+
                    '<textarea class="faq-a" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;resize:vertical;" placeholder="Escribe la respuesta..."></textarea></div></div>';
            }
            $('#faq-add-btn').on('click', function(){
                $('#faqs-empty').remove();
                faqCount++;
                $('#faqs-container').append(faqTemplate(faqCount));
            });
            $(document).on('click','.faq-remove-btn', function(){
                $(this).closest('.faq-item').remove();
                // Renumber
                $('#faqs-container .faq-item').each(function(i){
                    $(this).find('span').first().text('Pregunta #'+(i+1));
                });
                faqCount = $('#faqs-container .faq-item').length;
            });
            $('#faq-save-btn').on('click', function(){
                var faqs = [];
                $('#faqs-container .faq-item').each(function(){
                    var q = $(this).find('.faq-q').val().trim();
                    var a = $(this).find('.faq-a').val().trim();
                    if (q && a) faqs.push({q:q, a:a});
                });
                var $msg = $('#faq-msg');
                $msg.text('Guardando...').css('color','#666');
                $.post(ajaxurl, {
                    action: 'petsgo_save_faqs',
                    _ajax_nonce: '<?php echo wp_create_nonce("petsgo_ajax"); ?>',
                    faqs: JSON.stringify(faqs),
                }, function(r){
                    $msg.text(r.success ? '✅ FAQs guardadas' : '❌ Error').css('color', r.success ? '#28a745' : '#dc3545');
                    setTimeout(function(){ $msg.text(''); }, 3000);
                }).fail(function(){
                    $msg.text('❌ Error de red').css('color','#dc3545');
                });
            });
        });
        </script>
        <?php
    }
}

new PetsGo_Core();
