<template>
  <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Histórico de Transações</h1>
        <p class="text-gray-600">Veja todas suas transações de envio e recebimento</p>
      </div>

      <div class="card">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div class="mb-4 sm:mb-0">
            <label for="filter" class="form-label">Filtrar por tipo:</label>
            <select
              id="filter"
              v-model="selectedFilter"
              @change="loadTransactions"
              class="form-input"
            >
              <option value="all">Todas</option>
              <option value="sent">Enviadas</option>
              <option value="received">Recebidas</option>
            </select>
          </div>
          
          <button
            @click="loadTransactions"
            class="btn btn-secondary"
          >
            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Atualizar
          </button>
        </div>

        <div v-if="isLoading" class="text-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
          <p class="text-gray-600 mt-2">Carregando transações...</p>
        </div>

        <div v-else-if="transactions.length === 0" class="text-center py-8">
          <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
          </svg>
          <p class="text-gray-600">Nenhuma transação encontrada</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Data
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Tipo
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Usuário
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Valor
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Ações
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="transaction in transactions" :key="transaction.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(transaction.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="[
                      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                      transaction.type === 'sent' 
                        ? 'bg-red-100 text-red-800'
                        : 'bg-green-100 text-green-800'
                    ]"
                  >
                    {{ transaction.type === 'sent' ? 'Enviado' : 'Recebido' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ transaction.other_user_name }}
                  <div class="text-xs text-gray-500">{{ transaction.other_user_email }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <span :class="transaction.type === 'sent' ? 'text-red-600' : 'text-green-600'">
                    {{ transaction.type === 'sent' ? '-' : '+' }}R$ {{ formatCurrency(transaction.amount) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="[
                      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                      transaction.status === 'completed' 
                        ? 'bg-green-100 text-green-800'
                        : transaction.status === 'pending'
                        ? 'bg-yellow-100 text-yellow-800'
                        : 'bg-red-100 text-red-800'
                    ]"
                  >
                    {{ getStatusLabel(transaction.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <button
                    v-if="transaction.can_refund && transaction.status === 'completed'"
                    @click="handleRefund(transaction)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Estornar
                  </button>
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
            </tbody>
          </table>
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
  name: 'Transactions',
  setup() {
    const authStore = useAuthStore()
    const transactions = ref([])
    const selectedFilter = ref('all')
    const isLoading = ref(false)

    const formatCurrency = (value) => {
      return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(value)
    }

    const formatDate = (dateString) => {
      return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      }).format(new Date(dateString))
    }

    const getStatusLabel = (status) => {
      const labels = {
        completed: 'Concluída',
        pending: 'Pendente',
        failed: 'Falhou',
        refunded: 'Estornada'
      }
      return labels[status] || status
    }

    const loadTransactions = async () => {
      isLoading.value = true
      try {
        const response = await api.get(`/transactions?type=${selectedFilter.value}`)
        const rawTransactions = response.data.data.transactions || []
        
        transactions.value = rawTransactions.map(transaction => {
          let mappedTransaction = {
            id: transaction.id,
            amount: transaction.amount,
            status: transaction.status,
            description: transaction.description,
            created_at: transaction.created_at,
            updated_at: transaction.updated_at,
            can_refund: transaction.can_refund,
            original_type: transaction.type
          }

          if (transaction.type === 'refund') {
            if (transaction.user_role === 'sender') {
              mappedTransaction.type = 'sent'
              mappedTransaction.other_user_name = transaction.payee ? transaction.payee.name : 'Sistema'
              mappedTransaction.other_user_email = transaction.payee ? 
                (transaction.payee.type === 'merchant' ? 'Lojista' : 'Usuário') : ''
            } else {
              mappedTransaction.type = 'received'
              mappedTransaction.other_user_name = transaction.payer ? transaction.payer.name : 'Sistema'
              mappedTransaction.other_user_email = transaction.payer ? 
                (transaction.payer.type === 'merchant' ? 'Lojista' : 'Usuário') : ''
            }
          } else if (transaction.user_role === 'sender') {
            mappedTransaction.type = 'sent'
            if (transaction.payee) {
              mappedTransaction.other_user_name = transaction.payee.name
              mappedTransaction.other_user_email = transaction.payee.type === 'merchant' ? 'Lojista' : 'Usuário'
            } else {
              mappedTransaction.other_user_name = 'Saque'
              mappedTransaction.other_user_email = ''
            }
          } else {
            mappedTransaction.type = 'received'
            if (transaction.payer) {
              mappedTransaction.other_user_name = transaction.payer.name
              mappedTransaction.other_user_email = transaction.payer.type === 'merchant' ? 'Lojista' : 'Usuário'
            } else {
              mappedTransaction.other_user_name = 'Depósito'
              mappedTransaction.other_user_email = ''
            }
          }

          return mappedTransaction
        })
      } catch (error) {
        console.error('Erro ao carregar transações:', error)
        alert('Erro ao carregar transações')
        transactions.value = []
      } finally {
        isLoading.value = false
      }
    }

    const handleRefund = async (transaction) => {
      if (!confirm('Tem certeza que deseja estornar esta transação?')) {
        return
      }

      try {
        const response = await api.post(`/transfers/${transaction.id}/refund`)
        
        if (response.data.success) {
          alert('Transação estornada com sucesso!')
          loadTransactions()
          authStore.fetchUser()
        }
      } catch (error) {
        alert('Erro ao estornar transação: ' + (error.response?.data?.message || 'Erro desconhecido'))
      }
    }

    onMounted(() => {
      loadTransactions()
    })

    return {
      transactions,
      selectedFilter,
      isLoading,
      formatCurrency,
      formatDate,
      getStatusLabel,
      loadTransactions,
      handleRefund
    }
  }
}
</script>
