# Manual de Usuario - PetsGo Marketplace

Este manual define los procesos operativos para cada rol en la plataforma.

## 1. Administrador (Superusuario)
**Persona**: Alexiandra Andrade

### Tareas Comunes:
- **Auditar Tiendas (Impersonate)**: Accede al Dashboard Global, selecciona una tienda de la lista y visualiza sus métricas como si fueras el dueño.
- **Configurar Comisiones**:
    1. Ve a "Ajustes Globales".
    2. Modifica el % de "Sales Commission" o "Delivery Fee Cut".
    3. Guarda los cambios. Afectará a los próximos pedidos.
- **Gestión de Planes**: Crea o edita planes de suscripción para vendedores.

## 2. Tienda (Vendor)
** Acceso**: Panel de Vendedor (`/vendor-dashboard`)

### Gestión de Inventario:
1. Navega a la pestaña "Mis Productos".
2. Haz clic en "Agregar Producto".
3. Rellena: Nombre, Precio, Stock Inicial y Categoría.
4. Sube una foto (se guardará en la biblioteca de medios del sistema).

### Procesar Pedidos:
1. Recibirás una notificación en "Pedidos Pendientes".
2. Empaca el producto.
3. Cambia el estado a "Listo para enviar".
4. Asigna un Rider desde el panel de Delivery (o el admin lo hará).
5. El Rider acepta y el estado cambia a "Asignado a Rider".

## 3. Cliente (User)
**Acceso**: Web Principal o App Móvil

### Realizar Compra:
1. Navega por el marketplace o busca un producto.
2. Agrega productos al carrito (puedes mezclar tiendas, pero se generan órdenes separadas).
3. Ve al Checkout y confirma pago.
4. Sigue el estado en "Mis Compras".

### Uso del Asistente IA:
1. Haz clic en el ícono de Huella (PawPrint).
2. Escribe tu consulta (ej: "Busco comida de perro barata").
3. El bot sugerirá productos con stock real.

## 4. Delivery (Rider)
**Acceso**: Dashboard Rider (`/rider`)

### Entrega de Pedidos:
1. Activa tu disponibilidad (toggle Online/Offline en tu dashboard).
2. Acepta una asignación del admin o toma un pedido disponible con "Tomar Pedido".
3. El estado del pedido cambia a "Asignado a Rider".
4. Ve a la tienda (dirección en pantalla). Presiona "Iniciar Entrega" → estado pasa a "En camino".
5. Al entregar al cliente, marca "Entregado" para liberar tu pago.

## 5. Soporte (Moderador)
**Acceso**: Panel de Administración (Restringido)

### Intervención Chatbot:
1. Si el Bot no puede responder, la conversación se marca como "Requiere Humano".
2. Entra al chat y responde directamente al cliente.
3. Cierra el ticket al resolver.
