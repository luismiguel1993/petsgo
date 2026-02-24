# Guía: Convertir PetsGo a App Móvil con Capacitor
**Proyecto:** PetsGo  
**Framework:** React + Vite  
**Tool:** Capacitor v6  
**Fecha:** 2026-02-23

---

## 💰 COSTOS

| Concepto | Costo |
|---|---|
| Capacitor (open source) | **Gratis** |
| Android Studio | **Gratis** |
| Xcode (solo Mac) | **Gratis** |
| Google Play Store (cuenta developer) | **$25 USD — pago único** |
| Apple App Store (cuenta developer) | **$99 USD/año** |
| Mac para compilar iOS | Obligatorio (no se puede en Windows) |

> **Resumen:** Android = $25 USD una vez. iOS = $99 USD/año + necesitas Mac.

---

## 📋 REQUISITOS PREVIOS

### Para Android (Windows ✅)
- Node.js 18+
- Java JDK 17+ → https://adoptium.net/
- Android Studio → https://developer.android.com/studio
- Variable de entorno `ANDROID_HOME` configurada

### Para iOS (Solo macOS ❌ Windows no puede)
- Mac con macOS 13+
- Xcode 15+ (desde App Store, gratis)
- Cuenta Apple Developer ($99/año) → https://developer.apple.com
- CocoaPods: `sudo gem install cocoapods`

---

## 🤖 PARTE 1 — ANDROID

### Paso 1: Instalar dependencias de Capacitor

```powershell
cd c:\wamp64\www\PetsGoDev\frontend
npm install @capacitor/core @capacitor/cli
npm install @capacitor/android
```

### Paso 2: Inicializar Capacitor en el proyecto

```powershell
npx cap init "PetsGo" "com.petsgo.app" --web-dir=dist
```

Esto crea el archivo `capacitor.config.ts` en la raíz del frontend.

### Paso 3: Configurar capacitor.config.ts

Editar el archivo generado para apuntar a tu API de producción:

```typescript
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.petsgo.app',
  appName: 'PetsGo',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
    // Descomentar SOLO en desarrollo local:
    // url: 'http://10.0.2.2/PetsGoDev', // <- IP especial de emulador Android para localhost
    // cleartext: true,
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      backgroundColor: '#00A8E8',
      showSpinner: false,
    },
    StatusBar: {
      style: 'LIGHT',
      backgroundColor: '#00A8E8',
    },
  },
};

export default config;
```

> ⚠️ En producción la app conecta a `https://petsgo.cl` (tu API real). No necesitas cambiar las URLs del código porque ya usan la URL de producción.

### Paso 4: Hacer el build del frontend

```powershell
npm run build
```

### Paso 5: Agregar plataforma Android

```powershell
npx cap add android
```

Esto crea la carpeta `android/` en el proyecto.

### Paso 6: Sincronizar archivos

```powershell
npx cap sync android
```

Ejecutar este comando **cada vez que hagas cambios** en el frontend.

### Paso 7: Configurar íconos y splash screen

Instalar el plugin de assets:
```powershell
npm install @capacitor/assets -D
```

Crear la carpeta y poner tus imágenes:
```
frontend/
  assets/
    icon.png         (1024x1024px — logo PetsGo sin fondo)
    splash.png       (2732x2732px — fondo azul #00A8E8 con logo centrado)
    icon-foreground.png  (1024x1024px — solo el ícono, para Android adaptive)
    icon-background.png  (1024x1024px — fondo color)
```

Generar todos los tamaños automáticamente:
```powershell
npx @capacitor/assets generate --android
```

### Paso 8: Abrir en Android Studio

```powershell
npx cap open android
```

Android Studio se abre. Esperar que termine de indexar (puede tardar 5 min la primera vez).

### Paso 9: Ejecutar en emulador o dispositivo físico

**En emulador:**
1. En Android Studio → Tools → Device Manager → Create Virtual Device
2. Elegir Pixel 7, API 34 (Android 14)
3. Click ▶ Run

**En dispositivo físico:**
1. Activar "Opciones de desarrollador" en el teléfono (toca 7 veces el número de build)
2. Activar "Depuración USB"
3. Conectar por USB
4. Click ▶ Run en Android Studio

### Paso 10: Generar APK / AAB para publicar

En Android Studio:
1. `Build` → `Generate Signed Bundle / APK`
2. Elegir **Android App Bundle (.aab)** (requerido por Play Store)
3. Crear un keystore nuevo (guardarlo en lugar seguro — **nunca perderlo**)
4. Completar los datos y generar

El archivo `.aab` se guarda en `android/app/release/`

### Paso 11: Publicar en Google Play Store

1. Ir a https://play.google.com/console
2. Crear cuenta developer ($25 USD, una sola vez)
3. Crear nueva app → "PetsGo"
4. Completar ficha: descripción, capturas de pantalla, categoría (Shopping)
5. Subir el archivo `.aab`
6. Configurar precios (si es gratis, marcar como gratuita)
7. Enviar a revisión (generalmente 1-3 días hábiles)

---

## 🍎 PARTE 2 — iOS (Requiere Mac)

> Los pasos 1-4 son iguales que Android. Si ya los hiciste, continúa desde el Paso 5.

### Paso 5: Agregar plataforma iOS (desde Mac)

```bash
npx cap add ios
```

Esto crea la carpeta `ios/` en el proyecto.

### Paso 6: Instalar dependencias de CocoaPods

```bash
cd ios/App
pod install
cd ../..
```

### Paso 7: Sincronizar archivos

```bash
npm run build
npx cap sync ios
```

### Paso 8: Configurar íconos para iOS

```bash
npx @capacitor/assets generate --ios
```

### Paso 9: Abrir en Xcode

```bash
npx cap open ios
```

### Paso 10: Configurar el proyecto en Xcode

1. Click en el proyecto `App` en el panel izquierdo
2. En `Signing & Capabilities`:
   - Team: seleccionar tu Apple Developer account
   - Bundle Identifier: `com.petsgo.app`
3. Verificar que `Deployment Target` sea iOS 14+

### Paso 11: Ejecutar en simulador o dispositivo

**Simulador:**
- Seleccionar `iPhone 15` en el menú de dispositivos
- Click ▶ Run

**Dispositivo físico:**
1. Conectar iPhone por USB
2. Confiar en el Mac desde el iPhone
3. Seleccionar el iPhone en el menú de dispositivos
4. Click ▶ Run

### Paso 12: Generar archivo para App Store (.ipa)

1. `Product` → `Archive`
2. Al terminar abre el `Organizer`
3. Click `Distribute App`
4. Seleccionar `App Store Connect`
5. Subir automáticamente

### Paso 13: Publicar en App Store

1. Ir a https://appstoreconnect.apple.com
2. Crear nueva app → "PetsGo"
3. Completar ficha: descripción, keywords, categoría (Shopping)
4. Subir capturas de pantalla (iPhone 6.7", 6.5", iPad 12.9" opcional)
5. El build subido desde Xcode aparece en "Builds"
6. Seleccionarlo y enviar a revisión
7. Revisión de Apple: **3-7 días hábiles** (más estrictos que Google)

---

## 🔄 FLUJO DE ACTUALIZACIÓN

Cada vez que hagas cambios en el frontend:

```powershell
# 1. Build
npm run build

# 2. Sincronizar
npx cap sync

# 3. Para Android: abrir Android Studio y generar nuevo .aab
# 4. Para iOS (Mac): abrir Xcode y hacer Archive nuevamente
# 5. Subir nueva versión a las tiendas (incrementar version en package.json)
```

---

## 📁 ESTRUCTURA FINAL DEL PROYECTO

```
frontend/
  android/          ← Proyecto Android Studio (NO subir a Git sin .gitignore)
  ios/              ← Proyecto Xcode (NO subir a Git sin .gitignore)
  dist/             ← Build del frontend (generado)
  src/              ← Tu código React
  capacitor.config.ts
  assets/
    icon.png
    splash.png
```

Agregar al `.gitignore`:
```
android/
ios/
```

---

## ⚠️ CONSIDERACIONES IMPORTANTES

1. **API CORS:** Verificar que `petsgo.cl` tenga CORS configurado para aceptar requests desde la app móvil (`capacitor://localhost` y `https://localhost`).

2. **HTTP vs HTTPS:** Las apps móviles bloquean HTTP en producción. Tu API debe estar en HTTPS (ya lo está si usas Hostinger con SSL).

3. **Deep Links:** Para links de verificación de boleta (`/verificar-boleta/:token`) necesitas configurar App Links (Android) y Universal Links (iOS) adicional.

4. **Notificaciones Push:** Si se quieren agregar después, usar `@capacitor/push-notifications`.

5. **Versiones:** Cada update a las tiendas requiere incrementar la versión:
   - `package.json` → version: "1.0.1"
   - Android: `android/app/build.gradle` → versionCode + versionName
   - iOS: Xcode → Version + Build number

---

## 🗺️ RESUMEN DE TIEMPOS ESTIMADOS

| Tarea | Tiempo estimado |
|---|---|
| Configurar Capacitor + Android Studio | 2-3 horas |
| Primer build Android funcionando | 1-2 horas |
| Publicar en Google Play | 1 hora + 1-3 días revisión |
| Configurar iOS en Mac | 2-3 horas |
| Publicar en App Store | 1 hora + 3-7 días revisión |
| **Total hasta tener ambas apps publicadas** | **~2 semanas** (con revisiones) |
