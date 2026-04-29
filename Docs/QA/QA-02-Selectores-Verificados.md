# Selectores Verificados de PetsGO — Guía Completa para el Agente QA

**Proyecto:** PetsGO — Marketplace multi-vendor de mascotas  
**Fecha:** 2026-03-21  
**Propósito:** Reemplazar los selectores genéricos del CLAUDE.md con selectores **verificados directamente del código fuente JSX**. Usar este documento como referencia obligatoria al escribir o corregir tests Playwright.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad** porque fue verificado contra el código fuente real.

---

## 1. Header (Global — `Header.jsx`)

### Buscador global

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Input de búsqueda | `page.locator('input[placeholder="¿Qué estás buscando?"]')` | `type="text"`, border cambia a rojo si error |
| Botón buscar (ícono lupa) | `page.locator('button').filter({ has: page.locator('svg') }).last()` | Background `#00A8E8`, posición absoluta dentro del input |
| Error búsqueda vacía | `page.getByText('⚠ Escribe algo para buscar')` | Aparece 2 segundos, color `#ff6b6b` |

**Comportamiento de búsqueda vacía:**
```js
// El handler previene navegación si el campo está vacío:
// if (q.trim()) → navega a /categoria/Todos?q={q}
// else → muestra error "⚠ Escribe algo para buscar" por 2 segundos

// CORRECTO — usar el input del header:
await page.locator('input[placeholder="¿Qué estás buscando?"]').fill('');
await page.keyboard.press('Enter');
await expect(page.getByText('Escribe algo para buscar')).toBeVisible();

// ❌ INCORRECTO — NO navegar directo a la URL (bypasea la validación):
// await page.goto('/categoria/Todos?q=');  // ESTO NO TESTEA NADA
```

### Carrito

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón carrito | `page.getByText('Carrito')` | Contiene ícono ShoppingCart + texto "Carrito" |
| Badge cantidad | `page.locator('span').filter({ hasText: /^\\d+$/ })` | Background `#FFC400` (amarillo), 20x20px, border-radius 50% |

### Menú usuario (logueado)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Trigger del menú | `page.locator('header button').filter({ has: page.locator('span[style*="border-radius: 50%"]') })` | Botón con gradiente azul, muestra inicial del nombre |
| Mi Perfil | `page.getByText('Mi Perfil')` | Link a `/perfil` |
| Soporte | `page.getByText('Soporte')` | Link a `/soporte` |
| Cerrar Sesión | `page.getByText('Cerrar Sesión')` | Color `#ef4444` (rojo) |

### Menú hamburguesa (mobile < 768px)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón hamburguesa | `page.locator('button').filter({ has: page.locator('svg') }).first()` | Ícono Menu / X de lucide-react |

---

## 2. HomePage (`/` — `HomePage.jsx`)

### Hero / Banner

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título principal | `page.locator('h1')` | **Es `<h1>`**, texto: "Cuidamos a los que **más amas.**" |
| Subtítulo amarillo | `page.getByText('DESPACHO GRATIS EN TU PRIMER PEDIDO')` | Span con background `#FFC400` |
| CTA principal | `page.getByText('COMPRAR AHORA')` o `page.getByText('VER TIENDAS')` | Links dentro del hero |

### Productos Destacados

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título sección | `page.getByText('Productos Destacados')` | `<h2>`, fontSize 2.25rem, fontWeight 900 |
| Subtítulo | `page.getByText('Los favoritos de nuestras mascotas')` | |
| Grid de productos | Cards dentro de grid CSS responsive | Misma estructura que CategoryPage |

### Categorías rápidas

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Link "CATEGORÍAS" | `page.getByText('CATEGORÍAS')` | Con ícono Filter + ChevronRight |
| Card categoría | `page.getByText('Perros')`, `page.getByText('Gatos')`, etc. | Cada card: emoji 40px + nombre bold + descripción gris |
| Navega a | `/categoria/{nombre}` | |

---

## 3. CategoryPage (`/categoria/:slug` — `CategoryPage.jsx`)

### Select de ordenamiento

**Opciones exactas (value → label):**

| value | Label exacto | Caso de test |
|-------|-------------|--------------|
| `default` | `Relevancia` | Valor por defecto |
| `price_asc` | `Precio: menor a mayor` | CA-023 |
| `price_desc` | `Precio: mayor a menor` | CA-024 |
| `name_asc` | `Nombre A-Z` | CA-025 |
| `discount` | `Mayor descuento` | — |

```js
// ✅ CORRECTO — seleccionar por value:
const sortSelect = page.locator('select').filter({ has: page.locator('option[value="price_asc"]') });
await sortSelect.selectOption('price_asc');

// ✅ TAMBIÉN CORRECTO — por label exacto:
await sortSelect.selectOption({ label: 'Precio: menor a mayor' });

// ❌ INCORRECTO — textos que NO existen:
// 'Menor a Mayor', 'Menor precio', 'Precio: Menor a Mayor' (mayúscula en "Menor")
```

### Filtro de precio (slider)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Slider rango | `page.locator('input[type="range"]')` | **Dual range inputs**, min=0, max=priceMax, step=1000 |
| Texto del rango | `page.getByText(/Rango de precio/)` | Muestra `formatPrice(min) — formatPrice(max)` |

```js
// Cambiar precio máximo:
await page.locator('input[type="range"]').last().fill('30000');
```

### Paginación

**NO usa botones con texto "siguiente"/"anterior". Usa íconos SVG.**

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botones numéricos | `page.locator('button').filter({ hasText: /^\\d+$/ })` | 1, 2, 3... con "..." para ellipsis |
| Botón anterior | Primer botón con ícono `ChevronLeft` | `disabled` cuando page=1 |
| Botón siguiente | Último botón con ícono `ChevronRight` | `disabled` cuando page=totalPages |
| Total info | `page.getByText(/total/)` | Ej: "24 total" |

```js
// ✅ CORRECTO — click en página 2:
await page.locator('button').filter({ hasText: '2' }).click();

// ✅ CORRECTO — click siguiente (ícono, no texto):
const paginationBtns = page.locator('button').filter({ has: page.locator('svg') });
await paginationBtns.last().click(); // ChevronRight es el último

// ❌ INCORRECTO:
// page.getByText('siguiente')  → NO EXISTE
// page.getByText('anterior')   → NO EXISTE
```

> Items por página: `PRODUCTS_PER_PAGE = 12`. La paginación solo aparece si hay >12 productos.

### Buscador dentro de categoría

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Input búsqueda | `page.locator('input[placeholder="Buscar productos..."]')` | type="text", siempre visible |
| Ícono lupa | Lucide `<Search size={18}>` posicionado absoluto | |

### Tarjeta de producto

```js
// Cada producto es un <Link> → <div> con borderRadius 18px
// Selectores útiles:
const productCards = page.locator('a[href*="/producto/"]');
const firstCard = productCards.first();

// Nombre del producto (dentro de la card):
const productName = firstCard.locator('h4'); // fontWeight 700, fontSize 14px

// Precio:
const productPrice = firstCard.locator('span').filter({ hasText: /^\$/ });

// Badge descuento (si existe):
const discountBadge = firstCard.locator('span').filter({ hasText: /-%/ });
```

---

## 4. ProductDetailPage (`/producto/:id` — `ProductDetailPage.jsx`)

### Galería de imágenes

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Imagen principal | `page.locator('img[style*="object-fit"]').first()` | Cambia con `setSelectedImage(i)` |
| Thumbnails | `page.locator('button[style*="72px"]')` | Cada uno 72x72px, border cambia al seleccionar |
| Thumbnail activo | Border `3px solid #00A8E8` | |
| Thumbnail inactivo | Border `2px solid #e5e7eb` | |

```js
// Click en segundo thumbnail:
await page.locator('button[style*="72px"]').nth(1).click();
```

### Spinner de cantidad (+/-)

**⚠️ NO es `input[type=number]`. Es custom con buttons + span.**

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Contenedor | `page.locator('div[style*="f0f9ff"]')` | Background `#f0f9ff`, borderRadius 14px |
| Botón menos (−) | `page.locator('div[style*="f0f9ff"] button').first()` | 44x44px, contiene ícono `Minus` |
| Botón más (+) | `page.locator('div[style*="f0f9ff"] button').last()` | 44x44px, background `#00A8E8` (o `#e5e7eb` si stock agotado) |
| Valor cantidad | `page.locator('div[style*="f0f9ff"] span')` | fontSize 18px, fontWeight 700 |

```js
// ✅ CORRECTO:
const qtyContainer = page.locator('div[style*="f0f9ff"]');
const minusBtn = qtyContainer.locator('button').first();
const plusBtn = qtyContainer.locator('button').last();
const qtyDisplay = qtyContainer.locator('span');

// Verificar que no baja de 1:
await minusBtn.click();
const qty = await qtyDisplay.textContent();
expect(Number(qty)).toBeGreaterThanOrEqual(1);

// Aumentar cantidad:
await plusBtn.click();
expect(Number(await qtyDisplay.textContent())).toBe(2);

// ❌ INCORRECTO:
// page.locator('input[type="number"]')  → NO EXISTE
```

### Botón "Agregar al carrito"

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Con stock | `page.getByText('Agregar al carrito')` | Contiene ícono ShoppingCart |
| Sin stock | `page.getByText('Sin stock')` | Botón disabled, background `#e5e7eb` |

### Selector de variantes

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botones de variante | `page.locator('button[style*="border"]').filter({ hasText: /S|M|L|XL|\\d+\\s*kg/i })` | Cada opción es un `<button>` |
| Variante activa | Border `2px solid #00A8E8`, background `#f0f9ff` | |
| Variante inactiva | Border `2px solid #e5e7eb`, background `#fff` | |

### "Ver más" / "Ver menos" (descripción)

**Solo se renderiza si la descripción tiene >180 caracteres.**

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón expandir | `page.getByText('▼ Ver más')` | color `#00A8E8`, fontSize 13px, fontWeight 700 |
| Botón colapsar | `page.getByText('▲ Ver menos')` | Mismo estilo |

```js
// ⚠️ PRECONDICIÓN: el producto de prueba DEBE tener descripción > 180 caracteres
// Si la descripción es corta, el botón NO se renderiza y el test fallará

const verMasBtn = page.getByText('▼ Ver más');
if (await verMasBtn.isVisible({ timeout: 3000 })) {
  await verMasBtn.click();
  await expect(page.getByText('▲ Ver menos')).toBeVisible();
}
```

### "También te puede gustar" (productos relacionados)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título sección | `page.getByText('También te puede gustar')` | `<h3>` con prefijo emoji "🐾" |
| Grid de productos | Máx. 6 productos de la misma categoría | Grid CSS responsive, minmax(240px, 1fr) |

```js
// Esperar que la sección cargue (depende de API):
await expect(page.getByText('También te puede gustar')).toBeVisible({ timeout: 10000 });
```

> ⚠️ **Si falla:** La sección carga productos de la misma categoría vía API. Si la categoría tiene pocos productos o la API no responde a tiempo, no se muestra. Usar un producto de una categoría populada (ej: "Alimento").

### Producto inactivo / no encontrado

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Emoji | `page.getByText('🚫')` | fontSize 64px |
| Título | `page.getByText('Producto no disponible')` | `<h2>` |
| Mensaje | `page.getByText('Este producto no está disponible actualmente')` | |
| Link volver | `page.getByText('Volver al Inicio')` | Link a `/` con ícono ArrowLeft |

> 🐛 **Bug conocido:** Solo se activa con status HTTP 403 (producto inactivo en BD). Si el slug no existe en la BD, la API puede devolver otro código y el frontend queda en blanco.

---

## 5. VendorDetailPage (`/tienda/:id` — `VendorDetailPage.jsx`)

### Filtro de precio

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Slider | `page.locator('input[type="range"]')` | **Nativo HTML**, single range (no dual). `accentColor: #00A8E8` |
| Texto rango | Muestra `Precio: {min} — {max}` | |

```js
// ✅ CORRECTO:
await page.locator('input[type="range"]').fill('20000');

// ❌ INCORRECTO — no es componente custom
```

### Sort select

Mismas opciones que CategoryPage: `default`, `price_asc`, `price_desc`, `name_asc`, `discount`.

---

## 6. VendorDashboard (`/vendor` — `VendorDashboard.jsx`)

### Tabs

| Tab | Texto exacto | Ícono |
|-----|-------------|-------|
| 1 | `Dashboard` | BarChart3 |
| 2 | `Inventario` | Package |
| 3 | `Pedidos` | ShoppingBag |
| 4 | `Cupones` | Tag |

```js
await page.getByText('Inventario').click(); // Ir a tab de productos
```

### Botón agregar producto

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón | `page.getByRole('button', { name: /Agregar Producto/i })` | **NO dice "Nuevo Producto"**. Tiene ícono Plus |
| Modal título (crear) | `page.getByText('Nuevo Producto')` | Aparece después del click |
| Modal título (editar) | `page.getByText('Editar Producto')` | |

```js
// ✅ CORRECTO:
await page.getByText('Inventario').click();
await page.getByRole('button', { name: /Agregar Producto/i }).click();
await expect(page.getByText('Nuevo Producto')).toBeVisible();

// ❌ INCORRECTO:
// page.getByText('Nuevo Producto').click()  → es el título del modal, no el botón
```

### Formulario de producto (campos)

| Campo | Selector | Notas |
|-------|---------|-------|
| Nombre | `page.locator('input[placeholder*="Royal Canin"]')` | required |
| Descripción | `page.locator('textarea')` | 2 rows |
| Precio | `page.locator('input[type="number"]').first()` | |
| Stock | `page.locator('input[type="number"]').nth(1)` | |
| Categoría | `page.locator('select').filter({ has: page.locator('option') })` | Opciones dinámicas desde API |

### Gestión de pedidos (tab Pedidos)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Avanzar estado | `page.getByText('Avanzar')` | Cambia status al siguiente paso |
| Estado del pedido | Badge con colores según status | |

---

## 7. AdminDashboard (`/admin` — `AdminDashboard.jsx`)

### Tabs

| Tab | Texto exacto | Ícono |
|-----|-------------|-------|
| 1 | `Dashboard Global` | BarChart3 |
| 2 | `Tiendas` | Store |
| 3 | `Tienda PetsGo` | ShoppingBag |
| 4 | `Riders` | Truck |

```js
// Tabs activo: bg-[#2F3A40] text-[#FFC400]
// Tab inactivo: bg-white text-gray-500

await page.getByText('Tienda PetsGo').click(); // Tab de inventario oficial
await page.getByText('Tiendas').click();        // Gestión de vendors
await page.getByText('Riders').click();         // Gestión de riders
```

> ⚠️ **Las categorías se gestionan dentro del tab "Tienda PetsGo"**, no como sección separada. El select de categorías se puebla dinámicamente desde la API.

### Tab Tiendas (vendors)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Tabla | `page.locator('table')` | Headers: Tienda, RUT, Estado, % Venta, % Delivery, Acciones |
| Filas | `page.locator('table tbody tr')` | |
| Badge estado | `page.locator('[style*="background"]').filter({ hasText: /activo|pendiente|suspendido/i })` | Verde=activo, Amarillo=pendiente, Rojo=suspendido |

### Tab Riders

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Tabla | `page.locator('table')` | Nombre, Email, Vehículo, Estado, Rating, Entregas, Ingresos |
| Estados rider | `pending_docs`, `pending_review`, `approved`, `rejected` | |

---

## 8. CartPage (`/carrito` — `CartPage.jsx`)

### Items del carrito

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Item card | `page.locator('div[style*="borderRadius: 16px"]').filter({ has: page.locator('img') })` | Cada item: imagen 90x90, nombre, tienda, precio, controles qty |
| Nombre producto | `page.locator('h3')` dentro del item | fontSize 15px, fontWeight 600 |
| Precio | `page.locator('[style*="color: #00A8E8"]').filter({ hasText: /\\$/ })` | fontSize 20px, fontWeight 700 |

### Controles de cantidad (en carrito)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Contenedor qty | `page.locator('div[style*="f9fafb"]')` | Background `#f9fafb`, border-radius 12px |
| Botón − | Primer `<button>` dentro del contenedor | 36x36px (diferente al detalle que es 44x44) |
| Valor | `<span>` central | fontWeight 700, width 36px |
| Botón + | Segundo `<button>` dentro del contenedor | 36x36px |
| Botón eliminar | `page.locator('button').filter({ has: page.locator('svg') })` con ícono Trash2 | color `#d1d5db` |

### Cupón

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Input código | `page.locator('input[placeholder="Código del cupón"]')` | textTransform uppercase, letterSpacing 1px |
| Botón aplicar | `page.getByRole('button', { name: /Aplicar/ })` | disabled si input vacío |
| Error cupón | `page.getByText(/Cupón inválido/)` | |

### Método de entrega

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Despacho | `page.getByRole('button', { name: /Despacho/ })` | Ícono Truck, border azul `#00A8E8` cuando activo |
| Retiro en tienda | `page.getByRole('button', { name: /Retiro/ })` | Ícono Store, border verde `#22C55E` cuando activo |
| Mensaje retiro gratis | `page.getByText('Retiro gratis')` | Aparece al seleccionar retiro |

### Dirección de envío (cuando despacho seleccionado)

| Campo | Selector verificado | Notas |
|-------|-------------------|-------|
| Tipo dirección | `page.getByRole('button', { name: /Casa|Depto|Oficina/ })` | Tres botones toggle |
| Región | `page.locator('select').filter({ has: page.locator('option[value="Metropolitana"]') })` | |
| Comuna | `page.locator('select').nth(1)` | Se habilita tras seleccionar región |
| Calle y número | `page.locator('input[placeholder*="Calle y número"]')` | Con autocomplete Nominatim |
| Detalle (depto/piso) | `page.locator('input[placeholder*="Torre"]')` o `page.locator('input[placeholder*="Piso"]')` | |

### Botón pagar / confirmar

| Estado | Selector verificado | Texto |
|--------|-------------------|-------|
| Procesando | `page.getByText('⏳ Procesando...')` | disabled |
| Modo prueba | `page.getByText('🧪 Confirmar (Modo Prueba)')` | Solo usuarios test |
| Retiro | `page.getByText('🏪 Confirmar Retiro')` | Cuando pickup seleccionado |
| Pagar normal | `page.getByText('💳 Pagar y Confirmar')` | Con despacho |

```js
// Selector genérico que cubre todos los estados:
await page.getByRole('button', { name: /Confirmar|Pagar|Procesando/ }).click();
```

---

## 9. MyOrdersPage (`/mis-pedidos` — `MyOrdersPage.jsx`)

### Estados de pedido (colores y textos)

| Status | Label | Color | Background |
|--------|-------|-------|-----------|
| `pending` | `Pendiente` | `#FFC400` | `#fff8e1` |
| `payment_pending` | `Pago Pendiente` | `#FFC400` | `#fff8e1` |
| `preparing` | `Preparando` | `#00A8E8` | — |
| `ready_for_pickup` | `Listo para enviar` | `#8B5CF6` | — |
| `in_transit` | `En camino` | `#F97316` | — |
| `delivered` | `Entregado` | `#22C55E` | — |
| `cancelled` | `Cancelado` | `#EF4444` | — |

### Lista de pedidos

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Card de compra | Div con padding 20px, fondo gradiente `#f8fafc → #f0f9ff` | Clickeable para expandir |
| Fecha compra | `page.getByText(/Compra del/)` | fontWeight 800, fontSize 16px |
| Total | `page.locator('[style*="color: #00A8E8"]').filter({ hasText: /\\$/ })` | fontWeight 800, fontSize 18px |
| Badge "Pagado" | `page.getByText('✅ Pagado')` | color `#22C55E` |
| Expand/collapse | Ícono ChevronUp / ChevronDown | |

### Dentro de una compra expandida

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| ID pedido | `page.getByText(/Pedido #\\d+/)` | |
| Badge estado | Ver tabla de colores arriba | |
| Boleta PDF | `page.getByText('Boleta PDF')` | Link de descarga |
| En camino | `page.getByText('Tu pedido está en camino')` | Solo si `in_transit` |

### Modal de valoración

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón valorar tienda | `page.getByRole('button', { name: /Valorar Tienda/ })` | |
| Botón valorar producto | `page.getByRole('button', { name: /Valorar/ })` | |
| Estrellas (1-5) | Botones con ícono Star | |
| Feedback emocional | `page.getByText(/😞|😕|😐|😊|🤩/)` | Cambia según estrellas |
| Textarea comentario | `page.locator('textarea')` | Placeholder "Cuéntanos tu experiencia" |
| Enviar | `page.getByRole('button', { name: /Enviar Valoración/ })` | |

---

## 10. Datos de prueba recomendados para evitar falsos negativos

### Productos que deben existir en producción para tests específicos

| Test | Precondición | Producto recomendado |
|------|-------------|---------------------|
| CA-041 | Producto con **múltiples imágenes** | Buscar en categoría "Alimento" o "Accesorios" |
| CA-042 | Producto con **variantes** (talla/peso) | Buscar producto con selector de opciones |
| CA-043 | Producto con **variante stock=0** | Si no existe → `test.skip(true, 'No hay producto con variante agotada')` |
| CA-046 | Categoría con **≥6 productos** | Usar categoría "Alimento" (la más poblada) |
| CA-048 | Producto con **descripción >180 chars** | Verificar antes: `const desc = await page.locator('.description').textContent(); if (desc.length <= 180) test.skip();` |
| CA-049 | Producto con **descuento** (precio oferta) | Buscar badge con `-%` |

### Estrategia para encontrar productos válidos

```js
// Helper: encontrar un producto que cumpla la precondición
async function findProductWithImages(page) {
  await page.goto('/categoria/Alimento');
  await page.waitForLoadState('networkidle');
  const productLinks = page.locator('a[href*="/producto/"]');
  const count = await productLinks.count();
  
  for (let i = 0; i < Math.min(count, 5); i++) {
    const href = await productLinks.nth(i).getAttribute('href');
    await page.goto(href);
    await page.waitForLoadState('networkidle');
    const thumbnails = page.locator('button[style*="72px"]');
    if (await thumbnails.count() > 1) return; // producto con múltiples imágenes
  }
  test.skip(true, 'No se encontró producto con múltiples imágenes');
}
```

---

## 11. Mitigación de rate-limiting

petsgo.cl aplica rate-limiting cuando recibe muchos requests seguidos. Los tests CA-002, CA-041, CA-060, CA-082 son intermitentes por esta causa.

```js
// OBLIGATORIO — agregar pausa entre tests:
test.afterEach(async () => {
  await new Promise(resolve => setTimeout(resolve, 2500));
});

// Para tests sensibles a carga, usar timeout largo:
await page.waitForLoadState('networkidle', { timeout: 20000 });
await expect(element).toBeVisible({ timeout: 15000 });
```

**Para confirmar que un test intermitente NO es bug, ejecutar individualmente:**
```bash
npx playwright test tests/catalogo.spec.js --grep "CA-002" --headed
```

---

## 12. Bugs reales confirmados (dejar como FAIL, no corregir el test)

### 🐛 CA-029 — Buscador mobile superpuesto en ≤639px
- **Severidad:** Alta
- **Archivo:** `CategoryPage.jsx`
- **Descripción:** Ícono de lupa se superpone o desaparece en viewport ≤639px
- **Acción test:** Dejar como FAIL con screenshot de evidencia

### 🐛 CA-050 — Slug inexistente → página en blanco
- **Severidad:** Alta
- **Archivo:** `ProductDetailPage.jsx`
- **Descripción:** Solo maneja `productInactive` (status 403). Si el slug no existe en BD, la API devuelve otro código y el frontend queda en blanco sin feedback.
- **Acción test:** Dejar como FAIL con screenshot de evidencia
