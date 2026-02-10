import React, { useState, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import { Truck, Package, MapPin, CheckCircle2, Clock, Phone, Navigation } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { getRiderDeliveries, updateDeliveryStatus } from '../services/api';

const STATUS_CONFIG = {
  ready_for_pickup: { label: 'Recoger', color: '#8B5CF6', next: 'in_transit', action: 'Iniciar Entrega' },
  in_transit: { label: 'En camino', color: '#F97316', next: 'delivered', action: 'Marcar Entregado' },
  delivered: { label: 'Entregado', color: '#22C55E', next: null, action: null },
};

const DEMO_DELIVERIES = [
  { id: 1051, status: 'ready_for_pickup', store_name: 'PetShop Las Condes', customer_name: 'María López', address: 'Av. Las Condes 5678, Depto 302', phone: '+56 9 8765 4321', total_amount: 52990, delivery_fee: 2990, created_at: '2026-02-10T08:30:00Z' },
  { id: 1052, status: 'in_transit', store_name: 'La Huella Store', customer_name: 'Carlos Muñoz', address: 'Calle Sucre 1234, Providencia', phone: '+56 9 1111 2222', total_amount: 38990, delivery_fee: 2990, created_at: '2026-02-10T09:15:00Z' },
  { id: 1048, status: 'delivered', store_name: 'Mundo Animal Centro', customer_name: 'Ana Torres', address: 'Av. Matta 890, Santiago', phone: '+56 9 3333 4444', total_amount: 91980, delivery_fee: 2990, created_at: '2026-02-09T14:00:00Z' },
  { id: 1045, status: 'delivered', store_name: 'Vet & Shop', customer_name: 'Pedro Soto', address: 'Av. Vitacura 3210, Vitacura', phone: '+56 9 5555 6666', total_amount: 14990, delivery_fee: 2990, created_at: '2026-02-08T11:30:00Z' },
  { id: 1040, status: 'delivered', store_name: 'Happy Pets Providencia', customer_name: 'Camila Reyes', address: 'Irarrázaval 2500, Ñuñoa', phone: '+56 9 7777 8888', total_amount: 62990, delivery_fee: 0, created_at: '2026-02-07T16:00:00Z' },
];

const RiderDashboard = () => {
  const { isAuthenticated, isRider, isAdmin } = useAuth();
  const [deliveries, setDeliveries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('active');

  useEffect(() => {
    loadDeliveries();
  }, []);

  const loadDeliveries = async () => {
    setLoading(true);
    try {
      const { data } = await getRiderDeliveries();
      const d = Array.isArray(data) ? data : [];
      setDeliveries(d.length > 0 ? d : DEMO_DELIVERIES);
    } catch (err) {
      console.error('Error cargando entregas:', err);
      setDeliveries(DEMO_DELIVERIES);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated || (!isRider() && !isAdmin())) return <Navigate to="/login" />;

  const formatPrice = (price) => `$${parseInt(price || 0).toLocaleString('es-CL')}`;

  const handleStatusUpdate = async (orderId, newStatus) => {
    try {
      await updateDeliveryStatus(orderId, newStatus);
      loadDeliveries();
    } catch (err) {
      alert('Error actualizando estado de entrega');
    }
  };

  const activeDeliveries = deliveries.filter((d) => d.status !== 'delivered');
  const completedDeliveries = deliveries.filter((d) => d.status === 'delivered');
  const displayedDeliveries = filter === 'active' ? activeDeliveries : completedDeliveries;

  return (
    <div className="max-w-4xl mx-auto px-4 lg:px-8 py-8">
      <div className="flex items-center gap-3 mb-8">
        <div className="w-12 h-12 bg-[#F97316] rounded-2xl flex items-center justify-center">
          <Truck size={24} className="text-white" />
        </div>
        <div>
          <h2 className="text-2xl font-black text-[#2F3A40]">Panel de Rider</h2>
          <p className="text-sm text-gray-400 font-medium">Gestiona tus entregas</p>
        </div>
      </div>

      {/* Resumen rapido */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <div className="petsgo-card p-5">
          <span className="text-xs text-gray-400 font-bold">Pendientes</span>
          <p className="text-2xl font-black text-[#F97316]">{activeDeliveries.length}</p>
        </div>
        <div className="petsgo-card p-5">
          <span className="text-xs text-gray-400 font-bold">Completadas</span>
          <p className="text-2xl font-black text-[#22C55E]">{completedDeliveries.length}</p>
        </div>
        <div className="petsgo-card p-5 hidden md:block">
          <span className="text-xs text-gray-400 font-bold">Total Entregas</span>
          <p className="text-2xl font-black text-[#2F3A40]">{deliveries.length}</p>
        </div>
      </div>

      {/* Filtros */}
      <div className="flex gap-2 mb-6">
        <button
          onClick={() => setFilter('active')}
          className={`px-5 py-2.5 rounded-xl text-sm font-bold transition-all ${
            filter === 'active' ? 'bg-[#F97316] text-white shadow-lg' : 'bg-white text-gray-500 border border-gray-200'
          }`}
        >
          🚀 Activas ({activeDeliveries.length})
        </button>
        <button
          onClick={() => setFilter('completed')}
          className={`px-5 py-2.5 rounded-xl text-sm font-bold transition-all ${
            filter === 'completed' ? 'bg-[#22C55E] text-white shadow-lg' : 'bg-white text-gray-500 border border-gray-200'
          }`}
        >
          ✅ Completadas ({completedDeliveries.length})
        </button>
      </div>

      {/* Lista de entregas */}
      {loading ? (
        <div className="space-y-4">
          {[...Array(3)].map((_, i) => (
            <div key={i} className="petsgo-card p-6 animate-pulse">
              <div className="h-4 bg-gray-200 rounded w-1/3 mb-3" />
              <div className="h-3 bg-gray-200 rounded w-1/2 mb-2" />
              <div className="h-3 bg-gray-200 rounded w-1/4" />
            </div>
          ))}
        </div>
      ) : displayedDeliveries.length === 0 ? (
        <div className="text-center py-16 petsgo-card">
          <Truck size={48} className="mx-auto text-gray-300 mb-4" />
          <p className="text-gray-400 font-bold">
            {filter === 'active' ? 'No tienes entregas pendientes' : 'No hay entregas completadas'}
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {displayedDeliveries.map((delivery) => {
            const statusCfg = STATUS_CONFIG[delivery.status] || { label: delivery.status, color: '#6B7280' };
            return (
              <div key={delivery.id} className="petsgo-card p-6">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <span className="text-xs font-bold text-gray-400">Pedido #{delivery.id}</span>
                    <p className="font-bold text-[#2F3A40] mt-1 flex items-center gap-2">
                      <Package size={14} className="text-gray-400" />
                      {delivery.store_name || 'Tienda PetsGo'}
                    </p>
                  </div>
                  <span
                    className="px-3 py-1.5 rounded-full text-xs font-bold"
                    style={{ backgroundColor: statusCfg.color + '15', color: statusCfg.color }}
                  >
                    {statusCfg.label}
                  </span>
                </div>

                <div className="flex items-center gap-4 text-sm text-gray-500 mb-4">
                  <span className="font-bold">Total: <strong className="text-[#2F3A40]">{formatPrice(delivery.total_amount)}</strong></span>
                  <span className="font-bold">Delivery: <strong className="text-[#00A8E8]">{formatPrice(delivery.delivery_fee)}</strong></span>
                </div>

                {statusCfg.next && statusCfg.action && (
                  <button
                    onClick={() => handleStatusUpdate(delivery.id, statusCfg.next)}
                    className="w-full py-3 rounded-xl font-bold text-sm text-white transition-all hover:opacity-90"
                    style={{ backgroundColor: statusCfg.color }}
                  >
                    <div className="flex items-center justify-center gap-2">
                      {statusCfg.next === 'in_transit' ? <Navigation size={16} /> : <CheckCircle2 size={16} />}
                      {statusCfg.action}
                    </div>
                  </button>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default RiderDashboard;
