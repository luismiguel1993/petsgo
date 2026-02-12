import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { FileText, Shield, Truck, ArrowLeft, Loader2 } from 'lucide-react';
import { getLegalPage } from '../services/api';

const PAGE_CONFIG = {
  'terminos-y-condiciones': {
    icon: FileText,
    color: '#00A8E8',
    bg: 'linear-gradient(135deg, #00A8E8, #0077B6)',
    defaultContent: `
      <h2>1. Aceptación de los Términos</h2>
      <p>Al acceder y utilizar PetsGo, aceptas estar sujeto a estos términos y condiciones de uso. Si no estás de acuerdo con alguno de estos términos, no utilices nuestro marketplace.</p>

      <h2>2. Descripción del Servicio</h2>
      <p>PetsGo es un marketplace en línea que conecta tiendas de mascotas con clientes, facilitando la compra y despacho de productos para mascotas en Chile. PetsGo actúa como intermediario entre compradores y vendedores.</p>

      <h2>3. Registro y Cuentas</h2>
      <p>Para utilizar ciertas funcionalidades, debes crear una cuenta proporcionando información veraz y actualizada. Eres responsable de mantener la confidencialidad de tu contraseña y de todas las actividades realizadas bajo tu cuenta.</p>

      <h2>4. Compras y Pagos</h2>
      <p>Los precios de los productos son establecidos por cada tienda vendedora. PetsGo se reserva el derecho de aplicar cargos por servicio o despacho según las políticas vigentes. Todos los pagos se procesan de manera segura a través de los medios de pago habilitados.</p>

      <h2>5. Despacho</h2>
      <p>Los tiempos de despacho dependerán de la disponibilidad del producto, la ubicación del cliente y la cobertura del servicio. PetsGo hará su mejor esfuerzo para cumplir con los plazos estimados pero no garantiza tiempos exactos de entrega.</p>

      <h2>6. Devoluciones y Reembolsos</h2>
      <p>Los clientes pueden solicitar devoluciones dentro de los plazos establecidos por la ley de protección al consumidor chilena. Los productos deben devolverse en su estado original y con su embalaje.</p>

      <h2>7. Responsabilidad</h2>
      <p>PetsGo no se hace responsable por la calidad de los productos vendidos por las tiendas adheridas, actuando únicamente como plataforma intermediaria. Cada tienda es responsable de sus productos y servicios.</p>

      <h2>8. Propiedad Intelectual</h2>
      <p>Todo el contenido del marketplace, incluyendo logos, diseños y textos, son propiedad de PetsGo SpA y están protegidos por las leyes de propiedad intelectual de Chile.</p>

      <h2>9. Modificaciones</h2>
      <p>PetsGo se reserva el derecho de modificar estos términos en cualquier momento. Las modificaciones entrarán en vigencia al ser publicadas en el sitio web. El uso continuado del servicio después de cualquier modificación constituye aceptación de los nuevos términos.</p>

      <h2>10. Legislación Aplicable</h2>
      <p>Estos términos se rigen por las leyes de la República de Chile. Cualquier controversia será resuelta por los tribunales ordinarios de justicia con sede en Santiago de Chile.</p>
    `,
  },
  'politica-de-privacidad': {
    icon: Shield,
    color: '#22C55E',
    bg: 'linear-gradient(135deg, #22C55E, #16A34A)',
    defaultContent: `
      <h2>1. Información que Recopilamos</h2>
      <p>Recopilamos la información personal que nos proporcionas al registrarte, como nombre, correo electrónico, teléfono, dirección de despacho y datos de pago. También recopilamos información de uso del sitio de manera automática.</p>

      <h2>2. Uso de la Información</h2>
      <p>Tu información personal se utiliza para:</p>
      <ul>
        <li>Procesar y gestionar tus pedidos</li>
        <li>Comunicarnos contigo sobre el estado de tus compras</li>
        <li>Mejorar nuestros servicios y la experiencia de usuario</li>
        <li>Enviar notificaciones relevantes sobre tu cuenta</li>
        <li>Cumplir con obligaciones legales y tributarias</li>
      </ul>

      <h2>3. Compartir Información</h2>
      <p>Compartimos tu información solo con:</p>
      <ul>
        <li>Tiendas vendedoras (nombre y dirección de despacho para cumplir tu pedido)</li>
        <li>Riders (dirección de despacho para la entrega)</li>
        <li>Procesadores de pago autorizados</li>
        <li>Autoridades competentes cuando sea requerido por ley</li>
      </ul>

      <h2>4. Protección de Datos</h2>
      <p>Implementamos medidas de seguridad técnicas y organizativas para proteger tu información personal contra acceso no autorizado, pérdida o destrucción. Utilizamos encriptación SSL para todas las comunicaciones y almacenamos los datos de manera segura.</p>

      <h2>5. Cookies</h2>
      <p>Utilizamos cookies y tecnologías similares para mejorar la experiencia de navegación, recordar tus preferencias y analizar el uso del sitio. Puedes configurar tu navegador para rechazar cookies, aunque esto podría afectar la funcionalidad del sitio.</p>

      <h2>6. Derechos del Usuario</h2>
      <p>De acuerdo con la Ley N° 19.628 sobre Protección de la Vida Privada de Chile, tienes derecho a:</p>
      <ul>
        <li>Acceder a tus datos personales</li>
        <li>Solicitar la rectificación de datos inexactos</li>
        <li>Solicitar la eliminación de tus datos</li>
        <li>Oponerte al tratamiento de tus datos para fines de marketing</li>
      </ul>

      <h2>7. Retención de Datos</h2>
      <p>Conservamos tu información personal mientras tu cuenta esté activa o según sea necesario para cumplir con obligaciones legales y tributarias. Puedes solicitar la eliminación de tu cuenta en cualquier momento.</p>

      <h2>8. Contacto</h2>
      <p>Para consultas sobre privacidad, puedes contactarnos a través de nuestro Centro de Ayuda o escribirnos a contacto@petsgo.cl.</p>

      <h2>9. Cambios en la Política</h2>
      <p>Podemos actualizar esta política periódicamente. Te notificaremos sobre cambios significativos a través de tu correo electrónico registrado o mediante un aviso en nuestro sitio web.</p>
    `,
  },
  'politica-de-envios': {
    icon: Truck,
    color: '#FFC400',
    bg: 'linear-gradient(135deg, #FFC400, #F59E0B)',
    defaultContent: `
      <h2>1. Cobertura de Despacho</h2>
      <p>PetsGo realiza despachos a domicilio dentro de las zonas de cobertura habilitadas. La disponibilidad de despacho depende de la ubicación de la tienda vendedora y la dirección del cliente.</p>

      <h2>2. Tipos de Despacho</h2>
      <p>Ofrecemos las siguientes modalidades de despacho:</p>
      <ul>
        <li><strong>Despacho Express:</strong> Entrega en el mismo día para pedidos realizados antes de las 14:00 hrs (sujeto a disponibilidad).</li>
        <li><strong>Despacho Estándar:</strong> Entrega en 1-3 días hábiles.</li>
        <li><strong>Retiro en Tienda:</strong> Puedes retirar tu pedido directamente en la tienda vendedora (cuando aplique).</li>
      </ul>

      <h2>3. Costos de Despacho</h2>
      <p>El costo de despacho se calcula según la distancia entre la tienda y tu dirección. Los pedidos que superen el monto mínimo establecido para despacho gratuito no tendrán cargo por envío. El monto mínimo puede variar y se muestra al momento de la compra.</p>

      <h2>4. Seguimiento del Pedido</h2>
      <p>Una vez despachado tu pedido, podrás hacer seguimiento en tiempo real desde la sección "Mis Pedidos" en tu cuenta. Recibirás notificaciones por correo electrónico sobre el estado de tu pedido.</p>

      <h2>5. Recepción del Pedido</h2>
      <p>Al momento de la entrega, el rider verificará que la dirección sea correcta. En caso de no encontrarse nadie en el domicilio, el rider intentará comunicarse contigo. Si no es posible realizar la entrega tras los intentos correspondientes, el pedido será devuelto a la tienda.</p>

      <h2>6. Problemas con el Despacho</h2>
      <p>Si tu pedido llega dañado, incompleto o con productos incorrectos, debes reportarlo dentro de las primeras 24 horas a través de un ticket de soporte. PetsGo gestionará la solución correspondiente (reenvío, reembolso o cambio) junto con la tienda vendedora.</p>

      <h2>7. Restricciones de Despacho</h2>
      <p>Algunos productos pueden tener restricciones de despacho según su naturaleza (productos frescos, refrigerados, de gran tamaño, etc.). Estas restricciones serán informadas en la ficha de cada producto.</p>

      <h2>8. Fuerza Mayor</h2>
      <p>PetsGo no será responsable por retrasos en la entrega ocasionados por eventos de fuerza mayor como desastres naturales, restricciones sanitarias, manifestaciones u otros eventos fuera de nuestro control.</p>
    `,
  },
};

const LegalPage = ({ slug }) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const config = PAGE_CONFIG[slug];

  useEffect(() => {
    setLoading(true);
    (async () => {
      try {
        const res = await getLegalPage(slug);
        setData(res.data);
      } catch (e) { /* use defaults */ }
      finally { setLoading(false); }
    })();
  }, [slug]);

  if (!config) return null;

  const Icon = config.icon;
  const title = data?.title || slug.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
  const htmlContent = data?.content || config.defaultContent;

  const st = {
    page: { maxWidth: '800px', margin: '0 auto', padding: '32px 16px', fontFamily: 'Poppins, sans-serif' },
  };

  return (
    <div style={st.page}>
      {/* Hero */}
      <div style={{
        background: config.bg, borderRadius: '20px',
        padding: '40px 32px', textAlign: 'center', color: '#fff', marginBottom: '28px',
      }}>
        <Icon size={40} style={{ margin: '0 auto 12px', opacity: 0.9 }} />
        <h1 style={{ fontSize: '26px', fontWeight: 900, marginBottom: '8px' }}>{title}</h1>
        <p style={{ fontSize: '13px', opacity: 0.8 }}>
          Última actualización: {new Date().toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' })}
        </p>
      </div>

      {/* Content */}
      {loading ? (
        <div style={{
          background: '#fff', borderRadius: '16px', padding: '64px 24px', textAlign: 'center',
          boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
        }}>
          <Loader2 size={32} color="#9ca3af" style={{ animation: 'spin 1s linear infinite', margin: '0 auto' }} />
          <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
        </div>
      ) : (
        <div style={{
          background: '#fff', borderRadius: '16px', padding: '36px 32px',
          boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
        }}>
          <div
            dangerouslySetInnerHTML={{ __html: htmlContent }}
            style={{ lineHeight: 1.8, fontSize: '14px', color: '#374151' }}
            className="legal-content"
          />
          <style>{`
            .legal-content h2 { font-size: 18px; font-weight: 800; color: #2F3A40; margin: 28px 0 12px; }
            .legal-content h3 { font-size: 16px; font-weight: 700; color: #2F3A40; margin: 20px 0 10px; }
            .legal-content p { margin-bottom: 12px; }
            .legal-content ul, .legal-content ol { padding-left: 24px; margin-bottom: 12px; }
            .legal-content li { margin-bottom: 6px; }
            .legal-content strong { color: #2F3A40; }
          `}</style>
        </div>
      )}

      {/* Back */}
      <div style={{ marginTop: '24px', textAlign: 'center' }}>
        <Link to="/centro-de-ayuda" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          fontSize: '13px', fontWeight: 700, color: '#9ca3af', textDecoration: 'none',
        }}>
          <ArrowLeft size={16} /> Volver al Centro de Ayuda
        </Link>
      </div>
    </div>
  );
};

export default LegalPage;
