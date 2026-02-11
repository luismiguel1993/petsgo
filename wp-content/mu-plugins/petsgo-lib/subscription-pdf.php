<?php
/**
 * PetsGo Subscription / Plan Invoice PDF Generator
 * Genera boleta de suscripcion/contratacion de plan para tiendas.
 * Header: fondo celeste PetsGo con logo blanco centrado.
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/fpdf.php';

class PetsGo_Subscription_PDF extends FPDF {

    private $vendor_data;
    private $plan_data;
    private $invoice_number;
    private $date;

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
     * @param array  $plan_data     ['plan_name','monthly_price','features'=>[...], 'billing_period']
     * @param string $invoice_number  e.g. SUB-PC-20260211-001
     * @param string $date            e.g. 11/02/2026 15:30
     * @param string $pdf_path        Absolute path to write the PDF
     */
    public function generate($vendor_data, $plan_data, $invoice_number, $date, $pdf_path) {
        $this->vendor_data    = (object) $vendor_data;
        $this->plan_data      = (object) $plan_data;
        $this->invoice_number = $invoice_number;
        $this->date           = $date;

        $this->AliasNbPages();
        $this->SetAutoPageBreak(true, 35);
        $this->AddPage();
        $this->SetMargins(15, 15, 15);

        $this->buildTitle();
        $this->buildVendorInfo();
        $this->buildPlanDetail();
        $this->buildPaymentSummary();
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
        $hdrH = 40;

        // Fondo celeste PetsGo
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Rect(0, 0, 210, $hdrH, 'F');

        // Logo PetsGo blanco centrado
        $logo = $this->findWhiteLogo();
        if ($logo) {
            $logoH = 22;
            $imgSize = @getimagesize($logo);
            if ($imgSize && $imgSize[1] > 0) {
                $ratio = $imgSize[0] / $imgSize[1];
                $logoW = $logoH * $ratio;
            } else {
                $logoW = 60;
            }
            $logoX = (210 - $logoW) / 2;
            $logoY = ($hdrH - $logoH) / 2;
            $this->Image($logo, $logoX, $logoY, $logoW, $logoH);
        } else {
            $this->SetXY(0, 10);
            $this->SetFont('Arial', 'B', 24);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(210, 15, 'PetsGo', 0, 0, 'C');
        }

        // Franja amarilla
        $this->SetFillColor($this->secondary[0], $this->secondary[1], $this->secondary[2]);
        $this->Rect(0, $hdrH, 210, 2, 'F');

        $this->SetY($hdrH + 6);
    }

    // ========================
    // FOOTER: Pie de pagina estandar
    // ========================
    function Footer() {
        $this->SetY(-28);

        // Linea azul
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 3, $this->utf8('PetsGo Marketplace - www.petsgo.cl - contacto@petsgo.cl'), 0, 1, 'C');
        $this->Cell(0, 3, $this->utf8('Documento de suscripcion generado electronicamente.'), 0, 1, 'C');
        $this->Cell(0, 3, $this->utf8('Santiago, Chile'), 0, 1, 'C');

        // Pagina
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(160, 160, 160);
        $this->Cell(0, 3, $this->utf8('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    // ========================
    // TITULO BOLETA
    // ========================
    private function buildTitle() {
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 10, $this->utf8('BOLETA DE SUSCRIPCION'), 0, 1, 'C');
        $this->Ln(2);

        // Recuadro con numero y fecha
        $this->SetFillColor(240, 250, 255);
        $this->SetDrawColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $bx = 55; $by = $this->GetY(); $bw = 100;
        $this->Rect($bx, $by, $bw, 16, 'DF');

        $this->SetXY($bx + 3, $by + 2);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->Cell($bw - 6, 6, $this->utf8('No. ') . $this->invoice_number, 0, 2, 'C');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell($bw - 6, 5, $this->utf8('Fecha: ') . $this->date, 0, 2, 'C');

        $this->SetY($by + 22);
    }

    // ========================
    // DATOS DE LA TIENDA (CLIENTE)
    // ========================
    private function buildVendorInfo() {
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 7, $this->utf8('Datos del Cliente'), 0, 1, 'L');

        $this->SetFillColor(248, 249, 250);
        $this->SetDrawColor(220, 220, 220);
        $y = $this->GetY();
        $this->Rect(15, $y, 180, 28, 'DF');

        $this->SetXY(18, $y + 3);
        $this->SetFont('Arial', '', 9);
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
            $yPos = $y + 3 + floor($col / 2) * 10;
            $this->SetXY($xPos, $yPos);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 5, $label . ':', 0, 0);
            $this->SetFont('Arial', '', 9);
            $this->Cell(65, 5, $this->utf8($val), 0, 0);
            $col++;
        }

        $this->SetY($y + 34);
    }

    // ========================
    // DETALLE DEL PLAN
    // ========================
    private function buildPlanDetail() {
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 7, $this->utf8('Detalle del Plan Contratado'), 0, 1, 'L');

        // Tabla de detalle
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetTextColor(255, 255, 255);

        $this->Cell(80, 7, $this->utf8('Concepto'), 1, 0, 'L', true);
        $this->Cell(30, 7, $this->utf8('Periodo'), 1, 0, 'C', true);
        $this->Cell(35, 7, 'Precio', 1, 0, 'R', true);
        $this->Cell(35, 7, 'Total', 1, 1, 'R', true);

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(50, 50, 50);
        $this->SetFillColor(255, 255, 255);

        $plan_name = $this->plan_data->plan_name ?? 'Plan';
        $price     = floatval($this->plan_data->monthly_price ?? 0);
        $period    = $this->plan_data->billing_period ?? 'Mensual';

        $this->Cell(80, 7, $this->utf8('Plan ' . $plan_name . ' - PetsGo'), 1, 0, 'L');
        $this->Cell(30, 7, $this->utf8($period), 1, 0, 'C');
        $this->Cell(35, 7, '$' . number_format($price, 0, ',', '.'), 1, 0, 'R');
        $this->Cell(35, 7, '$' . number_format($price, 0, ',', '.'), 1, 1, 'R');

        // Features del plan
        $features = $this->plan_data->features ?? [];
        if (!empty($features)) {
            $this->Ln(4);
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor($this->primary[0], $this->primary[1], $this->primary[2]);
            $this->Cell(0, 6, $this->utf8('Caracteristicas incluidas en el Plan ' . $plan_name . ':'), 0, 1);

            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(80, 80, 80);
            foreach ($features as $feat) {
                $this->SetX(20);
                $this->Cell(5, 5, chr(149), 0, 0); // bullet
                $this->Cell(0, 5, $this->utf8(' ' . $feat), 0, 1);
            }
        }

        $this->Ln(4);
    }

    // ========================
    // RESUMEN DE PAGO
    // ========================
    private function buildPaymentSummary() {
        $price = floatval($this->plan_data->monthly_price ?? 0);
        $neto  = round($price / 1.19);
        $iva   = $price - $neto;

        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 7, $this->utf8('Resumen de Pago'), 0, 1, 'L');

        $x = 110;

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);

        $this->SetX($x);
        $this->Cell(45, 6, 'Subtotal (Neto):', 0, 0, 'R');
        $this->Cell(35, 6, '$' . number_format($neto, 0, ',', '.'), 0, 1, 'R');

        $this->SetX($x);
        $this->Cell(45, 6, 'IVA (19%):', 0, 0, 'R');
        $this->Cell(35, 6, '$' . number_format($iva, 0, ',', '.'), 0, 1, 'R');

        // Total
        $this->SetX($x);
        $this->SetFont('Arial', 'B', 13);
        $this->SetFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(45, 9, 'TOTAL:', 0, 0, 'R', true);
        $this->Cell(35, 9, '$' . number_format($price, 0, ',', '.'), 0, 1, 'R', true);

        $this->Ln(8);
    }

    // ========================
    // TERMINOS Y CONDICIONES
    // ========================
    private function buildTerms() {
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Cell(0, 6, $this->utf8('Terminos y Condiciones'), 0, 1);

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 120, 120);

        $terms = [
            'La suscripcion se renueva automaticamente al final de cada periodo de facturacion.',
            'El cobro se realiza al inicio de cada ciclo. Puede cancelar en cualquier momento.',
            'Al cancelar, mantiene acceso hasta el final del periodo pagado.',
            'PetsGo se reserva el derecho de modificar precios con 30 dias de anticipacion.',
            'Para soporte: contacto@petsgo.cl | www.petsgo.cl',
        ];

        foreach ($terms as $i => $term) {
            $this->SetX(18);
            $this->Cell(5, 4, ($i + 1) . '.', 0, 0);
            $this->MultiCell(170, 4, $this->utf8($term));
            $this->Ln(1);
        }
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
