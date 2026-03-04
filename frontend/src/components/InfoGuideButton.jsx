import React, { useState } from 'react';
import { Info, X } from 'lucide-react';

/**
 * Botón ℹ️ que abre un modal con la guía descriptiva de una sección/módulo.
 *
 * @param {string}  title    - Título del modal (ej: "Dashboard Global")
 * @param {string}  icon     - Emoji del encabezado (ej: "📊")
 * @param {string}  color    - Color principal del módulo (hex)
 * @param {React.ReactNode} children - Contenido HTML/JSX de la guía
 * @param {number}  [size=18] - Tamaño del ícono SVG
 */
export default function InfoGuideButton({ title, icon = '📖', color = '#00A8E8', children, size = 18 }) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        onClick={(e) => { e.stopPropagation(); setOpen(true); }}
        title={`Guía: ${title}`}
        style={{
          border: 'none', background: 'none', cursor: 'pointer', padding: 2,
          opacity: 0.45, transition: 'opacity 0.2s', lineHeight: 0,
        }}
        onMouseEnter={(e) => (e.currentTarget.style.opacity = '1')}
        onMouseLeave={(e) => (e.currentTarget.style.opacity = '0.45')}
      >
        <Info size={size} color={color} />
      </button>

      {open && (
        <div
          onClick={() => setOpen(false)}
          style={{
            position: 'fixed', inset: 0, zIndex: 170000,
            background: 'rgba(0,0,0,0.5)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontFamily: 'Poppins, Segoe UI, sans-serif',
            animation: 'pgGuideIn .25s ease',
          }}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            style={{
              background: '#fff', borderRadius: 16, maxWidth: 520, width: '92%',
              boxShadow: '0 20px 60px rgba(0,0,0,0.3)', overflow: 'hidden',
              animation: 'pgGuideScale .25s ease',
            }}
          >
            {/* Header */}
            <div style={{
              padding: '22px 26px 14px',
              borderBottom: '1px solid #f0f0f0',
              background: color + '10',
              display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span style={{ fontSize: 28 }}>{icon}</span>
                <span style={{ fontSize: 17, fontWeight: 800, color: '#2F3A40' }}>{title}</span>
              </div>
              <button
                onClick={() => setOpen(false)}
                style={{
                  border: 'none', background: 'none', cursor: 'pointer',
                  padding: 4, color: '#9ca3af', lineHeight: 0,
                }}
                title="Cerrar"
              >
                <X size={22} />
              </button>
            </div>

            {/* Body */}
            <div style={{
              padding: '20px 26px 26px',
              maxHeight: '62vh', overflowY: 'auto',
              fontSize: 13, color: '#4b5563', lineHeight: 1.8,
            }}>
              {children}
            </div>
          </div>
        </div>
      )}

      <style>{`
        @keyframes pgGuideIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pgGuideScale { from { transform: scale(0.92); opacity: 0; } to { transform: scale(1); opacity: 1; } }
      `}</style>
    </>
  );
}
