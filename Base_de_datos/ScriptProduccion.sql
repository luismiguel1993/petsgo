-- ================================================================
-- PetsGo Marketplace — Script Completo de Base de Datos
-- Versión: Producción v1.0
-- Fecha:   Febrero 2026
-- Motor:   MySQL 5.7+ / MariaDB 10.3+
--
-- USO: Ejecutar en phpMyAdmin o MySQL CLI sobre la BD de producción
--      DESPUÉS de instalar WordPress (las tablas wp_users ya deben existir).
-- ================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. PLANES DE SUSCRIPCIÓN
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_subscriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `plan_name` varchar(100) NOT NULL,
    `monthly_price` decimal(10,2) NOT NULL,
    `features_json` text DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `wp_petsgo_subscriptions` (`id`, `plan_name`, `monthly_price`) VALUES 
(1, 'Basico', 29990.00),
(2, 'Pro', 59990.00),
(3, 'Enterprise', 99990.00);

-- ============================================================
-- 2. TIENDAS (VENDORS)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_vendors` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL,
    `store_name` varchar(255) NOT NULL,
    `rut` varchar(20) NOT NULL,
    `logo_url` text DEFAULT NULL,
    `address` text DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `plan_id` int(11) DEFAULT 1,
    `subscription_start` DATE DEFAULT NULL,
    `subscription_end` DATE DEFAULT NULL,
    `sales_commission` decimal(5,2) DEFAULT 10.00,
    `delivery_fee_cut` decimal(5,2) DEFAULT 5.00,
    `status` varchar(20) DEFAULT 'pending',
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. INVENTARIO DE PRODUCTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_inventory` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `vendor_id` bigint(20) NOT NULL,
    `product_name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `price` decimal(10,2) NOT NULL,
    `stock` int(11) DEFAULT 0,
    `category` varchar(100) DEFAULT NULL,
    `image_id` bigint(20) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. CATEGORÍAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_categories` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) NOT NULL,
    `emoji` varchar(20) DEFAULT '📦',
    `description` varchar(255) DEFAULT '',
    `image_url` varchar(500) DEFAULT '',
    `sort_order` int DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `wp_petsgo_categories` (`name`, `slug`, `emoji`, `description`, `image_url`, `sort_order`, `is_active`) VALUES
('Perros',     'Perros',     '🐕', 'Todo para tu perro',        '', 1,  1),
('Gatos',      'Gatos',      '🐱', 'Todo para tu gato',         '', 2,  1),
('Alimento',   'Alimento',   '🍖', 'Seco y húmedo',             '', 3,  1),
('Snacks',     'Snacks',     '🦴', 'Premios y dental',          '', 4,  1),
('Farmacia',   'Farmacia',   '💊', 'Antiparasitarios y salud',  '', 5,  1),
('Accesorios', 'Accesorios', '🎾', 'Juguetes y más',            '', 6,  1),
('Higiene',    'Higiene',    '🧴', 'Shampoo y aseo',            '', 7,  1),
('Camas',      'Camas',      '🛏️', 'Descanso ideal',            '', 8,  1),
('Paseo',      'Paseo',      '🦮', 'Correas y arneses',         '', 9,  1),
('Ropa',       'Ropa',       '🧥', 'Abrigos y disfraces',       '', 10, 1),
('Ofertas',    'Ofertas',    '🔥', 'Los mejores descuentos',    '', 11, 1),
('Nuevos',     'Nuevos',     '✨', 'Recién llegados',           '', 12, 1);

-- ============================================================
-- 5. PERFILES DE USUARIO (datos extendidos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_user_profiles` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL,
    `first_name` varchar(100) DEFAULT NULL,
    `last_name` varchar(100) DEFAULT NULL,
    `id_type` varchar(20) DEFAULT 'rut',
    `id_number` varchar(30) DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `birth_date` date DEFAULT NULL,
    `vehicle_type` varchar(30) DEFAULT NULL,
    `bank_name` varchar(100) DEFAULT NULL,
    `bank_account_type` varchar(30) DEFAULT NULL,
    `bank_account_number` varchar(50) DEFAULT NULL,
    `region` varchar(60) DEFAULT NULL,
    `comuna` varchar(60) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. PEDIDOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_orders` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `vendor_id` bigint(20) NOT NULL,
    `rider_id` bigint(20) DEFAULT NULL,
    `total_amount` decimal(10,2) NOT NULL,
    `petsgo_commission` decimal(10,2) NOT NULL DEFAULT 0,
    `store_fee` decimal(10,2) DEFAULT 0,
    `petsgo_delivery_fee` decimal(10,2) DEFAULT 0,
    `rider_earning` decimal(10,2) DEFAULT 0,
    `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0,
    `delivery_distance_km` decimal(6,2) DEFAULT NULL,
    `delivery_method` varchar(20) DEFAULT 'delivery',
    `shipping_address` text DEFAULT NULL,
    `shipping_region` varchar(60) DEFAULT NULL,
    `shipping_comuna` varchar(60) DEFAULT NULL,
    `address_detail` varchar(255) DEFAULT NULL,
    `address_type` varchar(20) DEFAULT 'casa',
    `payment_method` varchar(50) DEFAULT NULL,
    `payment_status` varchar(30) DEFAULT 'pending_payment',
    `status` varchar(50) DEFAULT 'payment_pending',
    `coupon_code` varchar(50) DEFAULT NULL,
    `discount_amount` decimal(10,2) DEFAULT 0,
    `purchase_group` varchar(36) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `vendor_id` (`vendor_id`),
    KEY `customer_id` (`customer_id`),
    KEY `rider_id` (`rider_id`),
    KEY `idx_purchase_group` (`purchase_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. ITEMS DE PEDIDO
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_order_items` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) NOT NULL,
    `product_id` bigint(20) DEFAULT NULL,
    `product_name` varchar(255) NOT NULL,
    `quantity` int(11) NOT NULL DEFAULT 1,
    `unit_price` decimal(10,2) NOT NULL DEFAULT 0,
    `subtotal` decimal(10,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. FACTURAS / BOLETAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_invoices` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) NOT NULL,
    `vendor_id` bigint(20) NOT NULL,
    `invoice_number` varchar(50) NOT NULL,
    `qr_token` varchar(100) DEFAULT NULL,
    `html_content` longtext DEFAULT NULL,
    `pdf_path` varchar(500) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`),
    KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. DOCUMENTOS DE RIDER
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_rider_documents` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `rider_id` bigint(20) NOT NULL,
    `doc_type` varchar(50) NOT NULL,
    `file_url` text NOT NULL,
    `file_name` varchar(255) DEFAULT NULL,
    `status` varchar(20) DEFAULT 'pending',
    `admin_notes` text DEFAULT NULL,
    `reviewed_by` bigint(20) DEFAULT NULL,
    `reviewed_at` datetime DEFAULT NULL,
    `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `rider_id` (`rider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. VALORACIONES DE ENTREGAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_delivery_ratings` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) NOT NULL,
    `rider_id` bigint(20) NOT NULL,
    `rater_type` varchar(20) NOT NULL,
    `rater_id` bigint(20) NOT NULL,
    `rating` tinyint(1) NOT NULL DEFAULT 5,
    `comment` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_rating` (`order_id`, `rater_type`),
    KEY `rider_id` (`rider_id`),
    KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. OFERTAS DE ENTREGA A RIDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_rider_delivery_offers` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `order_id` bigint(20) NOT NULL,
    `rider_id` bigint(20) NOT NULL,
    `status` varchar(20) DEFAULT 'pending',
    `offered_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `responded_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `order_rider` (`order_id`, `rider_id`),
    KEY `rider_id` (`rider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. PAGOS A RIDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_rider_payouts` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `rider_id` bigint(20) NOT NULL,
    `period_start` date NOT NULL,
    `period_end` date NOT NULL,
    `total_deliveries` int DEFAULT 0,
    `total_earned` decimal(10,2) DEFAULT 0,
    `total_tips` decimal(10,2) DEFAULT 0,
    `total_deductions` decimal(10,2) DEFAULT 0,
    `net_amount` decimal(10,2) DEFAULT 0,
    `status` varchar(20) DEFAULT 'pending',
    `paid_at` datetime DEFAULT NULL,
    `payment_proof_url` varchar(500) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `rider_id` (`rider_id`),
    KEY `period` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13. TICKETS DE SOPORTE
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_tickets` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `ticket_number` varchar(20) NOT NULL,
    `user_id` bigint(20) NOT NULL,
    `user_name` varchar(100) DEFAULT '',
    `user_email` varchar(100) DEFAULT '',
    `user_role` varchar(30) DEFAULT 'cliente',
    `subject` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `image_url` text DEFAULT NULL,
    `category` varchar(50) DEFAULT 'general',
    `priority` varchar(20) DEFAULT 'media',
    `status` varchar(20) DEFAULT 'abierto',
    `assigned_to` bigint(20) DEFAULT NULL,
    `assigned_name` varchar(100) DEFAULT '',
    `resolved_at` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ticket_number` (`ticket_number`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    KEY `assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 14. RESPUESTAS A TICKETS
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_ticket_replies` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `ticket_id` bigint(20) NOT NULL,
    `user_id` bigint(20) NOT NULL,
    `user_name` varchar(100) DEFAULT '',
    `user_role` varchar(30) DEFAULT '',
    `message` text NOT NULL,
    `image_url` text DEFAULT NULL,
    `is_internal` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 15. HISTORIAL DE CHAT (IA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_chat_history` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL,
    `messages` longtext NOT NULL,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 16. CUPONES DE DESCUENTO
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_coupons` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `code` varchar(50) NOT NULL,
    `description` varchar(255) DEFAULT '',
    `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` decimal(10,2) NOT NULL DEFAULT 0,
    `min_purchase` decimal(10,2) NOT NULL DEFAULT 0,
    `max_discount` decimal(10,2) NOT NULL DEFAULT 0,
    `usage_limit` int NOT NULL DEFAULT 0,
    `usage_count` int NOT NULL DEFAULT 0,
    `per_user_limit` int NOT NULL DEFAULT 0,
    `vendor_ids` text DEFAULT NULL,
    `vendor_id` bigint(20) DEFAULT NULL,
    `created_by` bigint(20) NOT NULL,
    `valid_from` datetime DEFAULT NULL,
    `valid_until` datetime DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 17. LOG DE AUDITORÍA
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_audit_log` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL DEFAULT 0,
    `user_name` varchar(100) DEFAULT '',
    `action` varchar(100) NOT NULL,
    `entity_type` varchar(50) DEFAULT '',
    `entity_id` bigint(20) DEFAULT 0,
    `details` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT '',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `entity_type` (`entity_type`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 18. LEADS (solicitudes de tiendas nuevas)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_petsgo_leads` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `store_name` varchar(255) NOT NULL,
    `contact_name` varchar(255) NOT NULL,
    `email` varchar(100) NOT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `region` varchar(100) DEFAULT '',
    `comuna` varchar(100) DEFAULT '',
    `message` text DEFAULT NULL,
    `plan_name` varchar(100) DEFAULT NULL,
    `status` varchar(30) DEFAULT 'nuevo',
    `admin_notes` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- FIN DEL SCRIPT — 18 tablas PetsGo creadas correctamente
-- ================================================================
