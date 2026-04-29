<script setup>
import { ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { loginUser } from '../services/api';
import { saveSession } from '../services/auth';

const route = useRoute();
const router = useRouter();
const email = ref('');
const password = ref('');
const error = ref('');
const isSubmitting = ref(false);

async function submit() {
  error.value = '';
  isSubmitting.value = true;

  try {
    const payload = await loginUser({
      email: email.value,
      password: password.value,
    });

    saveSession(payload.token);
    router.push(route.query.redirect?.toString() || '/dashboard');
  } catch (exception) {
    if (exception.status === 404 && exception.payload?.error === 'Tenant not found.') {
      error.value = `Tenant "${exception.payload.subdomain}" was not found. Check the subdomain or create it from the registration page.`;
    } else {
      error.value = exception.message;
    }
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <section class="auth-layout">
    <div class="auth-copy">
      <p class="eyebrow">Tenant workspace</p>
      <h1>Sign in to your dashboard</h1>
      <p>Use your tenant subdomain and account credentials to access the active workspace.</p>
    </div>

    <form class="form-panel" @submit.prevent="submit">
      <label>
        Email
        <input v-model="email" type="email" autocomplete="email" required placeholder="you@example.com" />
      </label>

      <label>
        Password
        <input v-model="password" type="password" autocomplete="current-password" required placeholder="Your password" />
      </label>

      <p v-if="error" class="notice error">{{ error }}</p>

      <button class="primary-button" type="submit" :disabled="isSubmitting">
        {{ isSubmitting ? 'Signing in...' : 'Login' }}
      </button>

      <p class="form-footer">
        New here?
        <RouterLink to="/register">Create an account</RouterLink>
      </p>
    </form>
  </section>
</template>
