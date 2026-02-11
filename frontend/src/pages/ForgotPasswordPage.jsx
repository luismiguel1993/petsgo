import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Mail, AlertCircle } from 'lucide-react';
import { forgotPassword } from '../services/api';

const ForgotPasswordPage = () => {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    if (!email.includes('@')) { setError('Ingresa un correo válido'); return; }
    setLoading(true);
    try {
      await forgotPassword(email);
      setSent(true);
    } catch (err) {
      // Always show success to prevent email enumeration
      setSent(true);
    } finally { setLoading(false); }
  };

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px', background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fef9c3 100%)'
    }}>
      <div style={{ width: '100%', maxWidth: '440px' }}>
        <div style={{ textAlign: 'center', marginBottom: '24px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            width: '72px', height: '72px', background: 'linear-gradient(135deg, #00A8E8, #0077b6)', borderRadius: '18px',
            marginBottom: '12px', padding: '14px', boxShadow: '0 8px 25px rgba(0,168,232,0.3)'
          }}>
            <Mail size={36} color="#fff" />
          </div>
          <h2 style={{ fontSize: '24px', fontWeight: 900, color: '#2F3A40', marginBottom: '4px' }}>¿Olvidaste tu contraseña?</h2>
          <p style={{ color: '#9ca3af', fontSize: '14px' }}>Ingresa tu correo y te enviaremos un enlace para restablecerla</p>
        </div>

        <div style={{
          background: '#fff', borderRadius: '20px', padding: '28px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0'
        }}>
          {sent ? (
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: '48px', marginBottom: '12px' }}>📧</div>
              <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#2F3A40', marginBottom: '8px' }}>¡Correo enviado!</h3>
              <p style={{ fontSize: '14px', color: '#6b7280', lineHeight: 1.5 }}>
                Si existe una cuenta asociada a <strong>{email}</strong>, recibirás un enlace para restablecer tu contraseña.
                Revisa tu bandeja de entrada y la carpeta de spam.
              </p>
              <p style={{ fontSize: '13px', color: '#9ca3af', marginTop: '16px' }}>El enlace expira en 1 hora.</p>
              <Link to="/login" style={{
                display: 'inline-block', marginTop: '20px', padding: '12px 28px',
                background: 'linear-gradient(135deg, #00A8E8, #0077b6)', color: '#fff',
                borderRadius: '12px', fontWeight: 700, textDecoration: 'none', fontSize: '14px'
              }}>Volver al Login</Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              {error && (
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626', padding: '12px 16px', borderRadius: '12px', fontSize: '13px', display: 'flex', gap: '8px' }}>
                  <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
                </div>
              )}
              <div>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' }}>Correo Electrónico</label>
                <input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="tu@email.com" required
                  style={{
                    width: '100%', padding: '14px 16px', background: '#f9fafb', borderRadius: '12px',
                    border: '1.5px solid #e5e7eb', fontSize: '15px', fontWeight: 500, outline: 'none', boxSizing: 'border-box'
                  }} />
              </div>
              <button type="submit" disabled={loading} style={{
                width: '100%', padding: '14px', background: loading ? '#93c5fd' : 'linear-gradient(135deg, #00A8E8, #0077b6)',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
                boxShadow: '0 4px 14px rgba(0,168,232,0.35)'
              }}>
                {loading ? 'Enviando...' : 'Enviar enlace de recuperación'}
              </button>
            </form>
          )}
        </div>

        <div style={{ textAlign: 'center', marginTop: '20px', fontSize: '14px', color: '#6b7280' }}>
          <Link to="/login" style={{ color: '#00A8E8', fontWeight: 700, textDecoration: 'none' }}>← Volver al Login</Link>
        </div>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
