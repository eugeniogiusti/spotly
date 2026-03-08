<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import L from 'leaflet';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CitySearch from '@/components/CitySearch.vue';
import LayerToggle from '@/components/LayerToggle.vue';
import PoiCard from '@/components/PoiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { map as mapRoute } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

interface Layer {
    key: string;
    label: string;
    icon: string;
    color: string;
}

interface GeocodingResult {
    display_name: string;
    lat: number;
    lon: number;
    bbox: [number, number, number, number];
}

interface Poi {
    id: number;
    external_id: string;
    layer: string;
    name: string;
    lat: number;
    lng: number;
    raw_data: Record<string, any>;
}


const props = defineProps<{ layers: Layer[]; savedPoiIds: string[] }>();

// Local copy so saves/unsaves persist within the session without a full reload
const localSavedPoiIds = ref<string[]>([]);
watch(() => props.savedPoiIds, (ids: string[]) => { localSavedPoiIds.value = [...ids]; }, { immediate: true });

function onPoiSaved(externalId: string): void {
    if (!localSavedPoiIds.value.includes(externalId)) {
        localSavedPoiIds.value = [...localSavedPoiIds.value, externalId];
    }
    if (activeMarker && activeMarkerLayer && selectedPoi.value?.external_id === externalId) {
        activeMarkerSaved = true;
        activeMarker.setIcon(createSelectedMarkerIcon(activeMarkerLayer, true));
    }
}

function onPoiUnsaved(externalId: string): void {
    localSavedPoiIds.value = localSavedPoiIds.value.filter((id) => id !== externalId);
    if (activeMarker && activeMarkerLayer && selectedPoi.value?.external_id === externalId) {
        activeMarkerSaved = false;
        activeMarker.setIcon(createSelectedMarkerIcon(activeMarkerLayer, false));
    }
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Map', href: mapRoute() }];

const page = usePage();
const isLoggedIn = !!page.props.auth?.user;

const mapContainer = ref<HTMLDivElement | null>(null);
const activeLayers = ref<string[]>([]);
const loadingLayer = ref<string | null>(null);
const failedLayer = ref<string | null>(null);
const selectedPoi = ref<Poi | null>(null);
const selectedLayer = ref<Layer | null>(null);
const isLocating = ref(false);

let map: L.Map | null = null;
let moveDebounce: ReturnType<typeof setTimeout> | null = null;
let activeMarker: L.Marker | null = null;
let activeMarkerLayer: Layer | null = null;
let activeMarkerSaved = false;
let locationMarker: L.CircleMarker | null = null;
let searchMarker: L.Marker | null = null;
let targetPoiLatLng: { lat: number; lng: number } | null = null;

// One MarkerClusterGroup per active layer
const clusterGroups = new Map<string, L.MarkerClusterGroup>();

function layerByKey(key: string): Layer | undefined {
    return props.layers.find((l) => l.key === key);
}

const HEART_BADGE = `<div style="position:absolute;bottom:-3px;right:-3px;width:14px;height:14px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;box-shadow:0 1px 3px rgba(0,0,0,0.2)">❤️</div>`;

// Build a custom circular divIcon colored by layer
function createMarkerIcon(layer: Layer, isSaved = false): L.DivIcon {
    return L.divIcon({
        html: `<div style="position:relative;background:${layer.color};width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 2px 8px rgba(0,0,0,0.25);border:2px solid white;">${layer.icon}${isSaved ? HEART_BADGE : ''}</div>`,
        className: '',
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        popupAnchor: [0, -20],
    });
}

// Larger icon with animated pulsing ring for the selected marker
function createSelectedMarkerIcon(layer: Layer, isSaved = false): L.DivIcon {
    return L.divIcon({
        html: `<div class="marker-selected" style="position:relative;--mc:${layer.color};background:${layer.color};width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;border:2px solid white;">${layer.icon}${isSaved ? HEART_BADGE : ''}</div>`,
        className: '',
        iconSize: [44, 44],
        iconAnchor: [22, 22],
        popupAnchor: [0, -24],
    });
}

function deselectActiveMarker(): void {
    if (activeMarker && activeMarkerLayer) {
        activeMarker.setIcon(createMarkerIcon(activeMarkerLayer, activeMarkerSaved));
    }
    activeMarker = null;
    activeMarkerLayer = null;
    activeMarkerSaved = false;
}

function saveMapPosition(): void {
    if (!map) return;
    const c = map.getCenter();
    localStorage.setItem('map_lat', String(c.lat));
    localStorage.setItem('map_lng', String(c.lng));
    localStorage.setItem('map_zoom', String(map.getZoom()));
}

function loadMapPosition(): [number, number, number] {
    const lat = parseFloat(localStorage.getItem('map_lat') ?? '');
    const lng = parseFloat(localStorage.getItem('map_lng') ?? '');
    const zoom = parseInt(localStorage.getItem('map_zoom') ?? '');
    if (!isNaN(lat) && !isNaN(lng) && !isNaN(zoom)) {
        return [lat, lng, zoom];
    }
    return [54.5, 15.0, 4]; // fallback: Europe overview
}

function onKeyDown(e: KeyboardEvent): void {
    if (e.key !== 'Escape') return;
    deselectActiveMarker();
    selectedPoi.value = null;
    selectedLayer.value = null;
}

onMounted(() => {
    window.addEventListener('keydown', onKeyDown, true);

    if (!mapContainer.value) return;

    const [initLat, initLng, initZoom] = loadMapPosition();
    map = L.map(mapContainer.value).setView([initLat, initLng], initZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:
            '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    // Refresh active layers with debounce on pan/zoom
    map.on('moveend', () => {
        saveMapPosition();

        if (moveDebounce) clearTimeout(moveDebounce);
        moveDebounce = setTimeout(() => {
            activeLayers.value.forEach((key) => {
                // Skip layers that are currently being loaded by a user action
                if (key !== loadingLayer.value) {
                    loadLayer(key);
                }
            });
        }, 500);
    });

    // Fly to a specific POI if lat/lng query params are present (coming from My Places)
    const params = new URLSearchParams(window.location.search);
    const lat = parseFloat(params.get('lat') ?? '');
    const lng = parseFloat(params.get('lng') ?? '');
    const layerParam = params.get('layer');
    if (!isNaN(lat) && !isNaN(lng)) {
        targetPoiLatLng = { lat, lng };
        map.flyTo([lat, lng], 17, { duration: 1.2 });
        // auto-activate the layer; loadLayer will auto-select the matching POI
        if (layerParam && !activeLayers.value.includes(layerParam)) {
            toggleLayer(layerParam);
        }
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeyDown, true);
    if (moveDebounce) clearTimeout(moveDebounce);
    map?.remove();
    map = null;
});

function getBbox(): string {
    if (!map) return '';
    const b = map.getBounds();
    return `${b.getSouth()},${b.getWest()},${b.getNorth()},${b.getEast()}`;
}

async function loadLayer(layerKey: string) {
    if (!map) return;

    const layer = layerByKey(layerKey);
    if (!layer) return;

    const bbox = getBbox();
    const response = await fetch(
        `/api/pois?bbox=${encodeURIComponent(bbox)}&layer=${layerKey}`,
    );

    if (!response.ok) {
        throw new Error(`Failed to load layer ${layerKey}: ${response.status}`);
    }

    const pois: Poi[] = await response.json();

    // Remove old cluster group, rebuild fresh
    clusterGroups.get(layerKey)?.remove();

    const cluster = (L as any).markerClusterGroup({
        maxClusterRadius: 60,
        disableClusteringAtZoom: 17,
        spiderfyOnMaxZoom: false,
        iconCreateFunction: (clusterObj: any) => {
            const count = clusterObj.getChildCount();
            return L.divIcon({
                html: `<div style="
                    background:${layer.color};
                    width:38px;height:38px;
                    border-radius:50%;
                    display:flex;align-items:center;justify-content:center;
                    color:white;font-weight:700;font-size:13px;
                    box-shadow:0 2px 8px rgba(0,0,0,0.25);
                    border:2px solid white;
                ">${count}</div>`,
                className: '',
                iconSize: [38, 38],
                iconAnchor: [19, 19],
            });
        },
    });

    pois.forEach((poi) => {
        if (!poi.lat || !poi.lng) return;

        const isSaved = localSavedPoiIds.value.includes(poi.external_id);

        const marker = L.marker([poi.lat, poi.lng], {
            icon: createMarkerIcon(layer, isSaved),
        });

        marker.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            deselectActiveMarker();
            marker.setIcon(createSelectedMarkerIcon(layer, isSaved));
            activeMarker = marker;
            activeMarkerLayer = layer;
            activeMarkerSaved = isSaved;
            selectedPoi.value = poi;
            selectedLayer.value = layer;
        });

        cluster.addLayer(marker);

        // Auto-select: target from My Places (first load)
        const isTarget =
            targetPoiLatLng &&
            Math.abs(poi.lat - targetPoiLatLng.lat) < 0.00001 &&
            Math.abs(poi.lng - targetPoiLatLng.lng) < 0.00001;

        // Re-select: layer was reloaded but this POI was already selected
        const isReselect =
            !targetPoiLatLng &&
            selectedPoi.value !== null &&
            Math.abs(poi.lat - (selectedPoi.value?.lat ?? -9999)) < 0.00001 &&
            Math.abs(poi.lng - (selectedPoi.value?.lng ?? -9999)) < 0.00001;

        if (isTarget || isReselect) {
            if (isTarget) targetPoiLatLng = null;
            setTimeout(() => {
                deselectActiveMarker();
                marker.setIcon(createSelectedMarkerIcon(layer, isSaved));
                activeMarker = marker;
                activeMarkerLayer = layer;
                activeMarkerSaved = isSaved;
                if (isTarget) {
                    selectedPoi.value = poi;
                    selectedLayer.value = layer;
                }
            }, 300);
        }
    });

    if (!map) return; // map was destroyed while loading
    cluster.addTo(map);
    clusterGroups.set(layerKey, cluster);
}

async function toggleLayer(layerKey: string) {
    if (activeLayers.value.includes(layerKey)) {
        activeLayers.value = activeLayers.value.filter((k) => k !== layerKey);
        clusterGroups.get(layerKey)?.remove();
        clusterGroups.delete(layerKey);
        if (selectedPoi.value?.layer === layerKey) {
            selectedPoi.value = null;
            selectedLayer.value = null;
        }
    } else {
        activeLayers.value = [...activeLayers.value, layerKey];
        loadingLayer.value = layerKey;
        try {
            await loadLayer(layerKey);
        } catch {
            // Both attempts failed — deactivate layer and show brief error
            activeLayers.value = activeLayers.value.filter((k) => k !== layerKey);
            clusterGroups.get(layerKey)?.remove();
            clusterGroups.delete(layerKey);
            failedLayer.value = layerKey;
            setTimeout(() => { failedLayer.value = null; }, 4000);
        } finally {
            loadingLayer.value = null;
        }
    }
}

function onSearchCleared(): void {
    searchMarker?.remove();
    searchMarker = null;
}

function createSearchPinIcon(): L.DivIcon {
    return L.divIcon({
        html: `<div style="display:flex;flex-direction:column;align-items:center;">
            <div style="width:28px;height:28px;background:#e53e3e;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.35);"></div>
        </div>`,
        className: '',
        iconSize: [28, 34],
        iconAnchor: [14, 34],
    });
}

function onCitySelected(result: GeocodingResult) {
    if (!map) return;

    const [lat1, lon1, lat2, lon2] = result.bbox;
    const bboxSpan = Math.max(Math.abs(lat2 - lat1), Math.abs(lon2 - lon1));
    const isAddress = bboxSpan < 0.02;

    searchMarker?.remove();
    searchMarker = null;

    // After the fly animation ends, reload active layers using the visible viewport
    map.once('moveend', () => {
        if (activeLayers.value.length > 0) {
            activeLayers.value.forEach((key) => loadLayer(key));
        }
    });

    if (isAddress) {
        searchMarker = L.marker([result.lat, result.lon], { icon: createSearchPinIcon() }).addTo(map);
        map.flyTo([result.lat, result.lon], 17, { duration: 1.2 });
    } else {
        map.flyTo([result.lat, result.lon], 13, { duration: 1.2 });
    }
}

function locateMe(): void {
    if (!map || isLocating.value) return;

    isLocating.value = true;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords;

            locationMarker?.remove();
            locationMarker = L.circleMarker([latitude, longitude], {
                radius: 10,
                color: '#3b82f6',
                fillColor: '#3b82f6',
                fillOpacity: 1,
                weight: 3,
                opacity: 0.3,
            }).addTo(map!);

            map!.flyTo([latitude, longitude], 14, { duration: 1.2 });
            isLocating.value = false;
        },
        () => {
            isLocating.value = false;
        },
        { enableHighAccuracy: true, timeout: 8000 },
    );
}
</script>

<template>
    <Head title="Map" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-0 flex-1 overflow-hidden">
            <div ref="mapContainer" class="h-full w-full" />

            <!-- Layer load error toast -->
            <Transition name="fade">
                <div
                    v-if="failedLayer"
                    class="absolute top-4 left-1/2 z-[1001] -translate-x-1/2 rounded-xl bg-red-500 px-4 py-2.5 text-sm text-white shadow-lg"
                >
                    ⚠️ {{ trans('ui.layer_load_error') }}
                </div>
            </Transition>

            <!-- City search + locate — floating top-right -->
            <div
                class="absolute top-4 right-4 z-[1000] flex items-center gap-2"
            >
                <button
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-lg transition-colors hover:bg-gray-50 disabled:opacity-50"
                    :disabled="isLocating"
                    :title="isLocating ? trans('ui.locating') : trans('ui.my_location')"
                    @click="locateMe"
                >
                    <svg
                        v-if="!isLocating"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-blue-500"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="12" cy="12" r="3" />
                        <path d="M12 2v3m0 14v3M2 12h3m14 0h3" />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 animate-spin text-blue-500"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                    </svg>
                </button>
                <CitySearch @city-selected="onCitySelected" @cleared="onSearchCleared" />
            </div>

            <!-- Layer toggle bar — floating bottom-center -->
            <div class="absolute bottom-6 left-1/2 z-[1000] -translate-x-1/2">
                <LayerToggle
                    :layers="props.layers"
                    :active-layers="activeLayers"
                    :loading-layer="loadingLayer"
                    @toggle="toggleLayer"
                />
            </div>

            <!-- POI bottom sheet -->
            <PoiCard
                :poi="selectedPoi"
                :layer-color="selectedLayer?.color ?? '#6366F1'"
                :layer-icon="selectedLayer?.icon ?? '📍'"
                :saved-poi-ids="localSavedPoiIds"
                :is-logged-in="isLoggedIn"
                @saved="onPoiSaved"
                @unsaved="onPoiUnsaved"
                @close="
                    deselectActiveMarker();
                    selectedPoi = null;
                    selectedLayer = null;
                "
            />
        </div>
    </AppLayout>
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
</style>

<style>
@keyframes pulse-ring {
    0%,
    100% {
        box-shadow:
            0 0 0 3px white,
            0 0 0 6px var(--mc),
            0 4px 12px rgba(0, 0, 0, 0.3);
    }
    50% {
        box-shadow:
            0 0 0 3px white,
            0 0 0 13px var(--mc),
            0 6px 18px rgba(0, 0, 0, 0.35);
    }
}

.marker-selected {
    animation: pulse-ring 1.4s ease-in-out infinite;
}
</style>
