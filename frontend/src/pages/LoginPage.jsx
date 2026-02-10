import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { PawPrint, Eye, EyeOff } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import huellaSvg from '../assets/Huella-1.svg';

const LoginPage = () => {
  const [isRegister, setIsRegister] = useState(false);
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const { login, register } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);

    try {
      if (isRegister) {
        await register(username, email, password);
        setSuccess('¡Registro exitoso! Ahora puedes iniciar sesión.');
        setIsRegister(false);
      } else {
        const user = await login(username, password);
        // Redirigir según rol
        if (user.role === 'admin') navigate('/admin');
        else if (user.role === 'vendor') navigate('/vendor');
        else if (user.role === 'rider') navigate('/rider');
        else navigate('/');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Error de conexión. Verifica que WordPress esté activo.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '80vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '40px 16px',
      background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fff8e1 100%)'
    }}>
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
          <h2 style={{ fontSize: '26px', fontWeight: 900, color: '#2F3A40', marginBottom: '6px' }}>
            {isRegister ? 'Crear Cuenta' : 'Iniciar Sesión'}
          </h2>
          <p style={{ color: '#9ca3af', fontSize: '14px', fontWeight: 500 }}>
            {isRegister ? 'Únete al marketplace de mascotas' : 'Accede a PetsGo Marketplace'}
          </p>
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
            {success && (
              <div style={{
                background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a',
                padding: '12px 16px', borderRadius: '12px', fontSize: '13px', fontWeight: 500
              }}>
                {success}
              </div>
            )}

            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>Usuario</label>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="tu_usuario"
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

            {isRegister && (
              <div>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>Correo Electrónico</label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="tu@email.com"
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
            )}

            <div>
              <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '8px' }}>Contraseña</label>
              <div style={{ position: 'relative' }}>
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                  minLength={isRegister ? 8 : 1}
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
              {isRegister && (
                <p style={{ fontSize: '11px', color: '#9ca3af', marginTop: '6px' }}>Mínimo 8 caracteres</p>
              )}
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
              {loading ? 'Procesando...' : isRegister ? 'Crear Cuenta' : 'Ingresar'}
            </button>
          </div>
        </form>

        {/* Toggle Login/Register */}
        <p style={{ textAlign: 'center', marginTop: '24px', fontSize: '14px', color: '#6b7280', fontWeight: 500 }}>
          {isRegister ? '¿Ya tienes cuenta?' : '¿No tienes cuenta?'}{' '}
          <button
            onClick={() => { setIsRegister(!isRegister); setError(''); setSuccess(''); }}
            style={{ background: 'none', border: 'none', color: '#00A8E8', fontWeight: 700, cursor: 'pointer', textDecoration: 'none' }}
            onMouseEnter={e => e.target.style.textDecoration = 'underline'}
            onMouseLeave={e => e.target.style.textDecoration = 'none'}
          >
            {isRegister ? 'Inicia Sesión' : 'Regístrate'}
          </button>
        </p>
      </div>
    </div>
  );
};

export default LoginPage;
