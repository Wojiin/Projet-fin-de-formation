<script setup>
/** Vue de consultation d'un référentiel : recherche locale et suppression confirmée. */
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { getAdminResource } from '@/config/adminResources'
import { getAdminItemDetails, getAdminItemTitle } from '@/config/adminForms'
import { useAdminStore } from '@/stores/admin'
import PageContainer from '@/components/layout/PageContainer.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

const props = defineProps({
  resourceSlug: { type: String, required: true },
})

const adminStore = useAdminStore()
const { items, loading, deletingId, error } = storeToRefs(adminStore)
const search = ref('')

const resource = computed(() => getAdminResource(props.resourceSlug))
const displayedError = computed(() =>
  resource.value ? error.value : 'Ce référentiel n’existe pas.',
)
const filteredItems = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase('fr')
  if (!needle) return items.value
  return items.value.filter((item) =>
    JSON.stringify(item).toLocaleLowerCase('fr').includes(needle),
  )
})

/** Demande confirmation puis délègue la suppression, protégée côté API, au store. */
async function remove(item) {
  if (!window.confirm(`Supprimer « ${getAdminItemTitle(item)} » ?`)) return
  await adminStore.removeItem(props.resourceSlug, item.id)
}

watch(
  () => props.resourceSlug,
  (resourceSlug) => {
    search.value = ''
    if (resource.value) adminStore.loadItems(resourceSlug)
  },
  { immediate: true },
)
</script>

<template>
  <PageContainer>
    <header class="page-heading">
      <div>
        <p class="page-eyebrow">Administration</p>
        <p class="page-title">{{ resource?.label ?? 'Référentiel' }}</p>
        <p class="page-description">{{ resource?.description }}</p>
      </div>
      <RouterLink
        v-if="resource"
        :to="{ name: 'admin-new', params: { resource: resourceSlug } }"
        class="primary-link"
      >
        + Ajouter dans {{ resource.label }}
      </RouterLink>
    </header>

    <div v-if="resource" class="max-w-xl">
      <BaseInput
        v-model="search"
        label="Rechercher"
        type="search"
        placeholder="Rechercher dans le référentiel…"
      />
    </div>
    <ErrorMessage v-if="displayedError" :message="displayedError" />
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="resource && !filteredItems.length" />

    <section v-else-if="resource" aria-labelledby="resource-list-title">
      <h2 id="resource-list-title" class="sr-only">Liste des éléments</h2>
      <ul class="admin-mobile-list">
        <li v-for="item in filteredItems" :key="item.id">
          <article class="admin-mobile-card">
            <h3 class="item-title">{{ getAdminItemTitle(item) }}</h3>
            <p class="text-muted mt-1 line-clamp-2">{{ getAdminItemDetails(item) }}</p>
            <div class="admin-item-actions">
              <RouterLink
                :to="{
                  name: 'admin-edit',
                  params: { resource: resourceSlug, id: item.id },
                }"
                :aria-label="`Modifier ${getAdminItemTitle(item)}`"
                class="secondary-link"
              >
                Modifier
              </RouterLink>
              <BaseButton
                variant="danger"
                size="sm"
                :loading="deletingId === item.id"
                :aria-label="`Supprimer ${getAdminItemTitle(item)}`"
                @click="remove(item)"
              >
                Supprimer
              </BaseButton>
            </div>
          </article>
        </li>
      </ul>

      <div class="admin-table-shell">
        <table class="w-full text-left text-sm">
          <caption class="sr-only">Éléments du référentiel {{ resource.label }}</caption>
          <thead class="admin-table-head">
            <tr>
              <th scope="col" class="px-5 py-4">Intitulé</th>
              <th scope="col" class="px-5 py-4">Informations</th>
              <th scope="col" class="px-5 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="item in filteredItems" :key="item.id">
              <th scope="row" class="admin-table-cell item-title">
                {{ getAdminItemTitle(item) }}
              </th>
              <td class="admin-table-cell max-w-lg text-gray-500 dark:text-gray-400">
                {{ getAdminItemDetails(item) }}
              </td>
              <td class="admin-table-cell">
                <div class="admin-item-actions justify-end">
                  <RouterLink
                    :to="{
                      name: 'admin-edit',
                      params: { resource: resourceSlug, id: item.id },
                    }"
                    :aria-label="`Modifier ${getAdminItemTitle(item)}`"
                    class="secondary-link"
                  >
                    Modifier
                  </RouterLink>
                  <BaseButton
                    variant="danger"
                    size="sm"
                    :loading="deletingId === item.id"
                    :aria-label="`Supprimer ${getAdminItemTitle(item)}`"
                    @click="remove(item)"
                  >
                    Supprimer
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </PageContainer>
</template>
