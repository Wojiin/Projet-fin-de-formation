import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

// Les DevTools restent disponibles sur demande, mais ne pénalisent plus le chargement courant.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const enableDevTools = env.VITE_ENABLE_DEVTOOLS === 'true'

  return {
    plugins: [vue(), tailwindcss(), ...(enableDevTools ? [vueDevTools()] : [])],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
  }
})
