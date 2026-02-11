import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Eye, EyeOff, Check, X, AlertCircle, Truck } from 'lucide-react';
import { registerRider } from '../services/api';

const validateRut = (rut) => {
  const clean = rut.replace(/[^0-9kK]/g, '').toUpperCase();
  if (clean.length < 8 || clean.length > 9) return false;
  const body = clean.slice(0, -1);
  const dv = clean.slice(-1);
  let sum = 0, mul = 2;
  for (let i = body.length - 1; i >= 0; i--) {
    sum += parseInt(body[i]) * mul;
    mul = mul === 7 ? 2 : mul + 1;
  }
  let expected = 11 - (sum % 11);
  if (expected === 11) expected = '0';
  else if (expected === 10) expected = 'K';
  else expected = String(expected);
  return dv === expected;
};

const formatRut = (value) => {
  const clean = value.replace(/[^0-9kK]/g, '');
  if (clean.length <= 1) return clean;
  const dv = clean.slice(-1);
  const body = clean.slice(0, -1);
  return body.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
};

const inputStyle = {
  width: '100%', padding: '12px 16px', background: '#f9fafb',
  borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
  fontWeight: 500, outline: 'none', transition: 'all 0.2s', boxSizing: 'border-box',
};
const labelStyle = { display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' };

const RiderRegisterPage = () => {
  const [form, setForm] = useState({
    first_name: '', last_name: '', email: '', password: '', confirmPassword: '',
    id_type: 'rut', id_number: '', phone: '', birth_date: '', vehicle: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleChange = (field) => (e) => {
    let value = e.target.value;
    if (field === 'id_number' && form.id_type === 'rut') value = formatRut(value);
    if (field === 'phone') {
      value = value.replace(/[^0-9+]/g, '');
      if (!value.startsWith('+56') && value.length > 0) {
        if (value.startsWith('56')) value = '+' + value;
        else if (value.startsWith('9')) value = '+56' + value;
        else if (!value.startsWith('+')) value = '+56' + value;
      }
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

  const handleSubmit = async (e) => {
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
    if (!form.phone || !/^\+569\d{8}$/.test(form.phone)) errors.push('Teléfono chileno inválido (+569XXXXXXXX)');
    if (errors.length) { setError(errors.join('. ')); return; }

    setLoading(true);
    try {
      await registerRider({
        first_name: form.first_name, last_name: form.last_name,
        email: form.email, password: form.password,
        id_type: form.id_type, id_number: form.id_number,
        phone: form.phone, birth_date: form.birth_date, vehicle: form.vehicle,
      });
      setSuccess('¡Registro como Rider exitoso! Ya puedes iniciar sesión.');
      setTimeout(() => navigate('/login'), 2500);
    } catch (err) {
      setError(err.response?.data?.message || 'Error al registrar.');
    } finally { setLoading(false); }
  };

  const PassCheck = ({ ok, label }) => (
    <span style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', color: ok ? '#16a34a' : '#9ca3af' }}>
      {ok ? <Check size={12} /> : <X size={12} />} {label}
    </span>
  );

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px', background: 'linear-gradient(135deg, #fef9c3 0%, #FDFCFB 50%, #f0f9ff 100%)'
    }}>
      <div style={{ width: '100%', maxWidth: '520px' }}>
        <div style={{ textAlign: 'center', marginBottom: '24px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            width: '72px', height: '72px', background: 'linear-gradient(135deg, #f59e0b, #d97706)', borderRadius: '18px',
            marginBottom: '12px', padding: '14px', boxShadow: '0 8px 25px rgba(245,158,11,0.3)'
          }}>
            <Truck size={36} color="#fff" />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: 900, color: '#2F3A40', marginBottom: '4px' }}>Registro Rider 🚴</h2>
          <p style={{ color: '#9ca3af', fontSize: '14px' }}>Únete al equipo de delivery de PetsGo</p>
        </div>

        <form onSubmit={handleSubmit} style={{
          background: '#fff', borderRadius: '20px', padding: '28px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0'
        }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            {error && (
              <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', display: 'flex', gap: '8px' }}>
                <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
              </div>
            )}
            {success && (
              <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a', padding: '12px 16px', borderRadius: '12px', fontSize: '13px' }}>✅ {success}</div>
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
                  style={{ ...inputStyle, borderColor: form.phone ? (/^\+569\d{8}$/.test(form.phone) ? '#16a34a' : '#dc2626') : '#e5e7eb' }} />
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={labelStyle}>Fecha Nacimiento</label>
                <input type="date" value={form.birth_date} onChange={handleChange('birth_date')} style={inputStyle} max={new Date().toISOString().split('T')[0]} />
              </div>
            </div>

            <div>
              <label style={labelStyle}>Vehículo / Medio de transporte</label>
              <select value={form.vehicle} onChange={handleChange('vehicle')} style={{ ...inputStyle, cursor: 'pointer' }}>
                <option value="">Seleccionar...</option>
                <option value="bicicleta">🚲 Bicicleta</option>
                <option value="moto">🏍️ Moto</option>
                <option value="auto">🚗 Auto</option>
                <option value="scooter">🛵 Scooter eléctrico</option>
                <option value="a_pie">🚶 A pie</option>
              </select>
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

            <button type="submit" disabled={loading} style={{
              width: '100%', padding: '14px', background: loading ? '#fcd34d' : 'linear-gradient(135deg, #f59e0b, #d97706)',
              color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
              fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
              boxShadow: '0 4px 14px rgba(245,158,11,0.35)'
            }}>
              {loading ? 'Registrando...' : '🚴 Registrarme como Rider'}
            </button>
          </div>
        </form>

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
