import React, { useState, useEffect } from 'react';
import { Navigate, Link } from 'react-router-dom';
import {
  LifeBuoy, Send, MessageSquare, Clock, CheckCircle2, AlertCircle,
  ChevronDown, ChevronUp, ArrowLeft, PawPrint, XCircle, Loader2,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { createTicket, getTickets, getTicketDetail, addTicketReply } from '../services/api';

const STATUS_MAP = {
  abierto: { label: 'Abierto', color: '#3B82F6', bg: '#EFF6FF', icon: AlertCircle },
  en_proceso: { label: 'En Proceso', color: '#F59E0B', bg: '#FFFBEB', icon: Clock },
  resuelto: { label: 'Resuelto', color: '#22C55E', bg: '#F0FDF4', icon: CheckCircle2 },
  cerrado: { label: 'Cerrado', color: '#6B7280', bg: '#F9FAFB', icon: XCircle },
};

const CATEGORIES = [
  { value: 'general', label: '📋 General' },
  { value: 'productos', label: '📦 Productos' },
  { value: 'pedidos', label: '🛒 Pedidos' },
  { value: 'pagos', label: '💳 Pagos' },
  { value: 'cuenta', label: '👤 Mi Cuenta' },
  { value: 'entregas', label: '🚚 Entregas' },
  { value: 'otro', label: '❓ Otro' },
];

const PRIORITIES = [
  { value: 'baja', label: 'Baja', color: '#22C55E' },
  { value: 'media', label: 'Media', color: '#F59E0B' },
  { value: 'alta', label: 'Alta', color: '#EF4444' },
  { value: 'urgente', label: 'Urgente', color: '#DC2626' },
];

const SupportPage = () => {
  const { isAuthenticated, user } = useAuth();
  const [view, setView] = useState('list'); // 'list' | 'new' | 'detail'
  const [tickets, setTickets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [selectedTicket, setSelectedTicket] = useState(null);
  const [replyText, setReplyText] = useState('');
  const [sendingReply, setSendingReply] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [form, setForm] = useState({
    subject: '',
    description: '',
    category: 'general',
    priority: 'media',
  });

  useEffect(() => {
    if (isAuthenticated) loadTickets();
  }, [isAuthenticated]);

  const loadTickets = async () => {
    setLoading(true);
    try {
      const res = await getTickets();
      setTickets(res.data?.data || res.data || []);
    } catch (err) {
      console.error('Error cargando tickets:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.subject.trim() || !form.description.trim()) return;
    setSubmitting(true);
    try {
      await createTicket(form);
      setSuccessMsg('¡Ticket creado exitosamente! Te llegará un correo de confirmación.');
      setForm({ subject: '', description: '', category: 'general', priority: 'media' });
      setTimeout(() => {
        setSuccessMsg('');
        setView('list');
        loadTickets();
      }, 3000);
    } catch (err) {
      alert('Error al crear el ticket. Intenta de nuevo.');
    } finally {
      setSubmitting(false);
    }
  };

  const openDetail = async (ticketId) => {
    try {
      const res = await getTicketDetail(ticketId);
      setSelectedTicket(res.data?.data || res.data);
      setView('detail');
      setReplyText('');
    } catch (err) {
      alert('Error al cargar el ticket');
    }
  };

  const handleReply = async () => {
    if (!replyText.trim() || !selectedTicket) return;
    setSendingReply(true);
    try {
      await addTicketReply(selectedTicket.id, replyText);
      setReplyText('');
      // Recargar detalle
      const res = await getTicketDetail(selectedTicket.id);
      setSelectedTicket(res.data?.data || res.data);
    } catch (err) {
      alert('Error al enviar respuesta');
    } finally {
      setSendingReply(false);
    }
  };

  if (!isAuthenticated) return <Navigate to="/login" />;

  const st = {
    page: { maxWidth: '960px', margin: '0 auto', padding: '24px 16px', fontFamily: 'Poppins, sans-serif' },
    card: { background: '#fff', borderRadius: '16px', boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0' },
  };

  return (
    <div style={st.page}>
      <style>{`
        .sp-btn { transition: all 0.2s ease; cursor: pointer; font-family: Poppins, sans-serif; }
        .sp-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .sp-input:focus { border-color: #00A8E8; outline: none; box-shadow: 0 0 0 3px rgba(0,168,232,0.1); }
        .sp-ticket-row { transition: all 0.2s; cursor: pointer; }
        .sp-ticket-row:hover { background: #f0f9ff !important; }
        @keyframes spFadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .sp-animate { animation: spFadeIn 0.3s ease-out; }
      `}</style>

      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '14px', marginBottom: '28px' }}>
        {view !== 'list' && (
          <button onClick={() => { setView('list'); setSelectedTicket(null); }} className="sp-btn" style={{
            width: '40px', height: '40px', borderRadius: '12px', border: '1px solid #e5e7eb',
            background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            <ArrowLeft size={18} color="#6b7280" />
          </button>
        )}
        <div style={{
          width: '48px', height: '48px', borderRadius: '14px',
          background: 'linear-gradient(135deg, #00A8E8, #0077B6)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
        }}>
          <LifeBuoy size={24} color="#fff" />
        </div>
        <div>
          <h1 style={{ fontSize: '22px', fontWeight: 800, color: '#2F3A40' }}>Centro de Soporte</h1>
          <p style={{ fontSize: '13px', color: '#9ca3af', fontWeight: 500 }}>
            {view === 'list' ? 'Tus solicitudes de soporte' : view === 'new' ? 'Crear nueva solicitud' : `Ticket ${selectedTicket?.ticket_number || ''}`}
          </p>
        </div>
        {view === 'list' && (
          <button onClick={() => setView('new')} className="sp-btn" style={{
            marginLeft: 'auto', background: 'linear-gradient(135deg, #00A8E8, #0077B6)',
            color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '12px',
            fontWeight: 700, fontSize: '13px', display: 'flex', alignItems: 'center', gap: '8px',
          }}>
            <Send size={16} /> Nuevo Ticket
          </button>
        )}
      </div>

      {/* ═══════ VIEW: LISTA ═══════ */}
      {view === 'list' && (
        <div className="sp-animate">
          {loading ? (
            <div style={st.card}>
              {[...Array(3)].map((_, i) => (
                <div key={i} style={{ padding: '20px', borderBottom: '1px solid #f3f4f6' }}>
                  <div style={{ height: '16px', background: '#f3f4f6', borderRadius: '8px', width: '60%', marginBottom: '8px' }} />
                  <div style={{ height: '12px', background: '#f3f4f6', borderRadius: '6px', width: '40%' }} />
                </div>
              ))}
            </div>
          ) : tickets.length === 0 ? (
            <div style={{ ...st.card, textAlign: 'center', padding: '64px 24px' }}>
              <PawPrint size={48} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
              <p style={{ fontWeight: 700, color: '#9ca3af', fontSize: '16px', marginBottom: '8px' }}>
                No tienes solicitudes de soporte
              </p>
              <p style={{ fontSize: '13px', color: '#d1d5db', marginBottom: '20px' }}>
                ¿Necesitas ayuda? Crea tu primera solicitud
              </p>
              <button onClick={() => setView('new')} className="sp-btn" style={{
                background: '#00A8E8', color: '#fff', border: 'none', padding: '12px 28px',
                borderRadius: '12px', fontWeight: 700, fontSize: '14px',
              }}>
                Crear Solicitud
              </button>
            </div>
          ) : (
            <div style={st.card}>
              {tickets.map((ticket, idx) => {
                const s = STATUS_MAP[ticket.status] || STATUS_MAP.abierto;
                const StatusIcon = s.icon;
                return (
                  <div
                    key={ticket.id}
                    className="sp-ticket-row"
                    onClick={() => openDetail(ticket.id)}
                    style={{
                      padding: '18px 20px', display: 'flex', alignItems: 'center', gap: '14px',
                      borderBottom: idx < tickets.length - 1 ? '1px solid #f3f4f6' : 'none',
                    }}
                  >
                    <div style={{
                      width: '40px', height: '40px', borderRadius: '10px', background: s.bg,
                      display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                    }}>
                      <StatusIcon size={18} color={s.color} />
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                        <span style={{ fontSize: '11px', fontWeight: 700, color: '#9ca3af' }}>
                          {ticket.ticket_number}
                        </span>
                        <span style={{
                          fontSize: '10px', fontWeight: 700, padding: '2px 8px', borderRadius: '6px',
                          background: s.bg, color: s.color,
                        }}>
                          {s.label}
                        </span>
                        {ticket.priority === 'alta' || ticket.priority === 'urgente' ? (
                          <span style={{
                            fontSize: '10px', fontWeight: 700, padding: '2px 8px', borderRadius: '6px',
                            background: '#FEF2F2', color: '#EF4444',
                          }}>
                            ⚡ {ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}
                          </span>
                        ) : null}
                      </div>
                      <p style={{
                        fontWeight: 700, fontSize: '14px', color: '#2F3A40',
                        whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                      }}>
                        {ticket.subject}
                      </p>
                      <p style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 500, marginTop: '2px' }}>
                        {new Date(ticket.created_at).toLocaleDateString('es-CL', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                      </p>
                    </div>
                    <ChevronDown size={18} color="#d1d5db" style={{ transform: 'rotate(-90deg)' }} />
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* ═══════ VIEW: NUEVO TICKET ═══════ */}
      {view === 'new' && (
        <div className="sp-animate" style={st.card}>
          {successMsg ? (
            <div style={{ padding: '48px 24px', textAlign: 'center' }}>
              <div style={{
                width: '64px', height: '64px', borderRadius: '50%', background: '#F0FDF4',
                display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 16px',
              }}>
                <CheckCircle2 size={32} color="#22C55E" />
              </div>
              <p style={{ fontWeight: 800, fontSize: '18px', color: '#2F3A40', marginBottom: '8px' }}>
                ¡Ticket Creado!
              </p>
              <p style={{ fontSize: '14px', color: '#9ca3af' }}>{successMsg}</p>
            </div>
          ) : (
            <form onSubmit={handleSubmit} style={{ padding: '28px' }}>
              <h3 style={{ fontWeight: 800, fontSize: '18px', color: '#2F3A40', marginBottom: '24px' }}>
                Nueva Solicitud de Soporte
              </h3>

              {/* Categoría y Prioridad */}
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '20px' }}>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '6px' }}>
                    Categoría
                  </label>
                  <select
                    value={form.category}
                    onChange={e => setForm({ ...form, category: e.target.value })}
                    className="sp-input"
                    style={{
                      width: '100%', padding: '12px 14px', borderRadius: '10px',
                      border: '2px solid #e5e7eb', fontSize: '14px', fontWeight: 500,
                      fontFamily: 'Poppins, sans-serif', background: '#fff', cursor: 'pointer',
                    }}
                  >
                    {CATEGORIES.map(c => (
                      <option key={c.value} value={c.value}>{c.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '6px' }}>
                    Prioridad
                  </label>
                  <div style={{ display: 'flex', gap: '6px' }}>
                    {PRIORITIES.map(p => (
                      <button
                        key={p.value}
                        type="button"
                        onClick={() => setForm({ ...form, priority: p.value })}
                        style={{
                          flex: 1, padding: '10px 4px', borderRadius: '10px', border: '2px solid',
                          borderColor: form.priority === p.value ? p.color : '#e5e7eb',
                          background: form.priority === p.value ? `${p.color}10` : '#fff',
                          color: form.priority === p.value ? p.color : '#9ca3af',
                          fontWeight: 700, fontSize: '11px', cursor: 'pointer',
                          fontFamily: 'Poppins, sans-serif', transition: 'all 0.2s',
                        }}
                      >
                        {p.label}
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              {/* Asunto */}
              <div style={{ marginBottom: '20px' }}>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '6px' }}>
                  Asunto *
                </label>
                <input
                  type="text"
                  value={form.subject}
                  onChange={e => setForm({ ...form, subject: e.target.value })}
                  placeholder="Ej: Problema con mi pedido #1234"
                  required
                  className="sp-input"
                  style={{
                    width: '100%', padding: '12px 14px', borderRadius: '10px',
                    border: '2px solid #e5e7eb', fontSize: '14px', fontWeight: 500,
                    fontFamily: 'Poppins, sans-serif',
                  }}
                />
              </div>

              {/* Descripción */}
              <div style={{ marginBottom: '28px' }}>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '6px' }}>
                  Descripción *
                </label>
                <textarea
                  value={form.description}
                  onChange={e => setForm({ ...form, description: e.target.value })}
                  placeholder="Describe tu problema o consulta con el mayor detalle posible..."
                  required
                  rows={6}
                  className="sp-input"
                  style={{
                    width: '100%', padding: '14px', borderRadius: '10px',
                    border: '2px solid #e5e7eb', fontSize: '14px', fontWeight: 500,
                    fontFamily: 'Poppins, sans-serif', resize: 'vertical', lineHeight: 1.6,
                  }}
                />
              </div>

              <button
                type="submit"
                disabled={submitting || !form.subject.trim() || !form.description.trim()}
                className="sp-btn"
                style={{
                  width: '100%', padding: '14px', borderRadius: '12px', border: 'none',
                  background: submitting ? '#9ca3af' : 'linear-gradient(135deg, #00A8E8, #0077B6)',
                  color: '#fff', fontWeight: 800, fontSize: '15px',
                  fontFamily: 'Poppins, sans-serif', display: 'flex', alignItems: 'center',
                  justifyContent: 'center', gap: '10px',
                }}
              >
                {submitting ? (
                  <><Loader2 size={18} style={{ animation: 'spin 1s linear infinite' }} /> Enviando...</>
                ) : (
                  <><Send size={18} /> Enviar Solicitud</>
                )}
              </button>
              <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
            </form>
          )}
        </div>
      )}

      {/* ═══════ VIEW: DETALLE TICKET ═══════ */}
      {view === 'detail' && selectedTicket && (
        <div className="sp-animate">
          {/* Info del ticket */}
          <div style={{ ...st.card, padding: '24px', marginBottom: '16px' }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '14px', flexWrap: 'wrap' }}>
              <div style={{ flex: 1, minWidth: '200px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px', flexWrap: 'wrap' }}>
                  <span style={{ fontSize: '12px', fontWeight: 700, color: '#9ca3af' }}>
                    {selectedTicket.ticket_number}
                  </span>
                  {(() => {
                    const s = STATUS_MAP[selectedTicket.status] || STATUS_MAP.abierto;
                    return (
                      <span style={{
                        fontSize: '11px', fontWeight: 700, padding: '3px 10px', borderRadius: '8px',
                        background: s.bg, color: s.color,
                      }}>
                        {s.label}
                      </span>
                    );
                  })()}
                  <span style={{
                    fontSize: '11px', fontWeight: 700, padding: '3px 10px', borderRadius: '8px',
                    background: '#F3F4F6', color: '#6B7280',
                  }}>
                    {CATEGORIES.find(c => c.value === selectedTicket.category)?.label || selectedTicket.category}
                  </span>
                </div>
                <h2 style={{ fontWeight: 800, fontSize: '18px', color: '#2F3A40', marginBottom: '8px' }}>
                  {selectedTicket.subject}
                </h2>
                <p style={{ fontSize: '14px', color: '#6b7280', lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>
                  {selectedTicket.description}
                </p>
                <p style={{ fontSize: '11px', color: '#d1d5db', fontWeight: 500, marginTop: '12px' }}>
                  Creado: {new Date(selectedTicket.created_at).toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                  {selectedTicket.assigned_name && (
                    <> · Asignado a: <strong style={{ color: '#00A8E8' }}>{selectedTicket.assigned_name}</strong></>
                  )}
                </p>
              </div>
            </div>
          </div>

          {/* Respuestas */}
          <div style={{ ...st.card, padding: '4px 0', marginBottom: '16px' }}>
            <div style={{ padding: '16px 24px', borderBottom: '1px solid #f3f4f6' }}>
              <h4 style={{ fontWeight: 700, fontSize: '14px', color: '#2F3A40', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <MessageSquare size={16} color="#00A8E8" />
                Conversación ({(selectedTicket.replies || []).length})
              </h4>
            </div>

            {(selectedTicket.replies || []).length === 0 ? (
              <div style={{ padding: '32px', textAlign: 'center' }}>
                <p style={{ color: '#d1d5db', fontSize: '13px', fontWeight: 600 }}>
                  Aún no hay respuestas. Nuestro equipo revisará tu solicitud pronto.
                </p>
              </div>
            ) : (
              <div style={{ padding: '16px 24px' }}>
                {selectedTicket.replies.map((reply) => {
                  const isAdmin = reply.user_role === 'admin' || reply.user_role === 'soporte';
                  return (
                    <div key={reply.id} style={{
                      marginBottom: '16px', padding: '14px 16px', borderRadius: '12px',
                      background: isAdmin ? '#F0F9FF' : '#F9FAFB',
                      borderLeft: `3px solid ${isAdmin ? '#00A8E8' : '#e5e7eb'}`,
                    }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                        <span style={{ fontSize: '12px', fontWeight: 700, color: isAdmin ? '#00A8E8' : '#6b7280' }}>
                          {isAdmin ? '🛡️ ' : '👤 '}{reply.user_name}
                          <span style={{ fontWeight: 500, color: '#d1d5db', marginLeft: '6px' }}>
                            ({isAdmin ? 'Soporte' : 'Tú'})
                          </span>
                        </span>
                        <span style={{ fontSize: '11px', color: '#d1d5db' }}>
                          {new Date(reply.created_at).toLocaleDateString('es-CL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                        </span>
                      </div>
                      <p style={{ fontSize: '13px', color: '#374151', lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>
                        {reply.message}
                      </p>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Responder (solo si no está cerrado) */}
          {selectedTicket.status !== 'cerrado' && (
            <div style={{ ...st.card, padding: '20px 24px' }}>
              <div style={{ display: 'flex', gap: '12px' }}>
                <textarea
                  value={replyText}
                  onChange={e => setReplyText(e.target.value)}
                  placeholder="Escribe tu respuesta..."
                  rows={3}
                  className="sp-input"
                  style={{
                    flex: 1, padding: '12px 14px', borderRadius: '10px',
                    border: '2px solid #e5e7eb', fontSize: '13px', fontWeight: 500,
                    fontFamily: 'Poppins, sans-serif', resize: 'vertical',
                  }}
                />
                <button
                  onClick={handleReply}
                  disabled={sendingReply || !replyText.trim()}
                  className="sp-btn"
                  style={{
                    padding: '12px 20px', borderRadius: '12px', border: 'none',
                    background: sendingReply || !replyText.trim() ? '#d1d5db' : '#00A8E8',
                    color: '#fff', fontWeight: 700, fontSize: '13px',
                    fontFamily: 'Poppins, sans-serif', alignSelf: 'flex-end',
                    display: 'flex', alignItems: 'center', gap: '6px',
                  }}
                >
                  {sendingReply ? <Loader2 size={16} style={{ animation: 'spin 1s linear infinite' }} /> : <Send size={16} />}
                  Enviar
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default SupportPage;
