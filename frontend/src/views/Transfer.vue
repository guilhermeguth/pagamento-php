<template>
  <div class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Transferir Dinheiro</h1>
        <p class="text-gray-600">Envie dinheiro para outros usuários ou lojistas</p>
      </div>

      <div class="card">
        <form @submit.prevent="handleSubmit">
          <div v-if="error" class="mb-6 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md">
            {{ error }}
          </div>

          <div v-if="success" class="mb-6 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-md">
            {{ success }}
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="payee_email" class="form-label">Email do destinatário</label>
              <input
                id="payee_email"
                v-model="form.payee_email"
                type="email"
                required
                class="form-input"
                placeholder="destinatario@email.com"
              />
              <p class="text-sm text-gray-500 mt-1">
                Email do usuário ou lojista que receberá o dinheiro
              </p>
            </div>

            <div>
              <label for="amount" class="form-label">Valor</label>
              <input
                id="amount"
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                required
                class="form-input"
                placeholder="0.00"
              />
              <p class="text-sm text-gray-500 mt-1">
                Saldo disponível: R$ {{ formatCurrency(authStore.user?.balance || 0) }}
              </p>
            </div>
          </div>

          <div class="mb-6">
            <label for="description" class="form-label">Descrição (opcional)</label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="form-input"
              placeholder="Motivo da transferência..."
            ></textarea>
          </div>

          <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-md mb-6">
            <div class="flex">
              <svg class="w-5 h-5 text-yellow-400 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
              </svg>
              <div>
                <h4 class="font-medium">Importante:</h4>
                <p class="text-sm">
                  A transferência será validada por um serviço externo antes de ser processada. 
                  Você receberá uma notificação por email quando a operação for concluída.
                </p>
              </div>
            </div>
          </div>

          <div class="flex space-x-4">
            <button
              type="submit"
              :disabled="isLoading"
              class="btn btn-primary flex-1"
            >
              <span v-if="isLoading">Processando...</span>
              <span v-else>Transferir R$ {{ formatCurrency(form.amount || 0) }}</span>
            </button>
            <RouterLink
              to="/dashboard"
              class="btn btn-secondary flex-1 text-center"
            >
              Cancelar
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
import api from '@/services/api'

export default {
  name: 'Transfer',
  setup() {
    const router = useRouter()
    const authStore = useAuthStore()
    
    const form = ref({
      payee_email: '',
      amount: '',
      description: ''
    })
    
    const error = ref('')
    const success = ref('')
    const isLoading = ref(false)

    const formatCurrency = (value) => {
      return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(value)
    }

    const handleSubmit = async () => {
      if (isLoading.value) return
      
      error.value = ''
      success.value = ''
      isLoading.value = true

      try {
        // Primeiro, buscar o usuário por email
        const userResponse = await api.get('/users/find-by-email', {
          params: { email: form.value.payee_email }
        })
        
        if (!userResponse.data.success) {
          throw new Error(userResponse.data.message || 'Usuário não encontrado')
        }

        const recipient = userResponse.data.data
        
        // Agora fazer a transferência usando o ID do usuário
        const transferResponse = await api.post('/transfers', {
          recipient_id: recipient.id,
          amount: parseFloat(form.value.amount),
          description: form.value.description || null
        })
        
        if (transferResponse.data.success) {
          success.value = `Transferência realizada com sucesso para ${recipient.name}!`
          
          // Atualiza o saldo do usuário
          authStore.fetchUser()
          
          // Limpa o formulário
          form.value = {
            payee_email: '',
            amount: '',
            description: ''
          }
          
          // Redireciona após 3 segundos
          setTimeout(() => {
            router.push('/transactions')
          }, 3000)
        }
      } catch (err) {
        error.value = err.response?.data?.message || err.message || 'Erro ao processar transferência. Tente novamente.'
      } finally {
        isLoading.value = false
      }
    }

    return {
      authStore,
      form,
      error,
      success,
      isLoading,
      formatCurrency,
      handleSubmit
    }
  }
}
</script>
