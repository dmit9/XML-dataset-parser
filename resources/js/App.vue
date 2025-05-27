<template>
    <div class="container mx-auto p-4 max-w-xl">
        <h1 class="text-2xl font-bold mb-4">Импорт XML-файла</h1>

        <div class="mb-4">
            <label class="block font-semibold mb-1">Ссылка на XML-файл:</label>
            <input v-model="url" type="text" class="w-full border px-3 py-2" placeholder="https://example.com/data.xml" />
        </div>

        <button
            @click="startImport"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            :disabled="loading || !url"
        >
            Загрузить
        </button>

        <div v-if="importData" class="mt-6">
            <p class="font-semibold">Статус: <span class="font-mono">{{ importData.status }}</span></p>
            <div v-if="importData.total_bytes">
                <progress :value="importData.downloaded_bytes" :max="importData.total_bytes" class="w-full mt-2"></progress>
                <p>{{ Math.floor(progressPercent) }} % ({{ importData.downloaded_bytes }} / {{ importData.total_bytes }} байт)</p>
            </div>
            <div v-if="importData.status === 'parsing'">
                <p class="mt-2">Обработано товаров: {{ importData.parsed_offers }}</p>
            </div>
            <div v-if="importData.status === 'completed'" class="text-green-700 mt-2 font-bold">Импорт завершён!</div>
            <div v-if="importData.status === 'failed'" class="text-red-700 mt-2 font-bold">Ошибка: {{ importData.error }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const url = ref('')
const importId = ref(null)
const importData = ref(null)
const loading = ref(false)
let interval = null

const startImport = async () => {
    loading.value = true
    try {
        const res = await axios.post('/api/imports', { url: url.value })
        importId.value = res.data.id
        fetchProgress()
        interval = setInterval(fetchProgress, 2000)
    } catch (err) {
        alert('Ошибка запуска импорта')
    } finally {
        loading.value = false
    }
}

const fetchProgress = async () => {
    if (!importId.value) return
    try {
        const res = await axios.get(`/api/imports/${importId.value}`)
        importData.value = res.data

        if (['completed', 'failed'].includes(res.data.status)) {
            clearInterval(interval)
        }
    } catch (err) {
        clearInterval(interval)
        alert('Ошибка при получении статуса')
    }
}

const progressPercent = computed(() => {
    if (!importData.value || !importData.value.total_bytes) return 0
    return (importData.value.downloaded_bytes / importData.value.total_bytes) * 100
})
</script>
