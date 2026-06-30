<script setup>
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    StickyNote, 
    Clock, 
    User, 
    Pin, 
    PlusCircle
} from 'lucide-vue-next';

const props = defineProps({
    boards: { type: Array, default: () => [] }
});

const activeBoardId = ref('handovers');
const activeBoard = ref(props.boards.find(b => b.id === 'handovers') || props.boards[0]);

function selectBoard(boardId) {
    activeBoardId.value = boardId;
    activeBoard.value = props.boards.find(b => b.id === boardId) || props.boards[0];
}

watch(() => props.boards, (newBoards) => {
    if (newBoards && newBoards.length > 0) {
        activeBoard.value = newBoards.find(b => b.id === activeBoardId.value) || newBoards[0];
    }
}, { immediate: true });
</script>

<template>
    <AppLayout title="Staff Room Workspace">
        <div class="space-y-6">

            <div class="flex flex-wrap gap-2 border-b border-stone-200 pb-4">
                <button
                    v-for="board in boards"
                    :key="board.id"
                    type="button"
                    @click="selectBoard(board.id)"
                    class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all"
                    :class="activeBoardId === board.id 
                        ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                        : 'text-slate-600 border border-transparent hover:bg-white hover:text-slate-800'"
                >
                    <Pin class="h-4 w-4" :class="activeBoardId === board.id ? 'rotate-45 text-blue-700' : 'text-slate-500'" />
                    {{ board.name }}
                </button>
            </div>

            <div v-if="activeBoard" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <div class="lg:col-span-2 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">{{ activeBoard.name }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ activeBoard.description }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div 
                            v-for="note in activeBoard.notes" 
                            :key="note.id"
                            class="rounded-lg border bg-white p-4 flex flex-col justify-between space-y-4 transition-all hover:border-slate-700"
                            :class="[
                                note.color === 'rose' ? 'border-red-200 bg-red-50' : '',
                                note.color === 'violet' ? 'border-violet-500/20 bg-violet-500/5' : '',
                                note.color === 'amber' ? 'border-amber-200 bg-amber-50' : '',
                                note.color === 'sky' ? 'border-sky-500/20 bg-sky-500/5' : '',
                                note.color === 'zinc' ? 'border-stone-300' : ''
                            ]"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                        :class="[
                                            note.color === 'rose' ? 'bg-red-50 text-red-700' : '',
                                            note.color === 'violet' ? 'bg-violet-500/10 text-violet-400' : '',
                                            note.color === 'amber' ? 'bg-amber-50 text-amber-700' : '',
                                            note.color === 'sky' ? 'bg-sky-500/10 text-sky-400' : '',
                                            note.color === 'zinc' ? 'bg-stone-100 text-slate-600' : ''
                                        ]"
                                    >
                                        {{ note.tag }}
                                    </span>
                                    <div class="flex items-center gap-1 text-[11px] text-slate-500">
                                        <Clock class="h-3 w-3" />
                                        {{ note.time }}
                                    </div>
                                </div>

                                <div class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                                    Context: <span class="text-slate-950 font-medium">{{ note.target }}</span>
                                </div>

                                <p class="text-xs text-slate-600 leading-relaxed pt-1">
                                    {{ note.content }}
                                </p>
                            </div>

                            <div class="pt-3 border-t border-stone-200/60 flex items-center gap-2 text-slate-500 text-[11px]">
                                <User class="h-3 w-3 text-slate-600" />
                                <span>From: <strong class="text-slate-700 font-medium">{{ note.author }}</strong></span>
                            </div>
                        </div>
                        
                        <div v-if="!activeBoard.notes || activeBoard.notes.length === 0" class="col-span-full py-8 text-center text-xs text-slate-500 italic">
                            No live notes actively pinned on this board.
                        </div>
                    </div>
                </div>

                <div class="surface-card p-5 space-y-4">
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4 text-blue-700" />
                            Pin a Live Note
                        </h4>
                        <p class="text-[11px] text-slate-500 leading-normal">
                            Pins are designed for short-term visibility and automatically clear out every 24 hours.
                        </p>
                    </div>

                    <div class="space-y-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Target Context / Room</label>
                            <input 
                                type="text" 
                                placeholder="e.g., Class 10-A, Science Lab" 
                                class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 placeholder-slate-600 focus:outline-none focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Board Category Tag</label>
                            <select class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-600 focus:outline-none focus:border-blue-500">
                                <option>Urgent Context</option>
                                <option>Task Reminder</option>
                                <option>Lab Layout</option>
                                <option>Keys & Storage</option>
                                <option>Swap Request</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Message Description</label>
                            <textarea 
                                rows="3" 
                                placeholder="Write down structural context guidelines clearly..." 
                                class="w-full bg-stone-100 border border-stone-300 rounded-lg px-3 py-2 text-xs text-slate-800 placeholder-slate-600 focus:outline-none focus:border-blue-500 resize-none"
                            ></textarea>
                        </div>

                        <button 
                            type="button" 
                            class="w-full bg-blue-700 hover:bg-blue-700 text-slate-950 font-bold text-xs py-2 px-4 rounded-lg transition-colors shadow-lg shadow-none"
                        >
                            Pin Note onto Desk
                        </button>
                    </div>
                </div>

            </div>
            
            <div v-else class="text-center py-12 text-sm text-slate-500">
                Loading board channels...
            </div>
        </div>
    </AppLayout>
</template>