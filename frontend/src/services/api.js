/**
 * PetsGo API Service
 * Capa de comunicación con el backend WordPress Headless.
 * Compatible con Web y futura App Móvil (misma lógica HTTP).
 */
import axios from 'axios';

// En desarrollo usa proxy de Vite, en producción la URL real
const API_BASE = import.meta.env.VITE_API_URL || '/wp-json/petsgo/v1';

const api = axios.create({
  baseURL: API_BASE,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor: añadir token de autenticación si existe
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('petsgo_token');
  const nonce = localStorage.getItem('petsgo_nonce');
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`;
  }
  if (nonce) {
    config.headers['X-WP-Nonce'] = nonce;
  }
  return config;
});

// Interceptor: manejar errores globalmente
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('petsgo_token');
      localStorage.removeItem('petsgo_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// ==========================================
// ENDPOINTS PÚBLICOS
// ==========================================

/** Obtener tiendas activas */
export const getVendors = (page = 1, perPage = 20) =>
  api.get('/vendors', { params: { page, per_page: perPage } });

/** Detalle de una tienda */
export const getVendorDetail = (id) =>
  api.get(`/vendors/${id}`);

/** Catálogo de productos con filtros */
export const getProducts = ({ vendorId, category, search, page = 1, perPage = 20 } = {}) =>
  api.get('/products', {
    params: {
      vendor_id: vendorId,
      category,
      search,
      page,
      per_page: perPage,
    },
  });

/** Detalle de producto */
export const getProductDetail = (id) =>
  api.get(`/products/${id}`);

/** Planes de suscripción */
export const getPlans = () =>
  api.get('/plans');

// ==========================================
// AUTENTICACIÓN (Web + App Móvil)
// ==========================================

/** Login */
export const login = (username, password) =>
  api.post('/auth/login', { username, password });

/** Registro de cliente */
export const register = (username, email, password) =>
  api.post('/auth/register', { username, email, password });

// ==========================================
// CLIENTE AUTENTICADO
// ==========================================

/** Crear pedido */
export const createOrder = (vendorId, items, deliveryFee = 0) =>
  api.post('/orders', { vendor_id: vendorId, items, delivery_fee: deliveryFee });

/** Mis pedidos */
export const getMyOrders = () =>
  api.get('/orders/mine');

// ==========================================
// VENDOR
// ==========================================

/** Inventario del vendor */
export const getVendorInventory = () =>
  api.get('/vendor/inventory');

/** Agregar producto */
export const addProduct = (product) =>
  api.post('/vendor/inventory', product);

/** Actualizar producto */
export const updateProduct = (id, data) =>
  api.put(`/vendor/inventory/${id}`, data);

/** Eliminar producto */
export const deleteProduct = (id) =>
  api.delete(`/vendor/inventory/${id}`);

/** Dashboard del vendor */
export const getVendorDashboard = () =>
  api.get('/vendor/dashboard');

/** Pedidos del vendor */
export const getVendorOrders = (status) =>
  api.get('/vendor/orders', { params: { status } });

/** Actualizar estado de pedido */
export const updateOrderStatus = (orderId, status) =>
  api.put(`/vendor/orders/${orderId}/status`, { status });

// ==========================================
// RIDER
// ==========================================

/** Entregas asignadas */
export const getRiderDeliveries = () =>
  api.get('/rider/deliveries');

/** Actualizar estado de entrega */
export const updateDeliveryStatus = (orderId, status) =>
  api.put(`/rider/deliveries/${orderId}/status`, { status });

// ==========================================
// ADMIN
// ==========================================

/** Dashboard global */
export const getAdminDashboard = () =>
  api.get('/admin/dashboard');

/** Impersonate Mode */
export const getVendorDashboardAsAdmin = (vendorId) =>
  api.get(`/admin/vendor/${vendorId}/dashboard`);

/** Actualizar comisiones */
export const updateCommissions = (vendorId, salesCommission, deliveryFeeCut) =>
  api.put(`/admin/commissions/${vendorId}`, {
    sales_commission: salesCommission,
    delivery_fee_cut: deliveryFeeCut,
  });

/** Listar todos los vendors */
export const getAdminVendors = () =>
  api.get('/admin/vendors');

export default api;
