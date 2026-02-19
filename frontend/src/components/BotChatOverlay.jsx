import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Send, X, Loader2, Clock, Plus, Search, Trash2, ArrowLeft } from 'lucide-react';
import { getChatbotConfig, getChatbotUserContext, getChatHistory, saveChatHistory, sendChatbotMessage, listConversations, getConversation, deleteConversation } from '../services/api';
import { useAuth } from '../context/AuthContext';
import huellaSvg from '../assets/Huella-1.svg';

/**
 * Reemplaza las variables dinámicas en el system prompt
 * con datos reales de la BD (categorías, planes, settings, etc.)
 */

/**
 * Mini-parser Markdown → HTML para mensajes del bot.
 * Soporta: **bold**, *italic*, listas (• - *), saltos de línea.
 */
function formatBotMessage(text) {
  if (!text) return '';
  let html = text
    // Escapar HTML
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    // Bold **text** 
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    // Italic *text* (sin confundir con listas)
    .replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>')
    // Líneas horizontales
    .replace(/^━+$/gm, '<hr style="border:none;border-top:1px solid #e0e0e0;margin:6px 0!important">')
    // Listas: líneas que empiezan con •, -, o numeradas (1.)
    .replace(/^[•\-]\s+(.+)$/gm, '<li style="margin:2px 0!important;padding:0!important;list-style:none!important">$1</li>')
    .replace(/^(\d+)\.\s+(.+)$/gm, '<li style="margin:2px 0!important;padding:0!important;list-style:none!important"><strong>$1.</strong> $2</li>')
    // Agrupar <li> consecutivos en <ul>
    .replace(/((?:<li[^>]*>.*<\/li>\n?)+)/g, '<ul style="margin:4px 0 4px 8px!important;padding:0!important">$1</ul>')
    // Doble salto → párrafo
    .replace(/\n\n/g, '</p><p style="margin:6px 0!important">')
    // Salto simple → <br>
    .replace(/\n/g, '<br>');
  return '<p style="margin:0!important">' + html + '</p>';
}

function buildSystemPrompt(config) {
  let prompt = config.system_prompt || '';
  if (!prompt) return '';

  const s = config.settings || {};
  const freeShipping = s.free_shipping_min
    ? '$' + parseInt(s.free_shipping_min).toLocaleString('es-CL')
    : '$39.990';

  // Categorías: lista formateada
  const cats = (config.categories || []).join(' | ') || 'Sin categorías configuradas';

  // Planes: información detallada y organizada
  let planesText = '';
  if (config.plans?.length) {
    planesText = 'PetsGo ofrece ' + config.plans.length + ' planes para tiendas:\n\n';
    config.plans.forEach(p => {
      const featured = p.is_featured ? ' ⭐ RECOMENDADO' : '';
      planesText += `📦 **${p.name}**${featured}\n`;
      planesText += `   Precio: ${p.price}\n`;
      planesText += `   Productos: ${p.products === 'Ilimitados' ? 'Ilimitados' : 'Hasta ' + p.products}\n`;
      planesText += `   Comisión: ${p.commission}\n`;
      if (p.features?.length) {
        planesText += `   Incluye: ${p.features.filter(f => !/producto|comisi/i.test(f)).join(', ')}\n`;
      }
      planesText += '\n';
    });
  } else {
    planesText = 'Planes disponibles para vendedores (consultar en la web).';
  }

  // Reemplazar variables
  prompt = prompt
    .replace(/\{BOT_NAME\}/g, config.bot_name || 'PetBot')
    .replace(/\{CATEGORIAS\}/g, cats)
    .replace(/\{PLANES\}/g, planesText)
    .replace(/\{TELEFONO\}/g, s.phone || '+56 9 1234 5678')
    .replace(/\{EMAIL\}/g, s.email || 'contacto@petsgo.cl')
    .replace(/\{WEBSITE\}/g, s.website || 'https://petsgo.cl')
    .replace(/\{ENVIO_GRATIS\}/g, freeShipping)
    .replace(/\{WHATSAPP\}/g, s.whatsapp || s.phone || '+56 9 1234 5678');

  // Datos maestros dinámicos
  const m = config.master || {};
  prompt = prompt
    .replace(/\{ESTADOS_PEDIDO\}/g, m.order_statuses || '')
    .replace(/\{METODOS_PAGO\}/g, m.payment_methods || '')
    .replace(/\{VEHICULOS\}/g, m.vehicles || '')
    .replace(/\{HORARIO_DESPACHO\}/g, m.delivery_schedule || '')
    .replace(/\{POLITICA_DEVOLUCIONES\}/g, m.returns_policy || '');

  return prompt;
}

/**
 * Construye un bloque de contexto del usuario logueado para inyectar al system prompt.
 * Solo se genera para clientes y riders.
 */
function buildUserContextBlock(userCtx) {
  if (!userCtx) return '';
  const lines = ['\n\n━━━ CONTEXTO DEL USUARIO ACTUAL ━━━'];
  lines.push(`Nombre: ${userCtx.name}`);
  lines.push(`Email: ${userCtx.email}`);
  if (userCtx.phone) lines.push(`Teléfono: ${userCtx.phone}`);
  lines.push(`Rol: ${userCtx.role === 'rider' ? 'Rider (repartidor)' : 'Cliente'}`);
  if (userCtx.region) lines.push(`Ubicación: ${userCtx.comuna || ''}, ${userCtx.region}`);

  if (userCtx.role === 'customer') {
    if (userCtx.pets?.length) {
      lines.push(`Mascotas: ${userCtx.pets.join(', ')}`);
    }
    lines.push(`Total pedidos realizados: ${userCtx.total_orders || 0}`);
    if (userCtx.orders?.length) {
      lines.push('Últimos pedidos:');
      userCtx.orders.forEach(o => {
        const items = o.items?.join(', ') || 'Sin detalle';
        lines.push(`  • Pedido #${o.id} — ${o.status} — ${o.total} — ${o.store} — ${o.date} — Items: ${items}`);
      });
    }
  } else if (userCtx.role === 'rider') {
    lines.push(`Estado rider: ${userCtx.rider_status || 'pendiente'}`);
    if (userCtx.vehicle) lines.push(`Vehículo: ${userCtx.vehicle}`);
    lines.push(`Total entregas: ${userCtx.total_deliveries || 0}`);
    if (userCtx.deliveries?.length) {
      lines.push('Últimas entregas:');
      userCtx.deliveries.forEach(d => {
        lines.push(`  • Entrega #${d.id} — ${d.status} — ${d.fee} — ${d.store} — ${d.date}`);
      });
    }
  }

  lines.push('IMPORTANTE: Usa esta información para personalizar tus respuestas. Llama al usuario por su nombre. Si pregunta por sus pedidos, muéstrale la info que tienes.');
  return lines.join('\n');
}

/**
 * BotChatOverlay - Chat Widget PetsGo
 * Estilos con CSS-in-JS inyectado para máxima prioridad sobre Tailwind
 */

// CSS inyectado directamente en el DOM para evitar conflictos con Tailwind Preflight
const chatCSS = `
  .pgchat-widget * {
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 0 !important;
    text-align: left !important;
  }

  .pgchat-trigger {
    position: fixed !important;
    bottom: 30px !important;
    right: 30px !important;
    width: 60px !important;
    height: 60px !important;
    border-radius: 50% !important;
    background-color: #FFC400 !important;
    border: none !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
    z-index: 9990 !important;
    transition: transform 0.2s, right 0.4s ease, left 0.4s ease !important;
  }
  .pgchat-trigger.pgchat-left {
    right: auto !important;
    left: 30px !important;
  }
  .pgchat-trigger:hover {
    transform: scale(1.08) !important;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25) !important;
  }

  /* Anillo elíptico tipo Saturno — animal siempre derecho */
  .pgchat-orbit {
    position: fixed !important;
    bottom: 0px !important;
    right: 0px !important;
    width: 120px !important;
    height: 120px !important;
    pointer-events: none !important;
    z-index: 9991 !important;
    transition: right 0.4s ease, left 0.4s ease !important;
  }
  .pgchat-orbit.pgchat-left {
    right: auto !important;
    left: 0px !important;
  }
  .pgchat-orbiter {
    position: absolute !important;
    font-size: 22px !important;
    animation: pgchat-ellipse 4s ease-in-out infinite !important;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15)) !important;
    transition: opacity 0.3s !important;
  }
  /* Trayectoria elíptica: óvalo horizontal aplanado como anillo de Saturno */
  @keyframes pgchat-ellipse {
    0%   { top: 50%; left: -8px;  transform: translate(-50%, -50%) scale(0.85); z-index: -1; opacity: 0.5; }
    25%  { top: 18%;  left: 50%;  transform: translate(-50%, -50%) scale(1);    z-index: 1;  opacity: 1; }
    50%  { top: 50%; left: 128px; transform: translate(-50%, -50%) scale(0.85); z-index: -1; opacity: 0.5; }
    75%  { top: 82%;  left: 50%;  transform: translate(-50%, -50%) scale(1.1);  z-index: 1;  opacity: 1; }
    100% { top: 50%; left: -8px;  transform: translate(-50%, -50%) scale(0.85); z-index: -1; opacity: 0.5; }
  }
  /* Anillo visual: óvalo punteado dorado */
  .pgchat-ring-visual {
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    width: 140px !important;
    height: 50px !important;
    margin-top: -25px !important;
    margin-left: -70px !important;
    border: 2px dashed rgba(255,196,0,0.35) !important;
    border-radius: 50% !important;
    animation: pgchat-ring-glow 3s ease-in-out infinite !important;
  }
  @keyframes pgchat-ring-glow {
    0%, 100% { border-color: rgba(255,196,0,0.2); box-shadow: 0 0 0 transparent; }
    50% { border-color: rgba(255,196,0,0.5); box-shadow: 0 0 12px rgba(255,196,0,0.1); }
  }

  /* Tooltip invitación */
  .pgchat-tooltip {
    position: fixed !important;
    bottom: 98px !important;
    right: 20px !important;
    background: #fff !important;
    color: #2F3A40 !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    padding: 8px 14px !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
    z-index: 9991 !important;
    white-space: nowrap !important;
    animation: pgchat-bounce 2s ease-in-out infinite !important;
    font-family: 'Poppins', sans-serif !important;
    transition: right 0.4s ease, left 0.4s ease !important;
  }
  .pgchat-tooltip.pgchat-left {
    right: auto !important;
    left: 20px !important;
  }
  .pgchat-tooltip.pgchat-left::after {
    right: auto !important;
    left: 26px !important;
  }
  .pgchat-tooltip::after {
    content: '' !important;
    position: absolute !important;
    bottom: -6px !important;
    right: 26px !important;
    width: 12px !important;
    height: 12px !important;
    background: #fff !important;
    transform: rotate(45deg) !important;
    box-shadow: 2px 2px 4px rgba(0,0,0,0.05) !important;
  }
  @keyframes pgchat-bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
  }

  .pgchat-window {
    position: fixed !important;
    bottom: 100px !important;
    right: 30px !important;
    width: 350px !important;
    height: 500px !important;
    max-height: 80vh !important;
    background-color: #ffffff !important;
    border-radius: 12px !important;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15) !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    z-index: 9999 !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    transition: right 0.4s ease, left 0.4s ease !important;
  }
  .pgchat-window.pgchat-left {
    right: auto !important;
    left: 30px !important;
  }
  /* Mobile: chat cubre toda la pantalla */
  @media (max-width: 600px) {
    .pgchat-window {
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100% !important;
      height: 100% !important;
      max-height: 100% !important;
      border-radius: 0 !important;
    }
    .pgchat-window.pgchat-left {
      left: 0 !important;
      right: 0 !important;
    }
  }

  .pgchat-header {
    background-color: #00A8E8 !important;
    color: #ffffff !important;
    padding: 15px !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-shrink: 0 !important;
  }
  .pgchat-header-left {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
  }
  .pgchat-header-avatar {
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background-color: #fff !important;
    padding: 4px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
  }
  .pgchat-header-avatar img {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
  }
  .pgchat-header-info {
    display: flex !important;
    flex-direction: column !important;
  }
  .pgchat-header-title {
    font-weight: 600 !important;
    font-size: 15px !important;
    line-height: 1.2 !important;
    color: #fff !important;
  }
  .pgchat-header-status {
    font-size: 11px !important;
    opacity: 0.9 !important;
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
    color: #fff !important;
  }
  .pgchat-status-dot {
    width: 6px !important;
    height: 6px !important;
    background-color: #86EFAC !important;
    border-radius: 50% !important;
    display: inline-block !important;
  }
  .pgchat-close-btn {
    background: none !important;
    border: none !important;
    color: #ffffff !important;
    cursor: pointer !important;
    padding: 4px !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 4px !important;
  }
  .pgchat-close-btn:hover {
    background-color: rgba(255,255,255,0.2) !important;
  }

  .pgchat-messages {
    flex: 1 !important;
    padding: 15px !important;
    overflow-y: auto !important;
    background-color: #f9f9f9 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
  }

  .pgchat-row-bot {
    display: flex !important;
    align-items: flex-end !important;
    gap: 8px !important;
    align-self: flex-start !important;
    max-width: 85% !important;
  }
  .pgchat-row-user {
    display: flex !important;
    align-self: flex-end !important;
    max-width: 80% !important;
  }

  .pgchat-avatar-body {
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background-color: #fff !important;
    padding: 4px !important;
    border: 1px solid #e1e4e8 !important;
    flex-shrink: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }
  .pgchat-avatar-body img {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
  }

  .pgchat-bubble-bot {
    background-color: #e9ecef !important;
    color: #333333 !important;
    padding: 10px 14px !important;
    border-radius: 10px !important;
    border-bottom-left-radius: 2px !important;
    font-size: 14px !important;
    line-height: 1.5 !important;
    word-wrap: break-word !important;
    white-space: normal !important;
  }
  .pgchat-bubble-bot strong {
    font-weight: 700 !important;
    color: #1a1a1a !important;
  }
  .pgchat-bubble-bot ul {
    list-style: none !important;
    margin: 4px 0 4px 4px !important;
    padding: 0 !important;
  }
  .pgchat-bubble-bot li {
    margin: 2px 0 !important;
    padding: 0 0 0 12px !important;
    position: relative !important;
    list-style: none !important;
  }
  .pgchat-bubble-bot li::before {
    content: '•' !important;
    position: absolute !important;
    left: 0 !important;
    color: #00A8E8 !important;
    font-weight: bold !important;
  }
  .pgchat-bubble-bot hr {
    border: none !important;
    border-top: 1px solid #d0d0d0 !important;
    margin: 6px 0 !important;
  }
  .pgchat-bubble-bot p {
    margin: 4px 0 !important;
    padding: 0 !important;
  }
  .pgchat-bubble-user {
    background-color: #00A8E8 !important;
    color: #ffffff !important;
    padding: 10px 14px !important;
    border-radius: 10px !important;
    border-bottom-right-radius: 2px !important;
    font-size: 14px !important;
    line-height: 1.4 !important;
    word-wrap: break-word !important;
    white-space: pre-wrap !important;
  }

  .pgchat-input-area {
    padding: 10px !important;
    border-top: 1px solid #eee !important;
    display: flex !important;
    gap: 10px !important;
    background-color: #fff !important;
    flex-shrink: 0 !important;
  }
  .pgchat-input {
    flex: 1 !important;
    padding: 10px 15px !important;
    border: 1px solid #ddd !important;
    border-radius: 20px !important;
    outline: none !important;
    font-size: 14px !important;
    background-color: #fff !important;
    color: #333 !important;
    font-family: inherit !important;
  }
  .pgchat-input:focus {
    border-color: #00A8E8 !important;
  }
  .pgchat-input::placeholder {
    color: #999 !important;
  }

  .pgchat-send-btn {
    background-color: #00A8E8 !important;
    color: white !important;
    border: none !important;
    border-radius: 50% !important;
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    transition: background-color 0.2s !important;
    padding: 0 !important;
  }
  .pgchat-send-btn:hover {
    background-color: #0096d1 !important;
  }
  .pgchat-send-btn:disabled {
    background-color: #ccc !important;
    cursor: default !important;
  }

  /* Typing dots animation */
  .pgchat-typing-dots::after {
    content: '' !important;
    animation: pgchat-dots 1.5s steps(4, end) infinite !important;
  }
  @keyframes pgchat-dots {
    0%   { content: '' ; }
    25%  { content: '.' ; }
    50%  { content: '..' ; }
    75%  { content: '...' ; }
    100% { content: '' ; }
  }
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  /* ── History panel ── */
  .pgchat-history-panel {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
  }
  .pgchat-history-search {
    padding: 10px 12px !important;
    border-bottom: 1px solid #eee !important;
    flex-shrink: 0 !important;
  }
  .pgchat-history-search input {
    width: 100% !important;
    padding: 9px 12px 9px 36px !important;
    border: 1px solid #ddd !important;
    border-radius: 10px !important;
    font-size: 13px !important;
    outline: none !important;
    background: #f9fafb !important;
    color: #333 !important;
    font-family: inherit !important;
  }
  .pgchat-history-search input:focus {
    border-color: #00A8E8 !important;
    background: #fff !important;
  }
  .pgchat-history-search-icon {
    position: absolute !important;
    left: 22px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: #9ca3af !important;
    pointer-events: none !important;
  }
  .pgchat-history-list {
    flex: 1 !important;
    overflow-y: auto !important;
    padding: 6px 0 !important;
  }
  .pgchat-history-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 12px 14px !important;
    cursor: pointer !important;
    transition: background 0.15s !important;
    border-bottom: 1px solid #f3f4f6 !important;
  }
  .pgchat-history-item:hover {
    background: #f0f9ff !important;
  }
  .pgchat-history-item-icon {
    width: 36px !important;
    height: 36px !important;
    border-radius: 10px !important;
    background: linear-gradient(135deg, #00A8E8, #00d4aa) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    color: #fff !important;
    font-size: 16px !important;
  }
  .pgchat-history-item-body {
    flex: 1 !important;
    min-width: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
  }
  .pgchat-history-item-title {
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #1f2937 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
  }
  .pgchat-history-item-date {
    font-size: 11px !important;
    color: #9ca3af !important;
  }
  .pgchat-history-delete {
    background: none !important;
    border: none !important;
    color: #d1d5db !important;
    cursor: pointer !important;
    padding: 4px !important;
    border-radius: 6px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    transition: color 0.15s, background 0.15s !important;
  }
  .pgchat-history-delete:hover {
    color: #ef4444 !important;
    background: #fef2f2 !important;
  }
  .pgchat-history-empty {
    text-align: center !important;
    padding: 40px 20px !important;
    color: #9ca3af !important;
    font-size: 13px !important;
  }
  .pgchat-header-actions {
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
  }
  .pgchat-header-btn {
    background: none !important;
    border: none !important;
    color: #ffffff !important;
    cursor: pointer !important;
    padding: 6px !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 6px !important;
    transition: background 0.15s !important;
  }
  .pgchat-header-btn:hover {
    background: rgba(255,255,255,0.2) !important;
  }
  .pgchat-new-chat-bar {
    padding: 10px 12px !important;
    border-bottom: 1px solid #eee !important;
    flex-shrink: 0 !important;
  }
  .pgchat-new-chat-btn {
    width: 100% !important;
    padding: 10px !important;
    background: linear-gradient(135deg, #00A8E8, #00d4aa) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 10px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    transition: opacity 0.2s !important;
    font-family: inherit !important;
  }
  .pgchat-new-chat-btn:hover {
    opacity: 0.9 !important;
  }
`;

const ANIMALS = ['🐶', '🐱', '🐰', '🐦', '🐹', '🦎', '🐠', '🐾', '🦜', '🐢', '🐕', '🐈'];

const BotChatOverlay = ({ cartOpen = false }) => {
  const { user, isAuthenticated } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const [view, setView] = useState('chat'); // 'chat' | 'history'

  // Escuchar evento global para abrir chat (desde botón "Chatear Ahora" del Home)
  useEffect(() => {
    const handler = () => { setIsOpen(true); setView('chat'); };
    window.addEventListener('petsgo:open_chat', handler);
    return () => window.removeEventListener('petsgo:open_chat', handler);
  }, []);
  const [inputValue, setInputValue] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [currentAnimal, setCurrentAnimal] = useState(() => ANIMALS[Math.floor(Math.random() * ANIMALS.length)]);
  const messagesEndRef = useRef(null);
  const conversationRef = useRef([]);
  const conversationIdRef = useRef(null); // ID de la conversación activa en BD
  const [botConfig, setBotConfig] = useState(null);
  const systemPromptRef = useRef('');
  const historyLoadedRef = useRef(false);
  const saveTimerRef = useRef(null);
  
  const [messages, setMessages] = useState([]);

  // ── Estado del panel de historial ──
  const [conversations, setConversations] = useState([]);
  const [historySearch, setHistorySearch] = useState('');
  const [historyLoading, setHistoryLoading] = useState(false);

  // ── Helpers localStorage para invitados (TTL 2 horas) ──
  const GUEST_CHAT_KEY = 'petsgo_guest_chat';
  const GUEST_TTL_MS   = 2 * 60 * 60 * 1000;

  const saveGuestChat = useCallback((conv) => {
    try {
      localStorage.setItem(GUEST_CHAT_KEY, JSON.stringify({ ts: Date.now(), messages: conv }));
    } catch { /* quota exceeded */ }
  }, []);

  const loadGuestChat = useCallback(() => {
    try {
      const raw = localStorage.getItem(GUEST_CHAT_KEY);
      if (!raw) return null;
      const { ts, messages: msgs } = JSON.parse(raw);
      if (Date.now() - ts > GUEST_TTL_MS) {
        localStorage.removeItem(GUEST_CHAT_KEY);
        return null;
      }
      return msgs;
    } catch {
      localStorage.removeItem(GUEST_CHAT_KEY);
      return null;
    }
  }, []);

  const clearGuestChat = useCallback(() => {
    localStorage.removeItem(GUEST_CHAT_KEY);
  }, []);

  // Función para guardar historial (debounced)
  const saveHistory = useCallback(() => {
    if (saveTimerRef.current) clearTimeout(saveTimerRef.current);
    saveTimerRef.current = setTimeout(async () => {
      const conv = conversationRef.current;
      if (conv.length === 0) return;
      if (isAuthenticated && user && ['customer', 'rider'].includes(user.role)) {
        try {
          const { data } = await saveChatHistory(conv, conversationIdRef.current);
          if (data.conversation_id) conversationIdRef.current = data.conversation_id;
        } catch { /* silencioso */ }
      } else {
        saveGuestChat(conv);
      }
    }, 2000);
  }, [isAuthenticated, user, saveGuestChat]);

  // ── Cargar lista de conversaciones (para panel historial) ──
  const loadConversationsList = useCallback(async () => {
    if (!isAuthenticated) return;
    setHistoryLoading(true);
    try {
      const { data } = await listConversations();
      setConversations(data.conversations || []);
    } catch { setConversations([]); }
    finally { setHistoryLoading(false); }
  }, [isAuthenticated]);

  // ── Abrir una conversación del historial ──
  const openConversation = useCallback(async (convId) => {
    try {
      const { data } = await getConversation(convId);
      conversationRef.current = data.messages || [];
      conversationIdRef.current = data.id;
      const welcome = botConfig?.welcome_msg || '¡Hola! Soy PetBot 🐾. ¿En qué puedo ayudarte?';
      const restored = (data.messages || []).map((m, i) => ({
        id: i + 1,
        type: m.role === 'user' ? 'user' : 'bot',
        text: m.content,
      }));
      setMessages([{ id: 0, type: 'bot', text: welcome }, ...restored]);
      setView('chat');
    } catch { /* error loading */ }
  }, [botConfig]);

  // ── Nueva conversación ──
  const startNewConversation = useCallback(() => {
    // Guardar la actual si tiene mensajes
    if (conversationRef.current.length > 0 && isAuthenticated) {
      saveChatHistory(conversationRef.current, conversationIdRef.current).catch(() => {});
    }
    conversationRef.current = [];
    conversationIdRef.current = null;
    let welcome = botConfig?.welcome_msg || '¡Hola! Soy PetBot, el asistente inteligente de PetsGo 🐾. ¿En qué puedo ayudarte hoy?';
    if (isAuthenticated && user?.firstName) {
      welcome = welcome.replace('¡Hola!', `¡Hola ${user.firstName}!`);
    }
    setMessages([{ id: 1, type: 'bot', text: welcome }]);
    setView('chat');
  }, [botConfig, isAuthenticated, user]);

  // ── Eliminar conversación del historial ──
  const handleDeleteConversation = useCallback(async (convId, e) => {
    e.stopPropagation();
    try {
      await deleteConversation(convId);
      setConversations(prev => prev.filter(c => c.id !== convId));
      // Si estamos viendo esa conversación, limpiar
      if (conversationIdRef.current === convId) {
        startNewConversation();
      }
    } catch { /* silencioso */ }
  }, [startNewConversation]);

  // ── Abrir panel de historial ──
  const openHistoryPanel = useCallback(() => {
    setView('history');
    setHistorySearch('');
    loadConversationsList();
  }, [loadConversationsList]);

  // Cargar configuración del chatbot + contexto de usuario + historial
  useEffect(() => {
    const loadBot = async () => {
      try {
        const { data } = await getChatbotConfig();
        setBotConfig(data);

        // Construir system prompt base con variables dinámicas
        let prompt = buildSystemPrompt(data);

        // Si el usuario está logueado (cliente o rider), cargar su contexto
        if (isAuthenticated && user && ['customer', 'rider'].includes(user.role)) {
          try {
            const { data: ctxData } = await getChatbotUserContext();
            if (ctxData.user_context) {
              prompt += buildUserContextBlock(ctxData.user_context);
            }
          } catch { /* si falla, el bot sigue sin contexto personal */ }

          // Cargar historial guardado (API)
          if (!historyLoadedRef.current) {
            try {
              const { data: histData } = await getChatHistory();
              if (histData.messages?.length) {
                conversationRef.current = histData.messages;
                const restored = histData.messages.map((m, i) => ({
                  id: i + 1,
                  type: m.role === 'user' ? 'user' : 'bot',
                  text: m.content,
                }));
                const welcome = data.welcome_msg || '¡Hola! Soy PetBot 🐾. ¿En qué puedo ayudarte?';
                setMessages([{ id: 0, type: 'bot', text: welcome }, ...restored]);
                historyLoadedRef.current = true;
                systemPromptRef.current = prompt;
                clearGuestChat(); // limpiar cache local si ya tiene cuenta
                return;
              }
            } catch { /* sin historial previo */ }
            historyLoadedRef.current = true;
          }
        }

        systemPromptRef.current = prompt;

        // Mensaje de bienvenida personalizado
        let welcome = data.welcome_msg || '¡Hola! Soy PetBot, el asistente inteligente de PetsGo 🐾. ¿En qué puedo ayudarte hoy?';
        if (isAuthenticated && user?.firstName) {
          welcome = welcome.replace('¡Hola!', `¡Hola ${user.firstName}!`);
        }

        // Para invitados: restaurar desde localStorage (máx 2 h)
        if (!isAuthenticated && !historyLoadedRef.current) {
          const guestMsgs = loadGuestChat();
          if (guestMsgs?.length) {
            conversationRef.current = guestMsgs;
            const restored = guestMsgs.map((m, i) => ({
              id: i + 1,
              type: m.role === 'user' ? 'user' : 'bot',
              text: m.content,
            }));
            setMessages([{ id: 0, type: 'bot', text: welcome }, ...restored]);
            historyLoadedRef.current = true;
            return;
          }
          historyLoadedRef.current = true;
        }

        if (messages.length === 0) {
          setMessages([{ id: 1, type: 'bot', text: welcome }]);
        }
      } catch {
        setBotConfig({ enabled: true, bot_name: 'PetBot', model: 'gpt-4o-mini' });
        setMessages([{ id: 1, type: 'bot', text: '¡Hola! Soy PetBot, el asistente inteligente de PetsGo 🐾. ¿En qué puedo ayudarte hoy?' }]);
      }
    };
    loadBot();
  }, [isAuthenticated, user?.id]); // Recargar cuando cambia el estado de auth

  // Inyectar CSS una sola vez 
  useEffect(() => {
    const styleId = 'pgchat-styles';
    if (!document.getElementById(styleId)) {
      const style = document.createElement('style');
      style.id = styleId;
      style.textContent = chatCSS;
      document.head.appendChild(style);
    }
  }, []);

  // Cambiar animal aleatorio cada 4s (coincide con duración de órbita)
  useEffect(() => {
    if (isOpen) return;
    const interval = setInterval(() => {
      setCurrentAnimal(ANIMALS[Math.floor(Math.random() * ANIMALS.length)]);
    }, 4000);
    return () => clearInterval(interval);
  }, [isOpen]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isOpen]);

  const handleSend = async () => {
    if (!inputValue.trim() || isLoading) return;
    const userText = inputValue.trim();
    setMessages((prev) => [...prev, { id: Date.now(), type: 'user', text: userText }]);
    setInputValue('');
    setIsLoading(true);

    // Agregar mensaje del usuario al historial de conversación
    conversationRef.current.push({ role: 'user', content: userText });

    // Mantener máximo 20 mensajes de contexto para evitar exceso de tokens
    if (conversationRef.current.length > 20) {
      conversationRef.current = conversationRef.current.slice(-20);
    }

    try {
      const prompt = systemPromptRef.current;

      const allMessages = [
          ...(prompt ? [{ role: 'system', content: prompt }] : []),
          ...conversationRef.current,
      ];
      const response = await sendChatbotMessage(allMessages);

      const aiMessage = response.data?.choices?.[0]?.message?.content
        || 'Lo siento, no pude procesar tu mensaje. ¿Podrías intentar de nuevo? 🐾';

      // Agregar respuesta del asistente al historial
      conversationRef.current.push({ role: 'assistant', content: aiMessage });

      setMessages((prev) => [...prev, {
        id: Date.now() + 1, type: 'bot', text: aiMessage,
      }]);

      // Guardar historial para usuarios logueados
      saveHistory();
    } catch (error) {
      console.error('PetsGo Bot Error:', error);
      console.error('PetsGo Bot Error Response:', error.response?.data);
      console.error('PetsGo Bot Error Status:', error.response?.status);
      const fallback = error.code === 'ECONNABORTED'
        ? 'La respuesta está tardando más de lo esperado. Por favor intenta de nuevo en unos segundos ⏳'
        : 'Ups, tuve un problema al procesar tu consulta. Puedes intentar de nuevo o escribirnos por WhatsApp al +56 9 1234 5678 📱';
      setMessages((prev) => [...prev, {
        id: Date.now() + 1, type: 'bot', text: fallback,
      }]);
    } finally {
      setIsLoading(false);
    }
  };

  const leftClass = cartOpen ? ' pgchat-left' : '';

  // Helper: formatear fecha relativa
  const formatRelativeDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now - d;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'Ahora';
    if (diffMin < 60) return `Hace ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return `Hace ${diffH}h`;
    const diffD = Math.floor(diffH / 24);
    if (diffD < 7) return `Hace ${diffD}d`;
    return d.toLocaleDateString('es-CL', { day: '2-digit', month: 'short' });
  };

  // Filtrar conversaciones por búsqueda
  const filteredConvs = historySearch.trim()
    ? conversations.filter(c =>
        (c.title || '').toLowerCase().includes(historySearch.toLowerCase()) ||
        (c.preview || '').toLowerCase().includes(historySearch.toLowerCase())
      )
    : conversations;

  // Si el chatbot está desactivado desde el admin, no renderizar nada
  if (botConfig && !botConfig.enabled) return null;

  const isLoggedForHistory = isAuthenticated && user && ['customer', 'rider'].includes(user.role);

  return (
    <>
      {!isOpen && (
        <>
          <div className={`pgchat-tooltip${leftClass}`}>
            🐾 ¿Dudas? ¡Te ayudo!
          </div>
          <div className={`pgchat-orbit${leftClass}`}>
            <div className="pgchat-ring-visual" />
            <span className="pgchat-orbiter" key={currentAnimal}>{currentAnimal}</span>
          </div>
          <button className={`pgchat-trigger${leftClass}`} onClick={() => { setIsOpen(true); setView('chat'); }} aria-label="Abrir chat">
            <img src={huellaSvg} alt="Chat" style={{ width: '32px', height: '32px' }} />
          </button>
        </>
      )}

      {isOpen && (
        <div className={`pgchat-widget pgchat-window${leftClass}`}>
          {/* HEADER */}
          <div className="pgchat-header">
            <div className="pgchat-header-left">
              {view === 'history' && (
                <button className="pgchat-header-btn" onClick={() => setView('chat')} title="Volver al chat">
                  <ArrowLeft size={18} />
                </button>
              )}
              <div className="pgchat-header-avatar">
                <img src={huellaSvg} alt="PetsGo" />
              </div>
              <div className="pgchat-header-info">
                <span className="pgchat-header-title">
                  {view === 'history' ? 'Historial' : (botConfig?.bot_name || 'Asistente PetsGo')}
                </span>
                <span className="pgchat-header-status">
                  <span className="pgchat-status-dot"></span>
                  {view === 'history' ? 'Tus conversaciones' : 'En línea'}
                </span>
              </div>
            </div>
            <div className="pgchat-header-actions">
              {isLoggedForHistory && view === 'chat' && (
                <>
                  <button className="pgchat-header-btn" onClick={startNewConversation} title="Nueva conversación">
                    <Plus size={18} />
                  </button>
                  <button className="pgchat-header-btn" onClick={openHistoryPanel} title="Historial">
                    <Clock size={18} />
                  </button>
                </>
              )}
              <button className="pgchat-header-btn" onClick={() => setIsOpen(false)} title="Cerrar">
                <X size={18} />
              </button>
            </div>
          </div>

          {/* ═══ VISTA: CHAT ═══ */}
          {view === 'chat' && (
            <>
              <div className="pgchat-messages">
                {messages.map((msg) => (
                  <div key={msg.id} className={msg.type === 'user' ? 'pgchat-row-user' : 'pgchat-row-bot'}>
                    {msg.type === 'bot' && (
                      <div className="pgchat-avatar-body">
                        <img src={huellaSvg} alt="Bot" />
                      </div>
                    )}
                    <div className={msg.type === 'user' ? 'pgchat-bubble-user' : 'pgchat-bubble-bot'}>
                      {msg.type === 'bot'
                        ? <span dangerouslySetInnerHTML={{ __html: formatBotMessage(msg.text) }} />
                        : msg.text
                      }
                    </div>
                  </div>
                ))}
                {isLoading && (
                  <div className="pgchat-row-bot">
                    <div className="pgchat-avatar-body">
                      <img src={huellaSvg} alt="Bot" />
                    </div>
                    <div className="pgchat-bubble-bot" style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                      <span className="pgchat-typing-dots">Escribiendo</span>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>

              <div className="pgchat-input-area">
                <input
                  className="pgchat-input"
                  type="text"
                  placeholder={isLoading ? 'PetBot está pensando...' : 'Escribe tu mensaje...'}
                  value={inputValue}
                  onChange={(e) => setInputValue(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                  disabled={isLoading}
                />
                <button className="pgchat-send-btn" onClick={handleSend} disabled={!inputValue.trim() || isLoading}>
                  {isLoading ? <Loader2 size={18} style={{ animation: 'spin 1s linear infinite' }} /> : <Send size={18} />}
                </button>
              </div>
            </>
          )}

          {/* ═══ VISTA: HISTORIAL ═══ */}
          {view === 'history' && (
            <div className="pgchat-history-panel">
              {/* Botón nueva conversación */}
              <div className="pgchat-new-chat-bar">
                <button className="pgchat-new-chat-btn" onClick={startNewConversation}>
                  <Plus size={16} /> Nueva conversación
                </button>
              </div>

              {/* Buscador */}
              <div className="pgchat-history-search" style={{ position: 'relative' }}>
                <Search size={14} className="pgchat-history-search-icon" />
                <input
                  type="text"
                  placeholder="Buscar conversaciones..."
                  value={historySearch}
                  onChange={(e) => setHistorySearch(e.target.value)}
                  autoComplete="off"
                />
              </div>

              {/* Lista de conversaciones */}
              <div className="pgchat-history-list">
                {historyLoading ? (
                  <div className="pgchat-history-empty">
                    <Loader2 size={24} style={{ animation: 'spin 1s linear infinite', margin: '0 auto 8px' }} />
                    <div>Cargando historial…</div>
                  </div>
                ) : filteredConvs.length === 0 ? (
                  <div className="pgchat-history-empty">
                    <div style={{ fontSize: '32px', marginBottom: '8px' }}>💬</div>
                    {historySearch
                      ? 'No se encontraron conversaciones'
                      : 'Aún no tienes conversaciones guardadas'}
                  </div>
                ) : (
                  filteredConvs.map((conv) => (
                    <div
                      key={conv.id}
                      className="pgchat-history-item"
                      onClick={() => openConversation(conv.id)}
                    >
                      <div className="pgchat-history-item-icon">🐾</div>
                      <div className="pgchat-history-item-body">
                        <div className="pgchat-history-item-title">
                          {conv.title || 'Conversación sin título'}
                        </div>
                        <div className="pgchat-history-item-date">
                          {formatRelativeDate(conv.updated_at)}
                        </div>
                      </div>
                      <button
                        className="pgchat-history-delete"
                        onClick={(e) => handleDeleteConversation(conv.id, e)}
                        title="Eliminar conversación"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}
        </div>
      )}
    </>
  );
};

export default BotChatOverlay;