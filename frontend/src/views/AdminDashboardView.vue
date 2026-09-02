<script setup>
/** Tableau d'entrée de l'administration, construit à partir de la description déclarative des référentiels. */
import { adminResources } from '@/config/adminResources'
import PageContainer from '@/components/ui/PageContainer.vue'
import AdminResourceCard from '@/components/AdminResourceCard.vue'
</script>

<template>
  <PageContainer>
    <header>
      <p class="page-eyebrow">Gestion</p>
      <p class="page-title">Administration</p>
      <p class="page-description">
        Gérez les référentiels et les comptes utilisateurs de ChirOrg.
      </p>
    </header>
    <section aria-labelledby="admin-resources-title">
      <h2 id="admin-resources-title" class="sr-only">Référentiels administrables</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <AdminResourceCard v-for="resource in adminResources" :key="resource.slug" :resource="resource" />
      </div>
    </section>
  </PageContainer>
</template>
