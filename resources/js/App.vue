<template>
    <div class="app">
        <h1>Импорт товаров</h1>

        <!-- Импорт XML -->
        <form @submit.prevent="startImport" class="import-form">
            <input v-model="importUrl" placeholder="URL XML-файла" />
            <button :disabled="isImporting">Загрузить</button>
        </form>

        <!-- Прогресс -->
        <div v-if="isImporting" class="progress-bar">
            <p>Статус: {{ importStatus }}</p>
            <progress :value="downloadedBytes" :max="totalBytes"></progress>
            <p>{{ percentDownloaded }} % ({{ downloadedBytes }} / {{ totalBytes }} байт)</p>
        </div>

        <div v-if="filters.length || products.length" class="catalog">
            <aside class="filters">
                <h2>Фильтры</h2>
                <div v-for="filter in filters" :key="filter.slug" class="filter-block">
                    <label :for="'filter-' + filter.slug">{{ filter.name }}</label>
                    <select
                        :id="'filter-' + filter.slug"
                        multiple
                        :value="filterState[filter.slug] || []"
                        @change="onMultiSelectChange($event, filter.slug)"
                        class="filter-select"
                    >
                        <option value="">Выбрать все</option>
                        <option
                            v-for="val in filter.values"
                            :key="val.value"
                            :value="val.value"
                        >
                            {{ val.value }} ({{ val.count }}) {{ val.active ? '✓' : '' }}
                        </option>
                    </select>
                </div>

                <button @click="resetFilters" class="reset-btn">Сбросить фильтры</button>
            </aside>

            <main class="main">
                <div class="sort">
                    <label>Сортировка:</label>
                    <select v-model="sortBy" @change="fetchProducts">
                        <option value="">По умолчанию</option>
                        <option value="price_asc">Цена ↑</option>
                        <option value="price_desc">Цена ↓</option>
                    </select>
                </div>

                <ul class="product-list">
                    <li v-for="p in products" :key="p.id" class="product">
                        <h3>{{ p.name }}</h3>
                        <strong>{{ p.price }} грн</strong>
                        <p>{{ p.description }}</p>
                    </li>
                </ul>

                <div class="pagination">
                    <button @click="changePage(currentPage - 1)" :disabled="currentPage <= 1">←</button>
                    <span>Стр. {{ currentPage }} / {{ lastPage }}</span>
                    <button @click="changePage(currentPage + 1)" :disabled="currentPage >= lastPage">→</button>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted,computed } from 'vue'
import axios from 'axios'

const importUrl = ref('')
const isImporting = ref(false)
const importStatus = ref('')
const downloadedBytes = ref(0)
const totalBytes = ref(0)

const products = ref([])
const filters = ref([])
const filterState = ref({})
const sortBy = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = 10

const percentDownloaded = computed(() => {
    if (!totalBytes.value) return 0
    return ((downloadedBytes.value / totalBytes.value) * 100).toFixed(1)
})

onMounted(() => {
    fetchFilters()
    fetchProducts()
})

function startImport() {
    isImporting.value = true
    importStatus.value = 'pending'
    downloadedBytes.value = 0
    totalBytes.value = 0

    axios.post('/api/imports', { url: importUrl.value })
        .then(res => trackProgress(res.data.id))
        .catch(err => {
            console.error(err)
            isImporting.value = false
            alert('Ошибка запуска импорта')
        })
}

function trackProgress(id) {
    const interval = setInterval(async () => {
        try {
            const res = await axios.get(`/api/imports/${id}`)
            const data = res.data
            importStatus.value = data.status
            downloadedBytes.value = data.downloaded_bytes
            totalBytes.value = data.total_bytes

            if (data.status === 'completed' || data.status === 'failed') {
                clearInterval(interval)
                isImporting.value = false
                fetchFilters()
                fetchProducts()
            }
        } catch (e) {
            clearInterval(interval)
            isImporting.value = false
            alert('Ошибка отслеживания импорта')
        }
    }, 2000)
}

function fetchProducts() {
    axios.get('/api/catalog/products', {
        params: {
            page: currentPage.value,
            limit: perPage,
            sort_by: sortBy.value,
            filter: filterState.value
        }
    }).then(res => {
        products.value = res.data.data
        currentPage.value = res.data.meta.current_page
        lastPage.value = res.data.meta.last_page
    }).catch(err => {
        console.error('Ошибка товаров:', err)
    })
}

function fetchFilters() {
    axios.get('/api/catalog/filters', {
        params: { filter: filterState.value }
    }).then(res => {
        filters.value = res.data
    }).catch(err => {
        console.error('Ошибка фильтров:', err)
    })
}

function onMultiSelectChange(event, slug) {
    const selected = Array.from(event.target.selectedOptions).map(o => o.value)

    if (selected.includes('')) {
        // "Выбрать все" — удаляем фильтр
        delete filterState.value[slug]
    } else {
        filterState.value[slug] = selected
    }

    currentPage.value = 1
    fetchFilters()
    fetchProducts()
}

function resetFilters() {
    filterState.value = {}
    currentPage.value = 1
    fetchFilters()
    fetchProducts()
}

function changePage(page) {
    if (page >= 1 && page <= lastPage.value) {
        currentPage.value = page
        fetchProducts()
    }
}
</script>

<style scoped>
.app {
    padding: 2rem;
    font-family: sans-serif;
}
.import-form {
    margin-bottom: 1rem;
}
.catalog {
    display: flex;
    gap: 2rem;
    margin-top: 2rem;
}
.main {
    width: 100%;
}
.filters {
    width: 40%;
}
.filter-block {
    margin-bottom: 1rem;
}
.sort {
    margin-bottom: 1rem;
}
.product-list {
    list-style: none;
    padding: 0;
}
.product {
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #ccc;
    padding-bottom: 1rem;
}
.pagination {
    margin-top: 1rem;
}
.reset-btn {
    margin-top: 1rem;
    background: #eee;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
}
.filter-select {
    width: 100%;
    min-height: 90px;
    padding: 0.5rem;
    font-size: 0.9rem;
    border: 1px solid #ccc;
    border-radius: 4px;
}
</style>
