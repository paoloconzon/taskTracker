<template>
  <v-autocomplete
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :items="items"
    item-title="label"
    item-value="id"
    :label="label"
    variant="outlined"
    density="compact"
    clearable
    :loading="loading"
    no-data-text="Nessun argomento"
    :custom-filter="customFilter"
  >
    <template #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps" :title="undefined" :style="{ paddingLeft: (item.raw.livello * 16 + 16) + 'px' }">
        <template #prepend>
          <v-icon :color="item.raw.colore" size="18" class="mr-1">
            {{ item.raw.icona || 'mdi-folder' }}
          </v-icon>
        </template>
        <v-list-item-title>{{ item.raw.nome }}</v-list-item-title>
        <v-list-item-subtitle v-if="item.raw.path" class="text-caption">
          {{ item.raw.path }}
        </v-list-item-subtitle>
      </v-list-item>
    </template>
    <template #selection="{ item }">
      <v-chip size="small" :color="item.raw.colore" variant="tonal">
        <v-icon start size="14">{{ item.raw.icona || 'mdi-folder' }}</v-icon>
        {{ item.raw.label }}
      </v-chip>
    </template>
  </v-autocomplete>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { apiGetArgomenti } from '../api/index.js'

defineProps({
  modelValue: { default: null },
  label:      { type: String, default: 'Argomento' },
})
defineEmits(['update:modelValue'])

const items   = ref([])
const loading = ref(false)

// Costruisce lista piatta con indent visivo
async function loadAll() {
  loading.value = true
  try {
    const r = await apiGetArgomenti({ seMostraChiusi: false })
    const flat = buildFlat(r.data.elenco)
    items.value = flat
  } catch {} finally {
    loading.value = false
  }
}

function buildFlat(elenco) {
  // Costruisce albero in-place
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

  const result = []
  function walk(node, livello, pathParts) {
    const path = pathParts.join(' / ')
    result.push({
      ...node,
      livello,
      path,
      label: path ? path + ' / ' + node.nome : node.nome,
    })
    node.children.sort((a,b) => a.nome.localeCompare(b.nome))
    node.children.forEach(c => walk(c, livello + 1, [...pathParts, node.nome]))
  }
  roots.sort((a,b) => a.se_pausa - b.se_pausa || a.nome.localeCompare(b.nome))
  roots.forEach(r => walk(r, 0, []))
  return result
}

function customFilter(value, query, item) {
  if (!query) return true
  const q = query.toLowerCase()
  return item.raw.label.toLowerCase().includes(q) ||
         (item.raw.descrizione || '').toLowerCase().includes(q)
}

onMounted(loadAll)
</script>
