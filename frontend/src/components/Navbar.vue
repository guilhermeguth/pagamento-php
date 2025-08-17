<template>
  <nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <div class="flex items-center">
          <RouterLink to="/" class="flex-shrink-0 flex items-center">
            <h1 class="text-xl font-bold text-primary-600">Sistema de Pagamento</h1>
          </RouterLink>
        </div>

        <div class="flex items-center space-x-4">
          <template v-if="authStore.isAuthenticated">
            <RouterLink
              to="/dashboard"
              class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium"
            >
              Dashboard
            </RouterLink>
            <RouterLink
              to="/transfer"
              class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium"
              v-if="!authStore.isMerchant"
            >
              Transferir
            </RouterLink>
            <RouterLink
              to="/transactions"
              class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium"
            >
              Transações
            </RouterLink>
            <div class="flex items-center space-x-2">
              <span class="text-sm text-gray-600">{{ authStore.user?.name }}</span>
              <button
                @click="handleLogout"
                class="btn btn-secondary text-sm"
              >
                Sair
              </button>
            </div>
          </template>
          <template v-else>
            <RouterLink
              to="/login"
              class="text-gray-700 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium"
            >
              Login
            </RouterLink>
            <RouterLink
              to="/register"
              class="btn btn-primary text-sm"
            >
              Cadastrar
            </RouterLink>
          </template>
        </div>
      </div>
    </div>
  </nav>
</template>

<script>
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

export default {
  name: 'Navbar',
  setup() {
    const authStore = useAuthStore()
    const router = useRouter()

    const handleLogout = async () => {
      await authStore.logout()
      router.push('/')
    }

    return {
      authStore,
      handleLogout
    }
  }
}
</script>
