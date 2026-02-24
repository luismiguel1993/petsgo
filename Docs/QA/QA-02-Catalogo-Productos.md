# QA-02 — Catálogo y Productos
**Proyecto:** PetsGo  
**Módulo:** Categorías, Productos, Búsqueda, Tiendas Vendor  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Visitante, Cliente, Vendor, Admin

---

## 1. PÁGINA DE INICIO (CATÁLOGO)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-001 | Ver productos destacados en homepage | N/A (visitante) | 1. Abrir `https://petsgo.cl` | Sección "Productos Destacados" visible con tarjetas de producto | Alta |
| CA-002 | Hero/banner principal se muestra | N/A | 1. Cargar homepage | Banner con imagen, título y CTA visibles | Alta |
| CA-003 | Secciones de categorías rápidas | N/A | 1. Cargar homepage | Cards de categorías principales visibles y clicables | Alta |
| CA-004 | Botón "Chatear Ahora" abre chat | N/A | 1. Click en botón "Chatear Ahora" de homepage | Se abre overlay del chatbot | Alta |
| CA-005 | Navegación a homepage desde logo | En cualquier página | 1. Click en logo PetsGo del header | Redirige a `/` | Media |

---

## 2. PÁGINA DE CATEGORÍAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-020 | Listar categorías disponibles | N/A | 1. Ir a `/categorias` o navegar desde menú | Todas las categorías publicadas visibles | Alta |
| CA-021 | Ver productos de una categoría | Al menos 1 producto en categoría | 1. Ir a `/categoria/{slug}` | Productos de esa categoría listados | Alta |
| CA-022 | Filtrar productos por precio | En `/categoria/{slug}` | 1. Mover slider de rango de precio 2. Aplicar filtro | Solo productos dentro del rango se muestran | Alta |
| CA-023 | Ordenar productos por precio ASC | En listado de categoría | 1. Seleccionar "Precio: Menor a Mayor" | Productos reordenados correctamente | Alta |
| CA-024 | Ordenar productos por precio DESC | En listado de categoría | 1. Seleccionar "Precio: Mayor a Menor" | Productos reordenados correctamente | Alta |
| CA-025 | Ordenar por nombre A-Z | En listado de categoría | 1. Seleccionar "Nombre A-Z" | Orden alfabético ascendente | Media |
| CA-026 | Paginación de productos | Categoría con > 12 productos | 1. Scroll al final / click siguiente página | Segunda página cargar productos siguiente batch | Media |
| CA-027 | Categoría vacía muestra mensaje | Categoría sin productos | 1. Navegar a categoría vacía | Mensaje "No hay productos disponibles" visible | Media |
| CA-028 | Buscador en CategoryPage - texto | En `/categoria/{slug}` | 1. Escribir término en buscador interno 2. Esperar | Productos filtrados por nombre/descripción | Alta |
| CA-029 | Buscador mobile muestra ícono completo | Dispositivo ≤ 639px | 1. En mobile, ir a CategoryPage | Input de búsqueda y ícono de lupa ambos visibles, no superpuestos | Alta |

---

## 3. DETALLE DE PRODUCTO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-040 | Ver detalle de producto simple | Producto existente | 1. Click en producto 2. Ir a `/producto/{slug}` | Nombre, precio, descripción, fotos, vendor visible | Alta |
| CA-041 | Galería de imágenes funciona | Producto con múltiples fotos | 1. Click en thumbnails de galería | Imagen principal cambia | Alta |
| CA-042 | Selector de variantes (talla/color) | Producto variable | 1. Abrir producto con variantes 2. Seleccionar combinación | Precio y disponibilidad se actualizan | Alta |
| CA-043 | Variante sin stock deshabilita agregar al carrito | Variante con stock=0 | 1. Seleccionar variante agotada | Botón "Agregar al carrito" deshabilitado + badge "Sin stock" | Alta |
| CA-044 | Cambiar cantidad con +/- spinner | Detalle de producto | 1. Usar botones +/- para cambiar cantidad | Cantidad aumenta/disminuye, no permite negativo ni cero | Alta |
| CA-045 | Agregar al carrito desde detalle | Sesión activa o invitado | 1. Seleccionar cantidad 2. Click "Agregar al carrito" | Producto agregado, notificación toast, contador cabecera actualizado | Alta |
| CA-046 | Productos relacionados se muestran | Otros productos en tienda | 1. Scroll a sección "También te puede gustar" | Máx. 4-6 productos relacionados visibles | Media |
| CA-047 | Link a tienda del vendor | Vendor en producto | 1. Click en nombre de tienda en detalle | Navega a página del vendor/tienda | Media |
| CA-048 | Descripción larga truncada con "Ver más" | Producto con descripción > N chars | 1. Abrir producto 2. Ver descripción | Texto truncado con botón "Ver más" que expande | Baja |
| CA-049 | Precio con descuento muestra precio tachado | Producto en oferta | 1. Abrir producto en oferta | Precio original tachado + precio rebajado + % descuento | Alta |
| CA-050 | Producto inactivo no accesible | Producto con status=draft | 1. Intentar navegar a `/producto/{slug-inactivo}` | 404 o redirección al catálogo | Alta |

---

## 4. BÚSQUEDA GLOBAL

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-060 | Búsqueda desde barra del header | N/A | 1. Click en ícono de búsqueda 2. Escribir "alimento" 3. Presionar Enter | Resultados paginados de productos que coincidan | Alta |
| CA-061 | Búsqueda sin resultados | N/A | 1. Buscar "xyzproductoinexistente123" | Mensaje "Sin resultados para tu búsqueda" | Alta |
| CA-062 | Búsqueda vacía no busca | N/A | 1. Click buscar con campo vacío | No navega, campo indica que es requerido | Media |
| CA-063 | Resultados incluyen nombre y descripción | Producto con término en descripción | 1. Buscar término que solo esté en descripción | Producto aparece en resultados | Media |
| CA-064 | Búsqueda case-insensitive | Producto "Alimento Premium" | 1. Buscar "alimento premium" (minúsculas) | Mismo producto en resultados | Media |

---

## 5. TIENDAS / VENDORS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-080 | Ver página de tienda de vendor | Vendor con productos activos | 1. Navegar a `/tienda/{vendor-slug}` | Logo, nombre, descripción, productos del vendor visibles | Alta |
| CA-081 | Solo productos activos de vendor | Vendor con mezcla de activos/inactivos | 1. Abrir tienda | Solo productos publicados se muestran | Alta |
| CA-082 | Vendor sin productos activos | Vendor reciente | 1. Abrir tienda de vendor vacía | Mensaje "Esta tienda no tiene productos disponibles" | Media |
| CA-083 | Filtros funcionan dentro de tienda | Tienda con variedad de productos | 1. En tienda, aplicar filtro de precio | Productos filtrados correctamente | Media |

---

## 6. GESTIÓN DE PRODUCTOS (VENDOR)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-100 | Crear producto simple | Sesión vendor activo | 1. `/vendor` → Productos → Nuevo Producto 2. Completar nombre, precio, stock, categoría, descripción 3. Publicar | Producto creado con status `publish`, visible en catálogo | Alta |
| CA-101 | Crear producto con variantes | Sesión vendor | 1. Crear producto, activar variantes 2. Agregar atributo "talla" con valores S, M, L 3. Precio y stock por variante 4. Publicar | Variantes creadas, selector funcional en frontend | Alta |
| CA-102 | Subir imágenes a producto | Producto nuevo o existente | 1. Click zona de foto 2. Seleccionar hasta 5 imágenes 3. Guardar | Imágenes subidas a WordPress Media, URLs guardadas | Alta |
| CA-103 | Editar precio de producto existente | Producto publicado | 1. Editar producto 2. Cambiar precio 3. Guardar | Nuevo precio visible en frontend inmediatamente (o tras caché) | Alta |
| CA-104 | Desactivar producto (borrador) | Producto publicado | 1. Editar producto 2. Cambiar status a "borrador" 3. Guardar | Producto no visible en catálogo, pero conservado en BD | Alta |
| CA-105 | Eliminar producto | Producto en borrador | 1. Click eliminar en producto 2. Confirmar | Producto eliminado, no aparece en listados | Alta |
| CA-106 | Vendor no puede ver productos de otro vendor | 2 vendors activos | 1. Login vendor A 2. Intentar editar producto de vendor B via URL | Error 403 o producto no encontrado | Alta |
| CA-107 | Producto sin imagen usa placeholder | Producto sin foto | 1. Publicar producto sin imagen 2. Ver en catálogo | Imagen placeholder de PetsGo se muestra | Baja |
| CA-108 | Precio de oferta menor al precio normal | Creando producto con oferta | 1. Ingresar precio oferta mayor al normal | Validación alerta que oferta debe ser menor al precio regular | Alta |
| CA-109 | Límite de imágenes por producto | Producto abierto | 1. Intentar subir 6+ imágenes | Alerta de límite máximo de imágenes | Baja |

---

## 7. GESTIÓN DE CATEGORÍAS (ADMIN)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CA-120 | Crear categoría nueva | Sesión admin | 1. Panel Admin `/admin` → Gestionar Categorías → Nueva 2. Nombre + slug + imagen 3. Guardar | Categoría creada, visible en frontend | Alta |
| CA-121 | Crear subcategoría | Categoría padre existente | 1. Crear categoría, seleccionar padre | Subcategoría creada con jerarquía correcta | Media |
| CA-122 | Editar nombre de categoría | Categoría existente | 1. Editar nombre 2. Guardar | Nombre actualizado en frontend | Alta |
| CA-123 | Eliminar categoría vacía | Categoría sin productos | 1. Eliminar categoría | Categoría eliminada, no visible en frontend | Alta |
| CA-124 | Eliminar categoría con productos | Categoría con 5+ productos | 1. Intentar eliminar | Warning o los productos quedan en "Sin categoría" | Alta |
