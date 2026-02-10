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

const VendorDashboard = () => {
  const { isAuthenticated, isVendor, isAdmin } = useAuth();
  const [tab, setTab] = useState('dashboard');
  const [stats, setStats] = useState(null);
  const [inventory, setInventory] = useState([]);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editingProduct, setEditingProduct] = useState(null);
  const [showForm, setShowForm] = useState(false);

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
        setStats(data);
      } else if (tab === 'inventory') {
        const { data } = await getVendorInventory();
        setInventory(Array.isArray(data) ? data : data?.data || []);
      } else if (tab === 'orders') {
        const { data } = await getVendorOrders();
        setOrders(Array.isArray(data) ? data : []);
      }
    } catch (err) {
      console.error('Error cargando datos:', err);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated || (!isVendor() && !isAdmin())) return <Navigate to="/login" />;

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
