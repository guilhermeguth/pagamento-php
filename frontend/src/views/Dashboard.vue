<template>
  <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600">Bem-vindo, {{ authStore.user?.name }}!</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <div class="card">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Saldo Atual</p>
              <p class="text-2xl font-bold text-gray-900">
                R$ {{ formatCurrency(authStore.user?.balance || 0) }}
              </p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Tipo de Conta</p>
              <p class="text-lg font-semibold text-gray-900">
                {{ authStore.user?.type === 'merchant' ? 'Lojista' : 'Usuário Comum' }}
              </p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Documento</p>
              <p class="text-lg font-semibold text-gray-900">{{ authStore.user?.document }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Ações Rápidas</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <RouterLink
            v-if="!authStore.isMerchant"
            to="/transfer"
            class="btn btn-primary text-center"
          >
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
            Fazer Transferência
          </RouterLink>
          
          <RouterLink
            to="/transactions"
            class="btn btn-secondary text-center"
          >
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Ver Transações
          </RouterLink>

          <button
            @click="handleDeposit"
            class="btn btn-secondary"
          >
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Depositar
          </button>

          <button
            @click="handleWithdraw"
            class="btn btn-secondary"
          >
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Sacar
          </button>
        </div>
      </div>

      <div v-if="showDepositModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold mb-4">Depositar Dinheiro</h3>
          
          <form @submit.prevent="submitDeposit">
            <div class="mb-4">
              <label for="amount" class="form-label">Valor</label>
              <input
                id="amount"
                v-model="depositAmount"
                type="number"
                step="0.01"
                min="0.01"
                required
                class="form-input"
                placeholder="0.00"
              />
            </div>
            
            <div class="flex space-x-4">
              <button type="submit" class="btn btn-primary flex-1">
                Depositar
              </button>
              <button
                type="button"
                @click="showDepositModal = false"
                class="btn btn-secondary flex-1"
              >
                Cancelar
              </button>
            </div>
          </form>
        </div>
      </div>

      <div v-if="showWithdrawModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold mb-4">Sacar Dinheiro</h3>
          
          <form @submit.prevent="submitWithdraw">
            <div class="mb-4">
              <label for="withdraw-amount" class="form-label">Valor</label>
              <input
                id="withdraw-amount"
                v-model="withdrawAmount"
                type="number"
                step="0.01"
                min="0.01"
                :max="authStore.user?.balance || 0"
                required
                class="form-input"
                placeholder="0.00"
              />
              <p class="text-sm text-gray-500 mt-1">
                Saldo disponível: R$ {{ formatCurrency(authStore.user?.balance || 0) }}
              </p>
            </div>
            
            <div class="flex space-x-4">
              <button type="submit" class="btn btn-primary flex-1">
                Sacar
              </button>
              <button
                type="button"
                @click="showWithdrawModal = false"
                class="btn btn-secondary flex-1"
              >
                Cancelar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

export default {
  name: 'Dashboard',
  setup() {
    const authStore = useAuthStore()
    const showDepositModal = ref(false)
    const depositAmount = ref('')
    const showWithdrawModal = ref(false)
    const withdrawAmount = ref('')

    const formatCurrency = (value) => {
      return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(value)
    }

    const handleDeposit = () => {
      showDepositModal.value = true
      depositAmount.value = ''
    }

    const handleWithdraw = () => {
      showWithdrawModal.value = true
      withdrawAmount.value = ''
    }

    const submitDeposit = async () => {
      try {
        const response = await api.post('/transfers/deposit', {
          amount: parseFloat(depositAmount.value)
        })
        
        if (response.data.success) {
          authStore.fetchUser()
          showDepositModal.value = false
          alert('Depósito realizado com sucesso!')
        }
      } catch (error) {
        alert('Erro ao realizar depósito: ' + (error.response?.data?.message || 'Erro desconhecido'))
      }
    }

    const submitWithdraw = async () => {
      try {
        const amount = parseFloat(withdrawAmount.value)
        
        if (amount > (authStore.user?.balance || 0)) {
          alert('Saldo insuficiente para realizar o saque')
          return
        }

        const response = await api.post('/transfers/withdraw', {
          amount: amount
        })
        
        if (response.data.success) {
          authStore.fetchUser() // Atualiza o saldo
          showWithdrawModal.value = false
          alert('Saque realizado com sucesso!')
        }
      } catch (error) {
        alert('Erro ao realizar saque: ' + (error.response?.data?.message || 'Erro desconhecido'))
      }
    }

    onMounted(() => {
      authStore.fetchUser()
    })

    return {
      authStore,
      showDepositModal,
      depositAmount,
      showWithdrawModal,
      withdrawAmount,
      formatCurrency,
      handleDeposit,
      handleWithdraw,
      submitDeposit,
      submitWithdraw
    }
  }
}
</script>
