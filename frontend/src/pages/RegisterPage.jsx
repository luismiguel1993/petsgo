import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Eye, EyeOff, Check, X, AlertCircle, CheckCircle, FileText, Shield, ChevronDown } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { getLegalPage } from '../services/api';
import huellaSvg from '../assets/Huella-1.svg';
import { REGIONES, getComunas, validateRut, formatRut, formatPhoneDigits, isValidPhoneDigits, buildFullPhone, sanitizeName, isValidName, checkFormForSqlInjection } from '../utils/chile';

const inputStyle = {
  width: '100%', padding: '12px 16px', background: '#f9fafb',
  borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
  fontWeight: 500, outline: 'none', transition: 'all 0.2s', boxSizing: 'border-box',
};
const labelStyle = { display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' };

const RegisterPage = () => {
  const [form, setForm] = useState({
    first_name: '', last_name: '', email: '', password: '', confirmPassword: '',
    id_type: 'rut', id_number: '', phone: '', birth_date: '',
    region: '', comuna: '',
    address_street: '', address_detail: '', address_alias: '',
  });
  const [showAddressFields, setShowAddressFields] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [showSuccessModal, setShowSuccessModal] = useState(false);
  const [countdown, setCountdown] = useState(3);
  const [showTermsModal, setShowTermsModal] = useState(false);
  const [termsTab, setTermsTab] = useState('terms'); // 'terms' | 'privacy'
  const [reachedBottom, setReachedBottom] = useState(false);
  const [termsHtml, setTermsHtml] = useState('');
  const [privacyHtml, setPrivacyHtml] = useState('');
  const [termsLoading, setTermsLoading] = useState(false);
  const scrollRef = useRef(null);
  const { register } = useAuth();
  const navigate = useNavigate();

  // Countdown timer for success modal
  useEffect(() => {
    if (!showSuccessModal) return;
    if (countdown <= 0) { navigate('/login'); return; }
    const timer = setTimeout(() => setCountdown(c => c - 1), 1000);
    return () => clearTimeout(timer);
  }, [showSuccessModal, countdown, navigate]);

  // Default legal content (fallback)
  const defaultTerms = `
    <h2>1. Aceptación de los Términos</h2><p>Al acceder y utilizar PetsGo, aceptas estar sujeto a estos términos y condiciones de uso.</p>
    <h2>2. Descripción del Servicio</h2><p>PetsGo es un marketplace en línea que conecta tiendas de mascotas con clientes, facilitando la compra y despacho de productos para mascotas en Chile.</p>
    <h2>3. Registro y Cuentas</h2><p>Para utilizar ciertas funcionalidades, debes crear una cuenta proporcionando información veraz y actualizada. Eres responsable de mantener la confidencialidad de tu contraseña.</p>
    <h2>4. Compras y Pagos</h2><p>Los precios de los productos son establecidos por cada tienda vendedora. PetsGo se reserva el derecho de aplicar cargos por servicio o despacho.</p>
    <h2>5. Despacho</h2><p>Los tiempos de despacho dependerán de la disponibilidad del producto, la ubicación del cliente y la cobertura del servicio.</p>
    <h2>6. Devoluciones y Reembolsos</h2><p>Los clientes pueden solicitar devoluciones dentro de los plazos establecidos por la ley de protección al consumidor chilena.</p>
    <h2>7. Responsabilidad</h2><p>PetsGo no se hace responsable por la calidad de los productos vendidos por las tiendas adheridas, actuando únicamente como plataforma intermediaria.</p>
    <h2>8. Propiedad Intelectual</h2><p>Todo el contenido del marketplace, incluyendo logos, diseños y textos, son propiedad de PetsGo SpA.</p>
    <h2>9. Modificaciones</h2><p>PetsGo se reserva el derecho de modificar estos términos en cualquier momento.</p>
    <h2>10. Legislación Aplicable</h2><p>Estos términos se rigen por las leyes de la República de Chile.</p>
  `;
  const defaultPrivacy = `
    <h2>1. Información que Recopilamos</h2><p>Recopilamos la información personal que nos proporcionas al registrarte: nombre, correo electrónico, teléfono, dirección y datos de pago.</p>
    <h2>2. Uso de la Información</h2><p>Tu información se utiliza para procesar pedidos, comunicarnos contigo, mejorar nuestros servicios y cumplir con obligaciones legales.</p>
    <h2>3. Compartir Información</h2><p>Compartimos tu información solo con tiendas vendedoras, riders, procesadores de pago y autoridades cuando sea requerido por ley.</p>
    <h2>4. Protección de Datos</h2><p>Implementamos medidas de seguridad técnicas y organizativas. Utilizamos encriptación SSL para todas las comunicaciones.</p>
    <h2>5. Cookies</h2><p>Utilizamos cookies para mejorar la experiencia de navegación y analizar el uso del sitio.</p>
    <h2>6. Derechos del Usuario</h2><p>Tienes derecho a acceder, rectificar, eliminar tus datos y oponerte al tratamiento para fines de marketing (Ley N° 19.628).</p>
    <h2>7. Retención de Datos</h2><p>Conservamos tu información mientras tu cuenta esté activa o según sea necesario para cumplir obligaciones legales.</p>
    <h2>8. Contacto</h2><p>Para consultas sobre privacidad: contacto@petsgo.cl</p>
    <h2>9. Cambios en la Política</h2><p>Podemos actualizar esta política periódicamente. Te notificaremos sobre cambios significativos.</p>
  `;

  // Load legal content when modal opens
  const openTermsModal = useCallback(async () => {
    setShowTermsModal(true);
    setReachedBottom(false);
    if (!termsHtml) {
      setTermsLoading(true);
      try {
        const [tRes, pRes] = await Promise.allSettled([
          getLegalPage('terminos-y-condiciones'),
          getLegalPage('politica-de-privacidad'),
        ]);
        setTermsHtml(tRes.status === 'fulfilled' && tRes.value?.data?.content ? tRes.value.data.content : defaultTerms);
        setPrivacyHtml(pRes.status === 'fulfilled' && pRes.value?.data?.content ? pRes.value.data.content : defaultPrivacy);
      } catch {
        setTermsHtml(defaultTerms);
        setPrivacyHtml(defaultPrivacy);
      } finally { setTermsLoading(false); }
    }
  }, [termsHtml]);

  // Detect scroll reached bottom
  const handleTermsScroll = useCallback(() => {
    const el = scrollRef.current;
    if (!el) return;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 30) {
      setReachedBottom(true);
    }
  }, []);

  // Reset scroll detection when switching tabs
  useEffect(() => {
    if (showTermsModal) {
      setReachedBottom(false);
      if (scrollRef.current) scrollRef.current.scrollTop = 0;
    }
  }, [termsTab]);

  const handleChange = (field) => (e) => {
    let value = e.target.value;
    if (field === 'first_name' || field === 'last_name') value = sanitizeName(value);
    if (field === 'id_number' && form.id_type === 'rut') value = formatRut(value);
    if (field === 'phone') value = formatPhoneDigits(value);
    if (field === 'region') {
      setForm(prev => ({ ...prev, region: value, comuna: '' }));
      return;
    }
    setForm(prev => ({ ...prev, [field]: value }));
  };

  // Password strength
  const passChecks = {
    length: form.password.length >= 8,
    upper: /[A-Z]/.test(form.password),
    lower: /[a-z]/.test(form.password),
    number: /[0-9]/.test(form.password),
    special: /[^A-Za-z0-9]/.test(form.password),
  };
  const passStrength = Object.values(passChecks).filter(Boolean).length;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(''); setSuccess('');

    // Client-side validation
    const errors = [];
    if (!form.first_name.trim()) errors.push('Nombre es obligatorio');
    else if (!isValidName(form.first_name)) errors.push('Nombre solo puede contener letras');
    if (!form.last_name.trim()) errors.push('Apellido es obligatorio');
    else if (!isValidName(form.last_name)) errors.push('Apellido solo puede contener letras');
    const sqlCheck = checkFormForSqlInjection(form);
    if (sqlCheck) { setError(sqlCheck); return; }
    if (!form.email.includes('@')) errors.push('Email inválido');
    if (passStrength < 5) errors.push('La contraseña no cumple todos los requisitos');
    if (form.password !== form.confirmPassword) errors.push('Las contraseñas no coinciden');
    if (form.id_type === 'rut' && !validateRut(form.id_number)) errors.push('RUT inválido');
    if (!form.id_number) errors.push('Documento de identidad es obligatorio');
    if (!form.phone || !isValidPhoneDigits(form.phone)) errors.push('Debes completar los 8 dígitos del teléfono');
    if (!form.region) errors.push('Región es obligatoria');
    if (!form.comuna) errors.push('Comuna es obligatoria');
    if (!acceptTerms) errors.push('Debes aceptar los Términos y Condiciones');
    if (errors.length) { setError(errors.join('. ')); return; }

    setLoading(true);
    try {
      const payload = {
        first_name: form.first_name, last_name: form.last_name,
        email: form.email, password: form.password,
        id_type: form.id_type, id_number: form.id_number,
        phone: buildFullPhone(form.phone), birth_date: form.birth_date,
        region: form.region, comuna: form.comuna,
        accept_terms: true,
      };
      if (form.address_street.trim()) {
        payload.address_street = form.address_street.trim();
        payload.address_detail = form.address_detail.trim();
        payload.address_alias = form.address_alias.trim() || 'Mi Casa';
      }
      const data = await register(payload);
      setShowSuccessModal(true);
    } catch (err) {
      setError(err.response?.data?.message || 'Error al registrar. Intenta nuevamente.');
    } finally {
      setLoading(false);
    }
  };

  const PassCheck = ({ ok, label }) => (
    <span style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', color: ok ? '#16a34a' : '#9ca3af' }}>
      {ok ? <Check size={12} /> : <X size={12} />} {label}
    </span>
  );

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px', background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fff8e1 100%)'
    }}>
      <div style={{ width: '100%', maxWidth: '520px' }}>
        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '24px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            width: '72px', height: '72px', background: '#00A8E8', borderRadius: '18px',
            marginBottom: '12px', padding: '12px', boxShadow: '0 8px 25px rgba(0,168,232,0.3)'
          }}>
            <img src={huellaSvg} alt="PetsGo" style={{ width: '100%', height: '100%', filter: 'brightness(0) invert(1)' }} />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: 900, color: '#2F3A40', marginBottom: '4px' }}>Crear Cuenta</h2>
          <p style={{ color: '#9ca3af', fontSize: '14px' }}>Únete al marketplace de mascotas 🐾</p>
        </div>

        <form onSubmit={handleSubmit} style={{
          background: '#fff', borderRadius: '20px', padding: '24px 16px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0'
        }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', display: 'flex', gap: '8px', alignItems: 'flex-start' }}>
                <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
              </div>
            )}
            {success && (
              <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a', padding: '12px 16px', borderRadius: '12px', fontSize: '13px' }}>
                ✅ {success}
              </div>
            )}

            {/* Nombre y Apellido */}
            <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
              <div style={{ flex: '1 1 180px' }}>
                <label style={labelStyle}>Nombre *</label>
                <input type="text" inputMode="text" autoComplete="given-name" value={form.first_name} onChange={handleChange('first_name')} placeholder="Juan" required style={inputStyle} />
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={labelStyle}>Apellido *</label>
                <input type="text" inputMode="text" autoComplete="family-name" value={form.last_name} onChange={handleChange('last_name')} placeholder="Pérez" required style={inputStyle} />
              </div>
            </div>

            {/* Email */}
            <div>
              <label style={labelStyle}>Correo Electrónico *</label>
              <input type="email" value={form.email} onChange={handleChange('email')} placeholder="tu@email.com" required style={inputStyle} />
            </div>

            {/* Documento de identidad */}
            <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
              <div style={{ flex: '0 1 130px', minWidth: '110px' }}>
                <label style={labelStyle}>Tipo Doc.</label>
                <select value={form.id_type} onChange={handleChange('id_type')} style={{ ...inputStyle, cursor: 'pointer' }}>
                  <option value="rut">RUT</option>
                  <option value="dni">DNI</option>
                  <option value="passport">Pasaporte</option>
                </select>
              </div>
              <div style={{ flex: 1 }}>
                <label style={labelStyle}>N° Documento *</label>
                <input
                  type="text" value={form.id_number} onChange={handleChange('id_number')}
                  placeholder={form.id_type === 'rut' ? '12.345.678-K' : 'Número de documento'}
                  required style={{
                    ...inputStyle,
                    borderColor: form.id_number && form.id_type === 'rut' ? (validateRut(form.id_number) ? '#16a34a' : '#dc2626') : '#e5e7eb',
                  }}
                />
                {form.id_number && form.id_type === 'rut' && !validateRut(form.id_number) && (
                  <p style={{ fontSize: '11px', color: '#dc2626', marginTop: '4px' }}>RUT inválido</p>
                )}
              </div>
            </div>

            {/* Teléfono y Fecha Nacimiento */}
            <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
              <div style={{ flex: '1 1 180px' }}>
                <label style={labelStyle}>Teléfono *</label>
                <div style={{ display: 'flex' }}>
                  <span style={{ padding: '12px 10px', background: '#e5e7eb', borderRadius: '12px 0 0 12px', border: '1.5px solid #d1d5db', borderRight: 'none', fontSize: '14px', fontWeight: 600, color: '#374151', whiteSpace: 'nowrap', lineHeight: '1.2' }}>+569</span>
                  <input type="tel" value={form.phone} onChange={handleChange('phone')} placeholder="XXXXXXXX" maxLength={8} required style={{
                    ...inputStyle,
                    borderRadius: '0 12px 12px 0',
                    borderColor: form.phone ? (isValidPhoneDigits(form.phone) ? '#16a34a' : '#dc2626') : '#e5e7eb',
                  }} />
                </div>
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={labelStyle}>Fecha Nacimiento</label>
                <input type="date" value={form.birth_date} onChange={handleChange('birth_date')} style={inputStyle} max={new Date().toISOString().split('T')[0]} />
              </div>
            </div>

            {/* Región y Comuna */}
            <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
              <div style={{ flex: '1 1 200px' }}>
                <label style={labelStyle}>Región *</label>
                <select value={form.region} onChange={handleChange('region')} required style={{ ...inputStyle, cursor: 'pointer' }}>
                  <option value="">Selecciona región...</option>
                  {REGIONES.map(r => <option key={r} value={r}>{r}</option>)}
                </select>
              </div>
              <div style={{ flex: '1 1 200px' }}>
                <label style={labelStyle}>Comuna *</label>
                <select value={form.comuna} onChange={handleChange('comuna')} required disabled={!form.region} style={{ ...inputStyle, cursor: form.region ? 'pointer' : 'not-allowed', opacity: form.region ? 1 : 0.6 }}>
                  <option value="">{form.region ? 'Selecciona comuna...' : 'Primero selecciona región'}</option>
                  {getComunas(form.region).map(c => <option key={c} value={c}>{c}</option>)}
                </select>
              </div>
            </div>

            {/* Dirección (opcional) */}
            <div style={{ border: '1.5px dashed #e5e7eb', borderRadius: '12px', padding: '12px 14px', background: '#fafbfc' }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', cursor: 'pointer' }} onClick={() => setShowAddressFields(!showAddressFields)}>
                <span style={{ fontSize: '13px', fontWeight: 700, color: '#2F3A40' }}>📍 Agregar dirección de despacho <span style={{ fontWeight: 400, color: '#9ca3af' }}>(opcional)</span></span>
                <ChevronDown size={16} style={{ color: '#9ca3af', transition: 'transform 0.2s', transform: showAddressFields ? 'rotate(180deg)' : 'rotate(0deg)' }} />
              </div>
              {showAddressFields && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', marginTop: '12px' }}>
                  <div>
                    <label style={labelStyle}>Alias</label>
                    <input value={form.address_alias} onChange={handleChange('address_alias')} placeholder="Ej: Mi Casa, Oficina" style={inputStyle} maxLength={50} />
                  </div>
                  <div>
                    <label style={labelStyle}>Calle / Dirección</label>
                    <input value={form.address_street} onChange={handleChange('address_street')} placeholder="Av. Providencia 1234" style={inputStyle} />
                  </div>
                  <div>
                    <label style={labelStyle}>Depto / Piso / Referencia</label>
                    <input value={form.address_detail} onChange={handleChange('address_detail')} placeholder="Depto 302, Torre B" style={inputStyle} maxLength={255} />
                  </div>
                  <p style={{ fontSize: '11px', color: '#9ca3af', margin: 0 }}>Se guardará como dirección predeterminada. Usa la región y comuna seleccionadas arriba.</p>
                </div>
              )}
            </div>

            {/* Contraseña */}
            <div>
              <label style={labelStyle}>Contraseña *</label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showPassword ? 'text' : 'password'} value={form.password}
                  onChange={handleChange('password')} placeholder="••••••••" required
                  style={{ ...inputStyle, paddingRight: '48px' }}
                />
                <button type="button" onClick={() => setShowPassword(!showPassword)}
                  style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer', padding: '4px' }}>
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              {/* Strength bar */}
              {form.password && (
                <div style={{ marginTop: '8px' }}>
                  <div style={{ display: 'flex', gap: '4px', marginBottom: '6px' }}>
                    {[1,2,3,4,5].map(i => (
                      <div key={i} style={{ flex: 1, height: '4px', borderRadius: '2px', background: passStrength >= i ? (passStrength <= 2 ? '#dc2626' : passStrength <= 3 ? '#f59e0b' : '#16a34a') : '#e5e7eb' }} />
                    ))}
                  </div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px 12px' }}>
                    <PassCheck ok={passChecks.length} label="8+ caracteres" />
                    <PassCheck ok={passChecks.upper} label="Mayúscula" />
                    <PassCheck ok={passChecks.lower} label="Minúscula" />
                    <PassCheck ok={passChecks.number} label="Número" />
                    <PassCheck ok={passChecks.special} label="Especial (!@#...)" />
                  </div>
                </div>
              )}
            </div>

            {/* Confirmar contraseña */}
            <div>
              <label style={labelStyle}>Confirmar Contraseña *</label>
              <input type="password" value={form.confirmPassword} onChange={handleChange('confirmPassword')} placeholder="••••••••" required
                style={{ ...inputStyle, borderColor: form.confirmPassword ? (form.password === form.confirmPassword ? '#16a34a' : '#dc2626') : '#e5e7eb' }}
              />
              {form.confirmPassword && form.password !== form.confirmPassword && (
                <p style={{ fontSize: '11px', color: '#dc2626', marginTop: '4px' }}>Las contraseñas no coinciden</p>
              )}
            </div>

            {/* Términos y Condiciones */}
            <div style={{
              display: 'flex', alignItems: 'center', gap: '12px', padding: '14px 18px',
              background: acceptTerms ? '#f0fdf4' : '#fff7ed',
              border: `1.5px solid ${acceptTerms ? '#86efac' : '#fed7aa'}`,
              borderRadius: '12px', transition: 'all 0.2s',
            }}>
              <div style={{
                width: 22, height: 22, minWidth: 22, borderRadius: '6px',
                border: `2px solid ${acceptTerms ? '#00A8E8' : '#d1d5db'}`,
                background: acceptTerms ? '#00A8E8' : '#fff',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                transition: 'all 0.2s',
              }}>
                {acceptTerms && <Check size={14} color="#fff" strokeWidth={3} />}
              </div>
              <span style={{ fontSize: '13px', color: '#2F3A40', lineHeight: 1.5, flex: 1 }}>
                {acceptTerms ? (
                  <>✅ Has aceptado los <strong>Términos y Condiciones</strong> y la <strong>Política de Privacidad</strong></>
                ) : (
                  <>Debes leer y aceptar los <strong>Términos y Condiciones</strong></>
                )}
              </span>
              <button type="button" onClick={openTermsModal} style={{
                background: acceptTerms ? '#e0f2fe' : '#00A8E8', color: acceptTerms ? '#0077B6' : '#fff',
                border: 'none', borderRadius: '8px', padding: '8px 14px',
                fontSize: '12px', fontWeight: 800, cursor: 'pointer', whiteSpace: 'nowrap',
                transition: 'all 0.2s',
              }}>
                {acceptTerms ? 'Ver de nuevo' : 'Leer TyC'}
              </button>
            </div>

            <button type="submit" disabled={loading} style={{
              width: '100%', padding: '14px', background: loading ? '#93c5fd' : '#00A8E8',
              color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
              fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
              transition: 'all 0.3s', marginTop: '4px',
              boxShadow: '0 4px 14px rgba(0,168,232,0.35)'
            }}>
              {loading ? 'Creando cuenta...' : '🐾 Crear Mi Cuenta'}
            </button>
          </div>
        </form>

        {/* Footer links */}
        <div style={{ textAlign: 'center', marginTop: '20px', fontSize: '14px', color: '#6b7280' }}>
          <p>
            ¿Ya tienes cuenta?{' '}
            <Link to="/login" style={{ color: '#00A8E8', fontWeight: 700, textDecoration: 'none' }}>Inicia Sesión</Link>
          </p>
          <p style={{ marginTop: '8px', fontSize: '13px' }}>
            ¿Quieres ser repartidor?{' '}
            <Link to="/registro-rider" style={{ color: '#f59e0b', fontWeight: 700, textDecoration: 'none' }}>Regístrate como Rider 🚴</Link>
          </p>
        </div>

        {/* ===== MODAL TÉRMINOS Y CONDICIONES ===== */}
        {showTermsModal && (
          <div style={{
            position: 'fixed', inset: 0, zIndex: 9998,
            background: 'rgba(0,0,0,0.55)', backdropFilter: 'blur(4px)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            padding: '16px',
          }}>
            <div style={{
              background: '#fff', borderRadius: '20px',
              maxWidth: '560px', width: '100%', maxHeight: '90vh',
              display: 'flex', flexDirection: 'column',
              boxShadow: '0 20px 60px rgba(0,0,0,0.2)',
              overflow: 'hidden',
            }}>
              {/* Header */}
              <div style={{
                padding: '20px 24px 0', borderBottom: 'none',
              }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '16px' }}>
                  <h3 style={{ fontSize: '18px', fontWeight: 900, color: '#2F3A40', margin: 0 }}>
                    📋 Términos y Condiciones
                  </h3>
                  <button onClick={() => setShowTermsModal(false)} style={{
                    background: '#f3f4f6', border: 'none', borderRadius: '8px',
                    width: 32, height: 32, fontSize: '18px', cursor: 'pointer',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#6b7280',
                  }}>✕</button>
                </div>
                {/* Tabs */}
                <div style={{ display: 'flex', gap: '4px', background: '#f3f4f6', borderRadius: '10px', padding: '3px' }}>
                  {[
                    { key: 'terms', label: 'Términos y Condiciones', icon: <FileText size={14} /> },
                    { key: 'privacy', label: 'Política de Privacidad', icon: <Shield size={14} /> },
                  ].map(tab => (
                    <button key={tab.key} onClick={() => setTermsTab(tab.key)} style={{
                      flex: 1, padding: '8px 12px', border: 'none', borderRadius: '8px', cursor: 'pointer',
                      fontSize: '12px', fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                      background: termsTab === tab.key ? '#fff' : 'transparent',
                      color: termsTab === tab.key ? '#00A8E8' : '#6b7280',
                      boxShadow: termsTab === tab.key ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
                      transition: 'all 0.2s',
                    }}>
                      {tab.icon} {tab.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Scrollable content */}
              <div
                ref={scrollRef}
                onScroll={handleTermsScroll}
                style={{
                  flex: 1, overflowY: 'auto', padding: '20px 24px',
                  fontSize: '13px', lineHeight: 1.8, color: '#374151',
                  minHeight: '300px', maxHeight: '55vh',
                }}
              >
                {termsLoading ? (
                  <div style={{ textAlign: 'center', padding: '60px 0', color: '#9ca3af' }}>
                    <div style={{ fontSize: '32px', marginBottom: '12px', animation: 'spin 1s linear infinite' }}>⏳</div>
                    <p>Cargando contenido...</p>
                    <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
                  </div>
                ) : (
                  <>
                    <div
                      dangerouslySetInnerHTML={{ __html: termsTab === 'terms' ? (termsHtml || defaultTerms) : (privacyHtml || defaultPrivacy) }}
                      className="legal-modal-content"
                    />
                    <style>{`
                      .legal-modal-content h2 { font-size: 16px; font-weight: 800; color: #2F3A40; margin: 24px 0 8px; }
                      .legal-modal-content p { margin-bottom: 10px; }
                      .legal-modal-content ul, .legal-modal-content ol { padding-left: 20px; margin-bottom: 10px; }
                      .legal-modal-content li { margin-bottom: 4px; }
                      .legal-modal-content strong { color: #2F3A40; }
                    `}</style>
                  </>
                )}
              </div>

              {/* Scroll indicator + Accept button */}
              <div style={{
                padding: '16px 24px', borderTop: '1px solid #e5e7eb',
                background: '#fafafa',
              }}>
                {!reachedBottom && (
                  <div style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                    fontSize: '12px', color: '#f59e0b', fontWeight: 600, marginBottom: '10px',
                    animation: 'bounce 1.5s infinite',
                  }}>
                    <ChevronDown size={16} /> Desplázate hacia abajo para continuar <ChevronDown size={16} />
                    <style>{`@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(4px); } }`}</style>
                  </div>
                )}
                <button
                  onClick={() => {
                    if (reachedBottom) {
                      setAcceptTerms(true);
                      setShowTermsModal(false);
                    }
                  }}
                  disabled={!reachedBottom}
                  style={{
                    width: '100%', padding: '14px', border: 'none', borderRadius: '12px',
                    fontSize: '15px', fontWeight: 800, cursor: reachedBottom ? 'pointer' : 'not-allowed',
                    background: reachedBottom ? '#00A8E8' : '#d1d5db',
                    color: '#fff', transition: 'all 0.3s',
                    boxShadow: reachedBottom ? '0 4px 14px rgba(0,168,232,0.35)' : 'none',
                  }}
                >
                  {reachedBottom ? '✅ He leído y acepto los Términos y Condiciones' : '↓ Lee hasta el final para aceptar'}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ===== MODAL ÉXITO ===== */}
        {showSuccessModal && (
          <div style={{
            position: 'fixed', inset: 0, zIndex: 9999,
            background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            padding: '16px',
          }}>
            <div style={{
              background: '#fff', borderRadius: '24px', padding: '36px 28px',
              maxWidth: '420px', width: '100%', textAlign: 'center',
              boxShadow: '0 20px 60px rgba(0,0,0,0.15)',
              animation: 'fadeInUp 0.3s ease-out',
            }}>
              <div style={{
                width: '72px', height: '72px', borderRadius: '50%',
                background: 'linear-gradient(135deg, #22c55e, #16a34a)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                margin: '0 auto 20px',
                boxShadow: '0 8px 25px rgba(34,197,94,0.35)',
              }}>
                <CheckCircle size={40} color="#fff" />
              </div>
              <h3 style={{ fontSize: '22px', fontWeight: 900, color: '#2F3A40', margin: '0 0 8px' }}>
                ¡Cuenta Creada!
              </h3>
              <p style={{ fontSize: '14px', color: '#6b7280', margin: '0 0 6px', lineHeight: 1.6 }}>
                Tu cuenta se ha registrado exitosamente.
              </p>
              <p style={{ fontSize: '14px', color: '#6b7280', margin: '0 0 20px' }}>
                Serás redirigido al inicio de sesión en...
              </p>
              <div style={{
                width: '56px', height: '56px', borderRadius: '50%',
                background: '#f0fdf4', border: '3px solid #22c55e',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                margin: '0 auto 20px', fontSize: '24px', fontWeight: 900, color: '#16a34a',
              }}>
                {countdown}
              </div>
              <button
                onClick={() => navigate('/login')}
                style={{
                  width: '100%', padding: '14px', background: '#00A8E8',
                  color: '#fff', border: 'none', borderRadius: '12px',
                  fontSize: '15px', fontWeight: 800, cursor: 'pointer',
                  boxShadow: '0 4px 14px rgba(0,168,232,0.35)',
                }}
              >
                Ir a Iniciar Sesión Ahora
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default RegisterPage;
