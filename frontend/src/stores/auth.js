import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token'),
    isLoading: false
  }),

  getters: {
    isAuthenticated: (state) => {
      // Se tem token, considera autenticado (mesmo que o usuário ainda não foi carregado)
      return !!state.token
    },
    hasUserData: (state) => !!state.user,
    isMerchant: (state) => state.user?.type === 'merchant'
  },

  actions: {
    async login(credentials) {
      this.isLoading = true
      try {
        const response = await api.post('/login', credentials)
        const { user, token } = response.data.data
        
        this.user = user
        this.token = token
        localStorage.setItem('token', token)
        
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`
        
        return { success: true }
      } catch (error) {
        return { 
          success: false, 
          message: error.response?.data?.message || 'Erro ao fazer login' 
        }
      } finally {
        this.isLoading = false
      }
    },

    async register(userData) {
      this.isLoading = true
      try {
        const response = await api.post('/register', userData)
        const { user, token } = response.data.data
        
        this.user = user
        this.token = token
        localStorage.setItem('token', token)
        
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`
        
        return { success: true }
      } catch (error) {
        return { 
          success: false, 
          message: error.response?.data?.message || 'Erro ao criar conta' 
        }
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      try {
        await api.post('/logout')
      } catch (error) {
        console.error('Erro ao fazer logout:', error)
      } finally {
        this.user = null
        this.token = null
        localStorage.removeItem('token')
        delete api.defaults.headers.common['Authorization']
      }
    },

    async fetchUser() {
      if (!this.token) return
      
      try {
        const response = await api.get('/user')
        this.user = response.data.data
        return true
      } catch (error) {
        console.error('Erro ao buscar usuário:', error)
        // Só faz logout se for erro 401 (token inválido)
        if (error.response?.status === 401) {
          this.logout()
          return false
        }
        return false
      }
    },

    async initializeAuth() {
      if (this.token) {
        api.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
        // Tentar buscar os dados do usuário
        await this.fetchUser()
      }
    }
  }
})
