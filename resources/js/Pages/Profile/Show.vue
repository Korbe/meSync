<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckBadgeIcon, ExclamationCircleIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import Layout from '@/Layouts/Layout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const initials = computed(() => user.value.name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase());

const memberSince = computed(() => new Date(user.value.created_at).toLocaleDateString('de-AT', { month: 'long', year: 'numeric' }));
</script>

<template>
    <Layout title="Profil">
        <div class="mx-auto max-w-6xl space-y-6">

            <!-- Profile header -->
            <section class="card relative overflow-hidden p-6 sm:p-8">
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute -top-24 -left-16 h-64 w-64 rounded-full bg-primary-500/20 blur-[90px]"></div>
                    <div class="absolute -bottom-24 right-10 h-56 w-56 rounded-full bg-accent/10 blur-[90px]"></div>
                </div>

                <div class="relative flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                    <!-- Avatar with brand gradient ring -->
                    <div class="rounded-full bg-linear-to-br from-primary-400 via-primary-500 to-accent p-[3px] shadow-[0_0_40px_-8px_rgba(31,173,174,0.8)]">
                        <img v-if="$page.props.jetstream.managesProfilePhotos && user.profile_photo_path" :src="user.profile_photo_url" :alt="user.name" class="size-20 rounded-full object-cover">
                        <div v-else class="flex size-20 items-center justify-center rounded-full bg-white font-display text-2xl font-bold text-primary-600 dark:bg-gray-950 dark:text-primary-300">
                            {{ initials }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <h2 class="truncate font-display text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-white">{{ user.name }}</h2>
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-if="user.email_verified_at" class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/10 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-500/30 dark:text-primary-300">
                                <CheckBadgeIcon class="h-3.5 w-3.5" />E-Mail bestätigt
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-accent/40 dark:text-accent">
                                <ExclamationCircleIcon class="h-3.5 w-3.5" />E-Mail nicht bestätigt
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-500/10 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:text-gray-400">
                                <CalendarDaysIcon class="h-3.5 w-3.5" />Dabei seit {{ memberSince }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile + password side by side -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <UpdateProfileInformationForm v-if="$page.props.jetstream.canUpdateProfileInformation" :user="user" />
                <UpdatePasswordForm v-if="$page.props.jetstream.canUpdatePassword" />
            </div>

            <TwoFactorAuthenticationForm
                v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                :requires-confirmation="confirmsTwoFactorAuthentication"
            />

            <LogoutOtherBrowserSessionsForm :sessions="sessions" />

            <DeleteUserForm v-if="$page.props.jetstream.hasAccountDeletionFeatures" />
        </div>
    </Layout>
</template>
