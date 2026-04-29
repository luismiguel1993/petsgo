# 🏗️ PetsGo Marketplace — Contexto Técnico Completo

> **Propósito:** Este documento sirve como contexto completo para cualquier IA o desarrollador que necesite comprender, modificar o extender el sistema PetsGo. Contiene TODA la arquitectura, stack, endpoints, tablas, roles, flujos y convenciones del proyecto.

**Última actualización:** Febrero 2026  
**Repositorio:** `github.com/luismiguel1993/petsgo` — Rama activa: `develop`

---

## 1. RESUMEN DEL PROYECTO

| Campo | Valor |
|---|---|
| **Nombre** | PetsGo Marketplace |
| **Modelo** | Marketplace multi-vendor estilo "Pedidos Ya" para Petshops |
| **Cliente** | Alexiandra Andrade |
| **Fase** | Fase 1 — Sitio Web Maestro |
| **Público** | Dueños de mascotas en Chile (incluye adultos mayores) |
| **País** | Chile — Moneda CLP ($XX.XXX) |
| **URL Producción** | `https://petsgo.cl` |
| **URL Local** | `http://localhost:5173` (frontend) / `http://localhost/PetsGoDev` (WordPress) |

---

## 2. STACK TECNOLÓGICO

| Capa | Tecnología | Versión |
|---|---|---|
| **Backend** | WordPress Headless (PHP) | WP 6.x / PHP 8+ |
| **Frontend** | React + Vite | React 19.2.0 / Vite 7.2.4 |
| **CSS** | Tailwind CSS | v4.1.18 |
| **HTTP Client** | Axios | 1.13.4 |
| **Router** | React Router DOM | 7.13.0 |
| **Icons** | Lucide React | 0.563.0 |
| **PDF (frontend)** | jsPDF + AutoTable | 4.1.0 / 5.0.7 |
| **PDF (backend)** | FPDF + QR Code | Custom classes |
| **Base de Datos** | MySQL / MariaDB | 5.7+ / 10.3+ |
| **Entorno Local** | WAMP | PHP + MySQL + Apache |
| **Hosting Prod** | Hostinger (Business Web Hosting) | Build estático + WordPress |
| **Chatbot IA** | Proxy a AutomatizaTech → OpenAI (gpt-4o-mini) | Server-side relay |

---

## 3. ARQUITECTURA

```
┌─────────────────────────────────────────────────┐
│              FRONTEND (React 19 + Vite)         │
│  localhost:5173 / petsgo.cl                     │
│  SPA con React Router — 23 páginas              │
│  Context: AuthProvider > CartProvider > Site     │
└───────────────────┬─────────────────────────────┘
                    │ HTTP (Axios)
                    ▼
┌─────────────────────────────────────────────────┐
│         BACKEND (WordPress Headless)            │
│  /wp-json/petsgo/v1/*  — 71 REST endpoints     │
│  MU-Plugin: petsgo-core.php (~13,186 líneas)    │
│  Auth: Bearer Token custom (petsgo_api_token)   │
│  Admin: wp-admin con 20+ páginas AJAX           │
└───────────────────┬─────────────────────────────┘
                    │ $wpdb
                    ▼
┌─────────────────────────────────────────────────┐
│            BASE DE DATOS (MySQL)                │
│  20+ tablas custom con prefijo wp_petsgo_*      │
│  + tablas estándar de WordPress (wp_users, etc) │
└─────────────────────────────────────────────────┘
```

### Comunicación Frontend ↔ Backend
- **Desarrollo:** Vite proxy `/wp-json` → `http://localhost/PetsGoDev`
- **Producción:** La app compilada (`dist/`) y WordPress coexisten en `public_html/`
- **CORS:** Whitelist de orígenes locales (5173, 5174, 5177, 3000) + petsgo.cl
- **Auth:** Bearer token en header `Authorization` + fallback `X-PetsGo-Token`

---

## 4. ESTRUCTURA DE ARCHIVOS

```
PetsGoDev/
├── frontend/                          # App React (SPA)
│   ├── src/
│   │   ├── components/                # 5 componentes globales
│   │   │   ├── Header.jsx             # Navbar con carrito y menú usuario
│   │   │   ├── Footer.jsx             # Footer con links y RRSS
│   │   │   ├── FloatingCart.jsx        # Panel lateral de carrito
│   │   │   ├── BotChatOverlay.jsx     # Widget chatbot IA flotante
│   │   │   └── PromoSlider.jsx        # Slider de promociones
│   │   ├── context/                   # 3 Context Providers
│   │   │   ├── AuthContext.jsx        # Estado de autenticación
│   │   │   ├── CartContext.jsx        # Estado del carrito
│   │   │   └── SiteContext.jsx        # Settings públicos + categorías
│   │   ├── pages/                     # 23 páginas
│   │   │   ├── HomePage.jsx           # Landing con hero, categorías, productos top
│   │   │   ├── LoginPage.jsx          # Login
│   │   │   ├── RegisterPage.jsx       # Registro cliente
│   │   │   ├── RiderRegisterPage.jsx  # Registro rider (3 pasos)
│   │   │   ├── RiderVerifyEmailPage.jsx # Verificación email rider
│   │   │   ├── ForgotPasswordPage.jsx # Solicitar reset contraseña
│   │   │   ├── ResetPasswordPage.jsx  # Nueva contraseña con token
│   │   │   ├── ForceChangePasswordPage.jsx # Cambio forzado
│   │   │   ├── UserProfilePage.jsx    # Perfil + mascotas
│   │   │   ├── VendorsPage.jsx        # Listado de tiendas
│   │   │   ├── VendorDetailPage.jsx   # Detalle tienda + productos
│   │   │   ├── ProductDetailPage.jsx  # Detalle producto + reseñas
│   │   │   ├── CategoryPage.jsx       # Productos por categoría
│   │   │   ├── CartPage.jsx           # Carrito de compras
│   │   │   ├── PlansPage.jsx          # Planes para vendedores
│   │   │   ├── MyOrdersPage.jsx       # Historial pedidos del cliente
│   │   │   ├── VendorDashboard.jsx    # Panel vendedor
│   │   │   ├── AdminDashboard.jsx     # Panel administrador
│   │   │   ├── RiderDashboard.jsx     # Panel rider (estilo Uber/Rappi)
│   │   │   ├── SupportPage.jsx        # Tickets de soporte
│   │   │   ├── HelpCenterPage.jsx     # Centro de ayuda + FAQs
│   │   │   ├── LegalPage.jsx          # Términos, privacidad, envíos
│   │   │   └── InvoiceVerifyPage.jsx  # Verificación pública de boleta QR
│   │   ├── services/
│   │   │   └── api.js                 # 466 líneas — todos los endpoints
│   │   ├── utils/
│   │   │   ├── chile.js               # Regiones y comunas de Chile
│   │   │   └── productImages.js       # Utilidades de imágenes
│   │   ├── data/
│   │   │   └── chileRegions.js        # Data regiones
│   │   ├── App.jsx                    # Router principal
│   │   └── main.jsx                   # Entry point con Providers
│   ├── package.json
│   ├── vite.config.js
│   └── public/
│
├── wp-content/
│   └── mu-plugins/
│       ├── petsgo-core.php            # ⭐ CORAZÓN DEL BACKEND (~13,186 líneas)
│       └── petsgo-lib/
│           ├── fpdf.php               # Librería FPDF
│           ├── qrcode.php             # Generador QR
│           ├── invoice-pdf.php        # PDF de boletas de compra (428 líneas)
│           ├── subscription-pdf.php   # PDF de boletas de suscripción (454 líneas)
│           ├── logo-petsgo.png        # Logo para PDFs
│           └── font/                  # Fuentes PDF
│
├── Base_de_datos/
│   ├── ScriptProduccion.sql           # 18 tablas — script completo para producción
│   ├── ScriptInicial.sql              # Script original básico
│   ├── DemoData.sql                   # Usuarios y datos demo
│   └── insert_reviews.sql             # Reseñas demo
│
├── Docs/
│   ├── Documentacion_Tecnica.md       # Documentación técnica resumida
│   ├── Manual_Usuario.md              # Manual por roles
│   ├── Guia_Despliegue_Hostinger.md   # Guía paso a paso para producción
│   ├── Guia_Capacitor_Mobile.md       # (Vacío — futuro)
│   ├── CONTEXTO_CHATBOT_PETSGO_AUTOMATIZATECH.md # Arquitectura del chatbot
│   └── QA/                            # 11 suites de pruebas (391 casos)
│
├── Petsgo_Diseño/                     # Assets de marca (SVG, PNG, JPG, AI)
├── copilot-petsgo.md                  # Instrucciones para GitHub Copilot
├── wp-config.php                      # Config WP (auto-detect local/prod)
└── README.md                          # Documentación del proyecto
```

---

## 5. BASE DE DATOS — TABLAS CUSTOM (20+)

| # | Tabla | Propósito | Campos Clave |
|---|---|---|---|
| 1 | `wp_petsgo_subscriptions` | Planes de suscripción | plan_name, monthly_price, features_json |
| 2 | `wp_petsgo_vendors` | Tiendas | user_id, store_name, rut, sales_commission, status |
| 3 | `wp_petsgo_inventory` | Productos | vendor_id, product_name, price, stock, category |
| 4 | `wp_petsgo_categories` | Categorías | name, slug, emoji, sort_order, is_active |
| 5 | `wp_petsgo_user_profiles` | Perfiles extendidos | user_id, id_type, id_number, phone, region, comuna, is_online, last_online, vehicle_type |
| 6 | `wp_petsgo_orders` | Pedidos | customer_id, vendor_id, rider_id, rider_response, rider_assigned_at, rider_responded_at, estimated_minutes, total_amount, status, delivery_method, commission splits |
| 7 | `wp_petsgo_order_items` | Items por pedido | order_id, product_id, quantity, unit_price, subtotal |
| 8 | `wp_petsgo_invoices` | Boletas/Facturas | order_id, invoice_number, qr_token, pdf_path |
| 9 | `wp_petsgo_rider_documents` | Documentos rider | rider_id, doc_type, file_url, status, admin_notes, **expiry_date**, expiry_notified_30/15/1 |
| 10 | `wp_petsgo_delivery_ratings` | Valoraciones de entregas | order_id, rider_id, rater_type, rating (1-5) |
| 11 | `wp_petsgo_rider_delivery_offers` | Ofertas de entrega | order_id, rider_id, status |
| 12 | `wp_petsgo_rider_payouts` | Pagos a riders | rider_id, period, total_earned, net_amount |
| 13 | `wp_petsgo_tickets` | Tickets de soporte | ticket_number, user_id, subject, priority, status |
| 14 | `wp_petsgo_ticket_replies` | Respuestas a tickets | ticket_id, user_id, message |
| 15 | `wp_petsgo_chat_history` | Historial chatbot | user_id, messages (JSON), conversation_id |
| 16 | `wp_petsgo_coupons` | Cupones de descuento | code, discount_type, discount_value, vendor_id |
| 17 | `wp_petsgo_audit_log` | Log de auditoría | user_id, action, entity_type, entity_id, details |
| 18 | `wp_petsgo_leads` | Leads de tiendas | store_name, contact_name, email, plan_name |
| 19 | `wp_petsgo_reviews` | Reseñas de productos/tiendas | type (product/vendor), entity_id, rating, comment |
| 20 | `wp_petsgo_pets` | Mascotas de usuarios | user_id, name, type, breed, weight |
| 21 | `wp_petsgo_password_resets` | Tokens de reset password | user_id, token, expires_at, used |

---

## 6. ROLES Y PERMISOS

| Rol | Slug WP | Acceso Frontend | Acceso WP Admin | Capabilities |
|---|---|---|---|---|
| **Cliente** | `subscriber` | /, /perfil, /mis-pedidos, /soporte | No | read |
| **Vendedor** | `petsgo_vendor` | /vendor (dashboard) | Sí (limitado) | read, upload_files, manage_inventory |
| **Rider** | `petsgo_rider` | /rider (dashboard) | No | read, upload_files, manage_deliveries |
| **Soporte** | `petsgo_support` | — | Sí (tickets) | read, moderate_comments, manage_support_tickets |
| **Admin** | `administrator` | /admin (dashboard), todo | Sí (completo) | Todas |

### Flujo de estados del Rider:
`pending_email` → (verifica email) → `pending_docs` → (sube documentos) → `pending_review` → (admin aprueba) → `approved` / `rejected`

**Bloqueo por vigencia:** `approved` → (cron detecta docs vencidos) → `suspended` → (rider re-sube docs + admin aprueba) → `approved`

**Vigencia documental:**
- Documentos con vencimiento obligatorio: `id_card`, `license`, `vehicle_registration`
- Admin ingresa fecha al aprobar → notificaciones automáticas a 30/15/1 días → bloqueo si expira
- `GET /rider/status` retorna `expiry_alerts[]` con `doc_type`, `doc_label`, `expiry_date`, `days_left`, `expiry_status` (ok/warning/critical/expired)

---

## 7. API REST — ENDPOINTS COMPLETOS (71 rutas)

Namespace: `/wp-json/petsgo/v1`

### 7.1 Públicos (sin autenticación)

| Método | Ruta | Función | Descripción |
|---|---|---|---|
| GET | `/products` | `api_get_products` | Catálogo con filtros (vendor_id, category, search, paginación) |
| GET | `/products/{id}` | `api_get_product_detail` | Detalle de producto |
| GET | `/products/{id}/reviews` | `api_get_product_reviews` | Reseñas de un producto |
| GET | `/vendors` | `api_get_vendors` | Tiendas activas |
| GET | `/vendors/{id}` | `api_get_vendor_detail` | Detalle de tienda |
| GET | `/vendors/{id}/reviews` | `api_get_vendor_reviews` | Reseñas de una tienda |
| GET | `/plans` | `api_get_plans` | Planes de suscripción |
| GET | `/categories` | `api_get_categories` | Categorías activas ordenadas |
| GET | `/public-settings` | `api_get_public_settings` | Config pública (despacho gratis, colores, etc.) |
| GET | `/legal/{slug}` | `api_get_legal_page` | Contenido legal (términos, privacidad, envíos) |
| GET | `/chatbot-config` | `api_get_chatbot_config` | Configuración del chatbot (prompt, categorías, planes) |
| GET | `/invoice/validate/{token}` | `api_validate_invoice` | Verificación pública de boleta por QR |
| GET | `/rider/policy` | `api_get_rider_policy` | Política de comisiones y términos rider |
| POST | `/auth/login` | `api_login` | Inicio de sesión (retorna token + user) |
| POST | `/auth/register` | `api_register` | Registro de cliente |
| POST | `/auth/register-rider` | `api_register_rider` | Registro de rider (paso 1) |
| POST | `/auth/verify-rider-email` | `api_verify_rider_email` | Verificación email rider (código 6 dígitos) |
| POST | `/auth/resend-rider-verification` | `api_resend_rider_verification` | Reenviar código verificación |
| POST | `/auth/forgot-password` | `api_forgot_password` | Solicitar reset de contraseña |
| POST | `/auth/reset-password` | `api_reset_password` | Restablecer contraseña con token |
| POST | `/coupons/validate` | `api_validate_coupon` | Validar cupón en checkout |
| POST | `/vendor-lead` | `api_submit_vendor_lead` | Formulario de contacto para tiendas interesadas |
| POST | `/delivery/calculate-fee` | `api_calculate_delivery_fee` | Calcular tarifa de delivery por distancia |
| POST | `/chatbot-send` | `api_chatbot_send` | Enviar mensaje al chatbot (proxy a AutomatizaTech) |

### 7.2 Autenticados (requieren token)

| Método | Ruta | Función | Descripción |
|---|---|---|---|
| GET | `/profile` | `api_get_profile` | Perfil del usuario + mascotas |
| PUT | `/profile` | `api_update_profile` | Actualizar nombre, teléfono |
| POST | `/profile/change-password` | `api_change_password` | Cambiar contraseña |
| GET/POST | `/pets` | `api_get_pets` / `api_add_pet` | CRUD mascotas |
| PUT/DEL | `/pets/{id}` | `api_update_pet` / `api_delete_pet` | Editar/eliminar mascota |
| POST | `/pets/upload-photo` | `api_upload_pet_photo` | Subir foto de mascota |
| POST | `/orders` | `api_create_order` | Crear pedido |
| GET | `/orders/mine` | `api_get_my_orders` | Mis pedidos |
| POST | `/orders/{id}/rate-rider` | `api_rate_rider` | Valorar rider |
| GET | `/orders/{id}/review-status` | `api_get_order_review_status` | Estado de reseñas del pedido |
| POST | `/reviews` | `api_submit_review` | Enviar reseña de producto/tienda |
| GET/POST | `/tickets` | `api_get_tickets` / `api_create_ticket` | Soporte |
| GET | `/tickets/{id}` | `api_get_ticket_detail` | Detalle ticket |
| POST | `/tickets/{id}/reply` | `api_add_ticket_reply_rest` | Responder ticket |
| GET | `/chatbot-user-context` | `api_chatbot_user_context` | Contexto del usuario para el bot |
| GET/POST | `/chatbot-history` | `api_get/save_chat_history` | Historial de chat |
| GET/POST/DEL | `/chatbot-conversations/*` | Conversaciones | Multi-conversación chatbot |

### 7.3 Vendor (requieren rol petsgo_vendor)

| Método | Ruta | Función |
|---|---|---|
| GET/POST | `/vendor/inventory` | Inventario de la tienda |
| GET | `/vendor/dashboard` | Dashboard con métricas |
| GET/POST/DEL | `/vendor/coupons*` | CRUD cupones propios |
| GET | `/vendor/orders` | Pedidos de la tienda |
| PUT | `/vendor/orders/{id}/status` | Cambiar estado de pedido |

### 7.4 Rider (requieren rol petsgo_rider)

| Método | Ruta | Función |
|---|---|---|
| GET | `/rider/documents` | Mis documentos |
| POST | `/rider/documents/upload` | Subir documento |
| GET | `/rider/status` | Mi estado actual |
| GET | `/rider/deliveries` | Entregas asignadas al rider |
| GET | `/rider/deliveries/available` | Pedidos disponibles para tomar (sin rider asignado) |
| POST | `/rider/deliveries/{id}/claim` | Rider toma un pedido disponible (auto-asignación atómica) |
| PUT | `/rider/deliveries/{id}/status` | Actualizar estado entrega (rider_assigned→in_transit→delivered) |
| POST | `/rider/deliveries/{id}/respond` | Aceptar/rechazar asignación del admin |
| POST | `/rider/toggle-availability` | Activar/desactivar disponibilidad online |
| GET/PUT | `/rider/profile` | Perfil + datos bancarios |
| GET | `/rider/earnings` | Ganancias y pagos |
| GET | `/rider/ratings` | Mis valoraciones |
| GET | `/rider/stats` | Estadísticas (week/month/year) |

### 7.5 Admin (requieren rol administrator)

| Método | Ruta | Función |
|---|---|---|
| GET | `/admin/dashboard` | Dashboard global |
| GET | `/admin/riders` | Lista completa de riders |
| GET | `/admin/rider/{id}/stats` | Stats de un rider específico |
| GET/POST/PUT/DEL | `/admin/inventory*` | Tienda oficial PetsGo |
| POST | `/admin/inventory/upload-image` | Subir imagen producto admin |

---

## 8. SISTEMA DE AUTENTICACIÓN

### Flujo de Login
1. Frontend envía `POST /auth/login` con `{username, password}`
2. Backend autentica con `wp_authenticate()`
3. Genera token: `petsgo_` + 32 bytes random hex
4. Guarda en `user_meta` como `petsgo_api_token`
5. Retorna `{token, user: {id, role, displayName, ...}}`

### Persistencia
- Frontend guarda en `localStorage`: `petsgo_token`, `petsgo_user`
- Cada request incluye header `Authorization: Bearer {token}` + `X-PetsGo-Token: {token}`
- Filter `determine_current_user` resuelve el user_id desde el token
- `bypass_cookie_nonce_for_token` skipea la validación nonce/cookie de WP cuando hay token

### Seguridad
- Tokens con prefijo `petsgo_` (tokens viejos se purgan)
- Usuarios inactivos bloqueados en REST y wp-login.php
- Interceptor frontend detecta 401 → limpia sesión y dispara evento `petsgo:session_expired`

---

## 9. SISTEMA DE EMAILS (15+ tipos)

Todos los emails usan `email_wrap()` — template HTML corporativo con logo, colores PetsGo, footer con RRSS.

| Email | Trigger | Destinatario |
|---|---|---|
| Bienvenida cliente | Registro exitoso | Cliente |
| Bienvenida rider | Admin aprueba rider | Rider |
| Verificación email rider | Registro rider paso 1 | Rider |
| Rechazo documento rider | Admin rechaza doc | Rider |
| Reset de contraseña | Solicitud forgot-password | Usuario |
| Boleta de compra | Pedido confirmado | Cliente |
| Alerta stock bajo (<5) | Cambio de stock | Vendor |
| Alerta sin stock (0) | Cambio de stock | Vendor |
| Bienvenida vendedor | Admin crea vendor | Vendor |
| Creación ticket soporte | Nuevo ticket | Usuario + Admin |
| Respuesta a ticket | Nueva respuesta | Contraparte |
| Asignación ticket | Admin asigna | Agente |
| Cambio estado ticket | Ticket actualizado | Usuario |
| Lead auto-respuesta | Formulario contacto | Lead |
| Recordatorio renovación | Cron diario | Vendor |
| Creación usuario por admin | Admin crea usuario | Usuario |
| Doc. por vencer (30d) | Cron diario | Rider |
| Doc. por vencer (15d) | Cron diario | Rider |
| Doc. urgente (1d) | Cron diario | Rider |
| Cuenta suspendida por docs vencidos | Cron diario | Rider |

**BCC Global:** Todos los emails se copian a `soporte@petsgo.cl`

---

## 10. GENERADORES PDF (2 clases)

### PetsGo_Invoice_PDF (invoice-pdf.php)
- Boleta de compra con header corporativo (logo + datos empresa)
- Tabla de productos (nombre, cantidad, precio unitario, subtotal)
- Resumen: Neto, IVA 19%, Total
- QR de verificación con token único
- Footer paginado

### PetsGo_Subscription_PDF (subscription-pdf.php)
- Boleta de suscripción/plan
- Datos del cliente (tienda, RUT, email)
- Tabla de plan con precio/mes, meses, descuento anual
- QR de verificación
- Términos y condiciones

---

## 11. CHATBOT IA

### Arquitectura
```
Frontend (BotChatOverlay.jsx)
  → POST /chatbot-send
    → WordPress (petsgo-core.php)
      → POST https://automatizatech.cl/api-chat-proxy.php
        → OpenAI API (gpt-4o-mini)
```

### Características
- System prompt dinámico con variables ({BOT_NAME}, {CATEGORIAS}, {PLANES}, etc.)
- Historial de hasta 20 mensajes por conversación
- Multi-conversación (crear, listar, eliminar)
- Persistencia: invitados en localStorage (2h), logueados en BD
- Contexto del usuario inyectado (nombre, pedidos recientes, mascotas)
- Configurable desde wp-admin → Chatbot IA

---

## 12. PANEL DE ADMINISTRACIÓN WP (20 páginas)

| Menú | Slug | Acceso |
|---|---|---|
| Dashboard | petsgo-dashboard | Todos |
| Productos | petsgo-products | Todos |
| Tiendas | petsgo-vendors | Admin |
| Pedidos | petsgo-orders | Todos |
| Usuarios | petsgo-users | Admin |
| Delivery | petsgo-delivery | Todos |
| Planes | petsgo-plans | Admin |
| Boletas | petsgo-invoices | Todos |
| Config Boleta | petsgo-invoice-config | Todos |
| Auditoría | petsgo-audit | Admin |
| Configuración | petsgo-settings | Admin |
| Categorías | petsgo-categories | Admin |
| Cupones | petsgo-coupons | Todos |
| Tickets | petsgo-tickets | Todos |
| Leads | petsgo-leads | Admin |
| Contenido Legal | petsgo-legal | Admin |
| Chatbot IA | petsgo-chatbot | Admin |
| Preview Emails | petsgo-email-preview | Admin |

---

## 13. FLUJO DE PEDIDOS

### Estados (delivery — entrega a domicilio)
`pending` → `processing` → `ready_for_pickup` → `rider_assigned` → `on_the_way` → `delivered`  
También: `cancelled`, `refunded`

### Estados (pickup — retiro en tienda)
`pending` → `processing` → `ready_for_pickup` → `delivered`  
También: `cancelled`, `refunded`

### Descripción de cada estado
| Estado | Label | Color | Descripción |
|---|---|---|---|
| `pending` | Pendiente | `#f59e0b` | Pedido recibido, pendiente de preparación |
| `processing` | Procesando | `#3b82f6` | Tienda preparando el pedido |
| `ready_for_pickup` | Listo para enviar/retirar | `#8b5cf6` | Empacado y listo |
| `rider_assigned` | Asignado a Rider | `#0EA5E9` | Rider aceptó, pendiente de recoger (solo delivery) |
| `on_the_way` | En camino | `#f97316` | Rider recogió y va en ruta |
| `delivered` | Entregado | `#22c55e` | Entregado exitosamente |
| `cancelled` | Cancelado | `#ef4444` | Pedido cancelado |
| `refunded` | Reembolsado | `#6b7280` | Dinero devuelto al cliente |

### Asignación de Rider (solo pedidos `delivery_method='delivery'`)

**Dos vías de asignación:**
1. **Admin/Vendor asigna**: Desde el panel Delivery → modal con búsqueda de riders → selección manual o aleatoria ponderada. Estado del pedido pasa a `rider_response='pending'` hasta que el rider acepte.
2. **Rider auto-asigna**: Desde su dashboard ve pedidos disponibles (sin rider asignado) → click "Tomar Pedido" → asignación atómica con `WHERE rider_id IS NULL` para evitar duplicidad → estado cambia a `rider_assigned` inmediatamente.

**Protección anti-duplicidad:** El UPDATE SQL usa `WHERE rider_id IS NULL` como condición atómica. Si 100 riders intentan tomar el mismo pedido, solo el primero lo obtiene (`affected_rows=1`), los demás reciben HTTP 409 "Otro rider ya tomó este pedido".

**Respuesta del rider a asignación del admin:**
- `accepted` → estado cambia a `rider_assigned`, se calcula tiempo estimado
- `rejected` → se limpia `rider_id=NULL`, admin/vendor puede reasignar

**Transiciones permitidas por el rider:**
- `rider_assigned` → `in_transit` ("Iniciar Entrega")
- `in_transit` → `delivered` ("Marcar Entregado")

### Splits de comisión
- `sales_commission` — % del total para PetsGo (default 10%)
- `delivery_fee_cut` — % del delivery para PetsGo (default 5%)
- `store_fee` — Comisión cobrada a la tienda
- `petsgo_delivery_fee` — Parte de PetsGo del delivery
- `rider_earning` — Ganancia neta del rider

### Agrupación
- `purchase_group` — UUID que agrupa pedidos de múltiples tiendas en una sola compra

---

## 14. CRON JOBS

| Evento | Frecuencia | Handler | Propósito |
|---|---|---|---|
| `petsgo_check_renewals` | Diario | `process_renewal_reminders` | Enviar recordatorios de renovación de suscripción a vendors |
| `petsgo_check_rider_doc_expiry` | Diario | `process_rider_doc_expiry` | Notificar vigencia docs rider (30/15/1d) + auto-bloqueo por vencimiento |

---

## 15. PALETA DE COLORES

| Uso | Color | Hex |
|---|---|---|
| **Primario (Acción)** | Azul Cyan | `#00A8E8` |
| **Secundario (IA/Promos)** | Amarillo | `#FFC400` |
| **Textos/Dark** | Gris Oscuro | `#2F3A40` |

### Reglas UI
- Botones principales: `#00A8E8`, bordes redondeados `rounded-2xl`
- Gradientes: `linear-gradient(135deg, #00A8E8, #0077b6)`
- Asistente IA: ícono de huella (PawPrint)
- Diseño mobile-first, limpio, botones grandes (accesibilidad adultos mayores)

---

## 16. REGLAS DE CODIFICACIÓN (OBLIGATORIAS)

### Backend (PHP/WordPress)
- SIEMPRE usar `$wpdb->prepare()` para SQL
- SIEMPRE sanitizar inputs: `sanitize_text_field()`, `absint()`, `sanitize_email()`
- SIEMPRE verificar nonces en AJAX: `check_ajax_referer()`
- Usar Hooks WP (`add_action`, `add_filter`), no funciones aisladas
- Endpoints via `register_rest_route()`
- No usar `eval()`
- No crear archivos fuera de `mu-plugins/` o el tema

### Frontend (React)
- Tailwind CSS o CSS puro con la paleta definida
- Context API para estado global (no Redux)
- Axios con interceptores centralizados en `api.js`
- Variables de entorno via `VITE_*`

### Git
- Rama `develop` para trabajo activo
- Rama `main` para producción estable
- Commits descriptivos en español

---

## 17. CONFIGURACIÓN DE ENTORNO

### wp-config.php
- Auto-detecta local vs producción via `$_SERVER['SERVER_NAME']`
- **Local:** DB `petsgo`, user `root`, sin password, debug ON
- **Prod:** DB `u402745362_petsgo`, SSL forzado, debug OFF

### Vite (vite.config.js)
- Dev proxy: `/wp-json` → `http://localhost/PetsGoDev`
- Dev proxy: `/wp-content/uploads` → `http://localhost/PetsGoDev`
- Port: `5173`
- Build output: `dist/`

### Variables de entorno frontend
```
VITE_API_URL=https://petsgo.cl/wp-json/petsgo/v1  (prod)
VITE_WP_BASE=https://petsgo.cl                     (prod)
VITE_ENV=production                                 (prod)
```

---

## 18. QA — SUITES DE PRUEBA (391 casos)

| Suite | Módulo | Casos |
|---|---|---|
| QA-01 | Autenticación y Usuarios | 46 |
| QA-02 | Catálogo y Productos | 44 |
| QA-03 | Carrito, Checkout y Pagos | 43 |
| QA-04 | Pedidos y Boletas | 23 |
| QA-05 | Dashboard Vendor | 44 |
| QA-06 | Dashboard Rider | 38 |
| QA-07 | Panel Admin | 66 |
| QA-08 | Chatbot y Soporte | 44 |
| QA-09 | Mobile y Responsivo | 43 |
| QA-10 | Valoraciones y Reseñas | — |
| QA-11 | Tienda PetsGo Admin | — |
| **Total** | | **391** |

---

## 19. DATOS DEMO

### Usuarios
| Login | Rol | Email |
|---|---|---|
| tienda_pets_happy | vendor | petshappy@demo.cl |
| tienda_mundo_animal | vendor | mundoanimal@demo.cl |
| tienda_patitas | vendor | patitas@demo.cl |
| rider_carlos | rider | carlos.rider@demo.cl |
| cliente_maria | cliente | maria@demo.cl |

### Planes
| Plan | Precio CLP |
|---|---|
| Básico | $29.990/mes |
| Pro | $59.990/mes |
| Enterprise | $99.990/mes |

### Categorías (12)
Perros 🐕, Gatos 🐱, Alimento 🍖, Snacks 🦴, Farmacia 💊, Accesorios 🎾, Higiene 🧴, Camas 🛏️, Paseo 🦮, Ropa 🧥, Ofertas 🔥, Nuevos ✨

---

## 20. RESTRICCIONES HARD

- No usar `eval()`
- No crear archivos fuera de `mu-plugins/` o `petsgo-lib/`
- No sugerir bibliotecas pesadas de Node.js (Hostinger no soporta Node runtime)
- Frontend se despliega como build estático (`npm run build`)
- Todo SQL debe pasar por `$wpdb->prepare()`
- Mantener código ligero para Hostinger (RAM/timeout limitados)
- Responder SIEMPRE en español
