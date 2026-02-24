# QA-04 — Pedidos y Boletas Electrónicas
**Proyecto:** PetsGo  
**Módulo:** Gestión de Pedidos, Boletas, Verificación QR  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Cliente, Vendor, Rider, Admin

---

## 1. PEDIDOS — VISTA CLIENTE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-001 | Ver lista de pedidos propios | Al menos 1 pedido | 1. Login como cliente 2. Ir a `/mis-pedidos` | Lista de pedidos del cliente con número, fecha, estado, total | Alta |
| PB-002 | Ver detalle de pedido | Pedido existente | 1. Click en pedido de la lista | Detalle con productos, cantidades, subtotales, estado de envío, tracking | Alta |
| PB-003 | Filtrar pedidos por estado | Cliente con pedidos en distintos estados | 1. Usar selector de filtro de estado | Solo pedidos del estado seleccionado visibles | Media |
| PB-004 | Estados de pedido visibles correctamente | N/A | 1. Verificar estados | pending / processing / on-the-way / delivered / cancelled visibles en español | Alta |
| PB-005 | Cliente no puede ver pedidos de otro cliente | 2 clientes con pedidos | 1. Intentar acceder a `/mis-pedidos` como cliente A con ID de pedido de cliente B | Error 403 o redirección | Alta |
| PB-006 | Pedido cancelado muestra estado correcto | Pedido `cancelled` | 1. Ver pedido cancelado | Badge "Cancelado", sin botón de seguimiento | Alta |

---

## 2. PEDIDOS — VISTA VENDOR

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-020 | Ver pedidos que incluyen mis productos | Vendor con pedidos | 1. Login vendor 2. Dashboard → Pedidos | Solo pedidos con productos de este vendor visibles | Alta |
| PB-021 | Cambiar estado de pedido a "En preparación" | Pedido `processing` | 1. Seleccionar pedido 2. Cambiar estado 3. Guardar | Estado actualizado, cliente notificado (si aplica) | Alta |
| PB-022 | Marcar pedido como "Listo para retiro" | Pedido en preparación | 1. Cambiar estado a `ready_for_pickup` | Estado actualizado, rider puede verlo | Alta |
| PB-023 | Vendor no puede modificar pedido de otro vendor | 2 vendors | 1. Intentar modificar estado de pedido ajeno via API | Error 403 | Alta |
| PB-024 | Historial de estados del pedido | Pedido con varios cambios | 1. Ver timeline de estados en detalle | Logs de cambios de estado con timestamps | Media |

---

## 3. PEDIDOS — VISTA ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-040 | Ver TODOS los pedidos | Admin logueado | 1. Panel Admin → Pedidos | Todos los pedidos del sistema, de todos los vendors | Alta |
| PB-041 | Filtrar pedidos por vendor | Admin, múltiples vendors | 1. Seleccionar vendor en filtro | Solo pedidos de ese vendor | Alta |
| PB-042 | Filtrar pedidos por fecha | Admin | 1. Seleccionar rango de fechas | Pedidos dentro del rango | Media |
| PB-043 | Cambiar cualquier estado de pedido | Admin | 1. Modificar estado de cualquier pedido | Estado cambiado sin restricción de vendor | Alta |
| PB-044 | Exportar pedidos | Admin | 1. Click "Exportar CSV" | Archivo CSV descargado con datos de pedidos | Media |

---

## 4. BOLETAS ELECTRÓNICAS — GENERACIÓN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-060 | Boleta generada al confirmar pago | Pago exitoso (Transbank/MP) | 1. Completar compra con pago exitoso | Boleta PDF generada y guardada en BD con token único | Alta |
| PB-061 | Boleta con datos correctos | Boleta generada | 1. Descargar boleta PDF | Número correlativo, RUT empresa (76.543.210-K), productos, totales, IVA 19% | Alta |
| PB-062 | Boleta tiene QR de verificación | PDF generado | 1. Abrir PDF 2. Escanear QR | QR apunta a `https://petsgo.cl/verificar-boleta/{token}` | Alta |
| PB-063 | Número correlativo incrementa | 2+ boletas generadas | 1. Ver número de boletas | Siempre ascending, nunca duplicado | Alta |
| PB-064 | Boleta incluye folio tributario | PDF generado | 1. Revisar header de PDF | Folio visible en formato correcto | Media |
| PB-065 | Boleta generada en test_bypass | Entorno dev, pago test | 1. Completar compra con test_bypass | Boleta también se genera normalmente | Media |

---

## 5. BOLETAS — DESCARGA Y ACCESO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-080 | Cliente descarga su boleta | Pedido con boleta | 1. En `/mis-pedidos`, click "Descargar Boleta" | PDF descargado en el navegador | Alta |
| PB-081 | Admin descarga cualquier boleta | Admin logueado | 1. Panel Admin → Pedido → "Descargar Boleta" | PDF descargado | Alta |
| PB-082 | Cliente no puede descargar boleta ajena | 2 clientes | 1. Acceder a endpoint de boleta de otro cliente | Error 403 | Alta |
| PB-083 | Boleta disponible en detalle de pedido | Pedido procesado | 1. Ver detalle de pedido | Botón "Ver/Descargar Boleta" visible | Alta |

---

## 6. VERIFICACIÓN QR DE BOLETA

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-100 | Verificar boleta con token válido | Boleta generada con QR | 1. Navegar a `/verificar-boleta/{token-valido}` | Página con: escudo verde ✓, número de boleta, tienda, RUT, cliente, total, fecha, estado "VÁLIDA" | Alta |
| PB-101 | Verificar boleta con token inválido | N/A | 1. Navegar a `/verificar-boleta/tokenfake123` | Página con escudo rojo ✗, mensaje "Boleta no encontrada o token inválido" | Alta |
| PB-102 | Verificar boleta con token vacío | N/A | 1. Navegar a `/verificar-boleta/` | 404 o redirección | Media |
| PB-103 | Página de verificación accesible sin login | Sin sesión | 1. Abrir URL de verificación sin estar logueado | Página se muestra correctamente (pública) | Alta |
| PB-104 | QR escaneado con smartphone | Boleta PDF impresa | 1. Escanear QR con cámara de celular | Navegador abre URL correcta, página de verificación visible | Alta |
| PB-105 | Respuesta JSON del API también válida | Dev/QA | 1. GET `/wp-json/petsgo/v1/invoice/validate/{token}` | JSON con todos los datos de la boleta | Media |

---

## 7. SEGUIMIENTO DE ENVÍO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PB-120 | Cliente ve estado de entrega | Pedido asignado a rider | 1. En detalle del pedido | Badge de estado actualizado: "En camino" con nombre del rider | Alta |
| PB-121 | Historial de estados de envío | Múltiples cambios de estado | 1. Ver timeline en detalle de pedido | Todos los cambios con timestamps: `processing` → `ready` → `on_the_way` → `delivered` | Alta |
| PB-122 | Notificación al cliente cuando el pedido está "En camino" | Rider despacha pedido | 1. Rider marca "En camino" | Cliente recibe notificación/email | Media |
| PB-123 | Marcar pedido como entregado | Rider en destino | 1. Rider confirma entrega | Status del pedido cambia a `delivered`, timestamp guardado | Alta |
