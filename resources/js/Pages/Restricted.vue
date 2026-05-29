<script setup>
import { Head, Link, usePage } from "@inertiajs/vue3";
import { ShieldAlert } from "lucide-vue-next";
import { computed } from "vue";
import AppLayout from "../components/layout/AppLayout.vue";
import Button from "../components/ui/Button.vue";

const props = defineProps({
    area: {
        type: String,
        default: "This page",
    },
    role: {
        type: String,
        default: "user",
    },
});

const page = usePage();

const displayRole = computed(
    () =>
        props.role ||
        page.props.auth?.user?.role ||
        "user",
);

const formatRole = (role) =>
    role ? role.charAt(0).toUpperCase() + role.slice(1) : "User";
</script>

<template>
    <Head :title="`${area} — Restricted`" />

    <AppLayout :title="area">
        <div class="restricted-page">
            <ShieldAlert class="restricted-page__icon" />
            <p class="restricted-page__eyebrow">Access denied</p>
            <p class="restricted-page__message">
                <strong>{{ area }}</strong> is available to administrators only.
            </p>
            <p class="restricted-page__role">
                You are signed in as
                <strong>{{ formatRole(displayRole) }}</strong>. Contact an
                administrator if you need access.
            </p>
            <Link href="/dashboard">
                <Button>Back to Dashboard</Button>
            </Link>
        </div>
    </AppLayout>
</template>
