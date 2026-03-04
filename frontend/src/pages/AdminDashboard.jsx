import React, { useState, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import {
  BarChart3, DollarSign, Store, Users, Package, ShoppingBag, TrendingUp,
  Eye, Percent, Save, Search, Shield, PawPrint, Truck, Star, MapPin,
  Calendar, Download, ChevronLeft, ChevronRight, ArrowLeft, FileText, X, Plus, Trash2, Edit3, Image,
} from 'lucide-react';
import huellaPng from '../assets/huella.png';
import InfoGuideButton from '../components/InfoGuideButton';
import { useAuth } from '../context/AuthContext';
import {
  getAdminDashboard, getAdminVendors, getVendorDashboardAsAdmin,
  updateCommissions, getAdminRiders, getAdminRiderStats,
  getAdminInventory, addAdminProduct, updateAdminProduct, deleteAdminProduct, toggleAdminProduct, uploadAdminProductImage,
  getCategories,
} from '../services/api';

const DEMO_ADMIN_STATS = {
  total_sales: 12450000, total_commission: 1245000, total_orders: 342, total_vendors: 8,
};
const DEMO_ADMIN_VENDORS = [
  { id: 1, store_name: 'PetShop Las Condes', email: 'info@petshop.cl', total_sales: 3200000, total_orders: 87, sales_commission: 10, delivery_fee_cut: 5 },
  { id: 2, store_name: 'La Huella Store', email: 'ventas@lahuella.cl', total_sales: 2100000, total_orders: 65, sales_commission: 10, delivery_fee_cut: 5 },
  { id: 3, store_name: 'Mundo Animal Centro', email: 'contacto@mundoanimal.cl', total_sales: 1850000, total_orders: 52, sales_commission: 12, delivery_fee_cut: 5 },
  { id: 4, store_name: 'Vet & Shop', email: 'admin@vetshop.cl', total_sales: 2800000, total_orders: 74, sales_commission: 10, delivery_fee_cut: 5 },
  { id: 5, store_name: 'Happy Pets Providencia', email: 'hello@happypets.cl', total_sales: 980000, total_orders: 31, sales_commission: 10, delivery_fee_cut: 5 },
  { id: 6, store_name: 'PetLand Chile', email: 'soporte@petland.cl', total_sales: 1520000, total_orders: 33, sales_commission: 8, delivery_fee_cut: 5 },
];

const ITEMS_PER_PAGE = 5;

const loadImageAsBase64 = (url) => new Promise((resolve) => {
  const img = new window.Image();
  img.crossOrigin = 'anonymous';
  img.onload = () => {
    const c = document.createElement('canvas');
    c.width = img.width; c.height = img.height;
    c.getContext('2d').drawImage(img, 0, 0);
    resolve(c.toDataURL('image/png'));
  };
  img.onerror = () => resolve(null);
  img.src = url;
});

const PaginationControls = ({ currentPage, totalItems, onPageChange }) => {
  const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
  if (totalPages <= 1) return null;
  const pages = [];
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
      pages.push(i);
    } else if (pages[pages.length - 1] !== '...') {
      pages.push('...');
    }
  }
  return (
    <div className="flex items-center justify-center gap-2 mt-6 pt-4 border-t border-gray-100">
      <button onClick={() => onPageChange(currentPage - 1)} disabled={currentPage <= 1}
        className="p-2 rounded-lg hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
        <ChevronLeft size={18} />
      </button>
      {pages.map((p, i) => p === '...' ? (
        <span key={`e${i}`} className="text-gray-300 px-1">…</span>
      ) : (
        <button key={p} onClick={() => onPageChange(p)}
          className={`w-8 h-8 rounded-lg text-sm font-bold transition-all ${
            p === currentPage ? 'bg-[#00A8E8] text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'
          }`}>{p}</button>
      ))}
      <button onClick={() => onPageChange(currentPage + 1)} disabled={currentPage >= totalPages}
        className="p-2 rounded-lg hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
        <ChevronRight size={18} />
      </button>
      <span className="text-xs text-gray-400 ml-2">{totalItems} total</span>
    </div>
  );
};

const AdminDashboard = () => {
  const { isAuthenticated, isAdmin } = useAuth();
  const [tab, setTab] = useState('dashboard');
  const [stats, setStats] = useState(null);
  const [vendors, setVendors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [impersonateVendor, setImpersonateVendor] = useState(null);
  const [impersonateData, setImpersonateData] = useState(null);
  const [editingCommission, setEditingCommission] = useState(null);
  const [commissionForm, setCommissionForm] = useState({ sales_commission: '', delivery_fee_cut: '' });
  const [riders, setRiders] = useState([]);
  const [selectedRider, setSelectedRider] = useState(null);
  const [riderStats, setRiderStats] = useState(null);
  const [riderStatsRange, setRiderStatsRange] = useState('month');
  const [riderStatsFrom, setRiderStatsFrom] = useState('');
  const [riderStatsTo, setRiderStatsTo] = useState('');
  const [riderStatsLoading, setRiderStatsLoading] = useState(false);
  const [riderSearch, setRiderSearch] = useState('');
  // PetsGo Store tab
  const [petsgoProducts, setPetsgoProducts] = useState([]);
  const [petsgoVendorId, setPetsgoVendorId] = useState(null);
  const [showProductForm, setShowProductForm] = useState(false);
  const [editingProduct, setEditingProduct] = useState(null);
  const [productForm, setProductForm] = useState({ product_name: '', description: '', price: '', stock: '', category: '', image_id: null, image_url: null });
  const [productSaving, setProductSaving] = useState(false);
  const [categories, setCategories] = useState([]);
  const [uploadingImage, setUploadingImage] = useState(false);
  const [vendorPage, setVendorPage] = useState(1);
  const [riderPage, setRiderPage] = useState(1);
  const [storePage, setStorePage] = useState(1);

  useEffect(() => {
    setVendorPage(1); setRiderPage(1); setStorePage(1);
    loadData();
  }, [tab]);

  const loadData = async () => {
    setLoading(true);
    try {
      if (tab === 'dashboard') {
        const { data } = await getAdminDashboard();
        setStats(data || DEMO_ADMIN_STATS);
      } else if (tab === 'vendors') {
        const { data } = await getAdminVendors();
        const v = Array.isArray(data) ? data : (data?.data || []);
        setVendors(v.length > 0 ? v : DEMO_ADMIN_VENDORS);
      }
      if (tab === 'riders') {
        const { data } = await getAdminRiders();
        setRiders(Array.isArray(data) ? data : (data?.data || []));
      }
      if (tab === 'store') {
        const [invRes, catRes] = await Promise.all([getAdminInventory(), getCategories()]);
        setPetsgoProducts(invRes.data?.data || []);
        setPetsgoVendorId(invRes.data?.vendor_id || null);
        setCategories(catRes.data?.data || catRes.data || []);
      }
    } catch (err) {
      console.error('Error cargando datos admin:', err);
      if (tab === 'dashboard') setStats(DEMO_ADMIN_STATS);
      if (tab === 'vendors') setVendors(DEMO_ADMIN_VENDORS);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated || !isAdmin()) return <Navigate to="/login" />;

  const formatPrice = (price) => `$${parseInt(price || 0).toLocaleString('es-CL')}`;

  // Filtered riders for search + pagination
  const filteredRiders = riders.filter(r => !riderSearch || r.name?.toLowerCase().includes(riderSearch.toLowerCase()) || r.email?.toLowerCase().includes(riderSearch.toLowerCase()));

  // Impersonate Mode - Visualizar dashboard de tienda
  const handleImpersonate = async (vendorId, storeName) => {
    try {
      const { data } = await getVendorDashboardAsAdmin(vendorId);
      setImpersonateVendor(storeName);
      setImpersonateData(data);
    } catch (err) {
      if (window.PG?.toast) window.PG.toast('Error al acceder al dashboard de la tienda', 'error');
      else alert('Error al acceder al dashboard de la tienda');
    }
  };

  // Guardar comisiones
  const handleSaveCommission = async (vendorId) => {
    try {
      await updateCommissions(
        vendorId,
        parseFloat(commissionForm.sales_commission),
        parseFloat(commissionForm.delivery_fee_cut)
      );
      setEditingCommission(null);
      loadData();
      if (window.PG?.toast) window.PG.toast('Comisiones actualizadas', 'success');
    } catch (err) {
      if (window.PG?.toast) window.PG.toast('Error actualizando comisiones', 'error');
      else alert('Error actualizando comisiones');
    }
  };

  const tabs = [
    { key: 'dashboard', label: 'Dashboard Global', icon: BarChart3 },
    { key: 'vendors', label: 'Tiendas', icon: Store },
    { key: 'store', label: 'Tienda PetsGo', icon: ShoppingBag },
    { key: 'riders', label: 'Riders', icon: Truck },
  ];

  return (
    <div style={{ minHeight: '100vh', background: '#f8fafc' }}>
      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '48px 32px 60px' }}>
        <div className="flex items-center gap-3 mb-8">
          <div className="w-12 h-12 bg-[#2F3A40] rounded-2xl flex items-center justify-center">
            <Shield size={24} className="text-[#FFC400]" />
          </div>
          <div>
            <h2 className="text-2xl font-black text-[#2F3A40]">Panel Administrador</h2>
            <p className="text-sm text-gray-400 font-medium">PetsGo Marketplace - Control Total</p>
          </div>
        </div>

      {/* Tabs */}
      <div className="flex gap-2 mb-8">
        {tabs.map((t) => {
          const Icon = t.icon;
          return (
            <button
              key={t.key}
              onClick={() => { setTab(t.key); setImpersonateVendor(null); }}
              className={`flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold transition-all ${
                tab === t.key
                  ? 'bg-[#2F3A40] text-[#FFC400] shadow-lg'
                  : 'bg-white text-gray-500 hover:bg-gray-100 border border-gray-200'
              }`}
            >
              <Icon size={16} /> {t.label}
            </button>
          );
        })}
      </div>

      {/* ==================== DASHBOARD GLOBAL ==================== */}
      {tab === 'dashboard' && (
        <div>
          <div className="flex items-center gap-2 mb-4">
            <h3 className="text-lg font-black text-[#2F3A40]">📊 Dashboard Global</h3>
            <InfoGuideButton title="Dashboard Global" icon="📊" color="#2F3A40">
              <h4>📊 Dashboard Global</h4>
              <p><strong>¿Qué es?</strong><br/>El panel central del administrador. Muestra un resumen en tiempo real de toda la actividad del marketplace.</p>
              <p><strong>Métricas disponibles:</strong></p>
              <ul>
                <li><strong>Ventas Totales:</strong> Suma de todas las ventas realizadas en la plataforma.</li>
                <li><strong>Comisiones PetsGo:</strong> Total de comisiones cobradas a las tiendas por cada venta.</li>
                <li><strong>Total Pedidos:</strong> Cantidad total de pedidos procesados.</li>
                <li><strong>Tiendas Activas:</strong> Número de tiendas operando actualmente.</li>
              </ul>
              <p><strong>¿Cómo usar?</strong><br/>Revisa este panel regularmente para monitorear el rendimiento general del marketplace. Si notas caídas en ventas o pedidos, investiga en las pestañas de Tiendas o Riders.</p>
            </InfoGuideButton>
          </div>
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
            <>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div className="petsgo-card p-6 border-l-4 border-[#00A8E8]">
                  <div className="flex items-center gap-3 mb-3">
                    <DollarSign size={20} className="text-[#00A8E8]" />
                    <span className="text-sm text-gray-400 font-bold">Ventas Totales</span>
                  </div>
                  <p className="text-3xl font-black text-[#2F3A40]">{formatPrice(stats.total_sales)}</p>
                </div>
                <div className="petsgo-card p-6 border-l-4 border-[#FFC400]">
                  <div className="flex items-center gap-3 mb-3">
                    <TrendingUp size={20} className="text-[#FFC400]" />
                    <span className="text-sm text-gray-400 font-bold">Comisiones PetsGo</span>
                  </div>
                  <p className="text-3xl font-black text-[#2F3A40]">{formatPrice(stats.total_commission)}</p>
                </div>
                <div className="petsgo-card p-6 border-l-4 border-[#22C55E]">
                  <div className="flex items-center gap-3 mb-3">
                    <ShoppingBag size={20} className="text-[#22C55E]" />
                    <span className="text-sm text-gray-400 font-bold">Total Pedidos</span>
                  </div>
                  <p className="text-3xl font-black text-[#2F3A40]">{stats.total_orders || 0}</p>
                </div>
                <div className="petsgo-card p-6 border-l-4 border-[#8B5CF6]">
                  <div className="flex items-center gap-3 mb-3">
                    <Store size={20} className="text-[#8B5CF6]" />
                    <span className="text-sm text-gray-400 font-bold">Tiendas Activas</span>
                  </div>
                  <p className="text-3xl font-black text-[#2F3A40]">{stats.total_vendors || 0}</p>
                </div>
              </div>
            </>
          ) : (
            <p className="text-gray-400">No se pudieron cargar las estadisticas</p>
          )}
        </div>
      )}

      {/* ==================== TIENDAS ==================== */}
      {tab === 'vendors' && (
        <div>
          <div className="flex items-center gap-2 mb-4">
            <h3 className="text-lg font-black text-[#2F3A40]">🏪 Tiendas</h3>
            <InfoGuideButton title="Gestión de Tiendas" icon="🏪" color="#00A8E8">
              <h4>🏪 Gestión de Tiendas</h4>
              <p><strong>¿Qué es?</strong><br/>Panel de administración de todas las tiendas registradas en el marketplace. Permite supervisar, editar comisiones y ver los detalles de cada tienda.</p>
              <p><strong>Acciones disponibles:</strong></p>
              <ul>
                <li><strong>Supervisar tienda (ojo 👁️):</strong> Permite entrar en "modo supervisar" para ver el dashboard, ventas y productos de una tienda específica como si fueras el vendedor.</li>
                <li><strong>Editar comisiones:</strong> Ajusta el porcentaje de comisión por venta y el corte por fee de delivery para cada tienda.</li>
                <li><strong>Ver estadísticas:</strong> Revisa ventas totales, pedidos y rendimiento de cada tienda.</li>
              </ul>
              <p><strong>Comisiones:</strong><br/>La comisión por venta se descuenta automáticamente al procesar cada pedido. El corte de delivery es el porcentaje del fee de envío que retiene PetsGo.</p>
            </InfoGuideButton>
          </div>
          {/* Impersonate Modal */}
          {impersonateVendor && impersonateData && (
            <div className="petsgo-card p-6 mb-6 border-2 border-[#FFC400] bg-yellow-50/30">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-3">
                  <Eye size={20} className="text-[#FFC400]" />
                  <h4 className="font-black text-[#2F3A40]">
                    Modo Supervisar: <span className="text-[#00A8E8]">{impersonateVendor}</span>
                  </h4>
                </div>
                <button
                  onClick={() => setImpersonateVendor(null)}
                  className="text-gray-400 hover:text-gray-600 text-sm font-bold"
                >
                  Cerrar ✕
                </button>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white rounded-xl p-4">
                  <span className="text-xs text-gray-400 font-bold">Ventas</span>
                  <p className="text-xl font-black text-[#2F3A40]">{formatPrice(impersonateData.total_sales)}</p>
                </div>
                <div className="bg-white rounded-xl p-4">
                  <span className="text-xs text-gray-400 font-bold">Pedidos</span>
                  <p className="text-xl font-black text-[#2F3A40]">{impersonateData.total_orders || 0}</p>
                </div>
                <div className="bg-white rounded-xl p-4">
                  <span className="text-xs text-gray-400 font-bold">Productos</span>
                  <p className="text-xl font-black text-[#2F3A40]">{impersonateData.total_products || 0}</p>
                </div>
                <div className="bg-white rounded-xl p-4">
                  <span className="text-xs text-gray-400 font-bold">Comision</span>
                  <p className="text-xl font-black text-[#2F3A40]">{formatPrice(impersonateData.total_commission)}</p>
                </div>
              </div>
            </div>
          )}

          <h3 className="text-lg font-black text-[#2F3A40] mb-6">
            Tiendas Registradas <span className="text-gray-400 font-medium">({vendors.length})</span>
          </h3>

          {loading ? (
            <div className="petsgo-card p-6 animate-pulse">
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-3/4" />
            </div>
          ) : vendors.length === 0 ? (
            <div className="text-center py-16 petsgo-card">
              <Store size={48} className="mx-auto text-gray-300 mb-4" />
              <p className="text-gray-400 font-bold">No hay tiendas registradas</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Tienda</th>
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">RUT</th>
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Estado</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">% Venta</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">% Delivery</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {vendors.slice((vendorPage - 1) * ITEMS_PER_PAGE, vendorPage * ITEMS_PER_PAGE).map((v) => (
                    <tr key={v.id} className="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                      <td className="py-3 px-4">
                        <p className="font-bold text-[#2F3A40]">{v.store_name}</p>
                        <p className="text-xs text-gray-400">{v.email}</p>
                      </td>
                      <td className="py-3 px-4 font-medium text-gray-600">{v.rut}</td>
                      <td className="py-3 px-4">
                        <span className={`text-xs font-bold px-2 py-1 rounded-lg ${
                          v.status === 'active' ? 'bg-green-100 text-green-600' :
                          v.status === 'pending' ? 'bg-yellow-100 text-yellow-600' :
                          'bg-red-100 text-red-600'
                        }`}>
                          {v.status === 'active' ? 'Activa' : v.status === 'pending' ? 'Pendiente' : 'Suspendida'}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-right">
                        {editingCommission === v.id ? (
                          <input
                            type="number"
                            value={commissionForm.sales_commission}
                            onChange={(e) => setCommissionForm({ ...commissionForm, sales_commission: e.target.value })}
                            className="w-16 px-2 py-1 border rounded-lg text-center text-sm"
                            step="0.5"
                          />
                        ) : (
                          <span className="font-bold text-[#00A8E8]">{v.sales_commission}%</span>
                        )}
                      </td>
                      <td className="py-3 px-4 text-right">
                        {editingCommission === v.id ? (
                          <input
                            type="number"
                            value={commissionForm.delivery_fee_cut}
                            onChange={(e) => setCommissionForm({ ...commissionForm, delivery_fee_cut: e.target.value })}
                            className="w-16 px-2 py-1 border rounded-lg text-center text-sm"
                            step="0.5"
                          />
                        ) : (
                          <span className="font-bold text-gray-600">{v.delivery_fee_cut}%</span>
                        )}
                      </td>
                      <td className="py-3 px-4 text-right flex items-center justify-end gap-1">
                        <button
                          onClick={() => handleImpersonate(v.id, v.store_name)}
                          className="text-gray-400 hover:text-[#FFC400] p-1"
                          title="Supervisar tienda"
                        >
                          <Eye size={16} />
                        </button>
                        {editingCommission === v.id ? (
                          <button
                            onClick={() => handleSaveCommission(v.id)}
                            className="text-green-500 hover:text-green-600 p-1"
                            title="Guardar"
                          >
                            <Save size={16} />
                          </button>
                        ) : (
                          <button
                            onClick={() => {
                              setEditingCommission(v.id);
                              setCommissionForm({
                                sales_commission: v.sales_commission,
                                delivery_fee_cut: v.delivery_fee_cut,
                              });
                            }}
                            className="text-gray-400 hover:text-[#00A8E8] p-1"
                            title="Editar comisiones"
                          >
                            <Percent size={16} />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <PaginationControls currentPage={vendorPage} totalItems={vendors.length} onPageChange={setVendorPage} />
            </div>
          )}
        </div>
      )}

      {/* ==================== TIENDA PETSGO ==================== */}
      {tab === 'store' && (
        <div>
          <div className="flex items-center justify-between mb-6 flex-wrap gap-4">
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-lg font-black text-[#2F3A40]">
                  🛍️ Tienda PetsGo Oficial
                </h3>
                <InfoGuideButton title="Tienda PetsGo Oficial" icon="🛍️" color="#8B5CF6">
                  <h4>🛍️ Tienda PetsGo Oficial</h4>
                  <p><strong>¿Qué es?</strong><br/>La tienda propia de PetsGo dentro del marketplace. Aquí puedes gestionar productos que PetsGo vende directamente, sin intermediarios.</p>
                  <p><strong>Acciones disponibles:</strong></p>
                  <ul>
                    <li><strong>Agregar producto:</strong> Crea nuevos productos con nombre, descripción, precio, stock, categoría e imagen.</li>
                    <li><strong>Editar producto:</strong> Modifica los datos de un producto existente.</li>
                    <li><strong>Eliminar producto:</strong> Elimina un producto permanentemente del catálogo.</li>
                    <li><strong>Activar/Desactivar:</strong> Controla si un producto está visible para los clientes.</li>
                    <li><strong>Subir imagen:</strong> Sube una imagen del producto (mín 400×400px, máx 2000×2000px, máx 2MB).</li>
                  </ul>
                  <p><strong>Importante:</strong><br/>Los productos de esta tienda no generan comisión ya que son ventas directas de PetsGo.</p>
                </InfoGuideButton>
              </div>
              <p className="text-xs text-gray-400 mt-1">Gestiona los productos que PetsGo vende directamente</p>
            </div>
            <button
              onClick={() => {
                setEditingProduct(null);
                setProductForm({ product_name: '', description: '', price: '', stock: '', category: '', image_id: null, image_url: null });
                setShowProductForm(true);
              }}
              className="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold bg-[#00A8E8] text-white hover:bg-[#0090c7] transition-all shadow-lg"
            >
              <Plus size={16} /> Agregar Producto
            </button>
          </div>

          {/* Product Form Modal */}
          {showProductForm && (
            <div className="petsgo-card p-6 mb-6 border-2 border-[#00A8E8] bg-blue-50/30">
              <div className="flex items-center justify-between mb-4">
                <h4 className="font-black text-[#2F3A40] flex items-center gap-2">
                  <Package size={18} className="text-[#00A8E8]" />
                  {editingProduct ? 'Editar Producto' : 'Nuevo Producto PetsGo'}
                </h4>
                <button onClick={() => setShowProductForm(false)} className="text-gray-400 hover:text-gray-600">
                  <X size={18} />
                </button>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-500 mb-1">Nombre del Producto *</label>
                  <input
                    type="text" value={productForm.product_name}
                    onChange={e => setProductForm({ ...productForm, product_name: e.target.value })}
                    className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm"
                    placeholder="Ej: Royal Canin Adulto 15kg"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 mb-1">Categoría *</label>
                  <select
                    value={productForm.category}
                    onChange={e => setProductForm({ ...productForm, category: e.target.value })}
                    className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm"
                  >
                    <option value="">Seleccionar...</option>
                    {categories.map(c => (
                      <option key={c.id || c.slug} value={c.name}>{c.emoji || ''} {c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 mb-1">Precio (CLP) *</label>
                  <input
                    type="number" value={productForm.price}
                    onChange={e => setProductForm({ ...productForm, price: e.target.value })}
                    className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm"
                    placeholder="29990"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 mb-1">Stock *</label>
                  <input
                    type="number" value={productForm.stock}
                    onChange={e => setProductForm({ ...productForm, stock: e.target.value })}
                    className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm"
                    placeholder="100"
                  />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-gray-500 mb-1">Descripción</label>
                  <textarea
                    value={productForm.description}
                    onChange={e => setProductForm({ ...productForm, description: e.target.value })}
                    className="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm"
                    rows={3}
                    placeholder="Describe el producto..."
                  />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-gray-500 mb-1">Imagen del Producto</label>
                  <div className="flex items-center gap-4">
                    {productForm.image_url && (
                      <img src={productForm.image_url} alt="" className="w-16 h-16 rounded-xl object-cover border" />
                    )}
                    <label className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-600 cursor-pointer hover:bg-gray-50">
                      <Image size={16} />
                      {uploadingImage ? 'Subiendo...' : 'Subir Imagen'}
                      <input
                        type="file" accept="image/*" className="hidden"
                        onChange={async (e) => {
                          const file = e.target.files?.[0];
                          if (!file) return;
                          setUploadingImage(true);
                          try {
                            const res = await uploadAdminProductImage(file);
                            setProductForm(prev => ({ ...prev, image_id: res.data.image_id, image_url: res.data.image_url }));
                          } catch { if (window.PG?.toast) window.PG.toast('Error subiendo imagen', 'error'); }
                          setUploadingImage(false);
                        }}
                      />
                    </label>
                  </div>
                </div>
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button
                  onClick={() => setShowProductForm(false)}
                  className="px-5 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-100"
                >
                  Cancelar
                </button>
                <button
                  onClick={async () => {
                    if (!productForm.product_name || !productForm.price || !productForm.stock) {
                      if (window.PG?.toast) window.PG.toast('Completa nombre, precio y stock', 'warning');
                      else alert('Completa nombre, precio y stock'); return;
                    }
                    setProductSaving(true);
                    try {
                      const payload = {
                        product_name: productForm.product_name,
                        description: productForm.description,
                        price: parseFloat(productForm.price),
                        stock: parseInt(productForm.stock),
                        category: productForm.category,
                        image_id: productForm.image_id,
                      };
                      if (editingProduct) {
                        await updateAdminProduct(editingProduct, payload);
                      } else {
                        await addAdminProduct(payload);
                      }
                      setShowProductForm(false);
                      loadData();
                    } catch (err) {
                      const msg = err.response?.data?.message || 'Error guardando producto';
                      if (window.PG?.toast) window.PG.toast(msg, 'error');
                      else alert(msg);
                    }
                    setProductSaving(false);
                  }}
                  disabled={productSaving}
                  className="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold bg-[#00A8E8] text-white hover:bg-[#0090c7] disabled:bg-gray-300"
                >
                  <Save size={14} /> {productSaving ? 'Guardando...' : editingProduct ? 'Actualizar' : 'Crear Producto'}
                </button>
              </div>
            </div>
          )}

          {loading ? (
            <div className="petsgo-card p-6 animate-pulse">
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-3/4" />
            </div>
          ) : petsgoProducts.length === 0 ? (
            <div className="text-center py-16 petsgo-card">
              <ShoppingBag size={48} className="mx-auto text-gray-300 mb-4" />
              <p className="text-gray-400 font-bold text-lg">La Tienda PetsGo aún no tiene productos</p>
              <p className="text-gray-300 text-sm mt-1">Agrega productos para comenzar a vender directamente desde PetsGo</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Producto</th>
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Categoría</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Precio</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Stock</th>
                    <th className="text-center py-3 px-4 font-bold text-gray-400 text-xs uppercase">Estado</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {petsgoProducts.slice((storePage - 1) * ITEMS_PER_PAGE, storePage * ITEMS_PER_PAGE).map(p => (
                    <tr key={p.id} className="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-3">
                          {p.image_url ? (
                            <img src={p.image_url} alt="" className="w-10 h-10 rounded-lg object-cover" />
                          ) : (
                            <div className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                              <Package size={16} className="text-gray-400" />
                            </div>
                          )}
                          <div>
                            <p className="font-bold text-[#2F3A40]">{p.product_name}</p>
                            {p.description && <p className="text-xs text-gray-400 truncate max-w-xs">{p.description}</p>}
                          </div>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <span className="text-xs font-bold px-2 py-1 rounded-lg bg-blue-50 text-blue-600">{p.category || '—'}</span>
                      </td>
                      <td className="py-3 px-4 text-right font-bold text-[#00A8E8]">{formatPrice(p.price)}</td>
                      <td className="py-3 px-4 text-right">
                        <span className={`font-bold ${parseInt(p.stock) <= 5 ? 'text-red-500' : 'text-[#2F3A40]'}`}>
                          {p.stock}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <button
                          onClick={async () => {
                            try {
                              await toggleAdminProduct(p.id);
                              loadData();
                              if (window.PG?.toast) window.PG.toast(parseInt(p.is_active) !== 0 ? 'Producto desactivado' : 'Producto activado', 'success');
                            } catch { if (window.PG?.toast) window.PG.toast('Error al cambiar estado', 'error'); }
                          }}
                          style={{
                            border: 'none', cursor: 'pointer', padding: '4px 12px', borderRadius: '20px',
                            fontSize: '11px', fontWeight: 700,
                            background: parseInt(p.is_active) !== 0 ? '#e8f5e9' : '#fce4ec',
                            color: parseInt(p.is_active) !== 0 ? '#2e7d32' : '#c62828',
                          }}
                          title={parseInt(p.is_active) !== 0 ? 'Click para desactivar' : 'Click para activar'}
                        >
                          {parseInt(p.is_active) !== 0 ? '✅ Activo' : '❌ Inactivo'}
                        </button>
                      </td>
                      <td className="py-3 px-4 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <button
                            onClick={() => {
                              setEditingProduct(p.id);
                              setProductForm({
                                product_name: p.product_name || '',
                                description: p.description || '',
                                price: p.price || '',
                                stock: p.stock || '',
                                category: p.category || '',
                                image_id: p.image_id || null,
                                image_url: p.image_url || null,
                              });
                              setShowProductForm(true);
                            }}
                            className="text-gray-400 hover:text-[#00A8E8] p-1" title="Editar"
                          >
                            <Edit3 size={16} />
                          </button>
                          <button
                            onClick={async () => {
                              if (!confirm(`¿Eliminar "${p.product_name}"?`)) return;
                              try {
                                await deleteAdminProduct(p.id);
                                loadData();
                              } catch { if (window.PG?.toast) window.PG.toast('Error eliminando producto', 'error'); }
                            }}
                            className="text-gray-400 hover:text-red-500 p-1" title="Eliminar"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <PaginationControls currentPage={storePage} totalItems={petsgoProducts.length} onPageChange={setStorePage} />
            </div>
          )}
        </div>
      )}

      {/* ==================== RIDERS ==================== */}
      {tab === 'riders' && (
        <div>
          <div className="flex items-center gap-2 mb-4">
            <h3 className="text-lg font-black text-[#2F3A40]">🏍️ Riders</h3>
            <InfoGuideButton title="Gestión de Riders" icon="🏍️" color="#F97316">
              <h4>🏍️ Gestión de Riders</h4>
              <p><strong>¿Qué es?</strong><br/>Panel de gestión de todos los repartidores (riders) registrados en la plataforma. Permite ver su estado, estadísticas y rendimiento.</p>
              <p><strong>Información disponible:</strong></p>
              <ul>
                <li><strong>Listado de riders:</strong> Todos los riders registrados con su nombre, vehículo, estado y calificación.</li>
                <li><strong>Estadísticas detalladas:</strong> Al hacer clic en un rider, puedes ver sus entregas, ganancias y métricas por período.</li>
                <li><strong>Búsqueda:</strong> Filtra riders por nombre, email o estado.</li>
                <li><strong>Estados:</strong> Pendiente de documentos, en revisión, aprobado o suspendido.</li>
              </ul>
              <p><strong>Flujo del rider:</strong><br/>1. Se registra → 2. Sube documentos → 3. Admin revisa y aprueba → 4. Rider puede recibir entregas.</p>
            </InfoGuideButton>
          </div>
          {/* Rider detail modal */}
          {selectedRider && riderStats && (
            <RiderStatsModal
              stats={riderStats}
              loading={riderStatsLoading}
              range={riderStatsRange}
              customFrom={riderStatsFrom}
              customTo={riderStatsTo}
              onRangeChange={async (r, f, t) => {
                setRiderStatsRange(r);
                if (f !== undefined) setRiderStatsFrom(f);
                if (t !== undefined) setRiderStatsTo(t);
                setRiderStatsLoading(true);
                try {
                  const params = { range: r };
                  if (r === 'custom') { params.from = f || riderStatsFrom; params.to = t || riderStatsTo; }
                  const { data } = await getAdminRiderStats(selectedRider, params);
                  setRiderStats(data);
                } catch { /* keep old */ }
                setRiderStatsLoading(false);
              }}
              onClose={() => { setSelectedRider(null); setRiderStats(null); }}
              formatPrice={formatPrice}
            />
          )}

          <div className="flex items-center justify-between mb-6 flex-wrap gap-4">
            <h3 className="text-lg font-black text-[#2F3A40]">
              🏍️ Riders Registrados <span className="text-gray-400 font-medium">({filteredRiders.length})</span>
            </h3>
            <div className="relative" style={{ zIndex: 1 }}>
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style={{ pointerEvents: 'none' }} />
              <input
                type="text" placeholder="Buscar rider..." value={riderSearch} onChange={e => { setRiderSearch(e.target.value); setRiderPage(1); }}
                className="pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm w-64 focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] outline-none transition-all"
              />
            </div>
          </div>

          {loading ? (
            <div className="petsgo-card p-6 animate-pulse">
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-full mb-3" />
              <div className="h-4 bg-gray-200 rounded w-3/4" />
            </div>
          ) : riders.length === 0 ? (
            <div className="text-center py-16 petsgo-card">
              <Truck size={48} className="mx-auto text-gray-300 mb-4" />
              <p className="text-gray-400 font-bold">No hay riders registrados</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Rider</th>
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Vehículo</th>
                    <th className="text-left py-3 px-4 font-bold text-gray-400 text-xs uppercase">Estado</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Entregas</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Ganado</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Rating</th>
                    <th className="text-right py-3 px-4 font-bold text-gray-400 text-xs uppercase">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredRiders
                    .slice((riderPage - 1) * ITEMS_PER_PAGE, riderPage * ITEMS_PER_PAGE)
                    .map((r) => {
                    const VEHICLE_ICONS = { bicicleta: '🚲', scooter: '🛵', moto: '🏍️', auto: '🚗', a_pie: '🚶' };
                    const STATUS = { approved: { l: 'Activo', c: 'bg-green-100 text-green-600' }, pending: { l: 'Pendiente', c: 'bg-yellow-100 text-yellow-600' }, rejected: { l: 'Rechazado', c: 'bg-red-100 text-red-600' }, suspended: { l: 'Suspendido', c: 'bg-red-100 text-red-600' } };
                    const st = STATUS[r.status] || STATUS.pending;
                    return (
                      <tr key={r.id} className="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                        <td className="py-3 px-4">
                          <p className="font-bold text-[#2F3A40]">{r.name}</p>
                          <p className="text-xs text-gray-400">{r.email}</p>
                        </td>
                        <td className="py-3 px-4">
                          <span className="text-lg">{VEHICLE_ICONS[r.vehicle] || '🚗'}</span>
                          <span className="ml-1 text-xs text-gray-500 capitalize">{r.vehicle}</span>
                        </td>
                        <td className="py-3 px-4">
                          <span className={`text-xs font-bold px-2 py-1 rounded-lg ${st.c}`}>{st.l}</span>
                        </td>
                        <td className="py-3 px-4 text-right font-bold text-[#2F3A40]">{r.deliveries}</td>
                        <td className="py-3 px-4 text-right font-bold text-[#22C55E]">{formatPrice(r.earned)}</td>
                        <td className="py-3 px-4 text-right">
                          {r.avgRating ? <span className="font-bold text-[#F59E0B]">⭐ {r.avgRating}</span> : <span className="text-gray-300">—</span>}
                        </td>
                        <td className="py-3 px-4 text-right">
                          <button
                            onClick={async () => {
                              setSelectedRider(r.id);
                              setRiderStatsLoading(true);
                              try {
                                const { data } = await getAdminRiderStats(r.id, { range: riderStatsRange });
                                setRiderStats(data);
                              } catch { setRiderStats(null); }
                              setRiderStatsLoading(false);
                            }}
                            className="text-[#00A8E8] hover:text-[#0090c8] p-1 inline-flex items-center gap-1 text-xs font-bold"
                            title="Ver estadísticas"
                          >
                            <BarChart3 size={16} /> Estadísticas
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
              <PaginationControls currentPage={riderPage} totalItems={filteredRiders.length} onPageChange={setRiderPage} />
            </div>
          )}  
        </div>
      )}

      </div>
    </div>
  );
};

/* ═══════════════════════════════════════════════
   RIDER STATS MODAL — with PDF export
═══════════════════════════════════════════════ */
const RANGE_LABELS = { week: 'Esta Semana', month: 'Este Mes', year: 'Este Año', custom: 'Personalizado' };
const MONTH_NAMES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const fmtDate = (d) => { if (!d) return '—'; const p = new Date(d + (d.length === 10 ? 'T12:00:00' : '')); return isNaN(p) ? d : p.toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' }); };
const fmt = (n) => `$${parseInt(n || 0).toLocaleString('es-CL')}`;

const RiderStatsModal = ({ stats, loading, range, customFrom, customTo, onRangeChange, onClose, formatPrice }) => {
  if (!stats) return null;
  const { rider, summary, weekly, monthly, daily, lifetime, payouts, dateFrom, dateTo } = stats;

  // Bar chart helpers
  const maxEarned = Math.max(...(weekly || []).map(w => parseFloat(w.earned) || 0), 1);

  const exportPDF = async () => {
    const { jsPDF } = await import('jspdf');
    const autoTable = (await import('jspdf-autotable')).default;

    const doc = new jsPDF('p', 'mm', 'a4');
    const green = [34, 197, 94];
    const dark = [47, 58, 64];
    const orange = [249, 115, 22];
    let y = 15;

    // Header
    doc.setFillColor(...green);
    doc.rect(0, 0, 210, 38, 'F');
    const logoData = await loadImageAsBase64(huellaPng);
    if (logoData) doc.addImage(logoData, 'PNG', 10, 5, 28, 28);
    const textX = logoData ? 42 : 14;
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(20);
    doc.setFont('helvetica', 'bold');
    doc.text('PetsGo - Reporte de Rider', textX, 18);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    doc.text(`${rider.name} · ${rider.email}`, textX, 26);
    doc.text(`Período: ${fmtDate(dateFrom)} — ${fmtDate(dateTo)} (${RANGE_LABELS[range] || range})`, textX, 33);
    y = 46;

    // Summary KPIs
    doc.setTextColor(...dark);
    doc.setFontSize(13);
    doc.setFont('helvetica', 'bold');
    doc.text('Resumen del Período', 14, y); y += 8;

    const kpis = [
      ['Entregas', String(summary.deliveries)],
      ['Ganado', fmt(summary.earned)],
      ['Km recorridos', summary.totalKm + ' km'],
      ['Promedio/entrega', fmt(summary.avgEarning)],
      ['Km promedio', summary.avgKm + ' km'],
      ['Pagado en período', fmt(summary.totalPaidPeriod)],
    ];
    autoTable(doc, {
      startY: y, head: [['Métrica', 'Valor']], body: kpis,
      theme: 'grid', headStyles: { fillColor: green, textColor: 255, fontStyle: 'bold', fontSize: 10 },
      styles: { fontSize: 10, cellPadding: 4 }, columnStyles: { 0: { fontStyle: 'bold' } },
      margin: { left: 14, right: 14 },
    });
    y = doc.lastAutoTable.finalY + 10;

    // Lifetime stats
    doc.setFontSize(13);
    doc.setFont('helvetica', 'bold');
    doc.text('Estadísticas Globales (Lifetime)', 14, y); y += 8;
    const ltRows = [
      ['Total entregas', String(lifetime.deliveries)],
      ['Total ganado', fmt(lifetime.earned)],
      ['Tasa aceptación', lifetime.acceptanceRate + '%'],
      ['Rating promedio', lifetime.avgRating ? '⭐ ' + lifetime.avgRating : '—'],
      ['Total valoraciones', String(lifetime.totalRatings)],
    ];
    autoTable(doc, {
      startY: y, head: [['Métrica', 'Valor']], body: ltRows,
      theme: 'grid', headStyles: { fillColor: orange, textColor: 255, fontStyle: 'bold', fontSize: 10 },
      styles: { fontSize: 10, cellPadding: 4 }, columnStyles: { 0: { fontStyle: 'bold' } },
      margin: { left: 14, right: 14 },
    });
    y = doc.lastAutoTable.finalY + 10;

    // Weekly breakdown
    if (weekly && weekly.length > 0) {
      if (y > 240) { doc.addPage(); y = 15; }
      doc.setFontSize(13);
      doc.setFont('helvetica', 'bold');
      doc.text('Desglose Semanal', 14, y); y += 8;
      const wRows = weekly.map(w => [
        `${fmtDate(w.week_start)} — ${fmtDate(w.week_end)}`,
        String(w.deliveries),
        fmt(w.earned),
        (parseFloat(w.km) || 0).toFixed(1) + ' km'
      ]);
      autoTable(doc, {
        startY: y, head: [['Semana', 'Entregas', 'Ganado', 'Km']], body: wRows,
        theme: 'striped', headStyles: { fillColor: dark, textColor: 255, fontStyle: 'bold', fontSize: 10 },
        styles: { fontSize: 9, cellPadding: 3 }, margin: { left: 14, right: 14 },
      });
      y = doc.lastAutoTable.finalY + 10;
    }

    // Monthly breakdown
    if (monthly && monthly.length > 0) {
      if (y > 240) { doc.addPage(); y = 15; }
      doc.setFontSize(13);
      doc.setFont('helvetica', 'bold');
      doc.text('Desglose Mensual', 14, y); y += 8;
      const mRows = monthly.map(m => {
        const [yr, mo] = m.month.split('-');
        return [MONTH_NAMES[parseInt(mo) - 1] + ' ' + yr, String(m.deliveries), fmt(m.earned), (parseFloat(m.km) || 0).toFixed(1) + ' km'];
      });
      autoTable(doc, {
        startY: y, head: [['Mes', 'Entregas', 'Ganado', 'Km']], body: mRows,
        theme: 'striped', headStyles: { fillColor: [139, 92, 246], textColor: 255, fontStyle: 'bold', fontSize: 10 },
        styles: { fontSize: 9, cellPadding: 3 }, margin: { left: 14, right: 14 },
      });
      y = doc.lastAutoTable.finalY + 10;
    }

    // Payouts
    if (payouts && payouts.length > 0) {
      if (y > 240) { doc.addPage(); y = 15; }
      doc.setFontSize(13);
      doc.setFont('helvetica', 'bold');
      doc.text('Historial de Pagos', 14, y); y += 8;
      const pRows = payouts.map(p => [
        `${fmtDate(p.period_start)} — ${fmtDate(p.period_end)}`,
        String(p.total_deliveries),
        fmt(p.net_amount),
        p.status === 'paid' ? 'Pagado' : p.status === 'pending' ? 'Pendiente' : p.status,
        p.paid_at ? fmtDate(p.paid_at) : '—'
      ]);
      autoTable(doc, {
        startY: y, head: [['Período', 'Entregas', 'Neto', 'Estado', 'Pagado']], body: pRows,
        theme: 'striped', headStyles: { fillColor: [0, 168, 232], textColor: 255, fontStyle: 'bold', fontSize: 10 },
        styles: { fontSize: 9, cellPadding: 3 }, margin: { left: 14, right: 14 },
      });
    }

    // Footer
    const pages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pages; i++) {
      doc.setPage(i);
      doc.setFontSize(8);
      doc.setTextColor(150);
      doc.text(`PetsGo · Reporte generado ${new Date().toLocaleDateString('es-CL')} · Página ${i}/${pages}`, 14, 290);
    }

    doc.save(`Reporte_Rider_${rider.name.replace(/\s+/g,'_')}_${dateFrom}_${dateTo}.pdf`);
  };

  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}
      onClick={onClose}>
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)' }} />
      <div onClick={e => e.stopPropagation()} style={{
        position: 'relative', background: '#fff', borderRadius: 20, width: '100%', maxWidth: 900,
        maxHeight: '90vh', display: 'flex', flexDirection: 'column', boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
        overflow: 'hidden',
      }}>
        {/* Header */}
        <div style={{ padding: '20px 24px', background: 'linear-gradient(135deg, #2F3A40, #1a2328)', color: '#fff', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
          <div>
            <h3 style={{ margin: 0, fontWeight: 900, fontSize: 18, display: 'flex', alignItems: 'center', gap: 8 }}>
              <Truck size={22} /> {rider.name}
            </h3>
            <p style={{ margin: '4px 0 0', fontSize: 12, opacity: 0.7 }}>
              {rider.email} · {rider.vehicle} · {rider.region}
            </p>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={exportPDF} style={{ padding: '8px 16px', background: '#22C55E', color: '#fff', border: 'none', borderRadius: 10, fontWeight: 700, fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}>
              <Download size={14} /> Exportar PDF
            </button>
            <button onClick={onClose} style={{ padding: '8px 12px', background: 'rgba(255,255,255,0.15)', color: '#fff', border: 'none', borderRadius: 10, fontWeight: 700, cursor: 'pointer', fontSize: 16 }}>✕</button>
          </div>
        </div>

        {/* Range selector */}
        <div style={{ padding: '12px 24px', background: '#f9fafb', borderBottom: '1px solid #e5e7eb', display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          {['week', 'month', 'year', 'custom'].map(r => (
            <button key={r} onClick={() => r !== 'custom' && onRangeChange(r)} style={{
              padding: '6px 14px', borderRadius: 8, fontSize: 12, fontWeight: 700, border: 'none', cursor: 'pointer',
              background: range === r ? '#2F3A40' : '#fff', color: range === r ? '#FFC400' : '#6b7280',
              boxShadow: range === r ? '0 2px 8px rgba(47,58,64,0.3)' : '0 1px 3px rgba(0,0,0,0.1)',
            }}>
              {RANGE_LABELS[r]}
            </button>
          ))}
          {range === 'custom' && (
            <>
              <input type="date" value={customFrom} onChange={e => onRangeChange('custom', e.target.value, undefined)}
                style={{ padding: '6px 10px', borderRadius: 8, border: '1px solid #d1d5db', fontSize: 12 }} />
              <span style={{ color: '#9ca3af', fontSize: 12 }}>—</span>
              <input type="date" value={customTo} onChange={e => onRangeChange('custom', undefined, e.target.value)}
                style={{ padding: '6px 10px', borderRadius: 8, border: '1px solid #d1d5db', fontSize: 12 }} />
              <button onClick={() => onRangeChange('custom', customFrom, customTo)} style={{
                padding: '6px 12px', borderRadius: 8, fontSize: 12, fontWeight: 700, background: '#00A8E8', color: '#fff', border: 'none', cursor: 'pointer'
              }}>Aplicar</button>
            </>
          )}
          <span style={{ marginLeft: 'auto', fontSize: 11, color: '#9ca3af' }}>
            {fmtDate(dateFrom)} — {fmtDate(dateTo)}
          </span>
        </div>

        {/* Body */}
        <div style={{ flex: 1, overflowY: 'auto', padding: 24 }}>
          {loading ? (
            <div style={{ textAlign: 'center', padding: 60 }}>
              <div style={{ fontSize: 32 }}>⏳</div>
              <p style={{ color: '#9ca3af', fontWeight: 700, marginTop: 8 }}>Cargando estadísticas...</p>
            </div>
          ) : (
            <>
              {/* KPI cards */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: 12, marginBottom: 24 }}>
                {[
                  { label: 'Entregas', value: summary.deliveries, color: '#00A8E8', icon: '📦' },
                  { label: 'Ganado', value: fmt(summary.earned), color: '#22C55E', icon: '💰' },
                  { label: 'Km recorridos', value: summary.totalKm + ' km', color: '#8B5CF6', icon: '🛣️' },
                  { label: 'Prom./entrega', value: fmt(summary.avgEarning), color: '#F97316', icon: '📊' },
                  { label: 'Pagado', value: fmt(summary.totalPaidPeriod), color: '#2F3A40', icon: '💸' },
                ].map((k, i) => (
                  <div key={i} style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 14, padding: 16, borderLeft: `4px solid ${k.color}` }}>
                    <div style={{ fontSize: 20, marginBottom: 4 }}>{k.icon}</div>
                    <p style={{ fontSize: 20, fontWeight: 900, color: k.color, margin: 0 }}>{k.value}</p>
                    <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>{k.label}</span>
                  </div>
                ))}
              </div>

              {/* Lifetime stats bar */}
              <div style={{ display: 'flex', gap: 16, marginBottom: 24, flexWrap: 'wrap' }}>
                {[
                  { label: 'Total entregas', value: lifetime.deliveries },
                  { label: 'Total ganado', value: fmt(lifetime.earned) },
                  { label: 'Aceptación', value: lifetime.acceptanceRate + '%' },
                  { label: 'Rating', value: lifetime.avgRating ? '⭐ ' + lifetime.avgRating : '—' },
                ].map((s, i) => (
                  <div key={i} style={{ flex: '1 1 100px', textAlign: 'center', background: '#f0f9ff', borderRadius: 12, padding: '12px 8px' }}>
                    <p style={{ fontWeight: 900, color: '#2F3A40', margin: 0, fontSize: 16 }}>{s.value}</p>
                    <span style={{ fontSize: 10, color: '#6b7280', fontWeight: 600 }}>{s.label} (lifetime)</span>
                  </div>
                ))}
              </div>

              {/* Weekly chart (visual bars) */}
              {weekly && weekly.length > 0 && (
                <div style={{ marginBottom: 24 }}>
                  <h4 style={{ fontWeight: 800, color: '#2F3A40', margin: '0 0 12px', fontSize: 14 }}>📊 Ganancias Semanales</h4>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                    {weekly.map((w, i) => {
                      const pct = (parseFloat(w.earned) / maxEarned) * 100;
                      return (
                        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                          <span style={{ width: 160, fontSize: 11, fontWeight: 600, color: '#6b7280', flexShrink: 0 }}>
                            {fmtDate(w.week_start)} — {fmtDate(w.week_end)}
                          </span>
                          <div style={{ flex: 1, background: '#f3f4f6', borderRadius: 6, height: 24, overflow: 'hidden' }}>
                            <div style={{ width: `${Math.max(pct, 2)}%`, background: 'linear-gradient(90deg, #22C55E, #16a34a)', height: '100%', borderRadius: 6, display: 'flex', alignItems: 'center', justifyContent: 'flex-end', paddingRight: 8, transition: 'width 0.5s', minWidth: 50 }}>
                              <span style={{ fontSize: 10, fontWeight: 800, color: '#fff' }}>{fmt(w.earned)}</span>
                            </div>
                          </div>
                          <span style={{ fontSize: 11, color: '#9ca3af', width: 50, textAlign: 'right', flexShrink: 0 }}>{w.deliveries} ent.</span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Monthly breakdown */}
              {monthly && monthly.length > 0 && (
                <div style={{ marginBottom: 24 }}>
                  <h4 style={{ fontWeight: 800, color: '#2F3A40', margin: '0 0 12px', fontSize: 14 }}>📅 Desglose Mensual</h4>
                  <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                      <thead>
                        <tr style={{ borderBottom: '2px solid #e5e7eb' }}>
                          <th style={{ textAlign: 'left', padding: '8px 12px', fontWeight: 700, color: '#6b7280', fontSize: 11, textTransform: 'uppercase' }}>Mes</th>
                          <th style={{ textAlign: 'right', padding: '8px 12px', fontWeight: 700, color: '#6b7280', fontSize: 11, textTransform: 'uppercase' }}>Entregas</th>
                          <th style={{ textAlign: 'right', padding: '8px 12px', fontWeight: 700, color: '#6b7280', fontSize: 11, textTransform: 'uppercase' }}>Ganado</th>
                          <th style={{ textAlign: 'right', padding: '8px 12px', fontWeight: 700, color: '#6b7280', fontSize: 11, textTransform: 'uppercase' }}>Km</th>
                        </tr>
                      </thead>
                      <tbody>
                        {monthly.map((m, i) => {
                          const [yr, mo] = m.month.split('-');
                          return (
                            <tr key={i} style={{ borderBottom: '1px solid #f3f4f6' }}>
                              <td style={{ padding: '10px 12px', fontWeight: 700, color: '#2F3A40' }}>{MONTH_NAMES[parseInt(mo) - 1]} {yr}</td>
                              <td style={{ padding: '10px 12px', textAlign: 'right', color: '#2F3A40' }}>{m.deliveries}</td>
                              <td style={{ padding: '10px 12px', textAlign: 'right', fontWeight: 800, color: '#22C55E' }}>{fmt(m.earned)}</td>
                              <td style={{ padding: '10px 12px', textAlign: 'right', color: '#8B5CF6' }}>{(parseFloat(m.km) || 0).toFixed(1)}</td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Payouts */}
              {payouts && payouts.length > 0 && (
                <div>
                  <h4 style={{ fontWeight: 800, color: '#2F3A40', margin: '0 0 12px', fontSize: 14 }}>💸 Pagos en el Período</h4>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                    {payouts.map(p => {
                      const colors = { paid: '#22C55E', pending: '#F97316', failed: '#EF4444' };
                      const labels = { paid: 'Pagado', pending: 'Pendiente', failed: 'Fallido' };
                      return (
                        <div key={p.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f9fafb', borderRadius: 10, padding: '10px 14px', border: '1px solid #e5e7eb', flexWrap: 'wrap', gap: 8 }}>
                          <div>
                            <span style={{ fontSize: 12, fontWeight: 700, color: '#2F3A40' }}>{fmtDate(p.period_start)} — {fmtDate(p.period_end)}</span>
                            <span style={{ fontSize: 11, color: '#9ca3af', marginLeft: 8 }}>{p.total_deliveries} entregas</span>
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ fontWeight: 900, color: colors[p.status] || '#2F3A40', fontSize: 14 }}>{fmt(p.net_amount)}</span>
                            <span style={{ fontSize: 10, fontWeight: 700, color: colors[p.status], background: (colors[p.status] || '#6b7280') + '15', padding: '2px 8px', borderRadius: 6 }}>{labels[p.status] || p.status}</span>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Empty state */}
              {summary.deliveries === 0 && (
                <div style={{ textAlign: 'center', padding: 40 }}>
                  <Package size={48} color="#d1d5db" style={{ margin: '0 auto 12px' }} />
                  <p style={{ color: '#9ca3af', fontWeight: 700 }}>Sin entregas en este período</p>
                  <p style={{ color: '#d1d5db', fontSize: 12 }}>Intenta seleccionar otro rango de fechas</p>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;
