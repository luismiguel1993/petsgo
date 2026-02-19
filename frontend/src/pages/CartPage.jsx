import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Minus, Plus, Trash2, ShoppingCart, ArrowLeft, Truck, Shield, Store, MapPin, Tag, X, CheckCircle, Package, CreditCard, Home, Building2, Building, Search, ChevronDown } from 'lucide-react';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import { useSite } from '../context/SiteContext';
import { createOrder, validateCoupon, calculateDeliveryFee, getProductDetail } from '../services/api';
import CHILE_REGIONS from '../data/chileRegions';

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

/* ── Modal de Confirmación de Pedido (extracted for empty-cart render) ── */
const OrderConfirmModal = ({ orderConfirm, setOrderConfirm, navigate, formatPrice, getProductImageFn }) => (
  <>
    <div style={{
      position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', zIndex: 9999,
      display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px',
      backdropFilter: 'blur(4px)', animation: 'fadeIn 0.3s ease'
    }}>
      <div style={{
        background: '#fff', borderRadius: '20px', maxWidth: '560px', width: '100%',
        maxHeight: '90vh', overflowY: 'auto', boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
        animation: 'slideUp 0.4s ease'
      }}>
        {/* Header */}
        <div style={{
          background: 'linear-gradient(135deg, #00A8E8 0%, #0090c7 100%)',
          borderRadius: '20px 20px 0 0', padding: '28px 24px', textAlign: 'center', color: '#fff'
        }}>
          <div style={{
            width: '64px', height: '64px', background: 'rgba(255,255,255,0.2)',
            borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center',
            margin: '0 auto 12px', backdropFilter: 'blur(10px)'
          }}>
            <CheckCircle size={36} color="#fff" />
          </div>
          <h2 style={{ margin: 0, fontSize: '22px', fontWeight: 800 }}>¡Pedido Confirmado!</h2>
          <p style={{ margin: '6px 0 0', fontSize: '14px', opacity: 0.9 }}>
            {orderConfirm.method === 'pickup' ? '📦 Retiro en tienda' : '🚚 Envío a domicilio'}
            {' · '}
            {orderConfirm.isTestUser ? '🧪 Pago simulado' :
              orderConfirm.paymentMethod === 'transbank' ? '💳 Transbank' :
              orderConfirm.paymentMethod === 'mercadopago' ? '💳 Mercado Pago' : '💳 Pago'}
          </p>
        </div>

        {/* Order IDs */}
        <div style={{ padding: '20px 24px 0' }}>
          {orderConfirm.orders.map((o, idx) => (
            <div key={idx} style={{
              display: 'flex', alignItems: 'center', gap: '10px', padding: '10px 14px',
              background: '#f0faff', borderRadius: '10px', marginBottom: '8px', border: '1px solid #d0ecf9'
            }}>
              <Package size={18} color="#00A8E8" />
              <div style={{ flex: 1 }}>
                <span style={{ fontWeight: 700, color: '#00A8E8', fontSize: '15px' }}>Pedido #{o.order_id}</span>
                <span style={{ color: '#6b7280', fontSize: '12px', marginLeft: '8px' }}>{o.store_name}</span>
              </div>
              <span style={{
                background: o.payment_status === 'paid' ? '#d4edda' : '#fff3cd',
                color: o.payment_status === 'paid' ? '#155724' : '#856404',
                padding: '2px 10px',
                borderRadius: '10px', fontSize: '11px', fontWeight: 700
              }}>{o.payment_status === 'paid' ? '✅ Pagado' : '⏳ Pendiente pago'}</span>
            </div>
          ))}
        </div>

        {/* Items detail */}
        <div style={{ padding: '16px 24px' }}>
          <h4 style={{ margin: '0 0 12px', fontSize: '13px', color: '#6b7280', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.5px' }}>
            Detalle de productos
          </h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            {orderConfirm.items.map((item, idx) => (
              <div key={idx} style={{
                display: 'flex', alignItems: 'center', gap: '12px', padding: '10px',
                background: '#fafafa', borderRadius: '10px', border: '1px solid #f3f4f6'
              }}>
                <img src={getProductImageFn(item)} alt="" style={{
                  width: '44px', height: '44px', borderRadius: '8px', objectFit: 'cover',
                  border: '1px solid #eee'
                }} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontWeight: 600, fontSize: '13px', color: '#1f2937', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {item.product_name || item.name}
                  </div>
                  <div style={{ fontSize: '11px', color: '#9ca3af' }}>
                    {item.store_name} · x{item.quantity}
                  </div>
                </div>
                <span style={{ fontWeight: 700, color: '#1f2937', fontSize: '14px', whiteSpace: 'nowrap' }}>
                  {formatPrice(parseFloat(item.price) * item.quantity)}
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* Totals */}
        <div style={{ padding: '0 24px 20px' }}>
          <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: '16px', display: 'flex', flexDirection: 'column', gap: '8px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px', color: '#6b7280' }}>
              <span>Subtotal ({orderConfirm.items.reduce((s, i) => s + i.quantity, 0)} productos)</span>
              <span>{formatPrice(orderConfirm.subtotal)}</span>
            </div>
            {orderConfirm.discount > 0 && (
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px', color: '#16a34a', fontWeight: 600 }}>
                <span>🏷️ Descuento ({orderConfirm.coupon?.code})</span>
                <span>-{formatPrice(orderConfirm.discount)}</span>
              </div>
            )}
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px', color: '#6b7280' }}>
              <span>Envío</span>
              <span style={{ color: orderConfirm.shipping === 0 ? '#16a34a' : '#1f2937', fontWeight: orderConfirm.shipping === 0 ? 700 : 400 }}>
                {orderConfirm.shipping === 0 ? '¡Gratis!' : formatPrice(orderConfirm.shipping)}
              </span>
            </div>
            <div style={{ borderTop: '2px solid #e5e7eb', paddingTop: '12px', marginTop: '4px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ fontWeight: 800, fontSize: '16px', color: '#1f2937' }}>Total pagado</span>
              <span style={{ fontWeight: 800, fontSize: '24px', color: '#00A8E8' }}>{formatPrice(orderConfirm.total)}</span>
            </div>
          </div>
        </div>

        {/* Delivery info */}
        {orderConfirm.method === 'pickup' ? (
          <div style={{ margin: '0 24px 20px', padding: '12px 16px', background: '#FFF9E6', borderRadius: '10px', border: '1px solid #FFF3CD', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <Store size={18} color="#856404" />
            <span style={{ fontSize: '13px', color: '#856404', fontWeight: 500 }}>Recoge tu pedido directamente en la tienda</span>
          </div>
        ) : (
          <div style={{ margin: '0 24px 20px' }}>
            <div style={{ padding: '12px 16px', background: '#f0faff', borderRadius: '10px', border: '1px solid #d0ecf9' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }}>
                <Truck size={16} color="#00A8E8" />
                <span style={{ fontSize: '13px', color: '#0077b6', fontWeight: 600 }}>Envío a domicilio</span>
              </div>
              {orderConfirm.address && (
                <div style={{ fontSize: '12px', color: '#6b7280', lineHeight: 1.5 }}>
                  <div>📍 {orderConfirm.address.street}</div>
                  {orderConfirm.address.detail && <div>🏢 {orderConfirm.address.detail}</div>}
                  <div>📌 {orderConfirm.address.comuna}, {orderConfirm.address.region}</div>
                  <div>🏠 {orderConfirm.address.type === 'departamento' ? 'Departamento' : orderConfirm.address.type === 'oficina' ? 'Oficina' : 'Casa'}</div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Actions */}
        <div style={{ padding: '0 24px 24px', display: 'flex', gap: '10px' }}>
          <button
            onClick={() => { setOrderConfirm(null); navigate('/mis-pedidos'); }}
            style={{
              flex: 1, padding: '13px', background: '#00A8E8', color: '#fff', border: 'none',
              borderRadius: '12px', fontWeight: 700, fontSize: '14px', cursor: 'pointer',
              boxShadow: '0 4px 14px rgba(0,168,232,0.3)', transition: 'all 0.2s'
            }}
          >
            📋 Ver mis pedidos
          </button>
          <button
            onClick={() => { setOrderConfirm(null); navigate('/'); }}
            style={{
              flex: 1, padding: '13px', background: '#f3f4f6', color: '#374151', border: 'none',
              borderRadius: '12px', fontWeight: 600, fontSize: '14px', cursor: 'pointer', transition: 'all 0.2s'
            }}
          >
            🛍️ Seguir comprando
          </button>
        </div>

        {/* Footer note */}
        <div style={{ padding: '16px 24px', background: '#fafafa', borderRadius: '0 0 20px 20px', textAlign: 'center' }}>
          <p style={{ margin: 0, fontSize: '12px', color: '#9ca3af' }}>
            🐾 ¡Gracias por comprar en PetsGo! Recibirás actualizaciones sobre tu pedido.
          </p>
        </div>
      </div>
    </div>
    <style>{`
      @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
      @keyframes slideUp { from { opacity: 0; transform: translateY(40px) } to { opacity: 1; transform: translateY(0) } }
    `}</style>
  </>
);

const CartPage = () => {
  const { items, updateQuantity, removeItem, clearCart, subtotal, totalItems, appliedCoupon, setAppliedCoupon, discountAmount } = useCart();
  const { isAuthenticated, user } = useAuth();
  const site = useSite();
  const navigate = useNavigate();
  const [deliveryMethod, setDeliveryMethod] = useState('delivery');
  const [ordering, setOrdering] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [couponLoading, setCouponLoading] = useState(false);
  const [couponError, setCouponError] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('');
  const [orderConfirm, setOrderConfirm] = useState(null);

  // ── Detailed address state ──
  const [addressRegion, setAddressRegion] = useState('');
  const [addressComuna, setAddressComuna] = useState('');
  const [addressStreet, setAddressStreet] = useState('');
  const [addressDetail, setAddressDetail] = useState(''); // Torre/Bloque/Depto
  const [addressType, setAddressType] = useState('casa'); // casa | departamento | oficina
  const [addressSuggestions, setAddressSuggestions] = useState([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [searchingAddress, setSearchingAddress] = useState(false);
  const suggestionsRef = useRef(null);
  const searchTimerRef = useRef(null);

  // ── Shipping cost state ──
  const [calculatedShipping, setCalculatedShipping] = useState(null); // null = not calculated yet
  const [shippingLoading, setShippingLoading] = useState(false);
  const [deliveryDistanceKm, setDeliveryDistanceKm] = useState(0);

  const isTestUser = user?.email?.toLowerCase() === 'lmgm.0303@gmail.com';
  const freeShippingMin = site?.free_shipping_min || 39990;

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  const isPickup = deliveryMethod === 'pickup';
  const isFreeShipping = !isPickup && subtotal >= freeShippingMin;
  const shippingCost = isPickup ? 0 : (isFreeShipping ? 0 : (calculatedShipping ?? (site?.delivery_standard_cost || 2990)));
  const total = subtotal - discountAmount + shippingCost;

  // Comunas for selected region
  const selectedRegion = CHILE_REGIONS.find(r => r.name === addressRegion);
  const comunasList = selectedRegion?.comunas || [];

  // Full address string for order
  const fullShippingAddress = [addressStreet, addressDetail, addressComuna, addressRegion].filter(Boolean).join(', ');
  const addressComplete = addressRegion && addressComuna && addressStreet.trim();

  // ── Nominatim (OpenStreetMap) autocomplete ──
  const searchAddress = useCallback(async (query) => {
    if (query.length < 4) { setAddressSuggestions([]); return; }
    setSearchingAddress(true);
    try {
      const comuna = addressComuna || '';
      const region = addressRegion || '';
      const searchQuery = `${query}, ${comuna}, ${region}, Chile`;
      const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&countrycodes=cl&limit=5&addressdetails=1`;
      const res = await fetch(url, { headers: { 'Accept-Language': 'es' } });
      const data = await res.json();
      setAddressSuggestions(data.map(d => ({
        display: d.display_name.replace(', Chile', ''),
        lat: parseFloat(d.lat),
        lon: parseFloat(d.lon),
        road: d.address?.road || d.address?.pedestrian || d.address?.footway || '',
        houseNumber: d.address?.house_number || '',
      })));
      setShowSuggestions(true);
    } catch { setAddressSuggestions([]); }
    finally { setSearchingAddress(false); }
  }, [addressComuna, addressRegion]);

  const handleStreetChange = (e) => {
    const val = e.target.value;
    setAddressStreet(val);
    clearTimeout(searchTimerRef.current);
    if (val.length >= 4) {
      searchTimerRef.current = setTimeout(() => searchAddress(val), 500);
    } else {
      setAddressSuggestions([]);
      setShowSuggestions(false);
    }
  };

  const selectSuggestion = (s) => {
    const streetText = s.houseNumber ? `${s.road} ${s.houseNumber}` : (s.road || s.display.split(',')[0]);
    setAddressStreet(streetText);
    setShowSuggestions(false);
    setAddressSuggestions([]);
    // Calculate distance-based shipping
    calculateShipping(s.lat, s.lon);
  };

  // Close suggestions on outside click
  useEffect(() => {
    const handler = (e) => {
      if (suggestionsRef.current && !suggestionsRef.current.contains(e.target)) setShowSuggestions(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // ── Calculate shipping fee from API ──
  const calculateShipping = async (lat, lon) => {
    if (isFreeShipping) { setCalculatedShipping(0); return; }
    setShippingLoading(true);
    try {
      // Approximate distance: PetsGo HQ in Santiago center (-33.4489, -70.6693)
      const baseLat = -33.4489, baseLon = -70.6693;
      const R = 6371;
      const dLat = (lat - baseLat) * Math.PI / 180;
      const dLon = (lon - baseLon) * Math.PI / 180;
      const a = Math.sin(dLat / 2) ** 2 + Math.cos(baseLat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
      const dist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      setDeliveryDistanceKm(Math.round(dist * 10) / 10);
      const { data } = await calculateDeliveryFee(dist);
      setCalculatedShipping(data.delivery_fee);
    } catch {
      setCalculatedShipping(site?.delivery_standard_cost || 2990);
    } finally { setShippingLoading(false); }
  };

  // Reset shipping when switching to pickup
  useEffect(() => {
    if (isPickup) { setCalculatedShipping(null); setDeliveryDistanceKm(0); }
  }, [isPickup]);

  // Recalculate free shipping when subtotal changes
  useEffect(() => {
    if (!isPickup && subtotal >= freeShippingMin) setCalculatedShipping(0);
  }, [subtotal, freeShippingMin, isPickup]);

  const vendorIds = [...new Set(items.map(i => i.vendor_id).filter(Boolean))];

  const handleApplyCoupon = async () => {
    if (!couponCode.trim()) return;
    setCouponLoading(true);
    setCouponError('');
    try {
      const { data } = await validateCoupon(couponCode.trim(), vendorIds, subtotal);
      setAppliedCoupon(data);
      setCouponCode('');
    } catch (err) {
      setCouponError(err.response?.data?.message || 'Cupón inválido');
      setAppliedCoupon(null);
    } finally {
      setCouponLoading(false);
    }
  };

  const handleRemoveCoupon = () => {
    setAppliedCoupon(null);
    setCouponError('');
  };

  if (items.length === 0 && !orderConfirm) {
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

  /* ── If cart was cleared but confirmation modal is pending, render only the modal ── */
  if (items.length === 0 && orderConfirm) {
    return (
      <OrderConfirmModal
        orderConfirm={orderConfirm}
        setOrderConfirm={setOrderConfirm}
        navigate={navigate}
        formatPrice={formatPrice}
        getProductImageFn={getProductImage}
      />
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
            
            {/* Método de entrega */}
            <div style={{ marginBottom: '16px' }}>
              <p style={{ fontSize: '13px', fontWeight: 700, color: '#374151', marginBottom: '8px' }}>Método de entrega</p>
              <div style={{ display: 'flex', gap: '8px' }}>
                <button onClick={() => setDeliveryMethod('delivery')} style={{
                  flex: 1, padding: '10px', borderRadius: '10px', fontWeight: 700, fontSize: '13px',
                  border: deliveryMethod === 'delivery' ? '2px solid #00A8E8' : '2px solid #e5e7eb',
                  background: deliveryMethod === 'delivery' ? '#EBF8FF' : '#fff',
                  color: deliveryMethod === 'delivery' ? '#00A8E8' : '#6b7280',
                  cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                  transition: 'all 0.2s',
                }}>
                  <Truck size={16} /> Despacho
                </button>
                <button onClick={() => setDeliveryMethod('pickup')} style={{
                  flex: 1, padding: '10px', borderRadius: '10px', fontWeight: 700, fontSize: '13px',
                  border: deliveryMethod === 'pickup' ? '2px solid #22C55E' : '2px solid #e5e7eb',
                  background: deliveryMethod === 'pickup' ? '#F0FDF4' : '#fff',
                  color: deliveryMethod === 'pickup' ? '#22C55E' : '#6b7280',
                  cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                  transition: 'all 0.2s',
                }}>
                  <Store size={16} /> Retiro en tienda
                </button>
              </div>
              {isPickup && (
                <div style={{ marginTop: '8px', padding: '8px 12px', background: '#F0FDF4', borderRadius: '8px', fontSize: '12px', color: '#16a34a', fontWeight: 600 }}>
                  🎉 ¡Retiro gratis! Recoge tu pedido en la tienda.
                </div>
              )}
            </div>

            {/* Dirección de envío — Formulario detallado */}
            {!isPickup && (
              <div style={{ marginBottom: '16px' }}>
                <label style={{ fontSize: '13px', fontWeight: 700, color: '#374151', display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '10px' }}>
                  <MapPin size={14} /> Dirección de envío
                </label>

                {/* Tipo de dirección */}
                <div style={{ display: 'flex', gap: '6px', marginBottom: '10px' }}>
                  {[
                    { key: 'casa', label: 'Casa', icon: Home },
                    { key: 'departamento', label: 'Depto', icon: Building2 },
                    { key: 'oficina', label: 'Oficina', icon: Building },
                  ].map(({ key, label, icon: Icon }) => (
                    <button key={key} onClick={() => setAddressType(key)} style={{
                      flex: 1, padding: '8px 6px', borderRadius: '8px', fontSize: '11px', fontWeight: 700,
                      border: addressType === key ? '2px solid #00A8E8' : '2px solid #e5e7eb',
                      background: addressType === key ? '#EBF8FF' : '#fff',
                      color: addressType === key ? '#00A8E8' : '#6b7280',
                      cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '4px',
                      transition: 'all 0.2s',
                    }}>
                      <Icon size={13} /> {label}
                    </button>
                  ))}
                </div>

                {/* Región */}
                <div style={{ position: 'relative', marginBottom: '8px' }}>
                  <select
                    value={addressRegion}
                    onChange={(e) => { setAddressRegion(e.target.value); setAddressComuna(''); }}
                    style={{
                      width: '100%', padding: '10px 32px 10px 12px', borderRadius: '10px', border: '1px solid #e5e7eb',
                      fontSize: '13px', outline: 'none', boxSizing: 'border-box', background: '#fff',
                      color: addressRegion ? '#1f2937' : '#9ca3af', appearance: 'none', cursor: 'pointer',
                    }}
                  >
                    <option value="">Selecciona región</option>
                    {CHILE_REGIONS.map(r => <option key={r.name} value={r.name}>{r.name}</option>)}
                  </select>
                  <ChevronDown size={16} color="#9ca3af" style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }} />
                </div>

                {/* Comuna */}
                <div style={{ position: 'relative', marginBottom: '8px' }}>
                  <select
                    value={addressComuna}
                    onChange={(e) => setAddressComuna(e.target.value)}
                    disabled={!addressRegion}
                    style={{
                      width: '100%', padding: '10px 32px 10px 12px', borderRadius: '10px',
                      border: '1px solid #e5e7eb', fontSize: '13px', outline: 'none', boxSizing: 'border-box',
                      background: !addressRegion ? '#f9fafb' : '#fff',
                      color: addressComuna ? '#1f2937' : '#9ca3af', appearance: 'none',
                      cursor: addressRegion ? 'pointer' : 'not-allowed',
                    }}
                  >
                    <option value="">Selecciona comuna</option>
                    {comunasList.map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                  <ChevronDown size={16} color="#9ca3af" style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }} />
                </div>

                {/* Calle / Dirección con autocompletado */}
                <div style={{ position: 'relative', marginBottom: '8px' }} ref={suggestionsRef}>
                  <div style={{ position: 'relative' }}>
                    <input
                      type="text"
                      value={addressStreet}
                      onChange={handleStreetChange}
                      placeholder="Calle y número (ej: Av. Providencia 1234)"
                      disabled={!addressComuna}
                      style={{
                        width: '100%', padding: '10px 36px 10px 12px', borderRadius: '10px',
                        border: '1px solid #e5e7eb', fontSize: '13px', outline: 'none', boxSizing: 'border-box',
                        background: !addressComuna ? '#f9fafb' : '#fff', transition: 'border 0.2s',
                      }}
                      onFocus={(e) => { e.target.style.borderColor = '#00A8E8'; if (addressSuggestions.length) setShowSuggestions(true); }}
                      onBlur={(e) => { e.target.style.borderColor = '#e5e7eb'; }}
                    />
                    {searchingAddress ? (
                      <span style={{ position: 'absolute', right: '10px', top: '50%', transform: 'translateY(-50%)', fontSize: '12px', color: '#9ca3af' }}>⏳</span>
                    ) : (
                      <Search size={14} color="#9ca3af" style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)' }} />
                    )}
                  </div>
                  {/* Suggestions dropdown */}
                  {showSuggestions && addressSuggestions.length > 0 && (
                    <div style={{
                      position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 50,
                      background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
                      boxShadow: '0 8px 24px rgba(0,0,0,0.12)', marginTop: '4px', maxHeight: '200px', overflowY: 'auto'
                    }}>
                      {addressSuggestions.map((s, idx) => (
                        <button
                          key={idx}
                          onMouseDown={(e) => { e.preventDefault(); selectSuggestion(s); }}
                          style={{
                            width: '100%', padding: '10px 14px', border: 'none', background: 'none',
                            fontSize: '12px', color: '#374151', textAlign: 'left', cursor: 'pointer',
                            borderBottom: idx < addressSuggestions.length - 1 ? '1px solid #f3f4f6' : 'none',
                            display: 'flex', alignItems: 'center', gap: '8px', transition: 'background 0.15s',
                          }}
                          onMouseEnter={(e) => e.currentTarget.style.background = '#f0faff'}
                          onMouseLeave={(e) => e.currentTarget.style.background = 'none'}
                        >
                          <MapPin size={12} color="#00A8E8" style={{ flexShrink: 0 }} />
                          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.display}</span>
                        </button>
                      ))}
                      <div style={{ padding: '4px 14px 6px', fontSize: '10px', color: '#c4c4c4', textAlign: 'right' }}>
                        © OpenStreetMap
                      </div>
                    </div>
                  )}
                </div>

                {/* Detalle: Torre/Bloque/Depto */}
                <input
                  type="text"
                  value={addressDetail}
                  onChange={(e) => setAddressDetail(e.target.value)}
                  placeholder={addressType === 'departamento' ? 'Torre / Bloque / Nº Depto (ej: Torre B, Depto 502)' : addressType === 'oficina' ? 'Piso / Oficina (ej: Piso 3, Of 301)' : 'Referencia adicional (opcional)'}
                  style={{
                    width: '100%', padding: '10px 12px', borderRadius: '10px', border: '1px solid #e5e7eb',
                    fontSize: '13px', outline: 'none', boxSizing: 'border-box', transition: 'border 0.2s',
                  }}
                  onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                  onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                />

                {/* Shipping info badges */}
                {isFreeShipping && (
                  <div style={{ marginTop: '8px', padding: '8px 12px', background: '#F0FDF4', borderRadius: '8px', fontSize: '12px', color: '#16a34a', fontWeight: 600, border: '1px solid #bbf7d0' }}>
                    🎉 ¡Envío GRATIS! Tu compra supera {formatPrice(freeShippingMin)}
                  </div>
                )}
                {!isFreeShipping && calculatedShipping !== null && calculatedShipping > 0 && (
                  <div style={{ marginTop: '8px', padding: '8px 12px', background: '#f0faff', borderRadius: '8px', fontSize: '12px', color: '#0077b6', fontWeight: 500, border: '1px solid #d0ecf9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span>🚚 Costo envío calculado ({deliveryDistanceKm} km)</span>
                    <span style={{ fontWeight: 700 }}>{formatPrice(calculatedShipping)}</span>
                  </div>
                )}
                {!isFreeShipping && subtotal > 0 && (
                  <div style={{ marginTop: '6px', fontSize: '11px', color: '#9ca3af' }}>
                    💡 Agrega {formatPrice(freeShippingMin - subtotal)} más para envío gratis
                  </div>
                )}
                {shippingLoading && (
                  <div style={{ marginTop: '6px', fontSize: '11px', color: '#00A8E8', fontWeight: 500 }}>
                    ⏳ Calculando costo de envío...
                  </div>
                )}
              </div>
            )}

            {/* Detalles */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', fontSize: '14px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#6b7280' }}>Subtotal ({totalItems} productos)</span>
                <span style={{ fontWeight: 600, color: '#1f2937' }}>{formatPrice(subtotal)}</span>
              </div>

              {/* Cupón de descuento */}
              <div style={{ borderTop: '1px dashed #e5e7eb', paddingTop: '12px' }}>
                <p style={{ fontSize: '13px', fontWeight: 700, color: '#374151', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                  <Tag size={14} /> Cupón de descuento
                </p>
                {appliedCoupon ? (
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: '#F0FDF4', borderRadius: '8px', padding: '8px 12px' }}>
                    <div>
                      <span style={{ fontWeight: 700, color: '#16a34a', fontSize: '14px' }}>🎉 {appliedCoupon.code}</span>
                      {appliedCoupon.description && <span style={{ color: '#6b7280', fontSize: '12px', marginLeft: '8px' }}>— {appliedCoupon.description}</span>}
                    </div>
                    <button onClick={handleRemoveCoupon} style={{ background: 'none', border: 'none', cursor: 'pointer', padding: '4px', color: '#ef4444' }}>
                      <X size={16} />
                    </button>
                  </div>
                ) : (
                  <div style={{ display: 'flex', gap: '8px' }}>
                    <input
                      type="text"
                      value={couponCode}
                      onChange={(e) => { setCouponCode(e.target.value.toUpperCase()); setCouponError(''); }}
                      placeholder="Código del cupón"
                      onKeyDown={(e) => e.key === 'Enter' && handleApplyCoupon()}
                      style={{
                        flex: 1, padding: '8px 12px', borderRadius: '8px', border: couponError ? '1px solid #ef4444' : '1px solid #e5e7eb',
                        fontSize: '13px', outline: 'none', textTransform: 'uppercase', letterSpacing: '1px',
                        transition: 'border 0.2s',
                      }}
                      onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                      onBlur={(e) => e.target.style.borderColor = couponError ? '#ef4444' : '#e5e7eb'}
                    />
                    <button onClick={handleApplyCoupon} disabled={couponLoading || !couponCode.trim()} style={{
                      padding: '8px 16px', borderRadius: '8px', fontWeight: 700, fontSize: '13px',
                      background: !couponCode.trim() ? '#e5e7eb' : '#00A8E8', color: !couponCode.trim() ? '#9ca3af' : '#fff',
                      border: 'none', cursor: !couponCode.trim() ? 'not-allowed' : 'pointer', transition: 'all 0.2s',
                      whiteSpace: 'nowrap',
                    }}>
                      {couponLoading ? '...' : 'Aplicar'}
                    </button>
                  </div>
                )}
                {couponError && <p style={{ color: '#ef4444', fontSize: '12px', marginTop: '4px' }}>{couponError}</p>}
              </div>

              {discountAmount > 0 && (
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ color: '#16a34a', fontWeight: 600 }}>Descuento ({appliedCoupon?.code})</span>
                  <span style={{ fontWeight: 700, color: '#16a34a' }}>-{formatPrice(discountAmount)}</span>
                </div>
              )}

              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#6b7280' }}>Envío</span>
                <span style={{ color: shippingCost === 0 ? '#16a34a' : '#1f2937', fontWeight: shippingCost === 0 ? 700 : 500 }}>
                  {shippingCost === 0 ? '¡Gratis!' : formatPrice(shippingCost)}
                </span>
              </div>
            </div>

            {/* Divider */}
            <div style={{ borderTop: '1px solid #f3f4f6', margin: '16px 0' }}></div>

            {/* Método de pago */}
            <div style={{ marginBottom: '16px' }}>
              <p style={{ fontSize: '13px', fontWeight: 700, color: '#374151', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                <CreditCard size={14} /> Método de pago
              </p>
              {isTestUser && (
                <div style={{
                  marginBottom: '10px', padding: '8px 12px', background: '#FFF3CD', borderRadius: '8px',
                  fontSize: '12px', color: '#856404', fontWeight: 600, border: '1px solid #FFE69C',
                  display: 'flex', alignItems: 'center', gap: '6px'
                }}>
                  🧪 Modo prueba — El pago se simula automáticamente
                </div>
              )}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {/* Transbank */}
                <button onClick={() => setPaymentMethod('transbank')} style={{
                  width: '100%', padding: '12px 14px', borderRadius: '10px', fontWeight: 600, fontSize: '13px',
                  border: paymentMethod === 'transbank' ? '2px solid #E4002B' : '2px solid #e5e7eb',
                  background: paymentMethod === 'transbank' ? '#FFF0F3' : '#fff',
                  color: paymentMethod === 'transbank' ? '#E4002B' : '#6b7280',
                  cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '10px',
                  transition: 'all 0.2s', textAlign: 'left',
                }}>
                  <span style={{
                    width: '36px', height: '24px', borderRadius: '4px', display: 'flex',
                    alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: 800,
                    background: '#E4002B', color: '#fff', letterSpacing: '-0.5px', flexShrink: 0
                  }}>TBK</span>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: '13px' }}>Transbank</div>
                    <div style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 400 }}>Débito, Crédito, Prepago</div>
                  </div>
                  {paymentMethod === 'transbank' && <CheckCircle size={18} color="#E4002B" style={{ marginLeft: 'auto' }} />}
                </button>

                {/* Mercado Pago */}
                <button onClick={() => setPaymentMethod('mercadopago')} style={{
                  width: '100%', padding: '12px 14px', borderRadius: '10px', fontWeight: 600, fontSize: '13px',
                  border: paymentMethod === 'mercadopago' ? '2px solid #009EE3' : '2px solid #e5e7eb',
                  background: paymentMethod === 'mercadopago' ? '#EBF8FF' : '#fff',
                  color: paymentMethod === 'mercadopago' ? '#009EE3' : '#6b7280',
                  cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '10px',
                  transition: 'all 0.2s', textAlign: 'left',
                }}>
                  <span style={{
                    width: '36px', height: '24px', borderRadius: '4px', display: 'flex',
                    alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: 800,
                    background: '#009EE3', color: '#fff', letterSpacing: '-0.5px', flexShrink: 0
                  }}>MP</span>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: '13px' }}>Mercado Pago</div>
                    <div style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 400 }}>Tarjetas, transferencia, cuotas</div>
                  </div>
                  {paymentMethod === 'mercadopago' && <CheckCircle size={18} color="#009EE3" style={{ marginLeft: 'auto' }} />}
                </button>
              </div>
              {!paymentMethod && !isTestUser && (
                <p style={{ color: '#ef4444', fontSize: '11px', marginTop: '6px', fontWeight: 500 }}>
                  Selecciona un método de pago para continuar
                </p>
              )}
            </div>

            {/* Total */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
              <span style={{ fontWeight: 700, color: '#1f2937', fontSize: '18px' }}>Total</span>
              <span style={{ fontWeight: 800, fontSize: '26px', color: '#00A8E8' }}>
                {formatPrice(total)}
              </span>
            </div>

            {/* Botón principal */}
            {isAuthenticated ? (
              <button 
                disabled={ordering || (!isPickup && !addressComplete) || (!paymentMethod && !isTestUser)}
                onClick={async () => {
                  setOrdering(true);
                  try {
                    // Resolve missing vendor_ids from API before grouping
                    const resolvedItems = await Promise.all(items.map(async (item) => {
                      if (item.vendor_id) return item;
                      try {
                        const { data } = await getProductDetail(item.id);
                        return { ...item, vendor_id: data.vendor_id };
                      } catch { return item; }
                    }));

                    // Group items by vendor_id
                    const byVendor = {};
                    resolvedItems.forEach(i => {
                      const vid = i.vendor_id || 0;
                      if (!byVendor[vid]) byVendor[vid] = [];
                      byVendor[vid].push(i);
                    });

                    const orderResults = [];
                    const savedItems = [...items];
                    const savedSubtotal = subtotal;
                    const savedDiscount = discountAmount;
                    const savedShipping = shippingCost;
                    const savedTotal = total;
                    const savedMethod = deliveryMethod;
                    const savedCoupon = appliedCoupon;
                    const savedPayment = isTestUser ? 'test_bypass' : paymentMethod;

                    // Generate a unique purchase group ID to link all orders from this checkout
                    const purchaseGroup = crypto.randomUUID ? crypto.randomUUID() : `pg-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

                    for (const [vendorId, vendorItems] of Object.entries(byVendor)) {
                      const vendorTotal = vendorItems.reduce((s, i) => s + parseFloat(i.price) * i.quantity, 0);
                      const orderData = {
                        vendor_id: parseInt(vendorId),
                        items: vendorItems.map(i => ({ product_id: i.id, quantity: i.quantity, price: parseFloat(i.price) })),
                        total: vendorTotal,
                        delivery_method: deliveryMethod,
                        delivery_fee: shippingCost,
                        delivery_distance_km: deliveryDistanceKm || 0,
                        shipping_address: isPickup ? '' : fullShippingAddress,
                        shipping_region: isPickup ? '' : addressRegion,
                        shipping_comuna: isPickup ? '' : addressComuna,
                        address_detail: isPickup ? '' : addressDetail,
                        address_type: isPickup ? '' : addressType,
                        coupon_code: savedCoupon?.code || '',
                        payment_method: savedPayment,
                        purchase_group: purchaseGroup,
                      };
                      const { data } = await createOrder(orderData);
                      orderResults.push({
                        order_id: data.order_id,
                        vendor_id: parseInt(vendorId),
                        store_name: vendorItems[0]?.store_name || 'Tienda',
                        items: vendorItems,
                        subtotal: vendorTotal,
                        discount: data.discount_amount || 0,
                        coupon: data.coupon_code || null,
                        payment_status: data.payment_status || 'pending_payment',
                        payment_method: data.payment_method || savedPayment,
                      });
                    }

                    clearCart();
                    setOrderConfirm({
                      orders: orderResults,
                      items: savedItems,
                      subtotal: savedSubtotal,
                      discount: savedDiscount,
                      shipping: savedShipping,
                      total: savedTotal,
                      method: savedMethod,
                      coupon: savedCoupon,
                      paymentMethod: savedPayment,
                      isTestUser,
                      address: isPickup ? null : { street: addressStreet, detail: addressDetail, comuna: addressComuna, region: addressRegion, type: addressType },
                    });
                  } catch (err) {
                    console.error('Error creando orden:', err);
                    const apiMsg = err?.response?.data?.message || err?.message || 'Error desconocido';
                    alert(`Error al procesar tu pedido: ${apiMsg}`);
                  } finally { setOrdering(false); }
                }}
                style={{
                width: '100%', padding: '14px',
                background: ((!isPickup && !addressComplete) || (!paymentMethod && !isTestUser)) ? '#9ca3af' : '#00A8E8',
                color: '#fff',
                fontWeight: 700, border: 'none', borderRadius: '12px', fontSize: '15px',
                cursor: ((!isPickup && !addressComplete) || (!paymentMethod && !isTestUser)) ? 'not-allowed' : 'pointer',
                boxShadow: '0 4px 14px rgba(0,168,232,0.3)',
                transition: 'all 0.3s', opacity: ordering ? 0.7 : 1,
              }}
              >
                {ordering ? '⏳ Procesando...' : isTestUser ? '🧪 Confirmar (Modo Prueba)' : isPickup ? '🏪 Confirmar Retiro' : '💳 Pagar y Confirmar'}
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
                <span>Envío gratis desde {formatPrice(freeShippingMin)}</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '12px', color: '#6b7280' }}>
                <Shield size={16} color="#00A8E8" />
                <span>Compra 100% segura</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ── Modal de Confirmación de Pedido ── */}
      {orderConfirm && (
        <OrderConfirmModal
          orderConfirm={orderConfirm}
          setOrderConfirm={setOrderConfirm}
          navigate={navigate}
          formatPrice={formatPrice}
          getProductImageFn={getProductImage}
        />
      )}
    </div>
  );
};

export default CartPage;
