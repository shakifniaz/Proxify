<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    AlertTriangle,
    ArrowRightLeft,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Layers3,
    Plus,
    Printer,
    RefreshCw,
    Search,
    Send,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    UserX,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    activeRoutine: { type: Object, default: null },
    runs: { type: Array, default: () => [] },
    latestRun: { type: Object, default: null },
    defaultSubjectGroups: { type: Array, default: () => [] },
    leaveAbsences: { type: Array, default: () => [] },
});

const isSubmitting = ref(false);
const isSavingGroups = ref(false);
const groupsDirty = ref(false);
const subjectPicker = ref({ groupId: null, query: '', draft: [] });
const activeTab = ref('plan');
const activeProxyStep = ref('Plan');
const showProxyBuilder = ref(false);
const selectedRun = ref(props.latestRun);
const showApprovedLeaves = ref(false);
const query = ref('');
const manualAssignments = ref({});
const confirmDialog = ref({
    open: false,
    tone: 'danger',
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    onConfirm: null,
});
const whatsappDialog = ref({
    open: false,
    loading: false,
    sending: false,
    error: '',
    run: null,
    messages: [],
    copiedIndex: null,
});
const defaultRunTarget = nextRoutineTarget(props.activeRoutine?.days ?? []);
const selectedDay = ref(defaultRunTarget.day);
const runName = ref(`Substitution plan - ${selectedDay.value}`);
const runDate = ref(defaultRunTarget.date);
const classPeriodKeys = (props.activeRoutine?.periods ?? [])
    .filter((period) => (period.type ?? 'class') === 'class')
    .map((period) => period.key)
    .filter(Boolean);
const selectedTeacherIds = ref(new Set(props.leaveAbsences.map((absence) => String(absence.teacherId))));
const teacherPeriods = ref(
    Object.fromEntries(props.leaveAbsences.map((absence) => [
        String(absence.teacherId),
        (absence.periodKeys ?? []).length ? [...absence.periodKeys] : [...classPeriodKeys],
    ]))
);
const subjectGroups = ref(props.defaultSubjectGroups.map((group) => ({
    id: group.id,
    name: group.name,
    subjects: [...(group.subjects ?? [])],
})));

const tabs = [
    { key: 'plan', label: 'Substitution plan', icon: UserX },
    { key: 'groups', label: 'Subject groups', icon: SlidersHorizontal },
];

const proxySteps = [
    { name: 'Plan', hint: 'Day and date', icon: CalendarDays },
    { name: 'Teachers', hint: 'Who is away', icon: UserX },
    { name: 'Periods', hint: 'When they are away', icon: Clock3 },
    { name: 'Assign', hint: 'Manual or auto', icon: ShieldCheck },
    { name: 'Generate', hint: 'Create coverage', icon: RefreshCw },
];

const teachers = computed(() => props.activeRoutine?.teachers ?? []);
const periods = computed(() => props.activeRoutine?.periods ?? []);
const subjectOptions = computed(() => props.activeRoutine?.subjects ?? []);
const filteredPickerSubjects = computed(() => {
    const needle = subjectPicker.value.query.trim().toLowerCase();
    if (!needle) return subjectOptions.value;
    return subjectOptions.value.filter((subject) => subject.toLowerCase().includes(needle));
});

const filteredTeachers = computed(() => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) return teachers.value;

    return teachers.value.filter((teacher) =>
        `${teacher.name} ${teacher.subjectHint ?? ''}`.toLowerCase().includes(needle)
    );
});

const selectedTeachers = computed(() =>
    teachers.value.filter((teacher) => selectedTeacherIds.value.has(String(teacher.id)))
);
const approvedLeaveAbsences = computed(() => props.leaveAbsences.filter((absence) => absence.status === 'approved'));
const approvedLeaveTeacherIds = computed(() => new Set(approvedLeaveAbsences.value.map((absence) => String(absence.teacherId))));

const canGenerate = computed(() => Boolean(props.activeRoutine?.id) && selectedTeacherIds.value.size > 0 && !isSubmitting.value);
const selectedPeriodCount = computed(() =>
    selectedTeachers.value.reduce((sum, teacher) => sum + (teacherPeriods.value[String(teacher.id)]?.length ?? 0), 0)
);
const runDateDisplay = computed({
    get() {
        return formatDisplayDate(runDate.value);
    },
    set(value) {
        const parsed = parseDisplayDate(value);
        if (parsed) runDate.value = parsed;
    },
});
const selectedDayBadge = computed(() => `${selectedDay.value} - ${runDateDisplay.value}`);
const approvedLeavePreview = computed(() => approvedLeaveAbsences.value.slice(0, 2));
const activeProxyStepIndex = computed(() => proxySteps.findIndex((step) => step.name === activeProxyStep.value));
const proxyProgress = computed(() => Math.round(((activeProxyStepIndex.value + 1) / proxySteps.length) * 100));
const generatedGridSections = computed(() => Object.entries(props.activeRoutine?.generatedGrid ?? {}).map(([key, section]) => ({
    key: String(key),
    ...section,
})));
const proxyTargets = computed(() => {
    const targets = [];
    const selectedIds = selectedTeacherIds.value;

    generatedGridSections.value.forEach((section) => {
        const cells = section.days?.[selectedDay.value] ?? {};
        Object.entries(cells).forEach(([periodKey, cell]) => {
            const teacherId = String(cell?.teacherId ?? '');
            if (!teacherId || !selectedIds.has(teacherId)) return;
            if (!(teacherPeriods.value[teacherId] ?? []).includes(periodKey)) return;
            if ((cell?.type ?? 'class') !== 'class') return;

            targets.push({
                key: targetKey(section.key, periodKey, teacherId),
                sectionKey: section.key,
                periodKey,
                periodLabel: periodLabel(periodKey),
                classLabel: cell.classLabel ?? section.label ?? '',
                subject: cell.subject ?? '',
                absentTeacherId: teacherId,
                absentTeacherName: cell.teacherName ?? teacherName(teacherId),
            });
        });
    });

    return targets.sort((a, b) => periodIndex(a.periodKey) - periodIndex(b.periodKey) || a.classLabel.localeCompare(b.classLabel));
});
const manualAssignmentCount = computed(() => Object.keys(manualAssignments.value).filter((key) => manualAssignments.value[key]).length);
const validManualAssignments = computed(() => {
    const validTargetKeys = new Set(proxyTargets.value.map((target) => target.key));
    return Object.entries(manualAssignments.value)
        .filter(([targetKeyValue, teacherId]) => validTargetKeys.has(targetKeyValue) && teacherId)
        .map(([targetKeyValue, teacherId]) => ({ targetKey: targetKeyValue, teacherId }));
});
const teacherHistory = computed(() => {
    const history = {};

    teachers.value.forEach((teacher) => {
        history[String(teacher.id)] = {
            subjects: new Set(),
            ranks: new Set(),
            sections: new Set(),
            load: {},
        };
    });

    generatedGridSections.value.forEach((section) => {
        Object.entries(section.days ?? {}).forEach(([day, cells]) => {
            Object.entries(cells ?? {}).forEach(([periodKey, cell]) => {
                const teacherId = String(cell?.teacherId ?? '');
                if (!teacherId) return;
                history[teacherId] ??= { subjects: new Set(), ranks: new Set(), sections: new Set(), load: {} };
                if (cell.subject) history[teacherId].subjects.add(normalizeSubject(cell.subject));
                const rank = classRank(cell.classLabel ?? section.label ?? '');
                if (rank !== null) history[teacherId].ranks.add(rank);
                history[teacherId].sections.add(section.key);
                history[teacherId].load[day] = (history[teacherId].load[day] ?? 0) + 1;
            });
        });
    });

    return history;
});
const normalizedSubjectGroups = computed(() => subjectGroups.value.map((group) => ({
    ...group,
    subjects: (group.subjects ?? []).map(normalizeSubject).filter(Boolean),
})));
const latestResolvedRate = computed(() => {
    const affected = activeRun.value?.metrics?.affectedPeriods ?? 0;
    if (!affected) return 0;
    return Math.round(((activeRun.value?.metrics?.resolved ?? 0) / affected) * 100);
});
const activeRun = computed(() => selectedRun.value);
const activeConflictRun = computed(() => {
    if (!activeRun.value) return null;
    return props.runs.find((run) =>
        run.id !== activeRun.value.id
        && run.status === 'Approved'
        && String(run.routineId ?? '') === String(activeRun.value.routineId ?? '')
        && (
            String(run.day) === String(activeRun.value.day)
            || (run.date && activeRun.value.date && String(run.date) === String(activeRun.value.date))
        )
    ) ?? null;
});

function nextRoutineTarget(routineDays = []) {
    const normalizedDays = routineDays
        .map((day) => ({ label: day, index: dayIndex(day) }))
        .filter((day) => day.index !== null);
    const today = new Date();

    for (let offset = 1; offset <= 14; offset += 1) {
        const candidate = new Date(today);
        candidate.setDate(today.getDate() + offset);
        const match = normalizedDays.find((day) => day.index === candidate.getDay());

        if (match) {
            return {
                day: match.label,
                date: toLocalIsoDate(candidate),
            };
        }
    }

    const fallback = new Date(today);
    fallback.setDate(today.getDate() + 1);

    return {
        day: routineDays[0] ?? 'Sun',
        date: toLocalIsoDate(fallback),
    };
}

function dayIndex(label) {
    const key = String(label || '').trim().slice(0, 3).toLowerCase();
    return {
        sun: 0,
        mon: 1,
        tue: 2,
        wed: 3,
        thu: 4,
        fri: 5,
        sat: 6,
    }[key] ?? null;
}

function toLocalIsoDate(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function formatDisplayDate(value) {
    const [year, month, day] = String(value || '').split('-');
    if (!year || !month || !day) return '';
    return `${day}/${month}/${year.slice(-2)}`;
}

function parseDisplayDate(value) {
    const match = String(value || '').trim().match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2}|\d{4})$/);
    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3].length === 2 ? `20${match[3]}` : match[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function initials(name) {
    return String(name || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function targetKey(sectionKey, periodKey, teacherId) {
    return [sectionKey, periodKey, teacherId].map((part) => String(part ?? '')).join('|');
}

function teacherName(teacherId) {
    return teachers.value.find((teacher) => String(teacher.id) === String(teacherId))?.name ?? 'Teacher';
}

function periodLabel(periodKey) {
    return periods.value.find((period) => period.key === periodKey)?.label ?? periodKey;
}

function periodIndex(periodKey) {
    const index = periods.value.findIndex((period) => period.key === periodKey);
    return index === -1 ? 999 : index;
}

function normalizeSubject(subject) {
    return String(subject ?? '').trim().toLowerCase();
}

function classRank(label) {
    const normalized = String(label ?? '').toLowerCase();
    if (/\b(nursery|nur)\b/.test(normalized)) return 0;
    if (/\bkg\b|kindergarten/.test(normalized)) return 1;
    const numeric = normalized.match(/\b(\d{1,2})\b/);
    if (numeric) return Number(numeric[1]) + 1;
    const romanMap = { xii: 13, xi: 12, x: 11, ix: 10, viii: 9, vii: 8, vi: 7, v: 6, iv: 5, iii: 4, ii: 3, i: 2 };
    const roman = normalized.match(/\b(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)\b/);
    return roman ? romanMap[roman[1]] : null;
}

function teacherIsAbsent(teacherId, periodKey) {
    const id = String(teacherId);
    return selectedTeacherIds.value.has(id) && (teacherPeriods.value[id] ?? []).includes(periodKey);
}

function teacherIsBusy(teacherId, periodKey) {
    return generatedGridSections.value.some((section) => {
        const cell = section.days?.[selectedDay.value]?.[periodKey];
        return String(cell?.teacherId ?? '') === String(teacherId);
    });
}

function candidateTeachersFor(target) {
    const targetRank = classRank(target.classLabel);
    const targetSubject = normalizeSubject(target.subject);
    const matchingGroup = normalizedSubjectGroups.value.find((group) => group.subjects.includes(targetSubject));

    return teachers.value
        .filter((teacher) => {
            const teacherId = String(teacher.id);
            return teacherId !== target.absentTeacherId
                && !teacherIsAbsent(teacherId, target.periodKey)
                && !teacherIsBusy(teacherId, target.periodKey);
        })
        .map((teacher) => {
            const teacherId = String(teacher.id);
            const history = teacherHistory.value[teacherId] ?? { subjects: new Set(), ranks: new Set(), sections: new Set(), load: {} };
            const ranks = Array.from(history.ranks ?? []);
            const distance = targetRank === null || !ranks.length ? 99 : Math.min(...ranks.map((rank) => Math.abs(rank - targetRank)));
            const dailyLoad = history.load?.[selectedDay.value] ?? 0;
            const sameSection = history.sections?.has(target.sectionKey);
            const groupMatch = matchingGroup && Array.from(history.subjects ?? []).some((subject) => matchingGroup.subjects.includes(subject));
            const directSubjectMatch = Array.from(history.subjects ?? []).includes(targetSubject);

            let priority = 3;
            let reason = 'Nearby class';
            let score = 200 + (distance * 10) + (dailyLoad * 8);
            let subjectGroupName = groupMatch ? matchingGroup.name : null;

            if (directSubjectMatch) {
                priority = 1;
                reason = 'Same subject';
                score = -30 + (dailyLoad * 8);
            } else if (sameSection || distance === 0) {
                priority = 1;
                reason = sameSection ? 'Same section' : 'Same class';
                score = (sameSection ? -20 : 0) + (distance * 4) + (dailyLoad * 8) + (groupMatch ? -12 : 0);
            } else if (groupMatch) {
                priority = 2;
                reason = 'Subject group';
                score = 70 + (distance * 3) + (dailyLoad * 8);
            }

            return {
                id: teacherId,
                name: teacher.name,
                subjectHint: teacher.subjectHint,
                priority,
                reason,
                subjectGroupName,
                score,
                dailyLoad,
            };
        })
        .sort((a, b) => a.priority - b.priority || a.score - b.score || a.dailyLoad - b.dailyLoad || a.name.localeCompare(b.name));
}

function selectManualProxy(targetKeyValue, teacherId) {
    manualAssignments.value = {
        ...manualAssignments.value,
        [targetKeyValue]: String(teacherId),
    };
}

function clearManualProxy(targetKeyValue) {
    const next = { ...manualAssignments.value };
    delete next[targetKeyValue];
    manualAssignments.value = next;
}

function useAutoGenerationOnly() {
    manualAssignments.value = {};
    activeProxyStep.value = 'Generate';
}

function startNewProxy() {
    selectedRun.value = null;
    showProxyBuilder.value = true;
    activeProxyStep.value = 'Plan';
}

function openProxyRun(run) {
    selectedRun.value = run;
    showProxyBuilder.value = true;
    activeProxyStep.value = 'Generate';
}

function backToProxyLibrary() {
    showProxyBuilder.value = false;
    activeProxyStep.value = 'Plan';
}

function clearAllManualPicks() {
    manualAssignments.value = {};
}

function toggleTeacher(teacherId) {
    const id = String(teacherId);
    if (selectedTeacherIds.value.has(id)) {
        selectedTeacherIds.value.delete(id);
        delete teacherPeriods.value[id];
    } else {
        selectedTeacherIds.value.add(id);
        teacherPeriods.value[id] = [...classPeriodKeys];
    }
    selectedTeacherIds.value = new Set(selectedTeacherIds.value);
}

function proxyStepReady(stepName) {
    if (stepName === 'Plan') return Boolean(runName.value && runDate.value && selectedDay.value);
    if (stepName === 'Teachers') return selectedTeacherIds.value.size > 0;
    if (stepName === 'Periods') return selectedPeriodCount.value > 0;
    if (stepName === 'Assign') return proxyTargets.value.length > 0;
    return canGenerate.value;
}

function goToProxyStep(offset) {
    const nextIndex = Math.min(proxySteps.length - 1, Math.max(0, activeProxyStepIndex.value + offset));
    activeProxyStep.value = proxySteps[nextIndex].name;
}

function togglePeriod(teacherId, periodKey) {
    const id = String(teacherId);
    const current = teacherPeriods.value[id] ?? [];
    if (current.includes(periodKey) && current.length === 1) return;

    teacherPeriods.value[id] = current.includes(periodKey)
        ? current.filter((key) => key !== periodKey)
        : [...current, periodKey];
}

function markFullDay(teacherId) {
    teacherPeriods.value[String(teacherId)] = [...classPeriodKeys];
}

function periodSummary(periodKeys = []) {
    if (periodKeys.length === periods.value.length && periods.value.length > 0) {
        return `All ${periodKeys.length} periods`;
    }

    const labels = periods.value
        .filter((period) => periodKeys.includes(period.key))
        .map((period) => period.label);
    return labels.length ? labels.join(', ') : `${periodKeys.length} periods`;
}

function addSubjectGroup() {
    activeTab.value = 'groups';
    subjectGroups.value.push({
        id: `custom-${Date.now()}`,
        name: `New group ${subjectGroups.value.length + 1}`,
        subjects: [],
    });
    groupsDirty.value = true;
}

function removeSubjectGroup(index) {
    subjectGroups.value.splice(index, 1);
    groupsDirty.value = true;
}

function openSubjectPicker(group) {
    subjectPicker.value = {
        groupId: group.id,
        query: '',
        draft: [...(group.subjects ?? [])],
    };
}

function closeSubjectPicker() {
    subjectPicker.value = { groupId: null, query: '', draft: [] };
}

function draftHasSubject(subject) {
    return subjectPicker.value.draft.includes(subject);
}

function toggleDraftSubject(subject) {
    const draft = subjectPicker.value.draft;
    subjectPicker.value.draft = draft.includes(subject)
        ? draft.filter((item) => item !== subject)
        : [...draft, subject];
}

function confirmSubjectPicker(group) {
    group.subjects = [...subjectPicker.value.draft];
    groupsDirty.value = true;
    closeSubjectPicker();
}

function removeGroupSubject(group, subject) {
    group.subjects = (group.subjects ?? []).filter((item) => item !== subject);
    groupsDirty.value = true;
}

function markGroupsDirty() {
    groupsDirty.value = true;
}

function saveSubjectGroups() {
    if (!props.activeRoutine?.id || isSavingGroups.value) return;
    isSavingGroups.value = true;

    router.put('/proxy-manager/subject-groups', {
        routineId: props.activeRoutine.id,
        subjectGroups: subjectGroups.value.map((group) => ({
            name: group.name,
            subjects: group.subjects ?? [],
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            groupsDirty.value = false;
        },
        onFinish: () => {
            isSavingGroups.value = false;
        },
    });
}

function generateProxyRun() {
    if (!canGenerate.value) return;
    isSubmitting.value = true;

    router.post('/proxy-manager', {
        routineId: props.activeRoutine.id,
        name: runName.value || `Substitution plan - ${selectedDay.value}`,
        date: runDate.value,
        day: selectedDay.value,
        absentTeachers: Array.from(selectedTeacherIds.value).map((teacherId) => ({
            teacherId,
            periodKeys: teacherPeriods.value[teacherId] ?? [],
        })),
        subjectGroups: subjectGroups.value.map((group) => ({
            id: group.id,
            name: group.name,
            subjects: group.subjects ?? [],
        })),
        manualAssignments: validManualAssignments.value,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            selectedRun.value = page.props.latestRun ?? null;
            showProxyBuilder.value = true;
            activeProxyStep.value = 'Generate';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

function syncSelectedRun(page, runId) {
    const updated = page.props.runs?.find((run) => run.id === runId);
    selectedRun.value = updated ?? null;
    if (!updated) {
        showProxyBuilder.value = false;
        activeProxyStep.value = 'Plan';
    }
}

function openConfirmDialog({ tone = 'danger', title, message, confirmLabel = 'Confirm', onConfirm }) {
    confirmDialog.value = {
        open: true,
        tone,
        title,
        message,
        confirmLabel,
        onConfirm,
    };
}

function closeConfirmDialog() {
    confirmDialog.value = {
        open: false,
        tone: 'danger',
        title: '',
        message: '',
        confirmLabel: 'Confirm',
        onConfirm: null,
    };
}

function confirmDialogAction() {
    const action = confirmDialog.value.onConfirm;
    closeConfirmDialog();
    if (typeof action === 'function') {
        action();
    }
}

function enableProxyRun(run) {
    const conflict = props.runs.find((item) =>
        item.id !== run.id
        && item.status === 'Approved'
        && String(item.routineId ?? '') === String(run.routineId ?? '')
        && (
            String(item.day) === String(run.day)
            || (item.date && run.date && String(item.date) === String(run.date))
        )
    );

    const submit = () => router.post(`/proxy-manager/${run.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: (page) => syncSelectedRun(page, run.id),
    });

    if (conflict) {
        openConfirmDialog({
            tone: 'warning',
            title: 'Enable this substitution plan?',
            message: `"${conflict.name}" is already enabled for ${run.day}. Enabling "${run.name}" will disable the other substitution plan for that day.`,
            confirmLabel: 'Enable and disable other',
            onConfirm: submit,
        });
        return;
    }

    submit();
}

function disableProxyRun(run) {
    router.post(`/proxy-manager/${run.id}/disable`, {}, {
        preserveScroll: true,
        onSuccess: (page) => syncSelectedRun(page, run.id),
    });
}

function deleteProxyRun(run) {
    openConfirmDialog({
        tone: 'danger',
        title: 'Delete substitution plan?',
        message: `"${run.name}" will be permanently removed. If it is currently enabled, it will also stop affecting the active routine.`,
        confirmLabel: 'Delete proxy',
        onConfirm: () => router.delete(`/proxy-manager/${run.id}`, {
            preserveScroll: true,
            onSuccess: () => backToProxyLibrary(),
        }),
    });
}

async function sendWhatsAppUpdates(run) {
    whatsappDialog.value = {
        open: true,
        loading: true,
        sending: false,
        error: '',
        run,
        messages: [],
        copiedIndex: null,
    };

    try {
        const response = await fetch(`/proxy-manager/${run.id}/whatsapp-preview`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.message || 'Could not load WhatsApp messages.');
        }

        whatsappDialog.value.messages = payload.messages ?? [];
    } catch (error) {
        whatsappDialog.value.error = error.message || 'Could not load WhatsApp messages.';
    } finally {
        whatsappDialog.value.loading = false;
    }
}

function closeWhatsAppDialog() {
    whatsappDialog.value.open = false;
}

async function copyWhatsAppMessage(index) {
    const message = whatsappDialog.value.messages[index]?.message ?? '';
    if (!message) return;

    await navigator.clipboard?.writeText(message);
    whatsappDialog.value.copiedIndex = index;
    window.setTimeout(() => {
        if (whatsappDialog.value.copiedIndex === index) {
            whatsappDialog.value.copiedIndex = null;
        }
    }, 1400);
}

function submitWhatsAppMessages() {
    const run = whatsappDialog.value.run;
    if (!run || whatsappDialog.value.sending) return;

    whatsappDialog.value.sending = true;
    router.post(`/proxy-manager/${run.id}/whatsapp`, {
        messages: whatsappDialog.value.messages,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            syncSelectedRun(page, run.id);
            closeWhatsAppDialog();
        },
        onError: () => {
            whatsappDialog.value.error = 'Could not send WhatsApp messages. Check the message content and WhatsApp settings.';
        },
        onFinish: () => {
            whatsappDialog.value.sending = false;
        },
    });
}

function statusClass(status) {
    if (status === 'Needs Review') return 'border-amber-200 bg-amber-50 text-amber-800';
    if (status === 'Finalized' || status === 'Approved') return 'border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]';
    return 'border-[#09B884]/30 bg-[#8BED9A]/15 text-[#1e2924]';
}

function assignmentClass(item) {
    if (item.status === 'unresolved') return 'border-red-200 bg-red-50';
    if (item.status === 'review') return 'border-amber-200 bg-amber-50';
    if (item.strategy === 'period_swap') return 'border-[#09B884]/30 bg-[#8BED9A]/15';
    return 'border-stone-200 bg-white';
}

function strategyTone(item) {
    if (item.status === 'unresolved') return 'text-red-700';
    if (item.status === 'review') return 'text-amber-800';
    if (item.strategy === 'period_swap') return 'text-[#1e2924]';
    return 'text-[#09B884]';
}

function printPage() {
    window.print();
}
</script>

<template>
    <AppLayout title="Class Coverage">
        <div class="proxy-manager-shell space-y-5">
            <div v-if="!activeRoutine" class="surface-card p-8 text-center">
                <AlertTriangle class="mx-auto h-8 w-8 text-amber-600" />
                <p class="mt-3 text-base font-semibold text-slate-950">No active routine found</p>
            </div>

            <template v-else>
                <div class="surface-card p-2">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="relative grid w-full overflow-hidden rounded-2xl border border-[#8BED9A]/50 bg-[#8BED9A]/10 p-1.5 shadow-sm shadow-[#8BED9A]/20 sm:w-[31rem] sm:grid-cols-2">
                            <div
                                class="absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-[#1e2924] shadow-lg shadow-[#1e2924]/20 transition-transform duration-300 ease-out"
                                :class="activeTab === 'groups' ? 'translate-x-full' : 'translate-x-0'"
                            ></div>
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="relative z-10 flex min-h-14 items-center justify-between gap-3 rounded-xl px-3 text-left transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-[#09B884]/35"
                                :class="activeTab === tab.key ? 'text-white' : 'text-[#1e2924] hover:text-[#09B884]'"
                                @click="activeTab = tab.key"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors duration-300"
                                        :class="activeTab === tab.key ? 'border-white/15 bg-white/14 text-[#BDF8C8]' : 'border-[#8BED9A]/60 bg-white/80 text-[#09B884]'"
                                    >
                                        <component :is="tab.icon" class="h-4 w-4" />
                                    </span>
                                    <span class="truncate text-sm font-black">{{ tab.label }}</span>
                                </span>
                                <span
                                    class="min-w-8 shrink-0 rounded-full border px-2 py-1 text-center text-xs font-black transition-colors duration-300"
                                    :class="activeTab === tab.key ? 'border-white/20 bg-white/16 text-white' : 'border-[#8BED9A]/70 bg-white/80 text-[#1e2924]'"
                                >
                                    {{ tab.key === 'plan' ? runs.length : subjectGroups.length }}
                                </span>
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex max-w-md items-center gap-2 rounded-xl bg-[#8BED9A]/16 px-3 py-2 text-xs font-black text-[#1e2924]">
                                <CheckCircle2 class="h-4 w-4 shrink-0 text-[#09B884]" />
                                <span class="truncate" :title="activeRoutine.name">{{ activeRoutine.name }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <Transition name="proxy-tab" mode="out-in">
                <div v-if="activeTab === 'plan'" key="plan" class="space-y-5">
                    <section v-if="!showProxyBuilder" class="surface-card overflow-hidden">
                        <div class="flex flex-col gap-4 border-b border-stone-200 bg-white p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-lg font-black text-[#1e2924]">Substitution plan library</p>
                                <p class="mt-1 text-sm font-semibold text-slate-500">Review previous substitution plans, enable one for its day, or create a new plan.</p>
                            </div>
                            <button type="button" class="btn-primary min-h-11" @click="startNewProxy">
                                <Plus class="h-4 w-4" />
                                Create new proxy
                            </button>
                        </div>

                        <div v-if="!runs.length" class="p-8 text-center">
                            <Layers3 class="mx-auto h-8 w-8 text-slate-300" />
                            <p class="mt-3 text-sm font-black text-[#1e2924]">No substitution plans yet</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Create a substitution plan when a teacher is unavailable.</p>
                        </div>

                        <div v-else class="grid gap-4 p-5 lg:grid-cols-2 2xl:grid-cols-3">
                            <article
                                v-for="run in runs"
                                :key="run.id"
                                class="group rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                :class="run.status === 'Approved' ? 'border-[#8BED9A]/80 bg-[#8BED9A]/10' : 'border-stone-200'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <button type="button" class="block max-w-full truncate text-left text-base font-black text-[#1e2924] group-hover:text-[#09B884]" @click="openProxyRun(run)">
                                            {{ run.name }}
                                        </button>
                                        <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ run.routineName }} · {{ run.day }}<span v-if="run.date"> · {{ run.date }}</span></p>
                                    </div>
                                    <span class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-black" :class="statusClass(run.status)">
                                        {{ run.status === 'Approved' ? 'Enabled' : run.status }}
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl bg-stone-50 px-2 py-3">
                                        <p class="text-lg font-black text-[#1e2924]">{{ run.affected }}</p>
                                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Affected</p>
                                    </div>
                                    <div class="rounded-xl bg-[#8BED9A]/18 px-2 py-3">
                                        <p class="text-lg font-black text-[#1e2924]">{{ run.resolved }}</p>
                                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Resolved</p>
                                    </div>
                                    <div class="rounded-xl bg-red-50 px-2 py-3">
                                        <p class="text-lg font-black text-red-700">{{ run.unresolved }}</p>
                                        <p class="text-[10px] font-black uppercase tracking-wide text-red-700">Open</p>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary min-h-10 flex-1" @click="openProxyRun(run)">View</button>
                                    <button
                                        v-if="run.status === 'Approved'"
                                        type="button"
                                        class="min-h-10 flex-1 rounded-xl border border-amber-200 bg-amber-50 px-3 text-sm font-black text-amber-800 transition hover:bg-amber-100"
                                        @click="disableProxyRun(run)"
                                    >
                                        Disable
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="min-h-10 flex-1 rounded-xl bg-[#1e2924] px-3 text-sm font-black text-white transition hover:bg-[#1e2924]/90"
                                        @click="enableProxyRun(run)"
                                    >
                                        Enable
                                    </button>
                                    <button
                                        v-if="run.status === 'Approved'"
                                        type="button"
                                        class="min-h-10 rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 text-sm font-black text-[#1e2924] transition hover:bg-[#8BED9A]/25"
                                        @click="sendWhatsAppUpdates(run)"
                                    >
                                        <Send class="inline h-4 w-4" />
                                        WhatsApp
                                    </button>
                                    <button type="button" class="min-h-10 rounded-xl border border-red-200 bg-white px-3 text-sm font-black text-red-700 transition hover:bg-red-50" @click="deleteProxyRun(run)">
                                        Delete
                                    </button>
                                </div>
                                <p v-if="run.messageSummary?.total" class="mt-3 rounded-xl bg-white/80 px-3 py-2 text-xs font-bold text-slate-600">
                                    WhatsApp: {{ run.messageSummary.sent }} sent, {{ run.messageSummary.skipped }} skipped, {{ run.messageSummary.failed }} failed
                                </p>
                            </article>
                        </div>
                    </section>

                    <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
                        <button type="button" class="btn-secondary min-h-10" @click="backToProxyLibrary">All substitution plans</button>
                        <p class="text-sm font-black text-[#1e2924]">
                            {{ activeRun ? activeRun.name : 'Create new substitution plan' }}
                        </p>
                        <button v-if="activeRun" type="button" class="btn-secondary min-h-10" @click="printPage">
                            <Printer class="h-4 w-4" />
                            Export
                        </button>
                    </div>

                    <section class="surface-card overflow-hidden p-2">
                        <div class="grid grid-cols-5 gap-2">
                            <button
                                v-for="(step, index) in proxySteps"
                                :key="step.name"
                                type="button"
                                class="group rounded-xl border p-2 text-left transition-all duration-300 hover:-translate-y-0.5 sm:p-3"
                                :class="activeProxyStep === step.name ? 'border-[#1e2924] bg-[#1e2924] text-white shadow-lg shadow-[#1e2924]/15' : 'border-transparent bg-stone-50 text-[#1e2924] hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/15'"
                                @click="activeProxyStep = step.name"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="activeProxyStep === step.name ? 'bg-[#8BED9A]/20 text-[#8BED9A]' : 'bg-white text-[#09B884]'">
                                        <component :is="step.icon" class="h-4 w-4" />
                                    </span>
                                    <span class="text-xs font-black opacity-60">0{{ index + 1 }}</span>
                                </div>
                                <p class="mt-3 text-xs font-black sm:text-sm">{{ step.name }}</p>
                                <p class="mt-1 hidden truncate text-xs font-semibold sm:block" :class="activeProxyStep === step.name ? 'text-white/60' : 'text-slate-500'">{{ step.hint }}</p>
                            </button>
                        </div>

                        <div class="mt-3 overflow-hidden rounded-full bg-stone-100">
                            <div class="h-2 rounded-full bg-gradient-to-r from-[#09B884] to-[#8BED9A] transition-all duration-500" :style="{ width: `${proxyProgress}%` }"></div>
                        </div>
                    </section>

                    <Transition name="proxy-tab" mode="out-in">
                    <section :key="activeProxyStep" class="space-y-5">
                    <div v-if="activeProxyStep === 'Plan'" class="surface-card overflow-hidden bg-white shadow-sm">
                        <div class="border-b border-stone-200 bg-[#8BED9A]/10 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-white text-[#09B884] shadow-sm">
                                        <CalendarDays class="h-4 w-4" />
                                    </div>
                                    <p class="text-base font-black text-slate-950">Create a substitution plan</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-xs font-black text-[#1e2924] shadow-sm">
                                        {{ selectedTeacherIds.size }} away
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-xs font-black text-[#1e2924] shadow-sm">
                                        {{ selectedPeriodCount }} periods
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-xs font-black text-[#1e2924] shadow-sm">
                                        {{ subjectGroups.length }} groups
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 p-4 xl:grid-cols-[minmax(13rem,1fr)_10rem_10rem] xl:items-end">
                                        <div>
                                            <label class="section-title">Substitution plan name</label>
                                            <input v-model="runName" type="text" class="field-control mt-1 w-full bg-white" />
                                        </div>
                                            <div>
                                                <label class="section-title">Date</label>
                                                <input
                                                    v-model="runDateDisplay"
                                                    type="text"
                                                    inputmode="numeric"
                                                    placeholder="dd/mm/yy"
                                                    class="field-control mt-1 w-full bg-white"
                                                />
                                            </div>
                                            <div>
                                                <label class="section-title">Routine day</label>
                                                <select v-model="selectedDay" class="field-control mt-1 w-full bg-white">
                                                    <option v-for="day in activeRoutine.days" :key="day" :value="day">{{ day }}</option>
                                                </select>
                                            </div>
                        </div>
                    </div>

                        <div v-if="activeProxyStep === 'Plan' && approvedLeaveAbsences.length" class="surface-card overflow-hidden bg-[#8BED9A]/8">
                            <div class="flex flex-col gap-3 px-4 py-3 xl:flex-row xl:items-center xl:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#8BED9A]/70 bg-white text-[#09B884]">
                                        <ShieldCheck class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-black text-slate-950">Approved leaves imported</p>
                                            <span class="rounded-full bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-bold text-[#1e2924]">
                                                {{ approvedLeaveAbsences.length }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                                    <span
                                        v-for="absence in approvedLeavePreview"
                                        :key="`preview-${absence.id}`"
                                        class="inline-flex max-w-[15rem] items-center gap-2 rounded-lg border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 py-2 text-xs font-semibold text-[#1e2924]"
                                    >
                                        <span class="truncate">{{ absence.teacherName }}</span>
                                        <span class="shrink-0 text-[#09B884]">{{ periodSummary(absence.periodKeys) }}</span>
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-9 items-center justify-center rounded-lg border border-stone-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-sm transition hover:border-[#09B884]/40 hover:bg-[#8BED9A]/10 hover:text-[#1e2924]"
                                        @click="showApprovedLeaves = !showApprovedLeaves"
                                    >
                                        {{ showApprovedLeaves ? 'Hide' : approvedLeaveAbsences.length > 2 ? `View ${approvedLeaveAbsences.length}` : 'View' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="showApprovedLeaves" class="border-t border-stone-200 bg-stone-50/70 p-3">
                                <div class="grid max-h-52 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                                    <div
                                        v-for="absence in approvedLeaveAbsences"
                                        :key="absence.id"
                                        class="rounded-lg border border-stone-200 bg-white px-3 py-2 shadow-sm"
                                    >
                                        <p class="truncate text-sm font-semibold text-slate-950">{{ absence.teacherName }}</p>
                                        <p class="mt-1 text-xs font-semibold text-[#09B884]">{{ periodSummary(absence.periodKeys) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="activeProxyStep === 'Teachers' || activeProxyStep === 'Periods'"
                            class="surface-card grid gap-px overflow-hidden bg-stone-200"
                            :class="activeProxyStep === 'Teachers' ? 'xl:grid-cols-1' : 'xl:grid-cols-1'"
                        >
                            <div v-if="activeProxyStep === 'Teachers'" class="bg-white p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-950">Who is unavailable?</p>
                                    </div>
                                    <span class="rounded-full border border-[#09B884]/30 bg-[#8BED9A]/15 px-2.5 py-1 text-xs font-semibold text-[#1e2924]">
                                        {{ selectedTeacherIds.size }} selected
                                    </span>
                                </div>

                                <div class="relative mt-4">
                                    <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                    <input v-model="query" type="text" placeholder="Search teacher or subject" class="field-control w-full pl-9" />
                                </div>

                                <div class="mt-3 max-h-[34rem] space-y-2 overflow-y-auto pr-1">
                                    <button
                                        v-for="teacher in filteredTeachers"
                                        :key="teacher.id"
                                        type="button"
                                        class="group w-full rounded-lg border px-3 py-3 text-left transition"
                                        :class="selectedTeacherIds.has(String(teacher.id)) ? 'border-[#09B884]/50 bg-[#8BED9A]/15 shadow-sm' : 'border-stone-200 bg-white hover:border-[#8BED9A] hover:bg-[#8BED9A]/10'"
                                        @click="toggleTeacher(teacher.id)"
                                    >
                                        <span class="flex items-center gap-3">
                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border text-xs font-bold"
                                                :class="selectedTeacherIds.has(String(teacher.id)) ? 'border-[#8BED9A]/70 bg-white text-[#1e2924]' : 'border-stone-200 bg-stone-50 text-slate-500 group-hover:text-[#1e2924]'"
                                            >
                                                {{ initials(teacher.name) }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-2">
                                                    <span class="block truncate text-sm font-semibold text-slate-950">{{ teacher.name }}</span>
                                                    <span v-if="approvedLeaveTeacherIds.has(String(teacher.id))" class="shrink-0 rounded-full bg-[#8BED9A]/25 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#1e2924]">
                                                        Leave
                                                    </span>
                                                </span>
                                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ teacher.subjectHint || 'No subject history yet' }}</span>
                                            </span>
                                            <UserX
                                                class="h-4 w-4 shrink-0"
                                                :class="selectedTeacherIds.has(String(teacher.id)) ? 'text-[#09B884]' : 'text-slate-300'"
                                            />
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="activeProxyStep === 'Periods'" class="bg-white p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-950">Which periods?</p>
                                    </div>
                                    <span class="rounded-full border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-2.5 py-1 text-xs font-semibold text-[#1e2924]">
                                        {{ selectedPeriodCount }} period marks
                                    </span>
                                </div>

                                <div v-if="!selectedTeachers.length" class="mt-4 rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                                    <CalendarDays class="mx-auto h-7 w-7 text-slate-300" />
                                    <p class="mt-2 text-sm font-medium text-slate-600">No teacher selected</p>
                                </div>

                                <div v-else class="mt-4 max-h-[34rem] space-y-3 overflow-y-auto pr-1">
                                    <div v-for="teacher in selectedTeachers" :key="teacher.id" class="rounded-lg border border-stone-200 bg-stone-50/70 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-sm font-semibold text-slate-950">{{ teacher.name }}</p>
                                                    <span v-if="approvedLeaveTeacherIds.has(String(teacher.id))" class="rounded-full bg-[#8BED9A]/25 px-2 py-0.5 text-[11px] font-bold text-[#1e2924]">
                                                        Approved leave
                                                    </span>
                                                </div>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    {{ periodSummary(teacherPeriods[String(teacher.id)] ?? []) }}
                                                </p>
                                            </div>
                                            <button type="button" class="rounded-md border border-[#8BED9A]/70 bg-white px-2.5 py-1.5 text-xs font-semibold text-[#1e2924] hover:bg-[#8BED9A]/15" @click="markFullDay(teacher.id)">
                                                Mark full day
                                            </button>
                                        </div>
                                        <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-7">
                                            <button
                                                v-for="period in periods"
                                                :key="period.key"
                                                type="button"
                                                class="rounded-md border px-2 py-2 text-xs font-semibold transition"
                                                :class="(teacherPeriods[String(teacher.id)] ?? []).includes(period.key) ? 'border-[#09B884] bg-[#8BED9A]/15 text-[#1e2924] shadow-sm' : 'border-stone-200 bg-white text-slate-600 hover:bg-[#8BED9A]/10'"
                                                @click="togglePeriod(teacher.id, period.key)"
                                            >
                                                {{ period.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <div v-if="activeProxyStep === 'Assign'" class="surface-card overflow-hidden bg-white">
                        <div class="border-b border-stone-200 bg-[#8BED9A]/10 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-white text-[#09B884] shadow-sm">
                                        <ShieldCheck class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Select proxy teachers</p>
                                        <p class="mt-0.5 text-sm font-semibold text-slate-500">Choose manually where needed. Anything left blank will be handled automatically.</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-300 bg-white px-4 text-sm font-black text-[#1e2924] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-45"
                                        :disabled="manualAssignmentCount === 0"
                                        @click="clearAllManualPicks"
                                    >
                                        Clear all manual picks
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#1e2924] px-5 text-sm font-black text-white shadow-md shadow-[#1e2924]/15 transition hover:-translate-y-0.5 hover:bg-[#1e2924]/90"
                                        @click="useAutoGenerationOnly"
                                    >
                                        <RefreshCw class="h-4 w-4" />
                                        Auto generate instead
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 p-5">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-3">
                                    <p class="text-xl font-black text-[#1e2924]">{{ proxyTargets.length }}</p>
                                    <p class="text-xs font-black uppercase text-[#1e2924]/55">Affected periods</p>
                                </div>
                                <div class="rounded-xl bg-[#8BED9A]/16 px-4 py-3">
                                    <p class="text-xl font-black text-[#1e2924]">{{ manualAssignmentCount }}</p>
                                    <p class="text-xs font-black uppercase text-[#1e2924]/55">Manual picks</p>
                                </div>
                                <div class="rounded-xl bg-stone-50 px-4 py-3">
                                    <p class="text-xl font-black text-[#1e2924]">{{ Math.max(proxyTargets.length - manualAssignmentCount, 0) }}</p>
                                    <p class="text-xs font-black uppercase text-[#1e2924]/55">Auto remaining</p>
                                </div>
                            </div>

                            <div v-if="!proxyTargets.length" class="rounded-xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                                <ShieldCheck class="mx-auto h-8 w-8 text-slate-300" />
                                <p class="mt-2 text-sm font-bold text-slate-600">No affected periods found</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="target in proxyTargets"
                                    :key="target.key"
                                    class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm transition hover:border-[#8BED9A]/70"
                                >
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-[#1e2924] px-2.5 py-1 text-xs font-black text-white">{{ target.periodLabel }}</span>
                                                <span class="rounded-full bg-[#8BED9A]/18 px-2.5 py-1 text-xs font-black text-[#1e2924]">{{ target.classLabel }}</span>
                                            </div>
                                            <p class="mt-2 text-sm font-black text-slate-950">{{ target.subject || 'Class period' }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">Unavailable: {{ target.absentTeacherName }}</p>
                                        </div>

                                        <button
                                            v-if="manualAssignments[target.key]"
                                            type="button"
                                            class="inline-flex min-h-9 items-center justify-center rounded-lg border border-stone-300 bg-white px-3 text-xs font-black text-slate-700 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                                            @click="clearManualProxy(target.key)"
                                        >
                                            Clear manual pick
                                        </button>
                                    </div>

                                    <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                        <button
                                            v-for="candidate in candidateTeachersFor(target).slice(0, 6)"
                                            :key="`${target.key}-${candidate.id}`"
                                            type="button"
                                            class="group rounded-xl border p-3 text-left transition hover:-translate-y-0.5"
                                            :class="manualAssignments[target.key] === candidate.id ? 'border-[#09B884] bg-[#8BED9A]/18 shadow-md shadow-[#8BED9A]/20' : 'border-stone-200 bg-stone-50 hover:border-[#8BED9A]/70 hover:bg-white'"
                                            @click="selectManualProxy(target.key, candidate.id)"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-black text-[#1e2924]">{{ candidate.name }}</p>
                                                    <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ candidate.subjectHint || 'Available teacher' }}</p>
                                                </div>
                                                <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-black uppercase" :class="candidate.priority === 1 ? 'bg-[#8BED9A]/35 text-[#1e2924]' : candidate.priority === 2 ? 'bg-amber-100 text-amber-800' : 'bg-stone-200 text-slate-600'">
                                                    {{ candidate.reason }}
                                                </span>
                                            </div>
                                            <div class="mt-2 min-h-7">
                                                <p
                                                    v-if="candidate.subjectGroupName"
                                                    class="inline-flex rounded-full bg-[#09B884]/10 px-2 py-1 text-[11px] font-black text-[#08775a]"
                                                >
                                                    {{ candidate.subjectGroupName }} group
                                                </p>
                                            </div>
                                            <div class="mt-3 flex items-center justify-between text-xs font-semibold text-slate-500">
                                                <span>{{ candidate.dailyLoad }} classes today</span>
                                                <span>Rank {{ candidate.priority }}</span>
                                            </div>
                                        </button>
                                    </div>

                                    <p v-if="!candidateTeachersFor(target).length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                        No free teacher found for manual selection. The engine will leave this for review.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="activeProxyStep === 'Generate' && !activeRun" class="surface-card overflow-hidden bg-white">
                        <div class="flex flex-col gap-4 bg-white p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#8BED9A]/20 text-[#09B884]">
                                        <RefreshCw class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-base font-black text-[#1e2924]">Ready to generate</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ selectedDayBadge }} · {{ selectedTeacherIds.size }} teacher{{ selectedTeacherIds.size === 1 ? '' : 's' }} · {{ validManualAssignments.length }} manual · {{ Math.max(proxyTargets.length - validManualAssignments.length, 0) }} automatic</p>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:w-72">
                                <button
                                    type="button"
                                    class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#1e2924]/95 px-5 text-sm font-bold text-white shadow-md shadow-[#1e2924]/15 transition hover:-translate-y-0.5 hover:bg-[#1e2924] disabled:cursor-not-allowed disabled:bg-slate-300"
                                    :disabled="!canGenerate"
                                    @click="generateProxyRun"
                                >
                                    <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isSubmitting }" />
                                    {{ isSubmitting ? 'Generating...' : 'Generate substitution plan' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeProxyStep === 'Generate'" class="surface-card overflow-hidden">
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-black text-[#1e2924]">Generated substitution plan</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ activeRun ? `${activeRun.routineName} - ${activeRun.day} - ${activeRun.createdAt}` : 'Generate a plan to review substitution changes here.' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button v-if="activeRun" type="button" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-3 text-xs font-black text-[#1e2924] transition hover:bg-stone-50" @click="printPage">
                                    <Printer class="h-3.5 w-3.5" />
                                    Export
                                </button>
                                <button
                                    v-if="activeRun && activeRun.status === 'Approved'"
                                    type="button"
                                    class="inline-flex min-h-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-black text-amber-800 transition hover:bg-amber-100"
                                    @click="disableProxyRun(activeRun)"
                                >
                                    Disable
                                </button>
                                <button
                                    v-if="activeRun && activeRun.status === 'Approved'"
                                    type="button"
                                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 text-xs font-black text-[#1e2924] transition hover:bg-[#8BED9A]/25"
                                    @click="sendWhatsAppUpdates(activeRun)"
                                >
                                    <Send class="h-3.5 w-3.5" />
                                    Send WhatsApp
                                </button>
                                <button
                                    v-else-if="activeRun"
                                    type="button"
                                    class="inline-flex min-h-9 items-center justify-center rounded-xl bg-[#1e2924] px-3 text-xs font-black text-white transition hover:-translate-y-0.5 hover:bg-[#1e2924]/90"
                                    @click="enableProxyRun(activeRun)"
                                >
                                    Enable
                                </button>
                                <Link
                                    v-if="activeRun"
                                    :href="`/proxy-manager/${activeRun.id}`"
                                    class="inline-flex min-h-9 items-center justify-center rounded-xl border border-[#8BED9A]/70 bg-[#8BED9A]/15 px-3 text-xs font-black text-[#1e2924] transition hover:bg-[#8BED9A]/25"
                                >
                                    Open substitution schedule
                                </Link>
                            </div>
                        </div>
                        <div class="border-t border-stone-200 p-5">
                            <div v-if="!activeRun" class="rounded-xl border border-dashed border-stone-300 p-10 text-center">
                                <ShieldCheck class="mx-auto h-9 w-9 text-slate-300" />
                                <p class="mt-3 text-sm font-semibold text-slate-950">No substitution plan selected</p>
                                <p class="mt-1 text-sm text-slate-500">Create a new substitution plan or open one from the library.</p>
                            </div>

                            <div v-else class="space-y-5">
                                <div class="flex flex-col gap-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-black text-[#1e2924]">{{ activeRun.name }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ activeRun.status === 'Approved' ? 'Enabled on the active routine' : 'Saved as a draft substitution plan' }}</p>
                                        <p v-if="activeRun.messageSummary?.total" class="mt-2 text-xs font-bold text-slate-500">
                                            WhatsApp updates: {{ activeRun.messageSummary.sent }} sent, {{ activeRun.messageSummary.skipped }} skipped, {{ activeRun.messageSummary.failed }} failed
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-md border border-[#09B884]/30 bg-[#8BED9A]/15 px-3 py-2">
                                        <p class="text-sm font-bold text-[#1e2924]">{{ activeRun.metrics.swapCount ?? 0 }}</p>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]">Swaps</p>
                                    </div>
                                    <div class="rounded-md border border-[#8BED9A]/70 bg-[#8BED9A]/20 px-3 py-2">
                                        <p class="text-sm font-bold text-[#1e2924]">{{ activeRun.metrics.proxyCount ?? 0 }}</p>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[#1e2924]">Proxies</p>
                                    </div>
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-sm font-bold text-slate-950">{{ latestResolvedRate }}%</p>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Resolved</p>
                                    </div>
                                </div>
                                </div>

                                <div v-if="activeConflictRun && activeRun.status !== 'Approved'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                                    Enabling this plan will automatically disable "{{ activeConflictRun.name }}" for {{ activeRun.day }}.
                                </div>

                                <div v-if="activeRun.adjustments?.length" class="rounded-lg border border-[#09B884]/30 bg-[#8BED9A]/15 p-4">
                                    <div class="flex items-center gap-2 text-sm font-semibold text-[#1e2924]">
                                        <ArrowRightLeft class="h-4 w-4" />
                                        Period swaps applied
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        <div v-for="adjustment in activeRun.adjustments" :key="`${adjustment.classLabel}-${adjustment.from}-${adjustment.to}`" class="text-sm text-[#1e2924]">
                                            {{ adjustment.classLabel }}: {{ adjustment.coverTeacher }} covers {{ adjustment.from }}, {{ adjustment.absentTeacher }} moves to {{ adjustment.to }}.
                                        </div>
                                    </div>
                                </div>

                                <div v-for="group in activeRun.assignments" :key="group.period">
                                    <div class="mb-2 flex items-center gap-2">
                                        <Clock3 class="h-4 w-4 text-slate-400" />
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <div v-for="item in group.items" :key="item.id" class="rounded-lg border p-3 shadow-sm" :class="assignmentClass(item)">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="font-semibold text-slate-950">{{ item.classLabel }}</p>
                                                        <span class="rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-semibold" :class="strategyTone(item)">
                                                            {{ item.strategyLabel }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-sm text-slate-700">{{ item.subject }}</p>
                                                    <p class="mt-1 text-xs text-slate-500">Unavailable: {{ item.absentTeacher }}</p>
                                                </div>

                                                <div class="shrink-0 text-left sm:text-right">
                                                    <p v-if="item.assignedTeacher" class="text-sm font-semibold text-slate-950">{{ item.assignedTeacher }}</p>
                                                    <p v-else class="text-sm font-semibold text-red-700">No teacher assigned</p>
                                                    <p class="mt-1 max-w-xs text-xs text-slate-500">{{ item.reason }}</p>
                                                </div>
                                            </div>

                                            <div v-if="item.swapPath?.length" class="mt-3 rounded-md border border-amber-200 bg-white/70 p-2">
                                                <div v-for="step in item.swapPath" :key="`${step.teacher}-${step.from}-${step.to}`" class="flex items-center justify-between gap-3 text-xs text-amber-900">
                                                    <span class="font-medium">{{ step.teacher }}</span>
                                                    <span>{{ step.from }} - {{ step.to }}</span>
                                                </div>
                                            </div>

                                            <div v-if="item.subjectGroup" class="mt-3 inline-flex items-center gap-1 rounded-full bg-white/70 px-2 py-1 text-xs font-semibold text-slate-600">
                                                <SlidersHorizontal class="h-3 w-3" />
                                                {{ item.subjectGroup }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-black text-[#1e2924] shadow-sm transition hover:border-[#8BED9A]/70 hover:bg-[#8BED9A]/10 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="activeProxyStepIndex === 0"
                            @click="goToProxyStep(-1)"
                        >
                            Back
                        </button>

                        <div class="flex items-center justify-center gap-1.5">
                            <span
                                v-for="step in proxySteps"
                                :key="`dot-${step.name}`"
                                class="h-2.5 rounded-full transition-all duration-300"
                                :class="proxySteps.indexOf(step) <= activeProxyStepIndex ? 'w-8 bg-[#09B884]' : 'w-2.5 bg-stone-300'"
                            ></span>
                        </div>

                        <button
                            v-if="activeProxyStepIndex < proxySteps.length - 1"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#1e2924] px-5 text-sm font-black text-white shadow-sm shadow-black/10 transition hover:bg-[#1e2924]/90"
                            @click="goToProxyStep(1)"
                        >
                            Next
                        </button>
                        <span v-else class="inline-flex min-h-11 items-center rounded-xl bg-[#8BED9A]/16 px-4 text-sm font-black text-[#1e2924]">
                            Ready
                        </span>
                    </div>
                    </section>
                </Transition>

                <section class="proxy-print-export">
                    <header class="proxy-print-header">
                        <h1>{{ activeRun?.name ?? 'Substitution plan' }}</h1>
                        <p>{{ activeRun?.routineName ?? activeRoutine?.name }} - {{ activeRun?.day ?? selectedDay }} - {{ activeRun?.createdAt ?? runDate }}</p>
                    </header>

                    <section v-if="activeRun" class="proxy-print-section">
                        <h2>Summary</h2>
                        <div class="proxy-print-stats">
                            <div><strong>{{ activeRun.metrics?.swapCount ?? 0 }}</strong><span>Swaps</span></div>
                            <div><strong>{{ activeRun.metrics?.proxyCount ?? 0 }}</strong><span>Proxies</span></div>
                            <div><strong>{{ latestResolvedRate }}%</strong><span>Resolved</span></div>
                        </div>
                    </section>

                    <section v-if="activeRun?.adjustments?.length" class="proxy-print-section">
                        <h2>Period Swaps</h2>
                        <table class="proxy-print-table">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Cover Teacher</th>
                                    <th>Unavailable Teacher</th>
                                    <th>Move</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="adjustment in activeRun.adjustments" :key="`print-adjustment-${adjustment.classLabel}-${adjustment.from}-${adjustment.to}`">
                                    <td>{{ adjustment.classLabel }}</td>
                                    <td>{{ adjustment.coverTeacher }}</td>
                                    <td>{{ adjustment.absentTeacher }}</td>
                                    <td>{{ adjustment.from }} to {{ adjustment.to }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section v-if="activeRun?.assignments?.length" class="proxy-print-section">
                        <h2>Substitution Assignments</h2>
                        <article v-for="group in activeRun.assignments" :key="`print-proxy-group-${group.period}`" class="proxy-print-group">
                            <h3>{{ group.label }}</h3>
                            <table class="proxy-print-table">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Unavailable</th>
                                        <th>Assigned</th>
                                        <th>Strategy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in group.items" :key="`print-proxy-item-${item.id}`">
                                        <td>{{ item.classLabel }}</td>
                                        <td>{{ item.subject }}</td>
                                        <td>{{ item.absentTeacher }}</td>
                                        <td>{{ item.assignedTeacher || 'Unassigned' }}</td>
                                        <td>{{ item.strategyLabel }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </article>
                    </section>
                    </section>
                    </template>
                </div>

                <div v-else key="groups" class="surface-card overflow-visible">
                    <div class="border-b border-stone-200 bg-white p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="max-w-2xl">
                                <p class="text-sm font-semibold text-slate-950">Subject grouping</p>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex h-12 min-w-28 items-center gap-3 rounded-lg border border-stone-200 bg-white px-3 shadow-sm">
                                        <p class="text-lg font-bold leading-none text-slate-950">{{ subjectGroups.length }}</p>
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Groups</p>
                                    </div>
                                    <div class="flex h-12 min-w-28 items-center gap-3 rounded-lg border border-stone-200 bg-white px-3 shadow-sm">
                                        <p class="text-lg font-bold leading-none text-slate-950">{{ subjectOptions.length }}</p>
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Subjects</p>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button type="button" class="inline-flex h-12 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-[#1e2924]/95 px-5 text-sm font-bold text-white shadow-sm shadow-black/10 transition-colors hover:bg-[#1e2924]" @click="addSubjectGroup">
                                        <Plus class="h-4 w-4" />
                                        Add group
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-12 min-w-28 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-bold shadow-sm transition disabled:cursor-default"
                                        :class="groupsDirty ? 'bg-[#1e2924]/95 text-white shadow-sm shadow-black/10 hover:bg-[#1e2924] disabled:opacity-70' : 'border border-[#8BED9A]/70 bg-[#8BED9A]/20 text-[#1e2924]'"
                                        :disabled="!groupsDirty || isSavingGroups"
                                        @click="saveSubjectGroups"
                                    >
                                        <RefreshCw v-if="groupsDirty || isSavingGroups" class="h-4 w-4" :class="{ 'animate-spin': isSavingGroups }" />
                                        {{ isSavingGroups ? 'Saving...' : groupsDirty ? 'Save changes' : 'Saved' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div v-if="!subjectOptions.length" class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                            <SlidersHorizontal class="mx-auto h-7 w-7 text-slate-300" />
                            <p class="mt-2 text-sm font-semibold text-slate-950">No subjects found in the active routine</p>
                        </div>

                        <div v-else-if="!subjectGroups.length" class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-8 text-center">
                            <Plus class="mx-auto h-7 w-7 text-slate-300" />
                            <p class="mt-2 text-sm font-semibold text-slate-950">No subject groups yet</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div v-for="(group, index) in subjectGroups" :key="group.id" class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <input v-model="group.name" type="text" class="field-control w-full" @input="markGroupsDirty" />
                                            <p class="mt-2 text-xs text-slate-500">{{ group.subjects.length }} selected subjects</p>
                                        </div>
                                        <button type="button" class="rounded-md p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" @click="removeSubjectGroup(index)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="mt-4 min-h-16 rounded-lg border border-stone-200 bg-stone-50 p-3">
                                        <div v-if="group.subjects.length" class="flex flex-wrap gap-2">
                                            <span
                                                v-for="subject in group.subjects"
                                                :key="`${group.id}-selected-${subject}`"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-[#8BED9A]/70 bg-white px-2.5 py-1 text-xs font-semibold text-[#1e2924] shadow-sm"
                                            >
                                                {{ subject }}
                                                <button type="button" class="rounded text-slate-400 hover:text-rose-700" @click="removeGroupSubject(group, subject)">
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </div>
                                        <p v-else class="text-sm text-slate-500">No subjects selected yet.</p>
                                    </div>

                                    <div class="relative mt-3">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-stone-50"
                                            @click="openSubjectPicker(group)"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Add subject
                                        </button>

                                        <div
                                            v-if="subjectPicker.groupId === group.id"
                                            class="absolute left-0 top-12 z-30 w-full max-w-xl rounded-lg border border-stone-200 bg-white p-3 shadow-xl"
                                        >
                                            <div class="flex items-center justify-between gap-3 border-b border-stone-200 pb-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-950">Select subjects</p>
                                                    <p class="text-xs text-slate-500">{{ subjectPicker.draft.length }} selected for {{ group.name }}</p>
                                                </div>
                                                <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-stone-100 hover:text-slate-700" @click="closeSubjectPicker">
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <div class="relative mt-3">
                                                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                                <input
                                                    v-model="subjectPicker.query"
                                                    type="text"
                                                    class="field-control w-full pl-9"
                                                    placeholder="Search subjects..."
                                                />
                                            </div>

                                            <div class="mt-3 max-h-56 space-y-1 overflow-y-auto pr-1">
                                                <button
                                                    v-for="subject in filteredPickerSubjects"
                                                    :key="`${group.id}-picker-${subject}`"
                                                    type="button"
                                                    class="flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-left text-sm transition"
                                                    :class="draftHasSubject(subject) ? 'border-[#09B884]/50 bg-[#8BED9A]/15 text-[#1e2924]' : 'border-stone-200 bg-white text-slate-700 hover:bg-stone-50'"
                                                    @click="toggleDraftSubject(subject)"
                                                >
                                                    <span class="font-medium">{{ subject }}</span>
                                                    <span
                                                        class="flex h-4 w-4 items-center justify-center rounded border"
                                                        :class="draftHasSubject(subject) ? 'border-[#09B884] bg-[#09B884]' : 'border-stone-300 bg-white'"
                                                    >
                                                        <span v-if="draftHasSubject(subject)" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                                    </span>
                                                </button>
                                                <p v-if="!filteredPickerSubjects.length" class="px-3 py-6 text-center text-sm text-slate-500">No matching subjects.</p>
                                            </div>

                                            <div class="mt-3 flex justify-end gap-2 border-t border-stone-200 pt-3">
                                                <button type="button" class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-stone-50" @click="closeSubjectPicker">
                                                    Cancel
                                                </button>
                                                <button type="button" class="rounded-md bg-[#1e2924]/95 px-3 py-2 text-sm font-semibold text-white shadow-sm shadow-black/10 hover:bg-[#1e2924]" @click="confirmSubjectPicker(group)">
                                                    Done
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </Transition>
            </template>
        </div>

        <Teleport to="body">
            <Transition name="proxy-dialog">
                <div v-if="whatsappDialog.open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#1e2924]/45 px-4 py-6 backdrop-blur-sm" @click.self="closeWhatsAppDialog">
                    <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-white/70 bg-white shadow-2xl shadow-[#1e2924]/25">
                        <div class="border-b border-[#8BED9A]/50 bg-[#8BED9A]/12 p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-lg font-black text-[#1e2924]">WhatsApp message review</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-600">
                                        {{ whatsappDialog.run?.name }} · {{ whatsappDialog.run?.day }} · proxy messages are shown first.
                                    </p>
                                </div>
                                <button type="button" class="rounded-xl border border-stone-200 bg-white p-2 text-[#1e2924] shadow-sm transition hover:bg-stone-50" @click="closeWhatsAppDialog">
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto p-5">
                            <div v-if="whatsappDialog.loading" class="rounded-2xl border border-dashed border-stone-300 p-10 text-center">
                                <RefreshCw class="mx-auto h-8 w-8 animate-spin text-[#09B884]" />
                                <p class="mt-3 text-sm font-black text-[#1e2924]">Preparing teacher messages...</p>
                            </div>

                            <div v-else-if="whatsappDialog.error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
                                {{ whatsappDialog.error }}
                            </div>

                            <div v-else-if="!whatsappDialog.messages.length" class="rounded-2xl border border-dashed border-stone-300 p-10 text-center">
                                <Send class="mx-auto h-8 w-8 text-slate-300" />
                                <p class="mt-3 text-sm font-black text-[#1e2924]">No teacher messages available</p>
                            </div>

                            <div v-else class="space-y-4">
                                <article
                                    v-for="(message, index) in whatsappDialog.messages"
                                    :key="`${message.teacherId}-${index}`"
                                    class="rounded-2xl border bg-white p-4 shadow-sm"
                                    :class="message.hasProxy ? 'border-[#8BED9A]/80 bg-[#8BED9A]/8' : 'border-stone-200'"
                                >
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-base font-black text-[#1e2924]">{{ message.teacherName }}</p>
                                                <span v-if="message.hasProxy" class="rounded-full bg-[#8BED9A]/30 px-2.5 py-1 text-[11px] font-black uppercase text-[#1e2924]">Has substitution</span>
                                                <span v-else class="rounded-full bg-stone-100 px-2.5 py-1 text-[11px] font-black uppercase text-slate-500">Regular</span>
                                                <span v-if="message.whatsappEnabled === false" class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-black uppercase text-amber-800">Disabled in settings</span>
                                            </div>
                                            <p class="mt-1 text-xs font-bold text-slate-500">
                                                WhatsApp: {{ message.displayNumber || message.whatsappNumber || 'No number saved' }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex min-h-10 items-center justify-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-black text-[#1e2924] transition hover:bg-stone-50"
                                            @click="copyWhatsAppMessage(index)"
                                        >
                                            {{ whatsappDialog.copiedIndex === index ? 'Copied' : 'Copy message' }}
                                        </button>
                                    </div>

                                    <textarea
                                        v-model="message.message"
                                        rows="9"
                                        class="mt-4 w-full resize-y rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-semibold leading-6 text-slate-700 shadow-inner shadow-stone-100 outline-none transition focus:border-[#09B884] focus:ring-4 focus:ring-[#8BED9A]/20"
                                        :class="message.whatsappEnabled === false ? 'opacity-60' : ''"
                                        :disabled="message.whatsappEnabled === false"
                                    ></textarea>
                                </article>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-stone-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs font-bold text-slate-500">
                                {{ whatsappDialog.messages.filter((message) => message.hasProxy).length }} substitution updates · {{ whatsappDialog.messages.length }} total messages
                            </p>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-200 bg-white px-5 text-sm font-black text-[#1e2924] shadow-sm transition hover:-translate-y-0.5 hover:bg-stone-50" @click="closeWhatsAppDialog">
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#1e2924] px-5 text-sm font-black text-white shadow-md shadow-[#1e2924]/15 transition hover:-translate-y-0.5 hover:bg-[#1e2924]/90 disabled:cursor-not-allowed disabled:bg-slate-300"
                                    :disabled="whatsappDialog.loading || whatsappDialog.sending || !whatsappDialog.messages.length"
                                    @click="submitWhatsAppMessages"
                                >
                                    <Send class="h-4 w-4" />
                                    {{ whatsappDialog.sending ? 'Sending...' : 'Send edited messages' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="proxy-dialog">
                <div v-if="confirmDialog.open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#1e2924]/45 px-4 py-6 backdrop-blur-sm" @click.self="closeConfirmDialog">
                    <div class="w-full max-w-md overflow-hidden rounded-3xl border border-white/70 bg-white shadow-2xl shadow-[#1e2924]/25">
                        <div
                            class="p-5"
                            :class="confirmDialog.tone === 'warning' ? 'bg-amber-50/80' : 'bg-red-50/80'"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border bg-white shadow-sm"
                                    :class="confirmDialog.tone === 'warning' ? 'border-amber-200 text-amber-700' : 'border-red-200 text-red-700'"
                                >
                                    <AlertTriangle class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-lg font-black text-[#1e2924]">{{ confirmDialog.title }}</p>
                                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">{{ confirmDialog.message }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-2 bg-white p-4 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-200 bg-white px-5 text-sm font-black text-[#1e2924] shadow-sm transition hover:-translate-y-0.5 hover:bg-stone-50"
                                @click="closeConfirmDialog"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-sm font-black text-white shadow-md transition hover:-translate-y-0.5"
                                :class="confirmDialog.tone === 'warning' ? 'bg-[#1e2924] shadow-[#1e2924]/15 hover:bg-[#1e2924]/90' : 'bg-red-600 shadow-red-600/15 hover:bg-red-700'"
                                @click="confirmDialogAction"
                            >
                                {{ confirmDialog.confirmLabel }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.proxy-print-export {
    display: none;
}

.proxy-manager-shell {
    animation: proxy-rise 420ms ease-out both;
}

.proxy-tab-enter-active,
.proxy-tab-leave-active {
    transition: all 220ms ease;
}

.proxy-dialog-enter-active,
.proxy-dialog-leave-active {
    transition: opacity 180ms ease;
}

.proxy-dialog-enter-active > div,
.proxy-dialog-leave-active > div {
    transition: transform 220ms ease, opacity 180ms ease;
}

.proxy-dialog-enter-from,
.proxy-dialog-leave-to {
    opacity: 0;
}

.proxy-dialog-enter-from > div,
.proxy-dialog-leave-to > div {
    opacity: 0;
    transform: translateY(10px) scale(0.97);
}

.proxy-tab-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.proxy-tab-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@keyframes proxy-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media print {
    :global(body *) {
        visibility: hidden !important;
    }

    :global(.proxy-print-export),
    :global(.proxy-print-export *) {
        visibility: visible !important;
    }

    .proxy-print-export {
        display: block !important;
        position: absolute;
        inset: 0;
        width: 100%;
        background: #fff;
        color: #111827;
        padding: 18px;
        font-family: Arial, sans-serif;
    }

    .proxy-print-header {
        border-bottom: 2px solid #111827;
        margin-bottom: 16px;
        padding-bottom: 10px;
    }

    .proxy-print-header h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
    }

    .proxy-print-header p {
        margin: 4px 0 0;
        color: #4b5563;
        font-size: 12px;
        font-weight: 600;
    }

    .proxy-print-section {
        break-inside: avoid;
        margin-bottom: 16px;
    }

    .proxy-print-section h2 {
        margin: 0 0 8px;
        font-size: 15px;
        font-weight: 800;
    }

    .proxy-print-group {
        break-inside: avoid;
        margin-bottom: 12px;
    }

    .proxy-print-group h3 {
        margin: 0 0 6px;
        font-size: 12px;
        font-weight: 800;
    }

    .proxy-print-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .proxy-print-stats div {
        border: 1px solid #cbd5e1;
        padding: 8px;
    }

    .proxy-print-stats strong {
        display: block;
        font-size: 18px;
    }

    .proxy-print-stats span {
        display: block;
        color: #64748b;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .proxy-print-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 10px;
    }

    .proxy-print-table th,
    .proxy-print-table td {
        border: 1px solid #cbd5e1;
        padding: 6px;
        vertical-align: top;
        word-break: break-word;
    }

    .proxy-print-table th {
        background: #f1f5f9;
        font-weight: 800;
        text-align: left;
    }
}
</style>
