import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

import App    from './App.vue'
import router from './router/index.js'

const vuetify = createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary:   '#1565C0',
          secondary: '#455A64',
          accent:    '#FF6F00',
          error:     '#D32F2F',
          warning:   '#F57F17',
          info:      '#0288D1',
          success:   '#2E7D32',
          surface:   '#FFFFFF',
          background:'#F5F5F5',
        }
      }
    }
  },
  defaults: {
    VBtn:   { variant: 'elevated', rounded: 'lg' },
    VCard:  { elevation: 2, rounded: 'lg' },
    VChip:  { rounded: 'lg' },
  }
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(vuetify)
app.mount('#app')
