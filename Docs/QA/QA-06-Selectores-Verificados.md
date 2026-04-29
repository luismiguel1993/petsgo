# QA-06 — Selectores Verificados: Dashboard Rider

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `RiderDashboard.jsx`, `petsgo-core.php`  
**Propósito:** Selectores Playwright verificados contra el código real para tests del Dashboard Rider.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

---

## 1. Navegación por Tabs (7 tabs)

| Tab | Key | Texto/Ícono | Visible si | Selector |
|-----|-----|-------------|-----------|----------|
| Inicio | `home` | Home icon + "Inicio" | `approved` | `page.getByRole('button', { name: /Inicio/i })` |
| Entregas | `deliveries` | Truck icon + "Entregas" | `approved` | `page.getByRole('button', { name: /Entregas/i })` |
| Ganancias | `earnings` | DollarSign icon + "Ganancias" | `approved` | `page.getByRole('button', { name: /Ganancias/i })` |
| Estadísticas | `stats` | BarChart3 icon + "Estadísticas" | `approved` | `page.getByRole('button', { name: /Estadísticas/i })` |
| Documentos | `documents` | FileText icon + "Documentos" | **siempre** | `page.getByRole('button', { name: /Documentos/i })` |
| Valoraciones | `ratings` | Star icon + "Valoraciones" | `approved` | `page.getByRole('button', { name: /Valoraciones/i })` |
| Perfil | `profile` | User icon + "Perfil" | **siempre** | `page.getByRole('button', { name: /Perfil/i })` |

> **IMPORTANTE:** Los tabs 1-4 y 6 solo aparecen si el rider tiene status `approved`. Los tabs 5 (Documentos) y 7 (Perfil) siempre se muestran.

```js
// ✅ Navegar al tab de entregas:
await page.getByRole('button', { name: /Entregas/i }).click();
```

---

## 2. Tab Inicio (Home) — Solo riders aprobados

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Saludo | `page.getByText(/Hola,/)` | "Hola, {nombre}" |
| Resumen entregas hoy | Cards con métricas del día | |
| Entregas pendientes | Número de entregas activas | |

> **⚠️ DISCREPANCIA:** El QA spec menciona toggle de disponibilidad online/offline (RD-040/041/042). **No existe** en el código actual de RiderDashboard.jsx.

---

## 3. Tab Entregas — Flujo de delivery

### Estados de entrega

| Estado API | Texto mostrado | Color | Acción disponible |
|------------|---------------|-------|-------------------|
| `ready_for_pickup` | `Listo para retirar` | Morado/azul | "Retirar" → cambia a `in_transit` |
| `in_transit` | `En tránsito` | Naranja | "Marcar entregado" → cambia a `delivered` |
| `delivered` | `Entregado` | Verde | Sin acción |

### Elementos de entregas

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Card de entrega | Card con datos del pedido | |
| Dirección recogida | Texto con ícono MapPin | Dirección del vendor |
| Dirección entrega | Texto con ícono MapPin | Dirección del cliente |
| Botón retirar | `page.getByRole('button', { name: /Retirar/i })` | Cambia estado a `in_transit` |
| Botón marcar entregado | `page.getByRole('button', { name: /Marcar entregado|Entregado/i })` | Cambia estado a `delivered` |

```js
// ✅ Flujo completo de entrega:
// 1. Retirar el pedido
await page.getByRole('button', { name: /Retirar/i }).first().click();
// 2. Marcar como entregado
await page.getByRole('button', { name: /Marcar entregado/i }).first().click();
```

---

## 4. Tab Ganancias

### Desglose semanal

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Total semana | Card con monto total | Formato `$XX.XXX` |
| Desglose por día | Tabla/lista con montos por día | |
| Tabla mensual | Historial mensual con km tracking | Incluye distancia recorrida |

### Historial de pagos (payouts)

| Estado | Texto | Color |
|--------|-------|-------|
| `paid` | `Pagado` | Verde |
| `pending` | `Pendiente` | Amarillo |
| `processing` | `Procesando` | Azul |
| `failed` | `Fallido` | Rojo |

---

## 5. Tab Estadísticas

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Selector de rango | `page.locator('select')` | Opciones: semana, mes, año, personalizado |
| Exportar PDF | `page.getByRole('button', { name: /Exportar|PDF/i })` | Usa jsPDF + autoTable |
| Gráficos | Cards con métricas resumen | Total entregas, tasa éxito, ganancias |

**Opciones del select de rango:**

| Value | Label |
|-------|-------|
| `week` | `Esta Semana` |
| `month` | `Este Mes` |
| `year` | `Este Año` |
| `custom` | `Personalizado` |

---

## 6. Tab Documentos

### Tipos de documentos (7 tipos)

| Documento | Requerido para | Notas |
|-----------|---------------|-------|
| Cédula de identidad (anverso) | Todos | Obligatorio |
| Cédula de identidad (reverso) | Todos | Obligatorio |
| Licencia de conducir | scooter, moto, auto | No requerido para bicicleta/a_pie |
| Padrón del vehículo | scooter, moto, auto | No requerido para bicicleta/a_pie |
| Foto vehículo frontal | Todos | |
| Foto vehículo lateral | Todos | |
| Foto vehículo trasera | Todos | |

### Estados de documentos

| Estado | Texto | Ícono/Color |
|--------|-------|-------------|
| `approved` | `Aprobado` | ✅ Verde |
| `rejected` | `Rechazado` | ❌ Rojo |
| `pending` | `Pendiente` | ⏳ Amarillo |
| `missing` | `Faltante` | ⚠️ Gris |

### Alertas de expiración (3 niveles)

| Nivel | Condición | Color |
|-------|-----------|-------|
| Crítico | Expirado o < 7 días | Rojo |
| Advertencia | < 30 días | Naranja |
| Info | < 90 días | Amarillo |

### Botón de upload

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón subir documento | `page.locator('input[type="file"]')` | Hidden, se activa via label/botón |
| Previsualización | Thumbnail del documento | Después de subir |

> **SKIP:** Todos los tests de upload de documentos (RD-002, RD-141) requieren file picker del OS.

---

## 7. Tab Valoraciones

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Rating promedio | Número grande con estrellas | |
| Tarjetas de valoración | Cards con rating + comentario | |
| Tipo de valorador | `🏪` = vendor, `👤` = customer | |
| Fecha | Formato relativo en español | |

---

## 8. Tab Perfil

### Campos editables

| Campo | Tipo | Selector | Validación |
|-------|------|----------|-----------|
| Nombre | `text` | `page.locator('input').first()` | Mismo que registro |
| Apellido | `text` | `page.locator('input').nth(1)` | Mismo que registro |
| Teléfono | `tel` | `page.locator('input[type="tel"]')` | 8 dígitos, prefijo +569 |
| RUT | `text` | Input con validación mod 11 | |
| Banco | `select` | `page.locator('select')` | 15 bancos + 3 tipos de cuenta |

### Bancos disponibles (select)

`Banco de Chile`, `Banco Estado`, `Banco Santander`, `Banco BCI`, `Banco Scotiabank`, `Banco Itaú`, `Banco Security`, `Banco Falabella`, `Banco Ripley`, `Banco Consorcio`, `Banco BICE`, `Banco Internacional`, `HSBC`, `Banco BTG Pactual`, `Banco del Desarrollo`

### Tipos de cuenta

`Cuenta Corriente`, `Cuenta Vista/RUT`, `Cuenta de Ahorro`

### Validación bancaria RUT

| Regla | Detalle |
|-------|---------|
| RUT banco | Debe coincidir con el RUT del rider |
| Algoritmo | Módulo 11 (misma validación que registro) |

### Botón guardar perfil

| Elemento | Selector verificado |
|----------|-------------------|
| Guardar | `page.getByRole('button', { name: /Guardar/i })` |

---

## 9. API Endpoints utilizados

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/rider/dashboard` | Rider | Datos del dashboard |
| GET | `/rider/deliveries` | Rider | Lista entregas activas |
| PUT | `/rider/deliveries/{id}/status` | Rider | Cambiar estado de entrega |
| GET | `/rider/earnings` | Rider | Ganancias del período |
| GET | `/rider/stats` | Rider | Estadísticas |
| GET | `/rider/documents` | Rider | Estado de documentos |
| POST | `/rider/documents` | Rider | Subir documento |
| GET | `/rider/ratings` | Rider | Valoraciones recibidas |
| GET | `/rider/profile` | Rider | Datos del perfil |
| PUT | `/rider/profile` | Rider | Actualizar perfil |

---

## 10. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selectores principales |
|----|------|----------------------|
| RD-001 | Registro rider | En `/registro-rider`, no en dashboard |
| RD-020 | Acceso exitoso | Login + verificar tabs visibles (7 si approved) |
| RD-021 | Sin sesión | `await expect(page).toHaveURL(/\/login/)` |
| RD-040-042 | Toggle disponibilidad | **NO IMPLEMENTADO** en código actual |
| RD-060 | Pedidos disponibles | Tab Entregas → entregas con status `ready_for_pickup` |
| RD-062 | Aceptar pedido | Botón Retirar |
| RD-080 | Entregas activas | Tab Entregas |
| RD-083 | Confirmar entrega | Botón "Marcar entregado" |
| RD-120 | Ganancias | Tab Ganancias |
| RD-140 | Estado documentos | Tab Documentos → badges de estado |
| RD-142 | Editar datos | Tab Perfil → campos editables |

---

## 11. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| RD-040-042 | "Toggle disponibilidad online/offline" | **No existe toggle** en el código de RiderDashboard | **NO IMPLEMENTADO** — marcar como skip |
| RD-061 | "Ver detalle de pedido antes de aceptar" | No hay vista de detalle separada; datos se muestran inline en la card | Test debe verificar datos visibles en la card |
| RD-064 | "Solo pedidos de la zona del rider" | Filtro geográfico no visible en frontend | Posiblemente filtrado en backend |
| RD-081 | "Marcar recogido del vendor" | El flujo es: `ready_for_pickup` → `in_transit` (un solo paso de "Retirar"), no hay estado intermedio "recogido" | Solo hay 2 acciones: Retirar y Marcar entregado |
| RD-082 | "Marcar En camino" como paso separado | Se combina con "Retirar" — `ready_for_pickup` → `in_transit` directamente | Un solo botón |
| RD-084 | "Reportar problema" | No hay botón/función de reportar problema en el código | **NO IMPLEMENTADO** |
| RD-085 | "Cancelar entrega" | No hay botón de cancelar entrega en el código | **NO IMPLEMENTADO** |
| RD-102 | "Filtrar historial por fecha" | No hay filtro de fecha en entregas | **NO IMPLEMENTADO** |
| RD-103 | "Estadísticas resumen" | Existe en tab Estadísticas, no en Historial | Reubicar test a tab correcto |
| RD-122 | "Solicitar pago/retiro" | No hay botón explícito de solicitar pago | **NO IMPLEMENTADO** |
| RD-124 | "Retiro menor al saldo" | No hay función de retiro | **NO IMPLEMENTADO** |

---

## 12. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| RD-001-006 | Registro rider — en `/registro-rider`, no en dashboard; códigos email son MANUAL |
| RD-002 | Upload de documentos requiere file picker |
| RD-040, RD-041, RD-042 | Toggle online/offline no implementado |
| RD-064 | Filtro geográfico no visible en UI |
| RD-084 | Reportar problema no implementado |
| RD-085 | Cancelar entrega no implementado |
| RD-102 | Filtro por fecha no implementado |
| RD-122, RD-124 | Solicitar pago/retiro no implementado |
| RD-141 | Reenviar documento — requiere file picker |
