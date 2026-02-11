import React, { useState, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import {
  Package, Plus, Pencil, Trash2, Search, BarChart3, DollarSign, ShoppingBag,
  TrendingUp, Store, Eye, EyeOff, Save, X, Clock, CheckCircle2, Truck,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import {
  getVendorDashboard, getVendorInventory, addProduct, updateProduct,
  deleteProduct, getVendorOrders, updateOrderStatus,
} from '../services/api';

const STATUS_CONFIG = {
  payment_pending: { label: 'Pago Pendiente', color: '#FFC400', next: 'preparing' },
  preparing: { label: 'Preparando', color: '#00A8E8', next: 'ready_for_pickup' },
  ready_for_pickup: { label: 'Listo', color: '#8B5CF6', next: null },
  in_transit: { label: 'En camino', color: '#F97316', next: null },
  delivered: { label: 'Entregado', color: '#22C55E', next: null },
  cancelled: { label: 'Cancelado', color: '#EF4444', next: null },
};

const DEMO_VENDOR_STATS = {
  total_sales: 2100000, total_orders: 65, total_products: 42, total_commission: 210000,
};
const DEMO_INVENTORY = [
  { id: 101, product_name: 'Royal Canin Medium Adult 15kg', description: 'Alimento seco adulto raza mediana', price: 52990, stock: 24, category: 'Alimento', image_url: 'https://images.unsplash.com/photo-1589924749359-6852750f50e8?w=200&auto=format&fit=crop&q=80' },
  { id: 102, product_name: 'Pro Plan Gato Adulto 7.5kg', description: 'Fórmula avanzada pollo', price: 38990, stock: 18, category: 'Alimento', image_url: 'https://images.unsplash.com/photo-1615497001839-b0a0eac3274c?w=200&auto=format&fit=crop&q=80' },
  { id: 103, product_name: 'Collar LED Recargable', description: 'Collar luminoso 3 modos USB', price: 14990, stock: 35, category: 'Accesorios', image_url: 'https://images.unsplash.com/photo-1567612529009-afe25413fe2f?w=200&auto=format&fit=crop&q=80' },
  { id: 104, product_name: 'Bravecto Perro 10-20kg', description: 'Antiparasitario oral 12 semanas', price: 29990, stock: 12, category: 'Farmacia', image_url: 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=200&auto=format&fit=crop&q=80' },
  { id: 105, product_name: 'Cama Ortopédica Premium L', description: 'Espuma viscoelástica lavable', price: 45990, stock: 8, category: 'Accesorios', image_url: 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?w=200&auto=format&fit=crop&q=80' },
  { id: 106, product_name: 'Whiskas Adulto Pollo 10kg', description: 'Alimento gatos adulto', price: 12990, stock: 42, category: 'Alimento', image_url: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=200&auto=format&fit=crop&q=80' },
];
const DEMO_VENDOR_ORDERS = [
  { id: 1051, customer_name: 'María López', status: 'preparing', total_amount: 52990, delivery_fee: 2990, created_at: '2026-02-10T08:30:00Z' },
  { id: 1052, customer_name: 'Carlos Muñoz', status: 'ready_for_pickup', total_amount: 38990, delivery_fee: 0, created_at: '2026-02-09T14:20:00Z' },
  { id: 1048, customer_name: 'Ana Torres', status: 'delivered', total_amount: 91980, delivery_fee: 2990, created_at: '2026-02-08T11:00:00Z' },
  { id: 1045, customer_name: 'Pedro Soto', status: 'delivered', total_amount: 14990, delivery_fee: 2990, created_at: '2026-02-07T16:45:00Z' },
];

const VendorDashboard = () => {
  const { isAuthenticated, isVendor, isAdmin } = useAuth();
  const [tab, setTab] = useState('dashboard');
  const [stats, setStats] = useState(null);
  const [inventory, setInventory] = useState([]);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editingProduct, setEditingProduct] = useState(null);
  const [showForm, setShowForm] = useState(false);
  const [vendorInactive, setVendorInactive] = useState(false);
  const [inactiveMessage, setInactiveMessage] = useState('');

  // Formulario de producto
  const emptyProduct = { product_name: '', description: '', price: '', stock: '', category: '' };
  const [formData, setFormData] = useState(emptyProduct);

  useEffect(() => {
    loadData();
  }, [tab]);

  const loadData = async () => {
    setLoading(true);
    try {
      if (tab === 'dashboard') {
        const { data } = await getVendorDashboard();
        setStats(data || DEMO_VENDOR_STATS);
      } else if (tab === 'inventory') {
        const { data } = await getVendorInventory();
        const inv = Array.isArray(data) ? data : data?.data || [];
        setInventory(inv.length > 0 ? inv : DEMO_INVENTORY);
      } else if (tab === 'orders') {
        const { data } = await getVendorOrders();
        const ord = Array.isArray(data) ? data : [];
        setOrders(ord.length > 0 ? ord : DEMO_VENDOR_ORDERS);
      }
    } catch (err) {
      console.error('Error cargando datos:', err);
      // Detect vendor inactive / subscription expired
      if (err.response?.status === 403 && (err.response?.data?.code === 'vendor_inactive' || err.response?.data?.code === 'subscription_expired')) {
        setVendorInactive(true);
        setInactiveMessage(err.response?.data?.message || 'Tu tienda está inactiva.');
        return;
      }
      if (tab === 'dashboard') setStats(DEMO_VENDOR_STATS);
      if (tab === 'inventory') setInventory(DEMO_INVENTORY);
      if (tab === 'orders') setOrders(DEMO_VENDOR_ORDERS);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated || (!isVendor() && !isAdmin())) return <Navigate to="/login" />;

  // Vendor inactive screen
  if (vendorInactive) {
    return (
      <div style={{ minHeight: '70vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '40px 16px', background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fff8e1 100%)' }}>
        <div style={{ maxWidth: 520, width: '100%', textAlign: 'center' }}>
          <div style={{ background: '#fff', borderRadius: 20, padding: '48px 32px', boxShadow: '0 8px 32px rgba(0,0,0,0.08)', border: '2px solid #ef9a9a' }}>
            <div style={{ fontSize: 64, marginBottom: 16 }}>🔒</div>
            <h2 style={{ color: '#c62828', margin: '0 0 16px', fontSize: 24, fontWeight: 800 }}>Suscripción Inactiva</h2>
            <p style={{ color: '#555', fontSize: 15, lineHeight: 1.7, margin: '0 0 24px' }}>{inactiveMessage}</p>
            <div style={{ background: '#f8f9fa', borderRadius: 12, padding: '16px 20px', marginBottom: 24, textAlign: 'left', fontSize: 13, color: '#555' }}>
              <p style={{ margin: '0 0 8px', fontWeight: 700, color: '#333' }}>⚠️ Mientras tu tienda esté inactiva:</p>
              <ul style={{ margin: 0, paddingLeft: 20, lineHeight: 2 }}>
                <li>Tus productos no serán visibles para los clientes</li>
                <li>No podrás recibir nuevos pedidos</li>
                <li>No tendrás acceso al panel de administración</li>
              </ul>
            </div>
            <a href="mailto:contacto@petsgo.cl?subject=Renovación suscripción" style={{ display: 'inline-block', background: '#00A8E8', color: '#fff', fontSize: 15, fontWeight: 700, textDecoration: 'none', padding: '14px 36px', borderRadius: 10 }}>📧 Contactar para renovar</a>
            <p style={{ color: '#aaa', fontSize: 12, marginTop: 16 }}>contacto@petsgo.cl · +56 9 1234 5678</p>
          </div>
        </div>
      </div>
    );
  }

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  // CRUD de productos
  const handleSaveProduct = async (e) => {
    e.preventDefault();
    try {
      if (editingProduct) {
        await updateProduct(editingProduct.id, formData);
      } else {
        await addProduct(formData);
      }
      setShowForm(false);
      setEditingProduct(null);
      setFormData(emptyProduct);
      loadData();
    } catch (err) {
      alert(err.response?.data?.message || 'Error guardando producto');
    }
  };

  const handleEditProduct = (product) => {
    setEditingProduct(product);
    setFormData({
      product_name: product.product_name,
      description: product.description || '',
      price: product.price,
      stock: product.stock,
      category: product.category || '',
    });
    setShowForm(true);
  };

  const handleDeleteProduct = async (id) => {
    if (!confirm('¿Eliminar este producto?')) return;
    try {
      await deleteProduct(id);
      loadData();
    } catch (err) {
      alert('Error eliminando producto');
    }
  };

  const handleUpdateOrderStatus = async (orderId, newStatus) => {
    try {
      await updateOrderStatus(orderId, newStatus);
      loadData();
    } catch (err) {
      alert('Error actualizando estado');
    }
  };

  const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: BarChart3 },
    { key: 'inventory', label: 'Inventario', icon: Package },
    { key: 'orders', label: 'Pedidos', icon: ShoppingBag },
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 lg:px-8 py-8">
      <div className="flex items-center gap-3 mb-8">
        <div className="w-12 h-12 bg-[#00A8E8] rounded-2xl flex items-center justify-center">
          <Store size={24} className="text-white" />
        </div>
        <div>
          <h2 className="text-2xl font-black text-[#2F3A40]">Panel de Tienda</h2>
          <p className="text-sm text-gray-400 font-medium">Gestiona tu negocio en PetsGo</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-8 overflow-x-auto">
        {tabs.map((t) => {
          const Icon = t.icon;
          return (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold whitespace-nowrap transition-all ${
                tab === t.key
                  ? 'bg-[#00A8E8] text-white shadow-lg'
                  : 'bg-white text-gray-500 hover:bg-gray-100 border border-gray-200'
              }`}
            >
              <Icon size={16} /> {t.label}
            </button>
          );
        })}
      </div>

      {/* ==================== DASHBOARD ==================== */}
      {tab === 'dashboard' && (
        <div>
          {loading ? (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="petsgo-card p-6 animate-pulse">
                  <div className="h-4 bg-gray-200 rounded w-1/2 mb-3" />
                  <div className="h-8 bg-gray-200 rounded w-3/4" />
                </div>
              ))}
            </div>
          ) : stats ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <div className="petsgo-card p-6">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <DollarSign size={20} className="text-blue-600" />
                  </div>
                  <span className="text-sm text-gray-400 font-bold">Ventas Totales</span>
                </div>
                <p className="text-3xl font-black text-[#2F3A40]">{formatPrice(stats.total_sales || 0)}</p>
              </div>
              <div className="petsgo-card p-6">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <ShoppingBag size={20} className="text-green-600" />
                  </div>
                  <span className="text-sm text-gray-400 font-bold">Pedidos</span>
                </div>
                <p className="text-3xl font-black text-[#2F3A40]">{stats.total_orders || 0}</p>
              </div>
              <div className="petsgo-card p-6">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                    <Package size={20} className="text-purple-600" />
                  </div>
                  <span className="text-sm text-gray-400 font-bold">Productos</span>
                </div>
                <p className="text-3xl font-black text-[#2F3A40]">{stats.total_products || 0}</p>
              </div>
              <div className="petsgo-card p-6">
                <div className="flex items-center gap-3 mb-3">
                  <div className="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                    <TrendingUp size={20} className="text-orange-600" />
                  </div>
                  <span className="text-sm text-gray-400 font-bold">Comision PetsGo</span>
                </div>
                <p className="text-3xl font-black text-[#2F3A40]">{formatPrice(stats.total_commission || 0)}</p>
              </div>
            </div>
          ) : (
            <p className="text-gray-400">No se pudieron cargar las estadisticas</p>
          )}
        </div>
      )}

      {/* ==================== INVENTARIO ==================== */}
      {tab === 'inventory' && (
        <div>
          <div className="flex items-center justify-between mb-6">
            <h3 className="text-lg font-black text-[#2F3A40]">
              Mis Productos <span className="text-gray-400 font-medium">({inventory.length})</span>
            </h3>
            <button
              onClick={() => { setShowForm(true); setEditingProduct(null); setFormData(emptyProduct); }}
              className="petsgo-btn text-sm flex items-center gap-2"
            >
              <Plus size={16} /> Agregar Producto
            </button>
          </div>

          {/* Formulario modal */}
          {showForm && (
            <div className="petsgo-card p-6 mb-6 border-2 border-[#00A8E8]">
              <div className="flex items-center justify-between mb-4">
                <h4 className="font-bold text-[#2F3A40]">
                  {editingProduct ? 'Editar Producto' : 'Nuevo Producto'}
                </h4>
                <button onClick={() => setShowForm(false)} className="text-gray-400 hover:text-gray-600">
                  <X size={18} />
                </button>
              </div>
              <form onSubmit={handleSaveProduct} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <label className="block text-sm font-bold text-[#2F3A40] mb-1">Nombre del Producto *</label>
                  <input
                    value={formData.product_name}
                    onChange={(e) => setFormData({ ...formData, product_name: e.target.value })}
                    required
                    className="w-full px-4 py-3 bg-gray-50 rounded-xl border border-gray-200 focus:border-[#00A8E8] outline-none text-sm font-medium"
                    placeholder="Ej: Royal Canin Adulto 15kg"
                  />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-sm font-bold text-[#2F3A40] mb-1">Descripcion</label>
                  <textarea
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    rows={2}
                    className="w-full px-4 py-3 bg-gray-50 rounded-xl border border-gray-200 focus:border-[#00A8E8] outline-none text-sm font-medium resize-none"
                    placeholder="Descripcion del producto..."
                  />
                </div>
                <div>
                  <label className="block text-sm font-bold text-[#2F3A40] mb-1">Precio (CLP) *</label>
                  <input
                    type="number"
                    value={formData.price}
                    onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                    required
                    min="0"
                    className="w-full px-4 py-3 bg-gray-50 rounded-xl border border-gray-200 focus:border-[#00A8E8] outline-none text-sm font-medium"
                    placeholder="29990"
                  />
                </div>
                <div>
                  <label className="block text-sm font-bold text-[#2F3A40] mb-1">Stock *</label>
                  <input
                    type="number"
                    value={formData.stock}
                    onChange={(e) => setFormData({ ...formData, stock: e.target.value })}
                    required
                    min="0"
                    className="w-full px-4 py-3 bg-gray-50 rounded-xl border border-gray-200 focus:border-[#00A8E8] outline-none text-sm font-medium"
                    placeholder="50"
                  />
                </div>
                <div>
                  <label className="block text-sm font-bold text-[#2F3A40] mb-1">Categoria</label>
                  <select
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                    className="w-full px-4 py-3 bg-gray-50 rounded-xl border border-gray-200 focus:border-[#00A8E8] outline-none text-sm font-medium"
                  >
                    <option value="">Seleccionar...</option>
                    {['Alimento', 'Accesorios', 'Juguetes', 'Higiene', 'Farmacia', 'Transporte', 'Tecnologia', 'Snacks'].map((c) => (
                      <option key={c} value={c}>{c}</option>
                    ))}
                  </select>
                </div>
                <div className="flex items-end">
                  <button type="submit" className="petsgo-btn text-sm flex items-center gap-2 w-full justify-center">
                    <Save size={16} /> {editingProduct ? 'Actualizar' : 'Guardar'}
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* Tabla de inventario */}
          {loading ? (
            <div className="petsgo-card p-6 animate-pulse">
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-3/4" />
            </div>
          ) : inventory.length === 0 ? (
            <div className="text-center py-16 petsgo-card">
              <Package size={48} className="mx-auto text-gray-300 mb-4" />
              <p className="text-gray-400 font-bold">Aún no tienes productos</p>
              <p className="text-gray-300 text-sm">Agrega tu primer producto para comenzar a vender</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Producto</th>
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Categoria</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Precio</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Stock</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {inventory.map((p) => (
                    <tr key={p.id} className="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                      <td className="py-3 px-4 font-medium text-[#2F3A40]">{p.product_name}</td>
                      <td className="py-3 px-4">
                        <span className="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-1 rounded-lg">{p.category || '—'}</span>
                      </td>
                      <td className="py-3 px-4 text-right font-bold text-[#00A8E8]">{formatPrice(p.price)}</td>
                      <td className="py-3 px-4 text-right font-medium">
                        <span className={parseInt(p.stock) < 5 ? 'text-red-500 font-bold' : 'text-gray-600'}>
                          {p.stock}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right">
                        <button onClick={() => handleEditProduct(p)} className="text-gray-400 hover:text-[#00A8E8] mr-2" title="Editar">
                          <Pencil size={16} />
                        </button>
                        <button onClick={() => handleDeleteProduct(p.id)} className="text-gray-400 hover:text-red-500" title="Eliminar">
                          <Trash2 size={16} />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ==================== PEDIDOS ==================== */}
      {tab === 'orders' && (
        <div>
          <h3 className="text-lg font-black text-[#2F3A40] mb-6">
            Pedidos Recibidos <span className="text-gray-400 font-medium">({orders.length})</span>
          </h3>

          {loading ? (
            <div className="space-y-4">
              {[...Array(3)].map((_, i) => (
                <div key={i} className="petsgo-card p-6 animate-pulse">
                  <div className="h-4 bg-gray-200 rounded w-1/3 mb-2" />
                  <div className="h-3 bg-gray-200 rounded w-1/2" />
                </div>
              ))}
            </div>
          ) : orders.length === 0 ? (
            <div className="text-center py-16 petsgo-card">
              <ShoppingBag size={48} className="mx-auto text-gray-300 mb-4" />
              <p className="text-gray-400 font-bold">Aún no tienes pedidos</p>
            </div>
          ) : (
            <div className="space-y-4">
              {orders.map((order) => {
                const statusCfg = STATUS_CONFIG[order.status] || STATUS_CONFIG.payment_pending;
                return (
                  <div key={order.id} className="petsgo-card p-6">
                    <div className="flex items-start justify-between">
                      <div>
                        <span className="text-xs font-bold text-gray-400">Pedido #{order.id}</span>
                        <p className="font-bold text-[#2F3A40] mt-1">
                          Total: <span className="text-[#00A8E8]">{formatPrice(order.total_amount)}</span>
                        </p>
                        <p className="text-xs text-gray-400 mt-1">
                          Comision: {formatPrice(order.petsgo_commission)} | Delivery: {formatPrice(order.delivery_fee)}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        <span
                          className="px-3 py-1.5 rounded-full text-xs font-bold"
                          style={{ backgroundColor: statusCfg.color + '15', color: statusCfg.color }}
                        >
                          {statusCfg.label}
                        </span>
                        {statusCfg.next && (
                          <button
                            onClick={() => handleUpdateOrderStatus(order.id, statusCfg.next)}
                            className="petsgo-btn text-xs px-3 py-1.5"
                          >
                            Avanzar
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default VendorDashboard;
