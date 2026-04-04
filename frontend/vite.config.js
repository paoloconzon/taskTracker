import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd())
  return {
  base: env.VITE_BASE_PATH ?? '/',
  plugins: [
    vue(),
    vuetify({ autoImport: true }),
  ],
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1/tasktracker/backend/',   // ← cambia con il tuo server PHP
        changeOrigin: true,
        rewrite: path => path.replace(/^\/api/, '')
      }
    }
  }
}
})
