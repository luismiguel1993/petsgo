import React, { useState, useEffect } from 'react';
import { Check, Star, Crown, Store, Send, User, Mail, Phone, MapPin, Building2, MessageSquare, Sparkles, ChevronDown } from 'lucide-react';
import { getPlans, submitVendorLead } from '../services/api';
import { REGIONES, getComunas, formatPhoneDigits, buildFullPhone, sanitizeName, checkFormForSqlInjection, sanitizeInput } from '../utils/chile';

const DEMO_PLANS = [
  { id: 1, plan_name: 'Básico', monthly_price: 29990, is_featured: 0, features_json: '{"max_products":50,"commission_rate":15,"support":"email","analytics":false,"featured":false}' },
  { id: 2, plan_name: 'Pro', monthly_price: 59990, is_featured: 1, features_json: '{"max_products":200,"commission_rate":10,"support":"email+chat","analytics":true,"featured":true}' },
  { id: 3, plan_name: 'Enterprise', monthly_price: 99990, is_featured: 0, features_json: '{"max_products":-1,"commission_rate":5,"support":"prioritario","analytics":true,"featured":true,"api_access":true}' },
];

const featuresToList = (json) => {
  try {
    const obj = typeof json === 'string' ? JSON.parse(json) : json;
    if (Array.isArray(obj)) return obj;
    const labels = [];
    if (obj.max_products === -1) labels.push('Productos ilimitados');
    else if (obj.max_products) labels.push(`Hasta ${obj.max_products} productos`);
    if (obj.commission_rate) labels.push(`Comisión ${obj.commission_rate}%`);
    if (obj.support === 'email') labels.push('Soporte por email');
    else if (obj.support === 'email+chat') labels.push('Soporte email + chat');
    else if (obj.support === 'prioritario') labels.push('Soporte prioritario 24/7');
    if (obj.analytics) labels.push('Dashboard con analíticas');
    if (obj.featured) labels.push('Productos destacados');
    if (obj.api_access) labels.push('Acceso API personalizado');
    return labels;
  } catch {
    return [];
  }
};

const PlansPage = () => {
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [annualFreeMonths, setAnnualFreeMonths] = useState(2);
  const [formData, setFormData] = useState({
    storeName: '', contactName: '', email: '', phone: '', region: '', comuna: '', message: '', plan: '',
  });
  const [formSent, setFormSent] = useState(false);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    const load = async () => {
      try {
        const { data } = await getPlans();
        setPlans(data?.data?.length > 0 ? data.data : (data?.length > 0 ? data : DEMO_PLANS));
        if (data?.annual_free_months !== undefined) setAnnualFreeMonths(data.annual_free_months);
      } catch {
        setPlans(DEMO_PLANS);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    const sqlCheck = checkFormForSqlInjection(formData);
    if (sqlCheck) { setFormError(sqlCheck); setSubmitting(false); return; }
    setSubmitting(true);
    try {
      await submitVendorLead({ ...formData, phone: buildFullPhone(formData.phone) });
      setFormSent(true);
      setTimeout(() => setFormSent(false), 8000);
      setFormData({ storeName: '', contactName: '', email: '', phone: '', region: '', comuna: '', message: '', plan: '' });
    } catch (err) {
      setFormError(err.response?.data?.message || 'Error al enviar el formulario. Intenta nuevamente.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleChange = (field, value) => {
    if (field === 'contactName') value = sanitizeName(value);
    if (field === 'storeName' || field === 'message') value = sanitizeInput(value);
    if (field === 'region') return setFormData(prev => ({ ...prev, region: value, comuna: '' }));
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const colors = ['#00A8E8', '#FFC400', '#2F3A40'];
  const icons = [Star, Crown, Crown];

  const inputStyle = {
    width: '100%', padding: '14px 16px 14px 46px', borderRadius: '12px',
    border: '2px solid #e5e7eb', fontSize: '14px', fontWeight: 500,
    fontFamily: 'Poppins, sans-serif', outline: 'none', transition: 'border-color 0.2s',
    background: '#fff', color: '#374151', boxSizing: 'border-box',
  };

  const selectStyle = {
    ...inputStyle, cursor: 'pointer', appearance: 'none', WebkitAppearance: 'none',
    paddingRight: '40px', backgroundImage: 'none',
  };

  const iconWrapStyle = {
    position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)',
    color: '#9ca3af', pointerEvents: 'none',
  };

  const selectArrowStyle = {
    position: 'absolute', right: '14px', top: '50%', transform: 'translateY(-50%)',
    color: '#9ca3af', pointerEvents: 'none',
  };

  return (
    <div style={{ fontFamily: 'Poppins, sans-serif', background: '#f8f9fa' }}>
      <style>{`
        @media (max-width: 768px) {
          .plans-grid { grid-template-columns: 1fr !important; max-width: 400px; margin: 0 auto; }
          .plans-grid > div { transform: scale(1) !important; }
          .benefits-grid { grid-template-columns: repeat(2, 1fr) !important; }
          .contact-grid { grid-template-columns: 1fr !important; }
          .contact-left { padding: 32px 24px !important; }
          .contact-right { padding: 32px 24px !important; }
          .form-row { grid-template-columns: 1fr !important; }
          .hero-title { font-size: 28px !important; }
          .hero-section { padding: 48px 16px 64px !important; }
        }
        @media (max-width: 480px) {
          .benefits-grid { grid-template-columns: 1fr !important; }
          .plans-wrapper { padding: 0 16px !important; }
        }
      `}</style>

      {/* ========== HERO SECTION ========== */}
      <div className="hero-section" style={{
        background: 'linear-gradient(135deg, #00A8E8 0%, #0077B6 50%, #005f8a 100%)',
        padding: '64px 24px 80px', textAlign: 'center', position: 'relative', overflow: 'hidden',
      }}>
        <div style={{
          position: 'absolute', top: '-60px', right: '-60px', width: '200px', height: '200px',
          background: 'rgba(255,255,255,0.06)', borderRadius: '50%',
        }} />
        <div style={{
          position: 'absolute', bottom: '-30px', left: '-30px', width: '150px', height: '150px',
          background: 'rgba(255,196,0,0.1)', borderRadius: '50%',
        }} />
        <div style={{ position: 'relative', zIndex: 1 }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', gap: '8px',
            background: 'rgba(255,196,0,0.2)', borderRadius: '50px', padding: '8px 20px',
            marginBottom: '20px',
          }}>
            <Sparkles size={16} color="#FFC400" />
            <span style={{ color: '#FFC400', fontSize: '13px', fontWeight: 700 }}>PLANES PARA TIENDAS</span>
          </div>
          <h1 className="hero-title" style={{
            fontSize: '40px', fontWeight: 900, color: '#fff', marginBottom: '16px',
            lineHeight: 1.2, maxWidth: '600px', margin: '0 auto 16px',
          }}>
            Vende en PetsGo y llega a miles de clientes
          </h1>
          <p style={{
            fontSize: '16px', color: 'rgba(255,255,255,0.8)', maxWidth: '520px',
            margin: '0 auto', lineHeight: 1.6,
          }}>
            Elige el plan que mejor se adapte a tu negocio. Puedes cambiar de plan en cualquier momento.
          </p>
        </div>
      </div>

      {/* ========== PLANES ========== */}
      <div className="plans-wrapper" style={{
        maxWidth: '1000px', margin: '-48px auto 0', padding: '0 24px', position: 'relative', zIndex: 2,
      }}>
        <div className="plans-grid" style={{
          display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '24px',
        }}>
          {(loading ? DEMO_PLANS : plans).map((plan, idx) => {
            const features = featuresToList(plan.features_json || plan.features);
            const isPro = parseInt(plan.is_featured) === 1;
            const color = colors[idx] || '#00A8E8';
            const Icon = icons[idx] || Star;

            return (
              <div
                key={plan.id}
                style={{
                  background: '#fff', borderRadius: '24px', padding: '36px 32px',
                  position: 'relative', border: isPro ? '2px solid #FFC400' : '1px solid #f0f0f0',
                  transform: isPro ? 'scale(1.05)' : 'scale(1)',
                  boxShadow: isPro ? '0 20px 60px rgba(255,196,0,0.15)' : '0 4px 20px rgba(0,0,0,0.04)',
                  transition: 'all 0.3s ease', zIndex: isPro ? 2 : 1,
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.transform = isPro ? 'scale(1.07)' : 'scale(1.03)';
                  e.currentTarget.style.boxShadow = '0 20px 60px rgba(0,0,0,0.1)';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.transform = isPro ? 'scale(1.05)' : 'scale(1)';
                  e.currentTarget.style.boxShadow = isPro ? '0 20px 60px rgba(255,196,0,0.15)' : '0 4px 20px rgba(0,0,0,0.04)';
                }}
              >
                {isPro && (
                  <span style={{
                    position: 'absolute', top: '-14px', left: '50%', transform: 'translateX(-50%)',
                    background: 'linear-gradient(135deg, #FFC400, #ffb300)', color: '#2F3A40',
                    fontSize: '11px', fontWeight: 800, padding: '6px 20px', borderRadius: '50px',
                    letterSpacing: '1px', boxShadow: '0 4px 12px rgba(255,196,0,0.3)',
                  }}>
                    ⭐ MÁS POPULAR
                  </span>
                )}

                <div style={{
                  width: '52px', height: '52px', borderRadius: '16px',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  marginBottom: '20px', background: color + '15',
                }}>
                  <Icon size={24} color={color} />
                </div>

                <h3 style={{ fontSize: '22px', fontWeight: 800, color: '#2F3A40', marginBottom: '8px' }}>
                  {plan.plan_name}
                </h3>
                <div style={{ display: 'flex', alignItems: 'baseline', gap: '4px', marginBottom: '28px' }}>
                  <span style={{ fontSize: '36px', fontWeight: 900, color }}>{formatPrice(plan.monthly_price)}</span>
                  <span style={{ color: '#9ca3af', fontSize: '14px', fontWeight: 500 }}>/mes</span>
                </div>

                {annualFreeMonths > 0 && (
                  <div style={{
                    background: 'linear-gradient(135deg, #e8f5e9, #c8e6c9)', borderRadius: 10,
                    padding: '10px 16px', marginBottom: 20, textAlign: 'center',
                  }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: '#2e7d32' }}>
                      🎁 Plan Anual: {annualFreeMonths} {annualFreeMonths === 1 ? 'mes gratis' : 'meses gratis'}
                    </div>
                    <div style={{ fontSize: 12, color: '#43a047', marginTop: 2 }}>
                      Paga {12 - annualFreeMonths} de 12 meses ({formatPrice(plan.monthly_price * (12 - annualFreeMonths))}/año)
                    </div>
                  </div>
                )}

                <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: '20px', marginBottom: '28px' }}>
                  {features.map((feat, i) => (
                    <div key={i} style={{
                      display: 'flex', alignItems: 'center', gap: '10px',
                      padding: '8px 0', fontSize: '14px', fontWeight: 500, color: '#4b5563',
                    }}>
                      <div style={{
                        width: '22px', height: '22px', borderRadius: '6px',
                        background: color + '15', display: 'flex', alignItems: 'center', justifyContent: 'center',
                        flexShrink: 0,
                      }}>
                        <Check size={13} color={color} strokeWidth={3} />
                      </div>
                      {feat}
                    </div>
                  ))}
                </div>

                <button
                  onClick={() => {
                    handleChange('plan', plan.plan_name);
                    document.getElementById('contact-form')?.scrollIntoView({ behavior: 'smooth' });
                  }}
                  style={{
                    width: '100%', padding: '14px', borderRadius: '14px',
                    fontSize: '14px', fontWeight: 700, cursor: 'pointer',
                    transition: 'all 0.2s', border: isPro ? 'none' : `2px solid ${color}`,
                    background: isPro ? color : 'transparent',
                    color: isPro ? (idx === 1 ? '#2F3A40' : '#fff') : color,
                  }}
                  onMouseEnter={(e) => {
                    if (!isPro) { e.currentTarget.style.background = color; e.currentTarget.style.color = idx === 0 ? '#fff' : '#2F3A40'; }
                  }}
                  onMouseLeave={(e) => {
                    if (!isPro) { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = color; }
                  }}
                >
                  Elegir Plan
                </button>
              </div>
            );
          })}
        </div>
      </div>

      {/* ========== BENEFICIOS ========== */}
      <div style={{
        maxWidth: '1000px', margin: '80px auto 0', padding: '0 24px',
      }}>
        <div style={{ textAlign: 'center', marginBottom: '40px' }}>
          <h2 style={{ fontSize: '28px', fontWeight: 900, color: '#2F3A40', marginBottom: '12px' }}>
            ¿Por qué vender en PetsGo?
          </h2>
          <p style={{ color: '#9ca3af', fontSize: '15px', maxWidth: '500px', margin: '0 auto' }}>
            Miles de dueños de mascotas buscan productos todos los días en nuestra plataforma.
          </p>
        </div>
        <div className="benefits-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '20px' }}>
          {[
            { emoji: '🚀', title: 'Alcance masivo', desc: '+50.000 usuarios activos en RM' },
            { emoji: '📦', title: 'Logística incluida', desc: 'Riders PetsGo recogen y entregan' },
            { emoji: '💳', title: 'Pagos seguros', desc: 'Cobro automático en tu cuenta' },
            { emoji: '📊', title: 'Dashboard completo', desc: 'Gestiona productos y pedidos' },
          ].map((b, i) => (
            <div key={i} style={{
              background: '#fff', borderRadius: '20px', padding: '28px 24px',
              textAlign: 'center', border: '1px solid #f0f0f0',
              transition: 'all 0.3s',
            }}
              onMouseEnter={(e) => { e.currentTarget.style.transform = 'translateY(-4px)'; e.currentTarget.style.boxShadow = '0 12px 40px rgba(0,0,0,0.06)'; }}
              onMouseLeave={(e) => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = 'none'; }}
            >
              <div style={{ fontSize: '36px', marginBottom: '14px' }}>{b.emoji}</div>
              <h4 style={{ fontSize: '15px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' }}>{b.title}</h4>
              <p style={{ fontSize: '13px', color: '#9ca3af', lineHeight: 1.5 }}>{b.desc}</p>
            </div>
          ))}
        </div>
      </div>

      {/* ========== FORMULARIO DE CONTACTO ========== */}
      <div id="contact-form" style={{
        maxWidth: '1100px', margin: '80px auto 0', padding: '0 24px 80px',
      }}>
        <div style={{
          background: '#fff', borderRadius: '28px', overflow: 'hidden',
          boxShadow: '0 8px 40px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
          display: 'grid', gridTemplateColumns: '2fr 3fr',
        }} className="contact-grid">
          {/* Left - info */}
          <div className="contact-left" style={{
            background: 'linear-gradient(135deg, #00A8E8, #0077B6)',
            padding: '48px 40px', color: '#fff', display: 'flex', flexDirection: 'column', justifyContent: 'center',
          }}>
            <div style={{
              width: '56px', height: '56px', background: 'rgba(255,255,255,0.15)',
              borderRadius: '16px', display: 'flex', alignItems: 'center', justifyContent: 'center',
              marginBottom: '24px',
            }}>
              <Store size={28} color="#fff" />
            </div>
            <h2 style={{ fontSize: '28px', fontWeight: 900, marginBottom: '12px', lineHeight: 1.3 }}>
              ¿Tienes una tienda de mascotas?
            </h2>
            <p style={{ fontSize: '15px', lineHeight: 1.7, color: 'rgba(255,255,255,0.85)', marginBottom: '32px' }}>
              Únete a nuestra red de tiendas asociadas y empieza a vender tus productos en PetsGo. 
              Te contactaremos en menos de 24 horas.
            </p>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              {[
                { icon: '✅', text: 'Sin costo de inscripción' },
                { icon: '✅', text: 'Alta en menos de 48 horas' },
                { icon: '✅', text: 'Soporte dedicado para tu tienda' },
                { icon: '✅', text: 'Panel de gestión incluido' },
              ].map((item, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '14px', fontWeight: 600 }}>
                  <span>{item.icon}</span> {item.text}
                </div>
              ))}
            </div>
          </div>

          {/* Right - form */}
          <div className="contact-right" style={{ padding: '40px 36px' }}>
            <h3 style={{ fontSize: '22px', fontWeight: 800, color: '#2F3A40', marginBottom: '8px' }}>
              Solicita tu registro
            </h3>
            <p style={{ fontSize: '13px', color: '#9ca3af', marginBottom: '28px' }}>
              Completa el formulario y nuestro equipo te contactará pronto.
            </p>

            {formSent ? (
              <div style={{
                background: '#f0fdf4', borderRadius: '16px', padding: '40px', textAlign: 'center',
              }}>
                <div style={{ fontSize: '48px', marginBottom: '12px' }}>🎉</div>
                <h4 style={{ fontSize: '18px', fontWeight: 700, color: '#059669', marginBottom: '8px' }}>¡Solicitud enviada!</h4>
                <p style={{ fontSize: '14px', color: '#6b7280' }}>Te contactaremos en menos de 24 horas a tu email.</p>
              </div>
            ) : (
              <form onSubmit={handleSubmit} autoComplete="off" style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                <div className="form-row" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div style={{ position: 'relative' }}>
                    <div style={iconWrapStyle}><Building2 size={18} /></div>
                    <input
                      type="text" required placeholder="Nombre de tu tienda" autoComplete="off"
                      value={formData.storeName} onChange={(e) => handleChange('storeName', e.target.value)}
                      style={inputStyle}
                      onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                      onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                    />
                  </div>
                  <div style={{ position: 'relative' }}>
                    <div style={iconWrapStyle}><User size={18} /></div>
                    <input
                      type="text" required placeholder="Nombre de contacto" inputMode="text" autoComplete="off"
                      value={formData.contactName} onChange={(e) => handleChange('contactName', e.target.value)}
                      style={inputStyle}
                      onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                      onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                    />
                  </div>
                </div>

                <div className="form-row" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div style={{ position: 'relative' }}>
                    <div style={iconWrapStyle}><Mail size={18} /></div>
                    <input
                      type="email" required placeholder="Email de contacto" autoComplete="off"
                      value={formData.email} onChange={(e) => handleChange('email', e.target.value)}
                      style={inputStyle}
                      onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                      onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                    />
                  </div>
                  <div style={{ position: 'relative' }}>
                    <div style={{ ...iconWrapStyle, left: '14px' }}><Phone size={18} /></div>
                    <div style={{ display: 'flex' }}>
                      <span style={{ padding: '14px 10px 14px 46px', background: '#f3f4f6', borderRadius: '12px 0 0 12px', border: '2px solid #e5e7eb', borderRight: 'none', fontSize: '14px', fontWeight: 600, color: '#374151', whiteSpace: 'nowrap', lineHeight: '1.2' }}>+569</span>
                      <input
                        type="tel" required placeholder="XXXXXXXX" maxLength={8} autoComplete="off"
                        value={formData.phone} onChange={(e) => handleChange('phone', formatPhoneDigits(e.target.value))}
                        style={{ ...inputStyle, paddingLeft: '14px', borderRadius: '0 12px 12px 0' }}
                        onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                        onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                      />
                    </div>
                  </div>
                </div>

                <div className="form-row" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div style={{ position: 'relative' }}>
                    <div style={iconWrapStyle}><MapPin size={18} /></div>
                    <div style={selectArrowStyle}><ChevronDown size={16} /></div>
                    <select
                      required value={formData.region}
                      onChange={(e) => handleChange('region', e.target.value)}
                      style={{ ...selectStyle, color: formData.region ? '#374151' : '#9ca3af' }}
                      onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                      onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                    >
                      <option value="" disabled>Selecciona región...</option>
                      {REGIONES.map(r => <option key={r} value={r} style={{ color: '#374151' }}>{r}</option>)}
                    </select>
                  </div>
                  <div style={{ position: 'relative' }}>
                    <div style={iconWrapStyle}><MapPin size={18} /></div>
                    <div style={selectArrowStyle}><ChevronDown size={16} /></div>
                    <select
                      required value={formData.comuna}
                      onChange={(e) => handleChange('comuna', e.target.value)}
                      disabled={!formData.region}
                      style={{ ...selectStyle, color: formData.comuna ? '#374151' : '#9ca3af', opacity: formData.region ? 1 : 0.6, cursor: formData.region ? 'pointer' : 'not-allowed' }}
                      onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                      onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                    >
                      <option value="" disabled>{formData.region ? 'Selecciona comuna...' : 'Primero selecciona región'}</option>
                      {getComunas(formData.region).map(c => <option key={c} value={c} style={{ color: '#374151' }}>{c}</option>)}
                    </select>
                  </div>
                </div>

                <div style={{ position: 'relative' }}>
                  <div style={iconWrapStyle}><Sparkles size={18} /></div>
                  <div style={selectArrowStyle}><ChevronDown size={16} /></div>
                  <select
                    required value={formData.plan}
                    onChange={(e) => handleChange('plan', e.target.value)}
                    style={{ ...selectStyle, color: formData.plan ? '#374151' : '#9ca3af' }}
                    onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                    onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                  >
                    <option value="" disabled>¿En qué plan estás interesado?</option>
                    {plans.map(p => <option key={p.id || p.plan_name} value={p.plan_name} style={{ color: '#374151' }}>{p.plan_name}</option>)}
                  </select>
                </div>

                <div style={{ position: 'relative' }}>
                  <div style={{ ...iconWrapStyle, top: '20px', transform: 'none' }}><MessageSquare size={18} /></div>
                  <textarea
                    placeholder="Cuéntanos sobre tu tienda y qué productos vendes..."
                    rows={3} value={formData.message} onChange={(e) => handleChange('message', e.target.value)}
                    style={{ ...inputStyle, resize: 'vertical', minHeight: '90px', paddingTop: '14px' }}
                    onFocus={(e) => e.target.style.borderColor = '#00A8E8'}
                    onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                  />
                </div>

                {formError && (
                  <div style={{
                    background: '#fef2f2', color: '#dc2626', padding: '12px 16px',
                    borderRadius: '10px', fontSize: '13px', fontWeight: 600, marginBottom: '8px',
                    border: '1px solid #fecaca',
                  }}>
                    ❌ {formError}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={submitting}
                  style={{
                    width: '100%', padding: '16px', borderRadius: '14px',
                    background: submitting ? '#94a3b8' : 'linear-gradient(135deg, #00A8E8, #0077B6)',
                    color: '#fff', fontSize: '15px', fontWeight: 700, border: 'none',
                    cursor: submitting ? 'not-allowed' : 'pointer', display: 'flex', alignItems: 'center',
                    justifyContent: 'center', gap: '10px', transition: 'all 0.2s',
                    boxShadow: submitting ? 'none' : '0 4px 16px rgba(0,168,232,0.25)',
                    opacity: submitting ? 0.7 : 1,
                  }}
                  onMouseEnter={(e) => { if (!submitting) { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 8px 24px rgba(0,168,232,0.35)'; } }}
                  onMouseLeave={(e) => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = submitting ? 'none' : '0 4px 16px rgba(0,168,232,0.25)'; }}
                >
                  <Send size={18} /> {submitting ? 'Enviando...' : 'Enviar Solicitud'}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>

    </div>
  );
};

export default PlansPage;
