<?php
/**
 * PetsGo Subscription / Plan Invoice PDF Generator
 * Genera boleta de suscripcion/contratacion de plan para tiendas.
 * Header: fondo celeste PetsGo con logo blanco centrado.
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/fpdf.php';
require_once __DIR__ . '/qrcode.php';

class PetsGo_Subscription_PDF extends FPDF {

    private $vendor_data;
    private $plan_data;
    private $invoice_number;
    private $date;
    private $qr_token;

    // Colores PetsGo
    private $primary   = [0, 168, 232];   // #00A8E8
    private $secondary = [255, 196, 0];    // #FFC400
    private $dark      = [47, 58, 64];     // #2F3A40

    public function __construct() {
        parent::__construct('P', 'mm', 'A4');
    }

    /**
     * Generate subscription/plan invoice PDF.
     *
     * @param array  $vendor_data   ['store_name','rut','address','phone','email','contact_name']
     * @param array  $plan_data     ['plan_name','monthly_price','features'=>[...], 'billing_period',
     *                               'billing_months'=>12, 'free_months'=>2]
     * @param string $invoice_number  e.g. SUB-PC-20260211-001
     * @param string $date            e.g. 11/02/2026 15:30
     * @param string $qr_token        UUID token for QR validation
     * @param string $pdf_path        Absolute path to write the PDF
     */
    public function generate($vendor_data, $plan_data, $invoice_number, $date, $qr_token, $pdf_path) {
        $this->vendor_data    = (object) $vendor_data;
        $this->plan_data      = (object) $plan_data;
        $this->invoice_number = $invoice_number;
        $this->date           = $date;
        $this->qr_token       = $qr_token;

        $this->AliasNbPages();
        $this->SetAutoPageBreak(false);
        $this->AddPage();
        $this->SetMargins(15, 15, 15);

        $this->buildTitle();
        $this->buildVendorInfo();
        $this->buildPlanDetail();
        $this->buildPaymentSummary();
        $this->buildQRSection();
        $this->buildTerms();

        $dir = dirname($pdf_path);
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $this->Output('F', $pdf_path);
        return file_exists($pdf_path);
    }

    private function utf8($str) {
        $str = preg_replace('/[\x{1F000}-\x{1FFFF}|\x{2600}-\x{27BF}|\x{FE00}-\x{FEFF}]/u', '', $str);
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
    }

    // ========================
    // HEADER: Fondo celeste con logo PetsGo blanco centrado
    // ========================
    function Header() {
        $hdrH = 30;

        // Fondo celeste PetsGo
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Rect(0, 0, 210, $hdrH, 'F');

        // Logo PetsGo blanco centrado
        $logo = $this->findWhiteLogo();
        if ($logo) {
            $logoH = 16;
            $imgSize = @getimagesize($logo);
            if ($imgSize && $imgSize[1] > 0) {
                $ratio = $imgSize[0] / $imgSize[1];
                $logoW = $logoH * $ratio;
            } else {
                $logoW = 50;
            }
            $logoX = (210 - $logoW) / 2;
            $logoY = ($hdrH - $logoH) / 2;
            $this->Image($logo, $logoX, $logoY, $logoW, $logoH);
        } else {
            $this->SetXY(0, 6);
            $this->SetFont('Arial', 'B', 20);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(210, 12, 'PetsGo', 0, 0, 'C');
        }

        // Franja amarilla
        $this->SetFillColor($this->secondary[0], $this->secondary[1], $this->secondary[2]);
        $this->Rect(0, $hdrH, 210, 1.5, 'F');

        $this->SetY($hdrH + 4);
    }

    // ========================
    // FOOTER: Pie de pagina estandar
    // ========================
    function Footer() {
        $this->SetY(-18);

        // Linea azul
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(1.5);

        $this->SetFont('Arial', '', 6.5);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 3, $this->utf8('PetsGo Marketplace - www.petsgo.cl - contacto@petsgo.cl'), 0, 1, 'C');
        $this->Cell(0, 3, $this->utf8('Documento de suscripcion generado electronicamente - Santiago, Chile'), 0, 1, 'C');

        $this->SetFont('Arial', '', 6.5);
        $this->SetTextColor(160, 160, 160);
        $this->Cell(0, 3, $this->utf8('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    // ========================
    // TITULO BOLETA
    // ========================
    private function buildTitle() {
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 7, $this->utf8('BOLETA DE SUSCRIPCION'), 0, 1, 'C');
        $this->Ln(1);

        // Recuadro con numero y fecha
        $this->SetFillColor(240, 250, 255);
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $bx = 55; $by = $this->GetY(); $bw = 100;
        $this->Rect($bx, $by, $bw, 13, 'DF');

        $this->SetXY($bx + 3, $by + 1.5);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Cell($bw - 6, 5, $this->utf8('No. ') . $this->invoice_number, 0, 2, 'C');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell($bw - 6, 4, $this->utf8('Fecha: ') . $this->date, 0, 2, 'C');

        $this->SetY($by + 16);
    }

    // ========================
    // DATOS DE LA TIENDA (CLIENTE)
    // ========================
    private function buildVendorInfo() {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 6, $this->utf8('Datos del Cliente'), 0, 1, 'L');

        $this->SetFillColor(248, 249, 250);
        $this->SetDrawColor(220, 220, 220);
        $y = $this->GetY();
        $this->Rect(15, $y, 180, 20, 'DF');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(60, 60, 60);

        $fields = [
            'Tienda'  => $this->vendor_data->store_name ?? 'N/A',
            'RUT'     => $this->vendor_data->rut ?? 'N/A',
            'Email'   => $this->vendor_data->email ?? 'N/A',
            'Contacto'=> $this->vendor_data->contact_name ?? ($this->vendor_data->phone ?? 'N/A'),
        ];

        $col = 0;
        foreach ($fields as $label => $val) {
            $xPos = 18 + ($col % 2) * 90;
            $yPos = $y + 2 + floor($col / 2) * 8;
            $this->SetXY($xPos, $yPos);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(22, 4, $label . ':', 0, 0);
            $this->SetFont('Arial', '', 8);
            $this->Cell(65, 4, $this->utf8($val), 0, 0);
            $col++;
        }

        $this->SetY($y + 23);
    }

    // ========================
    // DETALLE DEL PLAN
    // ========================
    private function buildPlanDetail() {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 6, $this->utf8('Detalle del Plan Contratado'), 0, 1, 'L');

        // Tabla de detalle - encabezado
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetTextColor(255, 255, 255);

        $this->Cell(60, 6, $this->utf8('Concepto'), 1, 0, 'L', true);
        $this->Cell(25, 6, $this->utf8('Periodo'), 1, 0, 'C', true);
        $this->Cell(25, 6, $this->utf8('Precio/Mes'), 1, 0, 'R', true);
        $this->Cell(20, 6, $this->utf8('Meses'), 1, 0, 'C', true);
        $this->Cell(25, 6, $this->utf8('Dcto.'), 1, 0, 'R', true);
        $this->Cell(25, 6, 'Total', 1, 1, 'R', true);

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(50, 50, 50);
        $this->SetFillColor(255, 255, 255);

        $plan_name     = $this->plan_data->plan_name ?? 'Plan';
        $monthly_price = floatval($this->plan_data->monthly_price ?? 0);
        $period        = $this->plan_data->billing_period ?? 'Mensual';
        $billing_months= intval($this->plan_data->billing_months ?? 1);
        $free_months   = intval($this->plan_data->free_months ?? 0);

        $total_months  = max(1, $billing_months);
        $charged_months= max(1, $total_months - $free_months);
        $discount      = $monthly_price * $free_months;
        $total         = $monthly_price * $charged_months;

        $this->Cell(60, 6, $this->utf8('Plan ' . $plan_name . ' - PetsGo'), 1, 0, 'L');
        $this->Cell(25, 6, $this->utf8($period), 1, 0, 'C');
        $this->Cell(25, 6, '$' . number_format($monthly_price, 0, ',', '.'), 1, 0, 'R');
        $this->Cell(20, 6, $total_months, 1, 0, 'C');

        if ($discount > 0) {
            $this->SetTextColor(220, 53, 69);
            $this->Cell(25, 6, '-$' . number_format($discount, 0, ',', '.'), 1, 0, 'R');
            $this->SetTextColor(50, 50, 50);
        } else {
            $this->Cell(25, 6, '-', 1, 0, 'C');
        }

        $this->SetFont('Arial', 'B', 8);
        $this->Cell(25, 6, '$' . number_format($total, 0, ',', '.'), 1, 1, 'R');

        // Free months message
        if ($free_months > 0) {
            $this->Ln(1);
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(40, 167, 69);
            $this->SetX(18);
            $msg = $free_months == 1
                ? 'Beneficio anual: 1 mes de gracia incluido. Paga ' . $charged_months . ' de ' . $total_months . ' meses.'
                : 'Beneficio anual: ' . $free_months . ' meses de gracia incluidos. Paga ' . $charged_months . ' de ' . $total_months . ' meses.';
            $this->Cell(0, 4, $this->utf8($msg), 0, 1);
        }

        // Features del plan
        $features = $this->plan_data->features ?? [];
        if (!empty($features)) {
            $this->Ln(2);
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor($this->primary[0], $this->primary[1], $this->primary[2]);
            $this->Cell(0, 5, $this->utf8('Caracteristicas incluidas en el Plan ' . $plan_name . ':'), 0, 1);

            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(80, 80, 80);
            foreach ($features as $feat) {
                $this->SetX(20);
                $this->Cell(4, 4, chr(149), 0, 0);
                $this->Cell(0, 4, $this->utf8(' ' . $feat), 0, 1);
            }
        }

        $this->Ln(2);
    }

    // ========================
    // RESUMEN DE PAGO
    // ========================
    private function buildPaymentSummary() {
        $monthly_price  = floatval($this->plan_data->monthly_price ?? 0);
        $billing_months = intval($this->plan_data->billing_months ?? 1);
        $free_months    = intval($this->plan_data->free_months ?? 0);
        $total_months   = max(1, $billing_months);
        $charged_months = max(1, $total_months - $free_months);

        $subtotal_bruto = $monthly_price * $total_months;
        $descuento      = $monthly_price * $free_months;
        $total_cobrar   = $monthly_price * $charged_months;
        $neto           = round($total_cobrar / 1.19);
        $iva            = $total_cobrar - $neto;

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 6, $this->utf8('Resumen de Pago'), 0, 1, 'L');

        $x = 105;
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(80, 80, 80);

        $this->SetX($x);
        $this->Cell(55, 5, $this->utf8($total_months . ' mes(es) x $' . number_format($monthly_price, 0, ',', '.') . ':'), 0, 0, 'R');
        $this->Cell(30, 5, '$' . number_format($subtotal_bruto, 0, ',', '.'), 0, 1, 'R');

        if ($descuento > 0) {
            $this->SetX($x);
            $this->SetTextColor(220, 53, 69);
            $this->Cell(55, 5, $this->utf8('Dcto. ' . $free_months . ' mes(es) gratis:'), 0, 0, 'R');
            $this->Cell(30, 5, '-$' . number_format($descuento, 0, ',', '.'), 0, 1, 'R');
            $this->SetTextColor(80, 80, 80);
        }

        $this->SetX($x);
        $this->Cell(55, 5, 'Subtotal (Neto):', 0, 0, 'R');
        $this->Cell(30, 5, '$' . number_format($neto, 0, ',', '.'), 0, 1, 'R');

        $this->SetX($x);
        $this->Cell(55, 5, 'IVA (19%):', 0, 0, 'R');
        $this->Cell(30, 5, '$' . number_format($iva, 0, ',', '.'), 0, 1, 'R');

        $this->SetX($x);
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(55, 8, 'TOTAL:', 0, 0, 'R', true);
        $this->Cell(30, 8, '$' . number_format($total_cobrar, 0, ',', '.'), 0, 1, 'R', true);

        $this->Ln(4);
    }

    // ========================
    // TERMINOS Y CONDICIONES
    // ========================
    private function buildTerms() {
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 5, $this->utf8('Terminos y Condiciones'), 0, 1);

        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(130, 130, 130);

        $terms = [
            'La suscripcion se renueva automaticamente al final de cada periodo de facturacion.',
            'El cobro se realiza al inicio de cada ciclo. Puede cancelar en cualquier momento.',
            'Al cancelar, mantiene acceso hasta el final del periodo pagado.',
            'PetsGo se reserva el derecho de modificar precios con 30 dias de anticipacion.',
            'Para soporte: contacto@petsgo.cl | www.petsgo.cl',
        ];

        foreach ($terms as $i => $term) {
            $this->SetX(18);
            $this->Cell(4, 3.5, ($i + 1) . '.', 0, 0);
            $this->Cell(165, 3.5, $this->utf8($term), 0, 1);
        }
    }

    // ========================
    // QR CODE VALIDATION
    // ========================
    private function buildQRSection() {
        if (empty($this->qr_token)) return;

        $validation_url = site_url('/wp-json/petsgo/v1/subscription/validate/' . $this->qr_token);

        // Generate QR image
        $qr_dir  = WP_CONTENT_DIR . '/uploads/petsgo-qr/';
        $qr_file = $qr_dir . 'qr-' . $this->invoice_number . '.png';

        if (!file_exists($qr_file)) {
            if (!is_dir($qr_dir)) wp_mkdir_p($qr_dir);
            SimpleQRCode::png($validation_url, $qr_file, 'L', 3, 1);
        }

        $y = $this->GetY();

        // QR box - compact
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetFillColor(240, 250, 255);
        $this->Rect(15, $y, 180, 26, 'DF');

        if (file_exists($qr_file) && filesize($qr_file) > 100) {
            $this->Image($qr_file, 18, $y + 2, 22, 22);
        }

        $this->SetXY(44, $y + 3);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(145, 4, $this->utf8('Verificacion de Suscripcion'), 0, 2);

        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(100, 100, 100);
        $this->MultiCell(145, 3.5, $this->utf8("Escanee el codigo QR para verificar la autenticidad de esta boleta.  Token: " . $this->qr_token . "  |  Boleta: " . $this->invoice_number));

        $this->SetY($y + 29);
    }

    // ========================
    // FIND WHITE LOGO
    // ========================
    private function findWhiteLogo() {
        $paths = [
            ABSPATH . 'wp-content/uploads/petsgo-logo-blanco.png',
            ABSPATH . 'wp-content/uploads/petsgo-nombre-blanco.png',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) return $p;
        }
        $design_dir = ABSPATH . 'Petsgo_Diseño/PNG/Blanco/';
        if (is_dir($design_dir)) {
            $preferred = $design_dir . 'Logo completo_1@4x.png';
            if (file_exists($preferred)) return $preferred;
            $files = glob($design_dir . '*.png');
            if (!empty($files)) return $files[0];
        }
        return null;
    }
}
