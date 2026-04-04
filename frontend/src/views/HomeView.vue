<template>
  <v-container fluid class="pa-4">
    <v-row>
      <!-- ===================== Tiles argomenti ===================== -->
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center pa-4 pb-2">
            <v-icon class="mr-2">mdi-folder-open</v-icon>
            Seleziona Argomento
            <v-spacer />
            <v-switch
              v-model="mostraChiusi"
              label="Mostra chiusi"
              hide-details
              density="compact"
              class="mr-2"
              @update:model-value="onMostraChiusiChange"
            />
          </v-card-title>

          <v-card-text class="pb-1 pt-0">
            <v-text-field
              v-model="filtroTesto"
              prepend-inner-icon="mdi-magnify"
              label="Cerca argomento..."
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @update:model-value="onFiltroTesto"
            />
          </v-card-text>

          <!-- Breadcrumb navigazione (nascosto in modalità ricerca) -->
          <v-card-text class="pb-0 pt-0" v-if="breadcrumb.length && !filtroTesto">
            <v-breadcrumbs :items="breadcrumbItems" density="compact" class="pa-0">
              <template #prepend>
                <v-btn icon="mdi-home" size="x-small" variant="text" @click="resetBreadcrumb" />
              </template>
              <template #divider><v-icon size="small">mdi-chevron-right</v-icon></template>
              <template #item="{ item }">
                <v-breadcrumbs-item
                  :title="item.title"
                  :disabled="item.disabled"
                  class="cursor-pointer"
                  @click="!item.disabled && navBreadcrumb(item.id)"
                />
              </template>
            </v-breadcrumbs>
          </v-card-text>

          <!-- Tiles -->
          <v-card-text>
            <v-row v-if="loading">
              <v-col v-for="n in 6" :key="n" cols="6" sm="4" lg="3">
                <v-skeleton-loader type="card" />
              </v-col>
            </v-row>

            <v-row v-else-if="argomenti.length === 0">
              <v-col>
                <v-alert type="info" variant="tonal">
                  {{ filtroTesto ? 'Nessun argomento trovato per "' + filtroTesto + '"' : 'Nessun argomento disponibile' }}
                </v-alert>
              </v-col>
            </v-row>

            <v-row v-else>
              <!-- Tile "torna su" (solo in navigazione gerarchica) -->
              <v-col v-if="breadcrumb.length && !filtroTesto" cols="6" sm="4" lg="3">
                <v-card
                  style="cursor:pointer; border-top: 4px solid #9E9E9E;"
                  class="tile-back"
                  elevation="2"
                  rounded="lg"
                  @click="tornaIndietro"
                >
                  <v-card-text class="tile-back-content text-center pa-3">
                    <v-icon color="grey" size="36" class="mb-2">mdi-arrow-left-circle</v-icon>
                    <div class="text-subtitle-2 font-weight-bold text-grey">Su</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col
                v-for="arg in argomenti"
                :key="arg.id"
                cols="6" sm="4" lg="3"
              >
                <ArgomentoTile
                  :argomento="arg"
                  @click-single="onClickArgomento(arg)"
                  @click-double="onDoubleClickArgomento(arg)"
                />
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Dialog conferma avvio nuovo task -->
    <v-dialog v-model="dialogNuovoTask" max-width="480" persistent>
      <v-card>
        <v-card-title class="pa-4">
          <v-icon class="mr-2" :color="argomentoSelezionato?.colore">
            {{ argomentoSelezionato?.icona || 'mdi-play-circle' }}
          </v-icon>
          Avvia nuovo task
        </v-card-title>
        <v-card-text>
          <v-alert v-if="taskStore.taskAttivo" type="warning" variant="tonal" class="mb-4" dense>
            Il task corrente <strong>{{ taskStore.taskAttivo.argomento_nome }}</strong>
            verrà messo in pausa/chiuso automaticamente.
          </v-alert>

          <p class="mb-4">
            Argomento: <strong>{{ argomentoSelezionato?.nome }}</strong>
          </p>

          <v-text-field
            v-model="nuovoTask.descrizione"
            label="Descrizione (opzionale)"
            variant="outlined"
            clearable
            autofocus
          />

          <v-select
            v-model="nuovoTask.id_azione"
            :items="azioni"
            item-title="nome"
            item-value="id"
            label="Azione"
            variant="outlined"
            clearable
            class="mb-2"
          />

          <v-row dense>
            <v-col cols="4">
              <v-text-field v-model="nuovoTask.flag1" label="Flag 1" variant="outlined" density="compact" />
            </v-col>
            <v-col cols="4">
              <v-text-field v-model="nuovoTask.flag2" label="Flag 2" variant="outlined" density="compact" />
            </v-col>
            <v-col cols="4">
              <v-text-field v-model="nuovoTask.flag3" label="Flag 3" variant="outlined" density="compact" />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-btn variant="text" @click="dialogNuovoTask = false">Annulla</v-btn>
          <v-spacer />
          <v-btn color="success" :loading="saving" @click="avviaTask">
            <v-icon start>mdi-play</v-icon> Avvia
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useTaskStore }             from '../stores/task.js'
import { apiGetArgomenti, apiGetAzioni, apiPutTask, apiGetTaskAttivo } from '../api/index.js'
import ArgomentoTile   from '../components/ArgomentoTile.vue'

const taskStore = useTaskStore()

const argomenti          = ref([])
const azioni             = ref([])
const loading            = ref(false)
const mostraChiusi       = ref(false)
const filtroTesto        = ref('')
const breadcrumb         = ref([])   // [{id, nome}]
const dialogNuovoTask    = ref(false)
const argomentoSelezionato = ref(null)
const nuovoTask          = ref({ descrizione: '', id_azione: null, flag1: '', flag2: '', flag3: '' })
const saving             = ref(false)

const breadcrumbItems = computed(() =>
  breadcrumb.value.map((b, i) => ({
    ...b,
    title:    b.nome,
    disabled: i === breadcrumb.value.length - 1,
  }))
)

const idPadreCorrente = computed(() =>
  breadcrumb.value.length ? breadcrumb.value[breadcrumb.value.length - 1].id : null
)

let _debounceTimer = null

async function loadArgomenti(idPadre) {
  loading.value = true
  try {
    const filtro = { seMostraChiusi: mostraChiusi.value }
    if (filtroTesto.value) {
      // Modalità ricerca: ignora la navigazione, cerca su tutti i livelli
      filtro.testo = filtroTesto.value
    } else {
      if (idPadre !== undefined) filtro.idPadre = idPadre
      else if (idPadreCorrente.value) filtro.idPadre = idPadreCorrente.value
      else filtro.idPadre = null
    }

    const r = await apiGetArgomenti(filtro)
    argomenti.value = r.data.elenco
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    loading.value = false
  }
}

function onFiltroTesto() {
  clearTimeout(_debounceTimer)
  _debounceTimer = setTimeout(() => loadArgomenti(undefined), 300)
}

function onMostraChiusiChange() {
  if (filtroTesto.value) loadArgomenti(undefined)
  else loadArgomenti(null)
}

function resetBreadcrumb() {
  breadcrumb.value = []
  loadArgomenti(null)
}

function navBreadcrumb(id) {
  const idx = breadcrumb.value.findIndex(b => b.id === id)
  breadcrumb.value = breadcrumb.value.slice(0, idx + 1)
  loadArgomenti(id)
}

// Click singolo: naviga dentro i figli (oppure no-op se foglia)
function onClickArgomento(arg) {
  if (arg.num_figli > 0 || arg.se_pausa) {
    breadcrumb.value.push({ id: arg.id, nome: arg.nome })
    loadArgomenti(arg.id)
  }
  // foglia: nessuna azione (il doppio click apre il task)
}

// Doppio click: apre dialog creazione task
function onDoubleClickArgomento(arg) {
  argomentoSelezionato.value = arg
  nuovoTask.value = {
    descrizione: '', id_azione: null,
    flag1: arg.flag1 || '', flag2: arg.flag2 || '', flag3: arg.flag3 || '',
  }
  dialogNuovoTask.value = true
}

// Tile "Su": torna al livello precedente
function tornaIndietro() {
  breadcrumb.value.pop()
  const parentId = breadcrumb.value.length
    ? breadcrumb.value[breadcrumb.value.length - 1].id
    : null
  loadArgomenti(parentId)
}

async function avviaTask() {
  saving.value = true
  try {
    await apiPutTask({
      id_argomento: argomentoSelezionato.value.id,
      id_azione:    nuovoTask.value.id_azione,
      descrizione:  nuovoTask.value.descrizione,
      flag1:        nuovoTask.value.flag1 || null,
      flag2:        nuovoTask.value.flag2 || null,
      flag3:        nuovoTask.value.flag3 || null,
    })
    // Ricarica task attivo
    const ta = await apiGetTaskAttivo()
    taskStore.setTaskAttivo(ta.data.task)
    dialogNuovoTask.value = false
    taskStore.panelOpen = true
    window.$notify('Task avviato!', 'success')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loadArgomenti(null)
  try {
    const r = await apiGetAzioni()
    azioni.value = r.data.elenco
  } catch {}
})
</script>

<style scoped>
.tile-back { transition: transform .15s, box-shadow .15s; }
.tile-back:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.15) !important; }
.tile-back-content {
  height: 96px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
</style>
