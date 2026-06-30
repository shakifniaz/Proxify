<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    Layers, 
    BookOpen, 
    AlertTriangle, 
    CalendarDays, 
    FileText, 
    GraduationCap, 
    PlusCircle,
    Megaphone,
    BookmarkCheck
} from 'lucide-vue-next';

const props = defineProps({
    classrooms: { type: Array, default: () => [] } // Formatted inside your web.php data payload
});

const activeClassroomIndex = ref(0);
const currentClassroom = computed(() => props.classrooms[activeClassroomIndex.value] || null);

// Announcement Category Filters
const selectedSubjectFilter = ref('All Subjects');

const filteredAnnouncements = computed(() => {
    if (!currentClassroom.value) return [];
    if (selectedSubjectFilter.value === 'All Subjects') {
        return currentClassroom.value.announcements || [];
    }
    return (currentClassroom.value.announcements || []).filter(
        a => a.subject === selectedSubjectFilter.value
    );
});
</script>

<template>
    <AppLayout title="Academic Classrooms Management">
        <div class="space-y-6">

            <!-- Class Sub-Tabs Navigation Array Strip -->
            <div v-if="classrooms.length > 0" class="flex flex-wrap gap-2 border-b border-stone-200 pb-4">
                <button
                    v-for="(room, index) in classrooms"
                    :key="room.id"
                    type="button"
                    @click="activeClassroomIndex = index"
                    class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all"
                    :class="activeClassroomIndex === index 
                        ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                        : 'text-slate-600 border border-transparent hover:bg-white hover:text-slate-800'"
                >
                    <GraduationCap class="h-4 w-4" />
                    {{ room.name }}
                </button>
            </div>

            <!-- Single Classroom Active Desktop -->
            <div v-if="currentClassroom" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="surface-card p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                                <CalendarDays class="h-4 w-4 text-blue-700" />
                                Today's Schedule Deviations & Proxies
                            </h3>
                            <span class="text-[11px] text-slate-500 font-medium">Live sync tracking</span>
                        </div>

                        <div v-if="currentClassroom.proxyUpdates && currentClassroom.proxyUpdates.length > 0" class="space-y-2">
                            <div 
                                v-for="proxy in currentClassroom.proxyUpdates" 
                                :key="proxy.id"
                                class="rounded-lg border border-amber-200 bg-amber-50 p-3 flex gap-3 items-start"
                            >
                                <AlertTriangle class="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="text-xs font-bold text-amber-700 uppercase tracking-wide">{{ proxy.period }}</span>
                                        <span class="text-xs text-slate-700">— Substitute Teacher assigned</span>
                                    </div>
                                    <p class="text-xs text-slate-600 leading-normal">
                                        Original session (<span class="text-slate-700 font-medium">{{ proxy.originalTeacher }}</span>) will be covered by 
                                        <strong class="text-slate-900 font-semibold">{{ proxy.proxyTeacher }}</strong>. {{ proxy.note }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-slate-500 italic p-2 border border-dashed border-stone-300 rounded-lg text-center">
                            No external routine adjustments or substituted proxy slots recorded for this room class today.
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b border-stone-200 pb-2">
                            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                                <BookOpen class="h-4 w-4 text-blue-700" />
                                Active Academic Coursework Load
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div 
                                v-for="subject in currentClassroom.subjects" 
                                :key="subject.id"
                                class="surface-card p-4 space-y-3"
                            >
                                <div class="flex items-center justify-between border-b border-stone-200 pb-2">
                                    <h4 class="text-xs font-bold text-slate-950 uppercase tracking-wide">{{ subject.name }}</h4>
                                    <span class="text-[11px] text-slate-500">{{ subject.teacher }}</span>
                                </div>

                                <div class="space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700/80 block">Current Syllabus Target</span>
                                    <p class="text-xs text-slate-600 leading-normal">{{ subject.syllabus }}</p>
                                </div>

                                <div class="bg-stone-50 p-2.5 rounded-lg border border-stone-300 space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 block">Homework Desk</span>
                                    <p class="text-xs text-slate-700 leading-relaxed">{{ subject.homework }}</p>
                                </div>

                                <!-- Embedded Task Notice -->
                                <div v-if="subject.assignment" class="border-l-2 border-violet-500/40 pl-2.5 py-0.5 space-y-0.5">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-violet-400 flex items-center gap-1">
                                        <FileText class="h-3 w-3" />
                                        Task Assessment: {{ subject.assignment.title }}
                                    </span>
                                    <p class="text-[11px] text-slate-600 leading-tight">{{ subject.assignment.instruction }}</p>
                                    <span class="block text-[10px] text-slate-500 font-semibold pt-0.5">Due: {{ subject.assignment.deadline }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-stone-200 pb-2">
                            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                                <Megaphone class="h-4 w-4 text-blue-700" />
                                Interactive Noticeboard & Updates Stream
                            </h3>
                            
                            <select 
                                v-model="selectedSubjectFilter"
                                class="bg-stone-100 border border-stone-300 rounded-lg px-2.5 py-1 text-[11px] text-slate-600 focus:outline-none focus:border-blue-500"
                            >
                                <option>All Subjects</option>
                                <option v-for="subj in currentClassroom.subjects" :key="subj.id" :value="subj.name">
                                    {{ subj.name }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="announcement in filteredAnnouncements" 
                                :key="announcement.id"
                                class="rounded-lg border border-stone-200 bg-white p-4 space-y-2 hover:border-slate-700/80 transition-colors"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                            {{ announcement.subject }}
                                        </span>
                                        <span 
                                            class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"
                                            :class="[
                                                announcement.type === 'Test Announcement' ? 'bg-red-50 text-red-700 border border-red-200' : '',
                                                announcement.type === 'Assignment Announcement' ? 'bg-violet-500/10 text-violet-400 border border-violet-500/20' : '',
                                                announcement.type === 'General' ? 'bg-stone-100 text-slate-600 border border-slate-700/50' : ''
                                            ]"
                                        >
                                            {{ announcement.type }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] text-slate-500">{{ announcement.date }}</span>
                                </div>
                                <p class="text-xs text-slate-700 leading-relaxed">{{ announcement.content }}</p>
                                <div class="text-[10px] text-slate-500 font-medium pt-1">Posted by: <span class="text-slate-600">{{ announcement.author }}</span></div>
                            </div>

                            <div v-if="filteredAnnouncements.length === 0" class="text-center py-8 text-xs text-slate-500 italic">
                                No specific broadcast announcements mapped matching your active filter logic.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side Broadcast Dispatch Form Console -->
                <div class="surface-card p-5 space-y-4">
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4 text-blue-700" />
                            Dispatch Local Update
                        </h4>
                        <p class="text-[11px] text-slate-500 leading-normal">
                            Instantly push homework instructions, syllabus markers, or system notifications straight into the {{ currentClassroom.name }} student dashboard feed.
                        </p>
                    </div>

                    <div class="space-y-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Target Subject Channel</label>
                            <select class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-600 focus:outline-none focus:border-blue-500">
                                <option v-for="subj in currentClassroom.subjects" :key="subj.id">{{ subj.name }}</option>
                                <option>General Classroom Sync</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Update Type Classification</label>
                            <select class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-600 focus:outline-none focus:border-blue-500">
                                <option>Homework Desk Update</option>
                                <option>Syllabus Milestone</option>
                                <option>Test Announcement</option>
                                <option>Assignment Announcement</option>
                                <option>General Announcement</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Instruction Details</label>
                            <textarea 
                                rows="4" 
                                placeholder="State clearly what needs to be prepared, revised, or reviewed..." 
                                class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 placeholder-slate-600 focus:outline-none focus:border-blue-500 resize-none"
                            ></textarea>
                        </div>

                        <button 
                            type="button" 
                            class="w-full bg-blue-700 hover:bg-blue-700 text-slate-950 font-bold text-xs py-2 px-4 rounded-lg transition-colors shadow-lg shadow-none flex items-center justify-center gap-1.5"
                        >
                            <BookmarkCheck class="h-4 w-4" />
                            Broadcast to Class Stream
                        </button>
                    </div>
                </div>

            </div>

            <div v-else class="text-center py-12 text-sm text-slate-500 italic">
                No active classroom streams loaded into system storage.
            </div>
        </div>
    </AppLayout>
</template>