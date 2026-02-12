/**
 * Chile utilities: Regiones, Comunas, RUT validation, phone formatting,
 * name sanitization, and SQL injection protection.
 * Single source of truth for all registration & profile forms.
 */

/* ═══════════════════════════════════════════════
   NAME SANITIZATION — letters only (+ spaces, accents, hyphens, ñ)
═══════════════════════════════════════════════ */

/** Strip anything that is not a letter, space, accent, hyphen or apostrophe */
export const sanitizeName = (value) =>
  value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]/g, '');

/** Validate: at least 2 letters, no numbers/specials */
export const isValidName = (name) =>
  /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'-]{2,}$/.test(name.trim());

/* ═══════════════════════════════════════════════
   SQL INJECTION PROTECTION  —  client-side guard
   Detects common SQL injection patterns and strips them.
   This is a DEFENSE-IN-DEPTH layer; the backend also validates.
═══════════════════════════════════════════════ */

const SQL_PATTERNS = /(\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|EXEC|UNION|TRUNCATE|DECLARE|CAST|CONVERT|INTO|FROM|WHERE|OR|AND)\b\s+(ALL|TABLE|DATABASE|INTO|FROM|SET|VALUES|HAVING|LIKE|BETWEEN|EXISTS|TOP|COLUMN|INDEX|VIEW|PROCEDURE|FUNCTION|TRIGGER|SCHEMA|GRANT|REVOKE)?|--|;[\s]*$|\/\*|\*\/|xp_|sp_|0x[0-9a-fA-F]+|\bCHAR\s*\(|\bCONCAT\s*\(|'\s*(OR|AND)\s+'|'\s*=\s*'|INFORMATION_SCHEMA|SLEEP\s*\(|BENCHMARK\s*\(|LOAD_FILE\s*\(|OUTFILE\b)/gi;

/** Returns true if the value looks like a SQL injection attempt */
export const hasSqlInjection = (value) => {
  if (!value || typeof value !== 'string') return false;
  return SQL_PATTERNS.test(value);
};

/** Strip dangerous SQL tokens from a string */
export const sanitizeInput = (value) => {
  if (!value || typeof value !== 'string') return value;
  // Remove null bytes
  let v = value.replace(/\0/g, '');
  // Remove dangerous patterns
  v = v.replace(SQL_PATTERNS, '');
  // Remove stray single-quotes used for injection
  v = v.replace(/[']{2,}/g, "'");
  return v.trim();
};

/** Validate all form fields for SQL injection. Returns error message or null. */
export const checkFormForSqlInjection = (formObj) => {
  for (const [key, val] of Object.entries(formObj)) {
    if (typeof val === 'string' && hasSqlInjection(val)) {
      return `El campo contiene caracteres no permitidos. Por favor revisa tus datos.`;
    }
  }
  return null;
};

/* ═══════════════════════════════════════════════
   REGIONES Y COMUNAS DE CHILE
═══════════════════════════════════════════════ */
export const REGIONES_COMUNAS = {
  'Arica y Parinacota': ['Arica','Camarones','General Lagos','Putre'],
  'Tarapacá': ['Alto Hospicio','Camina','Colchane','Huara','Iquique','Pica','Pozo Almonte'],
  'Antofagasta': ['Antofagasta','Calama','María Elena','Mejillones','Ollagüe','San Pedro de Atacama','Sierra Gorda','Taltal','Tocopilla'],
  'Atacama': ['Caldera','Chañaral','Copiapó','Diego de Almagro','Freirina','Huasco','Tierra Amarilla','Vallenar'],
  'Coquimbo': ['Andacollo','Canela','Combarbalá','Coquimbo','Illapel','La Higuera','La Serena','Los Vilos','Monte Patria','Ovalle','Paihuano','Punitaqui','Río Hurtado','Salamanca','Vicuña'],
  'Valparaíso': ['Algarrobo','Cabildo','Calera','Cartagena','Casablanca','Catemu','Concón','El Quisco','El Tabo','Hijuelas','Isla de Pascua','Juan Fernández','La Cruz','La Ligua','Limache','Llaillay','Los Andes','Nogales','Olmué','Panquehue','Papudo','Petorca','Puchuncaví','Putaendo','Quillota','Quilpué','Quintero','Rinconada','San Antonio','San Esteban','San Felipe','Santa María','Santo Domingo','Valparaíso','Villa Alemana','Viña del Mar','Zapallar'],
  'Metropolitana': ['Alhué','Buin','Calera de Tango','Cerrillos','Cerro Navia','Colina','Conchalí','Curacaví','El Bosque','El Monte','Estación Central','Huechuraba','Independencia','Isla de Maipo','La Cisterna','La Florida','La Granja','La Pintana','La Reina','Lampa','Las Condes','Lo Barnechea','Lo Espejo','Lo Prado','Macul','Maipú','María Pinto','Melipilla','Ñuñoa','Padre Hurtado','Paine','Pedro Aguirre Cerda','Peñaflor','Peñalolén','Pirque','Providencia','Pudahuel','Puente Alto','Quilicura','Quinta Normal','Recoleta','Renca','San Bernardo','San Joaquín','San José de Maipo','San Miguel','San Pedro','San Ramón','Santiago','Talagante','Tiltil','Vitacura'],
  "O'Higgins": ['Chépica','Chimbarongo','Codegua','Coinco','Coltauco','Doñihue','Graneros','La Estrella','Las Cabras','Litueche','Lolol','Machalí','Malloa','Marchihue','Mostazal','Nancagua','Navidad','Olivar','Palmilla','Paredones','Peralillo','Peumo','Pichidegua','Pichilemu','Placilla','Pumanque','Quinta de Tilcoco','Rancagua','Rengo','Requínoa','San Fernando','San Vicente','Santa Cruz'],
  'Maule': ['Cauquenes','Chanco','Colbún','Constitución','Curepto','Curicó','Empedrado','Hualañé','Licantén','Linares','Longaví','Maule','Molina','Parral','Pelarco','Pelluhue','Pencahue','Rauco','Retiro','Río Claro','Romeral','Sagrada Familia','San Clemente','San Javier','San Rafael','Talca','Teno','Vichuquén','Villa Alegre','Yerbas Buenas'],
  'Ñuble': ['Bulnes','Chillán','Chillán Viejo','Cobquecura','Coelemu','Coihueco','El Carmen','Ninhue','Ñiquén','Pemuco','Pinto','Portezuelo','Quillón','Quirihue','Ránquil','San Carlos','San Fabián','San Ignacio','San Nicolás','Treguaco','Yungay'],
  'Biobío': ['Alto Biobío','Antuco','Arauco','Cabrero','Cañete','Chiguayante','Concepción','Contulmo','Coronel','Curanilahue','Florida','Hualpén','Hualqui','Laja','Lebu','Los Álamos','Los Ángeles','Lota','Mulchén','Nacimiento','Negrete','Penco','Quilaco','Quilleco','San Pedro de la Paz','San Rosendo','Santa Bárbara','Santa Juana','Talcahuano','Tirúa','Tomé','Tucapel','Yumbel'],
  'Araucanía': ['Angol','Carahue','Cholchol','Collipulli','Cunco','Curacautín','Curarrehue','Ercilla','Freire','Galvarino','Gorbea','Lautaro','Loncoche','Lonquimay','Los Sauces','Lumaco','Melipeuco','Nueva Imperial','Padre Las Casas','Perquenco','Pitrufquén','Pucón','Purén','Renaico','Saavedra','Temuco','Teodoro Schmidt','Toltén','Traiguén','Victoria','Vilcún','Villarrica'],
  'Los Ríos': ['Corral','Futrono','La Unión','Lago Ranco','Lanco','Los Lagos','Máfil','Mariquina','Paillaco','Panguipulli','Río Bueno','Valdivia'],
  'Los Lagos': ['Ancud','Calbuco','Castro','Chaitén','Chonchi','Cochamó','Curaco de Vélez','Dalcahue','Fresia','Frutillar','Futaleufú','Hualaihué','Llanquihue','Los Muermos','Maullín','Osorno','Palena','Puerto Montt','Puerto Octay','Puerto Varas','Puqueldón','Purranque','Puyehue','Queilén','Quellón','Quemchi','Quinchao','Río Negro','San Juan de la Costa','San Pablo'],
  'Aysén': ['Aysén','Chile Chico','Cisnes','Cochrane','Coyhaique','Guaitecas','Lago Verde',"O'Higgins",'Río Ibáñez','Tortel'],
  'Magallanes': ['Antártica','Cabo de Hornos','Laguna Blanca','Natales','Porvenir','Primavera','Punta Arenas','Río Verde','San Gregorio','Timaukel','Torres del Paine'],
};

export const REGIONES = Object.keys(REGIONES_COMUNAS);

export const getComunas = (region) => REGIONES_COMUNAS[region] || [];

/* ═══════════════════════════════════════════════
   RUT VALIDATION & FORMATTING
═══════════════════════════════════════════════ */
export const validateRut = (rut) => {
  const clean = rut.replace(/[^0-9kK]/g, '').toUpperCase();
  if (clean.length < 8 || clean.length > 9) return false;
  const body = clean.slice(0, -1);
  const dv = clean.slice(-1);
  let sum = 0, mul = 2;
  for (let i = body.length - 1; i >= 0; i--) {
    sum += parseInt(body[i]) * mul;
    mul = mul === 7 ? 2 : mul + 1;
  }
  let expected = 11 - (sum % 11);
  if (expected === 11) expected = '0';
  else if (expected === 10) expected = 'K';
  else expected = String(expected);
  return dv === expected;
};

export const formatRut = (value) => {
  const clean = value.replace(/[^0-9kK]/g, '');
  if (clean.length <= 1) return clean;
  const dv = clean.slice(-1);
  const body = clean.slice(0, -1);
  return body.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
};

/* ═══════════════════════════════════════════════
   PHONE FORMATTING  —  prefix +569 is fixed,
   user only types the remaining 8 digits.
═══════════════════════════════════════════════ */

/** Keep only digits, max 8 chars (used as onChange handler for phone inputs) */
export const formatPhoneDigits = (value) => value.replace(/\D/g, '').slice(0, 8);

/** Exactly 8 digits = valid (prefix +569 added separately) */
export const isValidPhoneDigits = (digits) => /^\d{8}$/.test(digits);

/** Build full phone from 8-digit input */
export const buildFullPhone = (digits) => `+569${digits}`;

/** Extract the 8 digits from a stored full phone (+569XXXXXXXX → XXXXXXXX) */
export const extractPhoneDigits = (phone) => {
  if (!phone) return '';
  const clean = phone.replace(/\D/g, '');
  if (clean.length === 11 && clean.startsWith('569')) return clean.slice(3);
  if (clean.length === 9 && clean.startsWith('9')) return clean.slice(1);
  if (clean.length === 8) return clean;
  return clean.slice(-8);
};

/** Full-format validation (kept for backend compat) */
export const isValidPhone = (phone) => /^\+569\d{8}$/.test(phone);

/** Legacy formatter — still useful if raw full phone value arrives */
export const formatPhone = (value) => {
  let v = value.replace(/[^0-9+]/g, '');
  if (!v.startsWith('+56') && v.length > 0) {
    if (v.startsWith('56')) v = '+' + v;
    else if (v.startsWith('9')) v = '+56' + v;
    else if (!v.startsWith('+')) v = '+56' + v;
  }
  return v;
};
