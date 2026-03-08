<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

interface TagCounts {
    [tag: string]: number;
}

interface RawData {
    tags?: Record<string, string>;
}

interface Poi {
    id: number;
    external_id: string;
    layer: string;
    name: string;
    lat: number;
    lng: number;
    raw_data: RawData;
}

const props = defineProps<{
    poi: Poi | null;
    layerColor: string;
    layerIcon: string;
    savedPoiIds: string[];
    isLoggedIn: boolean;
}>();

const emit = defineEmits<{
    close: [];
    saved: [externalId: string];
    unsaved: [externalId: string];
}>();

const TAGS = [
    'laptop_friendly',
    'wifi',
    'power_outlets',
    'quiet',
    'budget_friendly',
    'tourist_trap',
] as const;
const TAG_ICONS: Record<string, string> = {
    laptop_friendly: '💻',
    wifi: '📶',
    power_outlets: '🔌',
    quiet: '🤫',
    budget_friendly: '💸',
    tourist_trap: '⚠️',
};

const isSaved = ref(false);
const isSaving = ref(false);
const tagCounts = ref<TagCounts>({});
const userTags = ref<string[]>([]);
const tagsLoading = ref(false);
const togglingTag = ref<string | null>(null);
const errorMsg = ref<string | null>(null);
let errorTimer: ReturnType<typeof setTimeout> | null = null;

function showError(msg: string): void {
    if (errorTimer) clearTimeout(errorTimer);
    errorMsg.value = msg;
    errorTimer = setTimeout(() => { errorMsg.value = null; }, 3500);
}

watch(
    () => props.poi,
    (poi) => {
        isSaved.value = poi
            ? props.savedPoiIds.includes(poi.external_id)
            : false;
        tagCounts.value = {};
        userTags.value = [];
        errorMsg.value = null;
        if (poi) {
            fetchTags(poi.external_id);
        }
    },
    { immediate: true },
);

async function fetchTags(externalId: string): Promise<void> {
    tagsLoading.value = true;
    try {
        const res = await fetch(
            `/api/pois/${encodeURIComponent(externalId)}/tags`,
        );
        if (res.ok) {
            const data = await res.json();
            tagCounts.value = data.counts ?? {};
            userTags.value = data.user_tags ?? [];
        }
    } catch {
        // network error — tags shown without counts, non-critical
    } finally {
        tagsLoading.value = false;
    }
}

async function toggleTag(tag: string): Promise<void> {
    if (!props.poi || !props.isLoggedIn) {
        if (!props.isLoggedIn) {
            router.visit('/login');
        }
        return;
    }
    togglingTag.value = tag;
    try {
        const res = await fetch(
            `/api/pois/${encodeURIComponent(props.poi.external_id)}/tags`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ tag }),
            },
        );
        if (res.ok) {
            const data = await res.json();
            tagCounts.value = { ...tagCounts.value, [data.tag]: data.count };
            if (data.added) {
                userTags.value = [...userTags.value, data.tag];
            } else {
                userTags.value = userTags.value.filter((t) => t !== data.tag);
            }
        } else if (res.status === 419) {
            showError(trans('ui.error_session'));
        } else {
            showError(trans('ui.error_tag'));
        }
    } catch {
        showError(trans('ui.error_network'));
    } finally {
        togglingTag.value = null;
    }
}

function getCsrf(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? ''
    );
}

async function toggleSave() {
    if (!props.poi) return;

    if (!props.isLoggedIn) {
        router.visit('/login');
        return;
    }

    isSaving.value = true;

    try {
        if (isSaved.value) {
            const res = await fetch(
                `/saved-pois/${encodeURIComponent(props.poi.external_id)}`,
                {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': getCsrf() },
                },
            );
            if (!res.ok) {
                showError(res.status === 419 ? trans('ui.error_session') : trans('ui.error_save'));
                return;
            }
            isSaved.value = false;
            emit('unsaved', props.poi.external_id);
        } else {
            const res = await fetch('/saved-pois', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({
                    poi_external_id: props.poi.external_id,
                    layer: props.poi.layer,
                    name: props.poi.name || '',
                    lat: props.poi.lat,
                    lng: props.poi.lng,
                }),
            });
            if (!res.ok) {
                showError(res.status === 419 ? trans('ui.error_session') : trans('ui.error_save'));
                return;
            }
            isSaved.value = true;
            emit('saved', props.poi.external_id);
        }
    } catch {
        showError(trans('ui.error_network'));
    } finally {
        isSaving.value = false;
    }
}

function getAddress(rawData: RawData): string {
    const tags = rawData.tags ?? {};
    const street = [tags['addr:housenumber'], tags['addr:street']]
        .filter(Boolean)
        .join(' ');
    return street || tags['addr:city'] || '';
}

function getPhone(rawData: RawData): string {
    const tags = rawData.tags ?? {};
    return tags.phone ?? tags['contact:phone'] ?? '';
}

function getHours(): string {
    return props.poi?.raw_data.tags?.opening_hours ?? '';
}

function getDirectionsUrlGoogle(): string {
    if (!props.poi) return '#';
    return `https://www.google.com/maps/dir/?api=1&destination=${props.poi.lat},${props.poi.lng}`;
}

function getDirectionsUrlApple(): string {
    if (!props.poi) return '#';
    return `https://maps.apple.com/?daddr=${props.poi.lat},${props.poi.lng}&q=${encodeURIComponent(props.poi.name)}`;
}

function getWebsite(rawData: RawData): string {
    const tags = rawData.tags ?? {};
    return tags.website ?? tags['contact:website'] ?? '';
}

function getCuisine(rawData: RawData): string {
    return rawData.tags?.cuisine ?? '';
}
</script>

<template>
    <Transition name="slide-up">
        <div
            v-if="poi"
            class="fixed right-0 bottom-0 left-0 z-[2000] rounded-t-3xl border-t border-border bg-card shadow-2xl"
        >
            <!-- Drag handle -->
            <div class="mx-auto mt-3 h-1 w-10 rounded-full bg-border" />

            <!-- Error toast -->
            <Transition name="fade">
                <div
                    v-if="errorMsg"
                    class="mx-5 mt-3 rounded-xl bg-red-500/10 px-4 py-2 text-center text-xs font-medium text-red-600 dark:text-red-400"
                >
                    {{ errorMsg }}
                </div>
            </Transition>

            <!-- Header -->
            <div class="flex items-start gap-3 px-5 pt-4 pb-3">
                <!-- Layer icon badge -->
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-xl shadow-sm"
                    :style="{
                        background: layerColor + '22',
                        border: `1.5px solid ${layerColor}44`,
                    }"
                >
                    {{ layerIcon }}
                </div>

                <div class="min-w-0 flex-1">
                    <h2
                        class="truncate text-base font-semibold text-foreground"
                    >
                        {{ poi.name || '(no name)' }}
                    </h2>
                    <p
                        v-if="getAddress(poi.raw_data)"
                        class="mt-0.5 truncate text-sm text-muted-foreground"
                    >
                        {{ getAddress(poi.raw_data) }}
                    </p>
                    <p
                        v-if="getCuisine(poi.raw_data)"
                        class="mt-0.5 text-xs text-muted-foreground/70 capitalize"
                    >
                        {{ getCuisine(poi.raw_data).replaceAll(';', ' · ') }}
                    </p>
                </div>

                <!-- Save button -->
                <button
                    class="shrink-0 rounded-full p-2 transition-all"
                    :class="
                        isSaved
                            ? 'bg-red-500/10 text-red-500'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    :disabled="isSaving"
                    :aria-label="isSaved ? 'Remove from saved' : 'Save place'"
                    @click="toggleSave"
                >
                    <svg
                        v-if="isSaved"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path
                            d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                        />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                        />
                    </svg>
                </button>

                <!-- Close button -->
                <button
                    class="shrink-0 rounded-full p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Details -->
            <div class="space-y-2.5 px-5 pb-8">
                <!-- Info rows -->
                <div
                    v-if="
                        getPhone(poi.raw_data) ||
                        getHours() ||
                        getWebsite(poi.raw_data)
                    "
                    class="space-y-2 rounded-2xl border border-border bg-muted/50 p-3"
                >
                    <div
                        v-if="getPhone(poi.raw_data)"
                        class="flex items-center gap-3 text-sm text-foreground"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-background text-sm"
                            >📞</span
                        >
                        <a
                            :href="`tel:${getPhone(poi.raw_data)}`"
                            class="text-muted-foreground hover:underline"
                        >
                            {{ getPhone(poi.raw_data) }}
                        </a>
                    </div>

                    <div
                        v-if="getHours()"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-background text-sm"
                            >🕐</span
                        >
                        <span class="text-muted-foreground">{{
                            getHours()
                        }}</span>
                    </div>

                    <div
                        v-if="getWebsite(poi.raw_data)"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-background text-sm"
                            >🌐</span
                        >
                        <a
                            :href="getWebsite(poi.raw_data)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="truncate text-primary hover:underline"
                        >
                            {{ trans('ui.visit_website') }}
                        </a>
                    </div>
                </div>

                <p
                    v-if="
                        !getPhone(poi.raw_data) &&
                        !getHours() &&
                        !getWebsite(poi.raw_data)
                    "
                    class="text-sm text-muted-foreground"
                >
                    {{ trans('ui.no_info') }}
                </p>

                <!-- Community tags -->
                <div class="space-y-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ trans('tags.section_title') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tag in TAGS"
                            :key="tag"
                            class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-all disabled:opacity-50"
                            :class="
                                userTags.includes(tag)
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : 'border-border bg-muted/50 text-muted-foreground hover:border-border hover:bg-muted hover:text-foreground'
                            "
                            :disabled="togglingTag === tag"
                            @click="toggleTag(tag)"
                        >
                            <span>{{ TAG_ICONS[tag] }}</span>
                            <span>{{ trans(`tags.${tag}`) }}</span>
                            <span
                                v-if="(tagCounts[tag] ?? 0) > 0"
                                class="ml-0.5 rounded-full px-1.5 py-0.5 text-[10px] leading-none"
                                :class="
                                    userTags.includes(tag)
                                        ? 'bg-primary/20 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ tagCounts[tag] }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex gap-2 pt-1">
                    <a
                        :href="getDirectionsUrlGoogle()"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-opacity hover:opacity-90"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polygon points="3 11 22 2 13 21 11 13 3 11" />
                        </svg>
                        Google Maps
                    </a>
                    <a
                        :href="getDirectionsUrlApple()"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-muted py-3 text-sm font-semibold text-foreground shadow-sm transition-opacity hover:opacity-90"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polygon points="3 11 22 2 13 21 11 13 3 11" />
                        </svg>
                        Apple Maps
                    </a>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
