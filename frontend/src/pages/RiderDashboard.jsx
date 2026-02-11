import React, { useState, useEffect, useRef } from 'react';
import { Navigate } from 'react-router-dom';
import { Truck, Package, MapPin, CheckCircle2, Navigation, Upload, Star, Shield, AlertTriangle } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import {
  getRiderDeliveries, updateDeliveryStatus, getRiderDocuments, uploadRiderDocument,
  getRiderRatings, getRiderStatus,
} from '../services/api';

const STATUS_CONFIG = {
  ready_for_pickup: { label: 'Recoger', color: '#8B5CF6', next: 'in_transit', action: 'Iniciar Entrega' },
  in_transit: { label: 'En camino', color: '#F97316', next: 'delivered', action: 'Marcar Entregado' },
  delivered: { label: 'Entregado', color: '#22C55E', next: null, action: null },
};

const DOC_TYPES = {
  id_card:              { label: 'Documento de Identidad', icon: '🆔', description: 'Foto legible de tu documento (ambas caras)' },
  license:              { label: 'Licencia de Conducir', icon: '🪪', description: 'Foto de licencia vigente' },
  vehicle_registration: { label: 'Padrón del Vehículo', icon: '📄', description: 'Padrón o inscripción vehicular' },
};

const VEHICLE_LABELS = {
  bicicleta: '🚲 Bicicleta',
  scooter: '🛵 Scooter',
  moto: '🏍️ Moto',
  auto: '🚗 Auto',
  a_pie: '🚶 A pie',
};

const DEMO_DELIVERIES = [
  { id: 1051, status: 'ready_for_pickup', store_name: 'PetShop Las Condes', customer_name: 'María López', address: 'Av. Las Condes 5678, Depto 302', total_amount: 52990, delivery_fee: 2990, created_at: '2026-02-10T08:30:00Z' },
  { id: 1052, status: 'in_transit', store_name: 'La Huella Store', customer_name: 'Carlos Muñoz', address: 'Calle Sucre 1234, Providencia', total_amount: 38990, delivery_fee: 2990, created_at: '2026-02-10T09:15:00Z' },
  { id: 1048, status: 'delivered', store_name: 'Mundo Animal Centro', customer_name: 'Ana Torres', address: 'Av. Matta 890, Santiago', total_amount: 91980, delivery_fee: 2990, created_at: '2026-02-09T14:00:00Z' },
];

const RiderDashboard = () => {
  const { isAuthenticated, isRider, isAdmin, user } = useAuth();
  const [tab, setTab] = useState('deliveries');
  const [deliveries, setDeliveries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('active');

  // Status state
  const [riderStatus, setRiderStatus] = useState(user?.rider_status || 'pending_email');
  const [vehicleType, setVehicleType] = useState(user?.vehicle_type || '');
  const [requiredDocs, setRequiredDocs] = useState(['id_card']);
  const [missingDocs, setMissingDocs] = useState([]);

  // Docs state
  const [documents, setDocuments] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [uploadMsg, setUploadMsg] = useState('');
  const fileInputRef = useRef(null);
  const [selectedDocType, setSelectedDocType] = useState('');

  // Ratings state
  const [ratings, setRatings] = useState([]);
  const [avgRating, setAvgRating] = useState(null);

  // Profile data for matching
  const [idType, setIdType] = useState('');
  const [idNumber, setIdNumber] = useState('');

  useEffect(() => {
    loadStatus();
  }, []);

  useEffect(() => {
    if (tab === 'deliveries' && riderStatus === 'approved') loadDeliveries();
    else if (tab === 'documents') loadDocuments();
    else if (tab === 'ratings' && riderStatus === 'approved') loadRatings();
  }, [tab, riderStatus]);

  const loadStatus = async () => {
    try {
      const { data } = await getRiderStatus();
      setRiderStatus(data.rider_status || 'pending_email');
      setVehicleType(data.vehicle_type || '');
      setRequiredDocs(data.required_docs || ['id_card']);
      setMissingDocs(data.missing_docs || []);
      setDocuments(data.documents || []);
      setAvgRating(data.average_rating);
      setIdType(data.id_type || '');
      setIdNumber(data.id_number || '');
      // Auto-switch to documents tab if pending_docs
      if (data.rider_status === 'pending_docs') setTab('documents');
    } catch {
      // keep defaults from user context
    }
  };

  const loadDeliveries = async () => {
    setLoading(true);
    try {
      const { data } = await getRiderDeliveries();
      const d = Array.isArray(data) ? data : [];
      setDeliveries(d.length > 0 ? d : DEMO_DELIVERIES);
    } catch {
      setDeliveries(DEMO_DELIVERIES);
    } finally { setLoading(false); }
  };

  const loadDocuments = async () => {
    setLoading(true);
    try {
      const { data } = await getRiderDocuments();
      setDocuments(data?.documents || []);
      setVehicleType(data?.vehicle_type || vehicleType);
      setRiderStatus(data?.rider_status || riderStatus);
    } catch {
      // keep defaults
    } finally { setLoading(false); }
  };

  const loadRatings = async () => {
    setLoading(true);
    try {
      const { data } = await getRiderRatings();
      setRatings(data?.ratings || []);
      setAvgRating(data?.average || null);
    } catch {
      // keep defaults
    } finally { setLoading(false); }
  };

  if (!isAuthenticated || (!isRider() && !isAdmin())) return <Navigate to="/login" />;

  const formatPrice = (price) => `$${parseInt(price || 0).toLocaleString('es-CL')}`;

  const handleStatusUpdate = async (orderId, newStatus) => {
    try {
      await updateDeliveryStatus(orderId, newStatus);
      loadDeliveries();
    } catch { alert('Error actualizando estado de entrega'); }
  };

  const handleDocUpload = async (docType) => {
    if (!fileInputRef.current?.files?.[0]) return;
    setUploading(true);
    setUploadMsg('');
    try {
      const fd = new FormData();
      fd.append('document', fileInputRef.current.files[0]);
      fd.append('doc_type', docType);
      if (vehicleType) fd.append('vehicle_type', vehicleType);
      await uploadRiderDocument(fd);
      setUploadMsg('✅ Documento subido exitosamente');
      fileInputRef.current.value = '';
      setSelectedDocType('');
      // Reload status to see if advanced to pending_review
      await loadStatus();
      loadDocuments();
    } catch (err) {
      setUploadMsg('❌ ' + (err.response?.data?.message || 'Error al subir documento'));
    } finally { setUploading(false); }
  };

  const activeDeliveries = deliveries.filter((d) => d.status !== 'delivered');
  const completedDeliveries = deliveries.filter((d) => d.status === 'delivered');
  const displayedDeliveries = filter === 'active' ? activeDeliveries : completedDeliveries;

  const needsMotorDocs = ['moto', 'auto', 'scooter'].includes(vehicleType);
  const getDocStatus = (docType) => {
    const doc = documents.find(d => d.doc_type === docType);
    return doc ? doc.status : 'missing';
  };

  const tabStyle = (t) => ({
    padding: '10px 20px', borderRadius: 12, fontWeight: 700, fontSize: 14,
    border: 'none', cursor: 'pointer', transition: 'all 0.2s',
    background: tab === t ? '#F97316' : '#fff',
    color: tab === t ? '#fff' : '#6b7280',
    boxShadow: tab === t ? '0 4px 12px rgba(249,115,22,0.3)' : '0 1px 3px rgba(0,0,0,0.08)',
  });


  // ============= STATUS BANNERS =============
  const StatusBanner = () => {
    const configs = {
      pending_email: {
        bg: '#FEF3C7', border: '#FCD34D', icon: '📧', color: '#92400E',
        title: 'Verifica tu correo electrónico',
        desc: 'Revisa tu bandeja de entrada y usa el código de verificación para continuar.',
      },
      pending_docs: {
        bg: '#FFF7ED', border: '#FDBA74', icon: '📋', color: '#9A3412',
        title: 'Sube tus documentos',
        desc: `Debes subir: ${requiredDocs.map(d => DOC_TYPES[d]?.label || d).join(', ')}`,
      },
      pending_review: {
        bg: '#EFF6FF', border: '#93C5FD', icon: '⏳', color: '#1E40AF',
        title: 'Documentos en revisión',
        desc: 'El administrador está revisando tus documentos. Te notificaremos cuando sean aprobados.',
      },
      approved: {
        bg: '#F0FDF4', border: '#86EFAC', icon: '✅', color: '#166534',
        title: 'Cuenta aprobada',
        desc: 'Puedes recibir y realizar entregas.',
      },
    };
    const cfg = configs[riderStatus] || configs.pending_email;

    return (
      <div style={{
        background: cfg.bg, border: `1px solid ${cfg.border}`, borderRadius: '14px',
        padding: '16px 20px', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '14px',
      }}>
        <span style={{ fontSize: '32px' }}>{cfg.icon}</span>
        <div style={{ flex: 1 }}>
          <strong style={{ color: cfg.color, fontSize: '14px' }}>{cfg.title}</strong>
          <p style={{ fontSize: '12px', color: cfg.color, margin: '2px 0 0', opacity: 0.8 }}>{cfg.desc}</p>
        </div>
        {riderStatus === 'pending_docs' && missingDocs.length > 0 && (
          <span style={{ background: '#F97316', color: '#fff', padding: '4px 10px', borderRadius: '8px', fontSize: '11px', fontWeight: 700 }}>
            {missingDocs.length} pendiente{missingDocs.length > 1 ? 's' : ''}
          </span>
        )}
      </div>
    );
  };

  // ============= STEP PROGRESS =============
  const StepProgress = () => {
    const steps = [
      { key: 'pending_email', label: 'Registro', icon: '📝' },
      { key: 'pending_docs', label: 'Email verificado', icon: '📧' },
      { key: 'pending_review', label: 'Documentos', icon: '📋' },
      { key: 'approved', label: 'Aprobado', icon: '✅' },
    ];
    const statusOrder = ['pending_email', 'pending_docs', 'pending_review', 'approved'];
    const currentIdx = statusOrder.indexOf(riderStatus);

    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 0, marginBottom: '20px', flexWrap: 'wrap' }}>
        {steps.map((s, i) => (
          <React.Fragment key={s.key}>
            {i > 0 && <div style={{ width: '24px', height: '2px', background: i <= currentIdx ? '#F59E0B' : '#e5e7eb', flexShrink: 0 }} />}
            <div style={{ textAlign: 'center', minWidth: '56px' }}>
              <div style={{
                width: '32px', height: '32px', borderRadius: '50%', margin: '0 auto 4px',
                display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '14px',
                background: i <= currentIdx ? '#F59E0B' : '#f3f4f6',
                color: i <= currentIdx ? '#fff' : '#9ca3af',
                fontWeight: 900,
                boxShadow: i === currentIdx ? '0 4px 12px rgba(245,158,11,0.3)' : 'none',
              }}>
                {i < currentIdx ? '✓' : s.icon}
              </div>
              <span style={{ fontSize: '9px', fontWeight: 700, color: i <= currentIdx ? '#F59E0B' : '#9ca3af' }}>{s.label}</span>
            </div>
          </React.Fragment>
        ))}
      </div>
    );
  };

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '32px 16px' }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
        <div style={{ width: 48, height: 48, background: '#F97316', borderRadius: 16, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Truck size={24} color="#fff" />
        </div>
        <div>
          <h2 style={{ fontSize: 24, fontWeight: 900, color: '#2F3A40', margin: 0 }}>Panel de Rider</h2>
          <p style={{ fontSize: 13, color: '#9ca3af', fontWeight: 500, margin: 0 }}>
            {vehicleType && <span style={{ marginRight: 6 }}>{VEHICLE_LABELS[vehicleType] || vehicleType}</span>}
            {idNumber && <span style={{ color: '#d1d5db' }}>· Doc: {idNumber}</span>}
          </p>
        </div>
        {avgRating && (
          <div style={{ marginLeft: 'auto', background: '#FFF8E1', padding: '8px 16px', borderRadius: 12, textAlign: 'center' }}>
            <div style={{ fontSize: 20, fontWeight: 900, color: '#F59E0B' }}>⭐ {avgRating}</div>
            <div style={{ fontSize: 11, color: '#92400e', fontWeight: 600 }}>Rating</div>
          </div>
        )}
      </div>

      <StepProgress />
      <StatusBanner />

      {/* Tabs */}
      <div style={{ display: 'flex', gap: 8, marginBottom: 24, flexWrap: 'wrap' }}>
        {riderStatus === 'approved' && (
          <button onClick={() => setTab('deliveries')} style={tabStyle('deliveries')}>📦 Entregas</button>
        )}
        <button onClick={() => setTab('documents')} style={tabStyle('documents')}>📋 Documentos</button>
        {riderStatus === 'approved' && (
          <button onClick={() => setTab('ratings')} style={tabStyle('ratings')}>⭐ Valoraciones</button>
        )}
      </div>

      {/* ===== TAB: ENTREGAS (only for approved riders) ===== */}
      {tab === 'deliveries' && riderStatus === 'approved' && (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: 12, marginBottom: 24 }}>
            {[
              { label: 'Pendientes', value: activeDeliveries.length, color: '#F97316' },
              { label: 'Completadas', value: completedDeliveries.length, color: '#22C55E' },
              { label: 'Total', value: deliveries.length, color: '#2F3A40' },
            ].map((s, i) => (
              <div key={i} style={{ background: '#fff', borderRadius: 16, padding: 20, boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
                <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 700, textTransform: 'uppercase' }}>{s.label}</span>
                <p style={{ fontSize: 28, fontWeight: 900, color: s.color, margin: '4px 0 0' }}>{s.value}</p>
              </div>
            ))}
          </div>

          <div style={{ display: 'flex', gap: 8, marginBottom: 20 }}>
            <button onClick={() => setFilter('active')} style={{ padding: '10px 20px', borderRadius: 12, fontSize: 13, fontWeight: 700, border: 'none', cursor: 'pointer', background: filter === 'active' ? '#F97316' : '#fff', color: filter === 'active' ? '#fff' : '#6b7280', boxShadow: filter === 'active' ? '0 4px 12px rgba(249,115,22,0.3)' : '0 1px 3px rgba(0,0,0,0.06)' }}>
              🚀 Activas ({activeDeliveries.length})
            </button>
            <button onClick={() => setFilter('completed')} style={{ padding: '10px 20px', borderRadius: 12, fontSize: 13, fontWeight: 700, border: 'none', cursor: 'pointer', background: filter === 'completed' ? '#22C55E' : '#fff', color: filter === 'completed' ? '#fff' : '#6b7280', boxShadow: filter === 'completed' ? '0 4px 12px rgba(34,197,94,0.3)' : '0 1px 3px rgba(0,0,0,0.06)' }}>
              ✅ Completadas ({completedDeliveries.length})
            </button>
          </div>

          {loading ? (
            <div style={{ textAlign: 'center', padding: 60, color: '#9ca3af' }}>Cargando entregas...</div>
          ) : displayedDeliveries.length === 0 ? (
            <div style={{ textAlign: 'center', padding: 60, background: '#fff', borderRadius: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
              <Truck size={48} color="#d1d5db" style={{ marginBottom: 12 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700 }}>{filter === 'active' ? 'No tienes entregas pendientes' : 'No hay entregas completadas'}</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              {displayedDeliveries.map((delivery) => {
                const cfg = STATUS_CONFIG[delivery.status] || { label: delivery.status, color: '#6B7280' };
                return (
                  <div key={delivery.id} style={{ background: '#fff', borderRadius: 16, padding: 20, boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 }}>
                      <div>
                        <span style={{ fontSize: 11, fontWeight: 700, color: '#9ca3af' }}>Pedido #{delivery.id}</span>
                        <p style={{ fontWeight: 700, color: '#2F3A40', margin: '4px 0 0', display: 'flex', alignItems: 'center', gap: 6 }}>
                          <Package size={14} color="#9ca3af" /> {delivery.store_name || 'Tienda PetsGo'}
                        </p>
                      </div>
                      <span style={{ padding: '4px 12px', borderRadius: 50, fontSize: 11, fontWeight: 700, background: cfg.color + '15', color: cfg.color }}>{cfg.label}</span>
                    </div>
                    {delivery.address && (
                      <p style={{ fontSize: 13, color: '#6b7280', display: 'flex', alignItems: 'center', gap: 6, margin: '0 0 8px' }}>
                        <MapPin size={14} color="#9ca3af" /> {delivery.address}
                      </p>
                    )}
                    <div style={{ display: 'flex', gap: 16, fontSize: 13, color: '#6b7280', marginBottom: cfg.next ? 12 : 0 }}>
                      <span>Total: <strong style={{ color: '#2F3A40' }}>{formatPrice(delivery.total_amount)}</strong></span>
                      <span>Delivery: <strong style={{ color: '#00A8E8' }}>{formatPrice(delivery.delivery_fee)}</strong></span>
                    </div>
                    {cfg.next && cfg.action && (
                      <button onClick={() => handleStatusUpdate(delivery.id, cfg.next)} style={{ width: '100%', padding: 12, borderRadius: 12, fontWeight: 700, fontSize: 13, border: 'none', color: '#fff', cursor: 'pointer', background: cfg.color, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                        {cfg.next === 'in_transit' ? <Navigation size={16} /> : <CheckCircle2 size={16} />}
                        {cfg.action}
                      </button>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </>
      )}

      {/* ===== TAB: DOCUMENTOS ===== */}
      {tab === 'documents' && (
        <>
          {/* Block uploads until email verified */}
          {riderStatus === 'pending_email' && (
            <div style={{ background: '#FEF3C7', borderRadius: 16, padding: 32, textAlign: 'center', boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
              <div style={{ fontSize: 48, marginBottom: 12 }}>📧</div>
              <h3 style={{ fontWeight: 800, color: '#92400E', margin: '0 0 8px' }}>Verifica tu email primero</h3>
              <p style={{ fontSize: 14, color: '#A16207', margin: 0 }}>
                Debes verificar tu correo electrónico antes de poder subir documentos.
                Revisa tu bandeja de entrada.
              </p>
            </div>
          )}

          {/* Document upload section (available from pending_docs) */}
          {['pending_docs', 'pending_review', 'approved'].includes(riderStatus) && (
            <>
              {/* Vehicle info */}
              <div style={{ background: '#fff', borderRadius: 16, padding: 20, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', marginBottom: 16 }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <div>
                    <h3 style={{ fontSize: 15, fontWeight: 800, color: '#2F3A40', margin: '0 0 4px' }}>Vehículo registrado</h3>
                    <span style={{ fontSize: 20 }}>{VEHICLE_LABELS[vehicleType] || vehicleType || 'No definido'}</span>
                  </div>
                  <div style={{ textAlign: 'right' }}>
                    <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>Documento</span>
                    <p style={{ fontWeight: 700, color: '#2F3A40', margin: '2px 0 0', fontSize: 14 }}>
                      {idType?.toUpperCase()}: {idNumber || '—'}
                    </p>
                  </div>
                </div>
                {needsMotorDocs && (
                  <div style={{ marginTop: 12, padding: '10px 16px', background: '#FFF3E0', borderRadius: 10, fontSize: 12, color: '#e65100', display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Shield size={14} />
                    Vehículo motorizado: se requiere <strong>licencia de conducir</strong> y <strong>padrón del vehículo</strong>.
                    Los datos del documento deben coincidir con los registrados.
                  </div>
                )}
              </div>

              {/* Document cards */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {requiredDocs.map((key) => {
                  const dt = DOC_TYPES[key];
                  if (!dt) return null;
                  const docStatus = getDocStatus(key);
                  const doc = documents.find(d => d.doc_type === key);
                  const statusLabels = { approved: '✅ Aprobado', rejected: '❌ Rechazado', pending: '⏳ En revisión', missing: '📤 No subido' };
                  const statusBg = { approved: '#e8f5e9', rejected: '#fce4ec', pending: '#fff3e0', missing: '#f5f5f5' };
                  const statusColors = { approved: '#2e7d32', rejected: '#c62828', pending: '#e65100', missing: '#9ca3af' };
                  const canUpload = riderStatus === 'pending_docs' || (riderStatus === 'pending_review' && docStatus === 'rejected');

                  return (
                    <div key={key} style={{ background: '#fff', borderRadius: 16, padding: 20, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', border: `1px solid ${docStatus === 'rejected' ? '#ef9a9a' : missingDocs.includes(key) ? '#FDBA74' : '#f0f0f0'}` }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                          <span style={{ fontSize: 24 }}>{dt.icon}</span>
                          <div>
                            <h4 style={{ fontWeight: 700, color: '#2F3A40', margin: 0, fontSize: 14 }}>{dt.label}</h4>
                            <span style={{ fontSize: 11, color: '#9ca3af' }}>{dt.description}</span>
                          </div>
                        </div>
                        <span style={{ padding: '4px 10px', borderRadius: 8, fontSize: 11, fontWeight: 700, background: statusBg[docStatus], color: statusColors[docStatus] }}>
                          {statusLabels[docStatus]}
                        </span>
                      </div>

                      {doc?.admin_notes && docStatus === 'rejected' && (
                        <div style={{ background: '#fce4ec', borderRadius: 8, padding: '8px 12px', fontSize: 12, color: '#c62828', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
                          <AlertTriangle size={14} /> <strong>Motivo:</strong> {doc.admin_notes}
                        </div>
                      )}

                      {/* Match warning for motorized docs */}
                      {key === 'id_card' && idNumber && (docStatus === 'missing' || docStatus === 'rejected') && (
                        <div style={{ background: '#EFF6FF', borderRadius: 8, padding: '8px 12px', fontSize: 12, color: '#1E40AF', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
                          <Shield size={14} /> El documento debe corresponder a <strong>{idType?.toUpperCase()}: {idNumber}</strong>
                        </div>
                      )}

                      {canUpload && (docStatus === 'missing' || docStatus === 'rejected') && (
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                          {selectedDocType === key ? (
                            <>
                              <input ref={fileInputRef} type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" style={{ flex: 1, fontSize: 13, minWidth: 150 }} />
                              <button onClick={() => handleDocUpload(key)} disabled={uploading} style={{
                                padding: '8px 20px', borderRadius: 10, background: '#F97316', color: '#fff',
                                fontWeight: 700, border: 'none', cursor: 'pointer', fontSize: 13,
                                opacity: uploading ? 0.6 : 1,
                              }}>
                                {uploading ? '⏳ Subiendo...' : '📤 Subir'}
                              </button>
                              <button onClick={() => setSelectedDocType('')} style={{ padding: '8px 12px', borderRadius: 10, background: '#f3f4f6', border: 'none', cursor: 'pointer', fontSize: 13 }}>✕</button>
                            </>
                          ) : (
                            <button onClick={() => setSelectedDocType(key)} style={{
                              padding: '10px 20px', borderRadius: 10, background: '#FFF7ED', color: '#F97316',
                              fontWeight: 700, border: '1px solid #F97316', cursor: 'pointer', fontSize: 13,
                              display: 'flex', alignItems: 'center', gap: 6,
                            }}>
                              <Upload size={14} /> {docStatus === 'rejected' ? 'Subir nuevo documento' : 'Subir documento'}
                            </button>
                          )}
                        </div>
                      )}

                      {doc?.file_url && docStatus !== 'rejected' && (
                        <a href={doc.file_url} target="_blank" rel="noopener noreferrer" style={{ fontSize: 12, color: '#00A8E8', fontWeight: 600, textDecoration: 'none', marginTop: 8, display: 'inline-block' }}>
                          📎 Ver documento subido
                        </a>
                      )}
                    </div>
                  );
                })}
              </div>

              {uploadMsg && (
                <div style={{ marginTop: 12, padding: '10px 16px', borderRadius: 10, background: uploadMsg.startsWith('✅') ? '#e8f5e9' : '#fce4ec', fontSize: 13, fontWeight: 600, color: uploadMsg.startsWith('✅') ? '#2e7d32' : '#c62828' }}>
                  {uploadMsg}
                </div>
              )}

              {riderStatus === 'pending_docs' && missingDocs.length > 0 && (
                <div style={{ marginTop: 16, background: '#FFF7ED', border: '1px solid #FDBA74', borderRadius: 12, padding: '14px 18px', fontSize: 13, color: '#9A3412' }}>
                  <strong>📋 Documentos pendientes:</strong> {missingDocs.map(d => DOC_TYPES[d]?.label || d).join(', ')}.
                  <br />Sube todos los documentos requeridos para enviar tu solicitud a revisión.
                </div>
              )}
            </>
          )}
        </>
      )}

      {/* ===== TAB: VALORACIONES ===== */}
      {tab === 'ratings' && riderStatus === 'approved' && (
        <>
          <div style={{ background: '#fff', borderRadius: 16, padding: 24, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', marginBottom: 20, textAlign: 'center' }}>
            <div style={{ fontSize: 48, fontWeight: 900, color: '#F59E0B' }}>⭐ {avgRating || '—'}</div>
            <p style={{ fontSize: 14, color: '#6b7280', fontWeight: 600, margin: '4px 0 0' }}>
              Rating promedio · {ratings.length} valoraci{ratings.length === 1 ? 'ón' : 'ones'}
            </p>
          </div>

          {loading ? (
            <div style={{ textAlign: 'center', padding: 60, color: '#9ca3af' }}>Cargando valoraciones...</div>
          ) : ratings.length === 0 ? (
            <div style={{ textAlign: 'center', padding: 60, background: '#fff', borderRadius: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
              <Star size={48} color="#d1d5db" style={{ marginBottom: 12 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700 }}>Aún no tienes valoraciones</p>
              <p style={{ color: '#d1d5db', fontSize: 13 }}>Las recibirás después de completar entregas.</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {ratings.map((r) => {
                const stars = Array.from({ length: 5 }, (_, i) => i < r.rating ? '⭐' : '☆').join('');
                return (
                  <div key={r.id} style={{ background: '#fff', borderRadius: 14, padding: 16, boxShadow: '0 2px 8px rgba(0,0,0,0.05)' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ fontSize: 20 }}>{r.rater_type === 'vendor' ? '🏪' : '👤'}</span>
                        <div>
                          <p style={{ fontWeight: 700, color: '#2F3A40', margin: 0, fontSize: 14 }}>{r.rater_name}</p>
                          <span style={{ fontSize: 11, color: '#9ca3af' }}>
                            {r.rater_type === 'vendor' ? 'Tienda' : 'Cliente'} · Pedido #{r.order_id}
                          </span>
                        </div>
                      </div>
                      <span style={{ fontSize: 14 }}>{stars}</span>
                    </div>
                    {r.comment && <p style={{ fontSize: 13, color: '#555', margin: 0, lineHeight: 1.5, fontStyle: 'italic' }}>"{r.comment}"</p>}
                  </div>
                );
              })}
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default RiderDashboard;
