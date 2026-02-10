import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// PetsGo Marketplace - Vite Config
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    // Proxy para evitar CORS en desarrollo
    proxy: {
      '/wp-json': {
        target: 'http://localhost/PetsGo/WordPress',
        changeOrigin: true,
      },
    },
  },
})
