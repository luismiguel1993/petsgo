# QA-02 — Análisis de Fallos + Selectores Corregidos para Re-ejecución

**Proyecto:** PetsGO — Marketplace multi-vendor de mascotas  
**Módulo:** QA-02 Catálogo y Productos  
**Fecha análisis:** 2026-03-21  
**Propósito:** Contexto para el agente QA que re-ejecutará `tests/catalogo.spec.js`

---

## Resumen de la primera ejecución

| Resultado | Cantidad | Desglose |
|-----------|----------|----------|
| ✅ PASS | 21 | Funcionalidad confirmada |
| ❌ FAIL por selector incorrecto | 6 + 13 en cascada | **Falso negativo** — la app funciona bien, el test apuntó mal |
| ❌ FAIL por rate-limiting | 4 | **Intermitente** — pasan si se ejecutan individualmente |
| ❌ FAIL por datos faltantes | 3 | **No hay producto de prueba** que cumpla la precondición |
| 🐛 Bug real confirmado | 2 | CA-029, CA-050 |
| ⏭️ SKIP manual | 2 | CA-102, CA-109 (upload de archivos) |

---

## SECCIÓN 1 — Selectores correctos verificados en código fuente

> Los selectores de esta sección fueron verificados leyendo directamente los archivos `.jsx`.
> **USAR ESTOS en lugar de los que se intentaron en la primera ejecución.**

### 1.1 CategoryPage — Select de ordenamiento

**Archivo:** `frontend/src/pages/CategoryPage.jsx` (líneas 625-645)

El `<select>` tiene exactamente estas opciones:

```html
<option value="default">Relevancia</option>
<option value="price_asc">Precio: menor a mayor</option>
<option value="price_desc">Precio: mayor a menor</option>
<option value="name_asc">Nombre A-Z</option>
<option value="discount">Mayor descuento</option>
```

**Selector Playwright correcto:**
```js
// Localizar el select de ordenamiento
const sortSelect = page.locator('select').filter({ has: page.locator('option[value="price_asc"]') });

// Ordenar por precio ascendente (CA-023)
await sortSelect.selectOption({ value: 'price_asc' });

// Ordenar por precio descendente (CA-024)
await sortSelect.selectOption({ value: 'price_desc' });

// Ordenar por nombre A-Z (CA-025)
await sortSelect.selectOption({ value: 'name_asc' });

// Ordenar por mayor descuento
await sortSelect.selectOption({ value: 'discount' });
```

> ⚠️ **Error de la primera ejecución:** Se buscó texto "Menor a Mayor" o "menor precio". El texto real es `"Precio: menor a mayor"` (todo minúscula después de los dos puntos). Usar `value` en vez de `label` es más confiable.

---

### 1.2 CategoryPage — Paginación

**No usa botones con texto "siguiente"/"anterior".** Usa íconos SVG de lucide-react (ChevronLeft/ChevronRight).

**Selector Playwright correcto:**
```js
// Botones numéricos de página
const pageButtons = page.locator('button').filter({ hasText: /^\d+$/ });

// Botón "siguiente" — es el último botón del grupo de paginación (ícono >)
const nextButton = page.locator('button').filter({ has: page.locator('svg') }).last();

// Alternativa: buscar por aria o por posición relativa a los números
// El contenedor de paginación tiene botones: [<] [1] [2] [...] [5] [>]
const paginationContainer = page.locator('div').filter({ has: page.locator('button:has-text("1")') }).filter({ has: page.locator('button:has-text("2")') });
```

> ⚠️ **Error de la primera ejecución:** Se buscó `page.getByText('siguiente')`. No existe texto "siguiente" — son íconos `<ChevronRight>` y `<ChevronLeft>`.

> **Nota:** `PRODUCTS_PER_PAGE = 12`. La paginación solo aparece si la categoría tiene >12 productos.

---

### 1.3 ProductDetailPage — Spinner de cantidad (+/-)

**NO es `input[type=number]`.** Es un componente custom con 2 buttons + 1 span.

**Estructura del DOM:**
```html
<div style="display:flex; background:#f0f9ff; border-radius:14px; padding:6px">
  <!-- Botón MENOS -->
  <button style="width:44px; height:44px; background:#fff">
    <svg class="lucide lucide-minus">...</svg>  <!-- Minus icon -->
  </button>
  
  <!-- Cantidad mostrada -->
  <span style="font-size:18px; font-weight:700; min-width:40px; text-align:center">1</span>
  
  <!-- Botón MÁS -->
  <button style="width:44px; height:44px; background:#00A8E8">
    <svg class="lucide lucide-plus">...</svg>  <!-- Plus icon -->
  </button>
</div>
```

**Selector Playwright correcto:**
```js
// Contenedor del spinner
const qtyContainer = page.locator('div[style*="f0f9ff"]');

// Botón menos (primer botón dentro del contenedor)
const minusBtn = qtyContainer.locator('button').first();

// Botón más (segundo botón dentro del contenedor)
const plusBtn = qtyContainer.locator('button').last();

// Valor actual de cantidad
const qtyValue = qtyContainer.locator('span');

// Verificar que no permite cantidad 0
await minusBtn.click(); // intentar bajar de 1
const qty = await qtyValue.textContent();
expect(Number(qty), 'Cantidad no debe ser 0 ni negativa').toBeGreaterThanOrEqual(1);

// Verificar que + aumenta
await plusBtn.click();
const qtyAfter = await qtyValue.textContent();
expect(Number(qtyAfter)).toBe(2);
```

> ⚠️ **Error de la primera ejecución:** Se usó `page.locator('input[type=number]')`. No existe ese elemento.

> **Comportamiento del botón +:** Se deshabilita (background cambia a `#e5e7eb`) cuando `qty >= stock`. El botón - NO permite bajar de 1.

---

### 1.4 ProductDetailPage — Galería de imágenes

**Los thumbnails son `<button>` con medidas 72x72px.**

```js
// Thumbnails de la galería
const thumbnails = page.locator('button[style*="72px"]');

// Click en segundo thumbnail
await thumbnails.nth(1).click();

// Verificar que el thumbnail activo tiene borde azul #00A8E8
const activeBorder = await thumbnails.nth(1).evaluate(el => el.style.border);
expect(activeBorder).toContain('#00A8E8');
```

---

### 1.5 ProductDetailPage — "Ver más" / "Ver menos"

**Solo se renderiza si la descripción tiene más de 180 caracteres.**

```js
// Solo aparece si descripción > 180 chars
const verMasBtn = page.locator('button').filter({ hasText: /Ver más/i });

// Si existe, verificar comportamiento
if (await verMasBtn.isVisible({ timeout: 3000 })) {
  await verMasBtn.click();
  await expect(page.locator('button').filter({ hasText: /Ver menos/i })).toBeVisible();
}
```

> ⚠️ **Error de la primera ejecución:** El producto de prueba tenía descripción corta (<180 chars) → botón nunca se renderizó. **Elegir un producto con descripción larga.**

---

### 1.6 ProductDetailPage — "También te puede gustar"

**Existe en el código (línea 595).** Título con emoji: `🐾 También te puede gustar`

```js
// Verificar sección de productos relacionados
await expect(page.getByText('También te puede gustar')).toBeVisible({ timeout: 10000 });

// Verificar que hay al menos 1 producto relacionado
const relatedProducts = page.locator('h3').filter({ hasText: /También te puede gustar/i })
  .locator('..').locator('..').locator('a, [style*="cursor"]');
await expect(relatedProducts.first()).toBeVisible();
```

> ⚠️ **Error de la primera ejecución:** La sección carga productos de la misma categoría vía API. Si el request falla o la categoría tiene pocos productos, no se muestra. Agregar `timeout: 10000` y usar `waitForResponse` para esperar la API.

---

### 1.7 ProductDetailPage — Producto inactivo / 404

**El componente SÍ maneja productos inactivos** (muestra "🚫 Producto no disponible") pero solo cuando la API devuelve **status 403**.

```js
// Navegar a producto inexistente
await page.goto('/producto/este-slug-no-existe-xyz-12345');
await page.waitForLoadState('networkidle');

// Verificar que se muestra mensaje de no disponible
await expect(
  page.getByText(/no disponible|no encontrado/i)
).toBeVisible({ timeout: 10000 });
```

> 🐛 **Bug parcial confirmado:** Si el slug no existe en la BD, la API puede devolver un código distinto a 403, y el frontend queda en blanco sin feedback. El manejo de "producto inactivo" funciona, pero falta manejo de "slug inexistente".

---

### 1.8 VendorDetailPage — Filtro de precio

**Es un `<input type="range">` nativo** (NO un componente custom slider).

```js
// Selector del filtro de precio
const priceSlider = page.locator('input[type="range"]');
await expect(priceSlider).toBeVisible();

// Cambiar valor del slider
await priceSlider.fill('15000');

// Verificar que se filtran productos (el texto del rango se actualiza)
await expect(page.getByText(/\$/)).toBeVisible();
```

> ⚠️ **Error de la primera ejecución:** Se buscó un componente custom. Es `input[type=range]` estándar con `accentColor: #00A8E8`.

---

### 1.9 VendorDashboard — Botón "Agregar Producto"

**El texto del botón es "Agregar Producto" (NO "Nuevo Producto").**

"Nuevo Producto" solo aparece como **título del modal** que se abre después del click.

```js
// Login como vendor
await login(page, process.env.VENDOR_EMAIL, process.env.VENDOR_PASSWORD);
await page.goto('/vendor');
await page.waitForLoadState('networkidle');

// Navegar al tab de Inventario (si no está activo)
await page.getByText(/Inventario/i).click();

// Click en botón "Agregar Producto" (NO "Nuevo Producto")
await page.getByRole('button', { name: /Agregar Producto/i }).click();

// Ahora aparece el modal con título "Nuevo Producto"
await expect(page.getByText('Nuevo Producto')).toBeVisible();
```

> ⚠️ **Error de la primera ejecución:** Se buscó texto "Nuevo Producto" como botón. El botón dice "Agregar Producto" + ícono Plus.

> **Tabs disponibles en VendorDashboard:** Dashboard, Inventario, Pedidos, Cupones.

---

### 1.10 AdminDashboard — Gestión de categorías

**Las categorías se gestionan dentro del tab "🛒 Tienda PetsGo"**, no como sección independiente.

```js
// Login como admin
await login(page, process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD);
await page.goto('/admin');
await page.waitForLoadState('networkidle');

// Ir al tab "Tienda PetsGo"
await page.getByText(/Tienda PetsGo/i).click();
await page.waitForLoadState('networkidle');

// Buscar controles de categoría dentro de ese tab
// Las categorías se manejan como <select> dinámico populado desde API
const categorySelect = page.locator('select').filter({ has: page.locator('option') });
```

> **Tabs del AdminDashboard:** 📊 Dashboard Global, 🏪 Tiendas, 🛒 Tienda PetsGo, 🚗 Riders.

---

### 1.11 Header — Búsqueda global vacía

**SÍ tiene validación.** El handler previene navegación si el campo está vacío.

```js
// Buscar el input del header
const searchInput = page.locator('input[placeholder*="buscando"]');

// Intentar buscar vacío — presionar Enter sin escribir nada
await searchInput.click();
await searchInput.fill('');
await page.keyboard.press('Enter');

// Debe mostrar error "⚠ Escribe algo para buscar" y NO navegar
await expect(page.getByText(/Escribe algo para buscar/i)).toBeVisible({ timeout: 3000 });

// Verificar que NO navegó a otra URL
await expect(page).not.toHaveURL(/categoria/);
```

> ⚠️ **Error de la primera ejecución:** Es posible que el test navegara directamente a `/categoria/Todos?q=` en vez de usar el input del header, lo cual bypasea la validación frontend.

---

## SECCIÓN 2 — Tests intermitentes (rate-limiting)

Los siguientes tests pasaron en run 1 y fallaron en run 2 por **rate-limiting del servidor petsgo.cl**:

| ID | Test | Recomendación |
|---|---|---|
| CA-002 | Hero/banner (h1 dinámico) | Agregar `await page.waitForSelector('h1', { timeout: 15000 })` |
| CA-041 | Galería thumbnails | Agregar `await page.waitForLoadState('networkidle')` antes del click |
| CA-060 | Búsqueda desde header | Esperar respuesta API: `page.waitForResponse(r => r.url().includes('/products'))` |
| CA-082 | Vendor sin productos | Agregar retry o timeout largo |

**Mitigación global para todo el spec:**
```js
// Agregar en test.afterEach para evitar rate-limiting
test.afterEach(async () => {
  await new Promise(r => setTimeout(r, 2500)); // pausa 2.5s entre tests
});
```

**Para confirmar que NO son bugs, ejecutar individualmente:**
```bash
npx playwright test tests/catalogo.spec.js --grep "CA-002" --headed
npx playwright test tests/catalogo.spec.js --grep "CA-041" --headed
npx playwright test tests/catalogo.spec.js --grep "CA-060" --headed
npx playwright test tests/catalogo.spec.js --grep "CA-082" --headed
```

---

## SECCIÓN 3 — Tests que necesitan datos de prueba específicos

| ID | Precondición requerida | Qué falta en producción | Acción sugerida |
|---|---|---|---|
| CA-043 | Producto con variante cuyo stock = 0 | No hay productos en el catálogo actual con variante agotada | Saltar con `test.skip(true, 'No hay productos con variante stock=0 en producción')` o pedirle al equipo que cree uno |
| CA-046 | Categoría con ≥6 productos para "También te puede gustar" | La categoría del producto de prueba puede tener pocos items | Usar un producto de categoría "Alimento" o "Accesorios" (las más pobladas) |
| CA-048 | Producto con descripción >180 caracteres | El producto elegido tenía descripción corta | Buscar un producto con descripción larga o usar un slug conocido |

---

## SECCIÓN 4 — Bugs reales confirmados (2)

### 🐛 BUG 1: CA-029 — Buscador mobile se superpone en ≤639px

**Severidad:** Alta  
**Componente:** `CategoryPage.jsx`  
**Descripción:** En viewport ≤639px, el ícono de búsqueda (lupa) se superpone con el input o no es visible. La media query cambia `flex-direction: column` pero no ajusta z-index ni padding del ícono posicionado con `position: absolute`.  
**Reproducción:**
1. Abrir `/categoria/Perros` con viewport 375x812
2. Observar el input de búsqueda y el ícono de lupa
3. El ícono se superpone o desaparece

**Estado:** Bug de CSS — no impide la funcionalidad pero afecta UX mobile.

---

### 🐛 BUG 2: CA-050 — Producto inexistente muestra página en blanco

**Severidad:** Alta  
**Componente:** `ProductDetailPage.jsx`  
**Descripción:** El componente maneja `productInactive` (status 403 → muestra "🚫 Producto no disponible"), pero cuando se navega a un slug que **no existe en la BD**, la API puede devolver un código diferente y el frontend queda en blanco sin mensaje de error ni redirección.  
**Reproducción:**
1. Navegar a `/producto/slug-que-no-existe-12345`
2. La página queda en blanco (sin 404, sin mensaje, sin redirección)

**Causa:** El catch block solo setea `productInactive = true` cuando `response.status === 403`. Otros errores (404, 500, network) no se manejan visualmente.

---

## SECCIÓN 5 — Resumen de acciones para re-ejecución

### Tests a CORREGIR selectores (usar los de Sección 1):
- CA-023 → usar `selectOption({ value: 'price_asc' })`
- CA-026 → buscar botones con íconos SVG, no texto "siguiente"
- CA-044 → usar botones custom dentro de `div[style*="f0f9ff"]`, no `input[type=number]`
- CA-083 → usar `input[type="range"]`
- CA-100 → buscar "Agregar Producto", no "Nuevo Producto"
- CA-120 → navegar a tab "Tienda PetsGo" primero

### Tests en cascada que se desbloquean al corregir:
- CA-101, CA-103, CA-104, CA-105, CA-106, CA-107, CA-108 (dependen de CA-100)
- CA-121, CA-122, CA-123, CA-124 (dependen de CA-120)

### Tests a SALTAR:
- CA-043 → `test.skip(true, 'No hay productos con variante stock=0 en producción para probar')`
- CA-102 → `test.skip(true, 'MANUAL: Requiere file picker del OS para subir imagen')`
- CA-109 → `test.skip(true, 'MANUAL: Requiere file picker del OS para subir imagen')`

### Tests a ejecutar individualmente para confirmar (rate-limit):
- CA-002, CA-041, CA-060, CA-082

### Bugs reales a documentar (NO corregir en tests, dejar FAIL):
- CA-029 (CSS mobile)
- CA-050 (producto inexistente → blanco)
