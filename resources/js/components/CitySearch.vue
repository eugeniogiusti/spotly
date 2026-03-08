<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

interface GeocodingResult {
    display_name: string;
    lat: number;
    lon: number;
    bbox: [number, number, number, number]; // [lat1, lon1, lat2, lon2]
}

const emit = defineEmits<{
    citySelected: [result: GeocodingResult];
    cleared: [];
}>();

const query = ref('');
const results = ref<GeocodingResult[]>([]);
const isLoading = ref(false);
const showDropdown = ref(false);
const hasSelection = ref(false);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

async function search() {
    if (query.value.trim().length < 2) {
        showDropdown.value = false;
        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(
            `/api/geocode?q=${encodeURIComponent(query.value)}`,
        );
        results.value = await response.json();
        showDropdown.value = results.value.length > 0;
    } finally {
        isLoading.value = false;
    }
}

function onInput() {
    hasSelection.value = false;
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 400);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        if (debounceTimer) clearTimeout(debounceTimer);
        search();
    }
    if (event.key === 'Escape') {
        showDropdown.value = false;
    }
}

function select(result: GeocodingResult) {
    query.value = result.display_name;
    showDropdown.value = false;
    hasSelection.value = true;
    emit('citySelected', result);
}

function clear() {
    query.value = '';
    results.value = [];
    showDropdown.value = false;
    hasSelection.value = false;
    emit('cleared');
}
</script>

<template>
    <div class="relative w-80">
        <!-- Search input -->
        <div class="flex overflow-hidden rounded-xl bg-white shadow-lg">
            <input
                v-model="query"
                type="text"
                :placeholder="trans('ui.search_placeholder')"
                class="flex-1 px-4 py-3 text-sm text-gray-800 outline-none placeholder:text-gray-400"
                @input="onInput"
                @keydown="onKeydown"
            />
            <button
                v-if="hasSelection"
                class="px-4 text-gray-400 hover:text-gray-700"
                @click="clear"
            >
                ✕
            </button>
            <button
                v-else
                class="px-4 text-gray-500 hover:text-gray-800 disabled:opacity-40"
                :disabled="isLoading"
                @click="search"
            >
                <span v-if="isLoading">⏳</span>
                <span v-else>🔍</span>
            </button>
        </div>

        <!-- Results dropdown -->
        <ul
            v-if="showDropdown"
            class="absolute top-full right-0 left-0 z-[1000] mt-1 max-h-60 overflow-y-auto rounded-xl bg-white shadow-lg"
        >
            <li
                v-for="result in results"
                :key="result.display_name"
                class="cursor-pointer truncate px-4 py-3 text-sm text-gray-700 hover:bg-gray-100"
                @click="select(result)"
            >
                {{ result.display_name }}
            </li>
        </ul>
    </div>
</template>
