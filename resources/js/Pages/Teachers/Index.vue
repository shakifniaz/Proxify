<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Search, Plus, Pencil } from 'lucide-vue-next';

// Shape mirrors a future TeacherController@index response.
const props = defineProps({
    teachers: { type: Array, default: () => [] }, // [{ id, name, phone, initials, avatarColor, subject, proxyLoadThisMonth, leaveUsedDays, status, role }]
    subjectOptions: { type: Array, default: () => [] },
});

// Literal Tailwind class strings per avatar color — required so the JIT
// scanner can find them; never interpolate color names into class names.
const avatarColors = {
    emerald: 'bg-emerald-500/20 text-emerald-300',
    sky: 'bg-sky-500/20 text-sky-300',
    violet: 'bg-violet-500/20 text-violet-300',
    amber: 'bg-amber-500/20 text-amber-300',
    rose: 'bg-rose-500/20 text-rose-300',
};
const avatarColorKeys = Object.keys(avatarColors);

const statusBadge = {
    Active: 'bg-emerald-500/15 text-emerald-400',
    'On leave': 'bg-rose-500/15 text-rose-400',
    Inactive: 'bg-slate-800 text-slate-500',
};
const statusOptions = ['Active', 'On leave', 'Inactive'];

const roleBadge = {
    Teacher: 'bg-slate-800 text-slate-400',
    Admin: 'bg-violet-500/15 text-violet-400',
};
const roleOptions = ['Teacher', 'Admin'];

// Local, mutable copy — add/edit operates on this, never on props directly.
const localTeachers = ref(props.teachers.map((t) => ({ ...t })));

const search = ref('');
const filteredTeachers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return localTeachers.value;
    return localTeachers.value.filter(
        (t) => t.name.toLowerCase().includes(q) || t.phone.includes(q) || t.subject.toLowerCase().includes(q)
    );
});

function proxyLoadBadgeClass(teacher) {
    return teacher.status === 'On leave' ? 'bg-amber-500/15 text-amber-400' : 'bg-emerald-500/15 text-emerald-400';
}

/* ---------------------------- Add / Edit popup ---------------------------- */

const editing = ref(null); // { id, name, phone, subject, status, role, isNew }

function initialsFor(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function openAdd() {
    editing.value = { id: null, name: '', phone: '', subject: '', status: 'Active', role: 'Teacher', isNew: true };
}

function openEdit(teacher) {
    editing.value = {
        id: teacher.id,
        name: teacher.name,
        phone: teacher.phone,
        subject: teacher.subject,
        status: teacher.status,
        role: teacher.role ?? 'Teacher',
        isNew: false,
    };
}

function closeEditor() {
    editing.value = null;
}

function saveEditor() {
    if (!editing.value) return;
    const { id, name, phone, subject, status, role, isNew } = editing.value;
    if (!name || !phone || !subject) return;

    if (isNew) {
        const nextId = Math.max(0, ...localTeachers.value.map((t) => t.id)) + 1;
        const color = avatarColorKeys[localTeachers.value.length % avatarColorKeys.length];
        localTeachers.value.push({
            id: nextId,
            name,
            phone,
            subject,
            status,
            role,
            initials: initialsFor(name),
            avatarColor: color,
            proxyLoadThisMonth: 0,
            leaveUsedDays: 0,
        });
    } else {
        const teacher = localTeachers.value.find((t) => t.id === id);
        if (teacher) {
            teacher.name = name;
            teacher.phone = phone;
            teacher.subject = subject;
            teacher.status = status;
            teacher.role = role;
            teacher.initials = initialsFor(name);
        }
    }
    closeEditor();
}
</script>

<template>
    <AppLayout title="Teachers">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">Teachers</h2>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search teachers..."
                            class="w-56 rounded-lg border border-slate-800 bg-slate-800/60 py-2 pl-9 pr-3 text-sm text-slate-200 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                        @click="openAdd"
                    >
                        <Plus class="h-4 w-4" />
                        Add teacher
                    </button>
                </div>
            </div>

            <!-- Teacher table -->
            <div class="rounded-xl border border-slate-800 bg-slate-900/50">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-3 font-medium">Teacher</th>
                                <th class="px-3 py-3 font-medium">Role</th>
                                <th class="px-3 py-3 font-medium">Subjects</th>
                                <th class="px-3 py-3 font-medium">Proxy Load</th>
                                <th class="px-3 py-3 font-medium">Leave Used</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="t in filteredTeachers" :key="t.id">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                            :class="avatarColors[t.avatarColor]"
                                        >
                                            {{ t.initials }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-slate-100">{{ t.name }}</p>
                                            <p class="text-xs text-slate-500">{{ t.phone }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="roleBadge[t.role ?? 'Teacher']">
                                        {{ t.role ?? 'Teacher' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-slate-300">{{ t.subject }}</td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="proxyLoadBadgeClass(t)">
                                        {{ t.proxyLoadThisMonth }} this month
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-slate-400">
                                    {{ t.leaveUsedDays }} day{{ t.leaveUsedDays === 1 ? '' : 's' }}
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge[t.status]">
                                        {{ t.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <button
                                        type="button"
                                        class="flex items-center gap-1.5 rounded-full border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800/50"
                                        @click="openEdit(t)"
                                    >
                                        <Pencil class="h-3 w-3" />
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!filteredTeachers.length" class="px-5 py-8 text-center text-sm text-slate-500">
                        No teachers match "{{ search }}".
                    </p>
                </div>
            </div>
        </div>

        <!-- Add / Edit teacher popup -->
        <Teleport to="body">
            <div
                v-if="editing"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
                @click.self="closeEditor"
            >
                <div class="w-full max-w-sm rounded-xl border border-slate-800 bg-slate-900 p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-white">{{ editing.isNew ? 'Add Teacher' : 'Edit Teacher' }}</h3>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-slate-400">Name</label>
                            <input
                                v-model="editing.name"
                                type="text"
                                placeholder="e.g. Mr. Sarkar"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-400">Phone (WhatsApp)</label>
                            <input
                                v-model="editing.phone"
                                type="text"
                                placeholder="+8801XXXXXXXXX"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-400">Subject</label>
                            <select
                                v-model="editing.subject"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="" disabled>Select subject</option>
                                <option v-for="s in subjectOptions" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-400">Status</label>
                            <select
                                v-model="editing.status"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-400">Role / Permissions</label>
                            <select
                                v-model="editing.role"
                                class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option v-for="r in roleOptions" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Admin gets full access to every module, same as Super Admin.</p>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800/50"
                            @click="closeEditor"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!editing.name || !editing.phone || !editing.subject"
                            @click="saveEditor"
                        >
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>