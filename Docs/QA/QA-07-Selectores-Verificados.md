# QA-07 — Selectores Verificados: Panel de Administrador

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `AdminDashboard.jsx` (~1400 líneas), `petsgo-core.php`  
**Propósito:** Selectores Playwright verificados contra el código real para tests del Panel Admin.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

> ⚠️ **ALERTA CRÍTICA:** El AdminDashboard.jsx actual **solo implementa 4 tabs**. Los specs QA-07 tienen 11 secciones. Las secciones 2, 5, 6, 7, 8, 9, 10, 11 del spec **NO ESTÁN IMPLEMENTADAS** en el código frontend actual.

---

## 1. Navegación por Tabs (4 tabs implementados)

| Tab | Key | Texto | Ícono | Selector |
|-----|-----|-------|-------|----------|
| Dashboard Global | `dashboard` | `Dashboard Global` | BarChart3 | `page.getByRole('button', { name: /Dashboard Global/i })` |
| Tiendas | `vendors` | `Tiendas` | Store | `page.getByRole('button', { name: /^Tiendas$/i })` |
| Tienda PetsGo | `store` | `Tienda PetsGo` | ShoppingBag | `page.getByRole('button', { name: /Tienda PetsGo/i })` |
| Riders | `riders` | `Riders` | Truck | `page.getByRole('button', { name: /Riders/i })` |

**Estilos de tab (Tailwind):**
- Activo: `bg-[#2F3A40] text-[#FFC400]`
- Inactivo: `bg-white text-gray-500`

```js
// ✅ CORRECTO — cambiar a tab Tiendas:
await page.getByRole('button', { name: /^Tiendas$/i }).click();
await page.waitForLoadState('networkidle');
```

> **TABS NO IMPLEMENTADOS:** Usuarios, Pedidos, Productos, Finanzas, Cupones, Soporte, Planes, Configuración — **NINGUNO** existe en el código actual.

---

## 2. Tab Dashboard Global

### KPI Cards (4 tarjetas)

| Métrica | Texto | Selector | Ícono |
|---------|-------|----------|-------|
| Ventas Totales | `Ventas Totales` | `page.getByText('Ventas Totales')` | DollarSign |
| Comisiones PetsGo | `Comisiones PetsGo` | `page.getByText('Comisiones PetsGo')` | TrendingUp |
| Total Pedidos | `Total Pedidos` | `page.getByText('Total Pedidos')` | ShoppingCart |
| Tiendas Activas | `Tiendas Activas` | `page.getByText('Tiendas Activas')` | Store |

**Cada card tiene:**
- Border-left de 4px con color del tema
- Valor grande en bold
- Label descriptivo gris

---

## 3. Tab Tiendas (Vendors)

### Tabla de vendors

| Columna | Header | Contenido |
|---------|--------|-----------|
| 1 | TIENDA | store_name + email |
| 2 | RUT | RUT de la tienda |
| 3 | ESTADO | Badge con color |
| 4 | % VENTA | Input editable (porcentaje comisión venta) |
| 5 | % DELIVERY | Input editable (porcentaje comisión delivery) |
| 6 | ACCIONES | Botones de acción |

### Estados de vendor

| Estado | Texto | Color Tailwind |
|--------|-------|---------------|
| `active` | `Activa` | `bg-green-100 text-green-800` |
| `pending` | `Pendiente` | `bg-yellow-100 text-yellow-800` |
| `suspended` | `Suspendida` | `bg-red-100 text-red-800` |

### Inputs de comisión editables

```js
// ✅ Editar porcentaje de venta para un vendor:
const ventaInput = page.locator('input[type="number"]').first();
await ventaInput.fill('15');
// El cambio se guarda automáticamente al cambiar el valor (onBlur o onChange)
```

### Acciones por vendor

| Acción | Selector | Notas |
|--------|----------|-------|
| Supervisar (impersonate) | `page.getByRole('button', { name: /Supervisar/i })` | Abre modal "Modo Supervisar" |
| Aprobar vendor | Cambiar estado a `active` | |
| Suspender vendor | Cambiar estado a `suspended` | |

### Modal "Modo Supervisar"

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título modal | `page.getByText('Modo Supervisar')` | |
| Botón confirmar | `page.getByRole('button', { name: /Confirmar/i })` | |
| Botón cancelar | `page.getByRole('button', { name: /Cancelar/i })` | |

### Estado vacío

| Elemento | Selector verificado |
|----------|-------------------|
| Sin tiendas | `page.getByText('No hay tiendas registradas')` |

### Paginación

| Elemento | Notas |
|----------|-------|
| Items por página | `ITEMS_PER_PAGE = 5` |
| Controles | `PaginationControls` component — botones numéricos + prev/next |

---

## 4. Tab Tienda PetsGo (Inventario Admin)

> Documentado en detalle en **QA-11-Selectores-Verificados.md** ya que corresponde al spec QA-11.

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título sección | `page.getByText('🛍️ Tienda PetsGo Oficial')` | |
| Subtítulo | `page.getByText('Gestiona los productos que PetsGo vende directamente')` | |
| Botón agregar | `page.locator('button').filter({ hasText: 'Agregar Producto' })` | |
| Estado vacío | `page.getByText('La Tienda PetsGo aún no tiene productos')` | |

---

## 5. Tab Riders

### Tabla de riders

| Columna | Header | Contenido |
|---------|--------|-----------|
| 1 | RIDER | Nombre + email |
| 2 | TELÉFONO | +569XXXXXXXX |
| 3 | VEHÍCULO | Tipo con emoji |
| 4 | ESTADO | Badge con color |
| 5 | ENTREGAS | Número total |
| 6 | RATING | Estrellas |
| 7 | ACCIONES | Botones |

### Buscador de riders

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Input búsqueda | `page.locator('input[placeholder="Buscar rider..."]')` | Filtra la tabla |

```js
// ✅ Buscar un rider:
await page.locator('input[placeholder="Buscar rider..."]').fill('Juan');
```

### Acciones por rider

| Acción | Selector | Notas |
|--------|----------|-------|
| Ver estadísticas | Botón con ícono BarChart | Abre modal con stats |
| Aprobar rider | Cambiar estado a `approved` | |
| Rechazar rider | Cambiar estado a `rejected` | |

### Modal de Estadísticas del Rider

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título modal | Nombre del rider | |
| Selector de rango | `page.locator('select')` | Semana/Mes/Año/Personalizado |
| Exportar PDF | `page.getByRole('button', { name: /Exportar|PDF/i })` | jsPDF + autoTable |
| Cerrar modal | Botón X o Cerrar | |

**Opciones del select de rango:**

| Value | Label |
|-------|-------|
| `week` | `Esta Semana` |
| `month` | `Este Mes` |
| `year` | `Este Año` |
| `custom` | `Personalizado` |

### Estado vacío

| Elemento | Selector verificado |
|----------|-------------------|
| Sin riders | `page.getByText('No hay riders registrados')` |

### Paginación

| Elemento | Notas |
|----------|-------|
| Items por página | `ITEMS_PER_PAGE = 5` |

---

## 6. wp-login.php (Branding PetsGo)

> Tests AD-005 a AD-009 cubren el branding personalizado de wp-login.php.

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Fondo | Gradiente oscuro: `dark → #1a2228` | Fullscreen |
| Logo PetsGo | `page.locator('#login h1 a')` | 220×80px (desktop), 180×60px (mobile/notebook) |
| Subtítulo | `page.getByText('PetsGo · Panel de Administración')` | Debajo del logo |
| Formulario | `page.locator('#loginform')` | Border-radius 16px, barra gradiente superior |
| Input usuario | `page.locator('#user_login')` | |
| Input contraseña | `page.locator('#user_pass')` | |
| Botón login | `page.locator('#wp-submit')` | Texto "Iniciar sesión", bg `#00A8E8` |
| Link olvidé contraseña | `page.getByText('¿Olvidaste tu contraseña?')` | Color `rgba(255,255,255,0.65)`, hover `#FFC400` |
| Link volver | `page.getByText(/Ir a PetsGo Marketplace/i)` | |
| Overlay verificando | `page.getByText('Verificando credenciales…')` | Aparece al hacer submit |

### Responsive de wp-login.php

| Viewport | Logo | Comportamiento |
|----------|------|----------------|
| ≤ 480px (mobile) | 180×60px | Padding reducido, márgenes 12px |
| ≤ 700px height (notebook) | 180×60px | No centrado vertical, padding-top 20px, subtítulo 11px |
| 1920×1080 (desktop) | 220×80px | Centrado vertical y horizontal |

---

## 7. API Endpoints utilizados

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/admin/dashboard` | Admin | KPI cards |
| GET | `/admin/vendors` | Admin | Lista vendors |
| PUT | `/admin/vendors/{id}` | Admin | Actualizar vendor (estado, comisiones) |
| POST | `/admin/vendors/{id}/impersonate` | Admin | Modo supervisar |
| GET | `/admin/inventory` | Admin | Productos PetsGo Oficial |
| POST | `/admin/inventory` | Admin | Crear producto PetsGo |
| PUT | `/admin/inventory/{id}` | Admin | Actualizar producto PetsGo |
| DELETE | `/admin/inventory/{id}` | Admin | Eliminar producto PetsGo |
| PUT | `/admin/inventory/{id}/toggle` | Admin | Toggle activo/inactivo |
| POST | `/admin/inventory/upload-image` | Admin | Subir imagen de producto |
| GET | `/admin/riders` | Admin | Lista riders |
| PUT | `/admin/riders/{id}` | Admin | Actualizar rider (estado) |
| GET | `/admin/riders/{id}/stats` | Admin | Estadísticas del rider |

---

## 8. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selectores / Estado |
|----|------|---------------------|
| AD-001 | Login admin | Login estándar + verificar tabs admin |
| AD-002 | Sin sesión | `await expect(page).toHaveURL(/\/login/)` |
| AD-003 | Cliente no accede | Redirige a login |
| AD-005 | wp-login branding | `page.locator('#login h1 a')`, gradiente oscuro |
| AD-006 | Logo en notebook | Viewport 1366×768, logo 180×60px |
| AD-008 | Login wp-admin funcional | `#user_login`, `#user_pass`, `#wp-submit` |
| AD-040 | Listar vendors | Tab Tiendas → tabla con columnas |
| AD-041 | Aprobar vendor | Cambiar estado en tabla |
| AD-060 | Listar riders | Tab Riders → tabla con columnas |
| AD-062 | Aprobar rider | Cambiar estado del rider |
| AD-064 | Historial entregas | Modal stats del rider |

---

## 9. Discrepancias entre QA spec y código real — ⚠️ CRÍTICO

| Sección QA | IDs | Estado en código |
|------------|-----|-----------------|
| **2. Gestión de Usuarios** | AD-020 a AD-028 | **❌ NO IMPLEMENTADO** — No hay tab de Usuarios |
| **5. Gestión de Pedidos** | AD-080 a AD-086 | **❌ NO IMPLEMENTADO** — No hay tab de Pedidos |
| **6. Gestión de Productos** | AD-100 a AD-104 | **❌ NO IMPLEMENTADO** — No hay tab de Productos (general) |
| **7. Finanzas y Comisiones** | AD-120 a AD-125 | **❌ NO IMPLEMENTADO** — No hay tab de Finanzas |
| **8. Gestión de Cupones** | AD-140 a AD-146 | **❌ NO IMPLEMENTADO** — No hay tab de Cupones |
| **9. Soporte Tickets** | AD-160 a AD-164 | **❌ NO IMPLEMENTADO** — No hay tab de Soporte |
| **10. Gestión de Planes** | AD-180 a AD-184 | **❌ NO IMPLEMENTADO** — No hay tab de Planes |
| **11. Configuración General** | AD-200 a AD-205 | **❌ NO IMPLEMENTADO** — No hay tab de Configuración |

> **De 66 casos de test en QA-07, aproximadamente 45 corresponden a funcionalidades NO IMPLEMENTADAS.**

### Features parcialmente implementadas

| Feature | Estado |
|---------|--------|
| Vendors — listar, comisiones editables, supervisar | ✅ Implementado |
| Vendors — aprobar/rechazar/suspender | ✅ Parcial (cambio de estado) |
| Riders — listar, buscar, stats con export | ✅ Implementado |
| Riders — aprobar/rechazar | ✅ Parcial |
| Dashboard — KPI cards | ✅ Implementado |
| Tienda PetsGo — producto CRUD + imagen | ✅ Implementado (ver QA-11) |

---

## 10. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| AD-020 a AD-028 | Gestión de Usuarios no implementada en frontend |
| AD-042 | Rechazar vendor con motivo — no hay input de motivo en UI |
| AD-044 | Ver métricas de vendor — no hay modal de métricas de vendor |
| AD-045 | Procesar retiro de vendor — no implementado |
| AD-063 | Rechazar rider con motivo — verificar si hay input de motivo |
| AD-065 | Procesar pago a rider — no implementado |
| AD-066 | Riders en línea en tiempo real — no implementado |
| AD-080 a AD-086 | Gestión de Pedidos no implementada |
| AD-100 a AD-104 | Gestión de Productos (general) no implementada |
| AD-120 a AD-125 | Finanzas no implementadas |
| AD-140 a AD-146 | Cupones admin no implementados |
| AD-160 a AD-164 | Soporte Tickets admin no implementado |
| AD-180 a AD-184 | Gestión de Planes no implementada |
| AD-200 a AD-205 | Configuración General no implementada |
