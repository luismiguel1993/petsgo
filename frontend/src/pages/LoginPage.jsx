import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Eye, EyeOff } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import huellaSvg from '../assets/Huella-1.svg';

const LoginPage = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(null); // { name, role }
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const user = await login(username, password);
      // Force password change for accounts created by admin with temp password
      if (user.mustChangePassword) {
        navigate('/cambiar-contrasena');
        return;
      }
      setSuccess({ name: user.displayName || user.firstName || user.username, role: user.role });
      // Wait a moment to show message, then redirect
      setTimeout(() => {
        if (user.role === 'admin') navigate('/admin');
        else if (user.role === 'vendor') navigate('/vendor');
        else if (user.role === 'rider') navigate('/rider');
        else navigate('/');
      }, 1800);
    } catch (err) {
      const code = err.response?.data?.code;
      const data = err.response?.data?.data;
      // Rider con email pendiente de verificación → redirigir
      if (code === 'rider_pending_email') {
        const riderEmail = data?.email || username;
        navigate(`/verificar-rider?email=${encodeURIComponent(riderEmail)}`);
        return;
      }
      setError(err.response?.data?.message || 'Error de conexión. Verifica que WordPress esté activo.');
    } finally {
      setLoading(false);
    }
  };

  const roleLabels = { admin: '🛡️ Administrador', vendor: '🏪 Tienda', rider: '🚴 Rider', customer: '🐾 Cliente' };

  return (
    <div style={{
      minHeight: '80vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '40px 16px',
      background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fff8e1 100%)'
    }}>
      <style>{`
        @keyframes successSlideIn {
          0% { transform: scale(0.8) translateY(20px); opacity: 0; }
          50% { transform: scale(1.05) translateY(-5px); opacity: 1; }
          100% { transform: scale(1) translateY(0); opacity: 1; }
        }
        @keyframes checkPop {
          0% { transform: scale(0); }
          50% { transform: scale(1.3); }
          100% { transform: scale(1); }
        }
        @keyframes confettiDot {
          0% { transform: translateY(0) scale(0); opacity: 1; }
          100% { transform: translateY(-40px) scale(1); opacity: 0; }
        }
      `}</style>

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
            animation: 'successSlideIn 0.5s ease-out forwards',
          }}>
            {/* Confetti dots */}
            <div style={{ position: 'relative', display: 'inline-block', marginBottom: '16px' }}>
              {['#00A8E8', '#FFC400', '#22C55E', '#F97316', '#8B5CF6'].map((c, i) => (
                <span key={i} style={{
                  position: 'absolute', width: '8px', height: '8px', borderRadius: '50%', background: c,
                  top: `${-10 + Math.sin(i * 1.26) * 30}px`, left: `${25 + Math.cos(i * 1.26) * 40}px`,
                  animation: `confettiDot 0.8s ${i * 0.1}s ease-out forwards`, opacity: 0,
                }} />
              ))}
              <div style={{
                width: '72px', height: '72px', borderRadius: '50%',
                background: 'linear-gradient(135deg, #22C55E, #16a34a)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                boxShadow: '0 8px 24px rgba(34,197,94,0.35)',
                animation: 'checkPop 0.4s 0.2s ease-out forwards',
                transform: 'scale(0)',
              }}>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </div>
            </div>
            <h3 style={{ fontSize: '22px', fontWeight: 900, color: '#2F3A40', marginBottom: '8px' }}>
              ¡Bienvenido/a!
            </h3>
            <p style={{ fontSize: '16px', fontWeight: 700, color: '#00A8E8', marginBottom: '12px' }}>
              {success.name}
            </p>
            <p style={{
              display: 'inline-block', padding: '6px 16px', borderRadius: '50px', fontSize: '13px',
              fontWeight: 700, background: '#f0f9ff', color: '#0077b6', marginBottom: '8px',
            }}>
              {roleLabels[success.role] || roleLabels.customer}
            </p>
            <p style={{ fontSize: '13px', color: '#9ca3af', marginTop: '12px' }}>
              Redirigiendo...
            </p>
          </div>
        </div>
      )}

      <div style={{ width: '100%', maxWidth: '420px' }}>
        {/* Logo */}
        <div style={{ textAlign: 'center', marginBottom: '32px' }}>
          <div style={{
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '80px',
            height: '80px',
            background: '#00A8E8',
            borderRadius: '20px',
            marginBottom: '16px',
            padding: '14px',
            boxShadow: '0 8px 25px rgba(0, 168, 232, 0.3)'
          }}>
            <img src={huellaSvg} alt="PetsGo" style={{ width: '100%', height: '100%', filter: 'brightness(0) invert(1)' }} />
          </div>
          <h2 style={{ fontSize: '26px', fontWeight: 900, color: '#2F3A40', marginBottom: '6px' }}>Iniciar Sesión</h2>
          <p style={{ color: '#9ca3af', fontSize: '14px', fontWeight: 500 }}>Accede a PetsGo Marketplace</p>
        </div>

        {/* Formulario */}
        <form onSubmit={handleSubmit} style={{
          background: '#fff',
          borderRadius: '20px',
          padding: '32px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.04)',
          border: '1px solid #f0f0f0'
        }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            {error && (
              <div style={{
                background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626',
                padding: '12px 16px', borderRadius: '12px', fontSize: '13px', fontWeight: 500
              }}>
                {error}
              </div>
            )}

            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>Usuario o Correo</label>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="tu@email.com o usuario"
                required
                style={{
                  width: '100%', padding: '12px 16px', background: '#f9fafb',
                  borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
                  fontWeight: 500, outline: 'none', transition: 'all 0.2s',
                  boxSizing: 'border-box'
                }}
                onFocus={e => { e.target.style.borderColor = '#00A8E8'; e.target.style.boxShadow = '0 0 0 3px rgba(0,168,232,0.12)'; }}
                onBlur={e => { e.target.style.borderColor = '#e5e7eb'; e.target.style.boxShadow = 'none'; }}
              />
            </div>

            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>Contraseña</label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                  minLength={1}
                  style={{
                    width: '100%', padding: '12px 16px', paddingRight: '48px', background: '#f9fafb',
                    borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
                    fontWeight: 500, outline: 'none', transition: 'all 0.2s',
                    boxSizing: 'border-box'
                  }}
                  onFocus={e => { e.target.style.borderColor = '#00A8E8'; e.target.style.boxShadow = '0 0 0 3px rgba(0,168,232,0.12)'; }}
                  onBlur={e => { e.target.style.borderColor = '#e5e7eb'; e.target.style.boxShadow = 'none'; }}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  style={{
                    position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)',
                    background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer', padding: '4px'
                  }}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <div style={{ textAlign: 'right', marginTop: '-8px' }}>
              <Link to="/forgot-password" style={{ fontSize: '13px', color: '#00A8E8', fontWeight: 600, textDecoration: 'none' }}
                onMouseEnter={e => e.target.style.textDecoration = 'underline'}
                onMouseLeave={e => e.target.style.textDecoration = 'none'}>
                ¿Olvidaste tu contraseña?
              </Link>
            </div>

            <button
              type="submit"
              disabled={loading}
              style={{
                width: '100%', padding: '14px', background: loading ? '#93c5fd' : '#00A8E8',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
                transition: 'all 0.3s', marginTop: '4px',
                boxShadow: '0 4px 14px rgba(0, 168, 232, 0.35)'
              }}
              onMouseEnter={e => { if (!loading) { e.target.style.background = '#0090c7'; e.target.style.transform = 'translateY(-1px)'; } }}
              onMouseLeave={e => { e.target.style.background = '#00A8E8'; e.target.style.transform = 'translateY(0)'; }}
            >
              {loading ? 'Procesando...' : 'Ingresar'}
            </button>
          </div>
        </form>

        <div style={{ textAlign: 'center', marginTop: '24px', fontSize: '14px', color: '#6b7280', fontWeight: 500 }}>
          <p>
            ¿No tienes cuenta?{' '}
            <Link to="/registro" style={{ color: '#00A8E8', fontWeight: 700, textDecoration: 'none' }}>🐾 Regístrate</Link>
          </p>
          <p style={{ marginTop: '8px', fontSize: '13px' }}>
            ¿Quieres ser Rider?{' '}
            <Link to="/registro-rider" style={{ color: '#f59e0b', fontWeight: 700, textDecoration: 'none' }}>🚴 Registro Rider</Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;
