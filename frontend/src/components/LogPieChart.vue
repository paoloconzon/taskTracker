<template>
  <v-card class="mt-4" v-if="slices.length > 0">
    <v-card-title class="pa-4 pb-2">
      <v-icon class="mr-2">mdi-chart-pie</v-icon>
      Distribuzione argomenti
      <span class="text-caption text-medium-emphasis ml-2">(clicca uno spicchio per espandere/comprimere)</span>
    </v-card-title>

    <v-card-text>
      <div class="d-flex flex-wrap align-center justify-center" style="gap: 32px">

        <!-- Donut SVG -->
        <div style="position:relative; width:240px; height:240px; flex-shrink:0">
          <svg width="240" height="240" style="overflow:visible">
            <g transform="translate(120,120)">
              <path
                v-for="(s, i) in slices"
                :key="s.key"
                :d="pathFor(s)"
                :fill="s.color"
                fill-rule="evenodd"
                stroke="white"
                stroke-width="1.5"
                :transform="hovered === i ? translateSlice(s) : ''"
                :style="{
                  cursor: s.hasChildren ? 'pointer' : 'default',
                  opacity: hovered !== null && hovered !== i ? 0.5 : 1,
                  transition: 'transform 0.12s, opacity 0.12s',
                }"
                @mouseenter="hovered = i"
                @mouseleave="hovered = null"
                @click="toggleExpand(s)"
              />
            </g>
          </svg>

          <!-- Centro donut -->
          <div style="
            position:absolute; inset:0;
            display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            pointer-events:none; user-select:none;
          ">
            <template v-if="hovered !== null">
              <span class="text-h6 font-weight-bold" :style="{ color: slices[hovered].color }">
                {{ slices[hovered].pct }}
              </span>
              <span
                class="text-caption text-center text-medium-emphasis"
                style="max-width:92px; line-height:1.3"
              >{{ slices[hovered].label }}</span>
              <span class="text-caption mt-1">{{ formatDurata(slices[hovered].sec) }}</span>
            </template>
            <template v-else>
              <span class="text-caption text-medium-emphasis">Totale</span>
              <span class="text-body-2 font-weight-bold">{{ formatDurata(totaleSec) }}</span>
            </template>
          </div>
        </div>

        <!-- Legenda -->
        <div style="min-width:200px; max-width:380px">
          <div
            v-for="(s, i) in slices"
            :key="s.key"
            class="d-flex align-center gap-2 rounded py-1 mb-1"
            :style="{
              paddingLeft: (8 + s.depth * 18) + 'px',
              paddingRight: '8px',
              cursor: s.hasChildren ? 'pointer' : 'default',
              opacity: hovered !== null && hovered !== i ? 0.4 : 1,
              background: hovered === i ? 'rgba(128,128,128,0.12)' : 'transparent',
              transition: 'opacity 0.12s, background 0.12s',
            }"
            @mouseenter="hovered = i"
            @mouseleave="hovered = null"
            @click="toggleExpand(s)"
          >
            <div :style="{
              width: '10px', height: '10px', borderRadius: '2px',
              background: s.color, flexShrink: 0,
            }" />
            <span
              class="text-body-2 flex-grow-1 text-truncate"
              :title="s.label"
              style="max-width:220px"
            >{{ s.label }}</span>
            <span class="text-caption text-medium-emphasis mr-1" style="white-space:nowrap">
              {{ formatDurata(s.sec) }}
            </span>
            <span class="text-caption font-weight-bold" style="min-width:44px; text-align:right">
              {{ s.pct }}
            </span>
            <v-icon v-if="s.hasChildren && !s.isExpanded" size="14" class="ml-1">mdi-chevron-right</v-icon>
            <v-icon v-else-if="s.hasChildren && s.isExpanded" size="14" class="ml-1">mdi-chevron-down</v-icon>
            <div v-else style="width:18px; flex-shrink:0" />
          </div>
        </div>

      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const expanded = ref(new Set())
const hovered  = ref(null)

// 16 hue families; children use lightness variants of their parent's hue
const BASE_HUES = [213, 4, 43, 122, 24, 183, 280, 187, 28, 133, 348, 210, 330, 174, 21, 200]
const DIRECT    = '\x00'   // sentinel for "rows with no deeper level"

watch(() => props.items, () => {
  expanded.value = new Set()
  hovered.value  = null
})

function getRowLevels(r) {
  if (r.arg_nonno_nome) return [r.arg_nonno_nome, r.arg_padre_nome, r.argomento_nome].filter(Boolean)
  if (r.arg_padre_nome) return [r.arg_padre_nome, r.argomento_nome].filter(Boolean)
  return [r.argomento_nome].filter(Boolean)
}

// Returns { color, hueIdx } — hueIdx is passed down to children
function pickColor(depth, parentHueIdx, siblingIdx, siblingCount) {
  if (depth === 0) {
    const h = BASE_HUES[siblingIdx % BASE_HUES.length]
    return { color: `hsl(${h}, 65%, 52%)`, hueIdx: siblingIdx }
  }
  const h   = BASE_HUES[parentHueIdx % BASE_HUES.length]
  const mid = 52
  const rng = 28
  const off = siblingCount <= 1 ? 0 : ((siblingIdx / (siblingCount - 1)) - 0.5) * rng
  return { color: `hsl(${h}, 60%, ${Math.round(mid + off)}%)`, hueIdx: parentHueIdx }
}

// Recursively build flat list of visible slices
function buildSlices(rows, depth, parentPath, parentHueIdx) {
  // Group rows whose levels match parentPath, keyed by their next-level name
  const groups = {}
  for (const r of rows) {
    const levels = getRowLevels(r)
    if (!parentPath.every((name, i) => levels[i] === name)) continue
    const next = levels[depth]
    const k    = next ?? DIRECT
    if (!groups[k]) groups[k] = { sec: 0, hasChildren: false, isDirect: !next }
    groups[k].sec += r.secondi
    if (next && levels.length > depth + 1) groups[k].hasChildren = true
  }

  const entries = Object.entries(groups).sort((a, b) => b[1].sec - a[1].sec)
  const n       = entries.length
  const result  = []

  for (let i = 0; i < n; i++) {
    const [name, { sec, hasChildren, isDirect }] = entries[i]
    const path  = isDirect ? parentPath : [...parentPath, name]
    const key   = path.join('\x1f') + (isDirect ? '\x1f' + DIRECT : '')
    const label = path.join(' / ') || name
    const { color, hueIdx } = pickColor(depth, parentHueIdx, i, n)
    const isExp = expanded.value.has(key) && hasChildren

    if (isExp) {
      result.push(...buildSlices(rows, depth + 1, path, hueIdx))
    } else {
      result.push({ key, label, sec, hasChildren, isExpanded: isExp, depth, color })
    }
  }
  return result
}

const rawSlices = computed(() => {
  const rows = props.items.filter(r => !r.se_pausa && r.secondi > 0)
  return buildSlices(rows, 0, [], 0)
})

const slices = computed(() => {
  const raw   = rawSlices.value
  const total = raw.reduce((a, s) => a + s.sec, 0)
  if (total === 0) return []

  let angle = -Math.PI / 2
  return raw.map(s => {
    const frac = s.sec / total
    const sa   = angle
    angle += frac * 2 * Math.PI
    return { ...s, pct: (frac * 100).toFixed(1) + '%', startAngle: sa, endAngle: angle }
  })
})

const totaleSec = computed(() => slices.value.reduce((a, s) => a + s.sec, 0))

function f(n) { return n.toFixed(3) }

function pathFor(s) {
  const R = 100, r = 52
  if (s.endAngle - s.startAngle >= Math.PI * 2 * 0.9999) {
    return [
      `M 0 ${-R} A ${R} ${R} 0 0 1 0 ${R} A ${R} ${R} 0 0 1 0 ${-R}`,
      `M 0 ${-r} A ${r} ${r} 0 0 0 0 ${r} A ${r} ${r} 0 0 0 0 ${-r} Z`,
    ].join(' ')
  }
  const la  = s.endAngle - s.startAngle > Math.PI ? 1 : 0
  const x1o = R * Math.cos(s.startAngle), y1o = R * Math.sin(s.startAngle)
  const x2o = R * Math.cos(s.endAngle),   y2o = R * Math.sin(s.endAngle)
  const x1i = r * Math.cos(s.startAngle), y1i = r * Math.sin(s.startAngle)
  const x2i = r * Math.cos(s.endAngle),   y2i = r * Math.sin(s.endAngle)
  return [
    `M ${f(x1o)} ${f(y1o)}`,
    `A ${R} ${R} 0 ${la} 1 ${f(x2o)} ${f(y2o)}`,
    `L ${f(x2i)} ${f(y2i)}`,
    `A ${r} ${r} 0 ${la} 0 ${f(x1i)} ${f(y1i)} Z`,
  ].join(' ')
}

function translateSlice(s) {
  const mid = (s.startAngle + s.endAngle) / 2
  return `translate(${(7 * Math.cos(mid)).toFixed(2)}, ${(7 * Math.sin(mid)).toFixed(2)})`
}

function toggleExpand(s) {
  if (!s.hasChildren) return
  const next = new Set(expanded.value)
  if (next.has(s.key)) {
    // Collapse this node and all its descendants
    const prefix = s.key + '\x1f'
    for (const k of next) {
      if (k === s.key || k.startsWith(prefix)) next.delete(k)
    }
  } else {
    next.add(s.key)
  }
  expanded.value = next
  hovered.value  = null
}

function formatDurata(sec) {
  if (!sec || sec < 0) return '-'
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  if (h > 0) return `${h}h ${m}m`
  if (m > 0) return `${m}m ${s}s`
  return `${s}s`
}
</script>
