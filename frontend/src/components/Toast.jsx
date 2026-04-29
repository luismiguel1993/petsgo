import { useState, useEffect, useCallback, createContext, useContext } from 'react';

const ToastContext = createContext(null);

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast debe usarse dentro de ToastProvider');
  return ctx;
}

const ICONS = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
const COLORS = {
  success: { bg: 'linear-gradient(135deg, #065f46, #047857)', border: '#10b981' },
  error:   { bg: 'linear-gradient(135deg, #7f1d1d, #991b1b)', border: '#ef4444' },
  warning: { bg: 'linear-gradient(135deg, #78350f, #92400e)', border: '#f59e0b' },
  info:    { bg: 'linear-gradient(135deg, #1e3a5f, #1e40af)', border: '#3b82f6' },
};

let _id = 0;

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const toast = useCallback((message, type = 'info', duration = 4000) => {
    const id = ++_id;
    setToasts(prev => [...prev, { id, message, type, exiting: false }]);
    if (duration > 0) {
      setTimeout(() => {
        setToasts(prev => prev.map(t => t.id === id ? { ...t, exiting: true } : t));
        setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 350);
      }, duration);
    }
  }, []);

  // Exponer globalmente para componentes que no usan el hook (AdminDashboard usa window.PG.toast)
  useEffect(() => {
    window.PG = window.PG || {};
    window.PG.toast = toast;
    return () => { if (window.PG) delete window.PG.toast; };
  }, [toast]);

  return (
    <ToastContext.Provider value={toast}>
      {children}
      {/* Toast container */}
      <div style={{
        position: 'fixed', top: '80px', right: '20px', zIndex: 99999,
        display: 'flex', flexDirection: 'column', gap: '10px',
        pointerEvents: 'none', maxWidth: '420px', width: '100%',
      }}>
        {toasts.map(t => {
          const c = COLORS[t.type] || COLORS.info;
          return (
            <div key={t.id} style={{
              background: c.bg, color: '#fff',
              padding: '14px 20px', borderRadius: '14px',
              borderLeft: `4px solid ${c.border}`,
              boxShadow: '0 8px 32px rgba(0,0,0,0.3)',
              fontFamily: 'Poppins, sans-serif', fontSize: '14px', fontWeight: 500,
              display: 'flex', alignItems: 'flex-start', gap: '10px',
              animation: t.exiting ? 'pgToastOut 0.35s ease-in forwards' : 'pgToastIn 0.35s ease-out',
              pointerEvents: 'auto',
            }}>
              <span style={{ fontSize: '18px', flexShrink: 0, marginTop: '1px' }}>{ICONS[t.type] || ICONS.info}</span>
              <span style={{ lineHeight: 1.5 }}>{t.message}</span>
            </div>
          );
        })}
      </div>
      <style>{`
        @keyframes pgToastIn {
          0% { opacity: 0; transform: translateX(60px); }
          100% { opacity: 1; transform: translateX(0); }
        }
        @keyframes pgToastOut {
          0% { opacity: 1; transform: translateX(0); }
          100% { opacity: 0; transform: translateX(60px); }
        }
      `}</style>
    </ToastContext.Provider>
  );
}
