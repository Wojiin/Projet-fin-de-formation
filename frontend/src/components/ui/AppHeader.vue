<script setup>
/** En-tête du shell protégé : reflète le titre de route et porte la déconnexion. */
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { displayName, isAdmin } = storeToRefs(authStore)

const title = computed(() => route.meta.resolvedTitle ?? route.meta.title ?? 'ChirOrg')

/** Termine la session et remplace l'historique afin d'empêcher le retour sur une vue protégée. */
async function logout() {
  await authStore.logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <header class="app-header">
    <div class="flex h-full items-center justify-between gap-4 px-5 sm:px-7 lg:px-10">
      <div class="min-w-0">
        <p class="truncate text-xs font-semibold uppercase tracking-[0.18em] text-chirorg-700 md:hidden dark:text-chirorg-300">
          ChirOrg
        </p>
        <h1 class="truncate text-lg font-semibold text-gray-950 dark:text-white">{{ title }}</h1>
      </div>
      <div class="flex items-center gap-3">
        <div class="hidden text-right sm:block">
          <p class="text-sm font-medium text-gray-900 dark:text-white">{{ displayName }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ isAdmin ? 'Administrateur' : 'Utilisateur' }}
          </p>
        </div>
        <button
          type="button"
          class="header-action"
          @click="logout"
        >
          Déconnexion
        </button>
      </div>
    </div>
  </header>
</template>
