<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard, map as mapRoute } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Layer {
    key: string;
    label: string;
    icon: string;
    color: string;
}

interface CityData {
    city: string;
    count: number;
    layers: string[];
}

interface RecentPlace {
    id: number;
    name: string;
    layer: string;
    city: string | null;
    lat: number;
    lng: number;
    created_at: string;
}

interface Stats {
    totalSaved: number;
    citiesCount: number;
    favoriteLayer: string | null;
}

const props = defineProps<{
    stats: Stats;
    cities: CityData[];
    recentPlaces: RecentPlace[];
    layers: Record<string, Layer>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

function layerInfo(key: string): Layer | undefined {
    return props.layers[key];
}

function openOnMap(place: RecentPlace): void {
    router.visit(
        mapRoute.url({
            query: { lat: place.lat, lng: place.lng, layer: place.layer },
        }),
    );
}

function exploreCity(city: CityData): void {
    router.visit(`/my-places?city=${encodeURIComponent(city.city)}`);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div
                    class="flex flex-col gap-1 rounded-2xl bg-card p-5 shadow-sm"
                >
                    <span class="text-3xl font-bold text-foreground">{{
                        stats.totalSaved
                    }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        trans('dashboard.saved_places')
                    }}</span>
                    <span class="mt-1 text-2xl">📍</span>
                </div>
                <div
                    class="flex flex-col gap-1 rounded-2xl bg-card p-5 shadow-sm"
                >
                    <span class="text-3xl font-bold text-foreground">{{
                        stats.citiesCount
                    }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        trans('dashboard.cities_explored')
                    }}</span>
                    <span class="mt-1 text-2xl">🗺️</span>
                </div>
                <div
                    class="flex flex-col gap-1 rounded-2xl bg-card p-5 shadow-sm"
                >
                    <span class="text-3xl font-bold text-foreground">
                        {{
                            stats.favoriteLayer
                                ? (layerInfo(stats.favoriteLayer)?.icon ?? '—')
                                : '—'
                        }}
                    </span>
                    <span class="text-sm text-muted-foreground">{{
                        trans('dashboard.favorite_layer')
                    }}</span>
                    <span class="mt-1 text-sm font-medium text-foreground">
                        {{
                            stats.favoriteLayer
                                ? (layerInfo(stats.favoriteLayer)?.label ??
                                  stats.favoriteLayer)
                                : trans('dashboard.none')
                        }}
                    </span>
                </div>
            </div>

            <!-- Cities grid -->
            <div v-if="cities.length > 0" class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-foreground">
                        {{ trans('dashboard.your_cities') }}
                    </h2>
                    <button
                        class="cursor-pointer text-sm text-muted-foreground hover:text-foreground"
                        @click="router.visit('/my-places')"
                    >
                        {{ trans('dashboard.view_all') }} →
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="city in cities"
                        :key="city.city"
                        class="flex flex-col gap-3 rounded-2xl bg-card p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-foreground">
                                    {{ city.city }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ city.count }}
                                    {{
                                        city.count === 1
                                            ? trans('dashboard.place')
                                            : trans('dashboard.places')
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-wrap justify-end gap-1">
                                <span
                                    v-for="layerKey in city.layers"
                                    :key="layerKey"
                                    class="flex h-7 w-7 items-center justify-center rounded-full text-sm"
                                    :style="{
                                        background:
                                            (layerInfo(layerKey)?.color ??
                                                '#888') + '22',
                                        border:
                                            '1px solid ' +
                                            (layerInfo(layerKey)?.color ??
                                                '#888') +
                                            '55',
                                    }"
                                    :title="layerInfo(layerKey)?.label"
                                >
                                    {{ layerInfo(layerKey)?.icon }}
                                </span>
                            </div>
                        </div>
                        <button
                            class="w-full cursor-pointer rounded-xl bg-muted py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted/80"
                            @click="exploreCity(city)"
                        >
                            {{ trans('dashboard.see_places') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent places -->
            <div v-if="recentPlaces.length > 0" class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-foreground">
                        {{ trans('dashboard.recent') }}
                    </h2>
                    <button
                        class="cursor-pointer text-sm text-muted-foreground hover:text-foreground"
                        @click="router.visit('/my-places')"
                    >
                        {{ trans('dashboard.view_all') }} →
                    </button>
                </div>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="place in recentPlaces"
                        :key="place.id"
                        class="flex items-center gap-3 rounded-2xl bg-card p-4 shadow-sm"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg"
                            :style="{
                                background:
                                    (layerInfo(place.layer)?.color ?? '#888') +
                                    '22',
                            }"
                        >
                            {{ layerInfo(place.layer)?.icon ?? '📍' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-foreground">
                                {{ place.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ place.city ?? '—' }} ·
                                {{ formatDate(place.created_at) }}
                            </p>
                        </div>
                        <button
                            class="shrink-0 cursor-pointer rounded-xl bg-muted px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted/80"
                            @click="openOnMap(place)"
                        >
                            🗺️ {{ trans('dashboard.map_btn') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="stats.totalSaved === 0"
                class="flex flex-col items-center justify-center gap-4 py-24 text-center"
            >
                <div
                    class="flex h-20 w-20 items-center justify-center rounded-3xl bg-muted text-4xl"
                >
                    🗺️
                </div>
                <div>
                    <p class="font-semibold text-foreground">
                        {{ trans('dashboard.empty_title') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('dashboard.empty_subtitle') }}
                    </p>
                </div>
                <button
                    class="cursor-pointer rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground"
                    @click="router.visit('/map')"
                >
                    {{ trans('dashboard.go_to_map') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
