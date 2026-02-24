import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Store, MapPin, Phone, Mail, Plus, Minus, ArrowLeft, PawPrint, Star, MessageSquare } from 'lucide-react';
import { getVendorDetail, getProducts, getVendorReviews } from '../services/api';
import { useCart } from '../context/CartContext';

// Imágenes por categoría para productos sin imagen
const CATEGORY_IMAGES = {
  'Alimento': 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800',
  'Accesorios': 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800',
  'Juguetes': 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=800',
  'Higiene': 'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?auto=format&fit=crop&q=80&w=800',
  'Ropa': 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&q=80&w=800',
  'Camas': 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?auto=format&fit=crop&q=80&w=800',
  'Transportadores': 'https://images.unsplash.com/photo-1541364983171-a8ba01e95cfc?auto=format&fit=crop&q=80&w=800',
  'Farmacia': 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?auto=format&fit=crop&q=80&w=800',
  'Transporte': 'https://images.unsplash.com/photo-1541364983171-a8ba01e95cfc?auto=format&fit=crop&q=80&w=800',
  'default': 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800'
};

const getProductImage = (product) => {
  if (product.image_url) return product.image_url;
  return CATEGORY_IMAGES[product.category] || CATEGORY_IMAGES['default'];
};

const VendorDetailPage = () => {
  const { id } = useParams();
  const [vendor, setVendor] = useState(null);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [vendorReviews, setVendorReviews] = useState([]);
  const [vendorRating, setVendorRating] = useState(null);
  const [vendorReviewCount, setVendorReviewCount] = useState(0);
  const { addItem, getItemQuantity, updateQuantity } = useCart();

  useEffect(() => {
    loadVendor();
  }, [id]);

  const loadVendor = async () => {
    setLoading(true);
    try {
      const [vendorRes, productsRes] = await Promise.all([
        getVendorDetail(id),
        getProducts({ vendorId: id }),
      ]);
      setVendor(vendorRes.data);
      setProducts(productsRes.data.data || []);
      // Load vendor reviews
      try {
        const reviewsRes = await getVendorReviews(id);
        setVendorReviews(reviewsRes.data?.reviews || []);
        setVendorRating(reviewsRes.data?.average);
        setVendorReviewCount(reviewsRes.data?.count || 0);
      } catch { /* ignore */ }
    } catch (err) {
      console.error('Error cargando tienda:', err);
    } finally {
      setLoading(false);
    }
  };

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  if (loading) {
    return (
      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 16px', fontFamily: 'Poppins, sans-serif' }}>
        <div style={{ height: '160px', background: '#e5e7eb', borderRadius: '20px', marginBottom: '24px', animation: 'pulse 1.5s ease-in-out infinite' }} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '20px' }}>
          {[...Array(6)].map((_, i) => (
            <div key={i} style={{ background: '#f3f4f6', borderRadius: '20px', height: '300px', animation: 'pulse 1.5s ease-in-out infinite' }} />
          ))}
        </div>
        <style>{`@keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }`}</style>
      </div>
    );
  }

  if (!vendor) {
    return (
      <div style={{ maxWidth: '600px', margin: '0 auto', padding: '80px 16px', textAlign: 'center', fontFamily: 'Poppins, sans-serif' }}>
        <Store size={56} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
        <h2 style={{ fontSize: '22px', fontWeight: 800, color: '#2F3A40', marginBottom: '8px' }}>Tienda no encontrada</h2>
        <Link to="/tiendas" style={{
          display: 'inline-block', background: '#00A8E8', color: '#fff', padding: '12px 28px',
          borderRadius: '12px', fontWeight: 700, fontSize: '14px', textDecoration: 'none', marginTop: '16px',
        }}>Ver Tiendas</Link>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 16px', fontFamily: 'Poppins, sans-serif' }}>
      <style>{`
        @media (min-width: 640px) { .vd-container { padding: 32px 32px !important; } }
        @media (min-width: 1024px) { .vd-container { padding: 32px 48px !important; } }
        @media (max-width: 639px) {
          .vd-header-inner { flex-direction: column !important; gap: 16px !important; text-align: center; }
          .vd-header-inner > div:last-child { align-self: center; }
          .vd-meta { justify-content: center !important; }
          .vd-products-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; }
          .vd-product-info { padding: 12px 14px !important; }
          .vd-product-name { font-size: 13px !important; }
          .vd-product-price { font-size: 16px !important; }
          .vd-product-desc { display: none !important; }
        }
        @media (max-width: 380px) { .vd-products-grid { grid-template-columns: 1fr !important; } }
      `}</style>

      <div className="vd-container" style={{ maxWidth: '1280px', margin: '0 auto' }}>
        {/* Breadcrumb */}
        <Link to="/tiendas" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          color: '#9ca3af', fontWeight: 700, fontSize: '13px', textDecoration: 'none',
          marginBottom: '20px',
        }}>
          <ArrowLeft size={16} /> Volver a Tiendas
        </Link>

        {/* Header de la tienda */}
        <div style={{
          background: 'linear-gradient(135deg, #00A8E8 0%, #0077B6 100%)',
          borderRadius: '20px', padding: '24px', marginBottom: '32px', color: '#fff',
        }}>
          <div className="vd-header-inner" style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
            <div style={{
              width: '56px', height: '56px', background: 'rgba(255,255,255,0.2)',
              borderRadius: '14px', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
            }}>
              {vendor.logo_url ? (
                <img src={vendor.logo_url} alt={vendor.store_name} style={{ width: '100%', height: '100%', borderRadius: '14px', objectFit: 'cover' }} />
              ) : (
                <Store size={26} color="#fff" />
              )}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <h2 style={{ fontSize: '22px', fontWeight: 700, marginBottom: '6px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{vendor.store_name}</h2>
              <div className="vd-meta" style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', fontSize: '12px', color: 'rgba(255,255,255,0.85)' }}>
                {vendor.address && <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}><MapPin size={13} /> {vendor.address}</span>}
                {vendor.phone && <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}><Phone size={13} /> {vendor.phone}</span>}
                {vendor.email && <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}><Mail size={13} /> {vendor.email}</span>}
              </div>
            </div>
            <div style={{
              display: 'flex', alignItems: 'center', gap: '6px',
              background: 'rgba(255,255,255,0.2)', borderRadius: '10px', padding: '8px 14px', flexShrink: 0,
            }}>
              <Star size={16} fill="#FFC400" color="#FFC400" />
              <span style={{ fontWeight: 700, fontSize: '16px' }}>{vendorRating || vendor.rating || '—'}</span>
              {vendorReviewCount > 0 && (
                <span style={{ fontSize: '11px', opacity: 0.8 }}>({vendorReviewCount})</span>
              )}
            </div>
          </div>
        </div>

        {/* Productos */}
        <h3 style={{ fontSize: '22px', fontWeight: 900, color: '#2F3A40', marginBottom: '20px' }}>
          Productos
          <span style={{ color: '#9ca3af', fontSize: '14px', fontWeight: 500, marginLeft: '8px' }}>({products.length})</span>
        </h3>

        {products.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '64px 0' }}>
            <PawPrint size={48} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
            <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '16px' }}>Esta tienda aún no tiene productos</p>
          </div>
        ) : (
          <div className="vd-products-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '20px' }}>
            {products.map((product) => {
              const qty = getItemQuantity(product.id);
              return (
                <Link to={`/producto/${product.id}`} state={{ product }} key={product.id} style={{
                  background: '#fff', borderRadius: '18px', overflow: 'hidden',
                  border: '1px solid #f3f4f6', transition: 'all 0.3s ease',
                  boxShadow: '0 2px 8px rgba(0,0,0,0.04)', textDecoration: 'none', color: 'inherit',
                  display: 'flex', flexDirection: 'column',
                }}
                  onMouseEnter={(e) => { e.currentTarget.style.boxShadow = '0 12px 40px rgba(0,0,0,0.08)'; e.currentTarget.style.transform = 'translateY(-4px)'; }}
                  onMouseLeave={(e) => { e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.04)'; e.currentTarget.style.transform = 'translateY(0)'; }}
                >
                  <div style={{ position: 'relative', aspectRatio: '1', overflow: 'hidden', background: '#f9fafb' }}>
                    <img
                      src={getProductImage(product)}
                      alt={product.product_name}
                      style={{ width: '100%', height: '100%', objectFit: 'cover', transition: 'transform 0.5s ease' }}
                      loading="lazy"
                      onError={(e) => { e.target.src = CATEGORY_IMAGES['default']; }}
                    />
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
                  <div className="vd-product-info" style={{ padding: '16px 18px', flex: 1, display: 'flex', flexDirection: 'column' }}>
                    <h4 className="vd-product-name" style={{ fontWeight: 700, fontSize: '14px', color: '#1f2937', lineHeight: 1.4, marginBottom: '4px' }}>
                      {product.product_name}
                    </h4>
                    {product.description && (
                      <p className="vd-product-desc" style={{ fontSize: '11px', color: '#9ca3af', marginBottom: '8px', overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical' }}>{product.description}</p>
                    )}
                    <div style={{ marginTop: 'auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span className="vd-product-price" style={{ fontSize: '18px', fontWeight: 900, color: '#00A8E8' }}>{formatPrice(product.price)}</span>
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

        {/* Vendor Reviews Section */}
        <div style={{ marginTop: '40px' }}>
          <h3 style={{ fontSize: '22px', fontWeight: 900, color: '#2F3A40', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <MessageSquare size={22} color="#00A8E8" /> Reseñas de la Tienda
            {vendorReviewCount > 0 && (
              <span style={{ color: '#9ca3af', fontSize: '14px', fontWeight: 500 }}>({vendorReviewCount})</span>
            )}
          </h3>
          {vendorReviews.length === 0 ? (
            <div style={{
              textAlign: 'center', padding: '40px 16px', background: '#fff',
              borderRadius: '16px', border: '1px solid #f0f0f0',
            }}>
              <Star size={36} color="#d1d5db" style={{ margin: '0 auto 8px' }} />
              <p style={{ fontWeight: 700, fontSize: '14px', color: '#9ca3af' }}>Aún no hay reseñas para esta tienda</p>
              <p style={{ fontSize: '12px', color: '#d1d5db' }}>Las reseñas se agregan después de completar una compra</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              {vendorReviews.map((review, idx) => (
                <div key={idx} style={{
                  padding: '16px 20px', background: '#fff', borderRadius: '14px',
                  border: '1px solid #f3f4f6', boxShadow: '0 1px 4px rgba(0,0,0,0.04)',
                }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <div style={{
                        width: '32px', height: '32px', borderRadius: '50%',
                        background: 'linear-gradient(135deg, #00A8E8, #0077b6)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        color: '#fff', fontSize: '13px', fontWeight: 700,
                      }}>
                        {(review.customer_name || 'U')[0].toUpperCase()}
                      </div>
                      <span style={{ fontWeight: 700, fontSize: '13px', color: '#1f2937' }}>
                        {review.customer_name || 'Cliente'}
                      </span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '2px' }}>
                      {[1, 2, 3, 4, 5].map(s => (
                        <Star key={s} size={14} fill={s <= review.rating ? '#FFC400' : 'none'} color={s <= review.rating ? '#FFC400' : '#d1d5db'} />
                      ))}
                    </div>
                  </div>
                  {review.comment && (
                    <p style={{ fontSize: '13px', color: '#4b5563', lineHeight: 1.6, margin: 0 }}>{review.comment}</p>
                  )}
                  <span style={{ fontSize: '11px', color: '#9ca3af', marginTop: '6px', display: 'block' }}>
                    {new Date(review.created_at).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' })}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default VendorDetailPage;
