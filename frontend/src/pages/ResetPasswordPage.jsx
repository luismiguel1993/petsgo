import React, { useState } from 'react';
import { useSearchParams, Link, useNavigate } from 'react-router-dom';
import { Eye, EyeOff, Check, X, AlertCircle, ShieldCheck } from 'lucide-react';
import { resetPassword } from '../services/api';

const ResetPasswordPage = () => {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const passChecks = {
    length: password.length >= 8,
    upper: /[A-Z]/.test(password),
    lower: /[a-z]/.test(password),
    number: /[0-9]/.test(password),
    special: /[^A-Za-z0-9]/.test(password),
  };
  const passStrength = Object.values(passChecks).filter(Boolean).length;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (passStrength < 5) { setError('La contraseña no cumple todos los requisitos'); return; }
    if (password !== confirmPassword) { setError('Las contraseñas no coinciden'); return; }
    setLoading(true);
    try {
      await resetPassword(token, password);
      setSuccess(true);
      setTimeout(() => navigate('/login'), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Token inválido o expirado. Solicita un nuevo enlace.');
    } finally { setLoading(false); }
  };

  const PassCheck = ({ ok, label }) => (
    <span style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', color: ok ? '#16a34a' : '#9ca3af' }}>
      {ok ? <Check size={12} /> : <X size={12} />} {label}
    </span>
  );

  const inputStyle = {
    width: '100%', padding: '14px 16px', background: '#f9fafb', borderRadius: '12px',
    border: '1.5px solid #e5e7eb', fontSize: '15px', fontWeight: 500, outline: 'none', boxSizing: 'border-box',
  };

  if (!token) {
    return (
      <div style={{
        minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '40px 16px',
        background: 'linear-gradient(135deg, #fef2f2 0%, #FDFCFB 100%)'
      }}>
        <div style={{ textAlign: 'center', maxWidth: '440px' }}>
          <div style={{ fontSize: '48px', marginBottom: '12px' }}>⚠️</div>
          <h2 style={{ fontSize: '22px', fontWeight: 800, color: '#2F3A40', marginBottom: '8px' }}>Enlace inválido</h2>
          <p style={{ color: '#6b7280', fontSize: '14px', marginBottom: '20px' }}>
            No se encontró un token válido. El enlace puede haber expirado o ya fue utilizado.
          </p>
          <Link to="/forgot-password" style={{
            display: 'inline-block', padding: '12px 28px', background: 'linear-gradient(135deg, #00A8E8, #0077b6)',
            color: '#fff', borderRadius: '12px', fontWeight: 700, textDecoration: 'none', fontSize: '14px'
          }}>Solicitar nuevo enlace</Link>
        </div>
      </div>
    );
  }

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px', background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #f0fdf4 100%)'
    }}>
      <div style={{ width: '100%', maxWidth: '440px' }}>
        <div style={{ textAlign: 'center', marginBottom: '24px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            width: '72px', height: '72px', background: 'linear-gradient(135deg, #00A8E8, #0077b6)', borderRadius: '18px',
            marginBottom: '12px', padding: '14px', boxShadow: '0 8px 25px rgba(0,168,232,0.3)'
          }}>
            <ShieldCheck size={36} color="#fff" />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: 900, color: '#2F3A40', marginBottom: '4px' }}>Nueva Contraseña</h2>
          <p style={{ color: '#9ca3af', fontSize: '14px' }}>Ingresa tu nueva contraseña segura</p>
        </div>

        <div style={{
          background: '#fff', borderRadius: '20px', padding: '28px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0'
        }}>
          {success ? (
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: '48px', marginBottom: '12px' }}>✅</div>
              <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#16a34a', marginBottom: '8px' }}>¡Contraseña actualizada!</h3>
              <p style={{ fontSize: '14px', color: '#6b7280' }}>Redirigiendo al login en 3 segundos...</p>
              <Link to="/login" style={{
                display: 'inline-block', marginTop: '16px', padding: '12px 28px',
                background: 'linear-gradient(135deg, #00A8E8, #0077b6)', color: '#fff',
                borderRadius: '12px', fontWeight: 700, textDecoration: 'none', fontSize: '14px'
              }}>Ir al Login</Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              {error && (
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', display: 'flex', gap: '8px' }}>
                  <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
                </div>
              )}

              <div>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' }}>Nueva Contraseña</label>
                <div style={{ position: 'relative' }}>
                  <input type={showPassword ? 'text' : 'password'} value={password} onChange={e => setPassword(e.target.value)}
                    placeholder="••••••••" required style={{ ...inputStyle, paddingRight: '48px' }} />
                  <button type="button" onClick={() => setShowPassword(!showPassword)}
                    style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer' }}>
                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
                {password && (
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
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' }}>Confirmar Contraseña</label>
                <input type="password" value={confirmPassword} onChange={e => setConfirmPassword(e.target.value)} placeholder="••••••••" required
                  style={{ ...inputStyle, borderColor: confirmPassword ? (password === confirmPassword ? '#16a34a' : '#dc2626') : '#e5e7eb' }} />
              </div>

              <button type="submit" disabled={loading} style={{
                width: '100%', padding: '14px', background: loading ? '#93c5fd' : 'linear-gradient(135deg, #00A8E8, #0077b6)',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
                boxShadow: '0 4px 14px rgba(0,168,232,0.35)'
              }}>
                {loading ? 'Actualizando...' : '🔐 Restablecer Contraseña'}
              </button>
            </form>
          )}
        </div>
      </div>
    </div>
  );
};

export default ResetPasswordPage;
