import React, { useState, useEffect, useRef } from 'react';
import { Send, X } from 'lucide-react';
import huellaSvg from '../assets/Huella-1.svg';

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
    transition: transform 0.2s, right 0.4s cubic-bezier(0.4,0,0.2,1), left 0.4s cubic-bezier(0.4,0,0.2,1) !important;
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
    transition: right 0.4s cubic-bezier(0.4,0,0.2,1), left 0.4s cubic-bezier(0.4,0,0.2,1) !important;
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
    transition: right 0.4s cubic-bezier(0.4,0,0.2,1), left 0.4s cubic-bezier(0.4,0,0.2,1) !important;
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
    transition: right 0.4s cubic-bezier(0.4,0,0.2,1), left 0.4s cubic-bezier(0.4,0,0.2,1) !important;
  }
  .pgchat-window.pgchat-left {
    right: auto !important;
    left: 30px !important;
  }
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
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
    line-height: 1.4 !important;
    word-wrap: break-word !important;
    white-space: pre-wrap !important;
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
`;

const ANIMALS = ['🐶', '🐱', '🐰', '🐦', '🐹', '🦎', '🐠', '🐾', '🦜', '🐢', '🐕', '🐈'];

const BotChatOverlay = ({ cartOpen = false }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [inputValue, setInputValue] = useState('');
  const [currentAnimal, setCurrentAnimal] = useState(() => ANIMALS[Math.floor(Math.random() * ANIMALS.length)]);
  const messagesEndRef = useRef(null);
  
  const [messages, setMessages] = useState([
    { id: 1, type: 'bot', text: '¡Hola! Soy el asistente inteligente de PetsGo 🐾. ¿En qué puedo ayudarte hoy?' },
  ]);

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

  const handleSend = () => {
    if (!inputValue.trim()) return;
    const userMsg = inputValue.toLowerCase();
    setMessages((prev) => [...prev, { id: Date.now(), type: 'user', text: inputValue }]);
    setInputValue('');

    // Respuestas inteligentes basadas en keywords
    let botResponse = '';
    if (userMsg.includes('alimento') || userMsg.includes('comida') || userMsg.includes('comer')) {
      botResponse = '¡Tenemos excelentes opciones! Te recomiendo Royal Canin o Pro Plan según la raza y edad de tu mascota. Ambos tienen 20% de descuento esta semana 🦴';
    } else if (userMsg.includes('perro') || userMsg.includes('cachorro')) {
      botResponse = '🐕 Para perros tenemos alimento seco y húmedo, snacks dentales, collares LED, camas ortopédicas y antiparasitarios. ¿Qué necesita tu peludo?';
    } else if (userMsg.includes('gato') || userMsg.includes('gatito') || userMsg.includes('felino')) {
      botResponse = '🐱 Para gatos recomendamos Pro Plan o Whiskas. También tenemos areneros, rascadores y juguetes interactivos. ¿Qué buscas?';
    } else if (userMsg.includes('envío') || userMsg.includes('envio') || userMsg.includes('despacho') || userMsg.includes('delivery')) {
      botResponse = '🚚 ¡Envío gratis desde $39.990! Despacho express en pocas horas dentro de Santiago RM. Tu primer pedido tiene delivery gratuito 🎉';
    } else if (userMsg.includes('precio') || userMsg.includes('descuento') || userMsg.includes('oferta') || userMsg.includes('barato')) {
      botResponse = '💰 ¡Tenemos ofertas increíbles! Usa el código BIENVENIDO15 para 15% de descuento en tu primera compra. ¿Te muestro los productos en oferta?';
    } else if (userMsg.includes('tienda') || userMsg.includes('store')) {
      botResponse = '🏪 Contamos con +8 tiendas verificadas en la RM: PetShop Las Condes, La Huella Store, Vet & Shop y más. ¿Quieres que te recomiende una cerca de ti?';
    } else if (userMsg.includes('pago') || userMsg.includes('tarjeta') || userMsg.includes('transferencia')) {
      botResponse = '💳 Aceptamos tarjetas de débito/crédito, transferencia bancaria y pago contra entrega. ¡Todas las transacciones son 100% seguras! 🔒';
    } else if (userMsg.includes('hola') || userMsg.includes('buenas') || userMsg.includes('hey')) {
      botResponse = '¡Hola! 👋 Bienvenido a PetsGo. Puedo ayudarte a encontrar el mejor alimento, accesorios o resolver dudas sobre envíos. ¿Qué necesitas?';
    } else if (userMsg.includes('gracias') || userMsg.includes('thanks')) {
      botResponse = '¡De nada! 🐾 Estoy aquí cuando me necesites. ¡Que tu mascota disfrute sus productos! 💛';
    } else if (userMsg.includes('horario') || userMsg.includes('hora') || userMsg.includes('abierto')) {
      botResponse = '🕐 Nuestras tiendas despachan de lunes a sábado 9:00-20:00. Los pedidos online se procesan 24/7. ¡Siempre estamos disponibles!';
    } else {
      const generalResponses = [
        'Tengo excelentes opciones de alimento premium con 20% de descuento. ¿Te gustaría verlas? 🦴',
        '¿Sabías que tu primera compra tiene envío gratis? Usa el código BIENVENIDO15 para 15% extra 🎉',
        'Te recomiendo visitar nuestra sección de tiendas para encontrar la más cercana a ti 🏪',
        '¿Tu mascota es perro o gato? Así puedo darte recomendaciones más precisas 🐾',
        'Nuestras marcas top son Royal Canin, Pro Plan, Hills y Bravecto. ¿Cuál te interesa? ⭐',
      ];
      botResponse = generalResponses[Math.floor(Math.random() * generalResponses.length)];
    }

    setTimeout(() => {
      setMessages((prev) => [...prev, {
        id: Date.now() + 1, type: 'bot', text: botResponse,
      }]);
    }, 800 + Math.random() * 700);
  };

  const leftClass = cartOpen ? ' pgchat-left' : '';

  return (
    <>
      {/* BOTÓN FLOTANTE CON ANIMAL ORBITANDO */}
      {!isOpen && (
        <>
          {/* Tooltip invitación */}
          <div className={`pgchat-tooltip${leftClass}`}>
            🐾 ¿Dudas? ¡Te ayudo!
          </div>
          {/* Anillo tipo Saturno */}
          <div className={`pgchat-orbit${leftClass}`}>
            <div className="pgchat-ring-visual" />
            <span className="pgchat-orbiter" key={currentAnimal}>{currentAnimal}</span>
          </div>
          <button className={`pgchat-trigger${leftClass}`} onClick={() => setIsOpen(true)} aria-label="Abrir chat">
            <img src={huellaSvg} alt="Chat" style={{ width: '32px', height: '32px' }} />
          </button>
        </>
      )}

      {/* VENTANA DE CHAT */}
      {isOpen && (
        <div className={`pgchat-widget pgchat-window${leftClass}`}>
          {/* HEADER */}
          <div className="pgchat-header">
            <div className="pgchat-header-left">
              <div className="pgchat-header-avatar">
                <img src={huellaSvg} alt="PetsGo" />
              </div>
              <div className="pgchat-header-info">
                <span className="pgchat-header-title">Asistente PetsGo</span>
                <span className="pgchat-header-status">
                  <span className="pgchat-status-dot"></span>
                  En línea
                </span>
              </div>
            </div>
            <button className="pgchat-close-btn" onClick={() => setIsOpen(false)}>
              <X size={20} />
            </button>
          </div>

          {/* MENSAJES */}
          <div className="pgchat-messages">
            {messages.map((msg) => (
              <div key={msg.id} className={msg.type === 'user' ? 'pgchat-row-user' : 'pgchat-row-bot'}>
                {msg.type === 'bot' && (
                  <div className="pgchat-avatar-body">
                    <img src={huellaSvg} alt="Bot" />
                  </div>
                )}
                <div className={msg.type === 'user' ? 'pgchat-bubble-user' : 'pgchat-bubble-bot'}>
                  {msg.text}
                </div>
              </div>
            ))}
            <div ref={messagesEndRef} />
          </div>

          {/* INPUT */}
          <div className="pgchat-input-area">
            <input
              className="pgchat-input"
              type="text"
              placeholder="Escribe tu mensaje..."
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && handleSend()}
            />
            <button className="pgchat-send-btn" onClick={handleSend} disabled={!inputValue.trim()}>
              <Send size={18} />
            </button>
          </div>
        </div>
      )}
    </>
  );
};

export default BotChatOverlay;
