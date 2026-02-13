import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Store, Bike, ArrowRight, X } from 'lucide-react';

const PROMOS = [
  {
    id: 'tienda',
    icon: Store,
    color: '#00A8E8',
    bg: 'linear-gradient(135deg, #00A8E8, #0077B6)',
    title: '¿Tienes una tienda de mascotas?',
    text: 'Únete a PetsGo y empieza a vender. ¡Inscripción sin costo!',
    cta: 'Ver Planes',
    link: '/planes',
  },
  {
    id: 'rider',
    icon: Bike,
    color: '#22C55E',
    bg: 'linear-gradient(135deg, #22C55E, #16A34A)',
    title: '¿Quieres ser Rider?',
    text: 'Gana dinero entregando pedidos de mascotas. ¡Regístrate hoy!',
    cta: 'Registrarme',
    link: '/rider/registro',
  },
];

const SHOW_DURATION = 15000;   // 15s visible
const HIDE_DURATION = 15000;   // 15s hidden between promos

const PromoSlider = () => {
  const [activeIndex, setActiveIndex] = useState(0);
  const [visible, setVisible] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    if (dismissed) return;

    // Initial delay before first promo appears
    const initialTimer = setTimeout(() => setVisible(true), 3000);

    return () => clearTimeout(initialTimer);
  }, [dismissed]);

  useEffect(() => {
    if (dismissed || !visible) return;

    // After SHOW_DURATION, hide current promo
    const hideTimer = setTimeout(() => {
      setVisible(false);
    }, SHOW_DURATION);

    return () => clearTimeout(hideTimer);
  }, [visible, dismissed]);

  useEffect(() => {
    if (dismissed || visible) return;

    // After HIDE_DURATION, show next promo
    const showTimer = setTimeout(() => {
      setActiveIndex((prev) => (prev + 1) % PROMOS.length);
      setVisible(true);
    }, HIDE_DURATION);

    return () => clearTimeout(showTimer);
  }, [visible, dismissed]);

  if (dismissed) return null;

  const promo = PROMOS[activeIndex];
  const Icon = promo.icon;

  return (
    <>
      <style>{`
        @keyframes promoSlideIn {
          from { transform: translateX(-120%); opacity: 0; }
          to   { transform: translateX(0); opacity: 1; }
        }
        @keyframes promoSlideOut {
          from { transform: translateX(0); opacity: 1; }
          to   { transform: translateX(-120%); opacity: 0; }
        }
        .promo-slider {
          position: fixed;
          left: 20px;
          bottom: 100px;
          z-index: 900;
          max-width: 320px;
          width: calc(100vw - 40px);
          border-radius: 18px;
          overflow: hidden;
          box-shadow: 0 8px 32px rgba(0,0,0,0.18);
          font-family: 'Poppins', sans-serif;
          pointer-events: auto;
        }
        .promo-slider.promo-visible {
          animation: promoSlideIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .promo-slider.promo-hidden {
          animation: promoSlideOut 0.4s cubic-bezier(0.55, 0, 1, 0.45) forwards;
        }
        @media (max-width: 640px) {
          .promo-slider {
            left: 10px;
            bottom: 80px;
            max-width: calc(100vw - 20px);
          }
        }
      `}</style>
      <div className={`promo-slider ${visible ? 'promo-visible' : 'promo-hidden'}`}>
        <div style={{ background: promo.bg, padding: '20px 20px 16px', position: 'relative' }}>
          {/* Close button */}
          <button
            onClick={() => setDismissed(true)}
            style={{
              position: 'absolute', top: '10px', right: '10px',
              background: 'rgba(255,255,255,0.2)', border: 'none', borderRadius: '50%',
              width: '26px', height: '26px', display: 'flex', alignItems: 'center',
              justifyContent: 'center', cursor: 'pointer', color: '#fff',
              transition: 'background 0.2s',
            }}
            onMouseEnter={(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.35)'}
            onMouseLeave={(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.2)'}
            aria-label="Cerrar"
          >
            <X size={14} />
          </button>

          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '10px' }}>
            <div style={{
              width: '40px', height: '40px', borderRadius: '12px',
              background: 'rgba(255,255,255,0.2)', display: 'flex',
              alignItems: 'center', justifyContent: 'center',
            }}>
              <Icon size={22} color="#fff" />
            </div>
            <h4 style={{ fontSize: '15px', fontWeight: 800, color: '#fff', margin: 0, lineHeight: 1.3 }}>
              {promo.title}
            </h4>
          </div>
          <p style={{ fontSize: '13px', color: 'rgba(255,255,255,0.9)', margin: '0 0 14px', lineHeight: 1.5 }}>
            {promo.text}
          </p>
          <Link
            to={promo.link}
            style={{
              display: 'inline-flex', alignItems: 'center', gap: '6px',
              background: '#fff', color: promo.color, padding: '10px 20px',
              borderRadius: '10px', fontSize: '13px', fontWeight: 700,
              textDecoration: 'none', transition: 'transform 0.2s, box-shadow 0.2s',
              boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
            }}
            onMouseEnter={(e) => { e.currentTarget.style.transform = 'translateY(-1px)'; e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)'; }}
          >
            {promo.cta} <ArrowRight size={14} />
          </Link>
        </div>
        {/* Progress bar */}
        {visible && (
          <div style={{ height: '3px', background: 'rgba(0,0,0,0.1)' }}>
            <div style={{
              height: '100%', background: '#fff',
              animation: `promoProgress ${SHOW_DURATION}ms linear forwards`,
            }} />
            <style>{`
              @keyframes promoProgress {
                from { width: 100%; }
                to   { width: 0%; }
              }
            `}</style>
          </div>
        )}
      </div>
    </>
  );
};

export default PromoSlider;
