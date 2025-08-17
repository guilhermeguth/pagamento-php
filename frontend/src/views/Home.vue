<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-50 to-blue-100">
    <div class="container mx-auto px-4 py-16">
      <div class="text-center">
        <h1 class="text-4xl font-bold text-gray-900 mb-6">
          Sistema de Pagamento Simplificado
        </h1>
        <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
          Transfira dinheiro de forma segura e rápida entre usuários e lojistas. 
          Sistema completo com validação, autorização externa e notificações.
        </p>
        
        <div class="space-x-4">
          <RouterLink to="/register" class="btn btn-primary">
            Criar Conta
          </RouterLink>
          <RouterLink to="/login" class="btn btn-secondary">
            Fazer Login
          </RouterLink>
        </div>
      </div>

      <div class="mt-16 grid md:grid-cols-3 gap-8">
        <div class="card text-center">
          <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold mb-2">Seguro</h3>
          <p class="text-gray-600">Todas as transações são validadas e autorizadas por serviços externos.</p>
        </div>

        <div class="card text-center">
          <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold mb-2">Rápido</h3>
          <p class="text-gray-600">Transferências instantâneas com notificações em tempo real.</p>
        </div>

        <div class="card text-center">
          <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold mb-2">Confiável</h3>
          <p class="text-gray-600">Sistema com estorno de operações e controle completo de saldo.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Home'
}
</script>
