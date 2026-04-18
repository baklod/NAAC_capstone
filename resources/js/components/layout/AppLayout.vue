<script setup>
import Sidebar from "./Sidebar.vue";
import Navbar from "./Navbar.vue";
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Toaster } from "vue-sonner";

defineProps({
    title: {
        type: String,
        default: "Dashboard",
    },
});

const sidebarCollapsed = ref(false);
const storageKey = "naac.sidebar.collapsed";
const RESIZE_SYNC_STEPS_MS = [0, 140, 280, 380];
let resizeSyncTimers = [];

const dispatchLayoutResize = () => {
    window.dispatchEvent(new Event("resize"));
};

const stopResizeSync = () => {
    resizeSyncTimers.forEach((timer) => {
        window.clearTimeout(timer);
    });

    resizeSyncTimers = [];
};

const startResizeSync = () => {
    stopResizeSync();

    resizeSyncTimers = RESIZE_SYNC_STEPS_MS.map((delay) =>
        window.setTimeout(() => {
            dispatchLayoutResize();
        }, delay),
    );
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

onMounted(() => {
    const saved = window.localStorage.getItem(storageKey);
    sidebarCollapsed.value = saved === "1";
});

watch(sidebarCollapsed, (value) => {
    window.localStorage.setItem(storageKey, value ? "1" : "0");

    nextTick(() => {
        startResizeSync();
    });
});

onBeforeUnmount(() => {
    stopResizeSync();
});
</script>

<template>
    <div
        class="app-shell"
        :class="{ 'app-shell--collapsed': sidebarCollapsed }"
    >
        <Sidebar />

        <div class="app-shell__main">
            <Navbar
                :title="title"
                :collapsed="sidebarCollapsed"
                @toggle-sidebar="toggleSidebar"
            />
            <main class="content-area">
                <slot />
            </main>
        </div>

        <Toaster position="top-right" rich-colors />
    </div>
</template>
