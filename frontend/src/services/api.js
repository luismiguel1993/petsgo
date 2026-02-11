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
  withCredentials: true,
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
      // Only redirect if we think the user should be logged in
      // (token exists but server rejected it = session expired)
      const hadToken = localStorage.getItem('petsgo_token');
      if (hadToken) {
        localStorage.removeItem('petsgo_token');
        localStorage.removeItem('petsgo_nonce');
        localStorage.removeItem('petsgo_user');
        // Don't hard redirect — let the component handle it
      }
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

/** Registro de cliente (completo) */
export const register = (data) =>
  api.post('/auth/register', data);

/** Registro de rider */
export const registerRider = (data) =>
  api.post('/auth/register-rider', data);

/** Solicitar reset de contraseña */
export const forgotPassword = (email) =>
  api.post('/auth/forgot-password', { email });

/** Restablecer contraseña con token */
export const resetPassword = (token, password) =>
  api.post('/auth/reset-password', { token, password });

// ==========================================
// PERFIL DE USUARIO
// ==========================================

/** Obtener perfil del usuario autenticado */
export const getProfile = () =>
  api.get('/profile');

/** Actualizar perfil */
export const updateProfile = (data) =>
  api.put('/profile', data);

/** Cambiar contraseña */
export const changePassword = (currentPassword, newPassword) =>
  api.post('/profile/change-password', { currentPassword, newPassword });

// ==========================================
// MASCOTAS
// ==========================================

/** Obtener mascotas del usuario */
export const getPets = () =>
  api.get('/pets');

/** Agregar mascota */
export const addPet = (data) =>
  api.post('/pets', data);

/** Actualizar mascota */
export const updatePet = (id, data) =>
  api.put(`/pets/${id}`, data);

/** Eliminar mascota */
export const deletePet = (id) =>
  api.delete(`/pets/${id}`);

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

// ==========================================
// VENDOR LEADS
// ==========================================

/** Enviar formulario de contacto (tienda interesada) */
export const submitVendorLead = (data) =>
  api.post('/vendor-lead', data);

export default api;
