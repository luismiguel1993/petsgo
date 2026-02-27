import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { AlertCircle, Mail, Check, ArrowLeft } from 'lucide-react';
import { verifyRiderEmail, resendRiderVerification } from '../services/api';
import huellaSvg from '../assets/Huella-1.svg';

const inputStyle = {
  width: '100%', padding: '12px 16px', background: '#f9fafb',
  borderRadius: '12px', border: '1.5px solid #e5e7eb', fontSize: '14px',
  fontWeight: 500, outline: 'none', transition: 'all 0.2s', boxSizing: 'border-box',
};

const RiderVerifyEmailPage = () => {
  const [searchParams] = useSearchParams();
  const [email, setEmail] = useState(searchParams.get('email') || '');
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const [resending, setResending] = useState(false);
  const [verified, setVerified] = useState(false);
  const [countdown, setCountdown] = useState(3);
  const navigate = useNavigate();

  // Countdown after verification
  useEffect(() => {
    if (!verified) return;
    if (countdown <= 0) { navigate('/login'); return; }
    const timer = setTimeout(() => setCountdown(c => c - 1), 1000);
    return () => clearTimeout(timer);
  }, [verified, countdown, navigate]);

  const handleVerify = async (e) => {
    e.preventDefault();
    setError(''); setSuccess('');
    if (!email.trim()) { setError('Ingresa tu correo electrónico'); return; }
    if (!code.trim()) { setError('Ingresa el código de verificación'); return; }

    setLoading(true);
    try {
      const res = await verifyRiderEmail(email.trim(), code.trim());
      if (res.data?.already_verified) {
        setSuccess('Tu email ya fue verificado previamente. Puedes iniciar sesión.');
      } else {
        setSuccess('¡Email verificado exitosamente! Redirigiendo al login...');
      }
      setVerified(true);
    } catch (err) {
      setError(err.response?.data?.message || 'Código inválido o expirado.');
    } finally { setLoading(false); }
  };

  const handleResend = async () => {
    if (!email.trim()) { setError('Ingresa tu correo electrónico primero'); return; }
    setResending(true); setError(''); setSuccess('');
    try {
      await resendRiderVerification(email.trim());
      setSuccess('Se ha enviado un nuevo código a tu correo.');
    } catch (err) {
      setError(err.response?.data?.message || 'Error al reenviar. Verifica que el correo sea correcto.');
    } finally { setResending(false); }
  };

  return (
    <div style={{
      minHeight: '80vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '40px 16px',
      background: 'linear-gradient(135deg, #fff8e1 0%, #FDFCFB 50%, #f0f9ff 100%)',
      position: 'relative', overflow: 'hidden',
    }}>
      {/* Decorative paws */}
      {[...Array(6)].map((_, i) => (
        <img key={i} src={huellaSvg} alt="" style={{
          position: 'absolute', width: `${22 + i * 6}px`, opacity: 0.04 + i * 0.01,
          top: `${10 + i * 14}%`, left: i % 2 === 0 ? `${5 + i * 10}%` : 'auto',
          right: i % 2 === 1 ? `${5 + i * 8}%` : 'auto',
          transform: `rotate(${-20 + i * 15}deg)`, pointerEvents: 'none',
        }} />
      ))}

      <div style={{
        width: '100%', maxWidth: '440px', position: 'relative', zIndex: 1,
      }}>
        {/* Back link */}
        <Link to="/login" style={{
          display: 'inline-flex', alignItems: 'center', gap: '6px',
          color: '#6b7280', fontSize: '13px', textDecoration: 'none', marginBottom: '16px',
          fontWeight: 600,
        }}>
          <ArrowLeft size={14} /> Volver al login
        </Link>

        <div style={{
          background: '#fff', borderRadius: '20px', padding: '32px 24px',
          boxShadow: '0 4px 24px rgba(0,0,0,0.08)', border: '1px solid #f0f0f0',
        }}>
          {/* Header */}
          <div style={{ textAlign: 'center', marginBottom: '24px' }}>
            <div style={{
              width: '64px', height: '64px', background: '#FEF9C3', borderRadius: '50%',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              margin: '0 auto 12px', fontSize: '32px',
            }}>
              {verified ? '✅' : '📧'}
            </div>
            <h2 style={{ fontWeight: 900, color: '#2F3A40', margin: '0 0 8px', fontSize: '20px' }}>
              {verified ? '¡Email Verificado!' : 'Verificar Correo de Rider'}
            </h2>
            <p style={{ fontSize: '13px', color: '#6b7280', margin: 0, lineHeight: 1.6 }}>
              {verified
                ? `Serás redirigido al login en ${countdown} segundos...`
                : 'Ingresa el código de verificación que enviamos a tu correo electrónico.'}
            </p>
          </div>

          {/* Error / Success */}
          {error && (
            <div style={{
              background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626',
              padding: '12px 16px', borderRadius: '12px', fontSize: '13px',
              marginBottom: '16px', display: 'flex', gap: '8px', alignItems: 'flex-start',
            }}>
              <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} /> {error}
            </div>
          )}
          {success && (
            <div style={{
              background: '#f0fdf4', border: '1px solid #bbf7d0', color: '#16a34a',
              padding: '12px 16px', borderRadius: '12px', fontSize: '13px', marginBottom: '16px',
            }}>
              ✅ {success}
            </div>
          )}

          {!verified && (
            <form onSubmit={handleVerify}>
              {/* Email field */}
              <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' }}>
                  <Mail size={14} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
                  Correo Electrónico *
                </label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="tu@correo.cl"
                  style={inputStyle}
                  autoComplete="email"
                />
              </div>

              {/* Code field */}
              <div style={{ marginBottom: '20px' }}>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 700, color: '#2F3A40', marginBottom: '6px' }}>
                  Código de Verificación *
                </label>
                <input
                  type="text"
                  value={code}
                  onChange={(e) => setCode(e.target.value.toUpperCase())}
                  placeholder="Ej: A1B2C3"
                  maxLength={6}
                  style={{
                    ...inputStyle,
                    textAlign: 'center', fontSize: '24px', fontWeight: 900,
                    letterSpacing: '8px', fontFamily: 'monospace', padding: '16px',
                  }}
                  autoFocus
                />
              </div>

              {/* Verify button */}
              <button type="submit" disabled={loading || !code.trim()} style={{
                width: '100%', padding: '14px',
                background: loading || !code.trim() ? '#fcd34d' : 'linear-gradient(135deg, #f59e0b, #d97706)',
                color: '#fff', border: 'none', borderRadius: '12px', fontSize: '16px',
                fontWeight: 800, cursor: loading ? 'not-allowed' : 'pointer',
                boxShadow: '0 4px 14px rgba(245,158,11,0.35)', marginBottom: '12px',
                opacity: !code.trim() ? 0.6 : 1, transition: 'all 0.2s',
              }}>
                {loading ? '⏳ Verificando...' : '✅ Verificar Email'}
              </button>
            </form>
          )}

          {verified && (
            <div style={{ textAlign: 'center' }}>
              <Link to="/login" style={{
                display: 'inline-block', padding: '14px 32px',
                background: 'linear-gradient(135deg, #00A8E8, #0077B6)',
                color: '#fff', borderRadius: '12px', fontSize: '15px',
                fontWeight: 800, textDecoration: 'none',
                boxShadow: '0 4px 14px rgba(0,168,232,0.35)',
              }}>
                🔐 Ir a Iniciar Sesión
              </Link>
            </div>
          )}

          {/* Resend section */}
          {!verified && (
            <div style={{ textAlign: 'center', borderTop: '1px solid #f3f4f6', paddingTop: '16px', marginTop: '8px' }}>
              <p style={{ fontSize: '13px', color: '#9ca3af', margin: '0 0 8px' }}>¿No recibiste el código?</p>
              <button onClick={handleResend} disabled={resending} style={{
                background: 'none', border: '1px solid #e5e7eb', padding: '8px 20px', borderRadius: '8px',
                fontSize: '13px', fontWeight: 700, color: '#F59E0B', cursor: 'pointer',
                opacity: resending ? 0.5 : 1, transition: 'opacity 0.2s',
              }}>
                {resending ? '⏳ Reenviando...' : '📨 Reenviar Código'}
              </button>
            </div>
          )}

          {/* Info */}
          {!verified && (
            <div style={{
              marginTop: '16px', background: '#f0f9ff', border: '1px solid #bae6fd',
              borderRadius: '12px', padding: '14px 18px', fontSize: '12px', color: '#0369a1',
            }}>
              <strong>💡 ¿No encuentras el código?</strong>
              <ul style={{ margin: '4px 0 0', paddingLeft: '16px', lineHeight: '1.8' }}>
                <li>Revisa tu carpeta de <strong>spam</strong> o <strong>correo no deseado</strong></li>
                <li>El código tiene <strong>6 caracteres</strong> (letras y números)</li>
                <li>Es válido por <strong>48 horas</strong> desde el registro</li>
                <li>Si expiró, haz clic en "Reenviar Código"</li>
              </ul>
            </div>
          )}
        </div>

        {/* Footer links */}
        <div style={{ textAlign: 'center', marginTop: '20px', fontSize: '14px', color: '#6b7280' }}>
          <p>
            ¿Aún no te has registrado? <Link to="/registro-rider" style={{ color: '#F59E0B', fontWeight: 700, textDecoration: 'none' }}>Registrarse como Rider 🚴</Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default RiderVerifyEmailPage;
