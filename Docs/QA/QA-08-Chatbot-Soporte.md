# QA-08 — Chatbot IA y Soporte
**Proyecto:** PetsGo  
**Módulo:** Chatbot con IA, Multi-Conversación, Tickets de Soporte  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Visitante, Cliente, Soporte, Admin

---

## 1. CHATBOT — APERTURA Y CIERRE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-001 | Abrir chatbot desde botón flotante | Cualquier página | 1. Click en botón flotante de chat (esquina inferior derecha) | Overlay del chatbot se abre con animación | Alta |
| CH-002 | Abrir chatbot desde "Chatear Ahora" homepage | Homepage | 1. Click en botón "Chatear Ahora" | Evento `petsgo:open_chat` disparado, chat se abre | Alta |
| CH-003 | Cerrar chatbot con botón X | Chat abierto | 1. Click en X del header del chat | Overlay se cierra | Alta |
| CH-004 | Chatbot no bloquea la página | Chat abierto | 1. Intentar hacer scroll y click en elementos detrás del chat | Overlay no bloquea la página (solo parte visible ocupa espacio) | Alta |
| CH-005 | Mensaje de bienvenida al abrir | Primera apertura | 1. Abrir chat por primera vez | Mensaje de bienvenida del bot visible: "Hola, soy PetBot..." | Alta |

---

## 2. CONVERSACIÓN BÁSICA CON EL BOT

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-020 | Enviar mensaje de texto | Chat abierto | 1. Escribir "¿Cuáles son los horarios de atención?" 2. Presionar Enter o click en enviar | Bot responde en ≤ 5 segundos | Alta |
| CH-021 | Enviar mensaje con tecla Enter | Chat abierto | 1. Escribir mensaje 2. Presionar Enter | Mensaje enviado | Alta |
| CH-022 | Mensaje vacío no se envía | Chat abierto | 1. Click en enviar sin texto | No se envía, campo de texto mantiene foco | Media |
| CH-023 | Respuesta de la IA es coherente | Bot funcional | 1. Preguntar sobre mascotas/productos | Respuesta contextualizada a PetsGo | Alta |
| CH-024 | Indicador de "escribiendo..." | Mientras bot procesa | 1. Enviar mensaje | Animación de puntos visible mientras el bot genera respuesta | Media |
| CH-025 | Historial de conversación visible | Múltiples mensajes | 1. Ver el chat | Mensajes propios (derecha) y del bot (izquierda) con timestamps | Alta |
| CH-026 | Scroll automático al mensaje nuevo | Chat con historial largo | 1. Enviar mensaje cuando hay scroll | Chat hace scroll automático al mensaje más reciente | Media |
| CH-027 | Preguntas frecuentes / quick replies | Si aplica | 1. Abrir chat 2. Ver sugerencias de preguntas rápidas | Chips clicables con preguntas comunes | Baja |

---

## 3. MEMORIA Y PERSISTENCIA DE CONVERSACIÓN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-040 | Invitado: conversación persiste en localStorage | Sin sesión | 1. Chatear 2. Cerrar chat 3. Reabrir | Historial previo restaurado | Alta |
| CH-041 | Invitado: datos expiran a las 2 horas | Sin sesión, TTL=2h | 1. Chatear 2. Esperar 2+ horas 3. Reabrir | Chat limpio, sin historial anterior | Alta |
| CH-042 | Cliente logueado: historial guardado en BD | Sesión activa | 1. Chatear 2. Cerrar sesión 3. Volver a login 4. Reabrir chat | Historial previo restaurado desde BD | Alta |
| CH-043 | Al login, historial de guest se limpia | Guest con historial + login | 1. Chatear como guest 2. Iniciar sesión | localStorage limpio, sin historial de guest | Media |
| CH-044 | Botón "Nueva conversación" crea sesión nueva | Cliente logueado | 1. Click en + (nueva conversación) | Conversación nueva sin historial del anterior | Alta |

---

## 4. MULTI-CONVERSACIÓN (USUARIOS LOGUEADOS)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-060 | Ver panel de historial de conversaciones | Cliente logueado | 1. Click en ícono reloj del header | Panel lateral de historial se abre | Alta |
| CH-061 | Lista de conversaciones previas | 2+ conversaciones guardadas | 1. Abrir panel historial | Conversaciones listadas con título y fecha relativa | Alta |
| CH-062 | Título de conversación auto-generado | Conversación guardada | 1. Ver título en lista | Primer mensaje del usuario truncado como título | Alta |
| CH-063 | Búsqueda en el historial | 3+ conversaciones | 1. Escribir en barra de búsqueda del historial | Conversaciones filtradas por texto del título | Alta |
| CH-064 | Abrir conversación anterior | Panel historial | 1. Click en conversación de la lista | Chat carga el historial de esa conversación | Alta |
| CH-065 | Eliminar conversación del historial | Conversación en lista | 1. Click en ícono de basura junto a conversación | Diálogo de confirmación → confirmar → conversación eliminada | Alta |
| CH-066 | Botón "Volver" desde historial al chat | Panel historial abierto | 1. Click en ← del header | Panel cierra, regresa a la vista de chat activo | Alta |
| CH-067 | Cliente sin conversaciones ve estado vacío | Historial vacío | 1. Abrir panel historial | Mensaje "No tienes conversaciones guardadas" + CTA | Media |
| CH-068 | Conversación nueva desde panel historial | Panel historial | 1. Click "+ Nueva conversación" | Chat nuevo sin mensajes, ID de conversación nuevo | Alta |
| CH-069 | Máximo de conversaciones guardadas | 50+ conversaciones | 1. Crear muchas conversaciones | Las más antiguas se archivan o se muestra paginación | Baja |

---

## 5. CHATBOT EN MOBILE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-080 | Chat fullscreen en mobile (≤ 600px) | Dispositivo mobile / viewport < 600px | 1. Abrir chat en mobile | Chat ocupa 100% de pantalla (ancho y alto) | Alta |
| CH-081 | Cerrar chat en mobile funciona | Chat fullscreen | 1. Click en X | Chat cierra, regresa a la página | Alta |
| CH-082 | Input de texto no se oculta con teclado virtual | Mobile iOS/Android | 1. Tap en input de texto | Input visible por encima del teclado virtual | Alta |
| CH-083 | Mensajes legibles en pantallas pequeñas | Mobile XS | 1. Revisar tipografía en mobile | Fuente legible, no overflow horizontal | Media |

---

## 6. API DEL CHATBOT

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-100 | `POST /petsgo/v1/chatbot` con mensaje | N/A | 1. POST con `{ "message": "Hola" }` | `{ "reply": "...", "conversation_id": N }` | Alta |
| CH-101 | `POST /petsgo/v1/chatbot` con conversation_id | Conversación existente | 1. POST con `conversation_id` válido | Respuesta en el contexto de esa conversación | Alta |
| CH-102 | `GET /petsgo/v1/chatbot-conversations` | Cliente autenticado | 1. GET con token | Array de conversaciones del usuario | Alta |
| CH-103 | `GET /petsgo/v1/chatbot-conversations/{id}` | Conversación del usuario | 1. GET con token + id | Conversación con todos los mensajes | Alta |
| CH-104 | `GET /petsgo/v1/chatbot-conversations/{id}` de otro usuario | N/A | 1. GET con id de conversación ajena | 403 Forbidden | Alta |
| CH-105 | `DELETE /petsgo/v1/chatbot-conversations/{id}` | Conversación del usuario | 1. DELETE | `{ "success": true }`, conversación eliminada de BD | Alta |
| CH-106 | Chatbot endpoint sin autenticación (guest) | Sin token | 1. POST al endpoint sin token | Respuesta generada, sin guardar historial en BD | Alta |
| CH-107 | Rate limiting del chatbot | N/A | 1. Enviar 20+ mensajes en menos de 1 minuto | Respuesta de rate limit (429) | Media |

---

## 7. SOPORTE — TICKETS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-120 | Acceder a `/soporte` | Sesión activa | 1. Navegar a `/soporte` | Página de soporte con formulario de ticket y lista de tickets | Alta |
| CH-121 | Crear ticket de soporte | Sesión activa | 1. Completar asunto + categoría + descripción 2. Enviar | Ticket creado con estado `open`, número de ticket generado | Alta |
| CH-122 | Crear ticket con archivo adjunto | N/A | 1. Adjuntar captura de pantalla al ticket | Archivo adjunto guardado, visible en la conversación | Media |
| CH-123 | Ver mis tickets | 1+ tickets creados | 1. Ver lista de tickets | Todos mis tickets con número, asunto, estado, fecha última respuesta | Alta |
| CH-124 | Responder a ticket en curso | Ticket `in_progress` | 1. Abrir ticket 2. Escribir mensaje 3. Enviar | Mensaje añadido, agente notificado | Alta |
| CH-125 | Ticket cerrado no permite más respuestas | Ticket `closed` | 1. Intentar responder ticket cerrado | Input deshabilitado, mensaje "Ticket cerrado" | Media |
| CH-126 | Reabrir ticket cerrado | Ticket `closed` | 1. Click "Reabrir ticket" | Estado vuelve a `open` | Media |
| CH-127 | Sin sesión redirige al login | Sin sesión | 1. Ir a `/soporte` | Redirige a `/login` | Alta |
| CH-128 | F5 en `/soporte` no expulsa | Sesión activa | 1. Refrescar página | SoportePage mantiene sesión (fix authLoading) | Alta |

---

## 8. SOPORTE — AGENTE (ROL SUPPORT)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-140 | Ver tickets asignados | Agente con tickets asignados | 1. Login como soporte 2. Ver `/soporte` | Tickets asignados a este agente visibles | Alta |
| CH-141 | Responder ticket de cliente | Ticket asignado | 1. Abrir ticket 2. Escribir respuesta 3. Enviar | Respuesta enviada, cliente notificado | Alta |
| CH-142 | Cambiar estado de ticket | Ticket abierto | 1. Cambiar a `in_progress` | Estado actualizado | Alta |
| CH-143 | Cerrar ticket como agente | Ticket resuelto | 1. Click "Cerrar" | Ticket `closed`, cliente notificado | Alta |
| CH-144 | F5 en `/soporte` con rol support | Agente logueado | 1. Refrescar | Dashboard carga sin redirigir (fix authLoading) | Alta |
