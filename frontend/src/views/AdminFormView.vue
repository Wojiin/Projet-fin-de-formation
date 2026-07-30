<script setup>
/** Vue de formulaire générique : combine le schéma de ressource, ses références et le store CRUD. */
import { computed, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { getAdminResource } from '@/config/adminResources'
import {
  buildAdminPayload,
  createAdminForm,
  getAdminFormFields,
  referencesByResource,
} from '@/config/adminForms'
import { useAdminStore } from '@/stores/admin'
import { useReferenceStore } from '@/stores/references'
import PageContainer from '@/components/layout/PageContainer.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'

const props = defineProps({
  resourceSlug: { type: String, required: true },
  id: { type: Number, default: null },
})

const router = useRouter()
const adminStore = useAdminStore()
const referenceStore = useReferenceStore()
const {
  loading: itemLoading,
  saving,
  error: adminError,
} = storeToRefs(adminStore)
const {
  loading: referencesLoading,
  error: referenceError,
} = storeToRefs(referenceStore)

const form = reactive({})
const formError = ref('')
const resource = computed(() => getAdminResource(props.resourceSlug))
const isEditing = computed(() => Number.isInteger(props.id) && props.id > 0)
const fields = computed(() =>
  getAdminFormFields(props.resourceSlug, referenceStore.collections),
)
const loading = computed(
  () => itemLoading.value || referencesLoading.value || saving.value,
)
const displayedError = computed(() => {
  if (!resource.value) return 'Ce référentiel n’existe pas.'
  return formError.value || adminError.value || referenceError.value
})

/** Remplace l'état réactif sans conserver de champ de la ressource précédemment affichée. */
function replaceForm(values) {
  for (const key of Object.keys(form)) delete form[key]
  Object.assign(form, values)
}

/** Charge en parallèle les références de sélection et la ressource à éditer lorsque nécessaire. */
async function loadForm() {
  formError.value = ''
  if (!resource.value) {
    replaceForm({})
    return
  }

  const references = referencesByResource[props.resourceSlug] ?? []

  try {
    const [, existing] = await Promise.all([
      referenceStore.load(references),
      isEditing.value
        ? adminStore.loadItem(props.resourceSlug, props.id)
        : Promise.resolve(null),
    ])
    replaceForm(createAdminForm(fields.value, existing))
  } catch {
    replaceForm(createAdminForm(fields.value))
  }
}

/** Vérifie les champs obligatoires, persiste le payload et invalide le cache concerné. */
async function submit() {
  formError.value = ''
  const missingField = fields.value.find((field) => field.required && !form[field.key])
  if (missingField) {
    formError.value = `Le champ « ${missingField.label} » est obligatoire.`
    return
  }

  const savedItem = await adminStore.saveItem(
    props.resourceSlug,
    isEditing.value ? props.id : null,
    buildAdminPayload(form),
  )

  if (savedItem) {
    referenceStore.invalidate(props.resourceSlug)
    await router.push({
      name: 'admin-list',
      params: { resource: props.resourceSlug },
    })
  }
}

watch(
  () => [props.resourceSlug, props.id],
  loadForm,
  { immediate: true },
)
</script>

<template>
  <PageContainer>
    <header>
      <p class="page-eyebrow">Administration</p>
      <p class="page-title">
        {{ isEditing ? 'Modifier' : 'Ajouter' }} — {{ resource?.label ?? 'Référentiel' }}
      </p>
      <p class="page-description">Renseignez les informations du référentiel.</p>
    </header>

    <form v-if="resource" class="form-panel" @submit.prevent="submit">
      <ErrorMessage v-if="displayedError" :message="displayedError" />
      <fieldset class="form-grid" :disabled="loading">
        <legend class="sr-only">Informations de la ressource</legend>
        <template v-for="field in fields" :key="field.key">
          <BaseSelect
            v-if="field.type === 'select'"
            v-model="form[field.key]"
            :label="field.label"
            :options="field.options"
            :required="field.required"
          />
          <BaseTextarea
            v-else-if="field.type === 'textarea'"
            v-model="form[field.key]"
            :label="field.label"
            :required="field.required"
            class="md:col-span-2"
          />
          <BaseInput
            v-else
            v-model="form[field.key]"
            :label="field.label"
            :type="field.type ?? 'text'"
            :required="field.required"
          />
        </template>
      </fieldset>
      <div class="form-actions">
        <BaseButton type="button" variant="secondary" @click="router.back()">
          Annuler
        </BaseButton>
        <BaseButton type="submit" :loading="saving">Enregistrer</BaseButton>
      </div>
    </form>
    <ErrorMessage v-else :message="displayedError" />
  </PageContainer>
</template>
