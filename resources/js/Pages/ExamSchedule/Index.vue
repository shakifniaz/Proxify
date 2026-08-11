<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock3,
    DoorOpen,
    Eye,
    Pencil,
    Plus,
    Printer,
    Search,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    pageMode: { type: String, default: 'editor' },
    role: { type: String, default: 'admin' },
    currentTeacherName: { type: String, default: '' },
    viewerClassLabel: { type: String, default: '' },
    hasActiveSchedule: { type: Boolean, default: true },
    schedules: { type: Array, default: () => [] },
    session: { type: Object, default: () => ({}) },
    halls: { type: Array, default: () => [] },
    timeSlots: { type: Array, default: () => [] },
    subjectOptions: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    classSubjectOptions: { type: Object, default: () => ({}) },
    invigilatorOptions: { type: Array, default: () => [] },
    examGrid: { type: Object, default: () => ({}) },
});

const canEdit = computed(() => props.pageMode === 'editor' && props.role === 'admin');
const isViewer = computed(() => props.pageMode === 'viewer');
const examName = ref(props.session.title || 'New exam schedule');
const examNote = ref(props.session.subtitle || '');
const examStartDate = ref(props.session.startDate || '');
const examEndDate = ref(props.session.endDate || '');
const activeExamDate = ref(examStartDate.value);
const activeStep = ref(canEdit.value ? 'Details' : 'Schedule');
const classMode = ref('all');
const selectedClasses = ref([]);
const classSearch = ref('');
const teacherViewMode = ref(props.role === 'teacher' ? 'mine' : 'all');
const openMenuId = ref(null);
const halls = ref(props.halls.map((hall, index) => ({
    id: hall.id ?? `hall-${index + 1}`,
    name: hall.name,
    capacity: Number(hall.capacity ?? 30),
})));
const defaultTimeSlots = [
    { key: 'slot-1', label: '09:00-11:00', startLabel: '09:00', endLabel: '11:00' },
    { key: 'slot-2', label: '11:30-13:30', startLabel: '11:30', endLabel: '13:30' },
];
const sourceTimeSlots = props.timeSlots.length ? props.timeSlots : defaultTimeSlots;
const timeSlots = ref(sourceTimeSlots.map((slot, index) => ({
    key: slot.key ?? `slot-${index + 1}`,
    label: slot.label,
    startLabel: slot.startLabel ?? slot.label?.split('-')[0] ?? '',
    endLabel: slot.endLabel ?? slot.label?.split('-')[1] ?? '',
})));
const grid = ref(normalizeGrid(props.examGrid));
const newHall = ref({ name: '', capacity: 30 });
const newSlot = ref({ startLabel: '09:00', endLabel: '11:00' });
const editing = ref(null);

const steps = ['Details', 'Setup', 'Schedule', 'Duties'];
const stepDetails = computed(() => [
    { name: 'Details', icon: ClipboardList, hint: `${formatDate(examStartDate.value)} to ${formatDate(examEndDate.value)}` },
    { name: 'Setup', icon: Users, hint: `${activeClasses.value.length} classes, ${halls.value.length} halls` },
    { name: 'Schedule', icon: CalendarDays, hint: `${scheduledRequiredExams.value}/${totalRequiredExams.value} exams placed` },
    { name: 'Duties', icon: ShieldCheck, hint: `${guardDuties.value.length} teachers assigned` },
]);

const visibleClasses = computed(() => {
    const needle = classSearch.value.trim().toLowerCase();
    const source = props.classOptions ?? [];
    if (!needle) return source;
    return source.filter((className) => className.toLowerCase().includes(needle));
});
const activeClasses = computed(() => {
    if (props.role === 'student' && props.viewerClassLabel) return [props.viewerClassLabel];
    return classMode.value === 'all' ? props.classOptions : selectedClasses.value;
});
const scheduledSubjectKeys = computed(() => {
    const keys = new Set();
    for (const cell of scheduledCells.value) {
        for (const exam of cell.exams ?? []) {
            keys.add(`${exam.classLabel}::${exam.subject}`);
        }
    }
    return keys;
});
const unscheduledByClass = computed(() => activeClasses.value.map((className) => ({
    className,
    missing: subjectsForClass(className).filter((subject) => !scheduledSubjectKeys.value.has(`${className}::${subject}`)),
})));
const classesWithMissingSubjects = computed(() => unscheduledByClass.value.filter((item) => item.missing.length));
const totalRequiredExams = computed(() => activeClasses.value.reduce((total, className) => total + subjectsForClass(className).length, 0));
const scheduledRequiredExams = computed(() => {
    let total = 0;
    for (const className of activeClasses.value) {
        for (const subject of subjectsForClass(className)) {
            if (scheduledSubjectKeys.value.has(`${className}::${subject}`)) total += 1;
        }
    }
    return total;
});
const coveragePercent = computed(() => Math.round((scheduledRequiredExams.value / Math.max(1, totalRequiredExams.value)) * 100));
const examDateOptions = computed(() => {
    if (!examStartDate.value || !examEndDate.value || examStartDate.value > examEndDate.value) return [];
    const dates = [];
    const current = parseLocalDate(examStartDate.value);
    const end = parseLocalDate(examEndDate.value);
    while (current <= end) {
        const value = toLocalDateValue(current);
        dates.push({ value, label: formatDate(value) });
        current.setDate(current.getDate() + 1);
    }
    return dates;
});
const currentExamDate = computed(() => {
    if (examDateOptions.value.some((date) => date.value === activeExamDate.value)) return activeExamDate.value;
    return examDateOptions.value[0]?.value ?? examStartDate.value;
});
const scheduledCells = computed(() => {
    const cells = [];
    for (const hall of halls.value) {
        for (const date of examDateOptions.value) {
            for (const slot of timeSlots.value) {
                const cell = cellAt(hall.name, date.value, slot.key);
                if (cell && cellHasActiveClass(cell)) {
                    cells.push({ ...cell, hallName: hall.name, examDate: date.value, slotKey: slot.key, slotLabel: slotDisplayLabel(slot) });
                }
            }
        }
    }
    return cells;
});
const visibleScheduledCells = computed(() => {
    if (props.role === 'teacher' && teacherViewMode.value === 'mine') {
        return scheduledCells.value.filter((cell) => (cell.guards ?? []).includes(props.currentTeacherName));
    }
    return scheduledCells.value;
});
const viewerScheduleDays = computed(() => {
    const days = [];
    for (const date of examDateOptions.value) {
        const entries = [];
        for (const slot of timeSlots.value) {
            for (const hall of halls.value) {
                const cell = cellAt(hall.name, date.value, slot.key);
                if (!cell || !cellHasActiveClass(cell)) continue;
                if (props.role === 'teacher' && teacherViewMode.value === 'mine' && !(cell.guards ?? []).includes(props.currentTeacherName)) continue;
                entries.push({
                    ...cell,
                    exams: examsForViewer(cell),
                    hallName: hall.name,
                    examDate: date.value,
                    slotKey: slot.key,
                    slotLabel: slotDisplayLabel(slot),
                });
            }
        }
        if (entries.length) days.push({ value: date.value, label: date.label, weekday: formatWeekday(date.value), entries });
    }
    return days;
});
const guardDuties = computed(() => {
    const map = new Map();
    for (const cell of scheduledCells.value) {
        for (const guard of cell.guards ?? []) {
            if (!map.has(guard)) map.set(guard, []);
            map.get(guard).push(cell);
        }
    }

    return [...map.entries()]
        .filter(([teacher]) => props.role !== 'teacher' || teacherViewMode.value === 'all' || teacher === props.currentTeacherName)
        .map(([teacher, duties]) => ({
            teacher,
            duties,
            load: duties.length,
            hasConflict: conflictGroups.value.some((group) => group.teacher === teacher),
        }));
});
const conflictGroups = computed(() => {
    const groups = [];
    for (const date of examDateOptions.value) {
        for (const slot of timeSlots.value) {
            const byTeacher = {};
            for (const hall of halls.value) {
                const cell = cellAt(hall.name, date.value, slot.key);
                if (!cell || !cellHasActiveClass(cell)) continue;
                for (const teacher of cell.guards ?? []) {
                    byTeacher[teacher] ??= [];
                    byTeacher[teacher].push(hall.name);
                }
            }

            for (const [teacher, bookedHalls] of Object.entries(byTeacher)) {
                if (bookedHalls.length > 1) {
                    groups.push({ teacher, halls: bookedHalls, examDate: date.value, slotKey: slot.key, slotLabel: slotDisplayLabel(slot) });
                }
            }
        }
    }
    return groups;
});
const gridStyle = computed(() => ({
    gridTemplateColumns: `190px repeat(${Math.max(timeSlots.value.length, 1)}, minmax(170px, 1fr))`,
}));
const activeStepIndex = computed(() => Math.max(0, steps.indexOf(activeStep.value)));
const setupProgress = computed(() => Math.round(((activeStepIndex.value + 1) / steps.length) * 100));
const activeSchedule = computed(() => props.schedules.find((schedule) => schedule.status === 'Active'));

function normalizeGrid(source) {
    const normalized = {};
    for (const [hallName, dateOrSlots] of Object.entries(source ?? {})) {
        normalized[hallName] = {};
        for (const [key, value] of Object.entries(dateOrSlots ?? {})) {
            const looksLikeDateBucket = String(key).includes('-') && value && typeof value === 'object' && !('subject' in value) && !('exams' in value);
            if (looksLikeDateBucket) {
                normalized[hallName][key] ??= {};
                for (const [slotKey, cell] of Object.entries(value ?? {})) {
                    if (!cell) continue;
                    normalized[hallName][key][slotKey] = normalizeCell(cell);
                }
                continue;
            }

            const cell = value;
            if (!cell) continue;
            const date = cell.examDate ?? examStartDate.value;
            normalized[hallName][date] ??= {};
            normalized[hallName][date][key] = normalizeCell(cell);
        }
    }
    return normalized;
}

function normalizeCell(cell) {
    const exams = Array.isArray(cell.exams) && cell.exams.length
        ? cell.exams
        : [{ subject: cell.subject ?? '', classLabel: cell.classLabel ?? '' }];

    return {
        exams: exams
            .map((exam) => ({
                subject: exam.subject ?? '',
                classLabel: exam.classLabel ?? '',
            }))
            .filter((exam) => exam.subject && exam.classLabel),
        guards: Array.isArray(cell.guards) ? cell.guards : [cell.invigilator].filter(Boolean),
    };
}

function cellAt(hallName, date, slotKey) {
    return grid.value[hallName]?.[date]?.[slotKey] ?? null;
}

function shouldShowCell(hallName, date, slotKey) {
    const cell = cellAt(hallName, date, slotKey);
    if (!cell) return !(props.role === 'teacher' && teacherViewMode.value === 'mine');
    if (!cellHasActiveClass(cell)) return false;
    if (props.role === 'teacher' && teacherViewMode.value === 'mine') {
        return (cell.guards ?? []).includes(props.currentTeacherName);
    }
    return true;
}

function cellHasActiveClass(cell) {
    return (cell?.exams ?? []).some((exam) => activeClasses.value.includes(exam.classLabel));
}

function cellTitle(cell) {
    const exams = cell?.exams ?? [];
    if (!exams.length) return 'No exam assigned';
    if (exams.length === 1) return exams[0].subject;
    return `${exams.length} exam groups`;
}

function cellSubtitle(cell) {
    const exams = cell?.exams ?? [];
    if (!exams.length) return '';
    if (exams.length === 1) return exams[0].classLabel;
    return exams.map((exam) => `${exam.classLabel} - ${exam.subject}`).join(', ');
}

function examsForViewer(cell) {
    const exams = cell?.exams ?? [];
    if (props.role === 'student') {
        return exams.filter((exam) => activeClasses.value.includes(exam.classLabel));
    }
    return exams;
}

function isConflict(hallName, date, slotKey) {
    return conflictGroups.value.some((group) => group.examDate === date && group.slotKey === slotKey && group.halls.includes(hallName));
}

function cellClasses(hallName, date, slotKey) {
    const cell = cellAt(hallName, date, slotKey);
    if (!cell || !cellHasActiveClass(cell)) return 'border-dashed border-stone-300 bg-stone-50 text-slate-500 hover:border-[#8BED9A] hover:bg-[#8BED9A]/10';
    if (isConflict(hallName, date, slotKey)) return 'border-red-300 bg-red-50 text-red-900 ring-2 ring-red-100';
    return 'border-[#8BED9A]/70 bg-[#8BED9A]/14 text-[#1e2924] hover:bg-[#8BED9A]/20';
}

function toggleMenu(scheduleId) {
    openMenuId.value = openMenuId.value === scheduleId ? null : scheduleId;
}

function makeActive(schedule) {
    openMenuId.value = null;
    router.post(`/exam-schedule/${schedule.id}/activate`, {}, { preserveScroll: true });
}

function clearActiveSchedule() {
    openMenuId.value = null;
    router.post('/exam-schedule/none/activate', {}, { preserveScroll: true });
}

function examSchedulePayload() {
    return {
        name: examName.value,
        subtitle: examNote.value,
        start_date: examStartDate.value || null,
        end_date: examEndDate.value || null,
        halls: halls.value,
        time_slots: timeSlots.value,
        class_options: props.classOptions,
        subject_options: props.subjectOptions,
        invigilator_options: props.invigilatorOptions,
        exam_grid: grid.value,
    };
}

function saveSchedule() {
    const payload = examSchedulePayload();
    if (!payload.name.trim()) return;

    if (props.session.id) {
        router.put(`/exam-schedule/${props.session.id}`, payload, { preserveScroll: true });
        return;
    }

    router.post('/exam-schedule', payload, { preserveScroll: true });
}

function deleteSchedule(schedule) {
    openMenuId.value = null;
    router.delete(`/exam-schedule/${schedule.id}`, { preserveScroll: true });
}

function addHall() {
    const name = newHall.value.name.trim();
    if (!name || halls.value.some((hall) => hall.name.toLowerCase() === name.toLowerCase())) return;
    halls.value.push({ id: `hall-${Date.now()}`, name, capacity: Number(newHall.value.capacity || 30) });
    grid.value[name] ??= {};
    newHall.value = { name: '', capacity: 30 };
}

function removeHall(hallName) {
    halls.value = halls.value.filter((hall) => hall.name !== hallName);
    delete grid.value[hallName];
}

function addSlot() {
    if (!newSlot.value.startLabel || !newSlot.value.endLabel) return;
    const key = `slot-${Date.now()}`;
    timeSlots.value.push({
        key,
        label: `${newSlot.value.startLabel}-${newSlot.value.endLabel}`,
        startLabel: newSlot.value.startLabel,
        endLabel: newSlot.value.endLabel,
    });
}

function removeSlot(slotKey) {
    timeSlots.value = timeSlots.value.filter((slot) => slot.key !== slotKey);
    for (const hallName of Object.keys(grid.value)) {
        for (const date of Object.keys(grid.value[hallName] ?? {})) {
            delete grid.value[hallName][date][slotKey];
        }
    }
}

function setClassMode(mode) {
    classMode.value = mode;
    if (mode === 'specific') selectedClasses.value = [];
}

function toggleClass(className) {
    selectedClasses.value = selectedClasses.value.includes(className)
        ? selectedClasses.value.filter((item) => item !== className)
        : [...selectedClasses.value, className];
}

function subjectsForClass(className) {
    const classSubjects = props.classSubjectOptions?.[className] ?? [];
    return classSubjects.length ? classSubjects : props.subjectOptions;
}

function handleExamClassChange(exam) {
    if (!subjectsForClass(exam.classLabel).includes(exam.subject)) {
        exam.subject = '';
    }
}

function openEditor(hallName, date, slotKey) {
    if (!canEdit.value) return;
    const cell = cellAt(hallName, date, slotKey);
    editing.value = {
        hallName,
        slotKey,
        examDate: date,
        originalExamDate: date,
        exams: cell?.exams?.length
            ? cell.exams.map((exam) => ({ ...exam }))
            : [{ subject: '', classLabel: activeClasses.value[0] ?? '' }],
        guards: [...(cell?.guards ?? [])],
    };
}

function closeEditor() {
    editing.value = null;
}

function toggleGuard(name) {
    if (!editing.value) return;
    editing.value.guards = editing.value.guards.includes(name)
        ? editing.value.guards.filter((item) => item !== name)
        : [...editing.value.guards, name];
}

function addExamGroup() {
    if (!editing.value) return;
    editing.value.exams.push({ classLabel: activeClasses.value[0] ?? '', subject: '' });
}

function removeExamGroup(index) {
    if (!editing.value || editing.value.exams.length <= 1) return;
    editing.value.exams.splice(index, 1);
}

function validExamGroups() {
    return (editing.value?.exams ?? []).filter((exam) => exam.classLabel && exam.subject);
}

function saveEditor() {
    const exams = validExamGroups();
    if (!editing.value?.examDate || !exams.length || !editing.value.guards.length) return;
    grid.value[editing.value.hallName] ??= {};
    if (editing.value.originalExamDate !== editing.value.examDate && grid.value[editing.value.hallName]?.[editing.value.originalExamDate]) {
        grid.value[editing.value.hallName][editing.value.originalExamDate][editing.value.slotKey] = null;
    }
    grid.value[editing.value.hallName][editing.value.examDate] ??= {};
    grid.value[editing.value.hallName][editing.value.examDate][editing.value.slotKey] = {
        exams: exams.map((exam) => ({ ...exam })),
        guards: [...editing.value.guards],
    };
    closeEditor();
}

function clearCell() {
    if (!editing.value) return;
    if (grid.value[editing.value.hallName]?.[editing.value.examDate]) {
        grid.value[editing.value.hallName][editing.value.examDate][editing.value.slotKey] = null;
    }
    closeEditor();
}

function goToStep(offset) {
    const nextIndex = Math.min(steps.length - 1, Math.max(0, activeStepIndex.value + offset));
    activeStep.value = steps[nextIndex];
}

function printPage() {
    window.print();
}

function parseLocalDate(value) {
    const [year, month, day] = String(value || '').split('-').map(Number);
    return new Date(year, month - 1, day);
}

function toLocalDateValue(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function formatDate(value) {
    const [year, month, day] = String(value || '').split('-');
    if (!year || !month || !day) return value || '';
    return `${day}/${month}/${year.slice(-2)}`;
}

function formatWeekday(value) {
    const date = parseLocalDate(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-US', { weekday: 'short' });
}

function formatTimeLabel(value) {
    const [hourValue, minuteValue = '00'] = String(value || '').trim().split(':');
    const hour = Number(hourValue);
    if (Number.isNaN(hour)) return value || '';
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minuteValue.padStart(2, '0')} ${period}`;
}

function slotDisplayLabel(slot) {
    const [labelStart = '', labelEnd = ''] = String(slot.label || '').split('-');
    const start = slot.startLabel || labelStart.trim();
    const end = slot.endLabel || labelEnd.trim();
    if (!start || !end) return slot.label || '';
    return `${formatTimeLabel(start)} - ${formatTimeLabel(end)}`;
}
</script>

<template>
    <AppLayout title="Exam Schedule">
        <div v-if="pageMode === 'list'" class="space-y-5">
            <section class="surface-card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-5">
                    <div class="min-w-0">
                        <p class="text-lg font-black text-[#1e2924]">Plan and publish exams</p>
                        <p class="mt-1 truncate text-sm font-semibold text-slate-500">Manage drafts, previews, and active visibility.</p>
                        <p v-if="!hasActiveSchedule" class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-800">
                            No exam schedule is currently published
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link href="/exam-schedule?previewRole=teacher" class="btn-secondary min-h-11">
                            <ShieldCheck class="h-4 w-4" />
                            Teacher preview
                        </Link>
                        <Link href="/exam-schedule?previewRole=student" class="btn-secondary min-h-11">
                            <Users class="h-4 w-4" />
                            Student preview
                        </Link>
                        <button type="button" class="btn-secondary min-h-11 border-amber-200 text-amber-800 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!hasActiveSchedule" @click="clearActiveSchedule">
                            <X class="h-4 w-4" />
                            Clear active
                        </button>
                        <Link href="/exam-schedule/new" class="btn-primary min-h-11">
                            <Plus class="h-4 w-4" />
                            New exam schedule
                        </Link>
                    </div>
                </div>
            </section>

            <div class="space-y-3" @click="openMenuId = null">
                <div
                    v-for="schedule in schedules"
                    :key="schedule.id"
                    class="surface-card relative flex cursor-pointer items-center gap-4 p-4 transition hover:border-[#8BED9A]/70 hover:shadow-md"
                    :class="schedule.status === 'Active' ? 'border-[#8BED9A]/70 bg-[#8BED9A]/10 shadow-sm shadow-[#1e2924]/5' : 'border-stone-200 bg-white'"
                    @click="router.visit(`/exam-schedule/${schedule.id}`)"
                >
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border" :class="schedule.status === 'Active' ? 'border-[#8BED9A]/70 bg-white text-[#09B884]' : 'border-stone-200 bg-stone-50 text-slate-500'">
                        <ClipboardList class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-black text-[#1e2924]">{{ schedule.name }}</p>
                            <span v-if="schedule.status === 'Active'" class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-2 py-0.5 text-[11px] font-black text-[#1e2924]">Active</span>
                        </div>
                        <p class="mt-0.5 text-sm font-semibold text-slate-500">
                            {{ schedule.dateRange }} - {{ schedule.classes }} classes - {{ schedule.halls }} halls - {{ schedule.exams }} exams
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-black" :class="schedule.status === 'Active' ? 'bg-[#1e2924] text-white' : schedule.status === 'Draft' ? 'bg-amber-50 text-amber-700' : 'bg-stone-100 text-slate-600'">
                        {{ schedule.status }}
                    </span>
                    <div class="relative" @click.stop>
                        <button type="button" class="btn-secondary h-10 px-3" @click="toggleMenu(schedule.id)">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <div v-if="openMenuId === schedule.id" class="absolute right-0 top-12 z-20 w-52 rounded-xl border border-stone-200 bg-white p-1.5 shadow-lg">
                            <button v-if="schedule.status !== 'Active'" type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-bold text-[#1e2924] hover:bg-[#8BED9A]/15" @click="makeActive(schedule)">
                                <CheckCircle2 class="h-4 w-4" />
                                Make active
                            </button>
                            <Link :href="`/exam-schedule/${schedule.id}`" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-bold text-slate-700 hover:bg-stone-100">
                                <Eye class="h-4 w-4" />
                                Open schedule
                            </Link>
                            <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-bold text-red-700 hover:bg-red-50" @click="deleteSchedule(schedule)">
                                <Trash2 class="h-4 w-4" />
                                Delete schedule
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="space-y-5">
            <section v-if="canEdit" class="surface-card overflow-hidden p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#1e2924] text-[#8BED9A] shadow-lg shadow-[#1e2924]/15">
                            <ClipboardList class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-sm font-black uppercase tracking-[0.18em] text-[#09B884]">Exam builder</p>
                            <p class="mt-1 text-xl font-black text-[#1e2924]">{{ examName }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link href="/exam-schedule" class="btn-secondary">All schedules</Link>
                        <button type="button" class="btn-secondary min-h-11" @click="printPage">
                            <Printer class="h-4 w-4" />
                            Print
                        </button>
                        <button type="button" class="btn-primary min-h-11" :disabled="!examName.trim()" @click="saveSchedule">
                            <CheckCircle2 class="h-4 w-4" />
                            Save draft
                        </button>
                    </div>
                </div>
                <div class="relative mt-5 overflow-hidden rounded-full bg-stone-100">
                    <div class="h-2 rounded-full bg-gradient-to-r from-[#09B884] to-[#8BED9A] transition-all duration-500" :style="{ width: `${setupProgress}%` }"></div>
                </div>
            </section>

            <section v-if="canEdit" class="surface-card p-2">
                <div class="grid gap-2 md:grid-cols-4">
                    <button
                        v-for="(step, index) in stepDetails"
                        :key="step.name"
                        type="button"
                        class="group rounded-xl border p-3 text-left transition-all duration-300 hover:-translate-y-0.5"
                        :class="activeStep === step.name ? 'border-[#1e2924] bg-[#1e2924] text-white shadow-lg shadow-[#1e2924]/15' : 'border-transparent bg-stone-50 text-[#1e2924] hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/15'"
                        @click="activeStep = step.name"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="activeStep === step.name ? 'bg-[#8BED9A]/20 text-[#8BED9A]' : 'bg-white text-[#09B884]'">
                                <component :is="step.icon" class="h-4 w-4" />
                            </span>
                            <span class="text-xs font-black opacity-60">0{{ index + 1 }}</span>
                        </div>
                        <p class="mt-3 text-sm font-black">{{ step.name }}</p>
                        <p class="mt-1 truncate text-xs font-semibold" :class="activeStep === step.name ? 'text-white/60' : 'text-slate-500'">{{ step.hint }}</p>
                    </button>
                </div>
            </section>

            <section v-if="isViewer" class="surface-card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-5">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-white text-[#09B884] shadow-sm">
                            <ClipboardList class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-lg font-black text-[#1e2924]">{{ examName }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">
                                {{ role === 'student' ? viewerClassLabel : role === 'teacher' ? `${currentTeacherName} view` : 'Active exam schedule' }} - {{ formatDate(examStartDate) }} to {{ formatDate(examEndDate) }}
                            </p>
                        </div>
                    </div>
                    <div v-if="role === 'teacher'" class="grid rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1 sm:grid-cols-2">
                        <button type="button" class="rounded-xl px-4 py-2 text-sm font-black transition" :class="teacherViewMode === 'mine' ? 'bg-[#1e2924] text-white shadow-md shadow-[#1e2924]/15' : 'text-[#1e2924] hover:bg-white/80'" @click="teacherViewMode = 'mine'">My duties</button>
                        <button type="button" class="rounded-xl px-4 py-2 text-sm font-black transition" :class="teacherViewMode === 'all' ? 'bg-[#1e2924] text-white shadow-md shadow-[#1e2924]/15' : 'text-[#1e2924] hover:bg-white/80'" @click="teacherViewMode = 'all'">Full schedule</button>
                    </div>
                </div>
            </section>

            <section v-if="isViewer && !hasActiveSchedule" class="surface-card overflow-hidden">
                <div class="grid gap-px bg-stone-200/80 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="bg-white p-8">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#8BED9A]/18 text-[#09B884]">
                                <CalendarDays class="h-7 w-7" />
                            </div>
                            <div>
                                <p class="text-xl font-black text-[#1e2924]">No exam schedule is published right now</p>
                                <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-600">
                                    Exam routines are made visible to teachers and students only when an exam schedule has been marked active by the admin.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-[#8BED9A]/12 p-6">
                        <div class="rounded-2xl border border-[#8BED9A]/60 bg-white p-5">
                            <p class="text-sm font-black text-[#1e2924]">Availability</p>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Schedules will appear here during published exam periods.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="isViewer && role === 'teacher' && teacherViewMode === 'all'" class="surface-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-stone-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-base font-black text-[#1e2924]">Exam routine</p>
                        <p class="text-xs font-semibold text-slate-500">Full published hall schedule</p>
                    </div>
                    <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-black text-[#1e2924] transition hover:bg-stone-50" @click="printPage">
                        <Printer class="h-4 w-4" />
                        Print
                    </button>
                </div>

                <div class="border-b border-stone-200 bg-stone-50 p-4">
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <button v-for="date in examDateOptions" :key="date.value" type="button" class="shrink-0 rounded-xl border px-4 py-2 text-sm font-black transition" :class="currentExamDate === date.value ? 'border-[#1e2924] bg-[#1e2924] text-white shadow-md shadow-[#1e2924]/15' : 'border-stone-200 bg-white text-[#1e2924] hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/15'" @click="activeExamDate = date.value">
                            {{ date.label }}
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[880px]">
                        <div class="grid border-b border-stone-200 bg-stone-50" :style="gridStyle">
                            <div class="px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-slate-500">Hall</div>
                            <div v-for="slot in timeSlots" :key="slot.key" class="border-l border-stone-200 px-3 py-3 text-center text-xs font-black uppercase tracking-[0.14em] text-slate-500">{{ slotDisplayLabel(slot) }}</div>
                        </div>

                        <div v-for="hall in halls" :key="hall.id" class="grid border-b border-stone-200 last:border-b-0" :style="gridStyle">
                            <div class="bg-white px-4 py-4">
                                <p class="text-sm font-black text-[#1e2924]">{{ hall.name }}</p>
                                <p class="text-xs font-semibold text-slate-500">Capacity {{ hall.capacity }}</p>
                            </div>
                            <div v-for="slot in timeSlots" :key="slot.key" class="border-l border-stone-200 bg-white p-2">
                                <div v-if="cellAt(hall.name, currentExamDate, slot.key) && cellHasActiveClass(cellAt(hall.name, currentExamDate, slot.key))" class="min-h-28 w-full rounded-xl border p-3 text-left" :class="cellClasses(hall.name, currentExamDate, slot.key)">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-black">{{ cellTitle(cellAt(hall.name, currentExamDate, slot.key)) }}</p>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs font-bold">{{ cellSubtitle(cellAt(hall.name, currentExamDate, slot.key)) }}</p>
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        <span v-for="guard in cellAt(hall.name, currentExamDate, slot.key).guards" :key="`${hall.name}-${currentExamDate}-${slot.key}-${guard}`" class="rounded-full bg-white/80 px-2 py-1 text-[11px] font-bold">{{ guard }}</span>
                                    </div>
                                </div>
                                <div v-else class="min-h-28 rounded-xl border border-dashed border-stone-200 bg-stone-50/70"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="isViewer" class="surface-card overflow-hidden">
                <div class="border-b border-stone-200 bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-base font-black text-[#1e2924]">{{ role === 'teacher' && teacherViewMode === 'mine' ? 'My exam duties' : 'Exam schedule' }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ viewerScheduleDays.reduce((total, day) => total + day.entries.length, 0) }} scheduled slot{{ viewerScheduleDays.reduce((total, day) => total + day.entries.length, 0) === 1 ? '' : 's' }}</p>
                        </div>
                        <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-black text-[#1e2924] transition hover:bg-stone-50" @click="printPage">
                            <Printer class="h-4 w-4" />
                            Print
                        </button>
                    </div>
                </div>

                <div v-if="viewerScheduleDays.length" class="space-y-4 bg-stone-50 p-4">
                    <section v-for="day in viewerScheduleDays" :key="day.value" class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-200 bg-[#8BED9A]/12 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-white text-sm font-black text-[#09B884] shadow-sm">{{ day.weekday }}</span>
                                <div>
                                    <p class="text-sm font-black text-[#1e2924]">{{ day.label }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ day.entries.length }} exam slot{{ day.entries.length === 1 ? '' : 's' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="divide-y divide-stone-100">
                            <article v-for="entry in day.entries" :key="`${day.value}-${entry.hallName}-${entry.slotKey}`" class="grid gap-3 p-4 md:grid-cols-[9.5rem_10rem_minmax(0,1fr)_14rem] md:items-center">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Time</p>
                                    <p class="mt-1 text-sm font-black text-[#1e2924]">{{ entry.slotLabel }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Hall</p>
                                    <p class="mt-1 text-sm font-black text-[#1e2924]">{{ entry.hallName }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Exam</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="exam in entry.exams" :key="`${entry.hallName}-${entry.slotKey}-${exam.classLabel}-${exam.subject}`" class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 py-1 text-xs font-black text-[#1e2924]">
                                            {{ exam.classLabel }} - {{ exam.subject }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Hall guards</p>
                                    <p class="mt-1 text-sm font-semibold leading-6 text-slate-700">{{ (entry.guards ?? []).join(', ') }}</p>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <div v-else class="bg-white p-8 text-center">
                    <p class="text-base font-black text-[#1e2924]">{{ role === 'teacher' && teacherViewMode === 'mine' ? 'No duties assigned to you yet' : 'No exams found' }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-500">{{ role === 'teacher' && teacherViewMode === 'mine' ? 'Your assigned hall guard duties will appear here once the admin publishes them.' : 'The published schedule does not contain exams for this view.' }}</p>
                </div>
            </section>

            <Transition v-else-if="canEdit" name="exam-step" mode="out-in">
                <section :key="activeStep" class="space-y-5">
                    <div v-if="canEdit && activeStep === 'Details'" class="surface-card overflow-hidden">
                        <div class="grid gap-px bg-stone-200/80 lg:grid-cols-[minmax(0,1fr)_22rem]">
                            <div class="space-y-5 bg-white p-5">
                                <div>
                                    <label class="section-title">Exam name</label>
                                    <input v-model="examName" class="field-control mt-1 w-full bg-white text-lg font-black text-[#1e2924]" placeholder="Exam schedule name" />
                                </div>
                                <div>
                                    <label class="section-title">Short note</label>
                                    <input v-model="examNote" class="field-control mt-1 w-full bg-white" placeholder="Optional note" />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="section-title">Start date</label>
                                        <input v-model="examStartDate" type="date" class="field-control mt-1 w-full bg-white" :max="examEndDate || undefined" />
                                    </div>
                                    <div>
                                        <label class="section-title">End date</label>
                                        <input v-model="examEndDate" type="date" class="field-control mt-1 w-full bg-white" :min="examStartDate || undefined" />
                                    </div>
                                </div>
                            </div>
                            <div class="bg-[#8BED9A]/12 p-5">
                                <div class="rounded-2xl border border-[#8BED9A]/60 bg-white p-5">
                                    <p class="text-sm font-black text-[#1e2924]">Schedule identity</p>
                                    <p class="mt-3 text-sm font-semibold text-slate-600">{{ examNote || 'No note added yet' }}</p>
                                    <div class="mt-5 rounded-xl bg-[#1e2924] p-4 text-white">
                                        <p class="text-xs font-black uppercase tracking-[0.16em] text-[#8BED9A]">Window</p>
                                        <p class="mt-1 text-lg font-black">{{ formatDate(examStartDate) }} - {{ formatDate(examEndDate) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="canEdit && activeStep === 'Setup'" class="surface-card overflow-hidden">
                        <div class="grid gap-px bg-stone-200/80 lg:grid-cols-3">
                            <div class="bg-white p-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <Users class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Classes</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ classMode === 'all' ? 'All selected' : `${selectedClasses.length} selected` }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-2 rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5">
                                    <button type="button" class="rounded-xl px-4 py-3 text-sm font-black transition" :class="classMode === 'all' ? 'bg-[#1e2924] text-white shadow-md shadow-[#1e2924]/15' : 'text-[#1e2924] hover:bg-white/80'" @click="setClassMode('all')">All classes</button>
                                    <button type="button" class="rounded-xl px-4 py-3 text-sm font-black transition" :class="classMode === 'specific' ? 'bg-[#1e2924] text-white shadow-md shadow-[#1e2924]/15' : 'text-[#1e2924] hover:bg-white/80'" @click="setClassMode('specific')">Specific classes</button>
                                </div>
                                <div v-if="classMode === 'specific'" class="mt-4">
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                        <input v-model="classSearch" type="text" class="field-control w-full pl-9" placeholder="Search classes" />
                                    </div>
                                    <div class="mt-3 max-h-44 space-y-2 overflow-y-auto pr-1">
                                        <button v-for="className in visibleClasses" :key="className" type="button" class="w-full rounded-xl border px-3 py-2 text-left text-sm font-bold transition" :class="selectedClasses.includes(className) ? 'border-[#09B884] bg-[#8BED9A]/18 text-[#1e2924]' : 'border-stone-200 bg-white text-slate-600 hover:bg-stone-50'" @click="toggleClass(className)">
                                            {{ className }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <DoorOpen class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Halls</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ halls.length }} available</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_3rem]">
                                    <input v-model="newHall.name" class="field-control" placeholder="Hall name" />
                                    <input v-model.number="newHall.capacity" type="number" min="1" class="field-control text-center" />
                                    <button type="button" class="flex min-h-11 items-center justify-center rounded-xl bg-[#1e2924] text-white transition hover:bg-[#1e2924]/90" @click="addHall">
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="mt-4 max-h-44 space-y-2 overflow-y-auto pr-1">
                                    <div v-for="hall in halls" :key="hall.id" class="flex items-center justify-between gap-3 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2">
                                        <div>
                                            <p class="text-sm font-black text-[#1e2924]">{{ hall.name }}</p>
                                            <p class="text-xs font-semibold text-slate-500">Capacity {{ hall.capacity }}</p>
                                        </div>
                                        <button type="button" class="rounded-lg p-2 text-red-600 transition hover:bg-red-50" @click="removeHall(hall.name)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                        <Clock3 class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Times</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ timeSlots.length }} exam windows</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_3rem]">
                                    <input v-model="newSlot.startLabel" type="time" class="field-control bg-white" />
                                    <input v-model="newSlot.endLabel" type="time" class="field-control bg-white" />
                                    <button type="button" class="flex min-h-11 items-center justify-center rounded-xl bg-[#1e2924] text-white transition hover:bg-[#1e2924]/90" @click="addSlot">
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="mt-4 max-h-44 space-y-2 overflow-y-auto pr-1">
                                    <div v-for="slot in timeSlots" :key="slot.key" class="flex items-center justify-between gap-3 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2">
                                        <p class="text-sm font-black text-[#1e2924]">{{ slotDisplayLabel(slot) }}</p>
                                        <button type="button" class="rounded-lg p-2 text-red-600 transition hover:bg-red-50" @click="removeSlot(slot.key)">
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'Schedule'" class="space-y-5">
                        <section v-if="canEdit && conflictGroups.length" class="surface-card border-red-200 bg-red-50 p-4">
                            <div class="flex items-start gap-3 text-red-800">
                                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <p class="text-sm font-black">{{ conflictGroups.length }} invigilator conflict{{ conflictGroups.length === 1 ? '' : 's' }}</p>
                                    <p class="mt-1 text-sm font-semibold">{{ conflictGroups[0].teacher }} is booked in {{ conflictGroups[0].halls.join(', ') }} on {{ formatDate(conflictGroups[0].examDate) }} at {{ conflictGroups[0].slotLabel }}.</p>
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-5" :class="canEdit ? 'xl:grid-cols-[minmax(0,1fr)_27rem]' : ''">
                            <section class="surface-card overflow-hidden">
                                <div class="flex flex-col gap-3 border-b border-stone-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Exam routine</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ canEdit ? 'Click any cell to assign or edit an exam.' : 'Active exam schedule' }}</p>
                                    </div>
                                    <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-black text-[#1e2924] transition hover:bg-stone-50" @click="printPage">
                                        <Printer class="h-4 w-4" />
                                        Print
                                    </button>
                                </div>

                                <div class="border-b border-stone-200 bg-stone-50 p-4">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <button v-for="date in examDateOptions" :key="date.value" type="button" class="shrink-0 rounded-xl border px-4 py-2 text-sm font-black transition" :class="currentExamDate === date.value ? 'border-[#1e2924] bg-[#1e2924] text-white shadow-md shadow-[#1e2924]/15' : 'border-stone-200 bg-white text-[#1e2924] hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/15'" @click="activeExamDate = date.value">
                                            {{ date.label }}
                                        </button>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <div class="min-w-[880px]">
                                        <div class="grid border-b border-stone-200 bg-stone-50" :style="gridStyle">
                                            <div class="px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-slate-500">Hall</div>
                                            <div v-for="slot in timeSlots" :key="slot.key" class="border-l border-stone-200 px-3 py-3 text-center text-xs font-black uppercase tracking-[0.14em] text-slate-500">{{ slotDisplayLabel(slot) }}</div>
                                        </div>

                                        <div v-for="hall in halls" :key="hall.id" class="grid border-b border-stone-200 last:border-b-0" :style="gridStyle">
                                            <div class="bg-white px-4 py-4">
                                                <p class="text-sm font-black text-[#1e2924]">{{ hall.name }}</p>
                                                <p class="text-xs font-semibold text-slate-500">Capacity {{ hall.capacity }}</p>
                                            </div>
                                            <div v-for="slot in timeSlots" :key="slot.key" class="border-l border-stone-200 bg-white p-2">
                                                <button v-if="shouldShowCell(hall.name, currentExamDate, slot.key)" type="button" class="min-h-28 w-full rounded-xl border p-3 text-left transition" :class="[cellClasses(hall.name, currentExamDate, slot.key), canEdit ? 'hover:-translate-y-0.5 hover:shadow-sm' : 'cursor-default']" @click="openEditor(hall.name, currentExamDate, slot.key)">
                                                    <template v-if="!cellAt(hall.name, currentExamDate, slot.key) || !cellHasActiveClass(cellAt(hall.name, currentExamDate, slot.key))">
                                                        <span class="flex h-full min-h-20 items-center justify-center gap-2 text-xs font-black">
                                                            <Plus v-if="canEdit" class="h-4 w-4" />
                                                            {{ canEdit ? 'Add exam' : 'No exam' }}
                                                        </span>
                                                    </template>
                                                    <template v-else>
                                                        <div class="flex items-start justify-between gap-2">
                                                            <p class="text-sm font-black">{{ cellTitle(cellAt(hall.name, currentExamDate, slot.key)) }}</p>
                                                            <Pencil v-if="canEdit" class="h-4 w-4 text-[#09B884]" />
                                                        </div>
                                                        <p class="mt-1 line-clamp-2 text-xs font-bold">{{ cellSubtitle(cellAt(hall.name, currentExamDate, slot.key)) }}</p>
                                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                                            <span v-for="guard in cellAt(hall.name, currentExamDate, slot.key).guards" :key="`${hall.name}-${currentExamDate}-${slot.key}-${guard}`" class="rounded-full bg-white/80 px-2 py-1 text-[11px] font-bold">{{ guard }}</span>
                                                        </div>
                                                    </template>
                                                </button>
                                                <div v-else class="min-h-28 rounded-xl border border-dashed border-stone-200 bg-stone-50/70"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section v-if="canEdit" class="surface-card overflow-hidden">
                                <div class="border-b border-stone-200 bg-white p-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-base font-black text-[#1e2924]">Remaining subjects</p>
                                            <p class="text-xs font-semibold text-slate-500">{{ totalRequiredExams - scheduledRequiredExams }} exams still need dates</p>
                                        </div>
                                        <CheckCircle2 v-if="!classesWithMissingSubjects.length" class="h-5 w-5 text-[#09B884]" />
                                        <AlertTriangle v-else class="h-5 w-5 text-amber-600" />
                                    </div>
                                </div>
                                <div class="max-h-[40rem] space-y-3 overflow-y-auto bg-stone-50 p-4">
                                    <div v-if="!classesWithMissingSubjects.length" class="rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 p-5 text-center">
                                        <p class="text-sm font-black text-[#1e2924]">Every selected class has all subjects scheduled.</p>
                                    </div>
                                    <div v-for="item in classesWithMissingSubjects" :key="`side-${item.className}`" class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm font-black text-[#1e2924]">{{ item.className }}</p>
                                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">{{ item.missing.length }} left</span>
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                            <span v-for="subject in item.missing" :key="`side-${item.className}-${subject}`" class="rounded-full border border-stone-200 bg-stone-50 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ subject }}</span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <section v-else-if="activeStep === 'Duties'" class="surface-card overflow-hidden">
                        <div class="border-b border-stone-200 bg-white p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#8BED9A]/18 text-[#09B884]">
                                    <ShieldCheck class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-base font-black text-[#1e2924]">Hall guard duty list</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ guardDuties.length }} teachers assigned</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-px bg-stone-200 md:grid-cols-2 xl:grid-cols-3">
                            <div v-for="duty in guardDuties" :key="duty.teacher" class="bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-[#1e2924]">{{ duty.teacher }}</p>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-black" :class="duty.hasConflict ? 'bg-red-50 text-red-700' : 'bg-[#8BED9A]/18 text-[#1e2924]'">{{ duty.load }} duties</span>
                                </div>
                                <div class="mt-3 space-y-1.5">
                                    <p v-for="cell in duty.duties" :key="`${duty.teacher}-${cell.hallName}-${cell.slotKey}`" class="rounded-lg bg-stone-50 px-3 py-2 text-xs font-semibold text-slate-600">
                                        {{ formatDate(cell.examDate) }} - {{ cell.hallName }} - {{ cell.slotLabel }} - {{ cellSubtitle(cell) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </section>
            </Transition>

            <section v-if="canEdit" class="surface-card p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" class="btn-secondary min-h-11" :disabled="activeStepIndex === 0" @click="goToStep(-1)">Back</button>
                    <div class="flex items-center justify-center gap-1.5">
                        <span v-for="step in steps" :key="`dot-${step}`" class="h-2.5 rounded-full transition-all duration-300" :class="steps.indexOf(step) <= activeStepIndex ? 'w-8 bg-[#09B884]' : 'w-2.5 bg-stone-300'"></span>
                    </div>
                    <button v-if="activeStepIndex < steps.length - 1" type="button" class="btn-primary min-h-11" @click="goToStep(1)">
                        Next
                        <ArrowRight class="h-4 w-4" />
                    </button>
                    <button v-else type="button" class="btn-primary min-h-11" :disabled="!examName.trim()" @click="saveSchedule">
                        <CheckCircle2 class="h-4 w-4" />
                        Save schedule
                    </button>
                </div>
            </section>

            <section class="exam-print-export">
                <header class="exam-print-header">
                    <h1>{{ examName }}</h1>
                    <p>{{ formatDate(examStartDate) }} to {{ formatDate(examEndDate) }}</p>
                </header>

                <section v-for="date in examDateOptions" :key="`print-exam-${date.value}`" class="exam-print-day">
                    <h2>{{ date.label }}</h2>
                    <table class="exam-print-table">
                        <thead>
                            <tr>
                                <th>Hall</th>
                                <th v-for="slot in timeSlots" :key="`print-exam-head-${date.value}-${slot.key}`">{{ slotDisplayLabel(slot) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="hall in halls" :key="`print-exam-row-${date.value}-${hall.id}`">
                                <td>
                                    <strong>{{ hall.name }}</strong><br />
                                    <small>Capacity {{ hall.capacity }}</small>
                                </td>
                                <td v-for="slot in timeSlots" :key="`print-exam-cell-${date.value}-${hall.id}-${slot.key}`">
                                    <template v-if="cellAt(hall.name, date.value, slot.key) && cellHasActiveClass(cellAt(hall.name, date.value, slot.key))">
                                        <div v-for="exam in examsForViewer(cellAt(hall.name, date.value, slot.key))" :key="`print-exam-group-${date.value}-${hall.id}-${slot.key}-${exam.classLabel}-${exam.subject}`" class="exam-print-group">
                                            <strong>{{ exam.classLabel }}</strong> - {{ exam.subject }}
                                        </div>
                                        <div class="exam-print-guards">
                                            <strong>Hall guards:</strong>
                                            {{ (cellAt(hall.name, date.value, slot.key).guards ?? []).join(', ') }}
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-[#1e2924]/35 p-4 backdrop-blur-sm" @click.self="closeEditor">
                <div class="surface-card w-full max-w-xl overflow-hidden shadow-2xl">
                    <div class="border-b border-stone-200 bg-white p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-base font-black text-[#1e2924]">Schedule exam slot</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ editing.hallName }} - {{ slotDisplayLabel(timeSlots.find((slot) => slot.key === editing.slotKey) ?? {}) }}</p>
                            </div>
                            <button type="button" class="rounded-lg p-2 text-slate-500 transition hover:bg-stone-100" @click="closeEditor">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="grid gap-4 sm:grid-cols-1">
                            <div>
                                <label class="section-title">Date</label>
                                <select v-model="editing.examDate" class="field-control mt-1 w-full bg-white">
                                    <option value="" disabled>Select date</option>
                                    <option v-for="date in examDateOptions" :key="date.value" :value="date.value">{{ date.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label class="section-title">Classes and subjects in this slot</label>
                                <button type="button" class="rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 py-1.5 text-xs font-black text-[#1e2924] transition hover:bg-[#8BED9A]/25" @click="addExamGroup">
                                    Add group
                                </button>
                            </div>
                            <div class="mt-2 space-y-2">
                                <div v-for="(exam, index) in editing.exams" :key="`exam-group-${index}`" class="grid gap-2 rounded-xl border border-stone-200 bg-stone-50 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_2.5rem]">
                                    <select v-model="exam.classLabel" class="field-control bg-white" @change="handleExamClassChange(exam)">
                                        <option value="" disabled>Select class/section</option>
                                        <option v-for="className in activeClasses" :key="className" :value="className">{{ className }}</option>
                                    </select>
                                    <select v-model="exam.subject" class="field-control bg-white">
                                        <option value="" disabled>Select subject</option>
                                        <option v-for="subject in subjectsForClass(exam.classLabel)" :key="subject" :value="subject">{{ subject }}</option>
                                    </select>
                                    <button type="button" class="flex min-h-11 items-center justify-center rounded-xl border border-red-200 bg-white text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="editing.exams.length <= 1" @click="removeExamGroup(index)">
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label class="section-title">Hall guards</label>
                                <span class="text-xs font-semibold text-slate-500">{{ editing.guards.length }} selected</span>
                            </div>
                            <div class="mt-2 grid max-h-64 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                                <button v-for="teacher in invigilatorOptions" :key="teacher" type="button" class="rounded-xl border px-3 py-2 text-left text-sm font-bold transition" :class="editing.guards.includes(teacher) ? 'border-[#09B884] bg-[#8BED9A]/18 text-[#1e2924]' : 'border-stone-200 bg-white text-slate-600 hover:bg-stone-50'" @click="toggleGuard(teacher)">
                                    {{ teacher }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-stone-200 bg-stone-50 p-4 sm:flex-row sm:justify-between">
                        <button type="button" class="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-black text-red-700 transition hover:bg-red-50" @click="clearCell">Clear slot</button>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-stone-100" @click="closeEditor">Cancel</button>
                            <button type="button" class="rounded-xl bg-[#1e2924] px-5 py-2 text-sm font-black text-white shadow-md shadow-[#1e2924]/15 transition hover:bg-[#1e2924]/90 disabled:cursor-not-allowed disabled:bg-slate-300" :disabled="!editing.examDate || !validExamGroups().length || !editing.guards.length" @click="saveEditor">Save slot</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.exam-print-export {
    display: none;
}

.exam-step-enter-active,
.exam-step-leave-active {
    transition: opacity 220ms ease, transform 220ms ease;
}

.exam-step-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.exam-step-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media print {
    :global(body *) {
        visibility: hidden !important;
    }

    :global(.exam-print-export),
    :global(.exam-print-export *) {
        visibility: visible !important;
    }

    .exam-print-export {
        display: block !important;
        position: absolute;
        inset: 0;
        width: 100%;
        background: #fff;
        color: #111827;
        padding: 18px;
        font-family: Arial, sans-serif;
    }

    .exam-print-header {
        border-bottom: 2px solid #111827;
        margin-bottom: 16px;
        padding-bottom: 10px;
    }

    .exam-print-header h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
    }

    .exam-print-header p {
        margin: 4px 0 0;
        color: #4b5563;
        font-size: 12px;
        font-weight: 600;
    }

    .exam-print-day {
        break-inside: avoid;
        margin-bottom: 18px;
    }

    .exam-print-day:not(:last-child) {
        break-after: page;
    }

    .exam-print-day h2 {
        margin: 0 0 8px;
        font-size: 15px;
        font-weight: 800;
    }

    .exam-print-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 10px;
    }

    .exam-print-table th,
    .exam-print-table td {
        border: 1px solid #cbd5e1;
        padding: 6px;
        vertical-align: top;
        word-break: break-word;
    }

    .exam-print-table th {
        background: #f1f5f9;
        font-weight: 800;
        text-align: left;
    }

    .exam-print-table small {
        color: #64748b;
        font-size: 8px;
    }

    .exam-print-group {
        margin-bottom: 4px;
    }

    .exam-print-guards {
        border-top: 1px solid #e2e8f0;
        margin-top: 6px;
        padding-top: 5px;
        color: #334155;
        font-size: 9px;
    }
}
</style>
