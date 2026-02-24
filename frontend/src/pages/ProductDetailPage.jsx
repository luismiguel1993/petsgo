import React, { useState, useEffect } from 'react';
import { useParams, Link, useLocation } from 'react-router-dom';
import { ArrowLeft, Star, ShoppingCart, Plus, Minus, Truck, Shield, Clock, Heart, Share2, Check, MessageSquare } from 'lucide-react';
import { useCart } from '../context/CartContext';
import { getProductReviews, getProductDetail } from '../services/api';
import { getProductImage } from '../utils/productImages';

const ProductDetailPage = () => {
  const { id } = useParams();
  const location = useLocation();
  const stateProduct = location.state?.product;
  const { addItem, getItemQuantity, updateQuantity } = useCart();
  const [product, setProduct] = useState(stateProduct || null);
  const [loading, setLoading] = useState(!stateProduct);
  const [selectedImage, setSelectedImage] = useState(0);
  const [addedToCart, setAddedToCart] = useState(false);
  const [reviews, setReviews] = useState([]);
  const [reviewAvg, setReviewAvg] = useState(null);
  const [reviewCount, setReviewCount] = useState(0);

  // Si no hay product en el state (acceso directo por URL), intentar cargar desde API
  useEffect(() => {
    if (!stateProduct && id) {
      setLoading(true);
      getProductDetail(id).then(res => {
        const p = res.data?.data || res.data;
        if (p) {
          setProduct({
            ...p,
            name: p.product_name || p.name,
            image: p.image_url || p.image,
            brand: p.store_name || p.brand || '',
            price: Number(p.final_price || p.price),
            originalPrice: p.discount_active ? Number(p.price) : null,
            rating: p.rating || null,
          });
        }
      }).catch(() => {}).finally(() => setLoading(false));
    }
  }, [id, stateProduct]);

  useEffect(() => {
    const productId = product?.id;
    if (productId) {
      getProductReviews(productId).then(res => {
        setReviews(res.data?.reviews || []);
        setReviewAvg(res.data?.average);
        setReviewCount(res.data?.count || 0);
      }).catch(() => {});
    }
  }, [product?.id]);

  const formatPrice = (price) =>
    new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(price);

  if (loading) {
    return (
      <div style={{ maxWidth: '960px', margin: '0 auto', padding: '80px 24px', textAlign: 'center', fontFamily: 'Poppins, sans-serif' }}>
        <div style={{ fontSize: '48px', marginBottom: '16px', animation: 'pulse 1.5s infinite' }}>🐾</div>
        <p style={{ color: '#9ca3af', fontSize: '16px', fontWeight: 600 }}>Cargando producto...</p>
      </div>
    );
  }

  if (!product) {
    return (
      <div style={{ maxWidth: '960px', margin: '0 auto', padding: '80px 24px', textAlign: 'center', fontFamily: 'Poppins, sans-serif' }}>
        <div style={{ fontSize: '64px', marginBottom: '16px' }}>🐾</div>
        <h2 style={{ fontSize: '24px', fontWeight: 800, color: '#2F3A40', marginBottom: '8px' }}>Producto no encontrado</h2>
        <p style={{ color: '#9ca3af', marginBottom: '24px' }}>No pudimos encontrar la información de este producto.</p>
        <Link to="/" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          background: '#00A8E8', color: '#fff', padding: '12px 28px',
          borderRadius: '12px', fontWeight: 700, fontSize: '14px', textDecoration: 'none',
        }}>
          <ArrowLeft size={16} /> Volver al Inicio
        </Link>
      </div>
    );
  }

  const productImage = getProductImage(product);
  const qty = getItemQuantity(product.id);
  const discount = product.originalPrice ? Math.round((1 - product.price / product.originalPrice) * 100) : null;
  const displayRating = reviewAvg || product.rating || null;
  const displayReviewCount = reviewCount;

  const handleAddToCart = () => {
    addItem({ ...product, quantity: 1 });
    setAddedToCart(true);
    setTimeout(() => setAddedToCart(false), 2000);
  };

  const features = [
    { icon: Truck, label: 'Despacho gratis', desc: 'En tu primer pedido' },
    { icon: Shield, label: 'Compra segura', desc: 'Pago 100% protegido' },
    { icon: Clock, label: 'Entrega rápida', desc: '24-48 hrs hábiles' },
  ];

  return (
    <div style={{ fontFamily: 'Poppins, sans-serif', background: '#f8f9fa' }}>
      <style>{`
        @media (max-width: 768px) {
          .pd-main { grid-template-columns: 1fr !important; gap: 24px !important; padding: 16px 16px 48px !important; }
          .pd-breadcrumb { padding: 16px 16px 0 !important; }
          .pd-title { font-size: 22px !important; }
          .pd-price-box { padding: 16px !important; }
          .pd-price { font-size: 28px !important; }
          .pd-features { grid-template-columns: 1fr !important; }
          .pd-thumbs { justify-content: flex-start !important; }
        }
      `}</style>
      {/* Breadcrumb */}
      <div className="pd-breadcrumb" style={{ maxWidth: '1200px', margin: '0 auto', padding: '20px 32px 0' }}>
        <Link to="/" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          color: '#9ca3af', fontSize: '13px', fontWeight: 600, textDecoration: 'none',
          transition: 'color 0.2s',
        }}
          onMouseEnter={(e) => e.target.style.color = '#00A8E8'}
          onMouseLeave={(e) => e.target.style.color = '#9ca3af'}
        >
          <ArrowLeft size={16} /> Volver a productos
        </Link>
      </div>

      {/* Main content */}
      <div className="pd-main" style={{
        maxWidth: '1200px', margin: '0 auto', padding: '24px 32px 64px',
        display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '48px', alignItems: 'start',
      }}>
        {/* Image section */}
        <div>
          <div style={{
            background: '#fff', borderRadius: '24px', overflow: 'hidden',
            aspectRatio: '1', display: 'flex', alignItems: 'center', justifyContent: 'center',
            padding: '40px', position: 'relative', border: '1px solid #f0f0f0',
          }}>
            {discount && (
              <span style={{
                position: 'absolute', top: '20px', left: '20px',
                background: '#ef4444', color: '#fff', fontSize: '13px', fontWeight: 800,
                padding: '6px 14px', borderRadius: '10px',
              }}>
                -{discount}%
              </span>
            )}
            <img
              src={productImage}
              alt={product.name || product.product_name}
              style={{
                maxWidth: '100%', maxHeight: '100%', objectFit: 'contain',
                transition: 'transform 0.4s ease',
              }}
              onError={(e) => { e.target.src = 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80'; }}
            />
          </div>
          {/* Thumbnail strip */}
          <div className="pd-thumbs" style={{ display: 'flex', gap: '12px', marginTop: '16px', justifyContent: 'center' }}>
            {[0, 1, 2].map((i) => (
              <div
                key={i}
                onClick={() => setSelectedImage(i)}
                style={{
                  width: '72px', height: '72px', borderRadius: '14px', overflow: 'hidden',
                  border: selectedImage === i ? '2px solid #00A8E8' : '2px solid #f0f0f0',
                  cursor: 'pointer', background: '#fff', display: 'flex',
                  alignItems: 'center', justifyContent: 'center', padding: '8px',
                  transition: 'border-color 0.2s',
                }}
              >
                <img
                  src={productImage}
                  alt={`Vista ${i + 1}`}
                  style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain', transform: i === 1 ? 'scaleX(-1)' : i === 2 ? 'rotate(15deg)' : 'none', opacity: selectedImage === i ? 1 : 0.6 }}
                />
              </div>
            ))}
          </div>
        </div>

        {/* Info section */}
        <div>
          {/* Category & Brand */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px' }}>
            {product.category && (
              <span style={{
                background: '#e0f2fe', color: '#0284c7', fontSize: '11px', fontWeight: 700,
                padding: '5px 12px', borderRadius: '8px', letterSpacing: '0.5px', textTransform: 'uppercase',
              }}>
                {product.category}
              </span>
            )}
            {product.brand && (
              <span style={{ color: '#9ca3af', fontSize: '13px', fontWeight: 600 }}>
                {product.brand}
              </span>
            )}
          </div>

          {/* Name */}
          <h1 className="pd-title" style={{
            fontSize: '28px', fontWeight: 800, color: '#1f2937',
            lineHeight: 1.3, marginBottom: '16px',
          }}>
            {product.name || product.product_name}
          </h1>

          {/* Rating */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '24px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
              {[1, 2, 3, 4, 5].map((s) => (
                <Star
                  key={s}
                  size={18}
                  fill={s <= Math.floor(displayRating || 0) ? '#FFC400' : 'none'}
                  color={s <= Math.floor(displayRating || 0) ? '#FFC400' : '#d1d5db'}
                />
              ))}
            </div>
            {displayRating && <span style={{ fontSize: '14px', fontWeight: 700, color: '#374151' }}>{displayRating}</span>}
            <span style={{ fontSize: '13px', color: '#9ca3af' }}>
              {displayReviewCount > 0 ? `(${displayReviewCount} reseña${displayReviewCount !== 1 ? 's' : ''})` : 'Sin reseñas aún'}
            </span>
          </div>

          {/* Price */}
          <div className="pd-price-box" style={{
            background: '#f0fdf4', borderRadius: '16px', padding: '20px 24px',
            marginBottom: '28px', display: 'flex', alignItems: 'baseline', gap: '12px',
            flexWrap: 'wrap',
          }}>
            <span className="pd-price" style={{ fontSize: '36px', fontWeight: 900, color: '#059669' }}>
              {formatPrice(product.price)}
            </span>
            {product.originalPrice && (
              <>
                <span style={{
                  fontSize: '18px', color: '#9ca3af', textDecoration: 'line-through', fontWeight: 500,
                }}>
                  {formatPrice(product.originalPrice)}
                </span>
                <span style={{
                  background: '#ef4444', color: '#fff', fontSize: '12px', fontWeight: 800,
                  padding: '4px 10px', borderRadius: '8px',
                }}>
                  Ahorras {formatPrice(product.originalPrice - product.price)}
                </span>
              </>
            )}
          </div>

          {/* Description */}
          <div style={{ marginBottom: '28px' }}>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#374151', marginBottom: '8px' }}>Descripción</h3>
            <p style={{ fontSize: '14px', color: '#6b7280', lineHeight: 1.7 }}>
              {product.description || `${product.name || product.product_name} — Producto premium seleccionado para tu mascota. Formulado con ingredientes de primera calidad que aseguran una nutrición completa y balanceada. Contribuye a mantener la salud, energía y vitalidad de tu compañero. Recomendado por veterinarios y avalado por miles de dueños satisfechos en Chile.`}
            </p>
          </div>

          {/* Stock info */}
          {product.stock !== undefined && (
            <div style={{
              display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '24px',
              fontSize: '13px', color: product.stock > 10 ? '#059669' : '#f59e0b', fontWeight: 600,
            }}>
              <div style={{
                width: '8px', height: '8px', borderRadius: '50%',
                background: product.stock > 10 ? '#059669' : '#f59e0b',
              }} />
              {product.stock > 10 ? `Stock disponible (${product.stock} unidades)` : `¡Últimas ${product.stock} unidades!`}
            </div>
          )}

          {/* Add to cart */}
          <div style={{
            display: 'flex', gap: '12px', alignItems: 'center', marginBottom: '32px',
          }}>
            {qty > 0 ? (
              <div style={{
                display: 'flex', alignItems: 'center', gap: '4px',
                background: '#f0f9ff', borderRadius: '14px', padding: '6px',
              }}>
                <button
                  onClick={() => updateQuantity(product.id, qty - 1)}
                  style={{
                    width: '44px', height: '44px', borderRadius: '10px',
                    border: 'none', background: '#fff', cursor: 'pointer',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    boxShadow: '0 1px 3px rgba(0,0,0,0.08)', transition: 'all 0.15s',
                  }}
                >
                  <Minus size={18} color="#00A8E8" />
                </button>
                <span style={{
                  fontSize: '18px', fontWeight: 700, color: '#1f2937',
                  minWidth: '40px', textAlign: 'center',
                }}>
                  {qty}
                </span>
                <button
                  onClick={() => addItem({ ...product, quantity: 1 })}
                  style={{
                    width: '44px', height: '44px', borderRadius: '10px',
                    border: 'none', background: '#00A8E8', cursor: 'pointer',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    boxShadow: '0 2px 6px rgba(0,168,232,0.3)',
                  }}
                >
                  <Plus size={18} color="#fff" />
                </button>
              </div>
            ) : null}

            <button
              onClick={handleAddToCart}
              style={{
                flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px',
                background: addedToCart ? '#059669' : '#00A8E8', color: '#fff',
                border: 'none', borderRadius: '14px', padding: '16px 28px',
                fontSize: '15px', fontWeight: 700, cursor: 'pointer',
                transition: 'all 0.3s', boxShadow: '0 4px 16px rgba(0,168,232,0.25)',
              }}
              onMouseEnter={(e) => { if (!addedToCart) e.currentTarget.style.background = '#0090c7'; }}
              onMouseLeave={(e) => { if (!addedToCart) e.currentTarget.style.background = '#00A8E8'; }}
            >
              {addedToCart ? <Check size={20} /> : <ShoppingCart size={20} />}
              {addedToCart ? '¡Agregado al carrito!' : qty > 0 ? 'Agregar más al carrito' : 'Agregar al carrito'}
            </button>

            <button style={{
              width: '52px', height: '52px', borderRadius: '14px',
              border: '2px solid #f0f0f0', background: '#fff',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              cursor: 'pointer', transition: 'all 0.2s', flexShrink: 0,
            }}
              onMouseEnter={(e) => { e.currentTarget.style.borderColor = '#ef4444'; e.currentTarget.querySelector('svg').style.color = '#ef4444'; }}
              onMouseLeave={(e) => { e.currentTarget.style.borderColor = '#f0f0f0'; e.currentTarget.querySelector('svg').style.color = '#9ca3af'; }}
            >
              <Heart size={20} color="#9ca3af" style={{ transition: 'color 0.2s' }} />
            </button>
          </div>

          {/* Features */}
          <div className="pd-features" style={{
            display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '12px',
          }}>
            {features.map((feat, i) => (
              <div key={i} style={{
                background: '#fff', borderRadius: '14px', padding: '16px',
                textAlign: 'center', border: '1px solid #f0f0f0',
              }}>
                <feat.icon size={22} color="#00A8E8" style={{ margin: '0 auto 8px' }} />
                <div style={{ fontSize: '12px', fontWeight: 700, color: '#374151', marginBottom: '2px' }}>{feat.label}</div>
                <div style={{ fontSize: '11px', color: '#9ca3af' }}>{feat.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Reviews Section */}
      <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '0 32px 64px' }}>
        <div style={{ background: '#fff', borderRadius: '20px', padding: '28px', border: '1px solid #f0f0f0' }}>
          <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#1f2937', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <MessageSquare size={20} color="#00A8E8" /> Reseñas de Clientes
            {reviewCount > 0 && (
              <span style={{ fontSize: '13px', fontWeight: 500, color: '#9ca3af' }}>({reviewCount})</span>
            )}
          </h3>
          {reviews.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '32px 0', color: '#9ca3af' }}>
              <Star size={32} color="#d1d5db" style={{ margin: '0 auto 8px' }} />
              <p style={{ fontWeight: 600, fontSize: '14px' }}>Aún no hay reseñas para este producto</p>
              <p style={{ fontSize: '12px' }}>¡Sé el primero en valorar después de tu compra!</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
              {reviews.map((review, idx) => (
                <div key={idx} style={{
                  padding: '16px', background: '#fafafa', borderRadius: '14px',
                  border: '1px solid #f3f4f6',
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

export default ProductDetailPage;
