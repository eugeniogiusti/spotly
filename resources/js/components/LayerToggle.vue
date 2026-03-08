<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

interface Layer {
    key: string;
    label: string; // translation key, e.g. "layers.food"
    icon: string;
}

const props = defineProps<{
    layers: Layer[];
    activeLayers: string[];
    loadingLayer: string | null;
}>();

const emit = defineEmits<{
    toggle: [key: string];
}>();

function isActive(key: string): boolean {
    return props.activeLayers.includes(key);
}

function loadingLabel(): string {
    const layer = props.layers.find((l) => l.key === props.loadingLayer);
    return layer ? trans(layer.label) : '';
}
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <!-- Loading message -->
        <Transition name="fade">
            <div
                v-if="loadingLayer"
                class="rounded-xl bg-black/60 px-4 py-1.5 text-xs text-white backdrop-blur-sm"
            >
                {{ trans('ui.loading') }} {{ loadingLabel() }}...
            </div>
        </Transition>

        <!-- Layer buttons -->
        <div
            class="flex gap-2 overflow-x-auto rounded-2xl bg-white/90 p-2 shadow-lg backdrop-blur-sm"
        >
            <button
                v-for="layer in layers"
                :key="layer.key"
                class="flex shrink-0 flex-col items-center gap-1 rounded-xl px-3 py-2 text-xs font-medium transition-colors"
                :class="
                    isActive(layer.key)
                        ? 'bg-blue-600 text-white'
                        : 'text-gray-600 hover:bg-gray-100'
                "
                :disabled="loadingLayer !== null && !isActive(layer.key)"
                @click="emit('toggle', layer.key)"
            >
                <span v-if="loadingLayer === layer.key" class="spinner" />
                <span v-else class="text-lg leading-none">{{
                    layer.icon
                }}</span>
                <span>{{ trans(layer.label) }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
