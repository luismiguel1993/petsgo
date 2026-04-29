# 🐾 Contexto Completo del Ecosistema PetsGo — Guía para Agente QA

**Propósito:** Documento exhaustivo que describe cómo funciona TODO el ecosistema PetsGo, desde el frontend React hasta el backend WordPress, para que el agente QA pueda entender cada flujo, componente, endpoint y comportamiento antes de escribir tests.

**Fecha:** 2026-03-22  
**Fuente:** Código fuente real verificado — `frontend/src/` + `wp-content/mu-plugins/petsgo-core.php`

---

## TABLA DE CONTENIDOS

1. [Arquitectura General](#1-arquitectura-general)
2. [Stack Tecnológico](#2-stack-tecnológico)
3. [Estructura de Archivos](#3-estructura-de-archivos)
4. [Frontend — Punto de Entrada y Providers](#4-frontend--punto-de-entrada-y-providers)
5. [Frontend — Router y Rutas](#5-frontend--router-y-rutas)
6. [Frontend — Contexts (Estado Global)](#6-frontend--contexts-estado-global)
7. [Frontend — Servicio API (api.js)](#7-frontend--servicio-api-apijs)
8. [Frontend — Todas las Páginas](#8-frontend--todas-las-páginas)
9. [Frontend — Componentes Globales](#9-frontend--componentes-globales)
10. [Frontend — Utilidades](#10-frontend--utilidades)
11. [Backend — Plugin petsgo-core.php](#11-backend--plugin-petsgo-corephp)
12. [Backend — Base de Datos](#12-backend--base-de-datos)
13. [Backend — Todos los Endpoints REST](#13-backend--todos-los-endpoints-rest)
14. [Backend — Autenticación y Tokens](#14-backend--autenticación-y-tokens)
15. [Backend — Validaciones](#15-backend--validaciones)
16. [Backend — Rate Limiting](#16-backend--rate-limiting)
17. [Backend — Pasarelas de Pago](#17-backend--pasarelas-de-pago)
18. [Backend — Sistema de Pedidos](#18-backend--sistema-de-pedidos)
19. [Backend — Facturación y Boletas](#19-backend--facturación-y-boletas)
20. [Backend — Sistema de Riders](#20-backend--sistema-de-riders)
21. [Backend — Sistema de Vendors](#21-backend--sistema-de-vendors)
22. [Backend — Chatbot IA](#22-backend--chatbot-ia)
23. [Backend — Sistema de Soporte](#23-backend--sistema-de-soporte)
24. [Backend — Sistema de Reseñas](#24-backend--sistema-de-reseñas)
25. [Backend — Cupones](#25-backend--cupones)
26. [Backend — Emails](#26-backend--emails)
27. [Backend — Módulos Configurables](#27-backend--módulos-configurables)
28. [Backend — wp-admin (Sitio 2)](#28-backend--wp-admin-sitio-2)
29. [Backend — wp-login.php Personalizado](#29-backend--wp-loginphp-personalizado)
30. [Backend — CORS y Seguridad](#30-backend--cors-y-seguridad)
31. [Backend — Cron Jobs](#31-backend--cron-jobs)
32. [Backend — Auditoría](#32-backend--auditoría)
33. [Flujos Completos E2E](#33-flujos-completos-e2e)
34. [Datos de Prueba](#34-datos-de-prueba)
35. [Configuración de Entornos](#35-configuración-de-entornos)

---

## 1. Arquitectura General

PetsGo es un **marketplace multi-vendor de productos para mascotas** con arquitectura headless:

```
┌─────────────────────────────┐     ┌──────────────────────────────────┐
│   FRONTEND (React SPA)       │     │   BACKEND (WordPress Headless)   │
│   Puerto: 5173 (dev)         │────▶│   Puerto: 80                     │
│   URL: https://petsgo.cl     │     │   URL: localhost/PetsGoDev       │
│                               │     │                                  │
│  23 Páginas                   │     │  petsgo-core.php (14,990 líneas) │
│  6 Componentes               │     │  71+ REST endpoints              │
│  3 Contexts                   │     │  20+ tablas MySQL                │
│  1 Servicio API               │     │  60+ AJAX handlers              │
│  2 Utils                      │     │  20+ páginas wp-admin            │
└─────────────────────────────┘     └──────────────────────────────────┘
         │                                         │
         │  REST API: /wp-json/petsgo/v1/*         │
         │  Auth: Bearer Token                     │
         │◀────────────────────────────────────────│
```

**Dos "sitios" coexisten:**

| Sitio | URL | Quién lo usa | Tecnología |
|-------|-----|-------------|------------|
| **Frontend React** | `https://petsgo.cl` (prod) / `http://localhost:5173` (dev) | Clientes, Riders, Admins (dashboard React) | React 19 SPA |
| **WordPress Admin** | `https://petsgo.cl/wp-admin` / `http://localhost/PetsGoDev/wp-admin` | Vendors, Admins, Soporte | WordPress nativo + vistas PHP custom |

Los **Vendors** son el único rol que trabaja SIEMPRE en wp-admin. El frontend los redirige automáticamente a `wp-admin/admin.php?page=petsgo-dashboard`.

---

## 2. Stack Tecnológico

### Frontend
| Capa | Tecnología | Versión |
|------|-----------|---------|
| Framework | React | 19.2.0 |
| Bundler | Vite | 7.2.4 |
| CSS | Tailwind CSS (via @tailwindcss/vite) | 4.1.18 |
| Router | react-router-dom | 7.13.0 |
| HTTP | axios | 1.13.4 |
| Icons | lucide-react | 0.563.0 |
| PDF | jspdf + jspdf-autotable | 4.1.0 / 5.0.7 |
| Tipo módulo | ESM (`"type": "module"`) | — |

### Backend
| Capa | Tecnología | Versión |
|------|-----------|---------|
| CMS | WordPress | 6.x |
| PHP | PHP | 8.0+ |
| Plugin | mu-plugin `petsgo-core.php` | 2.0.0 |
| DB | MySQL / MariaDB | — |
| PDF Server | FPDF (petsgo-lib/) | — |
| QR | QRcode class (petsgo-lib/) | — |
| IA Proxy | AutomatizaTech API → OpenAI | gpt-4o-mini |

---

## 3. Estructura de Archivos

### Frontend (`frontend/src/`)
```
src/
├── main.jsx                    → Punto de entrada, providers
├── App.jsx                     → Router, layout, rutas
├── App.css                     → Estilos globales
├── index.css                   → Tailwind imports
│
├── context/
│   ├── AuthContext.jsx          → Estado de autenticación
│   ├── CartContext.jsx          → Estado del carrito (memoria)
│   └── SiteContext.jsx          → Configuración del sitio
│
├── services/
│   └── api.js                   → 71+ funciones API (axios)
│
├── utils/
│   ├── chile.js                 → Validación RUT, teléfono, SQL injection, regiones
│   └── productImages.js         → Mapper de imágenes por producto/categoría
│
├── data/
│   └── chileRegions.js          → Datos alternativos de regiones (no usado en registro)
│
├── components/
│   ├── Header.jsx               → Header global (487 líneas)
│   ├── Footer.jsx               → Footer global (189 líneas)
│   ├── FloatingCart.jsx          → Panel lateral carrito (259 líneas)
│   ├── BotChatOverlay.jsx       → Chatbot IA flotante (1209 líneas)
│   ├── PromoSlider.jsx          → Banners promocionales (237 líneas)
│   └── InfoGuideButton.jsx      → Botón de ayuda contextual (91 líneas)
│
└── pages/
    ├── HomePage.jsx             → Landing principal (827 líneas)
    ├── LoginPage.jsx            → Login (273 líneas)
    ├── RegisterPage.jsx         → Registro cliente (574 líneas)
    ├── RiderRegisterPage.jsx    → Registro rider (599 líneas)
    ├── RiderVerifyEmailPage.jsx → Verificar email rider (243 líneas)
    ├── ForgotPasswordPage.jsx   → Solicitar reset password (98 líneas)
    ├── ResetPasswordPage.jsx    → Nuevo password con token (164 líneas)
    ├── ForceChangePasswordPage.jsx → Cambio obligatorio (264 líneas)
    ├── UserProfilePage.jsx      → Perfil + mascotas (570 líneas)
    ├── VendorsPage.jsx          → Grid de tiendas (131 líneas)
    ├── VendorDetailPage.jsx     → Detalle tienda + productos (477 líneas)
    ├── CategoryPage.jsx         → Catálogo por categoría (1043 líneas)
    ├── ProductDetailPage.jsx    → Detalle producto + reseñas (701 líneas)
    ├── CartPage.jsx             → Carrito + checkout + pago (994 líneas)
    ├── PlansPage.jsx            → Planes vendor + formulario lead (528 líneas)
    ├── MyOrdersPage.jsx         → Mis pedidos + valorar (530 líneas)
    ├── AdminDashboard.jsx       → Panel admin (1229 líneas)
    ├── VendorDashboard.jsx      → Panel vendor (795 líneas)
    ├── RiderDashboard.jsx       → Panel rider (1598 líneas)
    ├── SupportPage.jsx          → Sistema de tickets (677 líneas)
    ├── HelpCenterPage.jsx       → Centro de ayuda (162 líneas)
    ├── LegalPage.jsx            → Páginas legales (215 líneas)
    └── InvoiceVerifyPage.jsx    → Verificar boleta (195 líneas)
```

### Backend (`wp-content/mu-plugins/`)
```
mu-plugins/
├── petsgo-core.php              → Plugin principal (14,990 líneas)
└── petsgo-lib/
    ├── invoice-pdf.php           → Generador PDF boleta (FPDF)
    ├── subscription-pdf.php      → Generador PDF suscripción
    └── qrcode.php                → Generador QR para boletas
```

---

## 4. Frontend — Punto de Entrada y Providers

### main.jsx — Orden de Providers

```jsx
<StrictMode>
  <BrowserRouter>
    <SiteProvider>        {/* 1º: Carga config del sitio */}
      <AuthProvider>      {/* 2º: Carga auth desde localStorage */}
        <CartProvider>    {/* 3º: Carrito (memoria, no persistente) */}
          <App />
        </CartProvider>
      </AuthProvider>
    </SiteProvider>
  </BrowserRouter>
</StrictMode>
```

**Implicancia para QA:**
- `SiteContext` se carga primero → si la API falla, usa defaults hardcodeados
- `AuthContext` lee de `localStorage` al montar — si hay token, el usuario "ya está logueado"
- `CartContext` está en memoria → al refrescar la página se pierde el carrito

---

## 5. Frontend — Router y Rutas

### Layout de App.jsx

```
┌─ Header (siempre) ─────────────────────────────────┐
│  Logo | Búsqueda | Categorías | Carrito | Usuario   │
├─────────────────────────────────────────────────────┤
│  [FloatingCart] (panel lateral, oculto por defecto)  │
│  [Banner Rider No Aprobado] (condicional)            │
│  [Toast Logout] (condicional, 2.5s)                  │
├─────────────────────────────────────────────────────┤
│                                                      │
│              <Routes> — Contenido                    │
│                                                      │
├─────────────────────────────────────────────────────┤
│  Footer (siempre)                                    │
├─────────────────────────────────────────────────────┤
│  [BotChatOverlay] (flotante, si module_chatbot=true) │
└─────────────────────────────────────────────────────┘
```

### Tabla completa de rutas

#### Públicas (sin autenticación requerida)

| Ruta | Componente | Notas |
|------|-----------|-------|
| `/` | `HomePage` | Riders redirigidos a `/rider` |
| `/login` | `LoginPage` | — |
| `/registro` | `RegisterPage` | — |
| `/registro-rider` | `RiderRegisterPage` | Solo si `module_riders !== false` |
| `/verificar-rider` | `RiderVerifyEmailPage` | Recibe `?email=` por URL |
| `/forgot-password` | `ForgotPasswordPage` | — |
| `/reset-password` | `ResetPasswordPage` | Recibe `?token=` por URL |
| `/cambiar-contrasena` | `ForceChangePasswordPage` | Para cuentas creadas por admin |
| `/tiendas` | `VendorsPage` | Riders → `/rider` |
| `/tienda/:id` | `VendorDetailPage` | Riders → `/rider` |
| `/categoria` | `CategoryPage` | Riders → `/rider` |
| `/categorias` | `CategoryPage` | Alias |
| `/categoria/:slug` | `CategoryPage` | Riders → `/rider` |
| `/productos` | Navigate → `/categoria/Todos` | Redirect |
| `/producto/:id` | `ProductDetailPage` | Riders → `/rider` |
| `/carrito` | `CartPage` | Riders → `/rider`. **Incluye checkout completo** |
| `/planes` | `PlansPage` | Riders → `/rider` |
| `/centro-de-ayuda` | `HelpCenterPage` | — |
| `/terminos-y-condiciones` | `LegalPage` | slug=terminos-y-condiciones |
| `/politica-de-privacidad` | `LegalPage` | slug=politica-de-privacidad |
| `/politica-de-envios` | `LegalPage` | slug=politica-de-envios |
| `/verificar-boleta/:token` | `InvoiceVerifyPage` | Público vía QR |

#### Requieren autenticación

| Ruta | Componente | Rol requerido | Notas |
|------|-----------|---------------|-------|
| `/mis-pedidos` | `MyOrdersPage` | subscriber | Riders → `/rider` |
| `/perfil` | `UserProfilePage` | cualquiera | Riders → `/rider` |
| `/soporte` | `SupportPage` | cualquiera | — |
| `/admin` | `AdminDashboard` | administrator | — |
| `/rider` | `RiderDashboard` | petsgo_rider | — |
| `/vendor` | `VendorBackendRedirect` | petsgo_vendor | Redirige a wp-admin |

### Restricciones de navegación para Riders

Los riders logueados son **redirigidos automáticamente a `/rider`** cuando intentan acceder a páginas del marketplace: `/`, `/tiendas`, `/tienda/*`, `/categoria/*`, `/producto/*`, `/carrito`, `/planes`, `/mis-pedidos`, `/perfil`.

### Restricción de Vendors

Los vendors logueados son redirigidos automáticamente a `wp-admin/admin.php?page=petsgo-dashboard` desde CUALQUIER ruta del frontend (excepto `/login` y `/cambiar-contrasena`).

---

## 6. Frontend — Contexts (Estado Global)

### 6.1 AuthContext — Estado de Autenticación

**Archivo:** `context/AuthContext.jsx`

| Estado | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `user` | Object/null | `null` | Usuario actual |
| `loading` | boolean | `true` | Cargando desde localStorage |
| `loggedOut` | boolean | `false` | Flag temporal para toast logout (2.5s) |

**Objeto `user` (shape):**
```js
{
  id: 123,
  username: "juan",
  email: "juan@email.com",
  displayName: "Juan Pérez",
  firstName: "Juan",
  lastName: "Pérez",
  phone: "+56912345678",
  avatarUrl: "https://...",
  role: "admin",           // ⚠️ Shorthand: "admin", "vendor", "rider" (NO wp roles)
  rider_status: "approved", // Solo para riders
  vehicle_type: "moto",    // Solo para riders
  mustChangePassword: false
}
```

> **⚠️ IMPORTANTE para QA:** Los roles en `user.role` son **abreviados**: `'admin'`, `'vendor'`, `'rider'`. No son los roles WordPress (`administrator`, `petsgo_vendor`, `petsgo_rider`). El backend los mapea en la respuesta de login.

**Funciones del context:**

| Función | Comportamiento |
|---------|---------------|
| `login(username, password)` | API call → guarda token y user en localStorage → setUser |
| `register(formData)` | API call → retorna respuesta (no auto-login) |
| `logout()` | Limpia localStorage → setUser(null) → flag loggedOut 2.5s |
| `updateUser(updates)` | Merge parcial → persiste en localStorage |
| `isAdmin()` | `user?.role === 'admin'` |
| `isVendor()` | `user?.role === 'vendor'` |
| `isRider()` | `user?.role === 'rider'` |
| `isAuthenticated` | `!!user` |

**Efecto de inicialización (mount):**
1. Lee `petsgo_token` y `petsgo_user` de localStorage
2. Si token existe pero NO empieza con `petsgo_` → purga todo (token legacy)
3. Si ambos existen → parsea user JSON → setUser
4. Si no hay token → limpia petsgo_user
5. loading = false

**Listener `petsgo:session_expired`:**
- Escucha evento global `window` (disparado por api.js en 401)
- setUser(null), setLoggedOut(false)

### 6.2 CartContext — Estado del Carrito

**Archivo:** `context/CartContext.jsx`

| Estado | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `items` | Array | `[]` | Items del carrito `[{...product, quantity}]` |
| `appliedCoupon` | Object/null | `null` | Cupón aplicado |

> **⚠️ CRÍTICO para QA:** El carrito está **solo en memoria** (useState). Al refrescar la página el carrito SE VACÍA. Tests que necesitan carrito con items deben agregar productos dentro del mismo test.

**Valores calculados:**
- `totalItems` = suma de quantities
- `subtotal` = suma de (price × quantity)
- `discountAmount` = appliedCoupon?.discount || 0

**Funciones:**

| Función | Comportamiento |
|---------|---------------|
| `addItem(product)` | Agrega o incrementa qty (respeta `product.stock` máximo). Dispara callback de auto-abrir FloatingCart |
| `removeItem(productId)` | Elimina item |
| `updateQuantity(productId, qty)` | Si qty ≤ 0 elimina, sino actualiza (respeta stock) |
| `clearCart()` | Vacía items + resetea cupón |
| `getItemQuantity(productId)` | Qty actual de un producto (0 si no está) |
| `setAppliedCoupon(coupon)` | Aplica cupón |

### 6.3 SiteContext — Configuración del Sitio

**Archivo:** `context/SiteContext.jsx`

Carga configuración desde `GET /wp-json/petsgo/v1/public-settings` al montar. Si la API falla, usa defaults hardcodeados.

**Defaults importantes:**

| Key | Default | Uso |
|-----|---------|-----|
| `company_name` | `'PetsGo'` | Nombre mostrado en toda la app |
| `free_shipping_min` | `39990` | Monto mínimo para envío gratis ($39.990) |
| `delivery_standard_cost` | `2990` | Costo estándar envío ($2.990) |
| `delivery_base_rate` | `2000` | Tarifa base delivery |
| `delivery_per_km` | `400` | Tarifa por km |
| `module_chatbot` | `true` | ¿Mostrar chatbot? |
| `module_riders` | `true` | ¿Mostrar registro rider? |
| `module_reviews` | `true` | ¿Mostrar reseñas? |
| `module_coupons` | `true` | ¿Habilitar cupones? |
| `module_promo_slider` | `true` | ¿Mostrar slider promo? |
| `module_delivery` | `true` | ¿Habilitar delivery? |
| `module_vendor_plans` | `true` | ¿Mostrar planes? |

**Colores del sistema:**
- Primary: `#00A8E8` (azul celeste)
- Secondary: `#FFC400` (amarillo)
- Dark: `#2F3A40` (gris oscuro)
- Success: `#22C55E` (verde)
- Danger: `#EF4444` (rojo)

---

## 7. Frontend — Servicio API (api.js)

### Configuración

```js
API_BASE = import.meta.env.VITE_API_URL || '/wp-json/petsgo/v1'
WP_BASE = import.meta.env.VITE_WP_BASE || ''
IS_PROD = import.meta.env.VITE_ENV === 'production'
```

### Interceptor de Request (automático en cada llamada)

```js
// Se ejecuta ANTES de cada request
headers['Authorization'] = `Bearer ${localStorage.getItem('petsgo_token')}`
headers['X-PetsGo-Token'] = localStorage.getItem('petsgo_token')  // Fallback Apache
```

### Interceptor de Response (manejo de 401)

```js
// Se ejecuta en cada error de response
if (error.response?.status === 401) {
  if (localStorage.getItem('petsgo_token')) {
    localStorage.removeItem('petsgo_token')
    localStorage.removeItem('petsgo_nonce')
    localStorage.removeItem('petsgo_user')
    window.dispatchEvent(new Event('petsgo:session_expired'))
  }
}
```

### Todas las funciones API exportadas (71+)

#### Autenticación (sin auth)
| Función | Método | Endpoint |
|---------|--------|----------|
| `login(username, password)` | POST | `/auth/login` |
| `register(data)` | POST | `/auth/register` |
| `registerRider(data)` | POST | `/auth/register-rider` |
| `verifyRiderEmail(email, code)` | POST | `/auth/verify-rider-email` |
| `resendRiderVerification(email)` | POST | `/auth/resend-rider-verification` |
| `forgotPassword(email)` | POST | `/auth/forgot-password` |
| `resetPassword(token, password)` | POST | `/auth/reset-password` |

#### Catálogo público (sin auth)
| Función | Método | Endpoint |
|---------|--------|----------|
| `getProducts({vendorId, category, search, page, perPage})` | GET | `/products` |
| `getProductDetail(id)` | GET | `/products/{id}` |
| `getVendors(page, perPage)` | GET | `/vendors` |
| `getVendorDetail(id)` | GET | `/vendors/{id}` |
| `getCategories()` | GET | `/categories` |
| `getPlans()` | GET | `/plans` |
| `getPublicSettings()` | GET | `/public-settings` |
| `getLegalPage(slug)` | GET | `/legal/{slug}` |
| `getProductReviews(productId)` | GET | `/products/{id}/reviews` |
| `getVendorReviews(vendorId)` | GET | `/vendors/{id}/reviews` |

#### Perfil (auth requerida)
| Función | Método | Endpoint |
|---------|--------|----------|
| `getProfile()` | GET | `/profile` |
| `updateProfile(data)` | PUT | `/profile` |
| `changePassword(currentPassword, newPassword)` | POST | `/profile/change-password` |

#### Mascotas (auth requerida)
| Función | Método | Endpoint |
|---------|--------|----------|
| `getPets()` | GET | `/pets` |
| `addPet(data)` | POST | `/pets` |
| `updatePet(id, data)` | PUT | `/pets/{id}` |
| `deletePet(id)` | DELETE | `/pets/{id}` |
| `uploadPetPhoto(file)` | POST multipart | `/pets/upload-photo` |

#### Pedidos (auth requerida)
| Función | Método | Endpoint |
|---------|--------|----------|
| `createOrder(orderData)` | POST | `/orders` |
| `getMyOrders()` | GET | `/orders/mine` |
| `getOrderReviewStatus(orderId)` | GET | `/orders/{id}/review-status` |
| `submitReview(data)` | POST | `/reviews` |
| `validateCoupon(code, vendorIds, subtotal)` | POST | `/coupons/validate` |

#### Vendor (auth vendor)
| Función | Método | Endpoint |
|---------|--------|----------|
| `getVendorDashboard()` | GET | `/vendor/dashboard` |
| `getVendorInventory()` | GET | `/vendor/inventory` |
| `addProduct(product)` | POST | `/vendor/inventory` |
| `updateProduct(id, data)` | PUT | `/vendor/inventory/{id}` |
| `deleteProduct(id)` | DELETE | `/vendor/inventory/{id}` |
| `getVendorOrders(status)` | GET | `/vendor/orders` |
| `updateOrderStatus(orderId, status)` | PUT | `/vendor/orders/{orderId}/status` |
| `getVendorCoupons()` | GET | `/vendor/coupons` |
| `saveVendorCoupon(data)` | POST | `/vendor/coupons` |
| `deleteVendorCoupon(id)` | DELETE | `/vendor/coupons/{id}` |

#### Rider (auth rider)
| Función | Método | Endpoint |
|---------|--------|----------|
| `getRiderStatus()` | GET | `/rider/status` |
| `getRiderDeliveries()` | GET | `/rider/deliveries` |
| `updateDeliveryStatus(orderId, status)` | PUT | `/rider/deliveries/{orderId}/status` |
| `getRiderDocuments()` | GET | `/rider/documents` |
| `uploadRiderDocument(formData)` | POST multipart | `/rider/documents/upload` |
| `getRiderProfile()` | GET | `/rider/profile` |
| `updateRiderProfile(data)` | PUT | `/rider/profile` |
| `getRiderEarnings()` | GET | `/rider/earnings` |
| `getRiderRatings()` | GET | `/rider/ratings` |
| `getRiderStats(params)` | GET | `/rider/stats` |
| `getRiderPolicy()` | GET | `/rider/policy` |
| `calculateDeliveryFee(distanceKm)` | POST | `/delivery/calculate-fee` |
| `rateRider(orderId, rating, comment)` | POST | `/orders/{orderId}/rate-rider` |

#### Admin (auth admin)
| Función | Método | Endpoint |
|---------|--------|----------|
| `getAdminDashboard()` | GET | `/admin/dashboard` |
| `getAdminVendors()` | GET | `/admin/vendors` |
| `getVendorDashboardAsAdmin(vendorId)` | GET | `/admin/vendor/{vendorId}/dashboard` |
| `updateCommissions(vendorId, salesCommission, deliveryFeeCut)` | PUT | `/admin/commissions/{vendorId}` |
| `getAdminRiders()` | GET | `/admin/riders` |
| `getAdminRiderStats(riderId, params)` | GET | `/admin/rider/{riderId}/stats` |
| `getAdminInventory()` | GET | `/admin/inventory` |
| `addAdminProduct(data)` | POST | `/admin/inventory` |
| `updateAdminProduct(id, data)` | PUT | `/admin/inventory/{id}` |
| `deleteAdminProduct(id)` | DELETE | `/admin/inventory/{id}` |
| `toggleAdminProduct(id)` | PUT | `/admin/inventory/{id}/toggle` |
| `uploadAdminProductImage(file)` | POST multipart | `/admin/inventory/upload-image` |
| `getModuleToggles()` | GET | `/admin/module-toggles` |
| `updateModuleToggles(modules)` | PUT | `/admin/module-toggles` |

#### Chatbot
| Función | Método | Endpoint |
|---------|--------|----------|
| `sendChatbotMessage(messages)` | POST | `/chatbot-send` (timeout: 60s) |
| `getChatbotConfig()` | GET | `/chatbot-config` |
| `getChatbotUserContext()` | GET | `/chatbot-user-context` |
| `getChatHistory()` | GET | `/chatbot-history` |
| `saveChatHistory(messages, conversationId)` | POST | `/chatbot-history` |
| `listConversations()` | GET | `/chatbot-conversations` |
| `getConversation(id)` | GET | `/chatbot-conversations/{id}` |
| `deleteConversation(id)` | DELETE | `/chatbot-conversations/{id}` |

#### Soporte
| Función | Método | Endpoint |
|---------|--------|----------|
| `createTicket(data, imageFile)` | POST multipart | `/tickets` |
| `getTickets()` | GET | `/tickets` |
| `getTicketDetail(id)` | GET | `/tickets/{id}` |
| `addTicketReply(id, message, imageFile)` | POST multipart | `/tickets/{id}/reply` |

#### Leads
| Función | Método | Endpoint |
|---------|--------|----------|
| `submitVendorLead(data)` | POST | `/vendor-lead` |

---

## 8. Frontend — Todas las Páginas

### 8.1 HomePage (827 líneas)
**Ruta:** `/`  
**API:** `getPublicSettings`, `getCategories`, `getProducts`  
**Contenido:**
- Hero con slideshow de imágenes (auto-rotación)
- Barra de envío gratis (`$39.990`)
- Grid de categorías con emojis (clickables → `/categoria/:slug`)
- Sección "Productos Destacados" (grid responsivo: 1→2→3→4 columnas)
- Sección "Sugeridos para ti"
- Cards de producto con imagen, nombre, precio, descuento, botón agregar con +/-
- Banner "Sé parte de PetsGo" → link a `/planes`

### 8.2 LoginPage (273 líneas)
**Ruta:** `/login`  
**API:** `login()` via AuthContext  
**Flujo:**
1. Form: input email/username (`type="text"`) + password (`type="password"`) + botón "Ingresar"
2. Submit → API login
3. Éxito → Modal con animación confeti + cuenta regresiva
4. Auto-redirect según rol:
   - `admin` → `/admin`
   - `vendor` → `wp-admin/admin.php?page=petsgo-dashboard`
   - `rider` → `/rider`
   - cliente → `/`
5. Si `mustChangePassword` → `/cambiar-contrasena`
6. Si rider con `pending_email` → `/verificar-rider?email=...`

**Errores:** Div con fondo rojo. Mensajes del backend: "Tu cuenta ha sido desactivada", "Tu solicitud como Rider ha sido rechazada", "Tu tienda se encuentra inactiva", "Tu suscripción venció", credenciales incorrectas.

### 8.3 RegisterPage (574 líneas)
**Ruta:** `/registro`  
**API:** `register()` via AuthContext, `getLegalPage`  
**Campos:** nombre, apellido, email, tipo doc (rut/dni/passport), N° doc, teléfono (8 dígitos, prefijo +569 fijo), fecha nacimiento, región, comuna, contraseña, confirmar contraseña  
**Validaciones frontend:**
- SQL injection check en TODOS los campos
- Nombre/Apellido: regex letras 2+ chars, sanitización automática
- Email: debe contener `@`
- RUT: módulo 11 con formato automático
- Teléfono: exactamente 8 dígitos
- Contraseña: 5 reglas (8+ chars, mayúscula, minúscula, número, especial)
- Región → Comuna: cascade (comuna disabled hasta seleccionar región)
- TyC: modal con scroll obligatorio al final → botón "He leído y acepto"

**Post-registro:** Modal éxito con countdown 3s → redirect a `/login`

### 8.4 RiderRegisterPage (599 líneas)
**Ruta:** `/registro-rider`  
**API:** `registerRider`, `verifyRiderEmail`, `resendRiderVerification`, `getRiderPolicy`  
**2 pasos:**
- **Paso 1:** Mismos campos que RegisterPage + selector de vehículo (bicicleta/scooter/moto/auto/a_pie)
- **Paso 2:** Ingresar código de verificación de 6 caracteres enviado por email + botón reenviar

### 8.5 RiderVerifyEmailPage (243 líneas)
**Ruta:** `/verificar-rider?email=...`  
**API:** `verifyRiderEmail`, `resendRiderVerification`  
**Página standalone** para verificar email del rider (se accede desde redirect del login cuando rider tiene status `pending_email`).

### 8.6 ForgotPasswordPage (98 líneas)
**Ruta:** `/forgot-password`  
**Flujo:** Input email → submit → mensaje "si el correo existe, recibirás instrucciones"

### 8.7 ResetPasswordPage (164 líneas)
**Ruta:** `/reset-password?token=...`  
**Flujo:** Nueva contraseña + confirmar (5 reglas) → submit con token → éxito → redirect login

### 8.8 ForceChangePasswordPage (264 líneas)
**Ruta:** `/cambiar-contrasena`  
**Cuándo:** Cuando admin crea una cuenta con contraseña temporal → `mustChangePassword = true`  
**Flujo:** Contraseña actual + nueva + confirmar → submit → actualiza token en localStorage → redirect según rol

### 8.9 UserProfilePage (570 líneas)
**Ruta:** `/perfil`  
**3 secciones:**
1. **Datos personales:** Editar nombre, apellido, teléfono (email y RUT NO editables)
2. **Cambiar contraseña:** Contraseña actual + nueva + confirmar
3. **Mascotas:** CRUD completo (agregar/editar/eliminar/foto). 8 tipos: perro, gato, ave, conejo, hamster, pez, reptil, otro

### 8.10 VendorsPage (131 líneas)
**Ruta:** `/tiendas`  
**Grid de tiendas:** Card con imagen, nombre, dirección, rating badge, link a detalle.

### 8.11 VendorDetailPage (477 líneas)
**Ruta:** `/tienda/:id`  
**Secciones:** Header vendor (logo, nombre, rating, dirección, redes sociales), búsqueda local, filtro precio, sort, grid de productos, sección reseñas "💬 Reseñas de la Tienda".

### 8.12 CategoryPage (1043 líneas)
**Ruta:** `/categoria/:slug` o `/categoria` o `/categorias`  
**Soporta:** Query param `?q=` para búsqueda  
**Contenido:** Sidebar de vendors, búsqueda, sort, filtro precio, grid de productos (1-4 columnas responsivo), paginación.

### 8.13 ProductDetailPage (701 líneas)
**Ruta:** `/producto/:id`  
**Secciones:**
- Galería de imagen + thumbnails
- Info: nombre, precio (con descuento), estrellas, vendedor link
- Selector de variantes (si aplica)
- Botón "Agregar al carrito" con cantidad +/-
- Descripción expandible
- Sección reseñas (estrellas amarillas #FFC400)
- Productos relacionados

### 8.14 CartPage (994 líneas)
**Ruta:** `/carrito`  
**⚠️ HALLAZGO CRÍTICO:** No existe `/checkout` separado. Todo el flujo de compra está en CartPage:

1. **Lista de items:** Tabla con imagen, nombre, cantidad +/-, precio, subtotal, eliminar
2. **Método de entrega:** Toggle "🚚 Despacho a Domicilio" / "🏪 Retiro en Tienda"
3. **Dirección:** (si despacho) Región/Comuna cascada + calle con autocompletado Nominatim + tipo (casa/departamento/oficina) + detalle adicional (Nº depto, piso, etc.)
4. **Cupón:** Input + botón "Aplicar" → valida vía API
5. **Método de pago:** Radio buttons (Transbank Webpay / MercadoPago / Test Bypass)
6. **Resumen:** Subtotal, descuento, envío (gratis si > $39.990), total
7. **Botón "Pagar y Confirmar"**
8. **Modal de confirmación** (OrderConfirmModal) → muestra pedidos creados

### 8.15 PlansPage (528 líneas)
**Ruta:** `/planes`  
**Contenido:** Cards de 3 planes (Básico/Pro/Enterprise) + formulario de contacto para tiendas interesadas (vendor lead).

### 8.16 MyOrdersPage (530 líneas)
**Ruta:** `/mis-pedidos`  
**Contenido:**
- Filtros por estado: Todos, Pago Pendiente, Pendiente, Preparando, Listo para enviar, En camino, Entregado, Cancelado
- Pedidos agrupados por `purchase_group`
- Cada pedido expandible con items, precios, estado
- Botón "⭐ Valorar" por producto (solo si `delivered` y no valorado)
- Modal de reseña: 5 estrellas + emoji + textarea + submit
- Link descarga boleta PDF

### 8.17 AdminDashboard (1229 líneas)
**Ruta:** `/admin`  
**4 tabs implementados:**
1. **📊 Dashboard Global:** KPIs (ventas, pedidos, tiendas, riders), estadísticas
2. **🏪 Tiendas:** Tabla de vendors, editar comisiones (%), modo "Supervisar" (impersonate vendor dashboard)
3. **🏪 Tienda PetsGo:** CRUD productos tienda oficial (agregar/editar/eliminar/toggle/imagen)
4. **🏍️ Riders:** Tabla riders con búsqueda, modal de estadísticas con export PDF

**⚠️ Tabs NO implementados:** Usuarios, Pedidos, Productos globales, Finanzas, Cupones, Soporte, Planes, Configuración.

### 8.18 VendorDashboard (795 líneas)
**Ruta:** `/vendor` → Redirige a wp-admin  
**⚠️ Nota:** Este componente React existe pero los vendors son redirigidos a wp-admin. Sin embargo, el admin puede "supervisar" al vendor desde AdminDashboard.  
**4 tabs:** Dashboard (stats), Inventario (CRUD productos), Pedidos (lista + cambiar estado), Cupones (CRUD)  
**Detección de inactividad:** Si vendor Status !== active, muestra pantalla "Suscripción Inactiva" con candado.

### 8.19 RiderDashboard (1598 líneas)
**Ruta:** `/rider`  
**7 tabs:**
1. **🏠 Inicio:** Resumen + onboarding según status (pending_docs/pending_review/approved)
2. **📦 Entregas:** Lista de entregas asignadas con botones de acción (Retirar → Entregar)
3. **💰 Ganancias:** Resumen de earnings
4. **📊 Estadísticas:** Gráficos con rango de fechas + export PDF
5. **📄 Documentos:** Grid de upload (selfie, ID, fotos vehículo, licencia, padrón)
6. **⭐ Valoraciones:** Lista de ratings recibidos
7. **👤 Perfil:** Editar datos personales + datos bancarios (15 bancos, 3 tipos cuenta)

**Status flow visible:** pending_docs → pending_review (en revisión) → approved (dashboard completo)

### 8.20 SupportPage (677 líneas)
**Ruta:** `/soporte`  
**3 vistas:** Lista de tickets, Nuevo ticket, Detalle ticket con chat.  
**7 categorías:** Pedidos, Productos, Envíos, Pagos/Reembolsos, Cuenta, Sugerencias, Otro  
**4 prioridades:** Baja, Media, Alta, Urgente  
**4 estados:** abierto, en_proceso, resuelto, cerrado

### 8.21 HelpCenterPage (162 líneas)
**Ruta:** `/centro-de-ayuda`  
**Contenido:** Hero con CTA "Crear Ticket", contenido HTML del backend, FAQs accordion, cards de contacto.

### 8.22 LegalPage (215 líneas)
**Ruta:** `/terminos-y-condiciones`, `/politica-de-privacidad`, `/politica-de-envios`  
**Carga:** HTML desde API con fallback hardcodeado.

### 8.23 InvoiceVerifyPage (195 líneas)
**Ruta:** `/verificar-boleta/:token`  
**Contenido:** Verificación de boleta vía QR. Muestra datos del pedido, tienda, cliente, monto, estado. Botón descargar PDF.

---

## 9. Frontend — Componentes Globales

### 9.1 Header (487 líneas)
**Siempre visible.** Se adapta por viewport:

| < 768px (mobile) | ≥ 768px (desktop) |
|-------------------|-------------------|
| Hamburguesa (`button.md\:hidden`) | Nav horizontal |
| Búsqueda en panel colapsable | Búsqueda en barra |
| Menú usuario colapsado | Dropdown usuario |

**Elementos:**
- Logo (link a `/`)
- Barra de búsqueda (Enter → navigate `/categoria/Todos?q={query}`)
- Selector de ubicación (comunas RM)
- Fila de categorías (cargadas de API)
- Ícono carrito con badge numérico
- Botón "INGRESAR" (si no logueado) o Avatar + menú usuario
- Menú usuario: Perfil, Mis Pedidos, Dashboard (según rol), Cerrar Sesión

**API calls:** `getPublicSettings`, `getCategories`

### 9.2 Footer (189 líneas)
**Siempre visible.** Links organizados en columnas:
- Marketplace: Productos, Tiendas, Planes
- Soporte: Centro de ayuda, Soporte, Legal
- Social: Instagram, Facebook, LinkedIn, Twitter, WhatsApp
- Copyright

### 9.3 FloatingCart (259 líneas)
**Panel lateral derecho** que se desliza al agregar producto.  
**Auto-cierre:** 3 segundos.  
**Contenido:** Lista items con +/- y eliminar, subtotal, botón "Ir al carrito" (→ `/carrito`).

### 9.4 BotChatOverlay (1209 líneas)
**Widget flotante** de chatbot IA con CSS clases `.pgchat-*`.

| Viewport | Tamaño |
|----------|--------|
| > 600px | 350 × 500px (esquina inferior derecha) |
| ≤ 600px | Fullscreen |

**Trigger:** Botón circular amarillo (#FFC400) 60×60px.  
**Funcionalidades:** Chat con IA, historial de conversaciones, búsqueda en historial, crear/eliminar conversaciones.  
**Guests:** Usa `localStorage['petsgo_guest_chat']` con TTL 2 horas.  
**Logged-in:** Guarda en BD vía API.  
**Welcome:** "¡Hola! Soy PetBot, el asistente inteligente de PetsGo 🐾"  
**Proxy:** Frontend → API → AutomatizaTech → OpenAI

### 9.5 PromoSlider (237 líneas)
**Banners rotativos** que aparecen/desaparecen (15s visible, 15s oculto).  
**CTAs:** Links a `/planes` y `/registro-rider`.  
**Condicionado** a módulos `module_promo_slider`, `module_vendor_plans`, `module_riders`.

### 9.6 InfoGuideButton (91 líneas)
**Botón "?"** reutilizable que abre modal con guía de ayuda contextual. Usado en dashboards.

---

## 10. Frontend — Utilidades

### 10.1 chile.js — Validaciones Chilenas

#### Nombres
```js
sanitizeName(value)  // Elimina todo excepto letras, espacios, guiones, apóstrofes
isValidName(name)    // Regex: /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]{2,}$/
```

#### RUT (módulo 11)
```js
validateRut(rut)     // Limpia → separa body+DV → módulo 11 → compara
formatRut(value)     // Limpia → agrega puntos cada 3 dígitos + guión antes de DV
```
**Algoritmo:** multiplicar dígitos del body de derecha a izquierda por 2,3,4,5,6,7,2,3... → sumar → `11 - (sum % 11)` → si 11='0', si 10='K'.

#### Teléfono
```js
formatPhoneDigits(value)      // Solo dígitos, max 8 chars
isValidPhoneDigits(digits)    // /^\d{8}$/
buildFullPhone(digits)        // '+569' + digits
extractPhoneDigits(phone)     // Extrae 8 dígitos de cualquier formato
isValidPhone(phone)           // /^\+569\d{8}$/
```

#### SQL Injection
```js
hasSqlInjection(value)              // Testea contra SQL_PATTERNS regex
sanitizeInput(value)                // Limpia null bytes, patrones peligrosos
checkFormForSqlInjection(formObj)   // Itera todos los campos del form
```
**Patrones detectados:** SELECT, INSERT, UPDATE, DELETE, DROP, ALTER, CREATE, EXEC, UNION, TRUNCATE, DECLARE, CAST, CONVERT, --, /*, */, xp_, sp_, 0x[hex], CHAR(), CONCAT(), SLEEP(), BENCHMARK(), LOAD_FILE(), OR '1'='1, AND '1'='1, INFORMATION_SCHEMA, OUTFILE.

#### Regiones y Comunas
```js
REGIONES_COMUNAS    // Object: { 'Metropolitana': ['Santiago', ...], ... }
REGIONES            // Array: ['Arica y Parinacota', ..., 'Magallanes']
getComunas(region)  // Retorna array de comunas para la región
```
**16 regiones:** Arica y Parinacota, Tarapacá, Antofagasta, Atacama, Coquimbo, Valparaíso, **Metropolitana**, O'Higgins, Maule, Ñuble, Biobío, Araucanía, Los Ríos, Los Lagos, Aysén, Magallanes

### 10.2 productImages.js — Imágenes de Productos

Mapper de imágenes para productos sin imagen propia:
1. Prioridad: `product.image_url` (si existe)
2. Fallback: match por keyword en nombre del producto (80+ keywords)
3. Fallback: pool de imágenes por categoría (hash determinístico)
4. Fallback: imagen genérica default

---

## 11. Backend — Plugin petsgo-core.php

**Archivo:** `wp-content/mu-plugins/petsgo-core.php`  
**Tamaño:** 14,990 líneas  
**Clase:** `PetsGo_Core` (singleton)  
**DB Version:** 4.0  
**Plugin Version:** 2.0.0

### Estructura interna
1. **Constructor** (líneas 55-165): Registra todos los hooks WordPress
2. **CORS** (líneas 17-49): Manejo de cross-origin
3. **Login branding** (líneas 299-835): Customización wp-login.php
4. **Validaciones** (líneas 868-990): RUT, teléfono, password, SQL injection, nombres
5. **Rate limiting** (líneas 1000-1050): Transient-based throttling
6. **Helpers** (líneas 1100-1500): Roles, settings, vendor helpers
7. **Create tables** (líneas 1500-2500): DDL de 20+ tablas
8. **Admin pages PHP** (líneas 2500-8500): Vistas wp-admin
9. **AJAX handlers** (líneas 8500-8700): 60+ handlers
10. **REST routes** (líneas 8726-9150): Registro de 71+ endpoints
11. **Auth** (líneas 9150-9600): Token, login, register, password
12. **Rider management** (líneas 9600-10800): Docs, status, deliveries
13. **Orders** (líneas 10800-11200): Cart → pedido → facturación
14. **Chatbot** (líneas 11600-12400): Proxy IA + historial
15. **Reviews** (líneas 12400-12800): Valoraciones
16. **Tickets** (líneas 12800-14200): Soporte
17. **Admin inventory** (líneas 14200-14990): Tienda PetsGo

---

## 12. Backend — Base de Datos

### Tablas custom `wp_petsgo_*`

| Tabla | Propósito | Columnas clave |
|-------|----------|----------------|
| `petsgo_vendors` | Tiendas | user_id, store_name, rut, email, status, sales_commission, subscription_start/end, social links |
| `petsgo_inventory` | Productos | vendor_id, product_name, price, stock, category, image_id, variants, is_active, discount_* |
| `petsgo_orders` | Pedidos | customer_id, vendor_id, total, commission, delivery_fee, status, payment_status, delivery_method, address, rider_id, rider_earning, purchase_group |
| `petsgo_order_items` | Items de pedido | order_id, product_id, product_name, quantity, unit_price, subtotal |
| `petsgo_invoices` | Boletas | order_id, vendor_id, invoice_number (PG-YYYYMMDD-NNNN), qr_token, pdf_path |
| `petsgo_user_profiles` | Perfiles extra | user_id (UNIQUE), first/last_name, id_type, id_number, phone, vehicle_type, bank_*, region, comuna |
| `petsgo_pets` | Mascotas | user_id, name, pet_type, breed, birth_date, notes, photo_url |
| `petsgo_categories` | Categorías | name, slug (UNIQUE), emoji, description, sort_order, is_active |
| `petsgo_subscriptions` | Planes | plan_name, monthly_price, max_products, features_json, is_featured |
| `petsgo_reviews` | Reseñas | customer_id, order_id, product_id, vendor_id, rating(1-5), comment. UNIQUE(customer_id, order_id, product_id) |
| `petsgo_coupons` | Cupones | code (UNIQUE), discount_type(percentage/fixed), discount_value, min_purchase, usage_limit/count, vendor_ids, valid_from/until |
| `petsgo_chat_history` | Historial chat | user_id, title, messages (LONGTEXT JSON), updated_at |
| `petsgo_tickets` | Tickets soporte | ticket_number (TK-XXXXXXXX), user_id, subject, description, category, priority, status, assigned_to |
| `petsgo_ticket_replies` | Respuestas tickets | ticket_id, user_id, user_name, message, image_url, is_internal |
| `petsgo_rider_documents` | Docs rider | rider_id, doc_type, file_url, status (pending/approved/rejected), expiry_date, admin_notes |
| `petsgo_delivery_ratings` | Ratings rider | order_id, rider_id, rating(1-5), comment. UNIQUE(order_id, rater_type) |
| `petsgo_rider_payouts` | Pagos rider | rider_id, period_start/end, total_deliveries, total_earned, net_amount, status |
| `petsgo_rider_delivery_offers` | Ofertas delivery | order_id, rider_id, status |
| `petsgo_password_resets` | Reset tokens | user_id, token (64 hex), expires_at, used(0/1) |
| `petsgo_audit_log` | Auditoría | user_id, action, entity_type, entity_id, details, ip_address |
| `petsgo_leads` | Leads vendor | store_name, contact_name, email, phone, region, comuna, message, plan, status |

### 12 Categorías default (seeded)
Perros 🐕, Gatos 🐱, Alimento 🍖, Snacks 🦴, Farmacia 💊, Juguetes 🎾, Accesorios 🦮, Higiene 🧴, Camas 🛏️, Transportes 🧳, Ropa 👕, Otros 📦

---

## 13. Backend — Todos los Endpoints REST

(Ver sección 7 para la lista completa desde el frontend. Aquí se añade la perspectiva backend.)

**Prefijo:** `/wp-json/petsgo/v1/`

### Resolución de permisos

| Permission Callback | Lógica |
|---------------------|--------|
| `__return_true` | Público — sin auth |
| `check_logged_in` | Token válido → usuario resuelto |
| `check_admin_role` | Token + rol `administrator` |
| `check_vendor_role` | Token + rol `petsgo_vendor` o `administrator` |
| `check_rider_role` | Token + rol `petsgo_rider` |

---

## 14. Backend — Autenticación y Tokens

### Formato del token
```
petsgo_ + 64 caracteres hexadecimales (32 bytes random)
Ejemplo: petsgo_a1b2c3d4e5f6...64 chars total
```

### Almacenamiento
- **Backend:** `wp_usermeta` → `meta_key = 'petsgo_api_token'`
- **Frontend:** `localStorage['petsgo_token']`

### Resolución del token (cada request)
1. Busca header `X-PetsGo-Token`
2. Fallback: header `Authorization: Bearer {token}`
3. Query: `SELECT user_id FROM wp_usermeta WHERE meta_key='petsgo_api_token' AND meta_value='{token}'`
4. Si encuentra → `wp_set_current_user($user_id)`

### Headers enviados por el frontend
```
Authorization: Bearer petsgo_abc123...
X-PetsGo-Token: petsgo_abc123...
Content-Type: application/json
```

### Flujo de login completo
```
Frontend                          Backend
────────                          ───────
POST /auth/login                  
  {username, password}     →      wp_authenticate()
                                  ├─ Credenciales incorrectas → 401
                                  ├─ petsgo_user_status = 'inactive' → 403
                                  ├─ Rider pending_email → 200 (redirect verify)
                                  ├─ Rider rejected → 403
                                  ├─ Vendor inactive → 403
                                  ├─ Vendor expired subscription → 403
                                  └─ OK:
                                     ├─ Genera/recupera token
                                     ├─ Mapea role: administrator→'admin', petsgo_vendor→'vendor', petsgo_rider→'rider'
                                     └─ Retorna {token, user}
                           ←      
localStorage.set('petsgo_token')
localStorage.set('petsgo_user')
setUser(userData)
Modal éxito → redirect según rol
```

### Flujo de logout
```
Frontend:
1. localStorage.removeItem('petsgo_token')
2. localStorage.removeItem('petsgo_nonce')
3. localStorage.removeItem('petsgo_user')
4. setUser(null)
5. setLoggedOut(true) → toast visible 2.5s
```

### Expiración de sesión (401)
```
api.js interceptor (Response error):
1. Si status 401 && petsgo_token existía:
   - Remove petsgo_token, petsgo_nonce, petsgo_user
   - window.dispatchEvent(new Event('petsgo:session_expired'))

AuthContext listener:
   - setUser(null) → UI vuelve a estado no autenticado
```

### localStorage Keys

| Key | Contenido | Seteado por | Leído por | Limpiado por |
|-----|-----------|-------------|-----------|--------------|
| `petsgo_token` | String token | AuthContext.login() | api.js interceptor, AuthContext init | logout(), 401 interceptor |
| `petsgo_user` | JSON user object | AuthContext.login(), updateUser() | AuthContext init | logout(), 401 interceptor |
| `petsgo_nonce` | String (legacy) | Externo | — | logout(), 401 interceptor |
| `petsgo_guest_chat` | JSON chat data (2h TTL) | BotChatOverlay | BotChatOverlay | TTL expiry |

---

## 15. Backend — Validaciones

### Doble capa (frontend + backend)

| Validación | Frontend (chile.js) | Backend (petsgo-core.php) |
|-----------|--------------------|--------------------------| 
| Nombre | `sanitizeName()` + `isValidName()` | `sanitize_name()` + `validate_name()` |
| Email | Contiene `@` | `email_exists()` + sanitize |
| RUT | `validateRut()` módulo 11 | `validate_rut()` módulo 11 |
| Teléfono | `isValidPhoneDigits()` 8 dígitos | `validate_chilean_phone()` regex |
| Password | 5 reglas strength | `validate_password_strength()` 5 reglas |
| SQL injection | `checkFormForSqlInjection()` | `check_form_sql_injection()` |

### 5 reglas de contraseña
1. ≥ 8 caracteres
2. Al menos 1 mayúscula [A-Z]
3. Al menos 1 minúscula [a-z]
4. Al menos 1 número [0-9]
5. Al menos 1 carácter especial [^A-Za-z0-9]

### Mensajes de error exactos del frontend

| Condición | Mensaje |
|-----------|---------|
| Nombre vacío | `Nombre es obligatorio` |
| Nombre inválido | `Nombre solo puede contener letras` |
| Apellido vacío | `Apellido es obligatorio` |
| Apellido inválido | `Apellido solo puede contener letras` |
| Email sin @ | `Email inválido` |
| Contraseña débil | `La contraseña no cumple todos los requisitos` |
| Contraseñas ≠ | `Las contraseñas no coinciden` |
| RUT inválido | `RUT inválido` |
| Doc vacío | `Documento de identidad es obligatorio` |
| Teléfono < 8 dígitos | `Debes completar los 8 dígitos del teléfono` |
| Región vacía | `Región es obligatoria` |
| Comuna vacía | `Comuna es obligatoria` |
| TyC no aceptado | `Debes aceptar los Términos y Condiciones` |
| Vehículo rider vacío | `Selecciona un vehículo o medio de transporte` |
| SQL injection | `El campo contiene caracteres no permitidos. Por favor revisa tus datos.` |
| Email duplicado (backend) | `El email ya está registrado` |
| Rate limited (backend) | `Demasiadas solicitudes. Por favor espera X minutos e intenta nuevamente.` |

---

## 16. Backend — Rate Limiting

**Función:** `check_rate_limit($action, $max=3, $window=1200, $cooldown=900)`

| Parámetro | Default | Descripción |
|-----------|---------|-------------|
| `$action` | requerido | Key: `register`, `register_rider`, `vendor_lead` |
| `$max` | 3 | Intentos máximos por ventana |
| `$window` | 1200 (20 min) | Ventana de tracking |
| `$cooldown` | 900 (15 min) | Duración del bloqueo |

**Mecanismo:** WP transients con key `petsgo_rl_` + MD5(IP + action)  
**Error:** HTTP 429 → `"Demasiadas solicitudes. Por favor espera X minutos e intenta nuevamente."`

---

## 17. Backend — Pasarelas de Pago

Manejado en `api_create_order()`:

| Método | Comportamiento |
|--------|---------------|
| `transbank` | Crea pedido con `payment_status = 'pending'`, frontend redirige a Webpay |
| `mercadopago` | Crea pedido con `payment_status = 'pending'`, frontend redirige a MP |
| `test_bypass` | **Solo para emails de prueba hardcodeados**. `payment_status = 'completed'` inmediatamente |

### Datos Transbank Sandbox
| Campo | Valor |
|-------|-------|
| N° Tarjeta | `4051 8856 0044 6623` |
| Fecha exp. | Cualquier fecha futura |
| CVV | `123` |
| RUT | `11.111.111-1` |
| Clave | `123` |

---

## 18. Backend — Sistema de Pedidos

### Flujo de creación de pedido (api_create_order)

```
Frontend CartPage                    Backend
────────────────                     ───────
POST /orders {                       
  items: [{id, qty}...],            1. Validar usuario logueado
  delivery_method,                   2. Validar items (stock, active, price)
  address, region, comuna,           3. Agrupar items por vendor → crear 1 orden por vendor
  payment_method,                    4. Para CADA vendor:
  coupon_code                           a. Calcular subtotal
}                                       b. Calcular comisión PetsGo (vendor.sales_commission %)
                                        c. Calcular delivery fee (si despacho)
                                        d. Aplicar cupón (si aplica a este vendor)
                                        e. INSERT petsgo_orders
                                        f. INSERT petsgo_order_items por cada item
                                        g. UPDATE stock (decrementar)
                                        h. Generar boleta PDF (auto_generate_invoice)
                                        i. Enviar email boleta
                                        j. Check stock alerts (< 5 o = 0)
                                     5. Asignar purchase_group (UUID) a todos los pedidos
                                     6. Retorna array de pedidos creados
```

### Estados del pedido

```
pending ──→ payment_pending ──→ processing ──→ ready_for_pickup ──→ on_the_way ──→ delivered
   │              │                  │               │                   │
   └──→ cancelled │                  └──→ cancelled  └──→ cancelled      └──→ cancelled
                  └──→ cancelled
```

| Estado | Significado | Quién lo cambia |
|--------|------------|-----------------|
| `pending` | Creado, esperando pago | Sistema |
| `payment_pending` | Pago iniciado | Sistema (Transbank/MP redirect) |
| `processing` | Pago confirmado, preparándose | Vendor (wp-admin) |
| `ready_for_pickup` | Listo para que rider recoja | Vendor |
| `on_the_way` | Rider recogió y va en camino | Rider (REST API) |
| `delivered` | Entregado exitosamente | Rider (REST API) |
| `cancelled` | Cancelado | Admin/Vendor/Sistema |

### Purchase Group
Todos los pedidos creados en una misma compra comparten un `purchase_group` (UUID). Esto permite agruparlos en `/mis-pedidos`.

---

## 19. Backend — Facturación y Boletas

### Generación automática
Se ejecuta dentro de `api_create_order()` para cada pedido por vendor.

**Invoice number format:** `PG-YYYYMMDD-NNNN` (secuencial por día)

### Datos de la boleta
- Vendor: nombre tienda, RUT, dirección, teléfono, logo
- Cliente: nombre, email, RUT/ID
- Items: nombre, cantidad, precio unitario, subtotal
- Totales: Neto, IVA 19%, Total
- QR: Link a `/verificar-boleta/{qr_token}`

### PDF
Generado con FPDF (clase `PetsGo_Invoice_PDF`):
- A4 portrait, 15mm márgenes
- Header con logos PetsGo + vendor
- Tabla de items
- Totales: Neto = Grand/1.19, IVA = Grand - Neto
- QR code embebido
- Footer con info legal

### Verificación pública
`GET /invoice/validate/{qr_token}` — retorna datos del pedido para verificar autenticidad.  
`GET /invoice/download/{qr_token}` — descarga PDF.

---

## 20. Backend — Sistema de Riders

### Status machine

```
Registro → pending_email → (verificar código) → pending_docs → (subir docs) → pending_review → (admin revisa) → approved
                                                                                                              └→ rejected
approved → (docs expiran) → suspended → (re-subir) → approved
```

### Documentos requeridos por vehículo

| Documento | Bicicleta | Scooter | Moto | Auto | A pie |
|-----------|-----------|---------|------|------|-------|
| Selfie | ✅ | ✅ | ✅ | ✅ | ✅ |
| Cédula (ID) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Foto vehículo frontal | ✅ | ✅ | ✅ | ✅ | — |
| Foto vehículo lateral | ✅ | ✅ | ✅ | ✅ | — |
| Foto vehículo trasera | ✅ | ✅ | ✅ | ✅ | — |
| Licencia de conducir | — | ✅ | ✅ | ✅ | — |
| Padrón del vehículo | — | ✅ | ✅ | ✅ | — |

### Verificación de email
- Código: primeros 6 caracteres del token hex (uppercase)
- Vigencia: 48 horas
- Reenvío: endpoint dedicado

### Delivery flow (rider actions)
```
ready_for_pickup → (rider click "Retirar") → on_the_way → (rider click "Entregar") → delivered
```
**Solo 2 acciones**, no 3.

### Rider earnings
```
rider_earning = delivery_fee × (rider_commission_pct / 100)
Default: 88% del delivery fee
```

### Datos bancarios (15 bancos)
Banco de Chile, BancoEstado, Santander, BCI, Itaú, Scotiabank, BICE, Security, Falabella, Ripley, Consorcio, Internacional, HSBC, Corpbanca, Coopeuch

### 3 tipos de cuenta
Corriente, Vista/RUT, Ahorros

---

## 21. Backend — Sistema de Vendors

### Status machine
```
pending → active (admin aprueba)
active → suspended (admin suspende) → active (admin reactiva)
active → inactive (suscripción vence) → active (renueva)
```

### Comisiones
- **Sales commission:** % sobre total del pedido (por vendor, default 10%)
- **Delivery fee split:** Rider 88%, Store 5%, PetsGo 7%
- Admin puede editar comisiones por vendor individual

### Suscripciones
- 3 planes: Básico ($29.990/mes, 10 productos), Pro ($59.990/mes, 50), Enterprise ($99.990/mes, ilimitado)
- Anual: 2 meses gratis
- Cron diario revisa vencimientos y envía alertas (10/5/3/1 días)
- Auto-desactiva vendors con suscripción vencida

### Backend access
Los vendors trabajan en wp-admin con menú limitado:
- `petsgo-my-store` — Configuración de su tienda
- `petsgo-tickets` — Soporte
- `upload.php` — Media Library

---

## 22. Backend — Chatbot IA

### Flujo completo
```
Usuario escribe mensaje
    ↓
Frontend BotChatOverlay.jsx
    ↓
POST /wp-json/petsgo/v1/chatbot-send
  { messages: [{role: "system", content: "..."}, {role: "user", content: "Hola"}] }
    ↓
Backend petsgo-core.php → api_chatbot_send()
    ↓
wp_remote_post("https://automatizatech.cl/api-chat-proxy.php")
  { model: "gpt-4o-mini", user_id: 1, client_identifier: "cliente_petsgo", messages }
    ↓
AutomatizaTech proxy → OpenAI
    ↓
Response normalizada → JSON → Frontend
    ↓
Mensaje mostrado en burbuja del bot
```

### System prompt (construido dinámicamente)
El frontend construye un system prompt con:
- Config del bot (nombre, personalidad)
- Categorías de la tienda
- Planes disponibles con features
- Settings del sitio (envío gratis, teléfono, redes)
- Contexto del usuario (si logueado): pedidos, mascotas, datos

### Persistencia
- **Guests:** `localStorage['petsgo_guest_chat']` con TTL 2h
- **Logged-in:** BD tabla `petsgo_chat_history`, max 50 conversaciones, max 50 mensajes por conversación

---

## 23. Backend — Sistema de Soporte

### Tickets
- **Número:** `TK-XXXXXXXX` (8 chars random)
- **7 categorías:** Pedidos, Productos, Envíos, Pagos/Reembolsos, Cuenta, Sugerencias, Otro
- **4 prioridades:** Baja, Media, Alta, Urgente  
- **4 estados:** abierto → en_proceso → resuelto → cerrado
- **Imagen adjunta:** Max 5MB (JPEG, PNG, WebP, GIF)
- **Asignación:** Admin puede asignar a usuario admin/support

### Emails automáticos
- Ticket creado → al usuario + a admins
- Ticket asignado → al asignado
- Respuesta → al usuario
- Cambio de estado → al usuario

---

## 24. Backend — Sistema de Reseñas

### Restricciones
- Solo clientes con pedido `delivered` pueden valorar
- **UNIQUE:** (customer_id, order_id, product_id) — 1 reseña por producto por pedido
- Rating: 1-5 estrellas
- Comentario: opcional (max 500 chars)

### API
- `POST /reviews` — crear (7 validaciones posibles)
- `GET /products/{id}/reviews` — reseñas de producto (público)
- `GET /vendors/{id}/reviews` — reseñas de tienda (público)
- `GET /orders/{id}/review-status` — qué items ya fueron valorados (auth)

### No implementado
- Editar reseña (no hay PUT)
- Eliminar reseña (no hay DELETE)
- Moderación UI en admin
- Respuesta del vendor

---

## 25. Backend — Cupones

### Campos
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `code` | String UNIQUE | Código alfanumérico (auto-uppercase, max 30) |
| `discount_type` | `percentage` / `fixed` | Tipo de descuento |
| `discount_value` | Decimal | Valor (% o monto fijo) |
| `min_purchase` | Decimal | Compra mínima requerida |
| `max_discount` | Decimal | Tope de descuento (solo %) |
| `usage_limit` | Int | Máximo de usos totales |
| `usage_count` | Int | Usos actuales |
| `per_user_limit` | Int | Máximo usos por usuario |
| `vendor_id` | Int | Vendor dueño del cupón |
| `vendor_ids` | Text | Multi-vendor (JSON array) |
| `valid_from` / `valid_until` | Datetime | Vigencia |
| `is_active` | Bool | Activo/inactivo |

### Validación (POST /coupons/validate)
Errores posibles: código inválido, cupón expirado, límite de uso alcanzado, monto mínimo no alcanzado.

---

## 26. Backend — Emails

### Template HTML branded
Todos los emails usan `email_wrap($inner, $pretext)`:
- Logo PetsGo en header
- Container max-width 600px responsive
- Colores del sistema (primary, secondary, dark)
- Footer con redes sociales + info empresa
- BCC global a `company_bcc_email`

### Headers estándar
```
Content-Type: text/html; charset=UTF-8
From: {company_name} <{company_from_email}>
Reply-To: {company_name} Soporte <{company_email}>
```

### Tipos de email

| Evento | Destinatario | Asunto |
|--------|-------------|--------|
| Registro cliente | Usuario | "Bienvenido(a) a PetsGo 🐾" |
| Registro rider | Rider | "Verifica tu email — PetsGo Rider 🏍️" |
| Reenvío código rider | Rider | "Tu código de verificación PetsGo" |
| Factura/Boleta | Cliente + BCC vendor + BCC empresa | "PetsGo — Tu Boleta {number}" |
| Stock bajo | Vendor | "⚠️ Stock bajo/agotado" |
| Forgot password | Usuario | "Recupera tu contraseña — PetsGo" |
| Cuenta creada por admin | Usuario | "Tu cuenta PetsGo ha sido creada" |
| Vendor lead | Lead + BCC | "Gracias por tu interés en PetsGo 🏪" |
| Ticket creado | Usuario + Admins | "Ticket #{number} creado" |
| Ticket asignado | Asignado | "Te han asignado ticket #{number}" |
| Ticket respuesta | Usuario | "Respuesta a ticket #{number}" |
| Ticket cambio estado | Usuario | "Tu ticket #{number} cambió a {status}" |
| Suscripción por vencer | Vendor | "Tu suscripción vence en X días" |
| Docs rider expirados | Rider + Admin | "Documentos expirados" |

---

## 27. Backend — Módulos Configurables

7 módulos on/off almacenados en `petsgo_settings`:

| Módulo | Default | Frontend afectado |
|--------|---------|-------------------|
| `module_delivery` | ON | Sistema de delivery |
| `module_riders` | ON | Registro rider, botón de registro |
| `module_vendor_plans` | ON | Planes de suscripción |
| `module_promo_slider` | ON | Slider promocional |
| `module_chatbot` | ON | BotChatOverlay widget |
| `module_reviews` | ON | Sistema de reseñas |
| `module_coupons` | ON | Sistema de cupones |

**Admin puede togglear** desde wp-admin (`petsgo-modules`) o REST API (`PUT /admin/modules`).

---

## 28. Backend — wp-admin (Sitio 2)

### ¿Qué es el "Sitio 2"?
WordPress Admin (`/wp-admin`) es la segunda interfaz de PetsGo. Mientras el frontend React maneja la experiencia del cliente/rider/admin-dashboard, wp-admin maneja la **gestión operativa completa**.

### Páginas wp-admin registradas (20+)

| Slug | Nombre | Acceso |
|------|--------|--------|
| `petsgo-dashboard` | Dashboard | Admin, Vendor |
| `petsgo-products` | Productos | Admin, Vendor |
| `petsgo-product-form` | Form Producto | Admin, Vendor |
| `petsgo-vendors` | Tiendas | Admin |
| `petsgo-vendor-form` | Form Tienda | Admin |
| `petsgo-my-store` | Mi Tienda | Vendor |
| `petsgo-orders` | Pedidos | Admin, Vendor |
| `petsgo-users` | Usuarios | Admin |
| `petsgo-user-form` | Form Usuario | Admin |
| `petsgo-delivery` | Delivery/Riders | Admin |
| `petsgo-plans` | Planes | Admin |
| `petsgo-invoices` | Boletas | Admin |
| `petsgo-invoice-config` | Config Boletas | Admin, Vendor |
| `petsgo-categories` | Categorías | Admin |
| `petsgo-coupons` | Cupones | Admin, Vendor |
| `petsgo-tickets` | Tickets Soporte | Admin, Support, Vendor |
| `petsgo-leads` | Leads Vendor | Admin |
| `petsgo-chatbot` | Config Chatbot | Admin |
| `petsgo-audit-log` | Log Auditoría | Admin |
| `petsgo-settings` | Configuración | Admin |
| `petsgo-modules` | Módulos | Admin |
| `petsgo-email-preview` | Preview Email | Admin |

### Restricciones de acceso por rol

| Rol | Puede ver en wp-admin |
|-----|----------------------|
| **Admin** | Todo |
| **Vendor** | Mi Tienda, Tickets, Media Library |
| **Support** | Tickets solamente |
| **Rider** | ❌ Bloqueado (redirect a home) |
| **Subscriber** | ❌ Bloqueado (redirect a home) |

### Operaciones destructivas
Las eliminaciones (usuario, producto, etc.) requieren **verificación de contraseña del admin** via AJAX `petsgo_verify_admin_password`.

### AJAX Handlers (~60)
Todos usan `check_ajax_referer('petsgo_ajax')` para CSRF.  
Los handlers cubren: CRUD completo de productos, vendors, usuarios, pedidos, riders, planes, categorías, cupones, tickets, leads, invoices, auditoría, settings, chatbot config.

---

## 29. Backend — wp-login.php Personalizado

El plugin inyecta CSS y JS custom en wp-login.php:

### Visual
- **Background:** Gradiente con colores del sistema
- **Logo:** PetsGo (reemplaza el logo WordPress)
- **Form:** Bordes redondeados, sombras custom
- **Responsive:** Logo se ajusta por viewport (180×60px mobile, 220×80px desktop)

### Animaciones
- Al hacer submit del login, aparece overlay con mascotas animadas en 3D
- 4 segundos de animación antes de que el form se envíe realmente
- Animaciones CSS: bounce, walk, tail-wag
- SVG pets: perro, gato, pájaro, hamster, pez

### Para QA
```
URL: /wp-login.php (NO /login del React)
Selectores:
- Logo: #login h1 a
- Form: #loginform
- Username: #user_login
- Password: #user_pass
- Submit: #wp-submit
- Overlay: .petsgo-login-overlay
```

---

## 30. Backend — CORS y Seguridad

### CORS
**Orígenes permitidos:**
- `http://localhost:5173`, `:5174`, `:5177`, `:5176`, `:3000`
- `https://petsgo.cl`, `https://www.petsgo.cl`

**Headers permitidos:** Content-Type, Authorization, X-WP-Nonce, X-PetsGo-Token, X-Requested-With  
**Métodos:** GET, POST, PUT, PATCH, DELETE, OPTIONS  
**Credentials:** true

### SQL Injection (doble capa)
Frontend: `checkFormForSqlInjection()` — 25+ patrones regex  
Backend: `detect_sql_injection()` + `check_form_sql_injection()` — mismos patrones

### File uploads
Todos validados por tipo MIME y tamaño:
- Mascotas: 5MB, image/*
- Rider docs: 5MB, image/* + PDF
- Tickets: 5MB, image/*
- Admin productos: 5MB, image/*

---

## 31. Backend — Cron Jobs

### `petsgo_check_renewals` — Diario
- Envía alertas a vendors: 10, 5, 3, 1 días antes de vencimiento
- Auto-desactiva vendors con suscripción vencida
- Usa transients para evitar emails duplicados

### `petsgo_check_rider_doc_expiry` — Diario
- Chequea `expiry_date` de documentos aprobados
- Alerta a 30, 15, 1 días (usa flags `expiry_notified_30/15/1`)
- Al expirar: suspende rider, marca doc como `expired`, email a rider + admin

---

## 32. Backend — Auditoría

**Función:** `audit($action, $entity_type, $entity_id, $details)`

Registra TODAS las operaciones CRUD en `petsgo_audit_log`:
- `user_id`, `user_name` (quien ejecutó)
- `action` (create/update/delete/login/toggle/etc.)
- `entity_type` (product/vendor/user/order/ticket/etc.)
- `entity_id`
- `details` (JSON con datos relevantes)
- `ip_address`
- `created_at`

Consultable desde wp-admin → Auditoría.

---

## 33. Flujos Completos E2E

### Flujo 1: Registro Cliente → Login → Compra → Valoración

```
1. GET /registro → Llenar form → POST /auth/register
2. Verificar email (manual/automático)
3. GET /login → Llenar credentials → POST /auth/login
4. Token en localStorage → Redirect a /
5. Navegar catálogo → /categoria/:slug → Click producto → /producto/:id
6. Click "Agregar al carrito" → FloatingCart se abre (auto-cierre 3s)
7. Ir a /carrito
8. Seleccionar método entrega (Despacho/Retiro)
9. Si despacho: llenar dirección (región/comuna/calle)
10. Opcional: aplicar cupón
11. Seleccionar pago (Transbank/MercadoPago/Test)
12. Click "Pagar y Confirmar" → POST /orders
13. Backend: crea 1 orden por vendor, genera boletas, envía emails
14. Modal confirmación con pedidos creados
15. /mis-pedidos → ver pedidos agrupados
16. Cuando estado = delivered → Click "⭐ Valorar"
17. Modal: 5 estrellas + comentario → POST /reviews
18. Producto muestra "✅ Valorado"
```

### Flujo 2: Registro Rider → Verificación → Documentos → Entregas

```
1. GET /registro-rider → Llenar form + vehículo → POST /auth/register-rider
2. Recibir código 6 chars por email
3. Ingresar código → POST /auth/verify-rider-email → status: pending_docs
4. GET /login → Login → Redirect a /rider
5. Dashboard rider → Tab Documentos
6. Subir docs requeridos (selfie, ID, fotos vehículo, licencia*)
7. Subida completa → status auto-avanza a pending_review
8. Admin revisa docs (wp-admin) → approved/rejected
9. Si approved: Tab Entregas activo
10. Ver entregas asignadas (ready_for_pickup)
11. Click "Retirar" → status on_the_way
12. Click "Entregar" → status delivered → rider_earning se acumula
13. Tab Ganancias: ver resumen
14. Tab Perfil: datos bancarios para pago
```

### Flujo 3: Vendor (wp-admin)

```
1. Admin crea vendor en wp-admin o vendor aplica via /planes (lead)
2. Admin aprueba vendor → status active
3. Vendor hace login → Redirect a wp-admin/admin.php?page=petsgo-dashboard
4. Vendor ve: Dashboard, Mi Tienda, Productos, Pedidos, Cupones, Tickets
5. Agregar productos (nombre, categoría, precio, stock, imagen)
6. Recibir pedidos → Cambiar estado: processing → ready_for_pickup
7. Rider recoge → on_the_way → delivered
8. Vendor ve ventas y comisiones en dashboard
```

### Flujo 4: Admin supervisa todo

```
1. Login como admin → /admin (React) o /wp-admin (WordPress)
2. Frontend /admin:
   - Tab Dashboard: KPIs globales
   - Tab Tiendas: ver/editar comisiones, "Supervisar" vendor
   - Tab Tienda PetsGo: CRUD productos tienda oficial
   - Tab Riders: buscar, ver stats, aprobar docs
3. wp-admin completo:
   - Todos los 20+ menús
   - CRUD de todo: productos, vendors, users, orders, riders, plans, categories, coupons, tickets
   - Config: settings, módulos, chatbot, email preview, auditoría
```

---

## 34. Datos de Prueba

### Credenciales (variables .env)
| Perfil | Email var | Password var |
|--------|----------|-------------|
| Cliente | `CLIENTE_EMAIL` | `CLIENTE_PASSWORD` |
| Admin | `ADMIN_EMAIL` | `ADMIN_PASSWORD` |
| Vendor | `VENDOR_EMAIL` | `VENDOR_PASSWORD` |
| Rider | `RIDER_EMAIL` | `RIDER_PASSWORD` |

### RUTs válidos verificados
| RUT | DV | Formato |
|-----|-----|---------|
| 11111111 | 1 | 11.111.111-1 |
| 12345678 | 5 | 12.345.678-5 |
| 76543210 | K | 76.543.210-K |
| 22222222 | 2 | 22.222.222-2 |
| 33333333 | 3 | 33.333.333-3 |

### Contraseña válida para tests
`Test@2026!` — cumple las 5 reglas

### Registro válido completo
```js
{
  first_name: 'María',
  last_name: 'González',
  email: `testqa+${Date.now()}@petsgo.cl`,
  password: 'Test@2026!',
  confirmPassword: 'Test@2026!',
  id_type: 'rut',
  id_number: '12345678-5',
  phone: '12345678',
  birth_date: '1990-05-15',
  region: 'Metropolitana',
  comuna: 'Santiago',
}
```

---

## 35. Configuración de Entornos

### Desarrollo Local

| Servicio | URL |
|----------|-----|
| Frontend (Vite) | `http://localhost:5173` |
| Backend (WordPress) | `http://localhost/PetsGoDev` |
| API | `http://localhost:5173/wp-json/petsgo/v1/*` (proxy Vite) |
| wp-admin | `http://localhost/PetsGoDev/wp-admin` |
| wp-login | `http://localhost/PetsGoDev/wp-login.php` |

**Proxy Vite (vite.config.js):**
```js
proxy: {
  '/wp-json': {
    target: 'http://localhost/PetsGoDev',
    changeOrigin: true,
    // Cookie domain rewrite
  },
  '/wp-content/uploads': {
    target: 'http://localhost/PetsGoDev',
    changeOrigin: true,
  }
}
```

### Producción

| Servicio | URL |
|----------|-----|
| Frontend (build estático) | `https://petsgo.cl` |
| Backend | `https://petsgo.cl` (mismo dominio) |
| API | `https://petsgo.cl/wp-json/petsgo/v1/*` |
| wp-admin | `https://petsgo.cl/wp-admin` |
| wp-login | `https://petsgo.cl/wp-login.php` |

### Variables de entorno (.env)
```bash
BASE_URL=https://petsgo.cl          # O http://localhost:5173 para local
CLIENTE_EMAIL=...
CLIENTE_PASSWORD=...
ADMIN_EMAIL=...
ADMIN_PASSWORD=...
VENDOR_EMAIL=...
VENDOR_PASSWORD=...
RIDER_EMAIL=...
RIDER_PASSWORD=...
```

### Comandos de desarrollo
```bash
cd frontend
npm install
npm run dev          # Vite dev server :5173
npm run build        # Build producción → dist/
npm run preview      # Preview build local
```

### Comandos de QA
```bash
cd PetsGoDev         # Raíz del proyecto
npx playwright test                          # Todos los tests
npx playwright test tests/autenticacion.spec.js   # Un archivo
npx playwright test -g "AU-040"              # Un test específico
npx playwright test --headed                 # Con navegador visible
npx playwright show-report reporte/          # Ver reporte HTML
```

---

## Apéndice: Window Events

| Evento | Disparado por | Escuchado por |
|--------|--------------|---------------|
| `petsgo:session_expired` | api.js (en 401) | AuthContext (limpia user) |
| `petsgo:open_chat` | Cualquier componente | BotChatOverlay (abre chat) |

## Apéndice: Colores del Sistema

| Color | Hex | Uso |
|-------|-----|-----|
| Primary (Celeste) | `#00A8E8` | Botones, links, headers |
| Secondary (Amarillo) | `#FFC400` | Acentos, badges, estrellas, chatbot trigger |
| Dark | `#2F3A40` | Tabs activas admin, header dark mode |
| Success | `#22C55E` | Estados exitosos |
| Danger | `#EF4444` | Errores, eliminación |
| Neutral BG | `#F9FAFB` | Fondos de secciones |

---

## 36. Detalles Críticos de Implementación — Verificados en Código Fuente

> **NOTA PARA EL AGENTE QA:** Esta sección fue generada directamente desde la lectura del código fuente (`CartPage.jsx`, `RegisterPage.jsx`, `AuthContext.jsx`, `LoginPage.jsx`, `api.js`, `MyOrdersPage.jsx`, `RiderDashboard.jsx`, `AdminDashboard.jsx`, `chile.js`). Los datos aquí son los **valores reales** que el sistema espera y produce. Úsalos para escribir assertions precisos.

---

### 36.1 CartPage — Valores Exactos y Comportamientos

#### Tipos de dirección (3 opciones, NO 4)
```js
// CÓDIGO FUENTE (CartPage.jsx) — tipos de dirección:
{ value: 'casa',         label: 'Casa',         icon: Home      }
{ value: 'departamento', label: 'Departamento',  icon: Building2 }
{ value: 'oficina',      label: 'Oficina',       icon: Building  }
// ❌ NO existe 'otro' — documentaciones anteriores que lo incluían son incorrectas
```

#### Métodos de pago (valores exactos)
```js
'transbank'    // Color de marca: #E4002B (rojo Webpay)
'mercadopago'  // Color de marca: #009EE3 (azul MercadoPago)
'test_bypass'  // Sólo para testEmails — no aparece en UI como opción seleccionable
```

#### Usuarios de prueba (hardcodeados en CartPage)
```js
const testEmails = ['lmgm.0303@gmail.com', 'automatizacionesbotcore@gmail.com'];
const isTestUser = testEmails.includes(user?.email?.toLowerCase());
// Si isTestUser=true → paymentMethod se fuerza a 'test_bypass', sin pasar por webpay/mercadopago
```

#### Condición de botón deshabilitado
```js
// El botón "Pagar y Confirmar" se deshabilita cuando:
ordering || (!isPickup && !addressComplete) || (!paymentMethod && !isTestUser)
// addressComplete: addressRegion && addressComuna && addressStreet.trim()
```

#### Textos del botón según contexto
```
⏳ Procesando...        → cuando ordering=true
🧪 Confirmar (Modo Prueba) → cuando isTestUser=true
🏪 Confirmar Retiro     → cuando isPickup=true (y no es testUser)
💳 Pagar y Confirmar    → caso normal de pago
```

#### Payload exacto de createOrder (por vendor)
```js
{
  vendor_id:             parseInt(vendorId),            // obligatorio
  items:                 vendorItems.map(i => ({
    product_id:          i.id,
    quantity:            i.quantity,
    price:               parseFloat(i.price)
  })),
  total:                 vendorTotal,                   // total para ese vendor
  delivery_method:       deliveryMethod,                // 'delivery' | 'pickup'
  delivery_fee:          shippingCost,                  // 0 si retiro o envío gratis
  delivery_distance_km:  deliveryDistanceKm || 0,       // calculado con Nominatim+Haversine
  shipping_address:      isPickup ? '' : fullShippingAddress,  // '' si retiro
  shipping_region:       isPickup ? '' : addressRegion,        // '' si retiro
  shipping_comuna:       isPickup ? '' : addressComuna,        // '' si retiro
  address_detail:        isPickup ? '' : addressDetail,        // '' si retiro
  address_type:          isPickup ? '' : addressType,          // '' si retiro
  coupon_code:           savedCoupon?.code || '',        // vacío si no hay cupón
  payment_method:        savedPayment,                  // 'transbank'|'mercadopago'|'test_bypass'
  purchase_group:        purchaseGroup,                 // crypto.randomUUID() (un UUID por checkout)
}
```

> **CRÍTICO:** `shipping_address` es una concatenación: `[addressStreet, addressDetail, addressComuna, addressRegion].filter(Boolean).join(', ')`. Si despacho a domicilio, NUNCA es null/undefined — o tiene valor o es string vacío.  
> `purchase_group` es el mismo UUID para TODOS los vendors en una transacción (agrupa pedidos de múltiples vendors).

#### Post-submit (éxito)
```js
clearCart()     // 1. Vacía el carrito en memoria
setOrderConfirm({ orders, items, subtotal, discount, shipping, total, method, coupon, paymentMethod, isTestUser, address })
// 2. Activa OrderConfirmModal con detalles del pedido
```

#### Post-submit (error)
```js
alert(`Error al procesar tu pedido: ${apiMsg}`)  // alert() nativo del navegador
```

#### "Vaciar carrito" — sin diálogo de confirmación
```js
onClick={clearCart}  // Directo, sin window.confirm() ni diálogo
```

#### Autocompletado de dirección con Nominatim
- Debounce: **500ms** após 4+ chars digitados en el campo de calle
- URL: `https://nominatim.openstreetmap.org/search?format=json&q={text}&countrycodes=cl&limit=5&addressdetails=1`
- Header: `Accept-Language: es`
- Coordenadas base Haversine: `-33.4489, -70.6693` (centro Santiago)

#### Costo de envío
```js
const shippingCost = isPickup ? 0
  : (isFreeShipping ? 0
     : (calculatedShipping ?? (site?.delivery_standard_cost || 2990)));
// Envío gratis cuando: subtotal >= (site?.free_shipping_min || 39990)
```

#### Módulo delivery deshabilitado
```js
const deliveryDisabled = site.module_delivery === false;
// Si true → automáticamente setDeliveryMethod('pickup'), esconde el toggle
```

#### Cupón
```js
// Input: auto-uppercase al escribir. Enter key aplica cupón.
// API call: validateCoupon(code, vendorIds, subtotal)
// Body: { code, vendor_ids: vendorIds, subtotal }  ← snake_case 'vendor_ids'
```

#### CartContext — Comportamiento crítico
- **Carrito en memoria** — se pierde al refrescar página
- Antes de cada test que requiera carrito, agregar productos dentro del test
- `FloatingCart` se abre automáticamente al agregar un producto, se cierra después de ~5 segundos
- Para evitar interferencias: `await page.goto('/carrito')` en lugar de interactuar con FloatingCart

---

### 36.2 RegisterPage — Orden Exacto de Validaciones Frontend

```
1. first_name  → ¿vacío?       → "Nombre es obligatorio"
2. last_name   → ¿vacío?       → "Apellido es obligatorio"
3. checkFormForSqlInjection(form)  ← ⚠️ ANTES del email
4. email       → ¿contiene '@'? → "Email inválido"
5. passStrength < 5              → "La contraseña no cumple todos los requisitos"
6. !passwordMatch                → "Las contraseñas no coinciden"
7. id_type=rut → validateRut()  → "RUT inválido"
8. id_number   → ¿vacío?        → "Documento de identidad es obligatorio"
9. phone.replace(/\D/g,'').length !== 8  → "Debes completar los 8 dígitos del teléfono"
10. region     → ¿vacío?        → "Región es obligatoria"
11. comuna     → ¿vacío?        → "Comuna es obligatoria"
12. !acceptTerms                 → "Debes aceptar los Términos y Condiciones"
```

> **IMPLICANCIA QA:** Si un form tiene SQL injection + email inválido, el error será de SQL injection (paso 3 ocurre antes que el email). Ajusta el orden de assertions en tests de validación negativa.

#### Teléfono enviado al backend
```js
buildFullPhone(form.phone)  →  '+569' + form.phone  // Siempre '+569XXXXXXXX'
```

#### TyC Modal — Comportamiento detallado
- **2 tabs independientes:** `'terms'` (Términos) y `'privacy'` (Política de Privacidad)
- Cada tab tiene su propio estado `reachedBottom`
- Al cambiar de tab: `scrollTop` se resetea a 0, `reachedBottom` vuelve a `false`
- El botón "He leído y acepto" se habilita cuando: `scrollTop + clientHeight >= scrollHeight - 30`
- Selector correcto del contenedor scrolleable:
  ```js
  const scrollContainer = page.locator('[style*="overflow"][style*="auto"]').last();
  await scrollContainer.evaluate(el => el.scrollTop = el.scrollHeight);
  ```

#### Contenido legal — fuente de datos
```js
// RegisterPage carga el contenido por API con fallback hardcodeado:
Promise.allSettled([
  getLegalPage('terminos-y-condiciones'),
  getLegalPage('politica-de-privacidad')
])
```

#### Fuente de datos de Regiones/Comunas en RegisterPage
- **Usa:** `chile.js` → `REGIONES_COMUNAS` (objeto con 16 claves de región, arrays de comunas)
- **NO usa** `chileRegions.js` (ese archivo es solo para CartPage)
- `REGIONES = Object.keys(REGIONES_COMUNAS)` → 16 strings en orden

---

### 36.3 LoginPage — Timings y Comportamientos Críticos

#### Delay de redirección post-login
```js
// Después de login exitoso, se muestra modal de éxito durante 1.8 segundos:
setTimeout(() => { navigate(targetRoute) }, 1800)
// ⚠️ Tests deben esperar este delay. No hacer assertion de URL inmediatamente.
```

#### Redirección según rol
```js
'admin'  → navigate('/admin')                           // React Router
'vendor' → window.location.href = '/wp-admin/admin.php?page=petsgo-dashboard'  // Hard nav
'rider'  → navigate('/rider')                           // React Router
default  → navigate(from || '/')                        // cliente → homepage (o página previa)
```

#### Redirección si mustChangePassword
```js
if (user.mustChangePassword) navigate('/cambiar-contrasena', { replace: true })
// Esta redirección tiene PRIORIDAD sobre la redirección por rol
```

#### Código de error especial para rider con email sin verificar
```js
// El backend retorna error_code: 'rider_pending_email'
// LoginPage detecta este código y redirige:
navigate(`/verificar-rider?email=${encodeURIComponent(username)}`)
```

#### Roleabels (visible en modal éxito)
```js
{ admin: '🛡️ Administrador', vendor: '🏪 Tienda', rider: '🚴 Rider', customer: '🐾 Cliente' }
```

---

### 36.4 AuthContext — Comportamientos Críticos para QA

#### Diferencia entre logout manual y expiración de sesión

| Evento | `setLoggedOut` | Toast visible | localStorage |
|--------|---------------|--------------|--------------|
| `logout()` (manual) | `true` por 2500ms | ✅ Sí ("¡Hasta pronto!") | Limpia token+user |
| `petsgo:session_expired` (401) | `false` (NO cambia) | ❌ No | Limpia token+user |

> **IMPLICANCIA QA:** Un 401 (token vencido) NO muestra toast. Solo redirige a `/login`. Un test que espere toast después de simular expiración de sesión fallará.

#### Validación de token en inicialización
```js
// Si el token existe pero NO empieza con 'petsgo_' → se purga (cleanup de tokens legacy):
if (token && !token.startsWith('petsgo_')) {
  localStorage.removeItem('petsgo_token');
  localStorage.removeItem('petsgo_user');
}
```

#### Shape exacto de userData en localStorage ('petsgo_user')
```json
{
  "id":                123,
  "username":          "juan",
  "email":             "juan@email.com",
  "displayName":       "Juan Pérez",
  "firstName":         "Juan",
  "lastName":          "Pérez",
  "phone":             "+56912345678",
  "avatarUrl":         "https://...",
  "role":              "subscriber",
  "rider_status":      "approved",
  "vehicle_type":      "moto",
  "mustChangePassword": false
}
```

> **CRÍTICO:** `role` en localStorage usa **abreviaciones**: `'admin'`, `'vendor'`, `'rider'`, `'subscriber'`. NO son los roles WordPress. El mapeo inverso es: `admin←→administrator`, `vendor←→petsgo_vendor`, `rider←→petsgo_rider`.

#### `logout()` — flujo completo
```js
localStorage.removeItem('petsgo_token')
localStorage.removeItem('petsgo_user')
localStorage.removeItem('petsgo_nonce')
setUser(null)
setLoggedOut(true)
setTimeout(() => setLoggedOut(false), 2500)  // Toast visible 2.5 segundos
```

#### `updateUser()` — actualización parcial
```js
setUser(prev => {
  const updated = { ...prev, ...updates };
  localStorage.setItem('petsgo_user', JSON.stringify(updated));
  return updated;
});
```

---

### 36.5 api.js — Convenciones de Parámetros (Critical)

| Función | Parámetro | Casing | Ejemplo |
|---------|-----------|--------|---------|
| `validateCoupon(code, vendorIds, subtotal)` | `vendor_ids` | snake_case | `{ code, vendor_ids: vendorIds, subtotal }` |
| `calculateDeliveryFee(distanceKm)` | `distance_km` | snake_case | `{ distance_km: distanceKm }` |
| `changePassword(currentPassword, newPassword)` | `currentPassword`, `newPassword` | camelCase | `{ currentPassword, newPassword }` |
| `updateCommissions(vendorId, salesCommission, deliveryFeeCut)` | `sales_commission`, `delivery_fee_cut` | snake_case | `{ sales_commission, delivery_fee_cut }` |
| `uploadPetPhoto(file)` | `'photo'` | FormData key | `formData.append('photo', file)` |
| `uploadAdminProductImage(file)` | `'image'` | FormData key | `formData.append('image', file)` |
| `uploadRiderDocument(formData)` | raw FormData | — | `formData` sin wrapping adicional |
| `sendChatbotMessage(messages)` | — | — | Timeout override: `60000ms` |
| `getRiderStats(params)` | — | — | Spreads `params` en query string |

---

### 36.6 MyOrdersPage — Estados y Reseñas

#### 7 estados de pedido (STATUS_CONFIG)
```js
{
  pending:          { color: '#FFC400', label: 'Pendiente' },
  payment_pending:  { color: '#FFC400', label: 'Pago Pendiente' },
  preparing:        { color: '#00A8E8', label: 'Preparando' },
  ready_for_pickup: { color: '#8B5CF6', label: 'Listo para enviar' },
  in_transit:       { color: '#F97316', label: 'En camino' },
  delivered:        { color: '#22C55E', label: 'Entregado' },
  cancelled:        { color: '#EF4444', label: 'Cancelado' },
}
```

> **NOTA:** El CLAUDE.md menciona `on_the_way` pero el código usa `in_transit`. Usar `in_transit` en tests.

#### Agrupación de pedidos
```js
groupIntoPurchases(orders)
// Agrupa por order.purchase_group
// Si un order no tiene purchase_group → usa `single_${order.id}` como key
```

#### Reseñas — cuándo y cómo
- Button "⭐ Valorar" solo aparece en pedidos con status `delivered`
- Estado de reseña cargado en paralelo para todos los delivered orders: `getOrderReviewStatus(id)`
- Shape `reviewedItems`: `{ [orderId]: { products: [productId, ...], vendor: boolean } }`
- Rating default al abrir modal: **5 estrellas**

#### Payload de submitReview
```js
// Para reseña de PRODUCTO:
{ order_id, product_id, vendor_id, review_type: 'product', rating: 1-5, comment }

// Para reseña de VENDOR:
{ order_id, vendor_id, review_type: 'vendor', rating: 1-5, comment }
// product_id no se envía en reseñas de vendor
```

#### Errores en reviews
- Error en `submitReview()` → `alert(msg)` (alert nativo). No hay toasts inline.

---

### 36.7 RiderDashboard — Constantes Exactas

#### Tabs (7 valores)
```js
'home'       // default — panel principal
'deliveries' // historial de entregas
'documents'  // subir/ver documentos
'ratings'    // calificaciones recibidas
'earnings'   // ganancias
'report'     // reporte de actividad
'profile'    // perfil y cuenta bancaria
```

#### Auto-redirect de tab en carga
```js
// Si rider_status === 'pending_docs' || rider_status === 'suspended'
// → se fuerza automáticamente setTab('documents')
// Tests deben tener en cuenta que el tab inicial puede no ser 'home'
```

#### STATUS_CONFIG de entregas (flujo secuencial)
```js
ready_for_pickup  → acción "Marcar como recogido"    → siguiente: in_transit
in_transit        → acción "Marcar como entregado"   → siguiente: delivered
delivered         → estado final (no hay acción)
```

#### Tipos de documentos (DOC_TYPES — keys exactas)
```js
'selfie'               // Foto del rider (obligatorio para todos)
'id_card'              // Cédula de identidad
'vehicle_photo_1'      // Foto vehículo - frontal
'vehicle_photo_2'      // Foto vehículo - lateral
'vehicle_photo_3'      // Foto vehículo - trasera
'license'              // Licencia de conducir (solo scooter/moto/auto)
'vehicle_registration' // Padrón del vehículo (solo scooter/moto/auto)
```

#### Tipos de vehículo (VEHICLE_LABELS)
```js
bicicleta: '🚲 Bicicleta'
scooter:   '🛵 Scooter'
moto:      '🏍️ Moto'
auto:      '🚗 Auto'
a_pie:     '🚶 A pie'
```

#### Bancos disponibles (BANK_OPTIONS — 15 opciones)
```
Banco de Chile, Banco Estado, Banco Santander, BCI, Banco Itaú,
Scotiabank, Banco Falabella, Banco Ripley, Banco Security, Banco BICE,
Banco Consorcio, Banco Internacional, MACH, Tenpo, Mercado Pago
```

#### Tipos de cuenta (ACCOUNT_TYPES)
```js
corriente: 'Cuenta Corriente'
vista:     'Cuenta Vista / RUT'
ahorro:    'Cuenta de Ahorro'
```

---

### 36.8 AdminDashboard — Detalles de Implementación

#### Tabs del panel admin (4 valores reales)
```js
'dashboard'  // default — KPIs globales
'vendors'    // tabla de vendors + modo supervisar
'riders'     // tabla de riders + estadísticas
'store'      // tienda PetsGo oficial CRUD
```

> **⚠️ El CLAUDE.md menciona tabs de 'Usuarios', 'Pedidos', 'Finanzas' etc. — esos NO están implementados en el código actual.** Solo existen estos 4 tabs.

#### Paginación
```js
const ITEMS_PER_PAGE = 5;  // 5 filas por página en todas las tablas del admin
```

#### Estrategia de carga de datos
```js
// useEffect que escucha cambios en `tab`
// Llama a loadData() solo cuando cambia la tab activa
// Cada tab carga sus propios datos en ese momento (lazy loading por tab)
```

#### Fallback de DEMO data
```js
// Si la API falla:
// - Estadísticas → DEMO_ADMIN_STATS (datos hardcodeados)
// - Vendors → DEMO_ADMIN_VENDORS (datos hardcodeados)
// Tests que dependen de datos reales pueden ver DEMO data si el servidor está caído
```

#### Búsqueda de riders
```js
// Es filtrado LOCAL (en memoria), no hace llamada API al buscar
// Filtra por nombre o email del array cargado previamente
```

---

### 36.9 chile.js vs chileRegions.js — Diferencias Críticas

| Característica | `chile.js` | `chileRegions.js` |
|---------------|----------|---------------|
| Estructura de datos | Object (`REGIONES_COMUNAS`) | Array `[{name, comunas:[]}]` |
| Acceso a regiones | `Object.keys(REGIONES_COMUNAS)` | `data.map(r => r.name)` |
| Acceso a comunas | `REGIONES_COMUNAS[region]` | `data.find(r=>r.name===region).comunas` |
| Usado por | RegisterPage, RiderRegisterPage, UserProfilePage, RiderDashboard | CartPage ÚNICAMENTE |
| Exporta | `REGIONES`, `REGIONES_COMUNAS`, `getComunas`, `sanitizeName`, `isValidName`, `validateRut`, `checkFormForSqlInjection`, etc. | Solo datos de regiones |

> **IMPLICANCIA QA:** Los selectores de región/comuna en el form de registro y en el checkout (CartPage) usan estructuras de datos internas distintas pero el resultado visual es idéntico. Los tests de Playwright no necesitan distinguirlos.

#### Regex de validación de nombres (chile.js)
```js
// sanitizeName — elimina caracteres inválidos:
const sanitizeRegex = /[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]/g;
// Los números y símbolos se eliminan silenciosamente al escribir

// isValidName — valida el nombre ya sanitizado:
const validNameRegex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]{2,}$/;
```

#### SQL injection patterns detectados (checkFormForSqlInjection)
```
SELECT, INSERT, UPDATE, DELETE, DROP, ALTER, CREATE, EXEC, UNION, TRUNCATE,
DECLARE, CAST, CONVERT (keywords SQL)
-- (comentario SQL)
;$ (punto y coma al final)
/* y */ (comentarios bloque)
xp_ (procedimientos extendidos SQL Server)
sp_ (procedimientos almacenados)
0x[hex] (hex injection)
CHAR() CONCAT() (string manipulation)
' OR '1'='1  y  ' AND '1'='1 (boolean-based injection)
INFORMATION_SCHEMA (info leak)
SLEEP() BENCHMARK() LOAD_FILE() OUTFILE (timing y file ops)
```

---

### 36.10 App.jsx — Comportamientos de Redirección

#### FloatingCart
```js
// ⚠️ FloatingCart NO se muestra a ningún rider (independiente del status):
{!activeRider && <FloatingCart ... />}
// activeRider = user?.role === 'rider'
```

#### Vendor redirect — dispara en CADA cambio de pathname
```js
// useEffect([pathname]) — con exclusiones:
if (user?.role === 'vendor' && pathname !== '/login' && pathname !== '/cambiar-contrasena') {
  window.location.href = '/wp-admin/admin.php?page=petsgo-dashboard';
}
// ⚠️ Tests con vendor no pueden navegar a ninguna ruta del frontend
```

#### Banner de rider no aprobado
```js
// isUnapprovedRider se muestra cuando:
user?.role === 'rider' && user?.rider_status && user.rider_status !== 'approved'
// Muestra el riderStatusLabels[rider_status]:
{
  pending_docs:   '📋 Sube tus documentos para completar tu registro como Rider',
  pending_review: '⏳ Tus documentos están en revisión. Te notificaremos cuando sean aprobados.'
}
```

#### Color de fondo de la app
```css
background-color: #f5f5f5;  /* bg-[#f5f5f5] */
```

---

### 36.11 Selectores Playwright Verificados (Actualizaciones)

#### CartPage — selectors de tipo de dirección (3 opciones)
```js
// Los 3 botones de tipo de dirección:
page.getByRole('button', { name: /casa/i })
page.getByRole('button', { name: /departamento/i })
page.getByRole('button', { name: /oficina/i })
// ❌ NO existe 'otro'
```

#### CartPage — selector de carrito activo
```js
// Verificar que el usuario tiene items en el carrito:
await expect(page.locator('table')).toBeVisible();
// O verificar que el botón de pago existe:
await expect(page.getByRole('button', { name: /pagar y confirmar|confirmar retiro|confirmar.*prueba/i })).toBeVisible();
```

#### LoginPage — modal de éxito
```js
// Esperar el modal de rol antes del redirect (1.8s):
await expect(page.getByText(/🐾 Cliente|🛡️ Administrador|🏪 Tienda|🚴 Rider/)).toBeVisible({ timeout: 10000 });
// Luego esperar el redirect con timeout mayor:
await page.waitForURL(/\/(admin|rider|$)/, { timeout: 10000 });
```

#### RiderDashboard — verificar tab activo
```js
// Para verificar que el rider fue auto-redirigido al tab documentos:
await expect(page.getByRole('button', { name: /documentos/i })).toHaveClass(/active|selected|bg-blue/i);
// O verificar que el contenido de documentos es visible:
await expect(page.getByText(/sube tus documentos|mis documentos/i)).toBeVisible();
```

---

### 36.12 Notas de QA — Trampas Comunes

| Trampa | Descripción | Solución |
|--------|-------------|---------|
| **Carrito se pierde al refrescar** | CartContext no persiste en localStorage | Agregar productos en el mismo test, no depender de estado previo |
| **Redirect vendor en cualquier ruta** | Vendor es redirigido a wp-admin desde CUALQUIER ruta frontend | No usar `page.goto()` a rutas del frontend para tests de vendor |
| **Riders en marketplace** | Todos los riders son redirigidos a `/rider` | Tests de marketplace deben usar cuentas cliente |
| **Login delay de 1.8s** | Hay un setTimeout de 1800ms antes del redirect | `waitForURL` con timeout suficiente (5-10s) |
| **TyC requiere scroll en cada tab** | Cada tab del modal TyC resetea el scroll | Si test necesita aceptar solo T&C, scrollear solo en el primer tab |
| **SQL check antes que email** | Si hay SQL injection + email inválido, el primer error es de SQL | En tests de validación múltiple, el orden importa |
| **AdminDashboard con DEMO data** | Si API falla, admin muestra datos de demo, no error | Verificar que la API está activa antes de comenzar tests de admin |
| **RiderDashboard auto-tab** | Si rider tiene `pending_docs` o `suspended`, el tab inicial es `documents` no `home` | Verificar tab activo esperado según rider_status |
| **FloatingCart auto-abre** | Al agregar un producto, FloatingCart se abre | Ir directo a `/carrito` para tests de checkout |
| **address 'otro' no existe** | El tipo de dirección 'otro' no existe aunque documentación anterior lo menciona | Solo 3 opciones: casa, departamento, oficina |
| **`in_transit` vs `on_the_way`** | El código usa `in_transit` pero algunos docs usan `on_the_way` | Siempre usar `in_transit` en assertions de estado |
| **Logout toast vs 401 toast** | Un 401 (sesión expirada) NO muestra toast — solo redirect a /login | No esperar toast en tests de expiración de sesión |
