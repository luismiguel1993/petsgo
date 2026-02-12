import React from 'react';
import { Link } from 'react-router-dom';
import { Mail, Phone, MapPin, Instagram, Facebook, Linkedin, Twitter } from 'lucide-react';
import logoBlanco from '../assets/Huella y nombre_1.svg';

const Footer = () => {
  return (
    <footer className="bg-[#1a1f23] text-white">
      
      {/* Main Footer */}
      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '80px 32px' }}>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-12 lg:gap-16">
          
          {/* Brand Column */}
          <div className="sm:col-span-2 lg:col-span-1">
            <div className="mb-6">
              <img src={logoBlanco} alt="PetsGo" className="h-12" />
            </div>
            <p className="text-gray-400 leading-relaxed mb-6">
              El marketplace más grande de Chile para mascotas. Conectamos tiendas locales con despacho ultra rápido garantizado.
            </p>
            {/* Social Icons */}
            <div className="flex gap-3">
              <a href="https://www.facebook.com/petsgo.cl" target="_blank" rel="noopener noreferrer" className="w-11 h-11 bg-white/10 rounded-lg flex items-center justify-center hover:bg-[#00A8E8] transition-all hover:scale-110">
                <Facebook size={20} />
              </a>
              <a href="https://www.instagram.com/petsgo.cl" target="_blank" rel="noopener noreferrer" className="w-11 h-11 bg-white/10 rounded-lg flex items-center justify-center hover:bg-[#00A8E8] transition-all hover:scale-110">
                <Instagram size={20} />
              </a>
              <a href="https://www.linkedin.com/company/petsgo-cl" target="_blank" rel="noopener noreferrer" className="w-11 h-11 bg-white/10 rounded-lg flex items-center justify-center hover:bg-[#00A8E8] transition-all hover:scale-110">
                <Linkedin size={20} />
              </a>
              <a href="https://x.com/petsgo_cl" target="_blank" rel="noopener noreferrer" className="w-11 h-11 bg-white/10 rounded-lg flex items-center justify-center hover:bg-[#00A8E8] transition-all hover:scale-110">
                <Twitter size={20} />
              </a>
            </div>
          </div>

          {/* Marketplace Column */}
          <div>
            <h4 className="text-[#00A8E8] font-bold text-sm uppercase tracking-wider mb-6">
              Marketplace
            </h4>
            <ul className="space-y-4">
              <li>
                <Link to="/" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Productos
                </Link>
              </li>
              <li>
                <Link to="/tiendas" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Tiendas
                </Link>
              </li>
              <li>
                <Link to="/planes" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Planes
                </Link>
              </li>
              <li>
                <Link to="/tiendas" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Ofertas
                </Link>
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
                <Link to="/centro-de-ayuda" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Centro de Ayuda
                </Link>
              </li>
              <li>
                <Link to="/politica-de-envios" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Política de Envíos
                </Link>
              </li>
              <li>
                <Link to="/terminos-y-condiciones" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Términos y Condiciones
                </Link>
              </li>
              <li>
                <Link to="/politica-de-privacidad" className="text-gray-400 hover:text-white hover:translate-x-1 transition-all inline-block no-underline">
                  Privacidad
                </Link>
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
                <span>contacto@petsgo.cl</span>
              </li>
              <li className="flex items-center gap-3 text-gray-400">
                <Phone size={18} className="text-[#00A8E8] shrink-0" /> 
                <span>+56 2 2345 6789</span>
              </li>
              <li className="flex items-center gap-3 text-gray-400">
                <MapPin size={18} className="text-[#00A8E8] shrink-0" /> 
                <span>Santiago, Chile</span>
              </li>
            </ul>
            
            {/* CTA Button */}
            <a 
              href="https://wa.me/56223456789" 
              target="_blank" 
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 mt-6 px-6 py-3 bg-[#00A8E8] text-white font-semibold rounded-lg hover:bg-[#FFC400] hover:text-gray-900 transition-all no-underline"
            >
              💬 Contáctanos
            </a>
          </div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="border-t border-white/10">
        <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 32px' }}>
          <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
            <p className="text-gray-500 text-sm">
              © {new Date().getFullYear()} PetsGo Marketplace. Todos los derechos reservados.
            </p>
            <div className="flex items-center gap-6 text-sm">
              <Link to="/politica-de-privacidad" className="text-gray-500 hover:text-white transition-colors no-underline">Política de Privacidad</Link>
              <span className="text-gray-600">|</span>
              <Link to="/terminos-y-condiciones" className="text-gray-500 hover:text-white transition-colors no-underline">Términos de Servicio</Link>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
