# QA-06 — Dashboard del Rider
**Proyecto:** PetsGo  
**Módulo:** Panel de Repartidor  
**Versión:** 1.0  
**Fecha:** 2026-02-19  
**Roles cubiertos:** Rider (petsgo_rider)  
**URL:** `/rider`

---

## 1. REGISTRO Y ONBOARDING DE RIDER

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-001 | Completar registro rider multi-paso | N/A | 1. Ir a `/registro-rider` 2. Paso 1: datos personales + tipo vehículo 3. Paso 2: código de verificación de email | Cuenta creada con status `pending_docs` | Alta |
| RD-002 | Subir documentos requeridos | Status `pending_docs` | 1. Login como rider 2. Dashboard → Documentos 3. Subir carnet de identidad (anverso/reverso) 4. Subir licencia de conducir (si aplica) | Documentos subidos, status cambia a `pending_review` | Alta |
| RD-003 | Vista de "espera de aprobación" | Status `pending_review` | 1. Login como rider pendiente | Mensaje de espera, sin acceso a pedidos | Alta |
| RD-004 | Rider aprobado tiene acceso completo | Status `approved` | 1. Admin aprueba rider 2. Login como rider | Acceso completo al dashboard de pedidos y entregas | Alta |
| RD-005 | Rider rechazado ve motivo | Status `rejected` | 1. Admin rechaza con motivo 2. Login como rider | Motivo de rechazo visible, opción de reenviar documentos | Alta |
| RD-006 | Tipos de vehículo válidos | Formulario de registro | 1. Verificar selector | Opciones: bicicleta, scooter, moto, auto, a_pie | Media |

---

## 2. ACCESO Y AUTENTICACIÓN

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-020 | Acceso exitoso con rider aprobado | Rider `approved` | 1. Login 2. Navegar a `/rider` | Dashboard carga con pedidos disponibles | Alta |
| RD-021 | Sin sesión redirige a login | Sin sesión | 1. Navegar a `/rider` | Redirige a `/login` | Alta |
| RD-022 | Refresh no expulsa a login | Rider logueado | 1. Presionar F5 en `/rider` | Dashboard vuelve a cargar (fix `authLoading`) | Alta |
| RD-023 | Cliente no accede a `/rider` | Sesión cliente | 1. Navegar a `/rider` | Redirige a `/login` | Alta |

---

## 3. DISPONIBILIDAD Y ESTADO DEL RIDER

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-040 | Activar disponibilidad (toggle "online") | Rider aprobado | 1. Click toggle "Estoy disponible" | Estado cambia a `online`, aparece para recibir pedidos | Alta |
| RD-041 | Desactivar disponibilidad | Rider `online` | 1. Click toggle para desactivar | Estado `offline`, no recibe nuevos pedidos | Alta |
| RD-042 | Rider offline no ve pedidos disponibles | Rider `offline` | 1. Verificar lista de pedidos | Lista vacía o botón deshabilitado con mensaje "Activa tu disponibilidad" | Alta |

---

## 4. PEDIDOS DISPONIBLES

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-060 | Ver pedidos disponibles para tomar | Rider `online`, pedidos `ready_for_pickup` | 1. Ver tab "Disponibles" | Lista de pedidos listos con dirección de recogida y entrega | Alta |
| RD-061 | Ver detalle de pedido antes de aceptar | Pedido disponible | 1. Click en pedido | Distancia, dirección origen, dirección destino, monto de pago por entrega | Alta |
| RD-062 | Aceptar pedido | Pedido disponible | 1. Click "Aceptar Pedido" | Pedido asignado al rider, desaparece de lista de disponibles | Alta |
| RD-063 | Pedido ya tomado por otro rider | 2 riders intentan tomar mismo pedido | 1. A acepta primero 2. B intenta aceptar | B recibe error "Este pedido ya fue tomado" | Alta |
| RD-064 | Solo ver pedidos de la zona del rider | Pedidos en diferentes comunas | 1. Ver lista de disponibles | Solo pedidos dentro del rango geográfico del rider | Media |

---

## 5. GESTIÓN DE ENTREGAS ACTIVAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-080 | Ver mis entregas activas | Pedido aceptado | 1. Ver tab "Mis Entregas" | Pedido activo con dirección, mapa, datos del cliente (si habilitado) | Alta |
| RD-081 | Marcar "Recogido del vendor" | Pedido aceptado | 1. Click "Marcar como recogido" | Estado del pedido: `picked_up`, vendor notificado | Alta |
| RD-082 | Marcar "En camino" | Pedido recogido | 1. Click "En camino" | Estado: `on_the_way`, cliente notificado | Alta |
| RD-083 | Confirmar entrega exitosa | En destino | 1. Click "Entrega completada" | Estado: `delivered`, ganancias actualizadas | Alta |
| RD-084 | Reportar problema en entrega | Cualquier estado activo | 1. Click "Reportar problema" 2. Seleccionar tipo (dirección incorrecta, cliente ausente, etc.) 3. Enviar | Problema registrado, caso escalado a soporte | Alta |
| RD-085 | Cancelar entrega aceptada | Pedido aceptado | 1. Click "Cancelar entrega" 2. Ingresar motivo | Pedido devuelto a disponibles, motivo registrado | Alta |

---

## 6. HISTORIAL DE ENTREGAS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-100 | Ver historial completo de entregas | Rider con historial | 1. Ver tab "Historial" | Lista de todas las entregas completadas | Alta |
| RD-101 | Detalle de entrega pasada | Entrega completada | 1. Click en entrega del historial | Fecha, dirección, monto, estado final | Media |
| RD-102 | Filtrar historial por fecha | Historial con entradas | 1. Seleccionar rango de fechas | Solo entregas del período | Media |
| RD-103 | Estadísticas resumen | 10+ entregas | 1. Ver sección de stats | Total entregas, tasa de éxito, ganancias totales del período | Media |

---

## 7. GANANCIAS Y PAGOS

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-120 | Ver ganancias del período | Entregas completadas | 1. Ver tab "Ganancias" | Total ganado en período, desglose por entrega | Alta |
| RD-121 | Ver saldo acumulado | Ganancias sin retirar | 1. Ver saldo disponible | Monto correcto | Alta |
| RD-122 | Solicitar pago/retiro | Saldo > mínimo | 1. Click "Solicitar Pago" 2. Confirmar | Solicitud creada, admin notificado | Alta |
| RD-123 | Monto por entrega visible | Entrega completada | 1. Ver en historial | Monto específico por cada entrega | Alta |
| RD-124 | Retiro menor al saldo | Saldo $10.000 | 1. Solicitar retiro de $15.000 | Error "Saldo insuficiente" | Alta |

---

## 8. DOCUMENTOS Y PERFIL DEL RIDER

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| RD-140 | Ver estado de documentos | Rider con docs enviados | 1. Ver tab "Documentos" o perfil | Estado de cada documento (pendiente/aprobado/rechazado) | Alta |
| RD-141 | Reenviar documento rechazado | Documento `rejected` | 1. Click "Reenviar" en doc rechazado 2. Subir nuevo archivo | Nuevo documento enviado, estado `under_review` | Alta |
| RD-142 | Editar datos personales del rider | Rider aprobado | 1. Editar teléfono, vehículo 2. Guardar | Datos actualizados | Media |
| RD-143 | Ver tipo de vehículo registrado | N/A | 1. Ver perfil | Tipo de vehículo actual visible | Baja |
