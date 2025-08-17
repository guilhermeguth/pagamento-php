<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
        Criar conta
      </h2>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md">
            {{ error }}
          </div>

          <div>
            <label for="name" class="form-label">Nome completo</label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              class="form-input"
              placeholder="Seu nome completo"
            />
          </div>

          <div>
            <label for="document" class="form-label">CPF/CNPJ</label>
            <input
              id="document"
              v-model="form.document"
              type="text"
              required
              class="form-input"
              placeholder="000.000.000-00 ou 00.000.000/0001-00"
              @input="formatDocument"
            />
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
              minlength="6"
              class="form-input"
              placeholder="Mínimo 6 caracteres"
            />
          </div>

          <div>
            <label for="type" class="form-label">Tipo de usuário</label>
            <select
              id="type"
              v-model="form.type"
              required
              class="form-input"
            >
              <option value="">Selecione...</option>
              <option value="common">Usuário comum</option>
              <option value="merchant">Lojista</option>
            </select>
          </div>

          <div>
            <label for="balance" class="form-label">Saldo inicial (R$)</label>
            <input
              id="balance"
              v-model.number="form.balance"
              type="number"
              step="0.01"
              min="0"
              max="999999.99"
              required
              class="form-input"
              placeholder="0.00"
            />
            <p class="mt-1 text-sm text-gray-500">
              Informe o saldo inicial da conta (mínimo R$ 0,00)
            </p>
          </div>

          <div>
            <button
              type="submit"
              :disabled="isLoading"
              class="w-full btn btn-primary"
            >
              <span v-if="isLoading">Carregando...</span>
              <span v-else>Criar conta</span>
            </button>
          </div>

          <div class="text-center">
            <RouterLink to="/login" class="text-primary-600 hover:text-primary-500">
              Já tem conta? Faça login
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
  name: 'Register',
  setup() {
    const router = useRouter()
    const authStore = useAuthStore()
    
    const form = ref({
      name: '',
      document: '',
      email: '',
      password: '',
      type: '',
      balance: 0.00
    })
    
    const error = ref('')
    const isLoading = ref(false)

    const formatDocument = (event) => {
      let value = event.target.value.replace(/\D/g, '')
      
      if (value.length <= 11) {
        // CPF
        value = value.replace(/(\d{3})(\d)/, '$1.$2')
        value = value.replace(/(\d{3})(\d)/, '$1.$2')
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2')
      } else {
        // CNPJ
        value = value.replace(/^(\d{2})(\d)/, '$1.$2')
        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2')
        value = value.replace(/(\d{4})(\d)/, '$1-$2')
      }
      
      form.value.document = value
    }

    const handleSubmit = async () => {
      if (isLoading.value) return
      
      error.value = ''
      isLoading.value = true

      try {
        const result = await authStore.register(form.value)
        
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
      handleSubmit,
      formatDocument
    }
  }
}
</script>
