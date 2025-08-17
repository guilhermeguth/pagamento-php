import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import './assets/main.css'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Inicializar a autenticação após o Pinia estar configurado
const authStore = useAuthStore()
authStore.initializeAuth().then(() => {
  app.mount('#app')
})
