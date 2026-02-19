import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Package, Clock, CheckCircle2, Truck, MapPin, Store, PawPrint, FileText, ShoppingBag, ChevronDown, ChevronUp, Download } from 'lucide-react';
import { getMyOrders } from '../services/api';
import { useAuth } from '../context/AuthContext';

const STATUS_CONFIG = {
  pending: { label: 'Pendiente', color: '#FFC400', bg: '#fff8e1', icon: Clock },
  payment_pending: { label: 'Pago Pendiente', color: '#FFC400', bg: '#fff8e1', icon: Clock },
  preparing: { label: 'Preparando', color: '#00A8E8', bg: '#e0f7fa', icon: Package },
  ready_for_pickup: { label: 'Listo para enviar', color: '#8B5CF6', bg: '#ede9fe', icon: Package },
  in_transit: { label: 'En camino', color: '#F97316', bg: '#fff7ed', icon: Truck },
  delivered: { label: 'Entregado', color: '#22C55E', bg: '#f0fdf4', icon: CheckCircle2 },
  cancelled: { label: 'Cancelado', color: '#EF4444', bg: '#fef2f2', icon: Package },
};

const cardStyle = {
  background: '#fff', borderRadius: '20px', padding: '0',
  boxShadow: '0 2px 16px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
  transition: 'transform 0.15s, box-shadow 0.15s',
  overflow: 'hidden',
};

/** Group flat orders into purchases by purchase_group or by timestamp proximity */
const groupIntoPurchases = (orders) => {
  const grouped = {};
  orders.forEach(order => {
    const key = order.purchase_group || `single_${order.id}`;
    if (!grouped[key]) grouped[key] = { key, orders: [], date: order.created_at };
    grouped[key].orders.push(order);
    // Use earliest date
    if (new Date(order.created_at) < new Date(grouped[key].date)) {
      grouped[key].date = order.created_at;
    }
  });
  return Object.values(grouped).sort((a, b) => new Date(b.date) - new Date(a.date));
};

const MyOrdersPage = () => {
  const { user, isAuthenticated, loading: authLoading } = useAuth();
  const navigate = useNavigate();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [expandedPurchases, setExpandedPurchases] = useState({});

  useEffect(() => {
    if (authLoading) return;
    if (!user) { navigate('/login'); return; }
    loadOrders();
  }, [user, authLoading, navigate]);

  const loadOrders = async () => {
    try {
      const res = await getMyOrders();
      const realOrders = Array.isArray(res.data) ? res.data : (Array.isArray(res.data?.data) ? res.data.data : []);
      setOrders(realOrders);
    } catch (err) {
      console.error('Error cargando pedidos:', err);
      setOrders([]);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated) return null;

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;
  const formatDate = (date) => new Date(date).toLocaleDateString('es-CL', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });

  // Apply filter then group
  const filteredOrders = filter === 'all' ? orders : orders.filter((o) => o.status === filter);
  const purchases = groupIntoPurchases(filteredOrders);

  const togglePurchase = (key) => {
    setExpandedPurchases(prev => ({ ...prev, [key]: !prev[key] }));
  };

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
          🛍️ Mis Compras
        </h1>
        <p style={{ color: '#9ca3af', fontWeight: 500, fontSize: '14px', marginBottom: '24px' }}>
          Historial y seguimiento de tus compras
        </p>

        {/* Filtros */}
        <div style={{ display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '12px', marginBottom: '20px' }}>
          <button onClick={() => setFilter('all')} style={filterBtnStyle(filter === 'all')}>
            Todos
          </button>
          {Object.entries(STATUS_CONFIG).filter(([k]) => k !== 'payment_pending').map(([key, val]) => (
            <button key={key} onClick={() => setFilter(key)} style={filterBtnStyle(filter === key)}>
              {val.label}
            </button>
          ))}
        </div>

        {/* Loading */}
        {loading ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {[1, 2, 3].map(i => (
              <div key={i} style={{ ...cardStyle, padding: '24px', opacity: 0.6 }}>
                <div style={{ height: '16px', background: '#e5e7eb', borderRadius: '8px', width: '35%', marginBottom: '12px' }} />
                <div style={{ height: '12px', background: '#e5e7eb', borderRadius: '8px', width: '55%', marginBottom: '8px' }} />
                <div style={{ height: '12px', background: '#e5e7eb', borderRadius: '8px', width: '30%' }} />
              </div>
            ))}
          </div>
        ) : purchases.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '64px 16px' }}>
            <PawPrint size={48} style={{ margin: '0 auto 16px', color: '#d1d5db' }} />
            <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '18px' }}>
              {filter === 'all' ? 'Aún no tienes compras' : 'No hay pedidos con este estado'}
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
          <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            {purchases.map((purchase) => {
              const isExpanded = expandedPurchases[purchase.key] !== false; // expanded by default
              const totalAmount = purchase.orders.reduce((s, o) => s + parseFloat(o.total_amount || 0), 0);
              const totalDelivery = purchase.orders.reduce((s, o) => s + parseFloat(o.delivery_fee || 0), 0);
              const totalItems = purchase.orders.reduce((s, o) => s + (o.items?.reduce((si, i) => si + parseInt(i.quantity), 0) || 0), 0);
              const allPaid = purchase.orders.every(o => o.payment_status === 'paid');
              const mainMethod = purchase.orders[0]?.delivery_method;

              return (
                <div key={purchase.key} style={cardStyle}
                  onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 8px 24px rgba(0,0,0,0.1)'; }}
                  onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = '0 2px 16px rgba(0,0,0,0.06)'; }}
                >
                  {/* Purchase Header */}
                  <div
                    onClick={() => togglePurchase(purchase.key)}
                    style={{
                      padding: '20px 24px', cursor: 'pointer',
                      background: 'linear-gradient(135deg, #f8fafc 0%, #f0f9ff 100%)',
                      borderBottom: isExpanded ? '1px solid #e5e7eb' : 'none',
                    }}
                  >
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div style={{
                          width: '42px', height: '42px', borderRadius: '12px',
                          background: 'linear-gradient(135deg, #00A8E8, #0090c7)',
                          display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}>
                          <ShoppingBag size={20} color="#fff" />
                        </div>
                        <div>
                          <div style={{ fontWeight: 800, fontSize: '16px', color: '#1f2937' }}>
                            Compra del {formatDate(purchase.date)}
                          </div>
                          <div style={{ fontSize: '12px', color: '#9ca3af', marginTop: '2px' }}>
                            {purchase.orders.length} {purchase.orders.length === 1 ? 'pedido' : 'pedidos'} · {totalItems} {totalItems === 1 ? 'producto' : 'productos'}
                            {mainMethod === 'pickup' ? ' · 🏪 Retiro en tienda' : ' · 🚚 Envío'}
                          </div>
                        </div>
                      </div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div style={{ textAlign: 'right' }}>
                          <div style={{ fontWeight: 800, fontSize: '18px', color: '#00A8E8' }}>
                            {formatPrice(totalAmount + totalDelivery)}
                          </div>
                          {allPaid && (
                            <span style={{ fontSize: '11px', fontWeight: 700, color: '#22C55E' }}>✅ Pagado</span>
                          )}
                        </div>
                        {isExpanded ? <ChevronUp size={20} color="#9ca3af" /> : <ChevronDown size={20} color="#9ca3af" />}
                      </div>
                    </div>
                  </div>

                  {/* Expanded: individual orders */}
                  {isExpanded && (
                    <div style={{ padding: '0' }}>
                      {purchase.orders.map((order, idx) => {
                        const status = STATUS_CONFIG[order.status] || STATUS_CONFIG.pending;
                        const StatusIcon = status.icon;
                        return (
                          <div key={order.id} style={{
                            padding: '18px 24px',
                            borderBottom: idx < purchase.orders.length - 1 ? '1px solid #f3f4f6' : 'none',
                          }}>
                            {/* Order header */}
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' }}>
                              <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                <Store size={16} color="#9ca3af" />
                                <div>
                                  <span style={{ fontWeight: 700, fontSize: '14px', color: '#1f2937' }}>
                                    {order.store_name || 'Tienda PetsGo'}
                                  </span>
                                  <span style={{ fontSize: '11px', color: '#9ca3af', marginLeft: '8px' }}>
                                    Pedido #{order.id}
                                  </span>
                                </div>
                              </div>
                              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <div style={{
                                  display: 'flex', alignItems: 'center', gap: '4px',
                                  padding: '4px 12px', borderRadius: '50px',
                                  backgroundColor: status.bg, color: status.color,
                                  fontSize: '11px', fontWeight: 700,
                                }}>
                                  <StatusIcon size={12} />
                                  {status.label}
                                </div>
                              </div>
                            </div>

                            {/* Order items */}
                            {order.items && order.items.length > 0 && (
                              <div style={{ background: '#fafafa', borderRadius: '10px', padding: '10px 14px', border: '1px solid #f3f4f6', marginBottom: '10px' }}>
                                {order.items.map((item, iIdx) => (
                                  <div key={iIdx} style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    padding: '5px 0', fontSize: '13px', color: '#4b5563',
                                    borderBottom: iIdx < order.items.length - 1 ? '1px solid #f0f0f0' : 'none',
                                  }}>
                                    <span>
                                      {item.product_name}
                                      <span style={{ color: '#9ca3af', marginLeft: '6px' }}>x{item.quantity}</span>
                                    </span>
                                    <span style={{ fontWeight: 600, whiteSpace: 'nowrap' }}>{formatPrice(item.subtotal)}</span>
                                  </div>
                                ))}
                              </div>
                            )}

                            {/* Order footer */}
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontSize: '13px' }}>
                              <div style={{ display: 'flex', gap: '16px', color: '#6b7280' }}>
                                <span>Subtotal: <strong style={{ color: '#1f2937' }}>{formatPrice(order.total_amount)}</strong></span>
                                <span>Envío: <strong style={{ color: '#1f2937' }}>{parseFloat(order.delivery_fee) > 0 ? formatPrice(order.delivery_fee) : 'Gratis'}</strong></span>
                              </div>
                              {/* Invoice download */}
                              {order.invoice_url && (
                                <a
                                  href={order.invoice_url}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  style={{
                                    display: 'inline-flex', alignItems: 'center', gap: '4px',
                                    padding: '5px 12px', borderRadius: '8px',
                                    background: '#f0faff', color: '#00A8E8',
                                    fontSize: '12px', fontWeight: 700, textDecoration: 'none',
                                    border: '1px solid #d0ecf9', transition: 'all 0.2s',
                                  }}
                                  onMouseEnter={e => { e.currentTarget.style.background = '#e0f4ff'; }}
                                  onMouseLeave={e => { e.currentTarget.style.background = '#f0faff'; }}
                                >
                                  <FileText size={14} />
                                  Boleta PDF
                                </a>
                              )}
                            </div>

                            {order.status === 'in_transit' && (
                              <div style={{
                                marginTop: '10px', background: '#fff7ed', borderRadius: '10px',
                                padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '8px',
                                fontSize: '12px', color: '#ea580c', fontWeight: 600,
                              }}>
                                <MapPin size={14} /> Tu pedido está en camino 🚚
                              </div>
                            )}
                          </div>
                        );
                      })}
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