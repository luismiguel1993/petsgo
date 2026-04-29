# QA-04 — Selectores Verificados: Pedidos y Boletas

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `MyOrdersPage.jsx`, `InvoiceVerifyPage.jsx`, `petsgo-core.php`  
**Propósito:** Selectores Playwright verificados contra el código real para tests de Pedidos y Boletas.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

---

## 1. MyOrdersPage (`/mis-pedidos`)

### Título y subtítulo

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título página | `page.getByText('🛍️ Mis Compras')` | `<h2>`, fontSize 26px, fontWeight 900 |
| Subtítulo | `page.getByText('Historial y seguimiento de tus compras')` | fontSize 14px, color `#6b7280` |

### Filtros de estado (8 botones)

| Estado | Texto del botón | Selector |
|--------|----------------|----------|
| Todos | `Todos` | `page.getByRole('button', { name: 'Todos' })` |
| Pago Pendiente | `Pago Pendiente` | `page.getByRole('button', { name: 'Pago Pendiente' })` |
| Pendiente | `Pendiente` | `page.getByRole('button', { name: /^Pendiente$/ })` |
| Preparando | `Preparando` | `page.getByRole('button', { name: 'Preparando' })` |
| Listo para enviar | `Listo para enviar` | `page.getByRole('button', { name: 'Listo para enviar' })` |
| En camino | `En camino` | `page.getByRole('button', { name: 'En camino' })` |
| Entregado | `Entregado` | `page.getByRole('button', { name: 'Entregado' })` |
| Cancelado | `Cancelado` | `page.getByRole('button', { name: 'Cancelado' })` |

**Estilos de filtro:**
- Activo: `background: #00A8E8`, `color: #fff`
- Inactivo: `background: #fff`, `color: #6b7280`

```js
// ✅ CORRECTO — filtrar por estado:
await page.getByRole('button', { name: 'Entregado' }).click();
await page.waitForLoadState('networkidle');

// Verificar que el filtro se activó (fondo azul):
const btn = page.getByRole('button', { name: 'Entregado' });
await expect(btn).toHaveCSS('background-color', 'rgb(0, 168, 232)');
```

### Estado vacío

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Sin pedidos (general) | `page.getByText('Aún no tienes compras')` | Con ícono ShoppingBag 48px |
| Sin pedidos (filtro activo) | `page.getByText('No hay pedidos con este estado')` | Aparece cuando hay pedidos pero ninguno en el estado seleccionado |

### Tarjeta de compra (purchase group)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Header compra | `page.getByText(/Compra del \d/)` | "Compra del {fecha formateada}" |
| Info resumen | `page.getByText(/pedidos? · \d+ productos?/)` | "{N} pedidos · {M} productos" |
| Total compra | Color `#00A8E8`, fontWeight 800 | Formato `$XX.XXX` |
| Badge pago | `page.getByText('✅ Pagado')` | Background `#22C55E`, solo si `payment_verified` |
| Botón expandir/colapsar | `page.locator('button').filter({ has: page.locator('svg') })` | Ícono ChevronDown/ChevronUp |
| Ícono ShoppingBag | 42×42px con gradiente azul | Decorativo en header |

### Pedido individual (dentro de compra expandida)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Nombre tienda | Texto bold fontSize 14px | Store icon + nombre |
| Número de pedido | `page.getByText(/Pedido #\d+/)` | fontSize 11px, color `#9ca3af` |
| Badge de estado | Pill con borderRadius 20px | Colores por estado (ver tabla abajo) |
| Items del pedido | Background `#fafafa`, borderRadius 8px | Nombre + "x{qty}" + precio |

**Colores de estado del pedido (badge):**

| Estado API | Texto mostrado | Color fondo | Color texto |
|------------|---------------|-------------|-------------|
| `payment_pending` | `Pago Pendiente` | `#FEF3C7` | `#92400E` |
| `pending` | `Pendiente` | `#FEF3C7` | `#92400E` |
| `preparing` | `Preparando` | `#DBEAFE` | `#1E40AF` |
| `ready_for_pickup` | `Listo para enviar` | `#E0E7FF` | `#3730A3` |
| `in_transit` | `En camino` | `#FFEDD5` | `#9A3412` |
| `delivered` | `Entregado` | `#D1FAE5` | `#065F46` |
| `cancelled` | `Cancelado` | `#FEE2E2` | `#991B1B` |

### Enlace a boleta PDF

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Link boleta | `page.getByText('📄 Boleta PDF')` | Es un `<a>` tag, no botón |
| Alternativa | `page.locator('a').filter({ hasText: 'Boleta PDF' })` | Background `#f0faff`, color `#00A8E8` |

```js
// ✅ CORRECTO — click en boleta PDF:
const boletaLink = page.locator('a').filter({ hasText: 'Boleta PDF' }).first();
await expect(boletaLink).toBeVisible();
// El link abre en nueva pestaña o descarga directamente
```

### Alerta de pedido en camino

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Alerta en camino | `page.getByText('Tu pedido está en camino')` | Background `#fff7ed`, color `#ea580c` |
| Ícono camión | Truck icon lucide-react | Dentro de la alerta |

### Sección de valoración (solo pedidos `delivered`)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Container | Background `#f0fdf4`, border `#dcfce7` | borderRadius 10px |
| Título sección | `page.getByText('⭐ Valorar tu compra')` | fontSize 12px, fontWeight 700, color `#15803d` |
| Botón valorar tienda | `page.getByRole('button', { name: /Valorar Tienda/i })` | Background `#00A8E8`, color `#fff` |
| Badge tienda valorada | `page.getByText('✅ Tienda valorada')` | Background `#dcfce7`, color `#15803d` |
| Botón valorar producto | `page.getByRole('button', { name: /⭐/ })` | Background `#fff`, border `#00A8E8` |
| Badge producto valorado | `page.getByText(/✅/)` | Background `#dcfce7` |

---

## 2. Modal de Valoración (Review Modal)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Backdrop | Fixed, inset 0, z-index 9999 | Background `rgba(0,0,0,0.5)` con blur |
| Container modal | Max-width 480px, borderRadius 20px | Background `#fff`, padding 28px |
| Botón cerrar (X) | Top 14px, right 14px | Color `#9ca3af`, lucide X icon |
| Título producto | `page.getByText('Valorar Producto')` | fontSize 18px, fontWeight bold |
| Título tienda | `page.getByText('Valorar Tienda')` | fontSize 18px, fontWeight bold |
| Estrellas (5 botones) | Cada estrella es un botón con ícono Star | size 32px, gap 8px |
| Estrella llena | `fill="#FFC400"`, `color="#FFC400"` | Amarilla |
| Estrella vacía | `fill="none"`, `color="#d1d5db"` | Gris |
| Emoji feedback | Textos: `😞 Muy malo`, `😕 Malo`, `😐 Regular`, `😊 Bueno`, `🤩 Excelente` | Según rating 1-5 |
| Textarea comentario | `page.locator('textarea')` | placeholder: "Cuéntanos tu experiencia (opcional)", rows 3 |
| Botón enviar | `page.getByRole('button', { name: /Enviar Valoración/i })` | Gradiente azul `#00A8E8 → #0077b6` |
| Botón enviando | `page.getByText('Enviando...')` | Gris, deshabilitado |

```js
// ✅ Flujo completo de valoración:
// 1. Click en valorar producto
await page.getByRole('button', { name: /⭐/ }).first().click();

// 2. Seleccionar 4 estrellas (click en la 4ta estrella)
const stars = page.locator('[style*="cursor: pointer"]').filter({ has: page.locator('svg') });
await stars.nth(3).click(); // 0-indexed, 4ta estrella

// 3. Escribir comentario
await page.locator('textarea').fill('Excelente calidad');

// 4. Enviar
await page.getByRole('button', { name: /Enviar Valoración/i }).click();

// 5. Verificar éxito
await expect(page.getByText('✅')).toBeVisible();
```

---

## 3. InvoiceVerifyPage (`/verificar-boleta/:token`)

> **NOTA:** Esta página usa **Tailwind CSS** (no inline styles como la mayoría de páginas).

### Estado: Cargando

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Spinner | `page.locator('.animate-spin')` | Loader2 de lucide-react |
| Texto cargando | `page.getByText('Verificando boleta…')` | Tailwind `text-gray-500 text-lg` |

### Estado: Error (token inválido)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Ícono error | XCircle rojo, `w-16 h-16 text-red-500` | lucide-react |
| Título error | `page.getByText('Boleta no válida')` | `text-2xl font-bold text-gray-800` |
| Mensaje error | Texto dinámico del API | `text-gray-500` |
| Link inicio | `page.getByText('Ir al inicio')` | Link a `/` |

### Estado: Éxito (token válido)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Header gradiente | `bg-gradient-to-r from-[#00B8D9] to-[#00D9A5]` | Con ícono ShieldCheck |
| Título | `page.getByText('Boleta Verificada')` | `text-2xl font-bold text-white` |
| N° Boleta | `page.getByText('N° Boleta')` | En card de datos |
| Tienda | `page.getByText('Tienda')` | store_name + RUT |
| Cliente | `page.getByText('Cliente')` | customer_name |
| Total | `page.getByText('Total')` | Formato `$XX.XXX` |
| Fecha | `page.getByText('Fecha')` | "Emitida: {fecha}" |

**Badge de estado de la boleta:**

| Estado | Texto | Color Tailwind |
|--------|-------|---------------|
| `paid` | `Pagado` | `bg-green-100 text-green-800` |
| `pending` | `Pendiente` | `bg-yellow-100 text-yellow-800` |
| `shipped` | `Enviado` | `bg-blue-100 text-blue-800` |
| `delivered` | `Entregado` | `bg-green-100 text-green-700` |
| `cancelled` | `Cancelado` | `bg-red-100 text-red-800` |

### Botones de acción

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Descargar boleta | `page.getByRole('button', { name: /Descargar Boleta PDF/i })` | `bg-emerald-500 text-white` |
| Descargando | `page.getByText('Descargando...')` | Con spinner, deshabilitado |
| Contador descargas | `page.getByText(/descargada \d+ vez/)` | "Esta boleta ha sido descargada {N} vez/veces" |
| Badge autenticidad | `page.getByText(/documento es auténtico/)` | "Este documento es auténtico y fue emitido por PetsGo." |
| Ir a PetsGo | `page.getByText('Ir a PetsGo')` | Background `#00B8D9`, link a `/` |

---

## 4. API Endpoints utilizados

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/orders/mine` | Sí | Lista pedidos del cliente con agrupación por `purchase_group` |
| GET | `/orders/{id}/review-status` | Sí | Estado de reseñas enviadas para un pedido |
| POST | `/reviews` | Sí | Enviar valoración de producto o tienda |
| GET | `/invoice/validate/{token}` | No | Verificar boleta (público) |
| GET | `/invoice/download/{token}` | No | Descargar PDF de boleta |

---

## 5. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selectores principales |
|----|------|----------------------|
| PB-001 | Ver lista de pedidos | `page.getByText('🛍️ Mis Compras')`, filtro "Todos", tarjetas de compra |
| PB-002 | Ver detalle de pedido | Click en chevron para expandir, items con `#fafafa` bg |
| PB-003 | Filtrar por estado | Botones de filtro (8), verificar CSS `background` activo |
| PB-004 | Estados visibles en español | Badges de estado con colores (ver tabla arriba) |
| PB-006 | Pedido cancelado | Badge `Cancelado` bg `#FEE2E2` |
| PB-060-065 | Boletas | Link `📄 Boleta PDF` en pedido |
| PB-080 | Descargar boleta | `page.locator('a').filter({ hasText: 'Boleta PDF' })` |
| PB-100 | Verificar boleta válida | `page.getByText('Boleta Verificada')`, ShieldCheck verde |
| PB-101 | Verificar token inválido | `page.getByText('Boleta no válida')`, XCircle rojo |
| PB-103 | Verificación sin login | Página es pública, no requiere auth |
| PB-105 | JSON del API | `GET /invoice/validate/{token}` directo |
| PB-120 | Estado de entrega | Alerta `Tu pedido está en camino` bg `#fff7ed` |

---

## 6. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| PB-002 | "Click en pedido de la lista" | Las compras se expanden con botón chevron, no hay página de detalle separada | Test debe hacer click en el botón de expandir |
| PB-005 | "Acceder con ID de pedido de otro cliente" | No hay URL de detalle individual; `/mis-pedidos` solo muestra pedidos propios via API | Test de seguridad debe hacerse via API directamente |
| PB-024 | "Historial/timeline de estados" | No hay timeline visual en la UI actual | **NO IMPLEMENTADO** — marcar como skip |
| PB-040-044 | "Panel Admin → Pedidos" | Tab de Pedidos **NO EXISTE** en AdminDashboard.jsx | **NO IMPLEMENTADO** — marcar como skip |
| PB-044 | "Exportar CSV de pedidos" | No hay función de exportar en el código | **NO IMPLEMENTADO** — marcar como skip |
| PB-120-123 | "Seguimiento de envío" | Solo muestra alerta "en camino", no hay tracking detallado | Test limitado a verificar la alerta |

---

## 7. Tests que deben ser SKIP

| ID | Razón |
|----|-------|
| PB-005 | Solo verificable via API (no hay URL de detalle individual) |
| PB-024 | Timeline de estados no implementado en UI |
| PB-040 a PB-044 | Tab "Pedidos" no existe en AdminDashboard |
| PB-044 | Exportar CSV no implementado |
| PB-061-064 | Verificación de contenido PDF requiere herramientas externas |
| PB-104 | QR con smartphone — test manual |
| PB-122 | Notificación por email — requiere acceso a bandeja |
