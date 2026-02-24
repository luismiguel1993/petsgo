# QA-11 — Tienda PetsGo (Admin como Vendedor)
**Proyecto:** PetsGo  
**Módulo:** Inventario PetsGo Oficial, Productos Propios del Admin, Vendor Auto-generado  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Admin (administrator)  
**URL:** `/admin` → Tab "Tienda PetsGo"

---

## 1. VENDOR PETSGO OFICIAL — AUTO-CREACIÓN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-001 | Vendor "PetsGo Oficial" se crea automáticamente | Primera carga del sistema | 1. Activar petsgo-core.php 2. Verificar BD `wp_petsgo_vendors` | Registro vendor con: store_name="PetsGo Oficial", user_id=admin, commission_rate=0%, status=active | Alta |
| TP-002 | Vendor PetsGo tiene suscripción permanente | Vendor PetsGo creado | 1. Verificar `wp_petsgo_subscriptions` | Suscripción con plan "Profesional", end_date lejano (2099), max_products alto | Alta |
| TP-003 | Vendor PetsGo no duplica al recargar | Sistema ya inicializado | 1. Recargar sistema múltiples veces | Solo un registro de "PetsGo Oficial" en vendors, sin duplicados | Alta |
| TP-004 | Vendor PetsGo aparece en listado público de tiendas | Vendor activo | 1. Navegar a listado de tiendas (público) | "PetsGo Oficial" visible como una tienda más | Alta |
| TP-005 | Página de tienda PetsGo accesible | Vendor creado | 1. Navegar a `/tienda/petsgo-oficial` | Página de tienda carga con logo, nombre "PetsGo Oficial", productos | Alta |
| TP-006 | Comisión 0% para PetsGo | Venta de producto PetsGo | 1. Completar compra de producto PetsGo 2. Verificar comisiones | Comisión = 0%, todo el ingreso va a la plataforma | Alta |

---

## 2. ACCESO AL TAB "TIENDA PETSGO" — ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-020 | Tab "Tienda PetsGo" visible en panel admin | Admin logueado | 1. Login como admin 2. Ir a `/admin` | Tab "Tienda PetsGo" visible con ícono de bolsa en la barra lateral/tabs | Alta |
| TP-021 | Tab no visible para otros roles | Sesión de cliente/vendor/rider | 1. Intentar acceder a `/admin` | No se muestra tab "Tienda PetsGo" (o no se accede a `/admin`) | Alta |
| TP-022 | Tab muestra inventario al cargar | Admin, productos PetsGo existentes | 1. Click en tab "Tienda PetsGo" | Tabla con productos de PetsGo Oficial: imagen, nombre, categoría, precio, stock | Alta |
| TP-023 | Tab muestra estado vacío si no hay productos | Admin, sin productos PetsGo | 1. Click en tab "Tienda PetsGo" | Mensaje "No hay productos" o tabla vacía con botón "Agregar Producto" | Media |

---

## 3. AGREGAR PRODUCTO — ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-040 | Abrir formulario de nuevo producto | Admin en tab "Tienda PetsGo" | 1. Click en botón "+ Agregar Producto" | Formulario modal/inline se muestra con campos: nombre, descripción, precio, precio oferta, stock, categoría | Alta |
| TP-041 | Crear producto con todos los campos completos | Formulario abierto | 1. Nombre: "Collar Premium PetsGo" 2. Descripción: "Collar de cuero..." 3. Precio: 15990 4. Stock: 50 5. Categoría: seleccionar 6. Click "Guardar" | Producto creado, aparece en tabla, toast de éxito | Alta |
| TP-042 | Producto creado pertenece al vendor PetsGo | Producto recién creado | 1. Verificar en BD `wp_petsgo_products` | vendor_id corresponde al vendor "PetsGo Oficial" | Alta |
| TP-043 | Producto creado aparece en catálogo público | Producto PetsGo activo | 1. Navegar a `/categorias` o buscar el producto | Producto visible en el catálogo general como cualquier otro producto | Alta |
| TP-044 | Validación: nombre requerido | Formulario, nombre vacío | 1. Dejar nombre vacío 2. Click "Guardar" | Error de validación, producto no se crea | Alta |
| TP-045 | Validación: precio requerido y numérico | Formulario, precio vacío o texto | 1. Dejar precio vacío o ingresar "abc" 2. Click "Guardar" | Error de validación | Alta |
| TP-046 | Validación: stock numérico y ≥ 0 | Formulario, stock negativo | 1. Ingresar stock: -5 2. Click "Guardar" | Error: stock no puede ser negativo | Media |
| TP-047 | Precio de oferta menor al precio normal | Formulario | 1. Precio: 20000 2. Precio oferta: 15000 3. Guardar | Producto creado con ambos precios, se muestra tachado/oferta en catálogo | Media |
| TP-048 | Categoría desplegable carga categorías existentes | Formulario abierto | 1. Ver dropdown de categoría | Lista de categorías del sistema cargada correctamente | Alta |
| TP-049 | Cancelar formulario de creación | Formulario con datos | 1. Click "Cancelar" | Formulario se cierra, producto no se crea | Media |

---

## 4. SUBIR IMAGEN DE PRODUCTO — ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-060 | Subir imagen principal del producto | Producto PetsGo existente | 1. Click en ícono de imagen/cámara 2. Seleccionar archivo JPG/PNG 3. Subir | Imagen subida, se muestra miniatura en tabla y en detalle de producto | Alta |
| TP-061 | Imagen se muestra en catálogo público | Producto con imagen subida | 1. Navegar al producto en catálogo | Imagen visible en tarjeta de producto y detalle | Alta |
| TP-062 | Subir imagen en formato JPG | Archivo .jpg | 1. Seleccionar JPG 2. Subir | Imagen aceptada y guardada | Alta |
| TP-063 | Subir imagen en formato PNG | Archivo .png | 1. Seleccionar PNG 2. Subir | Imagen aceptada y guardada | Alta |
| TP-064 | Subir imagen en formato WebP | Archivo .webp | 1. Seleccionar WebP 2. Subir | Imagen aceptada y guardada | Media |
| TP-065 | Rechazar archivo no imagen | Archivo .pdf o .exe | 1. Intentar subir un PDF | Error: formato no permitido | Alta |
| TP-066 | Reemplazar imagen existente | Producto con imagen previa | 1. Subir nueva imagen | Imagen anterior reemplazada por la nueva | Media |
| TP-067 | Indicador de carga al subir imagen | N/A | 1. Subir imagen pesada | Spinner/progreso visible durante la subida | Media |

---

## 5. EDITAR PRODUCTO — ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-080 | Abrir formulario de edición | Producto PetsGo existente | 1. Click en botón "Editar" (ícono lápiz) del producto | Formulario se abre pre-llenado con datos actuales del producto | Alta |
| TP-081 | Editar nombre del producto | Formulario de edición | 1. Cambiar nombre a "Collar Premium PetsGo v2" 2. Guardar | Nombre actualizado en tabla y en catálogo público | Alta |
| TP-082 | Editar precio del producto | Formulario de edición | 1. Cambiar precio de 15990 a 12990 2. Guardar | Precio actualizado | Alta |
| TP-083 | Editar stock del producto | Formulario de edición | 1. Cambiar stock de 50 a 100 2. Guardar | Stock actualizado | Alta |
| TP-084 | Editar categoría del producto | Formulario de edición | 1. Cambiar categoría 2. Guardar | Categoría actualizada, producto aparece en nueva categoría en catálogo | Alta |
| TP-085 | Editar descripción del producto | Formulario de edición | 1. Modificar texto de descripción 2. Guardar | Descripción actualizada en detalle de producto | Media |
| TP-086 | Cambios se reflejan en catálogo inmediatamente | Producto editado | 1. Editar producto 2. Navegar al catálogo | Datos actualizados visibles para visitantes | Alta |
| TP-087 | Validaciones aplican al editar | Formulario de edición | 1. Borrar nombre (vacío) 2. Guardar | Mismo error de validación que al crear | Alta |

---

## 6. ELIMINAR PRODUCTO — ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-100 | Eliminar producto con confirmación | Producto PetsGo existente | 1. Click en botón "Eliminar" (ícono papelera) 2. Confirmar en diálogo | Producto eliminado de la tabla y del catálogo público | Alta |
| TP-101 | Cancelar eliminación | Diálogo de confirmación | 1. Click "Cancelar" en el diálogo | Producto no se elimina | Alta |
| TP-102 | Producto eliminado desaparece del catálogo | Producto recién eliminado | 1. Navegar al catálogo 2. Buscar el producto | Producto ya no aparece en búsqueda ni listado | Alta |
| TP-103 | Producto eliminado con pedidos existentes | Producto con historial de ventas | 1. Eliminar producto que tiene pedidos | Producto eliminado, pedidos históricos mantienen datos (no se borran pedidos) | Alta |
| TP-104 | Toast de éxito al eliminar | Producto eliminado | 1. Completar eliminación | Toast/notificación: "Producto eliminado exitosamente" | Media |

---

## 7. API REST — ENDPOINTS DE INVENTARIO ADMIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-120 | GET `/admin/inventory` — listar productos PetsGo | Admin autenticado | 1. GET con token admin | 200 OK, array de productos del vendor PetsGo con id, name, price, stock, category, image | Alta |
| TP-121 | GET `/admin/inventory` — sin autenticación | Sin token | 1. GET sin auth | 401 Unauthorized | Alta |
| TP-122 | GET `/admin/inventory` — como cliente | Token de cliente | 1. GET con token de subscriber | 403 Forbidden | Alta |
| TP-123 | POST `/admin/products` — crear producto | Admin autenticado | 1. POST con `{name, description, price, stock, category_id}` | 201 Created, producto creado con vendor_id de PetsGo | Alta |
| TP-124 | PUT `/admin/products/{id}` — actualizar producto | Admin autenticado, producto existente | 1. PUT con campos a actualizar | 200 OK, producto actualizado | Alta |
| TP-125 | PUT `/admin/products/{id}` — producto de otro vendor | Admin autenticado, producto de vendor común | 1. PUT sobre producto que NO es de PetsGo | Error: solo se pueden editar productos PetsGo, o actualización exitosa (según permisos admin) | Alta |
| TP-126 | DELETE `/admin/products/{id}` — eliminar producto | Admin autenticado | 1. DELETE sobre producto PetsGo | 200 OK, producto eliminado | Alta |
| TP-127 | POST `/admin/products/{id}/image` — subir imagen | Admin autenticado, archivo válido | 1. POST multipart/form-data con imagen | 200 OK, URL de imagen devuelta | Alta |
| TP-128 | DELETE `/admin/products/{id}` — sin autenticación | Sin token | 1. DELETE sin auth | 401 Unauthorized | Alta |
| TP-129 | POST `/admin/products` — datos inválidos | Admin autenticado | 1. POST sin nombre ni precio | Error de validación, producto no creado | Alta |

---

## 8. INTEGRACIÓN CON CATÁLOGO PÚBLICO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TP-140 | Productos PetsGo mezclados con productos de vendors | Productos de múltiples vendors + PetsGo | 1. Navegar a `/categorias` | Productos de PetsGo aparecen junto a los de otros vendors, sin distinción especial | Alta |
| TP-141 | Filtrar por tienda "PetsGo Oficial" | Productos PetsGo activos | 1. Navegar a `/tienda/petsgo-oficial` | Solo productos del vendor PetsGo Oficial | Alta |
| TP-142 | Agregar producto PetsGo al carrito | Producto PetsGo en catálogo | 1. Click "Agregar al carrito" en producto PetsGo | Producto se añade al carrito normalmente | Alta |
| TP-143 | Comprar producto PetsGo completa el flujo | Producto PetsGo en carrito | 1. Checkout con producto PetsGo 2. Pagar (test_bypass) | Pedido creado, boleta generada, flujo idéntico a cualquier vendor | Alta |
| TP-144 | Pedido de producto PetsGo visible en admin | Compra completada | 1. Panel Admin → Pedidos | Pedido visible con vendor "PetsGo Oficial" | Alta |
| TP-145 | Stock de producto PetsGo se descuenta | Compra de producto con stock=50 | 1. Comprar 2 unidades 2. Verificar stock | Stock = 48 | Alta |
| TP-146 | Producto PetsGo sin stock no es comprable | Producto con stock=0 | 1. Intentar agregar al carrito | Botón deshabilitado o mensaje "Sin stock" | Alta |

---

## RESUMEN

| Sección | Casos |
|---|---|
| 1. Vendor PetsGo Oficial — Auto-creación | 6 |
| 2. Acceso al Tab — Admin | 4 |
| 3. Agregar Producto — Admin | 10 |
| 4. Subir Imagen — Admin | 8 |
| 5. Editar Producto — Admin | 8 |
| 6. Eliminar Producto — Admin | 5 |
| 7. API REST — Endpoints | 10 |
| 8. Integración con Catálogo Público | 7 |
| **TOTAL** | **58** |
