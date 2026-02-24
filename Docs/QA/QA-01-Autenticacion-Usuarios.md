# QA-01 — Autenticación y Gestión de Usuarios
**Proyecto:** PetsGo  
**Módulo:** Autenticación, Registro, Perfil  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Cliente, Rider, Vendor, Admin, Soporte

---

## 1. REGISTRO DE CLIENTE

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-001 | Registro exitoso con todos los campos | Usuario no registrado | 1. Ir a `/registro` 2. Completar nombre, apellido, email, contraseña, RUT, teléfono, fecha nacimiento, región, comuna 3. Hacer click en Registrar | Usuario creado, token en localStorage, redirección a `/` | Alta |
| AU-002 | Registro con email ya existente | Email ya registrado en BD | 1. Completar formulario con email duplicado 2. Enviar | Mensaje de error "El email ya está registrado" | Alta |
| AU-003 | Registro con RUT inválido | N/A | 1. Ingresar RUT con formato incorrecto (ej: 12345678) | Validación de formato RUT falla, no se envía formulario | Alta |
| AU-004 | Registro con contraseña débil (< 6 caracteres) | N/A | 1. Ingresar contraseña de 3 caracteres | Error de validación de longitud mínima | Media |
| AU-005 | Registro con email inválido | N/A | 1. Ingresar "texto_sin_arroba" en campo email | Validación HTML5/frontend rechaza el email | Media |
| AU-006 | Registro con campos obligatorios vacíos | N/A | 1. Enviar formulario vacío | Todos los campos requeridos muestran error | Alta |
| AU-007 | Registro con teléfono en formato +56 9 XXXX XXXX | N/A | 1. Ingresar "+56 9 1234 5678" 2. Enviar | Registro exitoso | Media |
| AU-008 | Rate limiting en registro | N/A | 1. Intentar registrar 10 veces seguidas con el mismo email | Cooldown activado, no permite nuevos intentos | Baja |

---

## 2. REGISTRO DE RIDER (MULTI-PASO)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-020 | Registro rider paso 1 completo | N/A | 1. Ir a `/registro-rider` 2. Completar todos los datos personales + tipo de vehículo 3. Click en Continuar | Avanza a Paso 2 (verificación de email) | Alta |
| AU-021 | Verificación de email correcta | Paso 1 completado | 1. Ingresar código de 6 dígitos recibido por email | Status cambia a `pending_docs`, redirección al dashboard rider | Alta |
| AU-022 | Código de verificación incorrecto | Código enviado al email | 1. Ingresar código erróneo | Error "Código incorrecto o expirado" | Alta |
| AU-023 | Reenvío de código de verificación | Paso 2 activo | 1. Click en "Reenviar código" | Nuevo código enviado al email, botón en cooldown | Media |
| AU-024 | Tipos de vehículo permitidos | N/A | 1. Verificar que selector muestre: bicicleta, scooter, moto, auto, a_pie | Todos los tipos visibles y seleccionables | Media |
| AU-025 | Email de rider ya registrado | Email existente como rider | 1. Intentar registro con mismo email | Error específico de duplicado | Alta |

---

## 3. LOGIN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-040 | Login exitoso como cliente | Usuario registrado | 1. Ir a `/login` 2. Ingresar email + contraseña 3. Click Login | Token guardado en localStorage, redirección a `/` | Alta |
| AU-041 | Login exitoso como vendor | Vendor activo | 1. Login con credenciales de vendor | Token guardado, acceso a `/vendor` disponible | Alta |
| AU-042 | Login exitoso como rider aprobado | Rider con status `approved` | 1. Login con credenciales de rider | Redirección a `/rider` | Alta |
| AU-043 | Login con contraseña incorrecta | Usuario existente | 1. Ingresar contraseña errónea | Error "Credenciales incorrectas" | Alta |
| AU-044 | Login con email no registrado | N/A | 1. Ingresar email inexistente | Error "Credenciales incorrectas" | Alta |
| AU-045 | Login con campos vacíos | N/A | 1. Enviar formulario vacío | Validación frontend impide envío | Media |
| AU-046 | Login como admin en frontend | Admin WordPress | 1. Login con credenciales de administrator | Token obtenido, acceso a dashboards | Alta |
| AU-047 | Persistencia de sesión al refrescar | Sesión activa | 1. Refrescar página F5 | Sesión se mantiene, no redirige a login | Alta |
| AU-048 | Logout limpia sesión | Sesión activa | 1. Click en Cerrar Sesión | localStorage limpio, usuario deslogueado, toast confirmación | Alta |
| AU-049 | Token expirado redirige a login | Token expirado en BD | 1. Con token inválido hacer petición autenticada | 401 capturado por interceptor, redirige a `/login` | Alta |

---

## 4. RECUPERACIÓN DE CONTRASEÑA

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-060 | Solicitar reset con email válido | Usuario registrado | 1. Ir a `/forgot-password` 2. Ingresar email 3. Enviar | Email de recuperación enviado, mensaje de confirmación | Alta |
| AU-061 | Solicitar reset con email inexistente | N/A | 1. Ingresar email no registrado | Error "Email no encontrado" | Alta |
| AU-062 | Resetear contraseña con token válido | Email de reset recibido | 1. Click en link del email 2. Ingresar nueva contraseña 2 veces 3. Confirmar | Contraseña actualizada, login funciona con nueva contraseña | Alta |
| AU-063 | Resetear contraseña con token expirado | Token > 1h | 1. Usar link de más de 1 hora | Error "Token expirado o inválido" | Alta |
| AU-064 | Contraseñas no coinciden en reset | Token válido | 1. Ingresar contraseñas diferentes | Error de validación de coincidencia | Media |

---

## 5. PERFIL DE USUARIO

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-080 | Ver perfil propio | Sesión activa | 1. Ir a `/perfil` | Datos del usuario cargados correctamente | Alta |
| AU-081 | Editar nombre y teléfono | Sesión activa | 1. Modificar nombre y teléfono 2. Guardar | Cambios persistidos, toast de éxito | Alta |
| AU-082 | Cambiar contraseña correctamente | Sesión activa | 1. Ingresar contraseña actual 2. Nueva contraseña 3. Confirmar | Contraseña cambiada exitosamente | Alta |
| AU-083 | Cambiar contraseña con actual incorrecta | Sesión activa | 1. Ingresar contraseña actual equivocada | Error "Contraseña actual incorrecta" | Alta |
| AU-084 | Acceso a `/perfil` sin sesión | Sin sesión | 1. Ir directamente a `/perfil` | Redirige a `/login` | Alta |

---

## 6. MASCOTAS (PETS)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-100 | Agregar mascota nueva | Sesión cliente activa | 1. En perfil, click "Agregar mascota" 2. Completar nombre, tipo, raza, fecha nacimiento, peso, notas 3. Guardar | Mascota creada y visible en lista | Alta |
| AU-101 | Tipos de mascota disponibles | N/A | 1. Verificar selector de tipo | Opciones: perro, gato, ave, conejo, hamster, pez, reptil, otro | Media |
| AU-102 | Subir foto de mascota | Mascota creada | 1. Click en foto de mascota 2. Seleccionar imagen 3. Subir | Foto actualizada, URL retornada | Media |
| AU-103 | Editar mascota existente | Al menos 1 mascota | 1. Click editar en mascota 2. Modificar datos 3. Guardar | Datos actualizados | Alta |
| AU-104 | Eliminar mascota | Al menos 1 mascota | 1. Click eliminar 2. Confirmar | Mascota eliminada de la lista | Alta |
| AU-105 | Agregar múltiples mascotas | Sesión activa | 1. Agregar 3+ mascotas | Todas se muestran en la lista | Media |

---

## 7. SEGURIDAD Y PERMISOS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-120 | Cliente intenta acceder a `/vendor` | Sesión como cliente | 1. Navegar a `/vendor` | Redirige a `/login` o muestra acceso denegado | Alta |
| AU-121 | Cliente intenta acceder a `/rider` | Sesión como cliente | 1. Navegar a `/rider` | Redirige a `/login` | Alta |
| AU-122 | Cliente intenta acceder a `/admin` | Sesión como cliente | 1. Navegar a `/admin` | Redirige a `/login` | Alta |
| AU-123 | Rider no aprobado ve pantalla de espera | Rider `pending_review` | 1. Login como rider pendiente | Dashboard muestra estado de revisión, sin acceso a funciones | Alta |
| AU-124 | Vendor inactivo ve aviso | Vendor `inactive` | 1. Login como vendor inactivo | VendorDashboard muestra pantalla "cuenta inactiva" | Alta |
| AU-125 | Admin puede acceder a todos los módulos | Sesión admin | 1. Navegar a `/vendor`, `/rider`, `/admin` | Acceso a todos los dashboards | Alta |
| AU-126 | Token PetsGo tiene prioridad sobre cookie WP | Admin logueado en wp-admin + cliente en frontend | 1. Abrir frontend como cliente con token activo | API devuelve datos del cliente (no del admin) | Alta |
