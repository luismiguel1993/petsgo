import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Eye, EyeOff, ShieldCheck, CheckCircle2, XCircle } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { changePassword } from '../services/api';
import huellaSvg from '../assets/Huella-1.svg';

const RULES = [
  { id: 'length', label: 'Mínimo 8 caracteres', test: (p) => p.length >= 8 },
  { id: 'upper',  label: 'Al menos una mayúscula', test: (p) => /[A-Z]/.test(p) },
  { id: 'lower',  label: 'Al menos una minúscula', test: (p) => /[a-z]/.test(p) },
  { id: 'number', label: 'Al menos un número', test: (p) => /[0-9]/.test(p) },
  { id: 'special', label: 'Al menos un carácter especial (!@#$%...)', test: (p) => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]/.test(p) },
];

const ForceChangePasswordPage = () => {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const { user, updateUser, logout } = useAuth();
  const navigate = useNavigate();

  const allRulesPass = RULES.every((r) => r.test(newPassword));
  const passwordsMatch = newPassword && confirmPassword && newPassword === confirmPassword;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (!currentPassword) { setError('Ingresa tu contraseña temporal actual.'); return; }
    if (!allRulesPass) { setError('La nueva contraseña no cumple con las políticas de seguridad.'); return; }
    if (!passwordsMatch) { setError('Las contraseñas no coinciden.'); return; }
    if (newPassword === currentPassword) { setError('La nueva contraseña debe ser diferente a la temporal.'); return; }

    setLoading(true);
    try {
      const { data } = await changePassword(currentPassword, newPassword);
      // Update token if returned
      if (data.token) {
        localStorage.setItem('petsgo_token', data.token);
      }
      // Clear mustChangePassword flag locally
      updateUser({ mustChangePassword: false });
      setSuccess(true);
      setTimeout(() => {
        if (user?.role === 'admin') navigate('/admin');
        else if (user?.role === 'vendor') navigate('/vendor');
        else if (user?.role === 'rider') navigate('/rider');
        else navigate('/');
      }, 2000);
    } catch (err) {
      setError(err.response?.data?.message || 'Error al cambiar la contraseña.');
    } finally {
      setLoading(false);
    }
  };

  // If user is not logged in, redirect to login
  if (!user) {
    navigate('/login');
    return null;
  }

  const inputStyle = {
    width: '100%', padding: '12px 16px', paddingRight: '48px', background: '#f9fafb',
    borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
    fontWeight: 500, outline: 'none', transition: 'all 0.2s', boxSizing: 'border-box',
  };

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px',
      background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fff8e1 100%)',
    }}>
      <div style={{ width: '100%', maxWidth: '460px' }}>
        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '28px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            width: '72px', height: '72px', background: '#FFC400', borderRadius: '18px',
            marginBottom: '14px', padding: '14px',
            boxShadow: '0 8px 25px rgba(255, 196, 0, 0.3)',
          }}>
            <ShieldCheck size={36} color="#2F3A40" />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: 900, color: '#2F3A40', marginBottom: '6px' }}>
            Cambio de Contraseña Obligatorio
          </h2>
          <p style={{ color: '#6b7280', fontSize: '14px', fontWeight: 500, maxWidth: '360px', margin: '0 auto' }}>
            Tu cuenta fue creada con una contraseña temporal. Por seguridad, debes establecer una nueva contraseña antes de continuar.
          </p>
        </div>

        {/* Success overlay */}
        {success && (
          <div style={{
            position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.35)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999,
            backdropFilter: 'blur(4px)',
          }}>
            <div style={{
              background: '#fff', borderRadius: '24px', padding: '40px 48px',
              textAlign: 'center', maxWidth: '400px', width: '90%',
              boxShadow: '0 24px 60px rgba(0,0,0,0.18)',
            }}>
              <div style={{
                width: '72px', height: '72px', borderRadius: '50%', margin: '0 auto 16px',
                background: 'linear-gradient(135deg, #22C55E, #16a34a)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                boxShadow: '0 8px 24px rgba(34,197,94,0.35)',
              }}>
                <CheckCircle2 size={36} color="#fff" />
              </div>
              <h3 style={{ fontSize: '20px', fontWeight: 900, color: '#2F3A40', marginBottom: '8px' }}>
                Contraseña Actualizada
              </h3>
              <p style={{ color: '#6b7280', fontSize: '14px' }}>Redirigiendo al panel...</p>
            </div>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} style={{
          background: '#fff', borderRadius: '20px', padding: '32px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.04)',
          border: '1px solid #f0f0f0',
        }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
            {error && (
              <div style={{
                background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626',
                padding: '12px 16px', borderRadius: '12px', fontSize: '13px', fontWeight: 500,
              }}>
                {error}
              </div>
            )}

            {/* Current (temp) password */}
            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>
                Contraseña Temporal Actual
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showCurrent ? 'text' : 'password'}
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  placeholder="Ingresa la contraseña que recibiste por correo"
                  required
                  style={inputStyle}
                  onFocus={(e) => { e.target.style.borderColor = '#00A8E8'; e.target.style.boxShadow = '0 0 0 3px rgba(0,168,232,0.12)'; }}
                  onBlur={(e) => { e.target.style.borderColor = '#e5e7eb'; e.target.style.boxShadow = 'none'; }}
                />
                <button type="button" onClick={() => setShowCurrent(!showCurrent)}
                  style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer', padding: '4px' }}>
                  {showCurrent ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            {/* New password */}
            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>
                Nueva Contraseña
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showNew ? 'text' : 'password'}
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder="Crea una contraseña segura"
                  required
                  style={inputStyle}
                  onFocus={(e) => { e.target.style.borderColor = '#00A8E8'; e.target.style.boxShadow = '0 0 0 3px rgba(0,168,232,0.12)'; }}
                  onBlur={(e) => { e.target.style.borderColor = '#e5e7eb'; e.target.style.boxShadow = 'none'; }}
                />
                <button type="button" onClick={() => setShowNew(!showNew)}
                  style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer', padding: '4px' }}>
                  {showNew ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>

              {/* Password strength rules */}
              {newPassword && (
                <div style={{ marginTop: '10px', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                  {RULES.map((rule) => {
                    const pass = rule.test(newPassword);
                    return (
                      <div key={rule.id} style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 500, color: pass ? '#16a34a' : '#9ca3af' }}>
                        {pass ? <CheckCircle2 size={14} color="#16a34a" /> : <XCircle size={14} color="#d1d5db" />}
                        {rule.label}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Confirm password */}
            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>
                Confirmar Nueva Contraseña
              </label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showConfirm ? 'text' : 'password'}
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="Repite la nueva contraseña"
                  required
                  style={inputStyle}
                  onFocus={(e) => { e.target.style.borderColor = '#00A8E8'; e.target.style.boxShadow = '0 0 0 3px rgba(0,168,232,0.12)'; }}
                  onBlur={(e) => { e.target.style.borderColor = '#e5e7eb'; e.target.style.boxShadow = 'none'; }}
                />
                <button type="button" onClick={() => setShowConfirm(!showConfirm)}
                  style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer', padding: '4px' }}>
                  {showConfirm ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              {confirmPassword && !passwordsMatch && (
                <p style={{ fontSize: '12px', color: '#dc2626', marginTop: '6px', fontWeight: 500 }}>
                  Las contraseñas no coinciden
                </p>
              )}
              {passwordsMatch && (
                <p style={{ fontSize: '12px', color: '#16a34a', marginTop: '6px', fontWeight: 500, display: 'flex', alignItems: 'center', gap: '4px' }}>
                  <CheckCircle2 size={14} /> Contraseñas coinciden
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={loading || !allRulesPass || !passwordsMatch}
              style={{
                width: '100%', padding: '14px',
                background: (loading || !allRulesPass || !passwordsMatch) ? '#93c5fd' : '#00A8E8',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: (loading || !allRulesPass || !passwordsMatch) ? 'not-allowed' : 'pointer',
                transition: 'all 0.3s', marginTop: '4px',
                boxShadow: '0 4px 14px rgba(0, 168, 232, 0.35)',
              }}
            >
              {loading ? 'Actualizando...' : 'Establecer Nueva Contraseña'}
            </button>
          </div>
        </form>

        <p style={{ textAlign: 'center', marginTop: '20px', fontSize: '12px', color: '#9ca3af' }}>
          Si no reconoces esta solicitud, contacta al administrador.
        </p>
      </div>
    </div>
  );
};

export default ForceChangePasswordPage;
