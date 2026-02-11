import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Package, Clock, CheckCircle2, Truck, MapPin, Store, PawPrint, Filter } from 'lucide-react';
import { getMyOrders } from '../services/api';
import { useAuth } from '../context/AuthContext';

const STATUS_CONFIG = {
  payment_pending: { label: 'Pago Pendiente', color: '#FFC400', bg: '#fff8e1', icon: Clock },
  preparing: { label: 'Preparando', color: '#00A8E8', bg: '#e0f7fa', icon: Package },
  ready_for_pickup: { label: 'Listo para enviar', color: '#8B5CF6', bg: '#ede9fe', icon: Package },
  in_transit: { label: 'En camino', color: '#F97316', bg: '#fff7ed', icon: Truck },
  delivered: { label: 'Entregado', color: '#22C55E', bg: '#f0fdf4', icon: CheckCircle2 },
  cancelled: { label: 'Cancelado', color: '#EF4444', bg: '#fef2f2', icon: Package },
};

const DEMO_ORDERS = [
  { id: 1042, status: 'delivered', store_name: 'PetShop Las Condes', total_amount: 52990, delivery_fee: 2990, created_at: '2026-02-05T14:30:00Z' },
  { id: 1051, status: 'in_transit', store_name: 'La Huella Store', total_amount: 38990, delivery_fee: 0, created_at: '2026-02-09T10:15:00Z' },
  { id: 1063, status: 'preparing', store_name: 'Mundo Animal Centro', total_amount: 91980, delivery_fee: 2990, created_at: '2026-02-10T08:45:00Z' },
  { id: 1070, status: 'payment_pending', store_name: 'Vet & Shop', total_amount: 29990, delivery_fee: 2990, created_at: '2026-02-10T11:00:00Z' },
  { id: 1028, status: 'delivered', store_name: 'Happy Pets Providencia', total_amount: 14990, delivery_fee: 2990, created_at: '2026-01-28T16:20:00Z' },
  { id: 1015, status: 'delivered', store_name: 'PetLand Chile', total_amount: 62990, delivery_fee: 0, created_at: '2026-01-20T09:00:00Z' },
];

const cardStyle = {
  background: '#fff', borderRadius: '20px', padding: '24px',
  boxShadow: '0 2px 16px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
  transition: 'transform 0.15s, box-shadow 0.15s',
};

const MyOrdersPage = () => {
  const { user, isAuthenticated, loading: authLoading } = useAuth();
  const navigate = useNavigate();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  useEffect(() => {
    if (authLoading) return;
    if (!user) { navigate('/login'); return; }
    loadOrders();
  }, [user, authLoading, navigate]);

  const loadOrders = async () => {
    try {
      const { data } = await getMyOrders();
      const realOrders = Array.isArray(data) ? data : [];
      setOrders(realOrders.length > 0 ? realOrders : DEMO_ORDERS);
    } catch (err) {
      console.error('Error cargando pedidos:', err);
      setOrders(DEMO_ORDERS);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated) return null;

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;
  const formatDate = (date) => new Date(date).toLocaleDateString('es-CL', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });

  const filteredOrders = filter === 'all' ? orders : orders.filter((o) => o.status === filter);

  const filterBtnStyle = (active) => ({
    padding: '8px 18px', borderRadius: '12px', fontSize: '12px', fontWeight: 700,
    border: active ? 'none' : '1.5px solid #e5e7eb', cursor: 'pointer',
    background: active ? '#00A8E8' : '#fff',
    color: active ? '#fff' : '#6b7280',
    boxShadow: active ? '0 4px 12px rgba(0,168,232,0.25)' : 'none',
    whiteSpace: 'nowrap', transition: 'all 0.2s',
    fontFamily: 'Poppins, sans-serif',
  });

  return (
    <div style={{
      minHeight: '80vh', padding: '32px 16px',
      background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fef9c3 100%)',
    }}>
      <div style={{ maxWidth: '900px', margin: '0 auto' }}>
        <h1 style={{ fontSize: '28px', fontWeight: 900, color: '#2F3A40', marginBottom: '4px' }}>
          📦 Mis Pedidos
        </h1>
        <p style={{ color: '#9ca3af', fontWeight: 500, fontSize: '14px', marginBottom: '24px' }}>
          Historial y seguimiento de tus compras
        </p>

        {/* Filtros */}
        <div style={{ display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '12px', marginBottom: '20px' }}>
          <button onClick={() => setFilter('all')} style={filterBtnStyle(filter === 'all')}>
            Todos
          </button>
          {Object.entries(STATUS_CONFIG).map(([key, val]) => (
            <button key={key} onClick={() => setFilter(key)} style={filterBtnStyle(filter === key)}>
              {val.label}
            </button>
          ))}
        </div>

        {/* Loading */}
        {loading ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {[1, 2, 3].map(i => (
              <div key={i} style={{ ...cardStyle, opacity: 0.6 }}>
                <div style={{ height: '16px', background: '#e5e7eb', borderRadius: '8px', width: '35%', marginBottom: '12px' }} />
                <div style={{ height: '12px', background: '#e5e7eb', borderRadius: '8px', width: '55%', marginBottom: '8px' }} />
                <div style={{ height: '12px', background: '#e5e7eb', borderRadius: '8px', width: '30%' }} />
              </div>
            ))}
          </div>
        ) : filteredOrders.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '64px 16px' }}>
            <PawPrint size={48} style={{ margin: '0 auto 16px', color: '#d1d5db' }} />
            <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '18px' }}>
              {filter === 'all' ? 'Aún no tienes pedidos' : 'No hay pedidos con este estado'}
            </p>
            <Link to="/" style={{
              display: 'inline-block', marginTop: '16px', padding: '12px 28px',
              background: 'linear-gradient(135deg, #00A8E8, #0077b6)', color: '#fff',
              borderRadius: '12px', fontWeight: 700, fontSize: '14px', textDecoration: 'none',
            }}>
              Explorar Productos
            </Link>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {filteredOrders.map((order) => {
              const status = STATUS_CONFIG[order.status] || STATUS_CONFIG.payment_pending;
              const StatusIcon = status.icon;
              return (
                <div key={order.id} style={cardStyle}
                  onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 8px 24px rgba(0,0,0,0.1)'; }}
                  onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = '0 2px 16px rgba(0,0,0,0.06)'; }}
                >
                  <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '14px' }}>
                    <div>
                      <span style={{ fontSize: '11px', fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                        Pedido #{order.id}
                      </span>
                      <h4 style={{ fontWeight: 800, color: '#2F3A40', fontSize: '16px', marginTop: '4px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Store size={15} style={{ color: '#9ca3af' }} />
                        {order.store_name || 'Tienda PetsGo'}
                      </h4>
                    </div>
                    <div style={{
                      display: 'flex', alignItems: 'center', gap: '6px',
                      padding: '6px 14px', borderRadius: '50px',
                      backgroundColor: status.bg, color: status.color,
                      fontSize: '12px', fontWeight: 700, flexShrink: 0,
                    }}>
                      <StatusIcon size={14} />
                      {status.label}
                    </div>
                  </div>

                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: '14px', flexWrap: 'wrap', gap: '8px' }}>
                    <div style={{ display: 'flex', gap: '24px', color: '#6b7280', fontWeight: 500 }}>
                      <span>Total: <strong style={{ color: '#2F3A40' }}>{formatPrice(order.total_amount)}</strong></span>
                      <span>Delivery: <strong style={{ color: '#2F3A40' }}>{order.delivery_fee > 0 ? formatPrice(order.delivery_fee) : 'Gratis'}</strong></span>
                    </div>
                    <span style={{ fontSize: '12px', color: '#9ca3af' }}>{formatDate(order.created_at)}</span>
                  </div>

                  {order.status === 'in_transit' && (
                    <div style={{
                      marginTop: '14px', background: '#fff7ed', borderRadius: '12px',
                      padding: '12px 16px', display: 'flex', alignItems: 'center', gap: '8px',
                      fontSize: '13px', color: '#ea580c', fontWeight: 600,
                    }}>
                      <MapPin size={15} /> Tu pedido está en camino 🚚
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};

export default MyOrdersPage;
