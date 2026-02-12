/**
 * Chile utilities: Regiones, Comunas, RUT validation, phone formatting.
 * Single source of truth for all registration & profile forms.
 */

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
   PHONE FORMATTING
═══════════════════════════════════════════════ */
export const formatPhone = (value) => {
  let v = value.replace(/[^0-9+]/g, '');
  if (!v.startsWith('+56') && v.length > 0) {
    if (v.startsWith('56')) v = '+' + v;
    else if (v.startsWith('9')) v = '+56' + v;
    else if (!v.startsWith('+')) v = '+56' + v;
  }
  return v;
};

export const isValidPhone = (phone) => /^\+569\d{8}$/.test(phone);
