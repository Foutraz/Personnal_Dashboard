import './bootstrap';
import { animate, inView, stagger } from 'motion';
import ApexCharts from 'apexcharts';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.Motion = { animate, inView, stagger };
window.ApexCharts = ApexCharts;
window.L = L;

const chartTheme = {
    chart: {
        background: 'transparent',
        toolbar: { show: false },
        fontFamily: 'Outfit, sans-serif',
        animations: { enabled: true, easing: 'easeinout', speed: 700 },
    },
    theme: { mode: 'dark' },
    grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4 },
    tooltip: { theme: 'dark' },
    colors: ['#2ff3ff', '#9d6bff', '#c5ff4a'],
};

const mergeChartOptions = (options) => ({
    ...chartTheme,
    ...options,
    chart: { ...chartTheme.chart, ...(options.chart ?? {}) },
});

document.addEventListener('alpine:init', () => {
    window.Alpine.directive('apexchart', (el, { expression }, { evaluate, cleanup }) => {
        const options = mergeChartOptions(evaluate(expression));
        const chart = new ApexCharts(el, options);
        chart.render();

        cleanup(() => chart.destroy());
    });

    window.Alpine.directive('leaflet', (el, { expression }, { evaluate, cleanup }) => {
        const config = evaluate(expression);
        const routes = config.routes ?? [];
        const cells = config.cells ?? [];

        const map = L.map(el, {
            zoomControl: true,
            attributionControl: false,
            preferCanvas: true,
        }).setView([46.6, 2.4], 6);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            maxZoom: 19,
            subdomains: 'abcd',
        }).addTo(map);

        const bounds = [];

        cells.forEach((cell) => {
            const intensity = Math.min(cell.count ?? 1, 12) / 12;
            L.circleMarker([cell.lat, cell.lng], {
                radius: 4,
                stroke: false,
                fillColor: '#c5ff4a',
                fillOpacity: 0.18 + intensity * 0.45,
            }).addTo(map);
            bounds.push([cell.lat, cell.lng]);
        });

        routes.forEach((points) => {
            const latlngs = points.map((p) => [p.lat, p.lng]);
            if (latlngs.length < 2) {
                return;
            }
            L.polyline(latlngs, {
                color: '#2ff3ff',
                weight: 2.5,
                opacity: 0.85,
                lineJoin: 'round',
            }).addTo(map);
            latlngs.forEach((ll) => bounds.push(ll));
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
        }

        cleanup(() => map.remove());
    });

    window.Alpine.directive('reveal', (el, { value }, { evaluate }) => {
        const delay = value ? Number(value) / 1000 : 0;

        animate(
            el,
            { opacity: [0, 1], transform: ['translateY(16px)', 'translateY(0px)'] },
            { duration: 0.7, delay, easing: [0.22, 1, 0.36, 1] }
        );
    });
});
