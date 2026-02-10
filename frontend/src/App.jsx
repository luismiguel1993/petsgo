import { useState, useEffect, useRef, useCallback } from 'react'
import { Routes, Route } from 'react-router-dom'
import Header from './components/Header'
import Footer from './components/Footer'
import BotChatOverlay from './components/BotChatOverlay'
import FloatingCart from './components/FloatingCart'
import { useCart } from './context/CartContext'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
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
      <main className="flex-1 pb-8">
        <Routes>
          {/* Rutas públicas */}
          <Route path="/" element={<HomePage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/tiendas" element={<VendorsPage />} />
          <Route path="/tienda/:id" element={<VendorDetailPage />} />
          <Route path="/producto/:id" element={<ProductDetailPage />} />
          <Route path="/carrito" element={<CartPage />} />
          <Route path="/planes" element={<PlansPage />} />

          {/* Rutas autenticadas */}
          <Route path="/mis-pedidos" element={<MyOrdersPage />} />

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
