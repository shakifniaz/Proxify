<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    CalendarCheck,
    CheckCircle2,
    ClipboardList,
    FileDown,
    Megaphone,
    RotateCw,
    ShieldCheck,
} from 'lucide-vue-next';

const props = defineProps({
    activeRoutineName: { type: String, default: '' },
    dateRangeLabel: { type: String, default: '' },
    stats: { type: Array, default: () => [] },
    routine: { type: Object, default: () => ({}) },
    proxy: { type: Object, default: () => ({}) },
    leaves: { type: Object, default: () => ({}) },
    notices: { type: Object, default: () => ({}) },
    exams: { type: Object, default: () => ({}) },
});

const maxTeacherLoad = computed(() => maxOf(props.routine.teacherLoad, 'count'));
const maxProxyLoad = computed(() => maxOf(props.proxy.teacherLoad, 'count'));
const maxAbsenceLoad = computed(() => maxOf(props.proxy.absenceLoad, 'count'));
const maxLeaveDay = computed(() => maxOf(props.leaves.daily, 'count'));
const maxClassCoverage = computed(() => maxOf(props.routine.classCoverage, 'scheduled'));
const leaveChartPoints = computed(() => chartPoints(props.leaves.daily ?? [], 'count'));
const metricIcons = [ClipboardList, RotateCw, CalendarCheck, ShieldCheck];
const routineGapPercent = computed(() => {
    const assigned = Number(props.routine.assignedSlots ?? 0);
    const gaps = Number(props.routine.unallocated ?? 0);
    return assigned + gaps ? Math.round((gaps / (assigned + gaps)) * 100) : 0;
});
const operationsScore = computed(() => {
    const routineScore = Number(props.routine.coverage ?? 0);
    const proxyScore = Number(props.proxy.resolutionRate ?? 0);
    const noticeScore = Number(props.notices.ackRate ?? 0);
    return Math.round((routineScore + proxyScore + noticeScore) / 3);
});
const noticePieStyle = computed(() => {
    const urgent = Number(props.notices.urgent ?? 0);
    const posted = Math.max(1, Number(props.notices.posted ?? 0));
    const urgentPercent = Math.min(100, Math.round((urgent / posted) * 100));
    return {
        background: `conic-gradient(#ef4444 0 ${urgentPercent}%, #09B884 ${urgentPercent}% 100%)`,
    };
});

function maxOf(items = [], key) {
    return Math.max(1, ...items.map((item) => Number(item[key] ?? 0)));
}

function barWidth(value, max) {
    return `${Math.max(4, (Number(value ?? 0) / max) * 100)}%`;
}

function ringStyle(value, color = '#09B884') {
    const amount = Math.max(0, Math.min(100, Number(value ?? 0)));
    return {
        background: `conic-gradient(${color} ${amount}%, #ecfdf3 ${amount}% 100%)`,
    };
}

function toneChip(tone) {
    return {
        good: 'border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]',
        warn: 'border-amber-200 bg-amber-50 text-amber-800',
        bad: 'border-red-200 bg-red-50 text-red-800',
        neutral: 'border-stone-200 bg-white text-slate-600',
    }[tone] ?? 'border-stone-200 bg-white text-slate-600';
}

function tonePanel(tone) {
    return {
        good: 'border-[#8BED9A]/80 bg-gradient-to-br from-[#ecfdf3] via-white to-[#8BED9A]/20',
        warn: 'border-amber-200 bg-gradient-to-br from-amber-50 via-white to-amber-100/70',
        bad: 'border-red-200 bg-gradient-to-br from-red-50 via-white to-red-100/70',
        neutral: 'border-stone-200 bg-gradient-to-br from-stone-50 via-white to-stone-100/70',
    }[tone] ?? 'border-stone-200 bg-gradient-to-br from-stone-50 via-white to-stone-100/70';
}

function chartPoints(items = [], key) {
    if (!items.length) return '0,130 520,130';
    const width = 520;
    const height = 150;
    const max = maxOf(items, key);
    return items.map((item, index) => {
        const x = items.length === 1 ? width : (index / (items.length - 1)) * width;
        const y = height - (Number(item[key] ?? 0) / max) * 120 - 15;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
}

function analyticsTableSections() {
    return [
        {
            title: 'Summary metrics',
            headers: ['Metric', 'Value', 'Detail', 'Status'],
            rows: props.stats.map((stat) => [
                stat.label,
                stat.value,
                stat.detail,
                stat.tone,
            ]),
        },
        {
            title: 'Routine coverage',
            headers: ['Metric', 'Value'],
            rows: [
                ['Active routine', props.activeRoutineName || 'No active routine'],
                ['Date range', props.dateRangeLabel],
                ['Coverage percent', props.routine.coverage ?? 0],
                ['Sections', props.routine.sectionCount ?? 0],
                ['Teachers', props.routine.teacherCount ?? 0],
                ['Assigned slots', props.routine.assignedSlots ?? 0],
                ['Open gaps', props.routine.unallocated ?? 0],
                ['Saved routines', props.routine.routineCount ?? 0],
                ['Open gap ratio percent', routineGapPercent.value],
            ],
        },
        {
            title: 'Teacher workload',
            headers: ['Rank', 'Teacher', 'Scheduled periods'],
            rows: (props.routine.teacherLoad ?? []).map((item, index) => [
                index + 1,
                item.teacher,
                item.count,
            ]),
        },
        {
            title: 'Class activity',
            headers: ['Rank', 'Class', 'Scheduled class periods'],
            rows: (props.routine.classCoverage ?? []).map((item, index) => [
                index + 1,
                item.className,
                item.scheduled,
            ]),
        },
        {
            title: 'Proxy engine',
            headers: ['Metric', 'Value'],
            rows: [
                ['Approved runs', props.proxy.approvedRuns ?? 0],
                ['Resolution rate percent', props.proxy.resolutionRate ?? 0],
                ['Resolved periods', props.proxy.resolved ?? 0],
                ['Open periods', props.proxy.unresolved ?? 0],
                ['Swaps', props.proxy.swapCount ?? 0],
            ],
        },
        {
            title: 'Proxy load by teacher',
            headers: ['Teacher', 'Assignments'],
            rows: (props.proxy.teacherLoad ?? []).map((item) => [
                item.teacher,
                item.count,
            ]),
        },
        {
            title: 'Absence pressure',
            headers: ['Teacher', 'Absences'],
            rows: (props.proxy.absenceLoad ?? []).map((item) => [
                item.teacher,
                item.count,
            ]),
        },
        {
            title: 'Leave activity',
            headers: ['Metric', 'Value'],
            rows: [
                ['Approved days', props.leaves.approvedDays ?? 0],
                ['Pending requests', props.leaves.pending ?? 0],
                ['Proxy relevant', props.leaves.proxyRelevant ?? 0],
            ],
        },
        {
            title: 'Approved leave trend',
            headers: ['Day', 'Approved leaves'],
            rows: (props.leaves.daily ?? []).map((item) => [
                item.day,
                item.count,
            ]),
        },
        {
            title: 'Leave types',
            headers: ['Type', 'Count'],
            rows: (props.leaves.typeBreakdown ?? []).map((item) => [
                item.type,
                item.count,
            ]),
        },
        {
            title: 'Noticeboard',
            headers: ['Metric', 'Value'],
            rows: [
                ['Posted notices', props.notices.posted ?? 0],
                ['Urgent notices', props.notices.urgent ?? 0],
                ['Reads', props.notices.reads ?? 0],
                ['Acknowledgement rate percent', props.notices.ackRate ?? 0],
            ],
        },
        {
            title: 'Exam readiness',
            headers: ['Metric', 'Value'],
            rows: [
                ['Total schedules', props.exams.totalSchedules ?? 0],
                ['Active exam', props.exams.active?.name ?? 'No active exam'],
                ['Active date range', props.exams.active?.dateRange ?? ''],
                ['Active halls', props.exams.active?.halls ?? ''],
                ['Scheduled groups', props.exams.scheduledGroups ?? 0],
                ['Draft schedules', props.exams.drafts ?? 0],
            ],
        },
        {
            title: 'Attention needed',
            headers: ['Class', 'Subject', 'Teacher', 'Remaining slots'],
            rows: (props.routine.unallocatedItems ?? []).map((item) => [
                item.classLabel,
                item.subject,
                item.teacherName,
                item.remaining,
            ]),
        },
    ];
}

function escapeHtml(value) {
    const text = value === null || value === undefined ? '' : String(value);
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderPrintableTable(section) {
    const rows = section.rows.length ? section.rows : [section.headers.map(() => '')];

    return `
        <section class="report-section">
            <h2>${escapeHtml(section.title)}</h2>
            <table>
                <thead>
                    <tr>${section.headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>
                    `).join('')}
                </tbody>
            </table>
        </section>
    `;
}

function exportAnalyticsTablesPdf() {
    const date = new Date().toISOString().slice(0, 10);
    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    document.body.appendChild(frame);

    const report = frame.contentWindow;
    report.document.open();
    report.document.write(`
        <!doctype html>
        <html>
            <head>
                <title>Analytics tables ${date}</title>
                <style>
                    @page { margin: 18mm; }
                    * { box-sizing: border-box; }
                    body {
                        color: #1e2924;
                        font-family: Arial, Helvetica, sans-serif;
                        margin: 0;
                        padding: 0;
                    }
                    header {
                        border-bottom: 2px solid #1e2924;
                        margin-bottom: 18px;
                        padding-bottom: 12px;
                    }
                    h1 {
                        font-size: 22px;
                        margin: 0 0 6px;
                    }
                    .meta {
                        color: #475569;
                        font-size: 12px;
                        font-weight: 700;
                        margin: 0;
                    }
                    .report-section {
                        break-inside: avoid;
                        margin: 0 0 18px;
                    }
                    h2 {
                        color: #047857;
                        font-size: 14px;
                        margin: 0 0 8px;
                    }
                    table {
                        border-collapse: collapse;
                        font-size: 11px;
                        width: 100%;
                    }
                    th,
                    td {
                        border: 1px solid #d6d3d1;
                        padding: 7px 8px;
                        text-align: left;
                        vertical-align: top;
                    }
                    th {
                        background: #ecfdf3;
                        color: #1e2924;
                        font-size: 10px;
                        text-transform: uppercase;
                    }
                    tr:nth-child(even) td {
                        background: #fafaf9;
                    }
                </style>
            </head>
            <body>
                <header>
                    <h1>Analytics tables</h1>
                    <p class="meta">${escapeHtml(props.activeRoutineName || 'No active routine')} | ${escapeHtml(props.dateRangeLabel)} | Exported ${date}</p>
                </header>
                ${analyticsTableSections().map(renderPrintableTable).join('')}
            </body>
        </html>
    `);
    report.document.close();

    setTimeout(() => {
        report.focus();
        report.print();
        setTimeout(() => frame.remove(), 1000);
    }, 100);
}
</script>

<template>
    <AppLayout title="Analytics">
        <div class="analytics-shell space-y-4">
            <section class="hero-panel relative overflow-hidden rounded-[1.75rem] border border-[#8BED9A]/70 p-4 shadow-sm sm:p-5">
                <div class="hero-glow"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div class="hero-icon">
                            <BarChart3 class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="eyebrow text-[#09B884]">Institution analytics</p>
                            <p class="mt-1 truncate text-2xl font-black leading-tight text-[#1e2924]">{{ activeRoutineName || 'No active routine' }}</p>
                            <p class="mt-1 max-w-3xl text-sm font-semibold leading-5 text-slate-600">A live snapshot of routine coverage, proxy pressure, leaves, notices, and exam readiness.</p>
                        </div>
                    </div>

                    <div class="relative flex shrink-0 items-center gap-2">
                        <span class="control-pill">{{ dateRangeLabel }}</span>
                        <button type="button" class="btn-primary h-11 px-4 text-sm shadow-lg shadow-[#1e2924]/15" @click="exportAnalyticsTablesPdf">
                            <FileDown class="h-4 w-4" />
                            Export PDF
                        </button>
                    </div>
                </div>
            </section>

            <section class="grid gap-3 lg:grid-cols-4">
                <article
                    v-for="(stat, index) in stats"
                    :key="stat.label"
                    class="metric-tile group overflow-hidden rounded-2xl border p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                    :class="tonePanel(stat.tone)"
                    :style="{ animationDelay: `${index * 70}ms` }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="metric-icon">
                            <component :is="metricIcons[index] || Activity" class="h-4 w-4" />
                        </span>
                        <span class="status-chip" :class="toneChip(stat.tone)">{{ stat.tone }}</span>
                    </div>
                    <div class="mt-4 min-w-0">
                        <p class="truncate text-[10px] font-black uppercase tracking-[0.11em] text-slate-500">{{ stat.label }}</p>
                        <div class="mt-1 flex items-end justify-between gap-3">
                            <p class="truncate text-3xl font-black leading-none text-[#1e2924]">{{ stat.value }}</p>
                        </div>
                        <p class="mt-2 truncate text-xs font-bold text-slate-600">{{ stat.detail }}</p>
                    </div>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.55fr)]">
                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon dark"><ClipboardList class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Routine coverage</p>
                                <p class="panel-subtitle">{{ routine.sectionCount ?? 0 }} sections, {{ routine.teacherCount ?? 0 }} teachers</p>
                            </div>
                        </div>
                        <span class="coverage-header-number">{{ routine.coverage ?? 0 }}%</span>
                    </div>

                    <div class="grid gap-4 p-4 pt-0 lg:grid-cols-[16rem_minmax(0,1fr)]">
                        <div class="insight-card">
                            <div class="coverage-readout">
                                <span>{{ routine.coverage ?? 0 }}%</span>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="stat-box">
                                    <p>{{ routine.assignedSlots ?? 0 }}</p>
                                    <span>assigned</span>
                                </div>
                                <div class="stat-box warn">
                                    <p>{{ routine.unallocated ?? 0 }}</p>
                                    <span>gaps</span>
                                </div>
                                <div class="stat-box">
                                    <p>{{ routine.routineCount ?? 0 }}</p>
                                    <span>saved</span>
                                </div>
                            </div>
                            <div class="mt-3 rounded-2xl bg-white/80 p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Open gap ratio</p>
                                <div class="mt-2 h-2 rounded-full bg-stone-100">
                                    <div class="animated-bar h-2 rounded-full" :class="routineGapPercent > 5 ? 'bg-amber-500' : 'bg-[#09B884]'" :style="{ width: `${Math.max(4, routineGapPercent)}%` }"></div>
                                </div>
                                <p class="mt-2 text-xs font-bold text-slate-600">{{ routineGapPercent }}% of routine slots need review</p>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="chart-card elevated">
                                <p class="chart-title">Teacher workload</p>
                                <div class="mt-4 space-y-2">
                                    <div v-for="(item, index) in routine.teacherLoad" :key="item.teacher" class="rank-row">
                                        <span class="rank-number">{{ index + 1 }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-black text-[#1e2924]">{{ item.teacher }}</p>
                                            <p class="text-[11px] font-bold text-slate-500">scheduled periods</p>
                                        </div>
                                        <span class="count-badge">{{ item.count }}</span>
                                    </div>
                                    <p v-if="!routine.teacherLoad?.length" class="empty-note">No routine load data yet.</p>
                                </div>
                            </div>

                            <div class="chart-card elevated">
                                <p class="chart-title">Class activity</p>
                                <div class="mt-4 space-y-2">
                                    <div v-for="(item, index) in routine.classCoverage" :key="item.className" class="rank-row">
                                        <span class="rank-number">{{ index + 1 }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-black text-[#1e2924]">{{ item.className }}</p>
                                            <p class="text-[11px] font-bold text-slate-500">scheduled class periods</p>
                                        </div>
                                        <span class="count-badge">{{ item.scheduled }}</span>
                                    </div>
                                    <p v-if="!routine.classCoverage?.length" class="empty-note">No class coverage data yet.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon dark"><RotateCw class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Proxy engine</p>
                                <p class="panel-subtitle">{{ proxy.approvedRuns ?? 0 }} approved runs</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 pt-0">
                        <div class="proxy-hero">
                            <div class="flex items-center justify-between gap-4">
                                <div class="progress-ring" :style="ringStyle(proxy.resolutionRate, '#1e2924')">
                                    <div class="progress-ring-inner">
                                        <span>{{ proxy.resolutionRate ?? 0 }}%</span>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black text-[#1e2924]">Daily substitution pressure</p>
                                    <p class="mt-1 text-xs font-bold leading-5 text-slate-600">Tracks approved proxy runs, unresolved periods, and teacher coverage balance.</p>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div class="stat-box"><p>{{ proxy.resolved ?? 0 }}</p><span>done</span></div>
                                <div class="stat-box danger"><p>{{ proxy.unresolved ?? 0 }}</p><span>open</span></div>
                                <div class="stat-box"><p>{{ proxy.swapCount ?? 0 }}</p><span>swaps</span></div>
                            </div>
                        </div>

                        <div class="chart-card elevated mt-3">
                            <p class="chart-title">Proxy load by teacher</p>
                            <div class="mt-4 space-y-3">
                                <div v-for="item in proxy.teacherLoad" :key="item.teacher" class="bar-row">
                                    <div class="flex items-center justify-between gap-3 text-xs font-bold">
                                        <span class="truncate text-slate-700">{{ item.teacher }}</span>
                                        <span class="text-[#1e2924]">{{ item.count }}</span>
                                    </div>
                                    <div class="mt-1.5 h-2.5 rounded-full bg-stone-100">
                                        <div class="animated-bar h-2.5 rounded-full bg-[#1e2924]" :style="{ width: barWidth(item.count, maxProxyLoad) }"></div>
                                    </div>
                                </div>
                                <p v-if="!proxy.teacherLoad?.length" class="empty-note">No proxy assignments recorded yet.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_23rem]">
                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon"><CalendarCheck class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Leave activity</p>
                                <p class="panel-subtitle">{{ leaves.approvedDays ?? 0 }} approved days, {{ leaves.proxyRelevant ?? 0 }} proxy relevant</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 p-4 pt-0 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="chart-card elevated">
                            <div class="relative h-64 overflow-hidden rounded-2xl bg-gradient-to-br from-[#8BED9A]/18 via-white to-[#09B884]/10 p-4">
                                <div class="absolute inset-x-4 top-4 flex items-center justify-between">
                                    <p class="chart-title">Approved leave trend</p>
                                    <span class="soft-chip">{{ leaves.pending ?? 0 }} pending</span>
                                </div>
                                <svg class="h-full w-full overflow-visible" viewBox="0 0 520 150" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="leaveLine" x1="0" x2="1" y1="0" y2="0">
                                            <stop offset="0%" stop-color="#09B884" />
                                            <stop offset="100%" stop-color="#1e2924" />
                                        </linearGradient>
                                    </defs>
                                    <polyline points="0,145 520,145" fill="none" stroke="#e7ece7" stroke-width="1" />
                                    <polyline :points="leaveChartPoints" fill="none" stroke="url(#leaveLine)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="absolute bottom-4 left-4 right-4 flex justify-between text-[10px] font-black uppercase text-slate-400">
                                    <span>{{ leaves.daily?.[0]?.day || 'Start' }}</span>
                                    <span>{{ leaves.daily?.[leaves.daily.length - 1]?.day || 'Today' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="chart-card elevated">
                            <p class="chart-title">Leave types</p>
                            <div class="mt-4 space-y-3">
                                <div v-for="item in leaves.typeBreakdown" :key="item.type" class="rounded-xl border border-stone-200 bg-stone-50 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-black text-[#1e2924]">{{ item.type }}</span>
                                        <span class="rounded-full bg-white px-2 py-1 text-xs font-black text-slate-700">{{ item.count }}</span>
                                    </div>
                                </div>
                                <p v-if="!leaves.typeBreakdown?.length" class="empty-note">No leave requests yet.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon amber"><Activity class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Absence pressure</p>
                                <p class="panel-subtitle">From proxy runs</p>
                            </div>
                        </div>
                    </div>
                    <div class="chart-card elevated m-4 mt-0">
                        <div class="space-y-3">
                            <div v-for="item in proxy.absenceLoad" :key="item.teacher" class="bar-row">
                                <div class="flex items-center justify-between gap-3 text-xs font-bold">
                                    <span class="truncate text-slate-700">{{ item.teacher }}</span>
                                    <span class="text-[#1e2924]">{{ item.count }}</span>
                                </div>
                                <div class="mt-1.5 h-2.5 rounded-full bg-stone-100">
                                    <div class="animated-bar h-2.5 rounded-full bg-gradient-to-r from-amber-400 to-amber-600" :style="{ width: barWidth(item.count, maxAbsenceLoad) }"></div>
                                </div>
                            </div>
                            <p v-if="!proxy.absenceLoad?.length" class="empty-note">No absent teacher data yet.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-3">
                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon"><Megaphone class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Noticeboard</p>
                                <p class="panel-subtitle">{{ notices.posted ?? 0 }} notices posted</p>
                            </div>
                        </div>
                    </div>
                    <div class="mx-4 grid gap-2 sm:grid-cols-3">
                        <div class="mini-stat">
                            <p class="mini-label">Urgent</p>
                            <p class="mini-value text-red-700">{{ notices.urgent ?? 0 }}</p>
                        </div>
                        <div class="mini-stat">
                            <p class="mini-label">Reads</p>
                            <p class="mini-value text-[#1e2924]">{{ notices.reads ?? 0 }}</p>
                        </div>
                        <div class="mini-stat">
                            <p class="mini-label">Ack</p>
                            <p class="mini-value text-[#09B884]">{{ notices.ackRate ?? 0 }}%</p>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-4 rounded-2xl border border-stone-200 bg-gradient-to-br from-stone-50 to-white p-4">
                            <div class="notice-donut" :style="noticePieStyle">
                                <div></div>
                            </div>
                            <div>
                                <p class="text-sm font-black text-[#1e2924]">Notice mix</p>
                                <p class="mt-2 text-xs font-semibold text-slate-600">Red shows urgent notices. Green shows normal and important institutional communication.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon"><ShieldCheck class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Exam readiness</p>
                                <p class="panel-subtitle">{{ exams.totalSchedules ?? 0 }} schedules saved</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 pt-0">
                        <div v-if="exams.active" class="exam-card">
                            <p class="text-sm font-black text-[#1e2924]">{{ exams.active.name }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-600">{{ exams.active.dateRange }}</p>
                            <p class="mt-4 text-3xl font-black text-[#1e2924]">{{ exams.scheduledGroups }}</p>
                            <p class="text-xs font-semibold text-slate-500">exam groups across {{ exams.active.halls }} halls</p>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-stone-200 p-4">
                            <p class="text-sm font-black text-[#1e2924]">No active exam schedule</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ exams.drafts ?? 0 }} drafts ready for review</p>
                        </div>
                    </div>
                </div>

                <div class="surface-card overflow-hidden">
                    <div class="panel-heading border-none">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="panel-icon amber"><AlertTriangle class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="panel-title">Attention needed</p>
                                <p class="panel-subtitle">Routine allocation gaps</p>
                            </div>
                        </div>
                    </div>
                    <div class="mx-4 mb-4 max-h-72 space-y-2 overflow-y-auto rounded-2xl bg-stone-50 p-3">
                        <div v-for="item in routine.unallocatedItems" :key="`${item.classLabel}-${item.subject}-${item.teacherName}`" class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <p class="text-sm font-black text-amber-900">{{ item.classLabel }}</p>
                            <p class="mt-1 text-xs font-semibold text-amber-800">{{ item.subject }} with {{ item.teacherName }} needs {{ item.remaining }} more</p>
                        </div>
                        <div v-if="!routine.unallocatedItems?.length" class="rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 p-4">
                            <div class="flex items-center gap-2 text-[#1e2924]">
                                <CheckCircle2 class="h-4 w-4 text-[#09B884]" />
                                <p class="text-sm font-black">No allocation gaps found</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.analytics-shell {
    position: relative;
    animation: analyticsFade 360ms ease both;
}

.hero-panel {
    background:
        radial-gradient(circle at 9% 12%, rgba(139, 237, 154, 0.52), transparent 30%),
        radial-gradient(circle at 80% 0%, rgba(9, 184, 132, 0.14), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(236, 253, 243, 0.86) 52%, rgba(9, 184, 132, 0.08));
}

.hero-glow {
    position: absolute;
    inset: auto -6rem -8rem auto;
    height: 14rem;
    width: 24rem;
    border-radius: 999px;
    background: rgba(9, 184, 132, 0.13);
    filter: blur(36px);
}

.hero-icon,
.metric-icon,
.panel-icon {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
}

.hero-icon {
    height: 3rem;
    width: 3rem;
    border-radius: 1rem;
    background: #1e2924;
    color: #8BED9A;
    box-shadow: 0 16px 36px rgba(30, 41, 36, 0.16);
}

.eyebrow {
    font-size: 0.66rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    text-transform: uppercase;
}

.control-pill,
.soft-chip,
.status-chip {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-weight: 900;
}

.control-pill {
    min-height: 2.75rem;
    border: 1px solid rgba(255, 255, 255, 0.78);
    background: rgba(255, 255, 255, 0.82);
    padding: 0 1rem;
    color: #1e2924;
    box-shadow: 0 10px 24px rgba(30, 41, 36, 0.08);
    font-size: 0.86rem;
}

.metric-tile,
.surface-card {
    min-width: 0;
    animation: cardRise 420ms ease both;
}

.metric-tile {
    min-height: 8.6rem;
    box-shadow: 0 16px 34px rgba(30, 41, 36, 0.06);
}

.metric-icon {
    height: 2.25rem;
    width: 2.25rem;
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.78);
    color: #09B884;
    box-shadow: inset 0 0 0 1px rgba(9, 184, 132, 0.14);
}

.status-chip {
    border-width: 1px;
    padding: 0.18rem 0.55rem;
    font-size: 0.55rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.surface-card {
    border: 1px solid rgb(231 229 228);
    border-radius: 1.35rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 42px rgba(30, 41, 36, 0.06);
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem;
    background: transparent;
}

.panel-heading > div {
    min-width: 0;
}

.panel-heading p {
    overflow-wrap: anywhere;
}

.panel-icon {
    height: 2.25rem;
    width: 2.25rem;
    border-radius: 0.875rem;
    background: rgba(139, 237, 154, 0.18);
    color: #09B884;
}

.panel-icon.dark {
    background: #1e2924;
    color: #8BED9A;
    box-shadow: 0 12px 22px rgba(30, 41, 36, 0.14);
}

.panel-icon.amber {
    background: rgba(245, 158, 11, 0.14);
    color: #b45309;
}

.panel-title {
    color: #1e2924;
    font-size: 1rem;
    font-weight: 900;
}

.panel-subtitle {
    margin-top: 0.1rem;
    color: rgb(100 116 139);
    font-size: 0.75rem;
    font-weight: 700;
}

.soft-chip {
    border: 1px solid rgba(139, 237, 154, 0.7);
    background: rgba(236, 253, 243, 0.9);
    padding: 0.35rem 0.75rem;
    color: #1e2924;
    font-size: 0.72rem;
}

.soft-chip.dark {
    border-color: #1e2924;
    background: #1e2924;
    color: #fff;
}

.coverage-header-number {
    flex-shrink: 0;
    color: #1e2924;
    font-size: 1.45rem;
    font-weight: 950;
    line-height: 1;
    transition: transform 220ms ease, color 220ms ease;
}

.surface-card:hover .coverage-header-number {
    color: #09B884;
    transform: translateY(-1px);
}

.insight-card,
.proxy-hero,
.exam-card {
    border: 1px solid rgba(139, 237, 154, 0.7);
    border-radius: 1.25rem;
    background:
        radial-gradient(circle at 10% 0%, rgba(139, 237, 154, 0.28), transparent 34%),
        linear-gradient(145deg, rgba(236, 253, 243, 0.92), rgba(255, 255, 255, 0.96));
    padding: 1rem;
}

.insight-card {
    transition: border-color 220ms ease, box-shadow 220ms ease, transform 220ms ease;
}

.insight-card:hover {
    border-color: rgba(9, 184, 132, 0.62);
    box-shadow: 0 18px 38px rgba(9, 184, 132, 0.12);
    transform: translateY(-2px);
}

.coverage-readout {
    display: flex;
    min-height: 8.5rem;
    align-items: center;
    justify-content: center;
}

.coverage-readout span {
    color: #1e2924;
    font-size: clamp(3.6rem, 6vw, 5.2rem);
    font-weight: 950;
    letter-spacing: -0.04em;
    line-height: 0.9;
    text-shadow: 0 14px 30px rgba(30, 41, 36, 0.1);
    transition: transform 260ms cubic-bezier(.2, .8, .2, 1), color 220ms ease, text-shadow 220ms ease;
}

.insight-card:hover .coverage-readout span {
    color: #09B884;
    transform: scale(1.05);
    text-shadow: 0 18px 34px rgba(9, 184, 132, 0.2);
}

.stat-box {
    min-width: 0;
    border: 1px solid rgba(231, 229, 228, 0.9);
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.82);
    padding: 0.65rem 0.5rem;
    box-shadow: 0 8px 18px rgba(30, 41, 36, 0.05);
    text-align: center;
    transition: transform 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
}

.stat-box:hover {
    border-color: rgba(9, 184, 132, 0.35);
    box-shadow: 0 12px 24px rgba(30, 41, 36, 0.08);
    transform: translateY(-2px);
}

.stat-box p {
    color: #1e2924;
    font-size: 1.1rem;
    font-weight: 900;
    line-height: 1;
}

.stat-box span {
    margin-top: 0.3rem;
    display: block;
    color: rgb(100 116 139);
    font-size: 0.58rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.stat-box.warn p {
    color: #b45309;
}

.stat-box.danger p {
    color: #b91c1c;
}

.proxy-hero {
    background:
        radial-gradient(circle at 92% 12%, rgba(139, 237, 154, 0.34), transparent 32%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(236, 253, 243, 0.88));
}

.progress-ring {
    display: grid;
    width: 7rem;
    height: 7rem;
    flex: 0 0 7rem;
    place-items: center;
    border-radius: 999px;
    box-shadow: 0 14px 30px rgba(30, 41, 36, 0.12);
    transition: transform 220ms ease, box-shadow 220ms ease;
}

.proxy-hero:hover .progress-ring {
    box-shadow: 0 18px 38px rgba(30, 41, 36, 0.16);
    transform: scale(1.03);
}

.progress-ring-inner {
    display: grid;
    width: 4.8rem;
    height: 4.8rem;
    place-items: center;
    border-radius: 999px;
    background: #fff;
    box-shadow: inset 0 0 0 1px rgba(226, 232, 240, 0.9);
}

.progress-ring-inner span {
    color: #1e2924;
    font-size: 1.55rem;
    font-weight: 950;
    line-height: 1;
}

.exam-card {
    min-height: 13rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.chart-card {
    background: #fff;
    min-width: 0;
    padding: 1rem;
}

.chart-card.elevated {
    border: 1px solid rgb(231 229 228);
    border-radius: 1.1rem;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(250, 250, 249, 0.92));
    box-shadow: 0 12px 24px rgba(30, 41, 36, 0.05);
}

.chart-title {
    font-size: 0.8125rem;
    font-weight: 900;
    color: #1e2924;
    overflow-wrap: anywhere;
}

.rank-row {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid rgb(231 229 228);
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.82);
    padding: 0.65rem;
    transition: transform 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
}

.rank-row:hover {
    border-color: rgba(9, 184, 132, 0.32);
    box-shadow: 0 12px 24px rgba(30, 41, 36, 0.06);
    transform: translateY(-2px);
}

.rank-number {
    display: grid;
    width: 1.65rem;
    height: 1.65rem;
    flex: 0 0 1.65rem;
    place-items: center;
    border-radius: 0.65rem;
    background: rgba(139, 237, 154, 0.18);
    color: #047857;
    font-size: 0.72rem;
    font-weight: 950;
}

.count-badge {
    display: grid;
    min-width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    border-radius: 0.8rem;
    background: #1e2924;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 950;
}

.bar-row {
    animation: cardRise 420ms ease both;
}

.animated-bar {
    transform-origin: left;
    animation: barGrow 700ms cubic-bezier(.2, .8, .2, 1) both;
}

.empty-note {
    border: 1px dashed rgb(214 211 209);
    border-radius: 0.75rem;
    padding: 1rem;
    color: rgb(100 116 139);
    font-size: 0.875rem;
    font-weight: 600;
}

.mini-stat {
    min-width: 0;
    border: 1px solid rgb(231 229 228);
    border-radius: 1rem;
    background: linear-gradient(180deg, #fff, rgba(236, 253, 243, 0.5));
    padding: 0.75rem;
    box-shadow: 0 8px 18px rgba(30, 41, 36, 0.04);
    text-align: center;
    transition: transform 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
}

.mini-stat:hover {
    border-color: rgba(9, 184, 132, 0.3);
    box-shadow: 0 12px 24px rgba(30, 41, 36, 0.07);
    transform: translateY(-2px);
}

.mini-label {
    font-size: 0.65rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(100 116 139);
    overflow-wrap: anywhere;
}

.mini-value {
    margin-top: 0.35rem;
    font-size: 1.5rem;
    line-height: 1.1;
    font-weight: 900;
}

.notice-donut {
    display: grid;
    width: 5.75rem;
    height: 5.75rem;
    flex: 0 0 5.75rem;
    place-items: center;
    border-radius: 999px;
    box-shadow: 0 12px 24px rgba(30, 41, 36, 0.08);
}

.notice-donut > div {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 999px;
    background: #fff;
    box-shadow: inset 0 0 0 1px rgba(226, 232, 240, 0.9);
}

@media (max-width: 1280px) {
    .mini-value {
        font-size: 1.35rem;
    }
}

@keyframes analyticsFade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes cardRise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes barGrow {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}
</style>
