<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
        Fazer login
      </h2>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md">
            {{ error }}
          </div>

          <div>
            <label for="email" class="form-label">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="form-input"
              placeholder="seu@email.com"
            />
          </div>

          <div>
            <label for="password" class="form-label">Senha</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="form-input"
              placeholder="Sua senha"
            />
          </div>

          <div>
            <button
              type="submit"
              :disabled="isLoading"
              class="w-full btn btn-primary"
            >
              <span v-if="isLoading">Carregando...</span>
              <span v-else>Entrar</span>
            </button>
          </div>

          <div class="text-center">
            <RouterLink to="/register" class="text-primary-600 hover:text-primary-500">
              Não tem conta? Cadastre-se
            </RouterLink>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'Login',
  setup() {
    const router = useRouter()
    const authStore = useAuthStore()
    
    const form = ref({
      email: '',
      password: ''
    })
    
    const error = ref('')
    const isLoading = ref(false)

    const handleSubmit = async () => {
      if (isLoading.value) return
      
      error.value = ''
      isLoading.value = true

      try {
        const result = await authStore.login(form.value)
        
        if (result.success) {
          router.push('/dashboard')
        } else {
          error.value = result.message
        }
      } catch (err) {
        error.value = 'Erro inesperado. Tente novamente.'
      } finally {
        isLoading.value = false
      }
    }

    return {
      form,
      error,
      isLoading,
      handleSubmit
    }
  }
}
</script>
