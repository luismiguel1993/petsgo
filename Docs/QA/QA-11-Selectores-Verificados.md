# QA-11 — Selectores Verificados: Tienda PetsGo (Admin)

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `AdminDashboard.jsx` (tab "Tienda PetsGo"), `petsgo-core.php` (endpoints `/admin/inventory`)  
**Propósito:** Selectores Playwright verificados contra el código real para la gestión de productos de la Tienda PetsGo oficial desde el panel admin.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

---

## 1. Ubicación en la UI

La "Tienda PetsGo" es el **3er tab** (índice 2) del `AdminDashboard.jsx`. Su key interna es `store`.

| Elemento | Selector verificado |
|----------|-------------------|
| Tab "Tienda PetsGo" | `page.locator('button').filter({ hasText: '🏪 Tienda PetsGo' })` |
| Tab activa (estilo) | `background: #2F3A40; color: #FFC400` |
| Tab inactiva | `background: white; color: #6B7280` |

```js
// ✅ Navegar al tab:
await page.goto('/admin');
await page.waitForLoadState('networkidle');
await page.locator('button').filter({ hasText: '🏪 Tienda PetsGo' }).click();
```

---

## 2. Formulario de Producto

### Título del formulario

| Estado | Texto | Selector |
|--------|-------|----------|
| Crear | `"Nuevo Producto PetsGo"` | `page.getByText('Nuevo Producto PetsGo')` |
| Editar | `"Editar Producto"` | `page.getByText('Editar Producto')` |

### Campos del formulario

| Campo | Selector verificado | Tipo | Placeholder / Notas |
|-------|-------------------|------|---------------------|
| Nombre | `page.locator('input[placeholder="Ej: Royal Canin Adulto 15kg"]')` | text | Placeholder exacto |
| Categoría | `page.locator('select').filter({ has: page.locator('option[value=""]') })` en contexto del form | select | Primera opción: `"Seleccionar categoría"` |
| Precio | `page.locator('input[type="number"][min="0"][step="1"]').first()` | number | min=0, step=1 |
| Stock | `page.locator('input[type="number"][min="0"][step="1"]').nth(1)` | number | min=0, step=1 |
| Descripción | `page.locator('textarea[placeholder="Descripción del producto"]')` | textarea | Placeholder exacto |
| Imagen | `page.locator('input[type="file"][accept="image/*"]')` | file | **Hidden** — se activa por botón |

### Botón de subir imagen

| Estado | Selector | Notas |
|--------|----------|-------|
| Sin imagen | `page.getByRole('button', { name: /Seleccionar Imagen/i })` | Texto: "📷 Seleccionar Imagen" |
| Con imagen | Preview `<img>` visible + botón cambia a "Cambiar Imagen" | |
| Límite | Max 5 MB, tipos: jpeg, png, webp, gif | Validación frontend |

```js
// ✅ Subir imagen (con fileChooser):
test.skip('TP-044: Subir imagen de producto', async ({ page }) => {
  test.skip(true, 'MANUAL: Requiere file picker del OS para subir imagen');
});
```

### Categorías disponibles (del endpoint `/categories`)

Las categorías se cargan dinámicamente via `GET /wp-json/petsgo/v1/categories`. Valores típicos:

| Categoría | ID (variable) |
|-----------|--------------|
| Perros | Varía |
| Gatos | Varía |
| Alimento | Varía |
| Snacks | Varía |
| Farmacia | Varía |
| Juguetes | Varía |
| Accesorios | Varía |
| Higiene | Varía |

```js
// ✅ Seleccionar categoría:
await page.locator('select').filter({ has: page.locator('option[value=""]') }).selectOption({ label: 'Perros' });
```

### Botones de acción del formulario

| Botón | Selector | Cuándo aparece |
|-------|----------|---------------|
| Crear producto | `page.getByRole('button', { name: /Crear Producto/i })` | Modo crear |
| Guardar cambios | `page.getByRole('button', { name: /Guardar Cambios/i })` | Modo editar |
| Cancelar edición | `page.getByRole('button', { name: /Cancelar/i })` | Modo editar |

---

## 3. Tabla de Productos

### Estructura

| Columna | Header | Selector datos |
|---------|--------|---------------|
| Producto | `Producto` | Nombre + imagen thumbnail |
| Categoría | `Categoría` | Nombre de categoría |
| Precio | `Precio` | Formato `$XX.XXX` |
| Stock | `Stock` | Número |
| Estado | `Estado` | Botón toggle "✅ Activo" / "❌ Inactivo" |
| Acciones | `Acciones` | Botones Editar + Eliminar |

```js
// ✅ Tabla completa:
const tabla = page.locator('table');
await expect(tabla).toBeVisible();

// ✅ Filas de datos:
const filas = tabla.locator('tbody tr');
const count = await filas.count();
```

### Estado vacío

| Elemento | Selector |
|----------|----------|
| Mensaje vacío | `page.getByText('La Tienda PetsGo aún no tiene productos')` |

### Paginación

| Config | Valor |
|--------|-------|
| Items por página | **5** (`ITEMS_PER_PAGE = 5`) |
| Botón anterior | `page.getByRole('button', { name: /Anterior/i })` |
| Botón siguiente | `page.getByRole('button', { name: /Siguiente/i })` |
| Indicador página | `page.getByText(/Página \d+ de \d+/)` |

```js
// ✅ Verificar paginación:
const indicador = page.getByText(/Página \d+ de \d+/);
await expect(indicador).toBeVisible();
```

---

## 4. Acciones por Producto

### Toggle Estado (Activo/Inactivo)

| Estado actual | Botón | Selector | API call |
|--------------|-------|----------|----------|
| Activo | `✅ Activo` (verde) | `page.getByRole('button', { name: /Activo/i }).nth(index)` | `PUT /admin/inventory/{id}/toggle` |
| Inactivo | `❌ Inactivo` (rojo) | `page.getByRole('button', { name: /Inactivo/i }).nth(index)` | `PUT /admin/inventory/{id}/toggle` |

```js
// ✅ Toggle estado:
test('TP-060: Toggle activo/inactivo', async ({ page }) => {
  await login(page, process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD);
  await page.goto('/admin');
  await page.locator('button').filter({ hasText: '🏪 Tienda PetsGo' }).click();
  
  // Click en el primer botón de estado
  const toggleBtn = page.getByRole('button', { name: /Activo|Inactivo/i }).first();
  const textoAntes = await toggleBtn.textContent();
  await toggleBtn.click();
  
  // Esperar API response
  await page.waitForResponse(resp => 
    resp.url().includes('/admin/inventory/') && resp.url().includes('/toggle')
  );
  
  // Verificar que cambió
  const textoDespues = await page.getByRole('button', { name: /Activo|Inactivo/i }).first().textContent();
  expect(textoDespues).not.toBe(textoAntes);
});
```

### Editar Producto

| Elemento | Selector |
|----------|----------|
| Botón editar (en fila) | `page.getByRole('button', { name: /Editar/i }).nth(index)` → en la fila correspondiente |

**Flujo:** Click editar → formulario se rellena con datos actuales → título cambia a "Editar Producto" → campos editables → "Guardar Cambios" o "Cancelar".

### Eliminar Producto

| Elemento | Selector |
|----------|----------|
| Botón eliminar (en fila) | `page.getByRole('button', { name: /Eliminar/i }).nth(index)` |
| Confirmación | `window.confirm()` nativo del navegador |

```js
// ✅ Eliminar con confirmación:
page.on('dialog', dialog => dialog.accept()); // Auto-aceptar confirm
await page.getByRole('button', { name: /Eliminar/i }).first().click();
```

---

## 5. API Endpoints Completos

### GET `/wp-json/petsgo/v1/admin/inventory`

**Auth:** Admin required  
**Retorna:** Array de productos de la tienda PetsGo oficial.

### POST `/wp-json/petsgo/v1/admin/inventory`

**Auth:** Admin required  
**Body:**
```json
{
  "name": "Royal Canin Adulto 15kg",
  "category_id": 5,
  "price": 45990,
  "stock": 50,
  "description": "Alimento premium para perros adultos",
  "image_url": "https://..."
}
```

**Nota backend:** No valida campos requeridos — inserta vacío si no se envían. La validación debe ser frontend.

### PUT `/wp-json/petsgo/v1/admin/inventory/{id}`

**Auth:** Admin required  
**Body:** Mismos campos que POST. Verifica ownership del producto.

### DELETE `/wp-json/petsgo/v1/admin/inventory/{id}`

**Auth:** Admin required  
**Verifica:** Que el producto pertenezca a la tienda PetsGo.

### PUT `/wp-json/petsgo/v1/admin/inventory/{id}/toggle`

**Auth:** Admin required  
**Alterna:** `status` entre `active` e `inactive`.

### POST `/wp-json/petsgo/v1/admin/inventory/upload-image`

**Auth:** Admin required  
**Body:** `multipart/form-data` con campo `image`.  
**Validación:** Max 5MB, tipos: jpeg, png, webp, gif.  
**Retorna:** `{ "url": "https://..." }`

---

## 6. Flujo E2E: Crear Producto

```js
test('TP-020: Crear producto en Tienda PetsGo', async ({ page }) => {
  await login(page, process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD);
  await page.goto('/admin');
  await page.waitForLoadState('networkidle');
  
  // 1. Ir al tab Tienda PetsGo
  await page.locator('button').filter({ hasText: '🏪 Tienda PetsGo' }).click();
  
  // 2. Verificar formulario visible
  await expect(page.getByText('Nuevo Producto PetsGo')).toBeVisible();
  
  // 3. Llenar formulario
  await page.locator('input[placeholder="Ej: Royal Canin Adulto 15kg"]').fill('Producto Test QA');
  
  // Seleccionar categoría (esperar a que el select tenga opciones cargadas)
  const catSelect = page.locator('select').filter({ has: page.locator('option[value=""]') });
  await catSelect.selectOption({ index: 1 }); // Primera categoría real
  
  // Precio
  await page.locator('input[type="number"][min="0"]').first().fill('9990');
  
  // Stock
  await page.locator('input[type="number"][min="0"]').nth(1).fill('100');
  
  // Descripción
  await page.locator('textarea[placeholder="Descripción del producto"]').fill('Producto creado por QA automation');
  
  // 4. Crear (sin imagen por ahora)
  const responsePromise = page.waitForResponse(resp => 
    resp.url().includes('/admin/inventory') && resp.request().method() === 'POST'
  );
  await page.getByRole('button', { name: /Crear Producto/i }).click();
  const response = await responsePromise;
  expect(response.status()).toBe(200);
  
  // 5. Verificar producto en tabla
  await expect(page.getByText('Producto Test QA')).toBeVisible();
  
  await page.screenshot({ path: 'evidencias/tienda-petsgo-TP020-pass.png', fullPage: true });
});
```

---

## 7. Flujo E2E: Editar Producto

```js
test('TP-040: Editar producto existente', async ({ page }) => {
  await login(page, process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD);
  await page.goto('/admin');
  await page.locator('button').filter({ hasText: '🏪 Tienda PetsGo' }).click();
  await page.waitForLoadState('networkidle');
  
  // 1. Click editar en primer producto
  await page.getByRole('button', { name: /Editar/i }).first().click();
  
  // 2. Verificar que formulario cambió a modo edición
  await expect(page.getByText('Editar Producto')).toBeVisible();
  
  // 3. Modificar nombre
  const nameInput = page.locator('input[placeholder="Ej: Royal Canin Adulto 15kg"]');
  await nameInput.clear();
  await nameInput.fill('Producto Editado QA');
  
  // 4. Guardar
  const responsePromise = page.waitForResponse(resp => 
    resp.url().includes('/admin/inventory/') && resp.request().method() === 'PUT'
  );
  await page.getByRole('button', { name: /Guardar Cambios/i }).click();
  await responsePromise;
  
  // 5. Verificar cambio en tabla
  await expect(page.getByText('Producto Editado QA')).toBeVisible();
});
```

---

## 8. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selector/Verificación |
|----|------|----------------------|
| TP-001 | Acceso al tab Tienda PetsGo | `page.locator('button').filter({ hasText: '🏪 Tienda PetsGo' })` click |
| TP-002 | Tab activa resaltada | Verificar estilo `background: #2F3A40; color: #FFC400` |
| TP-010 | Tabla con columnas | 6 headers: Producto, Categoría, Precio, Stock, Estado, Acciones |
| TP-011 | Estado vacío | `page.getByText('La Tienda PetsGo aún no tiene productos')` |
| TP-020 | Crear producto | Form → fill → "Crear Producto" click |
| TP-021 | Crear sin nombre | Submit vacío → verificar que no se crea (backend no valida) |
| TP-030 | Categoría select | Select con opciones de `/categories` |
| TP-040 | Editar producto | Click "Editar" → form "Editar Producto" → "Guardar Cambios" |
| TP-041 | Cancelar edición | Click "Cancelar" → form vuelve a "Nuevo Producto PetsGo" |
| TP-044 | Subir imagen | **SKIP** — requiere file picker |
| TP-050 | Eliminar producto | Click "Eliminar" → confirm dialog → producto desaparece |
| TP-060 | Toggle activo/inactivo | Click botón "✅ Activo" ↔ "❌ Inactivo" |
| TP-070 | Paginación | "Anterior"/"Siguiente", "Página X de Y" |
| TP-080 | Precio formateado | `$XX.XXX` en tabla |
| TP-090 | Stock muestra número | Número entero en columna Stock |
| TP-100 | API: GET inventory | `GET /admin/inventory` → 200 con array |
| TP-101 | API: POST inventory | `POST /admin/inventory` → 200 |
| TP-102 | API: PUT inventory | `PUT /admin/inventory/{id}` → 200 |
| TP-103 | API: DELETE inventory | `DELETE /admin/inventory/{id}` → 200 |
| TP-104 | API: Toggle | `PUT /admin/inventory/{id}/toggle` → 200 |
| TP-105 | API: Upload image | `POST /admin/inventory/upload-image` → 200 con URL |

---

## 9. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| TP-021 | "Validar campos requeridos" | Backend **no valida** campos requeridos en POST — inserta vacío | Test debe verificar validación frontend, no backend |
| TP-025 | "Modal de creación" | El formulario es **inline** (encima de la tabla), NO un modal | Diferente UX pero misma funcionalidad |
| TP-044 | "Arrastrar imagen" | Solo `input[type=file]` — no hay drag & drop | Solo click para seleccionar |
| TP-070 | "Búsqueda de productos" | **NO hay input de búsqueda** en el tab Tienda PetsGo | **NO IMPLEMENTADO** |
| TP-071 | "Filtrar por categoría" | **NO hay filtros** en la tabla | **NO IMPLEMENTADO** |
| TP-072 | "Ordenar por columna" | **NO hay sorting** en la tabla | **NO IMPLEMENTADO** |
| TP-090 | "Historial de cambios" | **NO implementado** | **NO IMPLEMENTADO** |
| TP-100-105 | "Gestión de variantes" | **NO implementado** — productos sin variantes | **NO IMPLEMENTADO** |
| TP-120 | "Importar/exportar CSV" | **NO implementado** | **NO IMPLEMENTADO** |
| TP-130 | "Descuentos por producto" | **NO implementado** en este tab | **NO IMPLEMENTADO** |
| TP-140 | "Auto-crear vendor PetsGo" | Backend crea vendor automáticamente si no existe (`get_or_create_petsgo_store()`) | Funcionalidad backend, no verificable por UI directamente |

---

## 10. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| TP-044, TP-045 | Subir/cambiar imagen — requiere file picker del OS |
| TP-070 | Búsqueda de productos no implementada |
| TP-071 | Filtrar por categoría no implementado |
| TP-072 | Ordenar por columna no implementado |
| TP-090 | Historial de cambios no implementado |
| TP-100-105 | Gestión de variantes no implementada |
| TP-120 | Importar/exportar CSV no implementado |
| TP-130 | Descuentos por producto no implementado |

---

## 11. Notas adicionales

### Auto-creación de vendor PetsGo

La función backend `get_or_create_petsgo_store()` asegura que exista un vendor "Tienda PetsGo" (user_id del primer admin). Si no existe, se crea automáticamente la primera vez que se accede al endpoint de inventario.

### Formato de precios

Los precios se almacenan como enteros (centavos no, pesos chilenos enteros). El formato visual en tabla es `$XX.XXX` con separador de miles usando punto.

### Relación con catálogo público

Los productos creados en "Tienda PetsGo" aparecen en el catálogo público (`/tienda/{id}`) como cualquier otro producto de vendor. El vendor_id es el del admin "PetsGo Oficial".
