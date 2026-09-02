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
import { adminApi } from '@/services/adminApi'
import { getApiErrorMessage, resolveApiAssetUrl } from '@/api/axios'
import { getMaterialsForSurgeon } from '@/services/adminFilters'
import PageContainer from '@/components/ui/PageContainer.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import MaterialPicker from '@/components/MaterialPicker.vue'
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
const uploading = ref(false)
const fileInputKey = ref(0)
const resource = computed(() => getAdminResource(props.resourceSlug))
const isEditing = computed(() => Number.isInteger(props.id) && props.id > 0)
const eligibleMaterials = computed(() => {
  const materials = referenceStore.collections.materiels ?? []
  if (props.resourceSlug !== 'listes-materiel') return materials
  return getMaterialsForSurgeon(
    materials,
    referenceStore.collections.chirurgiens ?? [],
    form.chirurgien,
  )
})
const formCollections = computed(() => ({
  ...referenceStore.collections,
  materiels: eligibleMaterials.value,
}))
const fields = computed(() =>
  getAdminFormFields(props.resourceSlug, formCollections.value),
)
const loading = computed(
  () => itemLoading.value || referencesLoading.value || saving.value || uploading.value,
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
    const values = createAdminForm(fields.value, existing)
    if (props.resourceSlug === 'fiches-techniques') {
      values.lienImage = existing?.lienImage ?? null
    }
    replaceForm(values)
  } catch {
    const values = createAdminForm(fields.value)
    if (props.resourceSlug === 'fiches-techniques') values.lienImage = null
    replaceForm(values)
  }
}

function selectImage(event) {
  const image = event.target.files?.[0] ?? null
  formError.value = ''
  if (image && !['image/jpeg', 'image/png', 'image/webp'].includes(image.type)) {
    formError.value = 'Seules les images JPEG, PNG et WebP sont acceptées.'
    event.target.value = ''
    form.imageFile = null
    return
  }
  if (image && image.size > 5 * 1024 * 1024) {
    formError.value = 'L’image ne peut pas dépasser 5 Mo.'
    event.target.value = ''
    form.imageFile = null
    return
  }
  form.imageFile = image
}

function removeImage() {
  form.imageFile = null
  form.lienImage = null
  fileInputKey.value += 1
}

/** Vérifie les champs obligatoires, persiste le payload et invalide le cache concerné. */
async function submit() {
  formError.value = ''
  const missingField = fields.value.find((field) =>
    field.required && (Array.isArray(form[field.key]) ? !form[field.key].length : !form[field.key]),
  )
  if (missingField) {
    formError.value = `Le champ « ${missingField.label} » est obligatoire.`
    return
  }

  if (props.resourceSlug === 'fiches-techniques'
    && !form.description?.trim()
    && !form.imageFile
    && !form.lienImage
  ) {
    formError.value = 'Ajoutez une consigne écrite, une image ou les deux.'
    return
  }

  let payload = buildAdminPayload(form)
  if (props.resourceSlug === 'fiches-techniques' && form.imageFile) {
    uploading.value = true
    try {
      payload.lienImage = await adminApi.uploadTechnicalSheetImage(form.imageFile)
    } catch (error) {
      formError.value = getApiErrorMessage(error, 'L’image n’a pas pu être téléversée.')
      uploading.value = false
      return
    }
    uploading.value = false
  }

  const savedItem = await adminStore.saveItem(
    props.resourceSlug,
    isEditing.value ? props.id : null,
    payload,
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

watch(
  () => form.chirurgien,
  (surgeonId, previousSurgeonId) => {
    if (props.resourceSlug !== 'listes-materiel'
      || previousSurgeonId == null
      || String(surgeonId) === String(previousSurgeonId)
    ) return

    const eligibleIds = new Set(eligibleMaterials.value.map((material) => String(material.id)))
    form.materiels = (form.materiels ?? []).filter((id) => eligibleIds.has(String(id)))
  },
)
</script>

<template>
  <PageContainer>
    <header>
      <p class="page-eyebrow">Administration</p>
      <p class="page-title">
        {{ isEditing ? 'Modifier' : 'Ajouter' }} {{ resource?.label ?? 'Référentiel' }}
      </p>
      <p class="page-description">Renseignez les informations du référentiel.</p>
    </header>

    <form v-if="resource" class="form-panel" @submit.prevent="submit">
      <ErrorMessage v-if="displayedError" :message="displayedError" />
      <fieldset class="form-grid" :disabled="loading">
        <legend class="sr-only">Informations de la ressource</legend>
        <template v-for="field in fields" :key="field.key">
          <div v-if="field.type === 'file'" class="md:col-span-2">
            <label :for="field.key" class="field-label">{{ field.label }}</label>
            <input
              :id="field.key"
              :key="fileInputKey"
              type="file"
              :accept="field.accept"
              class="field-control cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-chirorg-100 file:px-3 file:py-2 file:font-semibold file:text-chirorg-800"
              @change="selectImage"
            />
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
              JPEG, PNG ou WebP. 5 Mo maximum. Le texte reste facultatif si une image est fournie.
            </p>
            <p v-if="form.imageFile" class="mt-3 text-sm font-medium">
              Nouvelle image : {{ form.imageFile.name }}
            </p>
            <div v-if="form.lienImage" class="mt-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Image actuelle</p>
              <img
                :src="resolveApiAssetUrl(form.lienImage)"
                alt="Illustration actuelle de la consigne technique"
                class="max-h-64 w-full rounded-lg bg-white object-contain dark:bg-gray-900"
              />
            </div>
            <BaseButton
              v-if="form.imageFile || form.lienImage"
              type="button"
              variant="secondary"
              size="sm"
              class="mt-3"
              @click="removeImage"
            >
              Retirer l’image
            </BaseButton>
          </div>
          <BaseSelect
            v-else-if="field.type === 'select'"
            v-model="form[field.key]"
            :label="field.label"
            :options="field.options"
            :required="field.required"
          />
          <MaterialPicker
            v-else-if="field.type === 'material-picker'"
            v-model="form[field.key]"
            :label="field.label"
            :options="field.options"
            :required="field.required"
            :empty-message="form.chirurgien
              ? 'Aucun matériel de cette spécialité ne correspond à la recherche.'
              : 'Sélectionnez d’abord un chirurgien pour afficher les matériels de sa spécialité.'"
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
        <BaseButton type="submit" :loading="saving || uploading">Enregistrer</BaseButton>
      </div>
    </form>
    <ErrorMessage v-else :message="displayedError" />
  </PageContainer>
</template>
