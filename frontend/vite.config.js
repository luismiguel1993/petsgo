import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// PetsGo Marketplace - Vite Config
// El proxy solo aplica en desarrollo (Vite dev server).
// En producción el build estático se sirve desde el mismo dominio.
export default defineConfig(({ mode }) => {
  const isDev = mode === 'development';

  return {
    plugins: [react(), tailwindcss()],

    // En producción la app se sirve desde la raíz del dominio
    base: '/',

    server: {
      port: 5173,
      // Proxy solo para desarrollo local (WAMP)
      proxy: isDev
        ? {
            '/wp-json': {
              target: 'http://localhost/PetsGoDev',
              changeOrigin: true,
              cookieDomainRewrite: '',
              cookiePathRewrite: '/',
            },
            '/wp-content/uploads': {
              target: 'http://localhost/PetsGoDev',
              changeOrigin: true,
            },
          }
        : undefined,
    },

    build: {
      outDir: 'dist',
      sourcemap: false,
    },
  };
})
