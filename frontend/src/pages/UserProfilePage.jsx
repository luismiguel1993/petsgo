import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { User, Edit2, Save, X, Plus, Trash2, Camera, Eye, EyeOff, Check, AlertCircle } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { getProfile, updateProfile, changePassword, getPets, addPet, updatePet, deletePet, uploadPetPhoto } from '../services/api';

const inputStyle = {
  width: '100%', padding: '12px 16px', background: '#f9fafb', borderRadius: '12px',
  border: '1.5px solid #e5e7eb', fontSize: '14px', fontWeight: 500, outline: 'none', boxSizing: 'border-box',
};
const labelStyle = { display: 'block', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '4px', textTransform: 'uppercase', letterSpacing: '0.5px' };
const cardStyle = { background: '#fff', borderRadius: '20px', padding: '24px', boxShadow: '0 2px 16px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0' };
const btnPrimary = {
  padding: '10px 20px', background: 'linear-gradient(135deg, #00A8E8, #0077b6)', color: '#fff',
  border: 'none', borderRadius: '10px', fontWeight: 700, fontSize: '13px', cursor: 'pointer'
};

const PET_TYPES = [
  { value: 'perro', label: '🐶 Perro' }, { value: 'gato', label: '🐱 Gato' },
  { value: 'ave', label: '🐦 Ave' }, { value: 'conejo', label: '🐰 Conejo' },
  { value: 'hamster', label: '🐹 Hámster' }, { value: 'pez', label: '🐠 Pez' },
  { value: 'reptil', label: '🦎 Reptil' }, { value: 'otro', label: '🐾 Otro' },
];

const petEmojis = { perro: '🐶', gato: '🐱', ave: '🐦', conejo: '🐰', hamster: '🐹', pez: '🐠', reptil: '🦎', otro: '🐾' };

const calcAge = (birthDate) => {
  if (!birthDate) return null;
  const diff = Date.now() - new Date(birthDate).getTime();
  const years = Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000));
  const months = Math.floor((diff % (365.25 * 24 * 60 * 60 * 1000)) / (30.44 * 24 * 60 * 60 * 1000));
  if (years > 0) return `${years} año${years > 1 ? 's' : ''}${months > 0 ? ` ${months} mes${months > 1 ? 'es' : ''}` : ''}`;
  if (months > 0) return `${months} mes${months > 1 ? 'es' : ''}`;
  return 'Menos de 1 mes';
};

const UserProfilePage = () => {
  const { user, updateUser } = useAuth();
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [pets, setPets] = useState([]);
  const [editing, setEditing] = useState(false);
  const [editForm, setEditForm] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState({ type: '', text: '' });

  // Password change
  const [showPassSection, setShowPassSection] = useState(false);
  const [passForm, setPassForm] = useState({ currentPassword: '', newPassword: '', confirmPassword: '' });
  const [showNewPass, setShowNewPass] = useState(false);

  // Pet modal
  const [petModal, setPetModal] = useState(null); // null=closed, {}=new, {id:...}=edit

  const fetchData = useCallback(async () => {
    try {
      const profileRes = await getProfile();
      const d = profileRes.data;
      // Normalise camelCase from API into the flat shape the UI expects
      setProfile({
        first_name: d.firstName ?? '',
        last_name: d.lastName ?? '',
        email: d.email ?? '',
        phone: d.phone ?? '',
        id_type: d.idType ?? '',
        id_number: d.idNumber ?? '',
        birth_date: d.birthDate ?? '',
        avatar_url: d.avatarUrl ?? '',
      });
      setPets(Array.isArray(d.pets) ? d.pets : []);
    } catch (err) {
      if (err.response?.status === 401 || err.response?.status === 403) {
        setMsg({ type: 'error', text: 'Tu sesión ha expirado. Por favor inicia sesión nuevamente.' });
        setTimeout(() => navigate('/login'), 2000);
      } else {
        setMsg({ type: 'error', text: 'Error cargando datos. Intenta recargar la página.' });
      }
    }
    finally { setLoading(false); }
  }, [navigate]);

  useEffect(() => {
    if (!user) { navigate('/login'); return; }
    fetchData();
  }, [user, navigate, fetchData]);

  const startEdit = () => {
    setEditing(true);
    setEditForm({
      first_name: profile?.first_name || '',
      last_name: profile?.last_name || '',
      phone: profile?.phone || '',
    });
  };

  const saveProfile = async () => {
    setSaving(true); setMsg({ type: '', text: '' });
    try {
      // API expects camelCase
      await updateProfile({ firstName: editForm.first_name, lastName: editForm.last_name, phone: editForm.phone });
      setProfile(prev => ({ ...prev, ...editForm }));
      updateUser({ firstName: editForm.first_name, lastName: editForm.last_name, phone: editForm.phone });
      setEditing(false);
      setMsg({ type: 'success', text: 'Perfil actualizado' });
    } catch (err) {
      setMsg({ type: 'error', text: err.response?.data?.message || 'Error al guardar' });
    } finally { setSaving(false); }
  };

  const passChecks = {
    length: passForm.newPassword.length >= 8,
    upper: /[A-Z]/.test(passForm.newPassword),
    lower: /[a-z]/.test(passForm.newPassword),
    number: /[0-9]/.test(passForm.newPassword),
    special: /[^A-Za-z0-9]/.test(passForm.newPassword),
  };
  const passStrength = Object.values(passChecks).filter(Boolean).length;

  const handleChangePassword = async () => {
    setMsg({ type: '', text: '' });
    if (passStrength < 5) { setMsg({ type: 'error', text: 'La nueva contraseña no cumple los requisitos' }); return; }
    if (passForm.newPassword !== passForm.confirmPassword) { setMsg({ type: 'error', text: 'Las contraseñas no coinciden' }); return; }
    setSaving(true);
    try {
      await changePassword(passForm.currentPassword, passForm.newPassword);
      setMsg({ type: 'success', text: 'Contraseña actualizada correctamente' });
      setPassForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
      setShowPassSection(false);
    } catch (err) {
      setMsg({ type: 'error', text: err.response?.data?.message || 'Error al cambiar contraseña' });
    } finally { setSaving(false); }
  };

  // Pet CRUD
  const savePet = async () => {
    setSaving(true); setMsg({ type: '', text: '' });
    try {
      // Upload photo if a new file was selected
      let photoUrl = petModal.photoUrl || '';
      if (petModal._photoFile) {
        const uploadRes = await uploadPetPhoto(petModal._photoFile);
        photoUrl = uploadRes.data.url;
      }
      const payload = {
        name: petModal.name,
        petType: petModal.petType,
        breed: petModal.breed || '',
        birthDate: petModal.birthDate || '',
        notes: petModal.notes || '',
        photoUrl,
      };
      if (petModal.id) {
        await updatePet(petModal.id, payload);
      } else {
        await addPet(payload);
      }
      // Re-fetch from profile which includes pets
      const profileRes = await getProfile();
      const d = profileRes.data;
      setPets(Array.isArray(d.pets) ? d.pets : []);
      setPetModal(null);
      setMsg({ type: 'success', text: petModal.id ? 'Mascota actualizada' : 'Mascota agregada' });
    } catch (err) {
      setMsg({ type: 'error', text: err.response?.data?.message || 'Error al guardar mascota' });
    } finally { setSaving(false); }
  };

  const removePet = async (id) => {
    if (!window.confirm('¿Eliminar esta mascota?')) return;
    try {
      await deletePet(id);
      setPets(prev => prev.filter(p => p.id !== id));
      setMsg({ type: 'success', text: 'Mascota eliminada' });
    } catch { setMsg({ type: 'error', text: 'Error al eliminar' }); }
  };

  if (loading) return (
    <div style={{ minHeight: '60vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <div style={{ textAlign: 'center', color: '#9ca3af' }}>
        <div style={{ fontSize: '40px', marginBottom: '8px', animation: 'spin 1s linear infinite' }}>🐾</div>
        <p>Cargando perfil...</p>
      </div>
    </div>
  );

  return (
    <div style={{
      minHeight: '80vh', padding: '32px 16px',
      background: 'linear-gradient(135deg, #f0f9ff 0%, #FDFCFB 50%, #fef9c3 100%)'
    }}>
      <div style={{ maxWidth: '900px', margin: '0 auto' }}>
        <h1 style={{ fontSize: '28px', fontWeight: 900, color: '#2F3A40', marginBottom: '24px' }}>👤 Mi Perfil</h1>

        {msg.text && (
          <div style={{
            background: msg.type === 'error' ? '#fef2f2' : '#f0fdf4',
            border: `1px solid ${msg.type === 'error' ? '#fecaca' : '#bbf7d0'}`,
            color: msg.type === 'error' ? '#dc2626' : '#16a34a',
            padding: '12px 16px', borderRadius: '12px', fontSize: '13px', marginBottom: '16px',
            display: 'flex', alignItems: 'center', gap: '8px'
          }}>
            {msg.type === 'error' ? <AlertCircle size={16} /> : <Check size={16} />} {msg.text}
          </div>
        )}

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 300px), 1fr))', gap: '20px' }}>
          {/* Profile Card */}
          <div style={cardStyle}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#2F3A40', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <User size={20} /> Datos Personales
              </h2>
              {!editing ? (
                <button onClick={startEdit} style={{ ...btnPrimary, display: 'flex', alignItems: 'center', gap: '6px', padding: '8px 16px' }}>
                  <Edit2 size={14} /> Editar
                </button>
              ) : (
                <div style={{ display: 'flex', gap: '8px' }}>
                  <button onClick={() => setEditing(false)} style={{ ...btnPrimary, background: '#e5e7eb', color: '#6b7280', display: 'flex', alignItems: 'center', gap: '4px', padding: '8px 14px' }}>
                    <X size={14} /> Cancelar
                  </button>
                  <button onClick={saveProfile} disabled={saving} style={{ ...btnPrimary, display: 'flex', alignItems: 'center', gap: '4px', padding: '8px 14px' }}>
                    <Save size={14} /> {saving ? 'Guardando...' : 'Guardar'}
                  </button>
                </div>
              )}
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
              <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 140px' }}>
                  <label style={labelStyle}>Nombre</label>
                  {editing ? (
                    <input value={editForm.first_name} onChange={e => setEditForm(p => ({ ...p, first_name: e.target.value }))} style={inputStyle} />
                  ) : (
                    <p style={{ fontSize: '15px', fontWeight: 600, color: '#2F3A40' }}>{profile?.first_name || '—'}</p>
                  )}
                </div>
                <div style={{ flex: '1 1 140px' }}>
                  <label style={labelStyle}>Apellido</label>
                  {editing ? (
                    <input value={editForm.last_name} onChange={e => setEditForm(p => ({ ...p, last_name: e.target.value }))} style={inputStyle} />
                  ) : (
                    <p style={{ fontSize: '15px', fontWeight: 600, color: '#2F3A40' }}>{profile?.last_name || '—'}</p>
                  )}
                </div>
              </div>

              <div>
                <label style={labelStyle}>Email</label>
                <p style={{ fontSize: '15px', fontWeight: 600, color: '#2F3A40' }}>{profile?.email || user?.email}</p>
              </div>

              <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                <div style={{ flex: '1 1 140px' }}>
                  <label style={labelStyle}>Teléfono</label>
                  {editing ? (
                    <input value={editForm.phone} onChange={e => setEditForm(p => ({ ...p, phone: e.target.value }))} style={inputStyle} />
                  ) : (
                    <p style={{ fontSize: '15px', fontWeight: 600, color: '#2F3A40' }}>{profile?.phone || '—'}</p>
                  )}
                </div>
                <div style={{ flex: '1 1 140px' }}>
                  <label style={labelStyle}>Documento</label>
                  <p style={{ fontSize: '15px', fontWeight: 600, color: '#2F3A40' }}>
                    {profile?.id_type ? `${profile.id_type.toUpperCase()}: ${profile.id_number}` : '—'}
                  </p>
                </div>
              </div>

              <div>
                <label style={labelStyle}>Fecha de Nacimiento</label>
                <p style={{ fontSize: '15px', fontWeight: 600, color: '#2F3A40' }}>
                  {profile?.birth_date ? new Date(profile.birth_date + 'T12:00:00').toLocaleDateString('es-CL') : '—'}
                </p>
              </div>
            </div>

            {/* Change Password Section */}
            <div style={{ marginTop: '20px', paddingTop: '20px', borderTop: '1px solid #f0f0f0' }}>
              <button onClick={() => setShowPassSection(!showPassSection)}
                style={{ background: 'none', border: 'none', color: '#00A8E8', fontWeight: 700, fontSize: '14px', cursor: 'pointer', padding: 0 }}>
                🔐 {showPassSection ? 'Ocultar' : 'Cambiar contraseña'}
              </button>
              {showPassSection && (
                <div style={{ marginTop: '14px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                  <div>
                    <label style={labelStyle}>Contraseña actual</label>
                    <input type="password" value={passForm.currentPassword}
                      onChange={e => setPassForm(p => ({ ...p, currentPassword: e.target.value }))} style={inputStyle} />
                  </div>
                  <div>
                    <label style={labelStyle}>Nueva contraseña</label>
                    <div style={{ position: 'relative' }}>
                      <input type={showNewPass ? 'text' : 'password'} value={passForm.newPassword}
                        onChange={e => setPassForm(p => ({ ...p, newPassword: e.target.value }))}
                        style={{ ...inputStyle, paddingRight: '44px' }} />
                      <button type="button" onClick={() => setShowNewPass(!showNewPass)}
                        style={{ position: 'absolute', right: '12px', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', color: '#9ca3af', cursor: 'pointer' }}>
                        {showNewPass ? <EyeOff size={16} /> : <Eye size={16} />}
                      </button>
                    </div>
                    {passForm.newPassword && (
                      <div style={{ marginTop: '6px', display: 'flex', flexWrap: 'wrap', gap: '4px 10px' }}>
                        {[['length','8+'],['upper','A-Z'],['lower','a-z'],['number','0-9'],['special','#$@']].map(([k,l]) => (
                          <span key={k} style={{ fontSize: '11px', color: passChecks[k] ? '#16a34a' : '#9ca3af' }}>
                            {passChecks[k] ? '✓' : '✗'} {l}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                  <div>
                    <label style={labelStyle}>Confirmar nueva</label>
                    <input type="password" value={passForm.confirmPassword}
                      onChange={e => setPassForm(p => ({ ...p, confirmPassword: e.target.value }))}
                      style={{ ...inputStyle, borderColor: passForm.confirmPassword ? (passForm.newPassword === passForm.confirmPassword ? '#16a34a' : '#dc2626') : '#e5e7eb' }} />
                  </div>
                  <button onClick={handleChangePassword} disabled={saving} style={{ ...btnPrimary, width: '100%', padding: '12px' }}>
                    {saving ? 'Guardando...' : 'Cambiar Contraseña'}
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Pets Card */}
          <div style={cardStyle}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#2F3A40' }}>🐾 Mis Mascotas</h2>
              <button onClick={() => setPetModal({ petType: 'perro', name: '', breed: '', birthDate: '', notes: '', photoUrl: '' })}
                style={{ ...btnPrimary, display: 'flex', alignItems: 'center', gap: '6px', padding: '8px 16px', background: 'linear-gradient(135deg, #FFC400, #e6b000)' }}>
                <Plus size={14} /> Agregar
              </button>
            </div>

            {pets.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '32px 16px', color: '#9ca3af' }}>
                <div style={{ fontSize: '48px', marginBottom: '8px' }}>🐾</div>
                <p style={{ fontWeight: 600 }}>Aún no tienes mascotas registradas</p>
                <p style={{ fontSize: '13px', marginTop: '4px' }}>El administrador puede agregar tus mascotas desde el panel.</p>
              </div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                {pets.map(pet => (
                  <div key={pet.id} style={{
                    background: '#f9fafb', borderRadius: '14px', padding: '14px',
                    border: '1.5px solid #e5e7eb', display: 'flex', alignItems: 'center', gap: '14px'
                  }}>
                    {pet.photoUrl ? (
                      <img src={pet.photoUrl} alt={pet.name} style={{ width: '56px', height: '56px', borderRadius: '14px', objectFit: 'cover' }} />
                    ) : (
                      <div style={{
                        width: '56px', height: '56px', borderRadius: '14px', background: '#e0f2fe',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '28px', flexShrink: 0
                      }}>
                        {petEmojis[pet.petType] || '🐾'}
                      </div>
                    )}
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <p style={{ fontWeight: 800, fontSize: '15px', color: '#2F3A40' }}>{pet.name}</p>
                      <p style={{ fontSize: '12px', color: '#6b7280' }}>
                        {PET_TYPES.find(t => t.value === pet.petType)?.label || pet.petType}
                        {pet.breed ? ` · ${pet.breed}` : ''}
                      </p>
                      {pet.birthDate && (
                        <p style={{ fontSize: '11px', color: '#9ca3af', marginTop: '2px' }}>
                          🎂 {calcAge(pet.birthDate)}
                        </p>
                      )}
                    </div>
                    <div style={{ display: 'flex', gap: '6px', flexShrink: 0 }}>
                      <button onClick={() => setPetModal({ ...pet })}
                        style={{ background: '#e0f2fe', border: 'none', borderRadius: '8px', padding: '8px', cursor: 'pointer', color: '#0077b6' }}>
                        <Edit2 size={14} />
                      </button>
                      <button onClick={() => removePet(pet.id)}
                        style={{ background: '#fef2f2', border: 'none', borderRadius: '8px', padding: '8px', cursor: 'pointer', color: '#dc2626' }}>
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Pet Modal */}
      {petModal && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex',
          alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: '16px'
        }} onClick={() => setPetModal(null)}>
          <div style={{
            background: '#fff', borderRadius: '20px', width: '100%', maxWidth: '440px',
            boxShadow: '0 20px 60px rgba(0,0,0,0.2)',
            maxHeight: '90vh', display: 'flex', flexDirection: 'column', overflow: 'hidden',
          }} onClick={e => e.stopPropagation()}>
            {/* Modal header - always visible */}
            <div style={{
              display: 'flex', justifyContent: 'space-between', alignItems: 'center',
              padding: '20px 24px 16px', borderBottom: '1px solid #f0f0f0', flexShrink: 0,
            }}>
              <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#2F3A40', margin: 0 }}>
                {petModal.id ? '✏️ Editar Mascota' : '➕ Nueva Mascota'}
              </h3>
              <button onClick={() => setPetModal(null)} style={{
                background: '#f3f4f6', border: 'none', color: '#6b7280', cursor: 'pointer',
                borderRadius: '50%', width: '32px', height: '32px', display: 'flex',
                alignItems: 'center', justifyContent: 'center', flexShrink: 0,
              }}>
                <X size={18} />
              </button>
            </div>

            {/* Modal body - scrollable */}
            <div style={{ overflowY: 'auto', flex: 1, padding: '16px 24px', display: 'flex', flexDirection: 'column', gap: '14px' }}>

              {/* Photo upload */}
              <div>
                <label style={labelStyle}>Foto (opcional)</label>
                <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
                  {/* Preview */}
                  <div style={{
                    width: '80px', height: '80px', borderRadius: '16px', overflow: 'hidden', flexShrink: 0,
                    border: '2px dashed #d1d5db', background: '#f9fafb',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    position: 'relative', cursor: 'pointer',
                  }}
                    onClick={() => document.getElementById('pet-photo-input').click()}
                  >
                    {(petModal._photoPreview || petModal.photoUrl) ? (
                      <img src={petModal._photoPreview || petModal.photoUrl} alt="preview"
                        style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    ) : (
                      <div style={{ textAlign: 'center', color: '#9ca3af' }}>
                        <Camera size={24} style={{ margin: '0 auto 2px' }} />
                        <div style={{ fontSize: '10px', fontWeight: 600 }}>Subir</div>
                      </div>
                    )}
                  </div>
                  <div style={{ flex: 1 }}>
                    <input type="file" id="pet-photo-input" accept="image/*" style={{ display: 'none' }}
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (!file) return;
                        if (file.size > 5 * 1024 * 1024) { setMsg({ type: 'error', text: 'La imagen no debe exceder 5MB' }); return; }
                        const reader = new FileReader();
                        reader.onload = () => setPetModal(p => ({ ...p, _photoFile: file, _photoPreview: reader.result }));
                        reader.readAsDataURL(file);
                      }}
                    />
                    <button type="button" onClick={() => document.getElementById('pet-photo-input').click()}
                      style={{
                        display: 'flex', alignItems: 'center', gap: '6px', padding: '8px 14px',
                        background: '#f0f9ff', border: '1.5px solid #bae6fd', borderRadius: '10px',
                        color: '#0077b6', fontWeight: 600, fontSize: '12px', cursor: 'pointer',
                        fontFamily: 'Poppins, sans-serif',
                      }}>
                      <Camera size={14} /> {petModal._photoPreview || petModal.photoUrl ? 'Cambiar foto' : 'Seleccionar foto'}
                    </button>
                    {(petModal._photoPreview || petModal.photoUrl) && (
                      <button type="button"
                        onClick={() => setPetModal(p => ({ ...p, _photoFile: null, _photoPreview: null, photoUrl: '' }))}
                        style={{
                          marginTop: '6px', display: 'flex', alignItems: 'center', gap: '4px',
                          background: 'none', border: 'none', color: '#ef4444', fontSize: '11px',
                          fontWeight: 600, cursor: 'pointer', padding: 0,
                        }}>
                        <Trash2 size={12} /> Quitar foto
                      </button>
                    )}
                    <p style={{ fontSize: '10px', color: '#9ca3af', marginTop: '4px' }}>JPG, PNG, WebP. Máx 5MB</p>
                  </div>
                </div>
              </div>

              <div>
                <label style={labelStyle}>Tipo de Mascota *</label>
                <select value={petModal.petType} onChange={e => setPetModal(p => ({ ...p, petType: e.target.value }))}
                  style={{ ...inputStyle, cursor: 'pointer' }}>
                  {PET_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
              </div>
              <div>
                <label style={labelStyle}>Nombre *</label>
                <input value={petModal.name} onChange={e => setPetModal(p => ({ ...p, name: e.target.value }))}
                  placeholder="Nombre de la mascota" style={inputStyle} />
              </div>
              <div>
                <label style={labelStyle}>Raza</label>
                <input value={petModal.breed || ''} onChange={e => setPetModal(p => ({ ...p, breed: e.target.value }))}
                  placeholder="Ej: Labrador, Siamés..." style={inputStyle} />
              </div>
              <div>
                <label style={labelStyle}>Fecha de Nacimiento</label>
                <input type="date" value={petModal.birthDate || ''} onChange={e => setPetModal(p => ({ ...p, birthDate: e.target.value }))}
                  style={inputStyle} max={new Date().toISOString().split('T')[0]} />
              </div>
              <div>
                <label style={labelStyle}>Notas</label>
                <textarea value={petModal.notes || ''} onChange={e => setPetModal(p => ({ ...p, notes: e.target.value }))}
                  placeholder="Alergias, condiciones especiales..." rows={2}
                  style={{ ...inputStyle, resize: 'vertical', fontFamily: 'inherit' }} />
              </div>
            </div>

            {/* Modal footer - always visible */}
            <div style={{
              display: 'flex', gap: '10px', padding: '16px 24px 20px',
              borderTop: '1px solid #f0f0f0', flexShrink: 0,
            }}>
              <button onClick={() => setPetModal(null)}
                style={{ flex: 1, padding: '12px', background: '#f3f4f6', color: '#6b7280', border: 'none', borderRadius: '12px', fontWeight: 700, fontSize: '14px', cursor: 'pointer' }}>
                Cancelar
              </button>
              <button onClick={savePet} disabled={saving || !petModal.name?.trim()}
                style={{ flex: 1, ...btnPrimary, padding: '12px', fontSize: '14px', opacity: !petModal.name?.trim() ? 0.5 : 1 }}>
                {saving ? 'Guardando...' : (petModal.id ? 'Guardar' : 'Agregar')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default UserProfilePage;
