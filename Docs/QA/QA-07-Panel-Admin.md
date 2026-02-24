# QA-07 — Panel de Administrador
**Proyecto:** PetsGo  
**Módulo:** Panel Admin (wp-admin + frontend `/admin`)  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Admin (administrator)  
**URLs:** `/admin` (frontend) + `https://petsgo.cl/wp-admin/` (WP backend)

---

## 1. ACCESO Y SEGURIDAD

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-001 | Login admin vía frontend | Admin WP | 1. Login con credenciales de admin | Token PetsGo generado, acceso a `/admin` | Alta |
| AD-002 | Acceso a `/admin` sin sesión | Sin sesión | 1. Navegar a `/admin` | Redirige a `/login` | Alta |
| AD-003 | Cliente no puede acceder a `/admin` | Sesión cliente | 1. Navegar a `/admin` | Redirige o muestra acceso denegado | Alta |
| AD-004 | Admin puede navegar wp-admin normalmente | Admin | 1. Ir a `/wp-admin/` | Panel WordPress carga sin conflicto con token frontend | Alta |

---

## 2. GESTIÓN DE USUARIOS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-020 | Listar todos los usuarios | Admin logueado | 1. Panel Admin → Usuarios | Lista completa con nombre, email, rol, estado | Alta |
| AD-021 | Filtrar usuarios por rol | Múltiples roles | 1. Seleccionar filtro "Vendor" | Solo usuarios con rol vendor | Alta |
| AD-022 | Buscar usuario por nombre/email | N/A | 1. Escribir en buscador de usuarios | Resultados filtrados | Alta |
| AD-023 | Ver detalle de usuario | Usuario en lista | 1. Click en usuario | Datos completos: perfil, pedidos, mascotas, historial | Alta |
| AD-024 | Cambiar rol de usuario | N/A | 1. Editar usuario 2. Cambiar rol 3. Guardar | Rol actualizado, permisos cambian inmediatamente | Alta |
| AD-025 | Bloquear/suspender usuario | Usuario activo | 1. Toggle "Suspender cuenta" | Usuario no puede loguearse, token invalidado | Alta |
| AD-026 | Desbloquear usuario | Usuario suspendido | 1. Toggle "Reactivar" | Usuario puede volver a loguearse | Alta |
| AD-027 | Eliminar usuario (soft delete) | Usuario no admin | 1. Click "Eliminar" 2. Confirmar | Cuenta marcada como eliminada, datos preservados | Alta |
| AD-028 | Crear usuario manualmente | N/A | 1. Click "Nuevo Usuario" 2. Completar datos + rol 3. Guardar | Usuario creado, email de bienvenida enviado | Media |

---

## 3. GESTIÓN DE VENDORS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-040 | Listar todos los vendors | N/A | 1. Panel Admin → Vendors | Lista de todos los vendors con nombre de tienda, estado | Alta |
| AD-041 | Aprobar vendor nuevo | Vendor `pending` | 1. Click "Aprobar" en vendor pendiente | Status cambia a `active`, vendor recibe email | Alta |
| AD-042 | Rechazar vendor con motivo | Vendor `pending` | 1. Click "Rechazar" 2. Ingresar motivo 3. Confirmar | Vendor rechazado, motivo guardado | Alta |
| AD-043 | Suspender vendor activo | Vendor `active` | 1. Cambiar estado a `suspended` | Tienda oculta del catálogo, vendor no puede operar | Alta |
| AD-044 | Ver métricas de un vendor | Vendor con ventas | 1. Click en vendor → "Ver métricas" | Ventas totales, productos activos, comisiones generadas | Alta |
| AD-045 | Procesar retiro de vendor | Solicitud de retiro pendiente | 1. Ver solicitudes de retiro 2. Aprobar/Rechazar | Estado de retiro actualizado | Alta |

---

## 4. GESTIÓN DE RIDERS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-060 | Listar solicitudes de riders | Riders `pending_review` | 1. Panel Admin → Riders | Lista de riders pendientes de revisión | Alta |
| AD-061 | Ver documentos de rider | Rider con docs subidos | 1. Click en rider 2. Ver documentos | Imágenes de carnet y licencia visibles | Alta |
| AD-062 | Aprobar rider | Rider `pending_review` | 1. Click "Aprobar" | Rider `approved`, puede acceder al dashboard | Alta |
| AD-063 | Rechazar rider con motivo | Rider `pending_review` | 1. Click "Rechazar" 2. Ingresar motivo | Rider `rejected`, motivo visible en su panel | Alta |
| AD-064 | Ver historial de entregas de un rider | Rider con historial | 1. Click en rider → "Historial" | Lista de entregas con estado y ganancias | Media |
| AD-065 | Procesar pago a rider | Solicitud de pago pendiente | 1. Ver solicitudes de pago 2. Marcar como pagado | Solicitud marcada como completada | Alta |
| AD-066 | Ver riders activos en tiempo real | 1+ riders `online` | 1. Panel Admin → Riders → "En línea" | Lista de riders activos con estado actual | Media |

---

## 5. GESTIÓN DE PEDIDOS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-080 | Ver todos los pedidos | Admin | 1. Panel Admin → Pedidos | TODOS los pedidos del sistema, todos los vendors | Alta |
| AD-081 | Filtrar pedidos por estado | Pedidos variados | 1. Usar filtro de estado | Lista filtrada correctamente | Alta |
| AD-082 | Filtrar pedidos por vendor | N/A | 1. Seleccionar vendor en filtro | Solo pedidos de ese vendor | Alta |
| AD-083 | Filtrar por rango de fechas | N/A | 1. Ingresar fecha inicio y fin | Pedidos del período | Alta |
| AD-084 | Cambiar estado de cualquier pedido | N/A | 1. Editar pedido 2. Cambiar estado | Sin restricción de rol, cualquier transición posible | Alta |
| AD-085 | Reembolsar pedido | Pedido `delivered` | 1. Click "Reembolso" 2. Ingresar motivo | Reembolso registrado, vendedor/cliente notificados | Alta |
| AD-086 | Cancelar pedido con rembolso | Pedido `processing` | 1. Cancelar + reembolso | Pedido `cancelled`, transacción de reembolso iniciada | Alta |

---

## 6. GESTIÓN DE PRODUCTOS (ADMIN)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-100 | Ver todos los productos del sistema | Admin | 1. Panel Admin → Productos | Productos de TODOS los vendors | Alta |
| AD-101 | Aprobar/publicar producto pendiente | Producto `pending` | 1. Cambiar estado a `publish` | Producto visible en catálogo | Alta |
| AD-102 | Despublicar producto de cualquier vendor | N/A | 1. Cambiar estado a `draft` | Producto ocultado inmediatamente | Alta |
| AD-103 | Eliminar producto de cualquier vendor | N/A | 1. Eliminar producto | Eliminado del sistema | Alta |
| AD-104 | Crear categorías de productos | N/A | 1. Crear categoría con nombre, slug, imagen | Categoría disponible para vendors | Alta |

---

## 7. FINANZAS Y COMISIONES

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-120 | Ver balance total de la plataforma | Admin | 1. Panel Admin → Finanzas | Ingresos brutos, comisiones, pagos a vendors | Alta |
| AD-121 | Configurar % de comisión global | Admin | 1. Finanzas → Configuración 2. Cambiar % comisión por defecto | Nuevo porcentaje aplicado a futuros pedidos | Alta |
| AD-122 | Ver solicitudes de retiro de vendors | Vendors con solicitudes | 1. Finanzas → Retiros Vendors | Lista de solicitudes pendientes con monto | Alta |
| AD-123 | Aprobar retiro de vendor | Solicitud pendiente | 1. Click "Aprobar" | Retiro marcado como completado | Alta |
| AD-124 | Ver solicitudes de pago de riders | Riders con solicitudes | 1. Finanzas → Pagos Riders | Lista de solicitudes | Alta |
| AD-125 | Exportar reporte financiero | N/A | 1. Exportar CSV/Excel del período | Archivo con todas las transacciones | Media |

---

## 8. GESTIÓN DE CUPONES

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-140 | Crear cupón de porcentaje | Admin | 1. Cupones → Nuevo Cupón 2. Tipo: porcentaje, valor 20%, código | Cupón creado y activo | Alta |
| AD-141 | Crear cupón de monto fijo | Admin | 1. Tipo: monto fijo, valor $5000 | Cupón creado | Alta |
| AD-142 | Configurar fecha de expiración | Cupón nuevo | 1. Agregar fecha límite | Cupón expira automáticamente en esa fecha | Alta |
| AD-143 | Configurar mínimo de compra | Cupón nuevo | 1. Ingresar monto mínimo | Solo aplica si el carrito supera ese monto | Alta |
| AD-144 | Limitar a un solo uso por cliente | Cupón nuevo | 1. Activar "Un uso por cliente" | Cliente no puede usar el mismo cupón 2 veces | Alta |
| AD-145 | Desactivar cupón activo | Cupón activo | 1. Toggle "Desactivar" | Cupón no acepta más usos | Alta |
| AD-146 | Ver estadísticas de uso de cupón | Cupón con usos | 1. Click en cupón | Veces usado, clientes que lo usaron, descuento total generado | Media |

---

## 9. SOPORTE TICKETS (ADMIN)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-160 | Ver todos los tickets de soporte | Admin | 1. Panel Admin → Soporte | Lista de TODOS los tickets del sistema | Alta |
| AD-161 | Filtrar tickets por estado | N/A | 1. Filtrar por abierto/en progreso/cerrado | Solo tickets del estado seleccionado | Alta |
| AD-162 | Asignar ticket a agente de soporte | Ticket sin asignar | 1. Seleccionar agente en dropdown 2. Guardar | Ticket asignado, agente notificado | Alta |
| AD-163 | Responder ticket | Ticket abierto | 1. Escribir respuesta 2. Enviar | Respuesta guardada, cliente notificado | Alta |
| AD-164 | Cerrar ticket | Ticket resuelto | 1. Click "Cerrar ticket" | Estado `closed`, timestamp de cierre | Alta |

---

## 10. GESTIÓN DE PLANES

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-180 | Ver planes disponibles | Admin | 1. Panel Admin → Planes | Lista de planes: nombre, precio, features, activos | Alta |
| AD-181 | Crear nuevo plan | Admin | 1. Nuevo Plan → completar nombre, precio, límite de productos, comisión 2. Guardar | Plan disponible para vendors | Alta |
| AD-182 | Editar plan existente | Plan activo | 1. Editar precio o features 2. Guardar | Cambios aplicados a nuevos subscribers (no retroactivo) | Alta |
| AD-183 | Desactivar plan | Plan activo | 1. Desactivar plan | Plan no ofrecido a nuevos vendors | Alta |
| AD-184 | Ver vendors por plan | N/A | 1. Click en plan | Lista de vendors que tienen ese plan | Media |

---

## 11. CONFIGURACIÓN GENERAL

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-200 | Ver configuración general de la plataforma | Admin | 1. Panel Admin → Configuración | Nombre sitio, email contacto, moneda, IVA | Alta |
| AD-201 | Cambiar moneda del sitio | N/A | 1. Cambiar a USD 2. Guardar | Precios mostrados en USD | Media |
| AD-202 | Configurar zonas de envío | N/A | 1. Agregar zona nueva 2. Precio por zona | Zona activa en checkout | Alta |
| AD-203 | Configurar credenciales de Transbank | N/A | 1. Ingresar Commerce Code y API key 2. Guardar | Transbank funcional en checkout | Alta |
| AD-204 | Configurar credenciales de MercadoPago | N/A | 1. Ingresar Access Token 2. Guardar | MercadoPago funcional | Alta |
| AD-205 | Activar modo mantenimiento | N/A | 1. Toggle mantenimiento | Frontend muestra página de mantenimiento | Media |
