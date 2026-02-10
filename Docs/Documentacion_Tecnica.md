# Documentación Técnica - PetsGo Marketplace

## 1. Arquitectura del Sistema
El proyecto utiliza una arquitectura **Headless WordPress**:
- **Backend**: WordPress instalado en `C:\wamp64\www\PetsGo\WordPress`.
- **API**: WP REST API extendida mediante el tema `automatiza-tech`.
- **Frontend Web**: React Single Page Application (SPA).
- **Frontend Mobile**: (Futuro) React Native / Swift / Kotlin consumiendo la misma API.
- **Base de Datos**: MariaDB gestionada localmente por WAMP.

La comunicación entre el cliente y el servidor se realiza exclusivamente a través de endpoints JSON RESTful.

## 2. Diagrama de Base de Datos
El esquema extiende WordPress con tablas personalizadas. Prefijo: `wp_petsgo_`.

- **wp_petsgo_vendors**: Almacena información fiscal y de perfil de las tiendas.
- **wp_petsgo_inventory**: Catálogo de productos con stock y precio. Relacionado con `vendors`.
- **wp_petsgo_orders**: Pedidos transaccionales. Relaciona `customer`, `vendor`, y `rider`.
- **wp_petsgo_subscriptions**: Planes de pago para vendedores (SaaS).

## 3. API REST Endpoints
La API se encuentra en el namespace `/wp-json/petsgo/v1/`.

### Public Endpoints
| Método | Endpoint | Descripción | Parámetros |
|--------|----------|-------------|------------|
| GET | `/vendors` | Lista todas las tiendas activas | Ninguno |
| GET | `/inventory` | Lista productos con stock > 0 | `?vendor_id=X` (Opcional) |

### Private Endpoints (Requiere Auth/Nonce)
| Método | Endpoint | Descripción | Body Payload |
|--------|----------|-------------|--------------|
| POST | `/orders` | Crea una nueva orden | `{ "vendor_id": 1, "items": [{"product_id": 1, "quantity": 2}] }` |
| GET | `/vendor/stats`| Dashboard de ventas | Ninguno (Toma usuario actual) |

## 4. Seguridad
- **Authentication**: Utiliza Cookies/Nonce de WordPress para sesiones web y se recomienda JWT Auth para la App Móvil futura.
- **Validation**: Todos los inputs SQL pasan por `$wpdb->prepare()`.
- **CORS**: Configurado en `functions.php` para permitir conexiones externas (Mobile App).

## 5. Configuración del Entorno
1. Instalar WordPress en `/WordPress`.
2. Activar tema `automatiza-tech`.
3. Ejecutar script SQL `Base_de_datos/ScriptInicial.sql`.
4. Configurar permalinks en WP Admin a "Post Name" (`/%postname%/`) para habilitar la API REST.
