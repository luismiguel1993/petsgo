-- =============================================
-- PetsGo Marketplace - Datos de Demostración
-- =============================================

-- Corregir encoding del plan Basico
UPDATE wp_petsgo_subscriptions SET plan_name = 'Basico' WHERE id = 1;

-- =============================================
-- Crear usuarios demo en WordPress
-- =============================================
-- Password para todos los demo: Demo1234!
-- MD5 hash de 'Demo1234!' (WordPress rehashea en primer login)

INSERT IGNORE INTO wp_users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name)
VALUES
('tienda_pets_happy', '$P$BDummyHashPetsHappy1234567890abc', 'tienda-pets-happy', 'petshappy@demo.cl', NOW(), 0, 'Pets Happy Store'),
('tienda_mundo_animal', '$P$BDummyHashMundoAnimal123456789a', 'tienda-mundo-animal', 'mundoanimal@demo.cl', NOW(), 0, 'Mundo Animal'),
('tienda_patitas', '$P$BDummyHashPatitas12345678901234', 'tienda-patitas', 'patitas@demo.cl', NOW(), 0, 'Patitas Chile'),
('rider_carlos', '$P$BDummyHashRiderCarlos1234567890', 'rider-carlos', 'carlos.rider@demo.cl', NOW(), 0, 'Carlos Rider'),
('cliente_maria', '$P$BDummyHashClienteMaria12345678', 'cliente-maria', 'maria@demo.cl', NOW(), 0, 'Maria Gonzalez');

-- Asignar roles a usuarios demo
INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT u.ID, 'wp_capabilities', 'a:1:{s:13:"petsgo_vendor";b:1;}'
FROM wp_users u WHERE u.user_login IN ('tienda_pets_happy','tienda_mundo_animal','tienda_patitas');

INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT u.ID, 'wp_user_level', '0'
FROM wp_users u WHERE u.user_login IN ('tienda_pets_happy','tienda_mundo_animal','tienda_patitas');

INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT u.ID, 'wp_capabilities', 'a:1:{s:12:"petsgo_rider";b:1;}'
FROM wp_users u WHERE u.user_login = 'rider_carlos';

INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT u.ID, 'wp_user_level', '0'
FROM wp_users u WHERE u.user_login = 'rider_carlos';

INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT u.ID, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'
FROM wp_users u WHERE u.user_login = 'cliente_maria';

INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT u.ID, 'wp_user_level', '0'
FROM wp_users u WHERE u.user_login = 'cliente_maria';

-- =============================================
-- Crear tiendas (vendors)
-- =============================================
INSERT IGNORE INTO wp_petsgo_vendors (user_id, store_name, rut, logo_url, address, phone, email, plan_id, sales_commission, delivery_fee_cut, status)
SELECT u.ID, 'Pets Happy Store', '76.123.456-7', NULL, 'Av. Providencia 1234, Santiago', '+56912345678', 'petshappy@demo.cl', 2, 10.00, 5.00, 'active'
FROM wp_users u WHERE u.user_login = 'tienda_pets_happy';

INSERT IGNORE INTO wp_petsgo_vendors (user_id, store_name, rut, logo_url, address, phone, email, plan_id, sales_commission, delivery_fee_cut, status)
SELECT u.ID, 'Mundo Animal', '76.234.567-8', NULL, 'Av. Las Condes 5678, Santiago', '+56923456789', 'mundoanimal@demo.cl', 3, 5.00, 5.00, 'active'
FROM wp_users u WHERE u.user_login = 'tienda_mundo_animal';

INSERT IGNORE INTO wp_petsgo_vendors (user_id, store_name, rut, logo_url, address, phone, email, plan_id, sales_commission, delivery_fee_cut, status)
SELECT u.ID, 'Patitas Chile', '76.345.678-9', NULL, 'Calle Merced 910, Valparaiso', '+56934567890', 'patitas@demo.cl', 1, 15.00, 5.00, 'active'
FROM wp_users u WHERE u.user_login = 'tienda_patitas';

-- =============================================
-- Crear productos de inventario
-- =============================================

-- Productos de Pets Happy Store (vendor_id se obtiene dinámicamente)
INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Royal Canin Adulto 15kg', 'Alimento premium para perros adultos. Rico en proteinas y nutrientes esenciales.', 45990.00, 25, 'Alimento', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Cama Ortopedica XL', 'Cama ortopedica premium para perros grandes. Memory foam de alta densidad.', 69990.00, 10, 'Accesorios', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Collar LED Nocturno', 'Collar con luz LED recargable para paseos nocturnos. Resistente al agua.', 12990.00, 50, 'Accesorios', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Shampoo Hipoalergenico 500ml', 'Shampoo suave para pieles sensibles. Sin parabenos ni sulfatos.', 8990.00, 40, 'Higiene', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store';

-- Productos de Mundo Animal
INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Whiskas Gato Adulto 10kg', 'Alimento completo para gatos adultos. Sabor atun y salmon.', 32990.00, 30, 'Alimento', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Rascador Torre Gato 120cm', 'Torre rascador multinivel con casita y plataformas. Sisal natural.', 54990.00, 8, 'Accesorios', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Transportador Aerolinea M', 'Transportador aprobado por aerolineas. Ventilacion premium.', 39990.00, 15, 'Transporte', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Juguete Interactivo Laser', 'Laser automatico con patron aleatorio para gatos. Recargable USB.', 15990.00, 35, 'Juguetes', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Antipulgas Frontline 3 Dosis', 'Pipetas antipulgas y garrapatas. Para gatos y perros medianos.', 24990.00, 60, 'Farmacia', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal';

-- Productos de Patitas Chile
INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Arnes Ergonomico Talla M', 'Arnes anti-tiron con acolchado. Reflectante para paseos nocturnos.', 18990.00, 20, 'Accesorios', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Comedero Automatico WiFi', 'Dispensador de comida programable. Control desde app movil.', 79990.00, 5, 'Tecnologia', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Snacks Dentales Pack x30', 'Premios funcionales para higiene dental. Todas las razas.', 11990.00, 45, 'Snacks', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile';

INSERT INTO wp_petsgo_inventory (vendor_id, product_name, description, price, stock, category, image_id)
SELECT v.id, 'Pelota Indestructible Kong', 'Juguete ultra resistente para masticadores agresivos. Caucho natural.', 14990.00, 30, 'Juguetes', NULL
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile';

-- =============================================
-- Crear pedidos de ejemplo
-- =============================================
INSERT INTO wp_petsgo_orders (customer_id, vendor_id, rider_id, total_amount, petsgo_commission, delivery_fee, status, created_at)
SELECT 
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria'),
    v.id,
    (SELECT ID FROM wp_users WHERE user_login = 'rider_carlos'),
    45990.00,
    4599.00,
    3990.00,
    'delivered',
    DATE_SUB(NOW(), INTERVAL 5 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store';

INSERT INTO wp_petsgo_orders (customer_id, vendor_id, rider_id, total_amount, petsgo_commission, delivery_fee, status, created_at)
SELECT 
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria'),
    v.id,
    NULL,
    87980.00,
    4399.00,
    3990.00,
    'preparing',
    DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal';

INSERT INTO wp_petsgo_orders (customer_id, vendor_id, rider_id, total_amount, petsgo_commission, delivery_fee, status, created_at)
SELECT 
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria'),
    v.id,
    (SELECT ID FROM wp_users WHERE user_login = 'rider_carlos'),
    18990.00,
    2848.50,
    3990.00,
    'in_transit',
    NOW()
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile';
