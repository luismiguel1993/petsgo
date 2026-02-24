# QA-09 — Mobile y Diseño Responsivo
**Proyecto:** PetsGo  
**Módulo:** Responsividad, UX Mobile, Accesibilidad  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Breakpoints:** XS ≤ 400px | SM ≤ 639px | MD ≤ 1023px | LG ≥ 1024px

---

## 1. HEADER Y NAVEGACIÓN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-001 | Menú hamburgesa aparece en mobile | Viewport ≤ 639px | 1. Abrir sitio en mobile | Ícono de hamburguesa visible, menú horizontal oculto | Alta |
| MB-002 | Menú hamburguesa se abre | Mobile, menú cerrado | 1. Click en ícono hamburguesa | Menú desplegable/drawer animado aparece | Alta |
| MB-003 | Menú hamburguesa se cierra | Mobile, menú abierto | 1. Click en ícono X o fuera del menú | Menú se cierra | Alta |
| MB-004 | Links del menú mobile navegan correcto | Menú mobile abierto | 1. Click en "Categorías" | Navega a la página y cierra el menú | Alta |
| MB-005 | Logo visible en mobile | Viewport ≤ 639px | 1. Ver header | Logo PetsGo visible y correctamente dimensionado | Alta |
| MB-006 | Contador de carrito visible en mobile | Items en carrito | 1. Ver header en mobile | Badge de cantidad del carrito visible | Alta |
| MB-007 | Ícono de búsqueda abre buscador | Mobile | 1. Click en ícono de lupa | Input de búsqueda aparece o se expande | Alta |
| MB-008 | Header no overflow en XS (375px) | iPhone SE viewport | 1. Abrir en 375px de ancho | Header sin scroll horizontal | Alta |

---

## 2. HOMEPAGE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-020 | Hero banner se adapta a mobile | Viewport ≤ 639px | 1. Ver homepage | Banner sin overflow, texto legible, CTA visible | Alta |
| MB-021 | Grid de categorías en mobile | Homepage | 1. Ver sección de categorías en mobile | 2 columnas en mobile (no 4) | Alta |
| MB-022 | Tarjetas de producto en grid responsivo | Sección de productos | 1. Ver en diferentes anchos | 4 col → 3 col → 2 col → 1 col según breakpoint | Alta |
| MB-023 | Botón "Chatear Ahora" accesible mobile | Homepage mobile | 1. Scroll a sección de CTA | Botón completamente visible y clicable | Alta |

---

## 3. CATÁLOGO Y CATEGORÍAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-040 | CategoryPage: buscador en mobile | ≤ 639px | 1. Ir a `/categoria/{slug}` | Input de búsqueda ocupa ancho completo, ícono de lupa visible | Alta |
| MB-041 | Filtros de categoría en mobile colapsados | Mobile | 1. Ver sidebar de filtros | Filtros en panel colapsable/drawer, no en sidebar fijo | Alta |
| MB-042 | Botón "Aplicar filtros" visible | Panel de filtros mobile | 1. Abrir panel de filtros | Botón de aplicar siempre visible (sticky o al final) | Alta |
| MB-043 | Cards de productos sin overflow de texto | Mobile con títulos largos | 1. Ver tarjeta con título largo | Texto truncado con ellipsis, sin desbordamiento | Media |
| MB-044 | Imagen de producto no distorsionada | Tarjeta de producto | 1. Ver grid de productos | Imágenes con aspect ratio correcto (cuadradas) | Media |

---

## 4. DETALLE DE PRODUCTO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-060 | Galería de imágenes en mobile | Producto con galería | 1. Abrir detalle de producto en mobile | Galería con swipe o thumbnails | Alta |
| MB-061 | Selector de variantes accesible | Variantes disponibles | 1. Usar selctor de variante en mobile | Selector tipo chips o dropdown usable con dedo | Alta |
| MB-062 | Botón "Agregar al carrito" sticky | Producto con descripción larga | 1. Scroll hacia abajo en detalle | CTA sticky al fondo o siempre visible | Media |
| MB-063 | Precio prominente | Mobile | 1. Ver precio en detalle | Precio >= 18px, claramente legible | Alta |
| MB-064 | Sin scroll horizontal en detalle | Mobile XS | 1. Abrir detalle en 375px | Ningún elemento causa scroll horizontal | Alta |

---

## 5. CARRITO Y CHECKOUT

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-080 | Carrito usable en mobile | Items en carrito | 1. Abrir `/carrito` en mobile | Lista de items, +/-, eliminar, total, botón checkout todos accesibles | Alta |
| MB-081 | Checkout: pasos visibles en mobile | En checkout | 1. Ver indicador de pasos | Steps (1, 2, 3) visibles y coherentes con el avance | Alta |
| MB-082 | Formulario de dirección usable en touch | Mobile | 1. Completar formulario de checkout | Todos los campos accesibles, labels visibles | Alta |
| MB-083 | Dropdown región/comuna en mobile | Formulario checkout | 1. Abrir selector nativo | Selector nativo del SO abre correctamente | Alta |
| MB-084 | Resumen de pedido visible en checkout | Mobile | 1. Ver resumen en checkout | Productos, subtotal, envío, total visible sin scroll excesivo | Alta |
| MB-085 | Botón "Pagar" siempre accesible | Checkout final en mobile | 1. Ver CTA de pago | Botón no oculto por teclado ni por footer | Alta |

---

## 6. CHATBOT EN MOBILE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-100 | Chat fullscreen en ≤ 600px | Viewport mobile | 1. Abrir chatbot | Ocupa 100vw × 100vh (o 100dvh), sin bordes | Alta |
| MB-101 | Input de chat no se cubre con teclado | iOS/Android | 1. Tap en input de chat | Input sube junto con el teclado virtual | Alta |
| MB-102 | Header del chat con botones usables | Mobile | 1. Ver header del chatbot | Iconos +, reloj, X correctamente espaciados para touch (≥ 44px) | Alta |
| MB-103 | Panel historial usable en mobile | Mobile, historial | 1. Abrir panel de historial | Lista scrolleable, botones de eliminar accesibles | Alta |
| MB-104 | Chat no puede abrirse 2 veces | Mobile | 1. Tap repetidamente en botón de chat | Solo 1 instancia del chat abierta | Media |

---

## 7. DASHBOARDS EN TABLET

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-120 | Dashboard Vendor funcional en tablet (768px) | Vendor logueado | 1. Abrir `/vendor` en tablet | Pestañas, tablas y formularios usables en touch | Alta |
| MB-121 | Dashboard Rider funcional en tablet | Rider logueado | 1. Abrir `/rider` en tablet | Toggle disponibilidad, lista pedidos accesibles | Alta |
| MB-122 | Panel Admin funcional en tablet | Admin logueado | 1. Abrir `/admin` en tablet | Menú lateral colapsable, tablas con scroll horizontal si necesario | Alta |
| MB-123 | Tablas largas tienen scroll horizontal | Tabla en mobile/tablet | 1. Ver tabla con muchas columnas | Tabla permite scroll horizontal sin romper layout | Alta |

---

## 8. FOOTER

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-140 | Footer en columna en mobile | Mobile | 1. Hacer scroll al footer | Columnas del footer apiladas verticalmente | Media |
| MB-141 | Links del footer navegan correctamente y hacen scroll-to-top | Mobile | 1. Click en link del footer | Navega a página destino Y hace scroll al top | Alta |
| MB-142 | Redes sociales del footer clicables | Mobile | 1. Click en ícono social | Abre el link en nueva pestaña | Media |

---

## 9. PRUEBAS DE RENDIMIENTO MOBILE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-160 | LCP (Largest Contentful Paint) < 2.5s | Conexión 4G (throttle en DevTools) | 1. Abrir DevTools Lighthouse → Mobile 2. Analizar | LCP < 2.5 segundos | Alta |
| MB-161 | Sin CLS (Cumulative Layout Shift) > 0.1 | Cualquier page | 1. Ver métricas de layout shift | CLS < 0.1 | Media |
| MB-162 | Imágenes optimizadas (WebP/AVIF) | DevTools Network | 1. Revisar imágenes en network tab | Formato WebP o AVIF, no BMP/raw PNG | Media |
| MB-163 | Tamaño de bundle JS razonable | DevTools Network | 1. Revisar bundle principal | Bundle < 500KB gzipped | Media |

---

## 10. PÁGINAS LEGALES Y ESTÁTICAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MB-180 | Página `/terminos` accesible y responsiva | N/A | 1. Navegar a `/terminos` | Texto legible en mobile, sin overflow | Media |
| MB-181 | Página `/privacidad` accesible | N/A | 1. Navegar a `/privacidad` | Contenido visible | Media |
| MB-182 | Página 404 en mobile | URL inexistente | 1. Navegar a URL no existente | Página 404 amigable con link al home | Media |
| MB-183 | `/verificar-boleta/:token` en mobile | Token válido | 1. Abrir URL de verificación en celular | Página bien formateada, todos los datos visibles | Alta |
