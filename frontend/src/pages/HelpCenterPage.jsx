import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  LifeBuoy, MessageSquare, Truck,
  ChevronDown, ChevronUp, Shield, FileText,
} from 'lucide-react';
import { getLegalPage } from '../services/api';
import { useAuth } from '../context/AuthContext';
import { useSite } from '../context/SiteContext';

const DEFAULT_FAQS = [
  { q: '¿Cómo creo un ticket de soporte?', a: 'Si eres cliente o rider, inicia sesión y ve a la sección "Soporte" en tu perfil. Si eres una tienda, crea el ticket desde el portal de administración.' },
  { q: '¿Cuánto tardan en responder mi ticket?', a: 'Nuestro equipo revisa los tickets en un plazo máximo de 24 horas hábiles. Los tickets urgentes son atendidos con mayor prioridad.' },
  { q: '¿Puedo dar seguimiento a mi reclamo?', a: 'Sí, puedes ver el estado de todos tus tickets y agregar mensajes adicionales desde la sección "Soporte" en tu perfil.' },
  { q: '¿Qué tipo de problemas puedo reportar?', a: 'Puedes reportar problemas con pedidos, pagos, entregas, tu cuenta, productos y cualquier otra consulta relacionada con PetsGo.' },
  { q: '¿Puedo contactar directamente por WhatsApp?', a: 'Sí, puedes contactarnos por WhatsApp para consultas rápidas. Sin embargo, para reclamos formales te recomendamos crear un ticket para mejor seguimiento.' },
];

const HelpCenterPage = () => {
  const { isAuthenticated } = useAuth();
  const site = useSite();
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

  // Use admin-managed FAQs if available, otherwise defaults
  const faqs = (site.faqs && site.faqs.length > 0) ? site.faqs : DEFAULT_FAQS;

  /* CSS for admin-editable HTML content */
  const contentCSS = `
    .help-content h2 { font-size:20px; font-weight:800; color:#2F3A40; margin:24px 0 12px; display:flex; align-items:center; gap:8px; }
    .help-content h2:first-child { margin-top:0; }
    .help-content h3 { font-size:16px; font-weight:700; color:#00A8E8; margin:20px 0 8px; }
    .help-content p  { font-size:14px; color:#374151; line-height:1.8; margin:0 0 12px; }
    .help-content ol { padding-left:24px; margin:0 0 16px; }
    .help-content ol li { font-size:13px; color:#6b7280; line-height:1.8; margin-bottom:6px; padding-left:4px; }
    .help-content ol li::marker { color:#00A8E8; font-weight:700; }
    .help-content hr { border:none; border-top:1px solid #f0f0f0; margin:24px 0; }
    .help-content ul { padding-left:20px; margin:0 0 16px; }
    .help-content ul li { font-size:13px; color:#6b7280; line-height:1.8; margin-bottom:6px; }
    .help-content strong { color:#2F3A40; }
    .help-content a { color:#00A8E8; text-decoration:underline; }
    .help-content img { max-width:100%; height:auto; border-radius:8px; margin:12px 0; }
  `;

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

      <style>{contentCSS}</style>

      {/* Contenido gestionable desde el backend */}
      {loading && (
        <div style={{ textAlign: 'center', color: '#999', margin: '32px 0', fontSize: '14px' }}>Cargando...</div>
      )}
      {!loading && content && (
        <div style={{ ...st.card, marginBottom: '32px' }} className="help-content">
          <div dangerouslySetInnerHTML={{ __html: content }} />
        </div>
      )}

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
