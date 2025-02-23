<script setup lang="ts">
import { ref, onMounted, watch, reactive } from "vue";
import { router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import PaginationNumeric from "@/components/pagination/PaginationNumeric.vue";
import VButton from "@/components/VButton.vue";
import Datepicker from "@/components/Datepicker.vue";
import DropdownFilter from "@/components/DropdownFilter.vue";

interface EmotionData {
    id: number;
    user_id: number;
    primary: string;
    primary_label: string;
    secondary: string;
    secondary_label: string;
    description: string;
    score: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    emotions: {
        current_page: number;
        data: EmotionData[];
        first_page_url: string;
        from: number;
        last_page: number;
        last_page_url: string;
        links: any[];
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number;
        total: number;
    };
    dateRange: {
        startDate: string;
        endDate: string;
    };
}

const props = defineProps<Props>();

let isUpdating = false;

const emotions = ref<EmotionData[]>(props.emotions.data);
const dateRange = ref({
    startDate: new Date(props.dateRange?.startDate || new Date().setDate(new Date().getDate() - 7)),
    endDate: new Date(props.dateRange?.endDate || new Date())
});

const filters = ref([
    { label: 'Beschreibung', value: 'onlyWithDescription', selected: false },
])

watch(dateRange, (newRange) => {
    handleDateRangeUpdate(newRange);
}, { deep: true });



const getFilterParams = () => {
    return filters.value
        .filter(filter => filter.selected)
        .map(filter => ({ [filter.value]: 1 }))
        .reduce((acc, curr) => ({ ...acc, ...curr }), {});
};


const handleDateRangeUpdate = (newRange) => {
    // Check if both dates are present and valid
    if (!newRange?.startDate || !newRange?.endDate) {
        dateRange.value = newRange; // Update local state but don't trigger search
        return;
    }

    // Validate dates are actual Date objects
    if (!(newRange.startDate instanceof Date) || !(newRange.endDate instanceof Date)) 
        return;

    // Check if dates are valid (not NaN)
    if (isNaN(newRange.startDate.getTime()) || isNaN(newRange.endDate.getTime()))
        return;

    dateRange.value = newRange;

    search();
};

const handleFiltersChanged = (newFilters) => {
    filters.value = newFilters;

    search();
};

const search = () => {
    if (isUpdating) return;

    isUpdating = true;

    const filterParams = getFilterParams();
    console.log(filterParams);

    const request = {
            startDate: dateRange.value.startDate.toLocaleString(),
            endDate: dateRange.value.endDate.toLocaleString(),
            ...filterParams
        };
    
    console.log(request);

    router.get(
        route('emotions.index'), request,        
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setTimeout(() => {
                    isUpdating = false;
                }, 0);
            }
        }
    );
};

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return day + "." + month + "." + year;
}

onMounted(() => {
    console.log(props.emotions);
});

</script>

<template>
    <Layout title="Emotions">
        <template v-slot:actions>
            <DropdownFilter :options="filters" @filtersChanged="handleFiltersChanged" />
            <Datepicker align="right" v-model="dateRange" />
            <VButton :href="route('emotions.create')">Create</VButton>
        </template>

        <div class="w-full">
            <!-- Emotion header -->
            <div class="flex justify-between items-center mb-4">
                <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                    Showing {{ props.emotions.total }} Emotions
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h2>Aktuelle Filter:</h2>
            <ul>
                <li v-for="filter in filters" :key="filter.value">
                    {{ filter.label }}: {{ filter.selected ? 'aktiv' : 'inaktiv' }}
                </li>
            </ul>
        </div>

        <!-- Rest of your template remains the same -->
        <div class="space-y-2">
            <div v-for="emotion in props.emotions.data" :key="emotion.id">
                <Link :href="route('emotions.show', emotion.id)">
                <div class="bg-white dark:bg-gray-800 shadow-xs rounded-xl px-5 py-4">
                    <div class="flex justify-between items-center space-x-2">
                        <!-- Left side -->
                        <div class="flex items-start space-x-3 md:space-x-4">
                            <div class="text-4xl">
                                {{ emotion.score }}
                            </div>
                            <div>
                                <span class="inline-flex font-semibold text-gray-800 dark:text-gray-100">
                                    {{ emotion.primary_label }}
                                </span>
                                <div class="text-sm">{{ emotion.secondary_label }}</div>
                            </div>
                        </div>
                        <!-- Right side -->
                        <div class="flex items-center space-x-4 pl-10 md:pl-0">
                            <div class="text-sm text-gray-500 dark:text-gray-400 italic whitespace-nowrap">
                                {{ formatDate(emotion.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
                </Link>
            </div>
        </div>

        <PaginationNumeric :pagination="props.emotions" />
    </Layout>
</template>