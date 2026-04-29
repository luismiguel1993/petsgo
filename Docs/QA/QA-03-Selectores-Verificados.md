# QA-03 — Selectores Verificados: Carrito, Checkout y Pagos

**Proyecto:** PetsGO — Marketplace multi-vendor de mascotas  
**Fecha:** 2026-03-21  
**Propósito:** Guía exhaustiva del flujo de carrito, checkout y pagos con selectores verificados del código fuente JSX. Referencia obligatoria para el agente QA al escribir tests Playwright del módulo QA-03.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad** porque fue verificado contra el código fuente real.

---

## 0. Arquitectura del flujo de compra — HALLAZGO CRÍTICO

### ❗ NO existe ruta `/checkout` separada

**Todo el checkout está integrado en `CartPage.jsx` (1025 líneas), ruta `/carrito`.**

El flujo completo dentro de una sola página es:
1. Ver items del carrito (lista con qty +/-)
2. Seleccionar método de entrega (Despacho / Retiro en tienda)
3. Completar dirección (si despacho)
4. Aplicar cupón (opcional)
5. Seleccionar método de pago (Transbank / MercadoPago / Test)
6. Click en botón "Pagar y Confirmar"
7. Modal de confirmación (dentro de la misma página)

```js
// ❌ INCORRECTO — NO EXISTE:
// await page.goto('/checkout');

// ✅ CORRECTO — todo está en /carrito:
await page.goto('/carrito');
```

> **Consecuencia para tests CC-020:** El caso "Click Proceder al pago → Navega a /checkout" **NO aplica**. El checkout es inline dentro de `/carrito`. El test debe verificar que los controles de entrega, dirección, cupón y pago estén visibles en `/carrito`.

### Componentes involucrados

| Componente | Archivo | Función |
|------------|---------|---------|
| `CartPage` | `frontend/src/pages/CartPage.jsx` | Página principal: carrito + checkout + modal confirmación |
| `FloatingCart` | `frontend/src/components/FloatingCart.jsx` | Panel lateral: aparece al agregar producto (auto-cierre 3s) |
| `CartContext` | `frontend/src/context/CartContext.jsx` | Estado global: items, cupón, totales |
| `SiteContext` | `frontend/src/context/SiteContext.jsx` | Config del sitio: envío gratis, costos, módulos habilitados |

---

## 1. FloatingCart — Panel lateral al agregar producto

### Comportamiento automático
- Se abre **automáticamente** al agregar un producto al carrito (`addItem()` dispara callback)
- Se **auto-cierra después de 3 segundos** (NO 5 como dice el CLAUDE.md, son 3)
- Si el usuario interactúa (hover/mouseenter), se cancela el auto-cierre
- Se cierra al hacer click fuera del panel
- Ancho fijo: **380px** (`maxWidth: 90vw` en mobile)
- Slide desde la derecha con animación `0.35s cubic-bezier`

### Selectores del FloatingCart

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Panel completo | `page.locator('div[style*="width: 380px"]')` o `page.locator('div[style*="translateX"]')` | Posición fixed, zIndex 999 |
| Título "Mi Carrito" | `page.getByText('Mi Carrito')` | fontSize 18px, fontWeight 700 |
| Badge cantidad | Span con background `#FFC400` | borderRadius 20px, fontSize 12px |
| Botón cerrar (X) | `page.locator('button[style*="border-radius: 50%"]').filter({ has: page.locator('svg') })` | 36x36px, background `#f3f4f6` |
| Carrito vacío | `page.getByText('Tu carrito está vacío')` | FloatingCart muestra esto + "Agrega productos y aparecerán aquí" |
| Imagen item | 72x72px thumbnail | borderRadius 10px |
| Nombre item | `<p>` fontSize 13px, fontWeight 600 | overflow ellipsis |
| Precio item | `<p>` fontSize 15px, fontWeight 700, color `#00A8E8` | |
| Botón − (qty) | 28x28px button | borderRadius 8px, border `1px solid #d1d5db` |
| Valor qty | `<span>` fontSize 14px, fontWeight 600 | minWidth 20px |
| Botón + (qty) | 28x28px button | Mismo estilo que − |
| Botón eliminar | `<button>` con ícono Trash2, 28x28px | background `#fef2f2`, color `#ef4444` |
| Subtotal | Texto "Subtotal" + precio | |
| Texto envío | `page.getByText('Envío se calcula al finalizar')` | fontSize 12px, color `#9ca3af` |
| **Botón "Ir al Carrito"** | `page.getByText('Ir al Carrito')` | **Link a `/carrito`**, background `#00A8E8`, texto UPPERCASE |
| **Botón "Seguir comprando"** | `page.getByText('Seguir comprando')` | Cierra el FloatingCart |

```js
// ✅ Verificar que FloatingCart se abrió tras agregar producto:
// Después de hacer click en "Agregar al carrito" en ProductDetailPage:
await expect(page.getByText('Ir al Carrito')).toBeVisible({ timeout: 5000 });

// ✅ Ir al carrito desde FloatingCart:
await page.getByText('Ir al Carrito').click();
await expect(page).toHaveURL('/carrito');
```

---

## 2. CartPage — Estado vacío

### Cuándo se muestra
Cuando `items.length === 0` y no hay modal de confirmación pendiente.

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Ícono carrito | ShoppingCart 40px, color `#d1d5db` | Dentro de círculo 96px bg `#f3f4f6` |
| Título | `page.getByText('Tu carrito está vacío')` | `<h2>`, fontSize 24px, fontWeight 700 |
| Subtítulo | `page.getByText('¡Agrega productos desde el marketplace!')` | color `#6b7280` |
| CTA | `page.getByText('Explorar Productos')` | **Link a `/`**, background `#00A8E8`, con ícono ArrowLeft |

```js
// ✅ Test CC-012: Carrito vacío muestra CTA
await page.goto('/carrito');
await expect(page.getByText('Tu carrito está vacío')).toBeVisible();
await expect(page.getByText('Explorar Productos')).toBeVisible();
await page.getByText('Explorar Productos').click();
await expect(page).toHaveURL('/');
```

---

## 3. CartPage — Lista de items

### Encabezado

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Link "Seguir comprando" | `page.locator('a[href="/"]').filter({ hasText: 'Seguir comprando' })` | Con ícono ArrowLeft, color `#9ca3af` hover `#00A8E8` |
| Título | `page.locator('h1')` | Texto: "Mi Carrito (N productos)" |
| Contador items | Dentro del `<h1>`: `<span>` con "(N productos)" | color `#9ca3af`, fontSize 18px |

### Card de cada item

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Card completa | `page.locator('div[style*="borderRadius: 16px"]').filter({ has: page.locator('img') })` | boxShadow, border `1px solid #f0f0f0` |
| Imagen | 90x90px, borderRadius 12px | objectFit cover |
| Nombre producto | `<h3>` fontSize 15px, fontWeight 600, color `#1f2937` | |
| Tienda | `<p>` fontSize 12px, color `#9ca3af` | Muestra `store_name` o `brand` o "PetsGo" |
| Precio | `<p>` fontSize 20px, fontWeight 700, color `#00A8E8` | Formato: `$XX.XXX` |

### Controles de cantidad (en CartPage)

**Diferentes al FloatingCart (36px aquí vs 28px en FloatingCart) y al ProductDetail (44px).**

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Contenedor | `div` con background `#f9fafb`, borderRadius 12px, padding 6px | |
| Botón − (Minus) | **36x36px**, borderRadius 8px, bg `#fff`, border `1px solid #e5e7eb` | Ícono Minus size 16, color `#6b7280` |
| Valor cantidad | `<span>` width 36px, fontWeight 700, fontSize 16px | textAlign center |
| Botón + (Plus) | **36x36px**, mismos estilos | Ícono Plus size 16, color `#6b7280` |

```js
// ✅ Cambiar cantidad del primer item:
const firstItem = page.locator('div[style*="borderRadius: 16px"]').filter({ has: page.locator('img') }).first();

// Contenedor qty está DENTRO del item card:
const qtyContainer = firstItem.locator('div[style*="f9fafb"]');
const minusBtn = qtyContainer.locator('button').first();
const plusBtn = qtyContainer.locator('button').last();
const qtyDisplay = qtyContainer.locator('span');

// Incrementar:
await plusBtn.click();
await expect(qtyDisplay).toHaveText('2');

// Decrementar a 0 → elimina el item:
await minusBtn.click(); // qty=1
await minusBtn.click(); // qty=0 → removeItem()
```

> ⚠️ **Comportamiento de qty=0:** `updateQuantity(id, 0)` llama a `removeItem()`. El item desaparece del carrito.

### Botón eliminar (Trash)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón eliminar | `firstItem.locator('button').filter({ has: page.locator('svg') }).last()` | Ícono Trash2 size 20, color `#d1d5db`, hover: color `#ef4444` bg `#fef2f2` |

```js
// ✅ Test CC-005: Eliminar item
const deleteBtn = firstItem.locator('button').last(); // Trash2 es el último button del item
await deleteBtn.click();
```

### Stock check en el carrito

El `CartContext.updateQuantity()` limita la cantidad al `stock` del producto:
```js
// De CartContext.jsx línea 44:
const safeQty = maxStock ? Math.min(quantity, maxStock) : quantity;
```

Si `addItem()` se invoca para un producto que ya está en el carrito, solo incrementa qty si no supera `maxStock`. Si `stock === 0`, no agrega.

---

## 4. Método de entrega (Delivery Method)

### Ubicación
Dentro del panel "Resumen del pedido" (sidebar derecha en desktop, debajo de items en mobile).

### Opciones

| Método | Texto botón | Ícono | Border activo | Background activo | Color activo |
|--------|------------|-------|---------------|-------------------|-------------|
| **Despacho** | `Despacho` | Truck | `2px solid #00A8E8` | `#EBF8FF` | `#00A8E8` |
| **Retiro en tienda** | `Retiro en tienda` | Store | `2px solid #22C55E` | `#F0FDF4` | `#22C55E` |

```js
// ✅ Seleccionar despacho:
await page.getByRole('button', { name: /Despacho/i }).click();

// ✅ Seleccionar retiro:
await page.getByRole('button', { name: /Retiro en tienda/i }).click();

// Verificar mensaje de retiro gratis:
await expect(page.getByText('¡Retiro gratis!')).toBeVisible();
// Texto exacto: "🎉 ¡Retiro gratis! Recoge tu pedido en la tienda."
```

### Estado por defecto
- Si `module_delivery === false` → Solo muestra "Retiro en tienda" (sin opción de elegir)
- Si `module_delivery === true` → Default es `delivery` (despacho)

### Cuando `module_delivery` está deshabilitado

| Elemento | Selector | Notas |
|----------|---------|-------|
| Card fija | Div con border `2px solid #22C55E` | Solo muestra retiro |
| Texto | `Retiro en tienda` + `Delivery no disponible` | |

---

## 5. Formulario de dirección (solo cuando Despacho seleccionado)

### Tipo de dirección (3 botones toggle)

| Tipo | Texto | Ícono | Key del estado |
|------|-------|-------|---------------|
| Casa | `Casa` | Home | `casa` |
| Depto | `Depto` | Building2 | `departamento` |
| Oficina | `Oficina` | Building | `oficina` |

```js
// ✅ Seleccionar tipo:
await page.getByRole('button', { name: 'Casa' }).click();
await page.getByRole('button', { name: 'Depto' }).click();
await page.getByRole('button', { name: 'Oficina' }).click();
```

Estilo activo: border `2px solid #00A8E8`, background `#EBF8FF`, color `#00A8E8`

### Región (select)

| Detalle | Valor |
|---------|-------|
| Placeholder | `Selecciona región` |
| Opciones | 16 regiones de Chile (cargadas de `chileRegions.js`) |
| Al cambiar | Comuna se resetea a vacío |

```js
// ✅ Selector exacto (NO es el primer select general — está dentro del formulario de dirección):
await page.locator('select').filter({ has: page.locator('option[value="Metropolitana"]') }).selectOption('Metropolitana');
```

### Comuna (select)

| Detalle | Valor |
|---------|-------|
| Placeholder | `Selecciona comuna` |
| **Disabled** | Cuando no hay región seleccionada: `background: #f9fafb`, `cursor: not-allowed` |
| Se habilita | Cuando se selecciona región → carga comunas de esa región |

```js
// ✅ Selector (es el segundo select del formulario):
// NOTA: Esperar a que se habilite tras seleccionar región
await page.locator('select').nth(1).selectOption('Santiago');
```

### Calle y número (con autocomplete)

| Detalle | Valor |
|---------|-------|
| Placeholder exacto | `Calle y número (ej: Av. Providencia 1234)` |
| Disabled | Cuando no hay comuna seleccionada |
| Autocomplete | Nominatim (OpenStreetMap), se activa después de **4 caracteres**, con debounce de **500ms** |
| Ícono | Search (lupa) o ⏳ cuando está buscando |

```js
// ✅ Selector:
await page.locator('input[placeholder*="Calle y número"]').fill('Av. Providencia 1234');

// ✅ Esperar sugerencias de autocomplete:
await page.locator('input[placeholder*="Calle y número"]').fill('Av. Providencia');
// Las sugerencias aparecen como buttons dentro de un dropdown
// Cada sugerencia tiene ícono MapPin + texto de dirección
await page.locator('button').filter({ hasText: /Providencia/ }).first().click();
```

> ⚠️ **Autocomplete depende de Nominatim:** Si la API externa no responde, no se muestran sugerencias pero el campo sigue siendo editable manualmente. Al seleccionar una sugerencia, se calcula automáticamente el costo de envío por distancia.

### Detalle adicional (input)

| addressType | Placeholder exacto |
|-------------|-------------------|
| `casa` | `Referencia adicional (opcional)` |
| `departamento` | `Torre / Bloque / Nº Depto (ej: Torre B, Depto 502)` |
| `oficina` | `Piso / Oficina (ej: Piso 3, Of 301)` |

```js
// ✅ Selector (cambia placeholder según tipo de dirección):
// Si tipo es "departamento":
await page.locator('input[placeholder*="Torre"]').fill('Torre B, Depto 502');
// Si tipo es "oficina":
await page.locator('input[placeholder*="Piso"]').fill('Piso 3, Of 301');
// Si tipo es "casa":
await page.locator('input[placeholder*="Referencia"]').fill('Casa esquina azul');
```

### Badges de envío

| Condición | Texto exacto | Color |
|-----------|-------------|-------|
| Envío gratis (subtotal ≥ $39.990) | `🎉 ¡Envío GRATIS! Tu compra supera $39.990` | `#16a34a` (verde), bg `#F0FDF4` |
| Costo calculado | `🚚 Costo envío calculado (X km)` + precio | `#0077b6` (azul), bg `#f0faff` |
| Falta para envío gratis | `💡 Agrega $X más para envío gratis` | `#9ca3af` (gris) |
| Calculando | `⏳ Calculando costo de envío...` | `#00A8E8` |

### Validación de dirección completa
```js
// CartPage define:
const addressComplete = addressRegion && addressComuna && addressStreet.trim();
// El botón de pago se DESHABILITA si !addressComplete cuando delivery seleccionado
```

---

## 6. Cupón de descuento

### Input y botón

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Input código | `page.locator('input[placeholder="Código del cupón"]')` | textTransform `uppercase`, letterSpacing `1px` |
| Botón "Aplicar" | `page.getByRole('button', { name: /Aplicar/ })` | **disabled** si input vacío: bg `#e5e7eb`, color `#9ca3af`, cursor `not-allowed` |
| Botón habilitado | bg `#00A8E8`, color `#fff` | |
| Botón cargando | Texto `...` en vez de "Aplicar" | |

```js
// ✅ Aplicar cupón:
await page.locator('input[placeholder="Código del cupón"]').fill('PETSGO20');
await page.getByRole('button', { name: /Aplicar/ }).click();

// ✅ Aplicar con Enter:
await page.locator('input[placeholder="Código del cupón"]').fill('PETSGO20');
await page.keyboard.press('Enter'); // onKeyDown Enter → handleApplyCoupon()
```

### Cupón aplicado exitosamente

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Badge verde | Div con bg `#F0FDF4`, borderRadius 8px | |
| Código | `page.getByText('🎉 PETSGO20')` | fontWeight 700, color `#16a34a` |
| Descripción | Texto gris después del código (si existe) | fontSize 12px |
| Botón remover (X) | Ícono X size 16, color `#ef4444` | background none, border none |

```js
// ✅ Verificar cupón aplicado:
await expect(page.locator('[style*="F0FDF4"]').filter({ hasText: /🎉/ })).toBeVisible();

// ✅ Remover cupón aplicado (CC-047):
await page.locator('button').filter({ has: page.locator('svg') }).filter({ hasText: '' }).click(); // El botón X junto al cupón
// Alternativa más segura:
const couponBadge = page.locator('[style*="F0FDF4"]');
await couponBadge.locator('button').click();
```

### Línea de descuento en resumen

| Elemento | Texto | Color |
|----------|-------|-------|
| Label | `Descuento (CÓDIGO)` | `#16a34a` (verde), fontWeight 600 |
| Monto | `-$X.XXX` | `#16a34a`, fontWeight 700 |

### Errores de cupón (backend → frontend)

La variable `couponError` se muestra como `<p>` con `color: #ef4444`, `fontSize: 12px`, debajo del input.

| Error backend | Mensaje exacto mostrado |
|---------------|------------------------|
| `not_found` (404) | `Cupón no encontrado.` |
| `inactive` (400) | `Este cupón no está activo.` |
| `not_yet` (400) | `Este cupón aún no está vigente.` |
| `expired` (400) | `Este cupón ha expirado.` |
| `exhausted` (400) | `Este cupón ha alcanzado su límite de usos.` |
| `user_limit` (400) | `Ya usaste este cupón el máximo de veces permitido.` |
| `wrong_store` (400) | `Este cupón solo es válido para: {nombre_tienda}` |
| `min_purchase` (400) | `El monto mínimo de compra para este cupón es $XX.XXX` |
| `missing` (400) | `Ingresa un código de cupón.` |
| Fallback (red/error) | `Cupón inválido` |

```js
// ✅ Test CC-042: Cupón inexistente
await page.locator('input[placeholder="Código del cupón"]').fill('CODIGOFAKE');
await page.getByRole('button', { name: /Aplicar/ }).click();
await expect(page.getByText('Cupón no encontrado')).toBeVisible();

// ✅ Test CC-043: Cupón expirado
await page.locator('input[placeholder="Código del cupón"]').fill('CUPONVIEJO');
await page.getByRole('button', { name: /Aplicar/ }).click();
await expect(page.getByText('Este cupón ha expirado')).toBeVisible();

// ✅ Test CC-044: Monto mínimo no cumplido
await page.locator('input[placeholder="Código del cupón"]').fill('CUPONMIN');
await page.getByRole('button', { name: /Aplicar/ }).click();
await expect(page.getByText(/monto mínimo de compra/)).toBeVisible();
```

### Lógica de descuento (backend)
- **Porcentaje:** `discount = subtotal × (discount_value / 100)`. Si `max_discount > 0`, se limita.
- **Monto fijo:** `discount = discount_value`
- **Tope:** `if (discount > subtotal) discount = subtotal` — **nunca negativo**
- **Vendor restriction:** Cupón puede ser exclusivo de un vendor. Si el carrito no incluye productos de ese vendor, error `wrong_store`.

---

## 7. Método de pago

### Sección

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título | `page.getByText('Método de pago')` | fontSize 13px, fontWeight 700, con ícono CreditCard |

### Botones de pago

#### Transbank

| Detalle | Valor |
|---------|-------|
| Texto principal | `Transbank` |
| Subtexto | `Débito, Crédito, Prepago` |
| Badge | `TBK` — 36x24px, bg `#E4002B`, color `#fff`, fontSize 10px, fontWeight 800 |
| Border activo | `2px solid #E4002B` |
| Background activo | `#FFF0F3` |
| Color activo | `#E4002B` |
| Checkmark | CheckCircle size 18, color `#E4002B` (solo cuando seleccionado) |

```js
// ✅ Seleccionar Transbank:
await page.getByRole('button', { name: /Transbank/i }).click();
// Verificar seleccionado:
await expect(page.getByRole('button', { name: /Transbank/i })).toHaveCSS('border', '2px solid #E4002B');
```

#### Mercado Pago

| Detalle | Valor |
|---------|-------|
| Texto principal | `Mercado Pago` |
| Subtexto | `Tarjetas, transferencia, cuotas` |
| Badge | `MP` — 36x24px, bg `#009EE3`, color `#fff`, fontSize 10px |
| Border activo | `2px solid #009EE3` |
| Background activo | `#EBF8FF` |
| Color activo | `#009EE3` |
| Checkmark | CheckCircle size 18, color `#009EE3` |

```js
// ✅ Seleccionar MercadoPago:
await page.getByRole('button', { name: /Mercado Pago/i }).click();
```

#### Test bypass (solo para usuario de prueba)

| Detalle | Valor |
|---------|-------|
| Condición | Solo visible si `user.email === 'lmgm.0303@gmail.com'` |
| Badge amarillo | `🧪 Modo prueba — El pago se simula automáticamente` |
| Background | `#FFF3CD`, border `1px solid #FFE69C` |
| Comportamiento | No necesita seleccionar método — el botón cambia a "Confirmar (Modo Prueba)" |

```js
// ✅ Para testear con usuario de prueba:
// Login con lmgm.0303@gmail.com → el badge amarillo aparece
await expect(page.getByText('Modo prueba')).toBeVisible();
// No necesita seleccionar método de pago
```

### Mensaje si no se selecciona método

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Error | `page.getByText('Selecciona un método de pago para continuar')` | color `#ef4444`, fontSize 11px |

> Solo aparece si NO es test user Y no se ha seleccionado método.

---

## 8. Resumen de totales

### Líneas del resumen

| Línea | Label | Valor | Color |
|-------|-------|-------|-------|
| Subtotal | `Subtotal (N productos)` | `$XX.XXX` | Gris `#6b7280` / Negro `#1f2937` |
| Descuento | `Descuento (CÓDIGO)` | `-$XX.XXX` | Verde `#16a34a` (solo si cupón) |
| Envío | `Envío` | `¡Gratis!` o `$X.XXX` | Verde `#16a34a` si gratis, negro `#1f2937` si pagado |
| **Total** | `Total` | `$XX.XXX` | fontWeight 800, fontSize 26px, color `#00A8E8` |

```js
// ✅ Verificar total:
const totalElement = page.locator('span[style*="fontSize: 26"]').filter({ hasText: /\$/ });
// O más genérico:
const totalElement = page.locator('span[style*="color: #00A8E8"][style*="fontWeight: 800"]');
```

### Cálculo del total (verificado del código)
```js
// CartPage.jsx línea 230:
const isPickup = deliveryMethod === 'pickup';
const isFreeShipping = !isPickup && subtotal >= freeShippingMin; // freeShippingMin = $39.990
const shippingCost = isPickup ? 0 : (isFreeShipping ? 0 : (calculatedShipping ?? 2990));
const total = subtotal - discountAmount + shippingCost;
```

**Valores por defecto del sitio (de SiteContext):**
| Config | Valor default |
|--------|--------------|
| `free_shipping_min` | `$39.990` |
| `delivery_standard_cost` | `$2.990` |
| `delivery_base_rate` | `$2.000` |
| `delivery_per_km` | `$400` |
| `delivery_fee_min` | `$2.000` |
| `delivery_fee_max` | `$5.000` |

---

## 9. Botón principal — Pagar / Confirmar

### Variantes del texto del botón

| Condición | Texto exacto | Background |
|-----------|-------------|------------|
| Procesando | `⏳ Procesando...` | `#00A8E8` con opacity 0.7 |
| Test user | `🧪 Confirmar (Modo Prueba)` | `#00A8E8` |
| Retiro (pickup) | `🏪 Confirmar Retiro` | `#00A8E8` |
| Despacho + pago | `💳 Pagar y Confirmar` | `#00A8E8` |
| **Deshabilitado** | Cualquiera de los anteriores | `#9ca3af` (gris), cursor `not-allowed` |

```js
// ✅ Selector genérico que cubre todas las variantes:
await page.getByRole('button', { name: /Confirmar|Pagar|Procesando/ }).click();

// ✅ Para test específico de retiro:
await page.getByText('🏪 Confirmar Retiro').click();

// ✅ Para test de pago con pasarela:
await page.getByText('💳 Pagar y Confirmar').click();
```

### Condiciones para habilitar el botón

El botón está **deshabilitado** (gris + cursor not-allowed) cuando:
1. `ordering === true` (ya se está procesando)
2. Despacho seleccionado PERO dirección incompleta (`!addressComplete`)
3. NO es test user Y no se ha seleccionado método de pago (`!paymentMethod && !isTestUser`)

```js
// ✅ Verificar botón deshabilitado sin dirección completa:
await page.getByRole('button', { name: /Despacho/i }).click();
// No completar dirección...
await expect(page.getByRole('button', { name: /Pagar y Confirmar/ })).toBeDisabled();
```

### Si el usuario NO está autenticado

En vez del botón de pago, se muestra:

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Link login | `page.getByText('Inicia sesión para comprar')` | **Link a `/login`**, bg `#00A8E8`, borderRadius 12px |

```js
// ✅ Test: checkout sin sesión activa
await page.evaluate(() => { localStorage.removeItem('petsgo_token'); localStorage.removeItem('petsgo_user'); });
await page.goto('/carrito');
await expect(page.getByText('Inicia sesión para comprar')).toBeVisible();
```

---

## 10. Botón "Vaciar carrito"

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón | `page.getByText('Vaciar carrito')` | background none, color `#f87171` (rojo suave), hover: bg `#fef2f2` color `#ef4444` |

```js
// ✅ Test CC-006:
await page.getByText('Vaciar carrito').click();
// ⚠️ NO hay modal de confirmación — vacía directamente
// clearCart() de CartContext: items=[], appliedCoupon=null
await expect(page.getByText('Tu carrito está vacío')).toBeVisible();
```

> ⚠️ **SIN confirmación:** El botón "Vaciar carrito" llama a `clearCart()` directamente sin prompt. El test CC-006 dice "Confirmar" pero **no hay modal de confirmación**.

---

## 11. Beneficios (visual, debajo del botón)

| Texto | Ícono | Color |
|-------|-------|-------|
| `Envío gratis desde $39.990` | Truck | `#00A8E8` |
| `Compra 100% segura` | Shield | `#00A8E8` |

---

## 12. Flujo de creación de pedido (backend)

### Proceso al hacer click en el botón de pagar

1. **Resolver vendor_ids faltantes:** Si algún item no tiene `vendor_id`, llama a `getProductDetail(id)` para obtenerlo
2. **Agrupar por vendor:** Los items se dividen en sub-pedidos, uno por vendor (multi-vendor)
3. **Generar `purchase_group`:** UUID que vincula todos los pedidos de este checkout
4. **Para cada grupo de vendor:**
   - `POST /wp-json/petsgo/v1/orders` con datos del vendor
   - Backend crea orden con estado `pending`
   - Backend descuenta stock (`stock = GREATEST(stock - qty, 0)`)
   - Backend auto-genera boleta (invoice)
   - Si test user → `payment_status = 'paid'`
   - Si pasarela real → `payment_status = 'pending_payment'`
5. **Limpiar carrito:** `clearCart()` — items=[], cupón=null
6. **Mostrar modal de confirmación**

### Datos enviados al backend (POST /orders)

```js
{
  vendor_id: 1,
  items: [{ product_id: 123, quantity: 2, price: 9990 }],
  total: 19980,
  delivery_method: 'delivery', // 'delivery' | 'pickup'
  delivery_fee: 2990,
  delivery_distance_km: 5.3,
  shipping_address: 'Av. Providencia 1234, Santiago, Metropolitana',
  shipping_region: 'Metropolitana',
  shipping_comuna: 'Santiago',
  address_detail: 'Depto 502',
  address_type: 'departamento',
  coupon_code: 'PETSGO20',
  payment_method: 'transbank', // 'transbank' | 'mercadopago' | 'test_bypass'
  purchase_group: 'uuid-xxx'
}
```

### Respuesta exitosa del backend

```json
{
  "order_id": 456,
  "message": "Orden creada",
  "commission_logged": 1998,
  "discount_amount": 3996,
  "coupon_code": "PETSGO20",
  "payment_method": "transbank",
  "payment_status": "pending_payment",
  "is_test_user": false
}
```

### Errores del backend

| Código | Mensaje |
|--------|---------|
| 400 | `Faltan datos` (vendor_id, items, total missing) |
| 404 | `Vendedor no existe (id=X)` |
| 500 | `Error al crear la orden` (DB error) |

### Error en frontend

```js
// CartPage.jsx línea 918:
catch (err) {
  const apiMsg = err?.response?.data?.message || err?.message || 'Error desconocido';
  alert(`Error al procesar tu pedido: ${apiMsg}`);
}
```

> ⚠️ **Usa `alert()` nativo del browser**, no toast. Para capturar en Playwright:
```js
page.on('dialog', async dialog => {
  expect(dialog.message()).toContain('Error al procesar tu pedido');
  await dialog.accept();
});
```

---

## 13. Modal de confirmación de pedido (OrderConfirmModal)

### Cuándo aparece
Después de que todos los pedidos se crean exitosamente. El carrito se vacía pero el modal se mantiene visible.

### Header del modal

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Overlay | Fondo `rgba(0,0,0,0.6)`, zIndex 9999, con blur | |
| Checkmark | CheckCircle 36px, color blanco, dentro de círculo con bg `rgba(255,255,255,0.2)` | |
| Título | `page.getByText('¡Pedido Confirmado!')` | `<h2>`, fontSize 22px, fontWeight 800, color `#fff` |
| Subtítulo | Muestra método de entrega + método de pago | |

Subtítulos posibles:
- `📦 Retiro en tienda` o `🚚 Envío a domicilio`
- `🧪 Pago simulado` o `💳 Transbank` o `💳 Mercado Pago`

### Cards de pedidos

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Card pedido | Div con bg `#f0faff`, border `1px solid #d0ecf9`, borderRadius 10px | |
| ID pedido | `page.getByText(/Pedido #\d+/)` | fontWeight 700, color `#00A8E8` |
| Nombre tienda | Span fontSize 12px, color `#6b7280` | |
| Status pagado | `page.getByText('✅ Pagado')` | bg `#d4edda`, color `#155724` |
| Status pendiente | `page.getByText('⏳ Pendiente pago')` | bg `#fff3cd`, color `#856404` |

### Detalle de productos (en modal)

| Elemento | Notas |
|----------|-------|
| Thumbnail | 44x44px, borderRadius 8px |
| Nombre | fontWeight 600, fontSize 13px |
| Store + qty | `{store_name} · x{quantity}` |
| Subtotal | fontWeight 700, fontSize 14px |

### Totales (en modal)

| Línea | Texto | Notas |
|-------|-------|-------|
| Subtotal | `Subtotal (N productos)` | fontSize 13px, color `#6b7280` |
| Descuento | `🏷️ Descuento (CODE)` con `-$X.XXX` | Solo si hay descuento, color `#16a34a` |
| Envío | `Envío` → `¡Gratis!` o `$X.XXX` | Verde si gratis |
| **Total pagado** | `Total pagado` → `$XX.XXX` | fontWeight 800, fontSize 24px, color `#00A8E8` |

### Información de entrega (en modal)

**Si retiro en tienda:**
| Texto | Color |
|-------|-------|
| `Recoge tu pedido directamente en la tienda` | `#856404`, bg `#FFF9E6` |

**Si despacho:**
| Info | Formato |
|------|---------|
| Dirección | `📍 {street}` |
| Detalle | `🏢 {detail}` (si existe) |
| Comuna/Región | `📌 {comuna}, {region}` |
| Tipo | `🏠 Casa` / `Departamento` / `Oficina` |

### Botones de acción (en modal)

| Botón | Texto exacto | Acción | Estilo |
|-------|-------------|--------|--------|
| Ver mis pedidos | `📋 Ver mis pedidos` | Cierra modal + navega a `/mis-pedidos` | bg `#00A8E8`, color `#fff`, fontWeight 700 |
| Seguir comprando | `🛍️ Seguir comprando` | Cierra modal + navega a `/` | bg `#f3f4f6`, color `#374151`, fontWeight 600 |

```js
// ✅ Test CC-140 y CC-143:
await expect(page.getByText('¡Pedido Confirmado!')).toBeVisible({ timeout: 15000 });
await expect(page.getByText(/Pedido #\d+/)).toBeVisible();

// Click "Ver mis pedidos":
await page.getByText('Ver mis pedidos').click();
await expect(page).toHaveURL('/mis-pedidos');
```

### Footer del modal

| Texto | Notas |
|-------|-------|
| `🐾 ¡Gracias por comprar en PetsGo! Recibirás actualizaciones sobre tu pedido.` | fontSize 12px, color `#9ca3af` |

---

## 14. Tests que deben SALTARSE (test.skip)

| Test ID | Razón | Código |
|---------|-------|--------|
| CC-085 | Webhook Transbank — requiere servidor externo | `test.skip(true, 'MANUAL: Webhook de pasarela de pago externa')` |
| CC-104 | Webhook MercadoPago — requiere servidor externo | `test.skip(true, 'MANUAL: Webhook de pasarela de pago externa')` |
| CC-141 | Verificar email de confirmación al cliente | `test.skip(true, 'MANUAL: Requiere acceso a bandeja de correo')` |
| CC-142 | Verificar email de notificación al vendor | `test.skip(true, 'MANUAL: Requiere acceso a bandeja de correo')` |
| CC-082 | Pago exitoso en Webpay sandbox | `test.skip(true, 'MANUAL: Requiere redirección a sandbox Transbank real')` |
| CC-083 | Pago rechazado en Webpay | `test.skip(true, 'MANUAL: Requiere interacción con sandbox Transbank')` |
| CC-084 | Cancelar pago en Webpay | `test.skip(true, 'MANUAL: Requiere interacción con sandbox Transbank')` |
| CC-102 | Pago exitoso MercadoPago sandbox | `test.skip(true, 'MANUAL: Requiere redirección a sandbox MercadoPago')` |
| CC-103 | Pago rechazado MercadoPago | `test.skip(true, 'MANUAL: Requiere interacción con sandbox MercadoPago')` |

> **CC-081, CC-100, CC-101:** Los tests de "redirigir a pasarela" **SÍ se pueden automatizar parcialmente** — se puede verificar que al hacer click se inicia una redirección a la URL de la pasarela (interceptar el request).

---

## 15. Flujo completo recomendado para test automatizable (usando Test Bypass)

Para los tests de checkout automatizables, usar el **modo prueba (test_bypass)** que simula el pago sin pasarela real. Requiere login con `lmgm.0303@gmail.com`.

```js
test('CC-120: Test bypass crea pedido directamente', async ({ page }) => {
  // 1. Login como test user
  await login(page, 'lmgm.0303@gmail.com', process.env.ADMIN_PASSWORD);

  // 2. Agregar producto al carrito
  await page.goto('/categoria/Alimento');
  await page.waitForLoadState('networkidle');
  const product = page.locator('a[href*="/producto/"]').first();
  await product.click();
  await page.waitForLoadState('networkidle');
  await page.getByText('Agregar al carrito').click();
  
  // 3. Esperar FloatingCart y cerrar (o esperar auto-cierre 3s)
  await page.waitForTimeout(3500); // Auto-close del FloatingCart
  
  // 4. Ir al carrito
  await page.goto('/carrito');
  await page.waitForLoadState('networkidle');

  // 5. Verificar badge de modo prueba
  await expect(page.getByText('Modo prueba')).toBeVisible();

  // 6. Seleccionar retiro en tienda (evita llenar dirección)
  await page.getByRole('button', { name: /Retiro en tienda/i }).click();

  // 7. Confirmar (no necesita seleccionar método de pago)
  await page.getByText('🧪 Confirmar (Modo Prueba)').click();

  // 8. Esperar modal de confirmación
  await expect(page.getByText('¡Pedido Confirmado!')).toBeVisible({ timeout: 15000 });
  await expect(page.getByText('✅ Pagado')).toBeVisible();

  // 9. Screenshot de evidencia
  await page.screenshot({ path: 'evidencias/checkout-CC120-pass.png', fullPage: true });
});
```

---

## 16. Mapeo QA-03 → Selectores / Notas para cada caso

| ID | Test | Selector/Notas clave |
|----|------|---------------------|
| CC-001 | Agregar producto (cliente) | `page.getByText('Agregar al carrito')` → FloatingCart se abre → verificar badge header +1 |
| CC-002 | Agregar como invitado | Carrito funciona sin auth (CartContext es in-memory) |
| CC-003 | Ver carrito | `page.goto('/carrito')` → `page.locator('h1')` contiene "Mi Carrito" |
| CC-004 | Modificar cantidad | `qtyContainer.locator('button')` first/last → span actualiza |
| CC-005 | Eliminar item | Botón Trash2 → item desaparece |
| CC-006 | Vaciar carrito | `page.getByText('Vaciar carrito')` — **SIN confirmación** |
| CC-007 | Misma variante incrementa qty | `addItem()` → find existing → qty+1 |
| CC-008 | Stock limita qty | `Math.min(quantity, maxStock)` en CartContext |
| CC-009 | Persistencia al navegar | CartContext es in-memory → persiste mientras no recargue la página |
| CC-010 | Múltiples vendors | Items de distintos vendors → agrupados en checkout |
| CC-011 | Precio actualizado | ⚠️ El carrito NO actualiza precios dinámicamente (usa precio del momento de agregar) |
| CC-012 | Carrito vacío → CTA | `page.getByText('Explorar Productos')` → link a `/` |
| CC-020 | Ir al checkout | **NO hay /checkout** — todo en `/carrito` |
| CC-021 | Precarga datos perfil | ⚠️ **NO precarga** — el form de dirección empieza vacío |
| CC-022 | Dirección guardada | ⚠️ **NO hay selector de direcciones guardadas** — solo un form |
| CC-023 | Nueva dirección | El form de dirección ES la nueva dirección (no hay "lista de guardadas") |
| CC-024 | Región → Comunas | Select región → comunas dinámicas |
| CC-025 | Dirección obligatoria | Botón disabled si `!addressComplete` |
| CC-026 | Notas opcionales | ⚠️ **NO existe campo de notas** en CartPage |
| CC-040 | Cupón porcentaje | `input[placeholder="Código del cupón"]` + `Aplicar` → badge verde |
| CC-041 | Cupón monto fijo | Mismo flujo, descuento directo |
| CC-042 | Cupón inexistente | Error: `Cupón no encontrado.` |
| CC-043 | Cupón expirado | Error: `Este cupón ha expirado.` |
| CC-044 | Monto mínimo | Error: `El monto mínimo de compra para este cupón es $X` |
| CC-045 | Cupón ya usado | Error: `Ya usaste este cupón el máximo de veces permitido.` |
| CC-046 | Cupón multi-uso | Aplica descuento, `usage_count++` en backend |
| CC-047 | Remover cupón | Click X en badge verde → total restaurado |
| CC-048 | Descuento > total | Backend: `if (discount > subtotal) discount = subtotal` → total ≥ 0 |
| CC-060 | Costo envío por zona | Calculado vía Nominatim lat/lon → `calculateDeliveryFee()` |
| CC-061 | Envío gratis | `subtotal >= $39.990` → `🎉 ¡Envío GRATIS!` |
| CC-062 | Opciones envío | ⚠️ **NO hay radio buttons de opciones** — solo un precio calculado |
| CC-063 | Total = subtotal + envío - descuento | Verificar en resumen |
| CC-080 | Seleccionar Transbank | `page.getByRole('button', { name: /Transbank/i }).click()` |
| CC-081 | Redirigir a Webpay | Click "Pagar y Confirmar" → interceptar redirect |
| CC-100 | Seleccionar MercadoPago | `page.getByRole('button', { name: /Mercado Pago/i }).click()` |
| CC-101 | Redirigir a MercadoPago | Click "Pagar y Confirmar" → interceptar redirect |
| CC-120 | Test bypass | Login con test user → `🧪 Confirmar (Modo Prueba)` → `¡Pedido Confirmado!` |
| CC-121 | Test bypass NO en prod | Verificar que badge amarillo NO aparece con user normal |
| CC-140 | Página de confirmación | Modal `¡Pedido Confirmado!` con resumen |
| CC-143 | Link "Ver mis pedidos" | `page.getByText('Ver mis pedidos')` → navega a `/mis-pedidos` |

---

## 17. Discrepancias importantes entre QA-03 y la implementación real

| Test ID | Caso QA-03 dice | Realidad del código | Acción |
|---------|-----------------|---------------------|--------|
| CC-006 | "Vaciar carrito → Confirmar" | **NO hay confirmación**, vacía directamente | Ajustar test para no esperar modal de confirm |
| CC-011 | "Precio actualizado al cambiar" | El carrito usa el precio del momento de agregar — **no se actualiza dinámicamente** | El test puede no ser verificable sin refrescar |
| CC-020 | "Navega a /checkout" | **NO existe /checkout** — todo en `/carrito` | Reescribir test para verificar la sección de checkout inline |
| CC-021 | "Precarga datos del perfil" | **NO precarga** — el form empieza vacío | Marcar como discrepancia con UX esperado |
| CC-022 | "Selector de direcciones guardadas" | **NO existe** — solo un form manual | Ajustar o skip |
| CC-026 | "Campo Notas de pedido" | **NO existe** campo de notas en CartPage | Skip con nota |
| CC-062 | "Radio buttons con opciones de envío" | **NO hay opciones** — solo un precio calculado automáticamente | Ajustar test |

---

## 18. Datos de Transbank Sandbox

Para tests futuros (si se automatizan las pasarelas):

| Campo | Valor |
|-------|-------|
| N° Tarjeta | `4051 8856 0044 6623` |
| Fecha exp. | Cualquier fecha futura |
| CVV | `123` |
| RUT | `11.111.111-1` |
| Clave | `123` |

---

## 19. Mitigación de rate-limiting y flakiness

```js
// OBLIGATORIO — pausa entre tests:
test.afterEach(async () => {
  await new Promise(resolve => setTimeout(resolve, 2500));
});

// Para el flujo completo de compra:
test.setTimeout(60000); // 60 segundos timeout

// Ojo con el FloatingCart — esperar que se cierre o ir directo:
await page.goto('/carrito'); // Salta el FloatingCart
```

### Carrito en memoria (no localStorage)
El `CartContext` guarda items **en memoria** (useState), NO en localStorage. Esto significa:
- Al recargar la página, el carrito se **vacía**
- Para tests que necesitan carrito con items, **agregar productos dentro del mismo test** (no depender de tests anteriores)
- Para tests secuenciales, usar `test.describe.serial()` si necesitas estado compartido

```js
// ✅ CORRECTO — agregar producto antes de testar el carrito:
test('CC-004: Modificar cantidad en carrito', async ({ page }) => {
  // Primero agregar algo al carrito
  await page.goto('/categoria/Alimento');
  await page.waitForLoadState('networkidle');
  await page.locator('a[href*="/producto/"]').first().click();
  await page.waitForLoadState('networkidle');
  await page.getByText('Agregar al carrito').click();
  await page.waitForTimeout(3500); // Esperar auto-cierre FloatingCart
  
  // Ahora ir al carrito y testar
  await page.goto('/carrito');
  await page.waitForLoadState('networkidle');
  // ... test de cantidad
});
```
