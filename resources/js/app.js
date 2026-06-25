import './bootstrap';
import { animate, inView, stagger } from 'motion';
import ApexCharts from 'apexcharts';

window.Motion = { animate, inView, stagger };
window.ApexCharts = ApexCharts;

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

    window.Alpine.directive('reveal', (el, { value }, { evaluate }) => {
        const delay = value ? Number(value) / 1000 : 0;

        animate(
            el,
            { opacity: [0, 1], transform: ['translateY(16px)', 'translateY(0px)'] },
            { duration: 0.7, delay, easing: [0.22, 1, 0.36, 1] }
        );
    });
});
