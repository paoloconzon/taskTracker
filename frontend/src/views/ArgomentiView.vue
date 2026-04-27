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
                <v-text-field v-model="form.mantis" label="Mantis" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="4">
                <v-text-field v-model="form.ticket" label="Ticket" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="4">
                <v-text-field v-model="form.tags" label="Tags" variant="outlined" density="compact" />
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
  // Organizzazione
  'mdi-folder','mdi-folder-open','mdi-folder-multiple','mdi-warehouse','mdi-archive',
  'mdi-briefcase','mdi-filing-cabinet','mdi-inbox','mdi-inbox-multiple','mdi-inbox-full',
  'mdi-library-shelves','mdi-layers',
  
  // Lavoro/Ufficio
  'mdi-desk','mdi-office-building','mdi-hospital','mdi-school','mdi-university',
  'mdi-factory','mdi-store','mdi-shopping-center','mdi-bank','mdi-courthouse',
  'mdi-home','mdi-home-city','mdi-home-modern',
  
  // Trasporti
  'mdi-truck','mdi-van','mdi-car','mdi-taxi','mdi-bus','mdi-train','mdi-airplane',
  'mdi-helicopter','mdi-boat','mdi-bike','mdi-scooter','mdi-motorbike',
  'mdi-ship','mdi-package-variant','mdi-package-variant-closed','mdi-dolly',
  'mdi-forklift',
  
  // Tecnologia
  'mdi-laptop','mdi-desktop-classic','mdi-monitor','mdi-tablet','mdi-cellphone',
  'mdi-code-braces','mdi-code-tags','mdi-database','mdi-database-check','mdi-server',
  'mdi-server-network','mdi-network','mdi-wifi','mdi-cloud','mdi-cloud-upload',
  'mdi-cloud-download','mdi-cloud-sync','mdi-cloud-check','mdi-bug','mdi-bug-check',
  'mdi-github','mdi-gitlab','mdi-git','mdi-console','mdi-terminal',
  'mdi-application','mdi-application-braces','mdi-update',
  
  // Documenti
  'mdi-file','mdi-file-document','mdi-file-pdf','mdi-file-excel','mdi-file-word',
  'mdi-file-image','mdi-file-video','mdi-file-audio','mdi-file-zip','mdi-file-chart',
  'mdi-file-multiple','mdi-file-tree','mdi-notebook','mdi-clipboard',
  'mdi-clipboard-list','mdi-clipboard-check','mdi-note','mdi-note-multiple',
  'mdi-text-box','mdi-text-box-multiple','mdi-page-layout-header-footer',
  
  // Comunicazione
  'mdi-email','mdi-email-multiple','mdi-email-check','mdi-message','mdi-message-multiple',
  'mdi-phone','mdi-phone-in-talk','mdi-phone-outgoing','mdi-phone-incoming','mdi-phone-missed',
  'mdi-call-received','mdi-call-made','mdi-chat','mdi-chat-multiple','mdi-forum',
  'mdi-comment','mdi-comment-multiple','mdi-comment-alert','mdi-comment-question',
  'mdi-bell','mdi-bell-alert','mdi-bell-ring','mdi-bell-sleep','mdi-speaker',
  'mdi-microphone','mdi-microphone-off','mdi-megaphone','mdi-announce',
  
  // Analisi/Dati
  'mdi-chart-bar','mdi-chart-line','mdi-chart-pie','mdi-chart-box','mdi-chart-timeline',
  'mdi-chart-multiple','mdi-chart-box-outline','mdi-graph','mdi-table','mdi-table-large',
  'mdi-pivot-table','mdi-file-chart','mdi-calculator','mdi-sigma','mdi-percent',
  'mdi-currency-usd','mdi-cash-multiple','mdi-trending-up','mdi-trending-down',
  
  // Sviluppo/Coding
  'mdi-github','mdi-gitlab','mdi-git','mdi-github-face','mdi-source-branch',
  'mdi-source-commit','mdi-source-commit-end','mdi-source-commit-end-local',
  'mdi-source-commit-horizontal','mdi-source-commit-local','mdi-source-commit-next-local',
  'mdi-source-fork','mdi-source-merge','mdi-source-pull','mdi-branch','mdi-merge',
  'mdi-tag','mdi-tag-multiple','mdi-tag-outline','mdi-label','mdi-label-multiple',
  'mdi-bookmark','mdi-bookmark-multiple','mdi-flag','mdi-flag-outline','mdi-flag-variant',
  'mdi-flag-check','mdi-flag-checkered','mdi-pinned','mdi-pin','mdi-star','mdi-star-half',
  'mdi-heart','mdi-thumb-up','mdi-thumb-down','mdi-hand-right','mdi-hand-left',
  
  // Azioni/Status
  'mdi-check','mdi-check-circle','mdi-check-box','mdi-checkbox-marked','mdi-checkbox-blank',
  'mdi-checkbox-marked-circle','mdi-close','mdi-close-circle','mdi-cancel','mdi-stop',
  'mdi-stop-circle','mdi-pause','mdi-pause-circle','mdi-play','mdi-play-circle',
  'mdi-record','mdi-play-pause','mdi-fast-forward','mdi-rewind','mdi-skip-next',
  'mdi-skip-previous','mdi-next','mdi-previous','mdi-arrow-right','mdi-arrow-left',
  'mdi-arrow-up','mdi-arrow-down','mdi-refresh','mdi-refresh-circle','mdi-sync',
  'mdi-restart','mdi-power','mdi-power-on','mdi-power-off','mdi-lock','mdi-lock-open',
  'mdi-unlock','mdi-key','mdi-key-variant','mdi-download','mdi-upload','mdi-import',
  'mdi-export','mdi-share','mdi-share-variant','mdi-share-all','mdi-link','mdi-link-variant',
  'mdi-send','mdi-send-circle','mdi-forward','mdi-reply','mdi-reply-all',
  'mdi-menu','mdi-menu-open','mdi-menu-down','mdi-menu-up','mdi-menu-left','mdi-menu-right',
  'mdi-more-vertical','mdi-more-horizontal','mdi-dots-vertical','mdi-dots-horizontal',
  
  // Utilità
  'mdi-wrench','mdi-wrench-check','mdi-wrench-clock','mdi-hammer','mdi-hammer-wrench',
  'mdi-screwdriver','mdi-screwdriver-box','mdi-toolbox','mdi-tools','mdi-cog','mdi-cog-sync',
  'mdi-cog-clock','mdi-gear-outline','mdi-gears','mdi-settings','mdi-settings-box',
  'mdi-settings-helper','mdi-settings-outline','mdi-palette','mdi-palette-advanced',
  'mdi-format-paint','mdi-eyedropper','mdi-magnify','mdi-magnify-plus','mdi-magnify-minus',
  'mdi-binoculars','mdi-telescope','mdi-lightbulb','mdi-lightbulb-on','mdi-lightbulb-off',
  'mdi-lightbulb-alert','mdi-flashlight','mdi-flashlight-off','mdi-lamp','mdi-lamp-outline',
  
  // Personas
  'mdi-account','mdi-account-box','mdi-account-circle','mdi-account-multiple',
  'mdi-account-group','mdi-account-supervisor','mdi-account-supervisor-circle',
  'mdi-account-tie','mdi-account-edit','mdi-account-lock','mdi-account-remove',
  'mdi-account-convert','mdi-account-network','mdi-face','mdi-face-man','mdi-face-woman',
  'mdi-face-agent','mdi-emoticon','mdi-emoticon-cool','mdi-emoticon-dead','mdi-emoticon-excited',
  'mdi-emoticon-happy','mdi-emoticon-sad','mdi-emoticon-tongue','mdi-robotics',
  
  // Tempo
  'mdi-calendar','mdi-calendar-today','mdi-calendar-check','mdi-calendar-clock','mdi-clock',
  'mdi-clock-outline','mdi-clock-alert','mdi-history','mdi-timer','mdi-timer-10','mdi-timer-3',
  'mdi-alarm','mdi-alarm-check','mdi-alarm-off','mdi-alarm-multiple','mdi-alarm-note',
  'mdi-hourglass','mdi-hourglass-end','mdi-hourglass-start','mdi-stopwatch',
  'mdi-progress-clock','mdi-av-timer','mdi-fast-forward','mdi-rewind',
  
  // Altro
  'mdi-priority-high','mdi-priority-low','mdi-exclamation','mdi-alert','mdi-alert-box',
  'mdi-alert-circle','mdi-information','mdi-help','mdi-help-circle','mdi-help-box',
  'mdi-help-network','mdi-help-network-outline','mdi-progress-check','mdi-progress-download',
  'mdi-progress-upload','mdi-progress-pencil','mdi-progress-alert','mdi-progress-question',
  'mdi-progress-wrench','mdi-progress-clock','mdi-progress-close','mdi-progress-helper',
  'mdi-fingerprint','mdi-badge-account','mdi-badge-account-horizontal','mdi-badge-account-outline',
  'mdi-star-check','mdi-star-circle','mdi-target','mdi-target-account','mdi-target-variant',
  'mdi-clipboard-alert','mdi-cube','mdi-dice-1','mdi-dice-2','mdi-dice-3','mdi-dice-4','mdi-dice-5','mdi-dice-6',
  'mdi-puzzle','mdi-puzzle-outline','mdi-jigsaw','mdi-lock-reset','mdi-battery','mdi-battery-alert',
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
    mantis: padre?.mantis || '', ticket: padre?.ticket || '', tags: padre?.tags || '',
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
    mantis: item.mantis || '', ticket: item.ticket || '', tags: item.tags || '',
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
