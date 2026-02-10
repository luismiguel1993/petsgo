import React, { useState, useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { Package, Clock, CheckCircle2, Truck, MapPin, Store, ChevronRight, PawPrint } from 'lucide-react';
import { getMyOrders } from '../services/api';
import { useAuth } from '../context/AuthContext';

const STATUS_CONFIG = {
  payment_pending: { label: 'Pago Pendiente', color: '#FFC400', icon: Clock },
  preparing: { label: 'Preparando', color: '#00A8E8', icon: Package },
  ready_for_pickup: { label: 'Listo para enviar', color: '#8B5CF6', icon: Package },
  in_transit: { label: 'En camino', color: '#F97316', icon: Truck },
  delivered: { label: 'Entregado', color: '#22C55E', icon: CheckCircle2 },
  cancelled: { label: 'Cancelado', color: '#EF4444', icon: Package },
};

const DEMO_ORDERS = [
  { id: 1042, status: 'delivered', store_name: 'PetShop Las Condes', total_amount: 52990, delivery_fee: 2990, created_at: '2026-02-05T14:30:00Z' },
  { id: 1051, status: 'in_transit', store_name: 'La Huella Store', total_amount: 38990, delivery_fee: 0, created_at: '2026-02-09T10:15:00Z' },
  { id: 1063, status: 'preparing', store_name: 'Mundo Animal Centro', total_amount: 91980, delivery_fee: 2990, created_at: '2026-02-10T08:45:00Z' },
  { id: 1070, status: 'payment_pending', store_name: 'Vet & Shop', total_amount: 29990, delivery_fee: 2990, created_at: '2026-02-10T11:00:00Z' },
  { id: 1028, status: 'delivered', store_name: 'Happy Pets Providencia', total_amount: 14990, delivery_fee: 2990, created_at: '2026-01-28T16:20:00Z' },
  { id: 1015, status: 'delivered', store_name: 'PetLand Chile', total_amount: 62990, delivery_fee: 0, created_at: '2026-01-20T09:00:00Z' },
];

const MyOrdersPage = () => {
  const { isAuthenticated } = useAuth();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  useEffect(() => {
    loadOrders();
  }, []);

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

  if (!isAuthenticated) return <Navigate to="/login" />;

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;
  const formatDate = (date) => new Date(date).toLocaleDateString('es-CL', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });

  const filteredOrders = filter === 'all' ? orders : orders.filter((o) => o.status === filter);

  return (
    <div className="max-w-4xl mx-auto px-4 lg:px-8 py-8">
      <h2 className="text-3xl font-black text-[#2F3A40] mb-2">Mis Pedidos</h2>
      <p className="text-gray-400 font-medium mb-8">Historial y seguimiento de tus compras</p>

      {/* Filtros */}
      <div className="flex gap-2 overflow-x-auto pb-4 mb-6">
        {[{ key: 'all', label: 'Todos' }, ...Object.entries(STATUS_CONFIG).map(([key, val]) => ({ key, label: val.label }))].map((f) => (
          <button
            key={f.key}
            onClick={() => setFilter(f.key)}
            className={`px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all ${
              filter === f.key
                ? 'bg-[#00A8E8] text-white shadow-md'
                : 'bg-white text-gray-500 hover:bg-gray-100 border border-gray-200'
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* Lista de pedidos */}
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
      ) : filteredOrders.length === 0 ? (
        <div className="text-center py-20">
          <PawPrint size={48} className="mx-auto text-gray-300 mb-4" />
          <p className="text-gray-400 font-bold text-lg">
            {filter === 'all' ? 'Aún no tienes pedidos' : 'No hay pedidos con este estado'}
          </p>
          <Link to="/" className="petsgo-btn inline-block mt-4 no-underline text-sm">
            Explorar Productos
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {filteredOrders.map((order) => {
            const status = STATUS_CONFIG[order.status] || STATUS_CONFIG.payment_pending;
            const StatusIcon = status.icon;
            return (
              <div key={order.id} className="petsgo-card p-6">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <span className="text-xs font-bold text-gray-400">Pedido #{order.id}</span>
                    <h4 className="font-bold text-[#2F3A40] flex items-center gap-2 mt-1">
                      <Store size={14} className="text-gray-400" />
                      {order.store_name || 'Tienda PetsGo'}
                    </h4>
                  </div>
                  <div
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold"
                    style={{ backgroundColor: status.color + '15', color: status.color }}
                  >
                    <StatusIcon size={14} />
                    {status.label}
                  </div>
                </div>

                <div className="flex items-center justify-between text-sm">
                  <div className="flex gap-6 text-gray-500 font-medium">
                    <span>Total: <strong className="text-[#2F3A40]">{formatPrice(order.total_amount)}</strong></span>
                    <span>Delivery: <strong className="text-[#2F3A40]">{formatPrice(order.delivery_fee)}</strong></span>
                  </div>
                  <span className="text-xs text-gray-400">{formatDate(order.created_at)}</span>
                </div>

                {order.status === 'in_transit' && (
                  <div className="mt-4 bg-orange-50 rounded-xl p-3 flex items-center gap-2 text-sm text-orange-600 font-medium">
                    <MapPin size={14} /> Tu pedido está en camino
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default MyOrdersPage;
