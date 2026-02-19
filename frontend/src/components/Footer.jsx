import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Mail, Phone, MapPin, Instagram, Facebook, Linkedin, Twitter } from 'lucide-react';
import logoBlanco from '../assets/Huella y nombre_1.svg';
import { useSite } from '../context/SiteContext';

/** Link que hace scroll al inicio de la página al navegar */
const ScrollLink = ({ to, children, ...props }) => {
  const navigate = useNavigate();
  return (
    <a
      href={to}
      {...props}
      onClick={(e) => {
        e.preventDefault();
        navigate(to);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }}
    >
      {children}
    </a>
  );
};

const Footer = () => {
  const site = useSite();

  const socials = [
    { url: site.social_facebook, icon: Facebook },
    { url: site.social_instagram, icon: Instagram },
    { url: site.social_linkedin, icon: Linkedin },
    { url: site.social_twitter, icon: Twitter },
  ].filter(s => s.url);

  const waNum = (site.social_whatsapp || '').replace(/[^0-9]/g, '');

  return (
    <footer className="bg-[#1a1f23] text-white">
      
      {/* Main Footer */}
      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '80px 32px' }}>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-16">
          
          {/* Brand Column */}
          <div className="sm:col-span-2 lg:col-span-1">
            <div className="mb-6">
              <img src={logoBlanco} alt={site.company_name} className="h-12" />
            </div>
            <p className="text-gray-400 leading-relaxed mb-6">
              {site.footer_description}
            </p>
            {/* Social Icons */}
            {socials.length > 0 && (
              <div className="flex gap-3">
                {socials.map(({ url, icon: Icon }, i) => (
                  <a key={i} href={url} target="_blank" rel="noopener noreferrer" className="w-11 h-11 bg-white/10 rounded-lg flex items-center justify-center hover:bg-[#00A8E8] transition-all hover:scale-110">
                    <Icon size={20} />
                  </a>
                ))}
              </div>
            )}
          </div>

          {/* Marketplace Column */}
          <div>
            <h4 className="text-[#00A8E8] font-bold text-sm uppercase tracking-wider mb-6">
              Marketplace
            </h4>
            <ul className="space-y-4">
              <li>
                <ScrollLink to="/" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Productos
                </ScrollLink>
              </li>
              <li>
                <ScrollLink to="/tiendas" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Tiendas
                </ScrollLink>
              </li>
              <li>
                <ScrollLink to="/planes" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Planes
                </ScrollLink>
              </li>
              <li>
                <ScrollLink to="/tiendas" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Ofertas
                </ScrollLink>
              </li>
            </ul>
          </div>

          {/* Soporte Column */}
          <div>
            <h4 className="text-[#00A8E8] font-bold text-sm uppercase tracking-wider mb-6">
              Soporte
            </h4>
            <ul className="space-y-4">
              <li>
                <ScrollLink to="/centro-de-ayuda" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Centro de Ayuda
                </ScrollLink>
              </li>
              <li>
                <ScrollLink to="/politica-de-envios" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Política de Envíos
                </ScrollLink>
              </li>
              <li>
                <ScrollLink to="/terminos-y-condiciones" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Términos y Condiciones
                </ScrollLink>
              </li>
              <li>
                <ScrollLink to="/politica-de-privacidad" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Privacidad
                </ScrollLink>
              </li>
            </ul>
          </div>

          {/* Contacto Column */}
          <div>
            <h4 className="text-[#00A8E8] font-bold text-sm uppercase tracking-wider mb-6">
              Contacto
            </h4>
            <ul className="space-y-4">
              <li className="flex items-center gap-3 text-gray-400">
                <Mail size={18} className="text-[#00A8E8] shrink-0" /> 
                <span>{site.company_email}</span>
              </li>
              <li className="flex items-center gap-3 text-gray-400">
                <Phone size={18} className="text-[#00A8E8] shrink-0" /> 
                <span>{site.company_phone}</span>
              </li>
              <li className="flex items-center gap-3 text-gray-400">
                <MapPin size={18} className="text-[#00A8E8] shrink-0" /> 
                <span>{site.company_address}</span>
              </li>
            </ul>
            
            {/* CTA Button */}
            {waNum && (
              <a 
                href={`https://wa.me/${waNum}`}
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 mt-6 px-6 py-3 bg-[#00A8E8] text-white font-semibold rounded-lg hover:bg-[#FFC400] hover:text-gray-900 transition-all no-underline"
              >
                💬 Contáctanos
              </a>
            )}
          </div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="border-t border-white/10">
        <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 32px' }}>
          <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
            <p className="text-gray-500 text-sm">
              © {new Date().getFullYear()} {site.company_name} Marketplace. Todos los derechos reservados.
            </p>
            <div className="flex items-center gap-6 text-sm">
              <ScrollLink to="/politica-de-privacidad" className="text-gray-500 hover:text-white transition-colors no-underline">Política de Privacidad</ScrollLink>
              <span className="text-gray-600">|</span>
              <ScrollLink to="/terminos-y-condiciones" className="text-gray-500 hover:text-white transition-colors no-underline">Términos de Servicio</ScrollLink>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
