<template>
  <v-container fluid class="pa-4">
    <v-row>
      <!-- ======= ALBERO ======= -->
      <v-col cols="12" md="5">
        <v-card>
          <v-card-title class="d-flex align-center pa-4 pb-2">
            <v-icon class="mr-2">mdi-folder-tree</v-icon>
            Argomenti
            <v-spacer />
            <v-switch v-model="mostraChiusi" label="Chiusi" hide-details density="compact" class="mr-2" @update:model-value="loadTree" />
            <v-btn icon="mdi-plus" size="small" color="primary" @click="nuovoArgomento(null)" />
          </v-card-title>
          <v-card-text>
            <v-text-field
              v-model="searchTree"
              prepend-inner-icon="mdi-magnify"
              label="Cerca..."
              variant="outlined"
              density="compact"
              clearable
              class="mb-2"
            />
            <v-progress-linear v-if="loading" indeterminate class="mb-1" />

            <!-- Zona drop "radice" -->
            <div
              class="drop-root-zone mb-1 pa-1 rounded text-center text-caption text-grey"
              :class="{ 'drop-root-active': dropTargetId === '__root__' }"
              @dragover.prevent="dropTargetId = '__root__'"
              @dragleave="dropTargetId = null"
              @drop.prevent="onDrop(null)"
            >
              <v-icon size="14" class="mr-1">mdi-arrow-collapse-up</v-icon>
              Sposta qui per portare a radice
            </div>

            <!-- Lista flat con indentazione -->
            <v-list density="compact" class="pa-0">
              <v-list-item
                v-for="item in flatTreeFiltered"
                :key="item.id"
                :style="{ paddingLeft: `${item._depth * 20 + 4}px` }"
                class="tree-row rounded mb-px"
                :class="{
                  'drop-over':   dropTargetId === item.id,
                  'drag-source': dragItemId   === item.id,
                }"
                :draggable="!item.se_pausa"
                @dragstart="onDragStart($event, item)"
                @dragend="onDragEnd"
                @dragover.prevent="onDragOver(item)"
                @dragleave.self="dropTargetId = null"
                @drop.prevent="onDrop(item)"
              >
                <template #prepend>
                  <v-icon
                    v-if="!item.se_pausa"
                    size="16"
                    color="grey-lighten-1"
                    class="drag-handle mr-1"
                  >mdi-drag-vertical</v-icon>
                  <v-icon v-else size="16" class="mr-1" />
                  <v-icon :color="item.colore" size="20" class="mr-1">
                    {{ item.icona || 'mdi-folder' }}
                  </v-icon>
                </template>

                <template #title>
                  <span
                    class="cursor-pointer"
                    :class="{ 'text-medium-emphasis text-decoration-line-through': item.se_chiuso }"
                    @click="seleziona(item)"
                  >{{ item.nome }}</span>
                  <v-chip v-if="item.se_pausa" size="x-small" color="grey" class="ml-1">pausa</v-chip>
                </template>

                <template #append>
                  <v-btn
                    v-if="!item.se_pausa"
                    icon="mdi-plus"
                    size="x-small"
                    variant="text"
                    @click.stop="nuovoArgomento(item)"
                  />
                  <v-btn
                    icon="mdi-pencil"
                    size="x-small"
                    variant="text"
                    @click.stop="seleziona(item)"
                  />
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- ======= FORM ======= -->
      <v-col cols="12" md="7">
        <v-card v-if="!form.id && !isNew">
          <v-card-text class="text-center pa-10 text-medium-emphasis">
            <v-icon size="48">mdi-cursor-pointer</v-icon>
            <p class="mt-2">Seleziona un argomento dall'albero o crea uno nuovo</p>
          </v-card-text>
        </v-card>

        <v-card v-else :style="{ borderTop: `4px solid ${form.colore}` }">
          <v-card-title class="pa-4 d-flex align-center">
            <v-icon :color="form.colore" class="mr-2">{{ form.icona }}</v-icon>
            {{ isNew ? 'Nuovo argomento' : 'Modifica: ' + form.nome }}
            <v-spacer />
            <v-chip v-if="form.se_pausa" color="grey" size="small">PAUSA (speciale)</v-chip>
          </v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.nome"
                  label="Nome *"
                  variant="outlined"
                  :rules="[v => !!v || 'Obbligatorio']"
                  :disabled="form.se_pausa"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.id_argomento_padre"
                  :items="padriDisponibili"
                  item-title="label"
                  item-value="id"
                  label="Argomento padre"
                  variant="outlined"
                  clearable
                  :disabled="form.se_pausa"
                />
              </v-col>
            </v-row>

            <v-textarea
              v-model="form.descrizione"
              label="Descrizione"
              variant="outlined"
              rows="2"
              class="mb-3"
            />

            <!-- Colore + Icona -->
            <v-row align="center" class="mb-3">
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis mb-1">Colore</div>
                <div class="d-flex align-center gap-2 flex-wrap">
                  <v-sheet
                    v-for="c in COLORI_PRESET"
                    :key="c"
                    :color="c"
                    width="28" height="28"
                    rounded="circle"
                    class="cursor-pointer"
                    :style="{ outline: form.colore === c ? '3px solid #000' : '2px solid transparent', outlineOffset: '2px' }"
                    @click="form.colore = c"
                  />
                  <v-menu :close-on-content-click="false">
                    <template #activator="{ props }">
                      <v-btn v-bind="props" size="x-small" variant="outlined" icon="mdi-eyedropper" />
                    </template>
                    <v-color-picker v-model="form.colore" mode="hex" hide-inputs />
                  </v-menu>
                </div>
              </v-col>

              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis mb-1">Icona MDI</div>
                <v-text-field
                  v-model="form.icona"
                  label="es. mdi-folder"
                  variant="outlined"
                  density="compact"
                  :prepend-inner-icon="form.icona"
                >
                  <template #append-inner>
                    <v-btn size="x-small" variant="text" @click="mostraIconePicker = true">
                      Scegli
                    </v-btn>
                  </template>
                </v-text-field>
              </v-col>
            </v-row>

            <!-- Flags -->
            <v-row dense>
              <v-col cols="4">
                <v-text-field v-model="form.flag1" label="Flag 1" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="4">
                <v-text-field v-model="form.flag2" label="Flag 2" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="4">
                <v-text-field v-model="form.flag3" label="Flag 3" variant="outlined" density="compact" />
              </v-col>
            </v-row>

            <v-checkbox v-model="form.se_chiuso" label="Argomento chiuso" hide-details :disabled="form.se_pausa" />
            <v-checkbox v-model="form.se_personale" label="Personale (visibile solo al proprietario)" hide-details :disabled="form.se_pausa" />
          </v-card-text>

          <v-card-actions class="pa-4 pt-0">
            <v-spacer />
            <v-btn variant="text" @click="annulla">Annulla</v-btn>
            <v-btn color="primary" :loading="saving" @click="salva">
              <v-icon start>mdi-content-save</v-icon>Salva
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <!-- Dialog icone -->
    <v-dialog v-model="mostraIconePicker" max-width="600">
      <v-card>
        <v-card-title class="pa-4">Scegli icona
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" @click="mostraIconePicker = false" />
        </v-card-title>
        <v-card-text>
          <v-text-field v-model="searchIcona" label="Cerca icona" variant="outlined" density="compact" clearable class="mb-3" />
          <div class="d-flex flex-wrap gap-2">
            <v-tooltip
              v-for="icona in iconeFiltrate"
              :key="icona"
              :text="icona"
              location="top"
            >
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  :icon="icona"
                  size="small"
                  variant="tonal"
                  :color="form.icona === icona ? 'primary' : undefined"
                  @click="scegliIcona(icona)"
                />
              </template>
            </v-tooltip>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGetArgomenti, apiPutArgomento } from '../api/index.js'

const COLORI_PRESET = [
  '#1565C0','#1976D2','#0288D1','#00838F','#2E7D32','#558B2F',
  '#F57F17','#E65100','#C62828','#AD1457','#6A1B9A','#455A64','#9E9E9E',
]

const ICONE_PRESET = [
  'mdi-folder','mdi-folder-open','mdi-warehouse','mdi-truck','mdi-printer',
  'mdi-laptop','mdi-wrench','mdi-cog','mdi-account-group','mdi-email',
  'mdi-phone','mdi-chart-bar','mdi-shopping','mdi-cart','mdi-package',
  'mdi-database','mdi-cloud','mdi-code-braces','mdi-file-document',
  'mdi-magnify','mdi-star','mdi-heart','mdi-flag','mdi-home','mdi-office-building',
  'mdi-car','mdi-airplane','mdi-coffee','mdi-school','mdi-hospital',
]

const loading           = ref(false)
const saving            = ref(false)
const mostraChiusi      = ref(false)
const searchTree        = ref('')
const mostraIconePicker = ref(false)
const searchIcona       = ref('')
const isNew             = ref(false)
const allArgomenti      = ref([])
const treeItems         = ref([])

// Drag & Drop
const dragItemId   = ref(null)
const dropTargetId = ref(null)

const form = ref(emptyForm())

function emptyForm(padre = null) {
  return {
    id: null, nome: '', id_argomento_padre: padre?.id || null,
    descrizione: '', colore: '#607D8B', icona: 'mdi-folder',
    se_chiuso: false, se_pausa: false, se_personale: true,
    flag1: padre?.flag1 || '', flag2: padre?.flag2 || '', flag3: padre?.flag3 || '',
  }
}

// Albero appiattito con informazione di profondità
const flatTree = computed(() => {
  const result = []
  function traverse(items, depth) {
    for (const item of items) {
      result.push({ ...item, _depth: depth })
      if (item.children?.length) traverse(item.children, depth + 1)
    }
  }
  traverse(treeItems.value, 0)
  return result
})

const flatTreeFiltered = computed(() => {
  if (!searchTree.value) return flatTree.value
  const q = searchTree.value.toLowerCase()
  return flatTree.value.filter(i => i.nome.toLowerCase().includes(q))
})

const padriDisponibili = computed(() => {
  return allArgomenti.value
    .filter(a => !a.se_pausa && a.id !== form.value.id)
    .map(a => ({ id: a.id, label: a.nome }))
})

const iconeFiltrate = computed(() => {
  if (!searchIcona.value) return ICONE_PRESET
  return ICONE_PRESET.filter(i => i.includes(searchIcona.value.toLowerCase()))
})

// Verifica se `nodeId` è discendente di `ancestorId` (per evitare cicli)
function isDescendant(nodeId, ancestorId) {
  let current = allArgomenti.value.find(a => a.id === nodeId)
  while (current?.id_argomento_padre) {
    if (current.id_argomento_padre === ancestorId) return true
    current = allArgomenti.value.find(a => a.id === current.id_argomento_padre)
  }
  return false
}

// ---- Drag handlers ----
function onDragStart(event, item) {
  dragItemId.value = item.id
  event.dataTransfer.effectAllowed = 'move'
}

function onDragEnd() {
  dragItemId.value = null
  dropTargetId.value = null
}

function onDragOver(item) {
  if (!dragItemId.value) return
  if (item.id === dragItemId.value) return
  if (isDescendant(item.id, dragItemId.value)) return  // evita cicli
  dropTargetId.value = item.id
}

async function onDrop(targetItem) {
  const sourceId = dragItemId.value
  dragItemId.value  = null
  dropTargetId.value = null

  if (!sourceId) return
  const newParentId = targetItem ? targetItem.id : null
  if (sourceId === newParentId) return
  if (newParentId && isDescendant(newParentId, sourceId)) return  // evita cicli

  const source = allArgomenti.value.find(a => a.id === sourceId)
  if (!source) return

  // Stesso padre → nessuna modifica
  if ((source.id_argomento_padre || null) === (newParentId || null)) return

  try {
    await apiPutArgomento({ ...source, id_argomento_padre: newParentId })
    const label = targetItem ? `sotto "${targetItem.nome}"` : 'a radice'
    window.$notify(`Spostato ${label}`, 'success')
    await loadTree()
  } catch (e) {
    window.$notify(e.message, 'error')
  }
}

// ---- CRUD ----
async function loadTree() {
  loading.value = true
  try {
    const r = await apiGetArgomenti({ seMostraChiusi: mostraChiusi.value })
    allArgomenti.value = r.data.elenco
    treeItems.value = buildTree(r.data.elenco)
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    loading.value = false
  }
}

function buildTree(elenco) {
  const map = {}
  elenco.forEach(a => { map[a.id] = { ...a, children: [] } })
  const roots = []
  elenco.forEach(a => {
    if (a.id_argomento_padre && map[a.id_argomento_padre]) {
      map[a.id_argomento_padre].children.push(map[a.id])
    } else {
      roots.push(map[a.id])
    }
  })
  return roots.sort((a, b) => a.se_pausa - b.se_pausa || a.nome.localeCompare(b.nome))
}

function seleziona(item) {
  isNew.value = false
  form.value = {
    id: item.id, nome: item.nome,
    id_argomento_padre: item.id_argomento_padre || null,
    descrizione: item.descrizione || '',
    colore: item.colore || '#607D8B',
    icona:  item.icona  || 'mdi-folder',
    se_chiuso:    !!item.se_chiuso,
    se_pausa:     !!item.se_pausa,
    se_personale: item.se_personale !== undefined ? !!item.se_personale : true,
    flag1: item.flag1 || '', flag2: item.flag2 || '', flag3: item.flag3 || '',
  }
}

function nuovoArgomento(padre) {
  isNew.value = true
  form.value = emptyForm(padre)
}

function annulla() {
  isNew.value = false
  form.value = emptyForm()
}

async function salva() {
  if (!form.value.nome) { window.$notify('Nome obbligatorio', 'error'); return }
  saving.value = true
  try {
    await apiPutArgomento({ ...form.value })
    window.$notify('Salvato!', 'success')
    isNew.value = false
    form.value = emptyForm()
    await loadTree()
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    saving.value = false
  }
}

function scegliIcona(icona) {
  form.value.icona = icona
  mostraIconePicker.value = false
}

onMounted(loadTree)
</script>

<style scoped>
.tree-row { transition: background .1s; }
.tree-row:hover { background: rgba(21,101,192,.05); }
.drag-source { opacity: .4; }
.drop-over { background: rgba(21,101,192,.15) !important; outline: 2px dashed #1565C0; outline-offset: -2px; }
.drag-handle { cursor: grab; }
.drag-handle:active { cursor: grabbing; }

.drop-root-zone {
  border: 1px dashed #ccc;
  transition: background .1s, border-color .1s;
}
.drop-root-active {
  background: rgba(21,101,192,.1);
  border-color: #1565C0;
  color: #1565C0 !important;
}
.mb-px { margin-bottom: 2px; }
</style>
