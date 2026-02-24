# QA-05 — Dashboard del Vendor
**Proyecto:** PetsGo  
**Módulo:** Panel de Proveedor / Tienda  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Vendor (petsgo_vendor)  
**URL:** `/vendor`

---

## 1. ACCESO Y AUTENTICACIÓN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-001 | Acceso exitoso con rol vendor activo | Vendor `active` | 1. Login como vendor 2. Navegar a `/vendor` | Dashboard carga correctamente con las 4 pestañas | Alta |
| VD-002 | Vendor inactivo ve pantalla de espera | Vendor `inactive` | 1. Login como vendor inactivo | Pantalla "Tu cuenta está siendo revisada" o similar | Alta |
| VD-003 | Sin sesión redirige a login | Sin sesión | 1. Navegar directamente a `/vendor` | Redirige a `/login` | Alta |
| VD-004 | Sesión persiste al refrescar F5 | Vendor logueado | 1. Estar en `/vendor` 2. Presionar F5 | Dashboard vuelve a cargar sin redirigir a login | Alta |
| VD-005 | Cliente no puede acceder a `/vendor` | Sesión de cliente | 1. Navegar a `/vendor` | Redirige a `/login` o acceso denegado | Alta |

---

## 2. TAB: RESUMEN / INICIO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-020 | Métricas de ventas del período | Vendor con historial | 1. Ver tab Resumen | Total de ventas, número de pedidos del mes, ganancias netas | Alta |
| VD-021 | Gráfico de ventas por período | Historial de ventas | 1. Ver gráfico de ventas | Gráfico de barras/líneas con datos de ventas | Media |
| VD-022 | Últimos pedidos recientes | Pedidos recientes | 1. Ver sección "Pedidos Recientes" | Lista de los últimos 5 pedidos con estado | Alta |
| VD-023 | Alerta de stock bajo | Producto con stock < umbral | 1. Ver sección de alertas | Producto con stock bajo listado con alerta | Media |
| VD-024 | Cambiar período de métricas | N/A | 1. Cambiar selector de período (7 días, 30 días, año) | Métricas se actualizan con el período seleccionado | Media |

---

## 3. TAB: PRODUCTOS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-040 | Listar todos mis productos | Vendor con productos | 1. Click tab "Productos" | Tabla con nombre, precio, stock, estado de todos sus productos | Alta |
| VD-041 | Crear producto simple | N/A | 1. Click "Nuevo Producto" 2. Completar formulario básico 3. "Publicar" | Producto creado, estado `publish`, visible en catálogo | Alta |
| VD-042 | Crear producto con variantes | N/A | 1. Crear producto 2. Activar "Tiene variantes" 3. Agregar atributo + valores 4. Configurar precio/stock por variante | Variantes creadas correctamente | Alta |
| VD-043 | Editar producto existente | Producto publicado | 1. Click editar 2. Cambiar nombre/precio 3. Guardar | Cambios guardados, reflejados en frontend | Alta |
| VD-044 | Subir imagen principal | Formulario de producto | 1. Click en zona de upload 2. Seleccionar imagen JPEG/PNG | Imagen subida, preview visible, URL guardada | Alta |
| VD-045 | Subir galería de imágenes (máx 5) | Formulario de producto | 1. Agregar hasta 5 imgs adicionales | Galería guardada | Media |
| VD-046 | Publicar/Despublicar producto | Producto existente | 1. Toggle de estado activo/borrador | Estado cambia, visibilidad en frontend se actualiza | Alta |
| VD-047 | Eliminar producto (borrador) | Producto en borrador | 1. Click eliminar 2. Confirmar diálogo | Producto eliminado de la lista | Alta |
| VD-048 | Buscar producto en mi lista | Lista con 10+ productos | 1. Escribir en buscador de productos | Lista filtrada en tiempo real | Media |
| VD-049 | Ordenar productos por nombre/precio | Lista de productos | 1. Click en cabecera de columna | Lista reordenada | Baja |
| VD-050 | Precio oferta menor al precio normal | Form de producto | 1. Ingresar precio oferta ≥ precio normal | Validación impide guardar | Alta |
| VD-051 | Stock no puede ser negativo | Form de producto | 1. Ingresar stock = -5 | Validación rechaza valor negativo | Alta |
| VD-052 | Producto sin categoría asignada | N/A | 1. Intentar publicar sin seleccionar categoría | Error "Debes seleccionar una categoría" | Alta |

---

## 4. TAB: PEDIDOS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-060 | Ver pedidos propios | Pedidos con mis productos | 1. Click tab "Pedidos" | Lista de pedidos que incluyen productos de este vendor | Alta |
| VD-061 | Ver detalle de pedido | Pedido en lista | 1. Click en pedido | Detalle con cliente, dirección, productos, totales | Alta |
| VD-062 | Cambiar estado a "En preparación" | Pedido `processing` | 1. Seleccionar estado "Preparando" 2. Guardar | Estado actualizado | Alta |
| VD-063 | Cambiar estado a "Listo para retiro" | Pedido en preparación | 1. Cambiar a "Listo para retiro" | Estado `ready_for_pickup`, rider puede ver este pedido | Alta |
| VD-064 | Filtrar pedidos por estado | 5+ pedidos | 1. Seleccionar filtro "Pendiente" | Solo pedidos pendientes | Alta |
| VD-065 | Filtrar pedidos por fecha | N/A | 1. Seleccionar rango de fechas | Pedidos en ese rango | Media |
| VD-066 | Solo ver mis pedidos, no los de otros | 2 vendors | 1. Verificar que solo aparecen pedidos con mis productos | No hay "cross-contamination" de pedidos | Alta |
| VD-067 | Imprimir resumen de pedido | Detalle de pedido | 1. Click "Imprimir" o "Descargar PDF" | Impresión o PDF del pedido | Baja |

---

## 5. TAB: FINANZAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-080 | Ver ganancias del período | Pedidos entregados | 1. Click tab "Finanzas" | Total de ingresos brutos, comisión PetsGo, ganancia neta del período | Alta |
| VD-081 | Ver saldo pendiente de retiro | Ganancias acumuladas | 1. Ver saldo disponible | Monto correcto según ventas menos comisiones menos retiros previos | Alta |
| VD-082 | Solicitar retiro de fondos | Saldo > 0 | 1. Click "Solicitar Retiro" 2. Ingresar monto 3. Confirmar | Solicitud registrada, saldo pendiente actualizado | Alta |
| VD-083 | Retiro mayor al saldo disponible | N/A | 1. Intentar solicitar monto > saldo | Error "Fondos insuficientes" | Alta |
| VD-084 | Historial de retiros | Retiros previos | 1. Ver tabla de historial | Fecha, monto, estado (pendiente/completado) de cada retiro | Alta |
| VD-085 | Porcentaje de comisión correcto | Plan del vendor | 1. Verificar comisión mostrada | % coincide con el plan contratado | Alta |
| VD-086 | Exportar reporte financiero | Finanzas con historial | 1. Click "Exportar CSV" | Archivo descargado con historial de transacciones | Media |

---

## 6. TAB: CONFIGURACIÓN DE TIENDA

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-100 | Editar nombre de tienda | N/A | 1. Modificar nombre 2. Guardar | Nombre actualizado en página de tienda pública | Alta |
| VD-101 | Subir logo de tienda | N/A | 1. Subir imagen cuadrada < 2MB | Logo actualizado en tienda pública y dashboard | Alta |
| VD-102 | Editar descripción de tienda | N/A | 1. Modificar descripción larga 2. Guardar | Descripción actualizada en `/tienda/{slug}` | Media |
| VD-103 | Configurar banner de tienda | N/A | 1. Subir imagen horizontal (banner) | Banner visible en la página de la tienda | Baja |
| VD-104 | Cambiar información de contacto | N/A | 1. Editar email y teléfono de contacto 2. Guardar | Datos actualizados | Media |
| VD-105 | Campo RUT de empresa obligatorio | N/A | 1. Intentar guardar sin RUT | Error de validación | Alta |

---

## 7. PLANES / SUSCRIPCIÓN DEL VENDOR

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VD-120 | Ver plan activo | Vendor con plan | 1. Ir a `/planes` o sección de plan en dashboard | Plan actual, fecha expiración, features incluidas | Alta |
| VD-121 | Actualizar a plan superior | Plan básico activo | 1. Click "Actualizar Plan" 2. Seleccionar plan pro 3. Pagar | Plan actualizado, nuevos límites aplicados | Alta |
| VD-122 | Plan expirado limita funciones | Plan expirado | 1. Plan vence, login como vendor | Aviso de plan expirado, CTA para renovar | Alta |
| VD-123 | Límite de productos por plan | Plan con límite de 10 productos | 1. Intentar crear producto 11 | Aviso "Has alcanzado el límite de productos de tu plan" | Alta |
