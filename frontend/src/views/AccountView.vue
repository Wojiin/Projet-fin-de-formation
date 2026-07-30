<script setup>
/** Vue de profil qui expose uniquement les données déjà présentes dans la session Pinia. */
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import PageContainer from '@/components/layout/PageContainer.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const authStore = useAuthStore()
const { user, displayName, isAdmin } = storeToRefs(authStore)
</script>

<template>
  <PageContainer>
    <section class="account-panel">
      <div class="account-heading">
        <div aria-hidden="true" class="account-avatar">
          {{ displayName.charAt(0).toUpperCase() }}
        </div>
        <div>
          <h2 class="section-title">Informations du compte</h2>
          <p class="text-muted">{{ user?.email }}</p>
        </div>
      </div>
      <dl class="account-metadata">
        <div>
          <dt>Identifiant</dt>
          <dd>{{ user?.id }}</dd>
        </div>
        <div>
          <dt>Rôle</dt>
          <dd><StatusBadge :status="isAdmin ? 'Admin' : 'Utilisateur'" /></dd>
        </div>
      </dl>
    </section>
  </PageContainer>
</template>
