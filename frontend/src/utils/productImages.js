/**
 * Mapeo centralizado de imágenes para productos y categorías.
 * Se usa cuando el producto no tiene image_url desde la BD.
 */

// ── Imágenes específicas por nombre de producto (keyword match) ──
const PRODUCT_IMAGE_MAP = {
  // Alimentos perro
  'royal canin':         'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=600&auto=format&fit=crop&q=80',
  'pro plan perro':      'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?w=600&auto=format&fit=crop&q=80',
  'pro plan cachorro':   'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
  'hills':               'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=600&auto=format&fit=crop&q=80',
  'pro plan senior':     'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
  'eukanuba':            'https://images.unsplash.com/photo-1600804340584-c7db2eacf0bf?w=600&auto=format&fit=crop&q=80',
  'taste of the wild':   'https://images.unsplash.com/photo-1623387641168-d9803ddd3f35?w=600&auto=format&fit=crop&q=80',
  'barf':                'https://images.unsplash.com/photo-1585584114963-503344a119b0?w=600&auto=format&fit=crop&q=80',

  // Alimentos gato
  'whiskas':             'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=600&auto=format&fit=crop&q=80',
  'pro plan gato':       'https://images.unsplash.com/photo-1615497001839-b0a0eac3274c?w=600&auto=format&fit=crop&q=80',
  'felix':               'https://images.unsplash.com/photo-1495360010541-f48722b34f7d?w=600&auto=format&fit=crop&q=80',

  // Camas y descanso
  'cama ortop':          'https://images.unsplash.com/photo-1591946614720-90a587da4a36?w=600&auto=format&fit=crop&q=80',
  'cama elevada':        'https://images.unsplash.com/photo-1585664811087-47f65abbad64?w=600&auto=format&fit=crop&q=80',
  'colchón':             'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
  'iglú':                'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=600&auto=format&fit=crop&q=80',
  'manta':               'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
  'cojín donut':         'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',

  // Collares y paseo
  'collar led':          'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=600&auto=format&fit=crop&q=80',
  'collar anti':         'https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&auto=format&fit=crop&q=80',
  'collar con cascabel': 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=600&auto=format&fit=crop&q=80',
  'arnés':               'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
  'arnes':               'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
  'correa':              'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
  'bolso':               'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
  'bebedero portatil':   'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=600&auto=format&fit=crop&q=80',
  'luz led':             'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=600&auto=format&fit=crop&q=80',
  'bolsas bio':          'https://images.unsplash.com/photo-1585584114963-503344a119b0?w=600&auto=format&fit=crop&q=80',

  // Juguetes
  'pelota':              'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=600&auto=format&fit=crop&q=80',
  'kong':                'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=600&auto=format&fit=crop&q=80',
  'juguete interactivo': 'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
  'juguete laser':       'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=600&auto=format&fit=crop&q=80',
  'juguete ratón':       'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&auto=format&fit=crop&q=80',
  'juguete puzzle':      'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
  'ratón electrónico':   'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',

  // Rascadores
  'rascador':            'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=600&auto=format&fit=crop&q=80',

  // Higiene
  'shampoo':             'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
  'cepillo':             'https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?w=600&auto=format&fit=crop&q=80',
  'furminator':          'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
  'toallitas':           'https://images.unsplash.com/photo-1581888227599-779811939961?w=600&auto=format&fit=crop&q=80',
  'cortauñas':           'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=600&auto=format&fit=crop&q=80',
  'spray perfume':       'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
  'kit spa':             'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?w=600&auto=format&fit=crop&q=80',

  // Farmacia
  'antipulgas':          'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=600&auto=format&fit=crop&q=80',
  'frontline':           'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=600&auto=format&fit=crop&q=80',
  'bravecto':            'https://images.unsplash.com/photo-1612531386530-97286d97c2d2?w=600&auto=format&fit=crop&q=80',
  'pipeta':              'https://images.unsplash.com/photo-1612531386530-97286d97c2d2?w=600&auto=format&fit=crop&q=80',
  'suplemento':          'https://images.unsplash.com/photo-1585584114963-503344a119b0?w=600&auto=format&fit=crop&q=80',
  'vitaminas':           'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
  'gel dental':          'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
  'collar isabelino':    'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
  'revolution':          'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',
  'probióticos':         'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',

  // Snacks
  'snack':               'https://images.unsplash.com/photo-1582798358481-d199fb7347bb?w=600&auto=format&fit=crop&q=80',
  'dentastix':           'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
  'hueso':               'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
  'pollo deshidratado':  'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
  'galletas':            'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
  'jerky':               'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
  'catnip':              'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=600&auto=format&fit=crop&q=80',

  // Ropa
  'chaleco':             'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=600&auto=format&fit=crop&q=80',
  'impermeable':         'https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&auto=format&fit=crop&q=80',
  'pijama':              'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=600&auto=format&fit=crop&q=80',
  'botas':               'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
  'suéter':              'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
  'disfraz':             'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',

  // Accesorios / Tech
  'comedero':            'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?w=600&auto=format&fit=crop&q=80',
  'bebedero fuente':     'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
  'dispensador':         'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
  'plato':               'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=600&auto=format&fit=crop&q=80',
  'gps':                 'https://images.unsplash.com/photo-1561948955-570b270e7c36?w=600&auto=format&fit=crop&q=80',
  'cámara':              'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=600&auto=format&fit=crop&q=80',
  'puerta para mascotas':'https://images.unsplash.com/photo-1600804340584-c7db2eacf0bf?w=600&auto=format&fit=crop&q=80',
  'transportador':       'https://images.unsplash.com/photo-1561948955-570b270e7c36?w=600&auto=format&fit=crop&q=80',
  'mochila astronauta':  'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',
  'arena':               'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=600&auto=format&fit=crop&q=80',
};

// ── Pools de imágenes por categoría (para diversificar cuando no hay match por nombre) ──
const CATEGORY_IMAGE_POOLS = {
  'Alimento': [
    'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1600804340584-c7db2eacf0bf?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1623387641168-d9803ddd3f35?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1585584114963-503344a119b0?w=600&auto=format&fit=crop&q=80',
  ],
  'Alimento Perros': [
    'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?w=600&auto=format&fit=crop&q=80',
  ],
  'Alimento Gatos': [
    'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1615497001839-b0a0eac3274c?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1495360010541-f48722b34f7d?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',
  ],
  'Accesorios': [
    'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
  ],
  'Juguetes': [
    'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&auto=format&fit=crop&q=80',
  ],
  'Higiene': [
    'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1581888227599-779811939961?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
  ],
  'Farmacia': [
    'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1612531386530-97286d97c2d2?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1585584114963-503344a119b0?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',
  ],
  'Snacks': [
    'https://images.unsplash.com/photo-1582798358481-d199fb7347bb?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
  ],
  'Camas': [
    'https://images.unsplash.com/photo-1591946614720-90a587da4a36?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1544568100-847a948585b9?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1585664811087-47f65abbad64?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
  ],
  'Paseo': [
    'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&auto=format&fit=crop&q=80',
  ],
  'Ropa': [
    'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
  ],
  'Transporte': [
    'https://images.unsplash.com/photo-1561948955-570b270e7c36?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?w=600&auto=format&fit=crop&q=80',
  ],
  'Tecnologia': [
    'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1561948955-570b270e7c36?w=600&auto=format&fit=crop&q=80',
  ],
  'Perros': [
    'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=600&auto=format&fit=crop&q=80',
  ],
  'Gatos': [
    'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1495360010541-f48722b34f7d?w=600&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80',
  ],
};

// Imagen fallback general
const DEFAULT_IMAGE = 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80';

/**
 * Simple hash from string → number (for deterministic image selection)
 */
function hashString(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) - hash) + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

/**
 * Obtiene la imagen adecuada para un producto.
 * Prioridad:
 *   1. image_url del producto (si viene de la BD)
 *   2. Match por nombre de producto (keyword)
 *   3. Imagen del pool de la categoría (diversificada por hash del nombre + id)
 *   4. Fallback general
 *
 * @param {Object} product - Objeto producto con campos product_name/name, category, image_url, id
 * @returns {string} URL de imagen
 */
export function getProductImage(product) {
  // 1. Si tiene imagen real desde la BD
  if (product.image_url) return product.image_url;
  if (product.image && !product.image.includes('unsplash.com')) return product.image;

  const name = (product.product_name || product.name || '').toLowerCase();

  // 2. Buscar match por keyword en el nombre
  for (const [keyword, url] of Object.entries(PRODUCT_IMAGE_MAP)) {
    if (name.includes(keyword)) return url;
  }

  // 3. Usar pool de categoría con hash para diversificar
  const category = product.category || '';
  const pool = CATEGORY_IMAGE_POOLS[category];
  if (pool && pool.length > 0) {
    const uniqueKey = `${product.id || 0}-${name}`;
    const idx = hashString(uniqueKey) % pool.length;
    return pool[idx];
  }

  // 4. Fallback
  return DEFAULT_IMAGE;
}

/**
 * Imagen de categoría para headers / banners.
 */
export const CATEGORY_BANNER_IMAGES = {
  'Alimento':         'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=1200&auto=format&fit=crop&q=80',
  'Alimento Perros':  'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?w=1200&auto=format&fit=crop&q=80',
  'Alimento Gatos':   'https://images.unsplash.com/photo-1615497001839-b0a0eac3274c?w=1200&auto=format&fit=crop&q=80',
  'Accesorios':       'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=1200&auto=format&fit=crop&q=80',
  'Juguetes':         'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?w=1200&auto=format&fit=crop&q=80',
  'Higiene':          'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?w=1200&auto=format&fit=crop&q=80',
  'Farmacia':         'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=1200&auto=format&fit=crop&q=80',
  'Snacks':           'https://images.unsplash.com/photo-1582798358481-d199fb7347bb?w=1200&auto=format&fit=crop&q=80',
  'Camas':            'https://images.unsplash.com/photo-1591946614720-90a587da4a36?w=1200&auto=format&fit=crop&q=80',
  'Paseo':            'https://images.unsplash.com/photo-1560807707-8cc77767d783?w=1200&auto=format&fit=crop&q=80',
  'Ropa':             'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=1200&auto=format&fit=crop&q=80',
  'Transporte':       'https://images.unsplash.com/photo-1561948955-570b270e7c36?w=1200&auto=format&fit=crop&q=80',
  'Tecnologia':       'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?w=1200&auto=format&fit=crop&q=80',
  'Perros':           'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=1200&auto=format&fit=crop&q=80',
  'Gatos':            'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=1200&auto=format&fit=crop&q=80',
  'Ofertas':          'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=1200&auto=format&fit=crop&q=80',
  'Nuevos':           'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=1200&auto=format&fit=crop&q=80',
  'Todos':            'https://images.unsplash.com/photo-1544568100-847a948585b9?w=1200&auto=format&fit=crop&q=80',
  'default':          'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=1200&auto=format&fit=crop&q=80',
};

/**
 * Obtiene imagen de fallback para una categoría (cuando el producto no la tiene).
 */
export function getCategoryImage(categoryName) {
  return CATEGORY_BANNER_IMAGES[categoryName] || CATEGORY_BANNER_IMAGES['default'];
}
