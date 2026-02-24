# QA-10 — Valoraciones y Reseñas
**Proyecto:** PetsGo  
**Módulo:** Valoraciones de Productos, Reseñas de Tiendas, Ratings  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Cliente, Visitor (público), Admin

---

## 1. VALORAR PRODUCTO — CLIENTE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-001 | Botón "Valorar" visible en pedido entregado | Pedido `delivered` | 1. Login como cliente 2. Ir a `/mis-pedidos` 3. Localizar pedido entregado | Botón "Valorar [nombre producto]" visible para cada producto del pedido | Alta |
| VR-002 | Botón "Valorar" NO visible en pedidos no entregados | Pedido `processing` | 1. Login como cliente 2. Ir a `/mis-pedidos` 3. Localizar pedido en proceso | No aparecen botones de valoración | Alta |
| VR-003 | Abrir modal de valoración de producto | Pedido `delivered`, producto sin reseña | 1. Click en "Valorar [nombre producto]" | Modal se abre con: estrellas interactivas (1-5), textarea de comentario, botones Cancelar y Enviar | Alta |
| VR-004 | Seleccionar estrellas (1 a 5) | Modal abierto | 1. Click en estrella 1 → visual 1 estrella 2. Click en estrella 3 → visual 3 estrellas 3. Click en estrella 5 → visual 5 estrellas | Estrellas se marcan en amarillo hasta la seleccionada, feedback emoji visible | Alta |
| VR-005 | Enviar reseña de producto con estrellas y comentario | Modal abierto, 4 estrellas seleccionadas, comentario escrito | 1. Seleccionar 4 estrellas 2. Escribir "Excelente calidad" 3. Click "Enviar Valoración" | Reseña guardada, toast de éxito, modal se cierra, botón cambia a ✅ "Ya valorado" | Alta |
| VR-006 | Enviar reseña de producto solo con estrellas (sin comentario) | Modal abierto | 1. Seleccionar 3 estrellas 2. Dejar comentario vacío 3. Click "Enviar Valoración" | Reseña guardada correctamente (comentario es opcional) | Alta |
| VR-007 | No se puede enviar reseña sin estrellas | Modal abierto, 0 estrellas | 1. No seleccionar ninguna estrella 2. Click "Enviar Valoración" | Mensaje de error "Debes seleccionar al menos 1 estrella" o botón deshabilitado | Alta |
| VR-008 | Producto ya valorado muestra badge ✅ | Producto con reseña enviada | 1. Ir a `/mis-pedidos` 2. Localizar pedido entregado | Badge ✅ "Ya valorado" junto al producto | Alta |
| VR-009 | No se puede valorar el mismo producto/pedido dos veces | Producto ya valorado | 1. Intentar click en "Valorar" de producto ya reseñado | Botón deshabilitado o no hay botón, muestra "Ya valorado" | Alta |
| VR-010 | Cancelar modal de valoración | Modal abierto con datos | 1. Click en "Cancelar" o X | Modal se cierra, no se guarda reseña | Media |
| VR-011 | Reseña se persiste correctamente en BD | Reseña enviada | 1. Verificar en BD: `wp_petsgo_reviews` | Registro con: customer_id, order_id, product_id, review_type='product', rating, comment, created_at | Alta |
| VR-012 | Indicador de carga mientras se envía reseña | Modal abierto | 1. Click "Enviar Valoración" | Botón muestra spinner/loading, se deshabilita mientras procesa | Media |

---

## 2. VALORAR TIENDA (VENDOR) — CLIENTE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-020 | Botón "Valorar Tienda" visible en pedido entregado | Pedido `delivered` | 1. Login como cliente 2. Ir a `/mis-pedidos` 3. Localizar pedido entregado | Botón "⭐ Valorar Tienda" visible para el vendor del pedido | Alta |
| VR-021 | Abrir modal de valoración de tienda | Pedido `delivered`, tienda sin reseña | 1. Click en "Valorar Tienda" | Modal se abre con: nombre de la tienda, estrellas interactivas, textarea, botones | Alta |
| VR-022 | Enviar reseña de tienda | Modal abierto | 1. Seleccionar 5 estrellas 2. Escribir "Muy buena atención" 3. Click "Enviar Valoración" | Reseña guardada con review_type='vendor', toast éxito, modal se cierra | Alta |
| VR-023 | Tienda ya valorada muestra badge ✅ | Tienda del pedido ya reseñada | 1. Ir a `/mis-pedidos` | Botón "Valorar Tienda" cambia a "✅ Tienda valorada" | Alta |
| VR-024 | No se puede valorar la misma tienda/pedido dos veces | Tienda ya valorada para este pedido | 1. Intentar valorar misma tienda del mismo pedido | No hay botón o está deshabilitado | Alta |
| VR-025 | Reseña de tienda se persiste en BD | Reseña enviada | 1. Verificar en BD: `wp_petsgo_reviews` | Registro con: review_type='vendor', vendor_id correcto, sin product_id | Alta |

---

## 3. VISUALIZAR RESEÑAS — DETALLE DE PRODUCTO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-040 | Ver rating promedio en detalle de producto | Producto con 3+ reseñas | 1. Navegar a `/producto/:slug` | Rating promedio (ej. ★ 4.3) y cantidad de reseñas (ej. "12 reseñas") visibles | Alta |
| VR-041 | Ver sección "Reseñas de Clientes" | Producto con reseñas | 1. Scroll al final de `/producto/:slug` | Sección con lista de reseñas: avatar, nombre cliente, estrellas, comentario, fecha | Alta |
| VR-042 | Producto sin reseñas muestra estado vacío | Producto nuevo sin reseñas | 1. Navegar a `/producto/:slug` | Texto "Sin reseñas aún" o "0 reseñas", rating no muestra valor | Media |
| VR-043 | Rating promedio se calcula correctamente | Producto con ratings: 5, 4, 3 | 1. Verificar promedio mostrado | Promedio = 4.0 (cálculo correcto) | Alta |
| VR-044 | Reseña muestra información completa | Producto con reseñas | 1. Ver una reseña individual | Nombre del usuario, avatar con inicial, estrellas (1-5 en amarillo), comentario si existe, fecha formateada | Alta |
| VR-045 | Reseña sin comentario se muestra correctamente | Reseña con solo rating | 1. Ver reseña que no tiene comentario | Muestra estrellas y datos del usuario, sin texto de comentario (sin error) | Media |
| VR-046 | Múltiples reseñas ordenadas por fecha | 5+ reseñas | 1. Ver lista de reseñas | Reseñas ordenadas de más reciente a más antigua | Media |
| VR-047 | Rating real reemplaza valor hardcoded | Producto con reseñas | 1. Verificar que NO dice "4.8" fijo | Rating dinámico basado en reseñas reales | Alta |

---

## 4. VISUALIZAR RESEÑAS — TIENDA (VENDOR)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-060 | Ver rating promedio en página de tienda | Vendor con reseñas | 1. Navegar a `/tienda/:slug` | Badge con rating promedio (ej. ★ 4.5) y cantidad de reseñas en header de la tienda | Alta |
| VR-061 | Ver sección "Reseñas de la Tienda" | Vendor con reseñas | 1. Scroll al final de `/tienda/:slug` | Sección con lista de reseñas de la tienda: avatar, nombre, estrellas, comentario, fecha | Alta |
| VR-062 | Tienda sin reseñas muestra estado vacío | Vendor nuevo sin reseñas | 1. Navegar a `/tienda/:slug` | Texto "Sin reseñas aún" o "0 reseñas" | Media |
| VR-063 | Rating de tienda se calcula independiente de productos | Vendor con reseñas de tienda y de producto | 1. Verificar rating de tienda | Solo considera reseñas de review_type='vendor', no mezcla con 'product' | Alta |
| VR-064 | Reseñas de tienda muestran información completa | Vendor con reseñas | 1. Ver una reseña de tienda | Avatar del cliente, nombre, estrellas amarillas, comentario, fecha relativa | Alta |

---

## 5. API REST — ENDPOINTS DE RESEÑAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-080 | POST `/reviews` — enviar reseña de producto | Cliente autenticado, pedido entregado | 1. POST con `{order_id, product_id, rating: 4, comment: "Muy bueno"}` | 200 OK, reseña creada en BD | Alta |
| VR-081 | POST `/reviews` — enviar reseña de tienda | Cliente autenticado, pedido entregado | 1. POST con `{order_id, vendor_id, rating: 5, comment: "Gran servicio"}` | 200 OK, reseña tipo 'vendor' creada | Alta |
| VR-082 | POST `/reviews` — sin autenticación | Sin token | 1. POST `/reviews` sin header de auth | 401 Unauthorized | Alta |
| VR-083 | POST `/reviews` — pedido no pertenece al cliente | Pedido de otro cliente | 1. POST con order_id de otro usuario | 403 Forbidden o error de validación | Alta |
| VR-084 | POST `/reviews` — pedido no entregado | Pedido `processing` | 1. POST con order_id en estado processing | Error: "El pedido debe estar entregado" | Alta |
| VR-085 | POST `/reviews` — reseña duplicada | Ya existe reseña para este producto/pedido | 1. POST con mismos datos | Error: duplicado, no se crea segunda reseña | Alta |
| VR-086 | POST `/reviews` — rating fuera de rango | N/A | 1. POST con rating: 0 o rating: 6 | Error de validación, rating debe ser 1-5 | Alta |
| VR-087 | GET `/reviews/product/{id}` — reseñas de producto | Producto con reseñas | 1. GET público (sin auth) | 200 OK, array de reseñas con customer_name, rating, comment, created_at | Alta |
| VR-088 | GET `/reviews/vendor/{id}` — reseñas de vendor | Vendor con reseñas | 1. GET público (sin auth) | 200 OK, array de reseñas del vendor | Alta |
| VR-089 | GET `/reviews/order-status/{order_id}` — estado de reseñas del pedido | Cliente autenticado con pedido | 1. GET con auth | 200 OK, objeto con items: [{product_id, reviewed: true/false}, {vendor_id, reviewed: true/false}] | Alta |
| VR-090 | GET `/reviews/product/{id}` — producto sin reseñas | Producto nuevo | 1. GET | 200 OK, array vacío | Media |

---

## 6. RATING EN CATÁLOGO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-100 | Rating real en listado de productos | Productos con reseñas variadas | 1. Navegar a `/categorias` o buscar productos | Cada tarjeta de producto muestra su rating real y cantidad de reseñas, no valor fijo | Alta |
| VR-101 | Rating real en listado de tiendas | Vendors con reseñas | 1. Navegar al listado de tiendas | Cada tienda muestra su rating real y review_count | Alta |
| VR-102 | Producto sin reseñas en listado | Producto sin reseñas | 1. Ver producto en listado | Rating muestra 0 o "Sin valoraciones", no 4.8 fijo | Media |

---

## 7. SEGURIDAD Y EDGE CASES

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| VR-120 | Vendor no puede valorar sus propios productos | Vendor con pedido propio (si aplica) | 1. Intentar POST /reviews como vendor para su propio producto | Error o no permitido | Media |
| VR-121 | Inyección XSS en comentario de reseña | N/A | 1. Enviar reseña con `<script>alert('xss')</script>` en comentario | Texto sanitizado, no ejecuta script | Alta |
| VR-122 | Comentarios muy largos (>1000 caracteres) | N/A | 1. Enviar reseña con comentario de 2000 caracteres | Se trunca o se rechaza con error de longitud | Media |
| VR-123 | Varios clientes valoran el mismo producto | 3+ clientes diferentes con pedidos entregados | 1. Cada cliente envía reseña del mismo producto | Todas las reseñas se guardan, promedio calculado correctamente | Alta |
| VR-124 | Eliminar reseña (admin) | Admin logueado, reseña existente | 1. Admin elimina reseña via BD o panel (si UI disponible) | Reseña eliminada, rating se recalcula | Baja |

---

## RESUMEN

| Sección | Casos |
|---|---|
| 1. Valorar Producto — Cliente | 12 |
| 2. Valorar Tienda — Cliente | 6 |
| 3. Reseñas en Detalle de Producto | 8 |
| 4. Reseñas en Página de Tienda | 5 |
| 5. API REST — Endpoints | 11 |
| 6. Rating en Catálogo | 3 |
| 7. Seguridad y Edge Cases | 5 |
| **TOTAL** | **50** |
