<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import { registerUser } from '../services/api';

const subdomain = ref('');
const tenantName = ref('');
const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const error = ref('');
const success = ref('');
const isSubmitting = ref(false);

async function submit() {
  error.value = '';
  success.value = '';

  if (password.value !== confirmPassword.value) {
    error.value = 'Passwords do not match.';
    return;
  }

  isSubmitting.value = true;

  try {
    const user = await registerUser({
      subdomain: subdomain.value,
      tenant_name: tenantName.value,
      email: email.value,
      password: password.value,
    });

    success.value = `Workspace ${user.tenant.subdomain} created for ${user.email}. You can now log in on that subdomain.`;
    setTimeout(() => {
      window.location.assign(buildTenantLoginUrl(user.tenant.subdomain));
    }, 900);
  } catch (exception) {
    error.value = exception.message;
  } finally {
    isSubmitting.value = false;
  }
}

function buildTenantLoginUrl(tenantSubdomain) {
  const { protocol, hostname, port } = window.location;

  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return `${protocol}//${tenantSubdomain}.localhost${port ? `:${port}` : ''}/login`;
  }

  const labels = hostname.split('.');
  const baseLabels = labels.length > 2 && !['www', 'api'].includes(labels[0])
    ? labels.slice(1)
    : labels;

  return `${protocol}//${tenantSubdomain}.${baseLabels.join('.')}${port ? `:${port}` : ''}/login`;
}
</script>

<template>
  <section class="auth-layout">
    <div class="auth-copy">
      <p class="eyebrow">Tenant registration</p>
      <h1>Create a tenant workspace</h1>
      <p>Choose the subdomain for the new tenant. Wildcard DNS routes that subdomain back to this app after onboarding.</p>
    </div>

    <form class="form-panel" @submit.prevent="submit">
      <label>
        Workspace subdomain
        <input v-model="subdomain" type="text" autocomplete="organization" required minlength="3" maxlength="63" pattern="[a-zA-Z0-9-]+" placeholder="company_name" />
      </label>

      <label>
        Workspace name
        <input v-model="tenantName" type="text" autocomplete="organization" placeholder="Company Name" />
      </label>

      <label>
        Email
        <input v-model="email" type="email" autocomplete="email" required placeholder="you@example.com" />
      </label>

      <label>
        Password
        <input v-model="password" type="password" autocomplete="new-password" required minlength="6" placeholder="At least 6 characters" />
      </label>

      <label>
        Confirm password
        <input v-model="confirmPassword" type="password" autocomplete="new-password" required minlength="6" placeholder="Repeat password" />
      </label>

      <p v-if="error" class="notice error">{{ error }}</p>
      <p v-if="success" class="notice success">{{ success }}</p>

      <button class="primary-button" type="submit" :disabled="isSubmitting">
        {{ isSubmitting ? 'Creating...' : 'Register' }}
      </button>

      <p class="form-footer">
        Already registered?
        <RouterLink to="/login">Login</RouterLink>
      </p>
    </form>
  </section>
</template>
