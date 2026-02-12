import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Eye, EyeOff, Check, X, AlertCircle, Truck, Mail, Shield } from 'lucide-react';
import { registerRider, verifyRiderEmail, resendRiderVerification } from '../services/api';
import { REGIONES, getComunas, validateRut, formatRut, formatPhone, isValidPhone } from '../utils/chile';

const inputStyle = {
  width: '100%', padding: '12px 16px', background: '#f9fafb',
  borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
  fontWeight: 500, outline: 'none', transition: 'all 0.2s', boxSizing: 'border-box',
};
const labelStyle = { display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' };

const VEHICLES = [
  { value: 'bicicleta', label: '🚲 Bicicleta', needsDocs: false },
  { value: 'scooter', label: '🛵 Scooter eléctrico', needsDocs: true },
  { value: 'moto', label: '🏍️ Moto', needsDocs: true },
  { value: 'auto', label: '🚗 Auto', needsDocs: true },
  { value: 'a_pie', label: '🚶 A pie', needsDocs: false },
];

const RiderRegisterPage = () => {
  const [step, setStep] = useState(1); // 1 = datos, 2 = verificar email
  const [form, setForm] = useState({
    first_name: '', last_name: '', email: '', password: '', confirmPassword: '',
    id_type: 'rut', id_number: '', phone: '', birth_date: '', vehicle: '',
    region: '', comuna: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const [verifyCode, setVerifyCode] = useState('');
  const [resending, setResending] = useState(false);
  const navigate = useNavigate();

  const handleChange = (field) => (e) => {
    let value = e.target.value;
    if (field === 'id_number' && form.id_type === 'rut') value = formatRut(value);
    if (field === 'phone') value = formatPhone(value);
    if (field === 'region') {
      setForm(prev => ({ ...prev, region: value, comuna: '' }));
      return;
    }
    setForm(prev => ({ ...prev, [field]: value }));
  };

  const passChecks = {
    length: form.password.length >= 8,
    upper: /[A-Z]/.test(form.password),
    lower: /[a-z]/.test(form.password),
    number: /[0-9]/.test(form.password),
    special: /[^A-Za-z0-9]/.test(form.password),
  };
  const passStrength = Object.values(passChecks).filter(Boolean).length;

  const selectedVehicle = VEHICLES.find(v => v.value === form.vehicle);

  // ------- STEP 1: Submit basic data -------
  const handleSubmitStep1 = async (e) => {
    e.preventDefault();
    setError(''); setSuccess('');
    const errors = [];
    if (!form.first_name.trim()) errors.push('Nombre es obligatorio');
    if (!form.last_name.trim()) errors.push('Apellido es obligatorio');
    if (!form.email.includes('@')) errors.push('Email inválido');
    if (passStrength < 5) errors.push('La contraseña no cumple todos los requisitos');
    if (form.password !== form.confirmPassword) errors.push('Las contraseñas no coinciden');
    if (form.id_type === 'rut' && !validateRut(form.id_number)) errors.push('RUT inválido');
    if (!form.id_number) errors.push('Documento de identidad es obligatorio');
    if (!form.phone || !isValidPhone(form.phone)) errors.push('Teléfono chileno inválido (+569XXXXXXXX)');
    if (!form.vehicle) errors.push('Selecciona un vehículo o medio de transporte');
    if (!form.region) errors.push('Región es obligatoria');
    if (!form.comuna) errors.push('Comuna es obligatoria');
    if (errors.length) { setError(errors.join('. ')); return; }

    setLoading(true);
    try {
      await registerRider({
        first_name: form.first_name, last_name: form.last_name,
        email: form.email, password: form.password,
        id_type: form.id_type, id_number: form.id_number,
        phone: form.phone, birth_date: form.birth_date, vehicle: form.vehicle,
        region: form.region, comuna: form.comuna,
      });
      setStep(2);
      setSuccess('');
      setError('');
    } catch (err) {
      setError(err.response?.data?.message || 'Error al registrar.');
    } finally { setLoading(false); }
  };

  // ------- STEP 2: Verify email code -------
  const handleVerifyEmail = async (e) => {
    e.preventDefault();
    setError(''); setSuccess('');
    if (!verifyCode.trim()) { setError('Ingresa el código de verificación'); return; }

    setLoading(true);
    try {
      await verifyRiderEmail(form.email, verifyCode.trim());
      setSuccess('¡Email verificado! Ahora inicia sesión y sube tus documentos desde el Panel de Rider.');
      setTimeout(() => navigate('/login'), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Código inválido o expirado.');
    } finally { setLoading(false); }
  };

  const handleResend = async () => {
    setResending(true);
    setError('');
    try {
      await resendRiderVerification(form.email);
      setSuccess('Se ha enviado un nuevo código a tu correo.');
    } catch (err) {
      setError(err.response?.data?.message || 'Error al reenviar.');
    } finally { setResending(false); }
  };

  const PassCheck = ({ ok, label }) => (
    <span style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', color: ok ? '#16a34a' : '#9ca3af' }}>
      {ok ? <Check size={12} /> : <X size={12} />} {label}
    </span>
  );

  // =========== STEP INDICATOR ===========
  const StepIndicator = () => (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 0, marginBottom: '24px', flexWrap: 'wrap', rowGap: 8 }}>
      {[
        { n: 1, label: 'Datos', icon: '📝' },
        { n: 2, label: 'Email', icon: '📧' },
        { n: 3, label: 'Docs', icon: '📋' },
        { n: 4, label: 'Listo', icon: '✅' },
      ].map((s, i) => (
        <React.Fragment key={s.n}>
          {i > 0 && (
            <div style={{ flex: '0 1 24px', height: '2px', background: step >= s.n ? '#F59E0B' : '#e5e7eb' }} />
          )}
          <div style={{ textAlign: 'center', minWidth: '48px' }}>
            <div style={{
              width: '32px', height: '32px', borderRadius: '50%', margin: '0 auto 4px',
              display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '14px',
              background: step >= s.n ? '#F59E0B' : '#f3f4f6',
              color: step >= s.n ? '#fff' : '#9ca3af',
              fontWeight: 900, boxShadow: step === s.n ? '0 4px 12px rgba(245,158,11,0.3)' : 'none',
              transition: 'all 0.3s',
            }}>
              {step > s.n ? '✓' : s.icon}
            </div>
            <span style={{ fontSize: '10px', fontWeight: 700, color: step >= s.n ? '#F59E0B' : '#9ca3af' }}>{s.label}</span>
          </div>
        </React.Fragment>
      ))}
    </div>
  );

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px', background: 'linear-gradient(135deg, #fef9c3 0%, #FDFCFB 50%, #f0f9ff 100%)'
    }}>
      <div style={{ width: '100%', maxWidth: '550px' }}>
        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '24px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            width: '72px', height: '72px', background: 'linear-gradient(135deg, #f59e0b, #d97706)', borderRadius: '18px',
            marginBottom: '12px', padding: '14px', boxShadow: '0 8px 25px rgba(245,158,11,0.3)'
          }}>
            <Truck size={36} color="#fff" />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: 900, color: '#2F3A40', marginBottom: '4px' }}>
            {step === 1 ? 'Registro Rider 🚴' : 'Verifica tu Email 📧'}
          </h2>
          <p style={{ color: '#9ca3af', fontSize: '14px' }}>
            {step === 1 ? 'Paso 1: Ingresa tus datos básicos' : 'Paso 2: Ingresa el código enviado a tu correo'}
          </p>
        </div>

        <StepIndicator />

        {/* ========== STEP 1: DATOS BASICOS ========== */}
        {step === 1 && (
          <form onSubmit={handleSubmitStep1} style={{
            background: '#fff', borderRadius: '20px', padding: '24px 16px',
            boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0'
          }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              {error && (
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', display: 'flex', gap: '8px' }}>
                  <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
                </div>
              )}

              <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 180px' }}>
                  <label style={labelStyle}>Nombre *</label>
                  <input type="text" value={form.first_name} onChange={handleChange('first_name')} placeholder="Juan" required style={inputStyle} />
                </div>
                <div style={{ flex: '1 1 180px' }}>
                  <label style={labelStyle}>Apellido *</label>
                  <input type="text" value={form.last_name} onChange={handleChange('last_name')} placeholder="Pérez" required style={inputStyle} />
                </div>
              </div>

              <div>
                <label style={labelStyle}>Correo Electrónico *</label>
                <input type="email" value={form.email} onChange={handleChange('email')} placeholder="tu@email.com" required style={inputStyle} />
              </div>

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
                  <input type="text" value={form.id_number} onChange={handleChange('id_number')}
                    placeholder={form.id_type === 'rut' ? '12.345.678-K' : 'Número'} required
                    style={{ ...inputStyle, borderColor: form.id_number && form.id_type === 'rut' ? (validateRut(form.id_number) ? '#16a34a' : '#dc2626') : '#e5e7eb' }}
                  />
                </div>
              </div>

              <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 180px' }}>
                  <label style={labelStyle}>Teléfono *</label>
                  <input type="tel" value={form.phone} onChange={handleChange('phone')} placeholder="+569XXXXXXXX" required
                    style={{ ...inputStyle, borderColor: form.phone ? (isValidPhone(form.phone) ? '#16a34a' : '#dc2626') : '#e5e7eb' }} />
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

              {/* Vehicle selection */}
              <div>
                <label style={labelStyle}>Vehículo / Medio de transporte *</label>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                  {VEHICLES.map(v => (
                    <button key={v.value} type="button" onClick={() => setForm(prev => ({ ...prev, vehicle: v.value }))} style={{
                      padding: '10px 16px', borderRadius: '10px', fontSize: '13px', fontWeight: 700,
                      border: form.vehicle === v.value ? '2px solid #F59E0B' : '2px solid #e5e7eb',
                      background: form.vehicle === v.value ? '#FFF8E1' : '#fff',
                      color: form.vehicle === v.value ? '#F59E0B' : '#6b7280',
                      cursor: 'pointer', transition: 'all 0.2s',
                    }}>
                      {v.label}
                    </button>
                  ))}
                </div>
                {selectedVehicle?.needsDocs && (
                  <div style={{ marginTop: '8px', padding: '8px 14px', background: '#FFF3E0', borderRadius: '8px', fontSize: '12px', color: '#e65100', display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
                    <Shield size={14} />
                    Para vehículos motorizados deberás subir <strong>licencia de conducir</strong>, <strong>padrón del vehículo</strong> y <strong>3 fotos del vehículo</strong>.
                  </div>
                )}
                {selectedVehicle && !selectedVehicle.needsDocs && form.vehicle !== 'a_pie' && (
                  <div style={{ marginTop: '8px', padding: '8px 14px', background: '#E3F2FD', borderRadius: '8px', fontSize: '12px', color: '#1565C0', display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
                    <Shield size={14} />
                    Deberás subir <strong>3 fotos de tu medio de transporte</strong> (frontal, lateral y trasera).
                  </div>
                )}
              </div>

              <div>
                <label style={labelStyle}>Contraseña *</label>
                <div style={{ position: 'relative' }}>
                  <input type={showPassword ? 'text' : 'password'} value={form.password} onChange={handleChange('password')}
                    placeholder="••••••••" required style={{ ...inputStyle, paddingRight: '48px' }} />
                  <button type="button" onClick={() => setShowPassword(!showPassword)}
                    style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer' }}>
                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
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
                      <PassCheck ok={passChecks.special} label="Especial" />
                    </div>
                  </div>
                )}
              </div>

              <div>
                <label style={labelStyle}>Confirmar Contraseña *</label>
                <input type="password" value={form.confirmPassword} onChange={handleChange('confirmPassword')} placeholder="••••••••" required
                  style={{ ...inputStyle, borderColor: form.confirmPassword ? (form.password === form.confirmPassword ? '#16a34a' : '#dc2626') : '#e5e7eb' }} />
              </div>

              {/* Info box about process */}
              <div style={{ background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: '12px', padding: '14px 18px', fontSize: '12px', color: '#0369a1' }}>
                <strong>📋 Proceso de registro:</strong>
                <ol style={{ margin: '6px 0 0', paddingLeft: '18px', lineHeight: '1.8' }}>
                  <li>Registra tus datos básicos <span style={{ color: '#F59E0B', fontWeight: 700 }}>(este paso)</span></li>
                  <li>Verifica tu correo electrónico</li>
                  <li>Sube tus documentos (identidad, fotos vehículo{selectedVehicle?.needsDocs ? ', licencia, padrón' : ''})</li>
                  <li>Espera la aprobación del administrador</li>
                </ol>
              </div>

              <button type="submit" disabled={loading} style={{
                width: '100%', padding: '14px', background: loading ? '#fcd34d' : 'linear-gradient(135deg, #f59e0b, #d97706)',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
                boxShadow: '0 4px 14px rgba(245,158,11,0.35)',
              }}>
                {loading ? '⏳ Registrando...' : '📝 Registrar y Verificar Email →'}
              </button>
            </div>
          </form>
        )}

        {/* ========== STEP 2: VERIFICAR EMAIL ========== */}
        {step === 2 && (
          <div style={{
            background: '#fff', borderRadius: '20px', padding: '24px 16px',
            boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0'
          }}>
            <div style={{ textAlign: 'center', marginBottom: '24px' }}>
              <div style={{
                width: '64px', height: '64px', background: '#FEF9C3', borderRadius: '50%',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                margin: '0 auto 12px', fontSize: '32px',
              }}>
                📧
              </div>
              <h3 style={{ fontWeight: 800, color: '#2F3A40', margin: '0 0 8px', fontSize: '18px' }}>
                Revisa tu correo electrónico
              </h3>
              <p style={{ fontSize: '13px', color: '#6b7280', margin: 0 }}>
                Enviamos un código de verificación a <strong style={{ color: '#F59E0B' }}>{form.email}</strong>
              </p>
            </div>

            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', marginBottom: '16px', display: 'flex', gap: '8px' }}>
                <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
              </div>
            )}
            {success && (
              <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', marginBottom: '16px' }}>
                ✅ {success}
              </div>
            )}

            <form onSubmit={handleVerifyEmail}>
              <div style={{ marginBottom: '20px' }}>
                <label style={labelStyle}>Código de Verificación *</label>
                <input
                  type="text"
                  value={verifyCode}
                  onChange={(e) => setVerifyCode(e.target.value.toUpperCase())}
                  placeholder="Ej: A1B2C3"
                  maxLength={6}
                  style={{
                    ...inputStyle,
                    textAlign: 'center', fontSize: '24px', fontWeight: 900, letterSpacing: '8px', fontFamily: 'monospace',
                    padding: '16px',
                  }}
                  autoFocus
                />
              </div>

              <button type="submit" disabled={loading || !verifyCode.trim()} style={{
                width: '100%', padding: '14px', background: loading || !verifyCode.trim() ? '#fcd34d' : 'linear-gradient(135deg, #f59e0b, #d97706)',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
                boxShadow: '0 4px 14px rgba(245,158,11,0.35)', marginBottom: '12px',
                opacity: !verifyCode.trim() ? 0.6 : 1,
              }}>
                {loading ? '⏳ Verificando...' : '✅ Verificar Email'}
              </button>
            </form>

            <div style={{ textAlign: 'center', borderTop: '1px solid #f3f4f6', paddingTop: '16px', marginTop: '8px' }}>
              <p style={{ fontSize: '13px', color: '#9ca3af', margin: '0 0 8px' }}>¿No recibiste el código?</p>
              <button onClick={handleResend} disabled={resending} style={{
                background: 'none', border: '1px solid #e5e7eb', padding: '8px 20px', borderRadius: '8px',
                fontSize: '13px', fontWeight: 700, color: '#F59E0B', cursor: 'pointer',
                opacity: resending ? 0.5 : 1,
              }}>
                {resending ? '⏳ Reenviando...' : '📨 Reenviar Código'}
              </button>
            </div>

            {/* What's next */}
            <div style={{ marginTop: '20px', background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: '12px', padding: '14px 18px', fontSize: '12px', color: '#0369a1' }}>
              <strong>Después de verificar:</strong>
              <p style={{ margin: '4px 0 0' }}>Inicia sesión y desde tu Panel de Rider podrás subir tus documentos (documento de identidad, fotos de tu vehículo{selectedVehicle?.needsDocs ? ', licencia de conducir y padrón del vehículo' : ''}).</p>
            </div>
          </div>
        )}

        {/* Footer links */}
        <div style={{ textAlign: 'center', marginTop: '20px', fontSize: '14px', color: '#6b7280' }}>
          <p>
            ¿Ya tienes cuenta? <Link to="/login" style={{ color: '#00A8E8', fontWeight: 700, textDecoration: 'none' }}>Inicia Sesión</Link>
          </p>
          <p style={{ marginTop: '8px', fontSize: '13px' }}>
            ¿Quieres comprar? <Link to="/registro" style={{ color: '#00A8E8', fontWeight: 700, textDecoration: 'none' }}>Registro Cliente 🐾</Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default RiderRegisterPage;
