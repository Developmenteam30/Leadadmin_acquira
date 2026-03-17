<template>
  <div class="container">
    <form class="form-signin" @submit.prevent="handleLogin">
      <p class="text-center">
        <img src="/images/logo.png" alt="Logo" />
      </p>
      <label for="username" class="sr-only">Username</label>
      <input
        type="text"
        id="username"
        v-model="username"
        class="form-control"
        placeholder="Username"
        required
        autofocus
      />
      <label for="password" class="sr-only">Password</label>
      <input
        type="password"
        id="password"
        v-model="password"
        class="form-control"
        placeholder="Password"
        required
      />
      <button
        class="btn btn-lg btn-primary btn-block"
        type="submit"
        :disabled="loading"
      >
        {{ loading ? 'Logging in...' : 'Log in' }}
      </button>
      <div v-if="error" class="alert alert-danger" style="margin-top: 15px;">
        {{ error }}
      </div>
    </form>
  </div>
</template>

<script>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';

export default {
  name: 'Login',
  setup() {
    const router = useRouter();
    const { login } = useAuth();
    
    const username = ref('');
    const password = ref('');
    const loading = ref(false);
    const error = ref('');

    const handleLogin = async () => {
      error.value = '';
      loading.value = true;

      const result = await login(username.value, password.value);

      if (result.success) {
        router.push('/dashboard');
      } else {
        error.value = result.error || 'Invalid username or password.';
      }

      loading.value = false;
    };

    return {
      username,
      password,
      loading,
      error,
      handleLogin,
    };
  },
};
</script>

<style scoped>
.form-signin {
  max-width: 400px;
  margin: 0 auto;
}

.form-signin img {
  max-width: 400px;
  height: auto;
}

.btn-primary {
  background-color: #429038;
  border-color: #429038;
}

.btn-primary:hover,
.btn-primary:focus {
  background-color: #357a2e;
  border-color: #357a2e;
}
</style>
