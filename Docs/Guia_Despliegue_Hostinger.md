# 🚀 Guía de Despliegue — PetsGo Marketplace en Hostinger

> Dominio: `https://petsgo.cl`  
> Stack: WordPress (backend headless) + React 19 (frontend SPA)

---

## 📋 Índice

1. [Estructura en Hostinger](#1-estructura-en-hostinger)
2. [Paso 1 — Configurar Base de Datos](#2-paso-1--configurar-base-de-datos)
3. [Paso 2 — Subir WordPress (Backend)](#3-paso-2--subir-wordpress-backend)
4. [Paso 3 — Configurar wp-config.php](#4-paso-3--configurar-wp-configphp)
5. [Paso 4 — Compilar y Subir Frontend](#5-paso-4--compilar-y-subir-frontend)
6. [Paso 5 — Configurar SSL y DNS](#6-paso-5--configurar-ssl-y-dns)
7. [Paso 6 — Verificación Final](#7-paso-6--verificación-final)
8. [Estructura de Archivos Final](#8-estructura-de-archivos-final)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Estructura en Hostinger

PetsGo usa **un solo dominio** para todo. WordPress sirve la API REST y el panel de administración, y el frontend React se sirve como archivos estáticos desde la raíz.

```
petsgo.cl/                      ← Frontend React (dist/)
petsgo.cl/wp-admin/             ← Panel admin WordPress
petsgo.cl/wp-json/petsgo/v1/    ← API REST
petsgo.cl/wp-content/uploads/   ← Imágenes subidas
```

---

## 2. Paso 1 — Configurar Base de Datos

### 2.1 Crear la BD en Hostinger

1. Ve a **hPanel → Bases de datos → MySQL**
2. Crea una nueva base de datos:
   - Nombre BD: `u123456789_petsgo` (Hostinger agrega el prefijo automáticamente)
   - Nombre usuario: `u123456789_admin`
   - Contraseña: una contraseña segura (guárdala, se usa en wp-config.php)
3. **Anota** estos 3 valores, los necesitas en el paso 3

### 2.2 Instalar WordPress

Tienes **dos opciones**:

#### Opción A: Auto-instalador de Hostinger (Recomendado)
1. Ve a **hPanel → WordPress → Instalar WordPress**
2. Usa los datos de la BD que creaste
3. Completa el wizard (admin user, title, etc.)
4. WordPress queda instalado en `public_html/`

#### Opción B: Subir archivos manualmente
1. Descarga WordPress desde https://wordpress.org/download/
2. Sube los archivos a `public_html/` vía File Manager o FTP
3. Accede a `https://petsgo.cl/wp-admin/install.php` para completar la instalación

### 2.3 Ejecutar Script de Tablas PetsGo

1. Ve a **hPanel → Bases de datos → phpMyAdmin**
2. Selecciona tu base de datos
3. Ve a pestaña **SQL**
4. Pega el contenido completo de `Base_de_datos/ScriptProduccion.sql`
5. Click **Ejecutar**
6. Verifica que se crearon 18 tablas `wp_petsgo_*`:
   ```
   wp_petsgo_subscriptions, wp_petsgo_vendors, wp_petsgo_inventory,
   wp_petsgo_categories, wp_petsgo_user_profiles, wp_petsgo_orders,
   wp_petsgo_order_items, wp_petsgo_invoices, wp_petsgo_rider_documents,
   wp_petsgo_delivery_ratings, wp_petsgo_rider_delivery_offers,
   wp_petsgo_rider_payouts, wp_petsgo_tickets, wp_petsgo_ticket_replies,
   wp_petsgo_chat_history, wp_petsgo_coupons, wp_petsgo_audit_log,
   wp_petsgo_leads
   ```

---

## 3. Paso 2 — Subir WordPress (Backend)

### 3.1 Subir el Plugin PetsGo

El plugin es el corazón del backend. Solo necesitas subir **un archivo**:

```
wp-content/mu-plugins/petsgo-core.php
```

**Vía File Manager de Hostinger:**
1. Navega a `public_html/wp-content/`
2. Crea la carpeta `mu-plugins/` si no existe
3. Sube `petsgo-core.php` dentro de `mu-plugins/`

> **mu-plugins** se activan automáticamente, no necesitas activar nada en WP.

### 3.2 Subir Themes/Plugins adicionales (si los hay)

Si tienes themes o plugins extra en `wp-content/themes/` o `wp-content/plugins/`, súbelos también.

---

## 4. Paso 3 — Configurar wp-config.php

El `wp-config.php` ya está preparado para detectar el ambiente automáticamente. Solo debes actualizar las credenciales de producción:

1. Abre `public_html/wp-config.php` en el File Manager de Hostinger
2. Busca la sección `--- Producción (Hostinger) ---`
3. Reemplaza:

```php
// --- Producción (Hostinger) ---
define('DB_NAME',     'u123456789_petsgo');      // ← Tu nombre de BD real
define('DB_USER',     'u123456789_admin');        // ← Tu usuario de BD real
define('DB_PASSWORD', 'TuContraseñaSegura123!');  // ← Tu contraseña real
define('DB_HOST',     'localhost');               // ← Generalmente localhost en Hostinger
```

4. Verifica que las URLs estén correctas:
```php
define('WP_HOME',    'https://petsgo.cl');
define('WP_SITEURL', 'https://petsgo.cl');
```

5. Guarda el archivo

### 4.1 Verificar que WordPress funciona

1. Accede a `https://petsgo.cl/wp-admin/`
2. Inicia sesión con tu cuenta de administrador
3. Ve a **PetsGo → Dashboard** en el menú lateral
4. Verifica que el panel admin carga correctamente

---

## 5. Paso 4 — Compilar y Subir Frontend

### 5.1 Compilar para producción

En tu máquina local:

```powershell
cd c:\wamp64\www\PetsGoDev\frontend

# Verificar que .env.production tiene los valores correctos
cat .env.production
# Debe mostrar:
# VITE_API_URL=https://petsgo.cl/wp-json/petsgo/v1
# VITE_WP_BASE=https://petsgo.cl
# VITE_ENV=production

# Compilar
npm run build
```

Esto genera la carpeta `frontend/dist/` con:
```
dist/
├── .htaccess          ← Routing SPA + cache + gzip
├── index.html         ← Entrada de la app React
├── vite.svg          
└── assets/
    ├── index-XXXX.js  ← JavaScript compilado
    ├── index-XXXX.css ← Estilos
    └── ...            ← Otros assets
```

### 5.2 Subir el frontend a Hostinger

**El frontend va en la RAÍZ de `public_html/`**, junto a WordPress.

#### Vía File Manager:
1. Ve a **hPanel → Archivos → File Manager**
2. Navega a `public_html/`
3. Sube **TODO el contenido** de `frontend/dist/`:
   - `.htaccess` → sobrescribe el existente (haz backup primero)
   - `index.html` → sobrescribe el de WordPress
   - Carpeta `assets/` → sube completa
   - `vite.svg` → sube

#### Vía FTP (FileZilla):
```
Host: ftp.petsgo.cl (o la IP de Hostinger)
Puerto: 21
Usuario: tu usuario FTP de Hostinger
Password: tu contraseña FTP
```
Sube los archivos de `dist/` a `public_html/`

### 5.3 Configurar .htaccess final

El `.htaccess` del frontend debe coexistir con WordPress. Edita `public_html/.htaccess` para que incluya ambas reglas:

```apache
# ============================================
# PetsGo - .htaccess Producción
# React SPA + WordPress REST API
# ============================================

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # --- WordPress: admin, API, uploads, login ---
  # Si el request es para WordPress, dejar que WP lo maneje
  RewriteRule ^wp-admin(.*)$ - [L]
  RewriteRule ^wp-login\.php$ - [L]
  RewriteRule ^wp-json(.*)$ - [L]
  RewriteRule ^wp-content(.*)$ - [L]
  RewriteRule ^wp-includes(.*)$ - [L]
  RewriteRule ^wp-cron\.php$ - [L]
  RewriteRule ^xmlrpc\.php$ - [L]

  # --- Archivos estáticos: si existe el archivo, servirlo ---
  RewriteCond %{REQUEST_FILENAME} -f [OR]
  RewriteCond %{REQUEST_FILENAME} -d
  RewriteRule ^ - [L]

  # --- React SPA: todo lo demás va a index.html ---
  RewriteRule ^ index.html [QSA,L]
</IfModule>

# Seguridad
Options -Indexes

# Cache de assets con hash (Vite)
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
  ExpiresByType image/png "access plus 1 month"
  ExpiresByType image/jpeg "access plus 1 month"
  ExpiresByType image/webp "access plus 1 month"
  ExpiresByType image/svg+xml "access plus 1 month"
  ExpiresByType font/woff2 "access plus 1 year"
</IfModule>

# Compresión gzip
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
```

> ⚠️ **IMPORTANTE**: Este `.htaccess` reemplaza tanto el de WordPress como el del frontend. Guárdalo directamente en `public_html/.htaccess`.

---

## 6. Paso 5 — Configurar SSL y DNS

### 6.1 SSL (HTTPS)
1. Ve a **hPanel → SSL → Instalar SSL**
2. Hostinger ofrece SSL gratuito de Let's Encrypt
3. Actívalo para `petsgo.cl` y `www.petsgo.cl`
4. Activa **Forzar HTTPS** en las opciones

### 6.2 DNS
Si tu dominio fue comprado en otro proveedor:
1. Apunta los nameservers a Hostinger:
   ```
   ns1.dns-parking.com
   ns2.dns-parking.com
   ```
   (Los nameservers exactos los encuentras en hPanel → Dominio)

---

## 7. Paso 6 — Verificación Final

### ✅ Checklist

| # | Verificación | URL |
|---|-------------|-----|
| 1 | Frontend carga | `https://petsgo.cl/` |
| 2 | Rutas React funcionan | `https://petsgo.cl/tiendas` |
| 3 | WP Admin accesible | `https://petsgo.cl/wp-admin/` |
| 4 | API responde | `https://petsgo.cl/wp-json/petsgo/v1/vendors` |
| 5 | Imágenes cargan | `https://petsgo.cl/wp-content/uploads/` |
| 6 | Panel PetsGo funciona | `https://petsgo.cl/wp-admin/admin.php?page=petsgo-dashboard` |
| 7 | Login de clientes | Probar registro/login desde frontend |
| 8 | HTTPS forzado | `http://petsgo.cl` redirige a `https://` |

### Test rápido de API
Abre en el navegador:
```
https://petsgo.cl/wp-json/petsgo/v1/vendors
```
Debe retornar JSON con las tiendas.

---

## 8. Estructura de Archivos Final

```
public_html/                          ← Hostinger
├── .htaccess                         ← Routing SPA + WordPress (paso 5.3)
├── index.html                        ← React app (del build)
├── vite.svg                          ← Favicon Vite
├── assets/                           ← JS/CSS compilados (del build)
│   ├── index-XXXX.js
│   ├── index-XXXX.css
│   └── ...
├── wp-admin/                         ← WordPress admin
├── wp-includes/                      ← WordPress core
├── wp-content/
│   ├── mu-plugins/
│   │   └── petsgo-core.php           ← ⭐ Plugin principal PetsGo
│   ├── uploads/                      ← Imágenes subidas
│   ├── themes/
│   └── plugins/
├── wp-config.php                     ← Configuración (con credenciales prod)
├── wp-login.php
├── wp-cron.php
├── wp-load.php
├── wp-settings.php
└── ... (demás archivos de WordPress)
```

---

## 9. Troubleshooting

### Error 500 al acceder al sitio
- Revisa `wp-config.php`: credenciales de BD incorrectas
- Revisa permisos: archivos deben ser `644`, carpetas `755`
- Revisa `.htaccess`: puede haber conflicto de reglas

### API retorna 404
- Verifica que `wp-content/mu-plugins/petsgo-core.php` existe
- Ve a WP Admin → Ajustes → Enlaces permanentes → Click "Guardar" (regenera rewrite rules)

### CORS errors en consola
- El plugin ya incluye `https://petsgo.cl` en CORS permitidos
- Si usas `www.petsgo.cl`, también está permitido
- Verifica en el navegador (F12 → Network) el header `Access-Control-Allow-Origin`

### Frontend carga pero rutas dan 404
- El `.htaccess` no está correcto. Usar el del paso 5.3
- Verificar que `mod_rewrite` está activo (Hostinger lo tiene por defecto)

### Imágenes no cargan
- Subir la carpeta `wp-content/uploads/` si existía localmente
- Verificar permisos de la carpeta `uploads/` (755)

### WP Admin redirige a localhost
- En phpMyAdmin, tabla `wp_options`, buscar:
  - `siteurl` → debe ser `https://petsgo.cl`
  - `home` → debe ser `https://petsgo.cl`
- O bien verificar que `wp-config.php` tiene las URLs correctas

---

## 📌 Resumen de Comandos Rápidos

```powershell
# Compilar frontend para producción
cd frontend
npm run build

# Los archivos quedan en frontend/dist/
# Subir contenido de dist/ a public_html/ en Hostinger
```

```sql
-- En phpMyAdmin: verificar tablas PetsGo
SHOW TABLES LIKE 'wp_petsgo_%';

-- Verificar URLs de WordPress
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name IN ('siteurl', 'home');
```

---

**¿Dudas?** Contactar al equipo de desarrollo — Automatiza Tech System
