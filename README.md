# 🐾 PetsGo - Marketplace de Mascotas

**PetsGo** es un marketplace digital que conecta a dueños de mascotas con tiendas, productos y servicios especializados. Diseñado para ofrecer una experiencia moderna, rápida e intuitiva.

---

## 🚀 Tecnologías

### Frontend
- **React 19** + **Vite 7**
- **Tailwind CSS v4**
- Componentes responsivos (mobile-first)
- Context API para autenticación y carrito

### Backend
- **WordPress** como CMS / API REST
- **WooCommerce** para gestión de productos y pedidos
- Plugin personalizado `petsgo-core.php`

### Base de Datos
- **MySQL** (WAMP)

---

## 📁 Estructura del Proyecto

```
PetsGoDev/
├── frontend/               # Aplicación React + Vite
│   ├── src/
│   │   ├── components/     # Header, Footer, BotChatOverlay, FloatingCart
│   │   ├── context/        # AuthContext, CartContext
│   │   ├── pages/          # HomePage, VendorsPage, PlansPage, CartPage...
│   │   └── services/       # API client (api.js)
│   └── public/
├── wp-content/             # WordPress plugins
│   └── mu-plugins/         # petsgo-core.php
├── Base_de_datos/          # Scripts SQL
├── Docs/                   # Documentación técnica y manual de usuario
├── Petsgo_Diseño/          # Assets de marca (SVG, PNG, JPG)
└── Prototipo/              # Prototipos web
```

---

## ⚙️ Instalación

### Requisitos
- Node.js 18+
- WAMP / XAMPP (PHP 8+, MySQL)
- Git

### Frontend

```bash
cd frontend
npm install
npm run dev -- --port 5177
```

La app estará disponible en `http://localhost:5177`

### Backend (WordPress)

1. Instalar WordPress en la raíz del proyecto
2. Importar los scripts SQL desde `Base_de_datos/`
3. Activar el plugin `petsgo-core.php` (se carga automáticamente desde `mu-plugins`)

---

## 🎨 Diseño

| Elemento   | Color     |
|------------|-----------|
| Primario   | `#00A8E8` |
| Secundario | `#FFC400` |
| Oscuro     | `#2F3A40` |

---

## 📱 Páginas Principales

| Página | Descripción |
|--------|-------------|
| **Home** | Hero, categorías, productos top, tiendas, marcas |
| **Vendors** | Listado de tiendas con filtros |
| **Vendor Detail** | Productos de cada tienda con controles de cantidad |
| **Product Detail** | Detalle completo de producto |
| **Plans** | Planes para vendedores con formulario de contacto |
| **Cart** | Carrito de compras con resumen |
| **My Orders** | Historial de pedidos del usuario |
| **Login** | Inicio de sesión / registro |
| **Admin Dashboard** | Panel de administración |
| **Vendor Dashboard** | Panel del vendedor |
| **Rider Dashboard** | Panel del repartidor |

---

## 🤖 Chatbot

Widget flotante con asistente virtual para resolver dudas de los usuarios. Incluye animación de mascota orbitante.

---

## 👥 Roles

- **Comprador** — Explora, compra y rastrea pedidos
- **Vendedor** — Gestiona tienda, productos e inventario
- **Rider** — Recibe y entrega pedidos
- **Administrador** — Gestión completa de la plataforma

---

## 🌿 Flujo de Trabajo con Git

### Ramas

| Rama | Propósito |
|------|-----------|
| `main` | Versión estable / producción |
| `develop` | Rama activa de desarrollo |

### Clonar el repositorio

```bash
git clone https://github.com/luismiguel1993/petsgo.git
cd petsgo
```

### Cambiar a la rama de desarrollo

```bash
git checkout develop
```

### Guardar cambios en develop

```bash
git add .
git commit -m "Descripción del cambio"
git push
```

### Promover develop a producción (merge a main)

```bash
git checkout main
git merge develop
git push
git checkout develop   # Volver a develop para seguir trabajando
```

### Crear una rama para una funcionalidad específica (opcional)

```bash
git checkout -b feature/nombre-funcionalidad
# ... trabajar ...
git add .
git commit -m "Implementar nombre-funcionalidad"
git push -u origin feature/nombre-funcionalidad
# Luego crear Pull Request en GitHub para mergear a develop
```

---

## 📄 Licencia

Proyecto privado — Todos los derechos reservados © 2026 PetsGo
