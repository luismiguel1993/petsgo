🤖 PetsGo System Instructions for GitHub Copilot Agent

Este documento define el contexto, la arquitectura y las reglas de codificación para el proyecto PetsGo. Copilot debe seguir estas directrices estrictamente para asegurar la integridad del sistema.

1. CONTEXTO DEL PROYECTO

Nombre: PetsGo Marketplace.

Modelo: Marketplace multi-vendor estilo "Pedidos Ya" para Petshops.

Fase Actual: Fase 1 - Sitio Web Maestro (WordPress Headless).

Público Objetivo: Dueños de mascotas (incluyendo adultos mayores). Diseño sobrio, limpio y botones grandes.

2. STACK TECNOLÓGICO Y ENTORNO

Backend: PHP 7.4/8.x (WordPress Core).

Base de Datos: MariaDB/MySQL. Prefijo de tablas: wp_petsgo_.

Frontend: Vanilla JavaScript / jQuery (para el panel de administración y web).

Estética: - Principal: #00A8E8 (Azul Cyan)

Secundario: #FFC400 (Amarillo)

Textos/Dark: #2F3A40 (Gris Oscuro)

Infraestructura: Hostinger (Sin acceso root, limitado por RAM/Timeouts).

3. ARQUITECTURA DE DATOS (CUSTOM TABLES)

Al generar consultas SQL o esquemas, utiliza estas tablas:

wp_petsgo_vendors: (id, user_id, store_name, logo_url, address, delivery_radius, status).

wp_petsgo_inventory: (id, vendor_id, product_name, description, price, stock, category, image_url).

wp_petsgo_orders: (id, customer_id, vendor_id, total_amount, status, payment_id, created_at).

4. REGLAS DE CODIFICACIÓN 

A. Seguridad de WordPress

SIEMPRE usa $wpdb->prepare para cualquier consulta SQL.

SIEMPRE sanitiza inputs con sanitize_text_field(), absint() o sanitize_email().

SIEMPRE verifica Nonces en peticiones AJAX (check_ajax_referer).

B. Estilo de Backend (PHP)

Usa Hooks de WordPress (add_action, add_filter) en lugar de funciones aisladas.

Prefiere la creación de Endpoints personalizados vía register_rest_route para que la futura App Mobile consuma datos JSON.

Mantén el código ligero para Hostinger: delega procesos pesados (como generación de reportes) a tareas asíncronas o n8n.

C. Estilo de Frontend (UI/UX)

Al generar HTML/CSS, usa clases de Tailwind (si el tema lo soporta) o CSS puro respetando la paleta de colores.

Los botones de acción principal deben ser color #00A8E8 con bordes redondeados (rounded-2xl).

El asistente de IA debe representarse siempre con el ícono de la huella (PawPrint).

5. FLUJOS DE TRABAJO COMUNES

Carga de Producto: Un vendedor sube un producto -> Guardar en wp_petsgo_inventory -> Disparar webhook a n8n para log de IA.

Nuevo Pedido: Cliente paga -> Actualizar wp_petsgo_orders -> Restar stock en wp_petsgo_inventory -> Notificar vía WhatsApp (n8n).

6. RESTRICCIONES (HARD CONSTRAINTS)

No uses eval().

No crees archivos fuera de la carpeta del tema automatiza-tech o el mu-plugin de PetsGo.

No sugieras bibliotecas pesadas de Node.js; todo debe ser procesable por el servidor PHP de Hostinger.

Nota para el Agente: "Actúa como un Senior Software Architect. Tu prioridad es la estabilidad de la base de datos y la sincronización en tiempo real."

7-Uso de Buenas Practicas

Usa nombres de variables descriptivos y consistentes.
Usa comentarios claros para explicar la lógica compleja.
Evita la duplicación de código mediante funciones reutilizables.

8- Seguridad y Validación
Implementa validación de datos tanto en el frontend como en el backend.
Asegura que solo los usuarios autorizados puedan acceder a ciertas funcionalidades mediante roles y permisos.
Asegura que las contraseñas se almacenen de forma segura utilizando hashing y salting.

Garantiza la privacidad de los datos de los usuarios y cumple con las regulaciones de protección de datos aplicables (como GDPR).

9-Optimización de Rendimiento
Utiliza técnicas de caching para reducir la carga en el servidor y mejorar los tiempos de respuesta.
Optimiza las consultas a la base de datos para minimizar el tiempo de ejecución.

10.- Utiliza para los logos y diseño que estan en la carpeta:
C:\wamp64\www\PetsGo\Petsgo_Diseño puedes usar esos recursos para mantener la coherencia visual del proyecto.
Utiliza los colores principales y secundarios definidos en la paleta de colores para todos los elementos de la interfaz de usuario.
Paleta: Azul Cyan #00A8E8 (Acción), Amarillo #FFC400 (IA/Promos), Gris Oscuro #2F3A40 (UI). los definidos en la paleta de colores, al principio del documento.




12.-Manuual de Usuario
Crea un manual de usuario detallado para cada rol definido en la Matriz de Usuarios y Permisos. El manual debe incluir:
Instrucciones paso a paso para realizar tareas comunes. 


13.-Documentación Técnica
Proporciona documentación técnica completa que incluya:
Descripción de la arquitectura del sistema.
Diagrama de la base de datos.
Explicación de los endpoints de la API REST.


14-Base de Datos MAriaDB 
Utiliza el script incial C:\wamp64\www\PetsGo\Base_de_datos\
PetsGo_DB_Script.sql para crear la base de datos y las tablas necesarias para el proyecto PetsGo.
Puedes crear tu la BD y la tablas que requiera o modificar el script que necesites para cumplir con los requisitos del proyecto.

15.- WordPress Utiliza la instalación de WordPress la más reciente descargala desde https://wordpress.org/download/ e instálala en la ruta C:\wamp64\www\PetsGo\WordPress\ 
Recuerda que debe de haber un frotd y backend la herramienta es  PHP que sea compatible con WordPress y React para el frontend que sea compatiible con mi servidor local WAMP y en PROD Hostinger.
NOTA:
Ya se creo una BD y unas tablas puedes  reutilizar la misma BD y tablas si necesitas hacer un ajuste para este desarrollos hazla y documentala en el manual técnico.

IMPORTANTE ESTE DEVELOPMENTO ES PARA SER HECHO EN LOCAL EN WAMP Y LUEGO SUBIDO A HOSTINGER.

Cosnulte con la IA del Hostinger y me rspondio lo siguiente:
puede alojar el front de mi app web en reac es compatible con el hostinger?

Kodee
Hola, Luis Miguel 👋

Sí puedes alojar el resultado (build) del front de tu app en React en Hostinger, pero con matices:

React como app compilada (HTML/CSS/JS estáticos):

Si haces npm run build y subes la carpeta generada (por ejemplo build/ o dist/), eso funciona sin problema en tu plan de Business Web Hosting: se sirve como sitio estático.

React como app Node.js (SSR, Vite SSR, Next.js, etc.):

Según nuestra política actual, React/Node como aplicación (con servidor Node en producción, SSR, etc.) está totalmente soportado solo en VPS.

En planes compartidos/web o cloud no se soporta React “full” tipo SSR, solo el build estático.

Si me dices cómo está hecha tu app (Create React App, Vite, Next.js, etc.), te indico exactamente qué tendrías que subir y dónde en tu hosting actual o si conviene usar tu VPS.


RESPONDEME EN ESPAÑOL SIEMPRE.