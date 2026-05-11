<script setup>
import { ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { loginUser } from '../services/api';
import { saveSession } from '../services/auth';

const route = useRoute();
const router = useRouter();
const email = ref(route.query.email?.toString() || '');
const password = ref('');
const tenantSubdomain = ref(route.query.tenant?.toString() || '');
const error = ref('');
const isSubmitting = ref(false);
const isMainHost = window.location.hostname === 'localhost'
  || window.location.hostname === '127.0.0.1'
  || !hasTenantSubdomain(window.location.hostname);

async function submit() {
  error.value = '';

  if (isMainHost && tenantSubdomain.value.trim() !== '') {
    window.location.assign(buildTenantLoginUrl(tenantSubdomain.value.trim(), email.value));
    return;
  }

  if (isMainHost) {
    error.value = 'Enter your workspace subdomain to continue to tenant login.';
    return;
  }

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

function hasTenantSubdomain(hostname) {
  if (hostname.endsWith('.localhost')) {
    return true;
  }

  const labels = hostname.split('.');

  return labels.length > 2 && !['www', 'api'].includes(labels[0]);
}

function buildTenantLoginUrl(subdomain, emailValue) {
  const { protocol, hostname, port } = window.location;
  const normalizedSubdomain = subdomain.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');

  if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.endsWith('.localhost')) {
    return `${protocol}//${normalizedSubdomain}.localhost${port ? `:${port}` : ''}/login${emailValue ? `?email=${encodeURIComponent(emailValue)}` : ''}`;
  }

  const labels = hostname.split('.');
  const mainLabels = labels.length > 2 && !['www', 'api'].includes(labels[0])
    ? labels.slice(1)
    : labels;

  return `${protocol}//${normalizedSubdomain}.${mainLabels.join('.')}${port ? `:${port}` : ''}/login${emailValue ? `?email=${encodeURIComponent(emailValue)}` : ''}`;
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
      <label v-if="isMainHost">
        Workspace subdomain
        <input v-model="tenantSubdomain" type="text" autocomplete="organization" required placeholder="acme" />
      </label>

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
