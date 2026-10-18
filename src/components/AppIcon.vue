<script setup>
defineProps({ name: String })
const paths = {
  overview: 'm3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
  matches: 'M5 4h14v17H5z M9 2v4m6-4v4M8 10h8m-8 4h8m-8 4h4',
  honors: 'M8 3h8v6a4 4 0 0 1-8 0Zm0 2H4v3a4 4 0 0 0 5 4m7-7h4v3a4 4 0 0 1-5 4m-3 1v5m-4 3h8m-6-3h4',
  players: 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2',
  signup: 'M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm3 10 3 3 5-5',
  admin: 'M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
  arrow: 'M5 12h14m-5-5 5 5-5 5',
  'arrow-up-right': 'M7 17 17 7M7 7h10v10',
  'chevron-down': 'm6 9 6 6 6-6',
  close: 'm6 6 12 12M6 18 18 6',
  plus: 'M12 5v14M5 12h14',
  swords: 'm14 3 7 7-3 3-7-7ZM3 3l7 7m-7-7v5m0-5h5m6 11 7 7m-5 0 5-5M3 21l7-7m-7 2 5 5',
  jungle: 'm12 2 7 8h-4l5 7h-6v5h-4v-5H4l5-7H5Z',
  magic: 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z',
  target: 'M21 12a9 9 0 1 1-9-9m3 6-6 6m6-6V4l5-3v5ZM15 9l5-3M15 12a3 3 0 1 1-3-3',
  support: 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Zm0 5v8m-4-4h8',
  cup: 'M6 7h12l-2 14H8ZM5 4h14M14 4l3-3M9 12h6',
}
</script>
<template>
  <svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path :d="paths[name] || paths.cup" /></svg>
</template>
