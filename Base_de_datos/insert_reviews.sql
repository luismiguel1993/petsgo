-- Insert demo reviews (standalone script)

-- Product review: Royal Canin Adulto 15kg (Pets Happy Store) rating 5
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Royal Canin Adulto 15kg' LIMIT 1),
    v.id,
    'product', 5,
    'Excelente alimento, mi perro lo ama. Llegó bien empacado y en perfecto estado. La entrega fue muy rápida. 100% recomendado.',
    DATE_SUB(NOW(), INTERVAL 4 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store' LIMIT 1;

-- Vendor review: Pets Happy Store rating 5
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    NULL,
    v.id,
    'vendor', 5,
    'Tienda excelente, muy buena atención. El despacho fue rapidísimo y el producto llegó sellado. Volveré a comprar seguro.',
    DATE_SUB(NOW(), INTERVAL 4 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store' LIMIT 1;

-- Product review: Whiskas Gato Adulto 10kg (Mundo Animal) rating 4
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Whiskas Gato Adulto 10kg' LIMIT 1),
    v.id,
    'product', 4,
    'Buen alimento para mi gatita. A ella le encanta el sabor atún. Solo le bajo una estrella porque el envase llegó un poco abollado, pero el producto estaba en buen estado.',
    DATE_SUB(NOW(), INTERVAL 9 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal' LIMIT 1;

-- Vendor review: Mundo Animal rating 4
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    NULL,
    v.id,
    'vendor', 4,
    'Buena tienda con variedad de productos. El envío demoró un día más de lo esperado pero todo llegó bien. Recomendada.',
    DATE_SUB(NOW(), INTERVAL 9 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal' LIMIT 1;

-- Product review: Pelota Indestructible Kong (Patitas Chile) rating 5
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Pelota Indestructible Kong' LIMIT 1),
    v.id,
    'product', 5,
    'La pelota Kong es increíble, mi perro no ha podido destruirla en 3 días (récord mundial jaja). Muy resistente y le encanta jugar con ella. Súper recomendada para perros mordedores.',
    DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile' LIMIT 1;

-- Vendor review: Patitas Chile rating 5
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    NULL,
    v.id,
    'vendor', 5,
    'Patitas Chile es genial! Precios justos y envío rápido. El producto venía bien envuelto con una notita de agradecimiento. Detallazo.',
    DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile' LIMIT 1;

-- Extra product reviews for more data
-- Cama Ortopedica XL (Pets Happy Store) rating 5
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Cama Ortopedica XL' LIMIT 1),
    v.id,
    'product', 5,
    'La cama es espectacular, mi perro la adoptó inmediatamente. Material de primera calidad y muy fácil de lavar. Vale cada peso.',
    DATE_SUB(NOW(), INTERVAL 6 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store' LIMIT 1;

-- Collar LED Nocturno (Pets Happy Store) rating 4
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Collar LED Nocturno' LIMIT 1),
    v.id,
    'product', 4,
    'Muy útil para los paseos nocturnos. La batería dura bastante. Le bajo una estrella porque el broche podría ser más resistente.',
    DATE_SUB(NOW(), INTERVAL 5 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Pets Happy Store' LIMIT 1;

-- Rascador Torre Gato (Mundo Animal) rating 5
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Rascador Torre Gato 120cm' LIMIT 1),
    v.id,
    'product', 5,
    'Mi gata Luna está obsesionada con este rascador. Dejó de arañar los muebles desde que lo tenemos. Excelente inversión.',
    DATE_SUB(NOW(), INTERVAL 7 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Mundo Animal' LIMIT 1;

-- Antipulgas Frontline (Patitas Chile) rating 4
INSERT INTO wp_petsgo_reviews (customer_id, order_id, product_id, vendor_id, review_type, rating, comment, created_at)
SELECT
    (SELECT ID FROM wp_users WHERE user_login = 'cliente_maria' LIMIT 1),
    (SELECT o.id FROM wp_petsgo_orders o WHERE o.vendor_id=v.id AND o.status='delivered' LIMIT 1),
    (SELECT i.id FROM wp_petsgo_inventory i WHERE i.vendor_id=v.id AND i.product_name='Antipulgas Frontline 3 Dosis' LIMIT 1),
    v.id,
    'product', 4,
    'Funciona muy bien contra las pulgas. Es un poco caro pero vale la pena por la tranquilidad. Mi perrito quedó libre de bichos.',
    DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM wp_petsgo_vendors v WHERE v.store_name = 'Patitas Chile' LIMIT 1;
