# QA-03 — Carrito, Checkout y Pagos
**Proyecto:** PetsGo  
**Módulo:** Carrito, Checkout, Cupones, Delivery, Pasarelas de Pago  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Cliente, Visitante (invitado)

---

## 1. CARRITO DE COMPRAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-001 | Agregar producto al carrito (cliente) | Sesión activa | 1. Abrir producto 2. Seleccionar cantidad 3. Click "Agregar al carrito" | Item agregado, toast "Producto agregado", contador header +1 | Alta |
| CC-002 | Agregar al carrito como invitado | Sin sesión | 1. Agregar producto sin estar logueado | Carrito funciona con localStorage o sesión temporal | Alta |
| CC-003 | Ver carrito `/carrito` | Al menos 1 item | 1. Click en ícono carrito del header | Lista de productos, cantidades, precios y total | Alta |
| CC-004 | Modificar cantidad en carrito | Carrito con items | 1. En `/carrito`, cambiar cantidad con +/- | Total actualizado en tiempo real | Alta |
| CC-005 | Eliminar item del carrito | Carrito con items | 1. Click ícono de eliminar junto a item | Item removido, total recalculado | Alta |
| CC-006 | Vaciar carrito completo | Carrito con varios items | 1. Click "Vaciar carrito" 2. Confirmar | Carrito vacío, mensaje "Tu carrito está vacío" | Media |
| CC-007 | Agregar misma variante incrementa cantidad | Variante ya en carrito | 1. Agregar variante que ya está en carrito | Cantidad incrementa en lugar de crear item duplicado | Alta |
| CC-008 | Stock insuficiente bloquea cantidad | Producto con stock=3 | 1. Intentar poner cantidad 10 en carrito | Límite de cantidad al stock disponible, mensaje de alerta | Alta |
| CC-009 | Persistencia del carrito al navegar | Carrito con items | 1. Agregar items 2. Navegar a otras páginas 3. Regresar a `/carrito` | Items persisten | Alta |
| CC-010 | Carrito de múltiples vendors | 2+ vendors con productos | 1. Agregar productos de vendor A y vendor B | Carrito acepta mezcla, agrupado por tienda en checkout | Alta |
| CC-011 | Precio en carrito refleja precio actual | Producto con precio cambiado | 1. Agregar producto 2. Vendor modifica precio 3. Revisar carrito | Carrito usa precio actualizado (o avisa de cambio) | Alta |
| CC-012 | Carrito vacío muestra CTA al catálogo | Carrito vacío | 1. Ir a `/carrito` sin items | Mensaje vacío con botón "Ver productos" | Media |

---

## 2. CHECKOUT — DATOS DE ENTREGA

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-020 | Ir al checkout desde carrito | Carrito con items | 1. Click "Proceder al pago" | Navega a `/checkout` | Alta |
| CC-021 | Checkout precarga datos del perfil | Cliente con perfil completo | 1. Ir al checkout | Nombre, email, teléfono, dirección pre-rellenados | Alta |
| CC-022 | Seleccionar dirección guardada | Cliente con 1+ direcciones | 1. En checkout, abrir selector de direcciones | Direcciones guardadas del perfil disponibles | Alta |
| CC-023 | Agregar nueva dirección en checkout | N/A | 1. Click "Nueva dirección" en checkout 2. Completar formulario | Dirección temporal guardada para esta compra | Alta |
| CC-024 | Seleccionar región y comuna | Formulario de dirección | 1. Seleccionar región "Región Metropolitana" | Comunas de esa región cargan en segundo selector | Alta |
| CC-025 | Campo de dirección obligatorio | Formulario vacío | 1. Intentar continuar sin dirección | Error "Dirección es requerida" | Alta |
| CC-026 | Notas de pedido opcionales | Checkout | 1. Dejar campo "Notas" vacío 2. Completar checkout | Pedido se crea sin problema | Media |

---

## 3. CUPONES DE DESCUENTO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-040 | Aplicar cupón de porcentaje válido | Cupón "PETSGO20" 20% activo | 1. En checkout, ingresar "PETSGO20" 2. Click Aplicar | Descuento del 20% aplicado al total, línea de descuento visible | Alta |
| CC-041 | Aplicar cupón de monto fijo válido | Cupón "DESC5000" $5000 activo | 1. Ingresar "DESC5000" 2. Aplicar | $5.000 descontados del total | Alta |
| CC-042 | Cupón inexistente | N/A | 1. Ingresar código "CODIGOFAKE" | Error "Cupón no válido o no existe" | Alta |
| CC-043 | Cupón expirado | Cupón con fecha pasada | 1. Aplicar cupón expirado | Error "Este cupón ha expirado" | Alta |
| CC-044 | Cupón con mínimo de compra no cumplido | Cupón req. $10.000, carrito $5.000 | 1. Aplicar cupón con carrito insuficiente | Error "Compra mínima requerida: $X" | Alta |
| CC-045 | Cupón de un solo uso ya utilizado | Cupón usado por este cliente | 1. Intentar aplicar mismo cupón de nuevo | Error "Ya usaste este cupón" | Alta |
| CC-046 | Cupón válido de uso múltiple | Cupón con uses_remaining > 1 | 1. Aplicar cupón con usos restantes | Descuento aplicado, usos restantes disminuyen | Media |
| CC-047 | Remover cupón aplicado | Cupón activo en checkout | 1. Click "X" junto al cupón | Cupón removido, total original restaurado | Alta |
| CC-048 | Descuento no supera el total | Cupón fijo de $50.000 con carrito $20.000 | 1. Aplicar cupón | Total lleva al mínimo (0), no negativo | Alta |

---

## 4. CÁLCULO DE ENVÍO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-060 | Ver costo de envío por zona | Dirección ingresada | 1. Seleccionar dirección en checkout | Costo de envío calculado y visible | Alta |
| CC-061 | Envío gratis por monto mínimo | Configuración de envío gratis activa | 1. Carrito con monto ≥ mínimo de envío gratis | "Envío gratis" mostrado | Alta |
| CC-062 | Múltiples opciones de envío | Config con varias zonas | 1. En checkout, ver opciones de envío | Radio buttons con opciones y precios | Media |
| CC-063 | Total final suma subtotal + envío - descuento | Cupón + envío activos | 1. Verificar total en resumen | Cálculo correcto: subtotal - cupón + envío | Alta |

---

## 5. PAGO — TRANSBANK WEBPAY

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-080 | Seleccionar Transbank como método | Checkout completo | 1. En paso de pago, seleccionar "Webpay Plus" | Opción seleccionada, CTA habilitado | Alta |
| CC-081 | Redirigir a Webpay | Transbank seleccionado | 1. Click "Pagar con Webpay" | Redirección a formulario oficial de Transbank | Alta |
| CC-082 | Pago exitoso en Webpay (sandbox) | Tarjeta de prueba Transbank | 1. Completar pago en sandbox con datos de prueba | Retorno a `/confirmacion-pedido/{order_id}`, pedido con status `processing` | Alta |
| CC-083 | Pago rechazado en Webpay | Tarjeta de rechazo de prueba | 1. Usar tarjeta de rechazo | Retorno con mensaje de pago rechazado, pedido queda `pending` | Alta |
| CC-084 | Usuario cancela en Webpay | En página de Webpay | 1. Click "Cancelar" en Webpay | Retorno a PetsGo con mensaje de pago cancelado | Alta |
| CC-085 | Webhook de confirmación Transbank | Pago exitoso | 1. Verificar que el webhook llama al endpoint `/petsgo/v1/payment/transbank/confirm` | Pedido actualizado correctamente por el servidor | Alta |

---

## 6. PAGO — MERCADOPAGO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-100 | Seleccionar MercadoPago | Checkout completo | 1. Seleccionar "MercadoPago" | Opción seleccionada | Alta |
| CC-101 | Redirigir a MercadoPago | MP seleccionado | 1. Click "Pagar con MercadoPago" | Redirección a checkout de MercadoPago | Alta |
| CC-102 | Pago exitoso MP (sandbox) | Cuenta MP de prueba | 1. Completar pago en sandbox | Retorno a confirmación, pedido `processing` | Alta |
| CC-103 | Pago rechazado MP | Tarjeta de rechazo MP | 1. Usar tarjeta de prueba de rechazo | Mensaje de rechazo, pedido `pending` | Alta |
| CC-104 | Webhook MP actualiza pedido | Pago aprobado | 1. MP envía notificación webhook | Pedido actualizado, invoice generado | Alta |

---

## 7. PAGO — TEST BYPASS (SOLO DESARROLLO)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-120 | Test bypass crea pedido directamente | Entorno `dev` | 1. Seleccionar "Pago de Prueba" 2. Click Pagar | Pedido creado con status `processing`, sin pasarela real | Alta |
| CC-121 | Test bypass NO disponible en producción | Entorno `production` | 1. Verificar que `test_bypass` no aparece como opción | Método no visible en producción | Alta |

---

## 8. CONFIRMACIÓN DE PEDIDO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CC-140 | Página de confirmación post-pago | Pago exitoso | 1. Completar pago 2. Redirigir a `/confirmacion-pedido/{id}` | Número de pedido, resumen de productos, total, estado visible | Alta |
| CC-141 | Email de confirmación enviado al cliente | Pedido creado | 1. Verificar bandeja de entrada del email del cliente | Email con detalles del pedido recibido | Alta |
| CC-142 | Email notificación al vendor | Pedido con su producto | 1. Verificar email del vendor | Email con detalles del nuevo pedido recibido | Alta |
| CC-143 | Link "Ver mis pedidos" en confirmación | Página de confirmación | 1. Click en enlace | Navega a `/mis-pedidos` con el pedido nuevo visible | Alta |
