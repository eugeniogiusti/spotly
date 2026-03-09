<script setup lang="ts">
import { Head, router, InfiniteScroll } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { myPlaces, map as mapRoute } from '@/routes';
import type { BreadcrumbItem } from '@/types';

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

interface Layer {
    key: string;
    label: string;
    icon: string;
    color: string;
}

interface PoiDetails {
    phone?: string;
    website?: string;
    opening_hours?: string;
    cuisine?: string;
    'addr:street'?: string;
    'addr:housenumber'?: string;
    'addr:city'?: string;
}

interface CommunityTags {
    counts: Record<string, number>;
    user_tags: string[];
}

interface SavedPoi {
    id: number;
    poi_external_id: string;
    layer: string;
    name: string;
    lat: number;
    lng: number;
    city: string | null;
    notes: string | null;
    details: PoiDetails;
    community_tags: CommunityTags;
}

interface PaginatedPois {
    data: SavedPoi[];
    next_page_url: string | null;
}

const props = defineProps<{
    pois: PaginatedPois;
    layers: Record<string, Layer> | null;
    cities: string[];
    totalCount: number;
    selectedCity: string | null;
    selectedLayer: string | null;
    search: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: trans('ui.my_places_title'), href: myPlaces() },
];

const expandedId = ref<number | null>(null);
const savingNotes = ref<number | null>(null);
const savedNotes = ref<number | null>(null);
const localNotes = ref<Record<number, string>>({});
const togglingTag = ref<string | null>(null);
const localTags = ref<Record<number, CommunityTags>>({});
const searchQuery = ref(props.search);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (value) => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        const query: Record<string, string> = {};
        if (props.selectedCity) query.city = props.selectedCity;
        if (props.selectedLayer) query.layer = props.selectedLayer;
        if (value.trim()) query.search = value.trim();
        router.visit(myPlaces.url({ query }), { preserveScroll: false });
    }, 400);
});

function tagsFor(poi: SavedPoi): CommunityTags {
    return localTags.value[poi.id] ?? poi.community_tags;
}

function layerInfo(key: string): Layer | undefined {
    return (props.layers ?? {})[key];
}

function getCsrf(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? ''
    );
}

async function removePoi(poi: SavedPoi): Promise<void> {
    await fetch(`/saved-pois/${encodeURIComponent(poi.poi_external_id)}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
    });
    router.reload();
}

function openOnMap(poi: SavedPoi): void {
    router.visit(
        mapRoute.url({
            query: { lat: poi.lat, lng: poi.lng, layer: poi.layer },
        }),
    );
}

function toggleExpand(poi: SavedPoi): void {
    expandedId.value = expandedId.value === poi.id ? null : poi.id;
}

function filterByCity(city: string | null): void {
    const query: Record<string, string> = {};
    if (city) query.city = city;
    if (props.selectedLayer) query.layer = props.selectedLayer;
    if (searchQuery.value.trim()) query.search = searchQuery.value.trim();
    router.visit(myPlaces.url({ query }), { preserveScroll: false });
}

function filterByLayer(layer: string | null): void {
    const query: Record<string, string> = {};
    if (props.selectedCity) query.city = props.selectedCity;
    if (layer) query.layer = layer;
    if (searchQuery.value.trim()) query.search = searchQuery.value.trim();
    router.visit(myPlaces.url({ query }), { preserveScroll: false });
}

function notesFor(poi: SavedPoi): string {
    return localNotes.value[poi.id] ?? poi.notes ?? '';
}

function onNotesInput(poi: SavedPoi, value: string): void {
    localNotes.value[poi.id] = value;
}

async function saveNotes(poi: SavedPoi): Promise<void> {
    const notes = localNotes.value[poi.id] ?? poi.notes ?? '';
    savingNotes.value = poi.id;
    await fetch(
        `/saved-pois/${encodeURIComponent(poi.poi_external_id)}/notes`,
        {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': getCsrf(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notes: notes || null }),
        },
    );
    savingNotes.value = null;
    savedNotes.value = poi.id;
    setTimeout(() => {
        savedNotes.value = null;
    }, 2000);
}

function directionsUrlGoogle(poi: SavedPoi): string {
    return `https://www.google.com/maps/dir/?api=1&destination=${poi.lat},${poi.lng}`;
}

function directionsUrlApple(poi: SavedPoi): string {
    return `https://maps.apple.com/?daddr=${poi.lat},${poi.lng}&q=${encodeURIComponent(poi.name)}`;
}

function addressOf(details: PoiDetails): string | null {
    const parts = [details['addr:street'], details['addr:housenumber']].filter(
        Boolean,
    );
    return parts.length ? parts.join(' ') : null;
}

async function toggleTag(poi: SavedPoi, tag: string): Promise<void> {
    const current = tagsFor(poi);
    togglingTag.value = `${poi.id}:${tag}`;
    try {
        const res = await fetch(
            `/api/pois/${encodeURIComponent(poi.poi_external_id)}/tags`,
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
            const newCounts = { ...current.counts, [data.tag]: data.count };
            const newUserTags = data.added
                ? [...current.user_tags, data.tag]
                : current.user_tags.filter((t) => t !== data.tag);
            localTags.value[poi.id] = {
                counts: newCounts,
                user_tags: newUserTags,
            };
        }
    } catch {
        // silently ignore network/parse errors
    } finally {
        togglingTag.value = null;
    }
}
</script>

<template>
    <Head :title="trans('ui.my_places_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-6">
            <!-- Empty state -->
            <div
                v-if="totalCount === 0"
                class="flex flex-col items-center justify-center gap-4 py-24 text-center"
            >
                <div
                    class="flex h-20 w-20 items-center justify-center rounded-3xl bg-muted text-4xl"
                >
                    🗺️
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-foreground">
                        {{ trans('ui.my_places_empty_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ trans('ui.my_places_empty_subtitle') }}
                    </p>
                </div>
            </div>

            <template v-else>
                <!-- Header -->
                <p class="text-sm text-muted-foreground">
                    {{ trans('ui.places_saved', { count: totalCount }) }}
                </p>

                <!-- Search bar -->
                <div class="relative">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="search"
                        :placeholder="trans('ui.search_places_placeholder')"
                        class="w-full rounded-2xl border border-border bg-card py-2.5 pr-4 pl-9 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                    />
                </div>

                <!-- Layer filter select -->
                <select
                    :value="selectedLayer ?? ''"
                    class="w-full rounded-2xl border border-border bg-card px-4 py-2.5 text-sm text-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                    @change="filterByLayer(($event.target as HTMLSelectElement).value || null)"
                >
                    <option value="">{{ trans('ui.all_layers') }}</option>
                    <option
                        v-for="layer in Object.values(layers ?? {})"
                        :key="layer.key"
                        :value="layer.key"
                    >
                        {{ layer.icon }} {{ trans('layers.' + layer.key) }}
                    </option>
                </select>

                <!-- City filter tabs -->
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            selectedCity === null
                                ? 'bg-foreground text-background'
                                : 'bg-muted text-muted-foreground hover:text-foreground'
                        "
                        @click="filterByCity(null)"
                    >
                        {{ trans('ui.all_count', { count: totalCount }) }}
                    </button>
                    <button
                        v-for="city in cities"
                        :key="city"
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            selectedCity === city
                                ? 'bg-foreground text-background'
                                : 'bg-muted text-muted-foreground hover:text-foreground'
                        "
                        @click="filterByCity(city)"
                    >
                        {{ city }}
                    </button>
                </div>

                <!-- POI list with infinite scroll -->
                <div
                    class="overflow-hidden rounded-2xl border border-border bg-card"
                >
                    <InfiniteScroll data="pois" items-element="#poi-list">
                        <ul id="poi-list" class="divide-y divide-border">
                            <li v-for="poi in pois.data" :key="poi.id">
                                <!-- Main row -->
                                <div
                                    class="group flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                                    @click="toggleExpand(poi)"
                                >
                                    <!-- Layer icon -->
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-sm"
                                        :style="{
                                            background:
                                                (layerInfo(poi.layer)?.color ??
                                                    '#ccc') + '22',
                                        }"
                                    >
                                        {{ layerInfo(poi.layer)?.icon ?? '📍' }}
                                    </div>

                                    <!-- Name + city -->
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ poi.name }}
                                        </p>
                                        <p
                                            v-if="
                                                poi.city &&
                                                selectedCity === null
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ poi.city }}
                                        </p>
                                    </div>

                                    <!-- Expand chevron -->
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                        :class="{
                                            'rotate-180': expandedId === poi.id,
                                        }"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="m6 9 6 6 6-6" />
                                    </svg>

                                    <!-- View on map -->
                                    <button
                                        class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-medium text-primary opacity-0 transition-all group-hover:opacity-100 hover:bg-primary/10"
                                        @click.stop="openOnMap(poi)"
                                    >
                                        {{ trans('ui.view_on_map') }}
                                    </button>

                                    <!-- Remove -->
                                    <button
                                        class="shrink-0 rounded-lg p-1.5 text-muted-foreground/40 transition-colors hover:bg-destructive/10 hover:text-destructive"
                                        @click.stop="removePoi(poi)"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5"
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

                                <!-- Expanded detail panel -->
                                <Transition name="slide">
                                    <div
                                        v-if="expandedId === poi.id"
                                        class="border-t border-border bg-muted/30 px-4 pt-3 pb-4"
                                    >
                                        <dl class="flex flex-col gap-2">
                                            <div
                                                v-if="poi.details.phone"
                                                class="flex items-center gap-2"
                                            >
                                                <dt
                                                    class="w-5 shrink-0 text-base"
                                                >
                                                    📞
                                                </dt>
                                                <dd>
                                                    <a
                                                        :href="`tel:${poi.details.phone}`"
                                                        class="text-sm text-primary hover:underline"
                                                        >{{
                                                            poi.details.phone
                                                        }}</a
                                                    >
                                                </dd>
                                            </div>
                                            <div
                                                v-if="poi.details.website"
                                                class="flex items-center gap-2"
                                            >
                                                <dt
                                                    class="w-5 shrink-0 text-base"
                                                >
                                                    🌐
                                                </dt>
                                                <dd class="min-w-0">
                                                    <a
                                                        :href="
                                                            poi.details.website
                                                        "
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="block truncate text-sm text-primary hover:underline"
                                                        >{{
                                                            poi.details.website.replace(
                                                                /^https?:\/\//,
                                                                '',
                                                            )
                                                        }}</a
                                                    >
                                                </dd>
                                            </div>
                                            <div
                                                v-if="addressOf(poi.details)"
                                                class="flex items-center gap-2"
                                            >
                                                <dt
                                                    class="w-5 shrink-0 text-base"
                                                >
                                                    📍
                                                </dt>
                                                <dd
                                                    class="text-sm text-foreground"
                                                >
                                                    {{ addressOf(poi.details) }}
                                                </dd>
                                            </div>
                                            <div
                                                v-if="poi.details.opening_hours"
                                                class="flex items-start gap-2"
                                            >
                                                <dt
                                                    class="w-5 shrink-0 text-base"
                                                >
                                                    🕐
                                                </dt>
                                                <dd
                                                    class="text-sm text-foreground"
                                                >
                                                    {{
                                                        poi.details
                                                            .opening_hours
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                v-if="poi.details.cuisine"
                                                class="flex items-center gap-2"
                                            >
                                                <dt
                                                    class="w-5 shrink-0 text-base"
                                                >
                                                    🍽️
                                                </dt>
                                                <dd
                                                    class="text-sm text-foreground capitalize"
                                                >
                                                    {{
                                                        poi.details.cuisine.replace(
                                                            /_/g,
                                                            ' ',
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>

                                        <!-- Community tags -->
                                        <div
                                            class="mt-3 flex flex-wrap gap-1.5"
                                        >
                                            <button
                                                v-for="tag in TAGS"
                                                :key="tag"
                                                class="flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-all disabled:opacity-50"
                                                :class="
                                                    tagsFor(
                                                        poi,
                                                    ).user_tags.includes(tag)
                                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                                        : 'border-border bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
                                                "
                                                :disabled="
                                                    togglingTag ===
                                                    `${poi.id}:${tag}`
                                                "
                                                @click.stop="
                                                    toggleTag(poi, tag)
                                                "
                                            >
                                                <span>{{
                                                    TAG_ICONS[tag]
                                                }}</span>
                                                <span>{{
                                                    trans(`tags.${tag}`)
                                                }}</span>
                                                <span
                                                    v-if="
                                                        (tagsFor(poi).counts[
                                                            tag
                                                        ] ?? 0) > 0
                                                    "
                                                    class="ml-0.5 rounded-full px-1 text-[10px] leading-none"
                                                    :class="
                                                        tagsFor(
                                                            poi,
                                                        ).user_tags.includes(
                                                            tag,
                                                        )
                                                            ? 'bg-primary/20 text-primary'
                                                            : 'bg-muted text-muted-foreground'
                                                    "
                                                >
                                                    {{
                                                        tagsFor(poi).counts[tag]
                                                    }}
                                                </span>
                                            </button>
                                        </div>

                                        <!-- Personal notes -->
                                        <div class="mt-3 flex flex-col gap-2">
                                            <textarea
                                                :value="notesFor(poi)"
                                                :placeholder="trans('ui.notes_placeholder')"
                                                rows="2"
                                                class="w-full resize-none rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                                                @input="
                                                    onNotesInput(
                                                        poi,
                                                        (
                                                            $event.target as HTMLTextAreaElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                            <div
                                                class="flex items-center justify-end gap-2"
                                            >
                                                <Transition name="fade">
                                                    <span
                                                        v-if="
                                                            savedNotes ===
                                                            poi.id
                                                        "
                                                        class="text-xs text-green-600 dark:text-green-400"
                                                        >{{ trans('ui.notes_saved') }}</span
                                                    >
                                                </Transition>
                                                <button
                                                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                                                    :class="
                                                        savingNotes === poi.id
                                                            ? 'bg-muted text-muted-foreground'
                                                            : 'bg-primary text-primary-foreground hover:bg-primary/90'
                                                    "
                                                    :disabled="
                                                        savingNotes === poi.id
                                                    "
                                                    @click="saveNotes(poi)"
                                                >
                                                    {{
                                                        savingNotes === poi.id
                                                            ? trans('ui.saving')
                                                            : trans('ui.save_note')
                                                    }}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button
                                                class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                                @click="openOnMap(poi)"
                                            >
                                                {{ trans('ui.view_on_map') }}
                                            </button>
                                            <a
                                                v-if="poi.details.phone"
                                                :href="`tel:${poi.details.phone}`"
                                                class="flex items-center gap-1.5 rounded-lg bg-muted px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted/70"
                                            >
                                                {{ trans('ui.call') }}
                                            </a>
                                            <a
                                                v-if="poi.details.website"
                                                :href="poi.details.website"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-1.5 rounded-lg bg-muted px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted/70"
                                            >
                                                {{ trans('ui.website') }}
                                            </a>
                                            <a
                                                :href="directionsUrlGoogle(poi)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-1.5 rounded-lg bg-muted px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted/70"
                                            >
                                                Google Maps
                                            </a>
                                            <a
                                                :href="directionsUrlApple(poi)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-1.5 rounded-lg bg-muted px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted/70"
                                            >
                                                Apple Maps
                                            </a>
                                        </div>
                                    </div>
                                </Transition>
                            </li>
                        </ul>

                        <template #loading>
                            <div class="flex justify-center py-4">
                                <svg
                                    class="h-5 w-5 animate-spin text-muted-foreground"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v8z"
                                    />
                                </svg>
                            </div>
                        </template>
                    </InfiniteScroll>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
}
.slide-enter-to,
.slide-leave-from {
    opacity: 1;
    max-height: 500px;
}
</style>
