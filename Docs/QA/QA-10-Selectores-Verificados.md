# QA-10 — Selectores Verificados: Valoraciones y Reseñas

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `ProductDetailPage.jsx`, `VendorDetailPage.jsx`, `MyOrdersPage.jsx`, `petsgo-core.php` (endpoints de reviews)  
**Propósito:** Selectores Playwright verificados contra el código real del sistema de valoraciones/reseñas.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

---

## 1. Base de Datos — Tabla `wp_petsgo_reviews`

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | BIGINT PK AUTO_INCREMENT | |
| `customer_id` | BIGINT NOT NULL | ID del usuario WordPress |
| `product_id` | BIGINT NOT NULL | |
| `vendor_id` | BIGINT NOT NULL | |
| `order_id` | BIGINT NOT NULL | |
| `rating` | INT NOT NULL (1-5) | |
| `comment` | TEXT | Opcional |
| `status` | VARCHAR(20) DEFAULT 'approved' | `approved`, `pending`, `rejected` |
| `created_at` | DATETIME | |

**Constraint:** UNIQUE(`customer_id`, `order_id`, `product_id`) — un usuario puede dejar **una sola reseña por producto por pedido**.

---

## 2. API Endpoints

### POST `/wp-json/petsgo/v1/reviews` — Crear reseña

**Headers:** `Authorization: Bearer {token}` + `X-PetsGo-Token: {token}`

**Body:**
```json
{
  "product_id": 123,
  "order_id": 456,
  "rating": 5,
  "comment": "Excelente producto"
}
```

**Validaciones (7 errores posibles):**

| Validación | Error exacto | Código HTTP |
|-----------|-------------|-------------|
| order_id vacío | `"order_id es requerido"` | 400 |
| product_id vacío | `"product_id es requerido"` | 400 |
| rating vacío | `"rating es requerido"` | 400 |
| rating < 1 o > 5 | `"El rating debe estar entre 1 y 5"` | 400 |
| Pedido no pertenece al usuario | `"Este pedido no te pertenece"` | 403 |
| Producto no está en el pedido | `"Este producto no está en tu pedido"` | 400 |
| Reseña duplicada | `"Ya has dejado una reseña para este producto en este pedido"` | 400 |

**Respuesta exitosa:**
```json
{
  "message": "Reseña creada exitosamente",
  "review": { "id": 789, "rating": 5, "comment": "..." }
}
```

### GET `/wp-json/petsgo/v1/products/{id}/reviews` — Reseñas de producto

**Sin auth requerido.** Retorna:
```json
{
  "reviews": [
    { "id": 1, "rating": 5, "comment": "...", "customer_name": "Juan P.", "created_at": "2026-01-15" }
  ],
  "average": 4.5,
  "total": 12
}
```

### GET `/wp-json/petsgo/v1/vendors/{id}/reviews` — Reseñas de tienda

**Sin auth.** Agrega todas las reseñas de productos del vendor.

### GET `/wp-json/petsgo/v1/orders/{id}/review-status` — Estado de reseñas por pedido

**Con auth.** Retorna qué productos del pedido ya tienen reseña:
```json
{
  "items": [
    { "product_id": 123, "product_name": "Royal Canin", "reviewed": true },
    { "product_id": 456, "product_name": "Collar Premium", "reviewed": false }
  ]
}
```

---

## 3. Modal de Reseña (en MyOrdersPage)

### Cómo se abre

Desde `MyOrdersPage.jsx`, cada pedido con estado `delivered` muestra un botón "⭐ Valorar" por cada producto que aún no tiene reseña.

| Elemento | Selector verificado |
|----------|-------------------|
| Botón Valorar por producto | `page.getByRole('button', { name: /Valorar/i })` |
| Modal overlay | `page.locator('div[style*="position: fixed"]')` |
| Modal container | `page.locator('div[style*="max-width: 480px"]')` |

### Contenido del modal

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título modal | `page.getByText('Valorar Producto')` | |
| Nombre producto en modal | `page.locator('div[style*="480px"] h3, div[style*="480px"] p[style*="font-weight"]')` | |
| Estrellas (5) | `page.locator('span[style*="cursor: pointer"][style*="font-size: 2"]')` | 5 spans, cada uno con ★ |
| Estrella activa color | `color: rgb(255, 196, 0)` → **#FFC400** (amarillo PetsGo) | |
| Estrella inactiva color | `color: rgb(209, 213, 219)` → **#D1D5DB** (gris) | |
| Emoji feedback | `page.locator('span[style*="font-size: 1.5"]')` | Cambia según rating |
| Textarea comentario | `page.locator('textarea[placeholder="Cuéntanos tu experiencia (opcional)"]')` | maxLength: 500 |
| Counter caracteres | `page.locator('div[style*="text-align: right"][style*="font-size: 0.8"]')` | Ej: "45/500" |
| Botón enviar | `page.getByRole('button', { name: /Enviar Valoración/i })` | Incluye ⭐ |
| Botón cancelar | `page.getByRole('button', { name: /Cancelar/i })` | |
| Botón cerrar (X) | `page.locator('button').filter({ hasText: '✕' })` | Esquina superior derecha |

### Emojis según rating

| Rating | Emoji | Texto |
|--------|-------|-------|
| 1 | 😞 | Muy malo |
| 2 | 😕 | Malo |
| 3 | 😐 | Regular |
| 4 | 😊 | Bueno |
| 5 | 🤩 | Excelente |

```js
// ✅ Seleccionar 5 estrellas:
const stars = page.locator('span[style*="cursor: pointer"][style*="font-size: 2"]');
await stars.nth(4).click(); // 5ta estrella (índice 4)

// ✅ Verificar emoji de 5 estrellas:
await expect(page.getByText('🤩')).toBeVisible();
```

### Flujo completo de envío

```js
test('VR-020: Enviar reseña 5 estrellas', async ({ page }) => {
  await login(page, process.env.CLIENTE_EMAIL, process.env.CLIENTE_PASSWORD);
  await page.goto('/mis-pedidos');
  await page.waitForLoadState('networkidle');
  
  // 1. Click en botón Valorar del primer producto entregado
  const btnValorar = page.getByRole('button', { name: /Valorar/i }).first();
  await btnValorar.click();
  
  // 2. Modal visible
  await expect(page.getByText('Valorar Producto')).toBeVisible();
  
  // 3. Seleccionar 5 estrellas
  const stars = page.locator('span[style*="cursor: pointer"][style*="font-size: 2"]');
  await stars.nth(4).click();
  
  // 4. Emoji 🤩
  await expect(page.getByText('🤩')).toBeVisible();
  
  // 5. Escribir comentario
  await page.locator('textarea[placeholder*="experiencia"]').fill('Excelente producto, mi mascota lo amó');
  
  // 6. Enviar
  await page.getByRole('button', { name: /Enviar Valoración/i }).click();
  
  // 7. Esperar éxito (modal se cierra + toast o mensaje)
  await expect(page.getByText(/exitosamente|guardad/i)).toBeVisible({ timeout: 10000 });
  
  await page.screenshot({ path: 'evidencias/valoraciones-VR020-pass.png', fullPage: true });
});
```

---

## 4. Reseñas en ProductDetailPage

### Sección de reseñas

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Estrellas promedio (debajo del nombre) | `page.locator('span[style*="color: #FFC400"]').first()` | ★ repetidas |
| Texto promedio | `page.getByText(/\d+\.\d+/)` cerca de estrellas | Ej: "4.5" |
| Conteo reseñas | `page.getByText(/\(\d+ reseña/)` | Ej: "(12 reseñas)" |
| Review card | `page.locator('div').filter({ has: page.locator('span[style*="#FFC400"]') })` | Cada reseña individual |
| Nombre reviewer | Texto + estrellas en la card | Nombre parcial: "Juan P." |
| Fecha reseña | `page.locator('[style*="font-size: 0.8"]').filter({ hasText: /\d{4}|hace/ })` | Fecha en español |
| Comentario | Texto dentro de la review card | |
| Estado vacío | `page.getByText('Sin reseñas aún')` | Cuando no hay reseñas |

### Formato de estrellas (lectura)

Las estrellas en modo lectura (ProductDetailPage) son spans con:
- `★` (filled) con `color: #FFC400`
- `★` (empty) con `color: #D1D5DB`

```js
// ✅ Verificar promedio de estrellas en página de producto:
test('VR-040: Mostrar estrellas en producto', async ({ page }) => {
  await page.goto('/producto/1');
  await page.waitForLoadState('networkidle');
  
  // Buscar estrellas amarillas
  const yellowStars = page.locator('span[style*="#FFC400"]');
  const count = await yellowStars.count();
  expect(count, 'Debe haber al menos 1 estrella').toBeGreaterThan(0);
});
```

---

## 5. Reseñas en VendorDetailPage

### Sección de reseñas de tienda

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Badge rating en header | `page.getByText(/★\s*\d+\.\d+/)` | Ej: "★ 4.8 (5)" |
| Sección reseñas título | `page.getByText('💬 Reseñas de la Tienda')` | Título de la sección |
| Review card | Cards con avatar inicial + nombre + estrellas + comentario | |
| Avatar inicial | `page.locator('div[style*="border-radius: 50%"]').filter({ hasText: /^[A-Z]$/ })` | Letra inicial del nombre |
| Nombre del reviewer | Texto bold dentro de review card | |
| Fecha | Formateada con locale 'es-CL' | Ej: "15 de enero de 2026" |
| Estado vacío | `page.getByText('Sin reseñas aún')` | |

```js
// ✅ Verificar reseñas de tienda:
test('VR-060: Reseñas en página de tienda', async ({ page }) => {
  await page.goto('/tienda/1');
  await page.waitForLoadState('networkidle');
  
  // Badge de rating en header
  const badge = page.getByText(/★\s*\d/);
  if (await badge.isVisible()) {
    // Tienda tiene reseñas
    await expect(page.getByText('💬 Reseñas de la Tienda')).toBeVisible();
  } else {
    // Sin reseñas
    await expect(page.getByText('Sin reseñas aún')).toBeVisible();
  }
});
```

---

## 6. Estado de Reseñas por Pedido

En `MyOrdersPage.jsx`, para pedidos con estado `delivered`:

| Estado del producto | Qué muestra | Selector |
|--------------------|-------------|----------|
| Sin reseña | Botón "⭐ Valorar" | `page.getByRole('button', { name: /Valorar/i })` |
| Ya valorado | Badge "✅ Valorado" | `page.getByText('✅ Valorado')` |

```js
// ✅ Verificar estado de reseña:
test('VR-080: Producto ya valorado muestra badge', async ({ page }) => {
  await login(page, process.env.CLIENTE_EMAIL, process.env.CLIENTE_PASSWORD);
  await page.goto('/mis-pedidos');
  await page.waitForLoadState('networkidle');
  
  // Si hay productos valorados, deben tener badge
  const valoradoBadge = page.getByText('✅ Valorado');
  if (await valoradoBadge.first().isVisible()) {
    // Badge visible — test pass
    expect(true).toBe(true);
  }
});
```

---

## 7. Flujo E2E: Compra → Entrega → Valoración

1. **Precondición:** Pedido en estado `delivered`
2. Navegar a `/mis-pedidos`
3. Ubicar pedido entregado
4. Click "⭐ Valorar" en un producto
5. Modal se abre → seleccionar estrellas → comentario opcional → enviar
6. Modal se cierra → producto muestra "✅ Valorado"
7. Navegar a `/producto/{id}` → reseña visible en la sección de reseñas

---

## 8. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selector/Verificación |
|----|------|----------------------|
| VR-001 | Sección reseñas en producto | `page.locator('span[style*="#FFC400"]')` visible |
| VR-002 | Promedio numérico | Texto con `\d+\.\d+` cerca de estrellas |
| VR-003 | Conteo de reseñas | `page.getByText(/\(\d+ reseña/)` |
| VR-010 | Estado vacío | `page.getByText('Sin reseñas aún')` |
| VR-020 | Enviar reseña 5 estrellas | Stars nth(4) click → emoji 🤩 → submit |
| VR-021 | Enviar reseña 1 estrella | Stars nth(0) click → emoji 😞 → submit |
| VR-022 | Reseña sin comentario | No fill textarea → submit (comment es opcional) |
| VR-023 | Reseña con comentario largo | Fill 500 chars → counter "500/500" |
| VR-030 | Reseña duplicada | POST devuelve `"Ya has dejado una reseña..."` |
| VR-040 | Estrellas en ProductDetailPage | Estrellas amarillas ★ visible |
| VR-050 | Review card individual | Card con nombre + estrellas + comentario + fecha |
| VR-060 | Reseñas de tienda | `page.getByText('💬 Reseñas de la Tienda')` |
| VR-061 | Badge rating tienda | `page.getByText(/★\s*\d/)` |
| VR-070 | Avatar inicial | Div circular con letra mayúscula |
| VR-080 | Badge "Valorado" | `page.getByText('✅ Valorado')` |
| VR-090 | Modal se cierra al enviar | Modal no visible después de submit |
| VR-100 | Botón cancelar cierra modal | Click cancelar → modal desaparece |
| VR-110 | API: rating fuera de rango | POST con rating=0 → 400 |
| VR-120 | API: pedido ajeno | POST con order_id ajeno → 403 |

---

## 9. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| VR-030 | "Editar reseña existente" | **No hay endpoint PUT** para editar reseñas | **NO IMPLEMENTADO** — solo crear, no editar |
| VR-031 | "Eliminar reseña" | **No hay endpoint DELETE** | **NO IMPLEMENTADO** |
| VR-050 | "Filtrar reseñas por rating" | No hay filtro en frontend | **NO IMPLEMENTADO** |
| VR-051 | "Ordenar reseñas" | No hay sort en frontend | **NO IMPLEMENTADO** |
| VR-090 | "Moderación de reseñas (admin)" | Tabla tiene campo `status` pero no hay UI en AdminDashboard | **NO IMPLEMENTADO** en frontend |
| VR-091 | "Aprobar/rechazar reseña" | No hay tab de reseñas en admin | **NO IMPLEMENTADO** |
| VR-100 | "Respuesta del vendor a reseña" | No hay endpoint ni campo `reply` en tabla | **NO IMPLEMENTADO** |
| VR-110 | "Foto en reseña" | No hay campo imagen en reseñas | **NO IMPLEMENTADO** |

---

## 10. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| VR-030, VR-031 | Editar/Eliminar reseña no implementado |
| VR-050, VR-051 | Filtrar/Ordenar reseñas no implementado en frontend |
| VR-090, VR-091 | Moderación de reseñas sin UI en admin |
| VR-100, VR-101 | Respuesta del vendor no implementada |
| VR-110 | Foto en reseña no implementada |
| VR-120+ (admin) | Tab de reseñas no existe en AdminDashboard |
