import React, { useState, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import {
  BarChart3, DollarSign, Store, Users, Package, ShoppingBag, TrendingUp,
  Eye, Percent, Save, Search, Shield, PawPrint,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import {
  getAdminDashboard, getAdminVendors, getVendorDashboardAsAdmin,
  updateCommissions,
} from '../services/api';

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

  useEffect(() => {
    loadData();
  }, [tab]);

  const loadData = async () => {
    setLoading(true);
    try {
      if (tab === 'dashboard') {
        const { data } = await getAdminDashboard();
        setStats(data);
      } else if (tab === 'vendors') {
        const { data } = await getAdminVendors();
        setVendors(Array.isArray(data) ? data : []);
      }
    } catch (err) {
      console.error('Error cargando datos admin:', err);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated || !isAdmin()) return <Navigate to="/login" />;

  const formatPrice = (price) => `$${parseInt(price || 0).toLocaleString('es-CL')}`;

  // Impersonate Mode - Visualizar dashboard de tienda
  const handleImpersonate = async (vendorId, storeName) => {
    try {
      const { data } = await getVendorDashboardAsAdmin(vendorId);
      setImpersonateVendor(storeName);
      setImpersonateData(data);
    } catch (err) {
      alert('Error al acceder al dashboard de la tienda');
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
    } catch (err) {
      alert('Error actualizando comisiones');
    }
  };

  const tabs = [
    { key: 'dashboard', label: 'Dashboard Global', icon: BarChart3 },
    { key: 'vendors', label: 'Tiendas', icon: Store },
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 lg:px-8 py-8">
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
                  {vendors.map((v) => (
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
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default AdminDashboard;
