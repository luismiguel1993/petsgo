import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Navigate } from 'react-router-dom';
import {
  Truck, Package, MapPin, CheckCircle2, Navigation, Upload, Star, Shield,
  AlertTriangle, DollarSign, User, CreditCard, TrendingUp, Clock, Calendar,
  ChevronRight, Banknote, Wallet, Eye, EyeOff, Save, RefreshCw,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import {
  getRiderDeliveries, updateDeliveryStatus, getRiderDocuments, uploadRiderDocument,
  getRiderRatings, getRiderStatus, getRiderProfile, updateRiderProfile, getRiderEarnings,
} from '../services/api';
import { REGIONES, getComunas, formatPhoneDigits, isValidPhoneDigits, buildFullPhone, extractPhoneDigits, sanitizeName } from '../utils/chile';

/* ───── constants ───── */
const STATUS_CONFIG = {
  ready_for_pickup: { label: 'Recoger', color: '#8B5CF6', next: 'in_transit', action: 'Iniciar Entrega' },
  in_transit:       { label: 'En camino', color: '#F97316', next: 'delivered', action: 'Marcar Entregado' },
  delivered:        { label: 'Entregado', color: '#22C55E', next: null, action: null },
};

const DOC_TYPES = {
  selfie:               { label: 'Foto de Perfil (Selfie)', icon: '📸', description: 'Selfie clara de tu rostro para reconocimiento facial' },
  id_card:              { label: 'Documento de Identidad', icon: '🆔', description: 'Foto legible de tu documento (ambas caras)' },
  vehicle_photo_1:      { label: 'Foto Vehículo #1', icon: '🚗', description: 'Foto frontal de tu medio de transporte' },
  vehicle_photo_2:      { label: 'Foto Vehículo #2', icon: '🚗', description: 'Foto lateral de tu medio de transporte' },
  vehicle_photo_3:      { label: 'Foto Vehículo #3', icon: '🚗', description: 'Foto trasera de tu medio de transporte' },
  license:              { label: 'Licencia de Conducir', icon: '🪪', description: 'Foto de licencia vigente' },
  vehicle_registration: { label: 'Padrón del Vehículo', icon: '📄', description: 'Padrón o inscripción vehicular' },
};

const VEHICLE_LABELS = {
  bicicleta: '🚲 Bicicleta', scooter: '🛵 Scooter', moto: '🏍️ Moto', auto: '🚗 Auto', a_pie: '🚶 A pie',
};

const BANK_OPTIONS = [
  'Banco de Chile', 'Banco Estado', 'Banco Santander', 'BCI', 'Banco Itaú',
  'Scotiabank', 'Banco Falabella', 'Banco Ripley', 'Banco Security', 'Banco BICE',
  'Banco Consorcio', 'Banco Internacional', 'MACH', 'Tenpo', 'Mercado Pago',
];

const ACCOUNT_TYPES = [
  { value: 'corriente', label: 'Cuenta Corriente' },
  { value: 'vista',     label: 'Cuenta Vista / RUT' },
  { value: 'ahorro',    label: 'Cuenta de Ahorro' },
];

const DEMO_DELIVERIES = [
  { id: 1051, status: 'ready_for_pickup', store_name: 'PetShop Las Condes', customer_name: 'María López', address: 'Av. Las Condes 5678, Depto 302', total_amount: 52990, delivery_fee: 2990, created_at: '2026-02-10T08:30:00Z' },
  { id: 1052, status: 'in_transit', store_name: 'La Huella Store', customer_name: 'Carlos Muñoz', address: 'Calle Sucre 1234, Providencia', total_amount: 38990, delivery_fee: 2990, created_at: '2026-02-10T09:15:00Z' },
  { id: 1048, status: 'delivered', store_name: 'Mundo Animal Centro', customer_name: 'Ana Torres', address: 'Av. Matta 890, Santiago', total_amount: 91980, delivery_fee: 2990, created_at: '2026-02-09T14:00:00Z' },
];

/* ───── helpers ───── */
const fmt = (v) => `$${parseInt(v || 0).toLocaleString('es-CL')}`;
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const fmtDateTime = (d) => d ? new Date(d).toLocaleString('es-CL', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
const maskAccount = (n) => n ? '••••' + n.slice(-4) : '';

/* ───── shared UI atoms ───── */
const Card = ({ children, style }) => (
  <div style={{ background: '#fff', borderRadius: 16, padding: 20, boxShadow: '0 2px 8px rgba(0,0,0,0.05)', ...style }}>{children}</div>
);
const StatBox = ({ label, value, sub, color, icon: Icon }) => (
  <Card>
    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
      {Icon && <div style={{ width: 36, height: 36, borderRadius: 10, background: color + '18', display: 'flex', alignItems: 'center', justifyContent: 'center' }}><Icon size={18} color={color} /></div>}
      <div>
        <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 700, textTransform: 'uppercase' }}>{label}</span>
        <p style={{ fontSize: 22, fontWeight: 900, color: color || '#2F3A40', margin: '2px 0 0' }}>{value}</p>
        {sub && <span style={{ fontSize: 11, color: '#9ca3af' }}>{sub}</span>}
      </div>
    </div>
  </Card>
);

/* ============================================================ */
const RiderDashboard = () => {
  const { isAuthenticated, isRider, isAdmin, user } = useAuth();
  const [tab, setTab] = useState('home');
  const [loading, setLoading] = useState(true);

  /* ── rider status / onboarding ── */
  const [riderStatus, setRiderStatus] = useState(user?.rider_status || 'pending_email');
  const [vehicleType, setVehicleType] = useState(user?.vehicle_type || '');
  const [requiredDocs, setRequiredDocs] = useState(['id_card']);
  const [missingDocs, setMissingDocs] = useState([]);

  /* ── profile ── */
  const [profile, setProfile] = useState(null);
  const [profileForm, setProfileForm] = useState({});
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileMsg, setProfileMsg] = useState('');
  const [showAccount, setShowAccount] = useState(false);

  /* ── deliveries ── */
  const [deliveries, setDeliveries] = useState([]);
  const [filter, setFilter] = useState('active');

  /* ── documents ── */
  const [documents, setDocuments] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [uploadMsg, setUploadMsg] = useState('');
  const fileInputRef = useRef(null);
  const [selectedDocType, setSelectedDocType] = useState('');

  /* ── ratings ── */
  const [ratings, setRatings] = useState([]);
  const [avgRating, setAvgRating] = useState(null);

  /* ── earnings ── */
  const [earnings, setEarnings] = useState(null);

  /* ── identity (from status) ── */
  const [idType, setIdType] = useState('');
  const [idNumber, setIdNumber] = useState('');

  /* ────────────── loaders ────────────── */
  const loadStatus = useCallback(async () => {
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
      if (data.rider_status === 'pending_docs') setTab('documents');
    } catch { /* keep defaults */ }
  }, []);

  const loadProfile = useCallback(async () => {
    try {
      const { data } = await getRiderProfile();
      setProfile(data);
      setProfileForm({
        firstName: data.firstName || '',
        lastName: data.lastName || '',
        phone: extractPhoneDigits(data.phone || ''),
        region: data.region || '',
        comuna: data.comuna || '',
        bankName: data.bankName || '',
        bankAccountType: data.bankAccountType || '',
        bankAccountNumber: data.bankAccountNumber || '',
      });
    } catch { /* keep defaults */ }
  }, []);

  const loadDeliveries = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await getRiderDeliveries();
      const d = Array.isArray(data) ? data : [];
      setDeliveries(d.length > 0 ? d : DEMO_DELIVERIES);
    } catch { setDeliveries(DEMO_DELIVERIES); }
    finally { setLoading(false); }
  }, []);

  const loadDocuments = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await getRiderDocuments();
      setDocuments(data?.documents || []);
      setVehicleType(data?.vehicle_type || vehicleType);
      setRiderStatus(data?.rider_status || riderStatus);
    } catch { /* keep */ }
    finally { setLoading(false); }
  }, [vehicleType, riderStatus]);

  const loadRatings = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await getRiderRatings();
      setRatings(data?.ratings || []);
      setAvgRating(data?.average || null);
    } catch { /* keep */ }
    finally { setLoading(false); }
  }, []);

  const loadEarnings = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await getRiderEarnings();
      setEarnings(data);
    } catch { setEarnings(null); }
    finally { setLoading(false); }
  }, []);

  /* ── initial load ── */
  useEffect(() => {
    loadStatus();
    loadProfile();
  }, [loadStatus, loadProfile]);

  /* ── tab-based loading ── */
  useEffect(() => {
    if (tab === 'home' && riderStatus === 'approved') { loadDeliveries(); loadEarnings(); }
    else if (tab === 'deliveries' && riderStatus === 'approved') loadDeliveries();
    else if (tab === 'documents') loadDocuments();
    else if (tab === 'ratings' && riderStatus === 'approved') loadRatings();
    else if (tab === 'earnings' && riderStatus === 'approved') loadEarnings();
    else if (tab === 'profile') loadProfile();
  }, [tab, riderStatus, loadDeliveries, loadDocuments, loadRatings, loadEarnings, loadProfile]);

  /* ── auth guard ── */
  if (!isAuthenticated || (!isRider() && !isAdmin())) return <Navigate to="/login" />;

  /* ────────────── handlers ────────────── */
  const handleStatusUpdate = async (orderId, newStatus) => {
    try { await updateDeliveryStatus(orderId, newStatus); loadDeliveries(); }
    catch { alert('Error actualizando estado de entrega'); }
  };

  const handleDocUpload = async (docType) => {
    if (!fileInputRef.current?.files?.[0]) return;
    setUploading(true); setUploadMsg('');
    try {
      const fd = new FormData();
      fd.append('document', fileInputRef.current.files[0]);
      fd.append('doc_type', docType);
      if (vehicleType) fd.append('vehicle_type', vehicleType);
      await uploadRiderDocument(fd);
      setUploadMsg('✅ Documento subido exitosamente');
      fileInputRef.current.value = '';
      setSelectedDocType('');
      await loadStatus(); loadDocuments();
    } catch (err) {
      setUploadMsg('❌ ' + (err.response?.data?.message || 'Error al subir documento'));
    } finally { setUploading(false); }
  };

  const handleSaveProfile = async () => {
    setSavingProfile(true); setProfileMsg('');
    try {
      await updateRiderProfile({ ...profileForm, phone: buildFullPhone(profileForm.phone) });
      setProfileMsg('✅ Perfil actualizado correctamente');
      await loadProfile();
    } catch (err) {
      setProfileMsg('❌ ' + (err.response?.data?.message || 'Error al guardar'));
    } finally { setSavingProfile(false); }
  };

  /* ── derived ── */
  const activeDeliveries = deliveries.filter((d) => d.status !== 'delivered');
  const completedDeliveries = deliveries.filter((d) => d.status === 'delivered');
  const displayedDeliveries = filter === 'active' ? activeDeliveries : completedDeliveries;
  const needsMotorDocs = ['moto', 'auto', 'scooter'].includes(vehicleType);
  const getDocStatus = (docType) => { const d = documents.find(x => x.doc_type === docType); return d ? d.status : 'missing'; };
  const isApproved = riderStatus === 'approved';

  /* ────────────── tab styles ────────────── */
  const tabBtn = (key, emoji, label) => (
    <button key={key} onClick={() => setTab(key)} style={{
      padding: '10px 18px', borderRadius: 12, fontWeight: 700, fontSize: 13,
      border: 'none', cursor: 'pointer', transition: 'all 0.2s', whiteSpace: 'nowrap',
      background: tab === key ? '#F97316' : '#fff',
      color: tab === key ? '#fff' : '#6b7280',
      boxShadow: tab === key ? '0 4px 12px rgba(249,115,22,0.3)' : '0 1px 3px rgba(0,0,0,0.08)',
    }}>
      {emoji} {label}
    </button>
  );

  /* ═══════════════ STATUS BANNER ═══════════════ */
  const StatusBanner = () => {
    const cfgs = {
      pending_email: { bg: '#FEF3C7', border: '#FCD34D', icon: '📧', color: '#92400E', title: 'Verifica tu correo electrónico', desc: 'Revisa tu bandeja de entrada y usa el código de verificación.' },
      pending_docs:  { bg: '#FFF7ED', border: '#FDBA74', icon: '📋', color: '#9A3412', title: 'Sube tus documentos', desc: `Pendientes: ${requiredDocs.map(d => DOC_TYPES[d]?.label || d).join(', ')}` },
      pending_review:{ bg: '#EFF6FF', border: '#93C5FD', icon: '⏳', color: '#1E40AF', title: 'Documentos en revisión', desc: 'Te notificaremos cuando sean aprobados.' },
      approved:      { bg: '#F0FDF4', border: '#86EFAC', icon: '✅', color: '#166534', title: 'Cuenta aprobada', desc: 'Puedes recibir y realizar entregas.' },
    };
    const c = cfgs[riderStatus] || cfgs.pending_email;
    return (
      <div style={{ background: c.bg, border: `1px solid ${c.border}`, borderRadius: 14, padding: '14px 18px', marginBottom: 20, display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <span style={{ fontSize: 28 }}>{c.icon}</span>
        <div style={{ flex: 1 }}>
          <strong style={{ color: c.color, fontSize: 14 }}>{c.title}</strong>
          <p style={{ fontSize: 12, color: c.color, margin: '2px 0 0', opacity: 0.8 }}>{c.desc}</p>
        </div>
        {riderStatus === 'pending_docs' && missingDocs.length > 0 && (
          <span style={{ background: '#F97316', color: '#fff', padding: '4px 10px', borderRadius: 8, fontSize: 11, fontWeight: 700 }}>
            {missingDocs.length} pendiente{missingDocs.length > 1 ? 's' : ''}
          </span>
        )}
      </div>
    );
  };

  /* ═══════════════ STEP PROGRESS ═══════════════ */
  const StepProgress = () => {
    const steps = [
      { key: 'pending_email', label: 'Registro', icon: '📝' },
      { key: 'pending_docs', label: 'Email', icon: '📧' },
      { key: 'pending_review', label: 'Docs', icon: '📋' },
      { key: 'approved', label: 'Aprobado', icon: '✅' },
    ];
    const order = ['pending_email', 'pending_docs', 'pending_review', 'approved'];
    const ci = order.indexOf(riderStatus);
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 0, marginBottom: 20, flexWrap: 'wrap' }}>
        {steps.map((s, i) => (
          <React.Fragment key={s.key}>
            {i > 0 && <div style={{ width: 24, height: 2, background: i <= ci ? '#F59E0B' : '#e5e7eb', flexShrink: 0 }} />}
            <div style={{ textAlign: 'center', minWidth: 52 }}>
              <div style={{ width: 30, height: 30, borderRadius: '50%', margin: '0 auto 4px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, background: i <= ci ? '#F59E0B' : '#f3f4f6', color: i <= ci ? '#fff' : '#9ca3af', fontWeight: 900, boxShadow: i === ci ? '0 4px 12px rgba(245,158,11,0.3)' : 'none' }}>
                {i < ci ? '✓' : s.icon}
              </div>
              <span style={{ fontSize: 9, fontWeight: 700, color: i <= ci ? '#F59E0B' : '#9ca3af' }}>{s.label}</span>
            </div>
          </React.Fragment>
        ))}
      </div>
    );
  };

  /* ═══════════════ DELIVERY CARD (reused) ═══════════════ */
  const DeliveryCard = ({ delivery }) => {
    const cfg = STATUS_CONFIG[delivery.status] || { label: delivery.status, color: '#6B7280' };
    return (
      <Card style={{ border: `1px solid ${cfg.color}15` }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10, flexWrap: 'wrap', gap: 8 }}>
          <div>
            <span style={{ fontSize: 11, fontWeight: 700, color: '#9ca3af' }}>Pedido #{delivery.id}</span>
            <p style={{ fontWeight: 700, color: '#2F3A40', margin: '4px 0 0', display: 'flex', alignItems: 'center', gap: 6, fontSize: 14 }}>
              <Package size={14} color="#9ca3af" /> {delivery.store_name || 'Tienda PetsGo'}
            </p>
          </div>
          <span style={{ padding: '4px 12px', borderRadius: 50, fontSize: 11, fontWeight: 700, background: cfg.color + '15', color: cfg.color }}>{cfg.label}</span>
        </div>
        {delivery.address && (
          <p style={{ fontSize: 13, color: '#6b7280', display: 'flex', alignItems: 'center', gap: 6, margin: '0 0 6px' }}>
            <MapPin size={14} color="#9ca3af" /> {delivery.address}
          </p>
        )}
        <div style={{ display: 'flex', gap: 8, fontSize: 13, color: '#6b7280', marginBottom: cfg.next ? 12 : 0, flexWrap: 'wrap' }}>
          <span>Total: <strong style={{ color: '#2F3A40' }}>{fmt(delivery.total_amount)}</strong></span>
          <span>Delivery: <strong style={{ color: '#00A8E8' }}>{fmt(delivery.delivery_fee)}</strong></span>
          <span style={{ marginLeft: 'auto', fontSize: 11, color: '#b0b0b0' }}>{fmtDateTime(delivery.created_at)}</span>
        </div>
        {cfg.next && cfg.action && (
          <button onClick={() => handleStatusUpdate(delivery.id, cfg.next)} style={{ width: '100%', padding: 12, borderRadius: 12, fontWeight: 700, fontSize: 13, border: 'none', color: '#fff', cursor: 'pointer', background: cfg.color, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 6 }}>
            {cfg.next === 'in_transit' ? <Navigation size={16} /> : <CheckCircle2 size={16} />} {cfg.action}
          </button>
        )}
      </Card>
    );
  };

  /* ═══════════════════════════════════════════════
     RENDER
  ═══════════════════════════════════════════════ */
  return (
    <div style={{ maxWidth: 960, margin: '0 auto', padding: '32px 16px' }}>
      {/* ── Header ── */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
        <div style={{ width: 48, height: 48, background: '#F97316', borderRadius: 16, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
          <Truck size={24} color="#fff" />
        </div>
        <div style={{ flex: 1 }}>
          <h2 style={{ fontSize: 22, fontWeight: 900, color: '#2F3A40', margin: 0 }}>Panel de Rider</h2>
          <p style={{ fontSize: 12, color: '#9ca3af', fontWeight: 500, margin: 0 }}>
            {profile?.firstName ? `${profile.firstName} ${profile.lastName}` : user?.display_name}
            {vehicleType && <span style={{ marginLeft: 6 }}>{VEHICLE_LABELS[vehicleType] || vehicleType}</span>}
          </p>
        </div>
        {avgRating && (
          <div style={{ background: '#FFF8E1', padding: '8px 16px', borderRadius: 12, textAlign: 'center' }}>
            <div style={{ fontSize: 18, fontWeight: 900, color: '#F59E0B' }}>⭐ {avgRating}</div>
            <div style={{ fontSize: 10, color: '#92400e', fontWeight: 600 }}>Rating</div>
          </div>
        )}
        {isApproved && profile && (
          <div style={{ background: '#E8F5E9', padding: '8px 16px', borderRadius: 12, textAlign: 'center' }}>
            <div style={{ fontSize: 18, fontWeight: 900, color: '#2e7d32' }}>{fmt(profile.pendingBalance || 0)}</div>
            <div style={{ fontSize: 10, color: '#2e7d32', fontWeight: 600 }}>Saldo</div>
          </div>
        )}
      </div>

      <StepProgress />
      <StatusBanner />

      {/* ── Tabs ── */}
      <div style={{ display: 'flex', gap: 6, marginBottom: 24, flexWrap: 'wrap' }}>
        {isApproved && tabBtn('home', '🏠', 'Inicio')}
        {isApproved && tabBtn('deliveries', '📦', 'Entregas')}
        {isApproved && tabBtn('earnings', '💰', 'Ganancias')}
        {tabBtn('documents', '📋', 'Documentos')}
        {isApproved && tabBtn('ratings', '⭐', 'Valoraciones')}
        {tabBtn('profile', '👤', 'Perfil')}
      </div>

      {/* ══════════════════════════════════════════════
         TAB: INICIO (Dashboard Home)
      ══════════════════════════════════════════════ */}
      {tab === 'home' && isApproved && (
        <>
          {/* Quick stats */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: 12, marginBottom: 24 }}>
            <StatBox label="Esta semana" value={fmt(earnings?.currentWeek?.earned || profile?.weekEarned || 0)} sub={`${earnings?.currentWeek?.deliveries || profile?.weekDeliveries || 0} entregas`} color="#F97316" icon={TrendingUp} />
            <StatBox label="Saldo pendiente" value={fmt(earnings?.pendingBalance || profile?.pendingBalance || 0)} sub={earnings?.nextPayout ? `Pago: ${fmtDate(earnings.nextPayout)}` : 'Próximo pago semanal'} color="#22C55E" icon={Wallet} />
            <StatBox label="Total ganado" value={fmt(profile?.totalEarned || 0)} sub={`${profile?.totalDeliveries || 0} entregas totales`} color="#2F3A40" icon={DollarSign} />
            <StatBox label="Aceptación" value={`${profile?.acceptanceRate ?? earnings?.acceptanceRate ?? 100}%`} sub={`${profile?.totalOffers ?? earnings?.totalOffers ?? 0} ofertas`} color="#8B5CF6" icon={CheckCircle2} />
            <StatBox label="Rating" value={avgRating ? `⭐ ${avgRating}` : '—'} sub={`${profile?.totalRatings || 0} valoraciones`} color="#F59E0B" icon={Star} />
          </div>

          {/* Bank account notice */}
          {!profile?.bankName && (
            <Card style={{ borderLeft: '4px solid #F97316', marginBottom: 20 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <CreditCard size={24} color="#F97316" />
                <div style={{ flex: 1 }}>
                  <strong style={{ color: '#2F3A40', fontSize: 14 }}>Configura tu cuenta bancaria</strong>
                  <p style={{ fontSize: 12, color: '#6b7280', margin: '2px 0 0' }}>Agrega tus datos bancarios para recibir tus pagos semanales.</p>
                </div>
                <button onClick={() => setTab('profile')} style={{ padding: '8px 16px', borderRadius: 10, background: '#F97316', color: '#fff', fontWeight: 700, border: 'none', cursor: 'pointer', fontSize: 12, display: 'flex', alignItems: 'center', gap: 4 }}>
                  Configurar <ChevronRight size={14} />
                </button>
              </div>
            </Card>
          )}

          {/* Active deliveries */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
            <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: 0 }}>🚀 Entregas activas</h3>
            <button onClick={() => setTab('deliveries')} style={{ fontSize: 12, color: '#F97316', fontWeight: 700, background: 'none', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 2 }}>
              Ver todas <ChevronRight size={14} />
            </button>
          </div>
          {activeDeliveries.length === 0 ? (
            <Card style={{ textAlign: 'center', padding: 40, marginBottom: 20 }}>
              <Truck size={40} color="#d1d5db" style={{ marginBottom: 8 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700, margin: 0 }}>No tienes entregas pendientes</p>
            </Card>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 20 }}>
              {activeDeliveries.slice(0, 3).map((d) => <DeliveryCard key={d.id} delivery={d} />)}
            </div>
          )}

          {/* Recent completed */}
          {completedDeliveries.length > 0 && (
            <>
              <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: '0 0 12px' }}>✅ Entregas recientes</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {completedDeliveries.slice(0, 3).map((d) => <DeliveryCard key={d.id} delivery={d} />)}
              </div>
            </>
          )}
        </>
      )}

      {/* ══════════════════════════════════════════════
         TAB: ENTREGAS
      ══════════════════════════════════════════════ */}
      {tab === 'deliveries' && isApproved && (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: 12, marginBottom: 20 }}>
            {[
              { label: 'Pendientes', value: activeDeliveries.length, color: '#F97316' },
              { label: 'Completadas', value: completedDeliveries.length, color: '#22C55E' },
              { label: 'Total', value: deliveries.length, color: '#2F3A40' },
            ].map((s, i) => (
              <Card key={i}>
                <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 700, textTransform: 'uppercase' }}>{s.label}</span>
                <p style={{ fontSize: 28, fontWeight: 900, color: s.color, margin: '4px 0 0' }}>{s.value}</p>
              </Card>
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
            <Card style={{ textAlign: 'center', padding: 60 }}>
              <Truck size={48} color="#d1d5db" style={{ marginBottom: 12 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700 }}>{filter === 'active' ? 'No tienes entregas pendientes' : 'No hay entregas completadas'}</p>
            </Card>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              {displayedDeliveries.map((d) => <DeliveryCard key={d.id} delivery={d} />)}
            </div>
          )}
        </>
      )}

      {/* ══════════════════════════════════════════════
         TAB: GANANCIAS
      ══════════════════════════════════════════════ */}
      {tab === 'earnings' && isApproved && (
        <>
          {/* Summary cards */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 12, marginBottom: 24 }}>
            <StatBox label="Total ganado" value={fmt(earnings?.totalEarned || 0)} color="#2F3A40" icon={DollarSign} />
            <StatBox label="Total pagado" value={fmt(earnings?.totalPaid || 0)} color="#22C55E" icon={Banknote} />
            <StatBox label="Saldo pendiente" value={fmt(earnings?.pendingBalance || 0)} color="#F97316" icon={Wallet} />
            <StatBox label="Tasa aceptación" value={`${earnings?.acceptanceRate ?? 100}%`} sub={`${earnings?.totalOffers || 0} ofertas`} color="#8B5CF6" icon={TrendingUp} />
          </div>

          {/* Current week */}
          {earnings?.currentWeek && (
            <Card style={{ marginBottom: 20, borderLeft: '4px solid #F59E0B' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                <div>
                  <h4 style={{ fontWeight: 800, color: '#2F3A40', margin: '0 0 4px', fontSize: 14 }}>📅 Semana actual</h4>
                  <p style={{ fontSize: 12, color: '#6b7280', margin: 0 }}>
                    {fmtDate(earnings.currentWeek.start)} — {fmtDate(earnings.currentWeek.end)} · {earnings.currentWeek.deliveries} entrega{earnings.currentWeek.deliveries !== 1 ? 's' : ''}
                  </p>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <p style={{ fontWeight: 900, color: '#F59E0B', margin: 0, fontSize: 22 }}>{fmt(earnings.currentWeek.earned)}</p>
                </div>
              </div>
            </Card>
          )}

          {/* Commission info */}
          {earnings?.commission && (
            <Card style={{ marginBottom: 20, background: '#f0f9ff' }}>
              <h4 style={{ fontWeight: 800, color: '#2F3A40', margin: '0 0 12px', fontSize: 14, display: 'flex', alignItems: 'center', gap: 6 }}>
                📊 Estructura de comisiones
              </h4>
              <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', marginBottom: 12 }}>
                <div style={{ flex: '1 1 80px', textAlign: 'center', padding: 12, background: '#fff', borderRadius: 10, border: '1px solid #e5e7eb' }}>
                  <p style={{ fontSize: 24, fontWeight: 900, color: '#22C55E', margin: 0 }}>{earnings.commission.rider_pct}%</p>
                  <span style={{ fontSize: 11, color: '#6b7280', fontWeight: 600 }}>Tú recibes</span>
                </div>
                <div style={{ flex: '1 1 80px', textAlign: 'center', padding: 12, background: '#fff', borderRadius: 10, border: '1px solid #e5e7eb' }}>
                  <p style={{ fontSize: 24, fontWeight: 900, color: '#00A8E8', margin: 0 }}>{earnings.commission.petsgo_pct}%</p>
                  <span style={{ fontSize: 11, color: '#6b7280', fontWeight: 600 }}>PetsGo</span>
                </div>
                <div style={{ flex: '1 1 80px', textAlign: 'center', padding: 12, background: '#fff', borderRadius: 10, border: '1px solid #e5e7eb' }}>
                  <p style={{ fontSize: 24, fontWeight: 900, color: '#F97316', margin: 0 }}>{earnings.commission.store_pct}%</p>
                  <span style={{ fontSize: 11, color: '#6b7280', fontWeight: 600 }}>Tienda</span>
                </div>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                <p style={{ fontSize: 12, color: '#6b7280', margin: 0 }}>
                  💸 Pagos cada semana · Ciclo <strong>lunes a domingo</strong>
                </p>
                {earnings?.nextPayout && (
                  <span style={{ fontSize: 12, fontWeight: 700, color: '#22C55E', background: '#e8f5e9', padding: '4px 12px', borderRadius: 8 }}>
                    Próximo pago: {fmtDate(earnings.nextPayout)}
                  </span>
                )}
              </div>
            </Card>
          )}

          {/* Bank status */}
          <Card style={{ marginBottom: 20, borderLeft: profile?.bankName ? '4px solid #22C55E' : '4px solid #F97316' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
              <CreditCard size={22} color={profile?.bankName ? '#22C55E' : '#F97316'} />
              <div style={{ flex: 1 }}>
                <strong style={{ fontSize: 13, color: '#2F3A40' }}>
                  {profile?.bankName ? `${profile.bankName} · ${ACCOUNT_TYPES.find(t => t.value === profile.bankAccountType)?.label || profile.bankAccountType}` : 'Sin cuenta bancaria'}
                </strong>
                <p style={{ fontSize: 12, color: '#6b7280', margin: '2px 0 0' }}>
                  {profile?.bankName ? `Cuenta: ${maskAccount(profile.bankAccountNumber)} · Pagos semanales` : 'Configura tu cuenta para recibir pagos'}
                </p>
              </div>
              {!profile?.bankName && (
                <button onClick={() => setTab('profile')} style={{ fontSize: 12, color: '#F97316', fontWeight: 700, background: 'none', border: 'none', cursor: 'pointer' }}>Configurar →</button>
              )}
            </div>
          </Card>

          {/* Weekly breakdown */}
          <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: '0 0 12px' }}>📊 Ganancias semanales</h3>
          {(!earnings?.weekly || earnings.weekly.length === 0) ? (
            <Card style={{ textAlign: 'center', padding: 40, marginBottom: 24 }}>
              <Calendar size={40} color="#d1d5db" style={{ marginBottom: 8 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700, margin: 0 }}>Aún no hay historial de ganancias</p>
            </Card>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 24 }}>
              {earnings.weekly.map((w, i) => (
                <Card key={i}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                    <div>
                      <p style={{ fontWeight: 700, color: '#2F3A40', margin: 0, fontSize: 14 }}>
                        <Calendar size={14} color="#9ca3af" style={{ marginRight: 6, verticalAlign: 'middle' }} />
                        {fmtDate(w.week_start)} — {fmtDate(w.week_end)}
                      </p>
                      <span style={{ fontSize: 12, color: '#6b7280' }}>{w.deliveries} entrega{w.deliveries != 1 ? 's' : ''}</span>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <p style={{ fontWeight: 900, color: '#22C55E', margin: 0, fontSize: 18 }}>{fmt(w.earned)}</p>
                    </div>
                  </div>
                </Card>
              ))}
            </div>
          )}

          {/* Payouts history */}
          <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: '0 0 12px' }}>💸 Historial de pagos</h3>
          {(!earnings?.payouts || earnings.payouts.length === 0) ? (
            <Card style={{ textAlign: 'center', padding: 40 }}>
              <Banknote size={40} color="#d1d5db" style={{ marginBottom: 8 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700, margin: 0 }}>No hay pagos registrados aún</p>
              <p style={{ color: '#d1d5db', fontSize: 12, margin: '4px 0 0' }}>Los pagos se procesan semanalmente los {earnings?.commission?.payout_day === 'thursday' ? 'jueves' : earnings?.commission?.payout_day || 'jueves'}.</p>
            </Card>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {earnings.payouts.map((p) => {
                const statusColors = { paid: '#22C55E', pending: '#F97316', processing: '#3B82F6', failed: '#EF4444' };
                const statusLabels = { paid: 'Pagado', pending: 'Pendiente', processing: 'Procesando', failed: 'Fallido' };
                return (
                  <Card key={p.id} style={{ border: `1px solid ${statusColors[p.status] || '#e5e7eb'}20` }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                      <div>
                        <p style={{ fontWeight: 700, color: '#2F3A40', margin: 0, fontSize: 14 }}>
                          Período: {fmtDate(p.period_start)} — {fmtDate(p.period_end)}
                        </p>
                        <span style={{ fontSize: 12, color: '#6b7280' }}>
                          {p.total_deliveries} entregas · {p.paid_at ? `Pagado: ${fmtDate(p.paid_at)}` : 'Sin pagar'}
                        </span>
                      </div>
                      <div style={{ textAlign: 'right' }}>
                        <p style={{ fontWeight: 900, color: statusColors[p.status] || '#2F3A40', margin: 0, fontSize: 18 }}>
                          {fmt(p.net_amount)}
                        </p>
                        <span style={{ fontSize: 11, fontWeight: 700, color: statusColors[p.status], background: (statusColors[p.status] || '#6b7280') + '15', padding: '2px 8px', borderRadius: 6 }}>
                          {statusLabels[p.status] || p.status}
                        </span>
                      </div>
                    </div>
                    {p.notes && <p style={{ fontSize: 12, color: '#6b7280', margin: '8px 0 0', fontStyle: 'italic' }}>📝 {p.notes}</p>}
                  </Card>
                );
              })}
            </div>
          )}
        </>
      )}

      {/* ══════════════════════════════════════════════
         TAB: DOCUMENTOS
      ══════════════════════════════════════════════ */}
      {tab === 'documents' && (
        <>
          {riderStatus === 'pending_email' && (
            <Card style={{ textAlign: 'center', padding: 32 }}>
              <div style={{ fontSize: 48, marginBottom: 12 }}>📧</div>
              <h3 style={{ fontWeight: 800, color: '#92400E', margin: '0 0 8px' }}>Verifica tu email primero</h3>
              <p style={{ fontSize: 14, color: '#A16207', margin: 0 }}>Debes verificar tu correo electrónico antes de poder subir documentos.</p>
            </Card>
          )}

          {['pending_docs', 'pending_review', 'approved'].includes(riderStatus) && (
            <>
              {/* Vehicle info */}
              <Card style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8 }}>
                  <div>
                    <h3 style={{ fontSize: 15, fontWeight: 800, color: '#2F3A40', margin: '0 0 4px' }}>Vehículo registrado</h3>
                    <span style={{ fontSize: 20 }}>{VEHICLE_LABELS[vehicleType] || vehicleType || 'No definido'}</span>
                  </div>
                  <div style={{ textAlign: 'right' }}>
                    <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>Documento</span>
                    <p style={{ fontWeight: 700, color: '#2F3A40', margin: '2px 0 0', fontSize: 14 }}>{idType?.toUpperCase()}: {idNumber || '—'}</p>
                  </div>
                </div>
                {needsMotorDocs && (
                  <div style={{ marginTop: 12, padding: '10px 16px', background: '#FFF3E0', borderRadius: 10, fontSize: 12, color: '#e65100', display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Shield size={14} /> Vehículo motorizado: se requiere <strong>licencia</strong> y <strong>padrón</strong>.
                  </div>
                )}
              </Card>

              {/* Document cards */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {requiredDocs.map((key) => {
                  const dt = DOC_TYPES[key];
                  if (!dt) return null;
                  const docStatus = getDocStatus(key);
                  const doc = documents.find(d => d.doc_type === key);
                  const statusLabels = { approved: '✅ Aprobado', rejected: '❌ Rechazado', pending: '⏳ En revisión', missing: '📤 No subido' };
                  const statusBg = { approved: '#e8f5e9', rejected: '#fce4ec', pending: '#fff3e0', missing: '#f5f5f5' };
                  const statusClr = { approved: '#2e7d32', rejected: '#c62828', pending: '#e65100', missing: '#9ca3af' };
                  const canUp = riderStatus === 'pending_docs' || (riderStatus === 'pending_review' && docStatus === 'rejected');
                  return (
                    <Card key={key} style={{ border: `1px solid ${docStatus === 'rejected' ? '#ef9a9a' : missingDocs.includes(key) ? '#FDBA74' : '#f0f0f0'}` }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                          <span style={{ fontSize: 24 }}>{dt.icon}</span>
                          <div>
                            <h4 style={{ fontWeight: 700, color: '#2F3A40', margin: 0, fontSize: 14 }}>{dt.label}</h4>
                            <span style={{ fontSize: 11, color: '#9ca3af' }}>{dt.description}</span>
                          </div>
                        </div>
                        <span style={{ padding: '4px 10px', borderRadius: 8, fontSize: 11, fontWeight: 700, background: statusBg[docStatus], color: statusClr[docStatus] }}>{statusLabels[docStatus]}</span>
                      </div>
                      {doc?.admin_notes && docStatus === 'rejected' && (
                        <div style={{ background: '#fce4ec', borderRadius: 8, padding: '8px 12px', fontSize: 12, color: '#c62828', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
                          <AlertTriangle size={14} /> <strong>Motivo:</strong> {doc.admin_notes}
                        </div>
                      )}
                      {key === 'id_card' && idNumber && (docStatus === 'missing' || docStatus === 'rejected') && (
                        <div style={{ background: '#EFF6FF', borderRadius: 8, padding: '8px 12px', fontSize: 12, color: '#1E40AF', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
                          <Shield size={14} /> El documento debe corresponder a <strong>{idType?.toUpperCase()}: {idNumber}</strong>
                        </div>
                      )}
                      {canUp && (docStatus === 'missing' || docStatus === 'rejected') && (
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                          {selectedDocType === key ? (
                            <>
                              <input ref={fileInputRef} type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" style={{ flex: 1, fontSize: 13, minWidth: 150 }} />
                              <button onClick={() => handleDocUpload(key)} disabled={uploading} style={{ padding: '8px 20px', borderRadius: 10, background: '#F97316', color: '#fff', fontWeight: 700, border: 'none', cursor: 'pointer', fontSize: 13, opacity: uploading ? 0.6 : 1 }}>
                                {uploading ? '⏳ Subiendo...' : '📤 Subir'}
                              </button>
                              <button onClick={() => setSelectedDocType('')} style={{ padding: '8px 12px', borderRadius: 10, background: '#f3f4f6', border: 'none', cursor: 'pointer', fontSize: 13 }}>✕</button>
                            </>
                          ) : (
                            <button onClick={() => setSelectedDocType(key)} style={{ padding: '10px 20px', borderRadius: 10, background: '#FFF7ED', color: '#F97316', fontWeight: 700, border: '1px solid #F97316', cursor: 'pointer', fontSize: 13, display: 'flex', alignItems: 'center', gap: 6 }}>
                              <Upload size={14} /> {docStatus === 'rejected' ? 'Subir nuevo documento' : 'Subir documento'}
                            </button>
                          )}
                        </div>
                      )}
                      {doc?.file_url && docStatus !== 'rejected' && (
                        <a href={doc.file_url} target="_blank" rel="noopener noreferrer" style={{ fontSize: 12, color: '#00A8E8', fontWeight: 600, textDecoration: 'none', marginTop: 8, display: 'inline-block' }}>📎 Ver documento subido</a>
                      )}
                    </Card>
                  );
                })}
              </div>
              {uploadMsg && (
                <div style={{ marginTop: 12, padding: '10px 16px', borderRadius: 10, background: uploadMsg.startsWith('✅') ? '#e8f5e9' : '#fce4ec', fontSize: 13, fontWeight: 600, color: uploadMsg.startsWith('✅') ? '#2e7d32' : '#c62828' }}>{uploadMsg}</div>
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

      {/* ══════════════════════════════════════════════
         TAB: VALORACIONES
      ══════════════════════════════════════════════ */}
      {tab === 'ratings' && isApproved && (
        <>
          <Card style={{ marginBottom: 20, textAlign: 'center' }}>
            <div style={{ fontSize: 48, fontWeight: 900, color: '#F59E0B' }}>⭐ {avgRating || '—'}</div>
            <p style={{ fontSize: 14, color: '#6b7280', fontWeight: 600, margin: '4px 0 0' }}>
              Rating promedio · {ratings.length} valoraci{ratings.length === 1 ? 'ón' : 'ones'}
            </p>
          </Card>

          {loading ? (
            <div style={{ textAlign: 'center', padding: 60, color: '#9ca3af' }}>Cargando valoraciones...</div>
          ) : ratings.length === 0 ? (
            <Card style={{ textAlign: 'center', padding: 60 }}>
              <Star size={48} color="#d1d5db" style={{ marginBottom: 12 }} />
              <p style={{ color: '#9ca3af', fontWeight: 700 }}>Aún no tienes valoraciones</p>
              <p style={{ color: '#d1d5db', fontSize: 13 }}>Las recibirás después de completar entregas.</p>
            </Card>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {ratings.map((r) => {
                const stars = Array.from({ length: 5 }, (_, i) => i < r.rating ? '⭐' : '☆').join('');
                return (
                  <Card key={r.id}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8, flexWrap: 'wrap', gap: 8 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ fontSize: 20 }}>{r.rater_type === 'vendor' ? '🏪' : '👤'}</span>
                        <div>
                          <p style={{ fontWeight: 700, color: '#2F3A40', margin: 0, fontSize: 14 }}>{r.rater_name}</p>
                          <span style={{ fontSize: 11, color: '#9ca3af' }}>{r.rater_type === 'vendor' ? 'Tienda' : 'Cliente'} · Pedido #{r.order_id}</span>
                        </div>
                      </div>
                      <span style={{ fontSize: 14 }}>{stars}</span>
                    </div>
                    {r.comment && <p style={{ fontSize: 13, color: '#555', margin: 0, lineHeight: 1.5, fontStyle: 'italic' }}>"{r.comment}"</p>}
                  </Card>
                );
              })}
            </div>
          )}
        </>
      )}

      {/* ══════════════════════════════════════════════
         TAB: PERFIL
      ══════════════════════════════════════════════ */}
      {tab === 'profile' && (
        <>
          {/* Personal info */}
          <Card style={{ marginBottom: 16 }}>
            <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: '0 0 16px', display: 'flex', alignItems: 'center', gap: 8 }}>
              <User size={18} color="#F97316" /> Información personal
            </h3>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
              <div style={{ flex: '1 1 180px' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Nombre</label>
                <input value={profileForm.firstName || ''} onChange={e => setProfileForm({ ...profileForm, firstName: sanitizeName(e.target.value) })}
                  inputMode="text" autoComplete="given-name"
                  style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, boxSizing: 'border-box' }}
                  placeholder="Nombre" />
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Apellido</label>
                <input value={profileForm.lastName || ''} onChange={e => setProfileForm({ ...profileForm, lastName: sanitizeName(e.target.value) })}
                  inputMode="text" autoComplete="family-name"
                  style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, boxSizing: 'border-box' }}
                  placeholder="Apellido" />
              </div>
              <div style={{ flex: '1 1 100%' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Teléfono</label>
                <div style={{ display: 'flex' }}>
                  <span style={{ padding: '10px 10px', background: '#e5e7eb', borderRadius: '10px 0 0 10px', border: '1px solid #d1d5db', borderRight: 'none', fontSize: 14, fontWeight: 600, color: '#374151', whiteSpace: 'nowrap' }}>+569</span>
                  <input value={profileForm.phone || ''} onChange={e => setProfileForm({ ...profileForm, phone: formatPhoneDigits(e.target.value) })}
                    style={{ width: '100%', padding: '10px 14px', borderRadius: '0 10px 10px 0', border: `1px solid ${profileForm.phone ? (isValidPhoneDigits(profileForm.phone) ? '#16a34a' : '#dc2626') : '#e5e7eb'}`, fontSize: 14, boxSizing: 'border-box' }}
                    placeholder="XXXXXXXX" maxLength={8} />
                </div>
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Región</label>
                <select value={profileForm.region || ''} onChange={e => setProfileForm({ ...profileForm, region: e.target.value, comuna: '' })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, background: '#fff', boxSizing: 'border-box' }}>
                  <option value="">Selecciona región...</option>
                  {REGIONES.map(r => <option key={r} value={r}>{r}</option>)}
                </select>
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Comuna</label>
                <select value={profileForm.comuna || ''} onChange={e => setProfileForm({ ...profileForm, comuna: e.target.value })}
                  disabled={!profileForm.region}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, background: '#fff', boxSizing: 'border-box', opacity: profileForm.region ? 1 : 0.6 }}>
                  <option value="">{profileForm.region ? 'Selecciona comuna...' : 'Primero selecciona región'}</option>
                  {getComunas(profileForm.region).map(c => <option key={c} value={c}>{c}</option>)}
                </select>
              </div>
            </div>
            {/* Read-only info */}
            <div style={{ marginTop: 16, padding: '12px 16px', background: '#f9fafb', borderRadius: 10 }}>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                <div style={{ flex: '1 1 140px', minWidth: 0 }}>
                  <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>Email</span>
                  <p style={{ fontSize: 13, fontWeight: 600, color: '#2F3A40', margin: '2px 0 0', wordBreak: 'break-all' }}>{profile?.email || '—'}</p>
                </div>
                <div style={{ flex: '1 1 140px' }}>
                  <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>Documento</span>
                  <p style={{ fontSize: 13, fontWeight: 600, color: '#2F3A40', margin: '2px 0 0' }}>{idType?.toUpperCase()}: {idNumber || '—'}</p>
                </div>
                <div style={{ flex: '1 1 140px' }}>
                  <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>Vehículo</span>
                  <p style={{ fontSize: 13, fontWeight: 600, color: '#2F3A40', margin: '2px 0 0' }}>{VEHICLE_LABELS[vehicleType] || vehicleType || '—'}</p>
                </div>
                <div style={{ flex: '1 1 140px' }}>
                  <span style={{ fontSize: 11, color: '#9ca3af', fontWeight: 600 }}>Miembro desde</span>
                  <p style={{ fontSize: 13, fontWeight: 600, color: '#2F3A40', margin: '2px 0 0' }}>{fmtDate(profile?.registeredAt)}</p>
                </div>
              </div>
            </div>
          </Card>

          {/* Bank account */}
          <Card style={{ marginBottom: 16, borderLeft: '4px solid #00A8E8' }}>
            <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: '0 0 4px', display: 'flex', alignItems: 'center', gap: 8 }}>
              <CreditCard size={18} color="#00A8E8" /> Cuenta bancaria
            </h3>
            <p style={{ fontSize: 12, color: '#6b7280', margin: '0 0 16px' }}>Aquí recibirás tus pagos semanales. Asegúrate de ingresar datos válidos.</p>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
              <div style={{ flex: '1 1 100%' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Banco</label>
                <select value={profileForm.bankName || ''} onChange={e => setProfileForm({ ...profileForm, bankName: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, background: '#fff', boxSizing: 'border-box' }}>
                  <option value="">Seleccionar banco...</option>
                  {BANK_OPTIONS.map(b => <option key={b} value={b}>{b}</option>)}
                </select>
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Tipo de cuenta</label>
                <select value={profileForm.bankAccountType || ''} onChange={e => setProfileForm({ ...profileForm, bankAccountType: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, background: '#fff', boxSizing: 'border-box' }}>
                  <option value="">Seleccionar...</option>
                  {ACCOUNT_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
              </div>
              <div style={{ flex: '1 1 180px' }}>
                <label style={{ fontSize: 11, fontWeight: 700, color: '#6b7280', display: 'block', marginBottom: 4 }}>Número de cuenta</label>
                <div style={{ position: 'relative' }}>
                  <input type={showAccount ? 'text' : 'password'} value={profileForm.bankAccountNumber || ''}
                    onChange={e => setProfileForm({ ...profileForm, bankAccountNumber: e.target.value })}
                    style={{ width: '100%', padding: '10px 38px 10px 14px', borderRadius: 10, border: '1px solid #e5e7eb', fontSize: 14, boxSizing: 'border-box' }}
                    placeholder="Ej: 000123456789" />
                  <button onClick={() => setShowAccount(!showAccount)} style={{ position: 'absolute', right: 8, top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', padding: 4 }}>
                    {showAccount ? <EyeOff size={16} color="#9ca3af" /> : <Eye size={16} color="#9ca3af" />}
                  </button>
                </div>
              </div>
            </div>
          </Card>

          {/* Stats summary (read-only) */}
          {isApproved && profile && (
            <Card style={{ marginBottom: 16 }}>
              <h3 style={{ fontSize: 16, fontWeight: 800, color: '#2F3A40', margin: '0 0 16px', display: 'flex', alignItems: 'center', gap: 8 }}>
                <TrendingUp size={18} color="#22C55E" /> Resumen
              </h3>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))', gap: 12 }}>
                {[
                  { label: 'Entregas totales', value: profile.totalDeliveries, color: '#2F3A40' },
                  { label: 'Total ganado', value: fmt(profile.totalEarned), color: '#22C55E' },
                  { label: 'Saldo pendiente', value: fmt(profile.pendingBalance), color: '#F97316' },
                  { label: 'Rating', value: profile.averageRating ? `⭐ ${profile.averageRating}` : '—', color: '#F59E0B' },
                ].map((s, i) => (
                  <div key={i} style={{ textAlign: 'center', padding: 12, background: '#f9fafb', borderRadius: 10 }}>
                    <span style={{ fontSize: 10, color: '#9ca3af', fontWeight: 700, textTransform: 'uppercase' }}>{s.label}</span>
                    <p style={{ fontSize: 18, fontWeight: 900, color: s.color, margin: '4px 0 0' }}>{s.value}</p>
                  </div>
                ))}
              </div>
            </Card>
          )}

          {/* Save button */}
          <button onClick={handleSaveProfile} disabled={savingProfile} style={{
            width: '100%', padding: 14, borderRadius: 14, fontWeight: 700, fontSize: 15,
            border: 'none', cursor: 'pointer', color: '#fff',
            background: savingProfile ? '#9ca3af' : 'linear-gradient(135deg, #F97316, #F59E0B)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
            boxShadow: '0 4px 14px rgba(249,115,22,0.3)',
          }}>
            {savingProfile ? <><RefreshCw size={16} className="spin" /> Guardando...</> : <><Save size={16} /> Guardar cambios</>}
          </button>

          {profileMsg && (
            <div style={{ marginTop: 12, padding: '10px 16px', borderRadius: 10, background: profileMsg.startsWith('✅') ? '#e8f5e9' : '#fce4ec', fontSize: 13, fontWeight: 600, color: profileMsg.startsWith('✅') ? '#2e7d32' : '#c62828' }}>{profileMsg}</div>
          )}
        </>
      )}
    </div>
  );
};

export default RiderDashboard;
