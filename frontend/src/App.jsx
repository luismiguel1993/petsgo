import { useState, useEffect, useRef, useCallback } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import Header from './components/Header'
import Footer from './components/Footer'
import BotChatOverlay from './components/BotChatOverlay'
import FloatingCart from './components/FloatingCart'
import { useCart } from './context/CartContext'
import { useAuth } from './context/AuthContext'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import RiderRegisterPage from './pages/RiderRegisterPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import UserProfilePage from './pages/UserProfilePage'
import VendorsPage from './pages/VendorsPage'
import VendorDetailPage from './pages/VendorDetailPage'
import CartPage from './pages/CartPage'
import PlansPage from './pages/PlansPage'
import ProductDetailPage from './pages/ProductDetailPage'
import CategoryPage from './pages/CategoryPage'
import MyOrdersPage from './pages/MyOrdersPage'
import VendorDashboard from './pages/VendorDashboard'
import AdminDashboard from './pages/AdminDashboard'
import RiderDashboard from './pages/RiderDashboard'
import SupportPage from './pages/SupportPage'
import HelpCenterPage from './pages/HelpCenterPage'
import LegalPage from './pages/LegalPage'
import InvoiceVerifyPage from './pages/InvoiceVerifyPage'
import ForceChangePasswordPage from './pages/ForceChangePasswordPage'
import RiderVerifyEmailPage from './pages/RiderVerifyEmailPage'

function App() {
  const [cartOpen, setCartOpen] = useState(false)
  const { setOnCartOpen } = useCart()
  const { loggedOut, user, isRider } = useAuth()
  const autoCloseTimer = useRef(null)

  // Rider logueado: restringir navegación — solo puede ver su panel
  const activeRider = isRider();
  // Rider no aprobado: mostrar banner de estado
  const isUnapprovedRider = activeRider && user?.rider_status && user.rider_status !== 'approved';
  const riderStatusLabels = {
    pending_docs: '📋 Sube tus documentos para completar tu registro como Rider.',
    pending_review: '⏳ Tus documentos están en revisión. Te notificaremos cuando sean aprobados.',
  };

  const openCartWithAutoClose = useCallback(() => {
    setCartOpen(true)
    // Limpiar timer anterior si existe
    if (autoCloseTimer.current) clearTimeout(autoCloseTimer.current)
    // Auto-cerrar después de 3 segundos
    autoCloseTimer.current = setTimeout(() => {
      setCartOpen(false)
    }, 3000)
  }, [])

  // Registrar callback en CartContext
  useEffect(() => {
    setOnCartOpen(openCartWithAutoClose)
  }, [setOnCartOpen, openCartWithAutoClose])

  // Si el usuario interactúa con el carrito, cancelar auto-close
  const handleCartInteraction = useCallback(() => {
    if (autoCloseTimer.current) clearTimeout(autoCloseTimer.current)
  }, [])

  const handleCartClose = useCallback(() => {
    if (autoCloseTimer.current) clearTimeout(autoCloseTimer.current)
    setCartOpen(false)
  }, [])

  return (
    <div className="min-h-screen flex flex-col bg-[#f5f5f5]">
      <Header onCartToggle={() => { if (!activeRider) { setCartOpen(true); handleCartInteraction(); } }} />
      {/* FloatingCart oculto para riders */}
      {!activeRider && <FloatingCart isOpen={cartOpen} onClose={handleCartClose} onInteraction={handleCartInteraction} />}

      {/* Logout toast */}
      {loggedOut && (
        <div style={{
          position: 'fixed', top: '80px', left: '50%', transform: 'translateX(-50%)', zIndex: 9999,
          background: 'linear-gradient(135deg, #2F3A40, #1a2228)', color: '#fff',
          padding: '16px 32px', borderRadius: '16px', fontSize: '14px', fontWeight: 700,
          fontFamily: 'Poppins, sans-serif', display: 'flex', alignItems: 'center', gap: '12px',
          boxShadow: '0 12px 40px rgba(0,0,0,0.25)', animation: 'logoutToastIn 0.4s ease-out',
        }}>
          <style>{`
            @keyframes logoutToastIn {
              0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
              100% { opacity: 1; transform: translateX(-50%) translateY(0); }
            }
          `}</style>
          <span style={{
            width: '36px', height: '36px', borderRadius: '50%', background: 'rgba(255,255,255,0.12)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '18px', flexShrink: 0,
          }}>👋</span>
          <div>
            <div>Sesión cerrada</div>
            <div style={{ fontSize: '12px', fontWeight: 500, opacity: 0.7, marginTop: '2px' }}>
              ¡Hasta pronto!
            </div>
          </div>
        </div>
      )}
      {/* Banner persistente para riders no aprobados */}
      {isUnapprovedRider && (
        <div style={{
          background: 'linear-gradient(135deg, #FEF3C7, #FFF7ED)',
          borderBottom: '2px solid #FCD34D',
          padding: '12px 24px',
          display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '12px',
          fontFamily: 'Poppins, sans-serif', flexWrap: 'wrap',
        }}>
          <span style={{ fontSize: '14px', fontWeight: 600, color: '#92400E' }}>
            {riderStatusLabels[user.rider_status] || '⏳ Tu cuenta de Rider está en proceso de verificación.'}
          </span>
          <a href="/rider" style={{
            padding: '6px 16px', background: '#F97316', color: '#fff', borderRadius: '8px',
            fontSize: '12px', fontWeight: 700, textDecoration: 'none',
          }}>
            Ir a mi Panel de Rider
          </a>
        </div>
      )}

      <main className="flex-1 pb-8">
        <Routes>
          {/* Rutas públicas — Riders logueados son redirigidos a su panel */}
          <Route path="/" element={activeRider ? <Navigate to="/rider" /> : <HomePage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/registro" element={<RegisterPage />} />
          <Route path="/registro-rider" element={<RiderRegisterPage />} />
          <Route path="/verificar-rider" element={<RiderVerifyEmailPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
          <Route path="/cambiar-contrasena" element={<ForceChangePasswordPage />} />
          <Route path="/tiendas" element={activeRider ? <Navigate to="/rider" /> : <VendorsPage />} />
          <Route path="/tienda/:id" element={activeRider ? <Navigate to="/rider" /> : <VendorDetailPage />} />
          <Route path="/categoria/:slug" element={activeRider ? <Navigate to="/rider" /> : <CategoryPage />} />
          <Route path="/producto/:id" element={activeRider ? <Navigate to="/rider" /> : <ProductDetailPage />} />
          <Route path="/carrito" element={activeRider ? <Navigate to="/rider" /> : <CartPage />} />
          <Route path="/planes" element={activeRider ? <Navigate to="/rider" /> : <PlansPage />} />
          <Route path="/centro-de-ayuda" element={<HelpCenterPage />} />
          <Route path="/terminos-y-condiciones" element={<LegalPage slug="terminos-y-condiciones" />} />
          <Route path="/politica-de-privacidad" element={<LegalPage slug="politica-de-privacidad" />} />
          <Route path="/politica-de-envios" element={<LegalPage slug="politica-de-envios" />} />
          <Route path="/verificar-boleta/:token" element={<InvoiceVerifyPage />} />

          {/* Rutas autenticadas — Rider va a su panel, no al perfil de cliente */}
          <Route path="/mis-pedidos" element={activeRider ? <Navigate to="/rider" /> : <MyOrdersPage />} />
          <Route path="/perfil" element={activeRider ? <Navigate to="/rider" /> : <UserProfilePage />} />
          <Route path="/soporte" element={<SupportPage />} />

          {/* Dashboards por rol */}
          <Route path="/vendor" element={<VendorDashboard />} />
          <Route path="/admin" element={<AdminDashboard />} />
          <Route path="/rider" element={<RiderDashboard />} />
        </Routes>
      </main>
      <Footer />
      <BotChatOverlay cartOpen={cartOpen} />
    </div>
  )
}

export default App
