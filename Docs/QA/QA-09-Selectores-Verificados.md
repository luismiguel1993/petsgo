# QA-09 — Selectores Verificados: Mobile y Diseño Responsivo

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `Header.jsx`, `Footer.jsx`, `HomePage.jsx`, `CategoryPage.jsx`, `ProductDetailPage.jsx`, `CartPage.jsx`, `BotChatOverlay.jsx`, etc.  
**Propósito:** Selectores y breakpoints Playwright verificados contra el código real para tests mobile/responsivo.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

---

## 1. Breakpoints verificados del código fuente

| Breakpoint | Tipo | Componentes que lo usan |
|-----------|------|------------------------|
| **360px** | max-width CSS | PromoSlider (extra-small) |
| **380px** | max-width CSS | CategoryPage, VendorDetailPage → 1 columna |
| **480px** | min/max CSS | HomePage hero buttons en fila, CartPage, PlansPage, PromoSlider |
| **600px** | max-width CSS | **BotChatOverlay** → fullscreen |
| **639px** | max-width CSS | CategoryPage, VendorDetailPage → 2 columnas, oculta descripciones |
| **640px (sm)** | Tailwind | Header (labels de texto), Footer (2-col), HomePage |
| **768px (md)** | Tailwind + CSS | **Header** (hamburguesa ↔ desktop nav), ProductDetailPage, CartPage, dashboards |
| **1024px (lg)** | Tailwind + CSS | Header (full), HomePage hero side-by-side, Footer 4-col |
| **1280px (xl)** | Tailwind | HomePage grid productos → 4 columnas |

---

## 2. Viewports recomendados para tests

| Device | Width × Height | Breakpoint clave | Qué verificar |
|--------|---------------|-------------------|----------------|
| **iPhone SE** | 375 × 667 | < md (768) | Hamburguesa, 1 col productos, fullscreen chat |
| **Small phone** | 320 × 568 | < 380px | 1 col grid, PromoSlider compacto |
| **iPhone 14 Pro** | 393 × 852 | < md | Hamburguesa, stacked hero |
| **iPad portrait** | 768 × 1024 | = md | Transición: hamburguesa desaparece, nav visible |
| **iPad landscape** | 1024 × 768 | = lg | Hero side-by-side, 3 col productos |
| **Desktop** | 1280 × 720 | ≥ xl | Todo visible: nav, 4 col, full chat |

```js
// ✅ Configurar viewport por test:
test.use({ viewport: { width: 375, height: 667 } }); // iPhone SE

// ✅ O dinámicamente:
await page.setViewportSize({ width: 375, height: 667 });
```

---

## 3. Header Responsivo

### Elementos mobile (< 768px / md)

| Elemento | Selector verificado | Visible en |
|----------|-------------------|-----------|
| Botón hamburguesa | `page.locator('button.md\\:hidden')` | Mobile (< 768px) |
| Panel menú mobile | `page.locator('header .md\\:hidden.bg-white.border-t')` | Al click hamburguesa |
| Input búsqueda mobile | `page.locator('header .md\\:hidden.bg-white input')` | Dentro del panel |

### Elementos desktop (≥ 768px)

| Elemento | Selector verificado | Visible en |
|----------|-------------------|-----------|
| Nav desktop | `page.locator('nav.hidden.md\\:flex')` | Desktop (≥ 768px) |
| Input búsqueda desktop | `page.locator('.hidden.md\\:flex input[type="text"]')` | Desktop |

```js
// ✅ Test hamburguesa en mobile:
test('MB-001: Menú hamburguesa visible en mobile', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 667 });
  await page.goto('/');
  
  // Hamburguesa visible
  await expect(page.locator('button.md\\:hidden')).toBeVisible();
  
  // Nav desktop oculto
  await expect(page.locator('nav.hidden.md\\:flex')).not.toBeVisible();
  
  // Click hamburguesa
  await page.locator('button.md\\:hidden').click();
  
  // Panel mobile aparece
  await expect(page.locator('header .md\\:hidden.bg-white')).toBeVisible();
});

// ✅ Test desktop nav visible:
test('Verificar nav desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto('/');
  
  // Nav desktop visible
  await expect(page.locator('nav.hidden.md\\:flex')).toBeVisible();
  
  // Hamburguesa oculta
  await expect(page.locator('button.md\\:hidden')).not.toBeVisible();
});
```

### Logo

| Viewport | Tamaño | Selector |
|----------|--------|----------|
| Todos | Siempre visible | `page.locator('header a[href="/"]').first()` |
| sm+ (≥ 640px) | Texto labels visibles | |

---

## 4. HomePage Responsivo

### Hero / Banner

| Viewport | Layout | Notas |
|----------|--------|-------|
| < 480px | Stack vertical, botones full-width | CTA buttons stacked |
| 480px-1023px | Stack vertical, botones en fila | |
| ≥ 1024px (lg) | Side-by-side (50/50) | Imagen a la derecha |

### Grid de Productos

| Viewport | Columnas | Notas |
|----------|----------|-------|
| Base (< 640px) | 1 columna | |
| sm (≥ 640px) | 2 columnas | |
| lg (≥ 1024px) | 3 columnas | |
| xl (≥ 1280px) | 4 columnas | |

```js
// ✅ Verificar grid de productos en mobile:
test('MB-022: Grid responsivo de productos', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 667 });
  await page.goto('/');
  
  // Verificar que los productos se muestran
  const productCards = page.locator('a[href*="/producto/"]');
  await expect(productCards.first()).toBeVisible();
  
  // Screenshot como evidencia
  await page.screenshot({ path: 'evidencias/mobile-MB022-grid-375px.png', fullPage: true });
});
```

---

## 5. CategoryPage Responsivo

| Viewport | Columnas grid | Cambios |
|----------|--------------|---------|
| ≤ 380px | 1 columna | |
| ≤ 639px | 2 columnas | Descripciones de producto ocultas |
| ≥ 640px | Auto-fill grid (repeat) | Descripciones visibles |

### Filtros en mobile

| Aspecto | Comportamiento |
|---------|---------------|
| Sidebar de filtros | NO es drawer colapsable; siempre visible encima del grid |
| Input búsqueda | Ancho completo en mobile |
| Select ordenamiento | Ancho completo en mobile |

---

## 6. ProductDetailPage Responsivo

| Viewport | Layout | Notas |
|----------|--------|-------|
| < 768px (md) | 1 columna stacked | Imagen arriba, info abajo |
| ≥ 768px (md) | 2 columnas grid | Imagen izquierda, info derecha |

```js
// ✅ Verificar layout mobile de producto:
test('MB-060: Detalle producto en mobile', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 667 });
  await page.goto('/producto/1');
  await page.waitForLoadState('networkidle');
  
  // Imagen del producto visible
  await expect(page.locator('img').first()).toBeVisible();
  
  // Nombre del producto
  await expect(page.locator('h1')).toBeVisible();
  
  // Precio visible y legible
  const precio = page.locator('span').filter({ hasText: /^\$/ }).first();
  await expect(precio).toBeVisible();
});
```

---

## 7. CartPage Responsivo

| Viewport | Layout | Notas |
|----------|--------|-------|
| < 768px (md) | 1 columna stacked | Items arriba, resumen abajo |
| ≥ 768px (md) | 2 columnas grid | Items izquierda, resumen derecha |

---

## 8. BotChatOverlay Responsivo

| Viewport | Tamaño chat | Selector verificado |
|----------|------------|-------------------|
| > 600px | 350×500px fixed | `.pgchat-window` con dimensiones fijas |
| ≤ 600px | 100vw × 100vh fullscreen | `.pgchat-window` ocupa toda la pantalla |

```js
// ✅ Verificar fullscreen en mobile:
test('MB-100: Chat fullscreen ≤600px', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/');
  
  await page.locator('.pgchat-trigger').click();
  
  const chatWindow = page.locator('.pgchat-window');
  await expect(chatWindow).toBeVisible();
  
  const box = await chatWindow.boundingBox();
  expect(box.width).toBeCloseTo(375, -1); // Ancho completo
});
```

---

## 9. Footer Responsivo

| Viewport | Layout | Notas |
|----------|--------|-------|
| Base (< 640px) | 1 columna | Links stacked |
| sm (≥ 640px) | 2 columnas | |
| lg (≥ 1024px) | 4 columnas | Layout completo |

---

## 10. wp-login.php Responsivo

| Viewport | Logo | Alineación | Padding |
|----------|------|-----------|---------|
| ≤ 480px | 180×60px | Arriba | 12px lateral |
| 480-700px height | 180×60px | Arriba, padding-top 20px | |
| ≥ 1920×1080 | 220×80px | Centrado | Normal |

```js
// ✅ Test wp-login en diferentes viewports:
test('MB-160: wp-login en laptop', async ({ page }) => {
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.goto('/wp-login.php');
  const logo = page.locator('#login h1 a');
  await expect(logo).toBeVisible();
});

test('MB-162: wp-login en mobile', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/wp-login.php');
  const logo = page.locator('#login h1 a');
  await expect(logo).toBeVisible();
  // Verificar sin scroll horizontal
  const body = await page.evaluate(() => document.body.scrollWidth <= window.innerWidth);
  expect(body).toBe(true);
});
```

---

## 11. Dashboards en Tablet

| Dashboard | Viewport recomendado | Notas |
|-----------|---------------------|-------|
| Vendor (`/vendor`) | 768×1024 | Tabs y tablas deben ser accesibles |
| Rider (`/rider`) | 768×1024 | Tabs y botones de acción accesibles |
| Admin (`/admin`) | 768×1024 | Tablas con scroll horizontal si necesario |

---

## 12. Patrón de test para verificar "sin scroll horizontal"

```js
// ✅ Verificar que no hay overflow horizontal:
async function assertNoHorizontalScroll(page) {
  const hasScroll = await page.evaluate(() => {
    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
  });
  expect(hasScroll, 'No debe haber scroll horizontal').toBe(false);
}

// Uso:
test('MB-008: Header sin overflow en XS', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 667 });
  await page.goto('/');
  await assertNoHorizontalScroll(page);
});
```

---

## 13. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Viewport | Selector/Verificación |
|----|------|----------|----------------------|
| MB-001 | Hamburguesa visible | 375×667 | `page.locator('button.md\\:hidden')` visible |
| MB-002 | Hamburguesa se abre | 375×667 | Click → panel `.md\\:hidden.bg-white` visible |
| MB-005 | Logo visible mobile | 375×667 | `page.locator('header a[href="/"]')` visible |
| MB-006 | Badge carrito mobile | 375×667 | Badge numérico visible |
| MB-008 | Sin overflow XS | 375×667 | `scrollWidth <= clientWidth` |
| MB-020 | Hero banner mobile | 375×667 | Sin overflow, CTA visible |
| MB-022 | Grid responsivo | 375/768/1280 | Verificar columnas en cada VP |
| MB-040 | Buscador mobile | 375×667 | Input ancho completo |
| MB-060 | Galería mobile | 375×667 | Imagen + thumbnails visibles |
| MB-064 | Sin scroll horizontal detalle | 375×667 | No overflow |
| MB-080 | Carrito usable | 375×667 | Items, botones accesibles |
| MB-100 | Chat fullscreen | 375×812 | `.pgchat-window` 100vw×100vh |
| MB-120 | Vendor tablet | 768×1024 | Tabs y tablas accesibles |
| MB-140 | Footer columna mobile | 375×667 | Stacked vertical |
| MB-160 | wp-login laptop | 1366×768 | Logo 180×60px |
| MB-162 | wp-login mobile | 375×812 | Logo visible, sin overflow |

---

## 14. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| MB-001 | "Viewport ≤ 639px" para hamburguesa | El breakpoint real es **768px (md)** — hamburguesa visible < 768px | El spec dice ≤639px pero realmente desaparece a 768px |
| MB-041 | "Filtros en drawer colapsable" | Los filtros NO están en drawer; están siempre visibles arriba del grid | No hay botón "Aplicar filtros" separado |
| MB-042 | "Botón Aplicar filtros" | No existe botón de aplicar — los filtros se aplican en tiempo real | **NO IMPLEMENTADO** como botón |
| MB-060 | "Swipe gallery" | No hay swipe nativo; se usan thumbnails clickables | Diferente UX |
| MB-062 | "CTA sticky al fondo" | Botón agregar al carrito NO es sticky; está en su posición normal | **NO IMPLEMENTADO** como sticky |
| MB-081 | "Pasos de checkout" | No hay indicador visual de pasos (1, 2, 3) | **NO VERIFICADO** |
| MB-101 | "Input sube con teclado virtual" | Requiere device real iOS/Android | **Solo test manual** |
| MB-104 | "Chat no abre 2 veces" | Lógica de toggle en código (open/close) | ✅ Verificable |

---

## 15. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| MB-041, MB-042 | Drawer de filtros y botón "Aplicar" no implementados |
| MB-062 | CTA sticky no implementado |
| MB-082 | Teclado virtual — requiere device real |
| MB-101 | Teclado virtual iOS/Android — requiere device real |
| MB-160-163 (Performance) | LCP/CLS/bundle size — requieren Lighthouse, no Playwright |
| MB-181 | Página `/privacidad` — URL puede no existir (verificar) |
| MB-182 | Página 404 — comportamiento depende de router config |
