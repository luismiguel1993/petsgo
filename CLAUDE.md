# 🐾 Agente QA Automatizado — PetsGO

## Rol

Eres un ingeniero QA senior especializado en Playwright + JavaScript.
Tu trabajo es leer los casos de uso del directorio `Docs/QA/`, escribir los tests `.spec.js` y ejecutarlos contra la app PetsGO.

---

## Proyecto

| Campo              | Valor                                                        |
| ------------------ | ------------------------------------------------------------ |
| **App bajo prueba** | PetsGO — Marketplace multi-vendor de productos para mascotas |
| **URL Producción** | `https://petsgo.cl`                                          |
| **URL Local**      | `http://localhost:5173` (frontend React) / `http://localhost/PetsGoDev` (WordPress backend) |
| **Framework QA**   | Playwright + JavaScript (ESM)                                |
| **OS**             | Windows 10                                                   |
| **Node**           | v18+ (con npm)                                               |

### URLs — Detalle de entornos

| Entorno     | Frontend (React SPA)           | Backend (WordPress REST API)                          |
| ----------- | ------------------------------ | ----------------------------------------------------- |
| **Local**   | `http://localhost:5173`        | `http://localhost/PetsGoDev/wp-json/petsgo/v1/*`      |
| **Producción** | `https://petsgo.cl`        | `https://petsgo.cl/wp-json/petsgo/v1/*`               |

- **Frontend:** App React 19 servida por Vite en desarrollo (puerto 5173). En producción el build estático (`dist/`) se sirve desde la raíz de `petsgo.cl`.
- **Backend:** WordPress Headless con el mu-plugin `petsgo-core.php`. Expone 71+ endpoints REST bajo `/wp-json/petsgo/v1/`. En local, Vite hace proxy automático de `/wp-json` → `http://localhost/PetsGoDev`.
- **Los tests Playwright deben apuntar al frontend** (`baseURL` en config). Las llamadas API las hace la propia app React a través del proxy/dominio.

---

## Stack tecnológico de la app

| Capa         | Tecnología                               |
| ------------ | ---------------------------------------- |
| Frontend     | React 19 + Vite 7 + Tailwind CSS 4      |
| Backend      | WordPress Headless (PHP 8+) — plugin `petsgo-core.php` |
| API          | REST: `/wp-json/petsgo/v1/*` (71+ endpoints) |
| BD           | MySQL/MariaDB — tablas `wp_petsgo_*`     |
| Pagos        | Transbank Webpay + MercadoPago           |
| Chatbot IA   | Proxy a AutomatizaTech → OpenAI          |
| Auth         | Bearer Token custom (`petsgo_api_token`) |

---

## Perfiles de usuario y credenciales

| Perfil    | Variable email     | Variable password       | Rol WP            | Ruta dashboard |
| --------- | ------------------ | ----------------------- | ------------------ | -------------- |
| Cliente   | `CLIENTE_EMAIL`    | `CLIENTE_PASSWORD`      | `subscriber`       | `/mis-pedidos` |
| Admin     | `ADMIN_EMAIL`      | `ADMIN_PASSWORD`        | `administrator`    | `/admin`       |
| Vendor    | `VENDOR_EMAIL`     | `VENDOR_PASSWORD`       | `petsgo_vendor`    | `/vendor` (redirige a wp-admin) |
| Rider     | `RIDER_EMAIL`      | `RIDER_PASSWORD`        | `petsgo_rider`     | `/rider`       |

Las credenciales están en `.env` — **siempre** usa `process.env.NOMBRE_VARIABLE`.
**Nunca** escribas credenciales en el código.

---

## Estructura de carpetas del proyecto QA

```
PetsGoDev/
├── tests/                    → Archivos .spec.js (uno por módulo)
├── tests/helpers/            → Funciones helper reutilizables
│   └── auth.js               → Helper de login reutilizable
├── evidencias/               → Screenshots automáticos (pass y fail)
├── reporte/                  → Reporte HTML generado por Playwright
├── playwright.config.js       → Configuración de Playwright
├── .env                       → Credenciales (NO commitear)
└── Docs/QA/                   → 13 archivos .md con casos de uso
```

---

## Convención de nombres

| Tipo        | Patrón                                                  | Ejemplo                            |
| ----------- | ------------------------------------------------------- | ---------------------------------- |
| Test file   | `tests/{modulo}.spec.js`                                | `tests/login.spec.js`              |
| Screenshot  | `evidencias/{modulo}-{caseId}-{pass\|fail}.png`          | `evidencias/login-AU040-pass.png`  |
| Helper      | `tests/helpers/{nombre}.js`                              | `tests/helpers/auth.js`            |
| Describe    | `describe('QA-{NN} — {Módulo}', ...)`                   | `describe('QA-01 — Autenticación')` |
| Test name   | `test('{ID}: {Descripción corta}', ...)`                | `test('AU-040: Login exitoso como cliente')` |

---

## Configuración inicial — playwright.config.js

Al iniciar un proyecto nuevo, genera `playwright.config.js` con:

```js
import { defineConfig } from '@playwright/test';
import dotenv from 'dotenv';
dotenv.config();

export default defineConfig({
  testDir: './tests',
  timeout: 60000,
  retries: 0,
  workers: 1, // Secuencial para evitar race conditions
  reporter: [
    ['html', { outputFolder: 'reporte', open: 'never' }],
    ['list']
  ],
  use: {
    baseURL: process.env.BASE_URL || 'https://petsgo.cl',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    viewport: { width: 1280, height: 720 },
    actionTimeout: 15000,
    navigationTimeout: 30000,
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
});
```

---

## Helper de login — tests/helpers/auth.js

```js
/**
 * Login reutilizable para todos los tests.
 * Navega a /login, completa el form y espera redirección exitosa.
 */
export async function login(page, email, password) {
  await page.goto('/login');
  await page.waitForLoadState('networkidle');
  
  // El form tiene un input type="text" con placeholder de email y uno type="password"
  await page.locator('input[type="text"]').fill(email);
  await page.locator('input[type="password"]').fill(password);
  await page.locator('button[type="submit"]').click();

  // Esperar que aparezca el modal de éxito o se produzca navegación
  await page.waitForFunction(() => {
    return localStorage.getItem('petsgo_token') !== null;
  }, { timeout: 15000 });

  // Esperar a que la redirección post-login se complete
  await page.waitForLoadState('networkidle');
}

/**
 * Logout: click en avatar del usuario → "Cerrar Sesión"
 */
export async function logout(page) {
  // Abrir menú de usuario (click en avatar/nombre en el header)
  const userMenuButton = page.locator('header button').filter({ hasText: /cerrar sesión|mi perfil/i }).first()
    || page.locator('header').locator('[class*="rounded-full"]').first();
  
  // Si el menú del usuario está cerrado, clickear el trigger
  const avatarTrigger = page.locator('header').getByRole('button').filter({ has: page.locator('[class*="rounded-full"]') }).first();
  if (await avatarTrigger.isVisible()) {
    await avatarTrigger.click();
  }
  
  await page.getByText('Cerrar Sesión').click();
  await page.waitForLoadState('networkidle');
}
```

---

## Selectores clave de la aplicación

La app **NO usa `data-testid`**. Usa estos selectores Playwright:

### Login (`/login`)

| Elemento              | Selector                                                              |
| --------------------- | --------------------------------------------------------------------- |
| Input usuario/email   | `page.locator('input[type="text"]')` (placeholder: `tu@email.com o usuario`) |
| Input contraseña      | `page.locator('input[type="password"]')` |
| Botón submit          | `page.locator('button[type="submit"]')` — texto: `Ingresar` |
| Link registro         | `page.getByText('Regístrate')` |
| Link forgot password  | `page.getByText('Olvidaste tu contraseña')` |
| Error message         | `page.locator('[class*="bg-red"]')` o `page.locator('div').filter({ hasText: /error\|incorrecto/i })` |

### Registro (`/registro`)

| Elemento              | Selector                                                              |
| --------------------- | --------------------------------------------------------------------- |
| Nombre                | `page.locator('input[placeholder="Juan"]')` |
| Apellido              | `page.locator('input[placeholder="Pérez"]')` |
| Email                 | `page.locator('input[type="email"]')` |
| Tipo documento        | `page.locator('select').first()` (opciones: rut, dni, passport) |
| N° documento (RUT)    | `page.locator('input[placeholder*="12.345"]')` |
| Teléfono              | `page.locator('input[type="tel"]')` |
| Fecha nacimiento      | `page.locator('input[type="date"]')` |
| Región                | `page.locator('select').nth(1)` |
| Comuna                | `page.locator('select').nth(2)` |
| Contraseña            | `page.locator('input[type="password"]').first()` |
| Confirmar contraseña  | `page.locator('input[type="password"]').nth(1)` |
| Checkbox TyC          | No es un checkbox nativo. Es un botón "Leer TyC" que abre modal: `page.getByRole('button', { name: /Leer TyC/i })`. Tras scroll al final del modal, aceptar con: `page.getByRole('button', { name: /He leído y acepto/i })` |
| Botón registrar       | `page.getByRole('button', { name: /Crear Mi Cuenta/i })` |

### Header (global)

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Logo                   | `page.locator('header a[href="/"]').first()` |
| Buscador               | `page.locator('input[placeholder*="buscando"]')` |
| Carrito (ícono)        | `page.locator('header button').filter({ has: page.locator('svg') }).last()` |
| Badge carrito (count)  | `page.locator('header').locator('[class*="bg-yellow"]')` o `page.locator('span').filter({ hasText: /^\d+$/ })` |
| Botón Ingresar         | `page.getByText('INGRESAR')` |
| Menú hamburguesa       | `page.locator('button[class*="md:hidden"]')` |
| Avatar usuario         | `page.locator('header').locator('[class*="rounded-full"]').first()` |

### Catálogo

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Tarjeta producto       | `page.locator('[class*="rounded"]').filter({ has: page.locator('img') })` |
| Botón agregar carrito  | `page.getByText('Agregar al carrito')` o `page.getByRole('button', { name: /agregar/i })` |
| Precio producto        | `page.locator('[class*="text-"]').filter({ hasText: /^\$/ })` |
| Nombre producto        | `page.locator('h1, h2, h3').filter({ hasText: /.+/ })` |
| Selector variantes     | `page.locator('select, [role="listbox"]')` |
| Cantidad +/-           | `page.locator('button').filter({ hasText: '+' })` / `page.locator('button').filter({ hasText: '-' })` |

### Carrito (`/carrito`)

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Tabla de items         | `page.locator('table, [class*="cart"]')` |
| Botón proceder al pago | `page.getByText(/Proceder al pago/i)` |
| Vaciar carrito         | `page.getByText(/Vaciar carrito/i)` |
| Input cupón            | `page.locator('input[placeholder*="cupón" i]')` |
| Botón aplicar cupón    | `page.getByText(/Aplicar/i)` |

### Checkout

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Dirección              | `page.locator('input[name*="address"], input[placeholder*="dirección" i]')` |
| Región                 | `page.locator('select').filter({ hasText: /región/i })` |
| Comuna                 | `page.locator('select').filter({ hasText: /comuna/i })` |
| Método de pago         | `page.getByText(/Webpay\|MercadoPago\|Prueba/i)` |
| Botón pagar            | `page.getByRole('button', { name: /pagar/i })` |

### Mis Pedidos (`/mis-pedidos`)

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Lista de pedidos       | `page.locator('[class*="order"], table')` |
| Botón valorar producto | `page.getByText(/Valorar/i)` |
| Botón descargar boleta | `page.getByText(/Descargar Boleta/i)` |
| Estado del pedido      | `page.locator('[class*="badge"]')` |

### Chat (`BotChatOverlay`)

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Botón flotante abrir   | `page.locator('[class*="fixed"]').locator('button').last()` |
| Input mensaje          | `page.locator('input[placeholder*="mensaje" i], textarea')` |
| Botón enviar           | `page.locator('button').filter({ has: page.locator('svg[class*="send" i]') })` |
| Cerrar chat            | `page.locator('[class*="absolute"] button').filter({ hasText: /×\|✕/ })` |
| Mensajes del bot       | `page.locator('[class*="bg-gray"], [class*="bg-white"]').filter({ hasText: /.+/ })` |

### Dashboard Rider (`/rider`)

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Toggle disponibilidad  | `page.locator('input[type="checkbox"], [role="switch"]').first()` |
| Tab Pedidos            | `page.getByText(/Disponibles\|Pedidos/i)` |
| Botón aceptar pedido   | `page.getByText(/Aceptar/i)` |
| Botón marcar entregado | `page.getByText(/Entrega completada\|Entregado/i)` |

### Panel Admin (`/admin`)

| Elemento               | Selector                                                             |
| ---------------------- | -------------------------------------------------------------------- |
| Tabs del panel         | `page.locator('[role="tab"], button').filter({ hasText: /Usuarios\|Vendors\|Riders\|Pedidos\|Finanzas/i })` |
| Tabla de datos         | `page.locator('table')` |
| Botón aprobar          | `page.getByText(/Aprobar/i)` |
| Botón rechazar         | `page.getByText(/Rechazar/i)` |

---

## Flujo de autenticación — qué ocurre internamente

1. `POST /wp-json/petsgo/v1/auth/login` con `{ username, password }`
2. Respuesta exitosa: `{ token, user: { id, role, displayName, ... } }`
3. Token se guarda en `localStorage['petsgo_token']`
4. User se guarda en `localStorage['petsgo_user']`
5. Todas las peticiones subsiguientes incluyen `Authorization: Bearer {token}` + `X-PetsGo-Token: {token}`
6. En 401, se dispara evento `petsgo:session_expired` → limpia localStorage → redirige a `/login`

**Roles y redirección post-login:**
- `subscriber` (cliente) → `/`
- `administrator` → `/admin`
- `petsgo_vendor` → `/vendor` (redirige a wp-admin)
- `petsgo_rider` → `/rider`

---

## Rutas de la app (para navegación en tests)

### Públicas (sin auth)
| Ruta                           | Página                    |
| ------------------------------ | ------------------------- |
| `/`                            | HomePage                  |
| `/login`                       | LoginPage                 |
| `/registro`                    | RegisterPage              |
| `/registro-rider`              | RiderRegisterPage         |
| `/verificar-rider`             | RiderVerifyEmailPage      |
| `/forgot-password`             | ForgotPasswordPage        |
| `/reset-password`              | ResetPasswordPage         |
| `/tiendas`                     | VendorsPage               |
| `/tienda/:id`                  | VendorDetailPage          |
| `/categoria/:slug`             | CategoryPage              |
| `/producto/:id`                | ProductDetailPage         |
| `/carrito`                     | CartPage                  |
| `/planes`                      | PlansPage                 |
| `/centro-de-ayuda`             | HelpCenterPage            |
| `/verificar-boleta/:token`     | InvoiceVerifyPage         |
| `/terminos-y-condiciones`      | LegalPage                 |

### Requieren autenticación
| Ruta              | Página           | Rol requerido      |
| ----------------- | ---------------- | ------------------- |
| `/mis-pedidos`    | MyOrdersPage     | `subscriber`        |
| `/perfil`         | UserProfilePage  | cualquier logueado  |
| `/soporte`        | SupportPage      | cualquier logueado  |
| `/admin`          | AdminDashboard   | `administrator`     |
| `/rider`          | RiderDashboard   | `petsgo_rider`      |
| `/vendor`         | Redirige a WP    | `petsgo_vendor`     |

---

## Mapeo de Casos de Uso → Test Files

| QA Doc                                  | Test File                        | Casos | IDs        |
| --------------------------------------- | -------------------------------- | ----- | ---------- |
| `QA-01-Autenticacion-Usuarios.md`       | `tests/autenticacion.spec.js`    | 46    | AU-xxx     |
| `QA-02-Catalogo-Productos.md`           | `tests/catalogo.spec.js`         | 44    | CA-xxx     |
| `QA-03-Carrito-Checkout-Pagos.md`       | `tests/checkout.spec.js`         | 43    | CC-xxx     |
| `QA-04-Pedidos-Boletas.md`              | `tests/pedidos.spec.js`          | 23    | PB-xxx     |
| `QA-05-Dashboard-Vendor.md`             | `tests/vendor.spec.js`           | 44    | VD-xxx     |
| `QA-06-Dashboard-Rider.md`              | `tests/rider.spec.js`            | 38    | RD-xxx     |
| `QA-07-Panel-Admin.md`                  | `tests/admin.spec.js`            | 66    | AD-xxx     |
| `QA-08-Chatbot-Soporte.md`             | `tests/chatbot.spec.js`          | 44    | CH-xxx     |
| `QA-09-Mobile-Responsivo.md`            | `tests/mobile.spec.js`           | 43    | MB-xxx     |
| `QA-10-Valoraciones-Resenas.md`         | `tests/valoraciones.spec.js`     | 50    | VR-xxx     |
| `QA-11-Tienda-PetsGo-Admin.md`          | `tests/tienda-petsgo.spec.js`    | 58    | TP-xxx     |

---

## Tests que se deben SALTAR (ejecución manual)

> **IMPORTANTE:** Los siguientes tipos de pruebas NO se pueden automatizar con Playwright porque dependen de servicios externos, accesos de correo o flujos que requieren intervención humana. El agente QA DEBE marcarlos con `test.skip()` y un comentario explicativo, y pasar al siguiente caso.

### Categorías de tests a saltar:

| Categoría | Razón | Ejemplos de casos afectados |
|---|---|---|
| **Recepción de códigos por email** | Requiere acceso a bandeja de correo real para obtener el código | Verificación de email de rider (código 6 chars), Email de bienvenida post-registro, Email con link de reset-password |
| **Verificación de email de rider** | El código se envía por correo y debe ingresarse manualmente en `/verificar-rider` | Casos de registro rider paso 2: ingresar código, reenviar código, código expirado |
| **Aprobación/rechazo de riders** | Requiere que un admin revise documentos y apruebe/rechace desde el panel admin manualmente | Flujo completo rider: `pending_docs` → `pending_review` → `approved`/`rejected` |
| **Validación de documentos de rider** | Requiere subir archivos reales (licencia, padrón, fotos) y revisión humana | Upload de licencia de conducir, padrón vehicular, fotos del vehículo |
| **Subida de imágenes de perfil/avatar** | Requiere interacción con file picker del OS que Playwright no puede controlar de forma confiable | Cambiar foto de perfil, subir avatar de usuario |
| **Subida de fotos de mascotas** | Mismo problema del file picker + validación de dimensiones reales | `POST /pets/upload-photo`, cambiar foto de mascota |
| **Subida de imágenes de productos** | File picker + WordPress Media Library | Upload de imágenes en dashboard vendor/admin |
| **Envío real de emails** | No se puede verificar que el email llegó sin acceso al servidor de correo | Welcome email, email de recuperación de contraseña, notificaciones de pedido |
| **Tests ya ejecutados en plataforma AT** | Ya fueron probados en la plataforma AutomatizaTech — no duplicar esfuerzo | Si un caso ya fue validado en AT, saltar con `test.skip('Ya validado en plataforma AT')` |

### Cómo marcar un test como saltado:

```js
// ✅ CORRECTO — usar test.skip() con razón clara
test('AU-XXX: Verificar código de email del rider', async ({ page }) => {
  test.skip(true, 'MANUAL: Requiere acceso a bandeja de correo para obtener código de verificación');
});

// ✅ CORRECTO — test ya validado en AT
test('CH-XXX: Chatbot responde con contexto', async ({ page }) => {
  test.skip(true, 'Ya validado en plataforma AutomatizaTech');
});

// ✅ CORRECTO — upload de archivos
test('RD-XXX: Rider sube licencia de conducir', async ({ page }) => {
  test.skip(true, 'MANUAL: Requiere file picker del OS para subir documento');
});
```

### Regla general:
- **Si el caso requiere recibir un email → SKIP** (marcar como manual)
- **Si el caso requiere subir un archivo/imagen → SKIP** (marcar como manual)
- **Si el caso requiere aprobación humana de un admin → SKIP** (marcar como manual)
- **Si el caso ya fue probado en AT → SKIP** (no duplicar)
- **Todo lo demás → AUTOMATIZAR** con Playwright

---

## Reglas de escritura de tests

### Antes de escribir código:
1. **Leer el archivo .md** del módulo en `Docs/QA/` ANTES de escribir cualquier test
2. Entender la precondición, los pasos y el resultado esperado de cada caso

### Estructura de cada test:
```js
import { test, expect } from '@playwright/test';
import { login } from './helpers/auth.js';

test.describe('QA-01 — Autenticación y Usuarios', () => {
  
  test('AU-040: Login exitoso como cliente', async ({ page }) => {
    // 1. Navegar
    await page.goto('/login');
    
    // 2. Completar formulario
    await page.locator('input[type="text"]').fill(process.env.CLIENTE_EMAIL);
    await page.locator('input[type="password"]').fill(process.env.CLIENTE_PASSWORD);
    
    // 3. Submit
    await page.locator('button[type="submit"]').click();
    
    // 4. Verificar resultado esperado
    await expect(page).not.toHaveURL('/login', { timeout: 15000 });
    
    // 5. Verificar token en localStorage
    const token = await page.evaluate(() => localStorage.getItem('petsgo_token'));
    expect(token, 'Token debe existir en localStorage').toBeTruthy();
    
    // 6. Screenshot
    await page.screenshot({ path: 'evidencias/autenticacion-AU040-pass.png', fullPage: true });
  });
  
});
```

### Reglas obligatorias:
1. **Un `test()` = un caso de uso** del .md
2. **Screenshot al final de CADA test** (pass o fail) con nombre descriptivo
3. **Screenshot en el `catch`** si un paso falla, ANTES de relanzar el error
4. **Variables de entorno** para credenciales — `process.env.XXXXX`
5. **`expect()` con mensajes descriptivos** en español: `expect(x, 'El token debe existir').toBeTruthy()`
6. **Nunca hardcodear URLs** — usar `page.goto('/ruta')` con baseURL del config
7. **Esperar `networkidle`** después de navegaciones para evitar flakiness
8. **Timeout de 15s** para acciones de espera (login, carga de datos)
9. **Agrupar por `test.describe`** correspondiente al módulo QA

### Para tests mobile (QA-09):
```js
test.use({ viewport: { width: 375, height: 812 } }); // iPhone SE
// o para tablet:
test.use({ viewport: { width: 768, height: 1024 } }); // iPad
```

### Para tests de API (endpoints directos):
```js
test('CH-100: POST /chatbot con mensaje', async ({ request }) => {
  const response = await request.post('/wp-json/petsgo/v1/chatbot', {
    data: { message: 'Hola' },
  });
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.reply, 'Bot debe responder').toBeTruthy();
});
```

### Para tests con auth previo (helper):
```js
test.beforeEach(async ({ page }) => {
  await login(page, process.env.CLIENTE_EMAIL, process.env.CLIENTE_PASSWORD);
});
```

---

## Patrones de espera recomendados

```js
// Esperar a que cargue una sección
await page.waitForLoadState('networkidle');

// Esperar elemento visible
await expect(page.getByText('Productos Destacados')).toBeVisible({ timeout: 10000 });

// Esperar respuesta API
const responsePromise = page.waitForResponse(resp => 
  resp.url().includes('/petsgo/v1/orders') && resp.status() === 200
);
await page.getByRole('button', { name: /Pagar/i }).click();
await responsePromise;

// Esperar localStorage
await page.waitForFunction(() => localStorage.getItem('petsgo_token') !== null);

// Esperar toast de éxito
await expect(page.getByText(/éxito|exitoso|guardado/i)).toBeVisible({ timeout: 5000 });

// Esperar redirección
await expect(page).toHaveURL(/\/mis-pedidos/, { timeout: 15000 });
```

---

## Datos de prueba conocidos

### Transbank Sandbox (Webpay)
| Campo        | Valor                   |
| ------------ | ----------------------- |
| N° Tarjeta   | `4051 8856 0044 6623`   |
| Fecha exp.   | Cualquier fecha futura  |
| CVV          | `123`                   |
| RUT          | `11.111.111-1`          |
| Clave        | `123`                   |

### Categorías del sistema
Perros, Gatos, Alimento, Snacks, Farmacia, Juguetes, Accesorios, Higiene, Camas, Transportes, Ropa, Otros

### Estados de pedido

**Pedidos con delivery (entrega a domicilio):**
`pending` → `processing` → `ready_for_pickup` → `rider_assigned` → `on_the_way` → `delivered` / `cancelled`

**Pedidos con retiro en tienda:**
`pending` → `processing` → `ready_for_pickup` → `delivered` / `cancelled`

| Estado | Label (delivery) | Label (pickup) | Descripción |
|---|---|---|---|
| `pending` | Pendiente | Pendiente | Pedido recibido, pendiente de preparación |
| `processing` | Procesando | Procesando | Tienda preparando el pedido |
| `ready_for_pickup` | Listo para enviar | Listo para retirar | Pedido empacado y listo |
| `rider_assigned` | Asignado a Rider | — | Rider aceptó, pendiente de recoger |
| `on_the_way` / `in_transit` | En camino | — | Rider recogió y va en camino |
| `delivered` | Entregado | Entregado | Entrega/retiro completado |
| `cancelled` | Cancelado | Cancelado | Pedido cancelado |
| `refunded` | Reembolsado | Reembolsado | Dinero devuelto |

**Asignación de rider (solo pedidos con delivery):**
- El admin o vendor asigna un rider desde el panel Delivery (modal con búsqueda y selección)
- El rider también puede auto-asignarse tomando un pedido disponible desde su dashboard
- Al aceptar, el estado cambia automáticamente a `rider_assigned`
- Protección anti-duplicidad: SQL atómico con `WHERE rider_id IS NULL` previene que dos riders tomen el mismo pedido

### Estados de rider
`pending_docs` → `pending_review` → `approved` / `rejected`

### Estados de vendor
`pending` → `active` / `suspended` / `inactive`

### Planes de suscripción
- Básico: $29.990/mes — 10 productos
- Pro: $59.990/mes — 50 productos
- Enterprise: $99.990/mes — productos ilimitados

---

## Reglas de validación por campo y datos de prueba explícitos

> **IMPORTANTE:** El agente QA DEBE usar estos valores exactos en los tests.
> Las reglas vienen del código fuente (`utils/chile.js` + `RegisterPage.jsx` + `RiderRegisterPage.jsx`).

### 1. NOMBRE y APELLIDO (`first_name`, `last_name`)

| Regla | Detalle |
|---|---|
| Regex válido | `/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]{2,}$/` |
| Mínimo | 2 caracteres (solo letras) |
| Caracteres permitidos | Letras (A-Z, a-z), acentos (áéíóúñü), espacios, guiones (-), apóstrofes (') |
| Sanitización automática | Se eliminan números y caracteres especiales al escribir (cliente no verá el número en el input) |
| Placeholder | Nombre: `Juan` / Apellido: `Pérez` |
| Aplica en | Registro cliente, Registro rider, Editar perfil |
| Backend | `sanitize_name()` + `validate_name()` aplican las mismas reglas |

| Escenario | Valor de prueba | Resultado |
|---|---|---|
| ✅ Nombre válido simple | `Juan` | PASS |
| ✅ Nombre con tilde | `María José` | PASS |
| ✅ Nombre con ñ | `Iñaki` | PASS |
| ✅ Nombre compuesto con guión | `Ana-María` | PASS |
| ✅ Nombre con apóstrofe | `O'Brien` | PASS |
| ❌ Nombre vacío | `` (vacío) | FAIL → `Nombre es obligatorio` |
| ❌ Nombre de 1 carácter | `A` | FAIL → `Nombre solo puede contener letras` (menos de 2) |
| ❌ Nombre con números | `Juan123` → se sanitiza a `Juan` | Los números se eliminan automáticamente antes de validar |
| ❌ Nombre con símbolos | `Juan@#$` → se sanitiza a `Juan` | Los símbolos se eliminan automáticamente |

### 2. EMAIL

| Regla | Detalle |
|---|---|
| Validación frontend | Debe contener `@` (validación básica: `!form.email.includes('@')`) |
| Validación HTML5 | `input type="email"` agrega validación del navegador |
| Placeholder | `tu@email.com` |
| Unicidad | Backend valida con `email_exists()` que no exista otro usuario con el mismo email |
| Aplica en | Registro cliente, Registro rider |
| No editable | En perfil el email NO se puede cambiar (campo no aparece en el form de editar perfil) |
| Username auto-generado | Al registrar, el backend genera username del prefijo del email (ej: `user@email.com` → `user`). Si existe, agrega sufijo numérico: `user1`, `user2`, etc. |

| Escenario | Valor de prueba | Resultado |
|---|---|---|
| ✅ Email válido | `testqa@petsgo.cl` | PASS |
| ✅ Email con subdominio | `user@sub.domain.com` | PASS |
| ✅ Email con + (para uniqueness) | `testqa+1234@petsgo.cl` | PASS — útil para tests repetibles |
| ❌ Sin arroba | `textosinrroba` | FAIL → `Email inválido` |
| ❌ Email vacío | `` | FAIL → campo required del HTML5 |
| ❌ Email duplicado | Email ya registrado en BD | FAIL → `El email ya está registrado` (error del backend) |

### 3. RUT CHILENO (`id_number` con `id_type === 'rut'`)

| Regla | Detalle |
|---|---|
| Algoritmo | Módulo 11 — se valida dígito verificador |
| Largo cuerpo | 7–8 dígitos (sin DV) → total 8–9 caracteres limpios |
| Auto-formato | Al escribir, `formatRut()` agrega puntos y guión automáticamente |
| Regex limpieza | Se eliminan todos los caracteres excepto `0-9`, `k`, `K` |
| DV posibles | `0` a `9` o `K` (mayúscula) |
| Placeholder | `12.345.678-K` |
| Feedback visual | Borde verde si válido, borde rojo si inválido (en tiempo real) |
| Texto inline error | `<p>` con "RUT inválido" debajo del input (aparece en tiempo real si DV incorrecto) |
| Aplica en | Registro cliente, Registro rider. Backend valida también en `api_register()` |

**Algoritmo de validación (módulo 11):**
1. Limpiar: quitar puntos y guión → solo dígitos + DV
2. Separar: body (todos menos último char) + dv (último)
3. Sumar: recorrer body de derecha a izquierda, multiplicando por 2,3,4,5,6,7,2,3...
4. Cálculo: `expected = 11 - (sum % 11)`. Si 11 → `0`, si 10 → `K`, sino el número
5. Comparar `expected` con `dv`

| Escenario | Valor de prueba (como se escribe) | Valor formateado | Resultado |
|---|---|---|---|
| ✅ RUT válido | `12345678-5` | `12.345.678-5` | PASS ✅ (DV=5 es correcto) |
| ✅ RUT válido con K | `44444444-K` → verificar DV | Según algoritmo | PASS si DV correcto |
| ✅ RUT corto válido | `6543210-8` → verificar DV | `6.543.210-8` | PASS si DV correcto |
| ❌ RUT con DV incorrecto | `12345678-0` | `12.345.678-0` | FAIL → `RUT inválido` (DV debería ser 5) |
| ❌ RUT muy corto (< 8 digitos limpio) | `12345-6` | `12.345-6` | FAIL → `RUT inválido` |
| ❌ RUT vacío | `` | `` | FAIL → `Documento de identidad es obligatorio` |

**RUTs validados para usar en tests (DV verificado correctamente):**

| RUT | DV | Formato completo | Uso sugerido |
|---|---|---|---|
| `11111111` | `1` | `11.111.111-1` | Test genérico / Transbank sandbox |
| `12345678` | `5` | `12.345.678-5` | Registro cliente |
| `76543210` | `K` | `76.543.210-K` | Registro con DV=K |
| `22222222` | `2` | `22.222.222-2` | Segundo test registro |
| `33333333` | `3` | `33.333.333-3` | Tercer test registro |
| `44444444` | `4` | `44.444.444-4` | Cuarto test registro |

### 4. TELÉFONO

| Regla | Detalle |
|---|---|
| Prefijo fijo | `+569` — visible como `<span>` label fijo al costado izquierdo, NO se escribe |
| Input del usuario | Solo los 8 dígitos restantes |
| Regex válida | `/^\d{8}$/` (exactamente 8 dígitos) |
| Sanitización | Se eliminan todos los no-dígitos; max 8 chars (`formatPhoneDigits()`) |
| Formato enviado al backend | `+569XXXXXXXX` (`buildFullPhone()` junta prefijo + 8 dígitos) |
| Backend normalización | `normalize_phone()` convierte cualquier formato a `+569XXXXXXXX` |
| Placeholder | `XXXXXXXX` |
| maxLength del input | `8` |
| Feedback visual | Borde verde si 8 dígitos, borde rojo si incompleto |
| Aplica en | Registro cliente, Registro rider, Editar perfil |

| Escenario | Valor escrito (sin +569) | Teléfono completo | Resultado |
|---|---|---|---|
| ✅ Teléfono válido | `12345678` | `+56912345678` | PASS |
| ✅ Otro válido | `98765432` | `+56998765432` | PASS |
| ❌ Menos de 8 dígitos | `1234567` (7 dígitos) | — | FAIL → `Debes completar los 8 dígitos del teléfono` |
| ❌ Vacío | `` | — | FAIL → `Debes completar los 8 dígitos del teléfono` |
| ❌ Con letras | `1234abcd` → sanitiza a `1234` | — | FAIL (menos de 8 dígitos) |

**IMPORTANTE para el test:** El input tiene un label fijo `+569` al costado izquierdo (como `<span>` con estilos). El test solo debe escribir 8 dígitos en el `input[type="tel"]`. El selector es:
```js
page.locator('input[type="tel"]').fill('12345678');
```

### 5. CONTRASEÑA

| Regla | Detalle |
|---|---|
| Mínimo longitud | 8 caracteres |
| Requiere mayúscula | Al menos 1 `[A-Z]` |
| Requiere minúscula | Al menos 1 `[a-z]` |
| Requiere número | Al menos 1 `[0-9]` |
| Requiere especial | Al menos 1 `[^A-Za-z0-9]` (ej: `!@#$%^&*`) |
| 5 checks | TODAS deben cumplirse (`passStrength === 5`) |
| Confirmar contraseña | Debe ser idéntica a la contraseña |
| Barra de fuerza | 5 segmentos: rojo (≤2), amarillo (3), verde (4-5) |
| Feedback en tiempo real | 5 checks con ✓/✗: "8+ caracteres", "Mayúscula", "Minúscula", "Número", "Especial (!@#...)" |
| Backend | `validate_password_strength()` aplica las mismas 5 reglas |
| Aplica en | Registro cliente, Registro rider, Reset password, Cambiar contraseña en perfil, Forzar cambio tras creación por admin |

| Escenario | Valor de prueba | Resultado |
|---|---|---|
| ✅ Contraseña fuerte | `Test@2026!` | PASS (8+ chars, mayúscula, minúscula, número, especial) |
| ✅ Otra válida | `PetsGo#99` | PASS |
| ✅ Otra válida | `Qa$Tester1` | PASS |
| ✅ Otra válida | `SecurePass_123` | PASS |
| ❌ Sin mayúscula | `test@2026!` | FAIL → `La contraseña no cumple todos los requisitos` |
| ❌ Sin minúscula | `TEST@2026!` | FAIL |
| ❌ Sin número | `TestPass@!` | FAIL |
| ❌ Sin especial | `TestPass26` | FAIL |
| ❌ Menos de 8 chars | `Te@1!` | FAIL |
| ❌ Confirmación diferente | password=`Test@2026!`, confirm=`Test@2026?` | FAIL → `Las contraseñas no coinciden` |

### 6. REGIÓN y COMUNA

| Regla | Detalle |
|---|---|
| Tipo de campo | `<select>` nativo (dropdown) |
| Región | Obligatoria — 16 regiones de Chile |
| Comuna | Obligatoria — se carga dinámicamente según la región seleccionada |
| Dependencia | Comuna está **deshabilitada** (`disabled` + `opacity: 0.6`) hasta que se seleccione una región |
| Al cambiar región | La comuna se resetea a vacío (`setForm({...prev, region: value, comuna: ''})`) |
| Placeholder región | `Selecciona región...` |
| Placeholder comuna | `Primero selecciona región` (cuando región vacía) / `Selecciona comuna...` (cuando región seleccionada) |
| Aplica en | Registro cliente, Registro rider |

| Escenario | Región → Comuna | Resultado |
|---|---|---|
| ✅ Válido | `Metropolitana` → `Santiago` | PASS |
| ✅ Válido | `Valparaíso` → `Viña del Mar` | PASS |
| ✅ Válido | `Biobío` → `Concepción` | PASS |
| ❌ Sin región | Sin seleccionar | FAIL → `Región es obligatoria` |
| ❌ Sin comuna | Región seleccionada pero comuna vacía | FAIL → `Comuna es obligatoria` |

**Regiones disponibles (en orden del select):**
`Arica y Parinacota`, `Tarapacá`, `Antofagasta`, `Atacama`, `Coquimbo`, `Valparaíso`, `Metropolitana`, `O'Higgins`, `Maule`, `Ñuble`, `Biobío`, `Araucanía`, `Los Ríos`, `Los Lagos`, `Aysén`, `Magallanes`

**Comunas más usadas para tests:**
- Metropolitana → `Santiago`, `Providencia`, `Las Condes`, `Maipú`, `Puente Alto`
- Valparaíso → `Viña del Mar`, `Valparaíso`, `Quilpué`
- Biobío → `Concepción`, `Talcahuano`, `Los Ángeles`

### 7. TIPO DE DOCUMENTO

| Regla | Detalle |
|---|---|
| Tipo campo | `<select>` con 3 opciones |
| Default | `rut` |
| Opciones | `rut`, `dni`, `passport` |
| Comportamiento | Si tipo es `rut`, se activa formateo y validación de RUT chileno. Para `dni` y `passport`, solo se valida que no esté vacío |
| Backend | Si `id_type=rut`, backend también valida con `validate_rut()`. Para otros tipos, valida `min 5 chars` |

| Escenario | Tipo | Valor | Resultado |
|---|---|---|---|
| ✅ RUT válido | `rut` | `12.345.678-5` | PASS |
| ✅ DNI genérico | `dni` | `A12345678` | PASS (solo valida no vacío) |
| ✅ Pasaporte | `passport` | `AB1234567` | PASS |
| ❌ RUT inválido con tipo rut | `rut` | `12.345.678-0` | FAIL → `RUT inválido` |
| ❌ Cualquier tipo vacío | cualquiera | `` | FAIL → `Documento de identidad es obligatorio` |
| ❌ DNI muy corto | `dni` | `AB` | FAIL → backend: `Número de documento demasiado corto` |

### 8. FECHA DE NACIMIENTO

| Regla | Detalle |
|---|---|
| Tipo campo | `input[type="date"]` |
| Obligatorio | NO (es opcional en el form) |
| Máximo | La fecha de hoy (`max={new Date().toISOString().split('T')[0]}`) |
| Formato enviado | `YYYY-MM-DD` |

| Escenario | Valor | Resultado |
|---|---|---|
| ✅ Fecha válida | `1990-05-15` | PASS |
| ✅ Sin fecha (opcional) | vacío | PASS |
| ❌ Fecha futura | `2030-01-01` | Bloqueado por atributo `max` del input |

### 9. TÉRMINOS Y CONDICIONES (TyC)

| Regla | Detalle |
|---|---|
| NO es un checkbox nativo | Es un botón que abre un modal |
| Flujo obligatorio | 1. Click en botón "Leer TyC" → 2. Se abre modal → 3. Scroll hasta el final → 4. Click "He leído y acepto" |
| Estado visual antes | Fondo naranja claro (`#fff7ed`), borde naranja, texto "Debes leer y aceptar..." |
| Estado visual después | Fondo verde claro (`#f0fdf4`), borde verde, ícono check azul, texto "Has aceptado..." |
| Tab del modal | 2 pestañas: "Términos y Condiciones" y "Política de Privacidad" |
| Detección de scroll | El botón de aceptar se habilita cuando `scrollTop + clientHeight >= scrollHeight - 30` |
| Contenido | Se carga de API (`/legal/terminos-y-condiciones` y `/legal/politica-de-privacidad`), con fallback hardcodeado |
| Al cambiar tab | El scroll se resetea a 0 y `reachedBottom` vuelve a `false` — hay que scrollear de nuevo |
| Botón deshabilitado | Color gris `#d1d5db`, texto "↓ Lee hasta el final para aceptar", `cursor: not-allowed` |
| Botón habilitado | Color azul `#00A8E8`, texto "✅ He leído y acepto los Términos y Condiciones" |
| Después de aceptar | El botón "Leer TyC" cambia a "Ver de nuevo" (color azul claro) |
| Aplica en | Registro cliente, Registro rider |

**Secuencia para el test (Playwright):**
```js
// 1. Click en botón "Leer TyC"
await page.getByRole('button', { name: /Leer TyC/i }).click();

// 2. Esperar que el modal cargue
await expect(page.getByText('Términos y Condiciones')).toBeVisible();

// 3. Scroll hasta el final del contenido del modal
const scrollContainer = page.locator('[style*="overflow"][style*="auto"]').last();
await scrollContainer.evaluate(el => el.scrollTop = el.scrollHeight);

// 4. Esperar que el botón se habilite y hacer click
await page.getByRole('button', { name: /He leído y acepto/i }).click();

// 5. Verificar que se aceptó (el modal se cierra, texto cambia)
await expect(page.getByText('Has aceptado')).toBeVisible();
```

| Escenario | Acción | Resultado |
|---|---|---|
| ✅ Acepta TyC completo | Abrir modal → scroll al final → click aceptar | PASS — estado visual cambia a verde |
| ❌ No acepta TyC | No abre el modal | FAIL → `Debes aceptar los Términos y Condiciones` al hacer submit |
| ❌ Scroll incompleto | Abre modal pero no scrollea | Botón "↓ Lee hasta el final" deshabilitado, no se puede aceptar |

### 10. LOGIN (campos)

| Campo | Tipo input | Placeholder | Regla |
|---|---|---|---|
| Usuario/Email | `input[type="text"]` | `tu@email.com o usuario` | Acepta email O nombre de usuario WordPress |
| Contraseña | `input[type="password"]` | `••••••••` | Cualquier string, validado por backend |

**Flujo backend del login (`POST /wp-json/petsgo/v1/auth/login`):**
1. `wp_authenticate($username, $password)` — valida credenciales
2. Verifica `petsgo_user_status`:
   - Si `inactive` → Error: `"Tu cuenta ha sido desactivada"`
   - Si rider con `pending_email` → Redirige a `/verificar-rider?email=...`
   - Si rider con `rejected` → Error: `"Tu solicitud como Rider ha sido rechazada"`
   - Si vendor con status !== `active` → Error: `"Tu tienda se encuentra inactiva"`
   - Si vendor con suscripción vencida → Error: `"Tu suscripción venció"`
3. Genera/recupera token `petsgo_api_token` (formato: `petsgo_` + 64 hex chars)
4. Guarda en `localStorage['petsgo_token']` + `localStorage['petsgo_user']`
5. Modal de éxito visible 1.8 segundos, luego auto-redirección según rol:
   - `subscriber` (cliente) → `/`
   - `administrator` → `/admin`
   - `petsgo_vendor` → `/wp-admin/admin.php?page=petsgo-dashboard`
   - `petsgo_rider` → `/rider`
6. Si `mustChangePassword === true` → Redirige a `/cambiar-contrasena` (prioridad sobre rol)

| Escenario | Email | Password | Resultado |
|---|---|---|---|
| ✅ Login cliente | `process.env.CLIENTE_EMAIL` | `process.env.CLIENTE_PASSWORD` | Token en localStorage, redirección a `/` |
| ✅ Login admin | `process.env.ADMIN_EMAIL` | `process.env.ADMIN_PASSWORD` | Token + redirección a `/admin` |
| ✅ Login vendor | `process.env.VENDOR_EMAIL` | `process.env.VENDOR_PASSWORD` | Token + acceso a `/vendor` |
| ✅ Login rider | `process.env.RIDER_EMAIL` | `process.env.RIDER_PASSWORD` | Token + redirección a `/rider` |
| ❌ Contraseña incorrecta | email válido | `contraseña_mala` | Error mostrado en `div` con fondo rojo |
| ❌ Email no existe | `noexiste@test.cl` | cualquiera | Error de credenciales |
| ❌ Campos vacíos | `` | `` | Validación HTML5 required impide envío |
| ❌ Usuario inactivo | email válido | contraseña correcta | Error: `"Tu cuenta ha sido desactivada"` |

**Respuesta exitosa del login:**
```json
{
  "token": "petsgo_abc123...",
  "user": {
    "id": 123, "username": "user", "email": "user@email.com",
    "displayName": "Juan Pérez", "role": "subscriber",
    "firstName": "Juan", "lastName": "Pérez", "phone": "+56912345678",
    "avatarUrl": "...", "mustChangePassword": false,
    "rider_status": "approved", "vehicle_type": "bicicleta"
  }
}
```

### 11. REGISTRO RIDER (campos adicionales)

| Campo adicional | Detalle |
|---|---|
| Vehículo | `<select>` obligatorio — opciones: `bicicleta` (🚲), `scooter` (🛵 eléctrico), `moto` (🏍️), `auto` (🚗), `a_pie` (🚶) |
| Necesita documentos | `scooter`, `moto`, `auto` requieren subir documentos (licencia + padrón) |
| No necesita docs | `bicicleta`, `a_pie` no requieren documentos de conducir (solo fotos) |
| Verificación email | Paso 2: se envía código de 6 caracteres alfanuméricos (primeros 6 chars del token hex, uppercase) |
| Vigencia código | 48 horas desde generación |
| Reenvío | Endpoint `/auth/resend-rider-verification` + cooldown visual en botón |
| Error código | `"Código inválido o expirado"` |
| Post-verificación | Status cambia a `pending_docs` → rider debe subir documentos desde `/rider` |

**Documentos requeridos por vehículo:**

| Vehículo | Licencia | Padrón | Fotos (3) |
|---|---|---|---|
| 🚲 Bicicleta | ❌ | ❌ | ✅ frontal, lateral, trasera |
| 🛵 Scooter | ✅ | ✅ | ✅ frontal, lateral, trasera |
| 🏍️ Moto | ✅ | ✅ | ✅ frontal, lateral, trasera |
| 🚗 Auto | ✅ | ✅ | ✅ frontal, lateral, trasera |
| 🚶 A pie | ❌ | ❌ | ✅ rider con ID visible |

Todos los demás campos (nombre, apellido, email, contraseña, RUT, teléfono, región, comuna) tienen las **mismas reglas** que el registro de cliente.

### 12. PERFIL DE USUARIO — Edición (`PUT /wp-json/petsgo/v1/profile`)

| Campo | Editable | Regla |
|---|---|---|
| Nombre | ✅ | Mismas reglas que registro (sanitizeName + isValidName) |
| Apellido | ✅ | Mismas reglas que registro |
| Teléfono | ✅ | 8 dígitos, mismas reglas que registro |
| Email | ❌ | NO se puede cambiar desde el perfil |
| RUT | ❌ | NO se puede cambiar desde el perfil |
| Región/Comuna | ❌ | NO se cambian desde el perfil |
| Campos opcionales | Se pueden dejar vacíos — solo se actualiza lo que se envía |

**Cambiar contraseña en perfil (`POST /wp-json/petsgo/v1/profile/change-password`):**

| Campo | Regla |
|---|---|
| Contraseña actual | Obligatoria, verificada con `wp_check_password()` |
| Nueva contraseña | Mismas 5 reglas de fuerza (8+ chars, mayúscula, minúscula, número, especial) |
| Confirmar nueva | Debe coincidir exactamente con nueva contraseña |
| Post-éxito | Se regenera el token API → respuesta incluye nuevo token → hay que actualizar `localStorage` |

| Escenario | Resultado |
|---|---|
| ✅ Contraseña actual correcta + nueva fuerte | `"Contraseña actualizada exitosamente"` + nuevo token |
| ❌ Contraseña actual incorrecta | `"Contraseña actual incorrecta"` |
| ❌ Nueva contraseña débil | `"La nueva contraseña no cumple los requisitos"` |
| ❌ Confirmación no coincide | `"Las contraseñas no coinciden"` |

### 13. RECUPERACIÓN DE CONTRASEÑA

**Paso 1 — Solicitar reset (`POST /wp-json/petsgo/v1/auth/forgot-password`):**
- Input: email válido con `@`
- Backend genera token de 64 hex chars con expiración de **1 hora**
- Token guardado en tabla `petsgo_password_resets` con `used=0`
- Email enviado con link: `{home_url}/reset-password?token={token}`
- Respuesta genérica (seguridad): `"Si el correo existe, recibirás instrucciones..."`

**Paso 2 — Resetear contraseña (`POST /wp-json/petsgo/v1/auth/reset-password`):**
- Token viene por URL param `?token=...`
- Backend verifica: token existe + `expires_at > NOW()` + `used=0`
- Nueva contraseña: mismas 5 reglas de fuerza
- Confirmar nueva: debe coincidir
- Post-éxito: token marcado como `used=1`, flag `petsgo_must_change_password` eliminado

| Escenario | Resultado |
|---|---|
| ✅ Token válido + contraseña fuerte | `"Contraseña restablecida exitosamente"` |
| ❌ Token expirado (> 1 hora) | `"Token inválido o expirado"` |
| ❌ Token ya usado | `"Token inválido o expirado"` |
| ❌ Contraseña débil | `"La contraseña no cumple los requisitos"` |

### 14. MASCOTAS (PETS)

**Endpoints:** `POST /pets` (crear), `PUT /pets/{id}` (editar), `DELETE /pets/{id}` (eliminar), `POST /pets/upload-photo` (foto)

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| name | text | ✅ | No vacío, `sanitize_text_field()` |
| petType | select | ✅ | `perro`, `gato`, `ave`, `conejo`, `hamster`, `pez`, `reptil`, `otro` |
| breed | text | ❌ | Letras y espacios |
| birthDate | date | ❌ | Max: hoy |
| notes | textarea | ❌ | Texto libre |
| photoUrl | URL | ❌ | Subir foto por separado |

**Upload de foto de mascota:**

| Restricción | Valor |
|---|---|
| Formatos | image/jpeg, image/png, image/gif, image/webp |
| Tamaño máximo | 5 MB |
| Dimensiones máx | 2048 x 2048 px |
| Naming | `pet_{user_id}_{timestamp}.jpg` |

### 15. SEGURIDAD — SQL Injection Protection

**Frontend (`checkFormForSqlInjection()` en `utils/chile.js`):**
```
Patrones detectados:
- SQL keywords: SELECT, INSERT, UPDATE, DELETE, DROP, ALTER, CREATE, EXEC, UNION, TRUNCATE
- Comentarios SQL: --, /*, */
- Procedimientos: xp_, sp_
- Hex injection: 0x[hex]
- String manipulation: CHAR(), CONCAT()
- Timing attacks: SLEEP(), BENCHMARK()
- Boolean-based: ' OR '1'='1, ' AND '1'='1
- Info leaks: INFORMATION_SCHEMA, LOAD_FILE(), OUTFILE
```

**Comportamiento:** Se ejecuta ANTES de la validación de campos. Si detecta inyección en CUALQUIER campo, se muestra el error genérico y se detiene el submit.

**Error:** `"El campo contiene caracteres no permitidos. Por favor revisa tus datos."`

**Backend:** Misma protección con `detect_sql_injection()` — doble capa de defensa.

### 16. RATE LIMITING (Protección contra abuso)

| Config | Valor |
|---|---|
| Máximo intentos por IP | 3 |
| Ventana de tiempo | 1200 segundos (20 minutos) |
| Cooldown al exceder | 900 segundos (15 minutos) |
| Tracking | WordPress transients: `petsgo_rl_` + MD5(IP + action) |
| Acciones afectadas | `register`, `register_rider`, `vendor_lead` |

| Escenario | Resultado |
|---|---|
| ✅ Primer registro | PASS |
| ✅ Segundo registro (misma IP) | PASS |
| ✅ Tercer registro (misma IP) | PASS |
| ❌ Cuarto registro (misma IP, < 20 min) | HTTP 429 → `"Demasiadas solicitudes. Por favor espera X minutos e intenta nuevamente."` |

### 17. TOKEN y SESSION MANAGEMENT

**Formato token:** `petsgo_` + 64 caracteres hexadecimales (32 bytes random)

**localStorage keys:**

| Key | Tipo | Contenido |
|---|---|---|
| `petsgo_token` | String | Token Bearer para API |
| `petsgo_user` | JSON | `{id, username, email, displayName, role, firstName, lastName, phone, avatarUrl, mustChangePassword, rider_status, vehicle_type}` |
| `petsgo_nonce` | String | CSRF (legacy) |

**Headers enviados en cada petición autenticada:**
- `Authorization: Bearer {token}`
- `X-PetsGo-Token: {token}` (fallback para servidores Apache que eliminan Authorization)

**Expiración de sesión:**
- En respuesta 401, el frontend intercepta y:
  1. Limpia `localStorage['petsgo_token']`, `localStorage['petsgo_user']`, `localStorage['petsgo_nonce']`
  2. Dispara evento: `window.dispatchEvent(new Event('petsgo:session_expired'))`
  3. Redirige a `/login`

**Regeneración de token:** Ocurre al cambiar contraseña → la respuesta incluye nuevo token → cliente debe actualizar localStorage.

### 18. DATOS DE PRUEBA COMPLETOS PARA REGISTRO EXITOSO

```js
// ✅ Registro cliente PASS — copiar y pegar en tests:
const VALID_CLIENT = {
  first_name: 'María',
  last_name: 'González',
  email: `testqa+${Date.now()}@petsgo.cl`,   // email único con timestamp
  password: 'Test@2026!',
  confirmPassword: 'Test@2026!',
  id_type: 'rut',
  id_number: '12345678-5',                    // RUT válido (DV verificado)
  phone: '12345678',                          // 8 dígitos (se envía como +56912345678)
  birth_date: '1990-05-15',
  region: 'Metropolitana',
  comuna: 'Santiago',
};

// ❌ Registro que debe FALLAR — datos inválidos:
const INVALID_CLIENT = {
  first_name: '',                              // vacío → error
  last_name: 'A',                              // 1 carácter → error
  email: 'sin-arroba',                         // sin @ → error
  password: 'abc',                             // no cumple 5 requisitos → error
  confirmPassword: 'xyz',                      // no coincide → error
  id_type: 'rut',
  id_number: '12345678-0',                     // DV incorrecto → error
  phone: '1234',                               // menos de 8 dígitos → error
  region: '',                                  // vacío → error
  comuna: '',                                  // vacío → error
};

// ✅ Registro rider PASS:
const VALID_RIDER = {
  ...VALID_CLIENT,
  email: `rider+${Date.now()}@petsgo.cl`,
  vehicle: 'bicicleta',                        // No requiere documentos
};
```

**Respuesta exitosa registro cliente:**
```json
{
  "message": "Cuenta creada exitosamente",
  "username": "maria"
}
```
- Post-registro: Modal de éxito con countdown de 3 segundos → redirige a `/login`
- Welcome email enviado al correo registrado

**Respuesta exitosa registro rider:**
```json
{
  "message": "Registro exitoso. Revisa tu correo para verificar tu email y continuar con el proceso.",
  "username": "rider_user",
  "step": 1,
  "next_step": "verify_email"
}
```

### 19. ERRORES ESPERADOS (textos exactos del frontend)

| Condición | Mensaje de error exacto |
|---|---|
| Nombre vacío | `Nombre es obligatorio` |
| Nombre con chars inválidos | `Nombre solo puede contener letras` |
| Apellido vacío | `Apellido es obligatorio` |
| Apellido inválido | `Apellido solo puede contener letras` |
| Email sin @ | `Email inválido` |
| Contraseña débil | `La contraseña no cumple todos los requisitos` |
| Contraseñas no coinciden | `Las contraseñas no coinciden` |
| RUT inválido | `RUT inválido` |
| Doc vacío | `Documento de identidad es obligatorio` |
| Teléfono incompleto | `Debes completar los 8 dígitos del teléfono` |
| Región vacía | `Región es obligatoria` |
| Comuna vacía | `Comuna es obligatoria` |
| TyC no aceptado | `Debes aceptar los Términos y Condiciones` |
| Vehículo no seleccionado (rider) | `Selecciona un vehículo o medio de transporte` |
| SQL injection detectada | `El campo contiene caracteres no permitidos. Por favor revisa tus datos.` |
| Email duplicado (backend) | `El email ya está registrado` |
| Doc muy corto (backend, dni/passport) | `Número de documento demasiado corto` |
| Teléfono inválido (backend) | `Teléfono chileno inválido` |
| Rate limited (backend) | `Demasiadas solicitudes. Por favor espera X minutos e intenta nuevamente.` |
| Cuenta desactivada (login) | `Tu cuenta ha sido desactivada` |
| Rider rechazado (login) | `Tu solicitud como Rider ha sido rechazada` |
| Vendor inactivo (login) | `Tu tienda se encuentra inactiva` |
| Código rider inválido | `Código inválido o expirado` |
| Token reset expirado | `Token inválido o expirado` |
| Contraseña actual incorrecta (perfil) | `Contraseña actual incorrecta` |
| Error genérico de conexión | `Error de conexión. Verifica que WordPress esté activo.` |
| Error genérico registro | `Error al registrar. Intenta nuevamente.` |

> **Nota:** Múltiples errores se concatenan con `. ` (punto y espacio). Ejemplo: `Nombre es obligatorio. Email inválido. RUT inválido`

### 20. UPLOAD DE ARCHIVOS Y DOCUMENTOS

**Fotos de mascota:**

| Propiedad | Valor |
|---|---|
| Formatos | JPEG, PNG, GIF, WebP |
| Tamaño máx | 5 MB |
| Dimensiones | 2048 x 2048 px |
| Ruta | `/wp-content/uploads/petsgo-pets/` |

**Documentos de rider:**

| Tipo documento | Formatos | Tamaño máx | Cuándo |
|---|---|---|---|
| Licencia de conducir | PDF, JPG, PNG | 10 MB | scooter, moto, auto |
| Padrón del vehículo | PDF, JPG, PNG | 10 MB | scooter, moto, auto |
| Fotos del vehículo (3) | JPG, PNG | 5 MB c/u | todos los vehículos |

**Imágenes de productos:**

| Propiedad | Valor |
|---|---|
| Formatos | JPEG, PNG, WebP |
| Tamaño máx | 10 MB |
| Storage | WordPress Media Library |

### 21. CUPÓN DE DESCUENTO

**Endpoint:** `POST /wp-json/petsgo/v1/coupons/validate`

| Campo | Tipo | Regla |
|---|---|---|
| code | text | Alfanumérico, no vacío |

| Escenario | Resultado |
|---|---|
| ✅ Código válido activo | `{ valid: true, discount_type: "percentage", discount_value: 20, ... }` |
| ❌ Código inexistente | `"Código inválido"` |
| ❌ Cupón expirado | `"Cupón expirado"` |
| ❌ Límite de uso alcanzado | `"Límite de uso alcanzado"` |
| ❌ Monto mínimo no alcanzado | `"El monto mínimo para este cupón es $X"` |

---

## Endpoints REST más usados en tests

| Método | Endpoint                                    | Auth  | Descripción                        |
| ------ | ------------------------------------------- | ----- | ---------------------------------- |
| POST   | `/auth/login`                               | No    | Login                              |
| POST   | `/auth/register`                            | No    | Registro cliente                   |
| GET    | `/profile`                                  | Sí    | Perfil del usuario                 |
| GET    | `/products`                                 | No    | Listar productos                   |
| GET    | `/products/:id`                             | No    | Detalle producto                   |
| GET    | `/categories`                               | No    | Listar categorías                  |
| GET    | `/vendors`                                  | No    | Listar tiendas                     |
| POST   | `/orders`                                   | Sí    | Crear pedido                       |
| GET    | `/orders/mine`                              | Sí    | Mis pedidos                        |
| POST   | `/coupons/validate`                         | Sí    | Validar cupón                      |
| POST   | `/reviews`                                  | Sí    | Enviar reseña                      |
| GET    | `/products/:id/reviews`                     | No    | Reseñas de producto                |
| POST   | `/chatbot-send`                             | Opt   | Enviar mensaje al chatbot          |
| GET    | `/chatbot-conversations`                    | Sí    | Listar conversaciones              |
| GET    | `/invoice/validate/:token`                  | No    | Verificar boleta (público)         |
| POST   | `/tickets`                                  | Sí    | Crear ticket soporte               |
| GET    | `/admin/dashboard`                          | Admin | Dashboard admin                    |
| GET    | `/admin/vendors`                            | Admin | Listar vendors (admin)             |
| GET    | `/admin/riders`                             | Admin | Listar riders (admin)              |
| GET    | `/admin/inventory`                          | Admin | Productos PetsGo Oficial           |
| GET    | `/rider/deliveries`                         | Rider | Entregas del rider                 |
| GET    | `/vendor/orders`                            | Vendor| Pedidos del vendor                 |
| GET    | `/public-settings`                          | No    | Config pública del sitio           |

> **Prefijo completo:** `/wp-json/petsgo/v1/{endpoint}`

---

## Comandos útiles

```bash
# Instalar dependencias
npm init -y
npm install -D @playwright/test dotenv
npx playwright install chromium

# Ejecutar todos los tests
npx playwright test

# Ejecutar un archivo específico
npx playwright test tests/autenticacion.spec.js

# Ejecutar un test específico por nombre
npx playwright test -g "AU-040"

# Ejecutar con UI (debug)
npx playwright test --ui

# Ejecutar en modo headed (ver navegador)
npx playwright test --headed

# Ver reporte HTML
npx playwright show-report reporte/

# Ejecutar solo tests de un describe
npx playwright test tests/login.spec.js --grep "QA-01"
```

---

## Flujo de trabajo del agente

### Orden de ejecución recomendado:
1. **Smoke Tests primero** (S-01 a S-10 del QA-00)
2. Si algún smoke falla → PARAR, reportar bug crítico
3. Si todos pasan → ejecutar suite completa en orden:
   - QA-01 → QA-02 → QA-03 → QA-04 (flujo de compra)
   - QA-05 → QA-06 → QA-07 (dashboards)
   - QA-08 → QA-09 (chatbot + mobile)
   - QA-10 → QA-11 (valoraciones + tienda admin)

### Para cada módulo:
1. Leer `Docs/QA/QA-{NN}-{Nombre}.md`
2. Crear `tests/{modulo}.spec.js`
3. Escribir todos los `test()` correspondientes a los casos del .md
4. Ejecutar: `npx playwright test tests/{modulo}.spec.js`
5. Revisar resultados
6. Si hay fallos, capturar evidencia y documentar

### Si un test falla:
- Capturar screenshot antes del error
- Documentar: ID del caso, resultado esperado vs obtenido
- No marcar como "pass" un test que falla — déjalo fallido con evidencia
- Pasar al siguiente test

---

## Casos especiales a tener en cuenta

### Redirección de Vendor
El vendor se redirige a `wp-admin/admin.php?page=petsgo-dashboard` al ir a `/vendor`. Los tests de vendor que interactúan con la UI del dashboard necesitan navegar al WP Admin.

### Rider con restricción de navegación
Los riders logueados son **auto-redirigidos a `/rider`** si intentan navegar por el marketplace. Tener esto en cuenta en tests de seguridad.

### Floating Cart auto-cierre
Al agregar al carrito, el `FloatingCart` se abre automáticamente y se cierra después de ~5 segundos. Si el test necesita interactuar con el carrito, usar `page.goto('/carrito')` directamente.

### Token cleanup en tests
Antes de tests de login, limpiar localStorage:
```js
await page.evaluate(() => {
  localStorage.removeItem('petsgo_token');
  localStorage.removeItem('petsgo_user');
});
```

### Módulos configurables
Algunos módulos pueden estar deshabilitados via toggles: `module_riders`, `module_chatbot`, `module_reviews`, `module_coupons`. Los tests deben verificar si el módulo está activo antes de ejecutarse.

### Toast notifications
La app muestra toasts de éxito/error con `setTimeout`. Esperar 2-3 segundos o usar `waitForSelector` para detectarlos.

### RUT chileno
Para tests de registro, usar un RUT válido con dígito verificador correcto.
Ejemplo: `12.345.678-5` (verificar con algoritmo módulo 11).

---

## Resumen de la suite QA

| Módulo                    | Archivo .md         | Casos | Prioridad Alta |
| ------------------------- | ------------------- | ----- | -------------- |
| Autenticación y Usuarios  | QA-01               | 46    | ~30            |
| Catálogo y Productos      | QA-02               | 44    | ~25            |
| Carrito, Checkout, Pagos  | QA-03               | 43    | ~30            |
| Pedidos y Boletas         | QA-04               | 23    | ~18            |
| Dashboard Vendor          | QA-05               | 44    | ~28            |
| Dashboard Rider           | QA-06               | 38    | ~25            |
| Panel Admin               | QA-07               | 71    | ~45            |
| Chatbot y Soporte         | QA-08               | 44    | ~28            |
| Mobile y Responsivo       | QA-09               | 47    | ~27            |
| Valoraciones y Reseñas    | QA-10               | 50    | ~30            |
| Tienda PetsGo Admin       | QA-11               | 58    | ~35            |
| **TOTAL**                 |                     | **508** |              |
