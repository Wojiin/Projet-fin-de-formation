<script setup>
/** Variante mobile de la navigation principale, construite à partir des droits de session. */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const links = computed(() => [
  { label: 'Programme', shortLabel: 'Programme', to: '/programme', symbol: 'P' },
  { label: 'Planifier un programme', shortLabel: 'Planifier', to: '/planifier', symbol: '+' },
  ...(authStore.isAdmin ? [{ label: 'Administration', shortLabel: 'Admin', to: '/admin', symbol: 'A' }] : []),
  { label: 'Compte', shortLabel: 'Compte', to: '/compte', symbol: 'C' },
])
</script>

<template>
  <nav aria-label="Navigation mobile" class="bottom-navigation">
    <div class="mx-auto flex h-17 max-w-xl items-stretch justify-around">
      <RouterLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        :aria-label="link.label"
        class="bottom-navigation-link"
        active-class="!text-chirorg-600"
      >
        <span aria-hidden="true" class="grid size-6 place-items-center rounded-md text-xs font-bold">{{ link.symbol }}</span>
        <span class="truncate">{{ link.shortLabel }}</span>
      </RouterLink>
    </div>
  </nav>
</template>
