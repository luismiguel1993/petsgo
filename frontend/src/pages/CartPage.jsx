import React from 'react';
import { Link } from 'react-router-dom';
import { Minus, Plus, Trash2, ShoppingCart, ArrowLeft, Truck, Shield } from 'lucide-react';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';

// Imágenes por categoría para productos sin imagen
const CATEGORY_IMAGES = {
  'Alimento': 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=400',
  'Accesorios': 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=400',
  'Juguetes': 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=400',
  'Higiene': 'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?auto=format&fit=crop&q=80&w=400',
  'Ropa': 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&q=80&w=400',
  'Camas': 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?auto=format&fit=crop&q=80&w=400',
  'default': 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=400'
};

const getProductImage = (item) => {
  if (item.image_url || item.image) return item.image_url || item.image;
  return CATEGORY_IMAGES[item.category] || CATEGORY_IMAGES['default'];
};

const CartPage = () => {
  const { items, updateQuantity, removeItem, clearCart, subtotal, totalItems } = useCart();
  const { isAuthenticated } = useAuth();

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  if (items.length === 0) {
    return (
      <div style={{ maxWidth: '500px', margin: '0 auto', padding: '80px 24px', textAlign: 'center' }}>
        <div style={{
          width: '96px', height: '96px', background: '#f3f4f6', borderRadius: '50%',
          display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px'
        }}>
          <ShoppingCart size={40} color="#d1d5db" />
        </div>
        <h2 style={{ fontSize: '24px', fontWeight: 700, color: '#1f2937', marginBottom: '12px' }}>Tu carrito está vacío</h2>
        <p style={{ color: '#6b7280', marginBottom: '32px' }}>¡Agrega productos desde el marketplace!</p>
        <Link 
          to="/" 
          style={{
            display: 'inline-flex', alignItems: 'center', gap: '8px',
            padding: '14px 32px', background: '#00A8E8', color: '#fff',
            fontWeight: 700, borderRadius: '12px', textDecoration: 'none',
            boxShadow: '0 4px 14px rgba(0,168,232,0.3)', transition: 'all 0.3s'
          }}
        >
          <ArrowLeft size={20} />
          Explorar Productos
        </Link>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '32px 24px' }}>
      <style>{`
        @media (max-width: 768px) {
          .cart-layout { grid-template-columns: 1fr !important; }
          .cart-item { flex-direction: column !important; align-items: flex-start !important; }
          .cart-item-actions { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
          .cart-item-img { width: 100% !important; height: 140px !important; }
        }
        @media (max-width: 480px) {
          .cart-container { padding: 16px 12px !important; }
        }
      `}</style>
      {/* Header */}
      <div style={{ marginBottom: '32px' }}>
        <Link to="/" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          color: '#9ca3af', fontSize: '14px', fontWeight: 500,
          textDecoration: 'none', marginBottom: '8px', transition: 'color 0.2s'
        }}
          onMouseEnter={e => e.currentTarget.style.color = '#00A8E8'}
          onMouseLeave={e => e.currentTarget.style.color = '#9ca3af'}
        >
          <ArrowLeft size={16} /> Seguir comprando
        </Link>
        <h1 style={{ fontSize: 'clamp(22px, 3vw, 30px)', fontWeight: 700, color: '#1f2937', marginTop: '4px' }}>
          Mi Carrito <span style={{ color: '#9ca3af', fontSize: '18px', fontWeight: 400 }}>({totalItems} {totalItems === 1 ? 'producto' : 'productos'})</span>
        </h1>
      </div>

      {/* Layout principal */}
      <div className="cart-layout" style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '24px' }}>
        
        {/* Lista de productos */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          {items.map((item) => (
            <div 
              key={item.id} 
              className="cart-item"
              style={{
                background: '#fff', borderRadius: '16px', padding: '16px 20px',
                display: 'flex', gap: '16px', alignItems: 'center',
                boxShadow: '0 2px 10px rgba(0,0,0,0.05)', border: '1px solid #f0f0f0',
                transition: 'box-shadow 0.3s'
              }}
            >
              {/* Imagen */}
              <div className="cart-item-img" style={{ width: '90px', height: '90px', borderRadius: '12px', overflow: 'hidden', background: '#f3f4f6', flexShrink: 0 }}>
                <img
                  src={getProductImage(item)}
                  alt={item.product_name || item.name}
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                  onError={(e) => { e.target.src = CATEGORY_IMAGES['default']; }}
                />
              </div>
              
              {/* Info */}
              <div style={{ flex: 1, minWidth: 0 }}>
                <h3 style={{ fontWeight: 600, color: '#1f2937', marginBottom: '4px', fontSize: '15px' }}>
                  {item.product_name || item.name}
                </h3>
                <p style={{ fontSize: '12px', color: '#9ca3af', marginBottom: '6px' }}>{item.store_name || item.brand || 'PetsGo'}</p>
                <p style={{ fontSize: '20px', fontWeight: 700, color: '#00A8E8' }}>{formatPrice(item.price)}</p>
              </div>

              {/* Controles cantidad */}
              <div style={{ display: 'flex', alignItems: 'center', gap: '12px', background: '#f9fafb', borderRadius: '12px', padding: '6px' }}>
                <button 
                  onClick={() => updateQuantity(item.id, item.quantity - 1)} 
                  style={{
                    width: '36px', height: '36px', borderRadius: '8px', background: '#fff',
                    border: '1px solid #e5e7eb', display: 'flex', alignItems: 'center', justifyContent: 'center',
                    cursor: 'pointer', transition: 'all 0.2s'
                  }}
                >
                  <Minus size={16} color="#6b7280" />
                </button>
                <span style={{ width: '36px', textAlign: 'center', fontWeight: 700, fontSize: '16px', color: '#1f2937' }}>{item.quantity}</span>
                <button 
                  onClick={() => updateQuantity(item.id, item.quantity + 1)} 
                  style={{
                    width: '36px', height: '36px', borderRadius: '8px', background: '#fff',
                    border: '1px solid #e5e7eb', display: 'flex', alignItems: 'center', justifyContent: 'center',
                    cursor: 'pointer', transition: 'all 0.2s'
                  }}
                >
                  <Plus size={16} color="#6b7280" />
                </button>
              </div>

              {/* Eliminar */}
              <button 
                onClick={() => removeItem(item.id)} 
                style={{ background: 'none', border: 'none', padding: '8px', cursor: 'pointer', color: '#d1d5db', borderRadius: '8px', transition: 'all 0.2s' }}
                onMouseEnter={e => { e.currentTarget.style.color = '#ef4444'; e.currentTarget.style.background = '#fef2f2'; }}
                onMouseLeave={e => { e.currentTarget.style.color = '#d1d5db'; e.currentTarget.style.background = 'none'; }}
              >
                <Trash2 size={20} />
              </button>
            </div>
          ))}
        </div>

        {/* Resumen */}
        <div>
          <div style={{
            background: '#fff', borderRadius: '16px', padding: '24px',
            boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
            position: 'sticky', top: '100px'
          }}>
            <h2 style={{ fontWeight: 700, fontSize: '18px', color: '#1f2937', marginBottom: '20px' }}>Resumen del pedido</h2>
            
            {/* Detalles */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', fontSize: '14px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#6b7280' }}>Subtotal ({totalItems} productos)</span>
                <span style={{ fontWeight: 600, color: '#1f2937' }}>{formatPrice(subtotal)}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#6b7280' }}>Envío</span>
                <span style={{ color: '#16a34a', fontWeight: 500 }}>Por calcular</span>
              </div>
            </div>

            {/* Divider */}
            <div style={{ borderTop: '1px solid #f3f4f6', margin: '16px 0' }}></div>

            {/* Total */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
              <span style={{ fontWeight: 700, color: '#1f2937', fontSize: '18px' }}>Total</span>
              <span style={{ fontWeight: 800, fontSize: '26px', color: '#00A8E8' }}>{formatPrice(subtotal)}</span>
            </div>

            {/* Botón principal */}
            {isAuthenticated ? (
              <button style={{
                width: '100%', padding: '14px', background: '#00A8E8', color: '#fff',
                fontWeight: 700, border: 'none', borderRadius: '12px', fontSize: '15px',
                cursor: 'pointer', boxShadow: '0 4px 14px rgba(0,168,232,0.3)',
                transition: 'all 0.3s'
              }}
                onMouseEnter={e => { e.target.style.background = '#0090c7'; }}
                onMouseLeave={e => { e.target.style.background = '#00A8E8'; }}
              >
                Confirmar Pedido
              </button>
            ) : (
              <Link 
                to="/login" 
                style={{
                  display: 'block', width: '100%', padding: '14px', background: '#00A8E8',
                  color: '#fff', fontWeight: 700, border: 'none', borderRadius: '12px',
                  fontSize: '15px', textAlign: 'center', textDecoration: 'none',
                  boxShadow: '0 4px 14px rgba(0,168,232,0.3)', boxSizing: 'border-box'
                }}
              >
                Inicia sesión para comprar
              </Link>
            )}

            {/* Vaciar carrito */}
            <button 
              onClick={clearCart} 
              style={{
                width: '100%', marginTop: '12px', padding: '10px', background: 'none',
                border: 'none', fontSize: '13px', fontWeight: 500, color: '#f87171',
                cursor: 'pointer', borderRadius: '8px', transition: 'all 0.2s'
              }}
              onMouseEnter={e => { e.target.style.background = '#fef2f2'; e.target.style.color = '#ef4444'; }}
              onMouseLeave={e => { e.target.style.background = 'none'; e.target.style.color = '#f87171'; }}
            >
              Vaciar carrito
            </button>

            {/* Beneficios */}
            <div style={{ marginTop: '20px', paddingTop: '16px', borderTop: '1px solid #f3f4f6', display: 'flex', flexDirection: 'column', gap: '12px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '12px', color: '#6b7280' }}>
                <Truck size={16} color="#00A8E8" />
                <span>Envío gratis desde $39.990</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '12px', color: '#6b7280' }}>
                <Shield size={16} color="#00A8E8" />
                <span>Compra 100% segura</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CartPage;
