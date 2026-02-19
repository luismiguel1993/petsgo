# 🤖 Contexto Completo — Chatbot PetsGo + Proxy AutomatizaTech

**Proyecto:** PetsGo — Marketplace de Mascotas  
**Cliente:** Alexiandra Andrade  
**Proveedor Proxy IA:** AutomatizaTech  
**Fecha:** Febrero 2026  
**Estado actual:** ✅ Chatbot operativo — pendiente: tabla `ai_usage_log` falta en servidor AutomatizaTech

---

## 📋 Resumen Ejecutivo

PetsGo tiene un chatbot web integrado en su plataforma (React + WordPress headless). El chatbot ya está completamente implementado y envía las peticiones correctamente al proxy de AutomatizaTech (`api-chat-proxy.php`), pero **el proxy responde con un error indicando que la API Key de OpenAI no está configurada** para el `client_identifier` `cliente_petsgo`.

### Error actual (respuesta del proxy)

```json
{
  "reply": "Error: API Key no configurada en el servidor (api-chat-proxy.php). Por favor configura OPENAI_API_KEY en wp-config.php"
}
```

**Acción requerida:** Configurar la API Key de OpenAI en el servidor de AutomatizaTech para que el `client_identifier` `cliente_petsgo` funcione correctamente.

---

## 🏗️ Arquitectura del Chatbot Web

```
[Usuario en Web PetsGo]
        │
        ▼
[React Frontend - BotChatOverlay.jsx]
   │  (POST /wp-json/petsgo/v1/chatbot-send)
   ▼
[WordPress REST API - petsgo-core.php]
   │  (wp_remote_post con JSON al proxy)
   ▼
[AutomatizaTech Proxy]
   URL: https://automatizatech.cl/api-chat-proxy.php
   │
   ├──► [OpenAI API]  ← ⚠️ REQUIERE API KEY CONFIGURADA
   │
   └──► [ai_usage_log en MySQL] (tracking de consumo)
   │
   ▼
[Respuesta JSON al WordPress]
   │
   ▼
[WordPress normaliza y reenvía al Frontend]
   │
   ▼
[Usuario ve la respuesta en el chat]
```

### ¿Por qué hay un proxy WordPress intermedio?

El frontend (React en navegador) no puede llamar directamente a `automatizatech.cl` por restricciones CORS del browser. Por eso WordPress actúa como relay server-side:

1. Frontend → WordPress (`/chatbot-send`) — mismo dominio, sin CORS
2. WordPress → AutomatizaTech (`api-chat-proxy.php`) — server-side, sin restricción CORS
3. WordPress normaliza la respuesta y la devuelve al frontend

---

## 🔧 Configuración del Llamado al Proxy

### Datos que envía WordPress al proxy

**URL:** `POST https://automatizatech.cl/api-chat-proxy.php`  
**Content-Type:** `application/json`  
**Timeout:** 60 segundos

### Body (JSON)

```json
{
  "model": "gpt-4o-mini",
  "user_id": 1,
  "client_identifier": "cliente_petsgo",
  "messages": [
    {
      "role": "system",
      "content": "Eres PetBot, el asistente virtual de PetsGo..."
    },
    {
      "role": "user",
      "content": "Hola, ¿qué productos tienen para gatos?"
    }
  ]
}
```

### Campos fijos

| Campo | Valor | Descripción |
|-------|-------|-------------|
| `model` | `gpt-4o-mini` (configurable desde admin WP) | Modelo OpenAI a usar |
| `user_id` | `1` | ID del admin de AutomatizaTech |
| `client_identifier` | `"cliente_petsgo"` | **Obligatorio** — Identifica al cliente para tracking/facturación |
| `messages` | Array estándar OpenAI | System prompt + historial de conversación (máx 20 mensajes) |

---

## 📤 Formato de Respuesta Esperado

### Opción A: Formato OpenAI estándar (preferido)

```json
{
  "id": "chatcmpl-...",
  "model": "gpt-4o-mini",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "¡Hola! Soy PetBot..."
      },
      "finish_reason": "stop"
    }
  ],
  "usage": {
    "prompt_tokens": 150,
    "completion_tokens": 80,
    "total_tokens": 230
  }
}
```

### Opción B: Formato simplificado de AutomatizaTech

```json
{
  "reply": "¡Hola! Soy PetBot..."
}
```

**Nota:** El backend de PetsGo ya maneja ambos formatos — normaliza `{"reply": "..."}` a formato OpenAI `{choices: [...]}` automáticamente.

### ⚠️ IMPORTANTE: BOM en la respuesta

El proxy actualmente antepone un **BOM (Byte Order Mark: `EF BB BF`)** al inicio del JSON de respuesta. Esto rompe `json_decode()` en PHP. PetsGo ya tiene un fix para esto (`ltrim` del BOM), pero sería ideal que el proxy NO envíe el BOM.

**Solución en el proxy:** Asegurarse de que `api-chat-proxy.php` esté guardado sin BOM (UTF-8 sin BOM) y que no haya output previo a `echo json_encode(...)`.

---

## 🧠 System Prompt del Bot

El system prompt se configura desde el panel admin de WordPress (`/wp-admin/ → 🤖 Chatbot IA`) y se envía como primer mensaje del array `messages` con `role: "system"`.

### Variables dinámicas

Antes de enviarse, el frontend reemplaza estas variables con datos reales de la BD:

| Variable | Se reemplaza por | Ejemplo |
|----------|-----------------|---------|
| `{BOT_NAME}` | Nombre del bot configurado | `PetBot` |
| `{CATEGORIAS}` | Lista de categorías activas de la BD | `🐕 Perros \| 🐈 Gatos \| 🐦 Aves` |
| `{PLANES}` | Planes de suscripción para vendedores | `• Plan Básico: $9.990/mes...` |
| `{TELEFONO}` | Teléfono de la empresa | `+56 9 1234 5678` |
| `{EMAIL}` | Email de contacto | `contacto@petsgo.cl` |
| `{WEBSITE}` | URL del sitio | `https://petsgo.cl` |
| `{ENVIO_GRATIS}` | Monto mínimo para envío gratis | `$39.990` |
| `{WHATSAPP}` | Número WhatsApp | `+56 9 1234 5678` |

### Prompt por defecto (completo)

```
Eres **{BOT_NAME}**, el asistente virtual de PetsGo, un marketplace digital de productos y servicios para mascotas en Chile.

━━━ IDENTIDAD ━━━
• Nombre: {BOT_NAME} 🐾
• Empresa: PetsGo ({WEBSITE})
• Contacto: {EMAIL} | WhatsApp {TELEFONO}
• País: Chile — moneda CLP (pesos chilenos), formato $XX.XXX

━━━ ¿QUÉ ES PETSGO? ━━━
PetsGo es un marketplace multi-vendor que conecta a dueños de mascotas con tiendas especializadas (petshops) y riders de delivery. Las tiendas publican sus productos, los clientes compran desde la web, y los riders realizan la entrega a domicilio.

━━━ CATEGORÍAS DE PRODUCTOS ━━━
{CATEGORIAS}

━━━ ENVÍO Y DESPACHO ━━━
• Envío GRATIS en pedidos desde {ENVIO_GRATIS}
• Primer pedido siempre con envío GRATIS 🎉
• Costo de envío estándar: $2.990 (si el pedido es menor al monto de envío gratis)
• Despacho Express: mismo día para pedidos antes de las 14:00 (sujeto a disponibilidad)
• Despacho Estándar: 1-3 días hábiles
• Retiro en tienda: gratis, recoges en la tienda del vendedor
• Seguimiento: en tiempo real desde "Mis Pedidos" en la web

━━━ ESTADOS DE PEDIDO ━━━
1. Pago Pendiente → 2. Preparando → 3. Listo para enviar → 4. En camino → 5. Entregado
(También puede pasar a: Cancelado)

━━━ MÉTODOS DE PAGO ━━━
💳 Tarjetas de débito y crédito
🏦 Transferencia bancaria
💵 Pago contra entrega
Todas las transacciones son 100% seguras 🔒

━━━ PLANES PARA VENDEDORES ━━━
{PLANES}
• Plan anual: 2 meses gratis (pagas 10 de 12)
• Sin costo de inscripción
• Activación en menos de 48 horas
• Panel de gestión incluido
• Si alguien quiere ser vendedor, invítale a visitar la sección "Planes" en la web o enviar un mensaje por WhatsApp

━━━ DEVOLUCIONES Y GARANTÍA ━━━
• Ley del Consumidor chilena aplica a todas las compras
• Reportar problemas (producto dañado, incompleto o incorrecto) dentro de 24 horas mediante un ticket de soporte
• Productos deben devolverse en condición original con empaque
• PetsGo actúa como intermediario entre cliente y tienda para resolver

━━━ SOPORTE ━━━
• Tickets de soporte: desde la sección "Soporte" en la web (usuarios registrados)
• Categorías: General, Productos, Pedidos, Pagos, Mi Cuenta, Entregas
• Prioridades: Baja, Media, Alta, Urgente
• Tiempo de respuesta: dentro de 24 horas hábiles
• WhatsApp: para consultas rápidas al {TELEFONO}

━━━ FUNCIONALIDADES PARA CLIENTES ━━━
• Explorar tiendas y productos por categoría
• Agregar productos al carrito (puedes mezclar tiendas — se generan órdenes separadas por tienda)
• Registrar mascotas en tu perfil (perro, gato, ave, conejo, hámster, pez, reptil)
• Seguir pedidos en tiempo real
• Crear tickets de soporte con imágenes adjuntas
• Valorar riders después de cada entrega

━━━ RIDERS / DELIVERY ━━━
• Los riders son repartidores independientes que entregan los pedidos
• Vehículos: bicicleta, scooter, moto, auto o a pie
• Horario de despacho: lunes a sábado 9:00-20:00
• Los pedidos online se procesan 24/7

━━━ REGLAS ESTRICTAS (NO HACER) ━━━
❌ NO inventes precios, disponibilidad ni stock — di que el usuario consulte directamente en la web
❌ NO des consejos veterinarios ni médicos — recomienda visitar un veterinario
❌ NO compartas datos personales de otros usuarios
❌ NO proceses pagos ni tomes datos de tarjeta directamente
❌ NO hagas promesas de tiempo de entrega garantizado — usa siempre "estimado" o "sujeto a disponibilidad"
❌ NO inventes nombres de tiendas, productos o promociones que no existan
❌ NO respondas en otro idioma que no sea español

━━━ TONO Y ESTILO ━━━
• Amable, profesional, cercano y conciso
• Usa emojis con moderación para dar calidez (🐾 💛 🐕 📦)
• Responde SIEMPRE en español (Chile)
• Si no sabes algo o no puedes ayudar, indica amablemente que derivarás la consulta a un agente humano vía WhatsApp ({TELEFONO}) o ticket de soporte
• Mantén las respuestas breves (3-5 oraciones máximo) a menos que el usuario pida más detalle
• Cuando sugieras productos, menciona la categoría y que puede explorarlos en la web

━━━ HORARIO ━━━
• Tiendas despachan: lunes a sábado de 9:00 a 20:00
• Plataforma online: 24/7
• {BOT_NAME} (tú): siempre disponible 🤖
```

---

## 🖥️ Código Relevante del Backend (WordPress)

### Endpoint REST: `POST /wp-json/petsgo/v1/chatbot-send`

Ubicación: `wp-content/mu-plugins/petsgo-core.php` — función `api_chatbot_send()`

```php
public function api_chatbot_send($request) {
    $enabled = $this->pg_setting('chatbot_enabled', '1');
    if ($enabled !== '1') {
        return new WP_Error('chatbot_disabled', 'El chatbot está desactivado.', ['status' => 403]);
    }

    $body = $request->get_json_params();
    $messages = $body['messages'] ?? [];

    if (empty($messages)) {
        return new WP_Error('no_messages', 'No se enviaron mensajes.', ['status' => 400]);
    }

    $model = $this->pg_setting('chatbot_model', 'gpt-4o-mini');
    $proxy_url = 'https://automatizatech.cl/api-chat-proxy.php';

    $payload = json_encode([
        'model'             => $model,
        'user_id'           => 1,
        'client_identifier' => 'cliente_petsgo',
        'messages'          => $messages,
    ], JSON_UNESCAPED_UNICODE);

    $response = wp_remote_post($proxy_url, [
        'timeout'   => 60,
        'sslverify' => false,
        'headers'   => ['Content-Type' => 'application/json'],
        'body'      => $payload,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('proxy_error', $response->get_error_message(), ['status' => 502]);
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_body = ltrim($response_body, "\xEF\xBB\xBF"); // Strip BOM
    $data = json_decode($response_body, true);

    // Normalizar: {"reply":"..."} → {"choices":[{"message":{"content":"..."}}]}
    if (isset($data['reply'])) {
        return rest_ensure_response([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $data['reply']],
            ]],
        ]);
    }

    return rest_ensure_response($data);
}
```

### Endpoint REST: `GET /wp-json/petsgo/v1/chatbot-config`

Devuelve la configuración del chatbot (categorías, planes, settings, prompt). Usado por el frontend al montar el componente para construir el system prompt dinámicamente.

---

## 🎨 Frontend (React)

### Componente: `BotChatOverlay.jsx`

- Widget flotante tipo burbuja de chat
- Al montarse, llama `GET /chatbot-config` para obtener config + prompt
- Reemplaza variables dinámicas (`{CATEGORIAS}`, `{PLANES}`, etc.) con datos reales
- Envía mensajes a `POST /chatbot-send` con system prompt + historial (máx 20 mensajes)
- Lee respuesta como `response.data.choices[0].message.content`

### Servicio API: `api.js`

```javascript
export const getChatbotConfig = () =>
  api.get('/chatbot-config');

export const sendChatbotMessage = (messages) =>
  api.post('/chatbot-send', { messages }, { timeout: 60000 });
```

---

## ✅ Lo que ya funciona

| Componente | Estado |
|-----------|--------|
| Widget de chat en la web (UI) | ✅ Funcionando |
| Configuración admin en WordPress | ✅ Panel "🤖 Chatbot IA" operativo |
| System prompt dinámico con variables de BD | ✅ Categorías y planes se inyectan automáticamente |
| Endpoint `/chatbot-send` (proxy WP → AutomatizaTech) | ✅ Llamada llega correctamente al proxy |
| Manejo de BOM en respuesta | ✅ Strip automático |
| Normalización `{"reply":"..."}` → formato OpenAI | ✅ Implementado |
| CORS (browser → WP → proxy) | ✅ Resuelto con proxy server-side |

---

## ✅ Problemas Resueltos

| # | Problema | Estado |
|---|----------|--------|
| 1 | API Key no existía en wp-config.php | ✅ Configurada |
| 2 | API Key hardcodeada en controller (seguridad) | ✅ Removida, lee de wp-config |
| 3 | BOM en contact-form.php | ✅ BOM removido + ob_clean() |
| 4 | Doble require_once('wp-load.php') | ✅ Check condicional |
| 5 | HTML de errores WP antes del JSON | ✅ PetsGo extrae JSON resiliente |

## ⚠️ Pendiente (acción de AutomatizaTech)

### 1. Crear tabla `ai_usage_log` en la base de datos

El proxy emite errores HTML de WordPress antes del JSON porque la tabla `ai_usage_log` **no existe** en la BD `u402745362_automatizatech`:

```
Table 'u402745362_automatizatech.ai_usage_log' doesn't exist
```

Esto causa que el proxy emita `<div class="wpdberror">...</div>` **antes** del JSON de respuesta, rompiendo el parseo. PetsGo ahora tiene un fix resiliente que extrae el JSON del body sucio, pero es una solución temporal.

**Acción requerida:** Crear la tabla `ai_usage_log` en el servidor:

```sql
CREATE TABLE IF NOT EXISTS ai_usage_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    client_identifier VARCHAR(100) NOT NULL DEFAULT '',
    model VARCHAR(50) NOT NULL DEFAULT '',
    prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cost_estimated DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_identifier),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. Suprimir errores WP en modo producción

Agregar `@ini_set('display_errors', 0)` o `define('WP_DEBUG_DISPLAY', false)` en el wp-config.php del servidor de AutomatizaTech para que los errores de BD no se emitan como HTML en la respuesta.

### 3. Verificar tracking de consumo

Una vez creada la tabla, verificar:

```sql
SELECT * FROM ai_usage_log 
WHERE client_identifier LIKE 'cliente_petsgo%' 
ORDER BY created_at DESC 
LIMIT 5;
```

Dashboard: `https://automatizatech.cl/admin-ai-dashboard.php`

---

## 🧪 Cómo Probar

### Test directo al proxy (desde cualquier terminal con curl/PHP)

```bash
curl -X POST https://automatizatech.cl/api-chat-proxy.php \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4o-mini",
    "user_id": 1,
    "client_identifier": "cliente_petsgo",
    "messages": [
      {"role": "system", "content": "Eres PetBot, asistente de PetsGo."},
      {"role": "user", "content": "Hola, ¿qué es PetsGo?"}
    ]
  }'
```

**Respuesta esperada (cuando funcione):**

```json
{
  "choices": [{
    "message": {
      "role": "assistant",
      "content": "¡Hola! PetsGo es un marketplace de productos y servicios para mascotas en Chile..."
    }
  }],
  "usage": { "prompt_tokens": 150, "completion_tokens": 80, "total_tokens": 230 }
}
```

### Test desde WordPress (PowerShell)

```powershell
Invoke-RestMethod -Method POST `
  -Uri "http://localhost/PetsGoDev/wp-json/petsgo/v1/chatbot-send" `
  -ContentType "application/json" `
  -Body '{"messages":[{"role":"user","content":"hola"}]}' | ConvertTo-Json -Depth 10
```

---

## 📊 Modelos Disponibles

El modelo se configura desde el admin de WordPress. Por defecto usa `gpt-4o-mini`.

| Modelo | Input (1M tokens) | Output (1M tokens) | Uso recomendado |
|--------|-------------------|---------------------|-----------------|
| `gpt-4o-mini` | $0.15 | $0.60 | **Bot conversacional** (recomendado) |
| `gpt-4o` | $2.50 | $10.00 | Tareas complejas |

---

## 🔑 Datos de Configuración Fijos

| Parámetro | Valor |
|-----------|-------|
| **URL proxy** | `https://automatizatech.cl/api-chat-proxy.php` |
| **client_identifier** | `cliente_petsgo` |
| **user_id** | `1` |
| **Modelo por defecto** | `gpt-4o-mini` |
| **Timeout** | 60 segundos |
| **Autenticación** | Ninguna (el proxy no requiere auth) |

---

## 📁 Archivos Relevantes en el Proyecto PetsGo

| Archivo | Descripción |
|---------|-------------|
| `wp-content/mu-plugins/petsgo-core.php` | Backend principal — contiene endpoints `/chatbot-config`, `/chatbot-send`, admin page, prompt por defecto |
| `frontend/src/components/BotChatOverlay.jsx` | Widget de chat React — UI, manejo de mensajes, llamadas API |
| `frontend/src/services/api.js` | Capa de servicios HTTP — funciones `getChatbotConfig()` y `sendChatbotMessage()` |
| `frontend/vite.config.js` | Proxy de desarrollo: `/wp-json` → `http://localhost/PetsGoDev` |

---

## 🔀 Si hay Múltiples Bots (WhatsApp, Web, etc.)

Para distinguir canales en el tracking:

| Bot/Canal | `client_identifier` |
|-----------|---------------------|
| Bot web (este) | `cliente_petsgo` |
| Bot WhatsApp | `cliente_petsgo_whatsapp` |
| Bot ventas | `cliente_petsgo_ventas` |
| Bot soporte | `cliente_petsgo_soporte` |

Todos deben empezar con `cliente_petsgo` para agruparse en reportes de facturación.

---

## ✅ Checklist para AutomatizaTech

- [x] Configurar `OPENAI_API_KEY` en el servidor para `cliente_petsgo`
- [x] Verificar que el proxy acepta el body JSON con `model`, `user_id`, `client_identifier`, `messages`
- [x] Confirmar formato de respuesta: `{"choices":[...]}` (formato OpenAI estándar)
- [x] Eliminar BOM del archivo `api-chat-proxy.php`
- [x] Remover API Key hardcodeada del controller
- [x] Probar con el curl de ejemplo y confirmar respuesta exitosa de OpenAI
- [ ] **PENDIENTE:** Crear tabla `ai_usage_log` en la BD (ver SQL arriba)
- [ ] **PENDIENTE:** Suprimir `display_errors` en producción para evitar HTML antes del JSON
- [ ] Verificar que el consumo aparece en `ai_usage_log` con `client_identifier = 'cliente_petsgo'`

---

*Documento generado para coordinación entre PetsGo y AutomatizaTech*  
*Proyecto: PetsGo — Chatbot Web con IA*  
*Febrero 2026*
