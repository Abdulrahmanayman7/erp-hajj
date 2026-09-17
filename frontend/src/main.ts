import { createApp } from 'vue'

import App from './App.vue'
import { installProviders } from './app/providers'
import { registerServiceWorker } from './shared/pwa/registerServiceWorker'
import './shared/styles/main.css'

const app = createApp(App)

installProviders(app)

app.mount('#app')
registerServiceWorker()
