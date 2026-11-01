<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { icon, map, marker, tileLayer } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

const latitude = defineModel('latitude');
const longitude = defineModel('longitude');
const container = ref(null);
let locationMap;
let locationMarker;

onMounted(() => {
    locationMap = map(container.value, { scrollWheelZoom: false }).setView([latitude.value, longitude.value], 16);
    tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(locationMap);
    locationMarker = marker([latitude.value, longitude.value], {
        draggable: true,
        autoPan: true,
        title: 'Incident location — drag to adjust',
        icon: icon({ iconUrl: markerIcon, iconRetinaUrl: markerIconRetina, shadowUrl: markerShadow, iconSize: [25, 41], iconAnchor: [12, 41], shadowSize: [41, 41] }),
    }).addTo(locationMap);
    locationMarker.on('dragend', () => {
        const position = locationMarker.getLatLng().wrap();
        latitude.value = Number(position.lat.toFixed(7));
        longitude.value = Number(position.lng.toFixed(7));
    });
});

watch([latitude, longitude], ([lat, lon]) => {
    if (!locationMap) return;
    locationMarker.setLatLng([lat, lon]);
    locationMap.panTo([lat, lon]);
});

onBeforeUnmount(() => locationMap?.remove());
</script>

<template>
    <div class="mt-5">
        <div ref="container" class="relative z-0 h-64 overflow-hidden rounded-xl border border-slate-200 sm:h-72" role="region" aria-label="Incident location map" aria-describedby="map-help"></div>
        <p id="map-help" class="mt-2 text-xs text-slate-500">Drag the marker to the incident’s exact location, or adjust the coordinates below.</p>
    </div>
</template>