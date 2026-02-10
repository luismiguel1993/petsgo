import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ShoppingCart, Star, Truck, Heart, Sparkles, ChevronRight, ArrowRight, MessageCircle, ChevronLeft, Shield, Clock, Filter, PawPrint, Plus } from 'lucide-react';
import { useCart } from '../context/CartContext';
import { Minus } from 'lucide-react';

const HomePage = () => {
  const { addItem, getItemQuantity, updateQuantity } = useCart();
  const [currentSlide, setCurrentSlide] = useState(0);

  const heroImages = [
    { url: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=1200&auto=format&fit=crop&q=80', alt: 'Perro feliz con su dueña' },
    { url: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=1200&auto=format&fit=crop&q=80', alt: 'Gato curioso' },
    { url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=1200&auto=format&fit=crop&q=80', alt: 'Perro juguetón' },
    { url: 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=1200&auto=format&fit=crop&q=80', alt: 'Gato naranja' },
    { url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=1200&auto=format&fit=crop&q=80', alt: 'Dos perros corriendo' },
  ];

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % heroImages.length);
    }, 4000);
    return () => clearInterval(timer);
  }, []);

  const nextSlide = () => setCurrentSlide((prev) => (prev + 1) % heroImages.length);
  const prevSlide = () => setCurrentSlide((prev) => (prev - 1 + heroImages.length) % heroImages.length);

  const featuredProducts = [
    { id: 1, name: 'Royal Canin Medium Adult 15kg', brand: 'Royal Canin', price: 52990, originalPrice: 59990, image: 'https://images.unsplash.com/photo-1589924749359-6852750f50e8?w=400&auto=format&fit=crop&q=80', category: 'Alimento Perros', rating: 4.8, description: 'Alimento seco completo para perros adultos de raza mediana (11-25 kg). Con nutrientes que fortalecen las defensas naturales.' },
    { id: 2, name: 'Pro Plan Gato Adulto Pollo 7.5kg', brand: 'Pro Plan', price: 38990, originalPrice: 42990, image: 'https://images.unsplash.com/photo-1615497001839-b0a0eac3274c?w=400&auto=format&fit=crop&q=80', category: 'Alimento Gatos', rating: 4.7, description: 'Fórmula avanzada con pollo real como ingrediente principal. Rico en proteínas para gatos adultos activos.' },
    { id: 3, name: 'Hills Science Diet Puppy 12kg', brand: 'Hills', price: 48990, originalPrice: null, image: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&auto=format&fit=crop&q=80', category: 'Alimento Perros', rating: 4.9, description: 'Nutrición clínicamente probada para cachorros en crecimiento. DHA de aceite de pescado para desarrollo cerebral.' },
    { id: 4, name: 'Whiskas Adulto Pollo 10kg', brand: 'Whiskas', price: 12990, originalPrice: 15990, image: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=400&auto=format&fit=crop&q=80', category: 'Alimento Gatos', rating: 4.5, description: 'Alimento balanceado con sabor a pollo que a los gatos les encanta. Vitaminas y minerales esenciales.' },
    { id: 5, name: 'Eukanuba Large Breed Adult 15kg', brand: 'Eukanuba', price: 62990, originalPrice: 69990, image: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=400&auto=format&fit=crop&q=80', category: 'Alimento Perros', rating: 4.6, description: 'Formulado especialmente para razas grandes. Con glucosamina y condroitina para articulaciones saludables.' },
    { id: 6, name: 'Collar LED Recargable USB', brand: 'PetSafe', price: 14990, originalPrice: 19990, image: 'https://images.unsplash.com/photo-1567612529009-afe25413fe2f?w=400&auto=format&fit=crop&q=80', category: 'Accesorios', rating: 4.4, description: 'Collar luminoso LED con 3 modos de luz. Recargable USB, resistente al agua. Ideal para paseos nocturnos.' },
    { id: 7, name: 'Bravecto Perro 10-20kg', brand: 'Bravecto', price: 29990, originalPrice: 34990, image: 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=400&auto=format&fit=crop&q=80', category: 'Farmacia', rating: 4.8, description: 'Antiparasitario oral contra pulgas y garrapatas. Protección de hasta 12 semanas con una sola dosis.' },
    { id: 8, name: 'Cama Ortopédica Premium L', brand: 'PetsGo Select', price: 45990, originalPrice: 54990, image: 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?w=400&auto=format&fit=crop&q=80', category: 'Accesorios', rating: 4.7, description: 'Cama ortopédica con espuma viscoelástica. Funda lavable antialérgica. Ideal para perros grandes.' },
  ];

  const categories = [
    { name: 'Perros', emoji: '🐕', count: '+500 productos', link: '/tiendas' },
    { name: 'Gatos', emoji: '🐱', count: '+350 productos', link: '/tiendas' },
    { name: 'Alimento', emoji: '🍖', count: 'Seco y húmedo', link: '/tiendas' },
    { name: 'Snacks', emoji: '🦴', count: 'Premios y dental', link: '/tiendas' },
    { name: 'Farmacia', emoji: '💊', count: 'Antiparasitarios', link: '/tiendas' },
    { name: 'Accesorios', emoji: '🎾', count: 'Juguetes y más', link: '/tiendas' },
  ];

  const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP',
      minimumFractionDigits: 0,
    }).format(price);
  };

  const calculateDiscount = (price, originalPrice) => {
    if (!originalPrice) return null;
    return Math.round(((originalPrice - price) / originalPrice) * 100);
  };

  return (
    <div style={{ fontFamily: 'Poppins, sans-serif' }}>
      <style>{`
        .hero-wrapper { max-width: 1280px; display: flex; flex-direction: column; align-items: stretch; min-height: auto; justify-content: center; padding: 0 16px; }
        .hero-text { padding-top: 32px; padding-bottom: 24px; }
        .hero-text-inner { padding-left: 0; }
        .hero-carousel { min-height: 260px; border-radius: 0 0 20px 20px; }
        .hero-badge-float { display: none !important; }
        .hero-arrows { display: none !important; }
        .hero-h1 { font-size: 2rem; }
        .hero-p { font-size: 0.95rem; }
        .hero-btns { flex-direction: column; }
        .hero-btns > * { width: 100%; text-align: center; }
        .main-content { padding-left: 16px; padding-right: 16px; padding-bottom: 60px; }
        .benefits-bar { padding: 12px 12px !important; margin-top: -16px !important; }
        .benefits-inner { gap: 12px !important; }
        .benefits-inner > span > div:last-child p:first-child { font-size: 13px; }
        .benefits-inner > span > div:last-child p:last-child { font-size: 11px; }

        @media (min-width: 480px) {
          .hero-wrapper { padding: 0 24px; }
          .hero-carousel { min-height: 300px; }
          .hero-btns { flex-direction: row; }
          .hero-btns > * { width: auto; }
          .hero-badge-float { display: flex !important; }
          .main-content { padding-left: 24px; padding-right: 24px; }
        }

        @media (min-width: 640px) {
          .hero-wrapper { padding: 0 32px; }
          .hero-h1 { font-size: 2.75rem; }
          .hero-carousel { min-height: 360px; }
          .hero-arrows { display: flex !important; }
          .main-content { padding-left: 32px; padding-right: 32px; }
          .benefits-bar { padding: 14px 20px !important; margin-top: -20px !important; }
        }

        @media (min-width: 1024px) {
          .hero-wrapper { flex-direction: row; min-height: 480px; padding: 0 48px; }
          .hero-text { padding-top: 48px; padding-bottom: 32px; }
          .hero-text-inner { padding-left: 2.5rem; text-align: left; align-items: flex-start; }
          .hero-carousel { min-height: 420px; border-radius: 0; }
          .hero-h1 { font-size: 3.5rem; }
          .hero-p { font-size: 1.125rem; }
          .main-content { padding-left: 48px; padding-right: 48px; padding-bottom: 80px; }
          .benefits-bar { padding: 14px 28px !important; margin-top: -24px !important; }
          .benefits-inner { gap: 40px !important; }
        }
      `}</style>
      
      {/* ========== HERO SECTION (FULL WIDTH) ========== */}
      <section className="w-full bg-[#00A8E8] flex justify-center">
        <div className="hero-wrapper w-full">
          
          {/* Contenido Izquierda */}
          <div className="hero-text w-full lg:w-1/2 flex flex-col justify-start text-white relative z-10">
            <div className="hero-text-inner flex flex-col items-center lg:items-start h-full text-center lg:text-left">
              <div className="inline-flex items-center gap-2 bg-[#FFC400] px-3 py-1.5 rounded-md text-gray-900 text-xs font-bold mb-5 uppercase tracking-wide w-fit mx-auto lg:mx-0 shadow-sm">
                DESPACHO GRATIS EN TU PRIMER PEDIDO
              </div>

              <h1 className="hero-h1 font-bold leading-tight mb-5 tracking-tight max-w-xl mx-auto lg:mx-0 lg:pr-8" style={{letterSpacing: '-0.5px'}}>
                Cuidamos a los que
                <br className="hidden lg:block" />
                <span className="text-[#FFC400]"> más amas.</span>
              </h1>

              <p className="hero-p text-white/95 mb-8 max-w-lg mx-auto lg:mx-0 leading-relaxed font-medium lg:pr-8" style={{letterSpacing: '-0.2px'}}>
                La plataforma premium para dueños de mascotas. Encuentra las mejores tiendas locales con delivery asegurado.
              </p>

              <div className="hero-btns flex gap-4 justify-center lg:justify-start">
                <Link to="/tiendas" className="bg-[#FFC400] text-gray-900 font-bold rounded-xl hover:bg-yellow-400 transition-all shadow-lg hover:shadow-xl text-base uppercase tracking-wide inline-flex items-center justify-center gap-2.5 hover:-translate-y-1 hover:scale-105 active:scale-95 duration-300 no-underline" style={{ padding: '14px 32px' }}>
                  COMPRAR AHORA
                  <ArrowRight size={20} />
                </Link>
                <Link
                  to="/tiendas"
                  className="bg-white/15 backdrop-blur-sm text-white font-bold rounded-xl hover:bg-white/25 transition-all no-underline text-base uppercase tracking-wide border-2 border-white/40 inline-flex items-center justify-center hover:-translate-y-1 hover:scale-105 active:scale-95 duration-300" style={{ padding: '14px 32px' }}
                >
                  VER TIENDAS
                </Link>
              </div>
            </div>
          </div>

          {/* Carrusel Derecha */}
          <div className="hero-carousel w-full lg:w-1/2 relative overflow-hidden">
            {heroImages.map((img, index) => (
              <img
                key={index}
                src={img.url}
                alt={img.alt}
                style={{
                  position: 'absolute', top: 0, left: 0, width: '100%', height: '100%',
                  objectFit: 'cover', objectPosition: 'center 30%',
                  opacity: currentSlide === index ? 1 : 0,
                  transition: 'opacity 0.8s ease-in-out',
                }}
              />
            ))}
            {/* Indicadores */}
            <div style={{
              position: 'absolute', bottom: '20px', left: '50%', transform: 'translateX(-50%)',
              display: 'flex', gap: '8px', zIndex: 10
            }}>
              {heroImages.map((_, index) => (
                <button
                  key={index}
                  onClick={() => setCurrentSlide(index)}
                  style={{
                    width: currentSlide === index ? '28px' : '10px', height: '10px',
                    borderRadius: '5px', border: 'none', cursor: 'pointer',
                    background: currentSlide === index ? '#FFC400' : 'rgba(255,255,255,0.5)',
                    transition: 'all 0.3s ease'
                  }}
                />
              ))}
            </div>
            {/* Flechas */}
            <button onClick={prevSlide} className="hero-arrows" style={{
              position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)',
              background: 'rgba(255,255,255,0.2)', backdropFilter: 'blur(4px)',
              border: 'none', borderRadius: '50%', width: '40px', height: '40px',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              cursor: 'pointer', color: '#fff', zIndex: 10, transition: 'background 0.2s'
            }}>
              <ChevronLeft size={22} />
            </button>
            <button onClick={nextSlide} className="hero-arrows" style={{
              position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)',
              background: 'rgba(255,255,255,0.2)', backdropFilter: 'blur(4px)',
              border: 'none', borderRadius: '50%', width: '40px', height: '40px',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              cursor: 'pointer', color: '#fff', zIndex: 10, transition: 'background 0.2s'
            }}>
              <ChevronRight size={22} />
            </button>
            {/* Badge Flotante */}
            <div className="hero-badge-float" style={{
              position: 'absolute', top: '16px', right: '16px',
              background: 'rgba(255,255,255,0.95)', backdropFilter: 'blur(8px)',
              borderRadius: '12px', padding: '8px 12px', display: 'flex',
              alignItems: 'center', gap: '10px', zIndex: 20,
              boxShadow: '0 4px 16px rgba(0,0,0,0.12)'
            }}>
              <div style={{
                width: '32px', height: '32px', background: '#FFC400', borderRadius: '8px',
                display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0
              }}>
                <Truck size={16} color="#2F3A40" />
              </div>
              <div>
                <p style={{ fontWeight: 700, color: '#1f2937', fontSize: '12px', margin: 0, lineHeight: 1.3 }}>Envío Gratis</p>
                <p style={{ fontSize: '10px', color: '#6b7280', margin: 0, lineHeight: 1.3 }}>Desde $39.990</p>
              </div>
            </div>
          </div>

        </div>
      </section>

      {/* ========== CONTENIDO PRINCIPAL (Con Márgenes) ========== */}
      <div className="main-content w-full" style={{maxWidth: '1280px', margin: '0 auto'}}>

        {/* ========== BARRA DE BENEFICIOS ========== */}
        <section className="benefits-bar bg-white rounded-xl shadow-sm border border-gray-100" style={{ marginTop: '-24px', position: 'relative', zIndex: 10, padding: '12px 24px' }}>
          <div className="benefits-inner flex items-center justify-center gap-6 md:gap-12 text-sm flex-wrap">
            <span className="flex items-center gap-2 text-gray-700 group cursor-pointer">
              <div className="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center group-hover:bg-cyan-100 transition-colors">
                <Truck size={16} className="text-[#00A8E8]" />
              </div>
              <div>
                <p className="font-bold text-gray-800" style={{fontSize:'13px'}}>Envío gratis</p>
                <p className="text-gray-500" style={{fontSize:'11px'}}>desde $39.990</p>
              </div>
            </span>
            <span className="flex items-center gap-2 text-gray-700 group cursor-pointer">
              <div className="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center group-hover:bg-yellow-100 transition-colors">
                <Clock size={16} className="text-[#FFC400]" />
              </div>
              <div>
                <p className="font-bold text-gray-800" style={{fontSize:'13px'}}>Express</p>
                <p className="text-gray-500" style={{fontSize:'11px'}}>en pocas horas</p>
              </div>
            </span>
            <span className="flex items-center gap-2 text-gray-700 group cursor-pointer">
              <div className="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center group-hover:bg-green-100 transition-colors">
                <Shield size={16} className="text-green-500" />
              </div>
              <div>
                <p className="font-bold text-gray-800" style={{fontSize:'13px'}}>Pago seguro</p>
                <p className="text-gray-500" style={{fontSize:'11px'}}>100% garantizado</p>
              </div>
            </span>
          </div>
        </section>

        {/* ========== CATEGORÍAS ========== */}
        <section style={{ marginTop: '60px' }}>
          <h3 className="text-2xl font-black mb-6 flex items-center justify-center gap-3 text-gray-800">
            <Filter size={24} className="text-[#00A8E8]" />
            CATEGORÍAS
          </h3>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            {categories.map((cat) => (
                <Link 
                key={cat.name} 
                to={cat.link}
                className="group flex flex-col items-center justify-center p-6 bg-white hover:bg-cyan-50 rounded-3xl transition-all no-underline border border-gray-100 hover:border-cyan-200 hover:shadow-xl"
              >
                <span className="text-5xl mb-4 group-hover:scale-110 transition-transform">{cat.emoji}</span>
                <p className="font-bold text-gray-800 group-hover:text-[#00A8E8] text-center mb-1">{cat.name}</p>
                <p className="text-xs text-gray-400 text-center uppercase tracking-wider">{cat.count}</p>
                <ChevronRight size={16} className="text-gray-300 group-hover:text-[#00A8E8] mt-2 transition-colors" />
              </Link>
            ))}
          </div>
        </section>

        {/* ========== ASISTENTE IA ========== */}
        <section style={{ marginTop: '80px' }}>
          <div className="bg-gradient-to-br from-[#2F3A40] to-[#3d4a52] rounded-2xl p-10 sm:p-12 relative overflow-hidden group shadow-xl">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-6 relative z-10">
              <div className="flex items-center gap-5">
                <div className="w-14 h-14 bg-[#FFC400] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-yellow-500/20">
                  <MessageCircle size={28} className="text-[#2F3A40]" />
                </div>
                <div>
                  <h3 className="font-black text-white text-lg mb-1">
                    Asistente <span className="text-[#FFC400]">PetsGo</span>
                  </h3>
                  <p className="text-gray-400 text-sm leading-relaxed">
                    ¿No sabes qué alimento elegir? Nuestra IA te ayuda en tiempo real.
                  </p>
                </div>
              </div>
              <button className="px-8 py-3.5 bg-[#FFC400] text-[#2F3A40] font-black rounded-xl hover:scale-105 transition-all text-sm shadow-lg shadow-yellow-500/20 uppercase tracking-wide">
                CHATEAR AHORA 🐾
              </button>
            </div>
            <PawPrint className="absolute -bottom-8 -right-8 text-white/5 w-40 h-40 rotate-12 group-hover:rotate-0 transition-transform duration-700" />
          </div>
        </section>

        {/* ========== PRODUCTOS DESTACADOS ========== */}
        <section style={{ marginTop: '80px' }}>
          <div className="flex flex-col items-center mb-8 text-center">
            <h2 className="text-4xl font-black text-gray-800 mb-2">Top Ventas</h2>
            <p className="font-bold text-gray-400 uppercase tracking-widest text-xs italic mb-3">
              Sugerido para tu mascota 🦴
            </p>
            <Link to="/tiendas" className="flex items-center gap-2 text-[#00A8E8] font-bold hover:gap-4 transition-all no-underline uppercase text-sm tracking-wide">
              Ver todas <ArrowRight size={18} />
            </Link>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-10">
            {featuredProducts.map((product) => {
              const discount = calculateDiscount(product.price, product.originalPrice);
              return (
              <Link to={`/producto/${product.id}`} state={{ product }} key={product.id} className="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-[0_20px_50px_rgba(0,0,0,0.08)] transition-all duration-500 flex flex-col border border-gray-100" style={{ textDecoration: 'none', color: 'inherit' }}>
                  <div className="relative h-72 overflow-hidden bg-gray-50 flex items-center justify-center p-8">
                    <img 
                      src={product.image} 
                      alt={product.name} 
                      className="max-w-full max-h-full object-contain group-hover:scale-110 transition-transform duration-700" 
                    />
                    {discount && (
                      <span className="absolute top-4 left-4 bg-red-500 text-white text-xs font-black px-3 py-1.5 rounded-lg shadow-md">
                        -{discount}%
                      </span>
                    )}
                    <div className="absolute top-6 right-6 bg-white/95 backdrop-blur px-3 py-2 rounded-full shadow-sm flex items-center gap-2">
                      <Star size={14} fill="#FFC400" color="#FFC400" />
                      <span className="font-black text-xs">{product.rating}</span>
                    </div>
                  </div>
                  <div className="p-8 flex-1 flex flex-col">
                    <span className="text-[10px] font-black uppercase text-cyan-500 mb-2 tracking-widest">
                      {product.category}
                    </span>
                    <h3 className="font-bold text-lg text-gray-800 leading-tight mb-4 min-h-[3rem]">
                      {product.name}
                    </h3>
                    <p className="text-sm text-gray-400 mb-4">{product.brand}</p>
                    <div className="mt-auto flex flex-col gap-2">
                      <div className="flex items-end gap-2">
                        <span className="text-3xl font-black text-gray-800">
                          {formatPrice(product.price)}
                        </span>
                        {product.originalPrice && (
                          <span className="text-base text-gray-400 line-through mb-1">
                            {formatPrice(product.originalPrice)}
                          </span>
                        )}
                      </div>
                      <div className="flex justify-end mt-2">
                        {getItemQuantity(product.id) > 0 ? (
                          <div style={{
                            display: 'flex', alignItems: 'center', gap: '8px',
                            background: '#f0f9ff', borderRadius: '12px', padding: '4px',
                          }}
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); }}
                          >
                            <button
                              onClick={(e) => { e.preventDefault(); e.stopPropagation(); updateQuantity(product.id, getItemQuantity(product.id) - 1); }}
                              style={{
                                width: '36px', height: '36px', borderRadius: '10px',
                                border: 'none', background: '#fff', cursor: 'pointer',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                boxShadow: '0 1px 3px rgba(0,0,0,0.1)', transition: 'all 0.15s',
                              }}
                            >
                              <Minus size={16} color="#00A8E8" />
                            </button>
                            <span style={{
                              fontSize: '16px', fontWeight: 700, color: '#1f2937',
                              minWidth: '28px', textAlign: 'center',
                            }}>
                              {getItemQuantity(product.id)}
                            </span>
                            <button
                              onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem({ ...product, quantity: 1 }); }}
                              style={{
                                width: '36px', height: '36px', borderRadius: '10px',
                                border: 'none', background: '#00A8E8', cursor: 'pointer',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                boxShadow: '0 2px 6px rgba(0,168,232,0.3)', transition: 'all 0.15s',
                              }}
                            >
                              <Plus size={16} color="#fff" />
                            </button>
                          </div>
                        ) : (
                          <button 
                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem({ ...product, quantity: 1 }); }}
                            className="w-12 h-12 rounded-xl bg-[#00A8E8] flex items-center justify-center text-white shadow-lg shadow-cyan-200 hover:bg-[#0090c7] active:scale-95 transition-all"
                          >
                            <Plus size={24} />
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
              </Link>
            );
            })}
          </div>
        </section>

        {/* ========== BANNER PROMOCIONAL ========== */}
        <section style={{ marginTop: '96px' }}>
          <div className="bg-gradient-to-r from-[#00A8E8] to-[#00bfff] rounded-2xl p-10 md:p-14 flex flex-col md:flex-row items-center justify-between gap-10 shadow-xl relative overflow-hidden">
            <div className="text-center md:text-left relative z-10" style={{ marginLeft: '28px' }}>
              <span className="inline-block bg-[#FFC400] text-gray-900 text-sm font-black px-4 py-1.5 rounded-lg mb-5 uppercase tracking-widest shadow-md">
                🎉 PRIMERA COMPRA
              </span>
              <h3 className="text-3xl md:text-4xl font-black text-white mb-4 leading-tight">
                ¡15% de descuento!
              </h3>
              <p className="text-white/90 text-base" style={{ marginLeft: '4px' }}>
                Usa el código{' '}
                <strong className="bg-white/20 backdrop-blur px-3 py-1.5 rounded-lg font-black tracking-wider" style={{ marginLeft: '6px' }}>
                  BIENVENIDO15
                </strong>
              </p>
            </div>
            <Link to="/tiendas" className="px-10 py-4 bg-white text-[#00A8E8] font-black rounded-xl hover:bg-[#FFC400] hover:text-gray-900 transition-all shadow-lg text-lg uppercase tracking-wide hover:scale-105 active:scale-95 relative z-10 no-underline inline-block">
              COMPRAR AHORA
            </Link>
            <div className="absolute -right-10 -bottom-10 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
          </div>
        </section>

        {/* ========== TIENDAS (CARRUSEL INFINITO) ========== */}
        <section style={{ marginTop: '96px', overflow: 'hidden' }}>
          <div className="flex flex-col items-center text-center mb-10">
            <h2 className="text-4xl font-black text-gray-800 mb-2">Tiendas en PetsGo</h2>
            <p className="font-bold text-gray-400 uppercase tracking-widest text-xs italic">
              Las mejores tiendas locales 🏪
            </p>
            <Link 
              to="/tiendas" 
              className="flex items-center gap-2 text-[#00A8E8] font-bold no-underline uppercase text-sm tracking-wide hover:gap-4 transition-all mt-4"
            >
              Ver todas <ChevronRight size={18} />
            </Link>
          </div>

          <style>{`
            @keyframes storesScroll {
              0% { transform: translateX(0); }
              100% { transform: translateX(-50%); }
            }
            .stores-track {
              display: flex;
              gap: 28px;
              width: max-content;
              animation: storesScroll 35s linear infinite;
            }
            .stores-track:hover {
              animation-play-state: paused;
            }
            .store-card {
              min-width: 230px;
              background: #fff;
              border-radius: 20px;
              padding: 28px 24px;
              text-align: center;
              border: 1px solid #f3f4f6;
              display: flex;
              flex-direction: column;
              align-items: center;
              text-decoration: none;
              transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
              cursor: pointer;
              position: relative;
              overflow: hidden;
            }
            .store-card::before {
              content: '';
              position: absolute;
              inset: 0;
              background: linear-gradient(135deg, rgba(0,168,232,0.04) 0%, rgba(0,212,255,0.08) 100%);
              opacity: 0;
              transition: opacity 0.4s ease;
              border-radius: 20px;
            }
            .store-card:hover::before {
              opacity: 1;
            }
            .store-card:hover {
              transform: translateY(-8px);
              box-shadow: 0 20px 50px rgba(0,168,232,0.12), 0 8px 20px rgba(0,0,0,0.04);
              border-color: rgba(0,168,232,0.25);
            }
            .store-icon {
              width: 76px;
              height: 76px;
              border-radius: 20px;
              background: linear-gradient(135deg, #00A8E8 0%, #00d4ff 100%);
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 30px;
              margin-bottom: 18px;
              box-shadow: 0 8px 24px rgba(0,168,232,0.2);
              transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
              position: relative;
              z-index: 1;
            }
            .store-card:hover .store-icon {
              transform: scale(1.12) rotate(-3deg);
              box-shadow: 0 12px 32px rgba(0,168,232,0.3);
            }
            .store-name {
              font-weight: 900;
              color: #1f2937;
              font-size: 17px;
              margin-bottom: 6px;
              white-space: nowrap;
              transition: color 0.3s ease;
              position: relative;
              z-index: 1;
            }
            .store-card:hover .store-name {
              color: #00A8E8;
            }
            .store-products {
              color: #9ca3af;
              font-weight: 600;
              font-size: 11px;
              text-transform: uppercase;
              letter-spacing: 1.5px;
              margin-bottom: 14px;
              position: relative;
              z-index: 1;
            }
            .store-rating {
              display: inline-flex;
              align-items: center;
              gap: 6px;
              background: #fefce8;
              padding: 6px 14px;
              border-radius: 20px;
              font-weight: 900;
              font-size: 13px;
              color: #4b5563;
              position: relative;
              z-index: 1;
              transition: all 0.3s ease;
            }
            .store-card:hover .store-rating {
              background: #fef3c7;
              transform: scale(1.05);
            }
          `}</style>

          {(() => {
            const allStores = [
              { id: 1, name: 'PetShop Las Condes', products: '120 productos', rating: 4.9, emoji: '🏪', img: 'https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?w=80&auto=format&fit=crop&q=80' },
              { id: 2, name: 'La Huella Store', products: '85 productos', rating: 4.8, emoji: '🐾', img: 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=80&auto=format&fit=crop&q=80' },
              { id: 3, name: 'Mundo Animal Centro', products: '95 productos', rating: 4.7, emoji: '🦴', img: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=80&auto=format&fit=crop&q=80' },
              { id: 4, name: 'Vet & Shop', products: '110 productos', rating: 4.9, emoji: '⚕️', img: 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=80&auto=format&fit=crop&q=80' },
              { id: 5, name: 'Happy Pets Provi', products: '78 productos', rating: 4.6, emoji: '🐶', img: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=80&auto=format&fit=crop&q=80' },
              { id: 6, name: 'Patitas Felices', products: '65 productos', rating: 4.5, emoji: '🐱', img: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=80&auto=format&fit=crop&q=80' },
              { id: 7, name: 'Animal House', products: '140 productos', rating: 4.8, emoji: '🏠', img: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=80&auto=format&fit=crop&q=80' },
              { id: 8, name: 'PetLand Chile', products: '200 productos', rating: 4.9, emoji: '🌟', img: 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=80&auto=format&fit=crop&q=80' },
              { id: 9, name: 'Dr. Mascota', products: '55 productos', rating: 4.7, emoji: '💊', img: 'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?w=80&auto=format&fit=crop&q=80' },
              { id: 10, name: 'Zoo Market RM', products: '92 productos', rating: 4.6, emoji: '🛒', img: 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=80&auto=format&fit=crop&q=80' },
              { id: 11, name: 'Peluditos Store', products: '73 productos', rating: 4.5, emoji: '🐕', img: 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?w=80&auto=format&fit=crop&q=80' },
              { id: 12, name: 'MascotaExpress', products: '168 productos', rating: 4.8, emoji: '🚀', img: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=80&auto=format&fit=crop&q=80' },
            ];
            // Duplicar para efecto infinito
            const doubled = [...allStores, ...allStores];
            return (
              <div style={{ padding: '12px 0 20px' }}>
                <div className="stores-track">
                  {doubled.map((store, i) => (
                    <Link key={`${store.id}-${i}`} to="/tiendas" className="store-card">
                      <div className="store-icon" style={store.img ? { background: '#fff', padding: '4px' } : {}}>
                        {store.img ? <img src={store.img} alt={store.name} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '16px' }} /> : store.emoji}
                      </div>
                      <div className="store-name">{store.name}</div>
                      <div className="store-products">{store.products}</div>
                      <div className="store-rating">
                        <Star size={14} fill="#FFC400" color="#FFC400" />
                        {store.rating}
                      </div>
                    </Link>
                  ))}
                </div>
              </div>
            );
          })()}
        </section>

        {/* ========== MARCAS (CARRUSEL INFINITO) ========== */}
        <section className="bg-white rounded-2xl shadow-md border border-gray-100 py-14" style={{ marginTop: '96px', marginBottom: '96px', overflow: 'hidden' }}>
          <h2 className="text-center text-gray-400 font-black mb-10 uppercase tracking-widest text-sm">
            Marcas que confían en nosotros
          </h2>

          <style>{`
            @keyframes brandsScroll {
              0% { transform: translateX(0); }
              100% { transform: translateX(-50%); }
            }
            .brands-track {
              display: flex;
              align-items: center;
              gap: 48px;
              width: max-content;
              animation: brandsScroll 50s linear infinite;
            }
            .brands-track:hover {
              animation-play-state: paused;
            }
            .brand-item {
              display: flex;
              align-items: center;
              justify-content: center;
              min-width: 160px;
              padding: 16px 28px;
              border-radius: 16px;
              transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
              cursor: pointer;
              position: relative;
            }
            .brand-item:hover {
              background: #f0f9ff;
              transform: scale(1.08);
              box-shadow: 0 8px 30px rgba(0,168,232,0.1);
            }
            .brand-item img {
              height: 44px;
              width: auto;
              max-width: 140px;
              object-fit: contain;
              transition: all 0.5s ease;
            }
            .brand-item:hover img {
              transform: scale(1.1);
            }
          `}</style>

          {(() => {
            const brands = [
              { name: 'Royal Canin', color: '#E2001A', bg: '#FFF5F5', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7e/Royal_Canin_logo.svg/200px-Royal_Canin_logo.svg.png' },
              { name: "Hill's", color: '#003DA5', bg: '#F0F4FF', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/50/Hill%27s_Pet_Nutrition_logo.svg/200px-Hill%27s_Pet_Nutrition_logo.svg.png' },
              { name: 'Pro Plan', color: '#E31837', bg: '#FFF5F7', logo: null },
              { name: 'Bravecto', color: '#FF6B00', bg: '#FFF7F0', logo: null },
              { name: 'Eukanuba', color: '#1B3C71', bg: '#F0F3F9', logo: null },
              { name: 'Pedigree', color: '#FFD100', bg: '#FFFCF0', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/13/Pedigree_logo.svg/200px-Pedigree_logo.svg.png' },
              { name: 'Whiskas', color: '#6B2D8B', bg: '#F8F0FF', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Whiskas_logo.svg/200px-Whiskas_logo.svg.png' },
              { name: 'Purina', color: '#E31837', bg: '#FFF5F7', logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3f/Purina_logo.svg/200px-Purina_logo.svg.png' },
            ];
            const doubled = [...brands, ...brands];
            return (
              <div className="brands-track">
                {doubled.map((brand, i) => (
                  <div key={`${brand.name}-${i}`} className="brand-item" title={brand.name}
                    style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', background: brand.bg, borderRadius: '16px', padding: '16px 32px', minWidth: '180px' }}
                  >
                    {brand.logo ? (
                      <img src={brand.logo} alt={brand.name} style={{ height: '36px', maxWidth: '130px', objectFit: 'contain' }} onError={(e) => { e.target.style.display='none'; e.target.nextSibling.style.display='block'; }} />
                    ) : null}
                    <span style={{
                      fontSize: '22px', fontWeight: 900, color: brand.color,
                      whiteSpace: 'nowrap', letterSpacing: '-0.5px', fontFamily: 'Poppins, sans-serif',
                      display: brand.logo ? 'none' : 'block',
                    }}>
                      {brand.name}
                    </span>
                  </div>
                ))}
              </div>
            );
          })()}
        </section>

      </div>
    </div>
  );
};

export default HomePage;
