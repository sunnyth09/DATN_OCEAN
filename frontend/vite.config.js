import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  envDir: '../',
  envPrefix: ['VITE_', 'BANK_'],
  plugins: [
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    allowedHosts: true, // Cho phép mọi tên miền/tunnel (như oceansport.bcbdev.id.vn, ngrok, cloudflare) truy cập dev server
    watch: {
      usePolling: true,
      // Giảm polling interval → giảm CPU usage trong Docker
      interval: 1000,
      ignored: ['**/.env', '**/node_modules/**'],
    },
    host: true,
    strictPort: true,
    port: 3302,
    // HMR qua WebSocket → hot reload nhanh hơn
    hmr: {
      overlay: true,
    },
  },
  build: {
    // Xóa sạch thư mục dist/ trước mỗi lần build
    emptyOutDir: true,
    // Tách code thành nhiều chunk nhỏ
    rollupOptions: {
      output: {
        manualChunks: {
          // Tách vendor libs ra chunk riêng → cache lâu dài
          'vendor-vue': ['vue', 'vue-router'],
          'vendor-axios': ['axios'],
        },
      },
    },
    // Kích thước chunk cảnh báo
    chunkSizeWarningLimit: 500,
    // Minify bằng esbuild (nhanh hơn terser)
    minify: 'esbuild',
    // Tạo source map cho debug (tắt khi production thật)
    sourcemap: false,
    // CSS code splitting
    cssCodeSplit: true,
  },
  // Tối ưu dependencies pre-bundling
  optimizeDeps: {
    include: ['vue', 'vue-router', 'axios'],
  },
})
