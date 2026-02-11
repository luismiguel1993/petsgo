import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ShoppingCart, User, Search, Menu, X, LogOut,
  Store, Package, Truck, Shield, ShieldCheck, ChevronDown, MapPin,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import logoSvg from '../assets/Logo y nombre-1.svg';

const COMUNAS_RM = [
  'Santiago Centro', 'Providencia', 'Las Condes', 'Vitacura', 'Lo Barnechea',
  'Ñuñoa', 'La Reina', 'Peñalolén', 'Macul', 'San Joaquín',
  'La Florida', 'Puente Alto', 'Maipú', 'Cerrillos', 'Estación Central',
  'Quinta Normal', 'Recoleta', 'Independencia', 'Conchalí', 'Huechuraba',
  'La Cisterna', 'San Miguel', 'San Bernardo', 'Lo Espejo', 'Pedro Aguirre Cerda',
  'La Granja', 'El Bosque', 'Lo Prado', 'Cerro Navia', 'Renca',
  'Quilicura', 'Pudahuel', 'Colina', 'Lampa', 'Buin',
];

const Header = ({ onSearch, searchTerm = '', onCartToggle }) => {
  const { user, isAuthenticated, logout, isAdmin, isVendor, isRider } = useAuth();
  const { totalItems } = useCart();
  const [menuOpen, setMenuOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [localSearch, setLocalSearch] = useState(searchTerm);
  const [locationOpen, setLocationOpen] = useState(false);
  const [selectedComuna, setSelectedComuna] = useState('Santiago Centro');
  const [comunaFilter, setComunaFilter] = useState('');

  const getDashboardLink = () => {
    if (isAdmin()) return '/admin';
    if (isVendor()) return '/vendor';
    if (isRider()) return '/rider';
    return '/mis-pedidos';
  };

  const handleSearchChange = (e) => {
    setLocalSearch(e.target.value);
    if (onSearch) onSearch(e.target.value);
  };

  const categories = [
    { name: 'Perros', href: '#', icon: '🐕' },
    { name: 'Gatos', href: '#', icon: '🐱' },
    { name: 'Alimento', href: '#', icon: '🍖' },
    { name: 'Farmacia', href: '#', icon: '💊' },
    { name: 'Ofertas', href: '#', icon: '⚡' },
  ];

  return (
    <>
      {/* Barra Superior */}
      <div className="bg-[#2F3A40] py-2 overflow-hidden">
        <div style={{ maxWidth: '1280px', margin: '0 auto', paddingLeft: '32px', paddingRight: '32px' }}>
          <div className="flex items-center justify-center gap-8 text-white text-xs font-medium" style={{ fontFamily: 'Poppins, sans-serif' }}>
            <span className="flex items-center gap-2 whitespace-nowrap">
              <Truck size={14} className="text-[#FFC400]" />
              <span className="hidden sm:inline">¡Despacho gratis! desde $39.990</span>
              <span className="sm:hidden">Despacho gratis</span>
            </span>
            <span className="hidden md:flex items-center gap-2 whitespace-nowrap">
              <ShieldCheck size={14} className="text-[#FFC400]" />
              Pago 100% seguro
            </span>
            <span className="hidden lg:flex items-center gap-2 whitespace-nowrap">
              ⚡ Express: recibe en pocas horas
            </span>
          </div>
        </div>
      </div>

      {/* Header Principal */}
      <header className="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-100">
        <div style={{ maxWidth: '1280px', margin: '0 auto', paddingLeft: '32px', paddingRight: '32px' }}>
          
          {/* Fila Principal */}
          <div className="flex items-center justify-center gap-4 lg:gap-8" style={{ paddingTop: '16px', paddingBottom: '12px' }}>
            
            {/* Logo - Más centrado */}
            <Link to="/" className="shrink-0" style={{ marginTop: '4px' }}>
              <img 
                src={logoSvg} 
                alt="PetsGo" 
                className="h-10 sm:h-12 lg:h-14 transition-transform hover:scale-105" 
              />
            </Link>

            {/* Buscador Central - Más ancho */}
            <div className="hidden md:flex flex-1 max-w-3xl">
              <div className="w-full relative">
                <input
                  type="text"
                  value={localSearch}
                  onChange={handleSearchChange}
                  placeholder="¿Qué estás buscando?"
                  className="w-full h-12 bg-white border-2 border-gray-200 rounded-xl pl-5 pr-14 focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all outline-none text-sm text-gray-700 placeholder-gray-400 shadow-sm"
                  style={{ fontFamily: 'Poppins, sans-serif' }}
                />
                <button className="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-[#00A8E8] hover:bg-[#0090c7] text-white rounded-lg flex items-center justify-center transition-colors">
                  <Search size={20} />
                </button>
              </div>
            </div>

            {/* Acciones */}
            <div className="flex items-center gap-3 lg:gap-5">

              {/* Ubicación / Comuna */}
              <div className="relative">
                <button
                  onClick={() => setLocationOpen(!locationOpen)}
                  className="hidden sm:flex items-center gap-1.5 px-3 py-2 bg-gray-50 hover:bg-sky-50 rounded-xl transition-all text-sm"
                  style={{ fontFamily: 'Poppins, sans-serif' }}
                  title="Seleccionar comuna"
                >
                  <MapPin size={16} className="text-[#00A8E8]" />
                  <span className="font-medium text-gray-600 max-w-[120px] truncate">{selectedComuna}</span>
                  <ChevronDown size={14} className="text-gray-400" />
                </button>

                {locationOpen && (
                  <div
                    style={{
                      position: 'absolute', right: 0, top: '48px', background: '#fff',
                      borderRadius: '14px', boxShadow: '0 12px 40px rgba(0,0,0,0.15)',
                      border: '1px solid #f0f0f0', width: '260px', zIndex: 60,
                      maxHeight: '340px', display: 'flex', flexDirection: 'column',
                      fontFamily: 'Poppins, sans-serif',
                    }}
                  >
                    <div style={{ padding: '12px 14px', borderBottom: '1px solid #f3f4f6' }}>
                      <input
                        type="text"
                        placeholder="Buscar comuna..."
                        value={comunaFilter}
                        onChange={(e) => setComunaFilter(e.target.value)}
                        style={{
                          width: '100%', padding: '8px 12px', border: '1.5px solid #e5e7eb',
                          borderRadius: '10px', fontSize: '13px', outline: 'none',
                          fontFamily: 'Poppins, sans-serif',
                        }}
                        onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                        onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                        autoFocus
                      />
                    </div>
                    <div style={{ overflowY: 'auto', maxHeight: '270px', padding: '6px' }}>
                      {COMUNAS_RM
                        .filter(c => c.toLowerCase().includes(comunaFilter.toLowerCase()))
                        .map((comuna) => (
                          <button
                            key={comuna}
                            onClick={() => { setSelectedComuna(comuna); setLocationOpen(false); setComunaFilter(''); }}
                            style={{
                              display: 'flex', alignItems: 'center', gap: '8px',
                              width: '100%', padding: '9px 12px', border: 'none',
                              background: selectedComuna === comuna ? '#f0f9ff' : 'transparent',
                              borderRadius: '8px', cursor: 'pointer', fontSize: '13px',
                              fontWeight: selectedComuna === comuna ? 600 : 400,
                              color: selectedComuna === comuna ? '#00A8E8' : '#4b5563',
                              transition: 'all 0.15s', textAlign: 'left',
                              fontFamily: 'Poppins, sans-serif',
                            }}
                            onMouseEnter={(e) => { if (selectedComuna !== comuna) e.currentTarget.style.background = '#f9fafb'; }}
                            onMouseLeave={(e) => { if (selectedComuna !== comuna) e.currentTarget.style.background = 'transparent'; }}
                          >
                            <MapPin size={14} style={{ color: selectedComuna === comuna ? '#00A8E8' : '#9ca3af', flexShrink: 0 }} />
                            {comuna}
                          </button>
                        ))
                      }
                    </div>
                  </div>
                )}
              </div>
              
              {/* Carrito - Flotante dinámico */}
              <button 
                onClick={onCartToggle}
                className="relative flex items-center gap-2 px-3 py-2 bg-gray-50 hover:bg-[#FFC400]/20 rounded-xl transition-all group"
                title="Ver carrito"
                style={{ border: 'none', cursor: 'pointer', fontFamily: 'Poppins, sans-serif' }}
              >
                <div className="relative">
                  <ShoppingCart size={22} className="text-[#2F3A40] group-hover:text-[#00A8E8] transition-colors" />
                  {totalItems > 0 && (
                    <span className="absolute -top-2 -right-2 w-5 h-5 bg-[#FFC400] rounded-full text-[10px] text-black flex items-center justify-center font-bold shadow-md animate-pulse">
                      {totalItems}
                    </span>
                  )}
                </div>
                <span className="hidden lg:inline text-sm font-semibold text-gray-600 group-hover:text-[#00A8E8]">
                  Carrito
                </span>
              </button>

              {/* Usuario */}
              {isAuthenticated ? (
                <div className="relative">
                  <button
                    onClick={() => setUserMenuOpen(!userMenuOpen)}
                    style={{
                      display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 14px 6px 6px',
                      background: 'linear-gradient(135deg, #00A8E8, #0090c7)', color: '#fff',
                      borderRadius: '50px', fontSize: '13px', fontWeight: 700, border: 'none',
                      cursor: 'pointer', fontFamily: 'Poppins, sans-serif', transition: 'all 0.2s',
                      boxShadow: '0 2px 10px rgba(0,168,232,0.3)',
                    }}
                    onMouseEnter={e => e.currentTarget.style.boxShadow = '0 4px 16px rgba(0,168,232,0.45)'}
                    onMouseLeave={e => e.currentTarget.style.boxShadow = '0 2px 10px rgba(0,168,232,0.3)'}
                  >
                    {/* Avatar circle */}
                    <span style={{
                      width: '32px', height: '32px', borderRadius: '50%',
                      background: 'rgba(255,255,255,0.25)', display: 'flex',
                      alignItems: 'center', justifyContent: 'center', fontSize: '13px', fontWeight: 800,
                      letterSpacing: '0.5px', flexShrink: 0,
                    }}>
                      {(user.displayName || 'U').charAt(0).toUpperCase()}
                    </span>
                    <span className="hidden sm:inline" style={{ maxWidth: '100px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {user.displayName || 'Mi Cuenta'}
                    </span>
                    <ChevronDown size={14} style={{ opacity: 0.8, transition: 'transform 0.2s', transform: userMenuOpen ? 'rotate(180deg)' : 'rotate(0)' }} />
                  </button>

                  {userMenuOpen && (
                    <>
                      {/* Backdrop to close menu */}
                      <div style={{ position: 'fixed', inset: 0, zIndex: 49 }} onClick={() => setUserMenuOpen(false)} />
                      <div style={{
                        position: 'absolute', right: 0, top: '52px', background: '#fff',
                        borderRadius: '16px', boxShadow: '0 16px 48px rgba(0,0,0,0.14), 0 0 0 1px rgba(0,0,0,0.04)',
                        width: '260px', zIndex: 50, overflow: 'hidden',
                        fontFamily: 'Poppins, sans-serif',
                        animation: 'dropdownSlide 0.2s ease-out',
                      }}>
                        <style>{`
                          @keyframes dropdownSlide { from { opacity:0; transform: translateY(-8px) scale(0.97); } to { opacity:1; transform: translateY(0) scale(1); } }
                        `}</style>

                        {/* User info header */}
                        <div style={{
                          padding: '18px 18px 14px', background: 'linear-gradient(135deg, #f0f9ff, #fff)',
                          borderBottom: '1px solid #f0f5fa',
                        }}>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <div style={{
                              width: '44px', height: '44px', borderRadius: '50%',
                              background: 'linear-gradient(135deg, #00A8E8, #0077b6)', display: 'flex',
                              alignItems: 'center', justifyContent: 'center', color: '#fff',
                              fontSize: '17px', fontWeight: 800, flexShrink: 0,
                              boxShadow: '0 3px 10px rgba(0,168,232,0.3)',
                            }}>
                              {(user.displayName || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div style={{ overflow: 'hidden' }}>
                              <div style={{ fontSize: '14px', fontWeight: 700, color: '#2F3A40', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {user.displayName || 'Usuario'}
                              </div>
                              <div style={{
                                display: 'inline-flex', alignItems: 'center', gap: '4px',
                                marginTop: '3px', padding: '2px 8px', borderRadius: '50px',
                                fontSize: '11px', fontWeight: 700,
                                background: isAdmin() ? '#fef3c7' : isVendor() ? '#dbeafe' : isRider() ? '#d1fae5' : '#f0f9ff',
                                color: isAdmin() ? '#92400e' : isVendor() ? '#1e40af' : isRider() ? '#065f46' : '#0077b6',
                              }}>
                                {isAdmin() ? '🛡️ Admin' : isVendor() ? '🏪 Tienda' : isRider() ? '🚴 Rider' : '🐾 Cliente'}
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Menu items */}
                        <div style={{ padding: '6px' }}>
                          {[
                            { to: '/perfil', icon: <User size={17} />, label: 'Mi Perfil', emoji: '👤', color: '#00A8E8' },
                            {
                              to: getDashboardLink(),
                              icon: isAdmin() ? <Shield size={17} /> : isVendor() ? <Store size={17} /> : isRider() ? <Truck size={17} /> : <Package size={17} />,
                              label: isAdmin() ? 'Panel Admin' : isVendor() ? 'Mi Tienda' : isRider() ? 'Mis Entregas' : 'Mis Pedidos',
                              emoji: isAdmin() ? '⚙️' : isVendor() ? '🏪' : isRider() ? '🚚' : '📦',
                              color: isAdmin() ? '#8B5CF6' : isVendor() ? '#F59E0B' : isRider() ? '#22C55E' : '#00A8E8',
                            },
                          ].map((item, idx) => (
                            <Link key={idx} to={item.to} onClick={() => setUserMenuOpen(false)}
                              style={{
                                display: 'flex', alignItems: 'center', gap: '12px',
                                padding: '11px 14px', borderRadius: '10px', textDecoration: 'none',
                                color: '#374151', fontSize: '13px', fontWeight: 600,
                                transition: 'all 0.15s', cursor: 'pointer',
                              }}
                              onMouseEnter={e => { e.currentTarget.style.background = '#f0f9ff'; e.currentTarget.style.color = item.color; }}
                              onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = '#374151'; }}
                            >
                              <span style={{
                                width: '34px', height: '34px', borderRadius: '10px',
                                background: `${item.color}12`, display: 'flex', alignItems: 'center',
                                justifyContent: 'center', color: item.color, flexShrink: 0,
                              }}>
                                {item.icon}
                              </span>
                              {item.label}
                            </Link>
                          ))}
                        </div>

                        {/* Divider + logout */}
                        <div style={{ borderTop: '1px solid #f3f4f6', padding: '6px' }}>
                          <button
                            onClick={() => { logout(); setUserMenuOpen(false); }}
                            style={{
                              display: 'flex', alignItems: 'center', gap: '12px', width: '100%',
                              padding: '11px 14px', border: 'none', borderRadius: '10px',
                              background: 'transparent', cursor: 'pointer', fontSize: '13px',
                              fontWeight: 600, color: '#ef4444', fontFamily: 'Poppins, sans-serif',
                              transition: 'all 0.15s',
                            }}
                            onMouseEnter={e => e.currentTarget.style.background = '#fef2f2'}
                            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                          >
                            <span style={{
                              width: '34px', height: '34px', borderRadius: '10px',
                              background: '#fef2f2', display: 'flex', alignItems: 'center',
                              justifyContent: 'center', flexShrink: 0,
                            }}>
                              <LogOut size={17} />
                            </span>
                            Cerrar Sesión
                          </button>
                        </div>
                      </div>
                    </>
                  )}
                </div>
              ) : (
                <Link 
                  to="/login" 
                  className="flex items-center gap-3 bg-[#00A8E8] text-white rounded-xl text-sm font-bold hover:bg-[#0090c7] transition-all no-underline shadow-sm hover:shadow-md"
                  style={{ fontFamily: 'Poppins, sans-serif', padding: '12px 24px', letterSpacing: '0.5px' }}
                >
                  <User size={18} />
                  <span className="hidden sm:inline uppercase tracking-wide">Ingresar</span>
                </Link>
              )}

              {/* Menú Móvil */}
              <button onClick={() => setMenuOpen(!menuOpen)} className="md:hidden p-2 hover:bg-gray-100 rounded-full">
                {menuOpen ? <X size={24} /> : <Menu size={24} />}
              </button>
            </div>
          </div>

          {/* Navegación Categorías Desktop */}
          <nav className="hidden md:flex items-center justify-center gap-1 py-2 border-t border-gray-100">
            {categories.map((cat) => (
              <a
                key={cat.name}
                href={cat.href}
                className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-gray-600 hover:text-[#00A8E8] hover:bg-cyan-50 rounded-full transition-colors no-underline"
                style={{ fontFamily: 'Poppins, sans-serif' }}
              >
                <span>{cat.icon}</span>
                {cat.name}
              </a>
            ))}
            <Link
              to="/tiendas"
              className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-gray-600 hover:text-[#00A8E8] hover:bg-cyan-50 rounded-full transition-colors no-underline"
              style={{ fontFamily: 'Poppins, sans-serif' }}
            >
              <Store size={16} />
              Tiendas
            </Link>
          </nav>
        </div>

        {/* Menú Móvil */}
        {menuOpen && (
          <div className="md:hidden bg-white border-t px-4 py-4 space-y-2">
            <div className="relative mb-4">
              <input
                type="text"
                value={localSearch}
                onChange={handleSearchChange}
                placeholder="¿Qué estás buscando?"
                className="w-full h-12 bg-gray-50 border border-gray-200 rounded-full pl-5 pr-12 text-sm"
                style={{ fontFamily: 'Poppins, sans-serif' }}
              />
              <Search className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
            </div>
            
            {categories.map((cat) => (
              <a
                key={cat.name}
                href={cat.href}
                onClick={() => setMenuOpen(false)}
                className="flex items-center gap-3 py-3 px-4 text-gray-700 hover:bg-gray-50 rounded-xl no-underline font-medium"
              >
                <span className="text-lg">{cat.icon}</span>
                {cat.name}
              </a>
            ))}
            <Link 
              to="/tiendas" 
              onClick={() => setMenuOpen(false)} 
              className="flex items-center gap-3 py-3 px-4 text-gray-700 hover:bg-gray-50 rounded-xl no-underline font-medium"
            >
              <Store size={20} />
              Tiendas
            </Link>
          </div>
        )}
      </header>
    </>
  );
};

export default Header;
