<?php
/**
 * PetsGo Invoice PDF Generator — FPDF
 * Genera boletas/recibos PDF con cabecera PetsGo + Tienda, detalle, QR.
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/fpdf.php';
require_once __DIR__ . '/qrcode.php';

class PetsGo_Invoice_PDF extends FPDF {

    private $order_data;
    private $vendor_data;
    private $customer_data;
    private $invoice_number;
    private $qr_token;
    private $items;

    // Colores PetsGo
    private $primary   = [0, 168, 232];   // #00A8E8
    private $secondary = [255, 196, 0];    // #FFC400
    private $dark      = [47, 58, 64];     // #2F3A40

    public function __construct($order_data = null, $vendor_data = null, $customer_data = null, $invoice_number = '', $qr_token = '', $items = []) {
        parent::__construct('P', 'mm', 'A4');
        $this->order_data     = $order_data;
        $this->vendor_data    = $vendor_data;
        $this->customer_data  = $customer_data;
        $this->invoice_number = $invoice_number;
        $this->qr_token       = $qr_token;
        $this->items          = $items;
    }

    /**
     * Generate PDF from array-based data (used by petsgo-core invoice generators).
     * @param array  $vendor_data   ['store_name','rut','address','phone','email','contact_phone','social_*','logo_url']
     * @param array  $invoice_data  ['invoice_number','date','customer_name','customer_email']
     * @param array  $items         [['name'=>...,'qty'=>...,'price'=>...], ...]
     * @param float  $grand         Grand total (inc delivery)
     * @param string $qr_url        Validation URL (unused here, QR built from token)
     * @param string $qr_token      UUID token for QR verification
     * @param string $pdf_path      Absolute path to write the PDF
     */
    public function generate($vendor_data, $invoice_data, $items, $grand, $qr_url, $qr_token, $pdf_path) {
        $this->vendor_data    = (object) $vendor_data;
        $this->order_data     = (object) [
            'id'           => $invoice_data['order_id'] ?? '',
            'total_amount' => $grand,
            'delivery_fee' => $vendor_data['delivery_fee'] ?? 0,
            'created_at'   => $invoice_data['date'] ?? date('d/m/Y H:i'),
        ];
        $this->customer_data  = (object) [
            'display_name' => $invoice_data['customer_name'] ?? 'N/A',
            'user_email'   => $invoice_data['customer_email'] ?? '',
            'ID'           => $invoice_data['customer_id'] ?? '',
        ];
        $this->invoice_number = $invoice_data['invoice_number'] ?? '';
        $this->qr_token       = $qr_token;
        $this->items          = $items;
        $this->save($pdf_path);
    }

    private function utf8($str) {
        // Strip emojis/multibyte symbols that FPDF (ISO-8859-1) cannot render
        $str = preg_replace('/[\x{1F000}-\x{1FFFF}|\x{2600}-\x{27BF}|\x{FE00}-\x{FEFF}]/u', '', $str);
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
    }

    public function build() {
        $this->AddPage();
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 25);

        $this->buildHeader();
        $this->buildInvoiceInfo();
        $this->buildCustomerInfo();
        $this->buildItemsTable();
        $this->buildTotals();
        $this->buildQRSection();
        $this->buildFooter();
    }

    public function save($filepath) {
        $this->build();
        $dir = dirname($filepath);
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $this->Output('F', $filepath);
        return file_exists($filepath);
    }

    // ========================
    // HEADER: Logo PetsGo (izq) + Logo Tienda (der)
    // ========================
    private function buildHeader() {
        // Fondo blanco para el header (logo color se ve bien)
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, 210, 36, 'F');

        // Logo PetsGo color (izquierda) — mismo del frontend
        $petsgo_logo = $this->findLogo('petsgo');
        if ($petsgo_logo) {
            $this->Image($petsgo_logo, 15, 6, 50, 0);
        } else {
            $this->SetXY(15, 10);
            $this->SetFont('Arial', 'B', 22);
            $this->SetTextColor($this->primary[0], $this->primary[1], $this->primary[2]);
            $this->Cell(60, 10, 'PetsGo', 0, 0, 'L');
        }

        // Logo Tienda (derecha)
        $vendor_logo = $this->findVendorLogo();
        if ($vendor_logo) {
            $this->Image($vendor_logo, 150, 6, 45, 0);
        } else {
            $this->SetXY(100, 10);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
            $this->Cell(95, 10, $this->utf8($this->vendor_data->store_name ?? ''), 0, 0, 'R');
        }

        // Barra decorativa: azul PetsGo + franja amarilla
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Rect(0, 36, 210, 3, 'F');
        $this->SetFillColor($this->secondary[0], $this->secondary[1], $this->secondary[2]);
        $this->Rect(0, 39, 210, 1.5, 'F');

        $this->SetY(45);
    }

    private function findLogo($type) {
        // Buscar logo PetsGo color (celeste + amarillo, igual al frontend)
        $paths = [
            ABSPATH . 'wp-content/uploads/petsgo-logo-color.png',
            ABSPATH . 'wp-content/uploads/petsgo-logo.png',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) return $p;
        }
        // Buscar en el directorio de diseño Color
        $design_dir = ABSPATH . 'Petsgo_Diseño/PNG/Color/';
        if (is_dir($design_dir)) {
            // Preferir "Logo y nombre" (mismo que usa el frontend)
            $preferred = $design_dir . 'Logo y nombre-1@4x.png';
            if (file_exists($preferred)) return $preferred;
            $files = glob($design_dir . '*.png');
            if (!empty($files)) return $files[0];
        }
        return null;
    }

    private function findVendorLogo() {
        if (!empty($this->vendor_data->invoice_logo_id)) {
            $path = get_attached_file($this->vendor_data->invoice_logo_id);
            if ($path && file_exists($path)) return $path;
        }
        if (!empty($this->vendor_data->logo_url)) {
            // Intentar convertir URL a path local
            $upload_dir = wp_upload_dir();
            $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $this->vendor_data->logo_url);
            if (file_exists($path)) return $path;
        }
        return null;
    }

    // ========================
    // INFO BOLETA
    // ========================
    private function buildInvoiceInfo() {
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 8, $this->utf8('BOLETA DE VENTA ELECTRÓNICA'), 0, 1, 'C');

        $this->SetFont('Arial', '', 10);

        // Recuadro con datos de la boleta
        $this->SetFillColor(240, 250, 255);
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $bx = 120; $by = $this->GetY(); $bw = 75;
        $this->Rect($bx, $by, $bw, 24, 'DF');

        $this->SetXY($bx + 3, $by + 2);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($bw - 6, 5, $this->utf8('Nº Boleta: ') . $this->invoice_number, 0, 2);
        $this->SetFont('Arial', '', 9);
        $this->Cell($bw - 6, 5, $this->utf8('Fecha: ') . date('d/m/Y', strtotime($this->order_data->created_at ?? 'now')), 0, 2);
        $this->Cell($bw - 6, 5, $this->utf8('Pedido #') . ($this->order_data->id ?? ''), 0, 2);

        // Datos de la tienda a la izquierda
        $this->SetXY(15, $by);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Cell(100, 5, $this->utf8($this->vendor_data->store_name ?? ''), 0, 2);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(100, 4, 'RUT: ' . ($this->vendor_data->rut ?? 'N/A'), 0, 2);
        $this->Cell(100, 4, $this->utf8('Dir: ' . ($this->vendor_data->address ?? 'N/A')), 0, 2);
        $this->Cell(100, 4, 'Tel: ' . ($this->vendor_data->contact_phone ?? $this->vendor_data->phone ?? 'N/A'), 0, 2);
        $this->Cell(100, 4, 'Email: ' . ($this->vendor_data->email ?? 'N/A'), 0, 2);

        $this->Ln(6);
    }

    // ========================
    // DATOS CLIENTE
    // ========================
    private function buildCustomerInfo() {
        $this->SetFillColor(248, 249, 250);
        $this->SetDrawColor(200, 200, 200);
        $y = $this->GetY();
        $this->Rect(15, $y, 180, 16, 'DF');

        $this->SetXY(18, $y + 2);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(30, 5, 'CLIENTE:', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 5, $this->utf8($this->customer_data->display_name ?? 'N/A'), 0, 0);

        $this->Cell(15, 5, 'Email:', 0, 0);
        $this->Cell(55, 5, $this->customer_data->user_email ?? 'N/A', 0, 1);

        $this->SetX(18);
        $this->Cell(30, 5, 'ID:', 0, 0);
        $this->Cell(60, 5, '#' . ($this->customer_data->ID ?? ''), 0, 0);

        $this->SetY($y + 20);
    }

    // ========================
    // TABLA DE ITEMS
    // ========================
    private function buildItemsTable() {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetTextColor(255, 255, 255);

        // Headers
        $this->Cell(10, 7, '#', 1, 0, 'C', true);
        $this->Cell(80, 7, $this->utf8('Descripción'), 1, 0, 'L', true);
        $this->Cell(20, 7, 'Cant.', 1, 0, 'C', true);
        $this->Cell(35, 7, 'P. Unit', 1, 0, 'R', true);
        $this->Cell(35, 7, 'Subtotal', 1, 1, 'R', true);

        // Items
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(50, 50, 50);
        $fill = false;

        if (!empty($this->items)) {
            $i = 1;
            foreach ($this->items as $item) {
                $this->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 255 : 255);
                $name = $item['name'] ?? 'Producto';
                $qty  = intval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $sub  = $qty * $price;

                $this->Cell(10, 6, $i, 1, 0, 'C', true);
                $this->Cell(80, 6, $this->utf8(mb_substr($name, 0, 45)), 1, 0, 'L', true);
                $this->Cell(20, 6, $qty, 1, 0, 'C', true);
                $this->Cell(35, 6, '$' . number_format($price, 0, ',', '.'), 1, 0, 'R', true);
                $this->Cell(35, 6, '$' . number_format($sub, 0, ',', '.'), 1, 1, 'R', true);
                $fill = !$fill;
                $i++;
            }
        } else {
            // Fallback: una línea con el total del pedido
            $this->SetFillColor(255, 255, 255);
            $this->Cell(10, 6, '1', 1, 0, 'C', true);
            $this->Cell(80, 6, $this->utf8('Compra en ' . ($this->vendor_data->store_name ?? 'Tienda')), 1, 0, 'L', true);
            $this->Cell(20, 6, '1', 1, 0, 'C', true);
            $total = floatval($this->order_data->total_amount ?? 0);
            $this->Cell(35, 6, '$' . number_format($total, 0, ',', '.'), 1, 0, 'R', true);
            $this->Cell(35, 6, '$' . number_format($total, 0, ',', '.'), 1, 1, 'R', true);
        }

        $this->Ln(2);
    }

    // ========================
    // TOTALES
    // ========================
    private function buildTotals() {
        $total = floatval($this->order_data->total_amount ?? 0);
        $delivery = floatval($this->order_data->delivery_fee ?? 0);
        $neto = round($total / 1.19);
        $iva = $total - $neto;

        $x = 120;
        $this->SetX($x);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);

        $this->Cell(40, 6, 'Neto:', 0, 0, 'R');
        $this->Cell(35, 6, '$' . number_format($neto, 0, ',', '.'), 0, 1, 'R');
        $this->SetX($x);
        $this->Cell(40, 6, 'IVA (19%):', 0, 0, 'R');
        $this->Cell(35, 6, '$' . number_format($iva, 0, ',', '.'), 0, 1, 'R');

        if ($delivery > 0) {
            $this->SetX($x);
            $this->Cell(40, 6, 'Delivery:', 0, 0, 'R');
            $this->Cell(35, 6, '$' . number_format($delivery, 0, ',', '.'), 0, 1, 'R');
        }

        // Total en grande
        $this->SetX($x);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetTextColor(255, 255, 255);
        $grand = $total + $delivery;
        $this->Cell(40, 8, 'TOTAL:', 0, 0, 'R', true);
        $this->Cell(35, 8, '$' . number_format($grand, 0, ',', '.'), 0, 1, 'R', true);

        $this->Ln(6);
    }

    // ========================
    // QR CODE
    // ========================
    private function buildQRSection() {
        $validation_url = site_url('/wp-json/petsgo/v1/invoice/validate/' . $this->qr_token);

        // Generar QR
        $qr_dir  = WP_CONTENT_DIR . '/uploads/petsgo-qr/';
        $qr_file = $qr_dir . 'qr-' . $this->invoice_number . '.png';

        if (!file_exists($qr_file)) {
            if (!is_dir($qr_dir)) wp_mkdir_p($qr_dir);
            SimpleQRCode::png($validation_url, $qr_file, 'L', 4, 1);
        }

        $y = $this->GetY();

        // Cuadro QR
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetFillColor(248, 249, 250);
        $this->Rect(15, $y, 180, 42, 'DF');

        if (file_exists($qr_file) && filesize($qr_file) > 100) {
            $this->Image($qr_file, 20, $y + 3, 36, 36);
        }

        $this->SetXY(60, $y + 4);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(130, 5, $this->utf8('Verificación de Boleta'), 0, 2);

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->MultiCell(130, 4, $this->utf8("Escanee el código QR para verificar la autenticidad de esta boleta.\n\nToken: " . $this->qr_token . "\nBoleta: " . $this->invoice_number));

        $this->SetY($y + 46);
    }

    // ========================
    // FOOTER
    // ========================
    private function buildFooter() {
        $y = $this->GetY();
        if ($y > 250) { $this->AddPage(); $y = $this->GetY(); }

        // Redes sociales
        $socials = [];
        if (!empty($this->vendor_data->social_facebook))  $socials[] = 'Facebook: ' . $this->vendor_data->social_facebook;
        if (!empty($this->vendor_data->social_instagram)) $socials[] = 'Instagram: ' . $this->vendor_data->social_instagram;
        if (!empty($this->vendor_data->social_whatsapp))  $socials[] = 'WhatsApp: ' . $this->vendor_data->social_whatsapp;
        if (!empty($this->vendor_data->social_website))   $socials[] = 'Web: ' . $this->vendor_data->social_website;

        if (!empty($socials)) {
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 4, $this->utf8(implode(' | ', $socials)), 0, 1, 'C');
            $this->Ln(2);
        }

        // Línea
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);

        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 3, $this->utf8('Documento generado electrónicamente por PetsGo Marketplace — www.petsgo.cl'), 0, 1, 'C');
        $this->Cell(0, 3, $this->utf8('Este documento es una boleta de venta válida. Verifique escaneando el código QR.'), 0, 1, 'C');
    }
}
