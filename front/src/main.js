import { createApp } from 'vue'
import App from './App.vue'
import { enableResponsiveTables } from '@/js/utils/responsive-tables'
import { createHackStore } from '@/js/stores/hack'
import './css/index.css'

const app = createApp(App)
app.provide('hacklab', createHackStore())
app.mount('#app')
enableResponsiveTables()
