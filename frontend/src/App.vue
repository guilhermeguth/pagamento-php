<template>
  <div id="app">
    <Navbar />
    <main class="min-h-screen">
      <RouterView />
    </main>
  </div>
</template>

<script>
import Navbar from './components/Navbar.vue'

export default {
  name: 'App',
  components: {
    Navbar
  }
}
</script>
