# QA-08 — Selectores Verificados: Chatbot IA y Soporte

**Proyecto:** PetsGo  
**Fecha:** 2026-03-22  
**Fuente:** Código fuente `BotChatOverlay.jsx`, `SupportPage.jsx`, `petsgo-core.php`  
**Propósito:** Selectores Playwright verificados contra el código real para tests del Chatbot y Soporte.

> ⚠️ **REGLA:** Si un selector de este documento contradice al CLAUDE.md, **este documento tiene prioridad**.

> ⚠️ **NOTA IMPORTANTE:** BotChatOverlay usa **clases CSS propias** (`.pgchat-*`), NO inline styles ni Tailwind. SupportPage usa **inline styles CSS-in-JS**.

---

## 1. BotChatOverlay — Botón Flotante (Trigger)

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Botón flotante | `page.locator('.pgchat-trigger')` | Fixed bottom-right, 60×60px |
| Color fondo | `#FFC400` (amarillo) | border-radius 50% |
| Ícono | MessageCircle de lucide-react | Blanco, 28px |
| Tooltip | `page.locator('.pgchat-tooltip')` | "¿Necesitas ayuda?" — animación bounce |
| Emojis orbitando | 12 emojis animados (🐶🐱🐰🐦 etc.) | Órbita alrededor del botón |

```js
// ✅ CORRECTO — abrir chatbot:
await page.locator('.pgchat-trigger').click();

// ✅ Verificar que el tooltip aparece:
await expect(page.locator('.pgchat-tooltip')).toBeVisible();
```

---

## 2. BotChatOverlay — Ventana de Chat

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Ventana chat | `page.locator('.pgchat-window')` | 350×500px desktop, fullscreen ≤600px |
| Header | `page.locator('.pgchat-header')` | Background `#00A8E8` |
| Status "En línea" | Punto verde `#86EFAC` + texto "En línea" | En el header |
| Botón nueva conversación | `page.locator('.pgchat-new-chat-btn')` | Ícono `+`, gradiente |
| Botón historial | Ícono reloj en header | Abre panel de historial |
| Botón cerrar (X) | Ícono X en header | Cierra el chat |

### Área de mensajes

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Mensaje del bot | `page.locator('.pgchat-bubble-bot')` | Background `#e9ecef`, alineado izquierda |
| Mensaje del usuario | `page.locator('.pgchat-bubble-user')` | Background `#00A8E8`, alineado derecha, texto blanco |
| Animación "escribiendo" | `page.locator('.pgchat-typing-dots')` | 3 puntos animados |
| Mensaje de bienvenida | Texto: `¡Hola! Soy PetBot, el asistente inteligente de PetsGo 🐾` | Primer mensaje del bot |

```js
// ✅ Verificar mensaje de bienvenida:
await page.locator('.pgchat-trigger').click();
await expect(page.locator('.pgchat-bubble-bot').first()).toContainText('PetBot');
```

### Input y envío

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Input mensaje | `page.locator('.pgchat-input')` | border-radius 20px |
| Botón enviar | `page.locator('.pgchat-send-btn')` | 40×40px, circular |

```js
// ✅ Enviar un mensaje:
await page.locator('.pgchat-input').fill('¿Cuáles son los horarios de atención?');
await page.locator('.pgchat-send-btn').click();

// Esperar respuesta del bot
await expect(page.locator('.pgchat-typing-dots')).toBeVisible();
await expect(page.locator('.pgchat-bubble-bot').last()).not.toContainText('PetBot');

// ✅ Enviar con Enter:
await page.locator('.pgchat-input').fill('Hola');
await page.locator('.pgchat-input').press('Enter');
```

---

## 3. BotChatOverlay — Panel de Historial

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Panel historial | `page.locator('.pgchat-history-panel')` | Se abre al click en ícono reloj |
| Input búsqueda | `page.locator('.pgchat-history-panel input')` | Filtra conversaciones por título |
| Item conversación | `page.locator('.pgchat-history-item')` | Clickable, muestra título + fecha relativa |
| Botón eliminar | Ícono Trash dentro del item | Elimina la conversación |
| Botón nueva conv. | `page.locator('.pgchat-new-chat-btn')` | Texto: `+ Nueva Conversación` |
| Botón volver | Ícono ← en header del panel | Cierra panel, vuelve al chat |
| Estado vacío | `page.getByText('No tienes conversaciones guardadas')` | |

```js
// ✅ Abrir historial y buscar:
const historyBtn = page.locator('.pgchat-header button').nth(1); // ícono reloj
await historyBtn.click();
await expect(page.locator('.pgchat-history-panel')).toBeVisible();

// Buscar conversación:
await page.locator('.pgchat-history-panel input').fill('mascotas');

// Click en una conversación:
await page.locator('.pgchat-history-item').first().click();
```

---

## 4. BotChatOverlay — Persistencia

### Guest (sin sesión)

| Aspecto | Detalle |
|---------|---------|
| localStorage key | `petsgo_guest_chat` |
| TTL | 2 horas |
| Comportamiento | Conversación se guarda localmente; después de 2h se limpia |

### Usuario logueado

| Aspecto | Detalle |
|---------|---------|
| Storage | Base de datos (vía API) |
| Multi-conversación | Sí, ilimitadas |
| Al login | Se limpia `petsgo_guest_chat` de localStorage |

### Evento global

```js
// El chat se puede abrir programáticamente con:
window.dispatchEvent(new Event('petsgo:open_chat'));

// ✅ Test para abrir chat desde "Chatear Ahora" del homepage:
await page.evaluate(() => window.dispatchEvent(new Event('petsgo:open_chat')));
await expect(page.locator('.pgchat-window')).toBeVisible();
```

---

## 5. BotChatOverlay — Mobile (≤ 600px)

| Aspecto | Desktop (> 600px) | Mobile (≤ 600px) |
|---------|-------------------|-------------------|
| Tamaño ventana | 350×500px | 100vw × 100vh (fullscreen) |
| Posición | Fixed bottom-right | Fixed inset 0 |
| Bordes | border-radius | Sin border-radius |

```js
// ✅ Test mobile — verificar fullscreen:
test.use({ viewport: { width: 375, height: 812 } });
await page.locator('.pgchat-trigger').click();
const chatWindow = page.locator('.pgchat-window');
const box = await chatWindow.boundingBox();
expect(box.width).toBeCloseTo(375, -1);
```

---

## 6. SupportPage (`/soporte`)

> Requiere autenticación. Sin sesión redirige a `/login`.

### Vista: Lista de Tickets

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título página | `page.getByText('Soporte')` | |
| Botón crear ticket | `page.getByRole('button', { name: /Crear Solicitud|Nueva Solicitud/i })` | |
| Ticket row | `page.locator('[class*="sp-ticket-row"]')` o card-like div | |
| Badge ticket | Formato `T-001` | Número de ticket |
| Estado label | Texto del estado con color | |
| Badge urgencia | `⚡` si prioridad alta o urgente | |
| Estado vacío | `page.getByText('No tienes solicitudes de soporte')` | Con botón "Crear Solicitud" |

### Colores de estado del ticket

| Estado | Texto | Color fondo | Color texto |
|--------|-------|-------------|-------------|
| `abierto` | `Abierto` | `#EFF6FF` | `#3B82F6` |
| `en_proceso` | `En Proceso` | `#FFFBEB` | `#F59E0B` |
| `resuelto` | `Resuelto` | `#F0FDF4` | `#22C55E` |
| `cerrado` | `Cerrado` | `#F9FAFB` | `#6B7280` |

### Vista: Crear Nuevo Ticket

| Campo | Tipo | Selector | Notas |
|-------|------|----------|-------|
| Categoría | `select` | `page.locator('select')` | 7 opciones |
| Prioridad | 4 botones inline | `page.getByRole('button', { name: /Baja|Media|Alta|Urgente/i })` | Color coding |
| Asunto | `text` | `page.locator('input[type="text"]')` | |
| Descripción | `textarea` | `page.locator('textarea')` | rows=6 |
| Imagen | `file` | `page.locator('input[type="file"]')` | Opcional, max 5MB |
| Botón enviar | — | `page.getByRole('button', { name: /Enviar|Crear/i })` | |

**Categorías del select (7 opciones):**
`General`, `Productos`, `Pedidos`, `Pagos`, `Mi Cuenta`, `Entregas`, `Otro`

**Colores de prioridad (botones):**

| Prioridad | Color activo | Selector |
|-----------|-------------|----------|
| Baja | Verde | `page.getByRole('button', { name: 'Baja' })` |
| Media | Azul | `page.getByRole('button', { name: 'Media' })` |
| Alta | Naranja | `page.getByRole('button', { name: 'Alta' })` |
| Urgente | Rojo | `page.getByRole('button', { name: 'Urgente' })` |

```js
// ✅ Crear un ticket completo:
await page.getByRole('button', { name: /Crear Solicitud/i }).click();
await page.locator('select').selectOption('Pedidos');
await page.getByRole('button', { name: 'Alta' }).click();
await page.locator('input[type="text"]').fill('Problema con mi pedido');
await page.locator('textarea').fill('Mi pedido no ha llegado después de 3 días');
await page.getByRole('button', { name: /Enviar/i }).click();

// Verificar éxito:
await expect(page.getByText('¡Ticket Creado!')).toBeVisible();
// Auto-redirect después de 3 segundos a lista
```

### Vista: Detalle de Ticket

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Info del ticket | Card con datos del ticket | Número, estado, prioridad, fecha |
| Thread admin | Background `#F0F9FF` | Con ícono `🛡️ Soporte` |
| Thread usuario | Background `#F9FAFB` | Con ícono `👤 Tú` |
| Input respuesta | `page.locator('textarea')` | Para responder |
| Botón responder | `page.getByRole('button', { name: /Responder|Enviar/i })` | |
| Botón volver | `page.getByRole('button', { name: /Volver/i })` | Regresa a lista |

### Éxito al crear ticket

| Elemento | Selector verificado | Notas |
|----------|-------------------|-------|
| Título éxito | `page.getByText('¡Ticket Creado!')` | |
| Mensaje éxito | `page.getByText('¡Ticket creado exitosamente!')` | + mención de correo de confirmación |
| Auto-redirect | 3 segundos → lista de tickets | |

---

## 7. API Endpoints utilizados

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| POST | `/chatbot-send` | Opcional | Enviar mensaje al chatbot |
| GET | `/chatbot-conversations` | Sí | Lista conversaciones del usuario |
| GET | `/chatbot-conversations/{id}` | Sí | Detalle de conversación |
| DELETE | `/chatbot-conversations/{id}` | Sí | Eliminar conversación |
| POST | `/tickets` | Sí | Crear ticket de soporte |
| GET | `/tickets` | Sí | Lista tickets del usuario |
| GET | `/tickets/{id}` | Sí | Detalle del ticket |
| POST | `/tickets/{id}/reply` | Sí | Responder a ticket |

---

## 8. Mapeo Caso de Uso → Selectores clave

| ID | Caso | Selectores principales |
|----|------|----------------------|
| CH-001 | Abrir chatbot | `page.locator('.pgchat-trigger').click()` |
| CH-002 | Abrir desde homepage | `page.evaluate(() => window.dispatchEvent(new Event('petsgo:open_chat')))` |
| CH-003 | Cerrar chatbot | Botón X en header |
| CH-005 | Mensaje bienvenida | `.pgchat-bubble-bot` con texto "PetBot" |
| CH-020 | Enviar mensaje | `.pgchat-input` + `.pgchat-send-btn` |
| CH-021 | Enviar con Enter | `.pgchat-input` + `press('Enter')` |
| CH-022 | Mensaje vacío | Click send sin texto → no se envía |
| CH-024 | Indicador escribiendo | `.pgchat-typing-dots` |
| CH-040 | Persistencia guest | `localStorage.getItem('petsgo_guest_chat')` |
| CH-044 | Nueva conversación | `.pgchat-new-chat-btn` |
| CH-060 | Panel historial | `.pgchat-history-panel` |
| CH-063 | Búsqueda historial | `.pgchat-history-panel input` |
| CH-065 | Eliminar conversación | Ícono trash en `.pgchat-history-item` |
| CH-080 | Fullscreen mobile | Viewport ≤600px, window es 100vw×100vh |
| CH-120 | Acceder soporte | `/soporte` → lista de tickets |
| CH-121 | Crear ticket | Form: categoría + prioridad + asunto + descripción |
| CH-123 | Ver mis tickets | Lista con badge T-001, estado, prioridad |
| CH-124 | Responder ticket | Detalle → textarea + botón responder |
| CH-127 | Sin sesión redirige | `/soporte` → redirect a `/login` |

---

## 9. Discrepancias entre QA spec y código real

| ID | Spec dice | Código real | Impacto |
|----|-----------|-------------|---------|
| CH-004 | "Chat no bloquea la página" | El chat overlay NO bloquea scroll de la página de fondo | ✅ Correcto |
| CH-027 | "Quick replies / sugerencias" | No hay chips de preguntas rápidas en el código | **NO IMPLEMENTADO** |
| CH-041 | "Datos expiran a las 2 horas" | TTL de `petsgo_guest_chat` es 2 horas — correcto | ✅ Verificable |
| CH-043 | "Al login, historial guest se limpia" | Se limpia `petsgo_guest_chat` al detectar sesión | ✅ Correcto |
| CH-069 | "Máximo conversaciones / paginación" | No hay límite visible ni paginación en historial | **NO IMPLEMENTADO** |
| CH-107 | "Rate limiting chatbot" | Rate limiting en backend, no visible en frontend | Testear via API directa |
| CH-122 | "Adjuntar archivo a ticket" | SupportPage tiene input file max 5MB | ✅ Implementado (pero upload = SKIP en tests) |
| CH-125 | "Ticket cerrado no permite respuestas" | No verificado si el input se deshabilita | Verificar en runtime |
| CH-126 | "Reabrir ticket cerrado" | No hay botón "Reabrir" visible en el código | **NO IMPLEMENTADO** |
| CH-140-144 | "Rol Support / Agente" | SupportPage no tiene vista diferenciada para agentes | **NO IMPLEMENTADO** en frontend |

---

## 10. Tests que deben ser SKIP

| IDs | Razón |
|-----|-------|
| CH-027 | Quick replies no implementados |
| CH-041 | TTL de 2h — imposible verificar en test automatizado (hay que esperar 2h) |
| CH-069 | Límite de conversaciones no implementado |
| CH-082 | Teclado virtual iOS/Android — requiere device real |
| CH-107 | Rate limiting — solo verificable via API |
| CH-122 | Adjuntar archivo — requiere file picker (MANUAL) |
| CH-126 | Reabrir ticket — no implementado |
| CH-140 a CH-144 | Rol Support/Agente — no implementado en frontend |
