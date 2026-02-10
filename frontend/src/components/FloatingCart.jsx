import React, { useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { X, Plus, Minus, Trash2, ShoppingBag, ArrowRight } from 'lucide-react';
import { useCart } from '../context/CartContext';

const FloatingCart = ({ isOpen, onClose, onInteraction }) => {
  const { items, totalItems, subtotal, updateQuantity, removeItem } = useCart();
  const panelRef = useRef(null);

  // Cerrar al hacer click fuera
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) {
        onClose();
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen, onClose]);

  // Bloquear scroll del body
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(price);
  };

  return (
    <>
      {/* Overlay */}
      <div
        style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.4)',
          zIndex: 998, opacity: isOpen ? 1 : 0,
          pointerEvents: isOpen ? 'auto' : 'none',
          transition: 'opacity 0.3s ease',
        }}
      />

      {/* Panel */}
      <div
        ref={panelRef}
        onMouseEnter={onInteraction}
        style={{
          position: 'fixed', top: 0, right: 0, bottom: 0,
          width: '380px', maxWidth: '90vw',
          background: '#fff', zIndex: 999,
          transform: isOpen ? 'translateX(0)' : 'translateX(100%)',
          transition: 'transform 0.35s cubic-bezier(0.4,0,0.2,1)',
          display: 'flex', flexDirection: 'column',
          boxShadow: '-8px 0 30px rgba(0,0,0,0.15)',
          fontFamily: 'Poppins, sans-serif',
        }}
      >
        {/* Header del carrito */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '20px 24px', borderBottom: '1px solid #f0f0f0',
          background: '#fff',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <ShoppingBag size={22} color="#00A8E8" />
            <span style={{ fontSize: '18px', fontWeight: 700, color: '#1f2937' }}>
              Mi Carrito
            </span>
            {totalItems > 0 && (
              <span style={{
                background: '#FFC400', color: '#1f2937', fontSize: '12px', fontWeight: 700,
                borderRadius: '20px', padding: '2px 10px', minWidth: '24px', textAlign: 'center',
              }}>
                {totalItems}
              </span>
            )}
          </div>
          <button
            onClick={onClose}
            style={{
              background: '#f3f4f6', border: 'none', borderRadius: '50%',
              width: '36px', height: '36px', display: 'flex', alignItems: 'center',
              justifyContent: 'center', cursor: 'pointer', transition: 'background 0.2s',
            }}
            onMouseEnter={(e) => e.currentTarget.style.background = '#e5e7eb'}
            onMouseLeave={(e) => e.currentTarget.style.background = '#f3f4f6'}
          >
            <X size={18} color="#6b7280" />
          </button>
        </div>

        {/* Lista de productos */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '16px 24px' }}>
          {items.length === 0 ? (
            <div style={{
              display: 'flex', flexDirection: 'column', alignItems: 'center',
              justifyContent: 'center', height: '100%', gap: '16px', color: '#9ca3af',
            }}>
              <ShoppingBag size={56} strokeWidth={1.2} />
              <p style={{ fontSize: '16px', fontWeight: 600, margin: 0, color: '#6b7280' }}>
                Tu carrito está vacío
              </p>
              <p style={{ fontSize: '13px', margin: 0, textAlign: 'center' }}>
                Agrega productos y aparecerán aquí
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              {items.map((item) => (
                <div
                  key={item.id}
                  style={{
                    display: 'flex', gap: '14px', padding: '14px',
                    background: '#f9fafb', borderRadius: '14px',
                    border: '1px solid #f0f0f0', transition: 'box-shadow 0.2s',
                  }}
                >
                  {/* Imagen */}
                  <div style={{
                    width: '72px', height: '72px', borderRadius: '10px',
                    overflow: 'hidden', flexShrink: 0, background: '#e5e7eb',
                  }}>
                    <img
                      src={item.image || item.image_url || 'https://placehold.co/72x72/e5e7eb/9ca3af?text=🐾'}
                      alt={item.name || item.product_name}
                      style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                      onError={(e) => { e.target.src = 'https://placehold.co/72x72/e5e7eb/9ca3af?text=🐾'; }}
                    />
                  </div>

                  {/* Info */}
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <p style={{
                      fontSize: '13px', fontWeight: 600, color: '#1f2937',
                      margin: '0 0 4px 0', overflow: 'hidden', textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                    }}>
                      {item.name || item.product_name}
                    </p>
                    <p style={{ fontSize: '15px', fontWeight: 700, color: '#00A8E8', margin: '0 0 10px 0' }}>
                      {formatPrice(item.price)}
                    </p>

                    {/* Controles de cantidad */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <button
                        onClick={() => updateQuantity(item.id, item.quantity - 1)}
                        style={{
                          width: '28px', height: '28px', borderRadius: '8px',
                          border: '1px solid #d1d5db', background: '#fff',
                          display: 'flex', alignItems: 'center', justifyContent: 'center',
                          cursor: 'pointer', transition: 'all 0.15s',
                        }}
                      >
                        <Minus size={14} color="#6b7280" />
                      </button>
                      <span style={{ fontSize: '14px', fontWeight: 600, color: '#1f2937', minWidth: '20px', textAlign: 'center' }}>
                        {item.quantity}
                      </span>
                      <button
                        onClick={() => updateQuantity(item.id, item.quantity + 1)}
                        style={{
                          width: '28px', height: '28px', borderRadius: '8px',
                          border: '1px solid #d1d5db', background: '#fff',
                          display: 'flex', alignItems: 'center', justifyContent: 'center',
                          cursor: 'pointer', transition: 'all 0.15s',
                        }}
                      >
                        <Plus size={14} color="#6b7280" />
                      </button>
                      <button
                        onClick={() => removeItem(item.id)}
                        style={{
                          marginLeft: 'auto', width: '28px', height: '28px', borderRadius: '8px',
                          border: 'none', background: '#fef2f2',
                          display: 'flex', alignItems: 'center', justifyContent: 'center',
                          cursor: 'pointer', transition: 'all 0.15s',
                        }}
                      >
                        <Trash2 size={14} color="#ef4444" />
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer del carrito */}
        {items.length > 0 && (
          <div style={{
            padding: '20px 24px', borderTop: '1px solid #f0f0f0',
            background: '#fff',
          }}>
            {/* Subtotal */}
            <div style={{
              display: 'flex', justifyContent: 'space-between', alignItems: 'center',
              marginBottom: '8px',
            }}>
              <span style={{ fontSize: '14px', color: '#6b7280' }}>Subtotal</span>
              <span style={{ fontSize: '14px', fontWeight: 600, color: '#1f2937' }}>
                {formatPrice(subtotal)}
              </span>
            </div>
            <div style={{
              display: 'flex', justifyContent: 'space-between', alignItems: 'center',
              marginBottom: '16px',
            }}>
              <span style={{ fontSize: '12px', color: '#9ca3af' }}>Envío se calcula al finalizar</span>
            </div>

            {/* Botón ir al carrito */}
            <Link
              to="/carrito"
              onClick={onClose}
              style={{
                display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px',
                width: '100%', padding: '14px', background: '#00A8E8', color: '#fff',
                borderRadius: '12px', fontSize: '15px', fontWeight: 700,
                textDecoration: 'none', transition: 'background 0.2s', textTransform: 'uppercase',
                letterSpacing: '0.5px',
              }}
              onMouseEnter={(e) => e.currentTarget.style.background = '#0090c7'}
              onMouseLeave={(e) => e.currentTarget.style.background = '#00A8E8'}
            >
              Ir al Carrito
              <ArrowRight size={18} />
            </Link>

            {/* Seguir comprando */}
            <button
              onClick={onClose}
              style={{
                width: '100%', padding: '12px', marginTop: '10px',
                background: 'transparent', border: '1.5px solid #e5e7eb',
                borderRadius: '12px', fontSize: '13px', fontWeight: 600,
                color: '#6b7280', cursor: 'pointer', transition: 'all 0.2s',
              }}
              onMouseEnter={(e) => { e.currentTarget.style.borderColor = '#00A8E8'; e.currentTarget.style.color = '#00A8E8'; }}
              onMouseLeave={(e) => { e.currentTarget.style.borderColor = '#e5e7eb'; e.currentTarget.style.color = '#6b7280'; }}
            >
              Seguir comprando
            </button>
          </div>
        )}
      </div>
    </>
  );
};

export default FloatingCart;
