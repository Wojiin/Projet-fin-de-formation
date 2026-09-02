<script setup>
/** Vue de consultation d'un référentiel : recherche locale et suppression confirmée. */
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { getAdminResource } from '@/config/adminResources'
import { getAdminItemDetails, getAdminItemTitle } from '@/config/adminForms'
import { groupTechnicalSheets } from '@/utils/technicalSheets'
import {
  filterAdminItems,
  getAdminListFilterConfig,
  getAdminListFilterParams,
  getAdminListFilterReferences,
  getSpecialityFilterOptions,
  getSurgeonFilterOptions,
} from '@/services/adminFilters'
import { useAdminStore } from '@/stores/admin'
import { useReferenceStore } from '@/stores/references'
import PageContainer from '@/components/ui/PageContainer.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

const props = defineProps({
  resourceSlug: { type: String, required: true },
})

const adminStore = useAdminStore()
const referenceStore = useReferenceStore()
const { items, loading, deletingId, error } = storeToRefs(adminStore)
const {
  collections: referenceCollections,
  loading: referencesLoading,
  error: referencesError,
} = storeToRefs(referenceStore)
const search = ref('')
const specialityFilter = ref('')
const surgeonFilter = ref('')

const resource = computed(() => getAdminResource(props.resourceSlug))
const isTechnicalSheetList = computed(() => props.resourceSlug === 'fiches-techniques')
const filterConfig = computed(() => getAdminListFilterConfig(props.resourceSlug))
const hasSpecialityFilter = computed(() => Boolean(filterConfig.value.speciality))
const hasSurgeonFilter = computed(() => Boolean(filterConfig.value.surgeon))
const hasAdminFilters = computed(() => hasSpecialityFilter.value || hasSurgeonFilter.value)
const displayedError = computed(() =>
  resource.value
    ? error.value || (hasAdminFilters.value ? referencesError.value : '')
    : 'Ce référentiel n’existe pas.',
)
const pageLoading = computed(() =>
  loading.value || (hasAdminFilters.value && referencesLoading.value),
)
const filteredItems = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase('fr')
  const searchedItems = !needle ? items.value : items.value.filter((item) =>
    JSON.stringify(item).toLocaleLowerCase('fr').includes(needle),
  )
  return filterAdminItems(searchedItems, {
    specialityId: specialityFilter.value,
    surgeonId: surgeonFilter.value,
  })
})
const specialityOptions = computed(() => getSpecialityFilterOptions(
  referenceCollections.value.specialites ?? [],
))
const surgeonOptions = computed(() => getSurgeonFilterOptions(
  referenceCollections.value.chirurgiens ?? [],
))
const technicalSheetGroups = computed(() =>
  groupTechnicalSheets(filteredItems.value),
)
const hasDisplayedItems = computed(() =>
  isTechnicalSheetList.value ? technicalSheetGroups.value.length > 0 : filteredItems.value.length > 0,
)

/** Demande confirmation puis délègue la suppression, protégée côté API, au store. */
async function remove(item) {
  if (!window.confirm(`Supprimer « ${getAdminItemTitle(item)} » ?`)) return
  await adminStore.removeItem(props.resourceSlug, item.id)
}

watch(
  () => props.resourceSlug,
  (resourceSlug) => {
    search.value = ''
    specialityFilter.value = ''
    surgeonFilter.value = ''
    if (!resource.value) return

    adminStore.loadItems(resourceSlug)
    const filterReferences = getAdminListFilterReferences(resourceSlug)
    if (filterReferences.length) {
      referenceStore.load(filterReferences, { force: true }).catch(() => {})
    }
  },
  { immediate: true },
)

watch([specialityFilter, surgeonFilter], ([specialityId, surgeonId]) => {
  if (!filterConfig.value.serverSide) return
  adminStore.loadItems(props.resourceSlug, getAdminListFilterParams(props.resourceSlug, {
    specialityId,
    surgeonId,
  }))
})
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

    <div v-if="resource" class="grid max-w-6xl gap-4 md:grid-cols-2 lg:grid-cols-3">
      <BaseInput
        v-model="search"
        label="Rechercher"
        type="search"
        placeholder="Rechercher dans le référentiel…"
      />
      <BaseSelect
        v-if="hasSpecialityFilter"
        v-model="specialityFilter"
        :label="isTechnicalSheetList ? 'Spécialité de chirurgie' : 'Spécialité'"
        :options="specialityOptions"
        placeholder="Toutes les spécialités"
        allow-empty
      />
      <BaseSelect
        v-if="hasSurgeonFilter"
        v-model="surgeonFilter"
        label="Chirurgien"
        :options="surgeonOptions"
        placeholder="Tous les chirurgiens"
        allow-empty
      />
    </div>
    <ErrorMessage v-if="displayedError" :message="displayedError" />
    <LoadingState v-if="pageLoading" />
    <EmptyState v-else-if="resource && !hasDisplayedItems" />

    <section
      v-else-if="resource && isTechnicalSheetList"
      aria-labelledby="resource-list-title"
      class="space-y-8"
    >
      <h2 id="resource-list-title" class="sr-only">Fiches techniques regroupées par chirurgie</h2>

      <article v-for="group in technicalSheetGroups" :key="group.id">
        <header class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-chirorg-700 dark:text-chirorg-300">
              {{ group.speciality }}
            </p>
            <h3 class="text-lg font-bold text-gray-950 dark:text-white">{{ group.title }}</h3>
          </div>
          <span class="status-badge status-neutral">
            {{ group.items.length }} fiche{{ group.items.length > 1 ? 's' : '' }}
          </span>
        </header>

        <ul class="admin-mobile-list">
          <li v-for="item in group.items" :key="item.id">
            <article class="admin-mobile-card">
              <p class="text-xs font-semibold text-chirorg-700 dark:text-chirorg-300">
                Étape {{ item.ordre }}
              </p>
              <h4 class="item-title mt-1">{{ getAdminItemTitle(item) }}</h4>
              <p class="text-muted mt-1 line-clamp-2">{{ getAdminItemDetails(item) }}</p>
              <div class="admin-item-actions">
                <RouterLink
                  :to="{ name: 'admin-edit', params: { resource: resourceSlug, id: item.id } }"
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
            <caption class="sr-only">Fiches techniques de {{ group.title }}</caption>
            <thead class="admin-table-head">
              <tr>
                <th scope="col" class="px-5 py-4">Ordre</th>
                <th scope="col" class="px-5 py-4">Intitulé</th>
                <th scope="col" class="px-5 py-4">Consigne</th>
                <th scope="col" class="px-5 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="item in group.items" :key="item.id">
                <td class="admin-table-cell font-semibold">{{ item.ordre }}</td>
                <th scope="row" class="admin-table-cell item-title">
                  {{ getAdminItemTitle(item) }}
                </th>
                <td class="admin-table-cell max-w-lg text-gray-500 dark:text-gray-400">
                  {{ getAdminItemDetails(item) }}
                </td>
                <td class="admin-table-cell">
                  <div class="admin-item-actions justify-end">
                    <RouterLink
                      :to="{ name: 'admin-edit', params: { resource: resourceSlug, id: item.id } }"
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
      </article>
    </section>

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
