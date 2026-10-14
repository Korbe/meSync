<template>
    <!-- Auth pages are always dark (korbitsch.at look), independent of the theme toggle -->
    <div class="dark relative isolate flex min-h-screen flex-col items-center overflow-hidden bg-gray-950 px-4 py-10 text-gray-300 sm:justify-center">
        <!-- Background: grid, glows, vignette -->
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="auth-grid absolute inset-0"></div>
            <div class="absolute -bottom-40 -right-40 h-[620px] w-[620px] rounded-full bg-primary-500/25 blur-[150px]"></div>
            <div class="absolute -top-32 left-1/4 h-[320px] w-[320px] rounded-full bg-accent/10 blur-[140px]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(transparent_45%,rgba(0,0,0,0.55)_100%)]"></div>
        </div>

        <div>
            <slot name="logo" />
        </div>

        <div class="relative mt-8 w-full overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] px-6 py-8 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.8),0_0_80px_-20px_rgba(31,173,174,0.35)] backdrop-blur-xl sm:max-w-md sm:px-8">
            <!-- Teal hairline along the top edge -->
            <div class="pointer-events-none absolute inset-x-8 top-0 h-px bg-linear-to-r from-transparent via-primary-400/70 to-transparent" aria-hidden="true"></div>
            <slot />
        </div>
    </div>
</template>
