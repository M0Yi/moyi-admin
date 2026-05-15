import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import App from './App.vue'
import router from './router'

// 引入全局样式
import './styles/global.scss'

// 引入 Chiron GoRound TC 字体
import 'chiron-go-round-tc-webfont/css/vf.css'

// 引入 Font Awesome
import { library } from '@fortawesome/fontawesome-svg-core'
import { faWeixin, faWeibo } from '@fortawesome/free-brands-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

// 引入自定义 Icon 组件
import Icon from './components/Icon.vue'

// 添加图标到库
library.add(faWeixin, faWeibo)

console.log('main.ts loaded')

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(ElementPlus)

// 注册全局组件
app.component('font-awesome-icon', FontAwesomeIcon)
app.component('Icon', Icon)

console.log('About to mount app')
app.mount('#app')
console.log('App mounted')
