import { useState, useEffect, useRef, useCallback } from 'react'
import { Routes, Route } from 'react-router-dom'
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
import MyOrdersPage from './pages/MyOrdersPage'
import VendorDashboard from './pages/VendorDashboard'
import AdminDashboard from './pages/AdminDashboard'
import RiderDashboard from './pages/RiderDashboard'

function App() {
  const [cartOpen, setCartOpen] = useState(false)
  const { setOnCartOpen } = useCart()
  const { loggedOut } = useAuth()
  const autoCloseTimer = useRef(null)

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
      <Header onCartToggle={() => { setCartOpen(true); handleCartInteraction(); }} />
      <FloatingCart isOpen={cartOpen} onClose={handleCartClose} onInteraction={handleCartInteraction} />

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
      <main className="flex-1 pb-8">
        <Routes>
          {/* Rutas públicas */}
          <Route path="/" element={<HomePage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/registro" element={<RegisterPage />} />
          <Route path="/registro-rider" element={<RiderRegisterPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
          <Route path="/tiendas" element={<VendorsPage />} />
          <Route path="/tienda/:id" element={<VendorDetailPage />} />
          <Route path="/producto/:id" element={<ProductDetailPage />} />
          <Route path="/carrito" element={<CartPage />} />
          <Route path="/planes" element={<PlansPage />} />

          {/* Rutas autenticadas */}
          <Route path="/mis-pedidos" element={<MyOrdersPage />} />
          <Route path="/perfil" element={<UserProfilePage />} />

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
