import React, { useState, useEffect, useMemo } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, Search, Store, Star, Clock, MapPin, Plus, Minus, PawPrint, Package, Filter } from 'lucide-react';
import { getProducts, getVendors } from '../services/api';
import { useCart } from '../context/CartContext';

/* ── Mapeo de categorías ──────────────────────────────── */
const CATEGORY_META = {
  Perros:     { emoji: '🐕', label: 'Perros',     desc: 'Todo para tu perro' },
  Gatos:      { emoji: '🐱', label: 'Gatos',      desc: 'Todo para tu gato' },
  Alimento:   { emoji: '🍖', label: 'Alimento',   desc: 'Seco y húmedo' },
  Snacks:     { emoji: '🦴', label: 'Snacks',     desc: 'Premios y dental' },
  Farmacia:   { emoji: '💊', label: 'Farmacia',   desc: 'Antiparasitarios y salud' },
  Accesorios: { emoji: '🎾', label: 'Accesorios', desc: 'Juguetes y más' },
  Higiene:    { emoji: '🧴', label: 'Higiene',    desc: 'Shampoo y aseo' },
  Camas:      { emoji: '🛏️', label: 'Camas',      desc: 'Descanso ideal' },
  Paseo:      { emoji: '🦮', label: 'Paseo',      desc: 'Correas y arneses' },
  Ropa:       { emoji: '🧥', label: 'Ropa',       desc: 'Abrigos y disfraces' },
  Ofertas:    { emoji: '🔥', label: 'Ofertas',    desc: 'Los mejores descuentos' },
  Nuevos:     { emoji: '✨', label: 'Nuevos',     desc: 'Recién llegados' },
};

/* ── Imágenes fallback por categoría ─────────────────── */
const CATEGORY_IMAGES = {
  Alimento: 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800',
  Accesorios: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800',
  Juguetes: 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=800',
  Higiene: 'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?auto=format&fit=crop&q=80&w=800',
  Ropa: 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&q=80&w=800',
  Camas: 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?auto=format&fit=crop&q=80&w=800',
  Farmacia: 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?auto=format&fit=crop&q=80&w=800',
  Snacks: 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800',
  Paseo: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800',
  Perros: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800',
  Gatos: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?auto=format&fit=crop&q=80&w=800',
  default: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800',
};

const getProductImage = (product) => {
  if (product.image_url) return product.image_url;
  return CATEGORY_IMAGES[product.category] || CATEGORY_IMAGES['default'];
};

/* ── Componente principal ────────────────────────────── */
const CategoryPage = () => {
  const { slug } = useParams();
  const categoryName = decodeURIComponent(slug);
  const meta = CATEGORY_META[categoryName] || { emoji: '📦', label: categoryName, desc: '' };

  const [products, setProducts] = useState([]);
  const [allVendors, setAllVendors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [activeTab, setActiveTab] = useState('productos'); // 'productos' | 'tiendas'
  const { addItem, getItemQuantity, updateQuantity } = useCart();

  useEffect(() => {
    window.scrollTo(0, 0);
    loadData();
  }, [categoryName]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [productsRes, vendorsRes] = await Promise.all([
        getProducts({ category: categoryName }),
        getVendors(),
      ]);
      setProducts(productsRes.data.data || []);
      setAllVendors(vendorsRes.data.data || []);
    } catch (err) {
      console.error('Error cargando categoría:', err);
    } finally {
      setLoading(false);
    }
  };

  /* Filtrar productos por búsqueda */
  const filteredProducts = useMemo(() => {
    if (!searchTerm.trim()) return products;
    const term = searchTerm.toLowerCase();
    return products.filter(p =>
      p.product_name.toLowerCase().includes(term) ||
      p.store_name?.toLowerCase().includes(term)
    );
  }, [products, searchTerm]);

  /* Obtener vendors únicos que venden en esta categoría */
  const categoryVendors = useMemo(() => {
    const vendorIds = [...new Set(products.map(p => p.vendor_id))];
    return allVendors.filter(v => vendorIds.includes(v.id));
  }, [products, allVendors]);

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  /* ── Skeleton loading ──────────────────────────────── */
  if (loading) {
    return (
      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 16px', fontFamily: 'Poppins, sans-serif' }}>
        <div style={{ height: '120px', background: 'linear-gradient(135deg, #e5e7eb, #f3f4f6)', borderRadius: '20px', marginBottom: '24px', animation: 'cpPulse 1.5s ease-in-out infinite' }} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '20px' }}>
          {[...Array(8)].map((_, i) => (
            <div key={i} style={{ background: '#f3f4f6', borderRadius: '20px', height: '320px', animation: 'cpPulse 1.5s ease-in-out infinite' }} />
          ))}
        </div>
        <style>{`@keyframes cpPulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }`}</style>
      </div>
    );
  }

  return (
    <div style={{ fontFamily: 'Poppins, sans-serif' }}>
      <style>{`
        @keyframes cpPulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }
        .cp-product-card { transition: all 0.3s ease; }
        .cp-product-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.08) !important; transform: translateY(-4px); }
        .cp-vendor-card { transition: all 0.3s ease; }
        .cp-vendor-card:hover { box-shadow: 0 12px 32px rgba(0,0,0,0.12) !important; transform: translateY(-4px); }
        .cp-tab { cursor: pointer; padding: 10px 24px; border-radius: 12px; font-weight: 700; font-size: 14px; border: none; transition: all 0.2s; }
        .cp-tab-active { background: #00A8E8; color: #fff; box-shadow: 0 4px 16px rgba(0,168,232,0.3); }
        .cp-tab-inactive { background: #f3f4f6; color: #6b7280; }
        .cp-tab-inactive:hover { background: #e5e7eb; }
        @media (max-width: 639px) {
          .cp-products-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; }
          .cp-product-info { padding: 12px 14px !important; }
          .cp-product-name { font-size: 13px !important; }
          .cp-product-price { font-size: 16px !important; }
          .cp-product-desc { display: none !important; }
          .cp-search-wrap { flex-direction: column; }
        }
        @media (max-width: 380px) { .cp-products-grid { grid-template-columns: 1fr !important; } }
      `}</style>

      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 16px' }}>

        {/* ── Breadcrumb ── */}
        <Link to="/" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          color: '#9ca3af', fontWeight: 700, fontSize: '13px', textDecoration: 'none',
          marginBottom: '20px',
        }}>
          <ArrowLeft size={16} /> Volver al Inicio
        </Link>

        {/* ── Header de categoría ── */}
        <div style={{
          background: 'linear-gradient(135deg, #00A8E8 0%, #0077B6 100%)',
          borderRadius: '20px', padding: '28px 32px', marginBottom: '28px', color: '#fff',
          display: 'flex', alignItems: 'center', gap: '20px', flexWrap: 'wrap',
        }}>
          <div style={{
            width: '64px', height: '64px', background: 'rgba(255,255,255,0.2)',
            borderRadius: '16px', display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '32px', flexShrink: 0,
          }}>
            {meta.emoji}
          </div>
          <div style={{ flex: 1, minWidth: '200px' }}>
            <h1 style={{ fontSize: 'clamp(22px, 4vw, 32px)', fontWeight: 800, marginBottom: '4px' }}>
              {meta.label}
            </h1>
            <p style={{ fontSize: '14px', opacity: 0.85, fontWeight: 500 }}>
              {meta.desc} — {products.length} producto{products.length !== 1 ? 's' : ''} disponible{products.length !== 1 ? 's' : ''}
            </p>
          </div>
          <div style={{
            display: 'flex', gap: '12px', flexWrap: 'wrap',
          }}>
            <div style={{
              background: 'rgba(255,255,255,0.2)', borderRadius: '12px', padding: '10px 18px',
              display: 'flex', alignItems: 'center', gap: '8px',
            }}>
              <Package size={18} />
              <div>
                <div style={{ fontSize: '18px', fontWeight: 800 }}>{products.length}</div>
                <div style={{ fontSize: '10px', fontWeight: 600, opacity: 0.8 }}>Productos</div>
              </div>
            </div>
            <div style={{
              background: 'rgba(255,255,255,0.2)', borderRadius: '12px', padding: '10px 18px',
              display: 'flex', alignItems: 'center', gap: '8px',
            }}>
              <Store size={18} />
              <div>
                <div style={{ fontSize: '18px', fontWeight: 800 }}>{categoryVendors.length}</div>
                <div style={{ fontSize: '10px', fontWeight: 600, opacity: 0.8 }}>Tiendas</div>
              </div>
            </div>
          </div>
        </div>

        {/* ── Tabs + Buscador ── */}
        <div className="cp-search-wrap" style={{
          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
          gap: '16px', marginBottom: '24px', flexWrap: 'wrap',
        }}>
          <div style={{ display: 'flex', gap: '8px' }}>
            <button
              className={`cp-tab ${activeTab === 'productos' ? 'cp-tab-active' : 'cp-tab-inactive'}`}
              onClick={() => setActiveTab('productos')}
            >
              <span style={{ marginRight: '6px' }}>📦</span> Productos ({filteredProducts.length})
            </button>
            <button
              className={`cp-tab ${activeTab === 'tiendas' ? 'cp-tab-active' : 'cp-tab-inactive'}`}
              onClick={() => setActiveTab('tiendas')}
            >
              <span style={{ marginRight: '6px' }}>🏪</span> Tiendas ({categoryVendors.length})
            </button>
          </div>

          {activeTab === 'productos' && (
            <div style={{
              position: 'relative', flex: '0 1 360px', minWidth: '200px',
            }}>
              <Search size={18} style={{
                position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)',
                color: '#9ca3af', pointerEvents: 'none',
              }} />
              <input
                type="text"
                placeholder="Buscar productos..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                autoComplete="off"
                style={{
                  width: '100%', padding: '12px 16px 12px 44px',
                  borderRadius: '12px', border: '2px solid #e5e7eb',
                  fontSize: '14px', fontWeight: 500, fontFamily: 'Poppins, sans-serif',
                  outline: 'none', transition: 'border-color 0.2s',
                }}
                onFocus={(e) => (e.target.style.borderColor = '#00A8E8')}
                onBlur={(e) => (e.target.style.borderColor = '#e5e7eb')}
              />
            </div>
          )}
        </div>

        {/* ═══════ TAB: PRODUCTOS ═══════ */}
        {activeTab === 'productos' && (
          <>
            {filteredProducts.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '64px 0' }}>
                <PawPrint size={48} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
                <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '16px' }}>
                  {searchTerm ? 'No se encontraron productos' : 'Aún no hay productos en esta categoría'}
                </p>
                {searchTerm && (
                  <button onClick={() => setSearchTerm('')} style={{
                    marginTop: '12px', background: '#00A8E8', color: '#fff',
                    border: 'none', padding: '10px 24px', borderRadius: '10px',
                    fontWeight: 700, fontSize: '13px', cursor: 'pointer',
                  }}>
                    Limpiar búsqueda
                  </button>
                )}
              </div>
            ) : (
              <div className="cp-products-grid" style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
                gap: '20px',
              }}>
                {filteredProducts.map((product) => {
                  const qty = getItemQuantity(product.id);
                  return (
                    <Link
                      to={`/producto/${product.id}`}
                      state={{ product }}
                      key={product.id}
                      className="cp-product-card"
                      style={{
                        background: '#fff', borderRadius: '18px', overflow: 'hidden',
                        border: '1px solid #f3f4f6',
                        boxShadow: '0 2px 8px rgba(0,0,0,0.04)', textDecoration: 'none', color: 'inherit',
                        display: 'flex', flexDirection: 'column',
                      }}
                    >
                      <div style={{ position: 'relative', aspectRatio: '1', overflow: 'hidden', background: '#f9fafb' }}>
                        <img
                          src={getProductImage(product)}
                          alt={product.product_name}
                          style={{ width: '100%', height: '100%', objectFit: 'cover', transition: 'transform 0.5s ease' }}
                          loading="lazy"
                          onError={(e) => { e.target.src = CATEGORY_IMAGES['default']; }}
                        />
                        {product.discount_active && (
                          <span style={{
                            position: 'absolute', top: '12px', right: '12px',
                            background: '#ef4444', color: '#fff',
                            fontSize: '11px', fontWeight: 800, padding: '4px 10px', borderRadius: '8px',
                          }}>
                            -{product.discount_percent}%
                          </span>
                        )}
                        {product.category && (
                          <span style={{
                            position: 'absolute', top: '12px', left: '12px',
                            background: 'rgba(255,255,255,0.92)', backdropFilter: 'blur(4px)',
                            fontSize: '10px', fontWeight: 700, padding: '4px 8px', borderRadius: '6px', color: '#2F3A40',
                          }}>
                            {product.category}
                          </span>
                        )}
                      </div>
                      <div className="cp-product-info" style={{ padding: '16px 18px', flex: 1, display: 'flex', flexDirection: 'column' }}>
                        <h4 className="cp-product-name" style={{
                          fontWeight: 700, fontSize: '14px', color: '#1f2937', lineHeight: 1.4, marginBottom: '4px',
                        }}>
                          {product.product_name}
                        </h4>

                        {/* Tienda del producto */}
                        <Link
                          to={`/tienda/${product.vendor_id}`}
                          onClick={(e) => e.stopPropagation()}
                          style={{
                            display: 'flex', alignItems: 'center', gap: '4px',
                            fontSize: '11px', fontWeight: 600, color: '#00A8E8',
                            textDecoration: 'none', marginBottom: '8px',
                          }}
                        >
                          <Store size={11} /> {product.store_name}
                        </Link>

                        <div style={{ marginTop: 'auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                          <div>
                            {product.discount_active ? (
                              <>
                                <span style={{ fontSize: '11px', color: '#9ca3af', textDecoration: 'line-through', marginRight: '6px' }}>
                                  {formatPrice(product.price)}
                                </span>
                                <span className="cp-product-price" style={{ fontSize: '18px', fontWeight: 900, color: '#ef4444' }}>
                                  {formatPrice(product.final_price)}
                                </span>
                              </>
                            ) : (
                              <span className="cp-product-price" style={{ fontSize: '18px', fontWeight: 900, color: '#00A8E8' }}>
                                {formatPrice(product.price)}
                              </span>
                            )}
                          </div>
                          <div onClick={(e) => { e.preventDefault(); e.stopPropagation(); }}>
                            {qty > 0 ? (
                              <div style={{
                                display: 'flex', alignItems: 'center', gap: '4px',
                                background: '#f0f9ff', borderRadius: '10px', padding: '3px',
                              }}>
                                <button
                                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); updateQuantity(product.id, qty - 1); }}
                                  style={{
                                    width: '30px', height: '30px', borderRadius: '8px',
                                    border: 'none', background: '#fff', cursor: 'pointer',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
                                  }}
                                >
                                  <Minus size={14} color="#00A8E8" />
                                </button>
                                <span style={{ fontSize: '14px', fontWeight: 700, color: '#1f2937', minWidth: '24px', textAlign: 'center' }}>
                                  {qty}
                                </span>
                                <button
                                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem({ ...product, name: product.product_name, image: getProductImage(product), quantity: 1 }); }}
                                  style={{
                                    width: '30px', height: '30px', borderRadius: '8px',
                                    border: 'none', background: '#00A8E8', cursor: 'pointer',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    boxShadow: '0 2px 6px rgba(0,168,232,0.3)',
                                  }}
                                >
                                  <Plus size={14} color="#fff" />
                                </button>
                              </div>
                            ) : (
                              <button
                                onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem({ ...product, name: product.product_name, image: getProductImage(product), quantity: 1 }); }}
                                style={{
                                  width: '36px', height: '36px', borderRadius: '50%',
                                  border: 'none', background: '#fff', cursor: 'pointer',
                                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                                  boxShadow: '0 2px 8px rgba(0,0,0,0.1)', transition: 'all 0.2s',
                                }}
                              >
                                <Plus size={18} color="#00A8E8" />
                              </button>
                            )}
                          </div>
                        </div>
                      </div>
                    </Link>
                  );
                })}
              </div>
            )}
          </>
        )}

        {/* ═══════ TAB: TIENDAS ═══════ */}
        {activeTab === 'tiendas' && (
          <>
            {categoryVendors.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '64px 0' }}>
                <Store size={48} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
                <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '16px' }}>
                  Aún no hay tiendas con productos en esta categoría
                </p>
              </div>
            ) : (
              <>
                <p style={{ fontSize: '13px', color: '#9ca3af', fontWeight: 500, marginBottom: '20px' }}>
                  Tiendas que venden productos de <strong style={{ color: '#2F3A40' }}>{meta.label}</strong>. Haz clic en una para ver su catálogo completo.
                </p>
                <div style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
                  gap: '24px',
                }}>
                  {categoryVendors.map((vendor) => {
                    const vendorProductCount = products.filter(p => p.vendor_id === vendor.id).length;
                    return (
                      <Link
                        key={vendor.id}
                        to={`/tienda/${vendor.id}`}
                        className="cp-vendor-card"
                        style={{
                          background: '#fff', borderRadius: '16px', padding: '20px',
                          boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
                          textDecoration: 'none', color: 'inherit', display: 'block',
                        }}
                      >
                        <div style={{ display: 'flex', alignItems: 'center', gap: '14px', marginBottom: '14px' }}>
                          <div style={{
                            width: '52px', height: '52px', borderRadius: '14px',
                            background: '#f0f9ff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                            flexShrink: 0, overflow: 'hidden',
                          }}>
                            {vendor.logo_url ? (
                              <img src={vendor.logo_url} alt={vendor.store_name} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '14px' }} />
                            ) : (
                              <Store size={24} color="#00A8E8" />
                            )}
                          </div>
                          <div style={{ flex: 1, minWidth: 0 }}>
                            <h4 style={{
                              fontWeight: 700, fontSize: '15px', color: '#2F3A40', marginBottom: '4px',
                              whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                            }}>
                              {vendor.store_name}
                            </h4>
                            {vendor.address && (
                              <p style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 500, display: 'flex', alignItems: 'center', gap: '4px' }}>
                                <MapPin size={11} /> {vendor.address}
                              </p>
                            )}
                          </div>
                        </div>
                        <div style={{
                          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                          background: '#f9fafb', borderRadius: '10px', padding: '10px 14px',
                        }}>
                          <span style={{ fontSize: '12px', fontWeight: 700, color: '#00A8E8' }}>
                            {vendorProductCount} producto{vendorProductCount !== 1 ? 's' : ''} en {meta.label}
                          </span>
                          <span style={{
                            display: 'flex', alignItems: 'center', gap: '4px',
                            fontSize: '12px', fontWeight: 700, color: '#2F3A40',
                          }}>
                            <Star size={12} style={{ color: '#FFC400', fill: '#FFC400' }} />
                            {vendor.rating || '4.8'}
                          </span>
                        </div>
                      </Link>
                    );
                  })}
                </div>
              </>
            )}
          </>
        )}

        {/* ── Todas las categorías (navegación rápida) ── */}
        <div style={{ marginTop: '48px', borderTop: '1px solid #e5e7eb', paddingTop: '32px' }}>
          <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#2F3A40', marginBottom: '16px' }}>
            Explorar más categorías
          </h3>
          <div style={{
            display: 'flex', flexWrap: 'wrap', gap: '10px',
          }}>
            {Object.entries(CATEGORY_META).map(([key, val]) => (
              <Link
                key={key}
                to={`/categoria/${encodeURIComponent(key)}`}
                style={{
                  display: 'inline-flex', alignItems: 'center', gap: '6px',
                  padding: '8px 16px', borderRadius: '10px',
                  background: key === categoryName ? '#00A8E8' : '#f3f4f6',
                  color: key === categoryName ? '#fff' : '#4b5563',
                  fontWeight: 700, fontSize: '13px', textDecoration: 'none',
                  transition: 'all 0.2s',
                }}
              >
                <span>{val.emoji}</span> {val.label}
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default CategoryPage;
