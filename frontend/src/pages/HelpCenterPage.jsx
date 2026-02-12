import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  LifeBuoy, MessageSquare, ShoppingBag, Truck, User, Store,
  ChevronDown, ChevronUp, ArrowRight, HelpCircle, Shield, FileText,
} from 'lucide-react';
import { getLegalPage } from '../services/api';
import { useAuth } from '../context/AuthContext';

const HelpCenterPage = () => {
  const { isAuthenticated, user } = useAuth();
  const [content, setContent] = useState('');
  const [loading, setLoading] = useState(true);
  const [openFaq, setOpenFaq] = useState(null);

  useEffect(() => {
    (async () => {
      try {
        const res = await getLegalPage('centro-de-ayuda');
        setContent(res.data?.content || '');
      } catch (e) { /* no content yet */ }
      finally { setLoading(false); }
    })();
  }, []);

  const faqs = [
    {
      q: '¿Cómo creo un ticket de soporte?',
      a: 'Si eres cliente o rider, inicia sesión y ve a la sección "Soporte" en tu perfil. Si eres una tienda, crea el ticket desde el portal de administración.',
    },
    {
      q: '¿Cuánto tardan en responder mi ticket?',
      a: 'Nuestro equipo revisa los tickets en un plazo máximo de 24 horas hábiles. Los tickets urgentes son atendidos con mayor prioridad.',
    },
    {
      q: '¿Puedo dar seguimiento a mi reclamo?',
      a: 'Sí, puedes ver el estado de todos tus tickets y agregar mensajes adicionales desde la sección "Soporte" en tu perfil.',
    },
    {
      q: '¿Qué tipo de problemas puedo reportar?',
      a: 'Puedes reportar problemas con pedidos, pagos, entregas, tu cuenta, productos y cualquier otra consulta relacionada con PetsGo.',
    },
    {
      q: '¿Puedo contactar directamente por WhatsApp?',
      a: 'Sí, puedes contactarnos por WhatsApp para consultas rápidas. Sin embargo, para reclamos formales te recomendamos crear un ticket para mejor seguimiento.',
    },
  ];

  const ticketGuides = [
    {
      icon: User,
      role: 'Clientes',
      color: '#00A8E8',
      bg: '#EFF6FF',
      steps: [
        'Inicia sesión en tu cuenta de PetsGo',
        'Ve a tu perfil y selecciona "Soporte"',
        'Haz clic en "Nuevo Ticket"',
        'Selecciona la categoría y prioridad',
        'Describe tu problema con detalle',
        'Envía y recibirás un correo de confirmación',
      ],
      cta: { text: 'Ir a Soporte', link: '/soporte' },
    },
    {
      icon: Truck,
      role: 'Riders',
      color: '#FFC400',
      bg: '#FFFBEB',
      steps: [
        'Inicia sesión con tu cuenta de rider',
        'Ve a tu perfil y selecciona "Soporte"',
        'Haz clic en "Nuevo Ticket"',
        'Indica la categoría (entregas, pagos, cuenta, etc.)',
        'Describe el problema e incluye el N° de pedido si aplica',
        'Envía y haz seguimiento desde la misma sección',
      ],
      cta: { text: 'Ir a Soporte', link: '/soporte' },
    },
    {
      icon: Store,
      role: 'Tiendas',
      color: '#22C55E',
      bg: '#F0FDF4',
      steps: [
        'Ingresa al Portal de Administración de PetsGo',
        'En el menú lateral, busca "🎫 Tickets"',
        'Los tickets de tus clientes aparecerán aquí',
        'Para crear un ticket propio, usa el botón correspondiente',
        'El equipo de PetsGo revisará y responderá tu solicitud',
        'Recibirás notificaciones por correo sobre actualizaciones',
      ],
      cta: null,
    },
  ];

  const st = {
    page: { maxWidth: '960px', margin: '0 auto', padding: '32px 16px', fontFamily: 'Poppins, sans-serif' },
    card: { background: '#fff', borderRadius: '16px', boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0', padding: '28px' },
    sectionTitle: { fontSize: '20px', fontWeight: 800, color: '#2F3A40', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' },
  };

  return (
    <div style={st.page}>
      {/* Hero */}
      <div style={{
        background: 'linear-gradient(135deg, #00A8E8, #0077B6)', borderRadius: '20px',
        padding: '48px 32px', textAlign: 'center', color: '#fff', marginBottom: '32px',
      }}>
        <LifeBuoy size={48} style={{ margin: '0 auto 16px', opacity: 0.9 }} />
        <h1 style={{ fontSize: '28px', fontWeight: 900, marginBottom: '12px' }}>Centro de Ayuda</h1>
        <p style={{ fontSize: '16px', opacity: 0.9, maxWidth: '600px', margin: '0 auto 24px' }}>
          ¿Tienes un problema o consulta? Aquí te explicamos cómo obtener ayuda rápida y efectiva.
        </p>
        {isAuthenticated && (
          <Link to="/soporte" style={{
            display: 'inline-flex', alignItems: 'center', gap: '8px',
            background: '#fff', color: '#00A8E8', padding: '12px 28px', borderRadius: '12px',
            fontWeight: 700, fontSize: '14px', textDecoration: 'none',
          }}>
            <MessageSquare size={18} /> Crear Ticket de Soporte
          </Link>
        )}
      </div>

      {/* Custom admin content */}
      {content && (
        <div style={{ ...st.card, marginBottom: '24px' }}>
          <div
            dangerouslySetInnerHTML={{ __html: content }}
            style={{ lineHeight: 1.8, fontSize: '14px', color: '#374151' }}
          />
        </div>
      )}

      {/* How to create a ticket — per role */}
      <div style={{ marginBottom: '32px' }}>
        <h2 style={st.sectionTitle}>
          <HelpCircle size={22} color="#00A8E8" /> ¿Cómo crear un ticket de soporte?
        </h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '16px' }}>
          {ticketGuides.map((guide) => {
            const Icon = guide.icon;
            return (
              <div key={guide.role} style={{
                ...st.card, display: 'flex', flexDirection: 'column',
              }}>
                <div style={{
                  display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '18px',
                }}>
                  <div style={{
                    width: '44px', height: '44px', borderRadius: '12px', background: guide.bg,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                  }}>
                    <Icon size={22} color={guide.color} />
                  </div>
                  <h3 style={{ fontWeight: 800, fontSize: '16px', color: '#2F3A40' }}>{guide.role}</h3>
                </div>
                <ol style={{
                  paddingLeft: '20px', margin: 0, flex: 1,
                  listStyle: 'none', counterReset: 'step',
                }}>
                  {guide.steps.map((step, i) => (
                    <li key={i} style={{
                      fontSize: '13px', color: '#6b7280', marginBottom: '10px', paddingLeft: '8px',
                      counterIncrement: 'step', position: 'relative', lineHeight: 1.5,
                    }}>
                      <span style={{
                        position: 'absolute', left: '-20px', top: '0',
                        width: '20px', height: '20px', borderRadius: '50%',
                        background: guide.bg, color: guide.color,
                        fontSize: '11px', fontWeight: 800, display: 'flex',
                        alignItems: 'center', justifyContent: 'center',
                      }}>
                        {i + 1}
                      </span>
                      {step}
                    </li>
                  ))}
                </ol>
                {guide.cta && isAuthenticated && (
                  <Link to={guide.cta.link} style={{
                    display: 'flex', alignItems: 'center', gap: '6px', marginTop: '14px',
                    fontSize: '13px', fontWeight: 700, color: guide.color, textDecoration: 'none',
                  }}>
                    {guide.cta.text} <ArrowRight size={14} />
                  </Link>
                )}
                {guide.cta && !isAuthenticated && (
                  <Link to="/login" style={{
                    display: 'flex', alignItems: 'center', gap: '6px', marginTop: '14px',
                    fontSize: '13px', fontWeight: 700, color: guide.color, textDecoration: 'none',
                  }}>
                    Iniciar Sesión <ArrowRight size={14} />
                  </Link>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* FAQ */}
      <div style={{ marginBottom: '32px' }}>
        <h2 style={st.sectionTitle}>
          <MessageSquare size={22} color="#FFC400" /> Preguntas Frecuentes
        </h2>
        <div style={st.card}>
          {faqs.map((faq, idx) => {
            const isOpen = openFaq === idx;
            return (
              <div key={idx} style={{
                borderBottom: idx < faqs.length - 1 ? '1px solid #f3f4f6' : 'none',
              }}>
                <button
                  onClick={() => setOpenFaq(isOpen ? null : idx)}
                  style={{
                    width: '100%', padding: '18px 0', background: 'none', border: 'none',
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    cursor: 'pointer', fontFamily: 'Poppins, sans-serif', textAlign: 'left',
                  }}
                >
                  <span style={{ fontWeight: 700, fontSize: '14px', color: '#2F3A40' }}>{faq.q}</span>
                  {isOpen ? <ChevronUp size={18} color="#9ca3af" /> : <ChevronDown size={18} color="#9ca3af" />}
                </button>
                {isOpen && (
                  <p style={{
                    padding: '0 0 18px', fontSize: '13px', color: '#6b7280',
                    lineHeight: 1.7, margin: 0,
                  }}>
                    {faq.a}
                  </p>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Quick links */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '12px' }}>
        <Link to="/terminos-y-condiciones" style={{
          ...st.card, textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '12px',
          transition: 'transform 0.2s', padding: '18px 20px',
        }}>
          <FileText size={20} color="#00A8E8" />
          <span style={{ fontWeight: 700, fontSize: '13px', color: '#2F3A40' }}>Términos y Condiciones</span>
        </Link>
        <Link to="/politica-de-privacidad" style={{
          ...st.card, textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '12px',
          transition: 'transform 0.2s', padding: '18px 20px',
        }}>
          <Shield size={20} color="#22C55E" />
          <span style={{ fontWeight: 700, fontSize: '13px', color: '#2F3A40' }}>Política de Privacidad</span>
        </Link>
        <Link to="/politica-de-envios" style={{
          ...st.card, textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '12px',
          transition: 'transform 0.2s', padding: '18px 20px',
        }}>
          <Truck size={20} color="#FFC400" />
          <span style={{ fontWeight: 700, fontSize: '13px', color: '#2F3A40' }}>Política de Envíos</span>
        </Link>
      </div>
    </div>
  );
};

export default HelpCenterPage;
