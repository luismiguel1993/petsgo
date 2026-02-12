import React, { createContext, useContext, useState, useEffect } from 'react';
import { getPublicSettings } from '../services/api';

const SiteContext = createContext(null);

// Defaults match the backend pg_defaults + hardcoded content
const DEFAULTS = {
  company_name: 'PetsGo',
  company_tagline: 'Marketplace de mascotas',
  company_address: 'Santiago, Chile',
  company_phone: '+56 9 1234 5678',
  company_email: 'contacto@petsgo.cl',
  company_website: 'https://petsgo.cl',
  footer_description: 'El marketplace más grande de Chile para mascotas. Conectamos tiendas locales con despacho ultra rápido garantizado.',
  social_instagram: 'https://www.instagram.com/petsgo.cl',
  social_facebook: 'https://www.facebook.com/petsgo.cl',
  social_linkedin: 'https://www.linkedin.com/company/petsgo-cl',
  social_twitter: 'https://x.com/petsgo_cl',
  social_whatsapp: '+56912345678',
  free_shipping_min: 39990,
  plan_annual_free_months: 2,
  faqs: [],
};

export const SiteProvider = ({ children }) => {
  const [settings, setSettings] = useState(DEFAULTS);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await getPublicSettings();
        const data = res.data || {};
        setSettings((prev) => ({ ...prev, ...data }));
      } catch (e) {
        console.error('Error loading public settings:', e);
      } finally {
        setLoaded(true);
      }
    })();
  }, []);

  return (
    <SiteContext.Provider value={{ ...settings, loaded }}>
      {children}
    </SiteContext.Provider>
  );
};

export const useSite = () => {
  const ctx = useContext(SiteContext);
  if (!ctx) throw new Error('useSite must be used within SiteProvider');
  return ctx;
};

export default SiteContext;
