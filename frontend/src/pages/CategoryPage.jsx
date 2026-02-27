import React, { useState, useEffect, useMemo } from 'react';
import { useParams, Link, useSearchParams } from 'react-router-dom';
import { ArrowLeft, Search, Store, Star, Clock, MapPin, Plus, Minus, PawPrint, Package, Filter } from 'lucide-react';
import { getProducts, getVendors, getCategories } from '../services/api';
import { useCart } from '../context/CartContext';
import { getProductImage as getSmartProductImage } from '../utils/productImages';

/* ── Fallback de categorías ──────────────────────────── */
const FALLBACK_CATEGORY_META = {
  Perros:     { emoji: '🐕', label: 'Perros',     desc: 'Todo para tu perro' },
  Gatos:      { emoji: '🐱', label: 'Gatos',      desc: 'Todo para tu gato' },
  Alimento:   { emoji: '🍖', label: 'Alimento',   desc: 'Seco y húmedo' },
  Snacks:     { emoji: '🦴', label: 'Snacks',     desc: 'Premios y dental' },
  Farmacia:   { emoji: '💊', label: 'Farmacia',   desc: 'Antiparasitarios y salud' },
  Accesorios: { emoji: '🎾', label: 'Accesorios', desc: 'Juguetes y más' },
  Higiene:    { emoji: '🧴', label: 'Higiene',    desc: 'Shampoo y aseo' },
  Camas:      { emoji: '🛏️', label: 'Camas',      desc: 'Descanso ideal' },
  Paseo:      { emoji: '🦮', label: 'Paseo',      desc: 'Correas y arneses' },
  Ropa:       { emoji: '🧥', label: 'Ropa',       desc: 'Abrigos y disfraces' },
  Ofertas:    { emoji: '🔥', label: 'Ofertas',    desc: 'Los mejores descuentos' },
  Nuevos:     { emoji: '✨', label: 'Nuevos',     desc: 'Recién llegados' },
  Todos:      { emoji: '🔍', label: 'Resultados de búsqueda', desc: 'Productos encontrados' },
};

/* ── Imágenes: usa el mapeo centralizado de utils/productImages.js ── */

/* ── Datos demo por categoría ────────────────────────── */
const DEMO_VENDORS = [
  { id: 901, store_name: 'PetShop Las Condes', address: 'Av. Las Condes 1234, Las Condes', rating: '4.8', logo_url: null },
  { id: 902, store_name: 'Mundo Animal Centro', address: 'Calle Morandé 567, Santiago Centro', rating: '4.5', logo_url: null },
  { id: 903, store_name: 'La Huella Store', address: 'Av. Providencia 890, Providencia', rating: '4.9', logo_url: null },
  { id: 904, store_name: 'Patitas Chile', address: 'Calle Merced 910, Santiago Centro', rating: '4.7', logo_url: null },
  { id: 905, store_name: 'Happy Pets Providencia', address: 'Av. Irarrázaval 2030, Ñuñoa', rating: '4.6', logo_url: null },
  { id: 906, store_name: 'Vet & Shop', address: 'Av. Vitacura 4520, Vitacura', rating: '4.9', logo_url: null },
];

const DEMO_PRODUCTS = {
  Perros: [
    { id: 9001, vendor_id: 901, product_name: 'Royal Canin Medium Adult 15kg', price: 54990, stock: 25, category: 'Perros', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 54990 },
    { id: 9002, vendor_id: 902, product_name: 'Collar Antiparasitario Perro Grande', price: 18990, stock: 40, category: 'Perros', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 16142 },
    { id: 9003, vendor_id: 903, product_name: 'Cama Ortopédica Premium XL', price: 45990, stock: 12, category: 'Perros', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 45990 },
    { id: 9004, vendor_id: 904, product_name: 'Juguete Kong Classic Large', price: 14990, stock: 55, category: 'Perros', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 14990 },
    { id: 9005, vendor_id: 905, product_name: 'Shampoo Hipoalergénico 500ml', price: 8990, stock: 80, category: 'Perros', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1585584114963-503344a119b0?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 8091 },
    { id: 9006, vendor_id: 906, product_name: 'Arnés Reflectante Ajustable', price: 22990, stock: 30, category: 'Perros', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 22990 },
    { id: 9007, vendor_id: 901, product_name: 'Snack Dental DentaStix x7', price: 5990, stock: 100, category: 'Perros', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 5990 },
    { id: 9008, vendor_id: 902, product_name: 'Chaleco Abrigador Talla M', price: 16990, stock: 20, category: 'Perros', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&q=80&w=800', discount_percent: 20, discount_active: true, final_price: 13592 },
  ],
  Gatos: [
    { id: 9010, vendor_id: 903, product_name: 'Royal Canin Indoor Cat 7.5kg', price: 42990, stock: 20, category: 'Gatos', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 42990 },
    { id: 9011, vendor_id: 904, product_name: 'Arena Sanitaria Premium 10L', price: 9990, stock: 60, category: 'Gatos', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 8991 },
    { id: 9012, vendor_id: 905, product_name: 'Rascador Torre 3 Niveles', price: 34990, stock: 8, category: 'Gatos', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 34990 },
    { id: 9013, vendor_id: 906, product_name: 'Juguete Ratón con Catnip', price: 4990, stock: 100, category: 'Gatos', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 4990 },
    { id: 9014, vendor_id: 901, product_name: 'Felix Sensations Sachets x12', price: 11990, stock: 45, category: 'Gatos', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1495360010541-f48722b34f7d?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 10192 },
    { id: 9015, vendor_id: 902, product_name: 'Cama Iglú Gato Suave', price: 19990, stock: 15, category: 'Gatos', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 19990 },
    { id: 9016, vendor_id: 903, product_name: 'Transportador Gato Rígido', price: 24990, stock: 10, category: 'Gatos', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1561948955-570b270e7c36?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 24990 },
    { id: 9017, vendor_id: 904, product_name: 'Collar con Cascabel Ajustable', price: 3990, stock: 70, category: 'Gatos', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 3990 },
  ],
  Alimento: [
    { id: 9020, vendor_id: 901, product_name: 'Royal Canin Medium Adult 15kg', price: 54990, stock: 25, category: 'Alimento', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 54990 },
    { id: 9021, vendor_id: 902, product_name: 'Pro Plan Puppy Razas Medianas 15kg', price: 49990, stock: 18, category: 'Alimento', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 44991 },
    { id: 9022, vendor_id: 903, product_name: 'Brit Care Grain Free Salmon 12kg', price: 44990, stock: 14, category: 'Alimento', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1600804340584-c7db2eacf0bf?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 44990 },
    { id: 9023, vendor_id: 904, product_name: 'Hills Science Diet Senior 7+ 6.8kg', price: 38990, stock: 22, category: 'Alimento', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1623387641168-d9803ddd3f35?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 33142 },
    { id: 9024, vendor_id: 905, product_name: 'Whiskas Adulto Pollo 10kg', price: 24990, stock: 30, category: 'Alimento', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1615497001839-b0a0eac3274c?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 24990 },
    { id: 9025, vendor_id: 906, product_name: 'Alimento Húmedo Lata Pedigree x6', price: 8990, stock: 50, category: 'Alimento', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1585664811087-47f65abbad64?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 8990 },
    { id: 9026, vendor_id: 901, product_name: 'Taste of the Wild Pacific Stream 12.2kg', price: 62990, stock: 10, category: 'Alimento', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?auto=format&fit=crop&q=80&w=800', discount_percent: 5, discount_active: true, final_price: 59841 },
    { id: 9027, vendor_id: 903, product_name: 'Eukanuba Cachorro Raza Grande 15kg', price: 51990, stock: 16, category: 'Alimento', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 51990 },
  ],
  Snacks: [
    { id: 9030, vendor_id: 901, product_name: 'DentaStix Razas Medianas x28', price: 15990, stock: 40, category: 'Snacks', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1582798358481-d199fb7347bb?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 15990 },
    { id: 9031, vendor_id: 902, product_name: 'Hueso Natural Prensado x3', price: 6990, stock: 60, category: 'Snacks', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 6990 },
    { id: 9032, vendor_id: 903, product_name: 'Snack Pollo Deshidratado 200g', price: 7990, stock: 35, category: 'Snacks', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 7191 },
    { id: 9033, vendor_id: 904, product_name: 'Galletas Funcionales Articulaciones', price: 5990, stock: 55, category: 'Snacks', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 5990 },
    { id: 9034, vendor_id: 905, product_name: 'Jerky de Salmon Strips 150g', price: 8990, stock: 25, category: 'Snacks', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 8990 },
    { id: 9035, vendor_id: 906, product_name: 'Catnip Treats Gato x50', price: 4990, stock: 70, category: 'Snacks', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 4242 },
  ],
  Farmacia: [
    { id: 9040, vendor_id: 906, product_name: 'Antiparasitario Bravecto 20-40kg', price: 42990, stock: 15, category: 'Farmacia', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 42990 },
    { id: 9041, vendor_id: 901, product_name: 'Pipeta Frontline Plus Perro L', price: 18990, stock: 30, category: 'Farmacia', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1612531386530-97286d97c2d2?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 17091 },
    { id: 9042, vendor_id: 902, product_name: 'Suplemento Omega 3 Cápsulas x60', price: 12990, stock: 40, category: 'Farmacia', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1585584114963-503344a119b0?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 12990 },
    { id: 9043, vendor_id: 903, product_name: 'Vitaminas Cachorro Multivit 120tabs', price: 14990, stock: 25, category: 'Farmacia', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 14990 },
    { id: 9044, vendor_id: 904, product_name: 'Gel Dental Enzimático 70g', price: 7990, stock: 50, category: 'Farmacia', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 7990 },
    { id: 9045, vendor_id: 905, product_name: 'Collar Isabelino Cono Talla M', price: 5990, stock: 35, category: 'Farmacia', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=800', discount_percent: 20, discount_active: true, final_price: 4792 },
    { id: 9046, vendor_id: 906, product_name: 'Pipeta Gato Revolution Plus', price: 22990, stock: 18, category: 'Farmacia', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 22990 },
  ],
  Accesorios: [
    { id: 9050, vendor_id: 901, product_name: 'Juguete Kong Classic Large Rojo', price: 14990, stock: 45, category: 'Accesorios', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 14990 },
    { id: 9051, vendor_id: 902, product_name: 'Pelota Tennis Perro x3', price: 4990, stock: 80, category: 'Accesorios', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 4990 },
    { id: 9052, vendor_id: 903, product_name: 'Plato Comedero Doble Acero Inox', price: 12990, stock: 30, category: 'Accesorios', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 11042 },
    { id: 9053, vendor_id: 904, product_name: 'Bebedero Fuente Automática 2.5L', price: 29990, stock: 12, category: 'Accesorios', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 29990 },
    { id: 9054, vendor_id: 905, product_name: 'Dispensador Snacks Interactivo', price: 18990, stock: 20, category: 'Accesorios', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 18990 },
    { id: 9055, vendor_id: 906, product_name: 'Ratón Electrónico Gato Interactivo', price: 9990, stock: 40, category: 'Accesorios', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 8991 },
  ],
  Higiene: [
    { id: 9060, vendor_id: 901, product_name: 'Shampoo Hipoalergénico 500ml', price: 8990, stock: 50, category: 'Higiene', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1625794084867-8ddd239946b1?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 8990 },
    { id: 9061, vendor_id: 902, product_name: 'Cepillo Deslanador Furminator M', price: 24990, stock: 18, category: 'Higiene', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 22491 },
    { id: 9062, vendor_id: 903, product_name: 'Toallitas Húmedas Mascota x80', price: 5990, stock: 65, category: 'Higiene', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1581888227599-779811939961?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 5990 },
    { id: 9063, vendor_id: 904, product_name: 'Cortauñas Profesional con Luz LED', price: 11990, stock: 25, category: 'Higiene', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 11990 },
    { id: 9064, vendor_id: 905, product_name: 'Spray Perfume Mascota 250ml', price: 6990, stock: 40, category: 'Higiene', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 6990 },
    { id: 9065, vendor_id: 906, product_name: 'Kit Spa Baño Completo 4 Piezas', price: 19990, stock: 15, category: 'Higiene', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1544568100-847a948585b9?auto=format&fit=crop&q=80&w=800', discount_percent: 25, discount_active: true, final_price: 14993 },
  ],
  Camas: [
    { id: 9070, vendor_id: 901, product_name: 'Cama Ortopédica Premium XL', price: 45990, stock: 10, category: 'Camas', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1591946614720-90a587da4a36?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 45990 },
    { id: 9071, vendor_id: 902, product_name: 'Colchón Desenfundable Perro L', price: 29990, stock: 15, category: 'Camas', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 25492 },
    { id: 9072, vendor_id: 903, product_name: 'Iglú Cálido Gato Suave', price: 19990, stock: 20, category: 'Camas', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 19990 },
    { id: 9073, vendor_id: 904, product_name: 'Cama Elevada Cooling Bed M', price: 34990, stock: 8, category: 'Camas', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1601758174114-e711c0cbaa69?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 34990 },
    { id: 9074, vendor_id: 905, product_name: 'Manta Térmica Mascota 90x70cm', price: 12990, stock: 30, category: 'Camas', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 11691 },
    { id: 9075, vendor_id: 906, product_name: 'Cojín Donut Antiestrés S', price: 16990, stock: 25, category: 'Camas', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1585664811087-47f65abbad64?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 16990 },
  ],
  Paseo: [
    { id: 9080, vendor_id: 901, product_name: 'Arnés Reflectante No-Pull L', price: 22990, stock: 25, category: 'Paseo', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 22990 },
    { id: 9081, vendor_id: 902, product_name: 'Correa Retráctil 5m Resistente', price: 14990, stock: 35, category: 'Paseo', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 14990 },
    { id: 9082, vendor_id: 903, product_name: 'Bolsos Porta Mascotas Viaje', price: 39990, stock: 10, category: 'Paseo', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&q=80&w=800', discount_percent: 20, discount_active: true, final_price: 31992 },
    { id: 9083, vendor_id: 904, product_name: 'Bebedero Portátil Viaje 500ml', price: 8990, stock: 45, category: 'Paseo', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 8990 },
    { id: 9084, vendor_id: 905, product_name: 'Luz LED Collar Nocturno USB', price: 6990, stock: 60, category: 'Paseo', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 6990 },
    { id: 9085, vendor_id: 906, product_name: 'Bolsas Biodegradables x300', price: 5990, stock: 100, category: 'Paseo', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1585584114963-503344a119b0?auto=format&fit=crop&q=80&w=800', discount_percent: 10, discount_active: true, final_price: 5391 },
  ],
  Ropa: [
    { id: 9090, vendor_id: 901, product_name: 'Chaleco Abrigador Polar Talla M', price: 16990, stock: 20, category: 'Ropa', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 16990 },
    { id: 9091, vendor_id: 902, product_name: 'Impermeable con Capucha L', price: 19990, stock: 15, category: 'Ropa', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 16992 },
    { id: 9092, vendor_id: 903, product_name: 'Pijama Perro Algodón Estampado', price: 12990, stock: 25, category: 'Ropa', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 12990 },
    { id: 9093, vendor_id: 904, product_name: 'Botas Protectoras Lluvia x4', price: 14990, stock: 18, category: 'Ropa', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 14990 },
    { id: 9094, vendor_id: 905, product_name: 'Suéter Navideño Perro S', price: 9990, stock: 30, category: 'Ropa', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?auto=format&fit=crop&q=80&w=800', discount_percent: 20, discount_active: true, final_price: 7992 },
    { id: 9095, vendor_id: 906, product_name: 'Disfraz Superhéroe Talla M', price: 11990, stock: 12, category: 'Ropa', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 11990 },
  ],
  Ofertas: [
    { id: 9100, vendor_id: 901, product_name: 'Royal Canin Medium Adult 15kg', price: 54990, stock: 25, category: 'Alimento', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1568640347023-a616a30bc3bd?auto=format&fit=crop&q=80&w=800', discount_percent: 30, discount_active: true, final_price: 38493 },
    { id: 9101, vendor_id: 902, product_name: 'Cepillo Furminator Original M', price: 24990, stock: 18, category: 'Higiene', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?auto=format&fit=crop&q=80&w=800', discount_percent: 40, discount_active: true, final_price: 14994 },
    { id: 9102, vendor_id: 903, product_name: 'Cama Ortopédica Premium L', price: 39990, stock: 8, category: 'Camas', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1544568100-847a948585b9?auto=format&fit=crop&q=80&w=800', discount_percent: 35, discount_active: true, final_price: 25994 },
    { id: 9103, vendor_id: 904, product_name: 'Arnés + Correa Set Reflectante', price: 28990, stock: 20, category: 'Paseo', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&q=80&w=800', discount_percent: 25, discount_active: true, final_price: 21743 },
    { id: 9104, vendor_id: 905, product_name: 'Arena Premium Gato 20L', price: 16990, stock: 35, category: 'Gatos', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?auto=format&fit=crop&q=80&w=800', discount_percent: 20, discount_active: true, final_price: 13592 },
    { id: 9105, vendor_id: 906, product_name: 'Bravecto 10-20kg Antiparasitario', price: 38990, stock: 12, category: 'Farmacia', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1612531386530-97286d97c2d2?auto=format&fit=crop&q=80&w=800', discount_percent: 15, discount_active: true, final_price: 33142 },
    { id: 9106, vendor_id: 901, product_name: 'Kit Juguetes Gato x10 Piezas', price: 12990, stock: 30, category: 'Accesorios', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&q=80&w=800', discount_percent: 50, discount_active: true, final_price: 6495 },
    { id: 9107, vendor_id: 903, product_name: 'Impermeable Perro Reflectante L', price: 22990, stock: 15, category: 'Ropa', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&q=80&w=800', discount_percent: 30, discount_active: true, final_price: 16093 },
  ],
  Nuevos: [
    { id: 9110, vendor_id: 906, product_name: 'GPS Tracker Collar Inteligente', price: 49990, stock: 10, category: 'Accesorios', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 49990 },
    { id: 9111, vendor_id: 901, product_name: 'Comedero Inteligente WiFi 3L', price: 59990, stock: 8, category: 'Accesorios', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1535294435445-d7249524ef2e?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 59990 },
    { id: 9112, vendor_id: 902, product_name: 'Cámara Mascota HD con Audio', price: 44990, stock: 12, category: 'Accesorios', store_name: 'Mundo Animal Centro', image_url: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 44990 },
    { id: 9113, vendor_id: 903, product_name: 'Alimento Fresh Natural BARF 5kg', price: 34990, stock: 15, category: 'Alimento', store_name: 'La Huella Store', image_url: 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 34990 },
    { id: 9114, vendor_id: 904, product_name: 'Mochila Astronauta Gato Transparente', price: 32990, stock: 10, category: 'Paseo', store_name: 'Patitas Chile', image_url: 'https://images.unsplash.com/photo-1574158622682-e40e69881006?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 32990 },
    { id: 9115, vendor_id: 905, product_name: 'Probióticos Digestivos Polvo 200g', price: 18990, stock: 20, category: 'Farmacia', store_name: 'Happy Pets Providencia', image_url: 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 18990 },
    { id: 9116, vendor_id: 906, product_name: 'Juguete Puzzle IQ Level 3', price: 15990, stock: 25, category: 'Accesorios', store_name: 'Vet & Shop', image_url: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 15990 },
    { id: 9117, vendor_id: 901, product_name: 'Cepillo Autolimpiante One-Click', price: 9990, stock: 35, category: 'Higiene', store_name: 'PetShop Las Condes', image_url: 'https://images.unsplash.com/photo-1544568100-847a948585b9?auto=format&fit=crop&q=80&w=800', discount_percent: 0, discount_active: false, final_price: 9990 },
  ],
};
const CategoryPage = () => {
  const { slug } = useParams();
  const [searchParams] = useSearchParams();
  const queryFromUrl = searchParams.get('q') || '';
  const categoryName = slug ? decodeURIComponent(slug) : null;

  const [categoryMeta, setCategoryMeta] = useState(FALLBACK_CATEGORY_META);
  const meta = categoryName ? (categoryMeta[categoryName] || { emoji: '📦', label: categoryName, desc: '' }) : null;

  const [products, setProducts] = useState([]);
  const [allVendors, setAllVendors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState(queryFromUrl);
  const [activeTab, setActiveTab] = useState('productos'); // 'productos' | 'tiendas'
  const [priceRange, setPriceRange] = useState([0, 200000]);
  const [priceMax, setPriceMax] = useState(200000);
  const [sortBy, setSortBy] = useState('default');
  const { addItem, getItemQuantity, updateQuantity } = useCart();

  /* Cargar categorías desde la API */
  useEffect(() => {
    getCategories().then(res => {
      const cats = res.data?.data || res.data || [];
      if (cats.length > 0) {
        const map = {};
        cats.forEach(c => {
          map[c.name] = { emoji: c.emoji || '📦', label: c.name, desc: c.description || '' };
        });
        setCategoryMeta(map);
      }
    }).catch(() => {});
  }, []);

  useEffect(() => {
    if (queryFromUrl) setSearchTerm(queryFromUrl);
  }, [queryFromUrl]);

  useEffect(() => {
    window.scrollTo(0, 0);
    if (categoryName) loadData();
    else setLoading(false);
  }, [categoryName, queryFromUrl]);

  const loadData = async () => {
    setLoading(true);
    try {
      const apiParams = {};
      // Si es "Todos" con búsqueda, usar el parámetro search de la API
      if (categoryName === 'Todos' && queryFromUrl) {
        apiParams.search = queryFromUrl;
      } else if (categoryName !== 'Todos') {
        apiParams.category = categoryName;
      }
      const [productsRes, vendorsRes] = await Promise.all([
        getProducts(apiParams),
        getVendors(),
      ]);
      const realProducts = productsRes.data.data || [];
      const realVendors = vendorsRes.data.data || [];

      // Si no hay productos reales, usar datos demo
      const demoProducts = DEMO_PRODUCTS[categoryName] || [];
      setProducts(realProducts.length > 0 ? realProducts : demoProducts);
      setAllVendors(realVendors.length > 0 ? realVendors : DEMO_VENDORS);
    } catch (err) {
      console.error('Error cargando categoría:', err);
      // Fallback a demo en caso de error
      setProducts(DEMO_PRODUCTS[categoryName] || []);
      setAllVendors(DEMO_VENDORS);
    } finally {
      setLoading(false);
    }
  };

  /* Calcular rango de precios disponible cuando cambian los productos */
  useEffect(() => {
    if (products.length > 0) {
      const prices = products.map(p => Number(p.final_price || p.price) || 0);
      const max = Math.ceil(Math.max(...prices) / 1000) * 1000;
      setPriceMax(max || 200000);
      setPriceRange([0, max || 200000]);
    }
  }, [products]);

  /* Filtrar productos por búsqueda + rango de precio */
  const filteredProducts = useMemo(() => {
    let result = products;
    if (searchTerm.trim()) {
      const term = searchTerm.toLowerCase();
      result = result.filter(p =>
        p.product_name.toLowerCase().includes(term) ||
        p.store_name?.toLowerCase().includes(term)
      );
    }
    result = result.filter(p => {
      const price = Number(p.final_price || p.price) || 0;
      return price >= priceRange[0] && price <= priceRange[1];
    });
    if (sortBy === 'price_asc') result = [...result].sort((a, b) => (Number(a.final_price || a.price) || 0) - (Number(b.final_price || b.price) || 0));
    else if (sortBy === 'price_desc') result = [...result].sort((a, b) => (Number(b.final_price || b.price) || 0) - (Number(a.final_price || a.price) || 0));
    else if (sortBy === 'name_asc') result = [...result].sort((a, b) => (a.product_name || '').localeCompare(b.product_name || ''));
    else if (sortBy === 'discount') result = [...result].sort((a, b) => (Number(b.discount_percent) || 0) - (Number(a.discount_percent) || 0));
    return result;
  }, [products, searchTerm, priceRange, sortBy]);

  /* Obtener vendors únicos que venden en esta categoría */
  const categoryVendors = useMemo(() => {
    const vendorIds = [...new Set(products.map(p => p.vendor_id))];
    return allVendors.filter(v => vendorIds.includes(v.id));
  }, [products, allVendors]);

  const formatPrice = (price) => `$${parseInt(price).toLocaleString('es-CL')}`;

  /* ── Vista de lista de categorías (sin slug) ──────── */
  if (!categoryName) {
    const allCats = Object.entries(categoryMeta).filter(([k]) => k !== 'Todos');
    const [catSort, setCatSort] = React.useState('default');
    const sortedCats = [...allCats].sort((a, b) => {
      if (catSort === 'az') return a[1].label.localeCompare(b[1].label);
      if (catSort === 'za') return b[1].label.localeCompare(a[1].label);
      return 0;
    });
    return (
      <div style={{ fontFamily: 'Poppins, sans-serif', maxWidth: '1280px', margin: '0 auto', padding: '32px 20px' }}>
        <Link to="/" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', color: '#9ca3af', fontWeight: 700, fontSize: '13px', textDecoration: 'none', marginBottom: '24px' }}>
          <ArrowLeft size={16} /> Volver al Inicio
        </Link>
        <div style={{ background: 'linear-gradient(135deg, #00A8E8 0%, #0077B6 100%)', borderRadius: '20px', padding: '32px', marginBottom: '24px', color: '#fff', textAlign: 'center' }}>
          <h1 style={{ fontSize: '32px', fontWeight: 900, marginBottom: '8px' }}>🐾 Categorías</h1>
          <p style={{ fontSize: '14px', opacity: 0.85, fontWeight: 500 }}>Explora todas nuestras categorías de productos para mascotas</p>
        </div>
        {/* Barra de ordenamiento */}
        <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: '12px', marginBottom: '20px' }}>
          <label style={{ fontSize: '12px', fontWeight: 700, color: '#6b7280' }}>Ordenar:</label>
          <select value={catSort} onChange={(e) => setCatSort(e.target.value)} style={{
            padding: '10px 14px', borderRadius: '10px', border: '2px solid #e5e7eb',
            fontSize: '13px', fontWeight: 600, fontFamily: 'Poppins, sans-serif',
            outline: 'none', cursor: 'pointer', background: '#fff', color: '#374151',
          }}>
            <option value="default">Por defecto</option>
            <option value="az">Nombre A-Z</option>
            <option value="za">Nombre Z-A</option>
          </select>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '20px' }}>
          {sortedCats.map(([key, val]) => (
            <Link key={key} to={`/categoria/${key}`} style={{
              background: '#fff', borderRadius: '18px', padding: '28px 20px', textAlign: 'center',
              textDecoration: 'none', color: '#1f2937', border: '1px solid #f3f4f6',
              boxShadow: '0 2px 8px rgba(0,0,0,0.04)', transition: 'all 0.3s',
              display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px',
            }}
            onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-4px)'; e.currentTarget.style.boxShadow = '0 12px 40px rgba(0,0,0,0.08)'; e.currentTarget.style.borderColor = '#00A8E8'; }}
            onMouseLeave={e => { e.currentTarget.style.transform = 'none'; e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.04)'; e.currentTarget.style.borderColor = '#f3f4f6'; }}
            >
              <span style={{ fontSize: '40px' }}>{val.emoji}</span>
              <strong style={{ fontSize: '15px', fontWeight: 800 }}>{val.label}</strong>
              <span style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 600 }}>{val.desc}</span>
            </Link>
          ))}
        </div>
      </div>
    );
  }

  /* ── Skeleton loading ──────────────────────────────── */
  if (loading) {
    return (
      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 20px', fontFamily: 'Poppins, sans-serif' }}>
        <div style={{ height: '120px', background: 'linear-gradient(135deg, #e5e7eb, #f3f4f6)', borderRadius: '20px', marginBottom: '24px', animation: 'cpPulse 1.5s ease-in-out infinite' }} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '20px' }}>
          {[...Array(8)].map((_, i) => (
            <div key={i} style={{ background: '#f3f4f6', borderRadius: '20px', height: '320px', animation: 'cpPulse 1.5s ease-in-out infinite' }} />
          ))}
        </div>
        <style>{`@keyframes cpPulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }`}</style>
      </div>
    );
  }

  return (
    <div style={{ fontFamily: 'Poppins, sans-serif' }}>
      <style>{`
        @keyframes cpPulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }
        .cp-product-card { transition: all 0.3s ease; }
        .cp-product-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.08) !important; transform: translateY(-4px); }
        .cp-vendor-card { transition: all 0.3s ease; }
        .cp-vendor-card:hover { box-shadow: 0 12px 32px rgba(0,0,0,0.12) !important; transform: translateY(-4px); }
        .cp-tab { cursor: pointer; padding: 10px 24px; border-radius: 12px; font-weight: 700; font-size: 14px; border: none; transition: all 0.2s; }
        .cp-tab-active { background: #00A8E8; color: #fff; box-shadow: 0 4px 16px rgba(0,168,232,0.3); }
        .cp-tab-inactive { background: #f3f4f6; color: #6b7280; }
        .cp-tab-inactive:hover { background: #e5e7eb; }
        @media (max-width: 639px) {
          .cp-products-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 14px !important; }
          .cp-product-info { padding: 14px 16px !important; }
          .cp-product-name { font-size: 13px !important; }
          .cp-product-price { font-size: 16px !important; }
          .cp-product-desc { display: none !important; }
          .cp-search-wrap { flex-direction: column; }
          .cp-search-wrap > div:last-child { flex: 1 1 100% !important; min-width: 0 !important; width: 100% !important; }
        }
        @media (max-width: 380px) { .cp-products-grid { grid-template-columns: 1fr !important; } }
      `}</style>

      <div style={{ maxWidth: '1280px', margin: '0 auto', padding: '24px 20px' }}>

        {/* ── Breadcrumb ── */}
        <Link to="/" style={{
          display: 'inline-flex', alignItems: 'center', gap: '8px',
          color: '#9ca3af', fontWeight: 700, fontSize: '13px', textDecoration: 'none',
          marginBottom: '20px',
        }}>
          <ArrowLeft size={16} /> Volver al Inicio
        </Link>

        {/* ── Header de categoría ── */}
        <div style={{
          background: 'linear-gradient(135deg, #00A8E8 0%, #0077B6 100%)',
          borderRadius: '20px', padding: '28px 32px', marginBottom: '28px', color: '#fff',
          display: 'flex', alignItems: 'center', gap: '20px', flexWrap: 'wrap',
        }}>
          <div style={{
            width: '64px', height: '64px', background: 'rgba(255,255,255,0.2)',
            borderRadius: '16px', display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '32px', flexShrink: 0,
          }}>
            {meta.emoji}
          </div>
          <div style={{ flex: 1, minWidth: '200px' }}>
            <h1 style={{ fontSize: 'clamp(22px, 4vw, 32px)', fontWeight: 800, marginBottom: '4px' }}>
              {meta.label}
            </h1>
            <p style={{ fontSize: '14px', opacity: 0.85, fontWeight: 500 }}>
              {meta.desc} — {products.length} producto{products.length !== 1 ? 's' : ''} disponible{products.length !== 1 ? 's' : ''}
            </p>
          </div>
          <div style={{
            display: 'flex', gap: '12px', flexWrap: 'wrap',
          }}>
            <div style={{
              background: 'rgba(255,255,255,0.2)', borderRadius: '12px', padding: '10px 18px',
              display: 'flex', alignItems: 'center', gap: '8px',
            }}>
              <Package size={18} />
              <div>
                <div style={{ fontSize: '18px', fontWeight: 800 }}>{products.length}</div>
                <div style={{ fontSize: '10px', fontWeight: 600, opacity: 0.8 }}>Productos</div>
              </div>
            </div>
            <div style={{
              background: 'rgba(255,255,255,0.2)', borderRadius: '12px', padding: '10px 18px',
              display: 'flex', alignItems: 'center', gap: '8px',
            }}>
              <Store size={18} />
              <div>
                <div style={{ fontSize: '18px', fontWeight: 800 }}>{categoryVendors.length}</div>
                <div style={{ fontSize: '10px', fontWeight: 600, opacity: 0.8 }}>Tiendas</div>
              </div>
            </div>
          </div>
        </div>

        {/* ── Tabs + Buscador ── */}
        <div className="cp-search-wrap" style={{
          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
          gap: '16px', marginBottom: '24px', flexWrap: 'wrap',
        }}>
          <div style={{ display: 'flex', gap: '8px' }}>
            <button
              className={`cp-tab ${activeTab === 'productos' ? 'cp-tab-active' : 'cp-tab-inactive'}`}
              onClick={() => setActiveTab('productos')}
            >
              <span style={{ marginRight: '6px' }}>📦</span> Productos ({filteredProducts.length})
            </button>
            <button
              className={`cp-tab ${activeTab === 'tiendas' ? 'cp-tab-active' : 'cp-tab-inactive'}`}
              onClick={() => setActiveTab('tiendas')}
            >
              <span style={{ marginRight: '6px' }}>🏪</span> Tiendas ({categoryVendors.length})
            </button>
          </div>

          {activeTab === 'productos' && (
            <div style={{
              position: 'relative', flex: '0 1 360px', minWidth: '200px',
            }}>
              <Search size={18} style={{
                position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)',
                color: '#9ca3af', pointerEvents: 'none',
              }} />
              <input
                type="text"
                placeholder="Buscar productos..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                autoComplete="off"
                style={{
                  width: '100%', padding: '12px 16px 12px 44px',
                  borderRadius: '12px', border: '2px solid #e5e7eb',
                  fontSize: '14px', fontWeight: 500, fontFamily: 'Poppins, sans-serif',
                  outline: 'none', transition: 'border-color 0.2s',
                }}
                onFocus={(e) => (e.target.style.borderColor = '#00A8E8')}
                onBlur={(e) => (e.target.style.borderColor = '#e5e7eb')}
              />
            </div>
          )}
        </div>

        {/* ── Filtros: Precio + Ordenar ── */}
        {activeTab === 'productos' && products.length > 0 && (
          <div style={{
            display: 'flex', gap: '16px', marginBottom: '24px', flexWrap: 'wrap',
            alignItems: 'flex-end', background: '#fff', borderRadius: '14px', padding: '16px 20px',
            border: '1px solid #f3f4f6', boxShadow: '0 1px 4px rgba(0,0,0,0.03)',
          }}>
            <div style={{ flex: '1 1 320px', minWidth: '220px' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '8px' }}>
                <Filter size={14} /> Rango de precio: {formatPrice(priceRange[0])} — {formatPrice(priceRange[1])}
              </label>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <span style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 600, whiteSpace: 'nowrap' }}>{formatPrice(0)}</span>
                <div style={{ flex: 1, position: 'relative', height: '36px', display: 'flex', alignItems: 'center' }}>
                  <div style={{ position: 'absolute', width: '100%', height: '6px', background: '#e5e7eb', borderRadius: '3px' }} />
                  <div style={{
                    position: 'absolute', height: '6px', borderRadius: '3px', background: 'linear-gradient(90deg, #00A8E8, #0077B6)',
                    left: `${(priceRange[0] / priceMax) * 100}%`,
                    width: `${((priceRange[1] - priceRange[0]) / priceMax) * 100}%`,
                  }} />
                  <input type="range" min={0} max={priceMax} step={1000} value={priceRange[0]}
                    onChange={(e) => { const v = Number(e.target.value); if (v <= priceRange[1] - 1000) setPriceRange([v, priceRange[1]]); }}
                    style={{ position: 'absolute', width: '100%', height: '36px', opacity: 0, cursor: 'pointer', zIndex: 2 }}
                  />
                  <input type="range" min={0} max={priceMax} step={1000} value={priceRange[1]}
                    onChange={(e) => { const v = Number(e.target.value); if (v >= priceRange[0] + 1000) setPriceRange([priceRange[0], v]); }}
                    style={{ position: 'absolute', width: '100%', height: '36px', opacity: 0, cursor: 'pointer', zIndex: 3 }}
                  />
                  {/* Thumb indicators */}
                  <div style={{ position: 'absolute', left: `calc(${(priceRange[0] / priceMax) * 100}% - 8px)`, width: '16px', height: '16px', borderRadius: '50%', background: '#00A8E8', border: '2px solid #fff', boxShadow: '0 2px 6px rgba(0,0,0,0.15)', zIndex: 1 }} />
                  <div style={{ position: 'absolute', left: `calc(${(priceRange[1] / priceMax) * 100}% - 8px)`, width: '16px', height: '16px', borderRadius: '50%', background: '#0077B6', border: '2px solid #fff', boxShadow: '0 2px 6px rgba(0,0,0,0.15)', zIndex: 1 }} />
                </div>
                <span style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 600, whiteSpace: 'nowrap' }}>{formatPrice(priceMax)}</span>
              </div>
            </div>
            <div style={{ flex: '0 0 auto' }}>
              <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#6b7280', marginBottom: '8px' }}>Ordenar por</label>
              <select value={sortBy} onChange={(e) => setSortBy(e.target.value)} style={{
                padding: '10px 14px', borderRadius: '10px', border: '2px solid #e5e7eb',
                fontSize: '13px', fontWeight: 600, fontFamily: 'Poppins, sans-serif',
                outline: 'none', cursor: 'pointer', background: '#fff', color: '#374151',
              }}>
                <option value="default">Relevancia</option>
                <option value="price_asc">Precio: menor a mayor</option>
                <option value="price_desc">Precio: mayor a menor</option>
                <option value="name_asc">Nombre A-Z</option>
                <option value="discount">Mayor descuento</option>
              </select>
            </div>
            {(priceRange[0] > 0 || priceRange[1] < priceMax || sortBy !== 'default') && (
              <button onClick={() => { setPriceRange([0, priceMax]); setSortBy('default'); }} style={{
                padding: '10px 16px', borderRadius: '10px', border: '1px solid #e5e7eb',
                background: '#fff', color: '#6b7280', fontWeight: 700, fontSize: '12px',
                cursor: 'pointer', fontFamily: 'Poppins, sans-serif', whiteSpace: 'nowrap',
              }}>
                ✕ Limpiar filtros
              </button>
            )}
          </div>
        )}

        {/* ═══════ TAB: PRODUCTOS ═══════ */}
        {activeTab === 'productos' && (
          <>
            {filteredProducts.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '64px 0' }}>
                <PawPrint size={48} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
                <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '16px' }}>
                  {searchTerm ? 'No se encontraron productos' : 'Aún no hay productos en esta categoría'}
                </p>
                {searchTerm && (
                  <button onClick={() => setSearchTerm('')} style={{
                    marginTop: '12px', background: '#00A8E8', color: '#fff',
                    border: 'none', padding: '10px 24px', borderRadius: '10px',
                    fontWeight: 700, fontSize: '13px', cursor: 'pointer',
                  }}>
                    Limpiar búsqueda
                  </button>
                )}
              </div>
            ) : (
              <div className="cp-products-grid" style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
                gap: '24px',
              }}>
                {filteredProducts.map((product) => {
                  const qty = getItemQuantity(product.id);
                  return (
                    <Link
                      to={`/producto/${product.id}`}
                      state={{ product }}
                      key={product.id}
                      className="cp-product-card"
                      style={{
                        background: '#fff', borderRadius: '18px', overflow: 'hidden',
                        border: '1px solid #f3f4f6',
                        boxShadow: '0 2px 8px rgba(0,0,0,0.04)', textDecoration: 'none', color: 'inherit',
                        display: 'flex', flexDirection: 'column',
                      }}
                    >
                      <div style={{ position: 'relative', aspectRatio: '1', overflow: 'hidden', background: '#f9fafb' }}>
                        <img
                          src={getSmartProductImage(product)}
                          alt={product.product_name}
                          style={{ width: '100%', height: '100%', objectFit: 'cover', transition: 'transform 0.5s ease' }}
                          loading="lazy"
                          onError={(e) => { e.target.src = 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80'; }}
                        />
                        {product.discount_active && (
                          <span style={{
                            position: 'absolute', top: '12px', right: '12px',
                            background: '#ef4444', color: '#fff',
                            fontSize: '11px', fontWeight: 800, padding: '4px 10px', borderRadius: '8px',
                          }}>
                            -{product.discount_percent}%
                          </span>
                        )}
                        {product.category && (
                          <span style={{
                            position: 'absolute', top: '12px', left: '12px',
                            background: 'rgba(255,255,255,0.92)', backdropFilter: 'blur(4px)',
                            fontSize: '10px', fontWeight: 700, padding: '4px 8px', borderRadius: '6px', color: '#2F3A40',
                          }}>
                            {product.category}
                          </span>
                        )}
                      </div>
                      <div className="cp-product-info" style={{ padding: '18px 20px', flex: 1, display: 'flex', flexDirection: 'column' }}>
                        <h4 className="cp-product-name" style={{
                          fontWeight: 700, fontSize: '14px', color: '#1f2937', lineHeight: 1.4, marginBottom: '4px',
                        }}>
                          {product.product_name}
                        </h4>

                        {/* Tienda del producto */}
                        <Link
                          to={`/tienda/${product.vendor_id}`}
                          onClick={(e) => e.stopPropagation()}
                          style={{
                            display: 'flex', alignItems: 'center', gap: '4px',
                            fontSize: '11px', fontWeight: 600, color: '#00A8E8',
                            textDecoration: 'none', marginBottom: '8px',
                          }}
                        >
                          <Store size={11} /> {product.store_name}
                        </Link>

                        <div style={{ marginTop: 'auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                          <div>
                            {product.discount_active ? (
                              <>
                                <span style={{ fontSize: '11px', color: '#9ca3af', textDecoration: 'line-through', marginRight: '6px' }}>
                                  {formatPrice(product.price)}
                                </span>
                                <span className="cp-product-price" style={{ fontSize: '18px', fontWeight: 900, color: '#ef4444' }}>
                                  {formatPrice(product.final_price)}
                                </span>
                              </>
                            ) : (
                              <span className="cp-product-price" style={{ fontSize: '18px', fontWeight: 900, color: '#00A8E8' }}>
                                {formatPrice(product.price)}
                              </span>
                            )}
                          </div>
                          <div onClick={(e) => { e.preventDefault(); e.stopPropagation(); }}>
                            {qty > 0 ? (
                              <div style={{
                                display: 'flex', alignItems: 'center', gap: '4px',
                                background: '#f0f9ff', borderRadius: '10px', padding: '3px',
                              }}>
                                <button
                                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); updateQuantity(product.id, qty - 1); }}
                                  style={{
                                    width: '30px', height: '30px', borderRadius: '8px',
                                    border: 'none', background: '#fff', cursor: 'pointer',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
                                  }}
                                >
                                  <Minus size={14} color="#00A8E8" />
                                </button>
                                <span style={{ fontSize: '14px', fontWeight: 700, color: '#1f2937', minWidth: '24px', textAlign: 'center' }}>
                                  {qty}
                                </span>
                                <button
                                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem({ ...product, name: product.product_name, image: getSmartProductImage(product), quantity: 1 }); }}
                                  style={{
                                    width: '30px', height: '30px', borderRadius: '8px',
                                    border: 'none', background: '#00A8E8', cursor: 'pointer',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    boxShadow: '0 2px 6px rgba(0,168,232,0.3)',
                                  }}
                                >
                                  <Plus size={14} color="#fff" />
                                </button>
                              </div>
                            ) : (
                              <button
                                onClick={(e) => { e.preventDefault(); e.stopPropagation(); addItem({ ...product, name: product.product_name, image: getSmartProductImage(product), quantity: 1 }); }}
                                style={{
                                  width: '36px', height: '36px', borderRadius: '50%',
                                  border: 'none', background: '#fff', cursor: 'pointer',
                                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                                  boxShadow: '0 2px 8px rgba(0,0,0,0.1)', transition: 'all 0.2s',
                                }}
                              >
                                <Plus size={18} color="#00A8E8" />
                              </button>
                            )}
                          </div>
                        </div>
                      </div>
                    </Link>
                  );
                })}
              </div>
            )}
          </>
        )}

        {/* ═══════ TAB: TIENDAS ═══════ */}
        {activeTab === 'tiendas' && (
          <>
            {categoryVendors.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '64px 0' }}>
                <Store size={48} color="#d1d5db" style={{ margin: '0 auto 16px' }} />
                <p style={{ color: '#9ca3af', fontWeight: 700, fontSize: '16px' }}>
                  Aún no hay tiendas con productos en esta categoría
                </p>
              </div>
            ) : (
              <>
                <p style={{ fontSize: '13px', color: '#9ca3af', fontWeight: 500, marginBottom: '20px' }}>
                  Tiendas que venden productos de <strong style={{ color: '#2F3A40' }}>{meta.label}</strong>. Haz clic en una para ver su catálogo completo.
                </p>
                <div style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
                  gap: '24px',
                }}>
                  {categoryVendors.map((vendor) => {
                    const vendorProductCount = products.filter(p => p.vendor_id === vendor.id).length;
                    return (
                      <Link
                        key={vendor.id}
                        to={`/tienda/${vendor.id}`}
                        className="cp-vendor-card"
                        style={{
                          background: '#fff', borderRadius: '16px', padding: '20px',
                          boxShadow: '0 2px 12px rgba(0,0,0,0.06)', border: '1px solid #f0f0f0',
                          textDecoration: 'none', color: 'inherit', display: 'block',
                        }}
                      >
                        <div style={{ display: 'flex', alignItems: 'center', gap: '14px', marginBottom: '14px' }}>
                          <div style={{
                            width: '52px', height: '52px', borderRadius: '14px',
                            background: '#f0f9ff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                            flexShrink: 0, overflow: 'hidden',
                          }}>
                            {vendor.logo_url ? (
                              <img src={vendor.logo_url} alt={vendor.store_name} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '14px' }} />
                            ) : (
                              <Store size={24} color="#00A8E8" />
                            )}
                          </div>
                          <div style={{ flex: 1, minWidth: 0 }}>
                            <h4 style={{
                              fontWeight: 700, fontSize: '15px', color: '#2F3A40', marginBottom: '4px',
                              whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                            }}>
                              {vendor.store_name}
                            </h4>
                            {vendor.address && (
                              <p style={{ fontSize: '11px', color: '#9ca3af', fontWeight: 500, display: 'flex', alignItems: 'center', gap: '4px' }}>
                                <MapPin size={11} /> {vendor.address}
                              </p>
                            )}
                          </div>
                        </div>
                        <div style={{
                          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                          background: '#f9fafb', borderRadius: '10px', padding: '10px 14px',
                        }}>
                          <span style={{ fontSize: '12px', fontWeight: 700, color: '#00A8E8' }}>
                            {vendorProductCount} producto{vendorProductCount !== 1 ? 's' : ''} en {meta.label}
                          </span>
                          <span style={{
                            display: 'flex', alignItems: 'center', gap: '4px',
                            fontSize: '12px', fontWeight: 700, color: '#2F3A40',
                          }}>
                            <Star size={12} style={{ color: '#FFC400', fill: '#FFC400' }} />
                            {vendor.rating || '4.8'}
                          </span>
                        </div>
                      </Link>
                    );
                  })}
                </div>
              </>
            )}
          </>
        )}

        {/* ── Todas las categorías (navegación rápida) ── */}
        <div style={{ marginTop: '48px', borderTop: '1px solid #e5e7eb', paddingTop: '32px' }}>
          <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#2F3A40', marginBottom: '16px' }}>
            Explorar más categorías
          </h3>
          <div style={{
            display: 'flex', flexWrap: 'wrap', gap: '10px',
          }}>
            {Object.entries(categoryMeta).map(([key, val]) => (
              <Link
                key={key}
                to={`/categoria/${encodeURIComponent(key)}`}
                style={{
                  display: 'inline-flex', alignItems: 'center', gap: '6px',
                  padding: '8px 16px', borderRadius: '10px',
                  background: key === categoryName ? '#00A8E8' : '#f3f4f6',
                  color: key === categoryName ? '#fff' : '#4b5563',
                  fontWeight: 700, fontSize: '13px', textDecoration: 'none',
                  transition: 'all 0.2s',
                }}
              >
                <span>{val.emoji}</span> {val.label}
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default CategoryPage;
