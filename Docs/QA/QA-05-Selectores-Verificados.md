# QA-05 — Selectores Verificados: Dashboard Vendor

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `VendorDashboard.jsx` (~900 líneas), `petsgo-core.php`  
**Propósito:** Selectores Playwright verificados contra el código real para tests del Dashboard Vendor.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

---

## 1. Navegación por Tabs (4 tabs implementados)

| Tab | Texto visible | Emoji | Selector |
|-----|--------------|-------|----------|
| Dashboard | `📊 Dashboard` | 📊 | `page.getByRole('button', { name: /Dashboard/i })` |
| Inventario | `📦 Inventario` | 📦 | `page.getByRole('button', { name: /Inventario/i })` |
| Pedidos | `📥 Pedidos` | 📥 | `page.getByRole('button', { name: /Pedidos/i })` |
| Cupones | `🎫 Cupones` | 🎫 | `page.getByRole('button', { name: /Cupones/i })` |

**Estilos de tab:**
- Activo: `background: #00A8E8`, `color: #fff`, fontWeight 700
- Inactivo: `background: #f3f4f6`, `color: #6b7280`

```js
// ✅ CORRECTO — cambiar a tab Inventario:
await page.getByRole('button', { name: /Inventario/i }).click();
await page.waitForLoadState('networkidle');
```

---

## 2. Pantalla de Vendor Inactivo

> Si el vendor tiene `status !== 'active'`, se muestra esta pantalla en lugar del dashboard.

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Ícono candado | Emoji 🔒, fontSize 64px | Centrado |
| Título | `page.getByText('Suscripción Inactiva')` | fontSize 28px, fontWeight 900 |
| Descripción | `page.getByText(/Tu suscripción no está activa/)` | Indica contactar para renovar |
| Botón contacto | `page.getByText('📧 Contactar para renovar')` | Link `mailto:` |

---

## 3. Tab Dashboard (Métricas)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Card Ventas Totales | `page.getByText('Ventas Totales')` | Formato `$XX.XXX` |
| Card Pedidos | `page.getByText('Pedidos')` | Número entero |
| Card Productos | `page.getByText('Productos')` | Número entero |
| Card Comisión PetsGo | `page.getByText('Comision PetsGo')` | Porcentaje + monto |

**Cada card tiene:**
- Border top de 4px con color del ícono
- Ícono lucide-react (DollarSign, ShoppingCart, Package, Percent)
- Valor grande fontSize 28px fontWeight 900
- Label fontSize 13px color `#6b7280`

---

## 4. Tab Inventario — Formulario de Producto

### Campos del formulario

| Campo | Tipo | Placeholder | Selector |
|-------|------|-------------|----------|
| Nombre | `text` | `Ej: Royal Canin Adulto 15kg` | `page.locator('input[placeholder="Ej: Royal Canin Adulto 15kg"]')` |
| Descripción | `textarea` | `Descripción del producto...` | `page.locator('textarea[placeholder="Descripción del producto..."]')` |
| Precio (CLP) | `number` | `29990` | `page.locator('input[placeholder="29990"]')` |
| Stock | `number` | `100` | `page.locator('input[placeholder="100"]')` |
| Categoría | `select` | — | `page.locator('select').first()` |

**Categorías del select (opciones):**
`Alimento`, `Accesorios`, `Juguetes`, `Higiene`, `Farmacia`, `Transporte`, `Tecnología`, `Snacks`

```js
// ✅ Seleccionar categoría:
await page.locator('select').first().selectOption('Alimento');
```

### Botones del formulario

| Acción | Selector | Notas |
|--------|----------|-------|
| Publicar/Guardar | `page.getByRole('button', { name: /Publicar|Guardar/i })` | Background `#00A8E8` |
| Cancelar | `page.getByRole('button', { name: 'Cancelar' })` | Background `#6b7280` |

### Tabla de productos

| Columna | Contenido | Notas |
|---------|-----------|-------|
| Nombre | Texto bold | |
| Precio | `$XX.XXX` | Color `#00A8E8` |
| Stock | Número | Rojo si ≤ 5 |
| Estado | Badge activo/inactivo | |
| Acciones | Editar + Eliminar | Íconos  |

| Acción | Selector | Notas |
|--------|----------|-------|
| Editar | `page.locator('button[title="Editar"]')` | Ícono Pencil |
| Eliminar | `page.locator('button[title="Eliminar"]')` | Ícono Trash2 |

### Estado vacío

| Elemento | Selector verificado |
|----------|-------------------|
| Sin productos | `page.getByText('Aún no tienes productos')` |

---

## 5. Tab Pedidos

### Estados de pedido (colores)

| Estado API | Texto mostrado | Color badge |
|------------|---------------|-------------|
| `payment_pending` | `Pago Pendiente` | `#FFC400` |
| `preparing` | `Preparando` | `#00A8E8` |
| `ready_for_pickup` | `Listo para enviar` | `#8B5CF6` |
| `in_transit` | `En camino` | `#F97316` |
| `delivered` | `Entregado` | `#22C55E` |
| `cancelled` | `Cancelado` | `#EF4444` |

### Elementos de la lista de pedidos

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Pedido card | Cada pedido es una card con datos | |
| Número pedido | `page.getByText(/Pedido #\d+/)` | |
| Botón avanzar estado | `page.getByRole('button', { name: /Avanzar/i })` | Transición al siguiente estado |
| Badge estado | Pill con color según estado | Background es el color de la tabla |

**Flujo de transición de estado:**
```
payment_pending → preparing → ready_for_pickup → (rider toma) → in_transit → delivered
```

```js
// ✅ Avanzar estado de un pedido:
await page.getByRole('button', { name: /Avanzar/i }).first().click();
// Toast de éxito aparece
```

### Estado vacío

| Elemento | Selector verificado |
|----------|-------------------|
| Sin pedidos | `page.getByText('Aún no tienes pedidos')` |

---

## 6. Tab Cupones

### Formulario de cupón

| Campo | Tipo | Placeholder | Selector | Notas |
|-------|------|-------------|----------|-------|
| Código | `text` | `VERANO2025` | `page.locator('input[placeholder="VERANO2025"]')` | maxLength 30, auto-uppercase |
| Tipo descuento | `select` | — | `page.locator('select').filter({ hasText: /porcentaje/i })` | Opciones: porcentaje, monto fijo |
| Valor | `number` | — | `page.locator('input[type="number"]').first()` | Valor del descuento |
| Compra mínima | `number` | — | `page.locator('input[type="number"]').nth(1)` | Monto mínimo para aplicar |
| Máx descuento | `number` | — | Siguiente input number | Tope de descuento |
| Usos totales | `number` | — | — | Límite de usos globales |
| Usos por usuario | `number` | — | — | Límite por cliente |
| Válido desde | `datetime-local` | — | `page.locator('input[type="datetime-local"]').first()` | |
| Válido hasta | `datetime-local` | — | `page.locator('input[type="datetime-local"]').last()` | |
| Activo | `checkbox` | — | `page.locator('input[type="checkbox"]')` | Toggle activo/inactivo |

### Botones del formulario

| Acción | Selector | Notas |
|--------|----------|-------|
| Guardar cupón | `page.getByRole('button', { name: /Guardar|Crear/i })` | |
| Eliminar cupón | `page.locator('button[title="Eliminar"]')` | Ícono Trash2 |

### Estado vacío

| Elemento | Selector verificado |
|----------|-------------------|
| Sin cupones | `page.getByText('Aún no tienes cupones')` |

---

## 7. API Endpoints utilizados

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/vendor/dashboard` | Vendor | Dashboard con métricas |
| GET | `/vendor/inventory` | Vendor | Lista productos del vendor |
| POST | `/vendor/inventory` | Vendor | Crear producto |
| PUT | `/vendor/inventory/{id}` | Vendor | Actualizar producto |
| DELETE | `/vendor/inventory/{id}` | Vendor | Eliminar producto |
| GET | `/vendor/orders` | Vendor | Lista pedidos del vendor |
| PUT | `/vendor/orders/{id}/status` | Vendor | Cambiar estado de pedido |
| GET | `/vendor/coupons` | Vendor | Lista cupones |
| POST | `/vendor/coupons` | Vendor | Crear/actualizar cupón |
| DELETE | `/vendor/coupons/{id}` | Vendor | Eliminar cupón |

---

## 8. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selectores principales |
|----|------|----------------------|
| VD-001 | Acceso exitoso | Login vendor, verificar 4 tabs visibles |
| VD-002 | Vendor inactivo | `page.getByText('Suscripción Inactiva')` |
| VD-003 | Sin sesión redirige | `await expect(page).toHaveURL(/\/login/)` |
| VD-020 | Métricas ventas | Cards: Ventas Totales, Pedidos, Productos, Comisión |
| VD-040 | Listar productos | Tab Inventario, tabla de productos |
| VD-041 | Crear producto | Form: nombre, precio, stock, categoría → Publicar |
| VD-043 | Editar producto | `button[title="Editar"]` → form pre-llenado |
| VD-046 | Publicar/Despublicar | Toggle de estado en la tabla |
| VD-047 | Eliminar producto | `button[title="Eliminar"]` + confirm |
| VD-060 | Ver pedidos | Tab Pedidos, lista de pedidos |
| VD-062 | Cambiar estado | Botón "Avanzar" |

---

## 9. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| VD-021 | "Gráfico de ventas" | No hay gráficos en el dashboard, solo cards con números | **NO IMPLEMENTADO** |
| VD-022 | "Pedidos Recientes" | No hay sección de pedidos recientes en el tab Dashboard | Solo en tab Pedidos |
| VD-023 | "Alerta stock bajo" | No hay alertas de stock bajo en Dashboard | **NO IMPLEMENTADO** |
| VD-024 | "Cambiar período de métricas" | No hay selector de período en el Dashboard | **NO IMPLEMENTADO** |
| VD-042 | "Producto con variantes" | No hay soporte de variantes en el formulario actual | **NO IMPLEMENTADO** |
| VD-044-045 | "Subir imagen" | No hay campo de upload de imagen en el form de vendor | **NO IMPLEMENTADO** — marcar como skip |
| VD-048 | "Buscar producto" | No hay buscador en la tabla de productos | **NO IMPLEMENTADO** |
| VD-049 | "Ordenar por columna" | No hay sort en las columnas | **NO IMPLEMENTADO** |
| VD-050 | "Precio oferta" | No hay campo de precio oferta en el form | **NO IMPLEMENTADO** |
| VD-061 | "Detalle de pedido" | No hay vista detallada de pedido individual | Los datos se muestran inline |
| VD-065 | "Filtrar por fecha" | No hay filtro de fecha en pedidos | **NO IMPLEMENTADO** |
| VD-067 | "Imprimir pedido" | No hay función de imprimir/PDF | **NO IMPLEMENTADO** |
| VD-080-086 | "Tab Finanzas" | No existe tab Finanzas en VendorDashboard | **NO IMPLEMENTADO** — marcar como skip |
| VD-100-105 | "Tab Configuración" | No existe tab Configuración | **NO IMPLEMENTADO** — marcar como skip |
| VD-120-123 | "Planes/Suscripción" | No hay sección de planes en el dashboard | **NO IMPLEMENTADO** — marcar como skip |

---

## 10. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| VD-021 a VD-024 | Dashboard solo muestra 4 cards, sin gráficos ni selectores de período |
| VD-042 | Variantes no implementadas |
| VD-044, VD-045 | Upload de imágenes no implementado en vendor form |
| VD-048, VD-049 | Buscador y ordenamiento no implementados |
| VD-050 | Precio oferta no implementado |
| VD-065, VD-067 | Filtro de fecha e impresión no implementados |
| VD-080 a VD-086 | Tab Finanzas completo no implementado |
| VD-100 a VD-105 | Tab Configuración completo no implementado |
| VD-120 a VD-123 | Sección Planes no implementada |
