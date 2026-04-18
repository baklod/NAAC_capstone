<script setup>
import { usePage } from "@inertiajs/vue3";
import { PanelLeftClose, PanelLeftOpen, UserRound } from "lucide-vue-next";
import { computed } from "vue";

defineProps({
    title: {
        type: String,
        default: "Dashboard",
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["toggle-sidebar"]);

const page = usePage();

const adminName = computed(
    () =>
        page.props.auth?.user?.name ??
        page.props.auth?.user?.user_name ??
        "Admin",
);
</script>

<template>
    <header class="topbar">
        <div class="topbar__left">
            <button
                type="button"
                class="topbar__toggle"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                @click="$emit('toggle-sidebar')"
            >
                <PanelLeftOpen v-if="collapsed" class="topbar__toggle-icon" />
                <PanelLeftClose v-else class="topbar__toggle-icon" />
            </button>
            <h1 class="topbar__title">{{ title }}</h1>
        </div>

        <div class="topbar__actions">
            <div class="topbar__admin-card">
                <div class="topbar__admin-icon-wrap">
                    <UserRound class="topbar__user-icon" />
                </div>
                <div class="topbar__admin-info">
                    <span class="topbar__admin-label">ADMIN</span>
                    <span class="topbar__admin-name">{{ adminName }}</span>
                </div>
            </div>
        </div>
    </header>
</template>
