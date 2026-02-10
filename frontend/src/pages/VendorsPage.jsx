import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Store, MapPin, Star, Clock } from 'lucide-react';
import { getVendors } from '../services/api';

const DEMO_VENDORS = [
  { id: 1, store_name: 'PetShop Las Condes', address: 'Av. Las Condes 1234, Las Condes', rating: 4.8, time: '15-25 min', img: 'https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?auto=format&fit=crop&q=80&w=600' },
  { id: 2, store_name: 'Mundo Animal Centro', address: 'Calle Morandé 567, Santiago Centro', rating: 4.5, time: '20-30 min', img: 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&q=80&w=600' },
  { id: 3, store_name: 'La Huella Store', address: 'Av. Providencia 890, Providencia', rating: 4.9, time: '10-20 min', img: 'https://images.unsplash.com/photo-1581888227599-779811939961?auto=format&fit=crop&q=80&w=600' },
  { id: 4, store_name: 'Patitas Chile', address: 'Calle Merced 910, Santiago Centro', rating: 4.7, time: '30-45 min', img: 'https://images.unsplash.com/photo-1585584114963-503344a119b0?auto=format&fit=crop&q=80&w=600' },
  { id: 5, store_name: 'Happy Pets Providencia', address: 'Av. Irarrázaval 2030, Ñuñoa', rating: 4.6, time: '20-35 min', img: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=600' },
  { id: 6, store_name: 'Vet & Shop', address: 'Av. Vitacura 4520, Vitacura', rating: 4.9, time: '15-25 min', img: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=600' },
  { id: 7, store_name: 'PetLand Chile', address: 'Av. Apoquindo 6400, Las Condes', rating: 4.8, time: '25-40 min', img: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?auto=format&fit=crop&q=80&w=600' },
  { id: 8, store_name: 'MascotaExpress', address: 'Av. Matta 1250, San Joaquín', rating: 4.4, time: '30-50 min', img: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?auto=format&fit=crop&q=80&w=600' },
];

const VendorsPage = () => {
  const [vendors, setVendors] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      try {
        const { data } = await getVendors();
        // Mezclamos datos reales con imágenes demo si no tienen
        const realVendors = data.data?.map((v, i) => ({
          ...v,
          rating: (4 + Math.random()).toFixed(1),
          time: `${15 + i * 5}-${25 + i * 5} min`,
          img: DEMO_VENDORS[i % DEMO_VENDORS.length].img
        }));
        setVendors(realVendors?.length > 0 ? realVendors : DEMO_VENDORS);
      } catch {
        setVendors(DEMO_VENDORS);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  return (
    <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '40px 24px' }}>
      {/* Header de sección */}
      <div style={{ marginBottom: '40px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '8px' }}>
          <Store size={28} style={{ color: '#00A8E8' }} />
          <h2 style={{ fontSize: 'clamp(24px, 4vw, 36px)', fontWeight: 800, color: '#2F3A40' }}>Tiendas Oficiales</h2>
        </div>
        <p style={{ fontSize: '15px', color: '#9ca3af', fontWeight: 500, marginLeft: '40px' }}>Explora las mejores tiendas verificadas y sus catálogos.</p>
      </div>

      {loading ? (
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
          gap: '24px'
        }}>
          {[...Array(8)].map((_, i) => (
            <div key={i} style={{
              background: '#fff', borderRadius: '16px', padding: '16px',
              boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
              height: '300px', animation: 'pulse 2s infinite'
            }} />
          ))}
        </div>
      ) : (
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
          gap: '24px'
        }}>
          {vendors.map((vendor) => (
            <Link
              key={vendor.id}
              to={`/tienda/${vendor.id}`}
              style={{
                background: '#fff', borderRadius: '16px', padding: '12px',
                boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
                textDecoration: 'none', color: 'inherit', transition: 'all 0.3s ease',
                display: 'block'
              }}
              onMouseEnter={e => { e.currentTarget.style.boxShadow = '0 12px 32px rgba(0,0,0,0.12)'; e.currentTarget.style.transform = 'translateY(-4px)'; }}
              onMouseLeave={e => { e.currentTarget.style.boxShadow = '0 2px 12px rgba(0,0,0,0.06)'; e.currentTarget.style.transform = 'translateY(0)'; }}
            >
              <div style={{ position: 'relative', marginBottom: '14px', overflow: 'hidden', borderRadius: '12px' }}>
                <img 
                  src={vendor.img || vendor.logo_url} 
                  style={{ height: '180px', width: '100%', objectFit: 'cover', transition: 'transform 0.5s ease' }}
                  alt={vendor.store_name} 
                />
                <div style={{
                  position: 'absolute', top: '10px', right: '10px',
                  background: 'rgba(255,255,255,0.92)', backdropFilter: 'blur(8px)',
                  padding: '4px 10px', borderRadius: '8px', display: 'flex',
                  alignItems: 'center', gap: '4px', boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
                }}>
                  <Star size={12} style={{ color: '#FFC400', fill: '#FFC400' }} />
                  <span style={{ fontSize: '12px', fontWeight: 700, color: '#2F3A40' }}>{vendor.rating}</span>
                </div>
              </div>
              
              <div style={{ padding: '4px 8px 8px' }}>
                <h4 style={{
                  fontWeight: 700, fontSize: '16px', color: '#2F3A40', marginBottom: '8px',
                  whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis'
                }}>
                  {vendor.store_name}
                </h4>
                
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '4px', opacity: 0.7, fontSize: '12px', fontWeight: 600, color: '#2F3A40' }}>
                    <Clock size={13} style={{ color: '#00A8E8' }} /> 
                    {vendor.time}
                  </div>
                  {vendor.address && (
                     <div style={{ display: 'flex', alignItems: 'center', gap: '4px', opacity: 0.5, fontSize: '11px', fontWeight: 600, color: '#2F3A40' }}>
                       <MapPin size={12} /> {vendor.address.split(',')[0]}
                     </div>
                  )}
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
};

export default VendorsPage;
