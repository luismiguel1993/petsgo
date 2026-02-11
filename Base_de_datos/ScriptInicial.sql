-- Script de Creación de Tablas para PetsGo Marketplace
-- Arquitecto: Automatiza Tech System
-- Objetivo: Soporte multi-vendor, roles de usuario y lógica de comisiones.

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
    `sales_commission` decimal(5,2) DEFAULT 10.00, -- Porcentaje para PetsGo
    `delivery_fee_cut` decimal(5,2) DEFAULT 5.00,  -- Porcentaje para PetsGo por despacho
    `status` varchar(20) DEFAULT 'pending', -- pending, active, suspended
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `wp_petsgo_subscriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `plan_name` varchar(100) NOT NULL,
    `monthly_price` decimal(10,2) NOT NULL,
    `features_json` text DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wp_petsgo_orders` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `vendor_id` bigint(20) NOT NULL,
    `rider_id` bigint(20) DEFAULT NULL,
    `total_amount` decimal(10,2) NOT NULL,
    `petsgo_commission` decimal(10,2) NOT NULL,
    `delivery_fee` decimal(10,2) NOT NULL,
    `status` varchar(50) DEFAULT 'payment_pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `vendor_id` (`vendor_id`),
    KEY `customer_id` (`customer_id`),
    KEY `rider_id` (`rider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar planes iniciales de ejemplo
INSERT IGNORE INTO `wp_petsgo_subscriptions` (plan_name, monthly_price) VALUES 
('Básico', 29990.00),
('Pro', 59990.00),
('Enterprise', 99990.00);