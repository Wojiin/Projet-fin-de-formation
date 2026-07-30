<script setup>
/** Vue publique de connexion : délègue l'authentification au store puis restaure la destination demandée. */
import { reactive } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'

const props = defineProps({
  redirect: { type: String, default: '' },
})

const router = useRouter()
const authStore = useAuthStore()
const { error, loading } = storeToRefs(authStore)
const form = reactive({
  email: '',
  password: '',
})

/** Accepte uniquement une redirection interne afin d'éviter toute redirection ouverte. */
function destinationAfterLogin() {
  if (props.redirect.startsWith('/') && !props.redirect.startsWith('//')) {
    return props.redirect
  }
  return { name: 'programme' }
}

/** Envoie les identifiants, puis remplace l'historique par la première vue autorisée. */
async function submit() {
  if (await authStore.login({ ...form })) {
    await router.replace(destinationAfterLogin())
  }
}
</script>

<template>
  <main class="login-page">
    <div class="login-container">
      <header class="login-brand">
        <div aria-hidden="true" class="login-logo">C</div>
        <h1 class="login-title">
          Chir<span>Org</span>
        </h1>
        <p class="text-muted mt-3">Connexion à l’espace bloc opératoire</p>
      </header>

      <form class="login-panel" @submit.prevent="submit">
        <div>
          <h2 class="section-title">Connexion</h2>
          <p class="text-muted mt-1">Accédez au programme et aux préparations du jour.</p>
        </div>
        <ErrorMessage v-if="error" :message="error" />
        <BaseInput
          v-model="form.email"
          label="Email"
          type="email"
          autocomplete="username"
          placeholder="nom@exemple.fr"
          required
        />
        <BaseInput
          v-model="form.password"
          label="Mot de passe"
          type="password"
          autocomplete="current-password"
          placeholder="••••••••"
          required
        />
        <BaseButton type="submit" size="lg" class="w-full" :loading="loading">
          Se connecter
        </BaseButton>
      </form>
    </div>
  </main>
</template>
